<?php
/**
 * Islamic audio library — schema, ingest (upload + URL), ffmpeg optimize, home widget.
 */

function ame_islamic_audio_categories(): array
{
    return [
        'quran' => 'কুরআন',
        'hadith' => 'হাদিস',
        'bayan' => 'বয়ান',
        'nasheed' => 'নাশিদ',
        'dua' => 'দোয়া',
        'general' => 'সাধারণ',
    ];
}

function ame_islamic_audio_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $db->query("CREATE TABLE IF NOT EXISTS islamic_audio_tracks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        display_serial INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        speaker VARCHAR(200) NOT NULL DEFAULT '',
        category VARCHAR(60) NOT NULL DEFAULT 'general',
        file_name VARCHAR(220) NOT NULL,
        duration_sec DECIMAL(10,2) NOT NULL DEFAULT 0,
        file_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        orig_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        source_type VARCHAR(20) NOT NULL DEFAULT 'upload',
        source_url VARCHAR(2048) NULL DEFAULT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uq_serial (display_serial),
        KEY idx_pub (is_published, sort_order, display_serial),
        KEY idx_cat (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ame_islamic_audio_ensure_upload_dir();

    $db->query("CREATE TABLE IF NOT EXISTS islamic_audio_prefs (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        show_home_fab TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $prefRes = $db->query('SELECT id FROM islamic_audio_prefs WHERE id = 1 LIMIT 1');
    if ($prefRes && $prefRes->num_rows === 0) {
        $db->query('INSERT INTO islamic_audio_prefs (id, show_home_fab) VALUES (1, 1)');
    }
}

function ame_islamic_audio_show_home_fab(mysqli $db): bool
{
    ame_islamic_audio_schema($db);
    $res = $db->query('SELECT show_home_fab FROM islamic_audio_prefs WHERE id = 1 LIMIT 1');
    if (!$res || $res->num_rows === 0) {
        return true;
    }
    $row = $res->fetch_assoc();
    return (int)($row['show_home_fab'] ?? 1) === 1;
}

/** @return array{show_home_fab:bool} */
function ame_islamic_audio_prefs(mysqli $db): array
{
    return ['show_home_fab' => ame_islamic_audio_show_home_fab($db)];
}

function ame_islamic_audio_set_show_home_fab(mysqli $db, bool $on): array
{
    ame_islamic_audio_schema($db);
    $flag = $on ? 1 : 0;
    $st = $db->prepare('INSERT INTO islamic_audio_prefs (id, show_home_fab, updated_at) VALUES (1, ?, NOW())
        ON DUPLICATE KEY UPDATE show_home_fab = VALUES(show_home_fab), updated_at = NOW()');
    if (!$st) {
        throw new RuntimeException('সেটিংস সেভ হয়নি।');
    }
    $st->bind_param('i', $flag);
    $st->execute();
    $st->close();
    return ['ok' => true, 'show_home_fab' => $on];
}

function ame_islamic_audio_doc_root(): string
{
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($root === '' || !is_dir($root)) {
        $root = dirname(__DIR__);
    }
    return $root;
}

function ame_islamic_audio_upload_dir(): string
{
    return ame_islamic_audio_doc_root() . '/uploads/islamic_audio/';
}

function ame_islamic_audio_public_url(string $fileName): string
{
    $fn = basename($fileName);
    return '/uploads/islamic_audio/' . rawurlencode($fn);
}

function ame_islamic_audio_stream_url(int $id): string
{
    return '/modules/islamic_audio_stream.php?id=' . max(1, $id);
}

function ame_islamic_audio_ensure_upload_dir(): void
{
    $dir = ame_islamic_audio_upload_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\nRequire all granted\n</IfModule>\n");
    }
    $idx = $dir . 'index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, '');
    }
}

function ame_islamic_audio_php_can_exec(): bool
{
    $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
    return !in_array('exec', $disabled, true) && !in_array('shell_exec', $disabled, true);
}

/** @return array{ytdlp:?string,ffmpeg:?string,exec_ok:bool} */
function ame_islamic_audio_server_tools(): array
{
    return [
        'ytdlp' => ame_islamic_audio_ytdlp_bin(),
        'ffmpeg' => ame_islamic_audio_ffmpeg_bin(),
        'exec_ok' => ame_islamic_audio_php_can_exec(),
    ];
}

function ame_islamic_audio_ffmpeg_bin(): ?string
{
    static $bin = null;
    if ($bin !== null) {
        return $bin === '' ? null : $bin;
    }
    if (defined('AME_FFMPEG_BIN') && (string)AME_FFMPEG_BIN !== '' && is_executable((string)AME_FFMPEG_BIN)) {
        $bin = (string)AME_FFMPEG_BIN;
        return $bin;
    }
    $home = (string)(getenv('HOME') ?: '');
    $candidates = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'];
    if ($home !== '') {
        $candidates[] = $home . '/bin/ffmpeg';
    }
    foreach ($candidates as $c) {
        if ($c === 'ffmpeg') {
            $out = [];
            $code = 1;
            @exec('command -v ffmpeg 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $bin = trim((string)$out[0]);
                return $bin;
            }
            continue;
        }
        if (is_executable($c)) {
            $bin = $c;
            return $bin;
        }
    }
    $bin = '';
    return null;
}

function ame_islamic_audio_shell_quote(string $s): string
{
    return escapeshellarg($s);
}

function ame_islamic_audio_probe_duration(string $path): float
{
    $ff = ame_islamic_audio_ffmpeg_bin();
    if ($ff === null || !is_file($path)) {
        return 0.0;
    }
    $cmd = $ff . ' -i ' . ame_islamic_audio_shell_quote($path) . ' 2>&1';
    $out = [];
    @exec($cmd, $out);
    $text = implode("\n", $out);
    if (preg_match('/Duration:\s*(\d+):(\d+):(\d+(?:\.\d+)?)/', $text, $m)) {
        return ((int)$m[1]) * 3600 + ((int)$m[2]) * 60 + (float)$m[3];
    }
    return 0.0;
}

/**
 * @return array{file_name:string,bytes:int,orig:int,duration:float,mime:string}
 */
function ame_islamic_audio_process_file(string $srcPath, int $trackId): array
{
    if (!is_file($srcPath)) {
        throw new RuntimeException('অডিও ফাইল পড়া যায়নি।');
    }
    $orig = (int)filesize($srcPath);
    if ($orig <= 0) {
        throw new RuntimeException('খালি ফাইল আপলোড করা যাবে না।');
    }
    if ($orig > 120 * 1024 * 1024) {
        throw new RuntimeException('ফাইল ১২০ মেগাবাইটের বেশি হলে গ্রহণ করা যাবে না।');
    }

    ame_islamic_audio_ensure_upload_dir();
    $dir = ame_islamic_audio_upload_dir();
    $base = 'ia_' . max(1, $trackId) . '_' . time() . '_' . random_int(10000, 99999);
    $outMp3 = $dir . $base . '.mp3';

    $ff = ame_islamic_audio_ffmpeg_bin();
    $ok = false;
    if ($ff !== null) {
        $af = 'loudnorm=I=-16:TP=-1.5:LRA=11,acompressor=threshold=-18dB:ratio=3:attack=5:release=50';
        $cmd = $ff . ' -y -hide_banner -loglevel error -i ' . ame_islamic_audio_shell_quote($srcPath)
            . ' -af ' . ame_islamic_audio_shell_quote($af)
            . ' -codec:a libmp3lame -b:a 96k -ar 44100 -ac 2 '
            . ame_islamic_audio_shell_quote($outMp3) . ' 2>&1';
        $log = [];
        $code = 1;
        @exec($cmd, $log, $code);
        $ok = ($code === 0 && is_file($outMp3) && filesize($outMp3) > 0);
    }

    if (!$ok) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)finfo_file($finfo, $srcPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $ext = 'mp3';
        if (str_contains($mime, 'wav')) {
            $ext = 'wav';
        } elseif (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
            $ext = 'mp3';
        } elseif (str_contains($mime, 'ogg')) {
            $ext = 'ogg';
        } elseif (str_contains($mime, 'mp4') || str_contains($mime, 'm4a') || str_contains($mime, 'aac')) {
            $ext = 'm4a';
        } elseif (str_contains($mime, 'webm')) {
            $ext = 'webm';
        }
        $outMp3 = $dir . $base . '.' . $ext;
        if (!@copy($srcPath, $outMp3)) {
            throw new RuntimeException('অডিও সার্ভারে রাখা যায়নি।');
        }
    }

    $bytes = (int)filesize($outMp3);
    $duration = ame_islamic_audio_probe_duration($outMp3);
    $outExt = strtolower(pathinfo($outMp3, PATHINFO_EXTENSION));
    $mimeOut = match ($outExt) {
        'm4a', 'mp4' => 'audio/mp4',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'webm' => 'audio/webm',
        default => 'audio/mpeg',
    };
    return [
        'file_name' => basename($outMp3),
        'bytes' => $bytes,
        'orig' => $orig,
        'duration' => $duration,
        'mime' => $mimeOut,
    ];
}

