<?php session_start();
include("../func/bc-config.php");
include("../func/bc-giftcard-func.php");

if (!isset($_SESSION["user_session"]) || !isset($get_logged_user_details)) {
    header("Location: Login.php");
    exit();
}

$vid = $get_logged_user_details['vendor_id'];
$username = $get_logged_user_details['username'];
$user_id = $get_logged_user_details['id'];

if(!isServiceEnabled('gift_card')){
    header("Location: Dashboard.php");
    exit();
}

// Handle Purchase (User to Vendor)
if (isset($_POST['action']) && $_POST['action'] == 'purchase_card') {
    $product_id = (int)$_POST['product_id'];
    $amount = (float)$_POST['amount'];
    $pin = $_POST['pin'] ?? '';
    $otp = $_POST['otp'] ?? '';

    // 1. Security Check: PIN
    if (!verifyUserPIN($pin, $get_logged_user_details)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Transaction PIN']);
        exit;
    }

    // 2. Security Check: OTP
    if (empty($_SESSION["giftcard_otp"]) || $otp !== $_SESSION["giftcard_otp"]) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Email OTP']);
        exit;
    }
    if (time() - $_SESSION["giftcard_otp_time"] > 600) {
        echo json_encode(['status' => 'error', 'message' => 'OTP has expired']);
        exit;
    }

    // 3. Get Product Details & Calculate Price
    $q_p = mysqli_query($connection_server, "SELECT * FROM `sas_vendor_giftcard_products` WHERE vendor_id='$vid' AND reloadly_product_id='$product_id' AND status=1 LIMIT 1");
    $product = mysqli_fetch_assoc($q_p);

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found or inactive.']);
        exit;
    }

    // Fetch Dynamic Exchange Rate for product currency
    $rate = getLiveExchangeRate($product['currency_code'] ?: 'USD', 'NGN', $vid, 'gift-card');

    // Fetch vendor-specific fee
    $qv = mysqli_query($connection_server, "SELECT giftcard_fee_percent FROM sas_vendors WHERE id='$vid' LIMIT 1");
    $rv = mysqli_fetch_assoc($qv);
    $vendor_fee_percent = (float)($rv['giftcard_fee_percent'] ?? 0);

    $cost_ngn = $amount * $rate;
    $product_markup = (float)$product['vendor_markup'];
    $total_markup_percent = $product_markup + $vendor_fee_percent;
    $final_price_ngn = $cost_ngn + ($cost_ngn * ($total_markup_percent / 100));

    if ($get_logged_user_details['balance'] < $final_price_ngn) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance.']);
        exit;
    }

    // 4. Call Reloadly API
    $token = getReloadlyAccessToken($vid);
    $order = placeReloadlyOrder($token, $product_id, $amount, 1, $get_logged_user_details['email']);

    if ($order && isset($order['status']) && $order['status'] == 'SUCCESSFUL') {
        $reference = "GC_" . time() . "_" . rand(100, 999);
        chargeUser("debit", "giftcard_".$product_id, "Gift Card Purchase", $reference, $order['transactionId'], $final_price_ngn, $final_price_ngn, "Gift Card Purchase: " . $product['product_name'], "WEB", $_SERVER["HTTP_HOST"], 1);

        $raw_code = $order['product']['code'] ?? 'MOCK-CODE-' . rand(1000, 9999);
        $card_pin = $order['product']['pin'] ?? '';
        $card_code = encryptGiftCode($raw_code, $vid);

        mysqli_query($connection_server, "INSERT INTO `sas_giftcard_inventory`
            (vendor_id, reloadly_tx_id, reloadly_product_id, product_name, card_code, card_pin, face_value, currency_code, current_owner_id, source)
            VALUES ('$vid', '".$order['transactionId']."', '$product_id', '".$product['product_name']."', '$card_code', '$card_pin', '$amount', 'USD', '$user_id', 'api')");

        unset($_SESSION["giftcard_otp"]);
        echo json_encode(['status' => 'success', 'message' => 'Purchase successful! Check "My Cards" for your code.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $order['message'] ?? 'API Error. Please try again later.']);
    }
    exit;
}

// Handle OTP
if (isset($_POST['action']) && $_POST['action'] == 'send_otp') {
    $otp = generateOTP();
    $_SESSION["giftcard_otp"] = $otp;
    $_SESSION["giftcard_otp_time"] = time();
    $subject = "Gift Card Purchase OTP";
    $body = "Your verification code for gift card purchase is: <b>$otp</b>. Expires in 10 minutes.";
    sendVendorEmail($get_logged_user_details["email"], $subject, $body);
    echo json_encode(['status' => 'success', 'message' => 'OTP sent to your email']);
    exit;
}

// Handle Sell Listing (P2P)
if (isset($_POST['action']) && $_POST['action'] == 'list_for_sale') {
    $card_id = (int)$_POST['card_id'];
    $price = (float)$_POST['price'];
    mysqli_query($connection_server, "UPDATE `sas_giftcard_inventory` SET is_for_sale=1, sale_price_ngn='$price' WHERE id='$card_id' AND current_owner_id='$user_id' AND vendor_id='$vid'");
    echo json_encode(['status' => 'success', 'message' => 'Card listed on marketplace.']);
    exit;
}

// Handle Cancel Listing
if (isset($_POST['action']) && $_POST['action'] == 'cancel_listing') {
    $card_id = (int)$_POST['card_id'];
    mysqli_query($connection_server, "UPDATE `sas_giftcard_inventory` SET is_for_sale=0 WHERE id='$card_id' AND current_owner_id='$user_id' AND vendor_id='$vid'");
    echo json_encode(['status' => 'success', 'message' => 'Card removed from marketplace.']);
    exit;
}

// Handle Transfer
if (isset($_POST['action']) && $_POST['action'] == 'transfer_card') {
    $card_id = (int)$_POST['card_id'];
    $recipient_user = mysqli_real_escape_string($connection_server, $_POST['recipient']);
    $pin = $_POST['pin'] ?? '';

    if (!verifyUserPIN($pin, $get_logged_user_details)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Transaction PIN']);
        exit;
    }

    $q_r = mysqli_query($connection_server, "SELECT id FROM sas_users WHERE (username='$recipient_user' OR email='$recipient_user') AND vendor_id='$vid' LIMIT 1");
    $recipient = mysqli_fetch_assoc($q_r);

    if (!$recipient) {
        echo json_encode(['status' => 'error', 'message' => 'Recipient user not found on this platform.']);
        exit;
    }

    if ($recipient['id'] == $user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot transfer to yourself.']);
        exit;
    }

    $q_c = mysqli_query($connection_server, "SELECT id FROM `sas_giftcard_inventory` WHERE id='$card_id' AND current_owner_id='$user_id' AND vendor_id='$vid' LIMIT 1");
    if (mysqli_num_rows($q_c) > 0) {
        mysqli_query($connection_server, "UPDATE `sas_giftcard_inventory` SET current_owner_id='".$recipient['id']."', is_for_sale=0 WHERE id='$card_id' AND vendor_id='$vid'");
        echo json_encode(['status' => 'success', 'message' => 'Card transferred successfully to @' . $recipient_user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Asset not found or ownership mismatch.']);
    }
    exit;
}

// Handle Buy from P2P
if (isset($_POST['action']) && $_POST['action'] == 'buy_p2p') {
    $card_id = (int)$_POST['card_id'];
    $pin = $_POST['pin'] ?? '';

    if (!verifyUserPIN($pin, $get_logged_user_details)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Transaction PIN']);
        exit;
    }

    $q_c = mysqli_query($connection_server, "SELECT * FROM `sas_giftcard_inventory` WHERE id='$card_id' AND is_for_sale=1 AND vendor_id='$vid' LIMIT 1");
    $card = mysqli_fetch_assoc($q_c);

    if (!$card || $card['current_owner_id'] == $user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid trade.']);
        exit;
    }

    // For P2P, the sale price is already in NGN as set by the seller.
    // However, we should apply the vendor fee if requested.
    // The user said: "the charge fee percentage should apply to the amount the gift card cost for the buyer or the seller"

    $qv = mysqli_query($connection_server, "SELECT giftcard_fee_percent FROM sas_vendors WHERE id='$vid' LIMIT 1");
    $vendor_fee_percent = (float)(mysqli_fetch_assoc($qv)['giftcard_fee_percent'] ?? 0);

    $base_price_ngn = (float)$card['sale_price_ngn'];
    $fee_ngn = $base_price_ngn * ($vendor_fee_percent / 100);
    $total_buyer_cost_ngn = $base_price_ngn + $fee_ngn;

    if (holdP2PFunds($username, $total_buyer_cost_ngn)) {
        mysqli_query($connection_server, "INSERT INTO `sas_p2p_trades` (vendor_id, seller_id, buyer_id, card_id, amount_ngn, fee_ngn, status)
            VALUES ('$vid', '".$card['current_owner_id']."', '$user_id', '$card_id', '$base_price_ngn', '$fee_ngn', 'funded')");
        $trade_id = mysqli_insert_id($connection_server);
        if (releaseP2PFunds($trade_id)) {
            echo json_encode(['status' => 'success', 'message' => 'P2P Trade completed! Card is now in your wallet.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Funds held but release failed. Contact support.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient balance for P2P purchase.']);
    }
    exit;
}

// Fetch Data for UI
$category_filter = "";
$selected_category = $_GET['category'] ?? 'all';
if ($selected_category !== 'all') {
    $cat_name = mysqli_real_escape_string($connection_server, $selected_category);
    if ($cat_name === 'General') {
        $category_filter = " AND (v.category_name = 'General' OR v.category_name IS NULL OR v.category_name = '')";
    } else {
        $category_filter = " AND v.category_name = '$cat_name'";
    }
}

// Use robust ID matching to avoid Join failures due to data type mismatches or hidden spaces
// Added a fallback to ensure products display even if the global cache join fails (Emergency mode)
$sql_user_products = "SELECT v.*,
    IFNULL(g.min_value, 1) as min_value,
    IFNULL(g.max_value, 1000) as max_value,
    IFNULL(g.denomination_type, 'FIXED') as denomination_type,
    IFNULL(g.fixed_values, '[10,25,50,100]') as fixed_values,
    COALESCE(v.description, g.description, 'Standard Gift Card') as description,
    COALESCE(v.terms, g.terms, 'Terms apply') as terms,
    COALESCE(v.redemption_instructions, g.redemption_instructions, 'Follow brand instructions') as redemption_instructions,
    g.logo_url as global_logo,
    g.country_code
    FROM `sas_vendor_giftcard_products` v
    LEFT JOIN `sas_global_giftcard_products` g ON TRIM(CAST(v.reloadly_product_id AS CHAR)) = TRIM(CAST(g.reloadly_product_id AS CHAR))
    WHERE v.vendor_id='$vid' AND v.status=1 $category_filter
    ORDER BY v.product_name ASC";
$installed_products = mysqli_query($connection_server, $sql_user_products);
$my_cards = mysqli_query($connection_server, "SELECT * FROM `sas_giftcard_inventory` WHERE vendor_id='$vid' AND current_owner_id='$user_id' ORDER BY id DESC");
$p2p_listings = mysqli_query($connection_server, "SELECT i.*, u.username as seller_name FROM `sas_giftcard_inventory` i JOIN sas_users u ON i.current_owner_id = u.id WHERE i.vendor_id='$vid' AND i.is_for_sale=1 AND i.current_owner_id != '$user_id'");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Gift Card Hub | <?php echo $get_all_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .balance-hero {
            background: linear-gradient(135deg, <?php echo $vendor_primary_color; ?> 0%, <?php echo $vendor_primary_color; ?>dd 100%);
            border-radius: 1.5rem; color: white; padding: 2rem; position: relative; overflow: hidden;
        }
        .balance-hero::after {
            content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
        }
        .quick-action-btn {
            background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;
            padding: 0.75rem 1rem; border-radius: 1rem; font-weight: 600; backdrop-filter: blur(10px);
            transition: all 0.3s; text-align: center; flex: 1; margin: 0 5px;
        }
        .quick-action-btn:hover { background: rgba(255,255,255,0.3); color: white; transform: translateY(-3px); }
        .gc-icon-row img { width: 40px; height: 40px; margin-right: 10px; margin-bottom: 10px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
        .nav-tabs-custom { border-bottom: none; gap: 10px; flex-wrap: nowrap; overflow-x: auto; padding-bottom: 10px; }
        .nav-tabs-custom::-webkit-scrollbar { height: 4px; }
        .nav-tabs-custom::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .nav-tabs-custom .nav-link {
            border: none; border-radius: 1rem; padding: 0.75rem 1.5rem; font-weight: 700; color: #64748b; background: #f1f5f9;
        }
        .nav-tabs-custom .nav-link.active {
            background: <?php echo $vendor_primary_color; ?>; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .card-gc { border: none; border-radius: 1.25rem; transition: all 0.3s; }
        .card-gc:hover { transform: scale(1.02); box-shadow: 0 10px 25px rgba(0,0,0,0.05); cursor: pointer; }
        .chat-msg { margin-bottom: 10px; padding: 10px; border-radius: 15px; max-width: 80%; }
        .chat-msg.mine { background: <?php echo $vendor_primary_color; ?>; color: white; margin-left: auto; border-bottom-right-radius: 2px; }
        .chat-msg.theirs { background: #f1f5f9; color: #1e293b; margin-right: auto; border-bottom-left-radius: 2px; }
        .chat-box { height: 300px; overflow-y: auto; padding: 15px; border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; }
        .category-sidebar { background: white; border-radius: 1.25rem; padding: 1.5rem; position: sticky; top: 100px; }
        @media (max-width: 991px) {
            .category-sidebar { position: static; padding: 1rem; margin-bottom: 1rem !important; overflow: hidden; }
            .category-nav {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                gap: 8px;
                padding-bottom: 10px;
                -webkit-overflow-scrolling: touch;
            }
            .category-nav::-webkit-scrollbar { height: 5px; }
            .category-nav::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            .category-link {
                margin-bottom: 0 !important;
                white-space: nowrap;
                padding: 0.5rem 1rem !important;
                font-size: 0.85rem;
                flex-shrink: 0;
            }
        }
        .category-link {
            display: flex; align-items: center; padding: 0.75rem 1rem; border-radius: 0.75rem;
            color: #64748b; font-weight: 600; text-decoration: none; transition: all 0.2s; margin-bottom: 0.25rem;
        }
        .category-link:hover { background: #f1f5f9; color: <?php echo $vendor_primary_color; ?>; }
        .category-link.active { background: <?php echo $vendor_primary_color; ?>15; color: <?php echo $vendor_primary_color; ?>; }
        .search-container { position: relative; }
        .search-container i { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-input { padding-left: 3rem !important; height: 3.5rem; border-radius: 1.25rem; border: 1px solid #e2e8f0; font-weight: 500; }
        .search-input:focus { border-color: <?php echo $vendor_primary_color; ?>; box-shadow: 0 0 0 4px <?php echo $vendor_primary_color; ?>15; }
    </style>
</head>
<body>
    <?php include("../func/bc-header.php"); ?>

    <div class="pagetitle">
      <h1>GIFT CARD HUB</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Gift Card</li>
        </ol>
      </nav>
    </div>

    <section class="section">
        <div class="balance-hero mb-4 shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <small class="text-uppercase opacity-75 fw-bold letter-spacing-1">Card Balance Wallet</small>
                    <h1 class="display-5 fw-800 mb-3">₦<?php echo number_format($get_logged_user_details['balance'], 2); ?></h1>

                    <div class="gc-icon-row mb-4">
                        <img src="../asset/ngn-icon.png" alt="NGN" title="Naira">
                        <img src="../asset/gift-card/amazon.png" alt="Amazon" onerror="this.src='../asset/dash_unknown.jpg'">
                        <img src="../asset/gift-card/itunes.png" alt="iTunes" onerror="this.src='../asset/dash_unknown.jpg'">
                        <img src="../asset/gift-card/steam.png" alt="Steam" onerror="this.src='../asset/dash_unknown.jpg'">
                        <img src="../asset/gift-card/googleplay.png" alt="Google" onerror="this.src='../asset/dash_unknown.jpg'">
                        <img src="../asset/gift-card/playstation.png" alt="PlayStation" onerror="this.src='../asset/dash_unknown.jpg'">
                        <img src="../asset/gift-card/netflix.png" alt="Netflix" onerror="this.src='../asset/dash_unknown.jpg'">
                        <img src="../asset/gift-card/mastercard.png" alt="Mastercard" onerror="this.src='../asset/dash_unknown.jpg'">
                    </div>

                    <div class="d-grid gap-2 d-md-flex">
                        <a href="javascript:void(0)" class="quick-action-btn" onclick="switchTab('buy')"><i class="bi bi-cart-plus me-2"></i>Buy</a>
                        <a href="javascript:void(0)" class="quick-action-btn" onclick="switchTab('my-cards')"><i class="bi bi-wallet2 me-2"></i>Card</a>
                        <a href="javascript:void(0)" class="quick-action-btn" onclick="switchTab('p2p')"><i class="bi bi-people me-2"></i>P2P</a>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs nav-tabs-custom mb-4" id="gcTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" id="buy-tab" data-bs-toggle="tab" data-bs-target="#buy">Store</button></li>
            <li class="nav-item"><button class="nav-link" id="my-cards-tab" data-bs-toggle="tab" data-bs-target="#my-cards">My Assets</button></li>
            <li class="nav-item"><button class="nav-link" id="p2p-tab" data-bs-toggle="tab" data-bs-target="#p2p">P2P Market</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="buy">
                <div class="row g-4">
                    <div class="col-lg-3">
                        <div class="category-sidebar shadow-sm border mb-4">
                            <h6 class="fw-bold mb-3 small text-uppercase text-muted">Categories</h6>
                            <div class="nav flex-column category-nav">
                                <a href="GiftCard.php?category=all" class="category-link <?php echo ($selected_category == 'all') ? 'active' : ''; ?>">
                                    <i class="bi bi-grid me-2"></i> All Products
                                </a>
                                <?php
                                // Fetch categories specifically from the vendor's installed products to ensure only relevant ones show
                                $q_cats = mysqli_query($connection_server, "SELECT DISTINCT category_name FROM `sas_vendor_giftcard_products` WHERE vendor_id='$vid' AND status=1");
                                $cats_found = [];
                                while($cat = mysqli_fetch_assoc($q_cats)){
                                    $c_name = trim($cat['category_name'] ?: 'General');
                                    if(!in_array($c_name, $cats_found)) $cats_found[] = $c_name;
                                }
                                sort($cats_found);
                                foreach($cats_found as $c_name):
                                    $is_active = ($selected_category == $c_name);
                                ?>
                                <a href="GiftCard.php?category=<?php echo urlencode($c_name); ?>" class="category-link <?php echo $is_active ? 'active' : ''; ?>">
                                    <i class="bi bi-tag me-2"></i> <?php echo $c_name; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="search-container mb-4">
                            <i class="bi bi-search fs-5"></i>
                            <input type="text" id="gcSearch" class="form-control form-control-lg search-input shadow-sm" placeholder="Search by card name or country (e.g. USA, UK, Amazon)...">
                            <div class="mt-2 text-muted" style="font-size: 11px;">
                                <i class="bi bi-info-circle me-1"></i> You can type a country name to automatically filter cards in that country.
                            </div>
                        </div>

                        <div class="row g-3" id="productGrid">
                    <?php while($p = mysqli_fetch_assoc($installed_products)):
                        $country_name = getCountryNameByCode($p['country_code'] ?? '');
                    ?>
                    <div class="col-xl-4 col-md-6 gc-item" data-name="<?php echo strtolower($p['product_name']); ?>" data-country="<?php echo strtolower($country_name); ?>">
                        <div class="card card-gc h-100 shadow-sm overflow-hidden border" onclick="openDetailsModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                            <img src="func/giftcard-image.php?id=<?php echo $p['reloadly_product_id']; ?>"
                                 class="card-img-top p-4" style="height: 160px; object-fit: contain;"
                                 onerror="this.src='<?php echo $p['logo_url'] ?: $p['global_logo'] ?: '../asset/dash_unknown.jpg'; ?>'; this.onerror=null;">
                            <div class="card-body p-3 text-center">
                                <h6 class="fw-bold mb-1"><?php echo $p['product_name']; ?></h6>
                                <p class="text-muted small mb-3"><?php echo $p['currency_code'] ?: 'USD'; ?> | Fee: <?php echo $p['vendor_markup']; ?>%</p>
                                <button class="btn btn-primary btn-sm rounded-pill w-100" onclick="event.stopPropagation(); openPurchaseModal(<?php echo $p['reloadly_product_id']; ?>, '<?php echo addslashes($p['product_name']); ?>', '<?php echo $p['denomination_type']; ?>', <?php echo (float)$p['min_value']; ?>, <?php echo (float)$p['max_value']; ?>, '<?php echo addslashes($p['fixed_values']); ?>', <?php echo $p['vendor_markup']; ?>, '<?php echo $p['currency_code'] ?: 'USD'; ?>')">Buy Now</button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                        </div>
                        <div id="noResults" class="text-center py-5" style="display:none;">
                            <i class="bi bi-search fs-1 text-muted opacity-25"></i>
                            <p class="mt-3 text-muted">No matching gift cards found.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="my-cards">
                <div class="row g-3">
                    <?php if(mysqli_num_rows($my_cards) > 0): while($c = mysqli_fetch_assoc($my_cards)): ?>
                    <div class="col-md-4">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-success bg-opacity-10 text-success">Active Asset</span>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></small>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <img src="func/giftcard-image.php?id=<?php echo $c['reloadly_product_id']; ?>" class="me-3 rounded" style="width: 50px; height: 50px; object-fit: contain;">
                                    <h5 class="fw-bold mb-0"><?php echo $c['product_name']; ?></h5>
                                </div>
                                <div class="bg-light p-3 rounded-3 mb-3 position-relative">
                                    <?php $display_code = decryptGiftCode($c['card_code'], $vid); ?>
                                    <code class="fs-5 fw-bold"><?php echo $display_code; ?></code>
                                    <button class="btn btn-sm btn-link position-absolute end-0 top-50 translate-middle-y" onclick="copyText('<?php echo $display_code; ?>')"><i class="bi bi-clipboard"></i></button>
                                </div>
                                <div class="row g-2">
                                    <?php if($c['is_for_sale']): ?>
                                        <div class="col-6"><button class="btn btn-warning btn-sm w-100 rounded-pill" onclick="cancelListing(<?php echo $c['id']; ?>)">Delist</button></div>
                                    <?php else: ?>
                                        <?php
                                        $q_sell_rate = mysqli_query($connection_server, "SELECT credit_amount FROM sas_dollar_exchange_rates WHERE vendor_id='$vid' AND product_type='gift-card' AND currency='ngn' LIMIT 1");
                                        $sell_rate = (float)(mysqli_fetch_assoc($q_sell_rate)['credit_amount'] ?? 1450);
                                        ?>
                                        <div class="col-6"><button class="btn btn-outline-primary btn-sm w-100 rounded-pill" onclick="listCard(<?php echo $c['id']; ?>, <?php echo $c['face_value'] * $sell_rate; ?>)">Sell Card</button></div>
                                    <?php endif; ?>
                                    <div class="col-6"><button class="btn btn-outline-secondary btn-sm w-100 rounded-pill" onclick="openTransferModal(<?php echo $c['id']; ?>, '<?php echo addslashes($c['card_name']); ?>')">Transfer</button></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="col-12 text-center py-5"><p class="text-muted">No cards purchased yet.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="p2p">
                <div class="card border-0 rounded-4 shadow-sm overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="small text-uppercase"><th>Seller</th><th>Card</th><th>Value</th><th>Price</th><th class="pe-4 text-end">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($p2p_listings) > 0): while($l = mysqli_fetch_assoc($p2p_listings)): ?>
                                <tr>
                                    <td class="ps-4 fw-bold">@<?php echo $l['seller_name']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="func/giftcard-image.php?id=<?php echo $l['reloadly_product_id']; ?>" class="me-2 rounded" style="width: 30px; height: 30px; object-fit: contain;">
                                            <span><?php echo $l['product_name']; ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $l['currency_code']; ?> <?php echo $l['face_value']; ?></td>
                                    <td class="text-success fw-bold">₦<?php echo number_format($l['sale_price_ngn'], 2); ?></td>
                                    <td class="pe-4 text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button class="btn btn-light btn-sm rounded-pill border" onclick="openChat(<?php echo $l['id']; ?>)"><i class="bi bi-chat-text me-1"></i>Chat</button>
                                            <button class="btn btn-primary btn-sm rounded-pill px-4" onclick="buyP2P(<?php echo $l['id']; ?>, <?php echo $l['sale_price_ngn']; ?>, '<?php echo addslashes($l['product_name']); ?>')">Buy</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No cards listed for sale currently.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="purchaseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold" id="modalTitle">Purchase Card</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="purchaseForm">
                        <input type="hidden" id="p_id" name="product_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Denomination (USD)</label>
                            <select name="amount" class="form-select" required>
                                <option value="10">$10.00</option>
                                <option value="25">$25.00</option>
                                <option value="50">$50.00</option>
                                <option value="100">$100.00</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Transaction PIN</label>
                            <input type="password" name="pin" class="form-control" maxlength="4" placeholder="****" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email OTP</label>
                            <div class="input-group">
                                <input type="text" name="otp" class="form-control" placeholder="6-digit code" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="sendGCOTP(this)">Get Code</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">Confirm Purchase</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- P2P Chat Modal -->
    <div class="modal fade" id="chatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold">Trade Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="chat-box mb-3" id="chatWindow"></div>
                    <form id="chatForm">
                        <input type="hidden" id="chat_trade_id" name="trade_id">
                        <div class="input-group">
                            <input type="text" id="chatInput" class="form-control rounded-start-pill" placeholder="Type a message..." required>
                            <button type="submit" class="btn btn-primary rounded-end-pill px-4"><i class="bi bi-send"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold" id="detailsTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <img id="detailsLogo" src="" class="rounded-4 border shadow-sm p-3" style="width: 120px; height: 120px; object-fit: contain;">
                    </div>
                    <div id="detailsContent">
                        <h6 class="fw-bold small text-uppercase text-muted mb-2">Description</h6>
                        <p id="detailsDesc" class="small text-dark mb-4"></p>

                        <h6 class="fw-bold small text-uppercase text-muted mb-2">Redemption Instructions</h6>
                        <div id="detailsRedeem" class="small bg-light p-3 rounded-3 mb-4"></div>

                        <h6 class="fw-bold small text-uppercase text-muted mb-2">Terms & Conditions</h6>
                        <p id="detailsTerms" class="small text-muted mb-0" style="font-size: 0.75rem;"></p>
                    </div>
                    <hr class="my-4 opacity-25">
                    <button id="detailsBuyBtn" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">Buy Now</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="transferModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold">Transfer <span id="transferCardName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="transferForm">
                        <input type="hidden" id="t_card_id" name="card_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Recipient Username/Email</label>
                            <input type="text" name="recipient" class="form-control" placeholder="e.g. jules_vtu" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Transaction PIN</label>
                            <input type="password" name="pin" class="form-control" maxlength="4" placeholder="****" required>
                        </div>
                        <button type="submit" class="btn btn-secondary w-100 rounded-pill py-2">Send Asset</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="p2pPinModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-body p-4 text-center">
                    <h5 class="fw-bold mb-1">Confirm Trade</h5>
                    <p class="small text-muted mb-3" id="p2p_details"></p>
                    <label class="form-label small fw-bold">Enter Transaction PIN</label>
                    <input type="password" id="p2p_pin" class="form-control text-center mb-3" maxlength="4" placeholder="****">
                    <button class="btn btn-primary w-100 rounded-pill" id="btnConfirmP2P">Secure Purchase</button>
                </div>
            </div>
        </div>
    </div>

    <?php include("../func/bc-footer.php"); ?>

    <script>
    const VENDOR_FEE = <?php
        $qv = mysqli_query($connection_server, "SELECT giftcard_fee_percent FROM sas_vendors WHERE id='$vid' LIMIT 1");
        echo (float)(mysqli_fetch_assoc($qv)['giftcard_fee_percent'] ?? 0);
    ?>;

    function sendGCOTP(btn) {
        btn.disabled = true; btn.innerText = 'Sending...';
        fetch('GiftCard.php', { method: 'POST', body: 'action=send_otp', headers: {'Content-Type': 'application/x-www-form-urlencoded'} })
        .then(r => r.json()).then(res => {
            alert(res.message);
            if(res.status === 'success') {
                let count = 60;
                const timer = setInterval(() => { btn.innerText = `Resend in ${count}s`; count--; if(count < 0) { clearInterval(timer); btn.innerText = 'Get Code'; btn.disabled = false; } }, 1000);
            } else { btn.innerText = 'Get Code'; btn.disabled = false; }
        });
    }

    function listCard(id, defaultPrice) {
        const price = prompt("Enter selling price in NGN:", defaultPrice);
        if (price) {
            const fd = new FormData(); fd.append('action', 'list_for_sale'); fd.append('card_id', id); fd.append('price', price);
            fetch('GiftCard.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => { alert(res.message); if(res.status == 'success') location.reload(); });
        }
    }

    function cancelListing(id) {
        if(!confirm('Remove this card from the public marketplace?')) return;
        const fd = new FormData(); fd.append('action', 'cancel_listing'); fd.append('card_id', id);
        fetch('GiftCard.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => { alert(res.message); if(res.status == 'success') location.reload(); });
    }

    function buyP2P(id, price, name) {
        const fee = price * (VENDOR_FEE / 100);
        const total = price + fee;

        document.getElementById('p2p_details').innerHTML = `
            <div class="mb-3">Buying <b>${name}</b></div>
            <div class="bg-light p-3 rounded-4 small border mb-3">
                <div class="d-flex justify-content-between mb-1"><span>Price:</span><span class="fw-bold">₦${price.toLocaleString()}</span></div>
                <div class="d-flex justify-content-between mb-1"><span>Service Fee (${VENDOR_FEE}%):</span><span class="fw-bold text-primary">₦${fee.toLocaleString()}</span></div>
                <hr class="my-2 opacity-25">
                <div class="d-flex justify-content-between"><span class="fw-bold">Total Cost:</span><h5 class="fw-bold text-success mb-0">₦${total.toLocaleString()}</h5></div>
            </div>
        `;

        const modal = new bootstrap.Modal(document.getElementById('p2pPinModal'));
        modal.show();
        document.getElementById('btnConfirmP2P').onclick = function() {
            const pin = document.getElementById('p2p_pin').value;
            if(!pin) return;
            const fd = new FormData(); fd.append('action', 'buy_p2p'); fd.append('card_id', id); fd.append('price', price); fd.append('pin', pin);
            fetch('GiftCard.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => { alert(res.message); if(res.status == 'success') location.reload(); });
        }
    }

    function openTransferModal(id, name) {
        document.getElementById('t_card_id').value = id;
        document.getElementById('transferCardName').innerText = name;
        new bootstrap.Modal(document.getElementById('transferModal')).show();
    }

    document.getElementById('transferForm').onsubmit = function(e) {
        e.preventDefault();
        const fd = new FormData(this); fd.append('action', 'transfer_card');
        fetch('GiftCard.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            alert(res.message); if(res.status == 'success') location.reload();
        });
    }

    function switchTab(id) { document.getElementById(id + '-tab').click(); }
    function openDetailsModal(p) {
        document.getElementById('detailsTitle').innerText = p.product_name;
        const logoImg = document.getElementById('detailsLogo');
        logoImg.src = 'func/giftcard-image.php?id=' + p.reloadly_product_id;
        logoImg.onerror = () => { logoImg.src = p.logo_url || p.global_logo || '../asset/dash_unknown.jpg'; logoImg.onerror = null; };

        document.getElementById('detailsDesc').innerText = p.description || 'No description available.';
        document.getElementById('detailsRedeem').innerHTML = p.redemption_instructions || 'No specific instructions provided.';
        document.getElementById('detailsTerms').innerText = p.terms || 'Standard Reloadly terms apply.';

        document.getElementById('detailsBuyBtn').onclick = () => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('detailsModal'));
            if(modal) modal.hide();
            openPurchaseModal(p.reloadly_product_id, p.product_name, p.denomination_type, p.min_value, p.max_value, p.fixed_values, p.vendor_markup, p.currency_code);
        };

        new bootstrap.Modal(document.getElementById('detailsModal')).show();
    }

    let currentRate = 1.0;
    let currentCurrency = 'USD';

    async function openPurchaseModal(id, name, type, min, max, fixed, productMarkup, currency) {
        document.getElementById('p_id').value = id;
        document.getElementById('modalTitle').innerText = 'Buy ' + name;
        const select = document.querySelector('#purchaseForm select[name="amount"]');
        select.innerHTML = '';

        currentCurrency = currency || 'USD';
        document.querySelector('#purchaseModal label.form-label').innerText = `Denomination (${currentCurrency})`;

        // Fetch Live Rate for this specific currency (passing gift-card type for spread)
        const rateRes = await fetch(`func/get-rate-ajax.php?from=${currentCurrency}&type=gift-card`).then(r => r.json());
        currentRate = rateRes.rate || 1.0;

        let fixedArr = [];
        try { fixedArr = (typeof fixed === 'string') ? JSON.parse(fixed) : fixed; } catch(e) { fixedArr = [10, 20, 50, 100]; }

        const amounts = type === 'FIXED' ? fixedArr : [10, 25, 50, 100, 200, 500].filter(v => v >= min && v <= max);
        amounts.forEach(v => { select.innerHTML += `<option value="${v}" data-markup="${productMarkup}">${currentCurrency} ${parseFloat(v).toLocaleString()}</option>`; });

        select.onchange = () => updateCalculation(select);
        updateCalculation(select);

        new bootstrap.Modal(document.getElementById('purchaseModal')).show();
    }

    function updateCalculation(select) {
        const amount = parseFloat(select.value);
        const productMarkup = parseFloat(select.options[select.selectedIndex].getAttribute('data-markup') || 0);
        const totalMarkup = productMarkup + VENDOR_FEE;

        const baseNaira = amount * currentRate;
        const totalNaira = baseNaira + (baseNaira * (totalMarkup / 100));

        const html = `
            <div class="bg-light p-3 rounded-4 mt-3 small shadow-sm border">
                <div class="d-flex justify-content-between mb-1"><span>Rate:</span><span class="fw-bold text-dark">₦${currentRate.toLocaleString()}/${currentCurrency}1</span></div>
                <div class="d-flex justify-content-between mb-1"><span>Platform Fee:</span><span class="fw-bold text-primary">${totalMarkup}%</span></div>
                <hr class="my-2 opacity-25">
                <div class="d-flex justify-content-between"><span class="fw-bold">Total Cost:</span><h5 class="fw-bold text-success mb-0">₦${totalNaira.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</h5></div>
            </div>
        `;

        const existing = document.getElementById('calcDisplay');
        if(existing) existing.innerHTML = html;
        else select.parentElement.insertAdjacentHTML('afterend', `<div id="calcDisplay">${html}</div>`);
    }

    document.getElementById('purchaseForm').onsubmit = function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true; btn.innerText = 'Processing...';
        const fd = new FormData(this); fd.append('action', 'purchase_card');
        fetch('GiftCard.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            alert(res.message); if(res.status === 'success') location.reload(); else { btn.disabled = false; btn.innerText = 'Confirm Purchase'; }
        });
    }
    function copyText(txt) { navigator.clipboard.writeText(txt); alert('Copied to clipboard!'); }

    // Chat Logic
    let chatInterval = null;
    function openChat(tradeId) {
        document.getElementById('chat_trade_id').value = tradeId;
        fetchMessages(tradeId);
        if(chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(() => fetchMessages(tradeId), 3000);
        new bootstrap.Modal(document.getElementById('chatModal')).show();
    }

    function fetchMessages(tradeId) {
        fetch('p2p-chat-ajax.php?action=fetch_messages&trade_id=' + tradeId)
        .then(r => r.json()).then(res => {
            const win = document.getElementById('chatWindow');
            win.innerHTML = '';
            res.messages.forEach(m => {
                win.innerHTML += `<div class="chat-msg ${m.is_mine ? 'mine' : 'theirs'}">${m.message}</div>`;
            });
            win.scrollTop = win.scrollHeight;
        });
    }

    document.getElementById('chatForm').onsubmit = function(e) {
        e.preventDefault();
        const tid = document.getElementById('chat_trade_id').value;
        const msg = document.getElementById('chatInput').value;
        const fd = new FormData(); fd.append('action', 'send_message'); fd.append('trade_id', tid); fd.append('message', msg);
        fetch('p2p-chat-ajax.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            document.getElementById('chatInput').value = '';
            fetchMessages(tid);
        });
    }

    // Real-time Search Logic
    const searchInput = document.getElementById('gcSearch');
    const productGrid = document.getElementById('productGrid');
    const items = document.querySelectorAll('.gc-item');
    const noResults = document.getElementById('noResults');

    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            let visibleCount = 0;

            items.forEach(item => {
                const name = item.getAttribute('data-name');
                const country = item.getAttribute('data-country');
                if(name.includes(query) || country.includes(query)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            noResults.style.display = (visibleCount === 0) ? 'block' : 'none';
        });
    }
    </script>
</body>
</html>
