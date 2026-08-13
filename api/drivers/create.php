<?php
require '../../config/session.php';
require '../../config/database.php';
requireLogin();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
try {
    $stmt = $pdo->prepare('INSERT INTO driver (name, phone, license_number) VALUES (?, ?, ?)');
    $stmt->execute([$data['name'], $data['phone'] ?? '', $data['license_number'] ?? '']);
    echo json_encode(['message' => 'Driver created']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
