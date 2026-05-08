<?php
/**
 * DGV6.90 — Monthly AI Blueprint Audit Cron
 * DGV6.90 AI Edition v7.0
 *
 * Schedule: 0 8 1 * *  (8:00 AM on the 1st of every month)
 * Cron:     php /path/to/cron/ai_monthly_blueprint.php >> /path/to/logs/blueprint.log 2>&1
 *
 * What this does:
 *   1. Scans the entire codebase structure (meta-analysis — fast, no full code upload)
 *   2. Pulls platform stats from the DB (transactions, users, errors, revenue)
 *   3. Sends structured analysis to the local Ollama AI engine
 *   4. Generates a professional, agent-ready Blueprint HTML email
 *   5. Emails it to the super admin and stores in DB for archive access
 *
 * The output Blueprint is formatted so it can be copied directly into
 * a new AI Agent session as a task brief.
 */

define('CRON_CLI', true);
require_once __DIR__ . '/../func/bc-connect.php';
require_once __DIR__ . '/../func/bc-ai-engine.php';

$start_time = microtime(true);
echo "[BLUEPRINT] " . date('Y-m-d H:i:s') . " — Starting monthly AI Blueprint Audit\n";

// ── Check AI availability ─────────────────────────────────────────────────────
$ai_available = false;
try {
    $engine   = BcAiEngine::getInstance();
    $ping     = $engine->chat("Respond with exactly: READY");
    $ai_available = str_contains(strtoupper($ping ?? ''), 'READY');
} catch (Exception $e) {}

if (!$ai_available) {
    echo "[BLUEPRINT] ⚠ AI engine offline. Cannot generate Blueprint.\n";
    exit(0);
}

// ── Fetch super admin email ────────────────────────────────────────────────────
$admin_email = '';
$admin_name  = 'Super Admin';
$eq = mysqli_query($connection_server, "SELECT option_value FROM sas_super_admin_options WHERE option_name='admin_email' LIMIT 1");
if ($eq && $er = mysqli_fetch_assoc($eq)) $admin_email = $er['option_value'];
$nq = mysqli_query($connection_server, "SELECT option_value FROM sas_super_admin_options WHERE option_name='site_title' LIMIT 1");
if ($nq && $nr = mysqli_fetch_assoc($nq)) $site_title = $nr['option_value'];
$site_title ??= 'VTU Platform';

if (empty($admin_email)) {
    echo "[BLUEPRINT] ⚠ No super admin email found. Cannot send Blueprint.\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 1: CODEBASE META-ANALYSIS
// We do NOT send the full source code — we send a structured summary.
// This is fast, privacy-safe, and produces better AI output.
// ─────────────────────────────────────────────────────────────────────────────
echo "[BLUEPRINT] Step 1: Scanning codebase...\n";

$root        = dirname(__DIR__);
$php_version = PHP_VERSION;
$php_files   = iterator_to_array(
    new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)),
        '/\.php$/i'
    )
);
$total_files = count($php_files);
$total_lines = 0;
$file_index  = []; // [dir => [filename, ...]]

foreach ($php_files as $file) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    $dir = $parts[0] ?? 'root';
    $file_index[$dir][] = $parts[count($parts) - 1];
    $total_lines += count(file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
}
ksort($file_index);

