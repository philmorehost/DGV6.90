<?php
/**
 * DGV6.90 — Secure Database Credentials
 * Backward compatible with PHP 7.4+
 */

// Use a plain function for all versions to avoid parser syntax errors with readonly keywords
function _bc_db_config_safe(): array {
    return [
        'server'  => getenv('DB_HOST') ?: 'localhost',
        'user'    => getenv('DB_USER') ?: 'v8data_vendor',
        'pass'    => getenv('DB_PASS') ?: '1122@EBEN.COM',
        'dbname'  => getenv('DB_NAME') ?: 'v8data_vendor',
        'app_env' => getenv('APP_ENV') ?: 'production',
    ];
}

$db_json_decode = _bc_db_config_safe();

// Legacy variables kept for strict backward compatibility
$db_json_dtls   = $db_json_decode;
$db_json_encode = json_encode($db_json_decode, JSON_THROW_ON_ERROR);