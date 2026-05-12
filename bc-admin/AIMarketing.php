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
    if (!$ai->isModelCompatible($assigned_model)) {
        $assigned_model = $ai->getDefaultModel();
    }
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
        :root { 
            --admin-primary: <?php echo $vendor_primary_color ?? '#4f46e5'; ?>; 
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.4);
        }
        
        body { background-color: #f8fafc; }

        #main { 
            transition: all 0.3s;
            padding: 20px 30px;
        }

        .ai-marketing-header { 
            background: linear-gradient(135deg, var(--admin-primary), #1e1b4b); 
            color: white; 
            border-radius: 2rem; 
            padding: 3.5rem 2.5rem; 
            position: relative; 
            overflow: hidden;
            margin-bottom: 2.5rem !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .ai-marketing-header::after {
            content: 'MARKETING'; position: absolute; right: -20px; bottom: -20px; font-size: 8rem; font-weight: 900; opacity: 0.05; letter-spacing: 10px;
        }
        .ai-marketing-header .badge { backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); }

        .glass-card { 
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 2rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.04); 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .glass-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,0.08); border-color: var(--admin-primary); }

        .form-control-premium, .form-select-premium {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .form-control-premium:focus, .form-select-premium:focus {
            background: #fff;
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .generated-box { 
            background: rgba(255, 255, 255, 0.5); 
            border: 2px solid #e2e8f0; 
            border-radius: 1.5rem; 
            padding: 2rem; 
            position: relative; 
            white-space: pre-wrap; 
            font-size: 1.05rem; 
            line-height: 1.8;
            color: #1e293b;
            font-family: 'Inter', sans-serif;
        }
        
        /* Smartphone Frame */
        .phone-frame {
            width: 320px;
            height: 640px;
            background: #111;
            border-radius: 40px;
            padding: 12px;
            position: relative;
            box-shadow: 0 50px 100px rgba(0,0,0,0.3);
            border: 4px solid #333;
        }
        .phone-screen {
            width: 100%;
            height: 100%;
            border-radius: 32px;
            overflow: hidden;
            position: relative;
            background: #000;
        }
        .phone-notch {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 25px;
            background: #111;
            border-bottom-left-radius: 18px;
            border-bottom-right-radius: 18px;
            z-index: 10;
        }
        
        .flyer-canvas { 
            width: 100%; height: 100%; 
            background: <?php echo $bg_templates[$current_bg]['css']; ?>;
            position: relative;
            padding: 20px; display: flex; align-items: center; justify-content: center;
            border: <?php echo $bg_templates[$current_bg]['border'] ?? 'none'; ?>;
            transition: all 0.5s ease;
        }
        .flyer-glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px; width: 100%; height: 90%;
            display: flex; flex-direction: column; padding: 25px; color: white;
            text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .flyer-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 20px; }
        .flyer-content { flex-grow: 1; font-size: 0.95rem; line-height: 1.6; display: flex; align-items: center; justify-content: center; font-weight: 500; overflow: hidden; }
        .flyer-footer { margin-top: 20px; background: rgba(255,255,255,0.9); color: #000; border-radius: 12px; padding: 10px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }

        /* Template Selector */
        .bg-thumb { 
            width: 100%; height: 50px; border-radius: 10px; cursor: pointer; border: 3px solid transparent; 
            transition: all 0.3s; position: relative; display: flex; align-items: center; justify-content: center;
        }
        .bg-thumb.active { border-color: var(--admin-primary); transform: scale(1.1); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .bg-thumb i { color: white; font-size: 1.2rem; display: none; }
        .bg-thumb.active i { display: block; }
        
        .btn-premium {
            background: linear-gradient(135deg, var(--admin-primary), #6366f1);
            color: white; border: none; border-radius: 1rem; padding: 1rem 2rem;
            font-weight: 700; transition: all 0.3s; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);
        }
        .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.3); color: white; filter: brightness(1.1); }

        @media (max-width: 991px) {
            #main { padding: 20px 15px; }
            .ai-marketing-header { padding: 2.5rem 1.5rem; text-align: center; }
            .ai-marketing-header::after { font-size: 5rem; }
        }

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
        <div class="container-fluid p-0">
            <div class="row g-4">
                <!-- Header Banner -->
                <div class="col-12">
                    <div class="ai-marketing-header animate__animated animate__fadeIn">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <h1 class="fw-bold mb-2">Power Up Your Sales 🚀</h1>
                                <p class="mb-0 fs-5 opacity-75">Our AI Marketing Agent crafts high-converting viral ads tailored for <strong><?php echo $biz_name; ?></strong>.</p>
                            </div>
                            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                                <span class="badge p-3 px-4 rounded-pill">
                                    <i class="bi bi-cpu me-2"></i>AI Engine: <?php echo strtoupper($assigned_model); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Left Column: Settings -->
                <div class="col-xl-4 col-lg-5">
                    <div class="glass-card mb-4 animate__animated animate__fadeInLeft">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                                    <i class="bi bi-magic text-primary fs-4"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Campaign Settings</h5>
                            </div>
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Service to Promote</label>
                                    <select name="service" class="form-select-premium w-100">
                                        <option>Instant Cheap Data (All Networks)</option>
                                        <option>SME Data Promo (MTN 1GB @ N230)</option>
                                        <option>Airtel/MTN Gifting Data</option>
                                        <option>Electricity & Utility Bills</option>
                                        <option>DStv/GOtv/Startimes Subscription</option>
                                        <option>General Wallet Funding Promo</option>
                                        <option>Become a Reseller Today</option>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Desired Tone</label>
                                    <select name="tone" class="form-select-premium w-100">
                                        <option>Professional & Trustworthy</option>
                                        <option>High-Energy & Excited</option>
                                        <option>Short & Mysterious (Teaser)</option>
                                        <option>Urgent (Last Call)</option>
                                        <option>Friendly & Community Based</option>
                                    </select>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Target Audience</label>
                                    <input name="target" type="text" class="form-control-premium w-100" placeholder="e.g. Students, Resellers" value="All Customers">
                                </div>
                                <button type="submit" name="generate-ad" class="btn-premium w-100">
                                    <i class="bi bi-stars me-2"></i>Generate Marketing Kit
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Studio Backgrounds -->
                    <div class="glass-card animate__animated animate__fadeInLeft" style="animation-delay: 0.1s;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                                    <i class="bi bi-palette text-primary fs-4"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Studio Backgrounds</h5>
                            </div>
                            <div class="row g-3">
                                <?php foreach($bg_templates as $key => $tpl): $is_active = ($key === $current_bg); ?>
                                <div class="col-4">
                                    <form method="POST">
                                        <input type="hidden" name="bg_name" value="<?php echo $key; ?>">
                                        <input type="hidden" name="set-bg" value="1">
                                        <div class="bg-thumb <?php echo $is_active?'active':''; ?>" style="background: <?php echo $tpl['css']; ?>; <?php echo $tpl['border'] ?? ''; ?>" onclick="this.parentElement.submit()">
                                            <i class="bi bi-check-lg"></i>
                                        </div>
                                        <div class="small text-center mt-2 fw-bold text-muted" style="font-size: 0.65rem;"><?php echo strtoupper($tpl['name']); ?></div>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Results & Preview -->
                <div class="col-xl-8 col-lg-7">
                    <?php if (isset($generated_copy)): ?>
                    <div class="glass-card mb-4 animate__animated animate__fadeInUp">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0"><i class="bi bi-chat-left-quote me-2 text-primary"></i>High-Converting Ad Copy</h5>
                                <button class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="copyToClipboard(this)">
                                    <i class="bi bi-clipboard me-2"></i>Copy Text
                                </button>
                            </div>
                            <div class="generated-box" id="copyText"><?php echo $generated_copy; ?></div>
                            
                            <div class="mt-4 p-4 bg-primary bg-opacity-10 rounded-4 border border-primary border-opacity-10 d-flex gap-4 align-items-center">
                                <div class="bg-white p-3 rounded-circle shadow-sm">
                                    <i class="bi bi-lightbulb-fill text-warning fs-3"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Marketing Expert Tip</h6>
                                    <p class="small mb-0 text-muted">Combine this text with the flyer below for your WhatsApp Status. High-quality visuals can increase engagement by up to 300%!</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                        <div class="card-header bg-dark p-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-white"><i class="bi bi-phone-vibrate me-2"></i>Status Flyer Preview</h5>
                            <button class="btn btn-outline-light btn-sm rounded-pill px-4 fw-bold" onclick="alert('💡 Pro-Tip: Take a screenshot of the phone preview below!')">
                                <i class="bi bi-camera me-2"></i>Save Flyer
                            </button>
                        </div>
                        <div class="card-body p-5 d-flex justify-content-center bg-light bg-opacity-50">
                            <!-- Premium Phone Frame -->
                            <div class="phone-frame">
                                <div class="phone-screen">
                                    <div class="phone-notch"></div>
                                    <div id="flyer-preview" class="flyer-canvas">
                                        <div class="flyer-glass">
                                            <div class="flyer-header">
                                                <h4 class="mb-0 fw-bold"><?php echo strtoupper($biz_name); ?></h4>
                                                <div style="width:40px;height:3px;background:rgba(255,255,255,0.5);margin:15px auto; border-radius: 10px;"></div>
                                            </div>
                                            <div class="flyer-content">
                                                <p id="flyer-text"><?php echo mb_strimwidth($generated_copy, 0, 320, "..."); ?></p>
                                            </div>
                                            <div class="flyer-footer">
                                                <span><i class="bi bi-globe me-2"></i><?php echo $get_logged_admin_details['website_url']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Welcome State -->
                    <div class="glass-card text-center p-5 animate__animated animate__fadeInRight">
                        <div class="card-body py-5">
                            <div class="mb-5">
                                <div class="d-inline-flex bg-primary bg-opacity-10 p-5 rounded-circle mb-4 animate__animated animate__pulse animate__infinite">
                                    <i class="bi bi-megaphone text-primary" style="font-size: 5rem;"></i>
                                </div>
                                <h2 class="fw-bold display-6">Ready to go Viral?</h2>
                                <p class="text-muted mx-auto fs-5 mb-0" style="max-width: 500px;">Select your service, pick a vibe, and let AI build your next winning marketing campaign in seconds.</p>
                            </div>
                            <div class="row g-4 justify-content-center">
                                 <div class="col-md-4">
                                    <div class="p-4 glass-card h-100 shadow-sm border-0">
                                        <i class="bi bi-whatsapp text-success mb-3 d-block fs-1"></i>
                                        <span class="fw-bold d-block">WhatsApp Ads</span>
                                    </div>
                                 </div>
                                 <div class="col-md-4">
                                    <div class="p-4 glass-card h-100 shadow-sm border-0">
                                        <i class="bi bi-instagram text-danger mb-3 d-block fs-1"></i>
                                        <span class="fw-bold d-block">Social Media</span>
                                    </div>
                                 </div>
                                 <div class="col-md-4">
                                    <div class="p-4 glass-card h-100 shadow-sm border-0">
                                        <i class="bi bi-envelope-check text-primary mb-3 d-block fs-1"></i>
                                        <span class="fw-bold d-block">Direct Marketing</span>
                                    </div>
                                 </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
function copyToClipboard(btn) {
    const text = document.getElementById('copyText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Copied!';
        btn.classList.replace('btn-primary', 'btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.replace('btn-success', 'btn-primary');
        }, 2000);
    });
}
</script>

<?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