$dir_summary = [];
foreach ($file_index as $dir => $files) {
    $dir_summary[] = "  /$dir/ — " . count($files) . " PHP files";
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 2: DATABASE STATISTICS
// ─────────────────────────────────────────────────────────────────────────────
echo "[BLUEPRINT] Step 2: Gathering DB statistics...\n";

$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end   = date('Y-m-t', strtotime('-1 month'));

function safe_query_val(mysqli $conn, string $sql, string $col): string {
    $q = @mysqli_query($conn, $sql);
    return $q ? (string)(mysqli_fetch_assoc($q)[$col] ?? '0') : '0';
}

$stats = [
    'total_vendors'      => safe_query_val($connection_server, "SELECT COUNT(*) c FROM sas_vendors", 'c'),
    'total_users'        => safe_query_val($connection_server, "SELECT COUNT(*) c FROM sas_users", 'c'),
    'total_transactions' => safe_query_val($connection_server, "SELECT COUNT(*) c FROM sas_transactions", 'c'),
    'monthly_tx'         => safe_query_val($connection_server, "SELECT COUNT(*) c FROM sas_transactions WHERE DATE(date) BETWEEN '$last_month_start' AND '$last_month_end'", 'c'),
    'monthly_revenue'    => safe_query_val($connection_server, "SELECT COALESCE(SUM(discounted_amount),0) c FROM sas_transactions WHERE status=1 AND DATE(date) BETWEEN '$last_month_start' AND '$last_month_end'", 'c'),
    'failed_tx_rate'     => safe_query_val($connection_server, "SELECT ROUND(SUM(status=3)/COUNT(*)*100,1) c FROM sas_transactions WHERE DATE(date) BETWEEN '$last_month_start' AND '$last_month_end'", 'c'),
    'new_users_monthly'  => safe_query_val($connection_server, "SELECT COUNT(*) c FROM sas_users WHERE DATE(reg_date) BETWEEN '$last_month_start' AND '$last_month_end'", 'c'),
    'ai_tx_monthly'      => safe_query_val($connection_server, "SELECT COUNT(*) c FROM sas_ai_transactions WHERE DATE(created_at) BETWEEN '$last_month_start' AND '$last_month_end'", 'c'),
];

// Top services by volume
$top_services_q = @mysqli_query($connection_server,
    "SELECT type_alternative, COUNT(*) cnt FROM sas_transactions WHERE status=1 AND DATE(date) BETWEEN '$last_month_start' AND '$last_month_end' GROUP BY type_alternative ORDER BY cnt DESC LIMIT 5");
$top_services = [];
if ($top_services_q) {
    while ($ts = mysqli_fetch_assoc($top_services_q)) $top_services[] = "{$ts['type_alternative']} ({$ts['cnt']} tx)";
}

// DB tables list
$tables_q = @mysqli_query($connection_server, "SHOW TABLES");
$table_list = [];
if ($tables_q) while ($tr = mysqli_fetch_row($tables_q)) $table_list[] = $tr[0];

// ─────────────────────────────────────────────────────────────────────────────
// STEP 3: BUILD AI PROMPT
// ─────────────────────────────────────────────────────────────────────────────
echo "[BLUEPRINT] Step 3: Building AI analysis prompt...\n";

$dir_list     = implode("\n", $dir_summary);
$top_svc_str  = implode(', ', $top_services) ?: 'N/A';
$table_count  = count($table_list);
$month_label  = date('F Y', strtotime('-1 month'));
$revenue_fmt  = '₦' . number_format((float)$stats['monthly_revenue'], 2);

$prompt = <<<PROMPT
You are a Senior Software Architect and Fintech Consultant reviewing a Nigerian VTU (Virtual Top-Up) SaaS platform called "$site_title" running PHP $php_version. 

## Platform Profile
- Total PHP files: $total_files across these modules:
$dir_list
- Total lines of code: ~$total_lines
- Database: MySQL, $table_count tables
- PHP version: $php_version (modernized to PHP 8.3+)
- AI integration: Local Ollama LLM engine (privacy-first)
- Mobile apps: Android + iOS

## Performance Stats — $month_label
- Vendors: {$stats['total_vendors']}
- End Users: {$stats['total_users']} total, {$stats['new_users_monthly']} new this month
- Transactions: {$stats['monthly_tx']} this month (total: {$stats['total_transactions']})
- Revenue: $revenue_fmt
- Failed Transaction Rate: {$stats['failed_tx_rate']}%
- AI Interactions: {$stats['ai_tx_monthly']} this month
- Top Services: $top_svc_str

## Known Existing Features
- Services: Airtime, Data, Cable TV, Electricity, Betting, Exam, Gift Cards, Crypto, BulkSMS, Recharge Cards
- Security: CSRF protection, rate limiting, AI Security Sentinel, atomic wallet transactions
- AI: Chat assistant, voice intent parser, marketing studio, daily business briefing, dormant user alerts
- Admin: Multi-vendor SaaS (vendors have sub-admins, smart/agent/API pricing tiers)
- Payments: Paystack, Flutterwave, manual payment, wallet funding
- WhatsApp: Automated alerts via Baileys bridge

## Your Task
Generate a comprehensive monthly Blueprint report with EXACTLY these 7 sections. Be specific, actionable, and realistic for a Nigerian fintech platform. Format for email delivery.

### SECTION 1: EXECUTIVE SUMMARY (3-4 sentences summarizing the platform health and top priorities)

### SECTION 2: NEW FEATURE SUGGESTIONS (5-7 ideas with: Feature Name | Priority: High/Med/Low | Why: rationale | Implementation: brief technical approach)
Focus on: Nigeria-specific financial features, user engagement, revenue growth

### SECTION 3: SECURITY AUDIT RECOMMENDATIONS (4-6 items with: Issue | Risk Level | Fix)
Based on common PHP 8.3/fintech vulnerabilities, not just what's already implemented

### SECTION 4: SEO & DISCOVERABILITY (4-5 actionable items specific to VTU/fintech in Nigeria)

### SECTION 5: UI/UX IMPROVEMENTS (4-5 items with: Problem | Solution | Impact)

### SECTION 6: PERFORMANCE OPTIMIZATIONS (3-4 technical improvements)
Consider: DB query optimization, caching, CDN, PHP OPcache, lazy loading

### SECTION 7: AI AGENT TASK BRIEFS (3 ready-to-use prompts an AI coding agent can act on immediately)
Format each as: "Task: [title]\nContext: [what exists]\nObjective: [what to build]\nFiles to modify: [file list]\nAcceptance criteria: [how to verify]"

Be direct, specific, and avoid generic advice. Assume the developer will hand this document to an AI coding agent.
PROMPT;

// ─────────────────────────────────────────────────────────────────────────────
// STEP 4: GET AI RESPONSE (this may take 30-120 seconds for large models)
// ─────────────────────────────────────────────────────────────────────────────
echo "[BLUEPRINT] Step 4: Querying AI engine (this may take 1-2 minutes)...\n";

$blueprint_text = $engine->chat($prompt);

if (empty($blueprint_text)) {
    echo "[BLUEPRINT] ❌ AI returned empty response. Aborting.\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// STEP 5: FORMAT AS PROFESSIONAL HTML EMAIL
// ─────────────────────────────────────────────────────────────────────────────
echo "[BLUEPRINT] Step 5: Formatting Blueprint email...\n";

// Convert markdown-style text to HTML
function blueprint_to_html(string $text): string {
    // Section headers
    $text = preg_replace('/^### (.+)$/m', '<h3 class="section-header">$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h2 class="section-title">$1</h2>', $text);
    // Bold
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    // Bullets
    $text = preg_replace('/^[-•]\s+(.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);
    // Code blocks
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    // Line breaks
    $text = nl2br($text);
    return $text;
}

$blueprint_html_body = blueprint_to_html(htmlspecialchars($blueprint_text, ENT_QUOTES, 'UTF-8'));
$generated_date  = date('d F Y, H:i');
$elapsed         = round(microtime(true) - $start_time);

$email_html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DGV AI Blueprint — {$month_label}</title>
<style>
  body { margin:0; padding:0; background:#f0f4f8; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color:#1a202c; }
  .wrapper { max-width:720px; margin:32px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.1); }
  .header { background:linear-gradient(135deg,#1e1b4b 0%,#4c1d95 50%,#7c3aed 100%); color:#fff; padding:40px 48px; }
  .header h1 { margin:0 0 8px; font-size:28px; font-weight:800; letter-spacing:-0.5px; }
  .header p { margin:0; opacity:.8; font-size:15px; }
  .badge { display:inline-block; background:rgba(255,255,255,.2); border-radius:20px; padding:4px 14px; font-size:12px; font-weight:700; margin-top:12px; }
  .stats { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; padding:32px 48px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
  .stat-card { background:#fff; border-radius:12px; padding:16px; border:1px solid #e2e8f0; text-align:center; }
  .stat-card .value { font-size:24px; font-weight:800; color:#7c3aed; }
  .stat-card .label { font-size:12px; color:#64748b; margin-top:4px; }
  .body { padding:32px 48px; }
  .section-header { color:#4c1d95; font-size:17px; font-weight:700; margin:28px 0 12px; padding-bottom:8px; border-bottom:2px solid #e9d5ff; }
  .section-title { color:#1e1b4b; font-size:14px; font-weight:600; margin:16px 0 8px; text-transform:uppercase; letter-spacing:.5px; }
  ul { padding-left:20px; margin:8px 0; }
  li { margin-bottom:6px; line-height:1.6; }
  code { background:#f1f5f9; border-radius:4px; padding:2px 6px; font-family:monospace; font-size:13px; color:#be185d; }
  strong { color:#1e1b4b; }
  .footer { background:#f8fafc; padding:24px 48px; border-top:1px solid #e2e8f0; text-align:center; color:#94a3b8; font-size:12px; }
  .agent-note { background:#fef3c7; border-left:4px solid #f59e0b; border-radius:0 8px 8px 0; padding:16px 20px; margin:24px 0; font-size:14px; }
  .divider { height:1px; background:linear-gradient(90deg,transparent,#e2e8f0,transparent); margin:24px 0; }
  @media (max-width:600px) { .stats { grid-template-columns:1fr; } .header, .body, .footer { padding:24px; } }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>🧠 AI Platform Blueprint</h1>
    <p>Monthly Audit Report for <strong>{$site_title}</strong></p>
    <div class="badge">📅 {$month_label} Report · Generated {$generated_date}</div>
  </div>

  <div class="stats">
    <div class="stat-card">
      <div class="value">{$stats['monthly_tx']}</div>
      <div class="label">Transactions This Month</div>
    </div>
    <div class="stat-card">
      <div class="value">{$revenue_fmt}</div>
      <div class="label">Revenue This Month</div>
    </div>
    <div class="stat-card">
      <div class="value">{$stats['failed_tx_rate']}%</div>
      <div class="label">Failure Rate</div>
    </div>
    <div class="stat-card">
      <div class="value">{$stats['new_users_monthly']}</div>
      <div class="label">New Users</div>
    </div>
    <div class="stat-card">
      <div class="value">{$stats['ai_tx_monthly']}</div>
      <div class="label">AI Interactions</div>
    </div>
    <div class="stat-card">
      <div class="value">PHP {$php_version}</div>
      <div class="label">Platform Version</div>
    </div>
  </div>

  <div class="body">
    <div class="agent-note">
      <strong>📋 How to use this Blueprint:</strong> This document is formatted so you can copy any section and paste it directly into a new AI Agent conversation as a task brief. The "AI Agent Task Briefs" section (Section 7) contains ready-to-use prompts.
    </div>

    {$blueprint_html_body}

    <div class="divider"></div>
    <p style="font-size:13px;color:#94a3b8;text-align:center">
      Analysed {$total_files} PHP files · {$total_lines} lines of code · Generated in {$elapsed}s<br>
      AI Engine: Local Ollama (privacy-first — no data left your server)
    </p>
  </div>

  <div class="footer">
    <p>This Blueprint was automatically generated by the DGV6.90 AI Audit System.<br>
    To view past Blueprints, log into your Super Admin panel → AI Management → Blueprint History.<br>
    To run a manual audit, click <a href="#" style="color:#7c3aed">Run Blueprint Now</a> in the admin panel.</p>
  </div>
</div>
</body>
</html>
HTML;

// ─────────────────────────────────────────────────────────────────────────────
// STEP 6: SAVE TO DATABASE
// ─────────────────────────────────────────────────────────────────────────────
echo "[BLUEPRINT] Step 6: Saving to database...\n";

$bp_html_esc  = mysqli_real_escape_string($connection_server, $email_html);
$bp_text_esc  = mysqli_real_escape_string($connection_server, substr($blueprint_text, 0, 2000));
$php_v_esc    = mysqli_real_escape_string($connection_server, $php_version);
$month_esc    = mysqli_real_escape_string($connection_server, $month_label);

// Ensure table exists
mysqli_query($connection_server, "CREATE TABLE IF NOT EXISTS `sas_ai_blueprints` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `month_label`   VARCHAR(30) NOT NULL,
    `php_version`   VARCHAR(20) NOT NULL,
    `file_count`    INT DEFAULT 0,
    `tx_count`      INT DEFAULT 0,
    `revenue`       DECIMAL(15,2) DEFAULT 0,
    `blueprint_html` LONGTEXT,
    `summary_text`  TEXT,
    `email_sent`    TINYINT(1) DEFAULT 0,
    `email_address` VARCHAR(255),
    `generated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    `elapsed_s`     INT DEFAULT 0,
    INDEX idx_month (`month_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$insert = mysqli_query($connection_server,
    "INSERT INTO sas_ai_blueprints (month_label, php_version, file_count, tx_count, revenue, blueprint_html, summary_text, elapsed_s)
     VALUES ('$month_esc', '$php_v_esc', '$total_files', '{$stats['monthly_tx']}', '{$stats['monthly_revenue']}', '$bp_html_esc', '$bp_text_esc', '$elapsed')");

$blueprint_id = $insert ? (int)mysqli_insert_id($connection_server) : 0;

// ─────────────────────────────────────────────────────────────────────────────
// STEP 7: SEND EMAIL
// ─────────────────────────────────────────────────────────────────────────────
echo "[BLUEPRINT] Step 7: Sending Blueprint email to $admin_email...\n";

$subject = "🧠 AI Platform Blueprint — $month_label | $site_title";

// Use the platform's existing mailer
if (function_exists('bc_send_html_email')) {
    $sent = bc_send_html_email($admin_email, $subject, $email_html);
} elseif (function_exists('sendMail')) {
    $sent = sendMail($admin_email, $subject, strip_tags($blueprint_text), $email_html);
} else {
    // Fallback to PHP mail()
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: $site_title <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">",
        "X-Mailer: DGV6.90-AI-Blueprint",
    ];
    $sent = mail($admin_email, $subject, $email_html, implode("\r\n", $headers));
}

if ($sent && $blueprint_id) {
    $email_esc = mysqli_real_escape_string($connection_server, $admin_email);
    mysqli_query($connection_server, "UPDATE sas_ai_blueprints SET email_sent=1, email_address='$email_esc' WHERE id='$blueprint_id'");
}

$total_time = round(microtime(true) - $start_time);
echo "[BLUEPRINT] " . ($sent ? "✅ Blueprint email sent to $admin_email" : "⚠ Email send failed — Blueprint saved to DB (ID: $blueprint_id)") . "\n";
echo "[BLUEPRINT] Done. Total time: {$total_time}s\n";
