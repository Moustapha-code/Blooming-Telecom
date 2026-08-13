<?php
require '../../config/session.php';
require '../../config/database.php';
requireLogin();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
try {
    $stmt = $pdo->prepare('INSERT INTO car_maintenance (car_id, date_maintenance, description, cost) VALUES (?, ?, ?, ?)');
    $stmt->execute([$data['car_id'], $data['date_maintenance'], $data['description'], $data['cost']]);
    echo json_encode(['message' => 'Maintenance record created']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
