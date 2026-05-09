<?php session_start();
include("../func/bc-spadmin-config.php");
include_once("../func/bc-ai-engine.php");
include_once("../func/bc-whatsapp.php");

// ── Handle: Install Model ──────────────────────────────────
if (isset($_POST["install-model"])) {
    $model = bc_sanitize($_POST["model_name"] ?? '');
    $email = $get_super_admin_details["email"] ?? '';
    $ai    = ai_engine();
    if ($ai->pullModelBackground($model, $email)) {
        $_SESSION["response"] = "✅ Model '$model' download started in the background. You'll be notified when complete.";
    } else {
        $_SESSION["response"] = "❌ Failed to start download. Model name may not be in the allowed list.";
    }
    header("Location: AIManagement.php"); exit();
}

// ── Handle: Global AI Toggle ───────────────────────────────
if (isset($_POST["toggle-global-ai"])) {
    $val = (int)($_POST["ai_global_enabled"] ?? 0) ? '1' : '0';
    mysqli_query($connection_server,
        "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('ai_global_enabled', '$val')
         ON DUPLICATE KEY UPDATE option_value='$val'"
    );
    $_SESSION["response"] = "Global AI " . ($val ? "enabled" : "disabled") . ".";
    header("Location: AIManagement.php"); exit();
}

// ── Handle: Approve/Reject Vendor Request ─────────────────
if (isset($_GET['approve-vendor'])) {
    $v_id = (int)$_GET['approve-vendor'];
    $bonus = (int)getSuperAdminOption('ai_default_token_bonus', 1000);
    mysqli_query($connection_server, "UPDATE sas_vendors SET ai_status=1, ai_request_status='approved', ai_token_balance = ai_token_balance + $bonus WHERE id='$v_id'");
    $_SESSION["response"] = "✅ Vendor AI activation approved. $bonus bonus tokens granted.";
    header("Location: AIManagement.php"); exit();
}
if (isset($_GET['reject-vendor'])) {
    $v_id = (int)$_GET['reject-vendor'];
    mysqli_query($connection_server, "UPDATE sas_vendors SET ai_request_status='rejected' WHERE id='$v_id'");
    $_SESSION["response"] = "❌ Vendor AI request rejected.";
    header("Location: AIManagement.php"); exit();
}

// ── Handle: Update AI Pricing ──────────────────────────────
if (isset($_POST["update-ai-pricing"])) {
    $price_1k  = bc_sanitize_number($_POST["price_per_1k"] ?? 100);
    $per_tx    = (int)($_POST["per_tx_cost"] ?? 5);
    $voice_thr = (int)($_POST["voice_threshold"] ?? 100);
    $opts = [
        'ai_price_per_request'      => $per_tx,
        'ai_voice_unlock_threshold' => $voice_thr,
    ];
    foreach ($opts as $k => $v) {
        $esc_k = mysqli_real_escape_string($connection_server, $k);
        $esc_v = mysqli_real_escape_string($connection_server, $v);
        mysqli_query($connection_server,
            "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('$esc_k','$esc_v')
             ON DUPLICATE KEY UPDATE option_value='$esc_v'"
        );
    }
    // Update all vendors' price
    mysqli_query($connection_server, "UPDATE sas_vendors SET ai_price_per_1k_tokens='$price_1k', ai_per_tx_cost='$per_tx', voice_tx_threshold='$voice_thr'");
    $_SESSION["response"] = "✅ AI pricing updated for all vendors.";
    header("Location: AIManagement.php"); exit();
}

// ── Load current data ──────────────────────────────────────
$ai_global  = getSuperAdminOption('ai_global_enabled', '0');
$ai_host    = getSuperAdminOption('ai_ollama_host', 'http://127.0.0.1:11434');
$price_1k   = (float)getSuperAdminOption('ai_price_per_request', '5'); // token cost
$ai         = ai_engine();
$ollama_up  = $ai->isOllamaOnline();
$models     = $ai->listModels();
$queue_q    = mysqli_query($connection_server, "SELECT * FROM sas_ai_install_queue ORDER BY started_at DESC LIMIT 10");

