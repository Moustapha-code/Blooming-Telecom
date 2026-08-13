<?php
/**
 * Database connection configuration - EXAMPLE
 *
 * Copy this file to database.php and fill in your credentials:
 *   cp config/database.example.php config/database.php
 *
 * config/database.php is gitignored so real credentials never reach git.
 */

$host = 'localhost';
$db = 'bloowing_db';
$user = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}
?>
