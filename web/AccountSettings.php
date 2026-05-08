<?php session_start();
    include("../func/bc-config.php");
        
    if(isset($_POST["update-profile"])){
        $first = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["first"]))));
    	$last = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["last"]))));
        $other = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["other"]))));
    	$quest = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["quest"])));
    	$answer = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["answer"]))));
    	$address = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["address"]))));
        $phone = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["phone"]))));
        
        if(!empty($first) && !empty($last) && !empty($quest) && is_numeric($quest) && !empty($answer) && !empty($address) && !empty($phone)){
            $check_user_details = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."'");
            if(mysqli_num_rows($check_user_details) == 1){
                if((strlen($answer) >= 3) && (strlen($answer) <= 20)){
                    if(is_numeric($phone) && (strlen($phone) == 11)){
                    	mysqli_query($connection_server, "UPDATE sas_users SET security_quest='$quest', security_answer='$answer', firstname='$first', lastname='$last', othername='$other', home_address='$address', phone_number='$phone' WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."'");
                		// Email Beginning
               			$log_template_encoded_text_array = array("{firstname}" => $first, "{lastname}" => $last, "{email}" => $get_logged_user_details["email"], "{phone}" => $get_logged_user_details["phone_number"], "{address}" => $address, "{security_answer}" => $answer);
               			$raw_log_template_subject = getUserEmailTemplate('user-account-update','subject');
               			$raw_log_template_body = getUserEmailTemplate('user-account-update','body');
               			foreach($log_template_encoded_text_array as $array_key => $array_val){
              				$raw_log_template_subject = str_replace($array_key, $array_val, $raw_log_template_subject);
               				$raw_log_template_body = str_replace($array_key, $array_val, $raw_log_template_body);
               			}
               			sendVendorEmail($get_logged_user_details["email"], $raw_log_template_subject, $raw_log_template_body);
               			// Email End
               			
               			//Profile Information Updated Successfully
               			$json_response_array = array("desc" => "Profile Information Updated Successfully");
               			$json_response_encode = json_encode($json_response_array,true);
                	}else{
                		//Phone number should be 11 digit long
                		$json_response_array = array("desc" => "Phone number should be 11 digit long");
                		$json_response_encode = json_encode($json_response_array,true);
                	}
                }else{
                    //Security Answer Must Be Between 3-20 Charaters Without Special Charaters
                    $json_response_array = array("desc" => "Security Answer Must Be Between 3-20 Charaters Without Special Charaters");
                    $json_response_encode = json_encode($json_response_array,true);
                }
            }else{
                if(mysqli_num_rows($check_user_details) == 0){
                    //User Not Exists
                    $json_response_array = array("desc" => "User Not Exists");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(mysqli_num_rows($check_user_details) > 1){
                        //Duplicated Details, Contact Admin
                        $json_response_array = array("desc" => "Duplicated Details, Contact Admin");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }
            }
        }else{
            if(empty($first)){
                //Firstname Field Empty
                $json_response_array = array("desc" => "Firstname Field Empty");
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                if(empty($last)){
                    //Lastname Field Empty
                    $json_response_array = array("desc" => "Lastname Field Empty");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(empty($quest)){
                        //Security Question Field Empty
                        $json_response_array = array("desc" => "Security Question Field Empty");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        if(!is_numeric($quest)){
                            //Security Question Cannot Be String
                            $json_response_array = array("desc" => "Security Question Cannot Be String");
                            $json_response_encode = json_encode($json_response_array,true);
                        }else{
                            if(empty($answer)){
                                //Security Answer Field Empty
                                $json_response_array = array("desc" => "Security Answer Field Empty");
                                $json_response_encode = json_encode($json_response_array,true);
                            }else{
                                if(empty($address)){
                                    //Home Address Field Empty
                                    $json_response_array = array("desc" => "Home Address Field Empty");
                                    $json_response_encode = json_encode($json_response_array,true);
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
    
    
    if(isset($_POST["change-password"])){
        $old_pass = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["old-pass"])));
    	$new_pass = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["new-pass"])));
        $con_new_pass = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["con-new-pass"])));
        
        if(!empty($old_pass) && !empty($new_pass) && !empty($con_new_pass)){
            $check_user_details = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."'");
            if(mysqli_num_rows($check_user_details) == 1){
                $md5_old_pass = md5($old_pass);
                $md5_new_pass = md5($new_pass);
                $md5_con_new_pass = md5($con_new_pass);
                
                if($md5_old_pass == $get_logged_user_details["password"]){
                    if($md5_new_pass !== $get_logged_admin_details["password"]){
                        if($md5_new_pass == $md5_con_new_pass){
                            mysqli_query($connection_server, "UPDATE sas_users SET password='$md5_new_pass' WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."'");
                            // Email Beginning
                            $log_template_encoded_text_array = array("{firstname}" => $get_logged_user_details["firstname"], "{lastname}" => $get_logged_user_details["lastname"]);
                            $raw_log_template_subject = getUserEmailTemplate('user-pass-update','subject');
                            $raw_log_template_body = getUserEmailTemplate('user-pass-update','body');
                            foreach($log_template_encoded_text_array as $array_key => $array_val){
                            	$raw_log_template_subject = str_replace($array_key, $array_val, $raw_log_template_subject);
                            	$raw_log_template_body = str_replace($array_key, $array_val, $raw_log_template_body);
                            }
                            sendVendorEmail($get_logged_user_details["email"], $raw_log_template_subject, $raw_log_template_body);
                            // Email End
                            
                            //Account Password Updated Successfully
                            $json_response_array = array("desc" => "Account Password Updated Successfully");
                            $json_response_encode = json_encode($json_response_array,true);
                        }else{
                            //New & Confirm Password Not Match
                            $json_response_array = array("desc" => "New & Confirm Password Not Match");
                            $json_response_encode = json_encode($json_response_array,true);
                        }
                    }else{
                        //New & Old Password Must Be Different
                        $json_response_array = array("desc" => "New & Old Password Must Be Different");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }else{
                    //Incorrect Old Password
                    $json_response_array = array("desc" => "Incorrect Old Password");
                    $json_response_encode = json_encode($json_response_array,true);
                }
            }else{
                if(mysqli_num_rows($check_user_details) == 0){
                    //User Not Exists
                    $json_response_array = array("desc" => "User Not Exists");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(mysqli_num_rows($check_user_details) > 1){
                    //Duplicated Details, Contact Admin
                        $json_response_array = array("desc" => "Duplicated Details, Contact Admin");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }
            }
        }else{
            if(empty($old_pass)){
                //Old Password Field Empty
                $json_response_array = array("desc" => "Old Password Field Empty");
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                if(empty($new_pass)){
                    //New Password Field Empty
                    $json_response_array = array("desc" => "New Password Field Empty");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(empty($con_new_pass)){
                        //Confirm New Password Field Empty
                        $json_response_array = array("desc" => "Confirm New Password Field Empty");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }
            }
        }
    
        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: ".$_SERVER["REQUEST_URI"]);
    }

if (isset($_POST["update-user-security"])) {
    $new_pin = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["new_pin"])));
    $con_pin = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["con_pin"])));

    if (!empty($new_pin)) {
        if (is_numeric($new_pin) && strlen($new_pin) == 4) {
            if ($new_pin === $con_pin) {
                $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
                mysqli_query($connection_server, "UPDATE sas_users SET transaction_pin='$new_pin', security_pin='$hashed_pin' WHERE id='".$get_logged_user_details['id']."'");
                $_SESSION["product_purchase_response"] = "Security settings and PIN updated successfully";
            } else {
                $_SESSION["product_purchase_response"] = "PIN and Confirm PIN do not match";
            }
        } else {
            $_SESSION["product_purchase_response"] = "PIN must be 4 digits";
        }
    } else {
         $_SESSION["product_purchase_response"] = "Security settings updated successfully";
    }

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit();
}

