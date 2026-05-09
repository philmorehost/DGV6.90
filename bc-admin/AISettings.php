<?php session_start();
include("../func/bc-admin-config.php");
include_once("../func/bc-ai-engine.php");

$vid = $get_logged_admin_details["id"];
$esc_vid = (int)$vid;

// Quick DB Migration for Voice-to-VTU threshold
$check_col = mysqli_query($connection_server, "SHOW COLUMNS FROM sas_vendors LIKE 'ai_voice_min_tx'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($connection_server, "ALTER TABLE sas_vendors ADD COLUMN ai_voice_min_tx INT DEFAULT 50");
}
$check_col2 = mysqli_query($connection_server, "SHOW COLUMNS FROM sas_users LIKE 'ai_voice_status'");
if (mysqli_num_rows($check_col2) == 0) {
    mysqli_query($connection_server, "ALTER TABLE sas_users ADD COLUMN ai_voice_status TINYINT DEFAULT 0");
}
$check_col3 = mysqli_query($connection_server, "SHOW COLUMNS FROM sas_vendors LIKE 'ai_voice_fee_tokens'");
if (mysqli_num_rows($check_col3) == 0) {
    mysqli_query($connection_server, "ALTER TABLE sas_vendors ADD COLUMN ai_voice_fee_tokens INT DEFAULT 50");
}


// Handle AI Activation Request
if (isset($_POST['request-ai-activation'])) {
    bc_validate_csrf();
    $package = $_POST['token_package'] ?? 'starter';
    $packages = [
        'starter' => ['tokens' => 5000, 'price' => 500],
        'business' => ['tokens' => 20000, 'price' => 1800],
        'scale' => ['tokens' => 100000, 'price' => 7500]
    ];
    
    $p = $packages[$package] ?? $packages['starter'];
    $tokens = $p['tokens'];
    $cost = $p['price'];
    
    if ($get_logged_admin_details['balance'] >= $cost) {
        $ref = "AIREQ_" . time();
        $desc = "AI Activation Request ($tokens tokens)";
        $charge = chargeVendor("debit", "ai_activation", "AI Suite", $ref, $cost, $cost, $desc, $_SERVER["HTTP_HOST"], 1);
        
        if ($charge === 'success') {
            mysqli_query($connection_server, "UPDATE sas_vendors SET ai_request_status='pending', ai_pending_cost='$cost', ai_pending_tokens='$tokens' WHERE id='$esc_vid'");
            
            // Notify Super Admin
            $sa_email = "admin@" . explode(':', $_SERVER['HTTP_HOST'])[0]; // Fallback
            $site_name = $get_all_super_admin_site_details['site_title'];
            $msg = "New AI Feature activation request from: " . $get_logged_admin_details['company_name'] . "\nPackage: " . number_format($tokens) . " tokens\nAmount: ₦" . number_format($cost, 2);
            
            // WhatsApp Alert
            $sa_wa = getSuperAdminOption('ai_whatsapp_number', '');
            if(!empty($sa_wa)) sendWhatsAppAlert($sa_wa, "🤖 *AI Activation Request*\n\n" . $msg, 'admin_alert');
            
            $_SESSION['product_purchase_response'] = "✅ Request submitted! Super Admin has been notified for approval.";
        } else {
            $_SESSION['product_purchase_response'] = "❌ Transaction failed. Please try again.";
        }
    } else {
        $_SESSION['product_purchase_response'] = "❌ Insufficient balance to process AI activation. Please fund your wallet first.";
    }
    header("Location: AISettings.php");
    exit();
}

