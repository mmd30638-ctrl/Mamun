<?php
require_once __DIR__ . '/_boot.php';

hire_require($db, 'view');

$id = (int)($_GET['id'] ?? 0);
$kind = (string)($_GET['kind'] ?? 'pdf');
$row = $id > 0 ? hire_agr_get($db, $id) : null;
if (!$row || !in_array($kind, ['pdf', 'customer', 'guarantor'], true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ফাইল পাওয়া যায়নি।';
    exit;
}

$path = '';
$mime = 'application/pdf';
$ext = 'pdf';
if ($kind === 'pdf') {
    $path = (string)($row['pdf_path'] ?? '');
} else {
    $path = hire_agr_sign_file($id, $kind);
    $mime = 'image/png';
    $ext = 'png';
}
$full = $path !== '' ? hire_agr_within_root($path) : null;
if ($full === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $kind === 'pdf' ? 'PDF এখনও তৈরি হয়নি।' : 'স্বাক্ষর পাওয়া যায়নি।';
    exit;
}

$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$row['agreement_no']);
if ($base === '' || $base === null) {
    $base = 'agreement';
}
$name = $base . '_v' . (int)$row['version_no'] . '.' . $ext;
$download = (string)($_GET['dl'] ?? '') === '1';
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . (string)filesize($full));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
readfile($full);
exit;
