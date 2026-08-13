<?php
require '../../config/session.php';
require '../../config/database.php';
requireLogin();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
try {
    $stmt = $pdo->prepare('UPDATE driver SET name = ?, phone = ?, license_number = ? WHERE driver_id = ?');
    $stmt->execute([$data['name'], $data['phone'] ?? '', $data['license_number'] ?? '', $data['driver_id']]);
    echo json_encode(['message' => 'Driver updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
