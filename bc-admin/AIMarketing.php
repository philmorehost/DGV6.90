<?php session_start();
include("../func/bc-admin-config.php");
include_once("../func/bc-ai-engine.php");

$title = "AI Marketing Studio";
$vendor_id = $get_logged_admin_details['id'];
$assigned_model = $get_logged_admin_details['ai_model_assigned'] ?: getSuperAdminOption('ai_default_model', 'gemini-1.5-flash');

// Business Name Fallback Logic
$site_q = mysqli_query($connection_server, "SELECT site_title FROM sas_site_details WHERE vendor_id='$vendor_id' LIMIT 1");
$site_data = mysqli_fetch_assoc($site_q);
$biz_name = $get_logged_admin_details['company_name'] ?? ($site_data['site_title'] ?? 'Our VTU Platform');

$current_bg = $get_logged_admin_details['ai_marketing_bg'] ?? 'midnight';

// Handle Background Selection
if (isset($_POST['set-bg'])) {
    $new_bg = bc_sanitize($_POST['bg_name'] ?? 'midnight');
    mysqli_query($connection_server, "UPDATE sas_vendors SET ai_marketing_bg='$new_bg' WHERE id='$vendor_id'");
    header("Location: AIMarketing.php"); exit();
}

if (isset($_POST['generate-ad'])) {
    $service = bc_sanitize($_POST['service'] ?? 'Airtime');
    $tone    = bc_sanitize($_POST['tone'] ?? 'Professional');
    $target  = bc_sanitize($_POST['target'] ?? 'Customers');

    $prompt = "You are a professional VTU Marketing Strategist. 
               Generate a high-converting WhatsApp status ad copy for a business called '$biz_name'.
               Service being promoted: $service. 
               Tone: $tone. 
               Target Audience: $target.
               Include attractive emojis, a catchy headline, and a clear Call to Action (CTA) pointing to our website: {$get_logged_admin_details['website_url']}.
               The design vibe for this ad is '$current_bg'.
               Keep it extremely engaging and concise for mobile readers.";
    
    $start_time = microtime(true);
    $ai = ai_engine();
    $result = $ai->chat($assigned_model, $prompt, ['temperature' => 0.85]);
    $duration = round((microtime(true) - $start_time) * 1000);

    if ($result['status'] === 'success') {
        $generated_copy = $result['response'];
        $tokens = strlen($prompt . $generated_copy) / 4; 
        
        $esc_res = mysqli_real_escape_string($connection_server, $generated_copy);
        mysqli_query($connection_server, "INSERT INTO sas_ai_transactions (vendor_id, username, prompt, response, tokens_burned, status, duration_ms) VALUES ('$vendor_id', 'admin_{$get_logged_admin_details['email']}', 'Marketing Ad: $service', '$esc_res', '$tokens', 'success', '$duration')");
        
        bc_log_ai_intelligence($vendor_id, 'marketing_strategy_generated', $generated_copy, ['service' => $service, 'tone' => $tone]);
    } else {
        $generated_copy = '❌ AI Error: ' . ($result['message'] ?? 'Unable to connect to AI engine.');
    }
}

