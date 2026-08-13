<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['material_id'] ?? 0);

try {
    $stmt = $pdo->prepare('
        UPDATE materials SET name = ?, description = ?, unit = ?, stock_quantity = ? 
        WHERE material_id = ?
    ');
    
    $stmt->execute([
        $data['name'],
        $data['description'] ?: '',
        $data['unit'] ?: '',
        (int)($data['stock_quantity'] ?? 0),
        $id
    ]);

    echo json_encode(['message' => 'Updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
