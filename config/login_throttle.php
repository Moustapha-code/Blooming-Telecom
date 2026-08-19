<?php
/**
 * Limitation des tentatives de connexion (anti-force brute).
 *
 * Les échecs sont enregistrés en base : un attaquant qui vide ses cookies
 * repartirait de zéro si le compteur vivait dans la session.
 *
 * Deux plafonds complémentaires :
 *  - par (IP, identifiant) : bloque l'acharnement sur un compte précis ;
 *  - par IP, tous identifiants confondus : bloque le balayage de noms.
 */

const LOGIN_WINDOW_SECONDS   = 900; // 15 minutes observées
const LOGIN_MAX_PER_ACCOUNT  = 5;   // échecs tolérés sur un même compte
const LOGIN_MAX_PER_IP       = 15;  // échecs tolérés depuis une même IP
const LOGIN_FAILURE_DELAY_US = 300000; // 0,3 s ajoutés à chaque échec

/**
 * Adresse du client. Derrière le proxy de Render, REMOTE_ADDR est celle
 * du proxy ; on prend alors la première entrée de X-Forwarded-For.
 * Cet en-tête est falsifiable, d'où le second plafond par compte, qui ne
 * dépend pas uniquement de l'IP.
 */
function clientIp(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $first = trim(explode(',', $forwarded)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/**
 * Secondes de blocage restantes, 0 si la connexion est autorisée.
 */
function loginLockoutRemaining(PDO $pdo, string $ip, string $username): int
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS n, MAX(attempted_at) AS last_try
           FROM login_attempts
          WHERE ip = ? AND username = ? AND attempted_at > ?'
    );
    $stmt->execute([$ip, $username, $since]);
    $account = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS n, MAX(attempted_at) AS last_try
           FROM login_attempts
          WHERE ip = ? AND attempted_at > ?'
    );
    $stmt->execute([$ip, $since]);
    $perIp = $stmt->fetch();

    $blocked = null;
    if ((int) $account['n'] >= LOGIN_MAX_PER_ACCOUNT) {
        $blocked = $account['last_try'];
    }
    if ((int) $perIp['n'] >= LOGIN_MAX_PER_IP) {
        $blocked = max($blocked ?? '', $perIp['last_try']);
    }
    if ($blocked === null) {
        return 0;
    }

    // Le blocage court à partir du dernier échec : réessayer trop tôt le
    // prolonge, ce qui rend l'attaque automatisée inintéressante.
    $remaining = LOGIN_WINDOW_SECONDS - (time() - strtotime($blocked));
    return max($remaining, 0);
}

function recordFailedLogin(PDO $pdo, string $ip, string $username): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())'
    );
    $stmt->execute([$ip, $username]);
}

/** Connexion réussie : on efface l'ardoise de ce couple IP / compte. */
function clearLoginAttempts(PDO $pdo, string $ip, string $username): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ? AND username = ?');
    $stmt->execute([$ip, $username]);
}

/** Purge occasionnelle des lignes hors fenêtre, pour ne pas laisser grossir la table. */
function pruneLoginAttempts(PDO $pdo): void
{
    if (random_int(1, 20) !== 1) {
        return;
    }
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
    $stmt->execute([date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS * 4)]);
}

/** « 12 minutes », « 45 secondes » : message d'attente lisible. */
function formatLockoutDelay(int $seconds): string
{
    if ($seconds >= 60) {
        $minutes = (int) ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
    return max($seconds, 1) . ' seconde' . ($seconds > 1 ? 's' : '');
}
