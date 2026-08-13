<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $stmt = $pdo->prepare('
        INSERT INTO installations (date_intervention, nom, numero_client, port, zone, Gepon, scan, etat, nature_ot, technician_id, commentaire) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $data['date_intervention'] ?: date('Y-m-d'),
        $data['nom'],
        $data['numero_client'] ?: '',
        $data['port'] ?: '',
        $data['zone'] ?: '',
        $data['Gepon'] ?: '',
        $data['scan'] ?: NULL,
        $data['etat'] ?: 'encoure',
        $data['nature_ot'] ?: '',
        $data['technician_id'] ?: NULL,
        $data['commentaire'] ?? NULL
    ]);

    echo json_encode(['message' => 'Created']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
