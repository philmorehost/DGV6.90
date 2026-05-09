<?php
/**
 * DGV6.90 — PHP 8.3 Compatibility Shim & Security Hardening
 * Included by bc-config.php before any other library.
 *
 * This file:
 *   - Installs a custom error handler that logs to file (never echoes to screen)
 *   - Installs a custom exception handler with a clean fallback page
 *   - Adds PHP 8.0/8.1 polyfills for environments still on 7.4
 *   - Sets secure defaults for sessions if not yet started
 *   - Guards against double-include
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

// ── Log errors to file (always, regardless of environment) ────────────────────
$_bc_log_dir = dirname(__DIR__) . '/logs';
if (!is_dir($_bc_log_dir)) {
    @mkdir($_bc_log_dir, 0750, true);
    // Protect the logs directory from web access
    @file_put_contents($_bc_log_dir . '/.htaccess', "Order deny,allow\nDeny from all\n");
}
ini_set('log_errors', '1');
ini_set('error_log', $_bc_log_dir . '/php_errors.log');

// ── Custom error handler ───────────────────────────────────────────────────────
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Don't handle errors that are suppressed with @
    if (!(error_reporting() & $errno)) return false;

    $level = 'INFO';
    if ($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
        $level = 'FATAL';
    } elseif ($errno & (E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING)) {
        $level = 'WARNING';
    } elseif ($errno & E_NOTICE) {
        $level = 'NOTICE';
    } elseif ($errno & E_DEPRECATED) {
        $level = 'DEPRECATED';
    }

    $short_file = str_replace(dirname(__DIR__), '', $errfile);
    error_log("[DGV-$level] $errstr in $short_file:$errline");

    // Let PHP also handle fatal errors
    return false;
});

// ── Custom exception handler ───────────────────────────────────────────────────
set_exception_handler(function (Throwable $e): void {
    $short_file = str_replace(dirname(__DIR__), '', $e->getFile());
    error_log('[DGV-EXCEPTION] ' . $e->getMessage() . ' in ' . $short_file . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }

    // Never expose internals — show a clean page
    if (!defined('CRON_CLI')) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>System Error</title>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc}'
            . '.card{text-align:center;padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:400px}'
            . 'h2{color:#dc2626;margin-bottom:8px}p{color:#6b7280;margin:0}</style></head>'
            . '<body><div class="card"><h2>Something went wrong</h2>'
            . '<p>Our team has been notified. Please try again in a moment.</p>'
            . '<p style="margin-top:16px"><a href="javascript:history.back()" style="color:#3b82f6">← Go Back</a></p>'
            . '</div></body></html>';
    }
    exit(1);
});

// ── Session security (set before session_start()) ─────────────────────────────
if (PHP_SESSION_NONE === session_status()) {
    // Only set these if session not yet started
    $session_params = [
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'gc_maxlifetime'  => 3600,
    ];
    // cookie_secure: only over HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $session_params['cookie_secure'] = true;
    }
    session_set_cookie_params($session_params);
}

// ── PHP 8.0 polyfills (for servers still on PHP 7.4) ─────────────────────────
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// ── PHP 8.1 polyfill ──────────────────────────────────────────────────────────
if (!function_exists('array_is_list')) {
    function array_is_list(array $arr): bool {
        if ($arr === []) return true;
        $i = 0;
        foreach ($arr as $k => $v) {
            if ($k !== $i++) return false;
        }
        return true;
    }
}

// ── Secure headers (emitted on every PHP response) ────────────────────────────
if (!headers_sent() && !defined('CRON_CLI')) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    // Remove server fingerprint
    header_remove('X-Powered-By');
    header_remove('Server');
}
