<?php
/**
 * Version déployée.
 *
 * Sert à vérifier d'un coup d'œil quel commit tourne réellement sur le
 * serveur, sans se connecter : « je ne vois pas mes changements » vient
 * presque toujours soit d'un déploiement non terminé, soit du cache du
 * navigateur, et cette page tranche entre les deux.
 *
 * N'expose qu'un identifiant de commit et des dates de fichiers, aucune
 * donnée applicative.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Render expose le commit déployé dans l'environnement.
$commit = getenv('RENDER_GIT_COMMIT') ?: '';
echo 'commit      : ' . ($commit !== '' ? substr($commit, 0, 7) : 'inconnu (hors Render)') . "\n";

// Date des fichiers de l'image : reflète le moment du build.
foreach (['index.php', 'components/layout.php', 'config/helpers.php'] as $file) {
    $path = __DIR__ . '/' . $file;
    printf("%-12s: %s\n", $file, is_file($path) ? date('Y-m-d H:i:s', filemtime($path)) : 'absent');
}

echo 'heure serveur: ' . date('Y-m-d H:i:s') . "\n";

// Marqueurs de fonctionnalités : présents uniquement si le code
// correspondant est bien déployé.
$dashboard = @file_get_contents(__DIR__ . '/index.php') ?: '';
$layout    = @file_get_contents(__DIR__ . '/components/layout.php') ?: '';
echo "\nfonctionnalités :\n";
printf("  analyses tableau de bord : %s\n", strpos($dashboard, 'dashByEtat') !== false ? 'oui' : 'NON');
printf("  période par défaut       : %s\n", strpos($dashboard, 'resolveDateRange') !== false ? 'oui' : 'NON');
printf("  bouton déconnexion       : %s\n", strpos($layout, 'logoutBtn') !== false ? 'oui' : 'NON');
