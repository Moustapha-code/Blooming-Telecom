<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['attendance_id'] ?? 0);

try {
    $stmt = $pdo->prepare('
        UPDATE attendance SET technician_id = ?, date = ?, check_in_time = ?, check_out_time = ?, status = ?, notes = ? 
        WHERE attendance_id = ?
    ');
    
    $stmt->execute([
        $data['technician_id'],
        $data['date'],
        $data['check_in_time'] ?: NULL,
        $data['check_out_time'] ?: NULL,
        $data['status'],
        $data['notes'] ?: '',
        $id
    ]);

    echo json_encode(['message' => 'Updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
