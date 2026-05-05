<?php
    error_reporting(0);
    ini_set('display_errors', 0);

	date_default_timezone_set('Africa/Lagos');
	include_once(__DIR__ . "/db-dtl.php");
	include_once(__DIR__ . "/bc-mailer.php");
	include_once(__DIR__ . "/email-design.php");

    $connection = null;
    $connection_server = null;

    // Branch DG6.7 Optimization: Only connect once and remove redundant CREATE DATABASE check on every request.
    try {
	    $connection_server = mysqli_connect($mySqlServer, $mySqlUser, $mySqlPass, $mySqlDBName);
        if ($connection_server) {
            mysqli_set_charset($connection_server, "utf8mb4");
        }
        $connection = $connection_server;
    } catch (Exception $e) {
        // Silent fail, handled by checking $connection_server downstream
    }

    // Now include functions that may depend on $connection_server
    include_once(__DIR__ . "/bc-func.php");

    // Define the web host
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $protocol = "https://";
    } else {
        $protocol = "http://";
    }
    $web_http_host = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
	
	$get_requested_website_domain_url = $_SERVER["HTTP_HOST"] ?? 'localhost';
    if (substr($get_requested_website_domain_url, 0, 4) === "www.") {
        $non_www = substr($get_requested_website_domain_url, 4);
		if(isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on")){
			header("Location: https://" . $non_www . $_SERVER["REQUEST_URI"]);
		}else{
			header("Location: http://" . $non_www . $_SERVER["REQUEST_URI"]);
		}
        exit();
	}
