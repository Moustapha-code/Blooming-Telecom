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
 * Ramène un état d'OT à l'une des quatre valeurs canoniques :
 * 'encoure', 'realise', 'retard', 'negative'.
 *
 * Les imports CSV reprenaient le libellé du fichier tel quel, ce qui a
 * introduit plusieurs orthographes pour un même état ('en cours' à côté
 * de 'encoure'). Toute écriture d'état doit passer par cette fonction.
 */
function normalizeEtat(?string $raw): string
{
    $v = strtolower(trim((string) $raw));
    if ($v === '') {
        return 'encoure';
    }
    // Retirer les accents pour accepter "réalisé", "négatif", etc.
    $v = strtr($v, [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c',
    ]);
    $v = preg_replace('/[\s_-]+/', ' ', $v);

    $map = [
        'encoure' => 'encoure', 'en cours' => 'encoure', 'encours' => 'encoure',
        'en cour' => 'encoure', 'cours' => 'encoure',
        'realise' => 'realise', 'realiser' => 'realise', 'realisee' => 'realise',
        'fait' => 'realise', 'termine' => 'realise',
        'retard' => 'retard', 'en retard' => 'retard', 'late' => 'retard',
        'negative' => 'negative', 'negatif' => 'negative', 'negative ot' => 'negative',
    ];
    return $map[$v] ?? $v;
}

/**
 * Valeur destinée à une colonne entière : '' devient NULL.
 *
 * MySQL/MariaDB en mode permissif convertit '' en 0 sans rien dire, mais
 * les moteurs en mode strict (TiDB) refusent la requête entière. Les
 * champs vides d'un formulaire arrivent toujours sous forme de chaîne
 * vide, d'où ce filtre.
 */
function intOrNull($value): ?int
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    return (int) $value;
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
        'en cours' => 'badge-warning',   // même état, orthographe présente en base
        'retard'   => 'badge-danger',
        'negative' => 'badge-secondary',
        'present'  => 'badge-success',
        'absent'   => 'badge-danger',
    ];
    return $classes[strtolower(trim((string) $status))] ?? 'badge-secondary';
}

// Obtenir le texte du badge de statut
function getStatusBadgeText($status) {
    $texts = [
        'realise'  => 'Réalisé',
        'encoure'  => 'En cours',
        'en cours' => 'En cours',
        'retard'   => 'En retard',
        'negative' => 'Négatif',
        'present'  => 'Présent',
        'absent'   => 'Absent',
    ];
    return $texts[strtolower(trim((string) $status))] ?? $status;
}
?>
