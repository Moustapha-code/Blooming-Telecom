<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$name = sanitize($data['zone_name'] ?? '');

if (!$name) {
    http_response_code(400);
    exit(json_encode(['error' => 'Zone name is required']));
}

try {
    $stmt = $pdo->prepare('INSERT INTO zones (zone_name) VALUES (?)');
    $stmt->execute([$name]);
    echo json_encode(['message' => 'Zone created']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
