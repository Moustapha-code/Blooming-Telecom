<?php
require '../../config/session.php';
require '../../config/database.php';
requireLogin();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
try {
    $stmt = $pdo->prepare('DELETE FROM car_maintenance WHERE id = ?');
    $stmt->execute([$data['id']]);
    echo json_encode(['message' => 'Maintenance record deleted']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
