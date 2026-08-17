<?php
/**
 * Database connection configuration
 * Using PDO with prepared statements for security
 *
 * Credentials come from environment variables in production
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD).
 * The defaults below match a stock local XAMPP install.
 */

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_NAME') ?: 'bloowing_db';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    // The real cause (host, credentials) goes to the server log only —
    // never to the browser, where it would expose infrastructure details.
    error_log('Database connection failed: ' . $e->getMessage());

    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 120');
    }

    if ($isApi) {
        header('Content-Type: application/json');
        exit(json_encode([
            'error' => 'Service temporairement indisponible (base de données).',
            'detail' => $isLocal ? $e->getMessage() : null,
        ]));
    }

    $detail = $isLocal
        ? '<pre style="text-align:left;background:#f1f5f9;padding:12px;border-radius:8px;overflow:auto;font-size:.8rem">'
            . htmlspecialchars($e->getMessage()) . '</pre>'
        : '';
    exit('<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Service indisponible - Blooming FTTH</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#f8fafc;color:#1e293b;'
        . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}'
        . '.box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:40px;max-width:520px;'
        . 'text-align:center;box-shadow:0 4px 6px -1px rgba(0,0,0,.1)}'
        . 'h1{font-size:1.25rem;margin:0 0 12px}p{color:#64748b;line-height:1.6;margin:0 0 8px}</style>'
        . '</head><body><div class="box"><h1>⚠️ Service temporairement indisponible</h1>'
        . '<p>La base de données est injoignable pour le moment.</p>'
        . '<p>Merci de réessayer dans quelques minutes.</p>'
        . $detail . '</div></body></html>');
}
?>
