<?php
// Prevent direct access
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    exit('❌ Direct access not allowed.');
}

// Database credentials — move to .env in production
$host = 'localhost';
$db   = 'pharmacy';
$user = 'root';
$pass = ''; // ← Update for production! Use strong password.
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'", // UTC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("DB Connection Failed: " . $e->getMessage());
    die("<h1>🏥 Service Temporarily Unavailable</h1><p>We're performing maintenance. Please try again later.</p>");
}
?>