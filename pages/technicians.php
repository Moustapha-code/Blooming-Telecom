<?php
/**
 * Technicians Management Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get technicians
$stmt = $pdo->query('SELECT * FROM technicians ORDER BY technician_id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
$technicians = $stmt->fetchAll();

$stmt = $pdo->query('SELECT COUNT(*) as count FROM technicians');
$totalTechnicians = $stmt->fetch()['count'];
$totalPages = ceil($totalTechnicians / $limit);

// Get zones for dropdown
$stmt = $pdo->query('SELECT * FROM zones ORDER BY zone_name');
$zones = $stmt->fetchAll();

// Get cars for dropdown (if needed)
$stmt = $pdo->query('SELECT * FROM car ORDER BY matricule');
$cars = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Techniciens - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css">
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('technicians.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Équipe Technique</h2>
                        <p class="page-subtitle">Gérez vos techniciens et leurs affectations.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('techModal')">
                        <i class="fa-solid fa-user-plus"></i> Ajouter un Technicien
                    </button>
                </div>

                <!-- Stats Summary -->
                <div class="kpi-grid mb-6">
                    <div class="kpi-card">
                        <div class="kpi-label">Total Techniciens</div>
                        <div class="kpi-value"><?php echo $totalTechnicians; ?></div>
                        <p class="page-subtitle">Effectif total enregistré</p>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Zones Couvertes</div>
                        <div class="kpi-value text-blue-500"><?php echo count($zones); ?></div>
                        <p class="page-subtitle">Zones actives</p>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Flotte Véhicules</div>
                        <div class="kpi-value text-orange-500"><?php echo count($cars); ?></div>
                        <p class="page-subtitle">Véhicules disponibles</p>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Liste des Techniciens</h3>
                        <div class="search-container" style="width: 250px;">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                            <input type="text" id="searchTech" placeholder="Rechercher...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="techTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Technicien</th>
                                    <th>Téléphone</th>
                                    <th>Spécialité</th>
                                    <th>Zone</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($technicians as $tech): ?>
                                    <tr class="table-row">
                                        <td class="font-bold text-muted">#<?php echo htmlspecialchars($tech['technician_id']); ?></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar"><?php echo strtoupper(substr($tech['name'], 0, 1)); ?></div>
                                                <div>
                                                    <div class="font-bold"><?php echo htmlspecialchars($tech['name']); ?></div>
                                                    <div class="text-xs text-muted"><?php echo htmlspecialchars($tech['email'] ?? 'Pas d\'email'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($tech['phone'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($tech['specialty'] ?? 'Généraliste'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($tech['zone'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="icon-btn" style="color: var(--primary);" onclick="editTech(<?php echo htmlspecialchars(json_encode($tech)); ?>)" title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteTech(<?php echo $tech['technician_id']; ?>)" title="Supprimer">
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

    <!-- Technician Modal -->
    <div id="techModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fiche Technicien</h3>
                <button type="button" class="close-btn" onclick="closeModal('techModal')">&times;</button>
            </div>
            <form id="techForm" onsubmit="handleTechSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="techId" name="technician_id" value="">
                    
                    <div class="form-group">
                        <label for="techName">Nom Complet *</label>
                        <input type="text" id="techName" name="name" required placeholder="Ex: Jean Dupont">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="techPhone">Téléphone</label>
                            <input type="text" id="techPhone" name="phone" placeholder="06 XX XX XX XX">
                        </div>
                        <div class="form-group">
                            <label for="techEmail">Email</label>
                            <input type="email" id="techEmail" name="email" placeholder="jean.dupont@example.com">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="techSpecialty">Spécialité</label>
                            <input type="text" id="techSpecialty" name="specialty" placeholder="Ex: Fibre, ADSL...">
                        </div>
                        <div class="form-group">
                            <label for="techZone">Zone Affectée</label>
                            <select id="techZone" name="zone">
                                <option value="">Choisir une zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo htmlspecialchars($zone['zone_name']); ?>">
                                        <?php echo htmlspecialchars($zone['zone_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('techModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editTech(tech) {
            document.getElementById('techId').value = tech.technician_id || '';
            document.getElementById('techName').value = tech.name || '';
            document.getElementById('techPhone').value = tech.phone || '';
            document.getElementById('techEmail').value = tech.email || '';
            document.getElementById('techSpecialty').value = tech.specialty || '';
            document.getElementById('techZone').value = tech.zone || '';
            
            document.querySelector('#techModal .modal-title').textContent = 'Modifier le Technicien';
            openModal('techModal');
        }

        function deleteTech(id) {
            if (confirm('Voulez-vous vraiment supprimer ce technicien ? Cette action est irréversible.')) {
                apiCall('../api/technicians/delete.php', 'POST', { technician_id: id })
                    .then(() => {
                        showAlert('Technicien supprimé avec succès', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleTechSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('techForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.technician_id ? '../api/technicians/update.php' : '../api/technicians/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('Technicien enregistré avec succès', 'success');
                    closeModal('techModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }

        // Search functionality
        document.getElementById('searchTech').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#techTable tbody .table-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