function ame_islamic_audio_url_allowed(string $url): string
{
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('সঠিক অডিও লিংক দিন (https://...)');
    }
    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('শুধু http/https লিংক চলবে।');
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
        throw new InvalidArgumentException('এই হোস্ট থেকে ডাউনলোড অনুমোদিত নয়।');
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (!filter_var($host, FILTER_VALIDATE_IP, $flags)) {
            throw new InvalidArgumentException('অভ্যন্তরীণ আইপি থেকে ডাউনলোড বন্ধ।');
        }
    }
    return $url;
}

function ame_islamic_audio_download_url(string $url, string $destPath): int
{
    $url = ame_islamic_audio_url_allowed($url);
    $fp = @fopen($destPath, 'wb');
    if ($fp === false) {
        throw new RuntimeException('টেম্প ফাইল তৈরি হয়নি।');
    }
    $ch = curl_init($url);
    if ($ch === false) {
        fclose($fp);
        throw new RuntimeException('cURL শুরু হয়নি।');
    }
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT => 'APONSHOP-IslamicAudio/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FAILONERROR => false,
    ]);
    $ok = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    if (!$ok || $code < 200 || $code >= 400) {
        @unlink($destPath);
        throw new RuntimeException('লিংক থেকে ডাউনলোড হয়নি (' . $code . '). ' . $err);
    }
    $size = (int)filesize($destPath);
    if ($size <= 0) {
        @unlink($destPath);
        throw new RuntimeException('ডাউনলোডকৃত ফাইল খালি।');
    }
    if ($size > 120 * 1024 * 1024) {
        @unlink($destPath);
        throw new RuntimeException('রিমোট ফাইল ১২০ মেগাবাইটের বেশি।');
    }
    $looksAudio = str_contains(strtolower($ctype), 'audio')
        || str_contains(strtolower($ctype), 'mpeg')
        || preg_match('/\.(mp3|wav|m4a|ogg|aac)(\?|$)/i', $url);
    if (!$looksAudio) {
        @unlink($destPath);
        throw new RuntimeException('লিংকটি অডিও ফাইল নয় বলে মনে হচ্ছে।');
    }
    return $size;
}

