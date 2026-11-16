<?php
require_once __DIR__ . '/_boot.php';

$name = basename((string)($_GET['f'] ?? ''));
$fail = static function (): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo function_exists('ame_l')
        ? ame_l('এই বইটি বর্তমানে খোলা যাচ্ছে না। অনুগ্রহ করে পরে আবার চেষ্টা করুন।', 'This book cannot be opened right now. Please try again later.')
        : 'এই বইটি বর্তমানে খোলা যাচ্ছে না। অনুগ্রহ করে পরে আবার চেষ্টা করুন।';
    exit;
};
if (!preg_match('/^kitab_[A-Za-z0-9_]+\.(pdf|jpe?g|png|webp)$/', $name)) {
    $fail();
}
$dir = realpath(ame_kitab_dir());
$path = realpath(ame_kitab_dir() . '/' . $name);
if ($dir === false || $path === false || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !is_file($path)) {
    $fail();
}
$db = fam_db();
$st = $db->prepare('SELECT id, archived_at, visibility FROM family_books WHERE pdf_file=? OR cover_file=? LIMIT 1');
$st->bind_param('ss', $name, $name);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();
if (!$row) {
    $st = $db->prepare('SELECT b.id, b.archived_at, b.visibility FROM family_book_pages p JOIN family_books b ON b.id=p.book_id WHERE p.image_file=? LIMIT 1');
    $st->bind_param('s', $name);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
}
if (!$row) {
    $fail();
}
if (!empty($row['archived_at']) && !fam_can_write()) {
    $fail();
}
if (($row['visibility'] ?? 'family') === 'private' && !fam_can_write()) {
    $fail();
}
$size = (int)filesize($path);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'));
$start = 0;
$end = max(0, $size - 1);
$isRange = false;
if ($size > 0 && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $m)) {
    $start = (int)$m[1];
    if ($m[2] !== '') {
        $end = (int)$m[2];
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $end = min($end, $size - 1);
    $isRange = true;
}
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="book.' . $ext . '"');
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');
if ($isRange) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . ($end - $start + 1));
$fh = fopen($path, 'rb');
if ($fh === false) {
    $fail();
}
fseek($fh, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fh)) {
    $chunk = fread($fh, (int)min(65536, $left));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $left -= strlen($chunk);
}
fclose($fh);
