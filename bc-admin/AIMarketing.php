<?php session_start();
include("../func/bc-admin-config.php");
include_once("../func/bc-ai-engine.php");

// ── Require AI enabled ──────────────────────────────────────────────────────
if (empty($get_logged_admin_details['ai_status'])) {
    $_SESSION['product_purchase_response'] = 'Enable AI features from AI Settings to use AI Marketing.';
    header("Location: Dashboard.php"); exit();
}

$vendor_id = (int)$get_logged_admin_details['id'];
$ai_result = null;
$ai_type   = '';

// ── Handle AI generation request ─────────────────────────────────────────────
if (isset($_POST['generate-ai-content'])) {
    $content_type = trim($_POST['content-type'] ?? '');
    $business_name = htmlspecialchars(strip_tags(trim($_POST['business-name'] ?? $get_logged_admin_details['site_name'] ?? 'My VTU Business')));
    $custom_note   = htmlspecialchars(strip_tags(trim($_POST['custom-note'] ?? '')));
    $ai_type       = $content_type;

    // Check token balance
    $tok_bal = (int)($get_logged_admin_details['ai_tokens'] ?? 0);
    $tok_cost = max(1, (int)getSuperAdminOption('ai_marketing_token_cost', '3'));

    if ($tok_bal < $tok_cost) {
        $_SESSION['product_purchase_response'] = "Insufficient AI tokens. You need $tok_cost tokens. Current balance: $tok_bal.";
        header("Location: AIMarketing.php"); exit();
    }

    $prompts = [
        'whatsapp_ad' => "Write a compelling WhatsApp marketing message for a Nigerian VTU business called '$business_name'. "
            . "Promote their airtime, data bundles, electricity token, and cable TV services. "
            . "Make it engaging, use appropriate emojis, and include a call-to-action. "
            . ($custom_note ? "Additional info: $custom_note. " : '')
            . "Max 200 words. Format for WhatsApp (bold with *asterisks* for emphasis).",

        'promo_sms'   => "Write a short promotional SMS (max 160 characters) for '$business_name', a VTU service provider in Nigeria. "
            . "Promote cheap airtime and data. Include urgency. "
            . ($custom_note ? "Note: $custom_note." : ''),

        'business_names' => "Suggest 10 creative, catchy Nigerian VTU business names for a mobile recharge and data service company. "
            . ($custom_note ? "Theme/style preference: $custom_note. " : '')
            . "Format as a numbered list. Each name should be unique, memorable, and easy to say.",

        'referral_message' => "Write a WhatsApp referral invitation message for '$business_name' VTU platform. "
            . "Encourage users to invite friends and earn bonuses. Make it sound exciting and trustworthy. "
            . "Max 150 words. Use WhatsApp-friendly formatting.",

        'customer_retention' => "Write a WhatsApp re-engagement message for a customer of '$business_name' who hasn't used the platform in 2 weeks. "
            . "Offer a reason to come back (mention services: airtime, data, DSTV, electricity). "
            . "Keep it friendly and personal. Max 100 words.",
    ];

    $prompt = $prompts[$content_type] ?? null;
    if (!$prompt) {
        $_SESSION['product_purchase_response'] = 'Invalid content type selected.';
        header("Location: AIMarketing.php"); exit();
    }

    $engine   = BcAiEngine::getInstance();
    $ai_result = $engine->chat($prompt);

    if (!empty($ai_result)) {
        // Deduct tokens
        $new_tok = $tok_bal - $tok_cost;
        mysqli_query($connection_server, "UPDATE sas_vendors SET ai_tokens='$new_tok' WHERE id='$vendor_id'");
        // Log
        $p_esc = mysqli_real_escape_string($connection_server, substr($prompt, 0, 200));
        mysqli_query($connection_server, "INSERT INTO sas_ai_transactions (vendor_id, username, prompt, tokens_used, channel, created_at) VALUES ('$vendor_id','".$get_logged_admin_details['email']."','$p_esc','$tok_cost','marketing',NOW())");
    } else {
        $_SESSION['product_purchase_response'] = 'AI engine is unavailable. Please try again later.';
        header("Location: AIMarketing.php"); exit();
    }
}
?>
<!DOCTYPE html>
<head>
    <title>AI Marketing Studio | <?php echo $get_all_super_admin_site_details['site_title']; ?></title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <style>
        .content-type-card { cursor:pointer; border:2px solid transparent; transition:all .2s; }
        .content-type-card:hover, .content-type-card.selected { border-color:var(--bs-primary); transform:translateY(-2px); }
        .ai-output { background:#f8f9fa; border-left:4px solid #0d6efd; border-radius:8px; padding:16px; white-space:pre-wrap; font-family:inherit; line-height:1.7; }
        .token-badge { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-radius:20px; padding:4px 14px; font-weight:700; }
    </style>
</head>
<body>
<?php include("../func/bc-admin-header.php"); ?>
<div class="pagetitle">
    <h1><i class="bi bi-magic text-primary me-2"></i>AI Marketing Studio</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="#">Home</a></li><li class="breadcrumb-item active">AI Marketing</li></ol></nav>
</div>
<section class="section">

<!-- Token Balance Banner -->
<div class="alert alert-primary border-0 rounded-4 d-flex align-items-center justify-content-between mb-4">
    <div><i class="bi bi-stars me-2"></i><strong>AI Marketing Studio</strong> — Generate marketing content powered by your local AI engine.</div>
    <span class="token-badge"><i class="bi bi-coin me-1"></i><?php echo number_format((int)($get_logged_admin_details['ai_tokens']??0)); ?> tokens</span>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <!-- Content Type Selector -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2 text-primary"></i>Select Content Type</h6></div>
            <div class="card-body p-4">
                <form method="post" id="gen-form">
                    <input type="hidden" name="content-type" id="selected-type" value="<?php echo htmlspecialchars($ai_type); ?>">
                    <div class="row g-2 mb-4">
                        <?php
                        $types = [
                            ['key'=>'whatsapp_ad','icon'=>'bi-whatsapp','label'=>'WhatsApp Ad','color'=>'text-success','tokens'=>3],
                            ['key'=>'promo_sms','icon'=>'bi-chat-text','label'=>'Promo SMS','color'=>'text-primary','tokens'=>2],
                            ['key'=>'business_names','icon'=>'bi-briefcase','label'=>'Business Names','color'=>'text-warning','tokens'=>3],
                            ['key'=>'referral_message','icon'=>'bi-people','label'=>'Referral Message','color'=>'text-info','tokens'=>3],
                            ['key'=>'customer_retention','icon'=>'bi-arrow-repeat','label'=>'Re-engage Message','color'=>'text-danger','tokens'=>2],
                        ];
                        foreach ($types as $t):
                        ?>
                        <div class="col-12">
                            <div class="card shadow-none border rounded-3 p-3 content-type-card <?php echo $ai_type===$t['key']?'selected':''; ?>"
                                 onclick="selectType('<?php echo $t['key']; ?>', this)">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $t['icon']; ?> fs-4 me-3 <?php echo $t['color']; ?>"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small"><?php echo $t['label']; ?></div>
                                    </div>
                                    <span class="badge bg-light text-muted border small"><?php echo $t['tokens']; ?> tokens</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Business Name</label>
                        <input name="business-name" type="text" class="form-control rounded-3"
                               value="<?php echo htmlspecialchars($get_logged_admin_details['site_name'] ?? ''); ?>" placeholder="My VTU Business"/>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Custom Instructions <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="custom-note" rows="2" class="form-control rounded-3"
                                  placeholder="e.g. Focus on data bundles, target students..."></textarea>
                    </div>

                    <button name="generate-ai-content" type="submit" class="btn btn-primary w-100 rounded-3 fw-bold py-2 shadow-sm" id="gen-btn">
                        <i class="bi bi-stars me-2"></i>Generate with AI
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 rounded-4 h-100">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-magic me-2 text-primary"></i>AI Generated Content</h6>
                <?php if (!empty($ai_result)): ?>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary rounded-start-3" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                    <button class="btn btn-outline-success rounded-end-3" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($ai_result)): ?>
                    <div class="ai-output" id="ai-output"><?php echo htmlspecialchars($ai_result); ?></div>
                    <div class="mt-3 text-end">
                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Generated by your local AI — keep or edit as needed</small>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-magic opacity-25" style="font-size:4rem"></i>
                        <h5 class="mt-3 text-muted fw-bold">Your AI content appears here</h5>
                        <p class="text-muted small">Select a content type on the left and click <strong>Generate with AI</strong></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</section>
<?php include("../func/bc-admin-footer.php"); ?>

<script>
function selectType(type, el) {
    document.querySelectorAll('.content-type-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-type').value = type;
}
function copyResult() {
    const text = document.getElementById('ai-output')?.innerText;
    if (text) navigator.clipboard.writeText(text).then(() => alert('Copied to clipboard!'));
}
document.getElementById('gen-form').addEventListener('submit', function(e) {
    if (!document.getElementById('selected-type').value) {
        e.preventDefault(); alert('Please select a content type first.');
    }
    document.getElementById('gen-btn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    document.getElementById('gen-btn').disabled = true;
});
</script>
<?php if(isset($_SESSION["product_purchase_response"])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>Swal.fire('Notification','<?php echo addslashes($_SESSION["product_purchase_response"]); ?>','info');fetch('/func/unset-product-response.php');</script>
<?php unset($_SESSION["product_purchase_response"]); endif; ?>
</body></html>
