<?php session_start();
    include("./func/bc-spadmin-config.php");
    if($connection){
    	header("Location: /bc-spadmin");
        exit();
    }
    if(isset($_POST["setup"])){
    	$host = trim(strip_tags($_POST["host"]));
    	$dbname = trim(strip_tags($_POST["dbname"]));
    	$user = trim(strip_tags($_POST["user"]));
    	$pass = trim(strip_tags($_POST["pass"]));
    	if(!empty($host) && !empty($dbname) && !empty($user)){
    		$db_json_text = 
    		'<?php'."\n".'	$db_json_dtls = array("server" => "'.$host.'", "user" => "'.$user.'", "pass" => "'.$pass.'", "dbname" => "'.$dbname.'");'."\n".'	$db_json_encode = json_encode($db_json_dtls,true);'."\n".'	$db_json_decode = json_decode($db_json_encode,true);'."\n".'?>';
    		if(file_exists("./func/db-json.php")){
    			file_put_contents("./func/db-json.php", $db_json_text);
			$_SESSION["product_purchase_response"] = "Database Information Updated Successfully";
    		}else{
    			file_put_contents("./func/db-json.php", $db_json_text);
			$_SESSION["product_purchase_response"] = "Database Information Created Successfully";
    		}
    	}else{
            $_SESSION["product_purchase_response"] = "Please fill all required fields.";
    	}
		
        header("Location: ".$_SERVER["REQUEST_URI"]);
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Database Setup | Philmore Codes</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets-2/css/style.css" rel="stylesheet">
    <style>
	body {
		background: #f6f9ff;
            color: #444444;
            font-family: "Open Sans", sans-serif;
    	}
        .setup-card {
            max-width: 500px;
            width: 100%;
            padding: 40px;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        .icon-box {
            width: 70px;
            height: 70px;
            background: #e7f0fe;
            color: #287bff;
            border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px;
            margin: 0 auto 25px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            background-color: #f8f9fa;
        }
        .form-control:focus {
            background-color: #fff;
            border-color: #287bff;
            box-shadow: 0 0 0 0.25rem rgba(40, 123, 255, 0.1);
        }
        .btn-primary {
            background-color: #287bff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3">

    <div class="setup-card">
        <div class="text-center">
            <div class="icon-box">
                <i class="bi bi-database-fill-gear"></i>
            </div>
            <h3 class="fw-bold mb-1">Database Setup</h3>
            <p class="text-muted mb-4 small">Configure your server credentials to initialize the platform</p>
        </div>

        <?php if(isset($_SESSION["product_purchase_response"])): ?>
            <div class="alert alert-info alert-dismissible fade show py-2 small mb-4" role="alert">
                <i class="bi bi-info-circle me-2"></i><?php echo $_SESSION["product_purchase_response"]; unset($_SESSION["product_purchase_response"]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Host Address</label>
                <input name="host" type="text" value="<?php echo $mySqlServer ?? 'localhost'; ?>" placeholder="e.g. localhost" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Database Name</label>
                <input name="dbname" type="text" value="<?php echo $mySqlDBName ?? ''; ?>" placeholder="Enter database name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Database Username</label>
                <input name="user" type="text" value="<?php echo $mySqlUser ?? ''; ?>" placeholder="Enter username" class="form-control" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Database Password</label>
                <input name="pass" type="password" placeholder="••••••••" class="form-control">
            </div>
            <button name="setup" type="submit" class="btn btn-primary w-100 shadow-sm mb-3">
                SAVE CONFIGURATION
            </button>
            <div class="text-center">
                <p class="small text-muted mb-0">Powered by <span class="fw-bold text-primary">Philmore Codes</span></p>
            </div>
        </form>
    </div>

    <script src="assets-2/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(function() {
            var alert = document.querySelector('.alert');
            if (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    </script>
</body>
</html>