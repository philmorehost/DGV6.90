<?php
include("func/bc-connect.php");

$token = mysqli_real_escape_string($connection_server, $_GET['token'] ?? '');

if (empty($token)) {
    die("Error: Invalid download attempt.");
}

// Fetch the tracking record
$sql = "SELECT vd.*, a.download_url as addon_url, p.download_url as package_url
        FROM sas_vendor_downloads vd
        LEFT JOIN sas_billing_addons a ON vd.addon_id = a.id
        LEFT JOIN sas_billing_packages p ON vd.package_id = p.id
        WHERE vd.token='$token' LIMIT 1";
$res = mysqli_query($connection_server, $sql);
$dl = mysqli_fetch_assoc($res);

if (!$dl) {
    die("Error: Download link not found or has been revoked.");
}

// Check expiry (12 hours)
if (strtotime($dl['expiry']) < time()) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Download Link Expired</title>
        <link href="assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center vh-100">
        <div class="text-center p-5 bg-white rounded-4 shadow-sm" style="max-width: 400px;">
            <div class="bg-warning bg-opacity-10 p-4 rounded-circle d-inline-block mb-4">
                <i class="bi bi-exclamation-triangle text-warning fs-1"></i>
            </div>
            <h4 class="fw-bold text-dark">Link Expired</h4>
            <p class="text-muted mb-4 small">For security reasons, this download address expired after 12 hours. Please return to your Order Portal to generate a fresh link.</p>
            <a href="javascript:window.history.back()" class="btn btn-primary rounded-pill px-4 fw-bold">Return to Portal</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Track download
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
mysqli_query($connection_server, "UPDATE sas_vendor_downloads SET download_count = download_count + 1, ip_address='$ip' WHERE id='".$dl['id']."'");

// Redirect to actual file
$final_url = $dl['addon_url'] ?? $dl['package_url'];
if (empty($final_url)) {
    die("Error: The file source has not been configured by the administrator yet.");
}

header("Location: $final_url");
exit();
