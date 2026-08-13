<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $stmt = $pdo->prepare('
        INSERT INTO car (matricule, brand, model, driver_id, notes) 
        VALUES (?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $data['matricule'],
        $data['brand'] ?: '',
        $data['model'] ?: '',
        $data['driver_id'] ?: NULL,
        $data['notes'] ?: ''
    ]);

    echo json_encode(['message' => 'Car added']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
