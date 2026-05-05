<?php session_start();
include("../func/bc-admin-config.php");

// Migration: Create table if not exists
mysqli_query($connection_server, "CREATE TABLE IF NOT EXISTS sas_service_control (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    service_name VARCHAR(50) NOT NULL,
    status TINYINT(1) DEFAULT 1,
    UNIQUE KEY vendor_service (vendor_id, service_name)
)");

$services = [
    'data' => 'Buy Data Bundle',
    'airtime' => 'Buy Airtime VTU',
    'cable' => 'Buy CableTv Sub',
    'electric' => 'Buy Electric Token',
    'betting' => 'Fund Betting',
    'exam' => 'Buy Exam PIN',
    'bulk_sms' => 'Bulk SMS',
    'data_card' => 'Print Hub (Master Switch)',
    'print_data' => 'Print Hub — Data Cards',
    'print_airtime' => 'Print Hub — Airtime Cards',
    'print_cable' => 'Print Hub — Cable Cards',
    'print_electric' => 'Print Hub — Electric Cards',
    'print_exam' => 'Print Hub — Exam Pin Cards',
    'print_betting' => 'Print Hub — Betting Cards',
    'recharge_card' => 'Recharge Card Printing',
    'bank_transfer' => 'Bank Transfer Service',
    'payout' => 'Payout (API & Web)',
    'virtual_card' => 'Virtual Card System',
    'gift_card' => 'Gift Cards',
    'crypto_hub' => 'Crypto Service',
    'nin_card' => 'Digital NIN Slip',
    'bvn_verify' => 'BVN Verification'
];

$gateways = [
    'paystack' => 'Paystack',
    'flutterwave' => 'Flutterwave',
    'monnify' => 'Monnify',
    'payvessel' => 'PayVessel',
    'beewave' => 'BeeWave',
    'payhub' => 'PayHub',
    'plisio' => 'Plisio (Crypto)',
    'manual_funding' => 'Manual Bank Funding'
];

if (isset($_POST['toggle_service'])) {
    $service_name = mysqli_real_escape_string($connection_server, $_POST['service_name']);
    $status = (int)$_POST['status'];
    $vid = $get_logged_admin_details['id'];

    mysqli_query($connection_server, "INSERT INTO sas_service_control (vendor_id, service_name, status)
        VALUES ('$vid', '$service_name', $status)
        ON DUPLICATE KEY UPDATE status=$status");

    $_SESSION["product_purchase_response"] = "Setting updated successfully.";
    header("Location: ServiceControl.php");
    exit();
}

// Fetch current settings
$settings = [];
$vid = $get_logged_admin_details['id'];
$q = mysqli_query($connection_server, "SELECT service_name, status FROM sas_service_control WHERE vendor_id='$vid'");
while($r = mysqli_fetch_assoc($q)) $settings[$r['service_name']] = $r['status'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Service Control Center | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <?php include("../func/bc-admin-header-link.php"); ?>
</head>
<body>
    <?php include("../func/bc-admin-header.php"); ?>

    <div class="pagetitle">
      <h1>SERVICE CONTROL CENTER</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Service Control</li>
        </ol>
      </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-cpu me-2"></i>Service Visibility</h5>
                        <p class="small text-muted mb-0">Enable or disable services across the platform.</p>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach($services as $key => $label):
                                $status = isset($settings[$key]) ? $settings[$key] : 1;
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <h6 class="mb-0 fw-bold"><?php echo $label; ?></h6>
                                    <span class="small text-muted">ID: <?php echo $key; ?></span>
                                </div>
                                <form method="post" action="">
                                    <input type="hidden" name="service_name" value="<?php echo $key; ?>">
                                    <input type="hidden" name="status" value="<?php echo $status ? 0 : 1; ?>">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input h4 mb-0 cursor-pointer" type="checkbox" role="switch"
                                            <?php echo $status ? 'checked' : ''; ?>
                                            onchange="this.form.submit()">
                                        <input type="hidden" name="toggle_service" value="1">
                                    </div>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-credit-card-2-front me-2"></i>Payment Gateways</h5>
                        <p class="small text-muted mb-0">Control which payment methods are available to users.</p>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach($gateways as $key => $label):
                                $status = isset($settings[$key]) ? $settings[$key] : 1;
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <h6 class="mb-0 fw-bold"><?php echo $label; ?></h6>
                                    <span class="small text-muted">ID: <?php echo $key; ?></span>
                                </div>
                                <form method="post" action="">
                                    <input type="hidden" name="service_name" value="<?php echo $key; ?>">
                                    <input type="hidden" name="status" value="<?php echo $status ? 0 : 1; ?>">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input h4 mb-0 cursor-pointer" type="checkbox" role="switch"
                                            <?php echo $status ? 'checked' : ''; ?>
                                            onchange="this.form.submit()">
                                        <input type="hidden" name="toggle_service" value="1">
                                    </div>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
