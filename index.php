<?php
// Single source of truth for vendor data
include("func/bc-connect.php");

// Initialize vendor details
$vendor_account_details = null;
$error_message = null;

if ($connection_server) {
    $host = $_SERVER["HTTP_HOST"];
    
    // Use a prepared statement to securely fetch vendor details
    $stmt = mysqli_prepare($connection_server, "SELECT * FROM sas_vendors WHERE website_url = ? AND status = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $host);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $vendor_account_details = $row;
        } else {
            // No vendor found for this host
            $error_message = "No vendor found for this host.";
        }
    } else {
        // Query execution failed
        $error_message = "Failed to execute vendor query.";
    }
    
    mysqli_stmt_close($stmt);
} else {
    // Database connection failed
    $error_message = "Failed to connect to the database.";
}

// Default CSS template
$css_style_template_location = "index-bc-style-template-1.php";

// If a vendor is found, check for a custom style template
if ($vendor_account_details) {
    $stmt_template = mysqli_prepare($connection_server, "SELECT template_name FROM sas_vendor_style_templates WHERE vendor_id = ?");
    mysqli_stmt_bind_param($stmt_template, "i", $vendor_account_details["id"]);
    
    if (mysqli_stmt_execute($stmt_template)) {
        $result_template = mysqli_stmt_get_result($stmt_template);
        if ($get_vendor_style_template = mysqli_fetch_assoc($result_template)) {
            $style_template_name = explode(".", trim($get_vendor_style_template["template_name"]))[0];
            if (!empty($style_template_name)) {
                $style_template_location = "index-" . $style_template_name . ".php";
                if (file_exists($style_template_location)) {
                    $css_style_template_location = $style_template_location;
                }
            }
        }
    }
    
    mysqli_stmt_close($stmt_template);
}

// Pass both vendor data and any error message to the template
include($css_style_template_location);
?>