// ── Handle: Buy AI Tokens ───────────────────────────────────
if (isset($_POST["buy-ai-tokens"])) {
    bc_validate_csrf();
    $token_amount = (int)($_POST["token_amount"] ?? 0);
    $price_per_1k = (float)($get_logged_admin_details["ai_price_per_1k_tokens"] ?? 100.00);
    $cost = ($token_amount / 1000) * $price_per_1k;

    if ($token_amount >= 100 && $cost > 0 && $get_logged_admin_details["balance"] >= $cost) {
        $ref = "AITKN_" . time() . rand(10, 99);
        $desc = "Purchase of $token_amount AI Tokens";
        $charge = chargeVendor("debit", "ai_tokens", "AI Tokens", $ref, $cost, $cost, $desc, $_SERVER["HTTP_HOST"], 1);
        if ($charge === "success") {
            $new_bal = (int)$get_logged_admin_details["ai_token_balance"] + $token_amount;
            mysqli_query($connection_server, "UPDATE sas_vendors SET ai_token_balance='$new_bal' WHERE id='$esc_vid'");
            // Propagate to all users under this vendor who have AI enabled
            mysqli_query($connection_server, "UPDATE sas_users SET ai_token_balance=ai_token_balance+$token_amount WHERE vendor_id='$esc_vid' AND ai_status=1");
            $_SESSION["product_purchase_response"] = "✅ $token_amount AI Tokens purchased successfully!";
        } else {
            $_SESSION["product_purchase_response"] = "❌ Token purchase failed. Check your balance.";
        }
    } else {
        $_SESSION["product_purchase_response"] = "❌ Minimum purchase is 100 tokens. Ensure you have sufficient balance.";
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit();
}

// ── Handle: Toggle AI On/Off ─────────────────────────────────
if (isset($_POST["toggle-ai"])) {
    bc_validate_csrf();
    $new_status = (int)($_POST["ai_status"] ?? 0) === 1 ? 1 : 0;
    mysqli_query($connection_server, "UPDATE sas_vendors SET ai_status='$new_status' WHERE id='$esc_vid'");
    // Also update the vendor's own user row if they have one
    $esc_email = mysqli_real_escape_string($connection_server, $get_logged_admin_details["email"]);
    mysqli_query($connection_server, "UPDATE sas_users SET ai_status='$new_status' WHERE vendor_id='$esc_vid' AND email='$esc_email'");
    $_SESSION["product_purchase_response"] = "AI features " . ($new_status ? "enabled" : "disabled") . ".";
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit();
}

// ── Handle: Add VIP Whitelist ─────────────────────────────────
if (isset($_POST["add-whitelist"])) {
    bc_validate_csrf();
    $pid    = bc_sanitize($_POST["product_id"] ?? '');
    $limit  = bc_sanitize_number($_POST["limit_override"] ?? 0);
    $expiry = bc_sanitize($_POST["expiry"] ?? '');
    if (!empty($pid)) {
        $esc_pid = mysqli_real_escape_string($connection_server, $pid);
        $esc_exp = !empty($expiry) ? "'" . mysqli_real_escape_string($connection_server, $expiry) . "'" : "NULL";
        mysqli_query($connection_server,
            "INSERT INTO sas_customer_whitelist (vendor_id, product_id, is_whitelisted, daily_limit_override, override_expiry)
             VALUES ('$esc_vid', '$esc_pid', 1, '$limit', $esc_exp)
             ON DUPLICATE KEY UPDATE is_whitelisted=1, daily_limit_override='$limit', override_expiry=$esc_exp"
        );
        $_SESSION["product_purchase_response"] = "✅ VIP entry added for: $pid";
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit();
}

// ── Handle: Remove Whitelist ──────────────────────────────────
if (isset($_GET["remove-whitelist"])) {
    $pid = bc_sanitize($_GET["remove-whitelist"]);
    $esc_pid = mysqli_real_escape_string($connection_server, $pid);
    mysqli_query($connection_server, "DELETE FROM sas_customer_whitelist WHERE vendor_id='$esc_vid' AND product_id='$esc_pid'");
    $_SESSION["product_purchase_response"] = "VIP entry removed.";
    header("Location: AISettings.php");
    exit();
}

$voice_min_tx = (int)($get_logged_admin_details['ai_voice_min_tx'] ?? 50);
$voice_fee_tokens = (int)($get_logged_admin_details['ai_voice_fee_tokens'] ?? 50);

// Handle Voice Settings Update
if (isset($_POST['set-voice-limit'])) {
    bc_validate_csrf();
    $new_min = (int)$_POST['ai_voice_min_tx'];
    $new_fee = (int)$_POST['ai_voice_fee_tokens'];
    mysqli_query($connection_server, "UPDATE sas_vendors SET ai_voice_min_tx='$new_min', ai_voice_fee_tokens='$new_fee' WHERE id='$esc_vid'");
    $_SESSION['product_purchase_response'] = "✅ Voice settings updated.";
    header("Location: AISettings.php");
    exit();
}

// ── Load data ─────────────────────────────────────────────────
$ai_status  = (int)($get_logged_admin_details["ai_status"] ?? 0);
// Check for ai_status in sas_vendors (add column check)
$vendor_ai_check = mysqli_query($connection_server, "SHOW COLUMNS FROM sas_vendors LIKE 'ai_status'");
if (!$vendor_ai_check || mysqli_num_rows($vendor_ai_check) == 0) {
    // Column not yet migrated - default to 0
    $ai_status = 0;
}

$token_bal  = (int)($get_logged_admin_details["ai_token_balance"] ?? 0);
$price_1k   = (float)($get_logged_admin_details["ai_price_per_1k_tokens"] ?? 100.00);
$model_raw  = $get_logged_admin_details["ai_model_assigned"] ?? "";
$ai_engine  = ai_engine();
if (empty($model_raw) || !$ai_engine->isModelCompatible($model_raw)) {
    $model = $ai_engine->getDefaultModel();
} else {
    $model = $model_raw;
}

// AI usage history (last 20)
$tx_q = mysqli_query($connection_server,
    "SELECT * FROM sas_ai_transactions WHERE vendor_id='$esc_vid' ORDER BY created_at DESC LIMIT 20"
);

// VIP Whitelist
$wl_q = mysqli_query($connection_server,
    "SELECT * FROM sas_customer_whitelist WHERE vendor_id='$esc_vid' ORDER BY created_at DESC LIMIT 50"
);

// Sentinel flagged events (last 10)
$flags_q = mysqli_query($connection_server,
    "SELECT * FROM sas_ai_audit_log WHERE actor LIKE '$esc_vid:%' AND event_type='SENTINEL_FLAGGED' ORDER BY created_at DESC LIMIT 10"
);

// Token usage stats this month
$usage_q = mysqli_query($connection_server,
    "SELECT SUM(tokens_burned) as used, COUNT(*) as calls FROM sas_ai_transactions
     WHERE vendor_id='$esc_vid' AND MONTH(created_at)=MONTH(NOW()) AND status='success'"
);
$usage = $usage_q ? mysqli_fetch_assoc($usage_q) : ['used' => 0, 'calls' => 0];

// Voice Settings
$voice_min_tx = (int)($get_logged_admin_details["ai_voice_min_tx"] ?? 50);
$voice_apps_q = mysqli_query($connection_server, "SELECT id, username, email, phone_number, ai_voice_status FROM sas_users WHERE vendor_id='$esc_vid' AND ai_voice_status IN (1,2) ORDER BY ai_voice_status ASC, id DESC LIMIT 20");

// Real-Time Intelligence Hub Data (Vendor Context)
$top_consumers_q = mysqli_query($connection_server, 
    "SELECT username, SUM(tokens_burned) as total FROM sas_ai_transactions 
     WHERE vendor_id='$esc_vid' AND MONTH(created_at)=MONTH(NOW()) AND status='success' 
     GROUP BY username ORDER BY total DESC LIMIT 5"
);
$recent_intelligence_q = mysqli_query($connection_server, 
    "SELECT * FROM sas_ai_transactions WHERE vendor_id='$esc_vid' ORDER BY id DESC LIMIT 5"
);

// Live Health Metrics (Vendor Context)
$health_q = mysqli_query($connection_server, "SELECT AVG(duration_ms) as avg_lat FROM sas_ai_transactions WHERE vendor_id='$esc_vid' AND status='success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$health = ($health_q) ? mysqli_fetch_assoc($health_q) : ['avg_lat' => 0];
$v_avg_latency = ($health && $health['avg_lat'] > 0) ? round($health['avg_lat']) : 450;

$blocked_q = mysqli_query($connection_server, "SELECT COUNT(*) as blocked FROM sas_ai_transactions WHERE vendor_id='$esc_vid' AND status='blocked' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$v_blocked_count = ($blocked_q && $row_b = mysqli_fetch_assoc($blocked_q)) ? $row_b['blocked'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Business Suite | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        :root { --ai-purple: #7c3aed; --ai-blue: #2563eb; --ai-glow: rgba(124,58,237,0.15); }
        .ai-hero {
            background: linear-gradient(135deg, var(--ai-purple) 0%, var(--ai-blue) 100%);
            border-radius: 1.5rem; color: #fff; padding: 2rem; position: relative; overflow: hidden;
        }
        .ai-hero::before {
            content: ''; position: absolute; top: -50%; right: -20%;
            width: 400px; height: 400px; background: rgba(255,255,255,0.07);
            border-radius: 50%; pointer-events: none;
        }
        .token-badge {
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px); border-radius: 1rem; padding: 1rem;
        }
        .ai-card { border: none; border-radius: 1.25rem; box-shadow: 0 2px 20px rgba(0,0,0,0.06); }
        .ai-pill-btn {
            background: linear-gradient(135deg, var(--ai-purple), var(--ai-blue));
            border: none; border-radius: 2rem; color: #fff; font-weight: 700;
            padding: 0.6rem 2rem; transition: opacity 0.2s;
        }
        .ai-pill-btn:hover { opacity: 0.88; color: #fff; }
        .sentinel-flag { border-left: 4px solid #f59e0b; background: #fffbeb; border-radius: 0.75rem; }
        .model-badge { background: var(--ai-glow); color: var(--ai-purple); font-weight: 700; border-radius: 2rem; padding: 0.25rem 0.85rem; font-size: 0.78rem; }
        .pulse-ring { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(124,58,237,0.4)} 50%{box-shadow:0 0 0 10px rgba(124,58,237,0)} }
        .lock-overlay { background: rgba(255,255,255,0.8); backdrop-filter: blur(4px); border-radius: 1rem; }
    </style>
</head>
<body>
<?php include("../func/bc-admin-header.php"); ?>

<div class="pagetitle">
    <h1>AI BUSINESS SUITE</h1>
    <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">AI Settings</li>
    </ol></nav>
</div>

<section class="section">

<?php if (isset($_SESSION["product_purchase_response"])): ?>
    <div class="alert alert-info alert-dismissible fade show rounded-4 shadow-sm">
        <?php echo $_SESSION["product_purchase_response"]; unset($_SESSION["product_purchase_response"]); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- AI Hero Banner -->
<div class="ai-hero mb-4 shadow">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3">
                    <i class="bi bi-cpu-fill fs-3"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0">AI Business Suite</h4>
                    <?php 
                        $ai_prov = ai_engine()->getProvider();
                    ?>
                    <small class="opacity-75">Powered by <?php echo ucfirst($ai_prov); ?> · Cloud · High Speed</small>
                </div>
            </div>
            <p class="opacity-90 mb-3">Unlock AI-powered transaction security, smart business guides, marketing copy generation, and voice-to-VTU commands. Powered by state-of-the-art cloud intelligence.</p>
            <form method="post" class="d-inline">
                <?php echo bc_csrf_field(); ?>
                <input type="hidden" name="ai_status" value="<?php echo $ai_status ? 0 : 1; ?>">
                <button type="submit" name="toggle-ai" id="ai-toggle-btn"
                    class="btn fw-bold rounded-pill px-4 py-2 <?php echo $ai_status ? 'btn-warning' : 'btn-light text-primary'; ?>">
                    <i class="bi bi-<?php echo $ai_status ? 'pause-fill' : 'play-fill'; ?> me-1"></i>
                    <?php echo $ai_status ? 'Disable AI Features' : 'Enable AI Features'; ?>
                </button>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="row g-3">
                <div class="col-6">
                    <div class="token-badge text-center">
                        <div class="fw-bold" style="font-size:1.8rem;"><?php echo number_format($token_bal); ?></div>
                        <div class="small opacity-80">Token Balance</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="token-badge text-center">
                        <div class="fw-bold" style="font-size:1.8rem;"><?php echo number_format((int)$usage['calls']); ?></div>
                        <div class="small opacity-80">Calls This Month</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="token-badge d-flex justify-content-between align-items-center">
                        <span class="small opacity-80">Active Model</span>
                        <span class="model-badge bg-white text-primary"><?php echo htmlspecialchars($model); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI Activation Request / Approval UI -->
<?php 
$req_status = $get_logged_admin_details['ai_request_status'] ?? NULL;
if ($ai_status == 0 && ($req_status === NULL || $req_status === 'rejected')): 
?>
<div class="card ai-card shadow-lg mb-4 border-0 pulse-ring">
    <div class="card-body p-4 p-lg-5 text-center">
        <div class="stat-icon bg-purple-light mx-auto mb-4" style="width:80px; height:80px; font-size:2.5rem;"><i class="bi bi-cpu-fill"></i></div>
        <h2 class="fw-bold">Unlock the AI Intelligence Suite</h2>
        <p class="text-muted mx-auto" style="max-width: 600px;">Empower your platform with automated transaction monitoring, voice commands, and AI marketing tools. Choose a starting package to request activation.</p>
        
        <form method="POST" class="mt-4">
            <?php echo bc_csrf_field(); ?>
            <div class="row justify-content-center g-3 mb-4">
                <div class="col-md-4">
                    <label class="card p-3 h-100 cursor-pointer border">
                        <input type="radio" name="token_package" value="starter" class="form-check-input mb-2" checked>
                        <div class="fw-bold">Starter</div>
                        <div class="small text-muted">5,000 Tokens</div>
                        <div class="fs-4 fw-bold mt-2 text-primary">₦500</div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="card p-3 h-100 cursor-pointer border">
                        <input type="radio" name="token_package" value="business" class="form-check-input mb-2">
                        <div class="fw-bold">Business</div>
                        <div class="small text-muted">20,000 Tokens</div>
                        <div class="fs-4 fw-bold mt-2 text-primary">₦1,800</div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="card p-3 h-100 cursor-pointer border">
                        <input type="radio" name="token_package" value="scale" class="form-check-input mb-2">
                        <div class="fw-bold">Scale</div>
                        <div class="small text-muted">100,000 Tokens</div>
                        <div class="fs-4 fw-bold mt-2 text-primary">₦7,500</div>
                    </label>
                </div>
            </div>
            
            <div class="d-flex justify-content-center gap-3">
                <button type="submit" name="request-ai-activation" class="btn ai-pill-btn px-5 py-3">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Request Activation
                </button>
                <a href="Fund.php" class="btn btn-outline-primary rounded-pill px-4 py-3 fw-bold">
                    <i class="bi bi-plus-circle me-1"></i> Add Funds
                </a>
            </div>
            <p class="small text-muted mt-3"><i class="bi bi-info-circle me-1"></i> Payment will be deducted from your main wallet balance.</p>
        </form>
    </div>
</div>
<?php elseif ($req_status === 'pending'): ?>
<div class="card ai-card shadow-sm mb-4 border-0 bg-light">
    <div class="card-body p-5 text-center">
        <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;"></div>
        <h3 class="fw-bold">Request Pending Approval</h3>
        <p class="text-muted">Your AI Suite activation request is currently being reviewed by the Super Admin. You will be notified once it is approved.</p>
        <div class="badge bg-warning text-dark px-3 py-2 rounded-pill">Status: Awaiting Approval</div>
    </div>
</div>
<?php endif; ?>

<!-- AI Disabled Warning -->
<?php if (!$ai_status && $req_status === 'approved'): ?>
<div class="alert alert-warning border-0 rounded-4 d-flex align-items-center shadow-sm mb-4">
    <i class="bi bi-info-circle-fill me-3 fs-4 text-warning"></i>
    <div>
        <strong>AI Suite is Approved but Disabled.</strong> Enable it above to start using the features.
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- BUY TOKENS -->
    <div class="col-lg-5">
        <div class="card ai-card h-100">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-coin me-2 text-warning"></i>Buy AI Tokens</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-light border rounded-4 mb-3">
                    <div class="d-flex justify-content-between small fw-bold">
                        <span>Rate</span><span class="text-primary">₦<?php echo number_format($price_1k, 2); ?> / 1,000 tokens</span>
                    </div>
                    <div class="d-flex justify-content-between small fw-bold mt-1">
                        <span>Cost per AI call</span><span class="text-danger"><?php echo (int)($get_logged_admin_details['ai_per_tx_cost'] ?? 5); ?> tokens</span>
                    </div>
                    <div class="d-flex justify-content-between small fw-bold mt-1">
                        <span>Your Wallet Balance</span><span>₦<?php echo number_format($get_logged_admin_details['balance'], 2); ?></span>
                    </div>
                </div>
                <form method="post" id="buy-tokens-form">
                    <?php echo bc_csrf_field(); ?>
                    <label class="form-label small fw-bold text-muted text-uppercase">Tokens to Buy</label>
                    <input type="number" name="token_amount" id="token_amount_inp" class="form-control form-control-lg rounded-3 mb-2"
                        value="1000" min="100" step="100" onchange="updateTokenCost()">
                    <div class="alert alert-primary py-2 px-3 rounded-3 mb-3 small" id="token-cost-preview">
                        Cost: <strong id="token-cost-val">₦<?php echo number_format($price_1k, 2); ?></strong>
                    </div>
                    <button type="submit" name="buy-ai-tokens" class="ai-pill-btn w-100 btn">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Purchase Tokens
                    </button>
                </form>
                <!-- Quick amounts -->
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <?php foreach ([500, 1000, 5000, 10000] as $q): ?>
                    <button class="btn btn-outline-primary btn-sm rounded-pill"
                        onclick="document.getElementById('token_amount_inp').value=<?php echo $q; ?>; updateTokenCost()">
                        <?php echo number_format($q); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- AI SECURITY SENTINEL -->
    <div class="col-lg-7">
        <div class="card ai-card h-100" <?php if (!$ai_status) echo 'style="opacity:0.65;pointer-events:none;"'; ?>>
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-shield-shaded me-2 text-purple" style="color:var(--ai-purple)"></i>Security Sentinel</h5>
                <span class="badge rounded-pill px-3 <?php echo $ai_status ? 'bg-success' : 'bg-secondary'; ?>">
                    <?php echo $ai_status ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">The AI Sentinel monitors your customers' transaction patterns and flags suspicious behaviour — <strong>without replacing your existing purchase limits</strong>.</p>
                <!-- Recent Flags -->
                <div class="mb-3">
                    <div class="small fw-bold text-muted text-uppercase mb-2">Recent Security Flags</div>
                    <?php if ($flags_q && mysqli_num_rows($flags_q) > 0): ?>
                        <?php while ($flag = mysqli_fetch_assoc($flags_q)): ?>
                        <div class="sentinel-flag p-2 mb-2 small">
                            <div class="fw-bold"><?php echo htmlspecialchars($flag['action']); ?></div>
                            <div class="text-muted"><?php echo htmlspecialchars(substr($flag['detail'], 0, 100)); ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?php echo date('M j H:i', strtotime($flag['created_at'])); ?></div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-muted small py-3 text-center"><i class="bi bi-shield-check me-2 text-success"></i>No flags detected recently. All clear!</div>
                    <?php endif; ?>
                </div>
                <!-- VIP Whitelist -->
                <div class="fw-bold small text-muted text-uppercase mb-2">VIP Customer Whitelist</div>
                <form method="post" class="row g-2 mb-2">
                    <?php echo bc_csrf_field(); ?>
                    <div class="col-5"><input type="text" name="product_id" class="form-control form-control-sm rounded-3" placeholder="Phone / Account No." required></div>
                    <div class="col-4"><input type="date" name="expiry" class="form-control form-control-sm rounded-3"></div>
                    <div class="col-3"><button type="submit" name="add-whitelist" class="btn btn-sm btn-primary w-100 rounded-3">Add VIP</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- REAL-TIME INTELLIGENCE HUB (NEW) -->
    <div class="col-lg-12">
        <div class="card ai-card shadow-sm">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-cpu-fill me-2 text-primary"></i>Real-Time Intelligence Hub</h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-light text-dark border rounded-pill small"><i class="bi bi-globe me-1"></i> <?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?></span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill small">LIVE AUDIT</span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-5">
                        <h6 class="small fw-bold text-muted text-uppercase mb-3">Top AI Consumers (Month)</h6>
                        <?php if ($top_consumers_q && mysqli_num_rows($top_consumers_q) > 0): while($tc = mysqli_fetch_assoc($top_consumers_q)): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded-3">
                            <span class="small fw-bold"><i class="bi bi-person-circle me-2 opacity-50"></i><?php echo htmlspecialchars($tc['username']); ?></span>
                            <span class="badge bg-dark rounded-pill"><?php echo number_format($tc['total']); ?> tokens</span>
                        </div>
                        <?php endwhile; else: ?>
                        <div class="text-center py-3 text-muted small italic">No consumer data yet.</div>
                        <?php endif; ?>

                        <h6 class="small fw-bold text-muted text-uppercase mb-3 mt-4">Service Health Metrics</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 border rounded-3 bg-light">
                                    <div class="x-small text-muted">Latency (Avg)</div>
                                    <div class="small fw-bold <?php echo $v_avg_latency > 1000 ? 'text-warning' : 'text-success'; ?>"><?php echo $v_avg_latency; ?>ms</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded-3 bg-light">
                                    <div class="x-small text-muted">Security Sentinel</div>
                                    <div class="small fw-bold <?php echo $v_blocked_count > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $v_blocked_count > 0 ? "$v_blocked_count Blocked" : "Active"; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h6 class="small fw-bold text-muted text-uppercase mb-3">Recent Intelligence Logs</h6>
                        <div class="border rounded-4 overflow-hidden">
                            <?php if ($recent_intelligence_q && mysqli_num_rows($recent_intelligence_q) > 0): while($log = mysqli_fetch_assoc($recent_intelligence_q)): ?>
                            <div class="p-2 border-bottom d-flex justify-content-between align-items-center" style="font-size:0.75rem;">
                                <span>
                                    <i class="bi bi-lightning-fill text-warning me-1"></i>
                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong>: 
                                    <span class="text-muted"><?php echo ucfirst($log['action_type']); ?></span>
                                </span>
                                <span class="text-muted"><?php echo date('H:i', strtotime($log['created_at'])); ?></span>
                            </div>
                            <?php endwhile; else: ?>
                            <div class="p-4 text-center text-muted small">Awaiting activity...</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
                <?php if ($wl_q && mysqli_num_rows($wl_q) > 0): ?>
                <div class="table-responsive" style="max-height:150px;">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Product/Phone</th><th>Expires</th><th></th></tr></thead>
                        <tbody>
                        <?php while ($wl = mysqli_fetch_assoc($wl_q)): ?>
                        <tr>
                            <td class="small fw-bold"><?php echo htmlspecialchars($wl['product_id']); ?></td>
                            <td class="small"><?php echo $wl['override_expiry'] ? date('M j Y', strtotime($wl['override_expiry'])) : 'No Expiry'; ?></td>
                            <td><a href="AISettings.php?remove-whitelist=<?php echo urlencode($wl['product_id']); ?>" class="btn btn-outline-danger btn-sm py-0 px-2 rounded-pill">✕</a></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

        <!-- VOICE TO VTU SETTINGS -->
    <div class="col-lg-12">
        <div class="card ai-card">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-mic-fill me-2 text-danger"></i>Autonomous Voice-to-VTU Approvals</h5>
            </div>
            <div class="card-body p-4 row g-4">
                <div class="col-md-5">
                    <div class="alert alert-light border rounded-4">
                        <h6 class="fw-bold mb-2">Access Threshold</h6>
                        <p class="small text-muted mb-3">Set how many successful transactions a user must have before they can apply for Zero-Click Voice access.</p>
                        <form method="post" class="row g-3">
                            <?php echo bc_csrf_field(); ?>
                            <div class="col-6">
                                <label class="small fw-bold">Min Success Tx</label>
                                <input type="number" name="ai_voice_min_tx" class="form-control fw-bold" value="<?php echo $voice_min_tx; ?>" min="1">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold">Fee (AI Tokens)</label>
                                <input type="number" name="ai_voice_fee_tokens" class="form-control fw-bold" value="<?php echo $voice_fee_tokens; ?>" min="0">
                            </div>
                            <div class="col-12">
                                <button type="submit" name="set-voice-limit" class="btn btn-primary w-100 rounded-3">Save Voice Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-md-7">
                    <h6 class="fw-bold mb-3">Pending & Approved Applications</h6>
                    <div class="table-responsive" style="max-height:200px;">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>User</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            <?php if ($voice_apps_q && mysqli_num_rows($voice_apps_q) > 0): 
                                while ($app = mysqli_fetch_assoc($voice_apps_q)): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($app['username']); ?></div>
                                        <div class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($app['phone_number']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($app['ai_voice_status'] == 1): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($app['ai_voice_status'] == 1): ?>
                                        <form method="post" class="d-inline">
                                            <?php echo bc_csrf_field(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" name="process-voice-app" value="approve" class="btn btn-success btn-sm py-0 px-2"><i class="bi bi-check2"></i></button>
                                            <button type="submit" name="process-voice-app" value="reject" class="btn btn-danger btn-sm py-0 px-2 ms-1"><i class="bi bi-x-lg"></i></button>
                                            <input type="hidden" name="app_action" value="approve" id="act_<?php echo $app['id']; ?>">
                                        </form>
                                        <script>
                                            // Handle multiple submit buttons cleanly
                                            document.querySelectorAll('button[name="process-voice-app"]').forEach(btn => {
                                                btn.addEventListener('click', function() {
                                                    this.closest('form').querySelector('input[name="app_action"]').value = this.value;
                                                });
                                            });
                                        </script>
                                        <?php else: ?>
                                            <span class="small text-muted"><i class="bi bi-shield-check text-success"></i> Trusted</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted small">No applications yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI USAGE HISTORY -->
    <div class="col-12">
        <div class="card ai-card">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>AI Usage History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr class="small text-uppercase text-muted">
                            <th>Action</th><th>Model</th><th>Tokens</th><th>Cost</th><th>Status</th><th class="text-end pe-4">Date</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($tx_q && mysqli_num_rows($tx_q) > 0):
                            while ($tx = mysqli_fetch_assoc($tx_q)): ?>
                            <tr>
                                <td class="small fw-bold"><?php echo htmlspecialchars($tx['action_type']); ?></td>
                                <td><span class="badge bg-light text-dark rounded-pill"><?php echo htmlspecialchars($tx['model_used'] ?: '—'); ?></span></td>
                                <td class="small"><?php echo number_format((int)$tx['tokens_burned']); ?></td>
                                <td class="small">₦<?php echo number_format((float)$tx['cost_naira'], 2); ?></td>
                                <td>
                                    <?php if ($tx['status'] === 'success'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill">Success</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4 small text-muted"><?php echo date('M j, H:i', strtotime($tx['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No AI activity yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div><!-- /row -->

</section>
<?php include("../func/bc-admin-footer.php"); ?>
<script>
const pricePerK = <?php echo $price_1k; ?>;
function updateTokenCost() {
    const amount = parseInt(document.getElementById('token_amount_inp').value) || 0;
    const cost = (amount / 1000) * pricePerK;
    document.getElementById('token-cost-val').textContent = '₦' + cost.toLocaleString('en-NG', {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>
</body>
</html>

