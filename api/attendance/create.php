<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $stmt = $pdo->prepare('
        INSERT INTO attendance (technician_id, date, check_in_time, check_out_time, status, notes) 
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        intOrNull($data['technician_id'] ?? null),
        $data['date'] ?: NULL,
        $data['check_in_time'] ?: NULL,
        $data['check_out_time'] ?: NULL,
        $data['status'],
        $data['notes'] ?: ''
    ]);

    echo json_encode(['message' => 'Record created']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
