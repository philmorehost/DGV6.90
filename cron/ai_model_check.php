<?php
/**
 * ai_model_check.php — DGV6.90 AI Edition
 * Cron: Runs every 1 minute
 * Purpose: Detects when a background Ollama model download completes
 *          and sends an email notification to the admin who initiated it.
 *
 * cPanel Cron: * * * * * /usr/local/bin/php /home/[user]/public_html/cron/ai_model_check.php
 */

define('RUNNING_AS_CRON', true);
require_once(__DIR__ . '/../func/bc-config.php');
require_once(__DIR__ . '/../func/bc-ai-engine.php');

if (!$connection_server) exit("No DB connection\n");

$ai       = ai_engine();
$completed = $ai->checkAndUpdateModelStatus();

foreach ($completed as $item) {
    $model = $item['model'];
    $email = $item['notify_email'];

    echo date('Y-m-d H:i:s') . " Model ready: $model\n";

    if (!empty($email) && function_exists('sendSuperAdminEmail')) {
        $subject = "✅ AI Model Ready: $model";
        $body    = "<h3>Your AI model is ready!</h3>"
                 . "<p>The <strong>$model</strong> model has been successfully downloaded and is now available for use.</p>"
                 . "<p>You can assign this model to vendor tiers from the <strong>AI Management Center</strong>.</p>";
        sendSuperAdminEmail($email, $subject, $body);
    }
}

// Cleanup old rate limit records (once per run)
bc_cleanup_rate_limits(3600);

echo date('Y-m-d H:i:s') . " ai_model_check complete. Found " . count($completed) . " newly ready.\n";
