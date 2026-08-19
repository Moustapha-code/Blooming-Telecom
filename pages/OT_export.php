<?php
/**
 * Export Installations Page - Select Months & Columns
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

// Fetch distinct months with counts from database
$sqlMonths = "
    SELECT 
        DATE_FORMAT(date_intervention, '%Y-%m') AS month_key,
        YEAR(date_intervention) AS annee,
        MONTH(date_intervention) AS mois_num,
        COUNT(*) AS total_count
    FROM installations
    WHERE date_intervention IS NOT NULL
    GROUP BY month_key, annee, mois_num
    ORDER BY month_key DESC
";
$monthsData = $pdo->query($sqlMonths)->fetchAll();

$monthFullNames = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

// Available columns list with human readable labels and categories
$columnsList = [
    'id' => ['label' => 'ID OT', 'desc' => 'Identifiant unique de l\'intervention', 'default' => true],
    'date_intervention' => ['label' => 'Date Intervention', 'desc' => 'Date planifiée de l\'OT', 'default' => true],
    'nom' => ['label' => 'Nom Client', 'desc' => 'Nom et prénom du client', 'default' => true],
    'numero_client' => ['label' => 'Numéro Client', 'desc' => 'Identifiant client', 'default' => true],
    'zone' => ['label' => 'Zone', 'desc' => 'Zone géographique', 'default' => true],
    'nature_ot' => ['label' => 'Nature OT', 'desc' => 'Type d\'intervention (DRG, CST, CPL...)', 'default' => true],
    'etat' => ['label' => 'Statut / État', 'desc' => 'Réalisé, En cours, En retard, Négatif', 'default' => true],
    'Gepon' => ['label' => 'GEPON', 'desc' => 'Référence GEPON', 'default' => true],
    'port' => ['label' => 'Port', 'desc' => 'Numéro de port', 'default' => false],
    'scan' => ['label' => 'Statut Scan', 'desc' => 'Scanné / Non scanné', 'default' => true],
    'technician_name' => ['label' => 'Technicien', 'desc' => 'Nom du technicien affecté', 'default' => true],
    'commentaire' => ['label' => 'Commentaire Retard', 'desc' => 'Motif/Résultat du retard (DRG & CPL)', 'default' => true],
    'date_realise' => ['label' => 'Date Réalisation', 'desc' => 'Date effective de réalisation', 'default' => true],
    'date_de_cloture' => ['label' => 'Date Clôture', 'desc' => 'Date de clôture finale', 'default' => false],
    'temp_de_venir' => ['label' => 'Heure / Temps Venir', 'desc' => 'Heure de venue planifiée', 'default' => false],
    'commentaire_temp_de_realise' => ['label' => 'Commentaires', 'desc' => 'Notes et commentaires', 'default' => false],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportation Excel OT - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <style>
        .selection-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 24px;
        }
        .month-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
        .checkbox-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }
        .checkbox-pill:hover {
            border-color: var(--primary);
            background: var(--primary-soft);
        }
        .checkbox-pill input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }
        .column-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }
        .column-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
        }
        .column-card:hover {
            border-color: var(--primary);
        }
        .column-card input {
            margin-top: 3px;
        }
    </style>
    <?php require_once __DIR__ . '/../components/pwa.php'; renderPwaHead(); ?>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('OT_export.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h2 class="page-title">
                            <i class="fa-solid fa-file-excel text-green-600 mr-2"></i>
                            Exportation Excel des OT
                        </h2>
                        <p class="page-subtitle">Personnalisez votre rapport en sélectionnant les mois et les colonnes de votre choix.</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="installations.php" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Retour aux OT
                        </a>
                        <a href="monthly_analyse.php" class="btn btn-secondary">
                            <i class="fa-solid fa-chart-line"></i> Analyse Mensuelle
                        </a>
                    </div>
                </div>

                <form action="../api/installations/export.php" method="POST" id="exportForm">
                    <!-- SECTION 1: SELECTION DES MOIS -->
                    <div class="selection-card">
                        <div class="flex justify-between items-center flex-wrap gap-3 border-bottom pb-4 mb-4">
                            <div>
                                <h3 class="section-title mb-1">
                                    <i class="fa-solid fa-calendar-check text-primary mr-2"></i>
                                    1. Sélection des Mois
                                </h3>
                                <p class="text-sm text-muted">Cochez les mois que vous souhaitez inclure dans le fichier Excel.</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllMonths(true)">
                                    <i class="fa-solid fa-check-double"></i> Tout Sélectionner
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectPresetMonths('latest')">
                                    Dernier Mois
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectPresetMonths('last3')">
                                    3 Derniers Mois
                                </button>
                                <button type="button" class="btn btn-sm" style="background: transparent; border: 1px solid var(--border);" onclick="selectAllMonths(false)">
                                    Désélectionner Tout
                                </button>
                            </div>
                        </div>

                        <div class="month-grid">
                            <?php foreach ($monthsData as $idx => $m): 
                                $mLabel = ($monthFullNames[(int)$m['mois_num']] ?? 'Mois ' . $m['mois_num']) . ' ' . $m['annee'];
                            ?>
                                <label class="checkbox-pill">
                                    <input type="checkbox" name="months[]" value="<?php echo $m['month_key']; ?>" class="month-checkbox" checked onchange="updateSummaryCounter()">
                                    <div>
                                        <div class="font-bold text-sm"><?php echo htmlspecialchars($mLabel); ?></div>
                                        <div class="text-xs text-muted"><?php echo number_format($m['total_count']); ?> OT</div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SECTION 2: SELECTION DES COLONNES -->
                    <div class="selection-card">
                        <div class="flex justify-between items-center flex-wrap gap-3 border-bottom pb-4 mb-4">
                            <div>
                                <h3 class="section-title mb-1">
                                    <i class="fa-solid fa-table-columns text-primary mr-2"></i>
                                    2. Sélection des Colonnes
                                </h3>
                                <p class="text-sm text-muted">Choisissez quelles informations doivent apparaître dans votre fichier Excel.</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllColumns(true)">
                                    <i class="fa-solid fa-check-double"></i> Tout Sélectionner
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectPresetColumns('essential')">
                                    Colonnes Essentielles
                                </button>
                                <button type="button" class="btn btn-sm" style="background: transparent; border: 1px solid var(--border);" onclick="selectAllColumns(false)">
                                    Désélectionner Tout
                                </button>
                            </div>
                        </div>

                        <div class="column-grid">
                            <?php foreach ($columnsList as $key => $col): ?>
                                <label class="column-card">
                                    <input type="checkbox" name="columns[]" value="<?php echo $key; ?>" class="col-checkbox" <?php echo $col['default'] ? 'checked' : ''; ?> onchange="updateSummaryCounter()">
                                    <div>
                                        <div class="font-bold text-sm"><?php echo htmlspecialchars($col['label']); ?></div>
                                        <div class="text-xs text-muted"><?php echo htmlspecialchars($col['desc']); ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SUMMARY & SUBMIT BAR -->
                    <div class="card p-6 flex justify-between items-center flex-wrap gap-4" style="background: var(--primary-soft); border: 2px solid var(--primary);">
                        <div>
                            <h4 class="font-extrabold text-lg text-primary mb-1">Prêt pour l'exportation</h4>
                            <p class="text-sm text-muted">
                                <span id="selectedMonthCount" class="font-bold text-primary">0</span> mois sélectionné(s) — 
                                <span id="selectedColCount" class="font-bold text-primary">0</span> colonnes choisies.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 1rem;">
                                <i class="fa-solid fa-file-excel mr-2"></i> Télécharger le Fichier Excel (.CSV)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function selectAllMonths(checked) {
            document.querySelectorAll('.month-checkbox').forEach(cb => cb.checked = checked);
            updateSummaryCounter();
        }

        function selectPresetMonths(preset) {
            const checkboxes = Array.from(document.querySelectorAll('.month-checkbox'));
            checkboxes.forEach(cb => cb.checked = false);

            if (preset === 'latest' && checkboxes.length > 0) {
                checkboxes[0].checked = true;
            } else if (preset === 'last3') {
                checkboxes.slice(0, 3).forEach(cb => cb.checked = true);
            }
            updateSummaryCounter();
        }

        function selectAllColumns(checked) {
            document.querySelectorAll('.col-checkbox').forEach(cb => cb.checked = checked);
            updateSummaryCounter();
        }

        function selectPresetColumns(preset) {
            const essentials = ['id', 'date_intervention', 'nom', 'numero_client', 'zone', 'nature_ot', 'etat', 'Gepon', 'scan', 'technician_name'];
            document.querySelectorAll('.col-checkbox').forEach(cb => {
                if (preset === 'essential') {
                    cb.checked = essentials.includes(cb.value);
                }
            });
            updateSummaryCounter();
        }

        function updateSummaryCounter() {
            const monthCount = document.querySelectorAll('.month-checkbox:checked').length;
            const colCount = document.querySelectorAll('.col-checkbox:checked').length;

            document.getElementById('selectedMonthCount').textContent = monthCount;
            document.getElementById('selectedColCount').textContent = colCount;
        }

        document.addEventListener('DOMContentLoaded', updateSummaryCounter);
    </script>
</body>
</html>
