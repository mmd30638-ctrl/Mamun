<?php
/**
 * কপি-পোস্ট — একাধিক পণ্য/ক্রেতা একসাথে কপি, ড্রাফট, অ্যাক্টিভিটি লগ।
 * ব্যালেন্স, স্টক ও ইনভয়েস কপি হয় না।
 */

function ame_clip_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @$db->query("CREATE TABLE IF NOT EXISTS clip_drafts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        kind VARCHAR(20) NOT NULL,
        payload MEDIUMTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        posted_at DATETIME NULL,
        KEY status_kind (status, kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @$db->query("CREATE TABLE IF NOT EXISTS clip_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(40) NOT NULL,
        detail VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ame_clip_log(mysqli $db, string $action, string $detail): void
{
    ame_clip_schema($db);
    $user = (int)($_SESSION['user_id'] ?? 0);
    $detail = mb_substr($detail, 0, 240);
    $st = $db->prepare('INSERT INTO clip_logs (user_id, action, detail) VALUES (?, ?, ?)');
    if ($st) {
        $st->bind_param('iss', $user, $action, $detail);
        $st->execute();
        $st->close();
    }
}

function ame_clip_search(mysqli $db, string $kind, string $q): array
{
    $q = trim($q);
    $like = '%' . $q . '%';
    $rows = [];
    if ($kind === 'customer') {
        $pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';
        $st = $db->prepare('SELECT id, name, phone, address FROM customers WHERE ' . $pick . ' AND (name LIKE ? OR phone LIKE ? OR customer_code LIKE ?) ORDER BY name ASC LIMIT 40');
        if ($st) {
            $st->bind_param('sss', $like, $like, $like);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $rows[] = [
                    'id' => (int)$r['id'],
                    'name' => (string)$r['name'],
                    'meta' => trim((string)($r['phone'] ?? '') . ' · ' . (string)($r['address'] ?? ''), ' ·'),
                ];
            }
            $st->close();
        }
        return $rows;
    }
    if (!function_exists('ame_product_option_sql')) {
        require_once __DIR__ . '/helpers.php';
    }
    $on = ame_product_option_sql('status');
    $st = $db->prepare("SELECT id, name, sale_price, stock FROM products WHERE {$on} AND (name LIKE ? OR IFNULL(sku,'') LIKE ?) ORDER BY name ASC LIMIT 40");
    if ($st) {
        $st->bind_param('ss', $like, $like);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'meta' => 'বিক্রয় ৳' . number_format((float)$r['sale_price'], 2) . ' · স্টক ' . (string)$r['stock'],
            ];
        }
        $st->close();
    }
    return $rows;
}

