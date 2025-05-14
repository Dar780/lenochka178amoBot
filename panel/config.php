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
$db = new mysqli(
	$dbHost,
	$dbLogin,
	$dbPassword,
	$dbName
);