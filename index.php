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

// --- Analyses de la période -------------------------------------------
// Mêmes axes que la page Analyse OT, restreints à la période affichée.
$periodClause = $periodWhere
    ? 'WHERE ' . implode(' AND ', array_map(fn($c) => "i.$c", $periodWhere))
    : '';

/** Agrégation sur la période. */
$aggregate = function (string $select, string $tail, string $join = '') use ($pdo, $periodClause, $periodParams): array {
    $stmt = $pdo->prepare("SELECT $select FROM installations i $join $periodClause $tail");
    $stmt->execute($periodParams);
    return $stmt->fetchAll();
};

$byDate = $aggregate(
    'i.date_intervention, COUNT(*) AS total',
    'GROUP BY i.date_intervention ORDER BY i.date_intervention'
);

$byEtat = $aggregate('i.etat, COUNT(*) AS total', 'GROUP BY i.etat');

$byNature = $aggregate(
    'i.nature_ot, COUNT(*) AS total',
    "GROUP BY i.nature_ot HAVING i.nature_ot IS NOT NULL AND i.nature_ot <> '' ORDER BY total DESC"
);

// Le détail nature par nature écrase la lecture : DRG domine et les
// autres codes se réduisent à des barres d'une unité. On oppose donc le
// DRG au groupe Installations, en gardant « Autres » pour le reliquat
// afin que le total reste celui de la période.
$installNatures = array_map('strtoupper', natureGroups()['grp_installations']['natures']);
$natureBuckets  = ['DRG' => 0, 'Installations' => 0, 'Autres' => 0];
foreach ($byNature as $row) {
    $code  = strtoupper(trim((string) $row['nature_ot']));
    $count = (int) $row['total'];
    if ($code === 'DRG') {
        $natureBuckets['DRG'] += $count;
    } elseif (in_array($code, $installNatures, true)) {
        $natureBuckets['Installations'] += $count;
    } else {
        $natureBuckets['Autres'] += $count;
    }
}
$natureBuckets = array_filter($natureBuckets, fn($v) => $v > 0);

$byZoneChart = $aggregate(
    'i.zone, COUNT(*) AS total',
    'GROUP BY i.zone ORDER BY total DESC'
);

$byTech = $aggregate(
    "COALESCE(t.name, 'Non affecté') AS technician_name, COUNT(*) AS total",
    'GROUP BY technician_name ORDER BY total DESC',
    'LEFT JOIN technicians t ON i.technician_id = t.technician_id'
);

$byScan = $aggregate(
    "COALESCE(i.scan, 'Non scanné') AS scan_status, COUNT(*) AS total",
    'GROUP BY scan_status'
);

// Les états sont regroupés sur leur forme canonique : la base contient
// plusieurs orthographes d'un même état (voir normalizeEtat).
$etatChartData = [];
foreach ($byEtat as $row) {
    $key = normalizeEtat($row['etat']);
    $etatChartData[$key] = ($etatChartData[$key] ?? 0) + (int) $row['total'];
}

$dashboardCharts = [
    'byDate'   => $byDate,
    'byEtat'   => $etatChartData,
    'byNature' => $natureBuckets,
    'byZone'   => $byZoneChart,
    'byTech'   => $byTech,
    'byScan'   => $byScan,
];