function ame_islamic_audio_is_youtube_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host === '') {
        return false;
    }
    if ($host === 'youtu.be' || $host === 'www.youtu.be') {
        return true;
    }
    if (str_ends_with($host, 'youtube.com') || $host === 'youtube.com') {
        return true;
    }
    return false;
}

function ame_islamic_audio_youtube_url_allowed(string $url): string
{
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('সঠিক YouTube লিংক দিন।');
    }
    if (!ame_islamic_audio_is_youtube_url($url)) {
        throw new InvalidArgumentException('শুধু YouTube (youtu.be / youtube.com) লিংক চলবে।');
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('শুধু https লিংক চলবে।');
    }
    return $url;
}

function ame_islamic_audio_youtube_is_playlist(string $url): bool
{
    return (bool)preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $url);
}

function ame_islamic_audio_youtube_is_channel(string $url): bool
{
    $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?? ''));
    if ($path === '') {
        return false;
    }
    if (preg_match('#/@([a-zA-Z0-9._-]+)(/|$)#', $path)) {
        return true;
    }
    if (preg_match('#/(channel|c|user)/[^/]+#', $path)) {
        return true;
    }
    return false;
}

/** চ্যানেল হোম লিংক → /videos ট্যাব (ওয়াজ/গজল/Videos তালিকা) */
function ame_islamic_audio_youtube_resolve_feed_url(string $url): string
{
    $url = ame_islamic_audio_youtube_url_allowed($url);
    $parts = parse_url($url);
    $path = (string)($parts['path'] ?? '');
    $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.youtube.com') . $path;
    if (preg_match('#/@([a-zA-Z0-9._-]+)(/(videos|streams|shorts|playlists))?#i', $path, $m)) {
        if (empty($m[2])) {
            return rtrim($base, '/') . '/videos';
        }
        return $base;
    }
    if (preg_match('#/(channel|c|user)/([^/]+)#i', $path) && !str_ends_with(strtolower($path), '/videos')) {
        return rtrim($base, '/') . '/videos';
    }
    return $url;
}

function ame_islamic_audio_youtube_default_speaker(string $url): string
{
    if (preg_match('#/@([a-zA-Z0-9._-]+)#', $url, $m)) {
        return '@' . $m[1];
    }
    return '';
}

