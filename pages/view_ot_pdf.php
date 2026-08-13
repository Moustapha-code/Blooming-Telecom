<?php
require '../config/session.php';
require '../config/database.php';
require '../config/helpers.php';

requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit("ID invalide");
}

$stmt = $pdo->prepare("SELECT pdf_file FROM installations WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || empty($row['pdf_file'])) {
    http_response_code(404);
    exit("PDF introuvable pour cette OT");
}

// Folder where PDFs are stored (adjust if needed)
$baseDir = realpath(__DIR__ . '/../uploads/ot_pdfs');
if (!$baseDir) {
    http_response_code(500);
    exit("Dossier PDF manquant");
}

// If you store only filename in DB:
$pdfRel = $row['pdf_file']; // ex: "OT_123.pdf"
// If you stored full relative path, keep it but still validate below.

$fullPath = realpath($baseDir . DIRECTORY_SEPARATOR . basename($pdfRel));

if (!$fullPath || !file_exists($fullPath)) {
    http_response_code(404);
    exit("Fichier PDF introuvable");
}

// Security: ensure it stays inside uploads folder
if (strpos($fullPath, $baseDir) !== 0) {
    http_response_code(403);
    exit("Accès refusé");
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
