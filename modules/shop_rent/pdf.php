<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/shop_rent.php';

$id = (int)($_GET['id'] ?? 0);
$year = (string)($_GET['year'] ?? '');
$doc = (string)($_GET['doc'] ?? 'statement');
if (!in_array($doc, ['statement', 'agreement'], true)) {
    $doc = 'statement';
}
try {
    shop_rent_ensure($db);
    $lines = shop_rent_document_lines($db, $id, $year, $doc);
    $bytes = shop_rent_pdf($lines);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
    exit;
}
$name = $doc === 'agreement' ? 'Agreement-Summary.pdf' : 'Yearly-Rent-Statement.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . (string)strlen($bytes));
header('X-Content-Type-Options: nosniff');
echo $bytes;
