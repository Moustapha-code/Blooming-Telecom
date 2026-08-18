<?php
/**
 * Fonctions helper pour le tableau de bord
 */

// Base URL de l'application. En local (XAMPP) l'app vit sous /blooming2 ;
// en production, définir APP_BASE_URL="" pour servir à la racine du domaine.
if (!defined('BASE_URL')) {
    $envBase = getenv('APP_BASE_URL');
    define('BASE_URL', rtrim($envBase === false ? '/blooming2' : $envBase, '/'));
}

// Nettoyer / sécuriser une entrée
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Formater une date
function formatDate($date) {
    if (!$date) return '-';
    return date('Y-m-d', strtotime($date));
}

// Formater une date/heure
function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('Y-m-d H:i', strtotime($datetime));
}

// Formater une devise
function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',');
}

// Vérifier si une valeur existe dans un tableau
function inArray($value, $array) {
    return in_array($value, $array, true);
}

/**
 * Règle de clôture d'un OT : quand un OT quitte l'état 'encoure', on
 * horodate la clôture. Pour 'retard' on reprend la date/heure de
 * réalisation, sinon on prend la date/heure courante.
 *
 * Cette règle existe aussi en trigger MySQL (trg_update_cloture_on_status_change),
 * mais elle est reproduite ici car certains moteurs compatibles MySQL
 * (TiDB par exemple) ne supportent pas les triggers. Les deux
 * implémentations donnent le même résultat.
 *
 * @return array{0: ?string, 1: ?string}|null [date_de_cloture, temp_de_cloture]
 *         ou null s'il n'y a rien à horodater.
 */
function computeClotureStamp(?string $oldEtat, ?string $newEtat, ?string $dateRealise, ?string $tempRealise): ?array
{
    if ($oldEtat !== 'encoure' || $newEtat === 'encoure' || $newEtat === null) {
        return null;
    }
    if ($newEtat === 'retard') {
        return [$dateRealise, $tempRealise];
    }
    return [date('Y-m-d'), date('H:i:s')];
}

// Obtenir la classe du badge de statut
function getStatusBadgeClass($status) {
    $classes = [
        'realise'  => 'badge-success',
        'encoure'  => 'badge-warning',
        'retard'   => 'badge-danger',
        'negative' => 'badge-secondary',
        'Present'  => 'badge-success',
        'Absent'   => 'badge-danger',
    ];
    return $classes[$status] ?? 'badge-secondary';
}

// Obtenir le texte du badge de statut
function getStatusBadgeText($status) {
    $texts = [
        'realise'  => 'Réalisé',
        'encoure'  => 'En cours',
        'retard'   => 'En retard',
        'negative' => 'Négatif',
        'Present'  => 'Présent',
        'Absent'   => 'Absent',
    ];
    return $texts[$status] ?? $status;
}
?>
