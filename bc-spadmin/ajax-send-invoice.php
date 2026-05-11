<?php session_start();
include("../func/bc-spadmin-config.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// CSRF check
bc_validate_csrf(true);

if (!isset($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Registration ID missing.']);
    exit();
}

$id = mysqli_real_escape_string($connection_server, $_POST['id']);

// Fetch order details
$sql = "SELECT pv.*, bp.name as package_name, bp.price as package_price
        FROM sas_pending_vendors pv
        JOIN sas_billing_packages bp ON pv.billing_package_id = bp.id
        WHERE pv.id='$id'";
$res = mysqli_query($connection_server, $sql);
$order = mysqli_fetch_assoc($res);

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
    exit();
}

// Prepare items for invoice
$items = [];
$items[] = ['name' => $order['package_name'] . ' Subscription', 'price' => (float)$order['package_price']];

if($order['order_apk']) $items[] = ['name' => 'Android APK Development', 'price' => (float)getSuperAdminOption('apk_development_price', '0')];
if($order['order_ios']) $items[] = ['name' => 'iOS App Development', 'price' => (float)getSuperAdminOption('ios_development_price', '0')];
if($order['order_playstore']) $items[] = ['name' => 'PlayStore Listing Service', 'price' => (float)getSuperAdminOption('playstore_listing_price', '0')];
if($order['order_sms_bridge']) $items[] = ['name' => 'SMS Bridge Service Integration', 'price' => (float)getSuperAdminOption('sms_bridge_price', '0')];

if(!empty($order['selected_addons'])) {
    $addon_ids = $order['selected_addons'];
    $addons_res = mysqli_query($connection_server, "SELECT name, price FROM sas_billing_addons WHERE id IN ($addon_ids)");
    while($ar = mysqli_fetch_assoc($addons_res)) {
        $items[] = ['name' => $ar['name'], 'price' => (float)$ar['price']];
    }
}

if((float)$order['domain_registration_fee'] > 0) {
    $items[] = ['name' => 'Domain Name Registration (' . $order['app_base_url'] . ')', 'price' => (float)$order['domain_registration_fee']];
}

// Site Details
$site_name = getSuperAdminOption('site_name', 'Our Platform');
$admin_email = getSuperAdminOption('admin_email', '');
$admin_phone = getSuperAdminOption('admin_phone', '');
$bank_name = getSuperAdminOption('bank_name', 'N/A');
$account_name = getSuperAdminOption('account_name', 'N/A');
$account_number = getSuperAdminOption('account_number', 'N/A');

// Generate HTML Invoice
$invoice_html = '
<div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 30px; border-radius: 10px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h2 style="color: #0d6efd; margin-bottom: 5px;">' . strtoupper($site_name) . ' INVOICE</h2>
        <p style="color: #888; font-size: 14px;">Professional Solutions for Your Fintech Business</p>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #f8f9fa; padding-bottom: 20px;">
        <div style="width: 50%;">
            <p style="margin: 0; font-weight: bold; color: #555;">Bill To:</p>
            <p style="margin: 5px 0;">' . $order['firstname'] . ' ' . $order['lastname'] . '</p>
            <p style="margin: 0; font-size: 13px; color: #777;">' . $order['website_url'] . '</p>
        </div>
        <div style="width: 50%; text-align: right;">
            <p style="margin: 0; font-weight: bold; color: #555;">Invoice Details:</p>
            <p style="margin: 5px 0;">Date: ' . date('F d, Y') . '</p>
            <p style="margin: 0;">Order Ref: #' . str_pad($order['id'], 5, '0', STR_PAD_LEFT) . '</p>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
            <tr style="background-color: #f8f9fa;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #eee;">Service Description</th>
                <th style="padding: 12px; text-align: right; border-bottom: 1px solid #eee;">Amount (₦)</th>
            </tr>
        </thead>
        <tbody>';

foreach ($items as $item) {
    $invoice_html .= '
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #fcfcfc;">' . $item['name'] . '</td>
                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #fcfcfc;">' . number_format($item['price'], 2) . '</td>
            </tr>';
}

$invoice_html .= '
        </tbody>
        <tfoot>
            <tr style="background-color: #fcfcfc;">
                <td style="padding: 15px; font-weight: bold; text-align: right;">Total Payable:</td>
                <td style="padding: 15px; font-weight: bold; text-align: right; color: #0d6efd; font-size: 18px;">₦' . number_format($order['total_amount'], 2) . '</td>
            </tr>
        </tfoot>
    </table>

    <div style="background-color: #fff9e6; border-left: 4px solid #ffc107; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
        <h4 style="margin: 0 0 10px 0; color: #856404;">Payment Instructions</h4>
        <p style="margin: 0; font-size: 14px; color: #856404;">Please make your payment to the official account below and upload your proof of payment on the portal.</p>
        <div style="margin-top: 15px; font-size: 15px;">
            <strong>Bank:</strong> ' . $bank_name . '<br>
            <strong>Account Name:</strong> ' . $account_name . '<br>
            <strong>Account Number:</strong> ' . $account_number . '
        </div>
    </div>

    <div style="text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; pt: 20px;">
        <p>If you have any questions, please contact our support at ' . $admin_email . '</p>
        <p>&copy; ' . date('Y') . ' ' . $site_name . '. All Rights Reserved.</p>
    </div>
</div>';

// Send the Email
$subject = "Official Invoice - Order #" . str_pad($order['id'], 5, '0', STR_PAD_LEFT) . " | " . $site_name;

if (sendVendorEmail($order['email'], $subject, $invoice_html)) {
    echo json_encode(['status' => 'success', 'message' => 'Invoice sent successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to deliver email. Please check your SMTP settings.']);
}