function ame_islamic_audio_ytdlp_bin(): ?string
{
    static $bin = null;
    if ($bin !== null) {
        return $bin === '' ? null : $bin;
    }
    if (defined('AME_YTDLP_BIN') && (string)AME_YTDLP_BIN !== '') {
        $custom = trim((string)AME_YTDLP_BIN);
        if ($custom === 'python3 -m yt_dlp' || $custom === 'python -m yt_dlp') {
            $out = [];
            $code = 1;
            $mod = str_contains($custom, 'python3') ? 'python3' : 'python';
            @exec($mod . ' -m yt_dlp --version 2>/dev/null', $out, $code);
            if ($code === 0) {
                $bin = $mod . ' -m yt_dlp';
                return $bin;
            }
        } elseif (is_executable($custom)) {
            $bin = $custom;
            return $bin;
        }
    }
    $home = (string)(getenv('HOME') ?: '');
    $root = dirname(__DIR__);
    $candidates = [];
    if ($home !== '') {
        $candidates[] = $home . '/.local/bin/yt-dlp';
        $candidates[] = $home . '/bin/yt-dlp';
        $candidates[] = $home . '/yt-dlp';
    }
    $candidates[] = $root . '/tools/yt-dlp';
    $candidates[] = $root . '/bin/yt-dlp';
    $candidates[] = 'yt-dlp';
    $candidates[] = '/usr/bin/yt-dlp';
    $candidates[] = '/usr/local/bin/yt-dlp';
    foreach ($candidates as $c) {
        if ($c === 'yt-dlp') {
            $out = [];
            $code = 1;
            @exec('command -v yt-dlp 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $bin = trim((string)$out[0]);
                return $bin;
            }
            continue;
        }
        if (is_executable($c)) {
            $bin = $c;
            return $bin;
        }
    }
    foreach (['python3', 'python'] as $py) {
        $out = [];
        $code = 1;
        @exec($py . ' -m yt_dlp --version 2>/dev/null', $out, $code);
        if ($code === 0) {
            $bin = $py . ' -m yt_dlp';
            return $bin;
        }
    }
    $bin = '';
    return null;
}

/**
 * @return array<int, array{id:string,title:string,webpage_url:string}>
 */
function ame_islamic_audio_youtube_list_entries(string $url, int $maxItems = 25): array
{
    $ytdlp = ame_islamic_audio_ytdlp_bin();
    if (!ame_islamic_audio_php_can_exec()) {
        throw new RuntimeException('PHP exec বন্ধ — হোস্টার exec/shell_exec চালু করুন, অথবা VPS ব্যবহার করুন।');
    }
    if ($ytdlp === null) {
        throw new RuntimeException(
            'সার্ভারে yt-dlp নেই। SSH এ সাইটের tools/install_yt_dlp_cpanel.sh চালান, '
            . 'অথবা includes/config.php তে AME_YTDLP_BIN পাথ দিন। বিস্তারিত: YT_DLP_HOSTING_BN.txt'
        );
    }
    $raw = ame_islamic_audio_youtube_url_allowed($url);
    $maxItems = max(1, min(50, $maxItems));
    $isChannel = ame_islamic_audio_youtube_is_channel($raw);
    $isPl = ame_islamic_audio_youtube_is_playlist($raw);
    $url = ($isChannel || $isPl) ? ame_islamic_audio_youtube_resolve_feed_url($raw) : $raw;
    $useFlat = $isPl || $isChannel;
    $args = $useFlat
        ? ['--flat-playlist', '--print', '%(id)s\t%(title)s\t%(webpage_url)s', $url]
        : ['--no-playlist', '--print', '%(id)s\t%(title)s\t%(webpage_url)s', $url];
    $cmd = ame_islamic_audio_ytdlp_cmd($args);
    $out = [];
    $code = 1;
    @exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException('YouTube তথ্য পড়া যায়নি: ' . implode(' ', array_slice($out, -3)));
    }
    $entries = [];
    foreach ($out as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '[')) {
            continue;
        }
        $parts = explode("\t", $line, 3);
        if (count($parts) < 2 || $parts[0] === '') {
            continue;
        }
        $entries[] = [
            'id' => $parts[0],
            'title' => $parts[1] !== '' ? $parts[1] : ('YouTube ' . $parts[0]),
            'webpage_url' => $parts[2] ?? ('https://www.youtube.com/watch?v=' . $parts[0]),
        ];
        if (!$useFlat) {
            break;
        }
        if (count($entries) >= $maxItems) {
            break;
        }
    }
    if ($entries === []) {
        throw new RuntimeException('YouTube থেকে কোনো ভিডিও খুঁজে পাওয়া যায়নি।');
    }
    return $entries;
}

function ame_islamic_audio_ytdlp_cmd(array $args): string
{
    $ytdlp = ame_islamic_audio_ytdlp_bin();
    if ($ytdlp === null) {
        throw new RuntimeException('yt-dlp উপলব্ধ নয়।');
    }
    $quoted = array_map(static fn ($a) => ame_islamic_audio_shell_quote((string)$a), $args);
    if ($ytdlp === 'python3 -m yt_dlp') {
        return 'python3 -m yt_dlp ' . implode(' ', $quoted);
    }
    return ame_islamic_audio_shell_quote($ytdlp) . ' ' . implode(' ', $quoted);
}

/**
 * @return list<string> absolute paths to downloaded audio files
 */
