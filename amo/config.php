<?php 

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php.log');
error_reporting(E_ALL);

// Config
date_default_timezone_set('Europe/Moscow');
set_time_limit(1200); // 20 minutes (60 * 10 * 2)

// Database
$dbHost = 'localhost';
$dbLogin = 'admin';
$dbPassword = 'Hjk54AfmS';
$dbName = 'apartments';
// Не допускаем фатала при недоступной БД: мягкое подключение
mysqli_report(MYSQLI_REPORT_OFF);

try {
    $dbTmp = @new mysqli($dbHost, $dbLogin, $dbPassword, $dbName);
    if ($dbTmp && !$dbTmp->connect_errno) {
        $dbTmp->set_charset('utf8mb4');
        $db = $dbTmp;
    } else {
        error_log("[" . date('Y-m-d H:i:s') . "] MySQL connect error: " . ($dbTmp ? $dbTmp->connect_error : 'unknown'));
        $db = null; // важно: чтобы потребители могли проверить доступность
    }
} catch (Throwable $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] MySQL connect exception: " . $e->getMessage());
    $db = null;
}