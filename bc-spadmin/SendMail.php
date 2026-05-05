<?php session_start();
    include("../func/bc-spadmin-config.php");
    
    if(isset($_POST["send-mail"])){
        $subject = mysqli_real_escape_string($connection_server, trim($_POST["subject"]));
        $body = mysqli_real_escape_string($connection_server, str_replace(["\r\n", "\n"], "<br/>", trim($_POST["body"])));
        $mailto = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["mailto"]))));
        $mailto_array = array("all","a","b","d","bd");
        if(!empty($subject) && !empty($body) && !empty($mailto) && in_array($mailto, $mailto_array)){
            $select_users = mysqli_query($connection_server, "SELECT * FROM sas_vendors");
                if(mysqli_num_rows($select_users) >= 1){
                    // Email Beginning
                    $send_mail_to_specified_users = sendSuperAdminEmailSpecific($mailto, $subject, $body);
                    if($send_mail_to_specified_users == "success"){
                        //Mail Sent Successfully
                        $json_response_array = array("desc" => "Mail Sent Successfully");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        if($send_mail_to_specified_users == "failed"){
                            //Error: No Account For Mail-To Type
                            $json_response_array = array("desc" => "Error: No Account For Mail-To Type");
                            $json_response_encode = json_encode($json_response_array,true);
                        }else{
                            if($send_mail_to_specified_users == "error"){
                                //Error: Invalid Mail-To Function
                                $json_response_array = array("desc" => "Error: Invalid Mail-To Function");
                                $json_response_encode = json_encode($json_response_array,true);
                            }
                        }
                    }
                    // Email End
                }else{
                    //Error: No Account
                    $json_response_array = array("desc" => "Error: No Account");
                    $json_response_encode = json_encode($json_response_array,true);
                }
		}else{
			if(empty($subject)){
                //Email Subject Field Empty
				$json_response_array = array("desc" => "Email Subject Field Empty");
				$json_response_encode = json_encode($json_response_array,true);
            }else{
                if(empty($body)){
                    //Email Body Field Empty
                    $json_response_array = array("desc" => "Email Body Field Empty");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(empty($mailto)){
                        //Mail-To Field Empty
                        $json_response_array = array("desc" => "Mail-To Field Empty");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        if(!in_array($mailto, $mailto_array)){
                            //Invalid Mail-To Function
                            $json_response_array = array("desc" => "Invalid Mail-To Function");
                            $json_response_encode = json_encode($json_response_array,true);
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
    <title>Mailing System</title>
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
      <h1>MAIL SENDER</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Send Mail</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-4 border-0 text-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-envelope-paper text-dark-primary fs-1"></i>
                    </div>
                    <h4 class="fw-bold mb-0">Super Admin Mail Sender</h4>
                    <p class="text-muted small">Broadcast custom emails to all or specific vendor groups</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <form method="post">
                        <div class="alert alert-info border-0 rounded-4 d-flex align-items-center mb-5 shadow-sm">
                            <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                            <div class="small">
                                <p class="fw-bold mb-1 text-uppercase">Supported Placeholders:</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <code class="bg-white border rounded px-1">{firstname}</code>
                                    <code class="bg-white border rounded px-1">{lastname}</code>
                                    <code class="bg-white border rounded px-1">{email}</code>
                                    <code class="bg-white border rounded px-1">{phone}</code>
                                    <code class="bg-white border rounded px-1">{address}</code>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-muted text-uppercase">Email Subject</label>
                                <input name="subject" type="text" value="System Notification Update" class="form-control rounded-3 py-2" required />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Target Audience</label>
                                <select name="mailto" class="form-select rounded-3 py-2" required>
                                    <option value="" selected hidden default>Choose Mail To</option>
                                    <option value="all">All Vendors</option>
                                    <option value="a">Active Vendors Only</option>
                                    <option value="b">Suspended Vendors</option>
                                    <option value="d">Deleted Accounts</option>
                                    <option value="bd">Blocked & Deleted</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted text-uppercase">Message Body (HTML Supported)</label>
                                <textarea name="body" class="form-control rounded-4 p-3" rows="10" placeholder="Write your email content here..." required></textarea>
                            </div>
                        </div>

                        <button name="send-mail" type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm py-3 mt-3">
                            <i class="bi bi-send-fill me-2"></i>Dispatch Global Broadcast
                        </button>
                    </form>
                </div>
            </div>
        </div>
      </div>
    </section>

	<?php include("../func/bc-spadmin-footer.php"); ?>
	
</body>
</html>