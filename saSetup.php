<?php session_start();
    include("./func/bc-config.php");
    
    $json_response_encode = null;

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
                        	$q = mysqli_query($connection_server, "INSERT INTO sas_super_admin (email, password, firstname, lastname, phone_number, gender, home_address, status) VALUES ('$email','$md5_pass','$first','$last','$phone','$gender', '$address', '1')");
                            if ($q) {
                                $json_response_array = array("desc" => "Super Admin Account Created Successfully!");
                                $json_response_encode = json_encode($json_response_array);
                            } else {
                                $json_response_array = array("desc" => "Database Error: Could not save profile.");
                                $json_response_encode = json_encode($json_response_array);
                            }
                        }else{
                            $json_response_array = array("desc" => "Error: Phone Number already in use.");
                            $json_response_encode = json_encode($json_response_array);
                        }
                    }else{
                        $json_response_array = array("desc" => "Error: Email Address already in use.");
                        $json_response_encode = json_encode($json_response_array);
                    }
                }else{
                    $json_response_array = array("desc" => "Error: Invalid Email Format.");
                    $json_response_encode = json_encode($json_response_array);
                }
        }else{
            $json_response_array = array("desc" => "Error: Please fill all fields correctly (Phone must be 11 digits).");
            $json_response_encode = json_encode($json_response_array);
        }
    
        if ($json_response_encode) {
            $json_response_decode = json_decode($json_response_encode,true);
            $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
            
            if (str_contains($json_response_decode["desc"], "Successfully")) {
                header("Location: /bc-spadmin");
                exit();
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Super Admin Setup | DGV6.90 AI Edition</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --glass: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        body {
            background: radial-gradient(circle at top left, #f5f3ff, #ede9fe);
            color: #1e293b;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 2rem 0;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: var(--primary);
            filter: blur(120px);
            opacity: 0.15;
            top: -100px;
            right: -100px;
            z-index: -1;
        }

        .setup-card {
            max-width: 600px;
            width: 94%;
            padding: 3rem;
            border-radius: 28px;
            background: var(--glass);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 1;
        }

        .header-section {
            margin-bottom: 2.5rem;
        }

        .logo-box {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            padding: 15px;
        }

        .logo-box img {
            max-width: 100%;
            height: auto;
        }

        .section-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after {
            content: "";
            height: 1px;
            flex-grow: 1;
            background: linear-gradient(to right, rgba(99, 102, 241, 0.2), transparent);
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 14px;
            padding: 0.8rem 1.2rem;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(203, 213, 225, 0.6);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn-setup {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 16px;
            padding: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
            margin-top: 2.5rem;
        }

        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(99, 102, 241, 0.5);
            filter: brightness(1.1);
        }

        .alert-modern {
            border-radius: 16px;
            border: none;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
    </style>
</head>
<body>

    <div class="setup-card">
        <div class="header-section text-center">
            <div class="logo-box">
                <img src="uploaded-image/sp-logo.png" alt="Logo" onerror="this.src='asset/user-icon.png'">
            </div>
            <h2 class="fw-extrabold mb-1">Super Admin Setup</h2>
            <p class="text-muted small">Configure the master account for your platform</p>
        </div>

        <?php if(isset($_SESSION["product_purchase_response"])): ?>
            <div class="alert alert-modern fade show" role="alert">
                <div class="alert-icon"><i class="bi bi-info-circle-fill"></i></div>
                <div class="small fw-600"><?php echo $_SESSION["product_purchase_response"]; unset($_SESSION["product_purchase_response"]); ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="section-title"><i class="bi bi-person-badge me-2"></i> Personal Details</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input name="first" type="text" placeholder="e.g. John" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input name="last" type="text" placeholder="e.g. Doe" class="form-control" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select" required>
                        <option value="" hidden selected>Select Gender</option>
                        <option value="m">Male</option>
                        <option value="f">Female</option>
                    </select>
                </div>
            </div>

            <div class="section-title"><i class="bi bi-telephone-inbound me-2"></i> Contact Details</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input name="phone" type="text" placeholder="11 digits" pattern="[0-9]{11}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Home Address</label>
                    <input name="address" type="text" placeholder="City, Country" class="form-control" required>
                </div>
            </div>

            <div class="section-title"><i class="bi bi-shield-lock me-2"></i> Login Credentials</div>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Email Address</label>
                    <input name="email" type="email" placeholder="admin@example.com" class="form-control" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Secure Password</label>
                    <input name="pass" type="password" placeholder="••••••••••••" class="form-control" required>
                </div>
            </div>

            <button name="setup-profile" type="submit" class="btn btn-setup w-100">
                COMPLETE ADMIN SETUP
            </button>
        </form>

        <div class="text-center mt-4">
            <p style="font-size: 0.7rem; color: #94a3b8;" class="mb-0">DGV6.90 Architecture &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>

    <script src="assets-2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>