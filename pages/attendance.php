<?php
/**
 * Attendance Management Page
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query with filters
$where = [];
$params = [];

if (!empty($_GET['date'])) {
    $where[] = 'a.date = ?';
    $params[] = $_GET['date'];
}

if (!empty($_GET['technician_id'])) {
    $where[] = 'a.technician_id = ?';
    $params[] = $_GET['technician_id'];
}

if (!empty($_GET['status'])) {
    $where[] = 'a.status = ?';
    $params[] = $_GET['status'];
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare('
    SELECT a.*, t.name as technician_name 
    FROM attendance a 
    LEFT JOIN technicians t ON a.technician_id = t.technician_id
    ' . $whereClause . '
    ORDER BY a.date DESC, a.attendance_id DESC
    LIMIT ' . $limit . ' OFFSET ' . $offset
);
$stmt->execute($params);
$attendance = $stmt->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) as count FROM attendance ' . $whereClause);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['count'];
$totalPages = ceil($totalRecords / $limit);

$stmt = $pdo->query('SELECT * FROM technicians ORDER BY name');
$technicians = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présence Techniciens - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <?php require_once __DIR__ . '/../components/pwa.php'; renderPwaHead(); ?>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('attendance.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Gestion des Présences</h2>
                        <p class="page-subtitle">Suivi quotidien des pointages techniciens.</p>
                    </div>
                    <div class="flex gap-4">
                        <button class="btn btn-secondary" onclick="exportToCSV('attendanceTable', 'presence_<?php echo date('Y-m-d'); ?>')">
                            <i class="fa-solid fa-download"></i> Export CSV
                        </button>
                        <button class="btn btn-primary" onclick="openModal('attendanceModal')">
                            <i class="fa-solid fa-plus"></i> Ajouter une Présence
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-6" style="background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div class="form-group mb-0">
                            <label>Date</label>
                            <input type="date" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label>Technicien</label>
                            <select name="technician_id">
                                <option value="">Tous les techniciens</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?php echo $tech['technician_id']; ?>" <?php echo isset($_GET['technician_id']) && $_GET['technician_id'] == $tech['technician_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tech['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label>Statut</label>
                            <select name="status">
                                <option value="">Tous les statuts</option>
                                <option value="Present" <?php echo ($_GET['status'] ?? '') === 'Present' ? 'selected' : ''; ?>>Présent</option>
                                <option value="Absent" <?php echo ($_GET['status'] ?? '') === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary w-full">Filtrer</button>
                            <a href="attendance.php" class="btn btn-secondary"><i class="fa-solid fa-rotate"></i></a>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Registres de Présence</h3>
                        <div class="text-muted text-sm"><?php echo $totalRecords; ?> enregistrements trouvés</div>
                    </div>
                    <div class="table-responsive">
                        <table id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Technicien</th>
                                    <th>Arrivée</th>
                                    <th>Départ</th>
                                    <th>Statut</th>
                                    <th>Notes</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $record): ?>
                                    <tr class="table-row">
                                        <td class="font-bold"><?php echo formatDate($record['date']); ?></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar"><?php echo strtoupper(substr($record['technician_name'] ?? '?', 0, 1)); ?></div>
                                                <?php echo htmlspecialchars($record['technician_name'] ?? '-'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['check_in_time'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($record['check_out_time'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadgeClass($record['status']); ?>">
                                                <i class="fa-solid fa-circle"></i> <?php echo getStatusBadgeText($record['status']); ?>
                                            </span>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($record['notes']); ?>">
                                            <?php echo htmlspecialchars(strlen($record['notes']) > 30 ? substr($record['notes'], 0, 30) . '...' : ($record['notes'] ?: '-')); ?>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="icon-btn" style="color: var(--primary);" onclick="editAttendance(<?php echo htmlspecialchars(json_encode($record)); ?>)" title="Modifier">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteAttendance(<?php echo $record['attendance_id']; ?>)" title="Supprimer">
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
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                               class="btn btn-sm <?php echo $page == $i ? 'btn-primary' : 'btn-secondary'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Attendance Modal -->
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Enregistrement Présence</h3>
                <button type="button" class="close-btn" onclick="closeModal('attendanceModal')">&times;</button>
            </div>
            <form id="attendanceForm" onsubmit="handleAttendanceSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="attendanceId" name="attendance_id" value="">
                    
                    <div class="form-group">
                        <label for="attTech">Technicien *</label>
                        <select id="attTech" name="technician_id" required>
                            <option value="">Sélectionner un technicien</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['technician_id']; ?>"><?php echo htmlspecialchars($tech['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="attDate">Date *</label>
                            <input type="date" id="attDate" name="date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="attStatus">Statut</label>
                            <select id="attStatus" name="status">
                                <option value="Present">Présent</option>
                                <option value="Absent">Absent</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="attCheckIn">Heure d'arrivée</label>
                            <input type="time" id="attCheckIn" name="check_in_time">
                        </div>
                        <div class="form-group">
                            <label for="attCheckOut">Heure de départ</label>
                            <input type="time" id="attCheckOut" name="check_out_time">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="attNotes">Notes / Remarques</label>
                        <textarea id="attNotes" name="notes" rows="3" placeholder="Informations complémentaires..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('attendanceModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editAttendance(record) {
            document.getElementById('attendanceId').value = record.attendance_id || '';
            document.getElementById('attTech').value = record.technician_id || '';
            document.getElementById('attDate').value = record.date || '';
            document.getElementById('attCheckIn').value = record.check_in_time || '';
            document.getElementById('attCheckOut').value = record.check_out_time || '';
            document.getElementById('attStatus').value = record.status || '';
            document.getElementById('attNotes').value = record.notes || '';
            
            document.querySelector('#attendanceModal .modal-title').textContent = 'Modifier la Présence';
            openModal('attendanceModal');
        }

        function deleteAttendance(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet enregistrement ?')) {
                apiCall('../api/attendance/delete.php', 'POST', { attendance_id: id })
                    .then(() => {
                        showAlert('Enregistrement supprimé avec succès', 'success');
                        setTimeout(() => location.reload(), 1000);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleAttendanceSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('attendanceForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.attendance_id ? '../api/attendance/update.php' : '../api/attendance/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(() => {
                    showAlert('Données enregistrées avec succès', 'success');
                    closeModal('attendanceModal');
                    setTimeout(() => location.reload(), 1000);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }
    </script>
</body>
</html>