function ame_islamic_audio_youtube_download_one(string $webpageUrl, string $dir, string $videoId): string
{
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('টেম্প ফোল্ডার তৈরি হয়নি।');
    }
    $template = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $videoId) . '.%(ext)s';
    $args = [
        '--no-playlist',
        '--no-warnings',
        '--no-progress',
        '-f', 'bestaudio/best',
        '--extract-audio',
        '--audio-format', 'm4a',
        '--restrict-filenames',
        '-o', $template,
        $webpageUrl,
    ];
    $cmd = ame_islamic_audio_ytdlp_cmd($args);
    $log = [];
    $code = 1;
    @exec($cmd . ' 2>&1', $log, $code);
    if ($code !== 0) {
        throw new RuntimeException('YouTube অডিও ডাউনলোড ব্যর্থ (' . $videoId . ').');
    }
    $glob = glob($dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $videoId) . '.*') ?: [];
    foreach ($glob as $path) {
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }
    }
    throw new RuntimeException('ডাউনলোড ফাইল পাওয়া যায়নি (' . $videoId . ').');
}

/**
 * @param array{title?:string,speaker?:string,category?:string,youtube_url?:string,is_published?:bool} $meta
 */
function ame_islamic_audio_ingest_youtube(mysqli $db, array $meta): array
{
    ame_islamic_audio_schema($db);
    @set_time_limit(600);
    $url = trim((string)($meta['youtube_url'] ?? $meta['audio_url'] ?? ''));
    $url = ame_islamic_audio_youtube_url_allowed($url);
    $maxItems = max(1, min(50, (int)($meta['max_items'] ?? 25)));
    $entries = ame_islamic_audio_youtube_list_entries($url, $maxItems);
    $speaker = trim((string)($meta['speaker'] ?? ''));
    if ($speaker === '' && ame_islamic_audio_youtube_is_channel($url)) {
        $speaker = ame_islamic_audio_youtube_default_speaker($url);
    }
    $category = trim((string)($meta['category'] ?? 'general'));
    $published = !array_key_exists('is_published', $meta) || !empty($meta['is_published']);
    $fallbackTitle = trim((string)($meta['title'] ?? ''));
    $tmpdir = sys_get_temp_dir() . '/ia_yt_batch_' . bin2hex(random_bytes(4));
    $ids = [];
    try {
        foreach ($entries as $entry) {
            $title = $fallbackTitle !== '' && count($entries) === 1
                ? $fallbackTitle
                : (string)$entry['title'];
            $title = mb_substr(trim($title), 0, 255);
            $srcPath = ame_islamic_audio_youtube_download_one((string)$entry['webpage_url'], $tmpdir, (string)$entry['id']);
            $orig = (int)filesize($srcPath);
            $placeholderId = ame_islamic_audio_next_serial($db) + 100000;
            $processed = ame_islamic_audio_process_file($srcPath, $placeholderId);
            @unlink($srcPath);
            $ids[] = ame_islamic_audio_insert_row($db, [
                'title' => $title,
                'speaker' => $speaker,
                'category' => $category,
                'file_name' => $processed['file_name'],
                'duration_sec' => $processed['duration'],
                'file_size_bytes' => $processed['bytes'],
                'orig_size_bytes' => $orig,
                'source_type' => 'youtube',
                'source_url' => (string)$entry['webpage_url'],
                'is_published' => $published,
            ]);
        }
    } finally {
        if (is_dir($tmpdir)) {
            foreach (glob($tmpdir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpdir);
        }
    }
    return [
        'ok' => true,
        'imported' => count($ids),
        'ids' => $ids,
        'tracks' => ame_islamic_audio_admin_list($db),
    ];
}

function ame_islamic_audio_next_serial(mysqli $db): int
{
    $row = $db->query('SELECT COALESCE(MAX(display_serial), 0) + 1 AS n FROM islamic_audio_tracks')->fetch_assoc();
    return max(1, (int)($row['n'] ?? 1));
}

function ame_islamic_audio_row_to_public(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'serial' => (int)$row['display_serial'],
        'title' => (string)$row['title'],
        'speaker' => (string)$row['speaker'],
        'category' => (string)$row['category'],
        'category_label' => ame_islamic_audio_categories()[(string)$row['category']] ?? (string)$row['category'],
        'url' => ame_islamic_audio_stream_url((int)$row['id']),
        'file_url' => ame_islamic_audio_public_url((string)$row['file_name']),
        'duration' => round((float)$row['duration_sec'], 1),
        'size_mb' => round(((int)$row['file_size_bytes']) / 1048576, 2),
        'published' => (int)$row['is_published'] === 1,
    ];
}

function ame_islamic_audio_list_published(mysqli $db, int $limit = 50): array
{
    ame_islamic_audio_schema($db);
    $limit = max(1, min(200, $limit));
    $sql = "SELECT * FROM islamic_audio_tracks WHERE is_published = 1 ORDER BY sort_order ASC, display_serial ASC LIMIT $limit";
    $res = $db->query($sql);
    $out = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = ame_islamic_audio_row_to_public($r);
        }
        $res->close();
    }
    return $out;
}

