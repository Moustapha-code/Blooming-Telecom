<?php
/**
 * Zones Management Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$stmt = $pdo->query('SELECT * FROM zones ORDER BY zone_name');
$zones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Zones - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('zones.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Zones d'Intervention</h2>
                        <p class="page-subtitle">Définissez les secteurs géographiques d'activité.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('zoneModal')">
                        <i class="fa-solid fa-map-location-dot"></i> Ajouter une Zone
                    </button>
                </div>

                <!-- Data Table -->
                <div class="card-table" style="max-width: 800px;">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Répertoire des Zones</h3>
                    </div>
                    <div class="table-responsive">
                        <table id="zonesTable">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Nom de la Zone</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zones as $zone): ?>
                                    <tr class="table-row">
                                        <td class="font-bold text-muted">#<?php echo htmlspecialchars($zone['zone_id']); ?></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="kpi-icon blue" style="width: 32px; height: 32px; font-size: 1rem;">
                                                    <i class="fa-solid fa-location-dot"></i>
                                                </div>
                                                <span class="font-bold"><?php echo htmlspecialchars($zone['zone_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="icon-btn" style="color: var(--primary);" onclick="editZone(<?php echo htmlspecialchars(json_encode($zone)); ?>)" title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteZone(<?php echo $zone['zone_id']; ?>)" title="Supprimer">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
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

    <!-- Zone Modal -->
    <div id="zoneModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fiche Zone</h3>
                <button type="button" class="close-btn" onclick="closeModal('zoneModal')">&times;</button>
            </div>
            <form id="zoneForm" onsubmit="handleZoneSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="zoneId" name="zone_id" value="">
                    
                    <div class="form-group">
                        <label for="zoneName">Nom de la Zone *</label>
                        <input type="text" id="zoneName" name="zone_name" required placeholder="Ex: Casablanca - Maârif">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('zoneModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editZone(zone) {
            document.getElementById('zoneId').value = zone.zone_id || '';
            document.getElementById('zoneName').value = zone.zone_name || '';
            
            document.querySelector('#zoneModal .modal-title').textContent = 'Modifier la Zone';
            openModal('zoneModal');
        }

        function deleteZone(id) {
            if (confirm('Voulez-vous vraiment supprimer cette zone ?')) {
                apiCall('../api/zones/delete.php', 'POST', { zone_id: id })
                    .then(() => {
                        showAlert('Zone supprimée avec succès', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleZoneSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('zoneForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.zone_id ? '../api/zones/update.php' : '../api/zones/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('Zone enregistrée avec succès', 'success');
                    closeModal('zoneModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }
    </script>
</body>
</html>
