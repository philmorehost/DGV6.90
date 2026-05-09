<?php
/**
 * ai-handler.php — DGV6.90 AI Edition
 * AI Middleware — The PHP Safety Wall
 *
 * This is the single entry point for ALL AI requests from the web frontend.
 * Every call must pass through this file's gate checks before Cloud AI is reached.
 *
 * Accepts: POST with JSON body or form data
 * Returns: JSON response
 *
 * GOLDEN RULES enforced here:
 * 1. Auth gate — must be logged in
 * 2. AI status gate — vendor must have AI enabled
 * 3. Token gate — must have ai_token_balance >= ai_per_tx_cost
 * 4. Prompt firewall — malicious prompts are rejected
 * 5. Token deduction ONLY after successful Cloud AI response
 * 6. Every call is logged to sas_ai_transactions
 */

session_start();
include_once(__DIR__ . "/../func/bc-config.php");

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ─── Only accept POST requests ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// ─── Parse input (supports JSON body or form-data) ───────────
$raw_input = file_get_contents('php://input');
$json_input = json_decode($raw_input, true);
$prompt_raw    = $json_input['prompt'] ?? $_POST['prompt'] ?? '';
$action_type   = $json_input['action'] ?? $_POST['action'] ?? 'chat';
$request_model = $json_input['model'] ?? $_POST['model'] ?? '';

// ─── GATE 1: Authentication ──────────────────────────────────
$user_session   = $_SESSION['user_session'] ?? '';
$admin_session  = $_SESSION['admin_session'] ?? '';
$spadmin_session = $_SESSION['spadmin_session'] ?? '';

if (empty($user_session) && empty($admin_session) && empty($spadmin_session) || !isset($connection_server)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'NOT_LOGGED_IN', 'message' => 'Please log in to use AI features.']);
    exit;
}

$context = $_GET['context'] ?? 'user';
$username = $user_session;
if ($context === 'admin') $username = $admin_session;
if ($context === 'spadmin') $username = $spadmin_session;

$vendor_id = resolveVendorID();

if ($vendor_id <= 0 && $context !== 'spadmin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'VENDOR_ERROR', 'message' => 'Vendor not found.']);
    exit;
}

// ─── GATE 2: Rate limiting (per actor, 20 AI requests/minute) ─
$is_admin_actor = (($context === 'admin' || $context === 'spadmin') && (!empty($admin_session) || !empty($spadmin_session)));
$rate_key = $is_admin_actor ? "ai_adm_{$vendor_id}_{$username}" : "ai_usr_{$vendor_id}_{$username}";

// DEBUG
// file_put_contents('ai_debug.log', "VID: $vendor_id | Admin: $admin_session | User: $user_session | IsAdminActor: ".($is_admin_actor?'Y':'N')." | Username: $username\n", FILE_APPEND);

if (bc_is_rate_limited('ai_request', $rate_key, 20, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'code' => 'RATE_LIMITED', 'message' => 'Too many AI requests. Please wait a moment.']);
    exit;
}

// ─── Load actor and vendor details ────────────────────────────
$esc_name = mysqli_real_escape_string($connection_server, $username);
$safe_vid = (int)$vendor_id;

if ($is_admin_actor) {
    if ($context === 'spadmin') {
        // Super Admin uses system settings (vendor 0 or similar, but we check if they are authorized)
        $q = mysqli_query($connection_server, "SELECT 1 as id, 'Super' as firstname, 1 as ai_status, 999999 as ai_token_balance");
    } else {
        // Fetch from sas_vendors
        $q = mysqli_query($connection_server, "SELECT id, firstname, ai_status, ai_token_balance FROM sas_vendors WHERE id='$safe_vid' AND email='$esc_name' LIMIT 1");
    }
} else {
    // Fetch from sas_users
    $q = mysqli_query($connection_server, "SELECT id, firstname, ai_status, ai_token_balance FROM sas_users WHERE vendor_id='$safe_vid' AND username='$esc_name' AND status=1 LIMIT 1");
}

$actor = $q ? mysqli_fetch_assoc($q) : null;

if (!$actor) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'USER_NOT_FOUND', 'message' => 'Account not found.']);
    exit;
}

