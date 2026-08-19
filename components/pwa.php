<?php
/**
 * Balises et script rendant l'application installable (PWA).
 *
 * À appeler dans le <head> de chaque page :
 *     <?php require_once __DIR__ . '/components/pwa.php'; renderPwaHead(); ?>
 */

require_once __DIR__ . '/../config/app.php'; // BASE_URL

function renderPwaHead(): void
{
    $base = BASE_URL;
    ?>
    <link rel="manifest" href="<?php echo $base; ?>/manifest.php">
    <meta name="theme-color" content="#3b82f6">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base; ?>/assets/icons/icon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $base; ?>/assets/icons/icon-192.png">

    <!-- iOS n'utilise pas le manifeste pour l'écran d'accueil. -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Blooming">
    <link rel="apple-touch-icon" href="<?php echo $base; ?>/assets/icons/apple-touch-icon.png">

    <script>
    (function () {
        const BASE = <?php echo json_encode($base); ?>;

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register(BASE + '/service-worker.js', { scope: BASE + '/' })
                    .catch(function (err) { console.warn('Service worker non enregistré:', err); });
            });
        }

        // Déjà lancée en mode application : pas de bouton d'installation.
        const installed = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
        if (installed) return;

        const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);

        function makeButton(label, onClick) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'pwaInstallBtn';
            btn.innerHTML = '<i class="fa-solid fa-circle-down"></i> ' + label;
            btn.style.cssText = [
                'position:fixed', 'right:18px', 'bottom:18px', 'z-index:9998',
                'display:inline-flex', 'align-items:center', 'gap:8px',
                'padding:11px 18px', 'border:0', 'border-radius:999px',
                'background:#3b82f6', 'color:#fff', 'font-weight:600',
                'font-size:.9rem', 'cursor:pointer',
                'box-shadow:0 6px 18px rgba(59,130,246,.45)',
                'font-family:inherit'
            ].join(';');
            btn.addEventListener('click', onClick);
            document.body.appendChild(btn);
            return btn;
        }

        // Chrome / Edge / Android : invite native.
        let deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredPrompt = event;
            if (document.getElementById('pwaInstallBtn')) return;

            const btn = makeButton("Installer l'application", async function () {
                if (!deferredPrompt) return;
                btn.remove();
                deferredPrompt.prompt();
                await deferredPrompt.userChoice;
                deferredPrompt = null;
            });
        });

        window.addEventListener('appinstalled', function () {
            const btn = document.getElementById('pwaInstallBtn');
            if (btn) btn.remove();
            deferredPrompt = null;
        });

        // iOS ne déclenche jamais beforeinstallprompt : on explique le geste.
        if (isIOS) {
            window.addEventListener('load', function () {
                if (sessionStorage.getItem('pwaIosHintDismissed')) return;
                const btn = makeButton("Ajouter à l'écran d'accueil", function () {
                    alert("Pour installer Blooming FTTH :\n\n"
                        + "1. Touchez le bouton Partager (carré avec une flèche)\n"
                        + "2. Choisissez « Sur l'écran d'accueil »\n"
                        + "3. Confirmez avec « Ajouter »");
                    sessionStorage.setItem('pwaIosHintDismissed', '1');
                    btn.remove();
                });
            });
        }
    })();
    </script>
    <?php
}