function ame_islamic_audio_admin_list(mysqli $db): array
{
    ame_islamic_audio_schema($db);
    $res = $db->query('SELECT * FROM islamic_audio_tracks ORDER BY display_serial ASC');
    $out = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = ame_islamic_audio_row_to_public($r) + [
                'source_type' => (string)$r['source_type'],
                'source_url' => (string)($r['source_url'] ?? ''),
                'orig_mb' => round(((int)$r['orig_size_bytes']) / 1048576, 2),
            ];
        }
        $res->close();
    }
    return $out;
}

function ame_islamic_audio_insert_row(mysqli $db, array $fields): int
{
    $serial = ame_islamic_audio_next_serial($db);
    if (isset($fields['display_serial']) && (int)$fields['display_serial'] > 0) {
        $serial = (int)$fields['display_serial'];
    }
    $st = $db->prepare('INSERT INTO islamic_audio_tracks
        (display_serial, title, speaker, category, file_name, duration_sec, file_size_bytes, orig_size_bytes,
         source_type, source_url, is_published, sort_order, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    if (!$st) {
        throw new RuntimeException('ডাটাবেজে লিখা যায়নি।');
    }
    $title = (string)($fields['title'] ?? '');
    $speaker = (string)($fields['speaker'] ?? '');
    $category = (string)($fields['category'] ?? 'general');
    $file = (string)($fields['file_name'] ?? '');
    $dur = (float)($fields['duration_sec'] ?? 0);
    $bytes = (int)($fields['file_size_bytes'] ?? 0);
    $orig = (int)($fields['orig_size_bytes'] ?? 0);
    $srcType = (string)($fields['source_type'] ?? 'upload');
    $srcUrl = $fields['source_url'] ?? '';
    if ($srcUrl === null) {
        $srcUrl = '';
    }
    $pub = !empty($fields['is_published']) ? 1 : 0;
    $sort = (int)($fields['sort_order'] ?? $serial);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $st->bind_param(
        'issssdiissiii',
        $serial,
        $title,
        $speaker,
        $category,
        $file,
        $dur,
        $bytes,
        $orig,
        $srcType,
        $srcUrl,
        $pub,
        $sort,
        $uid
    );
    if (!$st->execute()) {
        $st->close();
        throw new RuntimeException('সেভ ব্যর্থ: ' . $db->error);
    }
    $id = (int)$st->insert_id;
    $st->close();
    return $id;
}

/**
 * @param array{title:string,speaker?:string,category?:string,is_published?:bool,display_serial?:int} $meta
 */
/** @return array<int, array<string, mixed>> */
function ame_islamic_audio_normalize_upload_files(string $field): array
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'] ?? null)) {
        return [$f];
    }
    $out = [];
    $n = count($f['name']);
    for ($i = 0; $i < $n; $i++) {
        $out[] = [
            'name' => $f['name'][$i] ?? '',
            'type' => $f['type'][$i] ?? '',
            'tmp_name' => $f['tmp_name'][$i] ?? '',
            'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $f['size'][$i] ?? 0,
        ];
    }
    return $out;
}

/**
 * @param array<int, array> $files
 * @param array{title?:string,speaker?:string,category?:string,is_published?:bool} $meta
 * @return array{ok:bool,imported:int,failed:array<int,string>,ids:array<int,int>}
 */
function ame_islamic_audio_ingest_upload_many(mysqli $db, array $files, array $meta): array
{
    $max = 40;
    if (count($files) > $max) {
        throw new InvalidArgumentException('একবারে সর্বোচ্চ ' . $max . 'টি ফাইল।');
    }
  $ids = [];
    $failed = [];
    $sharedTitle = trim((string)($meta['title'] ?? ''));
    $multi = count($files) > 1;
    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $rowMeta = $meta;
        if ($multi) {
            $rowMeta['title'] = $sharedTitle !== '' ? $sharedTitle : '';
        }
        try {
            $res = ame_islamic_audio_ingest_upload($db, $file, $rowMeta);
            $ids[] = (int)$res['id'];
        } catch (Throwable $e) {
            $failed[] = (($file['name'] ?? 'ফাইল') . ': ' . $e->getMessage());
        }
    }
    if ($ids === [] && $failed !== []) {
        throw new RuntimeException(implode(' | ', $failed));
    }
    return ['ok' => true, 'imported' => count($ids), 'failed' => $failed, 'ids' => $ids];
}

