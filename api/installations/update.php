<?php
require '../../config/session.php';
require '../../config/database.php';
require '../../config/helpers.php';

requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int)($data['id'] ?? 0);

try {
    // État actuel, nécessaire pour la règle de clôture (voir computeClotureStamp)
    $before = $pdo->prepare('SELECT etat, date_realise, temp_de_realise FROM installations WHERE id = ?');
    $before->execute([$id]);
    $current = $before->fetch();

    $newEtat = $data['etat'] ?: 'encoure';
    $cloture = $current
        ? computeClotureStamp($current['etat'], $newEtat, $current['date_realise'], $current['temp_de_realise'])
        : null;

    $sql = 'UPDATE installations SET date_intervention = ?, nom = ?, numero_client = ?, port = ?, zone = ?, Gepon = ?, scan = ?, etat = ?, nature_ot = ?, technician_id = ?, commentaire_temp_de_realise = ?, commentaire = ?';
    $params = [
        $data['date_intervention'] ?: date('Y-m-d'),
        $data['nom'],
        $data['numero_client'] ?: '',
        $data['port'] ?: '',
        $data['zone'] ?: '',
        $data['Gepon'] ?: '',
        $data['scan'] ?: NULL,
        $newEtat,
        $data['nature_ot'] ?: '',
        $data['technician_id'] ?: NULL,
        $data['commentaire_temp_de_realise'] ?: '',
        $data['commentaire'] ?? NULL,
    ];

    if ($cloture !== null) {
        $sql .= ', date_de_cloture = ?, temp_de_cloture = ?';
        $params[] = $cloture[0];
        $params[] = $cloture[1];
    }

    $sql .= ' WHERE id = ?';
    $params[] = $id;

    $pdo->prepare($sql)->execute($params);

    echo json_encode(['message' => 'Updated']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