if (isset($_POST["apply-ai-voice"])) {
    $uid = $get_logged_user_details['id'];
    // Re-verify the count
    $tx_count_q = mysqli_query($connection_server, "SELECT COUNT(id) as c FROM transactions WHERE username='".$get_logged_user_details["username"]."' AND status='success'");
    $tx_count = mysqli_fetch_assoc($tx_count_q)['c'];
    
    // Get vendor limit
    $v_limit_q = mysqli_query($connection_server, "SELECT ai_voice_min_tx FROM sas_vendors WHERE id='".$get_logged_user_details["vendor_id"]."'");
    $v_limit = mysqli_fetch_assoc($v_limit_q)['ai_voice_min_tx'] ?? 50;

    if ($tx_count >= $v_limit) {
        mysqli_query($connection_server, "UPDATE sas_users SET ai_voice_status=1 WHERE id='$uid'");
        $_SESSION["product_purchase_response"] = "Application submitted successfully! Your account is pending review by the admin.";
    } else {
        $_SESSION["product_purchase_response"] = "You have not met the transaction requirement to apply.";
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit();
}
?>
<!DOCTYPE html>
<head>
    <title>Account Settings | <?php echo $get_all_site_details["site_title"]; ?></title>
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
<?php
    $tx_count_q = mysqli_query($connection_server, "SELECT COUNT(id) as c FROM transactions WHERE username='".$get_logged_user_details["username"]."' AND status='success'");
    $tx_count = mysqli_fetch_assoc($tx_count_q)['c'];
    $v_limit_q = mysqli_query($connection_server, "SELECT ai_voice_min_tx FROM sas_vendors WHERE id='".$get_logged_user_details["vendor_id"]."'");
    $v_limit = mysqli_fetch_assoc($v_limit_q)['ai_voice_min_tx'] ?? 50;
    $ai_voice_status = (int)$get_logged_user_details['ai_voice_status'];
    $progress = min(100, ($tx_count / $v_limit) * 100);
?>
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
      <h1>USER ACCOUNT SETTINGS</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Account Settings</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 p-4 mb-4 rounded-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-person-badge me-2 text-primary"></i>Personal Profile</h5>
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">First Name</label>
                            <input name="first" type="text" value="<?php echo $get_logged_user_details['firstname']; ?>" class="form-control" required/>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Last Name</label>
                            <input name="last" type="text" value="<?php echo $get_logged_user_details['lastname']; ?>" class="form-control" required/>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Other Name</label>
                            <input name="other" type="text" value="<?php echo $get_logged_user_details['othername']; ?>" class="form-control" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Phone Number</label>
                            <input name="phone" type="text" value="<?php echo $get_logged_user_details['phone_number']; ?>" class="form-control" pattern="[0-9]{11}" required/>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Home Address</label>
                        <input name="address" type="text" value="<?php echo $get_logged_user_details['home_address']; ?>" class="form-control" required/>
                    </div>

                    <hr class="my-4 opacity-25">
                    <h6 class="fw-bold mb-3">Security Question</h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Question</label>
                        <select name="quest" class="form-select" required>
                            <option value="" default hidden selected>Choose Question</option>
                            <?php
                                $get_security_quest_details = mysqli_query($connection_server, "SELECT * FROM sas_security_quests");
                                while($security_details = mysqli_fetch_assoc($get_security_quest_details)){
                                    $selected = ($security_details["id"] == $get_logged_user_details['security_quest']) ? 'selected' : '';
                                    echo '<option value="'.$security_details["id"].'" '.$selected.'>'.$security_details["quest"].'</option>';
                                }
                            ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Answer</label>
                        <input name="answer" type="text" value="<?php echo $get_logged_user_details['security_answer']; ?>" class="form-control" placeholder="Your secret answer" required/>
                    </div>

                    <button name="update-profile" type="submit" class="btn btn-primary btn-lg w-100 shadow-sm rounded-3 py-3 fw-bold">
                        UPDATE PROFILE
                    </button>
                </form>
            </div>

        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 p-4 rounded-4 mb-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-shield-lock me-2 text-danger"></i>Security PIN Setup</h5>
                <form method="post" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NEW 4-DIGIT PIN</label>
                        <input name="new_pin" type="password" maxlength="4" pattern="[0-9]{4}" class="form-control bg-light" placeholder="Leave empty to keep current" inputmode="numeric"/>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">CONFIRM PIN</label>
                        <input name="con_pin" type="password" maxlength="4" pattern="[0-9]{4}" class="form-control bg-light" placeholder="Repeat new PIN" inputmode="numeric"/>
                    </div>
                    <button name="update-user-security" type="submit" class="btn btn-danger btn-lg w-100 shadow-sm rounded-3 py-3 fw-bold">
                        SAVE SECURITY SETTINGS
                    </button>
                </form>
            </div>

            <div class="card shadow-sm border-0 p-4 rounded-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-key me-2 text-warning"></i>Change Password</h5>
                <form method="post" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Current Password</label>
                        <input name="old-pass" type="password" class="form-control bg-light" required/>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">New Password</label>
                        <input name="new-pass" type="password" class="form-control bg-light" required/>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Confirm Password</label>
                        <input name="con-new-pass" type="password" class="form-control bg-light" required/>
                    </div>
                    <button name="change-password" type="submit" class="btn btn-warning btn-lg w-100 shadow-sm rounded-3 py-3 fw-bold">
                        UPDATE PASSWORD
                    </button>
                </form>
            </div>

                        <div class="card shadow-sm border-0 p-4 rounded-4 mt-4" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
                <h5 class="fw-bold mb-3"><i class="bi bi-mic-fill me-2 text-primary"></i>Autonomous AI Access</h5>
                <p class="small text-muted mb-3">Unlock "Zero-Click" Voice commands. Earn this feature by completing successful transactions.</p>
                
                <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span>Progress</span>
                    <span><?php echo $tx_count; ?> / <?php echo $v_limit; ?> Tx</span>
                </div>
                <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
                </div>

                <?php if ($ai_voice_status == 2): ?>
                    <div class="alert alert-success border-0 small py-2 mb-0"><i class="bi bi-shield-check me-1"></i> You are Approved! Tap the microphone in the AI Assistant to buy VTU with your voice.</div>
                <?php elseif ($ai_voice_status == 1): ?>
                    <div class="alert alert-warning border-0 small py-2 mb-0"><i class="bi bi-hourglass-split me-1"></i> Application Pending Review...</div>
                <?php elseif ($ai_voice_status == 3): ?>
                    <div class="alert alert-danger border-0 small py-2 mb-3"><i class="bi bi-x-circle me-1"></i> Application Revoked.</div>
                <?php endif; ?>

                <?php if ($ai_voice_status == 0 || $ai_voice_status == 3): ?>
                    <form method="post">
                        <?php echo bc_csrf_field(); ?>
                        <button type="submit" name="apply-ai-voice" class="btn btn-primary btn-sm w-100 fw-bold rounded-pill" <?php if ($tx_count < $v_limit) echo 'disabled'; ?>>
                            <?php echo $tx_count >= $v_limit ? 'Apply Now' : 'Locked'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card shadow-sm border-0 p-4 rounded-4 mt-4 bg-primary text-white">
                <h6 class="fw-bold mb-2">Need Help?</h6>
                <p class="small opacity-75 mb-3">If you're having trouble updating your profile or KYC details, please reach out to our support team.</p>
                <?php
                    $get_support_phone = mysqli_fetch_array(mysqli_query($connection_server, "SELECT phone_number FROM sas_admin_payments WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' LIMIT 1"));
                    $support_phone = !empty($get_support_phone['phone_number']) ? $get_support_phone['phone_number'] : $select_vendor_table['phone_number'];
                    $wa_phone = (substr($support_phone, 0, 1) == '0') ? "234" . substr($support_phone, 1) : $support_phone;
                ?>
                <a href="https://wa.me/<?php echo $wa_phone; ?>" target="_blank" class="btn btn-light btn-sm fw-bold rounded-pill px-3">Contact Support</a>
            </div>
        </div>
      </div>
      </div>
    </section>
	<?php include("../func/bc-footer.php"); ?>
	
</body>
</html>
