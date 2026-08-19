<?php
/**
 * import-pdf.php - Interactive OT Extractor (Modern Redesign)
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
if (!is_dir($exportDir)) {
    @mkdir($exportDir, 0777, true);
}

$errorMsg = '';
$successMsg = '';
$allExtractedLines = [];
$currentFileName = '';

function normalizePdfText(string $text): string {
    $text = str_replace("\xC2\xA0", " ", $text);
    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    return $text ?? '';
}

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
    return trim((string) $text);
}

$fileToParse = null;

if (isset($_GET['pdf_url'])) {
    $url = $_GET['pdf_url'];
    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ext_pdf_' . uniqid() . '.pdf';
    $ctx = stream_context_create(["http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]]);
    $content = @file_get_contents($url, false, $ctx);
    if ($content !== false && file_put_contents($tmpFile, $content)) {
        $fileToParse = $tmpFile;
        $currentFileName = basename(parse_url($url, PHP_URL_PATH) ?: 'extraction.pdf');
    } else {
        $errorMsg = "Impossible de récupérer le PDF.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ot_pdf'])) {
    if ($_FILES['ot_pdf']['error'] === UPLOAD_ERR_OK) {
        $fileToParse = $_FILES['ot_pdf']['tmp_name'];
        $currentFileName = $_FILES['ot_pdf']['name'];
    } else {
        $errorMsg = "Erreur lors de l'envoi.";
    }
}

if ($fileToParse) {
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($fileToParse);
        $text = trim($pdf->getText());

        if ($text === '') $text = extractTextWithOCR($fileToParse);

        if ($text !== '') {
            $text = normalizePdfText($text);
            $rawLines = explode("\n", $text);
            foreach ($rawLines as $l) {
                $trimmed = trim($l);
                if ($trimmed !== '') $allExtractedLines[] = $trimmed;
            }
            $successMsg = "Extraction réussie : " . count($allExtractedLines) . " lignes trouvées.";
        } else {
            $errorMsg = "Aucun texte extrait.";
        }

        if (isset($_GET['pdf_url']) && strpos($fileToParse, 'ext_pdf_') !== false) @unlink($fileToParse);
    } catch (Exception $e) {
        $errorMsg = "Erreur technique : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import PDF Interactif - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/modern-dashboard.css?v=<?php echo filemtime('../assets/css/modern-dashboard.css'); ?>">
    <style>
        .mapping-input {
            width: 100%;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 0.875rem;
        }
        .line-content-input {
            width: 100%;
            background: transparent;
            border: 1px solid transparent;
            color: var(--text);
            padding: 0.5rem;
            border-radius: 6px;
        }
        .line-content-input:focus {
            background: var(--surface);
            border-color: var(--primary);
            outline: none;
        }
        .selection-sticky {
            position: sticky;
            top: 0;
            background: var(--surface);
            z-index: 10;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }
    </style>
    <?php require_once __DIR__ . '/../components/pwa.php'; renderPwaHead(); ?>
</head>
<body>
    <div class="app-container">
        <?php renderSidebar('installations.php'); ?>

        <main class="main-content">
            <?php renderTopbar($_SESSION['admin_username']); ?>

            <div class="content-body fade-in">
                <div class="page-header">
                    <div>
                        <h2 class="page-title">Extracteur PDF Interactif</h2>
                        <p class="page-subtitle">Sélectionnez les données à extraire manuellement de vos fichiers.</p>
                    </div>
                </div>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger mb-6"><?php echo $errorMsg; ?></div>
                <?php endif; ?>

                <?php if (empty($allExtractedLines)): ?>
                    <div class="card p-8 text-center max-w-2xl mx-auto">
                        <i class="fa-solid fa-file-pdf text-primary mb-4" style="font-size: 4rem; opacity: 0.2;"></i>
                        <h3 class="text-xl font-bold mb-4">Importer un document</h3>
                        <p class="text-muted mb-6">Utilisez l'extension ou téléchargez un fichier pour voir son contenu ligne par ligne.</p>
                        
                        <form method="post" enctype="multipart/form-data" class="flex flex-col items-center">
                            <input type="file" name="ot_pdf" accept="application/pdf" required class="form-control mb-4 w-full max-w-sm">
                            <button type="submit" class="btn btn-primary w-full max-w-sm">
                                <i class="fa-solid fa-wand-magic-sparkles mr-2"></i> Lancer l'analyse
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="selection-sticky flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-primary-dim text-primary rounded-lg">
                                <i class="fa-solid fa-file-invoice" style="font-size: 1.5rem;"></i>
                            </div>
                            <div>
                                <h4 class="font-bold"><?php echo htmlspecialchars($currentFileName); ?></h4>
                                <p class="text-xs text-muted"><?php echo count($allExtractedLines); ?> lignes extraites</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <a href="import-pdf.php" class="btn btn-secondary">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Changer de fichier
                            </a>
                            <button class="btn btn-primary" id="exportBtn">
                                <i class="fa-solid fa-file-csv mr-2"></i> Exporter (.CSV)
                            </button>
                        </div>
                    </div>

                    <div class="card-table">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                        <th style="width: 250px;">Nom de la Colonne</th>
                                        <th>Texte Extrait</th>
                                    </tr>
                                </thead>
                                <tbody id="linesTable">
                                    <?php foreach ($allExtractedLines as $idx => $line): ?>
                                        <tr class="table-row">
                                            <td><input type="checkbox" class="row-check" data-idx="<?php echo $idx; ?>"></td>
                                            <td>
                                                <input type="text" class="mapping-input" placeholder="Ex: Client, Gepon..." id="col-<?php echo $idx; ?>">
                                            </td>
                                            <td>
                                                <input type="text" class="line-content-input" id="text-<?php echo $idx; ?>" value="<?php echo htmlspecialchars($line); ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php renderLayoutScripts(); ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const rowChecks = document.querySelectorAll('.row-check');
        const exportBtn = document.getElementById('exportBtn');

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                rowChecks.forEach(chk => chk.checked = selectAll.checked);
            });
        }

        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                const selectedData = [];
                rowChecks.forEach(chk => {
                    if (chk.checked) {
                        const idx = chk.getAttribute('data-idx');
                        const colName = document.getElementById('col-' + idx).value || ('Col_' + idx);
                        const content = document.getElementById('text-' + idx).value;
                        selectedData.push({ key: colName, value: content });
                    }
                });

                if (selectedData.length === 0) {
                    alert("Sélectionnez au moins une ligne.");
                    return;
                }

                let csvContent = "data:text/csv;charset=utf-8,";
                const headers = selectedData.map(d => '"' + d.key.replace(/"/g, '""') + '"').join(",");
                csvContent += headers + "\r\n";
                const values = selectedData.map(d => '"' + d.value.replace(/"/g, '""') + '"').join(",");
                csvContent += values + "\r\n";

                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "EXTRACTION_" + new Date().getTime() + ".csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    });
    </script>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const rowChecks = document.querySelectorAll('.row-check');
    const exportBtn = document.getElementById('exportBtn');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks.forEach(chk => chk.checked = selectAll.checked);
        });
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const selectedData = [];
            rowChecks.forEach(chk => {
                if (chk.checked) {
                    const idx = chk.getAttribute('data-idx');
                    const colName = document.getElementById('col-' + idx).value || ('Column_' + idx);
                    const content = document.getElementById('text-' + idx).value;
                    selectedData.push({ key: colName, value: content });
                }
            });

                    if (selectedData.length === 0) {
                        alert("Veuillez sélectionner au moins une ligne.");
                        return;
                    }

                    // Generate CSV
                    let csvContent = "data:text/csv;charset=utf-8,";

                    // Header: Column Names
                    const headers = selectedData.map(d => '"' + d.key.replace(/"/g, '""') + '"').join(",");
                    csvContent += headers + "\r\n";

                    // Data Line: Values
                    const values = selectedData.map(d => '"' + d.value.replace(/"/g, '""') + '"').join(",");
                    csvContent += values + "\r\n";

                    // Download
                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement("a");
                    link.setAttribute("href", encodedUri);
                    link.setAttribute("download", "OT_EXTRACTION_" + new Date().getTime() + ".csv");
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        });
    </script>
</body>

</html>