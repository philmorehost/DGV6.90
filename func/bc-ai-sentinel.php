<?php
/**
 * bc-ai-sentinel.php — DGV6.90 AI Edition
 * AI Security Sentinel
 *
 * An adaptive trust-based transaction monitoring layer that wraps
 * (but NEVER replaces) the existing hard purchase limits.
 *
 * ARCHITECTURE:
 * - The existing sas_daily_purchase_limit hard limits are PRESERVED
 * - Sentinel adds a DYNAMIC LAYER ON TOP: Trust Score + Velocity checks
 * - Sentinel can only BLOCK or FLAG — it cannot INCREASE beyond hard limits
 * - VIP whitelist allows temporary overrides for known bulk buyers
 *
 * GOLDEN RULES:
 * - This file is purely advisory. It returns decisions; PHP acts on them.
 * - No financial operations are performed here.
 * - Zero performance cost when vendor has AI disabled.
 */

if (class_exists('AISentinel')) return; // Guard against double-include

class AISentinel
{
    private int    $vendor_id;
    private string $username;

    // Trust score thresholds
    const SCORE_HIGH   = 75;  // Trusted — proceed normally
    const SCORE_MEDIUM = 40;  // Caution — light monitoring
    const SCORE_LOW    = 20;  // Risky — flag for vendor approval

    // Velocity check window: how many transactions in how many minutes
    const VELOCITY_WINDOW_MINUTES = 5;
    const VELOCITY_THRESHOLD      = 10; // 10+ transactions in 5 min = suspicious

    // Bot pattern: same amount to same recipient repeatedly
    const BOT_REPEAT_THRESHOLD = 5; // Same phone+amount 5+ times in 30 min = bot

    public function __construct(int $vendor_id, string $username)
    {
        $this->vendor_id = $vendor_id;
        $this->username  = $username;
    }

    // ─────────────────────────────────────────────────────────
    // 1. PRIMARY EVALUATION — Called before any service page
    // ─────────────────────────────────────────────────────────

    /**
     * Evaluates whether a transaction should proceed, be flagged, or be blocked.
     *
     * Returns: 'PROCEED', 'FLAG_FOR_APPROVAL', or 'BLOCK'
     *
     * @param string $service   'airtime', 'data', 'electricity', 'cable', 'betting'
     * @param float  $amount    Transaction amount in Naira
     * @param string $recipient The phone number or account being recharged
     */
    public function evaluate(string $service, float $amount, string $recipient = ''): string
    {
        global $connection_server;
        if (!$connection_server) return 'PROCEED'; // Safe default on DB failure

        // Check if user is on VIP whitelist — always proceed
        if ($this->isVipWhitelisted($recipient)) {
            return 'PROCEED';
        }

        // Get current trust score
        $score = $this->getTrustScore();

        // Check for bot patterns
        if (!empty($recipient) && $this->checkBotPattern($recipient, $amount)) {
            $this->flagForVendorApproval('bot_pattern', $service, $amount, $recipient);
            bc_log_security_event('SENTINEL_BOT_DETECTED', $service, $this->username,
                "Amount: $amount, Recipient: $recipient, Score: $score");
            return 'BLOCK';
        }

        // Check transaction velocity
        if ($this->checkVelocity()) {
            $this->flagForVendorApproval('high_velocity', $service, $amount, $recipient);
            bc_log_security_event('SENTINEL_HIGH_VELOCITY', $service, $this->username,
                "Score: $score, Amount: $amount");
            return 'FLAG_FOR_APPROVAL';
        }

        // Score-based decisions
        if ($score >= self::SCORE_HIGH) {
            return 'PROCEED';
        }

        if ($score >= self::SCORE_MEDIUM) {
            // Medium trust: flag large transactions
            $large_thresholds = [
                'airtime'     => 5000,
                'data'        => 10000,
                'electricity' => 20000,
                'cable'       => 15000,
                'betting'     => 10000,
            ];
            $threshold = $large_thresholds[$service] ?? 10000;
            if ($amount > $threshold) {
                $this->flagForVendorApproval('large_amount_medium_trust', $service, $amount, $recipient);
                return 'FLAG_FOR_APPROVAL';
            }
            return 'PROCEED';
        }

        // Low trust score — flag everything above minimum
        if ($score < self::SCORE_LOW) {
            $this->flagForVendorApproval('low_trust_score', $service, $amount, $recipient);
            return 'FLAG_FOR_APPROVAL';
        }

        return 'PROCEED';
    }

