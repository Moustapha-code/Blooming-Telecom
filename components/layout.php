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
                    <a href="/blooming2/index.php" class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/installations.php" class="nav-link <?php echo $currentPage == 'installations.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-plug-circle-check"></i>
                        <span>Installations (OT)</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/OT_export.php" class="nav-link <?php echo $currentPage == 'OT_export.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-excel"></i>
                        <span>Export Excel OT</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/technicians.php" class="nav-link <?php echo $currentPage == 'technicians.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Techniciens</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/drivers.php" class="nav-link <?php echo $currentPage == 'drivers.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-id-card"></i>
                        <span>Chauffeurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/attendance.php" class="nav-link <?php echo $currentPage == 'attendance.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-clipboard-user"></i>
                        <span>Pointage</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/OT_analyse.php" class="nav-link <?php echo $currentPage == 'OT_analyse.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                        <span>Analyse OT</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/monthly_analyse.php" class="nav-link <?php echo $currentPage == 'monthly_analyse.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Analyse Mensuelle</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/OT_costing.php" class="nav-link <?php echo $currentPage == 'OT_costing.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Coûts & Facturation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/materials.php" class="nav-link <?php echo $currentPage == 'materials.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span>Inventaire Stock</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/cars.php" class="nav-link <?php echo $currentPage == 'cars.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-truck-fast"></i>
                        <span>Gestion Flotte</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/zones.php" class="nav-link <?php echo $currentPage == 'zones.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Zones</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/blooming2/pages/admin-users.php" class="nav-link <?php echo $currentPage == 'admin-users.php' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Paramètres Admin</span>
                    </a>
                </li>
                <li class="nav-item" style="margin-top: auto; padding-top: 20px;">
                    <a href="/blooming2/api/logout.php" class="nav-link" style="color: var(--danger);">
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
        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="dashboardSearch" placeholder="Rechercher partout...">
        </div>
        
        <div class="topbar-actions">
            <button class="icon-btn">
                <i class="fa-solid fa-bell"></i>
                <span class="notification-badge"></span>
            </button>
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
                <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem; color: var(--text-muted);"></i>
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

        // Global Search
        const searchInput = document.getElementById('dashboardSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', () => {
                const query = searchInput.value.toLowerCase();
                document.querySelectorAll('.table-row').forEach(row => {
                    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
                });
            });
        }
    </script>
    <script src="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/pages/') !== false) ? '../' : ''; ?>assets/js/main.js"></script>
    <?php
}
?>