// Available model catalog
$model_catalog = [
    ['name' => 'phi4-mini',         'size' => '~2.5GB', 'desc' => 'Ultra-fast, ideal for page guides & quick answers.    Recommended for all vendors.', 'tier' => 'Free'],
    ['name' => 'gemma4:e2b',        'size' => '~5GB',   'desc' => 'Google\'s efficient 2B model. Good for marketing copy.', 'tier' => 'Standard'],
    ['name' => 'gemma4:12b',        'size' => '~8GB',   'desc' => 'High quality responses for premium AI features.',        'tier' => 'Premium'],
    ['name' => 'llama4-scout',      'size' => '~6GB',   'desc' => 'Meta\'s Llama 4 Scout — fast & capable.',               'tier' => 'Standard'],
    ['name' => 'qwen3:4b',          'size' => '~3GB',   'desc' => 'Alibaba\'s Qwen 3 4B — excellent for structured tasks.', 'tier' => 'Standard'],
    ['name' => 'deepseek-r1:1.5b',  'size' => '~1.5GB', 'desc' => 'Tiny reasoning model — great for intent parsing.',       'tier' => 'Free'],
    ['name' => 'llava',             'size' => '~4.5GB', 'desc' => 'Multimodal Vision Model — Required for Image-to-VTU.',  'tier' => 'Titanium'],
];

// Revenue from AI this month
$ai_rev_q = mysqli_query($connection_server,
    "SELECT SUM(cost_naira) as revenue, COUNT(*) as calls FROM sas_ai_transactions
     WHERE MONTH(created_at)=MONTH(NOW()) AND status='success'"
);
$ai_rev = $ai_rev_q ? mysqli_fetch_assoc($ai_rev_q) : ['revenue' => 0, 'calls' => 0];

// WhatsApp gateway status
$wa_online = isWhatsAppGatewayOnline();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>AI Management | Super Admin</title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <style>
        .ai-header{background:linear-gradient(135deg,#1e1b4b,#3730a3);color:#fff;border-radius:1.5rem;padding:2rem;}
        .status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;}
        .dot-green{background:#22c55e;box-shadow:0 0 8px #22c55e;}
        .dot-red{background:#ef4444;box-shadow:0 0 8px #ef4444;}
        .model-card{border:1px solid #e5e7eb;border-radius:1rem;padding:1rem;transition:.2s;cursor:default;}
        .model-card:hover{border-color:#6366f1;background:#faf5ff;}
        .model-card.installed{border-color:#22c55e;background:#f0fdf4;}
        .tier-badge{font-size:.65rem;font-weight:700;border-radius:2rem;padding:.15rem .6rem;}
    </style>
</head>
<body>
<?php include("../func/bc-spadmin-header.php"); ?>

<div class="pagetitle">
    <h1>AI MANAGEMENT CENTER</h1>
    <nav><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">AI Management</li>
    </ol></nav>
</div>

<section class="section">
<?php if (isset($_SESSION["response"])): ?>
    <div class="alert alert-info alert-dismissible fade show rounded-4">
        <?php echo $_SESSION["response"]; unset($_SESSION["response"]); ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Header -->
<div class="ai-header mb-4 shadow">
    <div class="row align-items-center g-3">
        <div class="col-md-6">
            <h4 class="fw-bold mb-1"><i class="bi bi-cpu me-2"></i>AI Ecosystem Control Center</h4>
            <p class="opacity-75 mb-0">Manage Ollama models, pricing, WhatsApp gateway, and revenue.</p>
        </div>
        <div class="col-md-6">
            <div class="row g-2">
                <div class="col-4 text-center">
                    <div class="fw-bold fs-4">₦<?php echo number_format((float)$ai_rev['revenue'], 0); ?></div>
                    <div class="small opacity-75">AI Revenue MTD</div>
                </div>
                <div class="col-4 text-center">
                    <div class="fw-bold fs-4"><?php echo number_format((int)$ai_rev['calls']); ?></div>
                    <div class="small opacity-75">AI Calls MTD</div>
                </div>
                <div class="col-4 text-center">
                    <div class="fw-bold fs-4"><?php echo count($models); ?></div>
                    <div class="small opacity-75">Models Ready</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>AI Revenue vs. Usage (Last 30 Days)</h5>
            </div>
            <div class="card-body p-4" style="height: 300px;">
                <canvas id="aiRevenueChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('aiRevenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php 
                for($i=29;$i>=0;$i--) echo '"'.date('M d', strtotime("-$i days")).'",';
            ?>],
            datasets: [{
                label: 'Revenue (₦)',
                data: [<?php 
                    for($i=29;$i>=0;$i--){
                        $d = date('Y-m-d', strtotime("-$i days"));
                        $rq = mysqli_query($connection_server, "SELECT SUM(cost_naira) as rev FROM sas_ai_transactions WHERE DATE(created_at)='$d' AND status='success'");
                        $rv = mysqli_fetch_assoc($rq);
                        echo ($rv['rev'] ?? 0) . ",";
                    }
                ?>],
                borderColor: '#6366f1', backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true, tension: 0.4
            }, {
                label: 'AI Calls',
                data: [<?php 
                    for($i=29;$i>=0;$i--){
                        $d = date('Y-m-d', strtotime("-$i days"));
                        $cq = mysqli_query($connection_server, "SELECT COUNT(*) as calls FROM sas_ai_transactions WHERE DATE(created_at)='$d'");
                        $cv = mysqli_fetch_assoc($cq);
                        echo ($cv['calls'] ?? 0) . ",";
                    }
                ?>],
                borderColor: '#10b981', backgroundColor: 'transparent',
                borderDash: [5, 5], tension: 0.4, yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true },
                y1: { beginAtZero: true, position: 'right', grid: { display: false } }
            }
        }
    });
});
</script>
</script>

