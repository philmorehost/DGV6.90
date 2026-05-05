<?php session_start();
    include("../func/bc-spadmin-config.php");
    
    if(isset($_GET["deleteBillingID"])){
        $billing_id_number = mysqli_real_escape_string($connection_server, preg_replace("/[^0-9]+/", "", trim(strip_tags($_GET["deleteBillingID"]))));
        if(is_numeric($billing_id_number)){
            $select_billing_with_id = mysqli_query($connection_server, "SELECT * FROM sas_vendor_billings WHERE id='$billing_id_number'");
            if(mysqli_num_rows($select_billing_with_id) == 1){
                mysqli_query($connection_server, "DELETE FROM sas_vendor_billings WHERE id='$billing_id_number'");
                $json_response_array = array("desc" => ucwords("Billing Deleted Successfully"));
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                if(mysqli_num_rows($select_billing_with_id) > 1){
                    $json_response_array = array("desc" => ucwords("Duplicated Details, Contact Admin"));
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(mysqli_num_rows($select_billing_with_id) < 1){
                        $json_response_array = array("desc" => ucwords("Billing Details Not Exists Or May Have Been Deleted"));
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }
            }
        }else{
            //Non-numeric string
            $json_response_array = array("desc" => "Non-numeric string");
            $json_response_encode = json_encode($json_response_array,true);
        }
        
        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: /bc-spadmin/Billings.php");
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

    <?php
    	//Redirect To Vendor Page
        $getVendorUrl = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["vendorUrl"])));
    	$getVendorLogAuth = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["vendorLogAuth"])));
        $getRedirectUrl = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["redirect"])));
    	
    	if(isset($_GET["vendorUrl"]) && !empty($getVendorUrl) && isset($_GET["vendorLogAuth"]) && !empty($getVendorLogAuth)){
            if(isset($_GET["redirect"]) && !empty($getRedirectUrl)){
                echo '<script>	window.onload = function(){	window.open("http://'.$getVendorUrl.'/bc-admin/Dashboard.php?logVendorAdmin='.$getVendorLogAuth.'&&redirectAdminTo='.$getRedirectUrl.'","_blank"); window.open("/bc-spadmin/Vendors.php","_self");	}	</script>';
            }else{
                echo '<script>	window.onload = function(){	window.open("http://'.$getVendorUrl.'/bc-admin/Dashboard.php?logVendorAdmin='.$getVendorLogAuth.'","_blank"); window.open("/bc-spadmin/Vendors.php","_self");	}	</script>';
            }
    	}
    ?>
</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>
    <div class="pagetitle">
      <h1>VIEW  BILLINGS</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Billings</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row g-4">
            <div class="col-12">
                <?php
                    $page_num = (isset($_GET["page"]) && is_numeric($_GET["page"]) && $_GET["page"] >= 1) ? (int)$_GET["page"] : 1;
                    $limit = 20;
                    $offset = ($page_num - 1) * $limit;

                    $searchq = isset($_GET["searchq"]) ? trim(strip_tags($_GET["searchq"])) : "";
                    $search_statement = "";
                    $search_parameter = "";
                    if(!empty($searchq)){
                        $search_esc = mysqli_real_escape_string($connection_server, $searchq);
                        $search_statement = " && (bill_type LIKE '%$search_esc%' OR amount LIKE '%$search_esc%' OR description LIKE '%$search_esc%' OR starting_date LIKE '%$search_esc%' OR ending_date LIKE '%$search_esc%')";
                        $search_parameter = "searchq=".urlencode($searchq)."&";
                    }
                    $get_billings = mysqli_query($connection_server, "SELECT * FROM sas_vendor_billings WHERE starting_date != '' $search_statement ORDER BY date DESC LIMIT $limit OFFSET $offset");
                ?>

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white py-4 border-0">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <h5 class="fw-bold mb-0 text-primary">Vendor Billing Records</h5>
                                <p class="text-muted small mb-0">Manage system-wide billing cycles and invoices</p>
                            </div>
                            <div class="col-md-6">
                                <form method="get" action="Billings.php" class="d-flex gap-2 justify-content-md-end">
                                    <input name="searchq" type="text" value="<?php echo $searchq; ?>" placeholder="Search billing details..." class="form-control rounded-pill px-3" style="max-width: 250px;" />
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Filter</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr class="small text-uppercase text-muted">
                                        <th class="ps-4">S/N</th>
                                        <th>Billing Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Period</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($get_billings) > 0):
                                        $count = $offset + 1;
                                        while($billing = mysqli_fetch_assoc($get_billings)):
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><?php echo $count; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo strtoupper($billing["bill_type"]); ?></div>
                                            <span class="small text-muted">ID: #B-<?php echo $billing['id']; ?></span>
                                        </td>
                                        <td class="small text-muted" style="max-width: 200px;">
                                            <div class="text-truncate" title="<?php echo strip_tags($billing["description"]); ?>">
                                                <?php echo strip_tags($billing["description"]); ?>
                                            </div>
                                        </td>
                                        <td class="fw-bold">₦<?php echo number_format($billing["amount"], 2); ?></td>
                                        <td>
                                            <div class="small"><i class="bi bi-calendar-event me-1 text-success"></i><?php echo date('M d, Y', strtotime($billing["starting_date"])); ?></div>
                                            <div class="small"><i class="bi bi-calendar-x me-1 text-danger"></i><?php echo date('M d, Y', strtotime($billing["ending_date"])); ?></div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm shadow-sm rounded-pill overflow-hidden">
                                                <button onclick="customJsRedirect('/bc-spadmin/BillingEdit.php?billingID=<?php echo $billing['id']; ?>', 'Edit this billing record?')" class="btn btn-white border-end" title="Edit"><i class="bi bi-pencil-square text-primary"></i></button>
                                                <button onclick="customJsRedirect('/bc-spadmin/Billings.php?deleteBillingID=<?php echo $billing['id']; ?>', 'Are you sure you want to delete this billing?')" class="btn btn-white text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $count++; endwhile; else: ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">No billing records found</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-4 border-0">
                        <div class="d-flex justify-content-center gap-2">
                            <?php if($page_num > 1): ?>
                            <a href="Billings.php?<?php echo $search_parameter; ?>page=<?php echo ($page_num - 1); ?>" class="btn btn-outline-primary btn-sm px-4 rounded-pill">Previous</a>
                            <?php endif; ?>
                            <a href="Billings.php?<?php echo $search_parameter; ?>page=<?php echo ($page_num + 1); ?>" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm">Next Page</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("../func/bc-spadmin-footer.php"); ?>
    
</body>
</html>