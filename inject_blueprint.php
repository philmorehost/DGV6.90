<?php
include("func/bc-connect.php");

$title = "Ultimate Mobile App Blueprint: The Developer's Guide to v6 Fintech Integration";
$slug = "fintech-mobile-app-blueprint-guide";

$content = "
<h2>1. Introduction & Professional distribution</h2>
<p>This guide serves as the definitive blueprint for developing a high-end Fintech Mobile Application integrated with the v6 VTU Platform. To maintain professional standards, the app should follow a <b>Branded Build</b> model where each vendor gets a custom APK with their BASE_URL hardcoded. For universal versions, a 'Setup Once' screen must be used to save the domain permanently in <i>EncryptedSharedPreferences</i>.</p>

<h3>UI/UX Design Philosophy</h3>
<p>The app must mirror the beauty and responsiveness of the web portal:</p>
<ul>
    <li><b>Fintech Aesthetic:</b> Clean, minimalist design with plenty of white space.</li>
    <li><b>Dynamic Theming:</b> Call <code>/web/api/site-info.php</code> on launch. Use the returned <code>primary_color</code> for buttons, headers, and progress bars.</li>
    <li><b>Interactive Components:</b> Use Bootstrap 5 style cards (16dp radius), soft shadows, and standard icons.</li>
</ul>

<h2>2. Core Features & Service Logic</h2>

<h3>A. Account & Security</h3>
<ul>
    <li><b>Unified Auth:</b> Use <code>register.php</code> and <code>login.php</code>. Both return a full user profile.</li>
    <li><b>KYC Bypass:</b> Identity verification is hardcoded as 'Verified' in this version. Skip KYC screens.</li>
    <li><b>Transaction PIN:</b> Prompt for a 4-digit numeric PIN before sensitive actions (Share Fund, Withdrawals).</li>
</ul>

<h3>B. Advanced Service Workflows</h3>
<p>To ensure 100% transaction success, the app <b>must</b> follow these verification protocols:</p>

<h4>1. Mandatory Pre-Purchase Verification</h4>
<p>For <b>Cable TV, Electricity, and Betting</b>, the app MUST call the corresponding <code>verify-xxx.php</code> endpoint immediately after the user enters the ID. Display the <code>customer_name</code> prominently. Do not enable the 'Pay' button until verification is successful.</p>

<h4>2. Real-Time Limit Enforcement</h4>
<p>Avoid user frustration by checking limits early. Call <code>/web/api/check-limit.php</code>. If the server returns <code>limit_reached: true</code>, lock the buy button and show the returned error message. This strictly adheres to Admin security policies.</p>

<h4>3. Bulk & Background Airtime/Data</h4>
<ul>
    <li>Allow users to select multiple contacts or enter comma-separated numbers.</li>
    <li>For Airtime, use <code>/web/api/identify-network.php</code> to automatically set the ISP for each number.</li>
    <li>The server handles bulk loops and returns a <code>batch_number</code>. The app should display a 'Processing in Background' notification and allow the user to continue other tasks.</li>
</ul>

<h3>C. Bulk SMS & Phonebook</h3>
<ul>
    <li><b>Sender ID:</b> Users must use approved IDs. Provide a form to call <code>submit-sender-id.php</code> for new ID review.</li>
    <li><b>Server-Side Contacts:</b> Use <code>contacts.php</code> to sync the user's favorite recipients. Allow multi-select from this list for SMS.</li>
    <li><b>Scheduling:</b> Include a DateTime picker to send the <code>date</code> parameter for scheduled delivery.</li>
</ul>

<h2>3. Payment Gateway Integration</h2>
<p>To prevent 'Unable to process' errors in SDKs (Monnify/Paystack):</p>
<ol>
    <li>Fetch gateway keys via <code>funding-config.php</code>.</li>
    <li><b>CRITICAL:</b> Call <code>/web/api/create-checkout.php</code> on your server BEFORE launching the gateway UI. This registers the transaction for webhook crediting.</li>
    <li>After payment, use <code>requery.php</code> to confirm the final wallet status.</li>
</ol>

<h2>4. Technical Implementation Checklist</h2>
<ul>
    <li><b>Networking:</b> Retrofit2 (Android) or Dio (Flutter).</li>
    <li><b>Architecture:</b> MVVM with clean separation of concerns.</li>
    <li><b>Persistence:</b> Room Database for local history and contact caching.</li>
    <li><b>Security:</b> Implement Biometric Login using <code>biometric.php</code> and enforce SSL Pinning.</li>
</ul>
<p>Follow these guidelines to build a secure, professional, and high-converting fintech application.</p>
";

$encoded_content = base64_encode($content);

// Insert for all vendors to ensure every site owner has the guide
$get_vendors = mysqli_query($connection_server, "SELECT id FROM sas_vendors");
while($v = mysqli_fetch_assoc($get_vendors)) {
    $vid = $v['id'];
    $check = mysqli_query($connection_server, "SELECT id FROM blog_posts WHERE author_id='$vid' AND slug='$slug'");
    if(mysqli_num_rows($check) == 0) {
        $q = "INSERT INTO blog_posts (author_id, title, slug, content, status, featured_image) VALUES ('$vid', '$title', '$slug', '$encoded_content', 'published', 'https://images.unsplash.com/photo-1563986768609-322da13575f3?w=1200')";
        mysqli_query($connection_server, $q);
    }
}

echo "Blueprint guide injected into blog for all vendors.";
?>
