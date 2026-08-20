<?php
/**
 * Dashboard Home Page with KPIs - Modern Redesign
 */

require 'config/session.php';
require 'config/database.php';
require 'config/helpers.php';
require 'components/layout.php';

requireLogin();

// --- Période affichée ---
// Sans paramètre dans l'URL, le tableau de bord s'ouvre sur les 3
// derniers jours. Les compteurs liés aux OT suivent cette période ;
// l'effectif et le stock, eux, décrivent un état courant et restent
// globaux.
[$dateFrom, $dateTo] = resolveDateRange('date_from', 'date_to');

$periodWhere  = [];
$periodParams = [];
if ($dateFrom !== '') {
    $periodWhere[]  = 'date_intervention >= ?';
    $periodParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $periodWhere[]  = 'date_intervention <= ?';
    $periodParams[] = $dateTo;
}
$periodSql = $periodWhere ? 'WHERE ' . implode(' AND ', $periodWhere) : '';

/** Compte les OT de la période, avec une condition supplémentaire éventuelle. */
$countOTs = function (?string $extra = null) use ($pdo, $periodWhere, $periodParams): int {
    $clauses = $periodWhere;
    if ($extra !== null) {
        $clauses[] = $extra;
    }
    $sql = 'SELECT COUNT(*) FROM installations'
         . ($clauses ? ' WHERE ' . implode(' AND ', $clauses) : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($periodParams);
    return (int) $stmt->fetchColumn();
};

// --- KPI Queries ---

// 1. Technicians Count (effectif courant, hors période)
$techCount = $pdo->query("SELECT COUNT(*) FROM technicians")->fetchColumn();

// 2. OT de la période
$totalOTs = $countOTs();

// 3. Réalisés sur la période
$realizedToday = $countOTs("etat = 'realise'");

// 4. Pending / In Progress
// Deux orthographes du même état coexistent en base ('encoure', 'en cours')
$pendingOTs = $countOTs("etat IN ('encoure', 'en cours')");

// 5. Stock Alerts (état courant, hors période)
$lowStock = $pdo->query("SELECT COUNT(*) FROM materials WHERE stock_quantity < 10")->fetchColumn();

// 6. Derniers OT de la période
$stmt = $pdo->prepare("
    SELECT i.*, t.name as technician_name
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    " . ($periodWhere ? 'WHERE ' . implode(' AND ', array_map(fn($c) => "i.$c", $periodWhere)) : '') . "
    ORDER BY i.date_intervention DESC, i.id DESC LIMIT 5
");
$stmt->execute($periodParams);
$recentOTs = $stmt->fetchAll();

// 7. OT Stats for Chart (Simple summary by zone)
$stmt = $pdo->prepare("SELECT zone, COUNT(*) as count FROM installations $periodSql GROUP BY zone");
$stmt->execute($periodParams);
$statsByZone = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/modern-dashboard.css?v=<?php echo filemtime('assets/css/modern-dashboard.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php require_once __DIR__ . '/components/pwa.php'; renderPwaHead(); ?>
</head>

<body>
    <div class="app-container">
        <?php renderSidebar('index.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <!-- 1. Header Section -->
                <div class="page-header items-center">
                    <div>
                        <h2 class="page-title">Bonjour, <?php echo htmlspecialchars($_SESSION['admin_username']); ?> 👋
                        </h2>
                        <p class="page-subtitle">Voici un aperçu de l'activité de Blooming Telecom.</p>
                    </div>
                    <div class="flex gap-4">
                        <a href="pages/installations.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> Nouvel OT
                        </a>
                    </div>
                </div>

                <!-- Période : 3 derniers jours par défaut -->
                <div class="card mb-6" style="padding: 16px 20px;">
                    <form method="GET" class="flex items-end gap-4 flex-wrap">
                        <div class="form-group mb-0">
                            <label>Date Début</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label>Date Fin</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-filter"></i> Appliquer
                        </button>
                        <a href="?date_from=&date_to=" class="btn btn-secondary">
                            <i class="fa-solid fa-infinity"></i> Tout l'historique
                        </a>
                        <span class="text-xs text-muted" style="margin-left: auto;">
                            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                                Du <strong><?php echo htmlspecialchars($dateFrom !== '' ? formatDate($dateFrom) : '—'); ?></strong>
                                au <strong><?php echo htmlspecialchars($dateTo !== '' ? formatDate($dateTo) : '—'); ?></strong>
                            <?php else: ?>
                                <strong>Tout l'historique</strong>
                            <?php endif; ?>
                            &bull; <strong><?php echo $totalOTs; ?></strong> OT
                        </span>
                    </form>
                </div>

                <!-- 2. KPI Section (Clean 4-column grid) -->
                <div class="kpi-grid mb-8">
                    <div class="kpi-card">
                        <div class="flex justify-between items-start mb-4">
                            <div class="kpi-icon blue">
                                <i class="fa-solid fa-users"></i>
                            </div>
                        </div>
                        <div class="kpi-label">Techniciens</div>
                        <div class="kpi-value"><?php echo $techCount; ?></div>
                    </div>

                    <div class="kpi-card">
                        <div class="flex justify-between items-start mb-4">
                            <div class="kpi-icon green">
                                <i class="fa-solid fa-check-double"></i>
                            </div>
                            <span class="badge badge-info">Période</span>
                        </div>
                        <div class="kpi-label">Réalisés</div>
                        <div class="kpi-value"><?php echo $realizedToday; ?></div>
                    </div>

                    <div class="kpi-card">
                        <div class="flex justify-between items-start mb-4">
                            <div class="kpi-icon orange">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                        </div>
                        <div class="kpi-label">En Attente</div>
                        <div class="kpi-value"><?php echo $pendingOTs; ?></div>
                    </div>

                    <div class="kpi-card">
                        <div class="flex justify-between items-start mb-4">
                            <div class="kpi-icon red">
                                <i class="fa-solid fa-box-open"></i>
                            </div>
                            <?php if ($lowStock > 0): ?>
                                <span class="badge badge-danger"><?php echo $lowStock; ?> Alertes</span>
                            <?php endif; ?>
                        </div>
                        <div class="kpi-label">Stock Critique</div>
                        <div class="kpi-value <?php echo $lowStock > 0 ? 'text-danger' : ''; ?>">
                            <?php echo $lowStock; ?></div>
                    </div>
                </div>

                <!-- 3. Middle Section: Chart (Full Width) -->
                <div class="mb-8">
                    <!-- Distribution Chart -->
                    <div class="card">
                        <h3 class="section-title">Répartition des OT par Zones</h3>
                        <div style="height: 350px; position: relative;">
                            <canvas id="otDistributionChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 4. Bottom Section: Recent Activities (Table stays as requested) -->
                <div class="card-table">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="section-title mb-0">Dernières Installations (OT)</h3>
                        <a href="pages/installations.php" class="text-sm font-bold text-primary">Voir toutes les
                            installations <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Zone</th>
                                    <th>Nature</th>
                                    <th>Statut</th>
                                    <th>GEPON</th>
                                    <th>Technicien</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOTs as $ot): ?>
                                    <tr class="table-row">
                                        <td class="font-bold text-muted">#<?php echo htmlspecialchars($ot['id']); ?></td>
                                        <td><?php echo formatDate($ot['date_intervention']); ?></td>
                                        <td>
                                            <div class="font-bold"><?php echo htmlspecialchars(substr($ot['nom'] ?? '-', 0, 25)); ?></div>
                                            <div class="text-xs text-muted">ID: <?php echo htmlspecialchars($ot['numero_client']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($ot['zone']); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($ot['nature_ot']); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadgeClass($ot['etat']); ?>">
                                                <i class="fa-solid fa-circle"></i> <?php echo getStatusBadgeText($ot['etat']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code class="text-xs px-2 py-1 bg-surface-2 rounded border border-border">
                                                <?php echo htmlspecialchars($ot['Gepon'] ?: '-'); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="avatar-xs" style="width: 20px; height: 20px; font-size: 0.6rem;">
                                                    <?php echo $ot['technician_name'] ? strtoupper(substr($ot['technician_name'], 0, 1)) : '?'; ?>
                                                </div>
                                                <span class="text-sm"><?php echo htmlspecialchars($ot['technician_name'] ?: 'Non affecté'); ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('otDistributionChart').getContext('2d');
            const stats = <?php echo json_encode($statsByZone); ?>;
            const labels = Object.keys(stats);
            const data = Object.values(stats);

            // Generate colors for zones
            const colors = [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
                '#06b6d4', '#f43f5e', '#14b8a6', '#f97316', '#6366f1'
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, labels.length),
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: getComputedStyle(document.body).getPropertyValue('--text-muted').trim(),
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 11 }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        });
    </script>
</body>

</html>