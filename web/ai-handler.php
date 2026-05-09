<?php
/**
 * ai-handler.php — DGV6.90 AI Edition
 * AI Middleware — The PHP Safety Wall
 *
 * This is the single entry point for ALL AI requests from the web frontend.
 * Every call must pass through this file's gate checks before Ollama is reached.
 *
 * Accepts: POST with JSON body or form data
 * Returns: JSON response
 *
 * GOLDEN RULES enforced here:
 * 1. Auth gate — must be logged in
 * 2. AI status gate — vendor must have AI enabled
 * 3. Token gate — must have ai_token_balance >= ai_per_tx_cost
 * 4. Prompt firewall — malicious prompts are rejected
 * 5. Token deduction ONLY after successful Ollama response
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
if (empty($_SESSION['user_session']) || !isset($connection_server)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'NOT_LOGGED_IN', 'message' => 'Please log in to use AI features.']);
    exit;
}

$username  = $_SESSION['user_session'];
$vendor_id = resolveVendorID();

if ($vendor_id <= 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'VENDOR_ERROR', 'message' => 'Vendor not found.']);
    exit;
}

// ─── GATE 2: Rate limiting (per user, 20 AI requests/minute) ─
$user_rate_key = "ai_{$vendor_id}_{$username}";
if (bc_is_rate_limited('ai_request', $user_rate_key, 20, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'code' => 'RATE_LIMITED', 'message' => 'Too many AI requests. Please wait a moment.']);
    exit;
}

// ─── Load user and vendor details ────────────────────────────
$esc_user = mysqli_real_escape_string($connection_server, $username);
$safe_vid = (int)$vendor_id;

$user_q = mysqli_query($connection_server,
    "SELECT id, firstname, ai_status, ai_token_balance, ai_requests_used, ai_quota_limit, trust_score
     FROM sas_users
     WHERE vendor_id='$safe_vid' AND username='$esc_user' AND status=1
     LIMIT 1"
);
$user = $user_q ? mysqli_fetch_assoc($user_q) : null;

if (!$user) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'code' => 'USER_NOT_FOUND', 'message' => 'User account not found.']);
    exit;
}

// ─── GATE 3: AI Status Check ─────────────────────────────────
if ((int)$user['ai_status'] !== 1) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'AI_DISABLED',
        'message' => 'AI features are not enabled on your account. Visit AI Settings to get started.',
    ]);
    exit;
}

// ─── Load vendor AI config ────────────────────────────────────
$vendor_q = mysqli_query($connection_server,
    "SELECT ai_per_tx_cost, ai_model_assigned, ai_price_per_1k_tokens FROM sas_vendors WHERE id='$safe_vid' LIMIT 1"
);
$vendor_ai = $vendor_q ? mysqli_fetch_assoc($vendor_q) : null;
$tokens_per_call = (int)($vendor_ai['ai_per_tx_cost'] ?? 5);
$assigned_model  = $vendor_ai['ai_model_assigned'] ?? 'phi4-mini';

// Use requested model only if it matches the assigned model (prevent tier-hopping)
$model_to_use = $assigned_model;

// ─── GATE 4: Token Balance Check ─────────────────────────────
$current_tokens = (int)($user['ai_token_balance'] ?? 0);
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

$safe_prompt = bc_firewall_prompt($prompt_raw);
if ($safe_prompt === false) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'PROMPT_REJECTED',
        'message' => 'Your request contains content that cannot be processed. Please ask a VTU business-related question.',
    ]);
    exit;
}

// ─── CALL OLLAMA ──────────────────────────────────────────────
$ai = ai_engine();

// Action routing
switch ($action_type) {
    case 'marketing':
        $ai_result = $ai->generateWithFallback($model_to_use, $safe_prompt, ['temperature' => 0.85]);
        break;
    case 'analysis':
        $ai_result = $ai->generateWithFallback($model_to_use, $safe_prompt, ['temperature' => 0.3]);
        break;
    default:
        $ai_result = $ai->generateWithFallback($model_to_use, $safe_prompt);
        break;
}

// ─── SUCCESS: Deduct tokens, log, respond ────────────────────
if ($ai_result['status'] === 'success') {
    // Deduct tokens ONLY on success (pay-per-success billing)
    $new_tokens = max(0, $current_tokens - $tokens_per_call);
    $user_id    = (int)$user['id'];

    mysqli_query($connection_server,
        "UPDATE sas_users SET ai_token_balance='$new_tokens', ai_requests_used=ai_requests_used+1
         WHERE id='$user_id'"
    );

    // Log the AI transaction (store only a hash of the prompt for privacy)
    $prompt_hash = hash('sha256', $prompt_raw);
    $esc_action  = mysqli_real_escape_string($connection_server, substr($action_type, 0, 50));
    $esc_model   = mysqli_real_escape_string($connection_server, $ai_result['model']);
    $esc_hash    = mysqli_real_escape_string($connection_server, $prompt_hash);
    $cost_naira  = ($tokens_per_call / 1000) * (float)($vendor_ai['ai_price_per_1k_tokens'] ?? 100);

    mysqli_query($connection_server,
        "INSERT INTO sas_ai_transactions
         (vendor_id, username, action_type, model_used, tokens_burned, cost_naira, prompt_hash, status)
         VALUES ('$safe_vid', '$esc_user', '$esc_action', '$esc_model', '$tokens_per_call', '$cost_naira', '$esc_hash', 'success')"
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
         VALUES ('$safe_vid', '$esc_user', 'failed_call', '', 0, 0, '$esc_hash', 'failed')"
    );

    http_response_code(503);
    echo json_encode([
        'status'  => 'error',
        'code'    => 'AI_UNAVAILABLE',
        'message' => 'The AI engine is temporarily unavailable. Please try again shortly. No tokens were deducted.',
    ]);
}
