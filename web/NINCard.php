<?php session_start();
include("../func/bc-config.php");
include_once("../func/bc-func.php");

// Guard: NIN card must be enabled for this vendor
if (!isServiceEnabled('nin_card')) {
    $_SESSION["product_purchase_response"] = "NIN Card service is not available on this platform.";
    header("Location: PrintHub.php");
    exit();
}

$nin_vendor = mysqli_fetch_array(mysqli_query($connection_server, "SELECT nin_card_fee, nin_card_fee_agent, nin_card_fee_api, identity_provider FROM sas_vendors WHERE id='".$get_logged_user_details["vendor_id"]."' LIMIT 1"));

// Determine fee by account level
$acc_level = $get_logged_user_details["account_level"];
if ($acc_level == 3) $service_fee = (float)$nin_vendor['nin_card_fee_api'];
elseif ($acc_level == 2) $service_fee = (float)$nin_vendor['nin_card_fee_agent'];
else $service_fee = (float)$nin_vendor['nin_card_fee'];

if (isset($_POST["request-nin-card"])) {
    $nin_input = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["nin_number"] ?? "")));

    if (empty($nin_input) || !ctype_digit($nin_input) || strlen($nin_input) !== 11) {
        $_SESSION["product_purchase_response"] = "Please enter a valid 11-digit NIN.";
        header("Location: NINCard.php");
        exit();
    }

    if (userBalance(1) < $service_fee) {
        $_SESSION["product_purchase_response"] = "Insufficient wallet balance. You need ₦" . number_format($service_fee, 2);
        header("Location: NINCard.php");
        exit();
    }

    $reference = "NIN" . strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 12));

    // Debit wallet first
    $debit = chargeUser("debit", $reference, "NIN Slip", $reference, "", $service_fee, $service_fee, "Digital NIN Slip for NIN: " . substr($nin_input, 0, 3) . "****" . substr($nin_input, -2), "WEB", $_SERVER["HTTP_HOST"], 1);

    if ($debit !== "success") {
        $_SESSION["product_purchase_response"] = "Transaction failed. Please try again.";
        header("Location: NINCard.php");
        exit();
    }

    // Fetch NIN profile from provider
    $profile = fetchNINProfile($nin_input, $get_logged_user_details["vendor_id"]);

    if ($profile['status'] !== 'success') {
        // Refund on API failure
        chargeUser("credit", $reference . "_REFUND", "NIN Slip Refund", $reference . "_RF", "", $service_fee, $service_fee, "Refund: NIN Slip API error - " . ($profile['message'] ?? 'Unknown error'), "WEB", $_SERVER["HTTP_HOST"], 1);
        $_SESSION["product_purchase_response"] = "NIN lookup failed: " . ($profile['message'] ?? 'Please try again.');
        header("Location: NINCard.php");
        exit();
    }

    // Store request record
    $firstname   = mysqli_real_escape_string($connection_server, $profile['firstname'] ?? '');
    $middlename  = mysqli_real_escape_string($connection_server, $profile['middlename'] ?? '');
    $lastname    = mysqli_real_escape_string($connection_server, $profile['lastname'] ?? '');
    $birthdate   = mysqli_real_escape_string($connection_server, $profile['birthdate'] ?? '');
    $gender      = mysqli_real_escape_string($connection_server, $profile['gender'] ?? '');
    $photo_data  = mysqli_real_escape_string($connection_server, $profile['photo_data'] ?? '');
    $phone       = mysqli_real_escape_string($connection_server, $profile['phone'] ?? '');
    $address     = mysqli_real_escape_string($connection_server, $profile['address'] ?? '');
    $res_state   = mysqli_real_escape_string($connection_server, $profile['residence_state'] ?? '');
    $soo         = mysqli_real_escape_string($connection_server, $profile['state_of_origin'] ?? '');
    $provider    = mysqli_real_escape_string($connection_server, $profile['provider'] ?? '');

    mysqli_query($connection_server, "INSERT INTO sas_nin_card_requests
        (vendor_id, user_id, reference, nin_input, firstname, middlename, lastname, birthdate, gender, photo_data, phone, address, residence_state, state_of_origin, price, provider, status)
        VALUES
        ('".$get_logged_user_details["vendor_id"]."', '".$get_logged_user_details["id"]."', '$reference', '$nin_input',
         '$firstname', '$middlename', '$lastname', '$birthdate', '$gender', '$photo_data', '$phone', '$address', '$res_state', '$soo',
         '$service_fee', '$provider', 'success')");

    header("Location: ViewNINCard.php?ref=$reference");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NIN Slip | <?php echo $get_all_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-header.php"); ?>

    <div class="pagetitle">
        <h1>NIN SLIP</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
                <li class="breadcrumb-item"><a href="PrintHub.php">Print Hub</a></li>
                <li class="breadcrumb-item active">NIN Slip</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <?php include("../func/service-header.php"); ?>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 p-4 rounded-4">
                    <div class="text-center mb-4">
                        <div style="width:64px;height:64px;border-radius:1.25rem;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h5 class="fw-bold mb-1">Digital NIN Slip</h5>
                        <p class="text-muted small mb-0">Enter an 11-digit NIN to generate a printable digital slip.</p>
                    </div>

                    <div class="alert alert-info border-0 small rounded-3 mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Service fee: <strong>₦<?php echo number_format($service_fee, 2); ?></strong> per request.
                        Your balance: <strong>₦<?php echo number_format(userBalance(1), 2); ?></strong>
                    </div>

                    <form method="post" action="">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase">NIN Number (11 digits)</label>
                            <input type="text" name="nin_number" class="form-control form-control-lg"
                                   placeholder="e.g. 12345678901" maxlength="11" pattern="[0-9]{11}"
                                   inputmode="numeric" required autocomplete="off">
                            <div class="form-text">Enter the full 11-digit National Identification Number.</div>
                        </div>

                        <button type="submit" name="request-nin-card" class="btn btn-success btn-lg w-100 rounded-pill fw-bold shadow-sm">
                            <i class="bi bi-person-badge me-2"></i>Generate NIN Slip — ₦<?php echo number_format($service_fee, 2); ?>
                        </button>
                    </form>
                </div>

                <div class="mt-3 text-center">
                    <a href="NINCardHistory.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                        <i class="bi bi-clock-history me-1"></i> View NIN Slip History
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include("../func/bc-footer.php"); ?>
</body>
</html>
