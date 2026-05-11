<?php session_start();
include("bc-connect.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']); exit;
}

$v_hash = mysqli_real_escape_string($connection_server, $_POST['hash'] ?? '');
$addon_id = isset($_POST['addon_id']) ? (int)$_POST['addon_id'] : null;
$package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : null;

if (empty($v_hash) || (!$addon_id && !$package_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']); exit;
}

// Verify vendor and ownership
$v_q = mysqli_query($connection_server, "SELECT id, selected_addons, current_billing_id FROM sas_vendors WHERE access_hash='$v_hash' LIMIT 1");
$vendor = mysqli_fetch_assoc($v_q);

if (!$vendor) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid authentication']); exit;
}

if ($addon_id) {
    $allowed_addons = explode(',', $vendor['selected_addons']);
    if (!in_array($addon_id, $allowed_addons)) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied for this asset']); exit;
    }
}

if ($package_id) {
    if ($vendor['current_billing_id'] != $package_id) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied for this package']); exit;
    }
}

// Generate token
$token = bin2hex(random_bytes(16));
$expiry = date('Y-m-d H:i:s', strtotime('+12 hours'));
$v_id = $vendor['id'];

// Save/Update tracking record
if ($addon_id) {
    mysqli_query($connection_server, "INSERT INTO sas_vendor_downloads (vendor_id, addon_id, token, expiry) VALUES ('$v_id', '$addon_id', '$token', '$expiry')");
} else {
    mysqli_query($connection_server, "INSERT INTO sas_vendor_downloads (vendor_id, package_id, token, expiry) VALUES ('$v_id', '$package_id', '$token', '$expiry')");
}

$dl_url = "https://" . $_SERVER['HTTP_HOST'] . "/DownloadService.php?token=" . $token;

echo json_encode(['status' => 'success', 'url' => $dl_url]);
