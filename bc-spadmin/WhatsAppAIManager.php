<?php session_start();
include("../func/bc-admin-config.php");
$is_super = (isset($get_logged_admin_details['id']) && $get_logged_admin_details['id'] == 1);
if (!$is_super) { header("Location: /bc-spadmin/Login.php"); exit(); }

if (isset($_POST['save-config'])) {
    $port = (int)($_POST['wa-port'] ?? 3001);
    if ($port >= 1024 && $port <= 65535) {
        $p = mysqli_real_escape_string($connection_server, $port);
        $ex = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT id FROM sas_super_admin_options WHERE option_name='whatsapp_bridge_port' LIMIT 1"));
        if ($ex) mysqli_query($connection_server, "UPDATE sas_super_admin_options SET option_value='$p' WHERE option_name='whatsapp_bridge_port'");
        else mysqli_query($connection_server, "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('whatsapp_bridge_port','$p')");
    }
    $_SESSION['product_purchase_response'] = 'Configuration saved.';
    header("Location: WhatsAppAIManager.php"); exit();
}

if (isset($_POST['send-test-message'])) {
    $ph  = preg_replace('/[^0-9]/', '', trim($_POST['test-phone'] ?? ''));
    $msg = htmlspecialchars(strip_tags(trim($_POST['test-message'] ?? '')));
    $wa_port = getSuperAdminOption('whatsapp_bridge_port', '3001');
    $ch = curl_init("http://127.0.0.1:$wa_port/send");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode(['phone'=>$ph,'message'=>$msg]),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>8]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    $res = $err ? ['success'=>false,'error'=>$err] : (json_decode($raw,true) ?? ['success'=>false,'error'=>'Invalid response']);
    $_SESSION['product_purchase_response'] = $res['success'] ? '✅ Test message sent!' : '❌ Failed: '.($res['error']??'Unknown');
    header("Location: WhatsAppAIManager.php"); exit();
}

