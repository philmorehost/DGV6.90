<?php session_start();
include("func/bc-connect.php");
include("func/bc-security.php");

$hash = mysqli_real_escape_string($connection_server, $_GET['hash'] ?? '');
if (empty($hash)) {
    die("Access denied. Invalid or missing secure key.");
}

$v_q = mysqli_query($connection_server, "SELECT v.*, bp.name as package_name, bp.download_url as package_dl FROM sas_vendors v LEFT JOIN sas_billing_packages bp ON v.current_billing_id = bp.id WHERE v.access_hash='$hash'");
$vendor = mysqli_fetch_assoc($v_q);

if (!$vendor) {
    die("Unauthorized access. This link may have been revoked.");
}

// Handle Renewal Actions
$renewal_response = null;
if (isset($_POST['renew_action'])) {
    $action = $_POST['renew_action'];
    $v_id = $vendor['id'];
    $current_balance = (float)$vendor['balance'];
    $pkg_id = $vendor['current_billing_id'];
    
    if ($action == 'renew_site') {
        $price = (float)$vendor['package_price'];
        $days = (int)$vendor['duration_days'];
        if ($current_balance >= $price) {
            $new_expiry = date('Y-m-d', strtotime($vendor['expiry_date'] . " + $days days"));
            mysqli_query($connection_server, "UPDATE sas_vendors SET balance = balance - $price, expiry_date = '$new_expiry' WHERE id = '$v_id'");
            mysqli_query($connection_server, "INSERT INTO sas_vendor_subscriptions (vendor_id, package_id, purchase_date, expiry_date, amount_paid) VALUES ('$v_id', '$pkg_id', NOW(), '$new_expiry', '$price')");
            $renewal_response = ['status' => 'success', 'message' => "Site successfully renewed until " . date('M d, Y', strtotime($new_expiry))];
            // Refresh vendor data
            $v_q = mysqli_query($connection_server, "SELECT v.*, bp.name as package_name, bp.price as package_price, bp.duration_days, bp.download_url as package_dl FROM sas_vendors v JOIN sas_billing_packages bp ON v.current_billing_id = bp.id WHERE v.id='$v_id'");
            $vendor = mysqli_fetch_assoc($v_q);
        } else {
            $renewal_response = ['status' => 'error', 'message' => "Insufficient wallet balance. Please fund your vendor account."];
        }
    } elseif ($action == 'renew_domain') {
        // Find domain price
        $ext = "." . pathinfo($vendor['app_base_url'], PATHINFO_EXTENSION);
        $q_ext = mysqli_query($connection_server, "SELECT price FROM sas_domain_extensions WHERE extension='$ext' LIMIT 1");
        $domain_price = ($r_ext = mysqli_fetch_assoc($q_ext)) ? (float)$r_ext['price'] : 0;
        
        if ($domain_price <= 0) $domain_price = 10000; // Fallback default

        if ($current_balance >= $domain_price) {
            $base_date = (!empty($vendor['domain_expiry_date']) && $vendor['domain_expiry_date'] != '0000-00-00') ? $vendor['domain_expiry_date'] : date('Y-m-d');
            $new_domain_expiry = date('Y-m-d', strtotime($base_date . " + 365 days"));
            mysqli_query($connection_server, "UPDATE sas_vendors SET balance = balance - $domain_price, domain_expiry_date = '$new_domain_expiry' WHERE id = '$v_id'");
            $renewal_response = ['status' => 'success', 'message' => "Domain successfully renewed until " . date('M d, Y', strtotime($new_domain_expiry))];
            // Refresh
            $v_q = mysqli_query($connection_server, "SELECT v.*, bp.name as package_name, bp.price as package_price, bp.duration_days, bp.download_url as package_dl FROM sas_vendors v JOIN sas_billing_packages bp ON v.current_billing_id = bp.id WHERE v.id='$v_id'");
            $vendor = mysqli_fetch_assoc($v_q);
        } else {
            $renewal_response = ['status' => 'error', 'message' => "Insufficient balance to renew domain (₦".number_format($domain_price, 2).")."];
        }
    }
}

$addon_ids = $vendor['selected_addons'];
$has_addons = !empty($addon_ids);
$has_package_dl = !empty($vendor['package_dl']);
$has_apps = ($vendor['apk_ordered'] || $vendor['ios_ordered'] || $vendor['playstore_ordered'] || $vendor['sms_bridge_ordered']);