// Titre de la section, fidèle à la période réellement affichée.
if ($dateFrom !== '' && $dateTo !== '') {
    $spanDays = (int) floor((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1;
    $periodTitle = $spanDays === DEFAULT_PERIOD_DAYS && $dateTo === date('Y-m-d')
        ? 'Analyse des ' . DEFAULT_PERIOD_DAYS . ' derniers jours'
        : "Analyse du " . formatDate($dateFrom) . " au " . formatDate($dateTo);
} else {
    $periodTitle = "Analyse de tout l'historique";
}
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
    <script src="assets/js/chart-labels.js?v=<?php echo filemtime(__DIR__ . '/assets/js/chart-labels.js'); ?>"></script>
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

                <!-- 3. Analyses de la période -->
                <div class="mb-8">
                    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
                        <h2 class="page-title" style="font-size: 1.35rem; margin: 0;">
                            <i class="fa-solid fa-chart-simple mr-2 text-primary"></i>
                            <?php echo htmlspecialchars($periodTitle); ?>
                        </h2>
                        <a href="pages/OT_analyse.php" class="text-sm font-bold text-primary">
                            Analyse détaillée <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <?php if ($totalOTs === 0): ?>
                        <div class="card text-center p-8 text-muted">
                            <i class="fa-solid fa-circle-info mr-2"></i>
                            Aucun OT sur cette période.
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="card">
                                <h3 class="section-title">OT par Jour</h3>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="dashByDate"></canvas>
                                </div>
                            </div>
                            <div class="card">
                                <h3 class="section-title">Répartition par État</h3>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="dashByEtat"></canvas>
                                </div>
                            </div>
                            <div class="card">
                                <h3 class="section-title">Répartition des OT par Zones</h3>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="otDistributionChart"></canvas>
                                </div>
                            </div>
                            <div class="card">
                                <h3 class="section-title">DRG / Installations</h3>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="dashByNature"></canvas>
                                </div>
                            </div>
                            <div class="card">
                                <h3 class="section-title">OT par Technicien</h3>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="dashByTech"></canvas>
                                </div>
                            </div>
                            <div class="card">
                                <h3 class="section-title">Répartition par Scan</h3>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="dashByScan"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
            const zoneCanvas = document.getElementById('otDistributionChart');
            if (!zoneCanvas) return; // période sans OT : aucun graphique rendu
            const ctx = zoneCanvas.getContext('2d');
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

    <script>
    // Analyses de la période : mêmes axes que la page Analyse OT.
    document.addEventListener('DOMContentLoaded', function () {
        const data = <?php echo json_encode($dashboardCharts); ?>;
        const muted = getComputedStyle(document.body).getPropertyValue('--text-muted').trim();

        // Couleurs par état, mappées par valeur : la requête renvoie les
        // états dans un ordre arbitraire, un tableau positionnel
        // attribuerait les couleurs au hasard.
        const STATUS_COLORS = {
            realise:  'rgba(34, 197, 94, 0.9)',
            encoure:  'rgba(234, 179, 8, 0.9)',
            retard:   'rgba(239, 68, 68, 0.9)',
            negative: 'rgba(148, 163, 184, 0.9)',
        };
        const STATUS_LABELS = {
            realise: 'Réalisé', encoure: 'En cours',
            retard: 'En retard', negative: 'Négatif',
        };

        const palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                         '#06b6d4', '#f43f5e', '#14b8a6', '#f97316', '#6366f1'];

        const legendBottom = {
            legend: { position: 'bottom', labels: { color: muted, usePointStyle: true, padding: 16, font: { size: 11 } } }
        };
        const noLegend = { legend: { display: false } };
        const gridOpts = {
            x: { ticks: { color: muted, font: { size: 10 } }, grid: { display: false } },
            y: { beginAtZero: true, ticks: { color: muted, precision: 0 }, grid: { color: 'rgba(148,163,184,.15)' } }
        };

        function make(id, config) {
            const el = document.getElementById(id);
            if (el) new Chart(el.getContext('2d'), config);
        }

        // 1) OT par jour
        make('dashByDate', {
            type: 'line',
            data: {
                labels: data.byDate.map(r => r.date_intervention),
                datasets: [{
                    label: 'OT', data: data.byDate.map(r => Number(r.total)),
                    borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.15)',
                    borderWidth: 2, fill: true, tension: .3, pointRadius: 3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: noLegend, scales: gridOpts }
        });

        // 2) Répartition par état
        const etatKeys = Object.keys(data.byEtat);
        make('dashByEtat', {
            type: 'doughnut',
            data: {
                labels: etatKeys.map(k => STATUS_LABELS[k] || k),
                datasets: [{
                    data: etatKeys.map(k => data.byEtat[k]),
                    backgroundColor: etatKeys.map(k => STATUS_COLORS[k] || 'rgba(148,163,184,.9)'),
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: legendBottom, cutout: '65%' }
        });

        // 3) DRG contre le groupe Installations
        const natureKeys = Object.keys(data.byNature);
        const NATURE_COLORS = {
            'DRG':           'rgba(239, 68, 68, .8)',   // dérangements
            'Installations': 'rgba(59, 130, 246, .8)',
            'Autres':        'rgba(148, 163, 184, .8)',
        };
        make('dashByNature', {
            type: 'bar',
            data: {
                labels: natureKeys,
                datasets: [{
                    label: 'OT',
                    data: natureKeys.map(k => Number(data.byNature[k])),
                    backgroundColor: natureKeys.map(k => NATURE_COLORS[k] || 'rgba(148,163,184,.8)'),
                    borderRadius: 6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: noLegend, scales: gridOpts }
        });

        // 4) OT par technicien
        make('dashByTech', {
            type: 'bar',
            data: {
                labels: data.byTech.map(r => r.technician_name),
                datasets: [{
                    label: 'OT', data: data.byTech.map(r => Number(r.total)),
                    backgroundColor: 'rgba(16,185,129,.8)', borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: noLegend,
                scales: {
                    x: { beginAtZero: true, ticks: { color: muted, precision: 0 }, grid: { color: 'rgba(148,163,184,.15)' } },
                    y: { ticks: { color: muted, font: { size: 10 } }, grid: { display: false } }
                }
            }
        });

        // 5) Répartition par scan
        make('dashByScan', {
            type: 'pie',
            data: {
                labels: data.byScan.map(r => r.scan_status),
                datasets: [{
                    data: data.byScan.map(r => Number(r.total)),
                    backgroundColor: data.byScan.map((r, i) =>
                        /non/i.test(r.scan_status) ? 'rgba(239,68,68,.8)' : 'rgba(34,197,94,.8)'),
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: legendBottom }
        });
    });
    </script>
</body>

</html>