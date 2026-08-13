<?php
/**
 * Admin Users Management Page - Modern Redesign
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get admin users
$stmt = $pdo->query('SELECT * FROM admin_users ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
$users = $stmt->fetchAll();

$stmt = $pdo->query('SELECT COUNT(*) as count FROM admin_users');
$totalUsers = $stmt->fetch()['count'];
$totalPages = ceil($totalUsers / $limit);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs Admin - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css">
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('admin-users.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Gestion des Administrateurs</h2>
                        <p class="page-subtitle">Gérez les accès au tableau de bord.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('userModal')">
                        <i class="fa-solid fa-user-plus"></i> Nouvel Admin
                    </button>
                </div>

                <div class="card-table">
                    <div class="card-header">
                        <h3 class="section-title mb-0">Répertoire des Admins</h3>
                    </div>
                    <div class="table-responsive">
                        <table id="usersTable">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Nom d'utilisateur</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr class="table-row">
                                        <td class="font-bold text-muted">#<?php echo htmlspecialchars($user['id']); ?></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                </div>
                                                <span class="font-bold"><?php echo htmlspecialchars($user['username']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="icon-btn" style="color: var(--primary);" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Modifier">
                                                    <i class="fa-solid fa-user-pen"></i>
                                                </button>
                                                <button class="icon-btn" style="color: var(--danger);" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Supprimer">
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

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fiche Utilisateur</h3>
                <button type="button" class="close-btn" onclick="closeModal('userModal')">&times;</button>
            </div>
            <form id="userForm" onsubmit="handleUserSubmit(event)">
                <div class="modal-body">
                    <input type="hidden" id="userId" name="id" value="">
                    
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur *</label>
                        <input type="text" id="username" name="username" required placeholder="ex: admin_johndoe">
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" placeholder="Laissez vide pour conserver l'actuel">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('userModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
        function editUser(id, username) {
            document.getElementById('userId').value = id;
            document.getElementById('username').value = username;
            document.getElementById('password').value = '';
            document.querySelector('#userModal .modal-title').textContent = 'Modifier l\'Admin';
            openModal('userModal');
        }

        function deleteUser(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
                apiCall('../api/admin-users/delete.php', 'POST', { id: id })
                    .then(response => {
                        showAlert('Utilisateur supprimé', 'success');
                        setTimeout(() => location.reload(), 500);
                    })
                    .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
            }
        }

        function handleUserSubmit(event) {
            event.preventDefault();
            const formData = new FormData(document.getElementById('userForm'));
            const data = Object.fromEntries(formData);
            const endpoint = data.id ? '../api/admin-users/update.php' : '../api/admin-users/create.php';
            
            apiCall(endpoint, 'POST', data)
                .then(response => {
                    showAlert('Enregistré avec succès', 'success');
                    closeModal('userModal');
                    setTimeout(() => location.reload(), 500);
                })
                .catch(error => showAlert('Erreur: ' + error.message, 'danger'));
        }
    </script>
</body>
</html>