function ame_islamic_audio_ingest_upload(mysqli $db, array $file, array $meta): array
{
    ame_islamic_audio_schema($db);
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('অডিও ফাইল বাছুন।');
    }
    if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI === 'cli') {
        // CLI tests
    } elseif (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('অবৈধ আপলোড।');
    }

    $title = trim((string)($meta['title'] ?? ''));
    if ($title === '') {
        $title = pathinfo((string)($file['name'] ?? 'অডিও'), PATHINFO_FILENAME);
    }
    $placeholderId = ame_islamic_audio_next_serial($db) + 100000;
    $processed = ame_islamic_audio_process_file($file['tmp_name'], $placeholderId);
    $id = ame_islamic_audio_insert_row($db, [
        'title' => $title,
        'speaker' => trim((string)($meta['speaker'] ?? '')),
        'category' => trim((string)($meta['category'] ?? 'general')),
        'file_name' => $processed['file_name'],
        'duration_sec' => $processed['duration'],
        'file_size_bytes' => $processed['bytes'],
        'orig_size_bytes' => $processed['orig'],
        'source_type' => 'upload',
        'is_published' => !array_key_exists('is_published', $meta) || !empty($meta['is_published']),
        'display_serial' => (int)($meta['display_serial'] ?? 0),
    ]);
    return ['ok' => true, 'id' => $id, 'track' => ame_islamic_audio_get($db, $id)];
}

/**
 * @param array{title:string,speaker?:string,category?:string,audio_url:string,is_published?:bool} $meta
 */
function ame_islamic_audio_ingest_url(mysqli $db, array $meta): array
{
    ame_islamic_audio_schema($db);
    $url = trim((string)($meta['audio_url'] ?? ''));
    if (ame_islamic_audio_is_youtube_url($url)) {
        throw new InvalidArgumentException('YouTube লিংক এখানে দেবেন না। PC/মোবাইল থেকে MP3 আপলোড করুন।');
    }
    $title = trim((string)($meta['title'] ?? ''));
    if ($title === '') {
        $title = 'অডিও ' . date('Y-m-d H:i');
    }
    $tmp = sys_get_temp_dir() . '/ia_dl_' . bin2hex(random_bytes(8));
    try {
        $orig = ame_islamic_audio_download_url($url, $tmp);
        $placeholderId = ame_islamic_audio_next_serial($db) + 100000;
        $processed = ame_islamic_audio_process_file($tmp, $placeholderId);
        @unlink($tmp);
        $id = ame_islamic_audio_insert_row($db, [
            'title' => $title,
            'speaker' => trim((string)($meta['speaker'] ?? '')),
            'category' => trim((string)($meta['category'] ?? 'general')),
            'file_name' => $processed['file_name'],
            'duration_sec' => $processed['duration'],
            'file_size_bytes' => $processed['bytes'],
            'orig_size_bytes' => $orig,
            'source_type' => 'url',
            'source_url' => $url,
            'is_published' => !array_key_exists('is_published', $meta) || !empty($meta['is_published']),
        ]);
        return ['ok' => true, 'id' => $id, 'track' => ame_islamic_audio_get($db, $id)];
    } catch (Throwable $e) {
        @unlink($tmp);
        throw $e;
    }
}

