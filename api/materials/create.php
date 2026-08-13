<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $stmt = $pdo->prepare('
        INSERT INTO materials (name, description, unit, stock_quantity) 
        VALUES (?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $data['name'],
        $data['description'] ?: '',
        $data['unit'] ?: '',
        (int)($data['stock_quantity'] ?? 0)
    ]);

    echo json_encode(['message' => 'Created']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
