<?php
/**
 * DGV6.90 Titanium Setup — Database Migrations
 */
include_once("func/bc-connect.php");

echo "<h1>Titanium AI Setup</h1>";

$queries = [
    "ALTER TABLE sas_transactions ADD COLUMN IF NOT EXISTS ai_sentinel_processed TINYINT(1) DEFAULT 0",
    "ALTER TABLE sas_users ADD COLUMN IF NOT EXISTS ai_budget_opt_in TINYINT(1) DEFAULT 0",
    "CREATE TABLE IF NOT EXISTS sas_ai_market_health (
        id INT AUTO_INCREMENT PRIMARY KEY,
        network VARCHAR(50),
        provider_id INT,
        success_rate DECIMAL(5,2),
        latency_ms INT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $sql) {
    if (mysqli_query($connection_server, $sql)) {
        echo "<p style='color:green'>Success: $sql</p>";
    } else {
        echo "<p style='color:red'>Error: " . mysqli_error($connection_server) . " ($sql)</p>";
    }
}

echo "<h2>Setup Complete!</h2>";
?>
