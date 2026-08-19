<?php
/**
 * Session configuration and security
 */

require_once __DIR__ . '/app.php'; // LOGIN_URL

session_start();

/**
 * La requête attend-elle du JSON (appel fetch d'une page) plutôt qu'une
 * page HTML ? Rediriger un appel d'API vers login.php renvoie du HTML
 * que response.json() ne sait pas lire : l'utilisateur voit une erreur
 * incompréhensible et sa saisie est perdue. Ces appels reçoivent un 401
 * JSON, que le client sait interpréter.
 */
function expectsJson(): bool
{
    if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
        return true;
    }
    if (strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0) {
        return true;
    }
    return stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
}

/** Session absente ou expirée : 401 JSON pour l'API, redirection sinon. */
function denyUnauthenticated(string $reason): void
{
    if (expectsJson()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error'           => $reason,
            'session_expired' => true,
            'login_url'       => LOGIN_URL,
        ]);
    } else {
        header('Location: ' . LOGIN_URL);
    }
    exit;
}

// Session timeout: 30 minutes
$timeout_duration = 1800;

if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        denyUnauthenticated('Session expirée, veuillez vous reconnecter.');
    }
}

$_SESSION['last_activity'] = time();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        denyUnauthenticated('Non authentifié, veuillez vous reconnecter.');
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
