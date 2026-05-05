<?php session_start();
    include("../func/bc-config.php");

    if(isset($_POST["regenerate"])){
        $api_key = substr(str_shuffle("abdcefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ12345678901234567890"), 0, 50);
		mysqli_query($connection_server, "UPDATE sas_users SET api_key='$api_key' WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."'");
        header("Location: ".$_SERVER["REQUEST_URI"]);
    }
?>
<!DOCTYPE html>
<head>
<title>Pricing | <?php echo $get_all_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="description" content="<?php echo substr($get_all_site_details["site_desc"], 0, 160); ?>" />
    <meta http-equiv="Content-Type" content="text/html; " />
    <meta name="theme-color" content="black" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <meta name="author" content="Philmore Codes">
    <meta name="dc.creator" content="Philmore Codes">

      <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/remixicon/remixicon.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets-2/css/style.css" rel="stylesheet">

</head>

<body>
	<?php include("../func/bc-header.php"); ?>


	<div class="pagetitle">
      <h1>PRODUCT PRICING</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Product Pricing</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="col-12">

    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-transparent shadow-none">
        <div class="card-header bg-white py-3 border-0 rounded-4 mb-3 shadow-sm">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-tags me-2"></i>Service Pricing Tables</h5>
        </div>
        <div class="card-body p-0">
            <div class="accordion" id="pricingAccordion">

                <!-- Airtime -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button fw-bold bg-white text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAirtime">
                            <i class="bi bi-telephone me-2"></i> Airtime VTU Discounts
                        </button>
                    </h2>
                    <div id="collapseAirtime" class="accordion-collapse collapse show" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Network</th><th>Product Code</th><th>Smart User (%)</th><th>Agent Vendor (%)</th><th>API Vendor (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function airtimeAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $account_level_table_name_arrays = array(1 => "sas_smart_parameter_values", 2 => "sas_agent_parameter_values", 3 => "sas_api_parameter_values");
                                                $product_name_arrays = array(1 => "mtn", 2 => "airtel", 3 => "9mobile", 4 => "glo");
                                                $acc_smart_level_table_name = $account_level_table_name_arrays[1];
                                                $acc_agent_level_table_name = $account_level_table_name_arrays[2];
                                                $acc_api_level_table_name = $account_level_table_name_arrays[3];

                                                $product_tr_list = "";
                                                foreach($product_name_arrays as $pname){
                                                    $status = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_airtime_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    if(!$status) continue;
                                                    $api_id = $status['api_id'];
                                                    $product_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_products WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && product_name='$pname' && status=1 LIMIT 1"));
                                                    if(!empty($product_table["id"])){
                                                        $q1 = mysqli_query($connection_server, "SELECT * FROM $acc_smart_level_table_name WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q2 = mysqli_query($connection_server, "SELECT * FROM $acc_agent_level_table_name WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q3 = mysqli_query($connection_server, "SELECT * FROM $acc_api_level_table_name WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");

                                                        if($q1 && mysqli_num_rows($q1) > 0){
                                                            while(($d1 = mysqli_fetch_assoc($q1)) && ($d2 = mysqli_fetch_assoc($q2)) && ($d3 = mysqli_fetch_assoc($q3))){
                                                                $product_tr_list .= '<tr>
                                                                        <td>Airtime</td><td>'.strtoupper($pname).'</td><td>'.$product_table["product_name"].'</td><td>'.toDecimal($d1["val_1"], 2).'</td><td>'.toDecimal($d2["val_1"], 2).'</td><td>'.toDecimal($d3["val_1"], 2).'</td>
                                                                    </tr>';
                                                            }
                                                        }
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo airtimeAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Plans -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseData">
                            <i class="bi bi-wifi me-2"></i> Internet Data Plans
                        </button>
                    </h2>
                    <div id="collapseData" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Network</th><th>Network Code</th><th>Type</th><th>Data Code(Qty)</th><th>Smart User (N)</th><th>Agent Vendor (N)</th><th>API Vendor (N)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function dataAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $account_level_table_name_arrays = array(1 => "sas_smart_parameter_values", 2 => "sas_agent_parameter_values", 3 => "sas_api_parameter_values");
                                                $product_name_arrays = array(1 => "mtn", 2 => "airtel", 3 => "9mobile", 4 => "glo");

                                                $product_tr_list = "";
                                                foreach($product_name_arrays as $pname){
                                                    $shared = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_shared_data_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    $sme = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_sme_data_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    $cg = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_cg_data_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    $dd = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_dd_data_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));

                                                    $api_ids = array_unique(array_filter([$shared['api_id'] ?? null, $sme['api_id'] ?? null, $cg['api_id'] ?? null, $dd['api_id'] ?? null]));
                                                    if(empty($api_ids)) continue;

                                                    $api_id_stmt = "api_id IN ('" . implode("','", $api_ids) . "')";
                                                    $product_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_products WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && product_name='$pname' && status=1 LIMIT 1"));

                                                    if($product_table){
                                                        $q1 = mysqli_query($connection_server, "SELECT * FROM sas_smart_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && $api_id_stmt && product_id='".$product_table["id"]."' && status=1");
                                                        $q2 = mysqli_query($connection_server, "SELECT * FROM sas_agent_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && $api_id_stmt && product_id='".$product_table["id"]."' && status=1");
                                                        $q3 = mysqli_query($connection_server, "SELECT * FROM sas_api_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && $api_id_stmt && product_id='".$product_table["id"]."' && status=1");

                                                        while(($d1 = mysqli_fetch_assoc($q1)) && ($d2 = mysqli_fetch_assoc($q2)) && ($d3 = mysqli_fetch_assoc($q3))){
                                                            $api_info = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_type FROM sas_apis WHERE id='".$d1["api_id"]."' LIMIT 1"));
                                                            $descriptive_name = !empty($d1["val_4"]) ? $d1["val_4"] : $d1["val_1"];
                                                            $product_tr_list .= '<tr>
                                                                    <td>Internet Data</td><td>'.strtoupper($pname).'</td><td>'.$product_table["product_name"].'</td><td>'.$api_info["api_type"].'</td><td>'.$descriptive_name.'</td><td>'.toDecimal($d1["val_2"], 2).'</td><td>'.toDecimal($d2["val_2"], 2).'</td><td>'.toDecimal($d3["val_2"], 2).'</td>
                                                                </tr>';
                                                        }
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo dataAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cable TV -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCable">
                            <i class="bi bi-tv me-2"></i> Cable TV Subscriptions
                        </button>
                    </h2>
                    <div id="collapseCable" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Cable</th><th>Type</th><th>Package</th><th>Smart User (N)</th><th>Agent Vendor (N)</th><th>API Vendor (N)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function cableAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $product_name_arrays = ["startimes", "dstv", "gotv", "showmax"];
                                                $product_tr_list = "";
                                                foreach($product_name_arrays as $pname){
                                                    $status = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_cable_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    if(!$status) continue;
                                                    $api_id = $status['api_id'];
                                                    $product_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_products WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && product_name='$pname' && status=1 LIMIT 1"));
                                                    if($product_table){
                                                        $q1 = mysqli_query($connection_server, "SELECT * FROM sas_smart_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q2 = mysqli_query($connection_server, "SELECT * FROM sas_agent_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q3 = mysqli_query($connection_server, "SELECT * FROM sas_api_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        while(($d1 = mysqli_fetch_assoc($q1)) && ($d2 = mysqli_fetch_assoc($q2)) && ($d3 = mysqli_fetch_assoc($q3))){
                                                            $descriptive_name = !empty($d1["val_4"]) ? $d1["val_4"] : $d1["val_1"];
                                                            $product_tr_list .= '<tr>
                                                                    <td>Cable</td><td>'.ucwords($pname).'</td><td>'.$product_table["product_name"] .'</td><td>'.$descriptive_name.'</td><td>'.toDecimal($d1["val_2"], 2).'</td><td>'.toDecimal($d2["val_2"], 2).'</td><td>'.toDecimal($d3["val_2"], 2).'</td>
                                                                </tr>';
                                                        }
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo cableAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam PINs -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExam">
                            <i class="bi bi-mortarboard me-2"></i> Exam Result PINs
                        </button>
                    </h2>
                    <div id="collapseExam" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Exam</th><th>Type</th><th>Qty</th><th>Smart User (N)</th><th>Agent Vendor (N)</th><th>API Vendor (N)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function examAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $product_name_arrays = ["waec", "neco", "nabteb", "jamb"];
                                                $product_tr_list = "";
                                                foreach($product_name_arrays as $pname){
                                                    $status = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_exam_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    if(!$status) continue;
                                                    $api_id = $status['api_id'];
                                                    $product_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_products WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && product_name='$pname' && status=1 LIMIT 1"));
                                                    if($product_table){
                                                        $q1 = mysqli_query($connection_server, "SELECT * FROM sas_smart_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q2 = mysqli_query($connection_server, "SELECT * FROM sas_agent_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q3 = mysqli_query($connection_server, "SELECT * FROM sas_api_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        while(($d1 = mysqli_fetch_assoc($q1)) && ($d2 = mysqli_fetch_assoc($q2)) && ($d3 = mysqli_fetch_assoc($q3))){
                                                            $descriptive_name = !empty($d1["val_4"]) ? $d1["val_4"] : $d1["val_1"];
                                                            $product_tr_list .= '<tr>
                                                                    <td>Exam PIN</td><td>'.strtoupper($pname).'</td><td>'.$product_table["product_name"] .'</td><td>'.$descriptive_name.'</td><td>'.toDecimal($d1["val_2"], 2).'</td><td>'.toDecimal($d2["val_2"], 2).'</td><td>'.toDecimal($d3["val_2"], 2).'</td>
                                                                </tr>';
                                                        }
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo examAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Electricity -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseElectric">
                            <i class="bi bi-lightning-charge me-2"></i> Electricity Bill Payments
                        </button>
                    </h2>
                    <div id="collapseElectric" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Electric</th><th>Provider</th><th>Type</th><th>Smart User (%)</th><th>Agent Vendor (%)</th><th>API Vendor (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function electricAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $product_name_arrays = ["ekedc", "eedc", "ikedc", "jedc", "kedco", "ibedc", "phed", "aedc", "yedc"];
                                                $product_tr_list = "";
                                                foreach($product_name_arrays as $pname){
                                                    $status = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_electric_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    if(!$status) continue;
                                                    $api_id = $status['api_id'];
                                                    $product_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_products WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && product_name='$pname' && status=1 LIMIT 1"));
                                                    if($product_table){
                                                        $q1 = mysqli_query($connection_server, "SELECT * FROM sas_smart_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q2 = mysqli_query($connection_server, "SELECT * FROM sas_agent_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q3 = mysqli_query($connection_server, "SELECT * FROM sas_api_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        while(($d1 = mysqli_fetch_assoc($q1)) && ($d2 = mysqli_fetch_assoc($q2)) && ($d3 = mysqli_fetch_assoc($q3))){
                                                            $product_tr_list .= '<tr>
                                                                    <td>Electric</td><td>'.ucwords($pname).'</td><td>'.$product_table["product_name"] .'</td><td>prepaid or postpaid</td><td>'.toDecimal($d1["val_1"], 2).'</td><td>'.toDecimal($d2["val_1"], 2).'</td><td>'.toDecimal($d3["val_1"], 2).'</td>
                                                                </tr>';
                                                        }
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo electricAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Cards -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCards">
                            <i class="bi bi-printer me-2"></i> Data & Recharge Cards
                        </button>
                    </h2>
                    <div id="collapseCards" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Network</th><th>Network Code</th><th>Type</th><th>Product Code(Qty)</th><th>Smart User (%)</th><th>Agent Vendor (%)</th><th>API Vendor (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function dataRechargeAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $product_name_arrays = ["mtn", "airtel", "9mobile", "glo"];
                                                $product_tr_list = "";
                                                foreach($product_name_arrays as $pname){
                                                    $dc = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_datacard_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    $rc = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_rechargecard_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    $api_ids = array_unique(array_filter([$dc['api_id'] ?? null, $rc['api_id'] ?? null]));
                                                    if(empty($api_ids)) continue;
                                                    $api_id_stmt = "api_id IN ('" . implode("','", $api_ids) . "')";
                                                    $product_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_products WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && product_name='$pname' && status=1 LIMIT 1"));
                                                    if($product_table){
                                                        $q1 = mysqli_query($connection_server, "SELECT * FROM sas_smart_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && $api_id_stmt && product_id='".$product_table["id"]."' && status=1");
                                                        $q2 = mysqli_query($connection_server, "SELECT * FROM sas_agent_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && $api_id_stmt && product_id='".$product_table["id"]."' && status=1");
                                                        $q3 = mysqli_query($connection_server, "SELECT * FROM sas_api_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && $api_id_stmt && product_id='".$product_table["id"]."' && status=1");
                                                        while(($d1 = mysqli_fetch_assoc($q1)) && ($d2 = mysqli_fetch_assoc($q2)) && ($d3 = mysqli_fetch_assoc($q3))){
                                                            $api_info = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_type FROM sas_apis WHERE id='".$d1["api_id"]."' LIMIT 1"));
                                                            $product_tr_list .= '<tr>
                                                                    <td>Card</td><td>'.strtoupper($pname).'</td><td>'.$product_table["product_name"].'</td><td>'.$api_info["api_type"].'</td><td>'.$d1["val_1"].'</td><td>'.toDecimal($d1["val_2"], 2).'</td><td>'.toDecimal($d2["val_2"], 2).'</td><td>'.toDecimal($d3["val_2"], 2).'</td>
                                                                </tr>';
                                                        }
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo dataRechargeAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk SMS -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSMS">
                            <i class="bi bi-chat-dots me-2"></i> Bulk SMS Units
                        </button>
                    </h2>
                    <div id="collapseSMS" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Network</th><th>Product Code</th><th>Smart User (%)</th><th>Agent Vendor (%)</th><th>API Vendor (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function bulksmsAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $product_name_arrays = ["mtn", "airtel", "9mobile", "glo"];
                                                $product_tr_list = "";
                                                foreach($product_name_arrays as $pname){
                                                    $status = mysqli_fetch_array(mysqli_query($connection_server, "SELECT api_id FROM sas_bulk_sms_status WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && product_name='$pname' && status=1"));
                                                    if(!$status) continue;
                                                    $api_id = $status['api_id'];
                                                    $product_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_products WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && product_name='$pname' && status=1 LIMIT 1"));
                                                    if($product_table){
                                                        $q1 = mysqli_query($connection_server, "SELECT * FROM sas_smart_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q2 = mysqli_query($connection_server, "SELECT * FROM sas_agent_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        $q3 = mysqli_query($connection_server, "SELECT * FROM sas_api_parameter_values WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && api_id='$api_id' && product_id='".$product_table["id"]."' && status=1");
                                                        while(($d1 = mysqli_fetch_assoc($q1)) && ($d2 = mysqli_fetch_assoc($q2)) && ($d3 = mysqli_fetch_assoc($q3))){
                                                            $product_tr_list .= '<tr>
                                                                    <td>Bulk SMS</td><td>'.strtoupper($pname).'</td><td>'.$product_table["product_name"].'</td><td>'.toDecimal($d1["val_2"], 2).'</td><td>'.toDecimal($d2["val_2"], 2).'</td><td>'.toDecimal($d3["val_2"], 2).'</td>
                                                                </tr>';
                                                        }
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo bulksmsAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Bundle Card -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBundleCard">
                            <i class="bi bi-phone me-2"></i> Data Bundle Cards
                        </button>
                    </h2>
                    <div id="collapseBundleCard" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product</th><th>Network</th><th>Type</th><th>Plan Code</th><th>Smart User (N)</th><th>Agent Vendor (N)</th><th>API Vendor (N)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            function bundleCardAPIDoc(){
                                                global $connection_server;
                                                global $get_logged_user_details;
                                                $vid = $get_logged_user_details["vendor_id"];
                                                $product_tr_list = "";

                                                $q = mysqli_query($connection_server, "SELECT p.*, prod.product_name FROM sas_databundle_plans p JOIN sas_products prod ON p.product_id = prod.id WHERE p.vendor_id='$vid' && p.status=1");

                                                while($plan = mysqli_fetch_assoc($q)){
                                                    $dtype = $plan['data_type'];
                                                    $pname = $plan['product_name'];
                                                    $pid = $plan['product_id'];
                                                    $pcode = $plan['plan_code'];

                                                    $type_tables = ["sme-data" => "sas_sme_data_status", "shared-data" => "sas_shared_data_status", "cg-data" => "sas_cg_data_status", "dd-data" => "sas_dd_data_status"];
                                                    $status_res = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT api_id FROM ".$type_tables[$dtype]." WHERE vendor_id='$vid' && product_name='$pname' && status=1"));
                                                    if(!$status_res) continue;

                                                    $api_id = $status_res['api_id'];

                                                    $d1 = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT val_2 FROM sas_smart_parameter_values WHERE vendor_id='$vid' AND api_id='$api_id' AND product_id='$pid' AND val_1='$pcode' AND status=1"));
                                                    $d2 = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT val_2 FROM sas_agent_parameter_values WHERE vendor_id='$vid' AND api_id='$api_id' AND product_id='$pid' AND val_1='$pcode' AND status=1"));
                                                    $d3 = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT val_2 FROM sas_api_parameter_values WHERE vendor_id='$vid' AND api_id='$api_id' AND product_id='$pid' AND val_1='$pcode' AND status=1"));

                                                    if($d1){
                                                        $product_tr_list .= '<tr>
                                                            <td>Bundle Card</td><td>'.strtoupper($pname).'</td><td>'.strtoupper(str_replace("-"," ",$dtype)).'</td><td>'.$pcode.'</td><td>'.toDecimal($d1["val_2"], 2).'</td><td>'.toDecimal($d2["val_2"], 2).'</td><td>'.toDecimal($d3["val_2"], 2).'</td>
                                                        </tr>';
                                                    }
                                                }
                                                return $product_tr_list;
                                            }
                                            echo bundleCardAPIDoc();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Crypto Hub -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCrypto">
                            <i class="bi bi-currency-bitcoin me-2"></i> Crypto Hub Services
                        </button>
                    </h2>
                    <div id="collapseCrypto" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Service</th><th>Fee Type</th><th>Rate / Fee</th><th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            $vid = $get_logged_user_details["vendor_id"];
                                            $v_q = mysqli_query($connection_server, "SELECT crypto_swap_fee FROM sas_vendors WHERE id='$vid' LIMIT 1");
                                            $v_data = mysqli_fetch_assoc($v_q);
                                            $swap_fee = $v_data['crypto_swap_fee'] ?? 0;
                                        ?>
                                        <tr><td>Crypto Deposits</td><td>Processing Fee</td><td>0%</td><td>Automated via Plisio</td></tr>
                                        <tr><td>Crypto Swaps</td><td>Service Fee</td><td><?php echo number_format($swap_fee, 2); ?>%</td><td>Fee on cross-currency swaps</td></tr>
                                        <tr><td>Crypto To NGN</td><td>Internal Rate</td><td>Market Rate</td><td>Dynamic live exchange rates</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gift Cards -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGiftCards">
                            <i class="bi bi-gift me-2"></i> International Gift Cards
                        </button>
                    </h2>
                    <div id="collapseGiftCards" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Service</th><th>Fee Type</th><th>Value</th><th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            $v_q = mysqli_query($connection_server, "SELECT giftcard_fee_percent, default_giftcard_markup FROM sas_vendors WHERE id='$vid' LIMIT 1");
                                            $v_data = mysqli_fetch_assoc($v_q);
                                            $gc_fee = $v_data['giftcard_fee_percent'] ?? 0;
                                            $gc_markup = $v_data['default_giftcard_markup'] ?? 0;
                                        ?>
                                        <tr><td>Gift Card Purchase</td><td>Processing Fee</td><td><?php echo number_format($gc_fee, 2); ?>%</td><td>Applicable on total amount</td></tr>
                                        <tr><td>Markup</td><td>Profit Margin</td><td><?php echo number_format($gc_markup, 2); ?>%</td><td>Added to global product rates</td></tr>
                                        <tr><td>Availability</td><td>Countries</td><td>Global</td><td>Supported in 150+ countries</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Virtual Cards -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVirtualCards">
                            <i class="bi bi-credit-card me-2"></i> Virtual Dollar Cards (USD)
                        </button>
                    </h2>
                    <div id="collapseVirtualCards" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Action</th><th>Type</th><th>Fee / Spread</th><th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            $q_set = mysqli_query($connection_server, "SELECT * FROM sas_settings WHERE vendor_id='$vid' AND setting_name IN ('vc_issuance_profit_usd', 'vc_funding_profit_percent', 'vc_conversion_spread')");
                                            $vc_settings = []; while($rs = mysqli_fetch_assoc($q_set)) $vc_settings[$rs['setting_name']] = $rs['setting_value'];
                                            $issuance = $vc_settings['vc_issuance_profit_usd'] ?? '2.00';
                                            $funding = $vc_settings['vc_funding_profit_percent'] ?? '3.00';
                                            $spread = $vc_settings['vc_conversion_spread'] ?? '0.00';
                                        ?>
                                        <tr><td>Card Creation</td><td>One-time Fee</td><td>$<?php echo number_format($issuance, 2); ?></td><td>Added to base issuance cost</td></tr>
                                        <tr><td>Card Funding</td><td>Service Fee</td><td><?php echo number_format($funding, 2); ?>%</td><td>Commission on top-up amount</td></tr>
                                        <tr><td>Exchange Rate</td><td>FX Spread</td><td>₦<?php echo number_format($spread, 2); ?>/$</td><td>Added to live mid-market rate</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Withdrawals -->
                <div class="accordion-item border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWithdrawal">
                            <i class="bi bi-bank me-2"></i> Bank Withdrawals
                        </button>
                    </h2>
                    <div id="collapseWithdrawal" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Service</th><th>Fee Type</th><th>Amount</th><th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            $v_q = mysqli_query($connection_server, "SELECT withdrawal_fee, min_withdrawal_amount FROM sas_vendors WHERE id='$vid' LIMIT 1");
                                            $v_data = mysqli_fetch_assoc($v_q);
                                            $w_fee = $v_data['withdrawal_fee'] ?? 50;
                                            $w_min = $v_data['min_withdrawal_amount'] ?? getSuperAdminOption('default_min_withdrawal', 1000);
                                        ?>
                                        <tr><td>Wallet Withdrawal</td><td>Flat Fee</td><td>₦<?php echo number_format($w_fee, 2); ?></td><td>Applicable per transfer request</td></tr>
                                        <tr><td>Minimum Limit</td><td>Threshold</td><td>₦<?php echo number_format($w_min, 2); ?></td><td>Minimum amount per withdrawal</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

      </div>
    </section>
	<?php include("../func/bc-footer.php"); ?>
	
</body>
</html>