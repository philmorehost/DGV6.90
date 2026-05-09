<?php session_start();
include("../func/bc-admin-config.php");
include_once("../func/bc-ai-engine.php");

$title = "AI Marketing Agent";
$vendor_id = $get_logged_admin_details['id'];
$assigned_model = $get_logged_admin_details['ai_model_assigned'] ?: getSuperAdminOption('ai_default_model', 'gemini-1.5-flash');

if (isset($_POST['generate-ad'])) {
    $service = bc_sanitize($_POST['service'] ?? 'Airtime');
    $tone    = bc_sanitize($_POST['tone'] ?? 'Professional');
    $target  = bc_sanitize($_POST['target'] ?? 'Customers');

    $prompt = "You are a professional VTU Marketing Strategist. 
               Generate a high-converting WhatsApp status ad copy for a business called '{$get_logged_admin_details['company_name']}'.
               Service being promoted: $service. 
               Tone: $tone. 
               Target Audience: $target.
               Include attractive emojis, a catchy headline, and a clear Call to Action (CTA) pointing to our website: {$get_logged_admin_details['website_url']}.
               Keep it extremely engaging and concise for mobile readers.";
    
    $start_time = microtime(true);
    $ai = ai_engine();
    $result = $ai->chat($assigned_model, $prompt, ['temperature' => 0.85]);
    $duration = round((microtime(true) - $start_time) * 1000);

    if ($result['status'] === 'success') {
        $generated_copy = $result['response'];
        $tokens = strlen($prompt . $generated_copy) / 4; // Approx tokens
        
        // Log transaction
        $esc_res = mysqli_real_escape_string($connection_server, $generated_copy);
        mysqli_query($connection_server, "INSERT INTO sas_ai_transactions (vendor_id, username, prompt, response, tokens_burned, status, duration_ms) VALUES ('$vendor_id', 'admin_{$get_logged_admin_details['email']}', 'Marketing Ad: $service', '$esc_res', '$tokens', 'success', '$duration')");
        
        // Intelligence Memory
        bc_log_ai_intelligence($vendor_id, 'marketing_strategy_generated', $generated_copy, ['service' => $service, 'tone' => $tone]);
    } else {
        $generated_copy = '❌ AI Error: ' . ($result['message'] ?? 'Unable to connect to AI engine.');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $title; ?> | <?php echo $get_all_super_admin_site_details['site_title']; ?></title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        :root { --admin-primary: <?php echo $vendor_primary_color ?? '#4f46e5'; ?>; }
        .ai-marketing-header { 
            background: linear-gradient(135deg, var(--admin-primary), #000); 
            color: white; border-radius: 1.5rem; padding: 3rem; 
            position: relative; overflow: hidden;
        }
        .ai-marketing-header::after {
            content: 'AI'; position: absolute; right: -20px; bottom: -20px; font-size: 10rem; font-weight: 900; opacity: 0.1;
        }
        .generated-box { 
            background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 1rem; 
            padding: 1.5rem; position: relative; white-space: pre-wrap; font-size: 1.05rem; line-height: 1.6;
        }
        .copy-btn { position: absolute; top: 15px; right: 15px; border-radius: 0.75rem; }
        
        .flyer-canvas { 
            width: 320px; height: 568px; 
            background: linear-gradient(45deg, #0f172a, #1e293b);
            border-radius: 24px; overflow: hidden; position: relative;
            padding: 20px; display: flex; align-items: center; justify-content: center;
            border: 8px solid #334155;
        }
        .flyer-glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px; width: 100%; height: 85%;
            display: flex; flex-direction: column; padding: 25px; color: white;
            text-align: center;
        }
        .flyer-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 20px; }
        .flyer-content { flex-grow: 1; font-size: 1.1rem; line-height: 1.5; display: flex; align-items: center; justify-content: center; font-weight: 500; }
        .flyer-footer { margin-top: 20px; background: white; color: black; border-radius: 12px; padding: 10px; font-weight: 800; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .premium-card { border: none; border-radius: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .premium-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
<?php include("../func/bc-admin-header.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AI Marketing Agent</h1>
        <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li><li class="breadcrumb-item active">Marketing</li></ol></nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-12">
                <div class="ai-marketing-header mb-4 shadow animate__animated animate__fadeInDown">
                    <h2 class="fw-bold mb-2">Market Smarter with AI ⚡</h2>
                    <p class="mb-0 opacity-75">Generate viral WhatsApp status ads and business strategies in seconds. Personalized for <strong><?php echo $get_logged_admin_details['company_name']; ?></strong>.</p>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card premium-card">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-4">Ad Generator</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Service to Promote</label>
                                <select name="service" class="form-select border-0 bg-light p-3 rounded-3">
                                    <option>Instant Cheap Data (All Networks)</option>
                                    <option>Airtel/MTN Gifting Data</option>
                                    <option>Electricity & Utility Bills</option>
                                    <option>DStv/GOtv/Startimes Subscription</option>
                                    <option>General Wallet Funding Promo</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Desired Tone</label>
                                <select name="tone" class="form-select border-0 bg-light p-3 rounded-3">
                                    <option>Professional & Trustworthy</option>
                                    <option>High-Energy & Excited</option>
                                    <option>Short & Mysterious (Teaser)</option>
                                    <option>Urgent (Last Call)</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Target Audience</label>
                                <input name="target" type="text" class="form-control border-0 bg-light p-3 rounded-3" placeholder="e.g. Students, Resellers" value="All Customers">
                            </div>
                            <button type="submit" name="generate-ad" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm">
                                <i class="bi bi-stars me-2"></i>Generate Ad Strategy
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <?php if (isset($generated_copy)): ?>
                <div class="card premium-card animate__animated animate__zoomIn">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title fw-bold mb-0">Your Ad Copy</h5>
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Generated via <?php echo strtoupper($assigned_model); ?></span>
                        </div>
                        <div class="generated-box" id="copyText"><?php echo $generated_copy; ?><button class="btn btn-primary btn-sm copy-btn" onclick="copyToClipboard()"><i class="bi bi-clipboard me-1"></i>Copy</button></div>
                        
                        <div class="mt-4 p-3 bg-primary bg-opacity-10 rounded-4 border border-primary border-opacity-10">
                            <h6 class="fw-bold mb-2 text-primary small"><i class="bi bi-lightbulb me-1"></i>Marketing Tip:</h6>
                            <p class="small mb-0 text-dark opacity-75">Posting this copy with a screenshot of your <strong>Dashboard</strong> or <strong>Successful Transactions</strong> significantly increases trust and conversions!</p>
                        </div>
                    </div>
                </div>

                <div class="card premium-card mt-4 overflow-hidden animate__animated animate__fadeInUp">
                    <div class="p-4 bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="bi bi-palette me-2"></i>Status Flyer Preview</h5>
                        <button class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="alert('Hint: Take a screenshot of the preview below!')">Download Flyer</button>
                    </div>
                    <div class="card-body p-5 bg-light d-flex justify-content-center">
                        <div id="flyer-preview" class="flyer-canvas shadow-lg">
                            <div class="flyer-glass">
                                <div class="flyer-header">
                                    <h3 class="mb-0 fw-bold"><?php echo strtoupper($get_logged_admin_details['company_name']); ?></h3>
                                    <div style="width:30px;height:2px;background:var(--admin-primary);margin:10px auto;"></div>
                                </div>
                                <div class="flyer-content">
                                    <p id="flyer-text" style="font-size: 0.95rem;"><?php echo mb_strimwidth($generated_copy, 0, 250, "..."); ?></p>
                                </div>
                                <div class="flyer-footer">
                                    <span><?php echo $get_logged_admin_details['website_url']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card premium-card text-center py-5">
                    <div class="card-body">
                        <div class="mb-4 text-primary opacity-25">
                            <i class="bi bi-megaphone" style="font-size: 5rem;"></i>
                        </div>
                        <h4 class="fw-bold">Ready to Scale?</h4>
                        <p class="text-muted mx-auto" style="max-width: 400px;">Tell us what you want to sell, and our AI Marketing Agent will craft the perfect message to drive sales.</p>
                        <div class="mt-4 d-flex justify-content-center gap-3">
                             <div class="p-3 bg-light rounded-4 text-start" style="width: 150px;">
                                <i class="bi bi-whatsapp text-success mb-2 d-block fs-4"></i>
                                <span class="small fw-bold">Status Ads</span>
                             </div>
                             <div class="p-3 bg-light rounded-4 text-start" style="width: 150px;">
                                <i class="bi bi-envelope text-primary mb-2 d-block fs-4"></i>
                                <span class="small fw-bold">Email Copy</span>
                             </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script>
function copyToClipboard() {
    const text = document.getElementById('copyText').innerText.replace('Copy', '');
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        btn.classList.replace('btn-primary', 'btn-success');
        setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
            btn.classList.replace('btn-success', 'btn-primary');
        }, 2000);
    });
}
</script>

<?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
