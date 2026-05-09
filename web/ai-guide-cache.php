<?php
/**
 * ai-guide-cache.php — DGV6.90 AI Edition
 * Serves pre-generated AI page guides from DB cache.
 * Falls back to Cloud AI generation only if no cache exists.
 */
session_start();
include_once(__DIR__ . "/../func/bc-config.php");
include_once(__DIR__ . "/../func/bc-ai-engine.php");

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ((empty($_SESSION['user_session']) && empty($_SESSION['admin_session'])) || !$connection_server) {
    echo json_encode(['guide' => null]);
    exit;
}

$page_slug = bc_sanitize($_GET['page'] ?? '');
$page_slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($page_slug));

if (empty($page_slug)) {
    echo json_encode(['guide' => null]);
    exit;
}

$vendor_id = resolveVendorID();
$safe_vid  = (int)$vendor_id;

// Check AI status
if (!empty($_SESSION['admin_session']) && empty($_SESSION['user_session'])) {
    // Admin Path
    $email = $_SESSION['admin_session'];
    $esc_email = mysqli_real_escape_string($connection_server, $email);
    $ai_chk = mysqli_query($connection_server, "SELECT ai_status FROM sas_vendors WHERE id='$safe_vid' AND email='$esc_email' LIMIT 1");
} else {
    // User Path
    $username  = $_SESSION['user_session'];
    $esc_user  = mysqli_real_escape_string($connection_server, $username);
    $ai_chk = mysqli_query($connection_server, "SELECT ai_status FROM sas_users WHERE vendor_id='$safe_vid' AND username='$esc_user' LIMIT 1");
}

$ai_row = $ai_chk ? mysqli_fetch_assoc($ai_chk) : null;
if (!$ai_row || (int)$ai_row['ai_status'] !== 1) {
    echo json_encode(['guide' => null]);
    exit;
}

// Check DB cache (valid for 24 hours)
$esc_slug = mysqli_real_escape_string($connection_server, $page_slug);
$cache_q  = mysqli_query($connection_server, "SELECT guide_text FROM sas_ai_page_guides WHERE page_slug='$esc_slug' AND vendor_id='$safe_vid' AND last_updated >= DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");
if ($cache_q && mysqli_num_rows($cache_q) > 0) {
    $cached = mysqli_fetch_assoc($cache_q);
    echo json_encode(['guide' => $cached['guide_text'], 'from_cache' => true]);
    exit;
}

// Cache miss — generate from Cloud AI
$ai     = ai_engine();
$vendor = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT ai_model_assigned FROM sas_vendors WHERE id='$safe_vid' LIMIT 1"));
$model  = $vendor['ai_model_assigned'] ?? getSuperAdminOption('ai_default_model', 'gemini-1.5-flash');

$page_name = str_replace('_', ' ', ucwords($page_slug));
$prompt = "You are a friendly Nigerian VTU business assistant. Give ONE concise, practical business tip for a user on the '$page_name' page. Max 120 words.";

$result = $ai->chat($model, $prompt, ['temperature' => 0.75]);

if ($result['status'] === 'success') {
    $guide_text = $result['response'];
    $esc_guide  = mysqli_real_escape_string($connection_server, $guide_text);
    mysqli_query($connection_server, "INSERT INTO sas_ai_page_guides (page_slug, vendor_id, guide_text) VALUES ('$esc_slug', '$safe_vid', '$esc_guide') ON DUPLICATE KEY UPDATE guide_text='$esc_guide', last_updated=NOW()");
    echo json_encode(['guide' => $guide_text, 'from_cache' => false]);
} else {
    echo json_encode(['guide' => null]);
}
