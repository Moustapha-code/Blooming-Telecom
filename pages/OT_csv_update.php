<?php
/**
 * OT_csv_update.php - Modern Redesign
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$errorMsg = '';
$successMsg = '';
$results = [];
$updatedCount = 0;
$skippedCount = 0;
$errorCount = 0;

/** Detect delimiter */
function detectDelimiter(string $line): string {
    $delims = [",", ";", "\t", "|"];
    $best = ","; $bestCount = 0;
    foreach ($delims as $d) {
        $c = substr_count($line, $d);
        if ($c > $bestCount) { $bestCount = $c; $best = $d; }
    }
    return $best;
}

/** Normalize header */
function normHeader(string $h): string {
    $h = trim($h);
    $h = mb_strtolower($h, 'UTF-8');
    $h = str_replace(["\xEF\xBB\xBF"], "", $h);
    $h = preg_replace('/\s+/', '_', $h);
    $h = str_replace(['-', ' '], '_', $h);
    return $h;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Veuillez sélectionner un fichier CSV valide.";
    } else {
        $tmpPath = $_FILES['csv_file']['tmp_name'];
        if (strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $errorMsg = "Format .csv requis.";
        } else {
            $fh = fopen($tmpPath, 'r');
            if (!$fh) {
                $errorMsg = "Impossible de lire le fichier.";
            } else {
                $firstLine = fgets($fh);
                if ($firstLine === false) {
                    $errorMsg = "Fichier vide.";
                    fclose($fh);
                } else {
                    $delimiter = detectDelimiter($firstLine);
                    rewind($fh);
                    $header = fgetcsv($fh, 0, $delimiter);
                    if (!$header) {
                        $errorMsg = "Header introuvable.";
                        fclose($fh);
                    } else {
                        $map = [];
                        foreach ($header as $idx => $name) $map[normHeader($name)] = $idx;
                        $required = ['numero_client', 'nom', 'telephone', 'gepon', 'port'];
                        $missingCols = [];
                        foreach ($required as $col) if (!array_key_exists($col, $map)) $missingCols[] = $col;

                        if ($missingCols) {
                            $errorMsg = "Colonnes manquantes: " . implode(', ', $missingCols);
                            fclose($fh);
                        } else {
                            $findStmt = $pdo->prepare("SELECT id, nom, telephone, port FROM installations WHERE numero_client = ? AND Gepon = ? ORDER BY id DESC LIMIT 1");
                            $updateStmt = $pdo->prepare("UPDATE installations SET nom = :nom, telephone = :telephone, port = :port WHERE id = :id");

                            $rowNum = 1;
                            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                                $rowNum++;
                                $numero_client = trim((string)($row[$map['numero_client']] ?? ''));
                                $nom           = trim((string)($row[$map['nom']] ?? ''));
                                $telephone     = trim((string)($row[$map['telephone']] ?? ''));
                                $gepon         = trim((string)($row[$map['gepon']] ?? ''));
                                $port          = trim((string)($row[$map['port']] ?? ''));

                                if ($numero_client === '' || $gepon === '') {
                                    $results[] = ['line' => $rowNum, 'status' => 'SKIP', 'message' => "Client ou Gepon vide"];
                                    $skippedCount++; continue;
                                }

                                try {
                                    $findStmt->execute([$numero_client, $gepon]);
                                    $found = $findStmt->fetch();

                                    if (!$found) {
                                        $results[] = ['line' => $rowNum, 'status' => 'SKIP', 'message' => "OT introuvable"];
                                        $skippedCount++; continue;
                                    }

                                    $updateStmt->execute([
                                        ':nom'       => $nom !== '' ? $nom : $found['nom'],
                                        ':telephone' => $telephone !== '' ? $telephone : $found['telephone'],
                                        ':port'      => $port !== '' ? $port : $found['port'],
                                        ':id'        => (int)$found['id'],
                                    ]);

                                    $results[] = ['line' => $rowNum, 'status' => 'OK', 'message' => "MAJ réussie (ID: {$found['id']})"];
                                    $updatedCount++;
                                } catch (Exception $e) {
                                    $results[] = ['line' => $rowNum, 'status' => 'ERROR', 'message' => "Erreur DB"];
                                    $errorCount++;
                                }
                            }
                            fclose($fh);
                            $successMsg = "Mise à jour terminée.";
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour CSV OT - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css">
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('installations.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Mise à jour via CSV</h2>
                        <p class="page-subtitle">Actualisez les informations des OT existantes (Noms, Tel, Port) par numéro client.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="col-span-1">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="section-title mb-0">Importer le fichier</h3>
                            </div>
                            <div class="p-4">
                                <div class="bg-primary-dim p-3 rounded-lg mb-4 text-xs text-primary">
                                    <i class="fa-solid fa-circle-info mr-1"></i>
                                    Format : numero_client, nom, telephone, Gepon, port
                                </div>
                                <form method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label>Fichier CSV (.csv)</label>
                                        <input type="file" name="csv_file" accept=".csv" required class="form-control">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-full">
                                        <i class="fa-solid fa-upload mr-2"></i> Mettre à jour
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php if ($updatedCount > 0 || $errorCount > 0): ?>
                            <div class="kpi-grid grid-cols-1 mt-6">
                                <div class="kpi-card">
                                    <div class="kpi-info">
                                        <span class="kpi-label">Résumé</span>
                                        <div class="flex flex-col gap-2 mt-2">
                                            <div class="flex justify-between text-sm">
                                                <span>Modifiés :</span>
                                                <span class="font-bold text-success"><?php echo $updatedCount; ?></span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span>Ignorés :</span>
                                                <span class="font-bold text-warning"><?php echo $skippedCount; ?></span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span>Erreurs :</span>
                                                <span class="font-bold text-danger"><?php echo $errorCount; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-span-2">
                        <?php if ($errorMsg): ?>
                            <div class="alert alert-danger mb-6"><?php echo $errorMsg; ?></div>
                        <?php endif; ?>

                        <?php if ($successMsg): ?>
                            <div class="alert alert-success mb-6"><?php echo $successMsg; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($results)): ?>
                            <div class="card-table">
                                <div class="card-header">
                                    <h3 class="section-title mb-0">Journal de mise à jour</h3>
                                </div>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th style="width: 80px;">Ligne</th>
                                                <th style="width: 100px;">Statut</th>
                                                <th>Message / Détails</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $r): ?>
                                                <tr class="table-row">
                                                    <td class="font-mono"><?php echo $r['line']; ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $r['status']==='OK' ? 'badge-success' : ($r['status']==='ERROR' ? 'badge-danger' : 'badge-warning'); ?>">
                                                            <?php echo $r['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-sm text-muted"><?php echo htmlspecialchars($r['message']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card p-12 text-center">
                                <i class="fa-solid fa-file-csv text-muted mb-4" style="font-size: 4rem; opacity: 0.2;"></i>
                                <p class="text-muted">Chargez un fichier pour voir les résultats du traitement ici.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
</body>
</html>
