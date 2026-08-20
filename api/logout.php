<?php
/**
 * Logout API
 */

require '../config/session.php';

// Vider les données avant de détruire la session : session_destroy() seul
// supprime le stockage mais laisse $_SESSION rempli pour la requête en
// cours, et le cookie continue d'être renvoyé par le navigateur.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ' . LOGIN_URL);
exit;
