<?php
    // ─── PHP 8.1+ Compatibility Fix ──────────────────────────────────────────────
    // PHP 8.1+ enables STRICT exception mode for MySQLi by default.
    // DGV6.90 legacy code expects mysqli_query to return false on failure instead of crashing.
    mysqli_report(MYSQLI_REPORT_OFF);

	date_default_timezone_set('Africa/Lagos');
	include_once(__DIR__ . "/db-dtl.php");
	include_once(__DIR__ . "/bc-mailer.php");
	include_once(__DIR__ . "/email-design.php");

    $connection = null;
    $connection_server = null;

    try {
	    $connection_server = mysqli_connect($mySqlServer, $mySqlUser, $mySqlPass, $mySqlDBName);
        if ($connection_server) {
            mysqli_set_charset($connection_server, "utf8mb4");
        }
        $connection = $connection_server;
    } catch (mysqli_sql_exception $e) {
        // Log DB connection failure without exposing credentials
        error_log('[DGV-DB] Connection failed: ' . $e->getMessage());
        $connection_server = null;
        $connection = null;
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
