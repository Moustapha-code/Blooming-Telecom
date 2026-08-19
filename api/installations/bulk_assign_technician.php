<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$ids  = $data['ids'] ?? [];

// Chaîne vide = retirer l'affectation (technicien « Non affecté »).
$technicianId = intOrNull($data['technician_id'] ?? null);

if (empty($ids) || !is_array($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune installation sélectionnée.']);
    exit;
}

$cleanIds = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
if (empty($cleanIds)) {
    http_response_code(400);
    echo json_encode(['error' => "Identifiants d'installation invalides."]);
    exit;
}

try {
    // La colonne porte une clé étrangère : un identifiant inconnu ferait
    // échouer toute la requête avec un message SQL illisible.
    $technicianName = 'Non affecté';
    if ($technicianId !== null) {
        $check = $pdo->prepare('SELECT name FROM technicians WHERE technician_id = ?');
        $check->execute([$technicianId]);
        $technicianName = $check->fetchColumn();
        if ($technicianName === false) {
            http_response_code(400);
            echo json_encode(['error' => "Ce technicien n'existe pas."]);
            exit;
        }
    }

    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $stmt = $pdo->prepare("UPDATE installations SET technician_id = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$technicianId], $cleanIds));

    $count = $stmt->rowCount();
    echo json_encode([
        'success'       => true,
        'message'       => "$count OT affecté(s) à $technicianName.",
        'updated_count' => $count,
        'technician'    => $technicianName,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
