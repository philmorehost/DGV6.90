<?php session_start();
    include("../func/bc-spadmin-config.php");
        
    if(isset($_POST["share-fund"])){
        $purchase_method = "web";
        $purchase_method = strtoupper($purchase_method);
        $purchase_method_array = array("WEB");
        if(in_array($purchase_method, $purchase_method_array)){
        if($purchase_method === "WEB"){
            $vendor = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["vendor"]))));
            $type = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["type"]))));
            $amount = mysqli_real_escape_string($connection_server, preg_replace("/[^0-9.]+/","",trim(strip_tags($_POST["amount"]))));
        }

        $discounted_amount = $amount;
        $type_alternative = ucwords("wallet ".$type);
        $reference = substr(str_shuffle("12345678901234567890"), 0, 15);
        $description = ucwords("account ".$type."ed by admin");
        if(in_array($type, array("debit"))){
            $transType = "debit";
        }
        
        if(in_array($type, array("credit","refund"))){
            $transType = "credit";
        }
        $get_logged_vendor_query = mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE email='$vendor' LIMIT 1");
        if(in_array($type, array("debit","credit","refund"))){
            if(mysqli_num_rows($get_logged_vendor_query) == 1){
                $credit_other_vendor = chargeOtherVendor($vendor, $transType, $vendor, ucwords("wallet ".$type), $reference, $amount, $discounted_amount, $description, $_SERVER["HTTP_HOST"], "1");
                if(in_array($credit_other_vendor, array("success"))){
                    $json_response_array = array("reference" => $reference, "status" => "success", "desc" => ucwords($vendor." ".$type."ed with N".$amount." successfully"));
                    $json_response_encode = json_encode($json_response_array,true);
                }
                                                    
                if($credit_other_vendor !== "success"){
                    $json_response_array = array("desc" => "Transaction Failed");
                    $json_response_encode = json_encode($json_response_array,true);
                }       
            }else{
                //Vendor not exists
                $json_response_array = array("desc" => "Vendor not exists");
                $json_response_encode = json_encode($json_response_array,true);
            }
        }else{
            //Invalid Transaction Type
            $json_response_array = array("desc" => "Invalid Transaction Type");
            $json_response_encode = json_encode($json_response_array,true);
        }
    }else{
        //Purchase Method Not specified
        $json_response_array = array("desc" => "Purchase Method Not specified");
        $json_response_encode = json_encode($json_response_array,true);
    }
        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: ".$_SERVER["REQUEST_URI"]);
    }
?>
<!DOCTYPE html>
<head>
    <title></title>
    <meta charset="UTF-8" />
    <meta name="description" content="" />
    <meta http-equiv="Content-Type" content="text/html; " />
    <meta name="theme-color" content="black" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <meta name="author" content="Philmore Codes">
    <meta name="dc.creator" content="Philmore Codes">
    
      
  <!-- Vendor CSS Files -->
  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets-2/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets-2/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets-2/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets-2/css/style.css" rel="stylesheet">

</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>  
     <div class="pagetitle">
      <h1>SHARE FUND</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Share Fund</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-4 border-0 text-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-cash-coin text-dark-primary fs-1"></i>
                    </div>
                    <h4 class="fw-bold mb-0">Super Fund Transfer</h4>
                    <p class="text-muted small">Directly credit or debit any vendor's wallet</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <form method="post">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Vendor Email Address</label>
                            <input id="share-fund-vendor" name="vendor" onkeyup="spAdminConfirmVendor();" type="email" class="form-control form-control-lg rounded-3" placeholder="email@vendor.com" required />
                            <div id="vendor-status-span" class="small mt-1 fw-bold"></div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Action Type</label>
                                <select name="type" class="form-select rounded-3 py-2" required>
                                    <option value="" selected hidden default>Choose Action</option>
                                    <option value="credit">Credit (Add Fund)</option>
                                    <option value="debit">Debit (Remove Fund)</option>
                                    <option value="refund">Refund (Add Back)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Transaction Amount (₦)</label>
                                <input id="share-fund-amount" name="amount" onkeyup="spAdminConfirmVendor();" type="number" class="form-control rounded-3 py-2" placeholder="0.00" required />
                            </div>
                        </div>

                        <button id="proceedBtn" name="share-fund" type="button" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm py-3" style="pointer-events: none; opacity: 0.7;">
                            EXECUTE TRANSFER
                        </button>

                        <div id="product-status-span" class="text-center mt-3 small text-danger fw-bold"></div>
                    </form>
                </div>
            </div>
        </div>
      </div>
    </section>

    <?php include("../func/bc-spadmin-footer.php"); ?>
    
</body>
</html>