<?php
/**
 * Titanium Setup Migration
 * Run this once to initialize AI features.
 */
include_once('func/bc-connect.php');

function add_column_if_not_exists($table, $column, $definition) {
    global $connection_server;
    $check = mysqli_query($connection_server, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($connection_server, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "✅ Added $column to $table<br>";
    } else {
        echo "ℹ️ $column already exists in $table<br>";
    }
}

echo "<h3>Running Titanium Migrations...</h3>";

// 1. Transactions Sentinel Columns
add_column_if_not_exists('sas_transactions', 'ai_sentinel_processed', 'TINYINT(1) DEFAULT 0');

// 2. User AI Profile Columns
add_column_if_not_exists('sas_users', 'ai_budget_opt_in', 'TINYINT(1) DEFAULT 0');
add_column_if_not_exists('sas_users', 'trust_score', 'INT DEFAULT 50');
add_column_if_not_exists('sas_users', 'last_trust_update', 'TIMESTAMP NULL');
add_column_if_not_exists('sas_users', 'ai_token_balance', 'DECIMAL(20,2) DEFAULT 0.00');
add_column_if_not_exists('sas_users', 'ai_voice_status', 'INT DEFAULT 0 COMMENT "0: Disabled, 1: Guided, 2: Autonomous"');
add_column_if_not_exists('sas_users', 'ai_requests_used', 'INT DEFAULT 0');
add_column_if_not_exists('sas_users', 'ai_quota_limit', 'INT DEFAULT 1000');

// 3. AI Usage Analytics Table (Synchronized with ai-handler.php)
mysqli_query($connection_server, "CREATE TABLE IF NOT EXISTS sas_ai_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED,
    username VARCHAR(100),
    action_type VARCHAR(50),
    model_used VARCHAR(100),
    tokens_burned INT,
    cost_naira DECIMAL(10,2),
    prompt_hash VARCHAR(100),
    status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "✅ AI Transactions table ready (Sync 7.1).<br>";

// 4. Rate Limiting Table
mysqli_query($connection_server, "CREATE TABLE IF NOT EXISTS sas_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50),
    rate_key VARCHAR(100),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(action, rate_key)
)");
echo "✅ Rate Limits table ready.<br>";

echo "<h4>Titanium Phase Fully Initialized!</h4>";