// Fetch active addons with download URLs
$addons = [];
if ($has_addons) {
    // Sanitize addon_ids (ensure it's a list of integers)
    $ids_arr = array_map('intval', explode(',', $addon_ids));
    $safe_ids = implode(',', $ids_arr);
    $ar = mysqli_query($connection_server, "SELECT * FROM sas_billing_addons WHERE id IN ($safe_ids)");
    while ($row = mysqli_fetch_assoc($ar)) {
        if (!empty($row['download_url'])) $addons[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Order Details & Downloads | <?php echo htmlspecialchars($vendor['firstname']); ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets-2/css/style.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .portal-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .download-card { 
            border: 2px solid #f0f0f0; 
            border-radius: 15px; 
            transition: all 0.3s ease;
        }
        .download-card:hover { 
            border-color: #0d6efd; 
            background-color: #f8fbff;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="fw-bold text-primary">Your Order Portal</h2>
                    <p class="text-muted">Hello, <?php echo htmlspecialchars($vendor['firstname']); ?>! Here is the summary of your activated services.</p>
                    <div class="badge bg-white shadow-sm border p-3 rounded-pill">
                        <span class="text-muted small text-uppercase fw-bold me-2">Wallet Balance:</span>
                        <span class="fw-black text-primary fs-5">₦<?php echo number_format($vendor['balance'], 2); ?></span>
                    </div>
                </div>

                <?php if ($renewal_response): ?>
                <div class="alert alert-<?php echo $renewal_response['status'] == 'success' ? 'success' : 'danger'; ?> border-0 rounded-4 shadow-sm mb-4 animate__animated animate__fadeIn">
                    <i class="bi <?php echo $renewal_response['status'] == 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
                    <?php echo $renewal_response['message']; ?>
                </div>
                <?php endif; ?>

                <!-- Order Summary Card -->
                <div class="card portal-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Subscription Summary</h5>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Active</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small text-muted text-uppercase fw-bold">Active Plan</label>
                            <div class="fw-bold fs-5 text-dark"><?php echo htmlspecialchars($vendor['package_name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted text-uppercase fw-bold">Platform URL</label>
                            <div class="text-primary fw-bold"><i class="bi bi-globe me-1"></i> <?php echo htmlspecialchars($vendor['app_base_url']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted text-uppercase fw-bold">Account Expiry</label>
                            <div class="text-dark fw-bold"><?php echo date('F d, Y', strtotime($vendor['expiry_date'])); ?></div>
                            <form method="post" onsubmit="return confirm('Renew your site for ₦<?php echo number_format($vendor['package_price'], 0); ?> using wallet balance?');">
                                <input type="hidden" name="renew_action" value="renew_site">
                                <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-3 mt-2 fw-bold">Renew Site (₦<?php echo number_format($vendor['package_price'], 0); ?>)</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted text-uppercase fw-bold">Domain Expiry</label>
                            <div class="text-dark fw-bold">
                                <?php echo (!empty($vendor['domain_expiry_date']) && $vendor['domain_expiry_date'] != '0000-00-00') ? date('F d, Y', strtotime($vendor['domain_expiry_date'])) : 'Pending Setup'; ?>
                            </div>
                            <?php if(!empty($vendor['app_base_url'])): ?>
                            <form method="post" onsubmit="return confirm('Renew your domain for another year using wallet balance?');">
                                <input type="hidden" name="renew_action" value="renew_domain">
                                <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3 mt-2 fw-bold">Renew Domain</button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <?php if($has_apps || $has_addons): ?>
                        <div class="col-12 mt-4 pt-3 border-top">
                            <label class="small text-muted text-uppercase fw-bold mb-2">Platform Configuration</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if($vendor['apk_ordered']): ?><span class="badge bg-light text-dark border rounded-pill px-3"><i class="bi bi-android2 me-1"></i>Android App</span><?php endif; ?>
                                <?php if($vendor['ios_ordered']): ?><span class="badge bg-light text-dark border rounded-pill px-3"><i class="bi bi-apple me-1"></i>iOS App</span><?php endif; ?>
                                <?php if($vendor['playstore_ordered']): ?><span class="badge bg-light text-dark border rounded-pill px-3"><i class="bi bi-shop me-1"></i>Playstore</span><?php endif; ?>
                                <?php if($vendor['sms_bridge_ordered']): ?><span class="badge bg-light text-dark border rounded-pill px-3"><i class="bi bi-sim me-1"></i>SMS Bridge</span><?php endif; ?>
                                
                                <?php 
                                    if($has_addons) {
                                        $ids_arr = array_map('intval', explode(',', $addon_ids));
                                        $safe_ids = implode(',', $ids_arr);
                                        $addons_res = mysqli_query($connection_server, "SELECT name FROM sas_billing_addons WHERE id IN ($safe_ids)");
                                        while($arow = mysqli_fetch_assoc($addons_res)) {
                                            echo '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill px-3"><i class="bi bi-plus-circle me-1"></i>'.htmlspecialchars($arow['name']).'</span>';
                                        }
                                    }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($has_addons || $has_apps || $has_package_dl): ?>
                <!-- Downloads Section -->
                <div class="card portal-card p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-cloud-arrow-down-fill me-2 text-primary"></i>Digital Assets & Apps</h5>
                    
                    <div class="alert alert-info border-0 rounded-4 small mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i> For your security, download links expire <strong>12 hours</strong> after generation. You can always come back here to generate a fresh link.
                    </div>

                    <div class="row g-3">
                        <?php if ($has_package_dl): ?>
                        <div class="col-12 mb-2">
                            <div class="download-card p-4 border-primary bg-primary bg-opacity-10 shadow-sm">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary p-3 rounded-4 me-3 text-white">
                                        <i class="bi bi-code-square fs-3"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-5"><?php echo htmlspecialchars($vendor['package_name']); ?> Source Script</div>
                                        <div class="small text-primary fw-bold">Primary Digital Asset</div>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100 rounded-pill fw-bold py-2 generate-dl" data-pkg-id="<?php echo $vendor['current_billing_id']; ?>" data-hash="<?php echo $hash; ?>">
                                    <i class="bi bi-shield-lock-fill me-1"></i> Generate Secure Script Link
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (empty($addons) && !$has_apps && !$has_package_dl): ?>
                            <div class="col-12 text-center py-4 text-muted small">
                                <i class="bi bi-hourglass-split fs-2 d-block mb-2"></i>
                                Your custom builds are being prepared. Check back shortly.
                            </div>
                        <?php else: ?>
                            <?php foreach ($addons as $addon): ?>
                            <div class="col-md-6">
                                <div class="download-card p-3 h-100">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded-4 me-3">
                                            <i class="bi <?php echo htmlspecialchars($addon['icon']); ?> text-primary fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($addon['name']); ?></div>
                                            <div class="extra-small text-muted">Ready for download</div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary w-100 rounded-pill fw-bold btn-sm generate-dl" data-id="<?php echo $addon['id']; ?>" data-hash="<?php echo $hash; ?>">
                                        <i class="bi bi-link-45deg me-1"></i> Generate Download Link
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="text-center mt-5">
                    <p class="text-muted small">&copy; <?php echo date('Y'); ?> Digital Assets Portal. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Dynamic Link -->
    <div class="modal fade" id="linkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-body p-4 text-center">
                    <div class="bg-success bg-opacity-10 p-4 rounded-circle d-inline-block mb-4">
                        <i class="bi bi-check2-circle text-success fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Link Generated Successfully!</h5>
                    <p class="text-muted small mb-4">You can now download your file. Remember, this link will expire in 12 hours.</p>
                    
                    <div class="bg-light p-3 rounded-4 mb-4 text-break small font-monospace" id="generatedLink">
                        Loading...
                    </div>

                    <div class="d-grid gap-2">
                        <a href="#" id="downloadBtn" class="btn btn-primary rounded-pill py-2 fw-bold">Download Now</a>
                        <button type="button" class="btn btn-light rounded-pill py-2" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets-2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('linkModal'));
        
        document.querySelectorAll('.generate-dl').forEach(btn => {
            btn.addEventListener('click', function() {
                const addonId = this.getAttribute('data-id');
                const pkgId = this.getAttribute('data-pkg-id');
                const vHash = this.getAttribute('data-hash');
                const originalText = this.innerHTML;
                
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating...';

                let postBody = 'hash=' + vHash;
                if(addonId) postBody += '&addon_id=' + addonId;
                if(pkgId) postBody += '&package_id=' + pkgId;

                fetch('func/bc-get-download-link.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: postBody
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('generatedLink').innerText = data.url;
                        document.getElementById('downloadBtn').href = data.url;
                        modal.show();
                    } else {
                        alert(data.message);
                    }
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = originalText;
                });
            });
        });
    </script>
</body>
</html>
