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

// Glyphe « signal » : un point et trois arcs, évoquant la diffusion FTTH.
$cx = (int) (CANVAS / 2);
$cy = (int) (CANVAS * 0.68);

// Chaque bande est obtenue en soustrayant un secteur intérieur d'un
// secteur extérieur : imagearc() avec une épaisseur laisse des trous.
$arcStart = 200;
$arcEnd   = 340;
foreach ([[340, 398], [220, 278], [100, 158]] as [$inner, $outer]) {
    imagefilledarc($img, $cx, $cy, $outer * 2, $outer * 2, $arcStart, $arcEnd, $white, IMG_ARC_PIE);
    imagefilledarc($img, $cx, $cy, $inner * 2, $inner * 2, $arcStart, $arcEnd, $brand, IMG_ARC_PIE);
}

imagefilledellipse($img, $cx, $cy, 116, 116, $white);

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
