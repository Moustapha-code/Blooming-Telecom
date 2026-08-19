<?php
/**
 * Technician Materials (Issued/Returned) Page - Modern Redesign
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
    SELECT tm.*, t.name as technician_name, m.name as material_name, c.matricule as car_number
    FROM technician_materials tm
    LEFT JOIN technicians t ON tm.technician_id = t.technician_id
    LEFT JOIN materials m ON tm.material_id = m.material_id
    LEFT JOIN car c ON tm.car_id = c.car_id
    ORDER BY tm.date_given DESC LIMIT ' . $limit . ' OFFSET ' . $offset
);
$materials = $stmt->fetchAll();

$stmt = $pdo->query('SELECT COUNT(*) as count FROM technician_materials');
$totalRecords = $stmt->fetch()['count'];
$totalPages = ceil($totalRecords / $limit);

$stmt = $pdo->query('SELECT * FROM technicians ORDER BY name');
$technicians = $stmt->fetchAll();

$stmt = $pdo->query('SELECT * FROM materials ORDER BY name');
$mats = $stmt->fetchAll();

$stmt = $pdo->query('SELECT * FROM car ORDER BY matricule');
$cars = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matériel Techniciens - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <?php require_once __DIR__ . '/../components/pwa.php'; renderPwaHead(); ?>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('technician-materials.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Mouvements de Matériel</h2>
                        <p class="page-subtitle">Suivi des consommables et outils par technicien.</p>
                    </div>
                    <div class="flex gap-4">
                        <button class="btn btn-secondary" onclick="exportToCSV('techMatTable', 'mouvements_materiel')">
                            <i class="fa-solid fa-file-export"></i> Exporter
                        </button>
                        <button class="btn btn-primary" onclick="openModal('techMatModal')">
                            <i class="fa-solid fa-hand-holding-hand"></i> Nouveau Mouvement
                        </button>
                    </div>
                </div>

                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Historique des Dotations</h3>
                    </div>
                    <div class="table-responsive">
                        <table id="techMatTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Technicien</th>
                                    <th>Matériel</th>
                                    <th>Donné</th>
                                    <th>Retourné</th>
                                    <th>Véhicule</th>
                                    <th>Zone</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $record): ?>
                                    <tr class="table-row">
                                        <td><?php echo formatDate($record['date_given']); ?></td>
                                        <td class="font-bold"><?php echo htmlspecialchars($record['technician_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($record['material_name'] ?? '-'); ?></td>
                                        <td><span class="badge badge-info"><?php echo $record['quantity_given'] ?? 0; ?></span></td>
                                        <td><span class="badge badge-success"><?php echo $record['quantity_returned'] ?? 0; ?></span></td>
                                        <td><?php echo htmlspecialchars($record['car_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($record['zone'] ?? '-'); ?></td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="icon-btn" style="color: var(--primary);" onclick="editTechMat(<?php echo htmlspecialchars(json_encode($record)); ?>)" title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteTechMat(<?php echo $record['usage_id']; ?>)" title="Supprimer">
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
                    <div class="pagination flex justify-center gap-2 mt-6">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="btn <?php echo $page == $i ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Tech Material Modal -->
    <div id="techMatModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fiche de Mouvement</h3>
                <button type="button" class="close-btn" onclick="closeModal('techMatModal')">&times;</button>
            </div>
            <form id="techMatForm" onsubmit="handleTechMatSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="techMatId" name="usage_id" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="tmTech">Technicien *</label>
                            <select id="tmTech" name="technician_id" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?php echo $tech['technician_id']; ?>"><?php echo htmlspecialchars($tech['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tmMaterial">Matériel *</label>
                            <select id="tmMaterial" name="material_id" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($mats as $mat): ?>
                                    <option value="<?php echo $mat['material_id']; ?>"><?php echo htmlspecialchars($mat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tmDate">Date</label>
                            <input type="date" id="tmDate" name="date_given" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="tmCar">Véhicule</label>
                            <select id="tmCar" name="car_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($cars as $car): ?>
                                    <option value="<?php echo $car['car_id']; ?>"><?php echo htmlspecialchars($car['matricule']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tmGiven">Quantité Donnée</label>
                            <input type="number" id="tmGiven" name="quantity_given" min="0" value="0">
                        </div>

                        <div class="form-group">
                            <label for="tmReturned">Quantité Retournée</label>
                            <input type="number" id="tmReturned" name="quantity_returned" min="0" value="0">
                        </div>

                        <div class="form-group">
                            <label for="tmZone">Zone d'intervention</label>
                            <input type="text" id="tmZone" name="zone" placeholder="ex: Zone A">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tmNotes">Commentaires / Notes</label>
                        <textarea id="tmNotes" name="notes" placeholder="Détails supplémentaires..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('techMatModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editTechMat(record) {
            document.getElementById('techMatId').value = record.usage_id || '';
            document.getElementById('tmTech').value = record.technician_id || '';
            document.getElementById('tmMaterial').value = record.material_id || '';
            document.getElementById('tmDate').value = record.date_given || '';
            document.getElementById('tmGiven').value = record.quantity_given || '';
            document.getElementById('tmReturned').value = record.quantity_returned || '';
            document.getElementById('tmCar').value = record.car_id || '';
            document.getElementById('tmZone').value = record.zone || '';
            document.getElementById('tmNotes').value = record.notes || '';
            
            document.querySelector('#techMatModal .modal-title').textContent = 'Modifier le Mouvement';
            openModal('techMatModal');
        }

        function deleteTechMat(id) {
            if (confirm('Voulez-vous vraiment supprimer cet enregistrement ?')) {
                apiCall('../api/technician-materials/delete.php', 'POST', { usage_id: id })
                    .then(() => {
                        showAlert('Enregistrement supprimé', 'success');
                        setTimeout(() => location.reload(), 1000);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleTechMatSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('techMatForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.usage_id ? '../api/technician-materials/update.php' : '../api/technician-materials/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('Enregistré avec succès', 'success');
                    closeModal('techMatModal');
                    setTimeout(() => location.reload(), 1000);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }
    </script>
</body>
</html>
