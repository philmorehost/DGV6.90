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
                                    $q_ext = mysqli_query($connection_server, "SELECT price, promo_price FROM sas_domain_extensions WHERE extension='$ext_esc' LIMIT 1");
                                    if($r_ext = mysqli_fetch_assoc($q_ext)) {
                                        $actual_domain_fee = ($r_ext['promo_price'] > 0) ? (float)$r_ext['promo_price'] : (float)$r_ext['price'];
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
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-rgb: 13, 110, 253;
        }
        body { background-color: #f6f9ff; font-family: 'Inter', sans-serif; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #444; margin-bottom: 8px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .card-header { border-bottom: 1px solid #f0f0f0; background: transparent; padding: 1.5rem; }
        .input-group-text { background: #f8f9fa; border-right: none; color: var(--bs-primary); }
        .form-control, .form-select { border-radius: 10px; padding: 0.75rem 1rem; border-color: #e0e0e0; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.1); border-color: var(--bs-primary); }

        /* Addon Cards Redesign */
        .addon-card {
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            padding: 1.25rem;
            height: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            background: #fff;
        }
        .addon-card:hover { border-color: var(--bs-primary); transform: translateY(-3px); }
        .addon-trigger:checked + .addon-card {
            border-color: var(--bs-primary);
            background-color: rgba(var(--bs-primary-rgb), 0.02);
            box-shadow: 0 10px 25px rgba(var(--bs-primary-rgb), 0.1);
        }
        .addon-icon {
            width: 45px;
            height: 45px;
            background: #f0f7ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--bs-primary);
            margin-bottom: 1rem;
        }
        .addon-price {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--bs-primary);
            margin-top: 0.5rem;
        }
        .addon-check {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .addon-trigger:checked + .addon-card .addon-check {
            background: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        .addon-trigger:checked + .addon-card .addon-check i {
            color: #fff;
            font-size: 12px;
        }

        /* Checkout Panel */
        .checkout-summary {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid #eee;
            position: sticky;
            top: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #eee;
        }
        .summary-total {
            font-size: 1.75rem;
            font-weight: 900;
            color: var(--bs-primary);
        }

        /* Responsive Fixes */
        @media (max-width: 768px) {
            .addon-card { padding: 1rem; }
            .addon-icon { width: 35px; height: 35px; font-size: 1.2rem; }
            .summary-total { font-size: 1.5rem; }
            .checkout-summary { margin-top: 2rem; position: static; }
        }

        #btn-submit {
            background-color: var(--bs-primary) !important;
            border: none;
            padding: 1rem 2rem;
            font-weight: 700;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        #btn-submit:hover:not(:disabled) {
            transform: scale(1.02);
            filter: brightness(1.1);
        }
    </style>
</head>
<body>
    <?php if($is_admin_session) include("../func/bc-spadmin-header.php"); ?>

    <div class="container py-4 py-lg-5">
        <?php if($is_admin_session): ?>
        <div class="pagetitle mb-4">
            <h1 class="h3 fw-900 text-dark">Vendor Registration</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Add Vendor</li>
                </ol>
            </nav>
        </div>
        <?php endif; ?>

        <form method="post" action="" id="reg-form">
            <div class="row g-4">
                <!-- Left Column: Form -->
                <div class="col-lg-8">
                    <!-- Section 1: Basic Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-person-circle me-2 text-primary"></i>Personal Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-uppercase small fw-bold">First Name</label>
                                    <input type="text" name="first" class="form-control" placeholder="e.g. John" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-uppercase small fw-bold">Last Name</label>
                                    <input type="text" name="last" class="form-control" placeholder="e.g. Doe" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-uppercase small fw-bold">Email Address</label>
                                    <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-uppercase small fw-bold">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" placeholder="080XXXXXXXX" pattern="[0-9]{11}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-uppercase small fw-bold">Business Address</label>
                                    <textarea name="address" class="form-control" rows="2" placeholder="Full office or home address" required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Domain Setup -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-globe2 me-2 text-primary"></i>App Base URL (Domain)</h5>
                            <span id="domain_spinner" class="spinner-border spinner-border-sm text-primary d-none"></span>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small mb-4">Choose a brand name for your app. This will be your permanent app domain.</p>
                            <div class="row g-2">
                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <span class="input-group-text">https://</span>
                                        <input type="text" id="target_domain" name="website-url" class="form-control border-start-0" placeholder="mybrandname" required>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <select id="domain_extension" class="form-select fw-bold" onchange="updateCheckoutTotal()">
                                            <?php
                                            $ext_res = mysqli_query($connection_server, "SELECT extension FROM sas_domain_extensions ORDER BY extension ASC");
                                            while($ext = mysqli_fetch_assoc($ext_res)) {
                                                echo "<option value='{$ext['extension']}'>{$ext['extension']}</option>";
                                            }
                                            ?>
                                        </select>
                                        <button class="btn btn-primary px-4 fw-bold" type="button" onclick="lookupDomain()"><i class="bi bi-search"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div id="domain_feedback" class="mt-3"></div>
                            <input type="hidden" name="domain_fee" id="domain_fee_input" value="0">
                            <input type="hidden" name="app_base_url" id="app_base_url_input">

                            <div class="mt-4 p-3 bg-light rounded-3 border-start border-4 border-warning">
                                <div class="d-flex">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-3"></i>
                                    <div>
                                        <h6 class="fw-bold mb-1">Important Note</h6>
                                        <p class="small mb-0 text-muted">This domain is hardcoded into your mobile app. While website domains can be mapped later, the core API URL remains fixed to this choice.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: App Addons -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Mobile App Add-ons</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <?php
                                $addons = [
                                    ['id' => 'add_apk', 'name' => 'order_apk', 'label' => 'Android APK', 'price' => $apk_price, 'icon' => 'bi-android2'],
                                    ['id' => 'add_ios', 'name' => 'order_ios', 'label' => 'iOS App', 'price' => $ios_price, 'icon' => 'bi-apple'],
                                    ['id' => 'add_playstore', 'name' => 'order_playstore', 'label' => 'Play Store', 'price' => $playstore_price, 'icon' => 'bi-google-play'],
                                    ['id' => 'add_sms_bridge', 'name' => 'order_sms_bridge', 'label' => 'SMS Bridge', 'price' => $sms_bridge_price, 'icon' => 'bi-chat-dots-fill']
                                ];
                                foreach($addons as $addon):
                                ?>
                                <div class="col-6 col-md-3">
                                    <input class="d-none addon-trigger" type="checkbox" name="<?php echo $addon['name'] ?>" id="<?php echo $addon['id'] ?>" data-price="<?php echo $addon['price'] ?>" onchange="updateCheckoutTotal()">
                                    <label class="addon-card" for="<?php echo $addon['id'] ?>">
                                        <div class="addon-check"><i class="bi bi-check"></i></div>
                                        <div class="addon-icon"><i class="bi <?php echo $addon['icon'] ?>"></i></div>
                                        <div class="fw-bold small text-dark"><?php echo $addon['label'] ?></div>
                                        <div class="addon-price">₦<?php echo number_format($addon['price'], 0) ?></div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Summary -->
                <div class="col-lg-4">
                    <div class="checkout-summary shadow-sm">
                        <h5 class="fw-black mb-4">Order Summary</h5>

                        <div class="mb-4">
                            <label class="form-label text-uppercase small fw-bold">Select Package</label>
                            <select name="billing_package_id" id="billing_package_id" class="form-select bg-light border-0" required onchange="updateCheckoutTotal()">
                                <option value="" data-price="0" hidden selected>Choose Package</option>
                                <?php
                                    $packages_result = mysqli_query($connection_server, "SELECT * FROM sas_billing_packages ORDER BY price ASC");
                                    while($package = mysqli_fetch_assoc($packages_result)) {
                                        echo "<option value='{$package['id']}' data-price='{$package['price']}'>".htmlspecialchars($package['name'])." (₦".number_format($package['price'], 0).")</option>";
                                    }
                                ?>
                            </select>
                        </div>

                        <div id="summary-details">
                            <div class="summary-item">
                                <span class="text-muted">Subscription</span>
                                <span class="fw-bold" id="sum-pkg">₦0.00</span>
                            </div>
                            <div class="summary-item">
                                <span class="text-muted">Domain Fee</span>
                                <span class="fw-bold" id="sum-domain">₦0.00</span>
                            </div>
                            <div class="summary-item">
                                <span class="text-muted">Add-ons</span>
                                <span class="fw-bold" id="sum-addons">₦0.00</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
                            <span class="fw-bold text-dark">TOTAL</span>
                            <span class="summary-total" id="display_total">₦0.00</span>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-uppercase small fw-bold">Payment Method</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="payment_method" id="paystack" value="paystack" required>
                                    <label class="btn btn-outline-primary w-100 fw-bold py-2" for="paystack">ONLINE</label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="payment_method" id="bank_deposit" value="bank_deposit">
                                    <label class="btn btn-outline-primary w-100 fw-bold py-2" for="bank_deposit">MANUAL</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-uppercase small fw-bold">Admin Password</label>
                            <input type="password" name="pass" class="form-control bg-light border-0" placeholder="Set password" required>
                        </div>

                        <input type="hidden" name="total_amount" id="total_amount_input" value="0">
                        <button type="submit" name="create-profile" id="btn-submit" class="btn btn-primary w-100 rounded-pill py-3">
                            COMPLETE ORDER <i class="bi bi-arrow-right-circle ms-2"></i>
                        </button>

                        <?php if(!$is_admin_session): ?>
                        <div class="text-center mt-4">
                            <a href="/" class="text-decoration-none text-muted small fw-bold"><i class="bi bi-house-door me-1"></i> Return Home</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php if($is_admin_session) include("../func/bc-spadmin-footer.php"); ?>

    <script src="../assets-2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
    let domainFee = 0;

    function useSuggestedDomain(domain) {
        const parts = domain.split('.');
        document.getElementById('target_domain').value = parts[0];
        document.getElementById('domain_extension').value = "." + parts.slice(1).join('.');
        lookupDomain();
    }

    function lookupDomain() {
        let domain = document.getElementById('target_domain').value.trim();
        const extension = document.getElementById('domain_extension').value;
        const feedback = document.getElementById('domain_feedback');
        const spinner = document.getElementById('domain_spinner');
        const submitBtn = document.getElementById('btn-submit');

        if (domain === '') {
            feedback.innerHTML = '<div class="alert alert-danger py-2 rounded-3 small">Enter a domain name first</div>';
            return;
        }

        if (domain.includes('.')) {
            domain = domain.split('.')[0];
            document.getElementById('target_domain').value = domain;
        }

        const fullDomain = domain + extension;
        spinner.classList.remove('d-none');
        feedback.innerHTML = '<div class="text-primary small animate-pulse">Checking availability...</div>';
        submitBtn.disabled = true;

        fetch('ajax-domain-check.php?domain=' + encodeURIComponent(fullDomain))
            .then(response => response.json())
            .then(data => {
                spinner.classList.add('d-none');
                if (data.status === 'available') {
                    domainFee = parseFloat(data.price || 0);
                    document.getElementById('domain_fee_input').value = domainFee;
                    document.getElementById('app_base_url_input').value = fullDomain;
                    feedback.innerHTML = `<div class="alert alert-success border-0 shadow-sm rounded-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div><strong>${fullDomain.toUpperCase()}</strong> is available!</div>
                        </div>
                    </div>`;
                    submitBtn.disabled = false;
                } else {
                    domainFee = 0;
                    document.getElementById('domain_fee_input').value = 0;
                    document.getElementById('app_base_url_input').value = "";
                    let html = `<div class="alert alert-danger border-0 rounded-3 small"><strong>${fullDomain.toUpperCase()}</strong> is not available.</div>`;
                    if(data.suggestions && data.suggestions.length > 0) {
                        html += `<div class="small fw-bold text-muted mb-2">TRY THESE:</div><div class="d-flex flex-wrap gap-2">`;
                        data.suggestions.forEach(s => {
                            html += `<button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1 fw-bold" onclick="useSuggestedDomain('${s}')">${s}</button>`;
                        });
                        html += `</div>`;
                    }
                    feedback.innerHTML = html;
                    submitBtn.disabled = true;
                }
                updateCheckoutTotal();
            })
            .catch(err => {
                spinner.classList.add('d-none');
                feedback.innerHTML = '<div class="alert alert-danger py-2 small">Error connecting to registrar API.</div>';
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

        document.getElementById('sum-pkg').innerText = "₦" + basePrice.toLocaleString();
        document.getElementById('sum-domain').innerText = "₦" + domainFee.toLocaleString();
        document.getElementById('sum-addons').innerText = "₦" + addOnTotal.toLocaleString();
        document.getElementById('display_total').innerText = "₦" + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('total_amount_input').value = grandTotal;
    }
    </script>
</body>
</html>
