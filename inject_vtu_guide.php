<?php
include("func/bc-connect.php");

$title = "The Master Blueprint: Building a Professional Fintech Mobile App for v6 Platform";
$slug = "master-vtu-app-blueprint";

$content = "
<h2>1. Professional Distribution & Multi-Tenant Setup</h2>
<p>To deliver a top-tier user experience, the v6 Mobile App supports two primary distribution models:</p>
<ul>
    <li><b>Branded (AAB/APK):</b> Hardcode the <code>BASE_URL</code> in the app code. This is the gold standard for site owners.</li>
    <li><b>Universal:</b> Use a Setup Screen to save the domain once in <i>EncryptedSharedPreferences</i>. Once saved, the user never sees that screen again.</li>
</ul>

<h3>Fintech UI/UX Design Standards</h3>
<p>The app must match the stunning aesthetic of the web dashboard. Developers should implement:</p>
<ul>
    <li><b>Dynamic Branding:</b> Call <code>/web/api/site-info.php</code> on launch to get the primary colors, toolbar logo, and support contacts.</li>
    <li><b>Modern Components:</b> Use Rounded Buttons (12dp), Glassmorphism headers, and Bottom Navigation (Home, History, Profile).</li>
</ul>

<h2>2. Advanced API Integration Guide</h2>

<h3>A. Core Security & Limits</h3>
<p>Success in fintech depends on real-time security. The app MUST implement these checks:</p>
<ul>
    <li><b>Limit Checks:</b> Call <code>/web/api/check-limit.php</code> as soon as a user inputs a phone number or Meter ID. If the limit is reached, disable the 'Buy' button immediately.</li>
    <li><b>KYC & PIN:</b> KYC is pre-verified ('Verified' status) to simplify onboarding. Use a 4-digit PIN for sensitive actions like 'Share Fund'.</li>
</ul>

<h3>B. Advanced Service Workflows</h3>
<h4>1. Verification-First Flow</h4>
<p>For Utility bills (Cable, Electric, Betting), verification is mandatory. Use <code>verify-xxx.php</code> to fetch and display the <b>Customer Name</b>. Only enable the payment button after the user confirms the name.</p>

<h4>2. Background Bulk Airtime/Data</h4>
<p>Enable 'Select Multiple' in the phonebook. The app should automatically join numbers with commas. The API now supports <b>Batch Processing</b>. After sending a bulk request, store the <code>batch_number</code> and use <code>batch-status.php</code> in a background worker to update the UI with real-time success/fail bubbles.</p>

<h3>C. SMS & Phonebook API</h3>
<ul>
    <li><b>Approved Sender IDs:</b> Use <code>sms-sender-ids.php</code> to populate the dropdown. New IDs must be submitted via <code>submit-sender-id.php</code>.</li>
    <li><b>Server-Side Phonebook:</b> Use <code>contacts.php</code> to manage the user's favorites. This allows a seamless experience even if they switch devices.</li>
</ul>

<h2>3. Payment SDK Integration (Monnify/Paystack)</h2>
<p>To avoid the common 'Unable to process transaction' error:</p>
<ol>
    <li>Call <code>funding-config.php</code> to get the latest keys.</li>
    <li><b>Execute <code>create-checkout.php</code></b> before initializing the mobile SDK. This logs the transaction on the server side for the webhook to find.</li>
</ol>

<p>Building with this blueprint ensures a robust, secure, and beautiful fintech experience that scales with your business.</p>

<h2>4. Customizing App Assets (Icons & Splash)</h2>
<p>To personalize your app, you must replace the following placeholder assets in the Android Studio project:</p>
<ul>
    <li><b>App Icon:</b> Replace the files in <code>app/src/main/res/mipmap-xxxx/ic_launcher.png</code> with your own 512x512 icon. We recommend using the 'Image Asset' studio tool for best results.</li>
    <li><b>Splash Screen Logo:</b> Replace <code>app/src/main/res/drawable/splash_logo.png</code> with your transparent logo (recommended size: 1024x1024 centered).</li>
</ul>
<p>The splash screen is programmed to <b>Spin Right Once</b> upon launch to provide a professional interactive feel.</p>
";

$encoded_content = base64_encode($content);

$get_vendors = mysqli_query($connection_server, "SELECT id FROM sas_vendors");
while($v = mysqli_fetch_assoc($get_vendors)) {
    $vid = $v['id'];
    mysqli_query($connection_server, "DELETE FROM blog_posts WHERE author_id='$vid' AND slug='$slug'");
    $q = "INSERT INTO blog_posts (author_id, title, slug, content, status, featured_image) VALUES ('$vid', '$title', '$slug', '$encoded_content', 'published', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200')";
    mysqli_query($connection_server, $q);
}

echo "VTU App Blueprint guide injected.";
?>
