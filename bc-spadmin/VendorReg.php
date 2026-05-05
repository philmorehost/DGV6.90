<?php session_start();
    // Use basic configs to allow optional login
    include("../func/bc-connect.php");
	include("../func/bc-tables.php");
    include("../func/bc-email-templates.php");
    include_once("../func/bc-func.php");
    include("../func/whmcs-func.php");

    $apk_price = (float)getSuperAdminOption('apk_development_price', '0');
    $ios_price = (float)getSuperAdminOption('ios_development_price', '0');
    $playstore_price = (float)getSuperAdminOption('playstore_listing_price', '0');
    $sms_bridge_price = (float)getSuperAdminOption('sms_bridge_price', '0');

    // Fetch domain settings
    $nameservers = '';
    $ip_address = '';
    $registrar_url = '';
    $sql_fetch = "SELECT * FROM sas_super_admin_options WHERE option_name IN ('domain_nameservers', 'domain_ip_address', 'domain_registrar_url')";
    $result = mysqli_query($connection_server, $sql_fetch);
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            if($row['option_name'] == 'domain_nameservers') $nameservers = $row['option_value'];
            if($row['option_name'] == 'domain_ip_address') $ip_address = $row['option_value'];
            if($row['option_name'] == 'domain_registrar_url') $registrar_url = $row['option_value'];
        }
    }

    if(isset($_POST["create-profile"])){
        $first = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["first"]))));
        $last = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["last"]))));
        $address = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["address"]))));
        $email = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["email"]))));
        $pass = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["pass"])));
        $phone = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["phone"]))));
        $billing_package_id = mysqli_real_escape_string($connection_server, $_POST['billing_package_id']);
        $payment_method = mysqli_real_escape_string($connection_server, $_POST['payment_method']);
        
        $app_base_url = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST['app_base_url'] ?? '')));
        $order_apk = isset($_POST['order_apk']) ? 1 : 0;
        $order_ios = isset($_POST['order_ios']) ? 1 : 0;
        $order_playstore = isset($_POST['order_playstore']) ? 1 : 0;
        $order_sms_bridge = isset($_POST['order_sms_bridge']) ? 1 : 0;
        $domain_fee = (float)($_POST['domain_fee'] ?? 0);
        $total_amount = (float)($_POST['total_amount'] ?? 0);

        $unrefined_website_url = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["website-url"]))));
        $refined_website_url = trim(str_replace(["https","http",":/","/","www."," "],"",$unrefined_website_url));
        $website_url = !empty($app_base_url) ? $app_base_url : $refined_website_url;
        
        if(!empty($first) && !empty($last) && !empty($address) && !empty($email) && !empty($pass) && !empty($phone) && !empty($website_url) && !empty($billing_package_id) && !empty($payment_method)){
            if(filter_var($email, FILTER_VALIDATE_EMAIL)){
                $check_vendor_details_with_email = mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE email='$email'");
                $check_pending_vendor_details_with_email = mysqli_query($connection_server, "SELECT * FROM sas_pending_vendors WHERE email='$email'");
                if(mysqli_num_rows($check_vendor_details_with_email) == 0 && mysqli_num_rows($check_pending_vendor_details_with_email) == 0){
                    $check_vendor_details_with_url = mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE website_url='$website_url'");
                    $check_pending_vendor_details_with_url = mysqli_query($connection_server, "SELECT * FROM sas_pending_vendors WHERE website_url='$website_url'");
                    if(mysqli_num_rows($check_vendor_details_with_url) == 0 && mysqli_num_rows($check_pending_vendor_details_with_url) == 0){
                        $md5_pass = md5($pass);
                        
                        $def_min = getSuperAdminOption('default_min_withdrawal', '1000');
                        $def_max = getSuperAdminOption('default_max_withdrawal', '50000');
                        $def_limit = getSuperAdminOption('default_daily_payout_limit', '10');

                        $sql = "INSERT INTO sas_pending_vendors (website_url, email, password, firstname, lastname, phone_number, home_address, billing_package_id, payment_method, status, min_withdrawal_amount, max_withdrawal_amount, daily_payout_limit, app_base_url, order_apk, order_ios, order_playstore, order_sms_bridge, domain_registration_fee, total_amount) VALUES ('$website_url', '$email', '$md5_pass', '$first', '$last', '$phone', '$address', '$billing_package_id', '$payment_method', '0', '$def_min', '$def_max', '$def_limit', '$app_base_url', '$order_apk', '$order_ios', '$order_playstore', '$order_sms_bridge', '$domain_fee', '$total_amount')";
                        if(mysqli_query($connection_server, $sql)) {
                            $pending_id = mysqli_insert_id($connection_server);

                            // Send admin notification email
                            $get_super_admin = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_super_admin LIMIT 1"));
                            if($get_super_admin) {
                                $admin_email = $get_super_admin['email'];
                                $email_placeholders = array(
                                    "{firstname}" => $first,
                                    "{lastname}" => $last,
                                    "{email}" => $email,
                                    "{website}" => $website_url
                                );
                                $email_subject = getSuperAdminEmailTemplate('new-vendor-pending-admin-alert', 'subject');
                                $email_body = getSuperAdminEmailTemplate('new-vendor-pending-admin-alert', 'body');
                                foreach($email_placeholders as $key => $val) {
                                    $email_subject = str_replace($key, $val, $email_subject);
                                    $email_body = str_replace($key, $val, $email_body);
                                }
                                sendVendorEmail($admin_email, $email_subject, $email_body);
                            }

                            if ($payment_method == 'paystack') {
                                // Server-side Total Verification (Protect against client manipulation)
                                $v_package_id = mysqli_real_escape_string($connection_server, $billing_package_id);
                                $v_pkg_res = mysqli_query($connection_server, "SELECT price FROM sas_billing_packages WHERE id='$v_package_id'");
                                $v_pkg = mysqli_fetch_assoc($v_pkg_res);

                                $calculated_total = (float)($v_pkg['price'] ?? 0);
                                if($order_apk) $calculated_total += $apk_price;
                                if($order_ios) $calculated_total += $ios_price;
                                if($order_playstore) $calculated_total += $playstore_price;
                                if($order_sms_bridge) $calculated_total += $sms_bridge_price;

                                // Recalculate domain fee on server for security (handle multi-part TLDs)
                                $actual_domain_fee = 0;
                                if (!empty($app_base_url)) {
                                    $first_dot = strpos($app_base_url, '.');
                                    $ext = ($first_dot !== false) ? substr($app_base_url, $first_dot) : '';
                                    $ext_esc = mysqli_real_escape_string($connection_server, $ext);
                                    $q_ext = mysqli_query($connection_server, "SELECT price FROM sas_domain_extensions WHERE extension='$ext_esc' LIMIT 1");
                                    if($r_ext = mysqli_fetch_assoc($q_ext)) {
                                        $actual_domain_fee = (float)$r_ext['price'];
                                    }
                                }
                                $calculated_total += $actual_domain_fee;

                                // Use total amount for payment initialization
                                $amount_in_kobo = $calculated_total * 100;

                                // Update record with correct calculated total if they differ
                                if(abs($calculated_total - $total_amount) > 0.01) {
                                    mysqli_query($connection_server, "UPDATE sas_pending_vendors SET total_amount='$calculated_total' WHERE id='$pending_id'");
                                }

                                // Fetch Paystack secret key
                                $gateway_res = mysqli_query($connection_server, "SELECT secret_key FROM sas_super_admin_payment_gateways WHERE gateway_name='paystack'");
                                $gateway = mysqli_fetch_assoc($gateway_res);
                                $secret_key = $gateway['secret_key'];

                                if ($secret_key) {
                                    $callback_url = $web_http_host . '/web/paystack_callback.php';
                                    $reference = 'vendor_reg_' . $pending_id . '_' . time();

                                    $post_data = [
                                        'email' => $email,
                                        'amount' => $amount_in_kobo,
                                        'callback_url' => $callback_url,
                                        'reference' => $reference,
                                        'metadata' => [
                                            'pending_vendor_id' => $pending_id,
                                            'type' => 'vendor_subscription'
                                        ]
                                    ];

                                    $curl = curl_init();
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_CUSTOMREQUEST => "POST",
                                        CURLOPT_POSTFIELDS => json_encode($post_data),
                                        CURLOPT_HTTPHEADER => [
                                            "Authorization: Bearer " . $secret_key,
                                            "Content-Type: application/json"
                                        ],
                                    ));

                                    $response = curl_exec($curl);
                                    $err = curl_error($curl);
                                    curl_close($curl);

                                    if ($err) {
                                        $_SESSION["product_purchase_response"] = "Payment gateway error. Please try again or contact support.";
                                    } else {
                                        $result = json_decode($response, true);
                                        if ($result['status'] == true) {
                                            mysqli_query($connection_server, "UPDATE sas_pending_vendors SET paystack_reference='$reference' WHERE id='$pending_id'");
                                            header('Location: ' . $result['data']['authorization_url']);
                                            exit();
                                        } else {
                                            $_SESSION["product_purchase_response"] = "Could not initialize payment: " . $result['message'];
                                        }
                                    }
                                } else {
                                     $_SESSION["product_purchase_response"] = "Paystack payment gateway is not configured. Please contact support.";
                                }
                            } else { // Manual bank deposit
                                header("Location: /web/manual_payment.php");
                                exit();
                            }
                        } else {
                            $_SESSION["product_purchase_response"] = "Could not save your registration. Please try again.";
                        }
                        header("Location: ".$_SERVER["REQUEST_URI"]);
                        exit();
                    } else {
                        $_SESSION["product_purchase_response"] = "A vendor with the same Website URL already exists.";
                    }
                } else {
                     $_SESSION["product_purchase_response"] = "A vendor with the same Email already exists.";
                }
            } else {
                $_SESSION["product_purchase_response"] = "Invalid Email format.";
            }
        } else {
            $_SESSION["product_purchase_response"] = "Please fill all required fields.";
        }
        header("Location: ".$_SERVER["REQUEST_URI"]);
        exit();
    }

    // Set layout based on session
    $is_admin_session = isset($_SESSION["spadmin_session"]);
    $css_style_template_location = "/cssfile/template/bc-style-template-1.css";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Vendor Registration | Super Admin</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        .register-card { border: none; border-radius: 20px; }
        .form-label { font-weight: 700; font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .input-group-text { background: #f8f9fa; border-right: none; color: #0d6efd; }
        .form-control { border-left: none; background: #ffffff; }
        .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); border-color: #86b7fe; }
        .card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important; }
        .btn-primary { background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important; }
        .btn-primary:hover { background-color: #0b5ed7 !important; border-color: #0a58ca !important; }
        .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
        .cursor-pointer { cursor: pointer; }
        @media (max-width: 991.98px) {
            .pagetitle h1 { font-size: 1.5rem; text-align: center; margin-bottom: 20px; }
            .card-header h5 { font-size: 1.1rem; }
        }
    </style>
</head>
<body class="<?php echo !$is_admin_session ? 'bg-light' : ''; ?>">
    <?php if($is_admin_session) include("../func/bc-spadmin-header.php"); ?>

    <div class="<?php echo $is_admin_session ? '' : 'container min-vh-100 d-flex align-items-center justify-content-center py-5'; ?>">
        <div class="<?php echo $is_admin_session ? '' : 'col-lg-9'; ?>">

            <?php if($is_admin_session): ?>
            <div class="pagetitle">
                <h1>NEW VENDOR REGISTRATION</h1>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Add Vendor</li>
                    </ol>
                </nav>
            </div>
            <?php endif; ?>

            <section class="section dashboard">
                <div class="row g-4">
                    <!-- Instructions Column -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white py-3 border-0">
                                <h5 class="card-title mb-0 d-flex align-items-center">
                                    <i class="bi bi-info-circle me-2 text-primary"></i>Domain Instructions
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-0">
                                    <p class="small mb-3">Domain name registration is not free. Use our suggested registrar or any of your choice.</p>
                                    <div class="bg-white bg-opacity-50 p-3 rounded-3 mb-3 border">
                                        <div class="small fw-bold text-muted mb-1">REGISTRAR URL</div>
                                        <a href="<?php echo htmlspecialchars($registrar_url); ?>" target="_blank" class="text-break fw-bold text-decoration-none"><?php echo htmlspecialchars($registrar_url); ?></a>
                                    </div>
                                    <div class="bg-white bg-opacity-50 p-3 rounded-3 mb-3 border">
                                        <div class="small fw-bold text-muted mb-1">NAMESERVERS</div>
                                        <code class="text-dark fw-bold"><?php echo nl2br(htmlspecialchars($nameservers)); ?></code>
                                    </div>
                                    <div class="bg-white bg-opacity-50 p-3 rounded-3 border">
                                        <div class="small fw-bold text-muted mb-1">A-RECORD (IP)</div>
                                        <code class="text-primary fw-bold"><?php echo htmlspecialchars($ip_address); ?></code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registration Form Column -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                            <div class="card-header bg-primary py-4 border-0 text-white">
                                <h5 class="mb-1 fw-bold">Vendor Profile Setup</h5>
                                <p class="mb-0 opacity-75 small">Fill in the details below to create a new sub-vendor account</p>
                            </div>
                            <div class="card-body p-4 p-md-5">
                                <?php if(isset($_SESSION["product_purchase_response"])): ?>
                                    <div class="alert alert-info alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                                        <i class="bi bi-info-circle me-2"></i><?php echo $_SESSION["product_purchase_response"]; unset($_SESSION["product_purchase_response"]); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="" class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person text-primary"></i></span>
                                            <input type="text" name="first" class="form-control rounded-end-3" placeholder="First Name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person text-primary"></i></span>
                                            <input type="text" name="last" class="form-control rounded-end-3" placeholder="Last Name" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Home Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo-alt text-primary"></i></span>
                                            <input type="text" name="address" class="form-control rounded-end-3" placeholder="Full residential or business address" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope text-primary"></i></span>
                                            <input type="email" name="email" class="form-control rounded-end-3" placeholder="email@example.com" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-phone text-primary"></i></span>
                                            <input type="text" name="phone" class="form-control rounded-end-3" placeholder="08012345678" pattern="[0-9]{11}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-between">
                                            <label class="form-label">Step 1: Choose Your Domain (App Base URL)</label>
                                            <span id="domain_spinner" class="spinner-border spinner-border-sm text-primary d-none"></span>
                                        </div>

                                        <!-- Responsive Domain Search Layout -->
                                        <div class="row g-2 align-items-stretch">
                                            <div class="col-lg-9">
                                                <div class="input-group input-group-lg shadow-sm h-100">
                                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-primary"></i></span>
                                                    <input type="text" id="target_domain" name="website-url" class="form-control border-start-0 border-end-0" placeholder="e.g. mybusiness" style="font-weight: 600;" required>
                                                    <select id="domain_extension" class="form-select border-start-0 fw-bold" style="max-width: 110px; background-color: #f8f9fa;" onchange="updateCheckoutTotal()">
                                                        <?php
                                                        $ext_res = mysqli_query($connection_server, "SELECT extension FROM sas_domain_extensions ORDER BY extension ASC");
                                                        while($ext = mysqli_fetch_assoc($ext_res)) {
                                                            echo "<option value='{$ext['extension']}'>{$ext['extension']}</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3">
                                                <button class="btn btn-primary btn-lg w-100 h-100 fw-bold shadow-sm domain-search-btn" type="button" onclick="lookupDomain()" style="background-color: #0d6efd !important; color: #ffffff !important; border: none; min-height: 48px;">
                                                    <span class="d-none d-lg-inline">SEARCH</span>
                                                    <span class="d-lg-none">CHECK AVAILABILITY</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div id="domain_feedback" class="mt-2"></div>
                                        <input type="hidden" name="domain_fee" id="domain_fee_input" value="0">
                                        <input type="hidden" name="app_base_url" id="app_base_url_input">

                                        <div class="alert border mt-3 mb-0 rounded-4" style="font-size: 12px; border-left: 5px solid #ffc107 !important; background: #fffcf0;">
                                            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                                            <strong>APK Warning:</strong> This domain will be hardcoded as your <strong>App Base URL</strong>. While website domains can be changed, the App Base URL <strong>cannot be replaced</strong> without paying for a new build.
                                        </div>
                                    </div>

                                    <div class="col-md-12 pt-2"><hr class="text-muted opacity-25"></div>

                                    <div class="col-12">
                                        <label class="form-label">Step 2: Mobile App Services (Optional Add-ons)</label>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="form-check border p-3 rounded-3 h-100">
                                                    <input class="form-check-input addon-trigger" type="checkbox" name="order_apk" id="add_apk" data-price="<?php echo $apk_price ?>" onchange="updateCheckoutTotal()">
                                                    <label class="form-check-label d-block cursor-pointer" for="add_apk">
                                                        <div class="fw-bold text-dark mb-1">Android APK</div>
                                                        <div class="text-primary small">₦<?php echo number_format($apk_price, 2) ?></div>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check border p-3 rounded-3 h-100">
                                                    <input class="form-check-input addon-trigger" type="checkbox" name="order_ios" id="add_ios" data-price="<?php echo $ios_price ?>" onchange="updateCheckoutTotal()">
                                                    <label class="form-check-label d-block cursor-pointer" for="add_ios">
                                                        <div class="fw-bold text-dark mb-1">iOS App</div>
                                                        <div class="text-primary small">₦<?php echo number_format($ios_price, 2) ?></div>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check border p-3 rounded-3 h-100">
                                                    <input class="form-check-input addon-trigger" type="checkbox" name="order_playstore" id="add_playstore" data-price="<?php echo $playstore_price ?>" onchange="updateCheckoutTotal()">
                                                    <label class="form-check-label d-block cursor-pointer" for="add_playstore">
                                                        <div class="fw-bold text-dark mb-1">Play Store Listing</div>
                                                        <div class="text-primary small">₦<?php echo number_format($playstore_price, 2) ?></div>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check border p-3 rounded-3 h-100">
                                                    <input class="form-check-input addon-trigger" type="checkbox" name="order_sms_bridge" id="add_sms_bridge" data-price="<?php echo $sms_bridge_price ?>" onchange="updateCheckoutTotal()">
                                                    <label class="form-check-label d-block cursor-pointer" for="add_sms_bridge">
                                                        <div class="fw-bold text-dark mb-1">SMS Bridge APK</div>
                                                        <div class="text-primary small">₦<?php echo number_format($sms_bridge_price, 2) ?></div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-7">
                                        <label class="form-label">Step 3: Subscription Package</label>
                                        <select name="billing_package_id" id="billing_package_id" class="form-select rounded-3 py-2" required onchange="updateCheckoutTotal()">
                                            <option value="" data-price="0" hidden selected>Choose a package...</option>
                                            <?php
                                                $packages_result = mysqli_query($connection_server, "SELECT * FROM sas_billing_packages ORDER BY price ASC");
                                                while($package = mysqli_fetch_assoc($packages_result)) {
                                                    echo "<option value='{$package['id']}' data-price='{$package['price']}'>".htmlspecialchars($package['name'])." - ₦".number_format($package['price'], 2)." (".$package['duration_days']." Days)</option>";
                                                }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="col-md-5">
                                        <label class="form-label">Payment Method</label>
                                        <div class="d-flex gap-3 pt-1">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" id="paystack" value="paystack" required>
                                                <label class="form-check-label small fw-bold" for="paystack">Online</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" id="bank_deposit" value="bank_deposit">
                                                <label class="form-check-label small fw-bold" for="bank_deposit">Manual</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Secure Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0"><i class="bi bi-lock text-primary"></i></span>
                                            <input id="password-field" type="password" name="pass" class="form-control border-start-0 border-end-0" placeholder="Create account password" required>
                                            <span class="input-group-text bg-white border-start-0 rounded-end-3" style="cursor: pointer;" onclick="togglePasswordVisibility('password-field', this)">
                                                <i class="bi bi-eye text-muted"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="bg-light p-4 rounded-4 border">
                                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                                                <div class="text-center text-md-start">
                                                    <h6 class="fw-bold text-muted mb-1 text-uppercase small">Checkout Summary</h6>
                                                    <h4 class="fw-black text-primary mb-0" id="display_total" style="word-break: break-all;">₦0.00</h4>
                                                </div>
                                                <input type="hidden" name="total_amount" id="total_amount_input" value="0">
                                                <button class="btn btn-primary btn-lg px-5 rounded-pill fw-bold shadow-sm w-100 w-md-auto" type="submit" name="create-profile" id="btn-submit">
                                                    COMPLETE ORDER
                                                </button>
                                            </div>
                                        </div>
                                        <?php if(!$is_admin_session): ?>
                                        <div class="text-center mt-3">
                                            <a href="/" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to Home</a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php if($is_admin_session) include("../func/bc-spadmin-footer.php"); ?>
    <script src="../assets-2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
    let domainFee = 0;

    function useSuggestedDomain(domain) {
        // Split domain and extension
        const parts = domain.split('.');
        const name = parts[0];
        const ext = "." + parts.slice(1).join('.');

        document.getElementById('target_domain').value = name;
        document.getElementById('domain_extension').value = ext;
        lookupDomain();
    }

    function lookupDomain() {
        let domain = document.getElementById('target_domain').value.trim();
        const extension = document.getElementById('domain_extension').value;
        const feedback = document.getElementById('domain_feedback');
        const spinner = document.getElementById('domain_spinner');
        const submitBtn = document.getElementById('btn-submit');

        if (domain === '') {
            feedback.innerHTML = '<div class="text-danger small">Enter a domain name first</div>';
            return;
        }

        // Auto-fix if user included extension
        if (domain.includes('.')) {
            domain = domain.split('.')[0];
            document.getElementById('target_domain').value = domain;
        }

        const fullDomain = domain + extension;
        spinner.classList.remove('d-none');
        feedback.innerHTML = '<div class="text-muted small">Checking availability...</div>';
        submitBtn.disabled = true;

        fetch('ajax-domain-check.php?domain=' + encodeURIComponent(fullDomain))
            .then(response => response.json())
            .then(data => {
                spinner.classList.add('d-none');
                if (data.status === 'available') {
                    domainFee = parseFloat(data.price || 0);
                    document.getElementById('domain_fee_input').value = domainFee;
                    document.getElementById('app_base_url_input').value = fullDomain;
                    feedback.innerHTML = `<div class="mt-2"><strong class="text-success" style="font-size: 1.1rem;">CONGRATULATIONS!!! ${fullDomain.toUpperCase()} IS AVAILABLE FOR REGISTRATION</strong><div class="text-muted small mt-1">Registration Fee: ₦${domainFee.toLocaleString()}</div></div>`;
                    submitBtn.disabled = false;
                } else {
                    domainFee = 0;
                    document.getElementById('domain_fee_input').value = 0;
                    document.getElementById('app_base_url_input').value = "";

                    let html = `<div class="mt-2"><strong class="text-danger" style="font-size: 1.1rem;">${fullDomain.toUpperCase()} IS NOT AVAILABLE OR ALREADY REGISTERED</strong></div>`;

                    if(data.suggestions && data.suggestions.length > 0) {
                        html += `<div class="mt-3"><div class="small fw-bold text-muted mb-2 text-uppercase">Try these instead:</div><div class="d-flex flex-wrap gap-2">`;
                        data.suggestions.forEach(s => {
                            html += `<button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="useSuggestedDomain('${s}')">${s}</button>`;
                        });
                        html += `</div></div>`;
                    }
                    feedback.innerHTML = html;
                    submitBtn.disabled = true;
                }
                updateCheckoutTotal();
            })
            .catch(err => {
                spinner.classList.add('d-none');
                feedback.innerHTML = '<div class="text-danger small">Error connecting to registrar API.</div>';
            });
    }

    function updateCheckoutTotal() {
        const packageSelect = document.getElementById('billing_package_id');
        const basePrice = parseFloat(packageSelect.options[packageSelect.selectedIndex].getAttribute('data-price') || 0);

        let addOnTotal = 0;
        document.querySelectorAll('.addon-trigger:checked').forEach(cb => {
            addOnTotal += parseFloat(cb.getAttribute('data-price') || 0);
        });

        const grandTotal = basePrice + domainFee + addOnTotal;
        document.getElementById('display_total').innerText = "₦" + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('total_amount_input').value = grandTotal;
    }

    function togglePasswordVisibility(fieldId, iconElement) {
        const field = document.getElementById(fieldId);
        const icon = iconElement.querySelector('i');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    </script>
</body>
</html>