<!-- AI Activation Requests -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-person-check me-2 text-success"></i>Pending Activation Requests</h5>
                <?php 
                $q_pcount = mysqli_query($connection_server, "SELECT COUNT(*) as count FROM sas_vendors WHERE ai_request_status='pending'");
                $pending_count = ($q_pcount && $r = mysqli_fetch_assoc($q_pcount)) ? $r['count'] : 0;
                if($pending_count > 0): ?>
                <span class="badge bg-danger rounded-pill"><?php echo $pending_count; ?> New</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr class="small text-uppercase text-muted"><th>Vendor</th><th>Request Date</th><th>Package</th><th class="text-end pe-4">Action</th></tr></thead>
                        <tbody>
                            <?php 
                            $req_q = mysqli_query($connection_server, "SELECT id, company_name, email, reg_date FROM sas_vendors WHERE ai_request_status='pending' ORDER BY id DESC");
                            if ($req_q && mysqli_num_rows($req_q) > 0):
                                while($req = mysqli_fetch_assoc($req_q)): ?>
                                <tr>
                                    <td class="ps-4"><div class="fw-bold"><?php echo htmlspecialchars($req['company_name']); ?></div><div class="small text-muted"><?php echo $req['email']; ?></div></td>
                                    <td class="small"><?php echo date('M j, Y', strtotime($req['reg_date'])); ?></td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info rounded-pill">Requested</span></td>
                                    <td class="text-end pe-4">
                                        <a href="AIManagement.php?approve-vendor=<?php echo $req['id']; ?>" class="btn btn-success btn-sm rounded-pill px-3" onclick="return confirm('Approve AI access?')">Approve</a>
                                        <a href="AIManagement.php?reject-vendor=<?php echo $req['id']; ?>" class="btn btn-danger btn-sm rounded-pill px-3" onclick="return confirm('Reject this request?')">Reject</a>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No pending activation requests.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- System Status -->
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3"><h5 class="fw-bold mb-0"><i class="bi bi-activity me-2 text-primary"></i>System Status</h5></div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="fw-bold small">Ollama Engine</span>
                    <span class="status-dot <?php echo $ollama_up ? 'dot-green' : 'dot-red'; ?> me-2"></span>
                    <span class="small <?php echo $ollama_up ? 'text-success' : 'text-danger'; ?>"><?php echo $ollama_up ? 'Online' : 'Offline'; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="fw-bold small">WhatsApp Gateway</span>
                    <span class="status-dot <?php echo $wa_online ? 'dot-green' : 'dot-red'; ?> me-2"></span>
                    <span class="small <?php echo $wa_online ? 'text-success' : 'text-danger'; ?>"><?php echo $wa_online ? 'Online' : 'Offline'; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="fw-bold small">Global AI</span>
                    <span class="badge rounded-pill <?php echo $ai_global ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $ai_global ? 'Enabled' : 'Disabled'; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 mb-3">
                    <span class="fw-bold small">Ollama Host</span>
                    <code class="small"><?php echo htmlspecialchars($ai_host); ?></code>
                </div>
                <!-- Global Toggle -->
                <form method="post">
                    <input type="hidden" name="ai_global_enabled" value="<?php echo $ai_global ? 0 : 1; ?>">
                    <button type="submit" name="toggle-global-ai" class="btn w-100 rounded-pill fw-bold <?php echo $ai_global ? 'btn-outline-danger' : 'btn-success'; ?>">
                        <i class="bi bi-<?php echo $ai_global ? 'pause-fill' : 'play-fill'; ?> me-1"></i>
                        <?php echo $ai_global ? 'Disable Global AI' : 'Enable Global AI'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- AI Pricing -->
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3"><h5 class="fw-bold mb-0"><i class="bi bi-currency-exchange me-2 text-warning"></i>Token Economics</h5></div>
            <div class="card-body p-4">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Price per 1,000 Tokens (₦)</label>
                        <input type="number" name="price_per_1k" class="form-control rounded-3"
                            value="<?php echo getSuperAdminOption('ai_price_per_1k_tokens', '100'); ?>"
                            min="1" step="0.01">
                        <div class="form-text">Vendors pay this to buy AI tokens from their wallet.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Tokens per AI Call</label>
                        <input type="number" name="per_tx_cost" class="form-control rounded-3"
                            value="<?php echo getSuperAdminOption('ai_price_per_request', '5'); ?>"
                            min="1">
                        <div class="form-text">Burned from user balance on each successful AI request.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Voice VTU Unlock Threshold (tx)</label>
                        <input type="number" name="voice_threshold" class="form-control rounded-3"
                            value="<?php echo getSuperAdminOption('ai_voice_unlock_threshold', '100'); ?>"
                            min="1">
                        <div class="form-text">Successful transactions required before a user can enable Voice-to-VTU.</div>
                    </div>
                    <button type="submit" name="update-ai-pricing" class="btn btn-primary w-100 rounded-pill fw-bold">Save Pricing</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Install Queue -->
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3"><h5 class="fw-bold mb-0"><i class="bi bi-cloud-download me-2 text-info"></i>Install Queue</h5></div>
            <div class="card-body p-4">
                <?php if ($queue_q && mysqli_num_rows($queue_q) > 0):
                    while ($qrow = mysqli_fetch_assoc($queue_q)):
                        $badge = 'secondary';
                        switch ($qrow['status']) {
                            case 'ready':       $badge = 'success'; break;
                            case 'downloading': $badge = 'primary'; break;
                            case 'failed':      $badge = 'danger'; break;
                        }
                ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="fw-bold small"><?php echo htmlspecialchars($qrow['model_name']); ?></div>
                        <div class="text-muted" style="font-size:.7rem;"><?php echo date('M j H:i', strtotime($qrow['started_at'])); ?></div>
                    </div>
                    <span class="badge bg-<?php echo $badge; ?> rounded-pill"><?php echo ucfirst($qrow['status']); ?></span>
                </div>
                <?php endwhile; else: ?>
                <div class="text-muted small text-center py-4"><i class="bi bi-inbox me-2"></i>No models in queue.</div>
                <?php endif; ?>
                <?php if ($ollama_up && !empty($models)): ?>
                <div class="mt-3">
                    <div class="small fw-bold text-muted text-uppercase mb-2">Installed Models</div>
                    <?php foreach ($models as $m): ?>
                    <span class="badge bg-success bg-opacity-10 text-success me-1 mb-1 rounded-pill px-3"><?php echo htmlspecialchars($m); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Model Catalog -->
    <div class="col-12">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-grid me-2 text-primary"></i>AI Model Marketplace</h5>
                <?php if (!$ollama_up): ?>
                <span class="badge bg-danger rounded-pill">Ollama Offline — Start Ollama first</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                <?php foreach ($model_catalog as $mc):
                    $is_installed = in_array($mc['name'], $models) || in_array($mc['name'].':latest', $models);
                    $tier_color = ($mc['tier'] === 'Premium' ? 'warning' : ($mc['tier'] === 'Standard' ? 'primary' : 'secondary'));
                ?>
                <div class="col-md-4 col-lg-3">
                    <div class="model-card <?php echo $is_installed ? 'installed' : ''; ?>">
                        <div class="d-flex justify-content-between mb-2">
                            <code class="fw-bold small"><?php echo htmlspecialchars($mc['name']); ?></code>
                            <span class="tier-badge bg-<?php echo $tier_color; ?> bg-opacity-10 text-<?php echo $tier_color; ?>"><?php echo $mc['tier']; ?></span>
                        </div>
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($mc['desc']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><i class="bi bi-hdd me-1"></i><?php echo $mc['size']; ?></span>
                            <?php if ($is_installed): ?>
                            <span class="badge bg-success rounded-pill"><i class="bi bi-check-circle me-1"></i>Installed</span>
                            <?php elseif ($ollama_up): ?>
                            <form method="post">
                                <input type="hidden" name="model_name" value="<?php echo htmlspecialchars($mc['name']); ?>">
                                <button type="submit" name="install-model" class="btn btn-primary btn-sm rounded-pill px-3">
                                    <i class="bi bi-cloud-download me-1"></i>Install
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="badge bg-secondary rounded-pill">Unavailable</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- ─── Blueprint Reports Card ─────────────────────────────────────────── -->
    <div class="col-12 mt-2">
        <div class="card border-0 rounded-4 shadow-sm" style="background:linear-gradient(135deg,#faf5ff,#f3e8ff)">
            <div class="card-body p-4">
                <div class="row align-items-center g-3">
                    <div class="col-md-1 text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width:64px;height:64px;background:linear-gradient(135deg,#7c3aed,#4c1d95)">
                            <i class="bi bi-stars text-white" style="font-size:1.8rem"></i>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h5 class="fw-bold mb-1" style="color:#4c1d95">Monthly AI Blueprint Audit</h5>
                        <p class="text-muted small mb-0">
                            Every month, the AI scans your entire codebase and platform stats, then emails you a structured
                            improvement Blueprint covering Features, Security, SEO, UI/UX, Performance, and Mobile.
                            Each Blueprint is formatted so you can paste it directly into an AI Agent as a task brief.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end d-flex flex-column flex-md-row gap-2 justify-content-md-end align-items-center">
                        <?php
                        $last_bp = mysqli_fetch_assoc(mysqli_query($connection_server,
                            "SELECT id, month_label, generated_at FROM sas_ai_blueprints ORDER BY generated_at DESC LIMIT 1") ?: false);
                        if ($last_bp): ?>
                        <div class="text-center text-muted small me-md-2">
                            Last: <strong><?php echo htmlspecialchars($last_bp['month_label']); ?></strong><br>
                            <a href="AIBlueprintHistory.php?view=<?php echo $last_bp['id']; ?>" class="text-primary">View Report →</a>
                        </div>
                        <?php endif; ?>
                        <a href="AIBlueprintHistory.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                            <i class="bi bi-clock-history me-2"></i>Blueprint History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</section>
<?php include("../func/bc-spadmin-footer.php"); ?>
</body>
</html>