// ─── GATE 3: AI Status Check ─────────────────────────────────
if ((int)$actor['ai_status'] !== 1) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'AI_DISABLED',
        'message' => 'AI features are not enabled. Visit AI Settings to get started.',
    ]);
    exit;
}

// ─── Load vendor AI config ────────────────────────────────────
$vendor_q = mysqli_query($connection_server,
    "SELECT ai_per_tx_cost, ai_model_assigned, ai_price_per_1k_tokens FROM sas_vendors WHERE id='$safe_vid' LIMIT 1"
);
$vendor_ai = $vendor_q ? mysqli_fetch_assoc($vendor_q) : null;
$tokens_per_call = (int)($vendor_ai['ai_per_tx_cost'] ?? 5);
$assigned_model  = $vendor_ai['ai_model_assigned'] ?? 'gemini-1.5-flash';

// Use requested model only if it matches the assigned model (prevent tier-hopping)
$model_to_use = $assigned_model;

// ─── GATE 4: Token Balance Check ─────────────────────────────
$current_tokens = (int)($actor['ai_token_balance'] ?? 0);
if ($current_tokens < $tokens_per_call) {
    http_response_code(402);
    echo json_encode([
        'status'         => 'error',
        'code'           => 'INSUFFICIENT_TOKENS',
        'message'        => "Insufficient AI tokens. You have $current_tokens tokens but need $tokens_per_call.",
        'current_tokens' => $current_tokens,
        'cost'           => $tokens_per_call,
    ]);
    exit;
}

// ─── GATE 5: Prompt Firewall ──────────────────────────────────
include_once(__DIR__ . "/../func/bc-ai-engine.php");

if (empty($prompt_raw)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'code' => 'EMPTY_PROMPT', 'message' => 'Please enter a question.']);
    exit;
}

$context_data = $json_input['context'] ?? [];
$safe_prompt = bc_firewall_prompt($prompt_raw, false, $context_data);

if ($safe_prompt === false) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'PROMPT_REJECTED',
        'message' => 'Your request contains content that cannot be processed. Please ask a VTU business-related question.',
    ]);
    exit;
}

// ─── CALL CLOUD AI ──────────────────────────────────────────
$ai = ai_engine();

// Ensure the model is compatible with the active provider
if (!$ai->isModelCompatible($model_to_use)) {
    $model_to_use = $ai->getDefaultModel();
}

// Action routing
switch ($action_type) {
    case 'voice_vtu':
        // 1. Parse Voice Intent
        $intent = $ai->parseVtuIntent($prompt_raw, $model_to_use);
        if (!$intent || $intent['confidence'] < 60) {
             echo json_encode(['status' => 'error', 'code' => 'LOW_CONFIDENCE', 'message' => 'I could not understand that command clearly. Please speak slowly and mention the network, amount, and number.']);
             exit;
        }

        // 2. Check Authorization for Autonomous Action
        if (($actor['ai_voice_status'] ?? 0) != 2) {
             echo json_encode(['status' => 'error', 'code' => 'NOT_APPROVED', 'message' => 'Your account is not approved for Zero-Click Autonomous Voice transactions. Please apply in Account Settings.']);
             exit;
        }

        // 3. Prepare for Transaction Execution
        // We simulate the environment for the existing service handlers
        $purchase_method = "API"; 
        $get_api_post_info = [
            'network'      => $intent['network'],
            'phone_number' => $intent['phone'],
            'amount'       => $intent['amount'],
            'id'           => $intent['id'] ?? '' // for data/cable
        ];

        // Map service name to file
        $service_map = [
            'airtime'     => 'func/airtime.php',
            'data'        => 'func/data.php',
            'electricity' => 'func/electric.php',
            'cable'       => 'func/cable.php',
            'betting'     => 'func/betting.php'
        ];

        $handler_file = $service_map[strtolower($intent['service'])] ?? '';
        if (empty($handler_file) || !file_exists(__DIR__ . "/" . $handler_file)) {
             echo json_encode(['status' => 'error', 'message' => 'That service is not yet supported for voice commands.']);
             exit;
        }

        // Execute Transaction
        include_once(__DIR__ . "/" . $handler_file);
        
        // The handlers set $json_response_encode
        $res = json_decode($json_response_encode ?? '{}', true);
        
        if (($res['status'] ?? '') === 'success') {
            $ai_result = [
                'status'   => 'success',
                'response' => "✅ Autonomous Order Placed Successfully!\nType: " . ucwords($intent['service']) . "\nDest: " . $intent['phone'] . "\nAmt: ₦" . number_format($intent['amount']) . "\nRef: " . ($res['ref'] ?? 'N/A'),
                'model'    => $model_to_use,
                'duration_ms' => 0 // will be calculated
            ];
            // Override standard token fee with the autonomous fee
            $tokens_per_call = (int)($vendor_ai['ai_voice_fee_tokens'] ?? 100);
        } else {
            // Transaction failed - don't charge AI tokens (Refund policy)
            echo json_encode([
                'status'  => 'error',
                'code'    => 'TRANSACTION_FAILED',
                'message' => "❌ Voice Transaction Failed: " . ($res['desc'] ?? 'Unknown Error') . ". No AI tokens were charged.",
                'intent'  => $intent
            ]);
            exit;
        }
        break;
    case 'marketing':
        $ai_result = $ai->chat($model_to_use, $safe_prompt, ['temperature' => 0.85]);
        break;
    case 'analysis':
        $ai_result = $ai->chat($model_to_use, $safe_prompt, ['temperature' => 0.3]);
        break;
    default:
        $ai_result = $ai->chat($model_to_use, $safe_prompt);
        break;
}
$ai_duration = $ai_result['duration_ms'] ?? 0;

