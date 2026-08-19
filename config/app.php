<?php
/**
 * Constantes de base de l'application.
 *
 * Fichier volontairement minimal : il ne déclare aucune fonction, il peut
 * donc être inclus depuis n'importe où sans risque de redéclaration.
 * session.php en a besoin avant helpers.php pour construire l'URL de
 * connexion.
 */

// En local (XAMPP) l'app vit sous /blooming2 ; en production, définir
// APP_BASE_URL="" pour servir à la racine du domaine.
if (!defined('BASE_URL')) {
    $envBase = getenv('APP_BASE_URL');
    define('BASE_URL', rtrim($envBase === false ? '/blooming2' : $envBase, '/'));
}

// URL absolue de la page de connexion. Une redirection relative vers
// « login.php » est résolue par le navigateur depuis le dossier courant :
// depuis /pages/ elle donnait /pages/login.php, qui n'existe pas.
if (!defined('LOGIN_URL')) {
    define('LOGIN_URL', BASE_URL . '/login.php');
}
