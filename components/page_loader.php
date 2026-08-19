<?php
/**
 * Indicateur de chargement affiché pendant les rechargements de page.
 *
 * La recherche, les filtres et les formulaires provoquent un aller-retour
 * serveur : sans retour visuel, la page semble figée et l'utilisateur
 * clique ou tape de nouveau. À appeler juste avant </body>.
 */

function renderPageLoader(): void
{
    ?>
    <style>
        #pageLoader {
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: opacity .15s ease;
        }
        #pageLoader.is-active { opacity: 1; }

        /* Barre de progression indéterminée en haut de l'écran. */
        #pageLoader .bar {
            height: 3px;
            width: 100%;
            background: rgba(59, 130, 246, .18);
            overflow: hidden;
        }
        #pageLoader .bar::before {
            content: '';
            display: block;
            height: 100%;
            width: 40%;
            background: #3b82f6;
            animation: pageLoaderSlide 1s ease-in-out infinite;
        }
        @keyframes pageLoaderSlide {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(350%); }
        }

        /* Pastille de statut, sous la barre. */
        #pageLoader .chip {
            position: absolute;
            top: 14px;
            left: 50%;
            transform: translateX(-50%) translateY(-6px);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 15px;
            border-radius: 999px;
            background: #1e293b;
            color: #fff;
            font-size: .82rem;
            font-weight: 600;
            font-family: inherit;
            white-space: nowrap;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .28);
            opacity: 0;
            transition: opacity .15s ease, transform .15s ease;
        }
        #pageLoader.is-active .chip {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        #pageLoader .chip .dot {
            width: 9px; height: 9px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, .35);
            border-top-color: #fff;
            animation: pageLoaderSpin .7s linear infinite;
        }
        @keyframes pageLoaderSpin { to { transform: rotate(360deg); } }

        @media (prefers-reduced-motion: reduce) {
            #pageLoader .bar::before,
            #pageLoader .chip .dot { animation-duration: 0s; }
            #pageLoader .bar::before { width: 100%; }
        }
    </style>

    <div id="pageLoader" role="status" aria-live="polite" aria-hidden="true">
        <div class="bar"></div>
        <span class="chip"><span class="dot"></span><span class="label">Chargement...</span></span>
    </div>

    <script>
    (function () {
        const el = document.getElementById('pageLoader');
        if (!el) return;
        const label = el.querySelector('.label');
        let shownAt = 0;

        window.showPageLoader = function (message) {
            label.textContent = message || 'Chargement...';
            el.classList.add('is-active');
            el.setAttribute('aria-hidden', 'false');
            shownAt = Date.now();
        };

        window.hidePageLoader = function () {
            el.classList.remove('is-active');
            el.setAttribute('aria-hidden', 'true');
        };

        // Toute soumission de formulaire recharge la page.
        document.addEventListener('submit', function (event) {
            // Les formulaires pilotés en JS (modales) ne rechargent rien :
            // ils annulent l'événement.
            if (event.defaultPrevented) return;
            window.showPageLoader('Chargement...');
        }, true);

        // Navigation par lien interne (menu, pagination).
        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');
            if (!link || event.defaultPrevented) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
            if (link.origin && link.origin !== window.location.origin) return;
            window.showPageLoader('Chargement...');
        }, true);

        // Retour arrière : la page vient du cache, l'indicateur doit partir.
        window.addEventListener('pageshow', function () {
            window.hidePageLoader();
        });

        // Filet de sécurité : ne jamais laisser l'indicateur tourner
        // indéfiniment si la navigation est annulée.
        window.addEventListener('beforeunload', function () {
            setTimeout(function () {
                if (Date.now() - shownAt > 20000) window.hidePageLoader();
            }, 20000);
        });
    })();
    </script>
    <?php
}
