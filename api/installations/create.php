<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    // nom et port ne sont plus saisis dans le formulaire : ils restent NULL
    // à la création et peuvent toujours arriver par l'import CSV.
    $stmt = $pdo->prepare('
        INSERT INTO installations (date_intervention, temp_de_venir, numero_client, zone, Gepon, scan, etat, nature_ot, technician_id, commentaire)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $data['date_intervention'] ?: date('Y-m-d'),
        $data['temp_de_venir'] ?: null,
        intOrNull($data['numero_client'] ?? null),
        $data['zone'] ?: '',
        $data['Gepon'] ?: '',
        $data['scan'] ?: NULL,
        normalizeEtat($data['etat'] ?? null),
        $data['nature_ot'] ?: '',
        intOrNull($data['technician_id'] ?? null),
        $data['commentaire'] ?? NULL
    ]);

    echo json_encode(['message' => 'Created']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
