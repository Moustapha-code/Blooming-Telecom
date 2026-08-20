<?php
/**
 * OT Analysis & Statistics Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

// Read filters from GET
// Sans paramètre dans l'URL, la page s'ouvre sur les 3 derniers jours.
[$startDate, $endDate] = resolveDateRange('start_date', 'end_date');
$etatFilter = $_GET['etat'] ?? '';
$zoneFilter = $_GET['zone'] ?? '';
$techFilter = $_GET['technician_id'] ?? '';
$natureFilter = $_GET['nature_ot'] ?? '';

// ---- ÉTATS DISPONIBLES ----
// Les variantes d'écriture d'un même état (« encoure », « en cous »…)
// sont regroupées sur leur forme canonique : sinon le filtre affiche deux
// entrées « En cours » et n'en sélectionne qu'une.
$etatOptions  = []; // valeur canonique => libellé affiché
$etatVariants = []; // valeur canonique => orthographes réellement en base
foreach ($pdo->query("SELECT DISTINCT etat FROM installations WHERE etat IS NOT NULL AND etat <> ''") as $row) {
    $canonical = normalizeEtat($row['etat']);
    $etatOptions[$canonical]  = getStatusBadgeText($canonical);
    $etatVariants[$canonical][] = $row['etat'];
}
asort($etatOptions);

// Build WHERE / params (with alias i.)
$where = [];
$params = [];

if ($startDate !== '') {
    $where[] = "i.date_intervention >= :start_date";
    $params[':start_date'] = $startDate;
}
if ($endDate !== '') {
    $where[] = "i.date_intervention <= :end_date";
    $params[':end_date'] = $endDate;
}
if ($etatFilter !== '') {
    // Le filtre porte sur la valeur canonique : il faut retrouver toutes
    // les orthographes correspondantes, sans quoi les lignes mal
    // orthographiées disparaissent du résultat.
    $variants = $etatVariants[$etatFilter] ?? [$etatFilter];
    $placeholders = [];
    foreach (array_values($variants) as $i => $variant) {
        $placeholders[] = ":etat$i";
        $params[":etat$i"] = $variant;
    }
    $where[] = 'i.etat IN (' . implode(', ', $placeholders) . ')';
}
if ($zoneFilter !== '') {
    $where[] = "i.zone = :zone";
    $params[':zone'] = $zoneFilter;
}
if ($techFilter !== '') {
    $where[] = "i.technician_id = :technician_id";
    $params[':technician_id'] = $techFilter;
}
if ($natureFilter !== '') {
    // La valeur peut désigner un groupe de natures (voir natureGroups()).
    $natureValues = resolveNatureFilter($natureFilter);
    $placeholders = [];
    foreach (array_values($natureValues) as $i => $value) {
        $placeholders[] = ":nature$i";
        $params[":nature$i"] = strtoupper($value);
    }
    $where[] = 'UPPER(i.nature_ot) IN (' . implode(', ', $placeholders) . ')';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- DISTINCT VALUES FOR FILTERS ----
$distinctZones = $pdo->query("SELECT DISTINCT zone FROM installations ORDER BY zone")->fetchAll();

// technicians list (id + name) only from OT that exist
$distinctTechs = $pdo->query("
    SELECT DISTINCT i.technician_id, t.name AS technician_name
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    WHERE i.technician_id IS NOT NULL
    ORDER BY t.name
")->fetchAll();

$distinctNature = $pdo->query("SELECT DISTINCT nature_ot FROM installations ORDER BY nature_ot")->fetchAll();

// ---- CHART DATA QUERIES ----

// 1) OT per day
$sqlByDate = "
    SELECT i.date_intervention, COUNT(*) AS total
    FROM installations i
    $whereSql
    GROUP BY i.date_intervention
    ORDER BY i.date_intervention
";
$stmt = $pdo->prepare($sqlByDate);
$stmt->execute($params);
$byDate = $stmt->fetchAll();

// 2) OT by etat
$sqlByEtat = "
    SELECT i.etat, COUNT(*) AS total
    FROM installations i
    $whereSql
    GROUP BY i.etat
";
$stmt = $pdo->prepare($sqlByEtat);
$stmt->execute($params);
$byEtat = $stmt->fetchAll();

// 3) OT by zone
$sqlByZone = "
    SELECT i.zone, COUNT(*) AS total
    FROM installations i
    $whereSql
    GROUP BY i.zone
    ORDER BY total DESC
";
$stmt = $pdo->prepare($sqlByZone);
$stmt->execute($params);
$byZone = $stmt->fetchAll();

// 4) OT by technician (with name)
$sqlByTech = "
    SELECT 
        i.technician_id,
        COALESCE(t.name, CONCAT('Tech #', i.technician_id)) AS technician_name,
        COUNT(*) AS total
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    $whereSql
    GROUP BY i.technician_id, t.name
    ORDER BY total DESC
";
$stmt = $pdo->prepare($sqlByTech);
$stmt->execute($params);
$byTech = $stmt->fetchAll();

// 5) OT by scan presence (especially for CPL/CST)
$sqlByScan = "
    SELECT 
        COALESCE(scan, 'Non scanné') AS scan_status,
        COUNT(*) AS total
    FROM installations i
    $whereSql
    GROUP BY scan_status
";
$stmt = $pdo->prepare($sqlByScan);
$stmt->execute($params);
$byScan = $stmt->fetchAll();

// 5) KPI Summaries
$sqlCount = "SELECT COUNT(*) as total FROM installations i $whereSql";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];

$sqlRealise = "SELECT COUNT(*) as total FROM installations i " . ($whereSql ? $whereSql . " AND " : "WHERE ") . "i.etat = 'realise'";
$stmt = $pdo->prepare($sqlRealise);
$stmt->execute($params);
$realiseCount = $stmt->fetch()['total'];
$completionRate = $totalRecords > 0 ? round(($realiseCount / $totalRecords) * 100, 1) : 0;

// 6) Raw data (with technician name)
$sqlTable = "
    SELECT 
        i.id,
        i.date_intervention,
        i.nom,
        i.numero_client,
        i.port,
        i.zone,
        i.Gepon,
        i.etat,
        i.date_realise,
        i.nature_ot,
        i.technician_id,
        t.name AS technician_name
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    $whereSql
    ORDER BY i.date_intervention DESC, i.id DESC
    LIMIT 500
";
$stmt = $pdo->prepare($sqlTable);
$stmt->execute($params);
// 7) Delay Analysis Query for DRG & CPL Retards
$sqlRetards = "
    SELECT 
        i.id,
        i.date_intervention,
        i.nom,
        i.numero_client,
        i.zone,
        i.nature_ot,
        i.etat,
        i.commentaire,
        COALESCE(t.name, CONCAT('Tech #', i.technician_id)) AS technician_name
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    " . ($whereSql ? $whereSql . " AND " : "WHERE ") . "
    (i.etat = 'retard' OR UPPER(i.nature_ot) = 'DRG')
    ORDER BY i.date_intervention DESC, i.id DESC
";
$stmt = $pdo->prepare($sqlRetards);
$stmt->execute($params);
$retardRows = $stmt->fetchAll();

$isRetard    = fn($r) => strtolower(trim((string) $r['etat'])) === 'retard';
$isDRG       = fn($r) => strtoupper(trim((string) $r['nature_ot'])) === 'DRG';
$hasComment  = fn($r) => trim((string) $r['commentaire']) !== '';

$countRetardsTotal = count(array_filter($retardRows, $isRetard));
$countDRG          = count(array_filter($retardRows, $isDRG));
$countCommented    = count(array_filter($retardRows, $hasComment));

// Le commentaire documente l'intervention, pas seulement le retard : la
// grande majorité porte sur des DRG qui ne sont pas en retard. On compte
// donc la couverture séparément pour chaque catégorie.
$countDRGCommented    = count(array_filter($retardRows, fn($r) => $isDRG($r) && $hasComment($r)));
$countRetardCommented = count(array_filter($retardRows, fn($r) => $isRetard($r) && $hasComment($r)));
$pct = fn(int $part, int $whole) => $whole > 0 ? round($part * 100 / $whole) : 0;

$motifsData = [];
foreach ($retardRows as $r) {
    $motif = trim((string)$r['commentaire']) !== '' ? trim((string)$r['commentaire']) : 'Non renseigné';
    if (!isset($motifsData[$motif])) {
        $motifsData[$motif] = ['DRG' => 0, 'CPL' => 0, 'Autres' => 0, 'total' => 0];
    }
    $nature = strtoupper(trim($r['nature_ot']));
    if ($nature === 'DRG') $motifsData[$motif]['DRG']++;
    elseif ($nature === 'CPL') $motifsData[$motif]['CPL']++;
    else $motifsData[$motif]['Autres']++;
    $motifsData[$motif]['total']++;
}
uasort($motifsData, fn($a, $b) => $b['total'] <=> $a['total']);

// Data to send to JS
$motifChartData = [];
foreach ($motifsData as $motif => $data) {
    $motifChartData[] = ['motif' => $motif, 'total' => $data['total']];
}

$chartData = [
    'byDate' => $byDate,
    'byEtat' => $byEtat,
    'byZone' => $byZone,
    'byTech' => $byTech,
    'byScan' => $byScan,
    'byMotif' => $motifChartData,
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Analyse OT - Blooming FTTH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/chart-labels.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/chart-labels.js'); ?>"></script>
    <style>
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-container {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            height: 350px;
        }

        .filter-panel {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }
    </style>
    <?php require_once __DIR__ . '/../components/pwa.php'; renderPwaHead(); ?>
</head>

<body>
    <div class="app-container">
        <?php renderSidebar('OT_analyse.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header flex justify-between items-center">
                    <div>
                        <h2 class="page-title">Analyse des Opérations (OT)</h2>
                        <p class="page-subtitle">Statistiques détaillées et tendances des installations.</p>
                    </div>
                    <div>
                        <a href="monthly_analyse.php" class="btn btn-primary">
                            <i class="fa-solid fa-trophy text-yellow-400 mr-2"></i> Comparatif Mensuel & Meilleur Mois
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-panel">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
                        <div class="form-group">
                            <label>Date Début</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="form-group">
                            <label>Date Fin</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="form-group">
                            <label>État</label>
                            <select name="etat">
                                <option value="">Tous les états</option>
                                <?php foreach ($etatOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === $etatFilter ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Zone</label>
                            <select name="zone">
                                <option value="">Toutes les zones</option>
                                <?php foreach ($distinctZones as $row): ?>
                                    <option value="<?php echo htmlspecialchars($row['zone']); ?>" <?php echo $row['zone'] === $zoneFilter ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['zone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nature OT</label>
                            <select name="nature_ot">
                                <option value="">Toutes</option>
                                <optgroup label="Groupes">
                                    <?php foreach (natureGroups() as $key => $group): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $key === $natureFilter ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Natures">
                                    <?php foreach ($distinctNature as $row): ?>
                                        <option value="<?php echo htmlspecialchars($row['nature_ot']); ?>" <?php echo $row['nature_ot'] === $natureFilter ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['nature_ot']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary w-full"
                                style="display: block; width: 100%;">Filtrer</button>
                        </div>
                    </form>
                </div>

                <!-- Metrics -->
                <div class="kpi-grid mb-6">
                    <div class="kpi-card">
                        <div class="kpi-label">Total OT (filtré)</div>
                        <div class="kpi-value"><?php echo number_format($totalRecords); ?></div>
                        <p class="page-subtitle">Installations correspondantes</p>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">OT Réalisés</div>
                        <div class="kpi-value text-green-500"><?php echo number_format($realiseCount); ?></div>
                        <p class="page-subtitle"><?php echo $completionRate; ?>% de réussite</p>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Zones Actives</div>
                        <div class="kpi-value"><?php echo count($byZone); ?></div>
                        <p class="page-subtitle">Zones couvertes</p>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Techniciens</div>
                        <div class="kpi-value"><?php echo count($byTech); ?></div>
                        <p class="page-subtitle">Effectif mobilisé</p>
                    </div>
                </div>

                <!-- Charts -->
                <div class="chart-grid">
                    <div class="chart-container">
                        <h3 class="section-title">Évolution Temporelle</h3>
                        <canvas id="chartByDate"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3 class="section-title">Répartition par État</h3>
                        <canvas id="chartByEtat"></canvas>
                    </div>
                </div>
                <div class="chart-grid">
                    <div class="chart-container">
                        <h3 class="section-title">Répartition par Zone</h3>
                        <canvas id="chartByZone"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3 class="section-title">Répartition par Technicien</h3>
                        <canvas id="chartByTech"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3 class="section-title">Répartition par Scan (CPL/CST)</h3>
                        <canvas id="chartByScan"></canvas>
                    </div>
                </div>

                <!-- ANALYSE DES RETARDS (DRG & CPL) -->
                <div class="card mb-6" style="border: 2px solid var(--danger); background: var(--bg-card);">
                    <div class="card-header flex justify-between items-center" style="background: rgba(239, 68, 68, 0.08); border-bottom: 1px solid rgba(239, 68, 68, 0.2);">
                        <div>
                            <h3 class="section-title mb-1 text-danger">
                                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                Analyse des Interventions DRG
                            </h3>
                            <p class="text-xs text-muted mb-0">Suivi des commentaires pour tous les retards et toutes les interventions DRG.</p>
                        </div>
                        <div class="flex gap-2">
                            <span class="badge badge-danger" style="font-size: 0.85rem; padding: 6px 12px;">
                                Total Enregistrements: <?php echo count($retardRows); ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-4 border-bottom">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="p-3 rounded-lg border bg-surface-2 flex items-center justify-between">
                                <div>
                                    <div class="text-xs text-muted font-bold">Total Retards</div>
                                    <div class="text-xl font-extrabold text-danger"><?php echo $countRetardsTotal; ?></div>
                                </div>
                                <i class="fa-solid fa-clock text-danger text-2xl"></i>
                            </div>
                            <div class="p-3 rounded-lg border bg-surface-2 flex items-center justify-between">
                                <div>
                                    <div class="text-xs text-muted font-bold">Total DRG (Tous états)</div>
                                    <div class="text-xl font-extrabold text-warning"><?php echo $countDRG; ?></div>
                                </div>
                                <i class="fa-solid fa-tools text-warning text-2xl"></i>
                            </div>
                            <div class="p-3 rounded-lg border bg-surface-2 flex items-center justify-between">
                                <div>
                                    <div class="text-xs text-muted font-bold">Interventions Commentées</div>
                                    <div class="text-xl font-extrabold text-primary"><?php echo $countCommented; ?> / <?php echo count($retardRows); ?></div>
                                    <div class="text-xs text-muted mt-1">
                                        DRG <span class="font-bold text-warning"><?php echo $countDRGCommented; ?>/<?php echo $countDRG; ?></span>
                                        (<?php echo $pct($countDRGCommented, $countDRG); ?>%)
                                        &middot;
                                        Retards <span class="font-bold text-danger"><?php echo $countRetardCommented; ?>/<?php echo $countRetardsTotal; ?></span>
                                        (<?php echo $pct($countRetardCommented, $countRetardsTotal); ?>%)
                                    </div>
                                </div>
                                <i class="fa-solid fa-comment-dots text-primary text-2xl"></i>
                            </div>
                        </div>

                        <div class="chart-container mb-6" style="height: 300px; border-color: rgba(239, 68, 68, 0.2);">
                            <h3 class="section-title text-danger"><i class="fa-solid fa-chart-bar mr-2"></i>Graphique des Motifs</h3>
                            <canvas id="chartByMotif"></canvas>
                        </div>

                        <h4 class="font-bold mb-3 mt-6 text-primary"><i class="fa-solid fa-chart-pie mr-2"></i>Répartition par Motif DRG</h4>
                        <div class="table-responsive mb-8">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Motif / Commentaire</th>
                                        <th>Total</th>
                                        <th>DRG</th>
                                        <th>CPL</th>
                                        <th>Autres</th>
                                        <th>% du Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $totalRecordsAnalysis = count($retardRows); ?>
                                    <?php if ($totalRecordsAnalysis === 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center p-6 text-muted">Aucune donnée.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($motifsData as $motif => $data): ?>
                                            <?php $pct = $totalRecordsAnalysis > 0 ? round(($data['total'] / $totalRecordsAnalysis) * 100, 1) : 0; ?>
                                            <tr class="table-row">
                                                <td class="font-bold text-white"><?php echo htmlspecialchars($motif); ?></td>
                                                <td class="font-extrabold text-primary"><?php echo $data['total']; ?></td>
                                                <td class="text-danger font-bold"><?php echo $data['DRG']; ?></td>
                                                <td class="text-warning font-bold"><?php echo $data['CPL']; ?></td>
                                                <td class="text-muted font-bold"><?php echo $data['Autres']; ?></td>
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-12 text-right text-xs font-bold"><?php echo $pct; ?>%</span>
                                                        <div class="w-full bg-surface-2 rounded-full h-2">
                                                            <div class="bg-primary h-2 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <h4 class="font-bold mb-3 mt-6 text-primary"><i class="fa-solid fa-list mr-2"></i>Détail des Interventions DRG</h4>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID OT</th>
                                        <th>Date</th>
                                        <th>Nature</th>
                                        <th>Client</th>
                                        <th>Zone</th>
                                        <th>Technicien</th>
                                        <th>Motif / Commentaire de l'intervention</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($retardRows)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center p-6 text-muted">
                                                <i class="fa-solid fa-circle-check text-success mr-2"></i> Aucun retard ou intervention DRG pour ces critères.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($retardRows as $r): ?>
                                            <tr class="table-row">
                                                <?php $rowRetard = $isRetard($r); ?>
                                                <td class="font-bold <?php echo $rowRetard ? 'text-danger' : 'text-warning'; ?>">#<?php echo htmlspecialchars($r['id']); ?></td>
                                                <td><?php echo htmlspecialchars($r['date_intervention']); ?></td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($r['nature_ot']); ?></span></td>
                                                <td class="font-medium"><?php echo htmlspecialchars($r['nom']); ?> <span class="text-xs text-muted">(<?php echo htmlspecialchars($r['numero_client']); ?>)</span></td>
                                                <td><span class="badge badge-secondary"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($r['zone']); ?></span></td>
                                                <td><?php echo htmlspecialchars($r['technician_name']); ?></td>
                                                <td>
                                                    <?php if (!empty(trim((string)$r['commentaire']))): ?>
                                                        <?php // Un commentaire sur un DRG à l'heure n'est pas un motif de retard :
                                                              // seuls les retards restent en rouge. ?>
                                                        <div class="p-2 rounded text-xs font-semibold <?php echo $rowRetard
                                                            ? 'bg-danger-soft border border-danger/30 text-danger'
                                                            : 'bg-surface-2 border border-warning/30 text-warning'; ?>">
                                                            <i class="fa-solid fa-comment-dots mr-1"></i> <?php echo htmlspecialchars($r['commentaire']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-xs text-muted italic"><i class="fa-solid fa-exclamation-circle"></i> Non renseigné</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Détails des Interventions (Dernières 500)</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Zone</th>
                                    <th>État</th>
                                    <th>Nature</th>
                                    <th>Technicien</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr class="table-row">
                                        <td class="font-bold">#<?php echo htmlspecialchars($r['id']); ?></td>
                                        <td><?php echo htmlspecialchars($r['date_intervention']); ?></td>
                                        <td><?php echo htmlspecialchars($r['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($r['zone']); ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadgeClass($r['etat']); ?>">
                                                <?php echo getStatusBadgeText($r['etat']); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge"
                                                style="background: var(--bg-main);"><?php echo htmlspecialchars($r['nature_ot']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['technician_name'] ?? ('Tech #' . $r['technician_id'])); ?>
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
    <!-- ... (JS Logic remains the same, will be appended or handled separately) -->
</body>

</html>

<script>
    // Pass PHP data to JS
    const chartData = <?php echo json_encode($chartData); ?>;

    // Helper to build charts safely
    function safeLabelsValues(data, labelKey, valueKey) {
        const labels = [];
        const values = [];
        (data || []).forEach(row => {
            labels.push(row[labelKey]);
            values.push(parseInt(row[valueKey] || 0, 10));
        });
        return { labels, values };
    }

    // Couleurs par statut. Indispensable de mapper par valeur et non par
    // position : la requête renvoie les états par ordre alphabétique, donc
    // un tableau positionnel attribue les couleurs au hasard.
    // 'en cours' et 'encoure' désignent le même état (deux orthographes
    // présentes en base), d'où la même couleur.
    const STATUS_COLORS = {
        'realise':  'rgba(34, 197, 94, 0.9)',    // vert
        'encoure':  'rgba(234, 179, 8, 0.9)',    // jaune
        'en cours': 'rgba(234, 179, 8, 0.9)',    // jaune
        'retard':   'rgba(239, 68, 68, 0.9)',    // rouge
        'negative': 'rgba(148, 163, 184, 0.9)',  // gris
    };
    const STATUS_LABELS = {
        'realise':  'Réalisé',
        'encoure':  'En cours',
        'en cours': 'En cours',
        'retard':   'En retard',
        'negative': 'Négatif',
    };
    function statusColor(etat) {
        return STATUS_COLORS[String(etat).toLowerCase().trim()] || 'rgba(148, 163, 184, 0.9)';
    }
    function statusLabel(etat) {
        return STATUS_LABELS[String(etat).toLowerCase().trim()] || etat;
    }

    // 1) OT par jour
    (function () {
        const ctx = document.getElementById('chartByDate');
        if (!ctx) return;
        const { labels, values } = safeLabelsValues(chartData.byDate, 'date_intervention', 'total');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'OT',
                    data: values,
                    tension: 0.25,
                    fill: true,
                    backgroundColor: 'rgba(34, 197, 94, 0.15)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    pointRadius: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.parsed.y + ' OT'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af', maxRotation: 45, minRotation: 45 },
                        grid: { display: false }
                    },
                    y: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(31,41,55,0.7)' }
                    }
                }
            }
        });
    })();

    // 2) Répartition des OT par état
    (function () {
        const ctx = document.getElementById('chartByEtat');
        if (!ctx) return;
        const { labels, values } = safeLabelsValues(chartData.byEtat, 'etat', 'total');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels.map(statusLabel),
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(statusColor),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#9ca3af' }
                    }
                }
            }
        });
    })();

    // 3) OT par zone
    (function () {
        const ctx = document.getElementById('chartByZone');
        if (!ctx) return;
        const { labels, values } = safeLabelsValues(chartData.byZone, 'zone', 'total');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'OT',
                    data: values,
                    backgroundColor: 'rgba(34, 197, 94, 0.7)'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(31,41,55,0.6)' }
                    },
                    y: {
                        ticks: { color: '#9ca3af' },
                        grid: { display: false }
                    }
                }
            }
        });
    })();

    // 4) OT par technicien (use technician_name)
    (function () {
        const ctx = document.getElementById('chartByTech');
        if (!ctx) return;
        const { labels, values } = safeLabelsValues(chartData.byTech, 'technician_name', 'total');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'OT',
                    data: values,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af', autoSkip: true, maxRotation: 0, minRotation: 0 },
                        grid: { display: false }
                    },
                    y: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(31,41,55,0.6)' }
                    }
                }
            }
        });
    })();

    // 5) Répartition par scan
    (function () {
        const ctx = document.getElementById('chartByScan');
        if (!ctx) return;
        const { labels, values } = safeLabelsValues(chartData.byScan, 'scan_status', 'total');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['rgba(239, 68, 68, 0.8)', 'rgba(34, 197, 94, 0.8)']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#9ca3af' }
                    }
                }
            }
        });
    })();

    // 6) Répartition par motif DRG (Horizontal Bar)
    (function () {
        const ctx = document.getElementById('chartByMotif');
        if (!ctx) return;
        const { labels, values } = safeLabelsValues(chartData.byMotif, 'motif', 'total');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'DRG',
                    data: values,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)'
                }]
            },
            options: {
                indexAxis: 'y', // horizontal bar chart
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(31,41,55,0.6)' }
                    },
                    y: {
                        ticks: { color: '#9ca3af' },
                        grid: { display: false }
                    }
                }
            }
        });
    })();
</script>
</body>

</html>