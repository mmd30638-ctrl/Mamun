<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/includes/islamic_audio.php';

$bin = ame_islamic_audio_ytdlp_bin();
echo 'yt-dlp: ' . ($bin ?: 'NONE') . "\n";
if ($bin === null) {
    fwrite(STDERR, "SKIP: install yt-dlp on server\n");
    exit(0);
}
try {
    ame_islamic_audio_youtube_url_allowed('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    echo "URL validate: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "PASS (download not run in CI)\n";