<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$name = sanitize($data['name'] ?? '');

if (!$name) {
    http_response_code(400);
    exit(json_encode(['error' => 'Name is required']));
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO technicians (name, phone, email, specialty, zone) 
        VALUES (?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $name,
        sanitize($data['phone'] ?? ''),
        sanitize($data['email'] ?? ''),
        sanitize($data['specialty'] ?? ''),
        sanitize($data['zone'] ?? '')
    ]);

    echo json_encode(['message' => 'Technician created successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