function ame_islamic_audio_get(mysqli $db, int $id): ?array
{
    $st = $db->prepare('SELECT * FROM islamic_audio_tracks WHERE id = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $id);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return $row ? ame_islamic_audio_row_to_public($row) : null;
}

function ame_islamic_audio_update(mysqli $db, int $id, array $in): array
{
    ame_islamic_audio_schema($db);
    $row = $db->prepare('SELECT id FROM islamic_audio_tracks WHERE id = ?');
    if (!$row) {
        throw new RuntimeException('অডিও নেই।');
    }
    $row->bind_param('i', $id);
    $row->execute();
    $exists = $row->get_result()?->fetch_assoc();
    $row->close();
    if (!$exists) {
        throw new RuntimeException('অডিও খুঁজে পাওয়া যায়নি।');
    }

    $fields = [];
    $types = '';
    $vals = [];
    if (isset($in['title'])) {
        $fields[] = 'title = ?';
        $types .= 's';
        $vals[] = trim((string)$in['title']);
    }
    if (isset($in['speaker'])) {
        $fields[] = 'speaker = ?';
        $types .= 's';
        $vals[] = trim((string)$in['speaker']);
    }
    if (isset($in['category'])) {
        $fields[] = 'category = ?';
        $types .= 's';
        $vals[] = trim((string)$in['category']);
    }
    if (isset($in['display_serial'])) {
        $serial = max(1, (int)$in['display_serial']);
        $dup = $db->prepare('SELECT id FROM islamic_audio_tracks WHERE display_serial = ? AND id <> ? LIMIT 1');
        if ($dup) {
            $dup->bind_param('ii', $serial, $id);
            $dup->execute();
            $taken = $dup->get_result()?->fetch_assoc();
            $dup->close();
            if ($taken) {
                throw new InvalidArgumentException('সিরিয়াল #' . $serial . ' আগে থেকেই আছে।');
            }
        }
        $fields[] = 'display_serial = ?';
        $types .= 'i';
        $vals[] = $serial;
    }
    if (isset($in['is_published'])) {
        $fields[] = 'is_published = ?';
        $types .= 'i';
        $vals[] = !empty($in['is_published']) ? 1 : 0;
    }
    if ($fields === []) {
        return ['ok' => true, 'track' => ame_islamic_audio_get($db, $id)];
    }
    $fields[] = 'updated_at = NOW()';
    $sql = 'UPDATE islamic_audio_tracks SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $types .= 'i';
    $vals[] = $id;
    $st = $db->prepare($sql);
    if (!$st) {
        throw new RuntimeException('আপডেট প্রস্তুত হয়নি।');
    }
    $st->bind_param($types, ...$vals);
    $st->execute();
    $st->close();
    return ['ok' => true, 'track' => ame_islamic_audio_get($db, $id)];
}

function ame_islamic_audio_delete(mysqli $db, int $id): array
{
    ame_islamic_audio_schema($db);
    $st = $db->prepare('SELECT file_name FROM islamic_audio_tracks WHERE id = ?');
    if (!$st) {
        throw new RuntimeException('মুছতে পারিনি।');
    }
    $st->bind_param('i', $id);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    if (!$row) {
        throw new RuntimeException('অডিও নেই।');
    }
    $fn = basename((string)$row['file_name']);
    $path = ame_islamic_audio_upload_dir() . $fn;
    if ($fn !== '' && is_file($path)) {
        @unlink($path);
    }
    $del = $db->prepare('DELETE FROM islamic_audio_tracks WHERE id = ?');
    if (!$del) {
        throw new RuntimeException('ডিলিট ব্যর্থ।');
    }
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
    return ['ok' => true, 'deleted_id' => $id];
}

function ame_islamic_audio_can_admin(): bool
{
    if (!empty($_SESSION['is_super_admin'])) {
        return true;
    }
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    return in_array($role, ['admin', 'super_admin', 'superadmin', 'owner'], true);
}

/** হোম/সব পেজ — লগইন ইউজারে অডিও FAB (ট্র্যাক না থাকলেও আইকন দেখায়) */
/** @param array{hub?:string,autoplay_next?:bool,panel_open?:bool} $opts */
function ame_islamic_audio_render_widget(mysqli $db, bool $floating = true, array $opts = []): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $isHub = isset($opts['hub']) && (string)$opts['hub'] === 'sm40';
    if (!$isHub && !ame_islamic_audio_show_home_fab($db)) {
        return;
    }
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
    $tracks = ame_islamic_audio_list_published($db);
    $json = htmlspecialchars(json_encode($tracks, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.900';
    $dock = $floating && !$isHub ? ' ia-home--dock' : '';
    if ($isHub) {
        $dock .= ' ia-home--sm40-hub';
    }
    $admin = ame_islamic_audio_can_admin();
    $hubAttr = $isHub ? ' data-ia-hub="sm40"' : '';
    $autoAttr = !empty($opts['autoplay_next']) ? ' data-ia-autoplay-next="1"' : '';
    $panelOpen = $isHub || !empty($opts['panel_open']);
    echo '<section class="ia-home' . $dock . '" id="iaHome" data-ia-tracks="' . $json . '"' . $hubAttr . $autoAttr . ' aria-label="ইসলামিক অডিও">';
    if (!$isHub) {
        echo '<button type="button" class="ia-fab" id="iaFab" aria-expanded="false" aria-controls="iaPanel" title="টেনে সরান — ক্লিকে অডিও খুলুন">';
        echo '<span class="ia-fab-ic" aria-hidden="true">🎧</span><span class="ia-fab-txt">অডিও</span></button>';
    }
    echo '<div class="ia-panel" id="iaPanel"' . ($panelOpen ? '' : ' hidden') . '>';
    echo '<header class="ia-head"><h2 class="ia-title">ইসলামিক অডিও</h2>';
    echo '<button type="button" class="ia-close" id="iaClose" aria-label="বন্ধ">×</button></header>';
    echo '<p class="ia-seq-hint">একটি শেষ হলে পরের অডিও স্বয়ংক্রিয় চলবে — বিক্রয়·পেমেন্ট পেজেও চালু থাকতে পারে</p>';
    echo '<div class="ia-player" id="iaPlayerHost"></div>';
  echo '<p class="ia-empty" id="iaEmpty" hidden>এখনো প্রকাশিত অডিও নেই।' . ($admin ? ' ইঞ্জিন থেকে আপলোড করুন।' : '') . '</p>';
    if ($admin) {
        echo '<p class="ia-admin-cta" id="iaAdminCta"><a href="/modules/settings/islamic_audio.php">+ অডিও যোগ / ম্যানেজ</a></p>';
    }
    echo '<ul class="ia-list" id="iaList" role="list"></ul></div></section>';
    echo '<link rel="stylesheet" href="/assets/css/islamic-audio.css?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '">';
    echo '<script src="/assets/js/islamic-audio.js?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" defer></script>';
}

/** @deprecated alias */
function ame_islamic_audio_render_home(mysqli $db): void
{
    ame_islamic_audio_render_widget($db, false);
}