<?php
/**
 * Update Admin User API
 */

require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!isset($data['id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'User ID is required']));
}

$id = (int)$data['id'];
$username = sanitize($data['username'] ?? '');
$password = $data['password'] ?? '';

try {
    // Check if username is taken by another user
    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ?');
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Username already exists']));
    }

    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE admin_users SET username = ?, password = ? WHERE id = ?');
        $stmt->execute([$username, $hashedPassword, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE admin_users SET username = ? WHERE id = ?');
        $stmt->execute([$username, $id]);
    }

    echo json_encode(['message' => 'Admin user updated successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
