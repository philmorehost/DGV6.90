<?php session_start();
include("bc-connect.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']); exit;
}

$v_hash = mysqli_real_escape_string($connection_server, $_POST['hash'] ?? '');
$addon_id = (int)($_POST['addon_id'] ?? 0);

if (empty($v_hash) || !$addon_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']); exit;
}

// Verify vendor and addon ownership
$v_q = mysqli_query($connection_server, "SELECT id, selected_addons FROM sas_vendors WHERE access_hash='$v_hash' LIMIT 1");
$vendor = mysqli_fetch_assoc($v_q);

if (!$vendor) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid authentication']); exit;
}

$allowed_addons = explode(',', $vendor['selected_addons']);
if (!in_array($addon_id, $allowed_addons)) {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied for this asset']); exit;
}

// Generate token
$token = bin2hex(random_bytes(16));
$expiry = date('Y-m-d H:i:s', strtotime('+12 hours'));
$v_id = $vendor['id'];

// Save/Update tracking record
mysqli_query($connection_server, "INSERT INTO sas_vendor_downloads (vendor_id, addon_id, token, expiry) VALUES ('$v_id', '$addon_id', '$token', '$expiry')");

$dl_url = "https://" . $_SERVER['HTTP_HOST'] . "/DownloadService.php?token=" . $token;

echo json_encode(['status' => 'success', 'url' => $dl_url]);
