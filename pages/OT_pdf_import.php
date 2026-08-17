<?php
/**
 * OT_pdf_import.php - Modern Redesign
 */

require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';
require '../components/layout.php';

requireLogin();

require_once __DIR__ . '/../vendor/autoload.php';
use Smalot\PdfParser\Parser;

error_reporting(E_ALL);
ini_set('display_errors', 1);

/** Export folder */
$exportDir = __DIR__ . '/../exports';
$exportUrl = '../exports';
if (!is_dir($exportDir)) {
    @mkdir($exportDir, 0777, true);
}

$errorMsg     = '';
$successMsg   = '';
$downloadLink = '';
$results      = [];
$exportRows   = [];
$seenKeys     = [];

/** Normalize PDF extracted text */
function normalizePdfText(string $text): string {
    $text = str_replace("\xC2\xA0", " ", $text);
    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    return $text ?? '';
}

/** OCR fallback */
function extractTextWithOCR(string $pdfPath): string {
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_ocr_' . uniqid();
    $pngPrefix = $base;
    $cmd1 = "pdftoppm -png -f 1 -l 1 " . escapeshellarg($pdfPath) . " " . escapeshellarg($pngPrefix);
    @exec($cmd1);
    $imgFile = $pngPrefix . "-1.png";
    if (!file_exists($imgFile)) return '';
    $cmd2 = "tesseract " . escapeshellarg($imgFile) . " stdout -l fra+eng";
    $text = @shell_exec($cmd2);
    @unlink($imgFile);
    return trim((string)$text);
}

