<?php
session_start();
include("../func/bc-spadmin-config.php");
include_once("../func/bc-ai-engine.php");

header('Content-Type: application/json');

// Check Admin Auth
if (!isset($_SESSION["sp_admin_session"])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? 'status';

if ($action === 'queue-progress') {
    $model = $_GET['model'] ?? '';
    if (empty($model)) {
        echo json_encode(['status' => 'error', 'message' => 'No model specified']);
        exit();
    }

    $log_name = 'ollama_pull_' . preg_replace('/[^a-z0-9]/', '_', $model) . '.log';
    $log_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $log_name;

    if (!file_exists($log_path)) {
        echo json_encode(['status' => 'waiting', 'progress' => 0, 'message' => 'Starting...']);
        exit();
    }

    $content = file_get_contents($log_path);
    
    // Parse progress like: "pulling 00366687009b... 100% ▕████████████████▏ 1.6 GB/1.6 GB"
    // or "pulling 00366687009b... 45% ..."
    preg_match_all('/([0-9]+)%/', $content, $matches);
    $progress = 0;
    if (!empty($matches[1])) {
        $progress = end($matches[1]); // Get the last percentage found
    }

    $status = ($progress >= 100) ? 'ready' : 'downloading';
    
    // If ready, verify with AI engine
    if ($progress >= 100) {
        $ai = ai_engine();
        if (!$ai->isModelReady($model)) {
            $status = 'verifying';
        }
    }

    echo json_encode([
        'status' => $status,
        'progress' => (int)$progress,
        'last_log' => substr(trim(strrchr($content, "\n")), 0, 100) ?: 'Processing...'
    ]);
    exit();
}

if ($action === 'status') {
    $ai = ai_engine();
    echo json_encode([
        'ai_up' => $ai->isAiOnline(),
        'provider' => getSuperAdminOption('ai_provider', 'ollama'),
        'whatsapp_up' => isWhatsAppGatewayOnline()
    ]);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
