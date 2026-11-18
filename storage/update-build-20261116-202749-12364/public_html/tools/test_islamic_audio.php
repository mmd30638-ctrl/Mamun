<?php
/**
 * CLI smoke test — ffmpeg compress + schema helpers (no DB required for ffmpeg).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/islamic_audio.php';

$tmp = sys_get_temp_dir() . '/ia_test_' . bin2hex(random_bytes(4)) . '.wav';
$cmd = 'ffmpeg -y -hide_banner -loglevel error -f lavfi -i sine=frequency=440:duration=2 -ac 1 ' . escapeshellarg($tmp) . ' 2>&1';
exec($cmd, $log, $code);
if ($code !== 0 || !is_file($tmp)) {
    fwrite(STDERR, "FAIL: test tone\n");
    exit(1);
}

ame_islamic_audio_ensure_upload_dir();
$dir = ame_islamic_audio_upload_dir();
$out = $dir . 'cli_test_' . time() . '.mp3';
try {
    $meta = ame_islamic_audio_process_file($tmp, 999);
    echo "OK compress: " . $meta['file_name'] . ' ' . $meta['bytes'] . " bytes\n";
    @unlink($dir . $meta['file_name']);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL process: ' . $e->getMessage() . "\n");
    @unlink($tmp);
    exit(1);
}
@unlink($tmp);
echo "ffmpeg: " . (ame_islamic_audio_ffmpeg_bin() ?: 'none') . "\n";
echo "PASS\n";