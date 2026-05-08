<?php
/**
 * DGV6.90 — Secure Database Credentials
 * PHP 8.2+ Readonly Class Pattern
 *
 * Priority order for credentials:
 *   1. Environment variables (set via server config / .env on VPS)
 *   2. Hardcoded fallback below (for cPanel shared hosting)
 *
 * On a VPS, set these env vars in /etc/environment or via PHP-FPM pool:
 *   DB_HOST=localhost
 *   DB_USER=your_db_user
 *   DB_PASS=your_db_password
 *   DB_NAME=your_db_name
 *   APP_ENV=production
 */

if (PHP_VERSION_ID < 80100) {
    // PHP 8.0 fallback: use a plain function instead of readonly class
    function _bc_db_config(): array {
        return [
            'server' => getenv('DB_HOST') ?: 'localhost',
            'user'   => getenv('DB_USER') ?: 'v8data_vendor',
            'pass'   => getenv('DB_PASS') ?: '1122@EBEN.COM',
            'dbname' => getenv('DB_NAME') ?: 'v8data_vendor',
        ];
    }
    $db_json_decode = _bc_db_config();
} else {
    /**
     * Readonly class — values assigned once, cannot be mutated.
     * PHP 8.2+: readonly class modifier ensures all properties are readonly by default.
     */
    final class DbConfig {
        public readonly string $server;
        public readonly string $user;
        public readonly string $pass;
        public readonly string $dbname;
        public readonly string $app_env;

        public function __construct() {
            $this->server  = (string)(getenv('DB_HOST') ?: 'localhost');
            $this->user    = (string)(getenv('DB_USER') ?: 'v8data_vendor');
            $this->pass    = (string)(getenv('DB_PASS') ?: '1122@EBEN.COM');
            $this->dbname  = (string)(getenv('DB_NAME') ?: 'v8data_vendor');
            $this->app_env = (string)(getenv('APP_ENV') ?: 'production');
        }

        /** Returns array format for backward compatibility with db-dtl.php */
        public function toArray(): array {
            return [
                'server' => $this->server,
                'user'   => $this->user,
                'pass'   => $this->pass,
                'dbname' => $this->dbname,
            ];
        }
    }

    $db_json_decode = (new DbConfig())->toArray();
}

// Legacy variable kept for strict backward compatibility (nothing should break)
$db_json_dtls   = $db_json_decode;
$db_json_encode = json_encode($db_json_decode, JSON_THROW_ON_ERROR);