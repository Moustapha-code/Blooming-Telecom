<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['car_id'] ?? 0);

try {
    $stmt = $pdo->prepare('
        UPDATE car SET matricule = ?, brand = ?, model = ?, driver_id = ?, notes = ? 
        WHERE car_id = ?
    ');
    
    $stmt->execute([
        $data['matricule'],
        $data['brand'] ?: '',
        $data['model'] ?: '',
        $data['driver_id'] ?: NULL,
        $data['notes'] ?: '',
        $id
    ]);

    echo json_encode(['message' => 'Updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
