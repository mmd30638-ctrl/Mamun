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

function ame_islamic_audio_ffmpeg_bin(): ?string
{
    static $bin = null;
    if ($bin !== null) {
        return $bin === '' ? null : $bin;
    }
    $candidates = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'];
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
        }
        $outMp3 = $dir . $base . '.' . $ext;
        if (!@copy($srcPath, $outMp3)) {
            throw new RuntimeException('অডিও সার্ভারে রাখা যায়নি।');
        }
    }

    $bytes = (int)filesize($outMp3);
    $duration = ame_islamic_audio_probe_duration($outMp3);
  return [
        'file_name' => basename($outMp3),
        'bytes' => $bytes,
        'orig' => $orig,
        'duration' => $duration,
        'mime' => 'audio/mpeg',
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
        'url' => ame_islamic_audio_public_url((string)$row['file_name']),
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

function ame_islamic_audio_render_home(mysqli $db): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    $tracks = ame_islamic_audio_list_published($db);
    if ($tracks === []) {
        return;
    }
    $json = htmlspecialchars(json_encode($tracks, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.900';
    echo '<section class="ia-home" id="iaHome" data-ia-tracks="' . $json . '" aria-label="ইসলামিক অডিও">';
    echo '<button type="button" class="ia-fab" id="iaFab" aria-expanded="false" aria-controls="iaPanel">';
    echo '<span class="ia-fab-ic" aria-hidden="true">🔊</span><span class="ia-fab-txt">অডিও শুনুন</span></button>';
    echo '<div class="ia-panel" id="iaPanel" hidden>';
    echo '<header class="ia-head"><h2 class="ia-title">ইসলামিক অডিও</h2>';
    echo '<button type="button" class="ia-close" id="iaClose" aria-label="বন্ধ">×</button></header>';
    echo '<div class="ia-player" id="iaPlayerHost"></div>';
    echo '<ul class="ia-list" id="iaList" role="list"></ul></div></section>';
    echo '<link rel="stylesheet" href="/assets/css/islamic-audio.css?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '">';
    echo '<script src="/assets/js/islamic-audio.js?v=' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '" defer></script>';
}