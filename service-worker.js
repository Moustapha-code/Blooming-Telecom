/*
 * Service worker de Blooming FTTH.
 *
 * Règle de base : on ne met JAMAIS en cache une page PHP ni une réponse
 * d'API. Les données (OT, pointage, stocks) changent en permanence et
 * afficher un compteur périmé serait pire qu'une erreur réseau. Seuls les
 * fichiers statiques (CSS, JS, icônes) sont servis depuis le cache, plus
 * une page hors ligne de secours.
 */

// À incrémenter dès qu'un fichier précaché change (icônes, CSS) : sinon
// les installations existantes continuent de servir l'ancienne version.
const VERSION     = 'v3';
const STATIC_CACHE = `blooming-static-${VERSION}`;

// Chemins résolus par rapport à l'emplacement du service worker, ce qui
// fonctionne aussi bien sous /blooming2/ qu'à la racine du domaine.
const OFFLINE_URL = new URL('offline.html', self.location).pathname;
const PRECACHE = [
    OFFLINE_URL,
    new URL('assets/css/modern-dashboard.css', self.location).pathname,
    new URL('assets/icons/icon-192.png', self.location).pathname,
    new URL('assets/icons/icon-512.png', self.location).pathname,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            // Une ressource manquante ne doit pas empêcher l'installation.
            .catch(() => undefined)
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== STATIC_CACHE).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

/** Fichier statique versionnable, sans risque de péremption métier. */
function isStaticAsset(url) {
    return /\.(css|js|png|jpg|jpeg|svg|webp|ico|woff2?|ttf)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Ne toucher qu'aux GET de notre propre origine.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Les appels d'API passent toujours par le réseau : une réponse mise
    // en cache renverrait des données fausses, et un 401 caché casserait
    // la reconnexion.
    if (url.pathname.includes('/api/')) return;

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        if (response && response.ok) {
                            const copy = response.clone();
                            caches.open(STATIC_CACHE).then((c) => c.put(request, copy));
                        }
                        return response;
                    })
                    .catch(() => cached);
                // Servir le cache tout de suite, rafraîchir en arrière-plan.
                return cached || network;
            })
        );
        return;
    }

    // Navigation (pages PHP) : réseau uniquement, avec repli hors ligne.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
    }
});
