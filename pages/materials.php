<?php
/**
 * Materials & Inventory Management Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$stmt = $pdo->query('
    SELECT * FROM materials 
    ORDER BY material_id DESC 
    LIMIT ' . $limit . ' OFFSET ' . $offset
);
$materials = $stmt->fetchAll();

$stmt = $pdo->query('SELECT COUNT(*) as count FROM materials');
$totalMaterials = $stmt->fetch()['count'];
$totalPages = ceil($totalMaterials / $limit);

$stmt = $pdo->query('SELECT COUNT(*) as count FROM materials WHERE stock_quantity < 10');
$lowStockCount = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaire Matériel - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <?php require_once __DIR__ . '/../components/pwa.php'; renderPwaHead(); ?>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('materials.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Gestion de l'Inventaire</h2>
                        <p class="page-subtitle">Suivez le stock de matériel et consommables.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('materialModal')">
                        <i class="fa-solid fa-plus"></i> Ajouter un Matériel
                    </button>
                </div>

                <?php if ($lowStockCount > 0): ?>
                    <div class="badge badge-danger mb-4 w-full justify-between" style="padding: 12px 20px; border-radius: var(--radius-md);">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Attention : <strong><?php echo $lowStockCount; ?></strong> article(s) sont en stock critique (moins de 10 unités).</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- KPI Quick Stats -->
                <div class="kpi-grid mb-6">
                    <div class="kpi-card">
                        <div class="kpi-label">Articles Totaux</div>
                        <div class="kpi-value"><?php echo $totalMaterials; ?></div>
                        <p class="page-subtitle">Références en catalogue</p>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Stock Faible</div>
                        <div class="kpi-value text-danger"><?php echo $lowStockCount; ?></div>
                        <p class="page-subtitle">Nécessitent une commande</p>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Catalogue Matériel</h3>
                        <div class="search-container" style="width: 250px;">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                            <input type="text" id="searchMaterial" placeholder="Filtrer...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="materialsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Matériel</th>
                                    <th>Description</th>
                                    <th>Unité</th>
                                    <th>Stock</th>
                                    <th>Statut</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $mat): ?>
                                    <tr class="table-row">
                                        <td class="font-bold text-muted">#<?php echo htmlspecialchars($mat['material_id']); ?></td>
                                        <td>
                                            <div class="font-bold"><?php echo htmlspecialchars($mat['name']); ?></div>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($mat['description'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(strlen($mat['description'] ?? '') > 40 ? substr($mat['description'], 0, 40) . '...' : ($mat['description'] ?: '-')); ?>
                                        </td>
                                        <td><span class="badge badge-secondary"><?php echo htmlspecialchars($mat['unit'] ?? 'Unité'); ?></span></td>
                                        <td class="font-bold <?php echo $mat['stock_quantity'] < 10 ? 'text-danger' : ''; ?>">
                                            <?php echo $mat['stock_quantity']; ?>
                                        </td>
                                        <td>
                                            <?php if ($mat['stock_quantity'] < 10): ?>
                                                <span class="badge badge-danger"><i class="fa-solid fa-arrow-trend-down"></i> Critique</span>
                                            <?php else: ?>
                                                <span class="badge badge-success"><i class="fa-solid fa-check"></i> En Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="icon-btn" style="color: var(--primary);" onclick="editMaterial(<?php echo htmlspecialchars(json_encode($mat)); ?>)" title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteMaterial(<?php echo $mat['material_id']; ?>)" title="Supprimer">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination p-4 flex justify-center gap-2 border-top">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="btn btn-sm <?php echo $page == $i ? 'btn-primary' : 'btn-secondary'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Material Modal -->
    <div id="materialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fiche Matériel</h3>
                <button type="button" class="close-btn" onclick="closeModal('materialModal')">&times;</button>
            </div>
            <form id="materialForm" onsubmit="handleMaterialSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="materialId" name="material_id" value="">
                    
                    <div class="form-group">
                        <label for="matName">Désignation *</label>
                        <input type="text" id="matName" name="name" required placeholder="Ex: Jumper Fibre 3m">
                    </div>

                    <div class="form-group">
                        <label for="matDesc">Description</label>
                        <textarea id="matDesc" name="description" rows="3" placeholder="Caractéristiques techniques..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="matUnit">Unité de mesure</label>
                            <input type="text" id="matUnit" name="unit" placeholder="Ex: Pcs, Mètres, Kg">
                        </div>
                        <div class="form-group">
                            <label for="matStock">Quantité en Stock</label>
                            <input type="number" id="matStock" name="stock_quantity" min="0" value="0">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('materialModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editMaterial(mat) {
            document.getElementById('materialId').value = mat.material_id || '';
            document.getElementById('matName').value = mat.name || '';
            document.getElementById('matDesc').value = mat.description || '';
            document.getElementById('matUnit').value = mat.unit || '';
            document.getElementById('matStock').value = mat.stock_quantity || 0;
            
            document.querySelector('#materialModal .modal-title').textContent = 'Modifier le Matériel';
            openModal('materialModal');
        }

        function deleteMaterial(id) {
            if (confirm('Voulez-vous vraiment supprimer cet article de l\'inventaire ?')) {
                apiCall('../api/materials/delete.php', 'POST', { material_id: id })
                    .then(() => {
                        showAlert('Article supprimé avec succès', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleMaterialSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('materialForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.material_id ? '../api/materials/update.php' : '../api/materials/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('Inventaire mis à jour', 'success');
                    closeModal('materialModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }

        // Search functionality
        document.getElementById('searchMaterial').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#materialsTable tbody .table-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
