<?php session_start();
include("../func/bc-spadmin-config.php");
include_once("../func/bc-ai-engine.php");

$current_page = "BridgeGuide.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Integration Guide | Super Admin</title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <style>
        .guide-header { background: linear-gradient(135deg, #0f172a, #1e293b); color: white; border-radius: 1.5rem; padding: 3rem 2rem; position: relative; overflow: hidden; }
        .guide-header::after { content: '🚀'; position: absolute; right: 5%; top: 50%; transform: translateY(-50%); font-size: 5rem; opacity: 0.1; }
        .code-block { background: #1e293b; color: #f8fafc; border-radius: 0.75rem; padding: 1.25rem; font-family: 'Courier New', Courier, monospace; position: relative; font-size: 0.85rem; border: 1px solid #334155; }
        .copy-btn { position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.1); border: none; color: white; padding: 4px 12px; border-radius: 4px; font-size: 0.7rem; transition: 0.2s; }
        .copy-btn:hover { background: rgba(255,255,255,0.2); }
        .step-num { width: 32px; height: 32px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .nav-pills .nav-link { border-radius: 2rem; font-weight: bold; color: #64748b; padding: 0.6rem 1.5rem; }
        .nav-pills .nav-link.active { background: var(--primary-color) !important; color: white !important; }
    </style>
</head>
<body>
<?php include("../func/bc-spadmin-header.php"); ?>
<div class="pagetitle"><h1>INTEGRATION GUIDE</h1></div>

<section class="section">
    <div class="guide-header mb-4 shadow">
        <h2 class="fw-bold mb-1">Cloud AI & WhatsApp Bridge</h2>
        <p class="opacity-75 mb-0">Follow this guide to activate real-time intelligence and automated notifications across your platform.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body p-3">
                    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <button class="nav-link active mb-2 text-start" data-bs-toggle="pill" data-bs-target="#tab-cron"><i class="bi bi-clock-history me-2"></i>Cron Jobs</button>
                        <button class="nav-link mb-2 text-start" data-bs-toggle="pill" data-bs-target="#tab-wa"><i class="bi bi-whatsapp me-2"></i>WhatsApp Bridge</button>
                        <button class="nav-link mb-2 text-start" data-bs-toggle="pill" data-bs-target="#tab-ai"><i class="bi bi-cpu me-2"></i>Cloud AI Setup</button>
                        <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#tab-test"><i class="bi bi-shield-check me-2"></i>Testing</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="tab-content" id="v-pills-tabContent">
                <!-- Cron Jobs Tab -->
                <div class="tab-pane fade show active" id="tab-cron">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-clock text-primary me-2"></i>Server Automation (Cron Jobs)</h5>
                            <p class="text-muted small mb-4">Set these up in your cPanel or VPS crontab to automate the AI engine and monitoring systems.</p>
                            
                            <div class="mb-4">
                                <h6 class="fw-bold small text-uppercase">1. API Aggregator Monitor (Every 5 mins)</h6>
                                <div class="code-block">
                                    <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                    <code>*/5 * * * * /usr/bin/php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/cron/aggregator_monitor.php >> <?php echo dirname($_SERVER['DOCUMENT_ROOT']); ?>/logs/agg_mon.log 2>&1</code>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-bold small text-uppercase">2. AI Daily WhatsApp Briefing (7:00 AM)</h6>
                                <div class="code-block">
                                    <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                    <code>0 7 * * * /usr/bin/php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/cron/ai_daily_briefing.php >> <?php echo dirname($_SERVER['DOCUMENT_ROOT']); ?>/logs/daily.log 2>&1</code>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-bold small text-uppercase">3. Dormant User WhatsApp Re-engagement (10:00 AM)</h6>
                                <div class="code-block">
                                    <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                    <code>0 10 * * * /usr/bin/php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/cron/dormant_user_alert.php >> <?php echo dirname($_SERVER['DOCUMENT_ROOT']); ?>/logs/dormant.log 2>&1</code>
                                </div>
                            </div>

                            <div class="mb-0">
                                <h6 class="fw-bold small text-uppercase">4. AI Monthly Platform Audit (1st of month)</h6>
                                <div class="code-block">
                                    <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                    <code>0 8 1 * * /usr/bin/php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/cron/ai_monthly_blueprint.php >> <?php echo dirname($_SERVER['DOCUMENT_ROOT']); ?>/logs/audit.log 2>&1</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Bridge Tab -->
                <div class="tab-pane fade" id="tab-wa">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-whatsapp text-success me-2"></i>WhatsApp Node.js Bridge</h5>
                            <div class="alert alert-warning rounded-4 small">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> This requires a VPS or a server with Node.js support. Shared hosting may not support the bridge.
                            </div>
                            
                            <h6 class="fw-bold small text-uppercase mb-3">Deployment Commands (Terminal)</h6>
                            
                            <div class="mb-3">
                                <p class="small mb-1">1. Navigate to bridge directory:</p>
                                <div class="code-block">
                                    <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                    <code>cd <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/vtu_whatsapp_ai</code>
                                </div>
                            </div>

                            <div class="mb-3">
                                <p class="small mb-1">2. Install dependencies:</p>
                                <div class="code-block">
                                    <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                    <code>npm install</code>
                                </div>
                            </div>

                            <div class="mb-3">
                                <p class="small mb-1">3. Start & Persistence (using PM2):</p>
                                <div class="code-block">
                                    <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                    <code>pm2 start index.js --name vtu-wa && pm2 save && pm2 startup</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Setup Tab -->
                <div class="tab-pane fade" id="tab-ai">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="bi bi-cpu text-info me-2"></i>Cloud AI Activation</h5>
                            <div class="d-flex align-items-start mb-4">
                                <div class="step-num">1</div>
                                <div>
                                    <h6 class="fw-bold">Obtain API Key</h6>
                                    <p class="text-muted small">Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a> to get a free/paid Gemini API key.</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-4">
                                <div class="step-num">2</div>
                                <div>
                                    <h6 class="fw-bold">Configure Provider</h6>
                                    <p class="text-muted small">Go to <a href="AIManagement.php">AI Management</a>, select "Gemini", and paste your API key.</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="step-num">3</div>
                                <div>
                                    <h6 class="fw-bold">Global Enable</h6>
                                    <p class="text-muted small">Ensure "Global AI" is toggled ON to allow vendors to request activation.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Testing Tab -->
                <div class="tab-pane fade" id="tab-test">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-4 text-center py-5">
                            <i class="bi bi-shield-check text-success display-1 opacity-25"></i>
                            <h4 class="fw-bold mt-3">Ready to Validate?</h4>
                            <p class="text-muted mx-auto" style="max-width: 500px;">After setting up the crons and the bridge, run the integration test via SSH to ensure all systems are communicating correctly.</p>
                            <div class="code-block mx-auto text-start" style="max-width: 600px;">
                                <button class="copy-btn" onclick="copyCode(this)">COPY</button>
                                <code>php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/tests/ai_integration_test.php</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function copyCode(btn) {
    const code = btn.nextElementSibling.innerText;
    navigator.clipboard.writeText(code).then(() => {
        const originalText = btn.innerText;
        btn.innerText = 'COPIED!';
        btn.classList.add('bg-success');
        setTimeout(() => {
            btn.innerText = originalText;
            btn.classList.remove('bg-success');
        }, 2000);
    });
}
</script>

<?php include("../func/bc-spadmin-footer.php"); ?>
</body>
</html>
