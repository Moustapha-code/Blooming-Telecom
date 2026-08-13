<?php
/**
 * Create Admin User API
 */

require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$username = sanitize($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    exit(json_encode(['error' => 'Username and password are required']));
}

try {
    // Check if username already exists
    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Username already exists']));
    }

    // Create new user
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password) VALUES (?, ?)');
    $stmt->execute([$username, $hashedPassword]);

    echo json_encode(['message' => 'Admin user created successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
