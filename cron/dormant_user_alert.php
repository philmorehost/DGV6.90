<?php
/**
 * Dormant User Alert Cron — DGV6.90 AI Edition
 * Runs: Daily at 10:00 AM
 * Cron: 0 10 * * * php /path/to/cron/dormant_user_alert.php
 *
 * Finds users who have NOT transacted in the last 14 days
 * and sends a personalized WhatsApp re-engagement message via bc-whatsapp.php
 */

define('CRON_CLI', true);
require_once __DIR__ . '/../func/bc-connect.php';
require_once __DIR__ . '/../func/bc-whatsapp.php';
require_once __DIR__ . '/../func/bc-ai-engine.php';

$start = microtime(true);
$log   = [];
$sent  = 0;
$skip  = 0;
$errors = 0;

echo "[DORMANT-ALERT] " . date('Y-m-d H:i:s') . " — Starting dormant user sweep\n";

// ── Check AI engine availability (optional — fallback to static message) ──────
$ai_available = false;
try {
    $engine = BcAiEngine::getInstance();
    $test   = $engine->chat("Reply with the single word: ready");
    $ai_available = (stripos($test, 'ready') !== false);
} catch (Exception $e) { /* no AI, use static message */ }

// ── Query all vendors that have the feature enabled ────────────────────────────
$vendors_q = mysqli_query($connection_server,
    "SELECT id, firstname, site_name, whatsapp_number FROM sas_vendors WHERE status=1");

while ($vendor = mysqli_fetch_assoc($vendors_q)) {
    $vid   = (int)$vendor['id'];
    $vname = $vendor['site_name'] ?? $vendor['firstname'] ?? 'VTU Platform';

    // ── Find dormant users (no successful transaction in 14 days) ─────────────
    $dormant_q = mysqli_query($connection_server,
        "SELECT u.firstname, u.phone_number, u.username,
                MAX(t.date) as last_tx,
                SUM(CASE WHEN t.status=1 THEN 1 ELSE 0 END) as total_tx
         FROM sas_users u
         LEFT JOIN sas_transactions t ON t.vendor_id=u.vendor_id AND t.username=u.username AND t.status=1
         WHERE u.vendor_id='$vid' AND u.status=1 AND u.phone_number != ''
         HAVING (last_tx IS NULL OR last_tx < DATE_SUB(NOW(), INTERVAL 14 DAY))
            AND total_tx > 0
         LIMIT 50"
    );

    if (!$dormant_q || mysqli_num_rows($dormant_q) === 0) { continue; }

    while ($user = mysqli_fetch_assoc($dormant_q)) {
        $name  = ucfirst($user['firstname'] ?? 'Valued Customer');
        $phone = preg_replace('/[^0-9]/', '', $user['phone_number'] ?? '');
        if (strlen($phone) < 10) { $skip++; continue; }

        // ── Generate personalized message ──────────────────────────────────────
        if ($ai_available) {
            $prompt = "Write a short, friendly WhatsApp message (max 3 sentences) to re-engage a VTU app user named $name who hasn't used the platform in 2 weeks. The platform is called $vname. Mention airtime, data, or bills. Don't use hashtags or emojis excessively.";
            $message = $engine->chat($prompt);
            if (empty($message)) {
                $message = static_dormant_message($name, $vname);
            }
        } else {
            $message = static_dormant_message($name, $vname);
        }

        // ── Send via WhatsApp gateway ──────────────────────────────────────────
        $result = bc_send_whatsapp($phone, $message, 'dormant_re_engagement');
        if ($result) {
            $sent++;
            echo "  ✅ Sent to $name ($phone)\n";
        } else {
            $errors++;
            echo "  ❌ Failed for $name ($phone)\n";
        }

        // Throttle: 3s between messages to avoid WhatsApp bans
        sleep(3);
    }
}

$elapsed = round(microtime(true) - $start, 2);
echo "\n[DORMANT-ALERT] Done. Sent: $sent | Skipped: $skip | Errors: $errors | Time: {$elapsed}s\n";

function static_dormant_message(string $name, string $platform): string {
    $messages = [
        "Hi $name! 👋 We've missed you on $platform. Your wallet is ready — recharge airtime, buy data, or pay your bills in seconds. Come back today! 🔋",
        "Hello $name! It's been a while since we saw you on $platform. Quick reminder: buy MTN, Airtel, Glo or 9mobile airtime/data at great rates. Tap in! 📱",
        "Hey $name, your $platform account is waiting for you! Pay your electricity, cable TV, or data bills fast and easy. Log in today! ⚡",
    ];
    return $messages[array_rand($messages)];
}
