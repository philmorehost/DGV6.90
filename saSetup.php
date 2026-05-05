<?php session_start();
    include("./func/bc-config.php");
    
    if(isset($_POST["setup-profile"])){
        $first = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["first"]))));
        $last = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["last"]))));
        $address = mysqli_real_escape_string($connection_server, trim(strip_tags(ucwords($_POST["address"]))));
        $email = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["email"]))));
        $phone = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["phone"]))));
    	$pass = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["pass"])));
        $gender = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["gender"]))));
        
        if(!empty($first) && !empty($last) && !empty($address) && !empty($email) && !empty($phone) && (strlen($phone) == 11) && !empty($pass) && !empty($gender)){
                $md5_pass = md5($pass);
                $check_admin_with_email = mysqli_query($connection_server, "SELECT * FROM sas_super_admin WHERE email='$email'");
                $check_admin_with_phone = mysqli_query($connection_server, "SELECT * FROM sas_super_admin WHERE phone_number='$phone'");
                
                if(filter_var($email, FILTER_VALIDATE_EMAIL)){
                    if(mysqli_num_rows($check_admin_with_email) == 0){
                        if(mysqli_num_rows($check_admin_with_phone) == 0){
                        	mysqli_query($connection_server, "INSERT INTO sas_super_admin (email, password, firstname, lastname, phone_number, gender, home_address, status) VALUES ('$email','$md5_pass','$first','$last','$phone','$gender', '$address', '1')");
                        }else{
                        	if(mysqli_num_rows($check_admin_with_phone) == 1){
                        		//Phone Number Taken By Another Admin
                        		$json_response_array = array("desc" => "Phone Number Taken By Another Admin");
                        		$json_response_encode = json_encode($json_response_array,true);
                        	}else{
                        		//Duplicated Phone Number, Contact Admin
                        		$json_response_array = array("desc" => "Duplicated Phone Number, Contact Admin");
                        		$json_response_encode = json_encode($json_response_array,true);
                        	}
                        }
                    }else{
                        if(mysqli_num_rows($check_admin_with_email) == 1){
                            //Email Taken By Another Admin
                            $json_response_array = array("desc" => "Email Taken By Another Admin");
                            $json_response_encode = json_encode($json_response_array,true);
                        }else{
                            //Duplicated Email, Contact Admin
                            $json_response_array = array("desc" => "Duplicated Email, Contact Admin");
                            $json_response_encode = json_encode($json_response_array,true);
                        }
                    }
    
                    $proceed_account_update = false;
                    if($proceed_account_phone_verification == true){
                        if(mysqli_num_rows($check_admin_with_phone) == 1){
                            $admin_phone_fetch = mysqli_fetch_array($check_admin_with_phone);
                            if($admin_phone_fetch["id"] == $get_logged_spadmin_details["id"]){
                                $proceed_account_update = true;
                            }else{
                                //Phone Number Taken By Another Admin
                                $json_response_array = array("desc" => "Phone Number Taken By Another Admin");
                                $json_response_encode = json_encode($json_response_array,true);
                            }
                        }else{
                            if(mysqli_num_rows($check_admin_with_phone) == 0){
                                $proceed_account_update = true;
                            }else{
                                //Duplicated Phone Number, Contact Admin
                                $json_response_array = array("desc" => "Duplicated Phone Number, Contact Admin");
                                $json_response_encode = json_encode($json_response_array,true);
                            }
                        }
                    }
    
                    if($proceed_account_update == true){
                        mysqli_query($connection_server, "UPDATE sas_super_admin SET firstname='$first', lastname='$last', home_address='$address', email='$email', phone_number='$phone' WHERE id='".$get_logged_spadmin_details["id"]."'");
                        //Profile Information Updated Successfully
                        $json_response_array = array("desc" => "Profile Information Updated Successfully");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }else{
                    //Invalid Email Address
                    $json_response_array = array("desc" => "Invalid Email Address");
                    $json_response_encode = json_encode($json_response_array,true);
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
                    if(empty($address)){
                        //Home Address Field Empty
                        $json_response_array = array("desc" => "Home Address Field Empty");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        if(empty($email)){
                            //Email Field Empty
                            $json_response_array = array("desc" => "Email Field Empty");
                            $json_response_encode = json_encode($json_response_array,true);
                        }else{
                            if(empty($phone)){
                                //Phone Number Field Empty
                                $json_response_array = array("desc" => "Phone Number Field Empty");
                                $json_response_encode = json_encode($json_response_array,true);
                            }else{
                            	if((strlen($phone) < 11)){
                            		//Phone Number Less Than 11 digit
                            		$json_response_array = array("desc" => "Phone Number Less Than 11 digit");
                            		$json_response_encode = json_encode($json_response_array,true);
                            	}else{
                            		if((strlen($phone) > 11)){
                            			//Phone Number Greater Than 11 digit
                            			$json_response_array = array("desc" => "Phone Number Greater Than 11 digit");
                            			$json_response_encode = json_encode($json_response_array,true);
                            		}else{
                                		if(empty($pass)){
                                    		//Password Field Empty
                                    		$json_response_array = array("desc" => "Password Field Empty");
                                    		$json_response_encode = json_encode($json_response_array,true);
                                		}else{
                                			if(empty($gender)){
                                				//Gender Field Empty
                                				$json_response_array = array("desc" => "Gender Field Empty");
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
        }
    
        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: /bc-spadmin");
    }
?>
<!DOCTYPE html>
<head>
	<title>Super Admin</title>
    <meta charset="UTF-8" />
    <meta name="description" content="" />
    <meta http-equiv="Content-Type" content="text/html; " />
    <meta name="theme-color" content="black" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <meta name="author" content="Philmore Codes">
    <meta name="dc.creator" content="Philmore Codes">
    <style>
    	body{
    		background-color: var(--color-5);
    	}
    </style>
</head>
<body>
    <div style="text-align: center;" class="bg-10 m-block-dp s-block-dp m-position-abs s-position-abs br-radius-5px m-width-94 s-width-50 m-height-auto s-height-auto m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-padding-tp-3 s-padding-tp-3 m-padding-bm-3 s-padding-bm-3 m-margin-tp-1 s-margin-tp-1 m-margin-lt-2 s-margin-lt-24">
        <img src="<?php echo $web_http_host; ?>/uploaded-image/sp-logo.png" style="user-select: auto; object-fit: contain; object-position: center;" class="a-cursor m-position-rel s-position-rel m-inline-block-dp s-inline-block-dp m-width-30 s-width-30 m-height-30 s-height-30 m-margin-tp-0 s-margin-tp-0 m-margin-bm-3 s-margin-bm-2"/><br/>
        <span style="user-select: auto;" class="text-bg-1 color-4 m-inline-block-dp s-inline-block-dp text-bold-500 m-font-size-20 s-font-size-25 m-margin-bm-2 s-margin-bm-2">SUPER ADMIN SET-UP</span><br>
        <form method="post" action="">
            <div style="text-align: center;" class="color-2 bg-3 m-inline-block-dp s-inline-block-dp m-width-20 s-width-15">
                <img src="<?php echo $web_http_host; ?>/asset/user-icon.png" class="a-cursor m-width-100 s-width-100" style="pointer-events: none; user-select: auto;"/>
            </div><br/>
            <div style="text-align: center;" class="color-4 bg-3 m-inline-block-dp s-inline-block-dp m-font-size-14 s-font-size-16 m-width-60 s-width-45">
                <span id="user-status-span" class="a-cursor" style="user-select: auto;">PERSONAL INFORMATION</span>
            </div><br/>
            <input style="text-align: center;" name="first" type="text" value="" placeholder="Firstname" pattern="[a-zA-Z ]{3,}" title="Firstname must be atleast 3 letters long" class="input-box outline-none color-4 bg-2 m-inline-block-dp s-inline-block-dp outline-none br-radius-5px br-width-4 br-color-4 m-width-60 s-width-45 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-1 s-margin-bm-1" required/><br/>
            <input style="text-align: center;" name="last" type="text" value="" placeholder="Lastname" pattern="[a-zA-Z ]{3,}" title="Lastname must be atleast 3 letters long" class="input-box outline-none color-4 bg-2 m-inline-block-dp s-inline-block-dp outline-none br-radius-5px br-width-4 br-color-4 m-width-60 s-width-45 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-1 s-margin-bm-1" required/><br/>
			<select style="text-align: center;" id="" name="gender" onchange="" class="select-box outline-none color-4 bg-2 m-inline-block-dp s-inline-block-dp outline-none br-radius-5px br-width-4 br-color-4 m-width-63 s-width-47 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-1 s-margin-bm-1" required/>
            	<option value="" default hidden selected>Gender</option>
            	<option value="m">Male</option>
            	<option value="f">Female</option>
            </select><br/>
            
            <div style="text-align: center;" class="color-4 bg-3 m-inline-block-dp s-inline-block-dp m-font-size-14 s-font-size-16 m-width-60 s-width-45">
                <span id="user-status-span" class="a-cursor" style="user-select: auto;">CONTACT INFORMATION</span>
            </div><br/>
            <input style="text-align: center;" name="phone" type="text" value="" placeholder="Phone Number" pattern="[0-9]{11}" title="Phone number must be 11 digit long" class="input-box outline-none color-4 bg-2 m-inline-block-dp s-inline-block-dp outline-none br-radius-5px br-width-4 br-color-4 m-width-60 s-width-45 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-1 s-margin-bm-1" required/><br/>
            <input style="text-align: center;" name="address" type="text" value="" placeholder="Home Address" class="input-box outline-none color-4 bg-2 m-inline-block-dp s-inline-block-dp outline-none br-radius-5px br-width-4 br-color-4 m-width-60 s-width-45 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-1 s-margin-bm-1" required/><br/>
            
            <div style="text-align: center;" class="color-4 bg-3 m-inline-block-dp s-inline-block-dp m-font-size-14 s-font-size-16 m-width-60 s-width-45">
                <span id="user-status-span" class="a-cursor" style="user-select: auto;">LOGIN INFORMATION</span>
            </div><br/>
            <input style="text-align: center;" name="email" type="email" value="" placeholder="Email" class="input-box outline-none color-4 bg-2 m-inline-block-dp s-inline-block-dp outline-none br-radius-5px br-width-4 br-color-4 m-width-60 s-width-45 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-1 s-margin-bm-1" required/><br/>
            <input style="text-align: center;" name="pass" type="password" value="" placeholder="Password" class="input-box outline-none color-4 bg-2 m-inline-block-dp s-inline-block-dp outline-none br-radius-5px br-width-4 br-color-4 m-width-60 s-width-45 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-1 s-margin-bm-1" required/><br/>
            
            <button onclick="askPermissionSubBtn(this,'Are you sure you want to proceed?');" id="" name="setup-profile" type="button" style="user-select: auto;" class="button-box a-cursor outline-none color-2 bg-7 m-inline-block-dp s-inline-block-dp outline-none onhover-bg-color-5 br-radius-5px br-width-4 br-color-4 m-width-63 s-width-47 m-padding-tp-2 s-padding-tp-1 m-padding-bm-2 s-padding-bm-1 m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-margin-bm-2 s-margin-bm-2" >
                SET-UP PROFILE
            </button><br/>
            
        </form>
    </div>

<?php if(isset($_SESSION["product_purchase_response"])){ ?>
<div style="text-align: center;" id="customAlertDiv" class="bg-2 box-shadow m-z-index-2 s-z-index-2 m-block-dp s-block-dp m-position-fix s-position-fix m-top-20 s-top-40 br-radius-5px m-width-60 s-width-26 m-height-auto s-height-auto m-padding-lt-1 s-padding-lt-1 m-padding-rt-1 s-padding-rt-1 m-padding-tp-5 s-padding-tp-1 m-padding-bm-5 s-padding-bm-1 m-margin-lt-19 s-margin-lt-36 m-margin-bm-2 s-margin-bm-2">
	<span style="user-select: notne;" class="color-10 text-bold-500 m-font-size-20 s-font-size-25">
		<?php echo $_SESSION["product_purchase_response"]; ?>
	</span><br/>
	<button style="text-align: center; user-select: auto;" onclick="customDismissPop();" onkeypress="keyCustomDismissPop(event);" class="button-box onhover-bg-color-10 a-cursor color-2 bg-10 m-font-size-12 s-font-size-13 br-style-tp-0 m-inline-dp s-inline-block-dp m-position-rel s-position-rel m-width-30 s-width-30 m-height-auto s-height-auto m-margin-tp-1 s-margin-tp-1 m-margin-bm-2 s-margin-bm-2 m-margin-lt-0 s-margin-lt-0 m-margin-rt-0 s-margin-rt-0 m-padding-tp-5 s-padding-tp-5 m-padding-bm-5 s-padding-bm-5 m-padding-lt-5 s-padding-lt-5 m-padding-rt-5 s-padding-rt-5">
		DISMISS
	</button>
</div>
<script>
	function customDismissPop(){
		var customAlertDiv = document.getElementById("customAlertDiv");
		setTimeout(function(){
			customAlertDiv.style.display = "none";
		}, 300);
	}
	
	document.addEventListener("keydown", function(event){
		if(event.keyCode === 13){
			//prevent enter key default function
			event.preventDefault();
			var customAlertDiv = document.getElementById("customAlertDiv");
			setTimeout(function(){
				customAlertDiv.style.display = "none";
			}, 300);
		}
	});
	
	clearProductResponse();
	function clearProductResponse(){
		var productHttp = new XMLHttpRequest();
        productHttp.open("GET", "../unset-product.php");
        productHttp.setRequestHeader("Content-Type", "application/json");
        // productHttp.onload = function(){
        //     alert(productHttp.status);
        // }
        productHttp.send();
	}
</script>
<?php } ?>
<script src="/jsfile/bc-custom-all.js"></script>
</body>
</html>