<?php
/**
 * Sidebar Component
 */
function renderSidebar($currentPage) {
    ?>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fa-solid fa-satellite-dish"></i>
                <span>Blooming FTTH</span>
            </div>
            <button class="icon-btn" id="toggleSidebar">
                <i class="fa-solid fa-bars-staggered"></i>
            </button>
        </div>
        
        <nav class="nav-menu">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/installations.php" class="nav-link <?php echo $currentPage == 'installations.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-plug-circle-check"></i>
                        <span>Installations (OT)</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/OT_export.php" class="nav-link <?php echo $currentPage == 'OT_export.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-excel"></i>
                        <span>Export Excel OT</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/technicians.php" class="nav-link <?php echo $currentPage == 'technicians.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Techniciens</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/drivers.php" class="nav-link <?php echo $currentPage == 'drivers.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-id-card"></i>
                        <span>Chauffeurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/attendance.php" class="nav-link <?php echo $currentPage == 'attendance.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-clipboard-user"></i>
                        <span>Pointage</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/OT_analyse.php" class="nav-link <?php echo $currentPage == 'OT_analyse.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                        <span>Analyse OT</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/monthly_analyse.php" class="nav-link <?php echo $currentPage == 'monthly_analyse.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Analyse Mensuelle</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/OT_costing.php" class="nav-link <?php echo $currentPage == 'OT_costing.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Coûts & Facturation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/materials.php" class="nav-link <?php echo $currentPage == 'materials.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span>Inventaire Stock</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/cars.php" class="nav-link <?php echo $currentPage == 'cars.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-truck-fast"></i>
                        <span>Gestion Flotte</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/zones.php" class="nav-link <?php echo $currentPage == 'zones.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Zones</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/admin-users.php" class="nav-link <?php echo $currentPage == 'admin-users.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Paramètres Admin</span>
                    </a>
                </li>
                <li class="nav-item" style="margin-top: auto; padding-top: 20px;">
                    <a href="<?php echo BASE_URL; ?>/api/logout.php" class="nav-link" style="color: var(--danger);">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    <?php
}

/**
 * Topbar Component
 */
function renderTopbar($username) {
    ?>
    <header class="topbar">
        <div class="flex items-center gap-3" style="flex: 1; min-width: 0;">
            <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn" title="Menu" aria-label="Ouvrir le menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="dashboardSearch" placeholder="Filtrer le tableau affiché...">
            </div>
        </div>

        <div class="topbar-actions">
            <button class="icon-btn" title="Mode Sombre/Clair" id="themeToggle">
                <i class="fa-solid fa-moon"></i>
            </button>

            <div class="user-profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <span class="name"><?php echo htmlspecialchars($username); ?></span>
                    <span class="role">Administrateur</span>
                </div>
            </div>
        </div>
    </header>
    <?php
}

/**
 * Layout Scripts
 */
function renderLayoutScripts() {
    ?>
    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
            }
        }

        // Mobile Menu (off-canvas sidebar)
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.remove('collapsed');
                sidebar.classList.toggle('open');
            });
            document.addEventListener('click', (e) => {
                if (sidebar.classList.contains('open') && !sidebar.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            });
        }

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark');
                const isDark = document.body.classList.contains('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                themeToggle.innerHTML = isDark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
            });
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark');
                if(themeToggle) themeToggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
            }
        }

        // Modal Functions
        function openModal(id) {
            const m = document.getElementById(id);
            if(m) {
                m.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }
        function closeModal(id) {
            const m = document.getElementById(id);
            if(m) {
                m.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        // Recherche de la barre supérieure.
        //
        // Le filtre au clavier ne masque que les lignes déjà rendues, soit
        // une page de résultats. Quand la page expose un champ de recherche
        // serveur (input[name="q"]), la touche Entrée relaie la saisie vers
        // ce formulaire : la recherche porte alors sur toute la base et les
        // autres filtres du formulaire sont conservés.
        const searchInput = document.getElementById('dashboardSearch');
        if (searchInput) {
            const serverInput = document.querySelector('input[name="q"]');
            const serverForm  = serverInput ? serverInput.closest('form') : null;

            if (serverForm) {
                searchInput.placeholder = 'Rechercher dans toute la base (Entrée)...';
                // Refléter la recherche serveur active.
                if (serverInput.value && !searchInput.value) {
                    searchInput.value = serverInput.value;
                }
            }

            searchInput.addEventListener('keyup', (event) => {
                if (event.key === 'Enter') return; // traité par keydown
                const query = searchInput.value.toLowerCase();
                document.querySelectorAll('.table-row').forEach(row => {
                    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
                });
            });

            searchInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' || !serverForm) return;
                event.preventDefault();
                serverInput.value = searchInput.value.trim();
                serverForm.submit(); // repart de la première page
            });
        }
    </script>
    <script src="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/pages/') !== false) ? '../' : ''; ?>assets/js/main.js"></script>
    <?php
}
?>
