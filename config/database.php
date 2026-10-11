<?php
// config/database.php
require_once __DIR__ . '/constants.php';

$db_host = '127.0.0.1';
$db_name = 'basa_library';
$db_user = 'root';
$db_pass = '';

if (!extension_loaded('pdo_mysql') || !class_exists('PDO')) {
    die('PDO MySQL extension is not enabled. Enable the "pdo_mysql" extension in php.ini.');
}

try {
    $pdo_class = 'PDO';
    $pdo = new $pdo_class(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            constant('PDO::ATTR_ERRMODE')            => constant('PDO::ERRMODE_EXCEPTION'),
            constant('PDO::ATTR_DEFAULT_FETCH_MODE') => constant('PDO::FETCH_ASSOC'),
            constant('PDO::ATTR_EMULATE_PREPARES')   => false,
        ]
    );
    $GLOBALS['pdo'] = $pdo;
} catch (\Throwable $e) {
    die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}