function ame_clip_save_draft(mysqli $db, string $kind, string $title, array $payload): int
{
    ame_clip_schema($db);
    if (!in_array($kind, ['product', 'customer', 'note'], true)) {
        throw new Exception('কী কপি হবে সেটি বাছুন।');
    }
    $title = trim($title);
    if ($title === '') {
        $title = $kind === 'note' ? 'নোট ড্রাফট' : 'কপি ড্রাফট';
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $user = (int)($_SESSION['user_id'] ?? 0);
    $st = $db->prepare('INSERT INTO clip_drafts (title, kind, payload, status, created_by) VALUES (?, ?, ?, \'draft\', ?)');
    $st->bind_param('sssi', $title, $kind, $json, $user);
    $st->execute();
    $id = (int)$db->insert_id;
    $st->close();
    ame_clip_log($db, 'draft', $title . ' #' . $id);
    return $id;
}

function ame_clip_post(mysqli $db, int $draftId): array
{
    ame_clip_schema($db);
    $st = $db->prepare('SELECT * FROM clip_drafts WHERE id = ? LIMIT 1');
    $st->bind_param('i', $draftId);
    $st->execute();
    $draft = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$draft) {
        throw new Exception('ড্রাফট পাওয়া যায়নি।');
    }
    if (($draft['status'] ?? '') === 'posted') {
        throw new Exception('এই ড্রাফট আগেই পোস্ট হয়েছে।');
    }
    $payload = json_decode((string)$draft['payload'], true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $kind = (string)$draft['kind'];
    $made = [];
    if ($kind === 'product') {
        $made = ame_clip_copy_products($db, $payload['ids'] ?? []);
    } elseif ($kind === 'customer') {
        $made = ame_clip_copy_customers($db, $payload['ids'] ?? []);
    } else {
        $made = ['নোট রাখা হয়েছে'];
    }
    $mark = $db->prepare("UPDATE clip_drafts SET status = 'posted', posted_at = NOW() WHERE id = ?");
    $mark->bind_param('i', $draftId);
    $mark->execute();
    $mark->close();
    ame_clip_log($db, 'post', (string)$draft['title'] . ' · ' . count($made) . 'টি');
    return ['ok' => true, 'message' => count($made) . 'টি পোস্ট হয়েছে।', 'made' => $made];
}

function ame_clip_copy_products(mysqli $db, array $ids): array
{
    $made = [];
    foreach ($ids as $raw) {
        $id = (int)$raw;
        if ($id <= 0) {
            continue;
        }
        $st = $db->prepare('SELECT name, sale_price, purchase_price, wholesale_price, dealer_price, category_id, unit_id, product_type FROM products WHERE id = ? LIMIT 1');
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            continue;
        }
        $name = mb_substr(trim((string)$row['name']) . ' কপি', 0, 200);
        $sale = (float)$row['sale_price'];
        $buy = (float)$row['purchase_price'];
        $whole = (float)$row['wholesale_price'];
        $dealer = (float)$row['dealer_price'];
        $cat = (int)($row['category_id'] ?? 0);
        $unit = (int)($row['unit_id'] ?? 0);
        $type = (string)($row['product_type'] ?? 'part');
        $ins = $db->prepare("INSERT INTO products (name, sale_price, purchase_price, wholesale_price, dealer_price, category_id, unit_id, product_type, stock, status) VALUES (?, ?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), ?, 0, 'active')");
        $ins->bind_param('sddddiis', $name, $sale, $buy, $whole, $dealer, $cat, $unit, $type);
        $ins->execute();
        $made[] = $name . ' #' . (int)$db->insert_id;
        $ins->close();
    }
    if (!$made) {
        throw new Exception('কপি করার পণ্য বাছুন।');
    }
    return $made;
}

function ame_clip_copy_customers(mysqli $db, array $ids): array
{
    $made = [];
    foreach ($ids as $raw) {
        $id = (int)$raw;
        if ($id <= 0) {
            continue;
        }
        $st = $db->prepare('SELECT name, address FROM customers WHERE id = ? LIMIT 1');
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            continue;
        }
        $name = mb_substr(trim((string)$row['name']) . ' কপি', 0, 150);
        $code = 'CPY' . date('ymdHis') . $id;
        $address = (string)($row['address'] ?? '');
        $ins = $db->prepare('INSERT INTO customers (customer_code, name, address, is_customer, receivable_balance, payable_balance, status) VALUES (?, ?, ?, 1, 0, 0, 1)');
        $ins->bind_param('sss', $code, $name, $address);
        $ins->execute();
        $made[] = $name . ' · ' . $code;
        $ins->close();
    }
    if (!$made) {
        throw new Exception('কপি করার ক্রেতা বাছুন।');
    }
    return $made;
}

function ame_clip_lists(mysqli $db): array
{
    ame_clip_schema($db);
    $drafts = [];
    $res = @$db->query('SELECT id, title, kind, status, created_at FROM clip_drafts ORDER BY id DESC LIMIT 30');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $drafts[] = $r;
        }
        $res->free();
    }
    $logs = [];
    $res = @$db->query('SELECT id, action, detail, created_at FROM clip_logs ORDER BY id DESC LIMIT 40');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $logs[] = $r;
        }
        $res->free();
    }
    return ['drafts' => $drafts, 'logs' => $logs];
}
