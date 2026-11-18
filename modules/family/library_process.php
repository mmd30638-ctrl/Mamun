<?php
require_once __DIR__ . '/_boot.php';
require_post();
csrf_verify();
$do = (string)($_POST['do'] ?? '');
$kitabReadDos = ['kitab_mark', 'kitab_pages_count', 'kitab_search', 'kitab_note', 'kitab_highlight', 'kitab_fold', 'kitab_unfold', 'kitab_fav', 'kitab_status', 'kitab_toc'];
if (!in_array($do, $kitabReadDos, true)) {
    fam_guard_write();
}

$back = (string)($_POST['back'] ?? '/modules/family/library.php');
if ($back === '' || !str_starts_with($back, '/modules/family/')) {
    $back = '/modules/family/library.php';
}

function kitab_go(string $to, string $ok = '', string $err = ''): void
{
    if ($ok !== '') {
        $_SESSION['fam_ok'] = $ok;
    }
    if ($err !== '') {
        $_SESSION['fam_err'] = $err;
    }
    header('Location: ' . $to);
    exit;
}

$db = fam_db();
ame_kitab_ensure($db);
$bid = (int)($_POST['book_id'] ?? 0);

try {
    if ($do === 'kitab_save') {
        $id = (int)($_POST['id'] ?? 0);
        $mid = function_exists('fam_id') ? fam_id($_POST['member_id'] ?? 0) : (int)($_POST['member_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('বইয়ের নাম লাগবে।');
        }
        $author = trim((string)($_POST['author'] ?? ''));
        $pub = trim((string)($_POST['publisher'] ?? ''));
        $sub = trim((string)($_POST['subject'] ?? ''));
        $lang = trim((string)($_POST['language'] ?? ''));
        $copies = max(1, (int)($_POST['copies'] ?? 1));
        $bd = function_exists('fam_date_sql') ? fam_date_sql($_POST['buy_date'] ?? '') : trim((string)($_POST['buy_date'] ?? ''));
        $price = function_exists('fam_money') ? fam_money($_POST['price'] ?? 0) : (float)($_POST['price'] ?? 0);
        $src = trim((string)($_POST['source'] ?? ''));
        $loc = trim((string)($_POST['location_note'] ?? ''));
        $read = isset($_POST['is_read']) ? 1 : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));
        $isNew = $id < 1;
        if ($id > 0) {
            $st = $db->prepare('UPDATE family_books SET member_id=NULLIF(?,0), title=?, author=?, publisher=?, subject=?, language=?, copies=?, buy_date=NULLIF(?,\'\'), price=?, source=?, location_note=?, is_read=?, notes=? WHERE id=?');
            $st->bind_param('isssssisdssisi', $mid, $title, $author, $pub, $sub, $lang, $copies, $bd, $price, $src, $loc, $read, $notes, $id);
            $st->execute();
            $st->close();
            $bid = $id;
        } else {
            $st = $db->prepare('INSERT INTO family_books (member_id,title,author,publisher,subject,language,copies,buy_date,price,source,location_note,is_read,notes) VALUES (NULLIF(?,0),?,?,?,?,?,?,NULLIF(?,\'\'),?,?,?,?,?)');
            $st->bind_param('isssssisdssis', $mid, $title, $author, $pub, $sub, $lang, $copies, $bd, $price, $src, $loc, $read, $notes);
            $st->execute();
            $bid = (int)$db->insert_id;
            $st->close();
        }
        if ($bid < 1) {
            throw new RuntimeException('বই সেভ হয়নি।');
        }
        if (function_exists('ame_kitab_apply_meta')) {
            ame_kitab_apply_meta($db, $bid);
        }
        if ($isNew && $price > 0 && function_exists('fam_add_expense')) {
            fam_add_expense($bd !== '' ? $bd : date('Y-m-d'), 'বই', $price, 'বই/কিতাব: ' . $title, $mid > 0 ? $mid : null, $src);
        }
        $pdf = null;
        if (!empty($_FILES['pdf_file']['name'])) {
            $pdf = ame_kitab_save_file($_FILES['pdf_file'], ['pdf'], 32 * 1024 * 1024);
            ame_kitab_set_pdf($db, $bid, $pdf);
        }
        $kinds = $_POST['leaf_kind'] ?? [];
        $nos = $_POST['leaf_no'] ?? [];
        $texts = $_POST['leaf_text'] ?? [];
        if (!is_array($kinds)) {
            $kinds = [$kinds];
        }
        if (!is_array($nos)) {
            $nos = [$nos];
        }
        if (!is_array($texts)) {
            $texts = [$texts];
        }
        $leaves = function_exists('ame_kitab_ingest_leaves')
            ? ame_kitab_ingest_leaves($db, $bid, $kinds, $nos, $texts, null)
            : 0;
        $ocr = $_POST['ocr_text'] ?? [];
        if (!is_array($ocr)) {
            $ocr = [$ocr];
        }
        $added = ame_kitab_ingest_pages($db, $bid, $_FILES['pages'] ?? null, $ocr);
        $n = $leaves + $added;
        if ($n || $pdf) {
            kitab_go('/modules/family/read.php?id=' . $bid, $n ? ($n . ' পেজ এন্ট্রি হয়েছে।') : 'PDF ইনস্টল হয়েছে — পড়া শুরু করুন।');
        }
        kitab_go('/modules/family/library.php?id=' . $bid, 'বই সংরক্ষণ হয়েছে। এখন পেজ খুলে লেখা বসান।');
    }

    if ($do === 'kitab_install' && $bid > 0) {
        $pdf = null;
        if (!empty($_FILES['pdf_file']['name'])) {
            $pdf = ame_kitab_save_file($_FILES['pdf_file'], ['pdf'], 32 * 1024 * 1024);
            ame_kitab_set_pdf($db, $bid, $pdf);
        }
        $ocr = $_POST['ocr_text'] ?? [];
        if (!is_array($ocr)) {
            $ocr = [$ocr];
        }
        $added = ame_kitab_ingest_pages($db, $bid, $_FILES['pages'] ?? null, $ocr);
        if ($added || $pdf) {
            kitab_go('/modules/family/read.php?id=' . $bid, 'কিতাব ইনস্টল হয়েছে — পড়া শুরু করুন।');
        }
        kitab_go('/modules/family/library.php?id=' . $bid, '', 'PDF বা পেজ ছবি দিন।');
    }

    if ($do === 'kitab_fold' && $bid > 0) {
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        $note = trim((string)($_POST['note'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $st = $db->prepare('INSERT INTO family_book_folds (book_id, page_no, note, title) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE note=VALUES(note), title=IF(VALUES(title)=\'\', title, VALUES(title))');
        $st->bind_param('iiss', $bid, $page, $note, $title);
        $st->execute();
        $st->close();
        $st = $db->prepare('UPDATE family_books SET last_page=? WHERE id=?');
        $st->bind_param('ii', $page, $bid);
        $st->execute();
        $st->close();
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo '{"ok":1}';
            exit;
        }
        kitab_go($back, 'পেজ ' . $page . ' ভাঁজ রাখা হয়েছে।');
    }

    if ($do === 'kitab_unfold' && $bid > 0) {
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        $st = $db->prepare('DELETE FROM family_book_folds WHERE book_id=? AND page_no=?');
        $st->bind_param('ii', $bid, $page);
        $st->execute();
        $st->close();
        kitab_go($back, 'ভাঁজ খুলে দেওয়া হয়েছে।');
    }

    if ($do === 'kitab_mark' && $bid > 0) {
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        $total = max(1, (int)($_POST['total'] ?? 1));
        ame_kitab_touch($db, $bid, $page, $total, trim((string)($_POST['status'] ?? '')));
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":1}';
        exit;
    }

    if ($do === 'kitab_page' && $bid > 0) {
        if (empty($_FILES['page']['name'])) {
            throw new RuntimeException('ক্যামেরায় পেজ তুলুন।');
        }
        $saved = ame_kitab_save_file($_FILES['page'], ['jpg', 'jpeg', 'png', 'webp'], 8 * 1024 * 1024);
        $want = (int)($_POST['page_no'] ?? 0);
        if ($want < 1) {
            $max = fam_one('SELECT COALESCE(MAX(page_no),0) AS m FROM family_book_pages WHERE book_id=' . $bid);
            $want = (int)($max['m'] ?? 0) + 1;
        }
        $text = (string)($_POST['ocr_text'] ?? '');
        $st = $db->prepare('INSERT INTO family_book_pages (book_id, page_no, image_file, ocr_text) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE image_file=VALUES(image_file), ocr_text=IFNULL(NULLIF(VALUES(ocr_text),\'\'), ocr_text)');
        $st->bind_param('iiss', $bid, $want, $saved, $text);
        $st->execute();
        $st->close();
        $cnt = fam_one('SELECT COALESCE(MAX(page_no),0) AS c FROM family_book_pages WHERE book_id=' . $bid);
        $pc = (int)($cnt['c'] ?? $want);
        $st = $db->prepare('UPDATE family_books SET page_count=GREATEST(page_count,?), last_page=? WHERE id=?');
        $st->bind_param('iii', $pc, $want, $bid);
        $st->execute();
        $st->close();
        $ajax = !empty($_POST['ajax']) || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => 1,
                'page_no' => $want,
                'image' => ame_kitab_href($saved),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        kitab_go('/modules/family/read.php?id=' . $bid . '&p=' . $want, 'পেজ ' . $want . ' যোগ হয়েছে।');
    }

    if ($do === 'kitab_ocr' && $bid > 0) {
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        $text = (string)($_POST['ocr_text'] ?? '');
        $blank = '';
        $st = $db->prepare('INSERT INTO family_book_pages (book_id, page_no, image_file, ocr_text) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE ocr_text=VALUES(ocr_text)');
        $st->bind_param('iiss', $bid, $page, $blank, $text);
        $st->execute();
        $st->close();
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":1}';
        exit;
    }

    if ($do === 'kitab_pages_count' && $bid > 0) {
        $n = max(1, (int)($_POST['page_count'] ?? 1));
        $st = $db->prepare('UPDATE family_books SET page_count=GREATEST(page_count,?) WHERE id=?');
        $st->bind_param('ii', $n, $bid);
        $st->execute();
        $st->close();
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":1}';
        exit;
    }

    if ($do === 'kitab_search' && $bid > 0) {
        $q = trim((string)($_POST['q'] ?? ''));
        $hits = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $st = $db->prepare('SELECT page_no, ocr_text FROM family_book_pages WHERE book_id=? AND ocr_text LIKE ? ORDER BY page_no LIMIT 40');
            $st->bind_param('is', $bid, $like);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $text = (string)($row['ocr_text'] ?? '');
                $pos = function_exists('mb_stripos') ? mb_stripos($text, $q, 0, 'UTF-8') : stripos($text, $q);
                $snip = $text;
                if ($pos !== false && function_exists('mb_substr')) {
                    $start = max(0, (int)$pos - 24);
                    $snip = mb_substr($text, $start, 80, 'UTF-8');
                } elseif (function_exists('mb_substr')) {
                    $snip = mb_substr($text, 0, 80, 'UTF-8');
                }
                $hits[] = ['page' => (int)$row['page_no'], 'snippet' => $snip];
            }
            $st->close();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => 1, 'hits' => $hits], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($do === 'kitab_note' && $bid > 0) {
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        $text = trim((string)($_POST['note_text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException(ame_l('নোট লিখুন।', 'Write a note.'));
        }
        $uid = ame_kitab_uid();
        $st = $db->prepare('INSERT INTO family_book_notes (book_id,user_id,page_no,note_text) VALUES (?,?,?,?)');
        $st->bind_param('iiis', $bid, $uid, $page, $text);
        $st->execute();
        $id = (int)$db->insert_id;
        $st->close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => 1, 'id' => $id], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($do === 'kitab_highlight' && $bid > 0) {
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        $quote = trim((string)($_POST['quote'] ?? ''));
        if ($quote === '') {
            throw new RuntimeException(ame_l('হাইলাইটের লেখা দিন।', 'Add the highlighted text.'));
        }
        if (function_exists('mb_substr')) {
            $quote = mb_substr($quote, 0, 500, 'UTF-8');
        }
        $uid = ame_kitab_uid();
        $st = $db->prepare('INSERT INTO family_book_highlights (book_id,user_id,page_no,quote) VALUES (?,?,?,?)');
        $st->bind_param('iiis', $bid, $uid, $page, $quote);
        $st->execute();
        $id = (int)$db->insert_id;
        $st->close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => 1, 'id' => $id], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($do === 'kitab_fav' && $bid > 0) {
        $on = (int)($_POST['favorite'] ?? 0) ? 1 : 0;
        $st = $db->prepare('UPDATE family_books SET favorite=? WHERE id=?');
        $st->bind_param('ii', $on, $bid);
        $st->execute();
        $st->close();
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":1}';
        exit;
    }

    if ($do === 'kitab_status' && $bid > 0) {
        $status = trim((string)($_POST['status'] ?? 'reading'));
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        $total = max(1, (int)($_POST['total'] ?? 1));
        ame_kitab_touch($db, $bid, $page, $total, $status);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":1}';
        exit;
    }

    if ($do === 'kitab_toc' && $bid > 0) {
        $title = trim((string)($_POST['title'] ?? ''));
        $page = max(1, (int)($_POST['page_no'] ?? 1));
        if ($title === '') {
            throw new RuntimeException(ame_l('অধ্যায়ের নাম দিন।', 'Enter a chapter name.'));
        }
        $st = $db->prepare('INSERT INTO family_book_toc (book_id,title,page_no,sort_order) VALUES (?,?,?,?)');
        $st->bind_param('isii', $bid, $title, $page, $page);
        $st->execute();
        $st->close();
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":1}';
        exit;
    }
} catch (Throwable $e) {
    $ajax = !empty($_POST['ajax']) || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    $msg = $e->getMessage();
    if ($msg === '' || preg_match('/SQL|prepare|mysqli|column|syntax/i', $msg)) {
        error_log('kitab: ' . $e->getMessage());
        $msg = ame_l('এই বইটি বর্তমানে খোলা যাচ্ছে না। অনুগ্রহ করে পরে আবার চেষ্টা করুন।', 'This book cannot be opened right now. Please try again later.');
    }
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => 0, 'error' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    kitab_go($back, '', $msg);
}

kitab_go($back);