    // ─────────────────────────────────────────────────────────
    // 2. TRUST SCORE COMPUTATION
    // ─────────────────────────────────────────────────────────

    /**
     * Returns the current trust score for this user (0-100).
     * Uses cached DB value if computed within last 6 hours.
     */
    public function getTrustScore(): float
    {
        global $connection_server;
        if (!$connection_server) return 50.0;

        $esc_user = mysqli_real_escape_string($connection_server, $this->username);
        $safe_vid = (int)$this->vendor_id;

        $q = mysqli_query($connection_server,
            "SELECT trust_score, last_trust_audit FROM sas_users
             WHERE vendor_id='$safe_vid' AND username='$esc_user' LIMIT 1"
        );
        $user = $q ? mysqli_fetch_assoc($q) : null;
        if (!$user) return 50.0;

        // Recompute if score is stale (older than 6 hours)
        $last_audit = $user['last_trust_audit'] ? strtotime($user['last_trust_audit']) : 0;
        if ((time() - $last_audit) > 21600) {
            return $this->computeAndStoreTrustScore($user);
        }

        return (float)($user['trust_score'] ?? 50.0);
    }

    /**
     * Computes a fresh trust score based on transaction history.
     * Score components:
     *   - Success rate (40 points max)
     *   - Account age (20 points max)
     *   - Balance consistency (20 points max)
     *   - KYC status (20 points max)
     */
    private function computeAndStoreTrustScore(array $user): float
    {
        global $connection_server;
        $safe_vid  = (int)$this->vendor_id;
        $esc_user  = mysqli_real_escape_string($connection_server, $this->username);

        $score = 0.0;
        $reasons = [];

        // 1. Transaction success rate (40 pts max)
        $tx_q = mysqli_query($connection_server,
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) as successful
             FROM sas_transactions
             WHERE vendor_id='$safe_vid' AND username='$esc_user'
             AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $tx = $tx_q ? mysqli_fetch_assoc($tx_q) : null;
        if ($tx && $tx['total'] > 0) {
            $rate = ($tx['successful'] / $tx['total']) * 40;
            $score += $rate;
            $reasons[] = "tx_success_rate:" . round($rate, 1);
        } else {
            $score += 20; // New user, give benefit of the doubt
            $reasons[] = "new_user_default:20";
        }

        // 2. Account age (20 pts max)
        $reg_q = mysqli_query($connection_server,
            "SELECT reg_date FROM sas_users WHERE vendor_id='$safe_vid' AND username='$esc_user' LIMIT 1"
        );
        $reg = $reg_q ? mysqli_fetch_assoc($reg_q) : null;
        if ($reg) {
            $days_old = (time() - strtotime($reg['reg_date'])) / 86400;
            $age_score = min(20, ($days_old / 90) * 20); // Full 20 pts at 90 days
            $score += $age_score;
            $reasons[] = "account_age_days:" . round($days_old) . "->pts:" . round($age_score, 1);
        }

        // 3. KYC status (20 pts max)
        $kyc_score = 0;
        if (!empty($user['bvn']) || !empty($user['nin'])) $kyc_score += 10;
        if (($user['kyc_status'] ?? 0) == 1) $kyc_score += 10;
        $score += $kyc_score;
        $reasons[] = "kyc:$kyc_score";

        // 4. Balance consistency — no sudden large spikes (20 pts max)
        $bal_q = mysqli_query($connection_server,
            "SELECT AVG(ABS(balance_after - balance_before)) as avg_txn
             FROM sas_transactions
             WHERE vendor_id='$safe_vid' AND username='$esc_user' AND status=1
             AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $bal = $bal_q ? mysqli_fetch_assoc($bal_q) : null;
        if ($bal && $bal['avg_txn'] !== null) {
            $avg_txn = (float)$bal['avg_txn'];
            // Low average transactions = consistent small usage = trustworthy
            $consistency_score = $avg_txn < 1000 ? 20 : ($avg_txn < 5000 ? 15 : ($avg_txn < 20000 ? 10 : 5));
            $score += $consistency_score;
            $reasons[] = "consistency:$consistency_score";
        } else {
            $score += 20; // No recent transactions, trust default
        }

        $final_score = min(100, max(0, round($score, 2)));
        $reason_str  = implode(',', $reasons);

        // Store computed score
        $esc_reason = mysqli_real_escape_string($connection_server, substr($reason_str, 0, 499));
        mysqli_query($connection_server,
            "UPDATE sas_users SET trust_score='$final_score', last_trust_audit=NOW()
             WHERE vendor_id='$safe_vid' AND username='$esc_user'"
        );
        // Archive in history table
        mysqli_query($connection_server,
            "INSERT INTO sas_ai_trust_scores (vendor_id, username, score, reason)
             VALUES ('$safe_vid', '$esc_user', '$final_score', '$esc_reason')"
        );

        return $final_score;
    }

    // ─────────────────────────────────────────────────────────
    // 3. BOT PATTERN DETECTION
    // ─────────────────────────────────────────────────────────

    /**
     * Detects repetitive bot-like patterns: same recipient + same amount > threshold in 30 min.
     */
    public function checkBotPattern(string $recipient, float $amount): bool
    {
        global $connection_server;
        if (!$connection_server) return false;

        $esc_user      = mysqli_real_escape_string($connection_server, $this->username);
        $esc_recipient = mysqli_real_escape_string($connection_server, $recipient);
        $safe_vid      = (int)$this->vendor_id;
        $safe_amount   = (float)$amount;

        // Count how many times this exact amount was sent to this recipient in last 30 min
        // We check the description field for the recipient (as VTU transactions embed phone in description)
        $q = mysqli_query($connection_server,
            "SELECT COUNT(*) as cnt FROM sas_transactions
             WHERE vendor_id='$safe_vid' AND username='$esc_user'
             AND description LIKE '%$esc_recipient%'
             AND discounted_amount='$safe_amount'
             AND date >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
        $row = $q ? mysqli_fetch_assoc($q) : null;
        return ($row && (int)$row['cnt'] >= self::BOT_REPEAT_THRESHOLD);
    }

    // ─────────────────────────────────────────────────────────
    // 4. VELOCITY CHECK
    // ─────────────────────────────────────────────────────────

    /**
     * Checks if the user is making too many transactions too quickly.
     */
    public function checkVelocity(): bool
    {
        global $connection_server;
        if (!$connection_server) return false;

        $esc_user = mysqli_real_escape_string($connection_server, $this->username);
        $safe_vid = (int)$this->vendor_id;
        $window   = self::VELOCITY_WINDOW_MINUTES;

        $q = mysqli_query($connection_server,
            "SELECT COUNT(*) as cnt FROM sas_transactions
             WHERE vendor_id='$safe_vid' AND username='$esc_user'
             AND date >= DATE_SUB(NOW(), INTERVAL $window MINUTE)"
        );
        $row = $q ? mysqli_fetch_assoc($q) : null;
        return ($row && (int)$row['cnt'] >= self::VELOCITY_THRESHOLD);
    }

    // ─────────────────────────────────────────────────────────
    // 5. VIP WHITELIST
    // ─────────────────────────────────────────────────────────

    /**
     * Checks if the target phone/account is on the VIP whitelist for this vendor.
     * VIP whitelisted customers bypass Sentinel checks.
     */
    public function isVipWhitelisted(string $product_id): bool
    {
        global $connection_server;
        if (!$connection_server || empty($product_id)) return false;

        $esc_pid  = mysqli_real_escape_string($connection_server, $product_id);
        $safe_vid = (int)$this->vendor_id;

        $q = mysqli_query($connection_server,
            "SELECT id, override_expiry FROM sas_customer_whitelist
             WHERE vendor_id='$safe_vid' AND product_id='$esc_pid' AND is_whitelisted=1
             LIMIT 1"
        );
        $row = $q ? mysqli_fetch_assoc($q) : null;

        if (!$row) return false;

        // Check if override has expired
        if (!empty($row['override_expiry']) && strtotime($row['override_expiry']) < time()) {
            // Auto-expire: remove whitelist entry
            mysqli_query($connection_server,
                "UPDATE sas_customer_whitelist SET is_whitelisted=0
                 WHERE vendor_id='$safe_vid' AND product_id='$esc_pid'"
            );
            return false;
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────
    // 6. FLAG FOR VENDOR APPROVAL
    // ─────────────────────────────────────────────────────────

    /**
     * Logs a flagged transaction to the audit log and sends WhatsApp alert to vendor.
     */
    public function flagForVendorApproval(string $reason, string $service, float $amount, string $recipient): void
    {
        global $connection_server;
        $safe_vid = (int)$this->vendor_id;

        bc_log_security_event(
            'SENTINEL_FLAGGED',
            "service:{$service}",
            "{$this->vendor_id}:{$this->username}",
            "reason={$reason}, amount={$amount}, recipient={$recipient}"
        );

        // Get vendor phone number for WhatsApp alert
        if ($connection_server) {
            $q = mysqli_query($connection_server,
                "SELECT phone_number, firstname FROM sas_vendors WHERE id='$safe_vid' LIMIT 1"
            );
            $vendor = $q ? mysqli_fetch_assoc($q) : null;
            if ($vendor) {
                $message = "🚨 *AI Security Alert*\n\n"
                         . "Customer *{$this->username}* has a flagged transaction:\n"
                         . "• Service: " . strtoupper($service) . "\n"
                         . "• Amount: ₦" . number_format($amount, 2) . "\n"
                         . "• Recipient: $recipient\n"
                         . "• Reason: $reason\n\n"
                         . "Login to your dashboard to review this transaction.";

                // Use WhatsApp gateway if available
                if (function_exists('sendWhatsAppAlert')) {
                    sendWhatsAppAlert($vendor['phone_number'], $message, 'high');
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────
// Convenience function — used as the single hook in service pages
// Usage:
//   $sentinel_result = ai_sentinel_evaluate($username, $vendor_id, 'airtime', $amount, $phone);
//   if ($sentinel_result === 'BLOCK') { /* show error */ exit; }
// ─────────────────────────────────────────────────────────────
function ai_sentinel_evaluate(string $username, int $vendor_id, string $service, float $amount, string $recipient = ''): string
{
    global $connection_server;
    if (!$connection_server) return 'PROCEED';

    // Gate: Only active if AI is globally enabled AND user has AI enabled
    $esc_user = mysqli_real_escape_string($connection_server, $username);
    $safe_vid = (int)$vendor_id;

    $ai_check = mysqli_query($connection_server,
        "SELECT ai_status FROM sas_users WHERE vendor_id='$safe_vid' AND username='$esc_user' LIMIT 1"
    );
    $ai_row = $ai_check ? mysqli_fetch_assoc($ai_check) : null;

    // If AI is off for this user, pass through to existing limit system
    if (!$ai_row || (int)$ai_row['ai_status'] !== 1) {
        return 'PROCEED';
    }

    $sentinel = new AISentinel($vendor_id, $username);
    return $sentinel->evaluate($service, $amount, $recipient);
}
