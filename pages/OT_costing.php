<?php
/**
 * OT Costing & Revenue Analysis Page
 * 
 * Costing Rules:
 * - CPL/CST/TRL/CMI RLR: Flat rate per intervention.
 * - DRG: Base rate with penalty based on delay percentage.
 *   - >95% success: 0% penalty
 *   - 70-95% success: 2.5% penalty
 *   - 51-69% success: 5% penalty
 *   - <51% success: 10% penalty
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$etatFilter = $_GET['etat'] ?? '';
$zoneFilter = $_GET['zone'] ?? '';
$techFilter = $_GET['technician_id'] ?? '';

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
    $where[] = "i.etat = :etat";
    $params[':etat'] = $etatFilter;
}
if ($zoneFilter !== '') {
    $where[] = "i.zone = :zone";
    $params[':zone'] = $zoneFilter;
}
if ($techFilter !== '') {
    $where[] = "i.technician_id = :technician_id";
    $params[':technician_id'] = $techFilter;
}
// Filter by relevant natures explicitly
$where[] = "i.nature_ot IN ('CPL', 'CST', 'TRL', 'DRG', 'CMI RLR')";

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Dropdowns
$distinctEtats = $pdo->query("SELECT DISTINCT etat FROM installations ORDER BY etat")->fetchAll();
$distinctZones = $pdo->query("SELECT DISTINCT zone FROM installations ORDER BY zone")->fetchAll();

// Main query to get grouped counts
$sql = "
    SELECT 
        i.nature_ot,
        i.etat,
        i.zone,
        i.technician_id,
        COALESCE(t.name, CONCAT('Tech #', i.technician_id)) AS technician_name,
        COUNT(*) as total
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    $whereSql
    GROUP BY i.nature_ot, i.etat, i.zone, i.technician_id, t.name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$groupedData = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Calcul et Coûts OT - Blooming FTTH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .grid-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .grid-layout { grid-template-columns: 1fr; }
        }
        .config-sidebar {
            position: sticky;
            top: 24px;
        }
        .metric-card {
            background: var(--bg-card);
            padding: 16px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
        }
        .metric-card.cpl { border-left-color: #3b82f6; }
        .metric-card.cst { border-left-color: #10b981; }
        .metric-card.trl { border-left-color: #8b5cf6; }
        .metric-card.drg { border-left-color: #f59e0b; }
        .metric-card.cmi_rlr { border-left-color: #0ea5e9; }
        
        .tab-group {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 8px 16px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            background: var(--bg-main);
            color: var(--text-muted);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('OT_costing.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Coûts & Facturation OT</h2>
                        <p class="page-subtitle">Estimation des revenus basée sur les interventions.</p>
                    </div>
                </div>

                <div class="grid-layout">
                    <!-- Config Sidebar -->
                    <aside class="config-sidebar space-y-6">
                        <div class="card p-6">
                            <h3 class="section-title mb-4"><i class="fa-solid fa-filter"></i> Filtres</h3>
                            <form method="get" class="space-y-4">
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
                                        <?php foreach ($distinctEtats as $row): ?>
                                            <option value="<?php echo htmlspecialchars($row['etat']); ?>" <?php echo $row['etat'] === $etatFilter ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($row['etat']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-full">Filtrer</button>
                            </form>
                        </div>

                        <div class="card p-6">
                            <h3 class="section-title mb-4"><i class="fa-solid fa-coins"></i> Tarification (MRU)</h3>
                            <div class="space-y-3">
                                <div class="form-group">
                                    <label>CPL</label>
                                    <input type="number" id="price_cpl" value="100" step="0.01" oninput="calculateCosts()">
                                </div>
                                <div class="form-group">
                                    <label>CST</label>
                                    <input type="number" id="price_cst" value="100" step="0.01" oninput="calculateCosts()">
                                </div>
                                <div class="form-group">
                                    <label>TRL</label>
                                    <input type="number" id="price_trl" value="100" step="0.01" oninput="calculateCosts()">
                                </div>
                                <div class="form-group">
                                    <label>CMI RLR</label>
                                    <input type="number" id="price_cmi_rlr" value="100" step="0.01" oninput="calculateCosts()">
                                </div>
                                <div class="form-group">
                                    <label>Base DRG (Brut)</label>
                                    <input type="number" id="price_drg" value="270000" step="0.01" oninput="calculateCosts()">
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Main Results -->
                    <div class="space-y-6">
                        <div class="kpi-grid">
                            <div class="metric-card cpl">
                                <div class="kpi-label">Gains CPL</div>
                                <div class="kpi-value" id="val_cpl">0.00</div>
                                <div class="text-xs text-muted" id="count_cpl">0 OT</div>
                            </div>
                            <div class="metric-card cst">
                                <div class="kpi-label">Gains CST</div>
                                <div class="kpi-value" id="val_cst">0.00</div>
                                <div class="text-xs text-muted" id="count_cst">0 OT</div>
                            </div>
                            <div class="metric-card trl">
                                <div class="kpi-label">Gains TRL</div>
                                <div class="kpi-value" id="val_trl">0.00</div>
                                <div class="text-xs text-muted" id="count_trl">0 OT</div>
                            </div>
                            <div class="metric-card cmi_rlr">
                                <div class="kpi-label">Gains CMI RLR</div>
                                <div class="kpi-value" id="val_cmi_rlr">0.00</div>
                                <div class="text-xs text-muted" id="count_cmi_rlr">0 OT</div>
                            </div>
                        </div>

                        <div class="card p-6" style="border-left: 4px solid var(--warning);">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="section-title mb-0">Analyse DRG Spéciale</h3>
                                <div class="kpi-value text-warning" id="val_drg_final">0.00</div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="p-4 bg-main rounded-lg">
                                    <p class="text-xs text-muted mb-1">Volume DRG</p>
                                    <p class="font-bold text-xl" id="drg_total_count">0</p>
                                </div>
                                <div class="p-4 bg-main rounded-lg">
                                    <p class="text-xs text-muted mb-1">Taux de Retard</p>
                                    <p class="font-bold text-xl text-danger" id="drg_retard_info">0%</p>
                                </div>
                                <div class="p-4 bg-main rounded-lg">
                                    <p class="text-xs text-muted mb-1">Pénalité Appliquée</p>
                                    <p class="font-bold text-xl" id="drg_penalty">0%</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-table">
                            <div class="card-header">
                                <div class="tab-group">
                                    <button class="tab-btn active" onclick="switchTab('tech')">Par Technicien</button>
                                    <button class="tab-btn" onclick="switchTab('zone')">Par Zone</button>
                                    <button class="tab-btn" onclick="switchTab('chart')">Graphique</button>
                                </div>
                            </div>

                            <div id="view_tech" class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Technicien</th>
                                            <th class="text-right">CPL</th>
                                            <th class="text-right">CST</th>
                                            <th class="text-right">TRL</th>
                                            <th class="text-right">CMI</th>
                                            <th class="text-right">DRG Net</th>
                                            <th class="text-right text-success font-bold">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="techTableBody"></tbody>
                                    <tfoot id="techTableFoot" style="background: var(--bg-main); font-weight: bold;"></tfoot>
                                </table>
                            </div>

                             <div id="view_zone" class="table-responsive" style="display: none;">
                                 <table>
                                     <thead>
                                         <tr>
                                             <th>Zone</th>
                                             <th class="text-right">CPL</th>
                                             <th class="text-right">CST</th>
                                             <th class="text-right">TRL</th>
                                             <th class="text-right">CMI</th>
                                             <th class="text-right">DRG Net</th>
                                             <th class="text-right text-success font-bold">Total</th>
                                         </tr>
                                     </thead>
                                     <tbody id="zoneTableBody"></tbody>
                                     <tfoot id="zoneTableFoot" style="background: var(--bg-main); font-weight: bold;"></tfoot>
                                 </table>
                             </div>

                            <div id="view_chart" style="display: none; padding: 20px;">
                                <div style="height: 400px;">
                                    <canvas id="costChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        const groupedData = <?php echo json_encode($groupedData); ?>;
        let costChartInstance = null;

        function getVal(id) { return parseFloat(document.getElementById(id).value) || 0; }

        function calculateCosts() {
            const prices = {
                CPL: getVal('price_cpl'),
                CST: getVal('price_cst'),
                TRL: getVal('price_trl'),
                CMI_RLR: getVal('price_cmi_rlr'),
                DRG: getVal('price_drg')
            };

            let totals = { CPL:0, CST:0, TRL:0, CMI_RLR:0, DRG:0, DRG_retard:0 };
            const techStats = {};
            const zoneStats = {};

            groupedData.forEach(row => {
                const nature = row.nature_ot;
                const etat = row.etat;
                const zone = row.zone || 'Inconnue';
                const total = parseInt(row.total);
                const tId = row.technician_id || 'unassigned';

                // Tech stats
                if (!techStats[tId]) {
                    techStats[tId] = {
                        name: row.technician_name || 'Non Assigné',
                        counts: { CPL: 0, CST: 0, TRL: 0, CMI_RLR: 0, DRG: 0, DRG_retard: 0 }
                    };
                }

                // Zone stats
                if (!zoneStats[zone]) {
                    zoneStats[zone] = {
                        name: zone,
                        counts: { CPL: 0, CST: 0, TRL: 0, CMI_RLR: 0, DRG: 0, DRG_retard: 0 }
                    };
                }

                const counts = techStats[tId].counts;
                const zCounts = zoneStats[zone].counts;

                if (nature === 'CPL') { 
                    totals.CPL += total; counts.CPL += total; zCounts.CPL += total; 
                } else if (nature === 'CST') { 
                    totals.CST += total; counts.CST += total; zCounts.CST += total; 
                } else if (nature === 'TRL') { 
                    totals.TRL += total; counts.TRL += total; zCounts.TRL += total; 
                } else if (nature === 'CMI RLR') { 
                    totals.CMI_RLR += total; counts.CMI_RLR += total; zCounts.CMI_RLR += total; 
                } else if (nature === 'DRG') { 
                    totals.DRG += total; 
                    counts.DRG += total;
                    zCounts.DRG += total;
                    if (etat.toLowerCase() === 'retard') { 
                        totals.DRG_retard += total; counts.DRG_retard += total; zCounts.DRG_retard += total; 
                    }
                }
            });

            // Global Metrics Update
            document.getElementById('val_cpl').innerText = (totals.CPL * prices.CPL).toLocaleString();
            document.getElementById('count_cpl').innerText = totals.CPL + ' OT';
            document.getElementById('val_cst').innerText = (totals.CST * prices.CST).toLocaleString();
            document.getElementById('count_cst').innerText = totals.CST + ' OT';
            document.getElementById('val_trl').innerText = (totals.TRL * prices.TRL).toLocaleString();
            document.getElementById('count_trl').innerText = totals.TRL + ' OT';
            document.getElementById('val_cmi_rlr').innerText = (totals.CMI_RLR * prices.CMI_RLR).toLocaleString();
            document.getElementById('count_cmi_rlr').innerText = totals.CMI_RLR + ' OT';

            // DRG Logic
            let tdrd = 0, penalty = 0;
            if (totals.DRG > 0) {
                tdrd = ((totals.DRG - totals.DRG_retard) / totals.DRG) * 100;
                if (tdrd >= 95) penalty = 0;
                else if (tdrd >= 70) penalty = 2.5;
                else if (tdrd >= 51) penalty = 5;
                else penalty = 10;
            }
            const drgNet = totals.DRG > 0 ? prices.DRG * (1 - penalty/100) : 0;
            document.getElementById('val_drg_final').innerText = drgNet.toLocaleString();
            document.getElementById('drg_total_count').innerText = totals.DRG;
            document.getElementById('drg_retard_info').innerText = (100 - tdrd).toFixed(1) + '%';
            document.getElementById('drg_penalty').innerText = penalty + '%';

            // Update Tables and Chart
            const tbody = document.getElementById('techTableBody');
            const tfoot = document.getElementById('techTableFoot');
            const zbody = document.getElementById('zoneTableBody');
            const zfoot = document.getElementById('zoneTableFoot');
            
            tbody.innerHTML = '';
            zbody.innerHTML = '';
            
            let chartData = { labels: [], datasets: { CPL:[], CST:[], TRL:[], CMI:[], DRG:[] } };
            let grandTotals = { cpl:0, cst:0, trl:0, cmi:0, drg:0, all:0 };

            // Tech Table
            Object.values(techStats).forEach(tech => {
                const c = tech.counts;
                let techDrgNet = 0;
                if (c.DRG > 0) {
                    let techTdrd = ((c.DRG - c.DRG_retard) / c.DRG) * 100;
                    let techPen = (techTdrd >= 95) ? 0 : (techTdrd >= 70 ? 2.5 : (techTdrd >= 51 ? 5 : 10));
                    techDrgNet = (c.DRG / totals.DRG) * prices.DRG * (1 - techPen/100);
                }
                const techTotal = (c.CPL*prices.CPL) + (c.CST*prices.CST) + (c.TRL*prices.TRL) + (c.CMI_RLR*prices.CMI_RLR) + techDrgNet;
                
                if (techTotal > 0) {
                    grandTotals.cpl += (c.CPL*prices.CPL);
                    grandTotals.cst += (c.CST*prices.CST);
                    grandTotals.trl += (c.TRL*prices.TRL);
                    grandTotals.cmi += (c.CMI_RLR*prices.CMI_RLR);
                    grandTotals.drg += techDrgNet;
                    grandTotals.all += techTotal;

                    tbody.innerHTML += `
                        <tr class="table-row">
                            <td class="font-bold">${tech.name}</td>
                            <td class="text-right">${(c.CPL*prices.CPL).toLocaleString()}</td>
                            <td class="text-right">${(c.CST*prices.CST).toLocaleString()}</td>
                            <td class="text-right">${(c.TRL*prices.TRL).toLocaleString()}</td>
                            <td class="text-right">${(c.CMI_RLR*prices.CMI_RLR).toLocaleString()}</td>
                            <td class="text-right">${techDrgNet.toLocaleString()}</td>
                            <td class="text-right text-success font-bold">${techTotal.toLocaleString()}</td>
                        </tr>
                    `;
                    chartData.labels.push(tech.name);
                    chartData.datasets.CPL.push(c.CPL*prices.CPL);
                    chartData.datasets.CST.push(c.CST*prices.CST);
                    chartData.datasets.TRL.push(c.TRL*prices.TRL);
                    chartData.datasets.CMI.push(c.CMI_RLR*prices.CMI_RLR);
                    chartData.datasets.DRG.push(techDrgNet);
                }
            });

            tfoot.innerHTML = `
                <tr>
                    <td>TOTAL GÉNÉRAL</td>
                    <td class="text-right">${grandTotals.cpl.toLocaleString()}</td>
                    <td class="text-right">${grandTotals.cst.toLocaleString()}</td>
                    <td class="text-right">${grandTotals.trl.toLocaleString()}</td>
                    <td class="text-right">${grandTotals.cmi.toLocaleString()}</td>
                    <td class="text-right">${grandTotals.drg.toLocaleString()}</td>
                    <td class="text-right text-success font-bold">${grandTotals.all.toLocaleString()}</td>
                </tr>
            `;

            // Zone Table
            let zoneGrandTotals = { cpl:0, cst:0, trl:0, cmi:0, drg:0, all:0 };
            Object.values(zoneStats).forEach(zone => {
                const c = zone.counts;
                let zoneDrgNet = 0;
                if (c.DRG > 0) {
                    let zoneTdrd = ((c.DRG - c.DRG_retard) / c.DRG) * 100;
                    let zonePen = (zoneTdrd >= 95) ? 0 : (zoneTdrd >= 70 ? 2.5 : (zoneTdrd >= 51 ? 5 : 10));
                    zoneDrgNet = (c.DRG / totals.DRG) * prices.DRG * (1 - zonePen/100);
                }
                const zoneTotal = (c.CPL*prices.CPL) + (c.CST*prices.CST) + (c.TRL*prices.TRL) + (c.CMI_RLR*prices.CMI_RLR) + zoneDrgNet;
                
                if (zoneTotal > 0) {
                    zoneGrandTotals.cpl += (c.CPL*prices.CPL);
                    zoneGrandTotals.cst += (c.CST*prices.CST);
                    zoneGrandTotals.trl += (c.TRL*prices.TRL);
                    zoneGrandTotals.cmi += (c.CMI_RLR*prices.CMI_RLR);
                    zoneGrandTotals.drg += zoneDrgNet;
                    zoneGrandTotals.all += zoneTotal;

                    zbody.innerHTML += `
                        <tr class="table-row">
                            <td class="font-bold">${zone.name}</td>
                            <td class="text-right">${(c.CPL*prices.CPL).toLocaleString()}</td>
                            <td class="text-right">${(c.CST*prices.CST).toLocaleString()}</td>
                            <td class="text-right">${(c.TRL*prices.TRL).toLocaleString()}</td>
                            <td class="text-right">${(c.CMI_RLR*prices.CMI_RLR).toLocaleString()}</td>
                            <td class="text-right">${zoneDrgNet.toLocaleString()}</td>
                            <td class="text-right text-success font-bold">${zoneTotal.toLocaleString()}</td>
                        </tr>
                    `;
                }
            });

            zfoot.innerHTML = `
                <tr>
                    <td>TOTAL PAR ZONES</td>
                    <td class="text-right">${zoneGrandTotals.cpl.toLocaleString()}</td>
                    <td class="text-right">${zoneGrandTotals.cst.toLocaleString()}</td>
                    <td class="text-right">${zoneGrandTotals.trl.toLocaleString()}</td>
                    <td class="text-right">${zoneGrandTotals.cmi.toLocaleString()}</td>
                    <td class="text-right">${zoneGrandTotals.drg.toLocaleString()}</td>
                    <td class="text-right text-success font-bold">${zoneGrandTotals.all.toLocaleString()}</td>
                </tr>
            `;

            updateChart(chartData);
        }

        function updateChart(data) {
            const ctx = document.getElementById('costChart');
            if (costChartInstance) costChartInstance.destroy();
            costChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { label: 'CPL', data: data.datasets.CPL, backgroundColor: '#3b82f6' },
                        { label: 'CST', data: data.datasets.CST, backgroundColor: '#10b981' },
                        { label: 'TRL', data: data.datasets.TRL, backgroundColor: '#8b5cf6' },
                        { label: 'CMI', data: data.datasets.CMI, backgroundColor: '#0ea5e9' },
                        { label: 'DRG', data: data.datasets.DRG, backgroundColor: '#f59e0b' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, grid: { color: 'rgba(31,41,55,0.4)' } }
                    },
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } } }
                }
            });
        }

        function switchTab(tab) {
            document.getElementById('view_tech').style.display = tab === 'tech' ? '' : 'none';
            document.getElementById('view_zone').style.display = tab === 'zone' ? '' : 'none';
            document.getElementById('view_chart').style.display = tab === 'chart' ? '' : 'none';
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('onclick').includes(tab));
            });
        }

        window.addEventListener('DOMContentLoaded', calculateCosts);
    </script>
</body>
</html>