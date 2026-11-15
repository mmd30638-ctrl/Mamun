<?php
/**
 * ইসলামিক অডিও স্ট্রিম — সঠিক MIME + Range (মোবাইল প্লে ফিক্স)
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/islamic_audio.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'লগইন লাগবে।';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

ame_islamic_audio_schema($db);
$st = $db->prepare('SELECT file_name, is_published FROM islamic_audio_tracks WHERE id = ? LIMIT 1');
$st->bind_param('i', $id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    http_response_code(404);
    exit;
}

$published = (int)($row['is_published'] ?? 0) === 1;
if (!$published && !ame_islamic_audio_can_admin()) {
    http_response_code(403);
    exit;
}

$fn = basename((string)($row['file_name'] ?? ''));
if ($fn === '' || preg_match('/[^\w.\-]+/', $fn)) {
    http_response_code(404);
    exit;
}

$path = ame_islamic_audio_upload_dir() . $fn;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
$types = [
    'mp3' => 'audio/mpeg',
    'mpeg' => 'audio/mpeg',
    'm4a' => 'audio/mp4',
    'mp4' => 'audio/mp4',
    'aac' => 'audio/aac',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'webm' => 'audio/webm',
];
$mime = $types[$ext] ?? 'application/octet-stream';
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo) {
    $detected = (string)finfo_file($finfo, $path);
    finfo_close($finfo);
    if ($detected !== '' && str_starts_with($detected, 'audio/')) {
        $mime = $detected;
    }
}

$size = filesize($path);
$start = 0;
$end = $size - 1;
$length = $size;
http_response_code(200);

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') {
        $start = (int)$m[1];
    }
    if ($m[2] !== '') {
        $end = (int)$m[2];
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    $length = $end - $start + 1;
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$length);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    @ob_end_clean();
}

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
if ($start > 0) {
    fseek($fp, $start);
}
$chunk = 1048576;
$left = $length;
while ($left > 0 && !feof($fp)) {
    $read = ($left > $chunk) ? $chunk : $left;
    $buf = fread($fp, (int)$read);
    if ($buf === false || $buf === '') {
        break;
    }
    echo $buf;
    $left -= strlen($buf);
    if (connection_aborted()) {
        break;
    }
    flush();
}
fclose($fp);
exit;