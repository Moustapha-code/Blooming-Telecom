<?php
require '../../config/session.php';
require '../../config/database.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$ids  = $data['ids'] ?? [];
$scan = trim($data['scan'] ?? '');

if (empty($ids) || !is_array($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune installation sélectionnée.']);
    exit;
}

$allowedScanValues = ['Scanné', 'Non scanné'];
if (!in_array($scan, $allowedScanValues, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valeur de scan invalide.']);
    exit;
}

try {
    $cleanIds = array_map('intval', array_filter($ids, 'is_numeric'));
    if (empty($cleanIds)) {
        http_response_code(400);
        echo json_encode(['error' => 'Identifiants d\'installation invalides.']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $stmt = $pdo->prepare("UPDATE installations SET scan = ? WHERE id IN ($placeholders)");
    
    $params = array_merge([$scan], $cleanIds);
    $stmt->execute($params);

    $count = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "$count installation(s) mise(s) à jour avec succès ($scan).",
        'updated_count' => $count
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
