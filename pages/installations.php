<?php
/**
 * Page de gestion OT (Installations / OT)
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();
// Pagination
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) && (int)$_GET['page'] > 0
    ? (int)$_GET['page']
    : 1;

$limit  = 15;
$offset = ($page - 1) * $limit;

// Construction des filtres
$where  = [];
$params = [];

// Texte de recherche
$search = trim($_GET['q'] ?? '');

// Filtres avec alias de table i.
if (!empty($_GET['etat'])) {
    if ($_GET['etat'] === 'encoure') {
        // Les deux orthographes du même état coexistent en base
        $where[]  = "i.etat IN ('encoure', 'en cours')";
    } else {
        $where[]  = 'i.etat = ?';
        $params[] = $_GET['etat'];
    }
}

if (!empty($_GET['date_from'])) {
    $where[]  = 'i.date_intervention >= ?';
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $where[]  = 'i.date_intervention <= ?';
    $params[] = $_GET['date_to'];
}

if (!empty($_GET['zone'])) {
    $where[]  = 'i.zone = ?';
    $params[] = $_GET['zone'];
}

if (!empty($_GET['nature_ot'])) {
    $where[]  = 'i.nature_ot = ?';
    $params[] = $_GET['nature_ot'];
}

if (!empty($_GET['scan'])) {
    $where[]  = 'i.scan = ?';
    $params[] = $_GET['scan'];
}

// Recherche sur plusieurs colonnes (préfixées avec i.)
if ($search !== '') {
    $where[] = '(i.nom LIKE ? 
                 OR i.numero_client LIKE ? 
                 OR i.Gepon LIKE ? 
                 OR i.zone LIKE ? 
                 OR i.nature_ot LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Requête principale avec le nom du technicien
$stmt = $pdo->prepare('
    SELECT i.*, t.name AS technician_name 
    FROM installations i 
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    ' . $whereClause . '
    ORDER BY (i.etat IN (\'encoure\', \'en cours\')) DESC,
             i.date_intervention DESC,
             i.temp_de_venir DESC
    LIMIT ' . $limit . ' OFFSET ' . $offset
);
$stmt->execute($params);
$installations = $stmt->fetchAll();

// Compteur pour la pagination
$countStmt = $pdo->prepare('
    SELECT COUNT(*) AS count
    FROM installations i
    LEFT JOIN technicians t ON i.technician_id = t.technician_id
    ' . $whereClause . '
');
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetch()['count'];
$totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

// Zones pour le filtre
$stmt  = $pdo->query('SELECT DISTINCT zone FROM installations ORDER BY zone');
$zones = $stmt->fetchAll();

// Natures OT pour le filtre
$stmt_nature = $pdo->query('SELECT DISTINCT nature_ot FROM installations WHERE nature_ot IS NOT NULL AND nature_ot != \'\' ORDER BY nature_ot');
$natures = $stmt_nature->fetchAll();

// Liste des techniciens (pour le select du modal)
$stmt        = $pdo->query('SELECT * FROM technicians ORDER BY name');
$technicians = $stmt->fetchAll();

// Helper pour les liens de pagination
function buildPageUrl($pageNumber): string {
    $query         = $_GET;
    $query['page'] = $pageNumber;
    return '?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installations OT - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <?php require_once __DIR__ . '/../components/pwa.php'; renderPwaHead(); ?>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('installations.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Gestion des OT</h2>
                        <p class="page-subtitle">Suivi des installations et interventions client.</p>
                    </div>
                    <div class="flex gap-4">
                        <a href="OT_analyse.php" class="btn btn-secondary">
                            <i class="fa-solid fa-chart-line"></i> Analyse OT
                        </a>
                        <a href="monthly_analyse.php" class="btn btn-secondary">
                            <i class="fa-solid fa-calendar-days"></i> Comparatif Mensuel
                        </a>
                        <a href="OT_export.php" class="btn btn-secondary" style="border-color: var(--success); color: var(--success);">
                            <i class="fa-solid fa-file-excel"></i> Exporter Excel
                        </a>
                        <button class="btn btn-primary" onclick="openModal('installModal')">
                            <i class="fa-solid fa-plus"></i> Nouvel OT
                        </button>
                    </div>
                </div>

                <!-- Advanced Filters -->
                <div class="card mb-6" style="background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 items-end">
                        <div class="form-group mb-0">
                            <label>Date Début</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label>Date Fin</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label>Statut</label>
                            <select name="etat">
                                <option value="">Tous les statuts</option>
                                <option value="encoure" <?php echo ($_GET['etat'] ?? '') === 'encoure' ? 'selected' : ''; ?>>En cours</option>
                                <option value="realise" <?php echo ($_GET['etat'] ?? '') === 'realise' ? 'selected' : ''; ?>>Réalisé</option>
                                <option value="retard"  <?php echo ($_GET['etat'] ?? '') === 'retard' ? 'selected' : ''; ?>>En retard</option>
                                <option value="negative" <?php echo ($_GET['etat'] ?? '') === 'negative' ? 'selected' : ''; ?>>Négatif</option>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label>Zone</label>
                            <select name="zone">
                                <option value="">Toutes les zones</option>
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?php echo htmlspecialchars($z['zone']); ?>" <?php echo ($_GET['zone'] ?? '') === $z['zone'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($z['zone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label>Nature OT</label>
                            <select name="nature_ot">
                                <option value="">Toutes les natures</option>
                                <?php foreach ($natures as $n): ?>
                                    <option value="<?php echo htmlspecialchars($n['nature_ot']); ?>" <?php echo ($_GET['nature_ot'] ?? '') === $n['nature_ot'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($n['nature_ot']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label>Recherche</label>
                            <input type="text" name="q" placeholder="Nom, Client, GEPON..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label>Scan</label>
                            <select name="scan">
                                <option value="">Tous</option>
                                <option value="Scanné" <?php echo ($_GET['scan'] ?? '') === 'Scanné' ? 'selected' : ''; ?>>Scanné</option>
                                <option value="Non scanné" <?php echo ($_GET['scan'] ?? '') === 'Non scanné' ? 'selected' : ''; ?>>Non scanné</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary w-full">Filtrer</button>
                            <a href="installations.php" class="btn btn-secondary"><i class="fa-solid fa-rotate"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Liste des Interventions</h3>
                        <div class="text-muted text-sm"><?php echo $totalRecords; ?> installations trouvées</div>
                    </div>

                    <!-- Bulk Action Bar -->
                    <div id="bulkActionBar" class="p-3 border-bottom flex items-center justify-between" style="display: none; background: var(--primary-soft); border-bottom: 1px solid var(--border); padding: 12px 20px;">
                        <div class="flex items-center gap-3">
                            <span class="badge badge-info" id="selectedCountBadge" style="font-size: 0.85rem; padding: 6px 12px;">0 sélectionné(s)</span>
                            <span class="text-sm font-medium">Actions groupées :</span>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="flex items-center gap-1" style="border-right: 1px solid var(--border); padding-right: 10px; margin-right: 4px;">
                                <i class="fa-solid fa-user-gear text-muted" title="Affecter un technicien"></i>
                                <select id="bulkTechSelect" class="form-control form-control-sm" style="min-width: 170px; height: 32px; padding: 2px 8px;">
                                    <option value="">— Non affecté —</option>
                                    <?php foreach ($technicians as $tech): ?>
                                        <option value="<?php echo $tech['technician_id']; ?>">
                                            <?php echo htmlspecialchars($tech['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-sm btn-primary" onclick="bulkAssignTechnician()">
                                    <i class="fa-solid fa-user-check"></i> Affecter
                                </button>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" onclick="bulkUpdateScan('Scanné')">
                                <i class="fa-solid fa-check"></i> Passer en "Scanné"
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="bulkUpdateScan('Non scanné')">
                                <i class="fa-solid fa-xmark"></i> Passer en "Non scanné"
                            </button>
                            <button type="button" class="btn btn-sm" style="background: transparent; border: 1px solid var(--border);" onclick="deselectAll()" title="Annuler la sélection">
                                <i class="fa-solid fa-rotate-left"></i> Annuler
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="installTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" title="Tout sélectionner">
                                    </th>
                                    <th>Date</th>
                                    <th>Heure de venir</th>
                                    <th>Zone</th>
                                    <th>Nature</th>
                                    <th>Statut</th>
                                    <th>GEPON</th>
                                    <th>Scan</th>
                                    <th>Technicien</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($installations)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center p-8">Aucun OT trouvé pour ces critères.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($installations as $inst): ?>
                                        <tr class="table-row">
                                            <td style="text-align: center;">
                                                <input type="checkbox" class="select-inst" value="<?php echo $inst['id']; ?>" onchange="updateBulkActionState()">
                                            </td>
                                            <td><?php echo formatDate($inst['date_intervention']); ?></td>
                                            <td>
                                                <?php if (!empty($inst['temp_de_venir'])): ?>
                                                    <?php // Colonne TIME : on n'affiche pas les secondes. ?>
                                                    <span class="font-medium"><?php echo htmlspecialchars(substr((string) $inst['temp_de_venir'], 0, 5)); ?></span>
                                                <?php else: ?>
                                                    <span class="text-xs text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($inst['zone']); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($inst['nature_ot']); ?></span></td>
                                             <td>
                                                 <span class="badge <?php echo getStatusBadgeClass($inst['etat']); ?>">
                                                     <i class="fa-solid fa-circle"></i> <?php echo getStatusBadgeText($inst['etat']); ?>
                                                 </span>
                                                 <?php if (!empty($inst['commentaire'])): ?>
                                                     <div class="text-xs text-danger font-medium mt-1" style="max-width: 150px; white-space: normal;" title="<?php echo htmlspecialchars($inst['commentaire']); ?>">
                                                         <i class="fa-solid fa-comment-dots"></i> <?php echo htmlspecialchars($inst['commentaire']); ?>
                                                     </div>
                                                 <?php endif; ?>
                                             </td>
                                            <td>
                                                <code class="text-xs px-2 py-1 bg-surface-2 rounded border border-border">
                                                    <?php echo htmlspecialchars($inst['Gepon'] ?: '-'); ?>
                                                </code>
                                            </td>
                                            <td>
                                                <?php if (($inst['scan'] ?? '') === 'Scanné'): ?>
                                                     <span class="badge badge-success">Scanné</span>
                                                 <?php else: ?>
                                                     <span class="badge badge-secondary">Non scanné</span>
                                                 <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <div class="avatar-xs" style="width: 20px; height: 20px; font-size: 0.6rem;">
                                                        <?php echo $inst['technician_name'] ? strtoupper(substr($inst['technician_name'], 0, 1)) : '?'; ?>
                                                    </div>
                                                    <span class="text-sm"><?php echo htmlspecialchars($inst['technician_name'] ?: 'Non affecté'); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex justify-end gap-2">
                                                    <button class="icon-btn" style="color: var(--primary);" onclick='editInstall(<?php echo json_encode($inst); ?>)' title="Modifier">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button class="icon-btn" style="color: var(--danger);" onclick="deleteInstall(<?php echo $inst['id']; ?>)" title="Supprimer">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination p-4 flex items-center justify-between border-top">
                        <div class="text-sm text-muted">
                            Page <?php echo $page; ?> sur <?php echo $totalPages; ?> 
                            (<?php echo $totalRecords; ?> OT au total)
                        </div>
                        <div class="flex gap-2">
                            <!-- Premier / Précédent -->
                            <?php if ($page > 1): ?>
                                <a href="<?php echo buildPageUrl(1); ?>" class="btn btn-sm btn-secondary" title="Première page">
                                    <i class="fa-solid fa-angles-left"></i>
                                </a>
                                <a href="<?php echo buildPageUrl($page - 1); ?>" class="btn btn-sm btn-secondary">
                                    <i class="fa-solid fa-chevron-left"></i> Précédent
                                </a>
                            <?php endif; ?>

                            <!-- Numéros de page (limités) -->
                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);
                            
                            if ($start > 1) echo '<span class="px-2">...</span>';
                            
                            for ($i = $start; $i <= $end; $i++): ?>
                                <a href="<?php echo buildPageUrl($i); ?>" 
                                   class="btn btn-sm <?php echo $page == $i ? 'btn-primary' : 'btn-secondary'; ?>"
                                   style="min-width: 35px;">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;
                            
                            if ($end < $totalPages) echo '<span class="px-2">...</span>';
                            ?>

                            <!-- Suivant / Dernier -->
                            <?php if ($page < $totalPages): ?>
                                <a href="<?php echo buildPageUrl($page + 1); ?>" class="btn btn-sm btn-secondary">
                                    Suivant <i class="fa-solid fa-chevron-right"></i>
                                </a>
                                <a href="<?php echo buildPageUrl($totalPages); ?>" class="btn btn-sm btn-secondary" title="Dernière page">
                                    <i class="fa-solid fa-angles-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Installation -->
    <div id="installModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 class="modal-title">Détails de l'OT</h3>
                <button type="button" class="close-btn" onclick="closeModal('installModal')">&times;</button>
            </div>
            <form id="installForm" onsubmit="handleInstallSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="installId" name="id" value="">

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label>Nom du Client</label>
                            <input type="text" id="installNom" name="nom" placeholder="Nom Complet">
                        </div>
                        <div class="form-group">
                            <label>Numéro Client</label>
                            <input type="text" id="installNumero" name="numero_client" placeholder="ID Client">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label>Date Intervention</label>
                            <input type="date" id="installDate" name="date_intervention" required>
                        </div>
                        <div class="form-group">
                            <label>Date Réalisation</label>
                            <input type="date" id="installDateRealise" name="date_realise">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="form-group">
                            <label>Zone</label>
                            <input type="text" id="installZone" name="zone" required>
                        </div>
                        <div class="form-group">
                            <label>Port</label>
                            <input type="text" id="installPort" name="port">
                        </div>
                        <div class="form-group">
                            <label>GEPON</label>
                            <input type="text" id="installGepon" name="Gepon">
                        </div>
                        <div class="form-group" id="scanFieldGroup" style="display: none;">
                            <label>Statut Scan</label>
                            <select id="installScan" name="scan">
                                <option value="Non scanné">Non scanné</option>
                                <option value="Scanné">Scanné</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="form-group">
                            <label>Nature OT</label>
                            <input type="text" id="installNature" name="nature_ot" required>
                        </div>
                        <div class="form-group">
                            <label>Technicien</label>
                            <select id="installTech" name="technician_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?php echo $tech['technician_id']; ?>">
                                        <?php echo htmlspecialchars($tech['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Statut</label>
                            <select id="installEtat" name="etat">
                                <option value="encoure">En cours</option>
                                <option value="realise">Réalisé</option>
                                <option value="retard">En retard</option>
                                <option value="negative">Négatif</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="retardCommentGroup" style="display: none; background: rgba(239, 68, 68, 0.08); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--danger);">
                        <label class="font-bold text-danger">
                            <i class="fa-solid fa-triangle-exclamation"></i> Motif / Résultat (Tous Retards & DRG)
                        </label>
                        <textarea id="installCommentaire" name="commentaire" rows="2" placeholder="Saisissez la raison ou le résultat..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Commentaires / Notes Générales</label>
                        <textarea id="installComments" name="commentaire_temp_de_realise" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('installModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.select-inst');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateBulkActionState();
        }

        function updateBulkActionState() {
            const checkboxes = document.querySelectorAll('.select-inst');
            const checked = document.querySelectorAll('.select-inst:checked');
            const bulkBar = document.getElementById('bulkActionBar');
            const countBadge = document.getElementById('selectedCountBadge');
            const master = document.getElementById('selectAll');

            if (checked.length > 0) {
                bulkBar.style.display = 'flex';
                countBadge.textContent = checked.length + ' sélectionné(s)';
            } else {
                bulkBar.style.display = 'none';
            }

            if (checkboxes.length > 0 && checked.length === checkboxes.length) {
                master.checked = true;
                master.indeterminate = false;
            } else if (checked.length > 0) {
                master.checked = false;
                master.indeterminate = true;
            } else {
                master.checked = false;
                master.indeterminate = false;
            }
        }

        function deselectAll() {
            const master = document.getElementById('selectAll');
            if (master) master.checked = false;
            document.querySelectorAll('.select-inst').forEach(cb => cb.checked = false);
            updateBulkActionState();
        }

        function bulkUpdateScan(scanValue) {
            const checkedNodes = document.querySelectorAll('.select-inst:checked');
            const ids = Array.from(checkedNodes).map(cb => parseInt(cb.value, 10));

            if (ids.length === 0) {
                showAlert('Veuillez sélectionner au moins une installation.', 'warning');
                return;
            }

            if (confirm(`Voulez-vous vraiment passer ${ids.length} installation(s) au statut Scan "${scanValue}" ?`)) {
                apiCall('../api/installations/bulk_update_scan.php', 'POST', { ids: ids, scan: scanValue })
                    .then(response => {
                        showAlert(response.message || 'Mise à jour effectuée avec succès', 'success');
                        setTimeout(() => location.reload(), 600);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function bulkAssignTechnician() {
            const ids = Array.from(document.querySelectorAll('.select-inst:checked'))
                             .map(cb => parseInt(cb.value, 10));

            if (ids.length === 0) {
                showAlert('Veuillez sélectionner au moins une installation.', 'warning');
                return;
            }

            const select   = document.getElementById('bulkTechSelect');
            const techId   = select.value;
            const techName = techId ? select.options[select.selectedIndex].text.trim() : 'Non affecté';

            const question = techId
                ? `Affecter ${ids.length} OT au technicien "${techName}" ?`
                : `Retirer l'affectation de ${ids.length} OT ?`;

            if (!confirm(question)) return;

            apiCall('../api/installations/bulk_assign_technician.php', 'POST', {
                ids: ids,
                technician_id: techId
            })
                .then(response => {
                    showAlert(response.message || 'Affectation effectuée', 'success');
                    setTimeout(() => location.reload(), 600);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }

        function editInstall(inst) {
            document.getElementById('installId').value       = inst.id || '';
            document.getElementById('installNom').value      = inst.nom || '';
            document.getElementById('installNumero').value   = inst.numero_client || '';
            document.getElementById('installDate').value     = inst.date_intervention || '';
            document.getElementById('installDateRealise').value = inst.date_realise || '';
            document.getElementById('installPort').value     = inst.port || '';
            document.getElementById('installZone').value     = inst.zone || '';
            document.getElementById('installGepon').value    = inst.Gepon || '';
            document.getElementById('installScan').value     = inst.scan || '';
            document.getElementById('installNature').value   = inst.nature_ot || '';
            
            toggleScanField(inst.nature_ot);
            
            document.getElementById('installTech').value     = inst.technician_id || '';
            document.getElementById('installEtat').value     = inst.etat || '';
            document.getElementById('installComments').value = inst.commentaire_temp_de_realise || '';
            document.getElementById('installCommentaire').value = inst.commentaire || '';
            
            toggleRetardCommentField();

            document.querySelector('#installModal .modal-title').textContent = 'Détails de l\'OT #' + inst.id;
            openModal('installModal');
        }

        function toggleRetardCommentField() {
            const nature = (document.getElementById('installNature').value || '').trim().toUpperCase();
            const etat = document.getElementById('installEtat').value;
            const group = document.getElementById('retardCommentGroup');

            if (etat === 'retard' || nature === 'DRG') {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        }

        document.getElementById('installNature').addEventListener('input', toggleRetardCommentField);
        document.getElementById('installEtat').addEventListener('change', toggleRetardCommentField);

        function deleteInstall(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet OT ?')) {
                apiCall('../api/installations/delete.php', 'POST', { id: id })
                    .then(() => {
                        showAlert('OT supprimé avec succès', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function toggleScanField(nature) {
            const group = document.getElementById('scanFieldGroup');
            if (nature && (nature.toUpperCase() === 'CPL' || nature.toUpperCase() === 'CST')) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        }

        document.getElementById('installNature').addEventListener('input', function(e) {
            toggleScanField(e.target.value);
        });

        function handleInstallSubmit(event) {
            event.preventDefault();
            const form     = document.getElementById('installForm');
            const formData = new FormData(form);
            const data     = Object.fromEntries(formData);
            const endpoint = data.id ? '../api/installations/update.php' : '../api/installations/create.php';

            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('OT mis à jour avec succès', 'success');
                    closeModal('installModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }
    </script>
</body>
</html>
