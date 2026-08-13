<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['id'] ?? 0);

try {
    $stmt = $pdo->prepare('DELETE FROM installations WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['message' => 'Deleted']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
