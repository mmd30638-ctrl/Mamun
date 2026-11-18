<?php
/**
 * Unlimited family kitab reader — schema + uploads.
 */
function ame_kitab_ensure(mysqli $db): void
{
    if (function_exists('fam_ensure_column')) {
        fam_ensure_column($db, 'family_books', 'pdf_file', 'VARCHAR(255) DEFAULT NULL');
        fam_ensure_column($db, 'family_books', 'page_count', 'INT NOT NULL DEFAULT 0');
        fam_ensure_column($db, 'family_books', 'last_page', 'INT NOT NULL DEFAULT 1');
        foreach ([
            'subtitle' => 'VARCHAR(255) DEFAULT NULL',
            'translator' => 'VARCHAR(160) DEFAULT NULL',
            'edition' => 'VARCHAR(80) DEFAULT NULL',
            'pub_year' => 'VARCHAR(12) DEFAULT NULL',
            'subcategory' => 'VARCHAR(80) DEFAULT NULL',
            'description' => 'TEXT',
            'cover_file' => 'VARCHAR(255) DEFAULT NULL',
            'file_size' => 'INT NOT NULL DEFAULT 0',
            'file_type' => 'VARCHAR(20) DEFAULT NULL',
            'tags' => 'VARCHAR(500) DEFAULT NULL',
            'favorite' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'read_status' => "VARCHAR(20) NOT NULL DEFAULT 'not_started'",
            'visibility' => "VARCHAR(20) NOT NULL DEFAULT 'family'",
            'updated_at' => 'DATETIME NULL',
            'archived_at' => 'DATETIME NULL',
        ] as $col => $def) {
            fam_ensure_column($db, 'family_books', $col, $def);
        }
    }
    $db->query("CREATE TABLE IF NOT EXISTS family_book_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        page_no INT NOT NULL,
        image_file VARCHAR(255) NOT NULL DEFAULT '',
        ocr_text MEDIUMTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY book_page (book_id, page_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS family_book_folds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        page_no INT NOT NULL,
        note VARCHAR(160) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY book_fold (book_id, page_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (function_exists('fam_ensure_column')) {
        fam_ensure_column($db, 'family_book_pages', 'ocr_text', 'MEDIUMTEXT');
        fam_ensure_column($db, 'family_book_folds', 'title', 'VARCHAR(120) DEFAULT NULL');
    } else {
        @$db->query("ALTER TABLE family_book_pages ADD COLUMN ocr_text MEDIUMTEXT");
    }
    $db->query("CREATE TABLE IF NOT EXISTS family_book_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        user_id INT NOT NULL DEFAULT 0,
        page_no INT NOT NULL,
        note_text TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY book_page (book_id, page_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS family_book_highlights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        user_id INT NOT NULL DEFAULT 0,
        page_no INT NOT NULL,
        quote VARCHAR(500) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY book_page (book_id, page_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS family_book_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        user_id INT NOT NULL DEFAULT 0,
        page_no INT NOT NULL DEFAULT 1,
        status VARCHAR(20) NOT NULL DEFAULT 'reading',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY book_user (book_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS family_book_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        user_id INT NOT NULL DEFAULT 0,
        page_no INT NOT NULL DEFAULT 1,
        progress_pct INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'reading',
        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY book_user (book_id, user_id, read_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS family_book_toc (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        title VARCHAR(180) NOT NULL,
        page_no INT NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY book_id (book_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @$db->query("UPDATE family_books SET read_status='completed' WHERE is_read=1 AND read_status='not_started'");
    ame_kitab_protect_dir();
}

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

function ame_kitab_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads/family';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function ame_kitab_save_file(array $info, array $okExt, int $maxBytes): ?string
{
    if (($info['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)($info['error'] ?? 1) !== UPLOAD_ERR_OK || empty($info['tmp_name']) || !is_uploaded_file($info['tmp_name'])) {
        throw new RuntimeException('ফাইল আপলোড হয়নি।');
    }
    if ((int)($info['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('ফাইল বড়। ছোট করে আবার দিন।');
    }
    $ext = strtolower(pathinfo((string)($info['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $okExt, true)) {
        throw new RuntimeException(function_exists('ame_l') ? ame_l('এই ফাইল টাইপ চলবে না।', 'This file type is not allowed.') : 'এই ফাইল টাইপ চলবে না।');
    }
    if (class_exists('finfo')) {
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($info['tmp_name']);
        $okMime = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];
        if ($mime !== '' && isset($okMime[$ext]) && !in_array($mime, $okMime[$ext], true)) {
            throw new RuntimeException(function_exists('ame_l') ? ame_l('এই ফাইলটি গ্রহণ করা যায়নি।', 'This file was not accepted.') : 'এই ফাইলটি গ্রহণ করা যায়নি।');
        }
    }
    $dir = ame_kitab_dir();
    $name = 'kitab_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($info['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('ফাইল সেভ হয়নি।');
    }
    return $name;
}

function ame_kitab_ingest_pages(mysqli $db, int $bid, ?array $files, array $ocrTexts = []): int
{
    if ($bid < 1 || !is_array($files) || !is_array($files['name'] ?? null)) {
        return 0;
    }
    $max = 0;
    $q = $db->query('SELECT COALESCE(MAX(page_no),0) AS m FROM family_book_pages WHERE book_id=' . (int)$bid);
    if ($q) {
        $row = $q->fetch_assoc();
        $max = (int)($row['m'] ?? 0);
    }
    $added = 0;
    $n = count($files['name']);
    for ($i = 0; $i < $n; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $one = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
        $saved = ame_kitab_save_file($one, ['jpg', 'jpeg', 'png', 'webp'], 8 * 1024 * 1024);
        if ($saved) {
            $max++;
            $text = (string)($ocrTexts[$i] ?? '');
            $st = $db->prepare('INSERT INTO family_book_pages (book_id, page_no, image_file, ocr_text) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE image_file=VALUES(image_file), ocr_text=IFNULL(NULLIF(VALUES(ocr_text),\'\'), ocr_text)');
            $st->bind_param('iiss', $bid, $max, $saved, $text);
            $st->execute();
            $st->close();
            $added++;
        }
    }
    if ($max > 0) {
        $st = $db->prepare('UPDATE family_books SET page_count=GREATEST(page_count,?) WHERE id=?');
        $st->bind_param('ii', $max, $bid);
        $st->execute();
        $st->close();
    }
    return $added;
}

function ame_kitab_ingest_leaves(mysqli $db, int $bid, array $kinds, array $nos, array $texts, ?array $files): int
{
    if ($bid < 1) {
        return 0;
    }
    $max = 0;
    $q = $db->query('SELECT COALESCE(MAX(page_no),0) AS m FROM family_book_pages WHERE book_id=' . (int)$bid);
    if ($q) {
        $row = $q->fetch_assoc();
        $max = (int)($row['m'] ?? 0);
    }
    $added = 0;
    $fileI = 0;
    $n = count($kinds);
    $st = $db->prepare('INSERT INTO family_book_pages (book_id, page_no, image_file, ocr_text) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE image_file=IF(VALUES(image_file)=\'\', image_file, VALUES(image_file)), ocr_text=IFNULL(NULLIF(VALUES(ocr_text),\'\'), ocr_text)');
    for ($i = 0; $i < $n; $i++) {
        $kind = (string)($kinds[$i] ?? 'text');
        $text = (string)($texts[$i] ?? '');
        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, 120000, 'UTF-8');
        } elseif (strlen($text) > 240000) {
            $text = substr($text, 0, 240000);
        }
        $want = (int)($nos[$i] ?? 0);
        $img = '';
        if ($kind === 'scan' && is_array($files) && is_array($files['name'] ?? null)) {
            while ($fileI < count($files['name']) && (($files['error'][$fileI] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
                $fileI++;
            }
            if ($fileI < count($files['name'])) {
                $one = [
                    'name' => $files['name'][$fileI],
                    'type' => $files['type'][$fileI] ?? '',
                    'tmp_name' => $files['tmp_name'][$fileI],
                    'error' => $files['error'][$fileI],
                    'size' => $files['size'][$fileI],
                ];
                $fileI++;
                $img = (string)(ame_kitab_save_file($one, ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024) ?? '');
            }
        }
        if ($img === '' && trim($text) === '') {
            continue;
        }
        if ($want < 1) {
            $want = $max + 1;
        }
        $st->bind_param('iiss', $bid, $want, $img, $text);
        $st->execute();
        if ($want > $max) {
            $max = $want;
        }
        $added++;
    }
    $st->close();
    if ($max > 0) {
        $up = $db->prepare('UPDATE family_books SET page_count=GREATEST(page_count,?) WHERE id=?');
        $up->bind_param('ii', $max, $bid);
        $up->execute();
        $up->close();
    }
    return $added;
}

function ame_kitab_set_pdf(mysqli $db, int $bid, ?string $pdf): void
{
    if ($bid < 1 || !$pdf) {
        return;
    }
    $size = 0;
    $path = ame_kitab_dir() . '/' . basename($pdf);
    if (is_file($path)) {
        $size = (int)filesize($path);
    }
    $type = 'pdf';
    $st = $db->prepare('UPDATE family_books SET pdf_file=?, file_size=?, file_type=?, updated_at=NOW() WHERE id=?');
    $st->bind_param('sisi', $pdf, $size, $type, $bid);
    $st->execute();
    $st->close();
}

function ame_kitab_protect_dir(): void
{
    $ht = ame_kitab_dir() . '/.htaccess';
    $cur = is_file($ht) ? (string)file_get_contents($ht) : "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n";
    if (!str_contains($cur, '^kitab_')) {
        $cur .= "\n<FilesMatch \"^kitab_\">\nRequire all denied\n</FilesMatch>\n";
        @file_put_contents($ht, $cur);
    }
}

function ame_kitab_href(?string $name): string
{
    $name = basename((string)$name);
    if ($name === '') {
        return '';
    }
    if (str_starts_with($name, 'kitab_')) {
        return '/modules/family/kitab_file.php?f=' . rawurlencode($name);
    }
    return '/uploads/family/' . rawurlencode($name);
}

function ame_kitab_uid(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function ame_kitab_touch(mysqli $db, int $bid, int $page, int $total, string $status = ''): void
{
    if ($bid < 1) {
        return;
    }
    $page = max(1, $page);
    $uid = ame_kitab_uid();
    $pct = $total > 0 ? (int)min(100, round($page / $total * 100)) : 0;
    if ($status === '') {
        $status = $page > 1 ? 'reading' : 'not_started';
        if ($total > 1 && $page >= $total) {
            $status = 'completed';
        }
    }
    $ok = ['not_started', 'reading', 'completed', 'paused'];
    if (!in_array($status, $ok, true)) {
        $status = 'reading';
    }
    $st = $db->prepare('INSERT INTO family_book_progress (book_id,user_id,page_no,status,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE page_no=VALUES(page_no), status=VALUES(status), updated_at=NOW()');
    $st->bind_param('iiis', $bid, $uid, $page, $status);
    $st->execute();
    $st->close();
    $st = $db->prepare('INSERT INTO family_book_history (book_id,user_id,page_no,progress_pct,status) VALUES (?,?,?,?,?)');
    $st->bind_param('iiiis', $bid, $uid, $page, $pct, $status);
    $st->execute();
    $st->close();
    $read = $status === 'completed' ? 1 : 0;
    $st = $db->prepare('UPDATE family_books SET last_page=?, read_status=IF(read_status=\'paused\' AND ?<>\'completed\', read_status, ?), is_read=IF(?=1,1,is_read), updated_at=NOW() WHERE id=?');
    $st->bind_param('issii', $page, $status, $status, $read, $bid);
    $st->execute();
    $st->close();
}

function ame_kitab_apply_meta(mysqli $db, int $bid): void
{
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $translator = trim((string)($_POST['translator'] ?? ''));
    $edition = trim((string)($_POST['edition'] ?? ''));
    $year = trim((string)($_POST['pub_year'] ?? ''));
    $subcat = trim((string)($_POST['subcategory'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $tags = trim((string)($_POST['tags'] ?? ''));
    $fav = isset($_POST['favorite']) ? 1 : 0;
    $vis = (string)($_POST['visibility'] ?? 'family');
    if (!in_array($vis, ['family', 'private'], true)) {
        $vis = 'family';
    }
    $st = $db->prepare('UPDATE family_books SET subtitle=?, translator=?, edition=?, pub_year=?, subcategory=?, description=?, tags=?, favorite=?, visibility=?, updated_at=NOW() WHERE id=?');
    $st->bind_param('sssssssisi', $subtitle, $translator, $edition, $year, $subcat, $desc, $tags, $fav, $vis, $bid);
    $st->execute();
    $st->close();
    $status = trim((string)($_POST['read_status'] ?? ''));
    if (in_array($status, ['not_started', 'reading', 'completed', 'paused'], true)) {
        $read = $status === 'completed' ? 1 : 0;
        $st = $db->prepare('UPDATE family_books SET read_status=?, is_read=? WHERE id=?');
        $st->bind_param('sii', $status, $read, $bid);
        $st->execute();
        $st->close();
    }
    if (!empty($_FILES['cover_file']['name'])) {
        $cover = ame_kitab_save_file($_FILES['cover_file'], ['jpg', 'jpeg', 'png', 'webp'], 4 * 1024 * 1024);
        if ($cover) {
            $st = $db->prepare('UPDATE family_books SET cover_file=? WHERE id=?');
            $st->bind_param('si', $cover, $bid);
            $st->execute();
            $st->close();
        }
    }
    $titles = $_POST['toc_title'] ?? [];
    $pages = $_POST['toc_page'] ?? [];
    if (is_array($titles)) {
        $ins = $db->prepare('INSERT INTO family_book_toc (book_id,title,page_no,sort_order) VALUES (?,?,?,?)');
        foreach ($titles as $i => $raw) {
            $t = trim((string)$raw);
            if ($t === '') {
                continue;
            }
            $p = max(1, (int)($pages[$i] ?? 1));
            $ins->bind_param('isii', $bid, $t, $p, $p);
            $ins->execute();
        }
        $ins->close();
    }
}
