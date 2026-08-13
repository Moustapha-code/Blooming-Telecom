<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['technician_id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit(json_encode(['error' => 'ID required']));
}

try {
    $stmt = $pdo->prepare('
        UPDATE technicians SET name = ?, phone = ?, email = ?, specialty = ?, zone = ? 
        WHERE technician_id = ?
    ');
    
    $stmt->execute([
        sanitize($data['name'] ?? ''),
        sanitize($data['phone'] ?? ''),
        sanitize($data['email'] ?? ''),
        sanitize($data['specialty'] ?? ''),
        sanitize($data['zone'] ?? ''),
        $id
    ]);

    echo json_encode(['message' => 'Technician updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
