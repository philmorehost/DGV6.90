<?php
/**
 * DGV6.90 — PHP 8.3 Compatibility Shim & Security Hardening
 * Included by bc-config.php before any other library.
 *
 * This version is optimized for maximum compatibility (PHP 7.0+).
 */

if (defined('BC_PHP_COMPAT_LOADED')) return;
define('BC_PHP_COMPAT_LOADED', true);

// ── Environment detection ──────────────────────────────────────────────────────
$_bc_app_env = strtolower((string)(getenv('APP_ENV') ?: 'production'));
$_bc_is_dev  = ($_bc_app_env === 'development' || $_bc_app_env === 'dev');

// ── Error display: Force ON for debugging ─────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// ── Log errors to file ────────────────────────────────────────────────────────
$_bc_log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($_bc_log_dir)) {
    @mkdir($_bc_log_dir, 0750, true);
    @file_put_contents($_bc_log_dir . '/.htaccess', "Order deny,allow\nDeny from all\n");
}
ini_set('log_errors', '1');
ini_set('error_log', $_bc_log_dir . '/php_errors.log');

// ── Custom error handler (Legacy-Safe) ────────────────────────────────────────
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;

    $level = 'INFO';
    if ($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
        $level = 'FATAL';
    } elseif ($errno & (E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING)) {
        $level = 'WARNING';
    } elseif ($errno & E_NOTICE) {
        $level = 'NOTICE';
    }

    $short_file = str_replace(dirname(__DIR__), '', $errfile);
    error_log("[DGV-$level] $errstr in $short_file:$errline");
    return false;
});

// ── Custom exception handler (Legacy-Safe) ────────────────────────────────────
set_exception_handler(function ($e) {
    $short_file = str_replace(dirname(__DIR__), '', $e->getFile());
    error_log('[DGV-EXCEPTION] ' . $e->getMessage() . ' in ' . $short_file . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if (!defined('CRON_CLI')) {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>System Error</title>'
            . '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc}'
            . '.card{text-align:center;padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:400px}'
            . 'h2{color:#dc2626;margin-bottom:8px}p{color:#6b7280;margin:0}</style></head>'
            . '<body><div class="card"><h2>Something went wrong</h2>'
            . '<p>The system encountered an error. Our team has been notified.</p>'
            . '<p style="margin-top:16px"><a href="javascript:history.back()" style="color:#3b82f6">← Go Back</a></p>'
            . '</div></body></html>';
    }
    exit(1);
});

// ── Session security (Legacy-Safe Compatibility) ──────────────────────────────
if (PHP_SESSION_NONE === session_status()) {
    // PHP < 7.3 does not support array in session_set_cookie_params
    // We use the traditional signature for maximum compatibility
    $lifetime = 3600;
    $path = '/';
    $domain = '';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $httponly = true;
    
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
    }
}

// ── PHP 8.0 polyfills ────────────────────────────────────────────────────────
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// ── Secure headers ────────────────────────────────────────────────────────────
if (!headers_sent() && !defined('CRON_CLI')) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    @header_remove('X-Powered-By');
}
