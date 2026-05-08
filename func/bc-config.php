<?php
// ─── PHP 8.3 Compatibility Shim — must be first ──────────────────────────────
// Sets up secure session params, custom error handlers, polyfills, security headers
require_once __DIR__ . '/bc-php-compat.php';

// Standardize session_start across the platform
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on")) {
    $web_http_host = "https://" . $_SERVER["HTTP_HOST"];
} else {
    $web_http_host = "http://" . $_SERVER["HTTP_HOST"];
}

include_once(__DIR__ . "/bc-connect.php");
include_once(__DIR__ . "/bc-security.php"); // DGV6.90 AI Edition — Security utilities
include_once(__DIR__ . "/bc-url.php");      // DGV6.90 v7.0 — Clean URL helper (bc_url())

if (!$connection_server) {
	if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/index.php"))) {
		header("Location: /index.php");
		exit();
	}
}

if ($connection_server) {
    // Branch DG6.7 Optimization: Only run migrations if not already done globally or in current session
    // This significantly improves site-wide page load speeds by skipping redundant DB structural checks.
    define('SYSTEM_VERSION', '6.9.2-ai'); // DGV6.90 AI Edition — triggers AI schema migrations
    $current_mig_v = $_SESSION['migrations_completed_version'] ?? '0';

    // Global Migration Check to avoid redundant checks for new visitors
    if ($current_mig_v !== SYSTEM_VERSION) {
        $q_mig = mysqli_query($connection_server, "SELECT option_value FROM sas_super_admin_options WHERE option_name='system_migration_version' LIMIT 1");
        $global_mig_v = ($q_mig && $r_mig = mysqli_fetch_assoc($q_mig)) ? $r_mig['option_value'] : '0';
        if ($global_mig_v === SYSTEM_VERSION) {
            $_SESSION['migrations_completed_version'] = SYSTEM_VERSION;
            $current_mig_v = SYSTEM_VERSION;
        }
    }

    if ($current_mig_v !== SYSTEM_VERSION) {
        include_once(__DIR__ . "/bc-tables.php");

        // Migration: Add val_4 to parameter value tables
        $param_tables = array("sas_smart_parameter_values", "sas_agent_parameter_values", "sas_api_parameter_values", "sas_smart_card_funding_parameter_values", "sas_agent_card_funding_parameter_values", "sas_api_card_funding_parameter_values", "sas_smart_card_transaction_parameter_values", "sas_agent_card_transaction_parameter_values", "sas_api_card_transaction_parameter_values");
        foreach ($param_tables as $table) {
            $check_val4 = mysqli_query($connection_server, "SHOW COLUMNS FROM `$table` LIKE 'val_4'");
            if (mysqli_num_rows($check_val4) == 0) {
                mysqli_query($connection_server, "ALTER TABLE `$table` ADD `val_4` VARCHAR(225) AFTER `val_3` ");
            }
        }

        // Migration: Brute force is_enabled
        $check_bf_enabled = mysqli_query($connection_server, "SHOW COLUMNS FROM `sas_bruteforce_settings` LIKE 'is_enabled'");
        if (mysqli_num_rows($check_bf_enabled) == 0) {
            mysqli_query($connection_server, "ALTER TABLE `sas_bruteforce_settings` ADD `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER vendor_id");
        }

        // Migration: Create sas_unblock_requests if missing
        mysqli_query($connection_server, "CREATE TABLE IF NOT EXISTS sas_unblock_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT,
            username VARCHAR(255),
            ip_address VARCHAR(255),
            reason TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Migration: API Requests Domain column
        $res_req = mysqli_query($connection_server, "SHOW COLUMNS FROM `sas_api_requests` LIKE 'api_domain'");
        if (mysqli_num_rows($res_req) == 0) {
            mysqli_query($connection_server, "ALTER TABLE `sas_api_requests` ADD COLUMN `api_domain` VARCHAR(255) AFTER username");
        }

        // Migration: Biometric Auth Table
        mysqli_query($connection_server, "CREATE TABLE IF NOT EXISTS sas_user_biometrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            credential_id TEXT,
            public_key TEXT,
            sign_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id)
        )");

        // Migration: Payment Checkouts Table
        mysqli_query($connection_server, "CREATE TABLE IF NOT EXISTS sas_user_payment_checkouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT,
            username VARCHAR(255),
            reference VARCHAR(255),
            status INT DEFAULT 0,
            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (reference),
            INDEX (vendor_id)
        )");

        // Migration: Crypto Wallet Table Schema Fix (Index limit)
        $check_crypto_wallet_schema = mysqli_query($connection_server, "SHOW COLUMNS FROM `sas_user_crypto_wallets` LIKE 'username'");
        if (mysqli_num_rows($check_crypto_wallet_schema) > 0) {
            $row = mysqli_fetch_assoc($check_crypto_wallet_schema);
            if ($row['Type'] == 'varchar(225)') {
                mysqli_query($connection_server, "ALTER TABLE `sas_user_crypto_wallets` MODIFY `username` VARCHAR(100) NOT NULL");
                // Ensure unique key exists
                $check_idx = mysqli_query($connection_server, "SHOW INDEX FROM sas_user_crypto_wallets WHERE Key_name = 'user_currency'");
                if (mysqli_num_rows($check_idx) == 0) {
                    mysqli_query($connection_server, "ALTER TABLE sas_user_crypto_wallets ADD UNIQUE KEY user_currency (vendor_id, username, currency_code)");
                }
            }
        }

        // Migration: Security & Lockout columns for existing installations
        $kyc_cols_users = array(
            "kyc_status" => "INT DEFAULT 0",
            "liveliness_video" => "VARCHAR(255)",
            "liveliness_picture" => "VARCHAR(255)",
            "govt_id_card" => "VARCHAR(255)",
            "kyc_id_type" => "VARCHAR(100)",
            "kyc_id_image" => "VARCHAR(255)",
            "kyc_face_image" => "VARCHAR(255)",
            "kyc_id_ok" => "TINYINT DEFAULT 0",
            "kyc_picture_ok" => "TINYINT DEFAULT 0",
            "kyc_video_ok" => "TINYINT DEFAULT 0",
            "kyc_approved_date" => "DATETIME NULL",
            "kyc_id_expiry" => "DATE NULL",
            "kyc_refresh_required" => "TINYINT DEFAULT 0",
            "kyc_reject_reason" => "TEXT",
            "proof_of_address" => "VARCHAR(255)",
            "kyc_address_ok" => "TINYINT DEFAULT 0"
        );

        // Migration: EPIN Plan Pricing tiers
        $check_price_agent = mysqli_query($connection_server, "SHOW COLUMNS FROM `sas_databundle_plans` LIKE 'price_agent'");
        if (mysqli_num_rows($check_price_agent) == 0) {
            mysqli_query($connection_server, "ALTER TABLE `sas_databundle_plans` ADD `price_agent` DECIMAL(10,2) DEFAULT 0.00 AFTER `price`, ADD `price_api` DECIMAL(10,2) DEFAULT 0.00 AFTER `price_agent` ");
            // Initialize existing plans
            mysqli_query($connection_server, "UPDATE sas_databundle_plans SET price_agent = price, price_api = price");
        }

        // Comprehensive Schema Migration
        $tables_to_migrate = array("sas_users", "sas_vendors", "sas_super_admin");
        foreach ($tables_to_migrate as $table) {
            $res = mysqli_query($connection_server, "SHOW COLUMNS FROM `$table` ");
            $existing = array();
            while($r = mysqli_fetch_assoc($res)) $existing[] = $r['Field'];

            // Core Security columns for everyone
            $core = array(
                "security_pin" => "VARCHAR(255)",
                "is_blocked" => "TINYINT(1) DEFAULT 0",
                "failed_login_count" => "INT DEFAULT 0",
                "last_failed_login" => "TIMESTAMP NULL",
                "failed_pin_count" => "INT DEFAULT 0",
                "last_failed_pin" => "TIMESTAMP NULL"
            );

            if ($table === 'sas_vendors' || $table === 'sas_super_admin') {
                $core["smtp_host"] = "VARCHAR(255)";
                $core["smtp_user"] = "VARCHAR(255)";
                $core["smtp_pass"] = "VARCHAR(255)";
                $core["smtp_port"] = "VARCHAR(10)";
                $core["smtp_sec"] = "VARCHAR(10)";
            }

            if ($table === 'sas_vendors') {
                $core["print_hub_secret"] = "VARCHAR(255)";
                $core["force_security_pin"] = "INT DEFAULT 0";
                $core["force_2fa"] = "INT DEFAULT 0";
                $core["force_google_sso"] = "INT DEFAULT 0";
                $core["google_client_id"] = "VARCHAR(255)";
                $core["force_kyc"] = "INT DEFAULT 0";
            }

            foreach($core as $c => $d) {
                if(!in_array($c, $existing)) {
                    mysqli_query($connection_server, "ALTER TABLE `$table` ADD COLUMN `$c` $d");
                }
            }

            if ($table === 'sas_users') {
                foreach ($kyc_cols_users as $col => $def) {
                    if (!in_array($col, $existing)) {
                        mysqli_query($connection_server, "ALTER TABLE `$table` ADD COLUMN `$col` $def");
                    }
                }

                // Migration: API Domain whitelisting
                if (!in_array('api_domain', $existing)) {
                    mysqli_query($connection_server, "ALTER TABLE `sas_users` ADD COLUMN `api_domain` VARCHAR(255)");
                }

                // Migration: 4-digit PIN for Mobile App
                if (!in_array('pin', $existing)) {
                    mysqli_query($connection_server, "ALTER TABLE `sas_users` ADD COLUMN `pin` VARCHAR(4) DEFAULT NULL");
                }
            }
        }

        // Migration: Sync existing transaction_pins to security_pin (hashed) for users
        $check_unfilled_pins = mysqli_query($connection_server, "SELECT id, transaction_pin FROM sas_users WHERE (security_pin IS NULL OR security_pin = '') AND transaction_pin IS NOT NULL AND transaction_pin != '' LIMIT 100");
        while($pin_row = mysqli_fetch_assoc($check_unfilled_pins)){
            $h_pin = password_hash($pin_row['transaction_pin'], PASSWORD_DEFAULT);
            mysqli_query($connection_server, "UPDATE sas_users SET security_pin='$h_pin' WHERE id='".$pin_row['id']."'");
        }

        // Migration: Optimization indexes for purchase tracker
        $check_idx = mysqli_query($connection_server, "SHOW INDEX FROM sas_daily_purchase_tracker WHERE Key_name = 'idx_tracker_lookup'");
        if (mysqli_num_rows($check_idx) == 0) {
            mysqli_query($connection_server, "ALTER TABLE sas_daily_purchase_tracker ADD INDEX idx_tracker_lookup (vendor_id, username, product_id, product_type, date_purchased)");
        }

        $check_idx_v = mysqli_query($connection_server, "SHOW INDEX FROM sas_transactions WHERE Key_name = 'idx_vendor_lookup'");
        if (mysqli_num_rows($check_idx_v) == 0) {
            mysqli_query($connection_server, "ALTER TABLE sas_transactions ADD INDEX idx_vendor_lookup (vendor_id, status, type_alternative)");
        }

        $check_idx_c = mysqli_query($connection_server, "SHOW INDEX FROM sas_conversions WHERE Key_name = 'idx_conv_lookup'");
        if (mysqli_num_rows($check_idx_c) == 0) {
            mysqli_query($connection_server, "ALTER TABLE sas_conversions ADD INDEX idx_conv_lookup (vendor_id, username, status)");
        }

        // Migration: Ensure default KYC config for all vendors (0 = Disabled by default)
        $kyc_defaults = array("bvn", "nin", "liveliness_video", "liveliness_picture", "govt_id", "proof_of_address");
        $vendors_q = mysqli_query($connection_server, "SELECT id FROM sas_vendors");
        while($v_row = mysqli_fetch_assoc($vendors_q)){
            $v_id = $v_row['id'];
            foreach($kyc_defaults as $kd){
                mysqli_query($connection_server, "INSERT IGNORE INTO sas_kyc_verifications (vendor_id, verification_name, status) VALUES ('$v_id', '$kd', '0')");
            }
        }

        mysqli_query($connection_server, "INSERT INTO sas_super_admin_options (option_name, option_value) VALUES ('system_migration_version', '".SYSTEM_VERSION."') ON DUPLICATE KEY UPDATE option_value='".SYSTEM_VERSION."'");
        $_SESSION['migrations_completed_version'] = SYSTEM_VERSION;
    }

    // Per-vendor template seeding must happen if not already done in this session
    // (We don't want to skip this globally because each vendor needs their own rows)
    $vendor_id_for_seed = resolveVendorID();
    $seed_key = 'templates_seeded_v' . $vendor_id_for_seed;
    if (!isset($_SESSION[$seed_key])) {
        include_once(__DIR__ . "/bc-email-templates.php");
        $_SESSION[$seed_key] = true;
    }

	// Optimization: Combined super admin and vendor check (Throttled fetching)
	$vendor_id = resolveVendorID();
	$select_vendor_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vendor_id' AND status=1 LIMIT 1"));

	if ($select_vendor_table) {
        seedVendorBlog($vendor_id);
		unset($_SESSION["admin_to_user_redirect"]);

		// Check for vendor expiry
		if ($select_vendor_table["expiry_date"] && strtotime($select_vendor_table["expiry_date"]) < time()) {
			if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/web/Inactive.php", "/web/Login.php", "/logout.php", "/admin-logout.php", "/bc-admin/Login.php", "/bc-admin/RenewSubscription.php", "/bc-spadmin/VerifyOTP.php", "/web/LockoutResolution.php"))) {
				header("Location: /web/Inactive.php");
				exit();
			}
		}

		if (isset($_SESSION["user_session"])) {
			$username = mysqli_real_escape_string($connection_server, $_SESSION["user_session"]);

            // Global Session Enforcement for Blocks
            if (isIPBlocked($_SERVER['REMOTE_ADDR'], $vendor_id) || isAccountLocked($username, $vendor_id)) {
                if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/web/LockoutResolution.php", "/web/ajax-unblock-request.php"))) {
                    session_destroy();
                    // Don't redirect if already on a public page
                    if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/web/APIDocs.php", "/blog.php", "/single-post.php"))) {
                        header("Location: /web/Login.php");
                        exit();
                    }
                }
            }

			$get_logged_user_query = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' AND username='$username' LIMIT 1");

			if (mysqli_num_rows($get_logged_user_query) == 1) {
				$get_logged_user_details = mysqli_fetch_array($get_logged_user_query);

				// Check for KYC Expiry
				if ($get_logged_user_details['kyc_status'] == 2 && !empty($get_logged_user_details['kyc_id_expiry'])) {
					if (strtotime($get_logged_user_details['kyc_id_expiry']) < time()) {
						// ID Expired! Reset status and flag for refresh
						mysqli_query($connection_server, "UPDATE sas_users SET kyc_status=0, kyc_refresh_required=1, kyc_id_ok=0 WHERE id='".$get_logged_user_details['id']."'");
						$get_logged_user_details['kyc_status'] = 0;
						$get_logged_user_details['kyc_refresh_required'] = 1;
						$get_logged_user_details['kyc_id_ok'] = 0;
					}
				}

				if (($get_logged_user_details["status"] == 1 && $get_logged_user_details["is_blocked"] == 0) || isset($_SESSION["admin_session"])) {
					// Branch DG6.7 Optimization: Cache billing status to prevent heavy JOIN queries on every request
                    $last_billing_check = $_SESSION['last_billing_check_time'] ?? 0;
                    $is_suspended = $_SESSION['vendor_suspended_cache'] ?? false;

                    if (time() - $last_billing_check > 3600 || isset($_SESSION['force_billing_recheck'])) {
                        $reg_date = $select_vendor_table["reg_date"];
                        $billing_check = mysqli_query($connection_server, "SELECT b.ending_date FROM sas_vendor_billings b LEFT JOIN sas_vendor_paid_bills p ON b.id = p.bill_id AND p.vendor_id = '$vendor_id' WHERE b.date >= '$reg_date' AND p.id IS NULL AND b.ending_date < CURDATE() LIMIT 1");
                        $is_suspended = (mysqli_num_rows($billing_check) > 0);

                        $_SESSION['vendor_suspended_cache'] = $is_suspended;
                        $_SESSION['last_billing_check_time'] = time();
                        unset($_SESSION['force_billing_recheck']);
                    }

					if ($is_suspended) {
						header("Location: /web/Suspended.php");
						exit();
					}

					// Master KYC Toggle Check (Super Admin Global OR Vendor Local)
					$master_force_kyc = isKYCEnforced($vendor_id);

					// Optimization: KYC check combined (Branch DG6.7: Session cached)
					if (!isset($_SESSION['kyc_data_cache']) || !isset($_SESSION['kyc_data_vid']) || $_SESSION['kyc_data_vid'] != $vendor_id) {
						$kyc_data = [];
						$kyc_res = mysqli_query($connection_server, "SELECT verification_name, status FROM sas_kyc_verifications WHERE vendor_id='$vendor_id'");
						while($krow = mysqli_fetch_assoc($kyc_res)) $kyc_data[$krow['verification_name']] = (int)$krow['status'];
						$_SESSION['kyc_data_cache'] = $kyc_data;
						$_SESSION['kyc_data_vid'] = $vendor_id;
					} else {
						$kyc_data = $_SESSION['kyc_data_cache'];
					}

					$needs_bvn = $master_force_kyc && (isset($kyc_data['bvn']) && $kyc_data['bvn'] == 1) && empty($get_logged_user_details['bvn']);
					$needs_nin = $master_force_kyc && (isset($kyc_data['nin']) && $kyc_data['nin'] == 1) && empty($get_logged_user_details['nin']);

					if ($needs_bvn || $needs_nin) {
						$fields = [];
						if($needs_bvn) $fields[] = "BVN";
						if($needs_nin) $fields[] = "NIN";
						$_SESSION["product_purchase_response"] = "Dear " . ucwords($get_logged_user_details["firstname"]) . ", please provide your " . implode(" and ", $fields) . " securely to comply with regulations.";
						if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/web/AccountSettings.php", "/web/Fund.php", "/web/SubmitPayment.php", "/web/PaymentOrders.php", "/web/KYCVerification.php"))) {
							header("Location: /web/AccountSettings.php");
							exit();
						}
					}

					// Media KYC Enforcement (Video, Picture, Govt ID, Proof of Address)
					$needs_media_kyc = $master_force_kyc && (
						((isset($kyc_data['liveliness_video']) && $kyc_data['liveliness_video'] == 1)) ||
						((isset($kyc_data['liveliness_picture']) && $kyc_data['liveliness_picture'] == 1)) ||
						((isset($kyc_data['govt_id']) && $kyc_data['govt_id'] == 1)) ||
						((isset($kyc_data['proof_of_address']) && $kyc_data['proof_of_address'] == 1))
					) && ((int)$get_logged_user_details["kyc_status"] != 2);

					$current_p = explode("?", trim($_SERVER["REQUEST_URI"]))[0];
					$sensitive_pages = array("/web/SendFund.php", "/web/VirtualBanks.php", "/web/ShareFund.php", "/web/CryptoHub.php");

					// Always require KYC for sensitive pages regardless of global force_kyc for security
					if ($master_force_kyc && (int)$get_logged_user_details["kyc_status"] != 2 && in_array($current_p, $sensitive_pages)) {
						// For VirtualBanks.php, we show a friendly "Start KYC" button inside the page
						// For others, we redirect to force verification
						if ($current_p != "/web/VirtualBanks.php") {
							$_SESSION["product_purchase_response"] = "Action Restricted: You must complete your KYC Verification to perform high-security transactions.";
							header("Location: /web/KYCVerification.php");
							exit();
						}
					}

					if ($needs_media_kyc) {
						$kyc_enforced_pages = array(
							"/web/Airtime.php", "/web/Data.php", "/web/Cable.php", "/web/Electric.php",
							"/web/Exam.php", "/web/Betting.php", "/web/BulkAirtime.php", "/web/BulkData.php",
							"/web/BulkSMS.php", "/web/PrintHub.php", "/web/DataBundleCard.php", "/web/VirtualCard.php",
							"/web/SendFund.php", "/web/VirtualBanks.php", "/web/ShareFund.php",
							"/web/GiftCard.php", "/web/CoinConversion.php", "/web/Card.php"
						);
						if (in_array($current_p, $kyc_enforced_pages)) {
							$_SESSION["product_purchase_response"] = "Action Restricted: You must complete your KYC Verification (Video/Picture/ID) to perform transactions.";
							header("Location: /web/KYCVerification.php");
							exit();
						}
					}

					if (!($needs_bvn || $needs_nin)) {
						// Minimum funding check - only if not already verified
						$is_verified = (isset($kyc_data['bvn']) && $kyc_data['bvn'] == 2) || (isset($kyc_data['nin']) && $kyc_data['nin'] == 2);
						if(!$is_verified) {
							$min_funding_q = mysqli_query($connection_server, "SELECT min_amount FROM sas_user_minimum_funding WHERE vendor_id='$vendor_id' LIMIT 1");
							$min_funding = ($row_mf = mysqli_fetch_assoc($min_funding_q)) ? (float)$row_mf['min_amount'] : 0;

							if ($min_funding > 0) {
								// Branch DG6.7 Optimization: Cache total funding to prevent heavy SUM queries on every request
								if (!isset($_SESSION['total_funding_cache']) || (time() - ($_SESSION['total_funding_time'] ?? 0) > 600)) {
									$stmt_funding = mysqli_prepare($connection_server, "SELECT SUM(discounted_amount) as total FROM sas_transactions WHERE vendor_id = ? AND username = ? AND status=1 AND (type_alternative LIKE '%credit%' OR type_alternative LIKE '%received%' OR type_alternative LIKE '%commission%')");
									mysqli_stmt_bind_param($stmt_funding, "is", $vendor_id, $get_logged_user_details["username"]);
									mysqli_stmt_execute($stmt_funding);
									$config_user_total_funding = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_funding))['total'] ?? 0;
									$_SESSION['total_funding_cache'] = $config_user_total_funding;
									$_SESSION['total_funding_time'] = time();
								} else {
									$config_user_total_funding = $_SESSION['total_funding_cache'];
								}

								if ($config_user_total_funding < $min_funding) {
									$_SESSION["product_purchase_response"] = "Dear " . ucwords($get_logged_user_details["firstname"]) . ", kindly fund your wallet with minimum of N" . number_format($min_funding - $config_user_total_funding) . " to unlock features.";
									if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/web/Fund.php", "/web/SubmitPayment.php", "/web/PaymentOrders.php", "/web/Dashboard.php"))) {
										header("Location: /web/Fund.php");
										exit();
									}
								}
							}
						}
					}

					// Security Question Check
					if (!isset($_COOKIE["security_answer"]) || $_COOKIE["security_answer"] != $get_logged_user_details["security_answer"]) {
						if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/web/SecurityQuest.php", "/web/Login.php", "/web/Register.php", "/web/PasswordRecovery.php", "/web/PayRequest.php", "/web/ajax-unblock-request.php", "/web/LockoutResolution.php", "/web/KYCVerification.php"))) {
							header("Location: /web/SecurityQuest.php");
							exit();
						}
					}


					// Update last login (Branch DG6.7 Optimization: Throttled update)
					if (!isset($_SESSION['last_login_update']) || (time() - $_SESSION['last_login_update'] > 900)) {
						mysqli_query($connection_server, "UPDATE sas_users SET last_login = NOW() WHERE id = '".$get_logged_user_details['id']."'");
						$_SESSION['last_login_update'] = time();
					}
				} else {
					header("Location: /logout.php");
					exit();
				}
			} else {
				header("Location: /logout.php");
				exit();
			}
		} else {
			// Not logged in
		$current_uri = explode("?", trim($_SERVER["REQUEST_URI"]))[0];
		$public_pages = array("/web/Login.php", "/web/Register.php", "/web/PasswordRecovery.php", "/web/PayRequest.php", "/web/ajax-unblock-request.php", "/web/Inactive.php", "/web/LockoutResolution.php", "/web/APIDocs.php", "/blog.php", "/single-post.php", "/web/biometric-ajax.php", "/manifest.php", "/web/ViewCryptoInvoice.php");

		$is_public_page = false;
		foreach($public_pages as $page) {
			if (substr($current_uri, -strlen($page)) === $page) {
				$is_public_page = true;
				break;
			}
		}

		if (!$is_public_page) {
			$redirecturl = trim($_SERVER["REQUEST_URI"]);
			header("Location: /web/Login.php" . (!empty($redirecturl) ? "?redirecturl=" . urlencode($redirecturl) : ""));
			exit();
		}
		}
		if (!isset($_SESSION['site_details_cache']) || $_SESSION['site_details_vid'] != $vendor_id) {
            $get_all_site_details_query = mysqli_query($connection_server, "SELECT * FROM sas_site_details WHERE vendor_id='$vendor_id' LIMIT 1");
            $get_all_site_details = $get_all_site_details_query ? mysqli_fetch_array($get_all_site_details_query) : null;
            $_SESSION['site_details_cache'] = $get_all_site_details;
            $_SESSION['site_details_vid'] = $vendor_id;
        } else {
            $get_all_site_details = $_SESSION['site_details_cache'];
        }

	} else {
		header("Location: /web/Error.php");
		exit();
	}
} else {
	//If Database Is Having Issue
	if (!in_array(explode("?", trim($_SERVER["REQUEST_URI"]))[0], array("/index.php"))) {
		header("Location: /index.php");
		exit();
	}
}

