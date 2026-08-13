<?php
/**
 * OT_force_status.php - Modern Redesign
 */
require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$successMsg = '';
$errorMsg = '';

function clampWorkTime(string $time): string {
    if ($time < '10:00:00') return '10:00:00';
    if ($time > '17:00:00') return '17:00:00';
    return $time;
}

function computeForcedDatetime(array $row, string $mode): array {
    $dateInterv = $row['date_intervention'];
    $timeVenir  = $row['temp_de_venir'] ?? '00:00:00';
    $nature     = trim((string)$row['nature_ot']);

    if ($timeVenir === '' || $timeVenir === null) $timeVenir = '00:00:00';
    if (strlen($timeVenir) === 5) $timeVenir .= ':00';

    $base = new DateTime($dateInterv . ' ' . $timeVenir);
    $slaHours = (strtoupper($nature) === 'DRG') ? 24 : 48;
    $deadline = (clone $base)->modify("+{$slaHours} hours");

    if ($mode === 'realise') {
        $target = (clone $deadline)->modify("-1 hour");
    } else {
        $target = (clone $deadline)->modify("+1 hour");
    }

    $time = clampWorkTime($target->format('H:i:s'));
    $date = $target->format('Y-m-d');
    return [$date, $time];
}

// Load all gepons + count
$geponRows = $pdo->query("
    SELECT Gepon, COUNT(*) AS total
    FROM installations
    WHERE Gepon IS NOT NULL AND Gepon <> ''
    GROUP BY Gepon
    ORDER BY Gepon
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $geponsRealise = $_POST['gepons_realise'] ?? [];
    $geponsRetard  = $_POST['gepons_retard'] ?? [];

    if (!is_array($geponsRealise)) $geponsRealise = [];
    if (!is_array($geponsRetard))  $geponsRetard  = [];

    $geponsRealise = array_values(array_filter(array_map('trim', $geponsRealise)));
    $geponsRetard  = array_values(array_filter(array_map('trim', $geponsRetard)));

    if (count($geponsRealise) === 0 && count($geponsRetard) === 0) {
        $errorMsg = "Veuillez sélectionner au moins un GEPON.";
    } else {
        try {
            $pdo->beginTransaction();
            $updatedRealise = 0;
            $updatedRetard  = 0;

            $stmtSel = $pdo->prepare("SELECT id, date_intervention, temp_de_venir, nature_ot FROM installations WHERE Gepon = ?");
            $stmtUpRealise = $pdo->prepare("UPDATE installations SET etat='realise', date_realise = ?, temp_de_realise = ? WHERE id = ?");
            $stmtUpRetard = $pdo->prepare("UPDATE installations SET etat='retard', date_realise = ?, temp_de_realise = ? WHERE id = ?");

            foreach ($geponsRealise as $gepon) {
                $stmtSel->execute([$gepon]);
                $rows = $stmtSel->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    [$d, $t] = computeForcedDatetime($r, 'realise');
                    $stmtUpRealise->execute([$d, $t, (int)$r['id']]);
                    $updatedRealise++;
                }
            }

            foreach ($geponsRetard as $gepon) {
                $stmtSel->execute([$gepon]);
                $rows = $stmtSel->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    [$d, $t] = computeForcedDatetime($r, 'retard');
                    $stmtUpRetard->execute([$d, $t, (int)$r['id']]);
                    $updatedRetard++;
                }
            }

            $pdo->commit();
            $successMsg = "Mise à jour terminée. ($updatedRealise réalisé(s), $updatedRetard retard(s))";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forçage Statut GEPON - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css">
    <style>
        .gepon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }
        .gepon-item {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 1rem;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            transition: all 0.2s;
        }
        .gepon-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        .gepon-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            border-top: 1px solid var(--border);
            padding-top: 0.75rem;
        }
        .action-toggle {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .action-toggle:hover {
            background: var(--surface-2);
        }
        .action-toggle.active-realise {
            background: var(--success-dim);
            color: var(--success);
            border-color: var(--success);
        }
        .action-toggle.active-retard {
            background: var(--danger-dim);
            color: var(--danger);
            border-color: var(--danger);
        }
        .sticky-actions {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--surface);
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            border-radius: 0 0 12px 12px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('installations.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Forçage Statut par GEPON</h2>
                        <p class="page-subtitle">Modifier massivement l'état des OT basés sur le code GEPON.</p>
                    </div>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success mb-6"><?php echo $successMsg; ?></div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger mb-6"><?php echo $errorMsg; ?></div>
                <?php endif; ?>

                <form method="post" id="forceForm">
                    <div class="sticky-actions">
                        <div class="flex gap-4 items-center flex-1">
                            <div class="search-box max-w-xs w-full">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="geponSearch" placeholder="Rechercher un GEPON...">
                            </div>
                            <span class="text-xs text-muted" id="selectedCount">0 GEPON sélectionnés</span>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-bolt mr-2"></i> Appliquer le forçage
                            </button>
                        </div>
                    </div>

                    <div class="gepon-grid" id="geponGrid">
                        <?php foreach ($geponRows as $row): ?>
                            <div class="gepon-item" data-gepon="<?php echo htmlspecialchars(strtolower($row['Gepon'])); ?>">
                                <div class="flex justify-between items-center">
                                    <span class="font-mono font-bold text-primary"><?php echo htmlspecialchars($row['Gepon']); ?></span>
                                    <span class="badge badge-secondary"><?php echo $row['total']; ?> OT</span>
                                </div>
                                
                                <div class="gepon-actions">
                                    <label class="action-toggle" id="label-realise-<?php echo md5($row['Gepon']); ?>">
                                        <input type="checkbox" name="gepons_realise[]" value="<?php echo htmlspecialchars($row['Gepon']); ?>" class="hidden chk-realise" onchange="updateUI(this, 'realise')">
                                        <i class="fa-solid fa-check-circle"></i> Réalisé
                                    </label>
                                    <label class="action-toggle" id="label-retard-<?php echo md5($row['Gepon']); ?>">
                                        <input type="checkbox" name="gepons_retard[]" value="<?php echo htmlspecialchars($row['Gepon']); ?>" class="hidden chk-retard" onchange="updateUI(this, 'retard')">
                                        <i class="fa-solid fa-clock"></i> Retard
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function updateUI(chk, type) {
            const labelId = (type === 'realise' ? 'label-realise-' : 'label-retard-') + btoa(chk.value).replace(/=/g, '').substring(0, 8);
            // Using a simpler ID generation for JS
        }

        // Search functionality
        const searchInput = document.getElementById('geponSearch');
        const grid = document.getElementById('geponGrid');
        const items = grid.getElementsByClassName('gepon-item');

        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            Array.from(items).forEach(item => {
                const gepon = item.getAttribute('data-gepon');
                item.style.display = gepon.includes(term) ? 'flex' : 'none';
            });
        });

        // Better UI management for checkboxes
        document.querySelectorAll('.action-toggle').forEach(label => {
            const chk = label.querySelector('input');
            const type = chk.classList.contains('chk-realise') ? 'realise' : 'retard';
            
            chk.addEventListener('change', function() {
                if (this.checked) {
                    label.classList.add(type === 'realise' ? 'active-realise' : 'active-retard');
                    // Uncheck the other one in the same group
                    const otherType = type === 'realise' ? 'retard' : 'realise';
                    const otherLabel = label.parentElement.querySelector(`.chk-${otherType}`).parentElement;
                    const otherChk = otherLabel.querySelector('input');
                    if (otherChk.checked) {
                        otherChk.checked = false;
                        otherLabel.classList.remove(otherType === 'realise' ? 'active-realise' : 'active-retard');
                    }
                } else {
                    label.classList.remove(type === 'realise' ? 'active-realise' : 'active-retard');
                }
                updateCounter();
            });
        });

        function updateCounter() {
            const count = document.querySelectorAll('input[type="checkbox"]:checked').length;
            document.getElementById('selectedCount').textContent = count + " GEPON sélectionné(s)";
        }
    </script>
</body>
</html>