$bg_templates = [
    'midnight' => ['name' => 'Midnight Aura', 'css' => 'linear-gradient(135deg, #0f172a, #1e293b)'],
    'solar'    => ['name' => 'Solar Flare', 'css' => 'linear-gradient(135deg, #f97316, #ef4444)'],
    'emerald'  => ['name' => 'Emerald Glass', 'css' => 'linear-gradient(135deg, #065f46, #064e3b)'],
    'royal'    => ['name' => 'Royal Velvet', 'css' => 'linear-gradient(135deg, #6d28d9, #4c1d95)'],
    'neon'     => ['name' => 'Cyber Neon', 'css' => '#000', 'border' => '2px solid #3b82f6']
];

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
        
        /* Layout Fixes to close gap between sidebar and card */
        #main { 
            margin-left: 290px !important; 
            padding: 20px 20px !important; 
            transition: all 0.3s;
        }
        @media (max-width: 1199px) { 
            #main { margin-left: 0 !important; padding: 20px 15px !important; } 
        }

        .ai-marketing-header { 
            background: linear-gradient(135deg, var(--admin-primary), #000); 
            color: white; border-radius: 1.5rem; padding: 2.5rem; 
            position: relative; overflow: hidden;
            margin-bottom: 2rem !important;
        }
        .ai-marketing-header::after {
            content: 'MARKETING'; position: absolute; right: -20px; bottom: -20px; font-size: 6rem; font-weight: 900; opacity: 0.05;
        }

        .premium-card { border: none; border-radius: 1.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.06); transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.03); }
        .premium-card:hover { transform: translateY(-5px); box-shadow: 0 15px 50px rgba(0,0,0,0.1); }

        .generated-box { 
            background: #fdfdfd; border: 1.5px dashed #cbd5e1; border-radius: 1.25rem; 
            padding: 1.5rem; position: relative; white-space: pre-wrap; font-size: 1rem; line-height: 1.7;
            color: #334155;
        }
        .copy-btn { position: absolute; top: 15px; right: 15px; border-radius: 0.75rem; font-weight: 600; padding: 0.5rem 1rem; }
        
        /* Flyer Styles */
        .flyer-canvas { 
            width: 320px; height: 568px; 
            background: <?php echo $bg_templates[$current_bg]['css']; ?>;
            border-radius: 28px; overflow: hidden; position: relative;
            padding: 18px; display: flex; align-items: center; justify-content: center;
            border: <?php echo $bg_templates[$current_bg]['border'] ?? '8px solid #1e293b'; ?>;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .flyer-glass {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 22px; width: 100%; height: 90%;
            display: flex; flex-direction: column; padding: 25px; color: white;
            text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .flyer-header { border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 15px; margin-bottom: 20px; }
        .flyer-content { flex-grow: 1; font-size: 1rem; line-height: 1.6; display: flex; align-items: center; justify-content: center; font-weight: 500; overflow: hidden; }
        .flyer-footer { margin-top: 20px; background: white; color: black; border-radius: 14px; padding: 12px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.5px; }

        /* Template Selector */
        .bg-thumb { 
            width: 100%; height: 60px; border-radius: 12px; cursor: pointer; border: 3px solid transparent; 
            transition: all 0.2s; position: relative; display: flex; align-items: center; justify-content: center;
        }
        .bg-thumb.active { border-color: var(--admin-primary); transform: scale(1.05); }
        .bg-thumb i { color: white; font-size: 1.5rem; display: none; }
        .bg-thumb.active i { display: block; }
        .bg-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-top: 5px; text-align: center; }

    </style>
</head>
<body>
<?php include("../func/bc-admin-header.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AI Marketing Studio</h1>
        <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li><li class="breadcrumb-item active">Marketing Studio</li></ol></nav>
    </div>

    <section class="section">
        <div class="row g-4">
            <div class="col-12">
                <div class="ai-marketing-header shadow animate__animated animate__fadeIn">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="fw-bold mb-2">Power Up Your Sales 🚀</h2>
                            <p class="mb-0 opacity-75">Our AI Marketing Agent crafts high-converting viral ads tailored for <strong><?php echo $biz_name; ?></strong>.</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-white bg-opacity-20 p-2 px-3 rounded-pill small">Model: <?php echo strtoupper($assigned_model); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card premium-card mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-4"><i class="bi bi-magic me-2 text-primary"></i>Campaign Settings</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Service to Promote</label>
                                <select name="service" class="form-select border-0 bg-light p-3 rounded-3">
                                    <option>Instant Cheap Data (All Networks)</option>
                                    <option>SME Data Promo (MTN 1GB @ N230)</option>
                                    <option>Airtel/MTN Gifting Data</option>
                                    <option>Electricity & Utility Bills</option>
                                    <option>DStv/GOtv/Startimes Subscription</option>
                                    <option>General Wallet Funding Promo</option>
                                    <option>Become a Reseller Today</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Desired Tone</label>
                                <select name="tone" class="form-select border-0 bg-light p-3 rounded-3">
                                    <option>Professional & Trustworthy</option>
                                    <option>High-Energy & Excited</option>
                                    <option>Short & Mysterious (Teaser)</option>
                                    <option>Urgent (Last Call)</option>
                                    <option>Friendly & Community Based</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Target Audience</label>
                                <input name="target" type="text" class="form-control border-0 bg-light p-3 rounded-3" placeholder="e.g. Students, Resellers" value="All Customers">
                            </div>
                            <button type="submit" name="generate-ad" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm">
                                <i class="bi bi-stars me-2"></i>Generate Marketing Kit
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Background Selector -->
                <div class="card premium-card">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-3"><i class="bi bi-palette me-2 text-primary"></i>Studio Backgrounds</h5>
                        <div class="row g-2">
                            <?php foreach($bg_templates as $key => $tpl): $is_active = ($key === $current_bg); ?>
                            <div class="col-4 mb-2">
                                <form method="POST">
                                    <input type="hidden" name="bg_name" value="<?php echo $key; ?>">
                                    <div class="bg-thumb <?php echo $is_active?'active':''; ?>" style="background: <?php echo $tpl['css']; ?>; <?php echo $tpl['border'] ?? ''; ?>" onclick="this.parentElement.submit()">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                    <div class="bg-label"><?php echo $tpl['name']; ?></div>
                                    <input type="hidden" name="set-bg" value="1">
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <?php if (isset($generated_copy)): ?>
                <div class="card premium-card animate__animated animate__zoomIn">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title fw-bold mb-0">High-Converting Ad Copy</h5>
                            <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="copyToClipboard()"><i class="bi bi-clipboard me-1"></i>Copy Text</button>
                        </div>
                        <div class="generated-box" id="copyText"><?php echo $generated_copy; ?></div>
                        
                        <div class="mt-4 p-3 bg-primary bg-opacity-10 rounded-4 border border-primary border-opacity-10">
                            <div class="d-flex gap-3 align-items-start">
                                <i class="bi bi-lightbulb text-primary fs-4 mt-1"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-primary">Smart Marketing Tip:</h6>
                                    <p class="small mb-0 text-dark opacity-75">Combine this text with the flyer below on your WhatsApp Status. Users who see professional visuals are 3.5x more likely to click!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card premium-card mt-4 overflow-hidden animate__animated animate__fadeInUp">
                    <div class="p-4 bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="bi bi-phone me-2"></i>Status Flyer Preview</h5>
                        <button class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="alert('💡 Pro-Tip: Take a screenshot of the preview below for your status!')">Save Flyer</button>
                    </div>
                    <div class="card-body p-5 bg-light d-flex justify-content-center">
                        <div id="flyer-preview" class="flyer-canvas shadow-lg">
                            <div class="flyer-glass">
                                <div class="flyer-header">
                                    <h3 class="mb-0 fw-bold"><?php echo strtoupper($biz_name); ?></h3>
                                    <div style="width:30px;height:2px;background:#fff;margin:10px auto; opacity:0.5;"></div>
                                </div>
                                <div class="flyer-content">
                                    <p id="flyer-text" style="font-size: 0.9rem; font-weight: 500; font-family: sans-serif;"><?php echo mb_strimwidth($generated_copy, 0, 280, "..."); ?></p>
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
                    <div class="card-body p-5">
                        <div class="mb-4 text-primary opacity-10">
                            <i class="bi bi-megaphone-fill" style="font-size: 6rem;"></i>
                        </div>
                        <h3 class="fw-bold">Ready to Viral?</h3>
                        <p class="text-muted mx-auto mb-4" style="max-width: 450px;">Choose a service, select a vibe, and let the AI build your next winning marketing campaign in seconds.</p>
                        <div class="row g-3 justify-content-center">
                             <div class="col-md-4">
                                <div class="p-4 bg-light rounded-4 text-center h-100">
                                    <i class="bi bi-whatsapp text-success mb-3 d-block fs-2"></i>
                                    <span class="small fw-bold d-block">WhatsApp Ads</span>
                                </div>
                             </div>
                             <div class="col-md-4">
                                <div class="p-4 bg-light rounded-4 text-center h-100">
                                    <i class="bi bi-instagram text-danger mb-3 d-block fs-2"></i>
                                    <span class="small fw-bold d-block">Social Media</span>
                                </div>
                             </div>
                             <div class="col-md-4">
                                <div class="p-4 bg-light rounded-4 text-center h-100">
                                    <i class="bi bi-envelope-check text-primary mb-3 d-block fs-2"></i>
                                    <span class="small fw-bold d-block">Direct Marketing</span>
                                </div>
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
    const text = document.getElementById('copyText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        if(btn) {
            btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
            btn.classList.replace('btn-primary', 'btn-success');
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
                btn.classList.replace('btn-success', 'btn-primary');
            }, 2000);
        } else {
            alert('Text copied to clipboard!');
        }
    });
}
</script>

<?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
