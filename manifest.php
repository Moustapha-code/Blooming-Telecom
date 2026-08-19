<?php
/**
 * Manifeste de l'application installable.
 *
 * Généré en PHP plutôt que statique : les chemins doivent suivre
 * BASE_URL, qui vaut /blooming2 en local et la racine du domaine en
 * production.
 */

require_once __DIR__ . '/config/app.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base = BASE_URL; // '' à la racine, '/blooming2' en local

echo json_encode([
    'name'             => 'Blooming FTTH',
    'short_name'       => 'Blooming',
    'description'      => "Gestion des ordres de travail, du pointage et du matériel FTTH.",
    'lang'             => 'fr',
    'dir'              => 'ltr',
    'start_url'        => $base . '/index.php',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'any',
    'theme_color'      => '#3b82f6',
    'background_color' => '#ffffff',
    'icons'            => [
        [
            'src'     => $base . '/assets/icons/icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $base . '/assets/icons/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            // Android rogne l'icône en cercle : le glyphe tient dans la
            // zone sûre, la même image sert donc pour « maskable ».
            'src'     => $base . '/assets/icons/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
    'shortcuts'        => [
        [
            'name'  => 'Installations (OT)',
            'url'   => $base . '/pages/installations.php',
            'icons' => [['src' => $base . '/assets/icons/icon-192.png', 'sizes' => '192x192']],
        ],
        [
            'name'  => 'Pointage',
            'url'   => $base . '/pages/attendance.php',
            'icons' => [['src' => $base . '/assets/icons/icon-192.png', 'sizes' => '192x192']],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