//CSS Template Update
$css_style_template_location = "/cssfile/template/bc-style-template-1.css";
$vendor_primary_color = "#287bff";
$select_vendor_style_template = mysqli_query($connection_server, "SELECT * FROM sas_vendor_style_templates WHERE vendor_id='" . $select_vendor_table["id"] . "'");
if (mysqli_num_rows($select_vendor_style_template) == 1) {
	$get_vendor_style_template = mysqli_fetch_array($select_vendor_style_template);
	$style_template_name = $get_vendor_style_template["template_name"];
	if (!empty($style_template_name)) {
		$style_template_location = "/cssfile/template/" . $style_template_name;
		if (file_exists($_SERVER['DOCUMENT_ROOT'] . $style_template_location)) {
			$css_style_template_location = $style_template_location;
		}
	}
	$vendor_primary_color = $get_vendor_style_template["primary_color"] ?? "#287bff";
}

//Service Provider ID Array
$mtn_carrier_id_array = array("803", "702", "703", "704", "903", "806", "706", "707", "813", "810", "814", "816", "906", "916", "913", "903");
$airtel_carrier_id_array = array("701", "708", "802", "808", "812", "901", "902", "904", "907", "911", "912");
$glo_carrier_id_array = array("805", "705", "905", "807", "815", "811", "915");
$etisalat_carrier_id_array = array("809", "817", "818", "908", "909");
