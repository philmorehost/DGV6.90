<?php
/**
 * bc-whatsapp.php — DGV6.90 AI Edition
 * WhatsApp High-Alert Gateway
 *
 * Sends WhatsApp messages via the locally running Baileys Node.js bridge.
 * The bridge runs on localhost only and is NEVER exposed publicly.
 *
 * Features:
 * - Sends HIGH priority alerts immediately
 * - MEDIUM priority alerts use email fallback
 * - Built-in anti-spam: minimum delay between non-critical messages
 * - Falls back silently if gateway is offline (no exceptions thrown)
 */

// ─── WhatsApp Gateway Configuration ──────────────────────────
if (!defined('WA_GATEWAY_PORT')) define('WA_GATEWAY_PORT', 3001);        // Internal port for Baileys bridge
if (!defined('WA_GATEWAY_HOST')) define('WA_GATEWAY_HOST', '127.0.0.1'); // NEVER change this to a public IP
if (!defined('WA_GATEWAY_SECRET')) define('WA_GATEWAY_SECRET', '');
if (!defined('WA_MIN_DELAY_SECONDS')) define('WA_MIN_DELAY_SECONDS', 5);
if (!defined('WA_MAX_MSG_LENGTH')) define('WA_MAX_MSG_LENGTH', 1500);

if (function_exists('sendWhatsAppAlert')) return; // Guard against double-include

/**
 * Send a WhatsApp message via the Baileys gateway.
 *
 * @param string $phone      Recipient phone number (Nigerian 11-digit or international)
 * @param string $message    Message text (Baileys supports *bold*, _italic_, etc.)
 * @param string $priority   'high' (immediate) | 'medium' (rate-limited) | 'low' (batch)
 *
 * @return bool True if message was dispatched, false if gateway is unavailable
 */
function sendWhatsAppAlert(string $phone, string $message, string $priority = 'high'): bool
{
    global $connection_server;

    // Normalize phone number
    $phone = bc_sanitize_phone($phone);
    if (empty($phone) || strlen($phone) < 10) return false;

    // Truncate overly long messages
    $message = substr(strip_tags($message), 0, WA_MAX_MSG_LENGTH);

    // Anti-spam: For non-critical messages, enforce rate limiting
    if ($priority !== 'high') {
        $rate_key = 'wa_' . $phone;
        if (bc_is_rate_limited('whatsapp_send', $rate_key, 5, 300)) {
            // Queue to email fallback instead
            _wa_email_fallback($phone, $message);
            return false;
        }
        // Add random 3-8 second delay for bulk/medium priority (anti-spam pattern)
        usleep(rand(3000000, 8000000));
    }

    // Check if gateway is marked online in DB
    if ($connection_server) {
        $q = mysqli_query($connection_server,
            "SELECT status FROM sas_whatsapp_gateway WHERE status='online' LIMIT 1"
        );
        if (!$q || mysqli_num_rows($q) === 0) {
            _wa_email_fallback($phone, $message);
            return false;
        }
    }

    // Build payload
    $payload = json_encode([
        'phone'    => $phone,
        'message'  => $message,
        'priority' => $priority,
        'secret'   => WA_GATEWAY_SECRET,
    ]);

    // Send to Baileys bridge via internal HTTP
    $ch = curl_init('http://' . WA_GATEWAY_HOST . ':' . WA_GATEWAY_PORT . '/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $success = ($http_code === 200 && $response !== false);

    // Log the dispatch
    bc_log_security_event(
        $success ? 'WHATSAPP_SENT' : 'WHATSAPP_FAILED',
        'whatsapp_gateway',
        $phone,
        "Priority: $priority, HTTP: $http_code"
    );

    if (!$success) {
        _wa_email_fallback($phone, $message);
    }

    return $success;
}

/**
 * Send a bulk marketing message to multiple recipients.
 * Enforces a 5-15 second random delay between each message to avoid WhatsApp bans.
 *
 * @param array  $phones    Array of phone numbers
 * @param string $message   The marketing message
 *
 * @return array ['sent' => int, 'failed' => int]
 */
function sendWhatsAppBulk(array $phones, string $message): array
{
    $results = ['sent' => 0, 'failed' => 0];

    foreach ($phones as $phone) {
        $sent = sendWhatsAppAlert($phone, $message, 'medium');
        if ($sent) {
            $results['sent']++;
        } else {
            $results['failed']++;
        }
        // Mandatory delay between bulk messages (5-15 seconds)
        usleep(rand(5000000, 15000000));
    }

    return $results;
}

/**
 * Check if the WhatsApp gateway is online.
 * @return bool
 */
function isWhatsAppGatewayOnline(): bool
{
    $ch = curl_init('http://' . WA_GATEWAY_HOST . ':' . WA_GATEWAY_PORT . '/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code === 200;
}

/**
 * Get the QR code from the Baileys gateway for initial WhatsApp scan.
 * @return string|null Base64-encoded QR code image, or null if not available
 */
function getWhatsAppQRCode(): ?string
{
    $ch = curl_init('http://' . WA_GATEWAY_HOST . ':' . WA_GATEWAY_PORT . '/qr');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) return null;

    $data = json_decode($response, true);
    return $data['qr_base64'] ?? null; // Aligned with index.js response field
}

// ─── Private Helpers ──────────────────────────────────────────

/**
 * Email fallback when WhatsApp gateway is unavailable.
 * Sends the alert via the existing vendor email system.
 */
function _wa_email_fallback(string $phone, string $message): void
{
    global $connection_server;
    if (!$connection_server) return;

    // Get super admin email from options
    $q = mysqli_query($connection_server,
        "SELECT option_value FROM sas_super_admin_options WHERE option_name='admin_email' LIMIT 1"
    );
    $row = $q ? mysqli_fetch_assoc($q) : null;
    $admin_email = $row['option_value'] ?? '';

    if (empty($admin_email)) return;

    $subject = '[WhatsApp Alert Fallback] Alert for: ' . $phone;
    $body    = "WhatsApp gateway was unavailable. This message was intended for $phone:\n\n"
             . strip_tags($message);

    if (function_exists('sendSuperAdminEmail')) {
        sendSuperAdminEmail($admin_email, $subject, $body);
    }
}
