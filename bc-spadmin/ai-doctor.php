<?php
header('Content-Type: text/plain');
echo "OS: " . PHP_OS_FAMILY . "\n";
echo "User: " . get_current_user() . " (UID: " . getmyuid() . ")\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Disabled Functions: " . ini_get('disable_functions') . "\n";

echo "\nChecking Ollama Path:\n";
echo "where ollama (Windows): " . shell_exec('where ollama 2>&1') . "\n";
echo "which ollama (Linux): " . shell_exec('which ollama 2>&1') . "\n";

echo "\nChecking Ollama Status:\n";
echo "ollama --version: " . shell_exec('ollama --version 2>&1') . "\n";

echo "\nTemp Dir: " . sys_get_temp_dir() . "\n";
$startup_log = sys_get_temp_dir() . '/ollama_startup.log';
if (file_exists($startup_log)) {
    echo "\nStartup Log Content:\n";
    echo file_get_contents($startup_log);
} else {
    echo "\nStartup log does not exist.\n";
}
?>
