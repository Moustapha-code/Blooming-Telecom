<?php
/**
 * Export Installations to Excel / CSV Endpoint
 */

require '../../config/session.php';
require '../../config/database.php';

requireLogin();

// Retrieve requested months & columns
$months = $_POST['months'] ?? $_GET['months'] ?? [];
$columns = $_POST['columns'] ?? $_GET['columns'] ?? [];

if (is_string($months)) {
    $months = explode(',', $months);
}
if (is_string($columns)) {
    $columns = explode(',', $columns);
}

$months = array_values(array_filter(array_map('trim', (array)$months)));
$columns = array_values(array_filter(array_map('trim', (array)$columns)));

// Available columns mapping (key => header label)
$availableColumns = [
    'id' => 'ID OT',
    'date_intervention' => 'Date Intervention',
    'temp_de_venir' => 'Heure / Temps de venir',
    'nom' => 'Nom Client',
    'numero_client' => 'Numéro Client',
    'zone' => 'Zone',
    'nature_ot' => 'Nature OT',
    'port' => 'Port',
    'Gepon' => 'GEPON',
    'scan' => 'Statut Scan',
    'etat' => 'Statut (État)',
    'technician_name' => 'Technicien',
    'date_realise' => 'Date Réalisation',
    'date_de_cloture' => 'Date Clôture',
    'commentaire' => 'Motif / Commentaire Retard',
    'commentaire_temp_de_realise' => 'Commentaires Générales'
];

// If no columns selected, default to all available columns
if (empty($columns)) {
    $columns = array_keys($availableColumns);
} else {
    // Filter to valid keys only
    $columns = array_values(array_intersect($columns, array_keys($availableColumns)));
}

// Build SQL query WHERE clause for months
$where = [];
$params = [];

if (!empty($months) && !in_array('ALL', $months, true)) {
    $placeholders = [];
    foreach ($months as $idx => $m) {
        $pName = ":m_" . $idx;
        $placeholders[] = "DATE_FORMAT(i.date_intervention, '%Y-%m') = {$pName}";
        $params[$pName] = $m;
    }
    $where[] = '(' . implode(' OR ', $placeholders) . ')';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Fetch data
$sql = "
    SELECT 
        i.id,
        i.date_intervention,
        i.temp_de_venir,
        i.nom,
        i.numero_client,
        i.zone,
        i.nature_ot,
        i.port,
        i.Gepon,
        i.scan,
        i.etat,
        i.date_realise,
        i.date_de_cloture,
        i.commentaire,
        i.commentaire_temp_de_realise,
        COALESCE(t.name, CONCAT('Tech #', i.technician_id)) AS technician_name
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    $whereSql
    ORDER BY i.date_intervention DESC, i.id DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for Excel CSV download
    $filename = "installations_export_" . date('Y-m-d_His') . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Output UTF-8 BOM for Microsoft Excel compatibility
    fprintf($output, "\xEF\xBB\xBF");

    // Output header row
    $headerRow = [];
    foreach ($columns as $colKey) {
        $headerRow[] = $availableColumns[$colKey] ?? $colKey;
    }
    fputcsv($output, $headerRow, ';');

    // Output data rows
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $colKey) {
            $val = $row[$colKey] ?? '';
            // Format state labels nicely
            if ($colKey === 'etat') {
                if ($val === 'realise') $val = 'Réalisé';
                elseif ($val === 'encoure') $val = 'En cours';
                elseif ($val === 'retard') $val = 'En retard';
                elseif ($val === 'negative') $val = 'Négatif';
            }
            $line[] = $val;
        }
        fputcsv($output, $line, ';');
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo "Erreur d'exportation: " . $e->getMessage();
    exit;
}
