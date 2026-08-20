<?php
/**
 * Fonctions helper pour le tableau de bord
 */

require_once __DIR__ . '/app.php'; // BASE_URL, LOGIN_URL

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

    // Reconnaissance par motif plutôt que par liste exacte : une table de
    // correspondance laisse passer la moindre faute de frappe. « en cous »
    // est ainsi arrivé en base par un import CSV et s'affichait comme un
    // second « En cours » dans les filtres.
    $patterns = [
        '/^en\s*retard/'  => 'retard',   // avant le motif « en cou… »
        '/^retard/'       => 'retard',
        '/^late/'         => 'retard',
        '/^en\s*cou/'     => 'encoure',  // en cours, en cour, en cous, encours…
        '/^encou/'        => 'encoure',
        '/^cours?$/'      => 'encoure',
        '/^real/'         => 'realise',  // realise, realiser, realisee…
        '/^fait/'         => 'realise',
        '/^termin/'       => 'realise',
        '/^neg/'          => 'negative', // negatif, negative…
    ];
    foreach ($patterns as $pattern => $canonical) {
        if (preg_match($pattern, $v)) {
            return $canonical;
        }
    }

    // Valeur inconnue : on la conserve telle quelle plutôt que de la
    // ranger de force dans une catégorie fausse.
    return $v;
}

/**
 * Regroupements de natures d'OT proposés dans les filtres, en plus des
 * natures individuelles. Sélectionner un groupe retourne toutes ses
 * natures d'un coup.
 *
 * Pour ajouter ou modifier un groupe, il suffit d'éditer ce tableau : les
 * pages Installations et Analyse OT le lisent toutes les deux.
 */
function natureGroups(): array
{
    return [
        'grp_installations' => [
            'label'   => 'Installations',
            'natures' => ['CPL', 'TRL', 'CMI', 'CLS', 'CST', 'RLR'],
        ],
    ];
}

/**
 * Natures correspondant à une valeur de filtre : la liste du groupe si la
 * valeur en désigne un, sinon la nature seule.
 */
function resolveNatureFilter(string $value): array
{
    $groups = natureGroups();
    return isset($groups[$value]) ? $groups[$value]['natures'] : [$value];
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
