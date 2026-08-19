<?php
/**
 * Génère les icônes de l'application installable (PWA).
 *
 * À relancer uniquement si le logo change :
 *     php assets/icons/generate_icons.php
 *
 * Le dessin est fait sur une grande toile puis rééchantillonné, ce qui
 * donne des bords lisses sans dépendre de l'anticrénelage de GD.
 */

// Outil en ligne de commande : rien ne justifie de le déclencher par HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const CANVAS = 1024;
const BRAND  = [0x3b, 0x82, 0xf6]; // --primary du thème
const SIZES  = [512, 192, 180, 32];

$img = imagecreatetruecolor(CANVAS, CANVAS);
imagealphablending($img, true);
imagesavealpha($img, true);

$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);

$brand = imagecolorallocate($img, BRAND[0], BRAND[1], BRAND[2]);
$dark  = imagecolorallocate($img, 0x1d, 0x4e, 0xd8); // bord légèrement plus soutenu
$white = imagecolorallocate($img, 0xff, 0xff, 0xff);

/** Rectangle aux coins arrondis. */
function roundedRect($img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
{
    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
    }
}

// Fond : carré arrondi. Les icônes « maskable » sont rognées en cercle sur
// Android, donc tout le glyphe reste dans les 70 % centraux.
roundedRect($img, 0, 0, CANVAS - 1, CANVAS - 1, 200, $dark);
roundedRect($img, 8, 8, CANVAS - 9, CANVAS - 9, 194, $brand);

// Marque « Blooming Telecom » : une fleur dont les pétales rayonnent
// depuis le centre, lecture double — la floraison du nom, et l'émission
// d'un signal télécom.
$cx = (int) (CANVAS / 2);
$cy = (int) (CANVAS / 2);

// Un pétale = ellipse décalée du centre, tracée en polygone pour pouvoir
// l'orienter librement (GD ne sait pas pivoter une ellipse).
$petalHalfLength = 190; // rayon de l'ellipse dans l'axe du pétale
$petalHalfWidth  = 82;  // rayon perpendiculaire
$petalOffset     = 205; // éloignement du centre
$petalCount      = 6;
$steps           = 72;  // finesse du contour

// Extension maximale : 205 + 190 = 395 px, sous les 410 px de la zone
// sûre d'une icône « maskable » (80 % de 1024).
for ($p = 0; $p < $petalCount; $p++) {
    $theta = 2 * M_PI * $p / $petalCount - M_PI / 2; // premier pétale vers le haut
    $cos = cos($theta);
    $sin = sin($theta);

    $points = [];
    for ($s = 0; $s < $steps; $s++) {
        $t = 2 * M_PI * $s / $steps;
        // Ellipse locale, axe long orienté vers l'extérieur.
        $lx = $petalHalfWidth * cos($t);
        $ly = $petalHalfLength * sin($t) + $petalOffset;
        // Rotation dans la direction du pétale.
        $points[] = (int) round($cx + $lx * $sin + $ly * $cos);
        $points[] = (int) round($cy - $lx * $cos + $ly * $sin);
    }
    imagefilledpolygon($img, $points, $white);
}

// Cœur de la fleur, en creux sur le fond pour détacher les pétales.
imagefilledellipse($img, $cx, $cy, 232, 232, $brand);
imagefilledellipse($img, $cx, $cy, 132, 132, $white);

$outDir = __DIR__;
foreach (SIZES as $size) {
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagealphablending($out, true);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, CANVAS, CANVAS);

    $name = $size === 180 ? 'apple-touch-icon.png' : "icon-$size.png";
    imagepng($out, "$outDir/$name", 9);
    imagedestroy($out);
    printf("  %-22s %d x %d\n", $name, $size, $size);
}
imagedestroy($img);
echo "Icônes générées dans assets/icons/\n";
