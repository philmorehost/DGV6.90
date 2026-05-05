<?php session_start();
    include("../func/bc-spadmin-config.php");
    
    if(isset($_POST["create-billing"])){
        $type = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["type"])));
        $starting_date = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["starting-date"])));
        $ending_date = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["ending-date"])));
        $desc = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["desc"])));
        $amount = mysqli_real_escape_string($connection_server, preg_replace("/[^0-9.]+/","",trim(strip_tags(strtolower($_POST["amount"])))));
        if(!empty($type) && !empty($starting_date) && strtotime($starting_date) && !empty($ending_date) && strtotime($ending_date) && !empty($amount) && is_numeric($amount) && (strtotime($starting_date) <= strtotime($ending_date))){
            $check_billing_details = mysqli_query($connection_server, "SELECT * FROM sas_vendor_billings WHERE starting_date='$starting_date' && ending_date='$ending_date'");

            if(mysqli_num_rows($check_billing_details) == 0){
                mysqli_query($connection_server, "INSERT INTO sas_vendor_billings (bill_type, description, amount, starting_date, ending_date) VALUES ('$type', '$desc', '$amount', '$starting_date','$ending_date')");
		        //Billing Created Successfully
                $json_response_array = array("desc" => "Billing Created Successfully");
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                if(mysqli_num_rows($check_billing_details) == 1){
                    //Billing Information Already Exists
                    $json_response_array = array("desc" => "Billing Information Already Exists");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(mysqli_num_rows($check_billing_details) > 1){
                        //Duplicated Details, Contact Admin
                        $json_response_array = array("desc" => "Duplicated Details, Contact Admin");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }
            }
        }else{
            if(empty($type)){
                //API Type Field Empty
                $json_response_array = array("desc" => "API Type Field Empty");
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                if(empty($starting_date)){
                    //Starting Date Field Empty
                    $json_response_array = array("desc" => "Starting Date Field Empty");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(!strtotime($starting_date)){
                        //Invalid Starting Date String
                        $json_response_array = array("desc" => "Invalid Starting Date String");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        if(empty($ending_date)){
                            //Ending Date Field Empty
                            $json_response_array = array("desc" => "Ending Date Field Empty");
                            $json_response_encode = json_encode($json_response_array,true);
                        }else{
                            if(!strtotime($ending_date)){
                                //Invalid Ending Date String
                                $json_response_array = array("desc" => "Invalid Ending Date String");
                                $json_response_encode = json_encode($json_response_array,true);
                            }else{
                                if(empty($amount)){
                                    //Amount Field Empty
                                    $json_response_array = array("desc" => "Amount Field Empty");
                                    $json_response_encode = json_encode($json_response_array,true);
                                }else{
                                    if(!is_numeric($amount)){
                                        //Non-numeric Amount
                                        $json_response_array = array("desc" => "Non-numeric Amount");
                                        $json_response_encode = json_encode($json_response_array,true);
                                    }else{
                                        if(strtotime($starting_date) > strtotime($ending_date)){
                                            //Ending Date Must Be Greater Than Or Equals Starting Date
                                            $json_response_array = array("desc" => "Ending Date Must Be Greater Than Or Equals Starting Date");
                                            $json_response_encode = json_encode($json_response_array,true);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }  
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
      <h1>ADD BILLING</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Create Billing</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-4 border-0 text-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-plus-circle text-dark-primary fs-1"></i>
                    </div>
                    <h4 class="fw-bold mb-0">Create Global Billing</h4>
                    <p class="text-muted small">Generate a new billing cycle for all active vendors</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <form method="post">
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted text-uppercase">Billing Category / Type</label>
                                <input name="type" type="text" class="form-control rounded-3 py-2" placeholder="e.g. Monthly Maintenance Fee" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Billing Amount (₦)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 rounded-start-3">₦</span>
                                    <input name="amount" type="number" step="0.01" min="0" class="form-control rounded-end-3 py-2" placeholder="0.00" required />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">Start Date</label>
                                <input name="starting-date" type="date" class="form-control rounded-3 py-2" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase">End Date (Due Date)</label>
                                <input name="ending-date" type="date" class="form-control rounded-3 py-2" required />
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted text-uppercase">Invoice Description</label>
                                <textarea name="desc" class="form-control rounded-3" rows="4" placeholder="Briefly explain what this billing covers..."></textarea>
                            </div>
                        </div>

                        <div class="mt-5">
                            <button name="create-billing" type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm py-3">
                                <i class="bi bi-plus-circle me-2"></i>Generate Billing Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
      </div>
    </section>
    <?php include("../func/bc-spadmin-footer.php"); ?>
    
</body>
</html>