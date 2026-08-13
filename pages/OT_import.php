<?php
/**
 * OT_import.php - Modern Redesign
 */
require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$errorMsg = '';
$successMsg = '';
$details = [];

function parseDateToSql(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '')
        return null;
    $formats = ['j/n/Y', 'd/m/Y', 'n/j/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y', 'j-n-Y', 'Y/m/d'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime)
            return $dt->format('Y-m-d');
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Erreur lors de l'envoi du fichier.";
    } else {
        $filename = $_FILES['excel_file']['name'];
        $tmpName = $_FILES['excel_file']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $errorMsg = "Le fichier doit être au format CSV.";
        } else {
            $handle = fopen($tmpName, 'r');
            if ($handle === false) {
                $errorMsg = "Impossible d'ouvrir le fichier.";
            } else {
                $header = fgetcsv($handle, 0, ',');
                if ($header === false) {
                    $errorMsg = "Le fichier est vide.";
                } else {
                    $headerMap = [];
                    foreach ($header as $idx => $col) {
                        $col = trim($col);
                        $col = str_replace(["\xEF\xBB\xBF"], '', $col);
                        $headerMap[strtolower($col)] = $idx;
                    }

                    $required = ['date_intervention', 'temp_de_venir', 'nature_ot', 'numero_client', 'gepon', 'zone'];
                    foreach ($required as $col) {
                        if (!isset($headerMap[$col])) {
                            $errorMsg = "Colonne obligatoire manquante : <strong>{$col}</strong>";
                            break;
                        }
                    }

                    $hasEtat = isset($headerMap['etat']);
                    $hasDateCloture = isset($headerMap['date_de_cloture']);
                    $hasTechId = isset($headerMap['technician_id']) || isset($headerMap['technicien_id']);
                    $hasScan = isset($headerMap['scan']);
                    $hasCommentaire = isset($headerMap['commentaire']);

                    if ($errorMsg === '') {
                        $inserted = 0;
                        $skipped = 0;
                        $pdo->beginTransaction();
                        try {
                            $stmt = $pdo->prepare("INSERT INTO installations (date_intervention, temp_de_venir, nature_ot, numero_client, Gepon, zone, etat, date_de_cloture, technician_id, scan, commentaire) VALUES (:date_intervention, :temp_de_venir, :nature_ot, :numero_client, :Gepon, :zone, :etat, :date_de_cloture, :technician_id, :scan, :commentaire)");

                            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                                $rawDate = trim($row[$headerMap['date_intervention']] ?? '');
                                $rawTime = trim($row[$headerMap['temp_de_venir']] ?? '');
                                $nature = trim($row[$headerMap['nature_ot']] ?? '');
                                $client = trim($row[$headerMap['numero_client']] ?? '');
                                $gepon = trim($row[$headerMap['gepon']] ?? '');
                                $zone = trim($row[$headerMap['zone']] ?? '');
                                $etatRaw = $hasEtat ? trim($row[$headerMap['etat']] ?? '') : '';
                                $clotureRaw = $hasDateCloture ? trim($row[$headerMap['date_de_cloture']] ?? '') : '';
                                $techIdRaw = $hasTechId ? trim($row[$headerMap['technician_id']] ?? $row[$headerMap['technicien_id']] ?? '') : '';
                                $scanRaw = $hasScan ? trim($row[$headerMap['scan']] ?? '') : '';
                                $commentaireRaw = $hasCommentaire ? trim($row[$headerMap['commentaire']] ?? '') : '';

                                if ($rawDate === '' && $client === '' && $gepon === '') {
                                    $skipped++;
                                    continue;
                                }

                                $dateSql = parseDateToSql($rawDate);
                                $timeSql = $rawTime;
                                $etatSql = ($etatRaw !== '') ? strtolower($etatRaw) : 'encoure';
                                $dateClotureSql = parseDateToSql($clotureRaw);
                                $techIdSql = ($techIdRaw !== '') ? (int) $techIdRaw : null;
                                $scanSql = ($scanRaw !== '') ? (strtolower($scanRaw) == 'scanne' || strtolower($scanRaw) == 'scanné' ? 'Scanné' : 'Non scanné') : null;
                                $commentaireSql = ($commentaireRaw !== '') ? $commentaireRaw : null;

                                $stmt->execute([
                                    ':date_intervention' => $dateSql,
                                    ':temp_de_venir' => $timeSql,
                                    ':nature_ot' => $nature,
                                    ':numero_client' => $client,
                                    ':Gepon' => $gepon,
                                    ':zone' => $zone,
                                    ':etat' => $etatSql,
                                    ':date_de_cloture' => $dateClotureSql,
                                    ':technician_id' => $techIdSql,
                                    ':scan' => $scanSql,
                                    ':commentaire' => $commentaireSql,
                                ]);
                                $inserted++;
                            }
                            $pdo->commit();
                            $successMsg = "Import réussi : <strong>{$inserted}</strong> OT importées.";
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $errorMsg = "Erreur lors de l'import : " . $e->getMessage();
                        }
                        fclose($handle);
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
    <title>Import OT - Blooming FTTH</title>
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
                        <h2 class="page-title">Importation Massive OT</h2>
                        <p class="page-subtitle">Importez vos fichiers CSV pour créer de nouvelles installations dans la
                            base.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="col-span-1">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="section-title mb-0">Sélection du fichier</h3>
                            </div>
                            <div class="p-6">
                                <form method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label>Fichier CSV</label>
                                        <input type="file" name="excel_file" accept=".csv" required
                                            class="form-control">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-full">
                                        <i class="fa-solid fa-file-import mr-2"></i> Lancer l'importation
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-2">
                        <?php if ($successMsg): ?>
                            <div class="alert alert-success mb-6"><?php echo $successMsg; ?></div>
                        <?php endif; ?>
                        <?php if ($errorMsg): ?>
                            <div class="alert alert-danger mb-6"><?php echo $errorMsg; ?></div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="section-title mb-0">Guide d'importation</h3>
                            </div>
                            <div class="p-6">
                                <div class="bg-primary-dim p-4 rounded-xl mb-6">
                                    <h4 class="font-bold text-primary mb-2">Instructions importantes :</h4>
                                    <ul class="text-sm space-y-2 opacity-80">
                                        <li>1. Exportez votre fichier Excel au format <strong>CSV (séparateur
                                                virgule)</strong>.</li>
                                        <li>2. Les en-têtes doivent être en première ligne du fichier.</li>
                                        <li>3. Assurez-vous que les colonnes obligatoires sont présentes.</li>
                                    </ul>
                                </div>

                                <div class="space-y-6">
                                    <div>
                                        <h4 class="font-bold mb-3 text-sm flex items-center">
                                            <span class="w-2 h-2 bg-primary rounded-full mr-2"></span>
                                            Colonnes obligatoires
                                        </h4>
                                        <div class="flex flex-wrap gap-2">
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">date_intervention</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">temp_de_venir</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">nature_ot</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">numero_client</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">gepon</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">zone</code>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="font-bold mb-3 text-sm flex items-center">
                                            <span class="w-2 h-2 bg-warning rounded-full mr-2"></span>
                                            Colonnes optionnelles
                                        </h4>
                                        <div class="flex flex-wrap gap-2">
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">etat</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">date_de_cloture</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">technician_id</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">scan</code>
                                            <code
                                                class="px-2 py-1 bg-surface-2 border border-border rounded text-xs">commentaire</code>
                                        </div>
                                        <p class="text-xs text-muted mt-2">Si l'état est vide, il sera défini par défaut
                                            sur <strong>"encoure"</strong>.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
</body>

</html>