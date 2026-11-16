<?php
/**
 * স্টক অ্যালার্ট — কম মজুদ পণ্যের গণনা ও ক্রয় ইনভয়েস তালিকা।
 */

function ame_stock_alert_can_view($db): bool
{
    if (!isset($_SESSION['role'])) {
        return false;
    }
    if (strtolower((string)$_SESSION['role']) === 'admin') {
        return true;
    }
    return has_permission($db, 'm-inv') || has_permission($db, 'm-purchase');
}

function ame_stock_alert_suggest_qty(float $available, float $min_stock): int
{
    $gap = $min_stock - $available;
    if ($gap < 1) {
        return 1;
    }
    return (int)ceil($gap);
}

function ame_stock_alert_fold(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(["\u{09A1}\u{09BC}", "\u{09AF}\u{09BC}"], ["\u{09DC}", "\u{09DF}"], $text);
    $text = str_replace(['বড়', 'বড়'], 'বড়', $text);
    $swaps = [
        'তারসহ' => 'তার সহ',
        'ব্যাটারী' => 'ব্যাটারি',
        'এলিডি' => 'এলিড',
        'এলইডি' => 'এলিড',
        'পিনিয়াম' => 'পিনিয়ম',
        'পিনিয়াম' => 'পিনিয়ম',
        'পিনিয়ম' => 'পিনিয়ম',
        'ফ্যান' => 'ফেন',
        'হরন' => 'হর্ন',
        'বাঁশী' => 'বাঁশি',
        'বাশি' => 'বাঁশি',
        'সেইব' => 'শেভ',
        'শেব' => 'শেভ',
        'সেভ' => 'শেভ',
        'ডায়াট' => 'ডায়েট',
        'ডায়াট' => 'ডায়েট',
        'ডায়েট' => 'ডায়েট',
        'ডাওয়েট' => 'ডায়েট',
        'ডাওয়েট' => 'ডায়েট',
        'ওয়ারিং' => 'ওয়ারিং',
        'wiring' => 'ওয়ারিং',
        'ব্রেক' => 'বেরিক',
        'দাত' => 'দাঁত',
        'চাপা' => 'চাঁপা',
        'এইচ পি' => 'hp',
        'এইচপি' => 'hp',
        'h p' => 'hp',
        'pellet' => 'পেলিট',
        'casing' => 'কেসিং',
        'half' => 'হাফ',
    ];
    $text = str_replace(array_keys($swaps), array_values($swaps), $text);
    $text = preg_replace('/চার্জর(?!া)/u', 'চার্জার', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function ame_stock_alert_has_token(string $text, string $token): bool
{
    $q = preg_quote($token, '/');
    return preg_match('/(?<![\p{L}\p{N}])' . $q . '(?![\p{L}\p{N}])/u', $text) === 1;
}

/** স্টক অ্যালার্ট থেকে অর্ডারে এই পণ্যগুলোর পিস নির্দিষ্ট। না মিললে null — আগের ঘাটতি হিসাব। */
function ame_stock_alert_fixed_order_qty(string $name, string $sku = '', string $oem = '', string $supplier = ''): ?int
{
    $raw = trim($name . ' ' . $sku . ' ' . $oem . ' ' . $supplier);
    if ($raw === '') {
        return null;
    }
    $n = ame_stock_alert_fold($raw);
    $small = mb_strpos($n, 'ছোট', 0, 'UTF-8') !== false && mb_strpos($n, 'বড়', 0, 'UTF-8') === false;
    $big = mb_strpos($n, 'বড়', 0, 'UTF-8') !== false;
    $hp = preg_match('/(?<![a-z])hp(?![a-z])/u', $n) === 1;
    $has = static function (string $needle) use ($n): bool {
        return $needle !== '' && mb_strpos($n, $needle, 0, 'UTF-8') !== false;
    };

    if ($has('করছ') && $has('শেভ')) {
        return 10;
    }
    if ($has('বেরিক') && $has('রট') && $small) {
        return 40;
    }
    if ($has('বেরিক') && $has('রট') && $big) {
        return 20;
    }
    if ($has('বাঁশি') && $has('হর্ন')) {
        return 30;
    }
    if ($has('ডায়েট') && ($small || $big)) {
        return 50;
    }
    if (ame_stock_alert_has_token($raw, '17') && $has('গাছ') && $has('পিনিয়ম') && !$has('লক')) {
        return 10;
    }
    if ($hp && $has('বাবুল') && $has('পিনিয়ম')) {
        return 10;
    }
    if ($hp && $has('ফুটবল')) {
        return 1;
    }
    if (ame_stock_alert_has_token($raw, '36') && $has('দাঁত')) {
        return 10;
    }
    if ($has('চার্জার') && $has('ফেন') && $small) {
        return 30;
    }
    if ($has('ব্যাটারি') && $has('কেসিং') && (ame_stock_alert_has_token($n, 'আফ') || $has('হাফ'))) {
        return 20;
    }
    if ($has('ব্যাটারি') && $has('রট') && !$has('কেসিং')) {
        return 50;
    }
    if ($has('পেলিট') && ame_stock_alert_has_token($n, 'ইউ')) {
        return 40;
    }
    if ($has('এলিড') && $has('সাদা') && !$has('চোখ')) {
        return 40;
    }
    if ($has('আইচি') && $has('তার সহ')) {
        return 10;
    }
    if ($has('চাঁপা')) {
        return 10;
    }
    if ($has('ওয়ারিং') && ($hp || $has('সেট'))) {
        return 5;
    }
    if (ame_stock_alert_has_token($raw, '6005')) {
        return 20;
    }
    return null;
}

function ame_stock_alert_store_parts(int $store_id): array
{
    $select = $store_id > 0
        ? 'COALESCE(ss.qty, p.stock) AS store_qty'
        : 'p.stock AS store_qty';
    $join = $store_id > 0
        ? ' LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ' . $store_id . ' '
        : '';
    return [$select, $join];
}

function ame_stock_shortlist_ensure($db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!$db instanceof mysqli) {
        $ok = false;
        return false;
    }
    $ok = (bool)@$db->query(
        "CREATE TABLE IF NOT EXISTS ame_stock_shortlist (
            product_id INT NOT NULL,
            store_id INT NOT NULL DEFAULT 0,
            flagged_at DATETIME NOT NULL,
            PRIMARY KEY (product_id, store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    return $ok;
}

/** শর্ট লিস্টে থাকে যতক্ষণ না ওই পণ্যের ক্রয় মেমো হয়। স্টক এমনিতে বাড়লে নাম সরানো হয় না। */
function ame_stock_shortlist_sync($db, int $store_id): bool
{
    if (!ame_stock_shortlist_ensure($db)) {
        return false;
    }
    $store_id = max(0, $store_id);
    @$db->query(
        "DELETE s FROM ame_stock_shortlist s
         LEFT JOIN products p ON p.id = s.product_id
         WHERE s.store_id = {$store_id}
           AND (p.id IS NULL OR (p.status IS NOT NULL AND TRIM(p.status) <> '' AND p.status <> 'active'))"
    );
    $drop = "DELETE s FROM ame_stock_shortlist s
             INNER JOIN purchases pu ON pu.product_id = s.product_id
             WHERE s.store_id = {$store_id}
               AND pu.qty > 0
               AND pu.created_at >= s.flagged_at";
    if (!@$db->query($drop)) {
        @$db->query(
            "DELETE s FROM ame_stock_shortlist s
             INNER JOIN purchases pu ON pu.product_id = s.product_id
             WHERE s.store_id = {$store_id}
               AND pu.created_at >= s.flagged_at"
        );
    }
    if ($store_id > 0) {
        $ins = "INSERT IGNORE INTO ame_stock_shortlist (product_id, store_id, flagged_at)
                SELECT p.id, {$store_id}, NOW()
                FROM products p
                LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = {$store_id}
                WHERE (p.status = 'active' OR p.status IS NULL OR p.status = '')
                  AND COALESCE(ss.qty, p.stock) <= COALESCE(p.min_stock, 0)";
    } else {
        $ins = "INSERT IGNORE INTO ame_stock_shortlist (product_id, store_id, flagged_at)
                SELECT p.id, 0, NOW()
                FROM products p
                WHERE (p.status = 'active' OR p.status IS NULL OR p.status = '')
                  AND p.stock <= COALESCE(p.min_stock, 0)";
    }
    return (bool)@$db->query($ins);
}

function ame_stock_shortlist_ids($db, int $store_id): array
{
    $set = [];
    if (!$db instanceof mysqli || !ame_stock_shortlist_sync($db, $store_id)) {
        return $set;
    }
    $store_id = max(0, $store_id);
    $res = @$db->query("SELECT product_id FROM ame_stock_shortlist WHERE store_id = {$store_id}");
    if (!$res) {
        return $set;
    }
    while ($row = $res->fetch_assoc()) {
        $set[(int)$row['product_id']] = true;
    }
    return $set;
}

function ame_stock_alert_count($db): int
{
    if (!$db instanceof mysqli) {
        return 0;
    }
    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    $now = time();
    if (session_status() === PHP_SESSION_ACTIVE) {
        $hit = $_SESSION['ame_stock_badge'] ?? null;
        if (is_array($hit) && (int)($hit['store'] ?? -1) === $store_id && (int)($hit['at'] ?? 0) + 60 > $now) {
            return (int)($hit['n'] ?? 0);
        }
    }
    if (ame_stock_shortlist_sync($db, $store_id)) {
        $held = @$db->query('SELECT COUNT(*) AS c FROM ame_stock_shortlist WHERE store_id = ' . max(0, $store_id));
        if ($held) {
            $row = $held->fetch_assoc();
            $n = (int)($row['c'] ?? 0);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['ame_stock_badge'] = ['at' => $now, 'n' => $n, 'store' => $store_id];
            }
            return $n;
        }
    }
    if ($store_id > 0) {
        $sql = 'SELECT COUNT(*) AS c
                FROM products p
                LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ' . $store_id . '
                WHERE (p.status = \'active\' OR p.status IS NULL OR p.status = \'\')
                  AND COALESCE(ss.qty, p.stock) <= COALESCE(p.min_stock, 0)';
    } else {
        $sql = "SELECT COUNT(*) AS c
                FROM products p
                WHERE (p.status = 'active' OR p.status IS NULL OR p.status = '')
                  AND p.stock <= COALESCE(p.min_stock, 0)";
    }
    $res = @$db->query($sql);
    if (!$res) {
        $res = @$db->query(
            "SELECT COUNT(*) AS c FROM products
             WHERE (status = 'active' OR status IS NULL OR status = '')
               AND stock <= COALESCE(min_stock, 0)"
        );
    }
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    $n = (int)($row['c'] ?? 0);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['ame_stock_badge'] = ['at' => $now, 'n' => $n, 'store' => $store_id];
    }
    return $n;
}

function ame_stock_alert_items($db): array
{
    if (!$db instanceof mysqli) {
        return [];
    }
    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    [$select, $join] = ame_stock_alert_store_parts($store_id);
    $reserved_map = function_exists('ame_workshop_reserved_map')
        ? ame_workshop_reserved_map($db, $store_id)
        : [];

    $sql = "SELECT
                p.id,
                p.name,
                p.sku,
                p.oem_code,
                p.supplier_code,
                p.stock,
                p.min_stock,
                p.purchase_price,
                {$select},
                u.unit_name
            FROM products p
            LEFT JOIN product_units u ON p.unit_id = u.id
            {$join}
            WHERE (p.status = 'active' OR p.status IS NULL OR p.status = '')
            ORDER BY p.name ASC";

    $res = @$db->query($sql);
    if (!$res) {
        $res = @$db->query(
            "SELECT id, name, sku, oem_code, supplier_code, stock, min_stock, purchase_price,
                    stock AS store_qty, NULL AS unit_name
             FROM products
             WHERE (status = 'active' OR status IS NULL OR status = '')
             ORDER BY name ASC"
        );
    }
    if (!$res) {
        return [];
    }

    $held = ame_stock_shortlist_ids($db, $store_id);
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $stock = (float)($row['store_qty'] ?? $row['stock'] ?? 0);
        $reserved = (float)($reserved_map[(int)$row['id']] ?? 0);
        $available = max(0, round($stock - $reserved, 3));
        $min_stock = (float)($row['min_stock'] ?? 0);
        $on_short = isset($held[(int)$row['id']]);
        if ($available > $min_stock && !$on_short) {
            continue;
        }
        if (!$on_short) {
            $pid = (int)$row['id'];
            @$db->query(
                'INSERT IGNORE INTO ame_stock_shortlist (product_id, store_id, flagged_at) VALUES ('
                . $pid . ', ' . max(0, $store_id) . ', NOW())'
            );
        }
        $qty = ame_stock_alert_suggest_qty($available, $min_stock);
        $fixed = ame_stock_alert_fixed_order_qty(
            (string)($row['name'] ?? ''),
            (string)($row['sku'] ?? ''),
            (string)($row['oem_code'] ?? ''),
            (string)($row['supplier_code'] ?? '')
        );
        if ($fixed !== null) {
            $qty = $fixed;
        }
        $price = (float)($row['purchase_price'] ?? 0);
        $items[] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'sku' => trim((string)($row['sku'] ?? '')),
            'oem_code' => trim((string)($row['oem_code'] ?? '')),
            'supplier_code' => trim((string)($row['supplier_code'] ?? '')),
            'unit_name' => trim((string)($row['unit_name'] ?? '')),
            'stock' => $stock,
            'reserved' => $reserved,
            'available' => $available,
            'min_stock' => $min_stock,
            'purchase_price' => $price,
            'qty' => $qty,
            'line_total' => round($price * $qty, 2),
        ];
    }

    usort($items, static function ($a, $b) {
        if ($a['available'] !== $b['available']) {
            return $a['available'] <=> $b['available'];
        }
        return strcmp($a['name'], $b['name']);
    });

    return $items;
}