// ─── SUCCESS: Deduct tokens, log, respond ────────────────────
if ($ai_result['status'] === 'success') {
    // Deduct tokens ONLY on success (pay-per-success billing)
    $new_tokens = max(0, $current_tokens - $tokens_per_call);
    $actor_id   = (int)$actor['id'];

    if ($context === 'spadmin') {
        // Super admin doesn't get debited
    } elseif ($context === 'admin') {
        mysqli_query($connection_server, "UPDATE sas_vendors SET ai_token_balance='$new_tokens' WHERE id='$actor_id'");
    } else {
        mysqli_query($connection_server, "UPDATE sas_users SET ai_token_balance='$new_tokens', ai_requests_used=ai_requests_used+1 WHERE id='$actor_id'");
    }

    // Log the AI transaction (store only a hash of the prompt for privacy)
    $prompt_hash = hash('sha256', $prompt_raw);
    $esc_action  = mysqli_real_escape_string($connection_server, substr($action_type, 0, 50));
    $esc_model   = mysqli_real_escape_string($connection_server, $ai_result['model']);
    $esc_hash    = mysqli_real_escape_string($connection_server, $prompt_hash);
    $cost_naira  = ($tokens_per_call / 1000) * (float)($vendor_ai['ai_price_per_1k_tokens'] ?? 100);

    mysqli_query($connection_server,
        "INSERT INTO sas_ai_transactions
         (vendor_id, username, action_type, model_used, tokens_burned, duration_ms, cost_naira, prompt_hash, status)
         VALUES ('$safe_vid', '" . mysqli_real_escape_string($connection_server, $username) . "', '$esc_action', '$esc_model', '$tokens_per_call', '$ai_duration', '$cost_naira', '$esc_hash', 'success')"
    );

    // Return response to frontend
    echo json_encode([
        'status'           => 'success',
        'response'         => $ai_result['response'],
        'model_used'       => $ai_result['model'],
        'duration_ms'      => $ai_result['duration_ms'],
        'tokens_used'      => $tokens_per_call,
        'tokens_remaining' => $new_tokens,
    ]);

} else {
    // Log failed call (no token deduction on failure)
    $prompt_hash = hash('sha256', $prompt_raw);
    $esc_hash    = mysqli_real_escape_string($connection_server, $prompt_hash);
    @mysqli_query($connection_server,
        "INSERT INTO sas_ai_transactions
         (vendor_id, username, action_type, model_used, tokens_burned, cost_naira, prompt_hash, status)
         VALUES ('$safe_vid', '" . mysqli_real_escape_string($connection_server, $username) . "', 'failed_call', '', 0, 0, '$esc_hash', 'failed')"
    );

    http_response_code(503);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'AI_UNAVAILABLE',
        'message' => 'The AI engine is temporarily unavailable. Please try again shortly. No tokens were deducted.',
    ]);
}
