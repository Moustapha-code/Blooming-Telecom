<?php
/**
 * Monthly Analytics & Best Month Comparison Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

// ── Filters ──────────────────────────────────────────────────────────────────
$yearFilter   = $_GET['year']          ?? date('Y');
$zoneFilter   = $_GET['zone']          ?? '';
$techFilter   = $_GET['technician_id'] ?? '';
$natureFilter = $_GET['nature_ot']     ?? '';
$sortBy       = $_GET['sort_by']       ?? 'month'; // 'month', 'realise', 'rate', 'total'

$where  = [];
$params = [];

if ($yearFilter !== 'ALL' && $yearFilter !== '') {
    $where[]  = "YEAR(i.date_intervention) = :year";
    $params[':year'] = $yearFilter;
}

if ($zoneFilter !== '') {
    $where[]  = "i.zone = :zone";
    $params[':zone'] = $zoneFilter;
}
if ($techFilter !== '') {
    $where[]  = "i.technician_id = :technician_id";
    $params[':technician_id'] = $techFilter;
}
if ($natureFilter !== '') {
    $where[]  = "i.nature_ot = :nature_ot";
    $params[':nature_ot'] = $natureFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Distinct filter values ────────────────────────────────────────────────────
$distinctYears  = $pdo->query("SELECT DISTINCT YEAR(date_intervention) AS yr FROM installations WHERE date_intervention IS NOT NULL ORDER BY yr DESC")->fetchAll();
$distinctZones  = $pdo->query("SELECT DISTINCT zone FROM installations WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll();
$distinctTechs  = $pdo->query("
    SELECT DISTINCT i.technician_id, COALESCE(t.name, CONCAT('Tech #', i.technician_id)) AS technician_name
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    WHERE i.technician_id IS NOT NULL
    ORDER BY technician_name
")->fetchAll();
$distinctNature = $pdo->query("SELECT DISTINCT nature_ot FROM installations WHERE nature_ot IS NOT NULL AND nature_ot != '' ORDER BY nature_ot")->fetchAll();

$monthFullNames = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];
$monthShortNames = [
    1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août',
    9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
];

// ── 1. Fetch Monthly Metrics ──────────────────────────────────────────────────
$sqlMonthly = "
    SELECT
        YEAR(i.date_intervention) AS annee,
        MONTH(i.date_intervention) AS mois_num,
        DATE_FORMAT(i.date_intervention, '%Y-%m') AS month_key,
        i.etat,
        COUNT(*) AS total
    FROM installations i
    $whereSql
    GROUP BY annee, mois_num, month_key, i.etat
    ORDER BY annee ASC, mois_num ASC
";
$stmt = $pdo->prepare($sqlMonthly);
$stmt->execute($params);
$rawMonthly = $stmt->fetchAll();

// Build structured monthly records
$monthlyData = [];

if ($yearFilter === 'ALL') {
    // Group by 'YYYY-MM'
    foreach ($rawMonthly as $row) {
        $mKey = $row['month_key'];
        if (!$mKey) continue;
        if (!isset($monthlyData[$mKey])) {
            $yr = $row['annee'];
            $mo = (int)$row['mois_num'];
            $monthlyData[$mKey] = [
                'key' => $mKey,
                'label' => $monthFullNames[$mo] . ' ' . $yr,
                'short_label' => $monthShortNames[$mo] . ' ' . substr($yr, 2),
                'annee' => $yr,
                'mois_num' => $mo,
                'total' => 0,
                'realise' => 0,
                'encoure' => 0,
                'retard' => 0,
                'negative' => 0,
                'top_tech' => 'Non disponible',
                'top_zone' => 'Non disponible'
            ];
        }
        $e = $row['etat'];
        $cnt = (int)$row['total'];
        $monthlyData[$mKey]['total'] += $cnt;
        if (isset($monthlyData[$mKey][$e])) {
            $monthlyData[$mKey][$e] += $cnt;
        }
    }
} else {
    // Fill all 12 months for the selected year
    $selectedYr = (int)$yearFilter;
    for ($m = 1; $m <= 12; $m++) {
        $mKey = sprintf('%04d-%02d', $selectedYr, $m);
        $monthlyData[$mKey] = [
            'key' => $mKey,
            'label' => $monthFullNames[$m] . ' ' . $selectedYr,
            'short_label' => $monthShortNames[$m],
            'annee' => $selectedYr,
            'mois_num' => $m,
            'total' => 0,
            'realise' => 0,
            'encoure' => 0,
            'retard' => 0,
            'negative' => 0,
            'top_tech' => 'Non disponible',
            'top_zone' => 'Non disponible'
        ];
    }
    foreach ($rawMonthly as $row) {
        $mKey = $row['month_key'];
        if (isset($monthlyData[$mKey])) {
            $e = $row['etat'];
            $cnt = (int)$row['total'];
            $monthlyData[$mKey]['total'] += $cnt;
            if (isset($monthlyData[$mKey][$e])) {
                $monthlyData[$mKey][$e] += $cnt;
            }
        }
    }
}

// ── 2. Top Technician & Top Zone per Month ────────────────────────────────────
$sqlTopTechs = "
    SELECT 
        DATE_FORMAT(i.date_intervention, '%Y-%m') AS month_key,
        COALESCE(t.name, CONCAT('Tech #', i.technician_id)) AS tech_name,
        COUNT(*) AS total_realise
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    $whereSql " . ($whereSql ? "AND " : "WHERE ") . "i.etat = 'realise' AND i.technician_id IS NOT NULL
    GROUP BY month_key, tech_name
    ORDER BY month_key, total_realise DESC
";
$stmt = $pdo->prepare($sqlTopTechs);
$stmt->execute($params);
$rawTopTechs = $stmt->fetchAll();

$topTechPerMonth = [];
foreach ($rawTopTechs as $r) {
    $mk = $r['month_key'];
    if (!isset($topTechPerMonth[$mk])) {
        $topTechPerMonth[$mk] = $r['tech_name'] . ' (' . $r['total_realise'] . ' OT)';
    }
}

$sqlTopZones = "
    SELECT 
        DATE_FORMAT(i.date_intervention, '%Y-%m') AS month_key,
        i.zone,
        COUNT(*) AS total_zone
    FROM installations i
    $whereSql " . ($whereSql ? "AND " : "WHERE ") . "i.zone IS NOT NULL AND i.zone != ''
    GROUP BY month_key, i.zone
    ORDER BY month_key, total_zone DESC
";
$stmt = $pdo->prepare($sqlTopZones);
$stmt->execute($params);
$rawTopZones = $stmt->fetchAll();

$topZonePerMonth = [];
foreach ($rawTopZones as $r) {
    $mk = $r['month_key'];
    if (!isset($topZonePerMonth[$mk])) {
        $topZonePerMonth[$mk] = $r['zone'];
    }
}

// Calculate Rates and attach top tech/zone
foreach ($monthlyData as $mk => &$mData) {
    $mData['rate'] = $mData['total'] > 0 ? round(($mData['realise'] / $mData['total']) * 100, 1) : 0;
    if (isset($topTechPerMonth[$mk])) {
        $mData['top_tech'] = $topTechPerMonth[$mk];
    }
    if (isset($topZonePerMonth[$mk])) {
        $mData['top_zone'] = $topZonePerMonth[$mk];
    }
}
unset($mData);

// ── 3. Calculate MoM Deltas & Rank Months ─────────────────────────────────────
$monthlyList = array_values($monthlyData);

// MoM calculation chronologically
for ($i = 0; $i < count($monthlyList); $i++) {
    $prevTotal = $i > 0 ? $monthlyList[$i - 1]['total'] : null;
    $currTotal = $monthlyList[$i]['total'];
    if ($prevTotal !== null && $prevTotal > 0) {
        $monthlyList[$i]['mom_delta'] = round((($currTotal - $prevTotal) / $prevTotal) * 100, 1);
    } else {
        $monthlyList[$i]['mom_delta'] = null;
    }
}

// Sort for Best Month Ranking (by Realised DESC, then Rate DESC, then Total DESC)
$rankedList = $monthlyList;
usort($rankedList, function ($a, $b) {
    if ($a['realise'] !== $b['realise']) {
        return $b['realise'] <=> $a['realise'];
    }
    if ($a['rate'] !== $b['rate']) {
        return $b['rate'] <=> $a['rate'];
    }
    return $b['total'] <=> $a['total'];
});

// Assign ranks
$rankMap = [];
foreach ($rankedList as $idx => $item) {
    $rankMap[$item['key']] = $idx + 1;
}

foreach ($monthlyList as &$item) {
    $item['rank'] = $rankMap[$item['key']];
}
unset($item);

// Identify Podium
$bestMonth    = $rankedList[0] ?? null;
$runnerUp     = $rankedList[1] ?? null;
$thirdMonth   = $rankedList[2] ?? null;

// Overall Aggregates
$totalYearCount   = array_sum(array_column($monthlyList, 'total'));
$totalRealiseCount = array_sum(array_column($monthlyList, 'realise'));
$totalRetardCount  = array_sum(array_column($monthlyList, 'retard'));
$activeMonthsCount = count(array_filter($monthlyList, fn($m) => $m['total'] > 0));
$avgMonthlyVolume  = $activeMonthsCount > 0 ? round($totalYearCount / $activeMonthsCount) : 0;
$avgMonthlyRealise = $activeMonthsCount > 0 ? round($totalRealiseCount / $activeMonthsCount) : 0;
$globalRate        = $totalYearCount > 0 ? round(($totalRealiseCount / $totalYearCount) * 100, 1) : 0;

// Sort option for data table display
if ($sortBy === 'realise') {
    usort($monthlyList, fn($a, $b) => $b['realise'] <=> $a['realise']);
} elseif ($sortBy === 'rate') {
    usort($monthlyList, fn($a, $b) => $b['rate'] <=> $a['rate']);
} elseif ($sortBy === 'total') {
    usort($monthlyList, fn($a, $b) => $b['total'] <=> $a['total']);
}
// Default 'month' keeps chronological order

// JS Payload for Charts and Month-vs-Month Comparator
$jsMonthlyData = json_encode(array_values($monthlyData));
$jsRankedData  = json_encode($rankedList);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse Mensuelle & Meilleur Mois - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .champion-card {
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.15) 0%, rgba(245, 158, 11, 0.05) 100%);
            border: 2px solid rgba(234, 179, 8, 0.4);
            border-radius: var(--radius-lg);
            padding: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(234, 179, 8, 0.15);
        }
        .champion-badge {
            display: inline-flex;
            items-center;
            gap: 8px;
            background: #eab308;
            color: #0f172a;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .podium-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .podium-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transition);
        }
        .podium-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }
        .rank-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
        }
        .rank-1 { background: #fef08a; color: #854d0e; border: 2px solid #eab308; }
        .rank-2 { background: #e2e8f0; color: #334155; border: 2px solid #94a3b8; }
        .rank-3 { background: #ffedd5; color: #9a3412; border: 2px solid #f97316; }
        .rank-other { background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border); }
        
        .comparator-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 20px;
        }
        .vs-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            margin: 0 auto;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('monthly_analyse.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h2 class="page-title">
                            <i class="fa-solid fa-trophy text-yellow-500 mr-2"></i>
                            Analyse Mensuelle & Meilleur Mois
                        </h2>
                        <p class="page-subtitle">Comparatif des performances, identification du meilleur mois et tendances mensuelles.</p>
                    </div>
                    <div class="flex gap-3">
                        <a href="OT_analyse.php" class="btn btn-secondary">
                            <i class="fa-solid fa-chart-pie"></i> Vue Globale OT
                        </a>
                        <a href="OT_export.php" class="btn btn-secondary" style="border-color: var(--success); color: var(--success);">
                            <i class="fa-solid fa-file-excel"></i> Exporter Excel
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Imprimer Rapport
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-6">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div class="form-group mb-0">
                            <label>Année</label>
                            <select name="year">
                                <option value="ALL" <?php echo $yearFilter === 'ALL' ? 'selected' : ''; ?>>Toutes les années</option>
                                <?php foreach ($distinctYears as $y): ?>
                                    <option value="<?php echo $y['yr']; ?>" <?php echo (string)$y['yr'] === (string)$yearFilter ? 'selected' : ''; ?>>
                                        Année <?php echo $y['yr']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
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
                        <div class="form-group mb-0">
                            <label>Technicien</label>
                            <select name="technician_id">
                                <option value="">Tous les techniciens</option>
                                <?php foreach ($distinctTechs as $row): ?>
                                    <option value="<?php echo htmlspecialchars($row['technician_id']); ?>" <?php echo (string)$row['technician_id'] === (string)$techFilter ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['technician_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label>Tri du Tableau</label>
                            <select name="sort_by">
                                <option value="month" <?php echo $sortBy === 'month' ? 'selected' : ''; ?>>Chronologique</option>
                                <option value="realise" <?php echo $sortBy === 'realise' ? 'selected' : ''; ?>>Par OT Réalisés (Best)</option>
                                <option value="rate" <?php echo $sortBy === 'rate' ? 'selected' : ''; ?>>Par Taux Réalisation %</option>
                                <option value="total" <?php echo $sortBy === 'total' ? 'selected' : ''; ?>>Par Volume Total OT</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary flex-1">
                                <i class="fa-solid fa-filter"></i> Filtrer
                            </button>
                            <a href="monthly_analyse.php" class="btn btn-secondary" title="Réinitialiser">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- SPOTLIGHT: BEST MONTH BANNER & PODIUM -->
                <?php if ($bestMonth && $bestMonth['total'] > 0): ?>
                    <div class="champion-card mb-6">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <div class="champion-badge mb-3">
                                    <i class="fa-solid fa-crown"></i> MEILLEUR MOIS : <?php echo htmlspecialchars($bestMonth['label']); ?>
                                </div>
                                <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main);" class="mb-2">
                                    🏆 <?php echo htmlspecialchars($bestMonth['label']); ?> — Performance Record
                                </h2>
                                <p class="text-muted" style="font-size: 0.95rem;">
                                    Ce mois détient le plus grand nombre d'installations réussies avec 
                                    <strong class="text-success"><?php echo number_format($bestMonth['realise']); ?> OT réalisés</strong> 
                                    (Taux de réussite : <strong><?php echo $bestMonth['rate']; ?>%</strong>).
                                </p>
                            </div>
                            <div class="flex items-center gap-6 bg-white dark:bg-slate-800 p-4 rounded-xl border border-yellow-400/40">
                                <div class="text-center">
                                    <div class="text-xs text-muted font-semibold uppercase">Total OT</div>
                                    <div class="text-2xl font-extrabold"><?php echo number_format($bestMonth['total']); ?></div>
                                </div>
                                <div class="border-l border-border h-10"></div>
                                <div class="text-center">
                                    <div class="text-xs text-muted font-semibold uppercase">Réalisés</div>
                                    <div class="text-2xl font-extrabold text-success"><?php echo number_format($bestMonth['realise']); ?></div>
                                </div>
                                <div class="border-l border-border h-10"></div>
                                <div class="text-center">
                                    <div class="text-xs text-muted font-semibold uppercase">Taux Succès</div>
                                    <div class="text-2xl font-extrabold text-primary"><?php echo $bestMonth['rate']; ?>%</div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-4 border-t border-yellow-500/20 text-sm">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-user-astronaut text-yellow-600"></i>
                                <span class="text-muted">Tech Star du mois :</span>
                                <strong><?php echo htmlspecialchars($bestMonth['top_tech']); ?></strong>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-map-location-dot text-yellow-600"></i>
                                <span class="text-muted">Zone Principale :</span>
                                <strong><?php echo htmlspecialchars($bestMonth['top_zone']); ?></strong>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-chart-line text-yellow-600"></i>
                                <span class="text-muted">vs Moyenne mensuelle :</span>
                                <?php 
                                    $diffAvg = $avgMonthlyRealise > 0 ? round((($bestMonth['realise'] - $avgMonthlyRealise) / $avgMonthlyRealise) * 100) : 0;
                                ?>
                                <strong class="<?php echo $diffAvg >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $diffAvg >= 0 ? '+' . $diffAvg : $diffAvg; ?>% d'OT réalisés
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TOP 3 PODIUM CARDS -->
                <div class="podium-grid">
                    <!-- #1 GOLD -->
                    <?php if ($bestMonth): ?>
                        <div class="podium-card" style="border-left: 5px solid #eab308;">
                            <div class="flex items-center justify-between mb-3">
                                <span class="rank-badge rank-1">🥇</span>
                                <span class="badge badge-success"><?php echo $bestMonth['rate']; ?>% Succès</span>
                            </div>
                            <h4 class="font-bold text-lg mb-1"><?php echo htmlspecialchars($bestMonth['label']); ?></h4>
                            <div class="text-2xl font-black text-success mb-2"><?php echo number_format($bestMonth['realise']); ?> <span class="text-sm font-normal text-muted">OT réalisés</span></div>
                            <div class="text-xs text-muted flex justify-between border-t pt-2 mt-2">
                                <span>Total Volume: <strong><?php echo number_format($bestMonth['total']); ?></strong></span>
                                <span>Retards: <strong class="text-danger"><?php echo number_format($bestMonth['retard']); ?></strong></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- #2 SILVER -->
                    <?php if ($runnerUp): ?>
                        <div class="podium-card" style="border-left: 5px solid #94a3b8;">
                            <div class="flex items-center justify-between mb-3">
                                <span class="rank-badge rank-2">🥈</span>
                                <span class="badge badge-info"><?php echo $runnerUp['rate']; ?>% Succès</span>
                            </div>
                            <h4 class="font-bold text-lg mb-1"><?php echo htmlspecialchars($runnerUp['label']); ?></h4>
                            <div class="text-2xl font-black text-primary mb-2"><?php echo number_format($runnerUp['realise']); ?> <span class="text-sm font-normal text-muted">OT réalisés</span></div>
                            <div class="text-xs text-muted flex justify-between border-t pt-2 mt-2">
                                <span>Total Volume: <strong><?php echo number_format($runnerUp['total']); ?></strong></span>
                                <span>Retards: <strong class="text-danger"><?php echo number_format($runnerUp['retard']); ?></strong></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- #3 BRONZE -->
                    <?php if ($thirdMonth): ?>
                        <div class="podium-card" style="border-left: 5px solid #f97316;">
                            <div class="flex items-center justify-between mb-3">
                                <span class="rank-badge rank-3">🥉</span>
                                <span class="badge badge-warning"><?php echo $thirdMonth['rate']; ?>% Succès</span>
                            </div>
                            <h4 class="font-bold text-lg mb-1"><?php echo htmlspecialchars($thirdMonth['label']); ?></h4>
                            <div class="text-2xl font-black text-warning mb-2"><?php echo number_format($thirdMonth['realise']); ?> <span class="text-sm font-normal text-muted">OT réalisés</span></div>
                            <div class="text-xs text-muted flex justify-between border-t pt-2 mt-2">
                                <span>Total Volume: <strong><?php echo number_format($thirdMonth['total']); ?></strong></span>
                                <span>Retards: <strong class="text-danger"><?php echo number_format($thirdMonth['retard']); ?></strong></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- KPIs OVERVIEW -->
                <div class="kpi-grid mb-6">
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: var(--primary-soft); color: var(--primary);">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <div class="kpi-info">
                            <span class="kpi-label">Total Installations</span>
                            <span class="kpi-value"><?php echo number_format($totalYearCount); ?></span>
                            <span class="kpi-trend trend-up">Sur <?php echo $activeMonthsCount; ?> mois enregistrés</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: var(--success-soft); color: var(--success);">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="kpi-info">
                            <span class="kpi-label">OT Réalisés Globaux</span>
                            <span class="kpi-value"><?php echo number_format($totalRealiseCount); ?></span>
                            <span class="kpi-trend trend-up"><?php echo $globalRate; ?>% de taux de succès global</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: var(--warning-soft); color: var(--warning);">
                            <i class="fa-solid fa-calculator"></i>
                        </div>
                        <div class="kpi-info">
                            <span class="kpi-label">Moyenne OT Réalisés / Mois</span>
                            <span class="kpi-value"><?php echo number_format($avgMonthlyRealise); ?></span>
                            <span class="kpi-trend">Moyenne globale : <?php echo number_format($avgMonthlyVolume); ?> OT/mois</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon" style="background: var(--danger-soft); color: var(--danger);">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>
                        <div class="kpi-info">
                            <span class="kpi-label">Total Retards</span>
                            <span class="kpi-value"><?php echo number_format($totalRetardCount); ?></span>
                            <span class="kpi-trend trend-down">
                                <?php echo $totalYearCount > 0 ? round(($totalRetardCount / $totalYearCount) * 100, 1) : 0; ?>% des OT
                            </span>
                        </div>
                    </div>
                </div>

                <!-- MONTH VS MONTH SIDE-BY-SIDE COMPARATOR TOOL -->
                <div class="card mb-6">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="section-title mb-0">
                            <i class="fa-solid fa-code-compare text-primary mr-2"></i>
                            Comparateur Direct entre 2 Mois
                        </h3>
                        <span class="text-xs text-muted">Sélectionnez 2 mois pour comparer directement leurs résultats side-by-side.</span>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center mb-6">
                            <div class="md:col-span-2 form-group mb-0">
                                <label class="font-bold text-primary">Premier Mois (Mois A)</label>
                                <select id="selectMonthA" onchange="runMonthComparison()" class="form-control">
                                    <?php foreach ($monthlyList as $idx => $m): ?>
                                        <option value="<?php echo $m['key']; ?>" <?php echo $idx === 0 ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($m['label']); ?> (<?php echo $m['realise']; ?> réalisés)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="text-center">
                                <div class="vs-circle">VS</div>
                            </div>

                            <div class="md:col-span-2 form-group mb-0">
                                <label class="font-bold text-success">Deuxième Mois (Mois B)</label>
                                <select id="selectMonthB" onchange="runMonthComparison()" class="form-control">
                                    <?php foreach ($monthlyList as $idx => $m): ?>
                                        <option value="<?php echo $m['key']; ?>" <?php echo ($idx === 1 || ($idx === 0 && count($monthlyList) > 1)) ? ($idx === 1 ? 'selected' : '') : ''; ?>>
                                            <?php echo htmlspecialchars($m['label']); ?> (<?php echo $m['realise']; ?> réalisés)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Comparison Results Box -->
                        <div id="comparisonResultBox" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- CHARTS SECTION -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="section-title mb-0">Évolution de l'État par Mois</h3>
                        </div>
                        <div class="p-4" style="height: 380px;">
                            <canvas id="monthlyComboChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header flex justify-between items-center">
                            <h3 class="section-title mb-0">Classement des Mois (Volume Réalisé)</h3>
                        </div>
                        <div class="p-4" style="height: 380px;">
                            <canvas id="rankingChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- DETAILED MONTHLY RANKING TABLE -->
                <div class="card-table">
                    <div class="card-header flex justify-between items-center">
                        <h3 class="section-title mb-0">Tableau de Comparaison Mensuel</h3>
                        <div class="text-muted text-sm"><?php echo count($monthlyList); ?> mois analysés</div>
                    </div>
                    <div class="table-responsive">
                        <table class="text-center">
                            <thead>
                                <tr>
                                    <th class="text-left" style="width: 70px;">Rang</th>
                                    <th class="text-left">Mois / Période</th>
                                    <th>Total OT</th>
                                    <th>OT Réalisés</th>
                                    <th>En cours</th>
                                    <th>Retard</th>
                                    <th>Taux Réalisation</th>
                                    <th>Évolution (MoM)</th>
                                    <th>Tech Star</th>
                                    <th>Zone Principale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($monthlyList)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center p-8">Aucune donnée mensuelle trouvée pour ces critères.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($monthlyList as $m): 
                                        $rk = $m['rank'];
                                        $rankBadgeClass = 'rank-other';
                                        $medal = '#' . $rk;
                                        if ($rk === 1) { $rankBadgeClass = 'rank-1'; $medal = '🥇 1er'; }
                                        elseif ($rk === 2) { $rankBadgeClass = 'rank-2'; $medal = '🥈 2ème'; }
                                        elseif ($rk === 3) { $rankBadgeClass = 'rank-3'; $medal = '🥉 3ème'; }
                                        
                                        $delta = $m['mom_delta'];
                                        $rate = $m['rate'];
                                    ?>
                                    <tr class="table-row <?php echo $rk === 1 ? 'bg-yellow-500/5' : ''; ?>">
                                        <td class="text-left font-bold">
                                            <span class="rank-badge <?php echo $rankBadgeClass; ?>" style="width: auto; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem;">
                                                <?php echo $medal; ?>
                                            </span>
                                        </td>
                                        <td class="text-left font-bold" style="font-size: 0.95rem;">
                                            <?php echo htmlspecialchars($m['label']); ?>
                                        </td>
                                        <td class="font-bold"><?php echo number_format($m['total']); ?></td>
                                        <td class="font-bold text-success" style="font-size: 1rem;"><?php echo number_format($m['realise']); ?></td>
                                        <td class="text-warning"><?php echo number_format($m['encoure']); ?></td>
                                        <td class="text-danger"><?php echo number_format($m['retard']); ?></td>
                                        <td>
                                            <div class="flex items-center justify-center gap-2">
                                                <div class="w-16 bg-gray-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                                    <div class="bg-success h-2 rounded-full" style="width: <?php echo min(100, $rate); ?>%;"></div>
                                                </div>
                                                <span class="badge <?php echo $rate >= 75 ? 'badge-success' : ($rate >= 50 ? 'badge-warning' : 'badge-danger'); ?>">
                                                    <?php echo $rate; ?>%
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($delta !== null): ?>
                                                <?php if ($delta > 0): ?>
                                                    <span class="text-success font-semibold"><i class="fa-solid fa-arrow-trend-up"></i> +<?php echo $delta; ?>%</span>
                                                <?php elseif ($delta < 0): ?>
                                                    <span class="text-danger font-semibold"><i class="fa-solid fa-arrow-trend-down"></i> <?php echo $delta; ?>%</span>
                                                <?php else: ?>
                                                    <span class="text-muted">0.0%</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-sm font-medium">
                                                <i class="fa-solid fa-user-gear text-muted mr-1"></i>
                                                <?php echo htmlspecialchars($m['top_tech']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($m['top_zone']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        const monthlyData = <?php echo $jsMonthlyData; ?>;
        const rankedData  = <?php echo $jsRankedData; ?>;

        // ── 1. Combo Bar + Line Chart ───────────────────────────────────────────────
        const labels = monthlyData.map(m => m.short_label);
        const realiseData = monthlyData.map(m => m.realise);
        const encoureData = monthlyData.map(m => m.encoure);
        const retardData  = monthlyData.map(m => m.retard);
        const rateData    = monthlyData.map(m => m.rate);

        const ctxCombo = document.getElementById('monthlyComboChart').getContext('2d');
        new Chart(ctxCombo, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Taux Réalisation %',
                        data: rateData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        yAxisID: 'yRate',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'OT Réalisés',
                        data: realiseData,
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    },
                    {
                        label: 'En cours',
                        data: encoureData,
                        backgroundColor: '#f59e0b',
                        borderRadius: 6
                    },
                    {
                        label: 'Retard',
                        data: retardData,
                        backgroundColor: '#ef4444',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Nombre d\'OT' }
                    },
                    yRate: {
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Taux (%)' },
                        grid: { drawOnChartArea: false }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // ── 2. Ranking Horizontal Bar Chart ──────────────────────────────────────────
        const rankLabels = rankedData.map(m => (m.rank === 1 ? '🏆 ' : '#' + m.rank + ' ') + m.label);
        const rankValues = rankedData.map(m => m.realise);
        const rankColors = rankedData.map(m => m.rank === 1 ? '#eab308' : (m.rank === 2 ? '#94a3b8' : (m.rank === 3 ? '#f97316' : '#3b82f6')));

        const ctxRank = document.getElementById('rankingChart').getContext('2d');
        new Chart(ctxRank, {
            type: 'bar',
            data: {
                labels: rankLabels,
                datasets: [{
                    label: 'OT Réalisés',
                    data: rankValues,
                    backgroundColor: rankColors,
                    borderRadius: 6
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
                    x: { beginAtZero: true }
                }
            }
        });

        // ── 3. Month vs Month Comparator Logic ──────────────────────────────────────
        function runMonthComparison() {
            const keyA = document.getElementById('selectMonthA').value;
            const keyB = document.getElementById('selectMonthB').value;

            const mA = monthlyData.find(m => m.key === keyA);
            const mB = monthlyData.find(m => m.key === keyB);

            if (!mA || !mB) return;

            const isAWinner = mA.realise >= mB.realise;

            const box = document.getElementById('comparisonResultBox');
            box.innerHTML = `
                <!-- Month A Card -->
                <div class="p-5 rounded-xl border ${isAWinner ? 'border-2 border-emerald-500 bg-emerald-500/5' : 'border-border bg-card'} relative">
                    ${isAWinner ? '<span class="badge badge-success absolute top-4 right-4"><i class="fa-solid fa-trophy mr-1"></i> Gagnant</span>' : ''}
                    <h4 class="font-extrabold text-xl text-primary mb-3">${mA.label}</h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">Volume Total</span>
                            <strong class="text-lg">${mA.total} OT</strong>
                        </div>
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">OT Réalisés</span>
                            <strong class="text-lg text-success">${mA.realise} OT</strong>
                        </div>
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">Taux Réalisation</span>
                            <strong class="text-lg text-primary">${mA.rate}%</strong>
                        </div>
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">Retards</span>
                            <strong class="text-lg text-danger">${mA.retard} OT</strong>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t text-xs text-muted flex justify-between">
                        <span>Star Tech: <strong>${mA.top_tech}</strong></span>
                        <span>Zone: <strong>${mA.top_zone}</strong></span>
                    </div>
                </div>

                <!-- Month B Card -->
                <div class="p-5 rounded-xl border ${!isAWinner ? 'border-2 border-emerald-500 bg-emerald-500/5' : 'border-border bg-card'} relative">
                    ${!isAWinner ? '<span class="badge badge-success absolute top-4 right-4"><i class="fa-solid fa-trophy mr-1"></i> Gagnant</span>' : ''}
                    <h4 class="font-extrabold text-xl text-success mb-3">${mB.label}</h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">Volume Total</span>
                            <strong class="text-lg">${mB.total} OT</strong>
                        </div>
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">OT Réalisés</span>
                            <strong class="text-lg text-success">${mB.realise} OT</strong>
                        </div>
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">Taux Réalisation</span>
                            <strong class="text-lg text-primary">${mB.rate}%</strong>
                        </div>
                        <div class="p-2 bg-surface-2 rounded">
                            <span class="text-xs text-muted block">Retards</span>
                            <strong class="text-lg text-danger">${mB.retard} OT</strong>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t text-xs text-muted flex justify-between">
                        <span>Star Tech: <strong>${mB.top_tech}</strong></span>
                        <span>Zone: <strong>${mB.top_zone}</strong></span>
                    </div>
                </div>
            `;
        }

        // Initialize comparison on load
        document.addEventListener('DOMContentLoaded', runMonthComparison);
    </script>
</body>
</html>