<?php
/**
 * Delete Admin User API
 */

require '../../config/session.php';
require '../../config/database.php';

requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!isset($data['id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'User ID is required']));
}

$id = (int)$data['id'];

// Prevent deleting own account
if ($id === $_SESSION['admin_id']) {
    http_response_code(400);
    exit(json_encode(['error' => 'Cannot delete your own account']));
}

try {
    $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = ?');
    $stmt->execute([$id]);

    echo json_encode(['message' => 'Admin user deleted successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