$wa_port = getSuperAdminOption('whatsapp_bridge_port', '3001');
$gw = ['online'=>false,'qr_ready'=>false,'phone'=>null,'uptime_s'=>0,'queue_len'=>0];
$qr_data = null;
try {
    $ch = curl_init("http://127.0.0.1:$wa_port/status");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3]);
    $raw = curl_exec($ch); curl_close($ch);
    if ($raw) $gw = array_merge($gw, json_decode($raw,true) ?? []);
} catch (Exception $e) {}
if (!$gw['online'] && $gw['qr_ready']) {
    try {
        $ch = curl_init("http://127.0.0.1:$wa_port/qr");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3]);
        $raw = curl_exec($ch); curl_close($ch);
        $qr = json_decode($raw,true); $qr_data = $qr['qr_base64'] ?? null;
    } catch (Exception $e) {}
}
function htd($ts) { $d=time()-$ts; return $d<60?'Just now':($d<3600?floor($d/60).'m ago':($d<86400?floor($d/3600).'h ago':date('d M',$ts))); }
?>
<!DOCTYPE html>
<head>
    <title>WhatsApp AI Manager | <?php echo $get_all_super_admin_site_details['site_title']??'System'; ?></title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        .status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;}
        .status-dot.on{background:#22c55e;box-shadow:0 0 6px #22c55e80;animation:pulse 2s infinite;}
        .status-dot.off{background:#ef4444;} .status-dot.qr{background:#f59e0b;}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
        .gw-card{border-left:4px solid}.gw-card.online{border-color:#22c55e}.gw-card.offline{border-color:#ef4444}.gw-card.pending{border-color:#f59e0b}
    </style>
</head>
<body>
<?php include("../func/bc-spadmin-header.php"); ?>
<div class="pagetitle">
    <h1>WhatsApp AI Gateway Manager</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="#">Home</a></li><li class="breadcrumb-item active">WhatsApp Manager</li></ol></nav>
</div>
<section class="section">
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 rounded-4 gw-card <?php echo $gw['online']?'online':($qr_data?'pending':'offline'); ?> mb-4">
            <div class="card-header bg-white border-0 py-3"><h5 class="mb-0 fw-bold"><i class="bi bi-whatsapp me-2 text-success"></i>Gateway Status</h5></div>
            <div class="card-body p-4">
                <?php if ($gw['online']): ?>
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2" style="width:70px;height:70px"><i class="bi bi-whatsapp text-success" style="font-size:2rem"></i></div>
                        <h5 class="fw-bold text-success"><span class="status-dot on"></span>Connected</h5>
                        <p class="text-muted small">+<?php echo htmlspecialchars($gw['phone']??'Unknown'); ?></p>
                    </div>
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4"><div class="bg-light rounded-3 p-2"><div class="fw-bold text-success small"><?php echo gmdate('H:i:s',(int)$gw['uptime_s']); ?></div><small class="text-muted" style="font-size:.7rem">Uptime</small></div></div>
                        <div class="col-4"><div class="bg-light rounded-3 p-2"><div class="fw-bold text-primary small"><?php echo (int)$gw['queue_len']; ?></div><small class="text-muted" style="font-size:.7rem">Queued</small></div></div>
                        <div class="col-4"><div class="bg-light rounded-3 p-2"><div class="fw-bold text-dark small">:<?php echo $wa_port; ?></div><small class="text-muted" style="font-size:.7rem">Port</small></div></div>
                    </div>
                <?php elseif ($qr_data): ?>
                    <div class="text-center">
                        <h6 class="fw-bold text-warning mb-2"><span class="status-dot qr"></span>Scan QR Code</h6>
                        <p class="text-muted small mb-3">WhatsApp → Settings → Linked Devices → Link a Device</p>
                        <div style="background:#fff;border-radius:12px;padding:12px;display:inline-block;box-shadow:0 2px 12px rgba(0,0,0,.08)">
                            <img src="<?php echo htmlspecialchars($qr_data); ?>" style="max-width:240px;width:100%;" alt="QR Code"/>
                        </div>
                        <p class="text-muted small mt-2">Auto-refreshing in <span id="qr-timer">30</span>s</p>
                    </div>
                    <script>let t=30;setInterval(()=>{t--;document.getElementById('qr-timer').textContent=t;if(t<=0)location.reload();},1000);</script>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-wifi-off text-danger" style="font-size:3rem"></i>
                        <h6 class="text-danger fw-bold mt-2 mb-1"><span class="status-dot off"></span>Bridge Offline</h6>
                        <p class="text-muted small mb-3">Start the Node.js bridge via SSH or PM2.</p>
                        <div class="bg-dark text-start rounded-3 p-3 mb-3">
                            <code class="text-success d-block">cd vtu_whatsapp_ai</code>
                            <code class="text-success d-block">npm install</code>
                            <code class="text-success d-block">pm2 start index.js --name vtu-whatsapp</code>
                        </div>
                        <button onclick="location.reload()" class="btn btn-outline-primary rounded-pill px-4 btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Check Again</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Bridge Configuration</h6></div>
            <div class="card-body p-4">
                <form method="post">
                    <div class="mb-3"><label class="form-label small fw-bold">Bridge Port</label>
                        <input name="wa-port" type="number" class="form-control rounded-3" value="<?php echo (int)$wa_port; ?>" min="1024" max="65535"/></div>
                    <button name="save-config" type="submit" class="btn btn-primary w-100 rounded-3 fw-bold"><i class="bi bi-save me-2"></i>Save</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-send me-2 text-primary"></i>Send Test Message</h6>
                <?php echo $gw['online']?'<span class="badge bg-success-subtle text-success rounded-pill px-3">Ready</span>':'<span class="badge bg-danger-subtle text-danger rounded-pill px-3">Offline</span>'; ?>
            </div>
            <div class="card-body p-4">
                <?php if (!$gw['online']): ?><div class="alert alert-warning border-0 rounded-3 small mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Gateway must be online to send messages.</div><?php endif; ?>
                <form method="post">
                    <div class="mb-3"><label class="form-label small fw-bold">Recipient Phone</label>
                        <input name="test-phone" type="text" class="form-control rounded-3" placeholder="08012345678" <?php echo !$gw['online']?'disabled':''; ?>></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Message</label>
                        <textarea name="test-message" rows="3" class="form-control rounded-3" maxlength="4096" <?php echo !$gw['online']?'disabled':''; ?>></textarea></div>
                    <button name="send-test-message" type="submit" class="btn btn-success w-100 rounded-3 fw-bold" <?php echo !$gw['online']?'disabled':''; ?>><i class="bi bi-whatsapp me-2"></i>Send Test</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Alert Events</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light"><tr><th class="ps-4">Time</th><th>Recipient</th><th>Type</th><th class="pe-4">Status</th></tr></thead>
                        <tbody>
                        <?php
                        $lq = @mysqli_query($connection_server, "SELECT * FROM sas_whatsapp_gateway ORDER BY created_at DESC LIMIT 20");
                        if ($lq && mysqli_num_rows($lq)>0) {
                            while ($l=mysqli_fetch_assoc($lq)) {
                                $sb = $l['status']=='sent'?'<span class="badge bg-success-subtle text-success rounded-pill px-2">Sent</span>':'<span class="badge bg-danger-subtle text-danger rounded-pill px-2">'.htmlspecialchars($l['status']).'</span>';
                                echo '<tr><td class="ps-4 small text-muted">'.htd(strtotime($l['created_at']??'now')).'</td><td class="small fw-bold">'.htmlspecialchars(substr($l['recipient']??'',0,15)).'</td><td><span class="badge bg-primary-subtle text-primary rounded-pill px-2 small">'.htmlspecialchars($l['event_type']??'alert').'</span></td><td class="pe-4">'.$sb.'</td></tr>';
                            }
                        } else echo '<tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-1 opacity-25"></i>No events yet</td></tr>';
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</section>
<?php include("../func/bc-spadmin-footer.php"); ?>
<?php if(isset($_SESSION["product_purchase_response"])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>Swal.fire('Notification','<?php echo addslashes($_SESSION["product_purchase_response"]); ?>','info');fetch('/func/unset-product-response.php');</script>
<?php unset($_SESSION["product_purchase_response"]); endif; ?>
</body></html>
