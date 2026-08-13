<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['zone_id'] ?? 0);
$name = sanitize($data['zone_name'] ?? '');

try {
    $stmt = $pdo->prepare('UPDATE zones SET zone_name = ? WHERE zone_id = ?');
    $stmt->execute([$name, $id]);
    echo json_encode(['message' => 'Updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
