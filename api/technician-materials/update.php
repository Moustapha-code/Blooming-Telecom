<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['usage_id'] ?? 0);

try {
    $stmt = $pdo->prepare('
        UPDATE technician_materials SET technician_id = ?, material_id = ?, date_given = ?, quantity_given = ?, quantity_returned = ?, car_id = ?, zone = ?, notes = ? 
        WHERE usage_id = ?
    ');
    
    $stmt->execute([
        $data['technician_id'],
        $data['material_id'],
        $data['date_given'] ?: date('Y-m-d'),
        $data['quantity_given'] ?: 0,
        $data['quantity_returned'] ?: 0,
        $data['car_id'] ?: NULL,
        $data['zone'] ?: '',
        $data['notes'] ?: '',
        $id
    ]);

    echo json_encode(['message' => 'Updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
