<?php
/**
 * Fonctions helper pour le tableau de bord
 */

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