function extractOtFromText(string $text): array {
    $text = normalizePdfText($text);
    $clientNum = null; $clientName = null; $gepon = null; $disRef = null; $gsm = '';

    if (preg_match('/Client\s*:\s*(\d+)\s+([^\n]+)/iu', $text, $m)) {
        $clientNum = trim($m[1]); $clientName = trim($m[2]);
    }
    if (preg_match('/\b(GSM|TEL|TÉLÉPHONE)\s*:?\s*(\d{6,15})\b/iu', $text, $m)) {
        $gsm = trim($m[2]);
    }
    if (preg_match('/\bGPON\s+(\d+)\b/iu', $text, $m)) {
        $gepon = trim($m[1]);
    }
    if (!$gepon && preg_match('/\bLigne\s*:\s*FGP\s+(\d+)\b/iu', $text, $m)) {
        $gepon = trim($m[1]);
    }
    if (!$gepon && preg_match('/\bFGP\s+(\d+)\b/iu', $text, $m)) {
        $gepon = trim($m[1]);
    }
    if (preg_match('/\bDIS\s+([A-Z0-9\/\-]+)\b/iu', $text, $m)) {
        $disRef = trim($m[1]);
    }

    $missing = [];
    if (!$clientNum) $missing[] = 'numero_client';
    if (!$clientName) $missing[] = 'nom';
    if (!$gepon) $missing[] = 'Gepon';
    if (!$disRef) $missing[] = 'port';

    if ($missing) {
        return ['ok' => false, 'data' => [], 'error' => 'Champs manquants: ' . implode(', ', $missing)];
    }

    return [
        'ok' => true,
        'data' => [
            'numero_client' => $clientNum,
            'nom'           => $clientName,
            'telephone'     => $gsm,
            'Gepon'         => $gepon,
            'port'          => $disRef,
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['ot_pdfs'])) {
        $errorMsg = "Aucun fichier reçu.";
    } else {
        $files = $_FILES['ot_pdfs'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        if ($count > 0) {
            $parser = new Parser();
            $okCount = 0; $skipCount = 0;

            for ($i = 0; $i < $count; $i++) {
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                if ($err !== UPLOAD_ERR_OK) {
                    $results[] = ['file' => $name, 'status' => 'SKIP', 'message' => "Erreur upload"];
                    $skipCount++; continue;
                }

                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
                    $results[] = ['file' => $name, 'status' => 'SKIP', 'message' => "Pas un PDF"];
                    $skipCount++; continue;
                }

                try {
                    $pdf = $parser->parseFile($tmp);
                    $text = trim($pdf->getText());
                    $usedOCR = false;

                    if ($text === '') {
                        $text = extractTextWithOCR($tmp);
                        $usedOCR = true;
                        if (trim($text) === '') {
                            $results[] = ['file' => $name, 'status' => 'SKIP', 'message' => "Scan illisible"];
                            $skipCount++; continue;
                        }
                    }

                    $ex = extractOtFromText($text);
                    if (!$ex['ok']) {
                        $results[] = ['file' => $name, 'status' => 'SKIP', 'message' => $ex['error']];
                        $skipCount++; continue;
                    }

                    $d = $ex['data'];
                    $uniqueKey = $d['numero_client'] . '|' . $d['Gepon'] . '|' . $d['port'];
                    if (isset($seenKeys[$uniqueKey])) {
                        $results[] = ['file' => $name, 'status' => 'SKIP', 'message' => "Doublon"];
                        $skipCount++; continue;
                    }
                    $seenKeys[$uniqueKey] = true;

                    $exportRows[] = [
                        'numero_client' => $d['numero_client'],
                        'nom'           => $d['nom'],
                        'telephone'     => $d['telephone'],
                        'Gepon'         => $d['Gepon'],
                        'port'          => $d['port'],
                        'source_pdf'    => $name,
                        'methode'       => $usedOCR ? 'OCR' : 'TEXTE',
                    ];

                    $results[] = ['file' => $name, 'status' => 'OK', 'message' => $usedOCR ? "Extrait (OCR)" : "Extrait"];
                    $okCount++;
                } catch (Exception $e) {
                    $results[] = ['file' => $name, 'status' => 'SKIP', 'message' => "Erreur"];
                    $skipCount++;
                }
            }

            if ($okCount > 0) {
                $csvName = 'OT_EXPORT_' . date('Ymd_His') . '.csv';
                $csvPath = $exportDir . '/' . $csvName;
                $fh = fopen($csvPath, 'w');
                if ($fh) {
                    fputcsv($fh, array_keys($exportRows[0]));
                    foreach ($exportRows as $row) fputcsv($fh, array_values($row));
                    fclose($fh);
                    $downloadLink = $exportUrl . '/' . $csvName;
                    $successMsg = "{$okCount} PDF traité(s).";
                } else {
                    $errorMsg = "Erreur création CSV.";
                }
            } else {
                $errorMsg = "Aucun PDF traité avec succès.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extracteur PDF OT - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('installations.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Extracteur de PDF (OCR)</h2>
                        <p class="page-subtitle">Convertissez vos fiches PDF en format Excel/CSV instantanément.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="col-span-1">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="section-title mb-0">Téléchargement</h3>
                            </div>
                            <form method="post" enctype="multipart/form-data" class="p-4">
                                <div class="form-group">
                                    <label>Sélectionner les PDFs</label>
                                    <input type="file" name="ot_pdfs[]" accept="application/pdf" multiple required class="form-control">
                                    <small class="text-muted mt-2 block">Supporte les scans (OCR automatique).</small>
                                </div>

                                <button type="submit" class="btn btn-primary w-full">
                                    <i class="fa-solid fa-file-export mr-2"></i> Lancer l'extraction
                                </button>
                            </form>
                        </div>

                        <?php if ($downloadLink): ?>
                            <div class="card mt-6 border-success" style="border-width: 2px;">
                                <div class="p-4 text-center">
                                    <i class="fa-solid fa-file-csv text-success mb-3" style="font-size: 3rem;"></i>
                                    <h4 class="font-bold mb-2">Extraction Terminée !</h4>
                                    <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="btn btn-success w-full" target="_blank">
                                        <i class="fa-solid fa-download mr-2"></i> Télécharger le CSV
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-span-2">
                        <?php if ($errorMsg): ?>
                            <div class="alert alert-danger mb-6"><?php echo $errorMsg; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($results)): ?>
                            <div class="card-table">
                                <div class="card-header">
                                    <h3 class="section-title mb-0">Journal de Traitement</h3>
                                </div>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Fichier PDF</th>
                                                <th>Statut</th>
                                                <th>Message</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $r): ?>
                                                <tr class="table-row">
                                                    <td class="font-mono text-sm"><?php echo htmlspecialchars($r['file']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $r['status'] === 'OK' ? 'badge-success' : 'badge-warning'; ?>">
                                                            <?php echo $r['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-muted"><?php echo htmlspecialchars($r['message']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card p-12 text-center">
                                <i class="fa-solid fa-cloud-arrow-up text-muted mb-4" style="font-size: 4rem; opacity: 0.2;"></i>
                                <p class="text-muted">Sélectionnez des fichiers à gauche pour commencer.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
</body>
</html>
