<?php session_start();
include("../func/bc-admin-config.php");
include_once("../func/bc-ai-engine.php");

$title = "AI Marketing Agent";

if (isset($_POST['generate-ad'])) {
    $service = bc_sanitize($_POST['service'] ?? 'Airtime');
    $tone    = bc_sanitize($_POST['tone'] ?? 'Professional');
    $target  = bc_sanitize($_POST['target'] ?? 'Customers');

    $prompt = "Generate a high-converting WhatsApp status ad copy for my VTU business. 
               Service: $service. 
               Tone: $tone. 
               Target Audience: $target.
               Include emojis and a clear Call to Action (CTA). Keep it concise.";
    
    $ai = ai_engine();
    $result = $ai->generate('phi4-mini', $prompt);
    $generated_copy = $result['status'] === 'success' ? $result['response'] : '❌ AI Error: ' . ($result['message'] ?? 'Unknown');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $title; ?> | <?php echo $get_all_super_admin_site_details['site_title']; ?></title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        .ai-marketing-header { background: linear-gradient(135deg, #4f46e5, #9333ea); color: white; border-radius: 1rem; padding: 2.5rem; }
        .generated-box { background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 0.75rem; padding: 1.5rem; position: relative; white-space: pre-wrap; }
        .copy-btn { position: absolute; top: 10px; right: 10px; }

        /* Flyer Styles */
        .flyer-canvas { 
            width: 320px; height: 568px; /* 9:16 aspect ratio */
            background: url('https://images.unsplash.com/photo-1614850523296-d8c1af93d400?w=800') center/cover;
            border-radius: 20px; overflow: hidden; position: relative;
            padding: 20px; display: flex; align-items: center; justify-content: center;
        }
        .flyer-glass {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px; width: 100%; height: 80%;
            display: flex; flex-direction: column; padding: 20px; color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3); text-align: center;
        }
        .flyer-header { border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 10px; margin-bottom: 20px; }
        .flyer-content { flex-grow: 1; font-size: 1.1rem; line-height: 1.4; overflow: hidden; display: flex; align-items: center; }
        .flyer-footer { margin-top: 15px; font-weight: bold; background: white; color: #4f46e5; border-radius: 10px; padding: 5px; text-shadow: none; font-size: 0.8rem; }
    </style>
</head>
<body>
<?php include("../func/bc-admin-header.php"); ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AI Marketing Agent</h1>
        <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li><li class="breadcrumb-item active">Marketing</li></ol></nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-12">
                <div class="ai-marketing-header mb-4 shadow-sm">
                    <h2 class="fw-bold mb-2">Automate Your Growth 🚀</h2>
                    <p class="mb-0 opacity-75">Let our AI generate high-converting WhatsApp ads and marketing strategies for your business.</p>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold">Ad Copy Generator</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Service Type</label>
                                <select name="service" class="form-select rounded-3">
                                    <option>Cheap MTN Data</option>
                                    <option>Airtel 5G Data</option>
                                    <option>Electricity Bill Payment</option>
                                    <option>Cable TV (DStv/GOtv)</option>
                                    <option>General VTU Business</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Tone of Voice</label>
                                <select name="tone" class="form-select rounded-3">
                                    <option>Professional</option>
                                    <option>Excited & Hype</option>
                                    <option>Friendly & Helpful</option>
                                    <option>Urgent (Promo)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Target Audience</label>
                                <input name="target" type="text" class="form-control rounded-3" placeholder="e.g. Students, Business Owners" value="Customers">
                            </div>
                            <button type="submit" name="generate-ad" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">
                                <i class="bi bi-magic me-2"></i>Generate Ad Copy
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <?php if (isset($generated_copy)): ?>
                <div class="card shadow-sm border-0 rounded-4 animate__animated animate__fadeIn">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold">Result</h5>
                        <div class="generated-box" id="copyText"><?php echo $generated_copy; ?><button class="btn btn-light btn-sm copy-btn" onclick="copyToClipboard()"><i class="bi bi-clipboard"></i></button></div>
                        <p class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i> You can copy and paste this directly to your WhatsApp Status.</p>
                    </div>
                </div>

                <!-- Flyer Generator -->
                <div class="card shadow-sm border-0 rounded-4 mt-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="p-4 bg-light border-bottom">
                            <h5 class="fw-bold mb-0"><i class="bi bi-image me-2 text-info"></i>Visual Status Flyer</h5>
                        </div>
                        <div class="p-4 d-flex justify-content-center bg-secondary bg-opacity-10">
                            <!-- Flyer Template (CSS-based) -->
                            <div id="flyer-preview" class="flyer-canvas shadow-lg">
                                <div class="flyer-glass">
                                    <div class="flyer-header">
                                        <h2 class="mb-0">FAST VTU</h2>
                                        <small>Reliable & Instant</small>
                                    </div>
                                    <div class="flyer-content">
                                        <p id="flyer-text"><?php echo $generated_copy; ?></p>
                                    </div>
                                    <div class="flyer-footer">
                                        <span>Order Now via our App!</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 text-center">
                            <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i> Hint: Screenshot this and crop to use as your WhatsApp Status flyer!</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card shadow-sm border-0 rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-chat-dots opacity-25" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 text-muted">Ready to write?</h5>
                        <p class="text-muted small">Fill out the form on the left to generate your first ad.</p>
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
        alert('Ad copy copied to clipboard!');
    });
}
</script>

<?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
