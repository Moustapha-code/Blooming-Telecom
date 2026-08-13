<?php
/**
 * Cars & Drivers Fleet Management Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get cars with driver info
$stmt = $pdo->query('
    SELECT c.*, d.name as driver_name, d.phone as driver_phone
    FROM car c
    LEFT JOIN driver d ON c.driver_id = d.driver_id
    ORDER BY c.car_id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
);
$cars = $stmt->fetchAll();

$stmt = $pdo->query('SELECT COUNT(*) as count FROM car');
$totalCars = $stmt->fetch()['count'];
$totalPages = ceil($totalCars / $limit);

$stmt = $pdo->query('SELECT * FROM driver ORDER BY name');
$drivers = $stmt->fetchAll();

// Get maintenance logs
$stmt = $pdo->query('
    SELECT m.*, c.matricule 
    FROM car_maintenance m 
    JOIN car c ON m.car_id = c.car_id 
    ORDER BY m.date_maintenance DESC
');
$maintenanceLogs = $stmt->fetchAll();

// Calculate total maintenance cost
$totalMaintenance = 0;
foreach ($maintenanceLogs as $log) {
    $totalMaintenance += $log['cost'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de Flotte - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css">
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('cars.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Gestion de Flotte</h2>
                        <p class="page-subtitle">Suivi des véhicules et des chauffeurs.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('carModal')">
                        <i class="fa-solid fa-car"></i> Ajouter un Véhicule
                    </button>
                </div>

                <!-- KPI Quick Stats -->
                <div class="kpi-grid mb-6">
                    <div class="kpi-card">
                        <div class="kpi-label">Véhicules Totaux</div>
                        <div class="kpi-value"><?php echo $totalCars; ?></div>
                        <p class="page-subtitle">Unités en service</p>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Maintenance</div>
                        <div class="kpi-value text-orange-500"><?php echo number_format($totalMaintenance, 0, ',', ' '); ?> MRU</div>
                        <p class="page-subtitle">Dépenses totales</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tab-group mb-6">
                    <button class="tab-btn active" onclick="switchTab('cars')">Véhicules</button>
                    <button class="tab-btn" onclick="switchTab('maintenance')">Entretien & Frais</button>
                </div>

                <!-- Data Table -->
                <div id="view_cars" class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Liste des Véhicules</h3>
                        <div class="search-container" style="width: 250px;">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                            <input type="text" id="searchCar" placeholder="Rechercher...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="carsTable">
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Véhicule</th>
                                    <th>Chauffeur</th>
                                    <th>Contact</th>
                                    <th>Statut</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                    <tr class="table-row">
                                        <td class="font-bold text-primary"><?php echo htmlspecialchars($car['matricule']); ?></td>
                                        <td>
                                            <div class="font-bold"><?php echo htmlspecialchars($car['brand'] ?? '-'); ?></div>
                                            <div class="text-xs text-muted"><?php echo htmlspecialchars($car['model'] ?? '-'); ?></div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-user-tie text-muted"></i>
                                                <?php echo htmlspecialchars($car['driver_name'] ?? 'Non affecté'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($car['driver_phone'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($car['driver_id']): ?>
                                                <span class="badge badge-success"><i class="fa-solid fa-check"></i> Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning"><i class="fa-solid fa-pause"></i> Disponible</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="icon-btn" style="color: var(--primary);" onclick="editCar(<?php echo htmlspecialchars(json_encode($car)); ?>)" title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteCar(<?php echo $car['car_id']; ?>)" title="Supprimer">
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

                <!-- Maintenance View -->
                <div id="view_maintenance" class="card-table" style="display: none;">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Historique d'Entretien</h3>
                        <button class="btn btn-sm btn-primary" onclick="openModal('maintenanceModal')">
                            <i class="fa-solid fa-tools"></i> Nouvel Entretien
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="maintenanceTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Véhicule</th>
                                    <th>Description</th>
                                    <th class="text-right">Montant</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($maintenanceLogs)): ?>
                                    <tr><td colspan="5" class="text-center p-8 text-muted">Aucun entretien enregistré.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($maintenanceLogs as $log): ?>
                                        <tr class="table-row">
                                            <td class="font-bold"><?php echo $log['date_maintenance']; ?></td>
                                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($log['matricule']); ?></span></td>
                                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                                            <td class="text-right font-bold text-danger"><?php echo number_format($log['cost'], 2); ?> MRU</td>
                                            <td class="text-right">
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteMaintenance(<?php echo $log['id']; ?>)">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
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

    <!-- Car Modal -->
    <div id="carModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fiche Véhicule</h3>
                <button type="button" class="close-btn" onclick="closeModal('carModal')">&times;</button>
            </div>
            <form id="carForm" onsubmit="handleCarSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="carId" name="car_id" value="">
                    
                    <div class="form-group">
                        <label for="carMatricule">Matricule *</label>
                        <input type="text" id="carMatricule" name="matricule" required placeholder="Ex: 12345-A-15">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="carBrand">Marque</label>
                            <input type="text" id="carBrand" name="brand" placeholder="Ex: Renault">
                        </div>
                        <div class="form-group">
                            <label for="carModel">Modèle</label>
                            <input type="text" id="carModel" name="model" placeholder="Ex: Kangoo">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="carDriver">Chauffeur Affecté</label>
                        <select id="carDriver" name="driver_id">
                            <option value="">-- Non affecté --</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?php echo $driver['driver_id']; ?>">
                                    <?php echo htmlspecialchars($driver['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="carNotes">Notes / État du véhicule</label>
                        <textarea id="carNotes" name="notes" rows="3" placeholder="Informations complémentaires..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('carModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Maintenance Modal -->
    <div id="maintenanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nouvel Entretien</h3>
                <button type="button" class="close-btn" onclick="closeModal('maintenanceModal')">&times;</button>
            </div>
            <form id="maintenanceForm" onsubmit="handleMaintenanceSubmit(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Véhicule *</label>
                        <select name="car_id" required>
                            <option value="">-- Sélectionner un véhicule --</option>
                            <?php foreach ($cars as $c): ?>
                                <option value="<?php echo $c['car_id']; ?>"><?php echo htmlspecialchars($c['matricule']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="date_maintenance" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Montant (MRU) *</label>
                            <input type="number" name="cost" step="0.01" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" rows="3" required placeholder="Ex: Vidange, Changement pneus..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('maintenanceModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editCar(car) {
            document.getElementById('carId').value = car.car_id || '';
            document.getElementById('carMatricule').value = car.matricule || '';
            document.getElementById('carBrand').value = car.brand || '';
            document.getElementById('carModel').value = car.model || '';
            document.getElementById('carDriver').value = car.driver_id || '';
            document.getElementById('carNotes').value = car.notes || '';
            
            document.querySelector('#carModal .modal-title').textContent = 'Modifier le Véhicule';
            openModal('carModal');
        }

        function deleteCar(id) {
            if (confirm('Voulez-vous vraiment supprimer ce véhicule ?')) {
                apiCall('../api/cars/delete.php', 'POST', { car_id: id })
                    .then(() => {
                        showAlert('Véhicule supprimé avec succès', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleCarSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('carForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.car_id ? '../api/cars/update.php' : '../api/cars/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('Véhicule enregistré avec succès', 'success');
                    closeModal('carModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }

        function switchTab(tab) {
            document.getElementById('view_cars').style.display = tab === 'cars' ? 'block' : 'none';
            document.getElementById('view_maintenance').style.display = tab === 'maintenance' ? 'block' : 'none';
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('onclick').includes(tab));
            });
        }

        function handleMaintenanceSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('maintenanceForm'));
            const data = Object.fromEntries(formData);
            
            apiCall('../api/cars/maintenance_create.php', 'POST', data)
                .then(() => {
                    showAlert('Entretien enregistré', 'success');
                    closeModal('maintenanceModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }

        function deleteMaintenance(id) {
            if (confirm('Supprimer cet enregistrement ?')) {
                apiCall('../api/cars/maintenance_delete.php', 'POST', { id: id })
                    .then(() => {
                        showAlert('Enregistrement supprimé', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }
    </script>
</body>
</html>
