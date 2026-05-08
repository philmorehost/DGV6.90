<?php
/**
 * AI Daily Business Briefing Cron — DGV6.90 AI Edition
 * Runs: Daily at 7:00 AM
 * Cron: 0 7 * * * php /path/to/cron/ai_daily_briefing.php
 *
 * Compiles yesterday's transaction stats per vendor,
 * sends to Ollama for a motivating summary, then delivers
 * as a WhatsApp message to the vendor's registered number.
 */

define('CRON_CLI', true);
require_once __DIR__ . '/../func/bc-connect.php';
require_once __DIR__ . '/../func/bc-whatsapp.php';
require_once __DIR__ . '/../func/bc-ai-engine.php';

$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$ai_up     = false;
$sent      = 0;

echo "[DAILY-BRIEFING] " . date('Y-m-d H:i:s') . " — Generating vendor briefings\n";

// Check AI
try {
    $engine = BcAiEngine::getInstance();
    $test   = $engine->chat("Say: ready");
    $ai_up  = !empty($test);
} catch (Exception $e) {}

if (!$ai_up) {
    echo "[DAILY-BRIEFING] AI engine offline — skipping (briefings require AI)\n";
    exit(0);
}

// Query all active vendors with a WhatsApp number
$vendors_q = mysqli_query($connection_server,
    "SELECT id, firstname, lastname, whatsapp_number, site_name FROM sas_vendors WHERE status=1 AND whatsapp_number != ''");

while ($vendor = mysqli_fetch_assoc($vendors_q)) {
    $vid    = (int)$vendor['id'];
    $vname  = $vendor['site_name'] ?? ($vendor['firstname'] . ' ' . $vendor['lastname']);
    $phone  = preg_replace('/[^0-9]/', '', $vendor['whatsapp_number']);

    if (strlen($phone) < 10) continue;

    // ── Pull yesterday's stats ─────────────────────────────────────────────────
    $stats_q = mysqli_query($connection_server,
        "SELECT
            COUNT(*) as total_tx,
            SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status=3 THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status=1 THEN discounted_amount ELSE 0 END) as revenue,
            COUNT(DISTINCT username) as unique_users
         WHERE vendor_id='$vid' AND DATE(date)='$yesterday'"
    );
    $stats = mysqli_fetch_assoc($stats_q);
    if (!$stats || $stats['total_tx'] == 0) {
        echo "  ⏭ Vendor $vid ($vname): No transactions yesterday\n";
        continue;
    }

    // Top services
    $top_q = mysqli_query($connection_server,
        "SELECT type_alternative, COUNT(*) cnt, SUM(discounted_amount) rev
         FROM sas_transactions
         WHERE vendor_id='$vid' AND DATE(date)='$yesterday' AND status=1
         GROUP BY type_alternative ORDER BY cnt DESC LIMIT 3");
    $top_services = [];
    while ($t = mysqli_fetch_assoc($top_q)) {
        $top_services[] = "{$t['type_alternative']}: {$t['cnt']} tx (₦".number_format($t['rev'],2).")";
    }

    $rev_fmt    = '₦' . number_format($stats['revenue'], 2);
    $top_str    = implode(', ', $top_services);
    $success_pct = $stats['total_tx'] > 0 ? round(($stats['successful']/$stats['total_tx'])*100) : 0;

    // ── Build AI prompt ────────────────────────────────────────────────────────
    $prompt = "You are a VTU business analyst. Write an encouraging WhatsApp briefing (max 5 sentences) for a Nigerian VTU business owner named $vname based on yesterday's performance:\n"
            . "- Total Transactions: {$stats['total_tx']} ({$stats['successful']} successful, {$stats['failed']} failed)\n"
            . "- Revenue: $rev_fmt | Success Rate: $success_pct%\n"
            . "- Unique Customers: {$stats['unique_users']}\n"
            . "- Top Services: $top_str\n"
            . "Start with '📊 *Daily Business Report - $yesterday*'. Include one actionable tip based on the data.";

    $message = $engine->chat($prompt);
    if (empty($message)) {
        $message = "📊 *Daily Business Report - $yesterday*\n\nHi $vname! Yesterday: {$stats['successful']} successful transactions, revenue $rev_fmt, $success_pct% success rate. Keep growing! 🚀";
    }

    // ── Send WhatsApp briefing ─────────────────────────────────────────────────
    $result = bc_send_whatsapp($phone, $message, 'daily_briefing');
    if ($result) {
        $sent++;
        echo "  ✅ Briefing sent to $vname ($phone)\n";
    } else {
        echo "  ❌ Failed for $vname ($phone)\n";
    }

    sleep(4); // Rate limit between vendor messages
}

echo "\n[DAILY-BRIEFING] Done. Briefings sent: $sent\n";
