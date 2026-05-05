<?php
include("../../func/bc-config.php");
include("../../func/bc-giftcard-func.php");

$from = $_GET['from'] ?? 'USD';
$to = $_GET['to'] ?? 'NGN';
$vid = $get_logged_user_details['vendor_id'] ?? 0;
$type = $_GET['type'] ?? 'generic';

$rate = getLiveExchangeRate($from, $to, $vid, $type);

echo json_encode(['status' => 'success', 'rate' => $rate, 'from' => $from, 'to' => $to]);
exit;
?>
