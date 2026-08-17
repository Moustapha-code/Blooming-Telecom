<?php
/**
 * Drivers Management Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get drivers
$stmt = $pdo->query('SELECT * FROM driver ORDER BY driver_id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
$drivers = $stmt->fetchAll();

$stmt = $pdo->query('SELECT COUNT(*) as count FROM driver');
$totalDrivers = $stmt->fetch()['count'];
$totalPages = ceil($totalDrivers / $limit);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chauffeurs - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('drivers.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Gestion des Chauffeurs</h2>
                        <p class="page-subtitle">Suivi et administration de votre personnel de conduite.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('driverModal')">
                        <i class="fa-solid fa-user-plus"></i> Ajouter un Chauffeur
                    </button>
                </div>

                <!-- Stats Summary -->
                <div class="kpi-grid mb-6">
                    <div class="kpi-card">
                        <div class="kpi-label">Total Chauffeurs</div>
                        <div class="kpi-value"><?php echo $totalDrivers; ?></div>
                        <p class="page-subtitle">Effectif total enregistré</p>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Liste des Chauffeurs</h3>
                        <div class="search-container" style="width: 250px;">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                            <input type="text" id="searchDriver" placeholder="Rechercher...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="driverTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Chauffeur</th>
                                    <th>Téléphone</th>
                                    <th>N° Permis</th>
                                    <th>Date Création</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($drivers)): ?>
                                    <tr><td colspan="6" class="text-center p-8">Aucun chauffeur enregistré.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($drivers as $d): ?>
                                        <tr class="table-row">
                                            <td class="font-bold text-muted">#<?php echo $d['driver_id']; ?></td>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <div class="avatar"><?php echo strtoupper(substr($d['name'], 0, 1)); ?></div>
                                                    <div class="font-bold"><?php echo htmlspecialchars($d['name']); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($d['phone'] ?: '-'); ?></td>
                                            <td>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($d['license_number'] ?: '-'); ?></span>
                                            </td>
                                            <td class="text-muted text-xs"><?php echo $d['created_at'] ?? '-'; ?></td>
                                            <td class="text-right">
                                                <div class="flex justify-end gap-2">
                                                    <button class="icon-btn" style="color: var(--primary);" onclick="editDriver(<?php echo htmlspecialchars(json_encode($d)); ?>)">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button class="icon-btn" style="color: var(--danger);" onclick="deleteDriver(<?php echo $d['driver_id']; ?>)">
                                                        <i class="fa-solid fa-trash"></i>
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

    <!-- Driver Modal -->
    <div id="driverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fiche Chauffeur</h3>
                <button type="button" class="close-btn" onclick="closeModal('driverModal')">&times;</button>
            </div>
            <form id="driverForm" onsubmit="handleDriverSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="driverId" name="driver_id" value="">
                    
                    <div class="form-group">
                        <label>Nom Complet *</label>
                        <input type="text" id="driverName" name="name" required placeholder="Ex: Mohamed Lemine">
                    </div>

                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" id="driverPhone" name="phone" placeholder="Ex: 44112233">
                    </div>

                    <div class="form-group">
                        <label>Numéro de Permis</label>
                        <input type="text" id="driverLicense" name="license_number" placeholder="Ex: P-12345678">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('driverModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editDriver(d) {
            document.getElementById('driverId').value = d.driver_id || '';
            document.getElementById('driverName').value = d.name || '';
            document.getElementById('driverPhone').value = d.phone || '';
            document.getElementById('driverLicense').value = d.license_number || '';
            
            document.querySelector('#driverModal .modal-title').textContent = 'Modifier le Chauffeur';
            openModal('driverModal');
        }

        function deleteDriver(id) {
            if (confirm('Voulez-vous vraiment supprimer ce chauffeur ?')) {
                apiCall('../api/drivers/delete.php', 'POST', { driver_id: id })
                    .then(() => {
                        showAlert('Chauffeur supprimé avec succès', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleDriverSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('driverForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.driver_id ? '../api/drivers/update.php' : '../api/drivers/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('Chauffeur enregistré avec succès', 'success');
                    closeModal('driverModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }

        // Search functionality
        document.getElementById('searchDriver').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#driverTable tbody .table-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
