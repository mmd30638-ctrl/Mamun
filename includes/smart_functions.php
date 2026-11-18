<?php
/**
 * Smart Business Hub — rule-based insights on existing Pulse / POS data.
 * No LLM, no new sale path, no countdown changes.
 */

function ame_smart_has_col(mysqli $db, string $table, string $col): bool
{
    static $cache = [];
    $key = $table . '.' . $col;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($col);
    $res = @$db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    $cache[$key] = $res && $res->num_rows > 0;
    return $cache[$key];
}

function ame_smart_money(float $n): string
{
    return number_format($n, 0) . ' ৳';
}

function ame_can_see_smart(mysqli $db): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
        return true;
    }
    return has_permission($db, 'm-smart');
}

function ame_smart_nav(): array
{
    return [
        'hub' => [
            'label' => t('smart_g_hub'),
            'items' => [
                ['href' => '/modules/smart/index.php', 'label' => t('smart_nav_dash')],
                ['href' => '/modules/smart/map.php', 'label' => t('smart_nav_map')],
                ['href' => '/modules/smart/assistant.php', 'label' => t('smart_nav_assistant')],
            ],
        ],
        'stock' => [
            'label' => t('smart_g_stock'),
            'items' => [
                ['href' => '/modules/smart/stock.php', 'label' => t('smart_nav_stock')],
                ['href' => '/modules/smart/purchase.php', 'label' => t('smart_nav_purchase')],
            ],
        ],
        'people' => [
            'label' => t('smart_g_people'),
            'items' => [
                ['href' => '/modules/smart/customers.php', 'label' => t('smart_nav_customers')],
                ['href' => '/modules/smart/profit.php', 'label' => t('smart_nav_profit')],
                ['href' => '/modules/smart/calendar.php', 'label' => t('smart_nav_calendar')],
            ],
        ],
        'house' => [
            'label' => t('smart_g_house'),
            'items' => [
                ['href' => '/modules/family/index.php', 'label' => t('smart_nav_family')],
                ['href' => '/modules/smart/privacy.php', 'label' => t('smart_nav_privacy')],
            ],
        ],
    ];
}

function ame_smart_open(string $title, string $lead = ''): void
{
    $here = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $ver = htmlspecialchars((string)($GLOBALS['sys_version'] ?? (defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '3.7')), ENT_QUOTES, 'UTF-8');
    echo '<link rel="stylesheet" href="/assets/css/smart.css?v=' . $ver . '">';
    echo '<div class="smt-app smt-premium" id="smtApp">';
    echo '<aside class="smt-rail">';
    echo '<div class="smt-brand"><span>' . htmlspecialchars(t('smart_brand_kicker'), ENT_QUOTES, 'UTF-8') . '</span><b>' . htmlspecialchars(t('smart_brand_title'), ENT_QUOTES, 'UTF-8') . '</b></div>';
    foreach (ame_smart_nav() as $g) {
        echo '<div class="smt-nav-g"><em>' . htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') . '</em>';
        foreach ($g['items'] as $it) {
            $on = str_contains($here, basename($it['href'])) ? ' on' : '';
            echo '<a class="smt-nav-a' . $on . '" href="' . htmlspecialchars($it['href'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8') . '</a>';
        }
        echo '</div>';
    }
    echo '<button type="button" class="smt-ghost smt-rail-theme" id="smtThemeBtn" title="' . htmlspecialchars(t('smart_theme'), ENT_QUOTES, 'UTF-8') . '">◐</button>';
    echo '</aside><div class="smt-main">';
}

function ame_smart_line_svg(array $rows, string $valueKey = 'total'): string
{
    $count = count($rows);
    if ($count < 2) {
        return '';
    }
    $width = 640.0;
    $height = 196.0;
    $padL = 10.0;
    $padR = 10.0;
    $padT = 18.0;
    $padB = 30.0;
    $vals = array_map(static fn($row) => (float)($row[$valueKey] ?? 0), $rows);
    $max = max(1.0, max($vals));
    $points = [];
    foreach ($vals as $i => $value) {
        $x = $padL + ($i / ($count - 1)) * ($width - $padL - $padR);
        $y = $padT + (1 - ($value / $max)) * ($height - $padT - $padB);
        $points[] = [round($x, 2), round($y, 2)];
    }
    $poly = implode(' ', array_map(static fn($pt) => $pt[0] . ',' . $pt[1], $points));
    $area = $padL . ',' . ($height - $padB) . ' ' . $poly . ' ' . $points[$count - 1][0] . ',' . ($height - $padB);
    $dots = '';
    $labels = '';
    foreach ($rows as $i => $row) {
        $dots .= '<circle cx="' . $points[$i][0] . '" cy="' . $points[$i][1] . '" r="4.2" fill="#22a961" stroke="#fff" stroke-width="2"/>';
        $labels .= '<text x="' . $points[$i][0] . '" y="' . ($height - 8) . '" text-anchor="middle" fill="#7a9486" font-size="11">'
            . htmlspecialchars((string)($row['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</text>';
    }
    return '<svg class="smt-if-line" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" aria-hidden="true">'
        . '<defs><linearGradient id="smtLineFill" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="#29ae68" stop-opacity=".18"/>'
        . '<stop offset="100%" stop-color="#29ae68" stop-opacity="0"/>'
        . '</linearGradient></defs>'
        . '<polygon fill="url(#smtLineFill)" points="' . $area . '"/>'
        . '<polyline fill="none" stroke="#22a961" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="' . $poly . '"/>'
        . $dots . $labels . '</svg>';
}

function ame_smart_close(): void
{
    $ver = htmlspecialchars((string)($GLOBALS['sys_version'] ?? (defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '3.7')), ENT_QUOTES, 'UTF-8');
    echo '</div></div>';
    echo '<script src="/assets/js/smart.js?v=' . $ver . '"></script>';
}

function ame_smart_empty(string $msg, string $href = '', string $cta = ''): string
{
    $h = '<div class="smt-empty"><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($href !== '' && $cta !== '') {
        $h .= '<a class="smt-btn" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($cta, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    return $h . '</div>';
}

function ame_smart_predictions(mysqli $db): array
{
    $pulse = ame_pulse_data($db);
    $rows = $pulse['restock'] ?? [];
    $low = ame_pulse_rows($db, "SELECT id, name, stock, min_stock, purchase_price, sale_price
        FROM products
        WHERE stock <= min_stock
        ORDER BY stock ASC, name ASC
        LIMIT 40");

    $seen = [];
    foreach ($rows as $r) {
        $seen[(int)$r['id']] = true;
    }
    foreach ($low as $r) {
        $id = (int)$r['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $stock = (float)$r['stock'];
        $min = (float)$r['min_stock'];
        $rows[] = [
            'id' => $id,
            'name' => $r['name'],
            'stock' => $stock,
            'min_stock' => $min,
            'purchase_price' => (float)$r['purchase_price'],
            'sold_14' => 0,
            'daily' => 0,
            'cover_days' => $stock > 0 ? 999 : 0,
            'suggest' => (int)max(1, ceil($min - $stock + 2)),
            'urgent' => true,
        ];
    }

    usort($rows, static function ($a, $b) {
        if (!empty($a['urgent']) !== !empty($b['urgent'])) {
            return !empty($a['urgent']) ? -1 : 1;
        }
        return ($a['cover_days'] ?? 999) <=> ($b['cover_days'] ?? 999);
    });

    return $rows;
}

function ame_smart_week_buy(mysqli $db): array
{
    $out = [];
    foreach (ame_smart_predictions($db) as $r) {
        $cover = (float)($r['cover_days'] ?? 999);
        if (!empty($r['urgent']) || $cover <= 7) {
            $out[] = $r;
        }
    }
    return $out;
}

function ame_smart_supplier_compare(mysqli $db, int $limit = 24): array
{
    if (!ame_smart_has_col($db, 'purchases', 'supplier_id')) {
        return [];
    }
    $sql = "SELECT p.product_id, pr.name AS product_name, p.purchase_price, p.qty, p.created_at,
            COALESCE(s.name, 'সাপ্লায়ার নেই') AS supplier_name, p.supplier_id
        FROM purchases p
        JOIN products pr ON pr.id = p.product_id
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        ORDER BY p.created_at DESC
        LIMIT 80";
    $rows = ame_pulse_rows($db, $sql, 80);
    $by = [];
    foreach ($rows as $r) {
        $pid = (int)$r['product_id'];
        if (!isset($by[$pid])) {
            $by[$pid] = [
                'product_id' => $pid,
                'name' => $r['product_name'],
                'offers' => [],
                'best' => null,
            ];
        }
        if (count($by[$pid]['offers']) >= 4) {
            continue;
        }
        $offer = [
            'supplier' => $r['supplier_name'],
            'price' => (float)$r['purchase_price'],
            'qty' => (float)$r['qty'],
            'when' => substr((string)$r['created_at'], 0, 10),
        ];
        $by[$pid]['offers'][] = $offer;
        if ($by[$pid]['best'] === null || $offer['price'] < $by[$pid]['best']['price']) {
            $by[$pid]['best'] = $offer;
        }
    }
    return array_slice(array_values($by), 0, $limit);
}

function ame_smart_profit_today(mysqli $db): array
{
    $sell = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(si.qty * si.sale_price),0) t
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        WHERE DATE(s.sale_date)=CURDATE() AND s.status='completed'");
    $cost = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(si.qty * si.purchase_price),0) t
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        WHERE DATE(s.sale_date)=CURDATE() AND s.status='completed'");
    $discount = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(discount),0) t FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'");
    $net = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(net_payable),0) t FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'");
    $other = 0.0;
    if (ame_smart_has_col($db, 'expenses', 'amount') && ame_smart_has_col($db, 'expenses', 'expense_date')) {
        $other = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE DATE(expense_date)=CURDATE()");
    } elseif (ame_smart_has_col($db, 'expenses', 'amount') && ame_smart_has_col($db, 'expenses', 'created_at')) {
        $other = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE DATE(created_at)=CURDATE()");
    }
    $gross = $sell - $cost;
    $real = $gross - $discount - $other;
    return [
        'sell' => $sell,
        'cost' => $cost,
        'discount' => $discount,
        'other' => $other,
        'net' => $net,
        'gross' => $gross,
        'real' => $real,
    ];
}

function ame_smart_profit_sales(mysqli $db, int $limit = 20): array
{
    $inv = ame_smart_has_col($db, 'sales', 'invoice_number') ? 's.invoice_number' : (ame_smart_has_col($db, 'sales', 'invoice_no') ? 's.invoice_no' : 's.id');
    $sql = "SELECT s.id, $inv AS invoice_no, s.sale_date, s.net_payable, s.discount,
            COALESCE(MAX(c.name), 'খুচরা') AS customer_name,
            COALESCE(SUM(si.qty * si.sale_price),0) AS sell,
            COALESCE(SUM(si.qty * si.purchase_price),0) AS cost
        FROM sales s
        LEFT JOIN sale_items si ON si.sale_id = s.id
        LEFT JOIN customers c ON c.id = s.customer_id
        WHERE s.status='completed'
        GROUP BY s.id, s.sale_date, s.net_payable, s.discount
        ORDER BY s.sale_date DESC, s.id DESC
        LIMIT " . (int)$limit;
    $rows = ame_pulse_rows($db, $sql, $limit);
    foreach ($rows as &$r) {
        $sell = (float)$r['sell'];
        $cost = (float)$r['cost'];
        $disc = (float)$r['discount'];
        $r['gross'] = $sell - $cost;
        $r['real'] = $sell - $cost - $disc;
    }
    unset($r);
    return $rows;
}

function ame_smart_customer_intel(mysqli $db): array
{
    $hasCreated = ame_smart_has_col($db, 'customers', 'created_at');
    $created = $hasCreated ? 'c.created_at' : 'NULL';
    $sql = "SELECT c.id, c.name, c.phone, c.receivable_balance, $created AS created_at,
            COALESCE(SUM(CASE WHEN s.status='completed' THEN s.net_payable ELSE 0 END),0) AS total_buy,
            COUNT(CASE WHEN s.status='completed' THEN s.id END) AS bill_count,
            MAX(CASE WHEN s.status='completed' THEN s.sale_date END) AS last_buy
        FROM customers c
        LEFT JOIN sales s ON s.customer_id = c.id
        WHERE c.is_customer = 1 OR c.is_customer IS NULL
        GROUP BY c.id
        ORDER BY total_buy DESC
        LIMIT 80";
    $rows = ame_pulse_rows($db, $sql, 80);
    $today = new DateTime('today');
    foreach ($rows as &$r) {
        $last = (string)($r['last_buy'] ?? '');
        $createdAt = (string)($r['created_at'] ?? '');
        $due = (float)($r['receivable_balance'] ?? 0);
        $bills = (int)$r['bill_count'];
        $total = (float)$r['total_buy'];
        $days = 999;
        if ($last !== '') {
            try {
                $days = (int)$today->diff(new DateTime(substr($last, 0, 10)))->days;
            } catch (Throwable $e) {
                $days = 999;
            }
        }
        $newDays = 999;
        if ($createdAt !== '') {
            try {
                $newDays = (int)$today->diff(new DateTime(substr($createdAt, 0, 10)))->days;
            } catch (Throwable $e) {
                $newDays = 999;
            }
        }
        if ($bills === 0 && $newDays <= 30) {
            $cat = 'new';
        } elseif ($bills === 0 || $days > 90) {
            $cat = 'inactive';
        } elseif ($total >= 20000 || $bills >= 8) {
            $cat = 'vip';
        } elseif ($newDays <= 30 && $bills <= 2) {
            $cat = 'new';
        } else {
            $cat = 'regular';
        }
        $r['category'] = $cat;
        $r['days'] = $days;
        $r['due'] = $due;
    }
    unset($r);
    return $rows;
}

function ame_smart_calendar(mysqli $db, string $from, string $to): array
{
    $events = [];
    $dues = ame_pulse_rows($db, "SELECT id, name, receivable_balance, next_due_date
        FROM customers
        WHERE receivable_balance > 0 AND next_due_date IS NOT NULL
          AND next_due_date BETWEEN '" . $db->real_escape_string($from) . "' AND '" . $db->real_escape_string($to) . "'
        ORDER BY next_due_date ASC
        LIMIT 40");
    foreach ($dues as $c) {
        $events[] = [
            'date' => $c['next_due_date'],
            'kind' => 'due',
            'title' => $c['name'] . ' · বকেয়া ' . ame_smart_money((float)$c['receivable_balance']),
            'href' => '/modules/customers/due-reminder.php',
        ];
    }

    if (ame_smart_has_col($db, 'suppliers', 'payable_balance') && ame_smart_has_col($db, 'suppliers', 'next_pay_date')) {
        $pays = ame_pulse_rows($db, "SELECT id, name, payable_balance, next_pay_date
            FROM suppliers
            WHERE payable_balance > 0 AND next_pay_date IS NOT NULL
              AND next_pay_date BETWEEN '" . $db->real_escape_string($from) . "' AND '" . $db->real_escape_string($to) . "'
            LIMIT 20");
        foreach ($pays as $s) {
            $events[] = [
                'date' => $s['next_pay_date'],
                'kind' => 'supplier',
                'title' => $s['name'] . ' · সাপ্লায়ার পেমেন্ট ' . ame_smart_money((float)$s['payable_balance']),
                'href' => '/modules/suppliers/pay.php',
            ];
        }
    }

    $sales = ame_pulse_rows($db, "SELECT DATE(sale_date) d, COUNT(*) c, COALESCE(SUM(net_payable),0) t
        FROM sales
        WHERE status='completed' AND DATE(sale_date) BETWEEN '" . $db->real_escape_string($from) . "' AND '" . $db->real_escape_string($to) . "'
        GROUP BY DATE(sale_date)
        LIMIT 40");
    foreach ($sales as $s) {
        $events[] = [
            'date' => $s['d'],
            'kind' => 'sale',
            'title' => (int)$s['c'] . 'টা সেল · ' . ame_smart_money((float)$s['t']),
            'href' => '/modules/sales/history.php',
        ];
    }

    if (ame_smart_has_col($db, 'purchases', 'created_at')) {
        $buys = ame_pulse_rows($db, "SELECT DATE(created_at) d, COUNT(*) c, COALESCE(SUM(total_cost),0) t
            FROM purchases
            WHERE DATE(created_at) BETWEEN '" . $db->real_escape_string($from) . "' AND '" . $db->real_escape_string($to) . "'
            GROUP BY DATE(created_at)
            LIMIT 40");
        foreach ($buys as $b) {
            $events[] = [
                'date' => $b['d'],
                'kind' => 'purchase',
                'title' => (int)$b['c'] . 'টা ক্রয় · ' . ame_smart_money((float)$b['t']),
                'href' => '/modules/purchase/history.php',
            ];
        }
    }

    $week = ame_smart_week_buy($db);
    if ($week) {
        $events[] = [
            'date' => date('Y-m-d'),
            'kind' => 'stock',
            'title' => count($week) . 'টা পণ্য এই সপ্তাহে কিনতে হবে',
            'href' => '/modules/smart/purchase.php',
        ];
    }

    usort($events, static fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
    return $events;
}

function ame_smart_dashboard(mysqli $db): array
{
    $pulse = ame_pulse_data($db);
    $profit = ame_smart_profit_today($db);
    $week = ame_smart_week_buy($db);
    $intel = ame_smart_customer_intel($db);
    $newCust = 0;
    $newSup = 0;
    if (ame_smart_has_col($db, 'customers', 'created_at')) {
        $newCust = (int)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM customers WHERE DATE(created_at)=CURDATE()");
    }
    if (ame_smart_has_col($db, 'suppliers', 'created_at')) {
        $newSup = (int)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM suppliers WHERE DATE(created_at)=CURDATE()");
    }
    $todayBuy = 0.0;
    if (ame_smart_has_col($db, 'purchases', 'created_at')) {
        $todayBuy = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(total_cost),0) t FROM purchases WHERE DATE(created_at)=CURDATE()");
    }
    $top = ame_pulse_rows($db, "SELECT p.name, SUM(si.qty) q
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.status='completed' AND DATE(s.sale_date)=CURDATE()
        JOIN products p ON p.id = si.product_id
        GROUP BY p.id, p.name
        ORDER BY q DESC
        LIMIT 5");
    $vip = 0;
    $inactive = 0;
    foreach ($intel as $c) {
        if ($c['category'] === 'vip') {
            $vip++;
        }
        if ($c['category'] === 'inactive') {
            $inactive++;
        }
    }
    $cal = ame_smart_calendar($db, date('Y-m-d'), date('Y-m-d', strtotime('+7 days')));
    return [
        'pulse' => $pulse,
        'profit' => $profit,
        'week' => $week,
        'today_buy' => $todayBuy,
        'new_customers' => $newCust,
        'new_suppliers' => $newSup,
        'top' => $top,
        'vip' => $vip,
        'inactive' => $inactive,
        'tasks' => array_slice($cal, 0, 8),
        'script' => ame_pulse_script($pulse),
    ];
}

function ame_smart_top_products(mysqli $db, int $days = 14, int $limit = 8): array
{
    return ame_pulse_rows($db, "SELECT p.name, SUM(si.qty) q, SUM(si.qty * si.sale_price) t
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id AND s.status='completed'
          AND DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days . " DAY)
        JOIN products p ON p.id = si.product_id
        GROUP BY p.id, p.name
        ORDER BY q DESC
        LIMIT " . (int)$limit, $limit);
}

function ame_smart_product_search(mysqli $db, string $q, int $limit = 8): array
{
    $q = trim($q);
    if (mb_strlen($q) < 1) {
        return [];
    }
    $like = '%' . $q . '%';
    $barcode = ame_smart_has_col($db, 'products', 'barcode');
    if (!function_exists('ame_product_option_sql')) {
        require_once __DIR__ . '/helpers.php';
    }
    $on = ame_product_option_sql('status');
    $sql = "SELECT id, sku, name, stock, sale_price, purchase_price, wholesale_price, dealer_price
        FROM products
        WHERE {$on} AND (name LIKE ? OR sku LIKE ?" . ($barcode ? ' OR barcode LIKE ?' : '') . ")
        ORDER BY name ASC
        LIMIT " . (int)$limit;
    $st = $db->prepare($sql);
    if ($barcode) {
        $st->bind_param('sss', $like, $like, $like);
    } else {
        $st->bind_param('ss', $like, $like);
    }
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $st->close();
    return $out;
}

function ame_smart_customer_search(mysqli $db, string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $like = '%' . $name . '%';
    $pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';
    $st = $db->prepare("SELECT id, name, phone, receivable_balance FROM customers
        WHERE name LIKE ? AND (is_customer = 1 OR is_customer IS NULL) AND {$pick}
        ORDER BY CHAR_LENGTH(name) ASC
        LIMIT 1");
    $st->bind_param('s', $like);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function ame_smart_parse_voice(string $text): array
{
    $raw = trim(preg_replace('/\s+/u', ' ', $text));
    $clean = preg_replace('/[।.!?,]+/u', ' ', $raw);
    $clean = trim((string)$clean);
    $stop = 'বিক্রি করো|বিক্রি কর|বিক্রি করুন|দাও|দিয়ে দাও|করে দাও|করে|সেল করো|সেল কর';
    $work = preg_replace('/\s*(?:' . $stop . ')\s*/u', ' ', $clean);
    $work = trim((string)$work);

    $customer = '';
    if (preg_match('/^(.+?)কে\s+/u', $work, $m)) {
        $customer = trim($m[1]);
        $work = trim(substr($work, strlen($m[0])));
    }

    $qty = 1.0;
    $price = null;
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:টা|টি|পিস|কেজি|kg)?/u', $work, $m, PREG_OFFSET_CAPTURE)) {
        $qty = (float)$m[1][0];
        $work = trim(substr($work, 0, $m[0][1]) . ' ' . substr($work, $m[0][1] + strlen($m[0][0])));
    }
    if (preg_match('/(\d+(?:\.\d+)?)\s*টাকা/u', $work, $m)) {
        $price = (float)$m[1];
        $work = trim(preg_replace('/(\d+(?:\.\d+)?)\s*টাকা/u', ' ', $work));
    }
    $product = trim(preg_replace('/\s+/u', ' ', $work));
    $product = preg_replace('/^(এবং|আর|একটা|একটি)\s+/u', '', $product);

    return [
        'raw' => $raw,
        'customer' => $customer,
        'product' => $product,
        'qty' => $qty > 0 ? $qty : 1,
        'price' => $price,
    ];
}

function ame_smart_resolve_voice(mysqli $db, string $text): array
{
    $parsed = ame_smart_parse_voice($text);
    $customer = $parsed['customer'] !== '' ? ame_smart_customer_search($db, $parsed['customer']) : null;
    $products = $parsed['product'] !== '' ? ame_smart_product_search($db, $parsed['product'], 5) : [];
    return [
        'ok' => true,
        'parsed' => $parsed,
        'customer' => $customer,
        'products' => $products,
        'draft_only' => true,
        'note' => 'Confirm চাপলেই সেল হবে — এখন শুধু Draft।',
    ];
}

function ame_smart_ask(mysqli $db, string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return ['title' => 'কী জানতে চান?', 'text' => 'আজকের বিক্রি, লাভ, কম স্টক, বেশি বিক্রি, বকেয়া — বাংলায় লিখুন।', 'cards' => []];
    }

    $n = mb_strtolower($q);
    $dash = ame_smart_dashboard($db);
    $p = $dash['pulse'];
    $pr = $dash['profit'];

    if (preg_match('/বিক্রি|সেল|আয়|কত\s*টাকা/u', $n) && !preg_match('/লাভ|প্রফিট/u', $n)) {
        return [
            'title' => 'আজকের বিক্রি',
            'text' => 'আজ বিক্রি ' . ame_smart_money((float)$p['today_sales']) . '। ' . (int)$p['today_bills'] . 'টা বিল। গতকালের তুলনায় '
                . number_format(abs((float)$p['delta']), 0) . '% ' . ($p['delta'] >= 0 ? 'বেশি' : 'কম') . '।',
            'cards' => [
                ['k' => 'বিক্রি', 'v' => ame_smart_money((float)$p['today_sales'])],
                ['k' => 'বিল', 'v' => (string)(int)$p['today_bills']],
                ['k' => 'আদায়', 'v' => ame_smart_money((float)$p['today_collected'])],
            ],
        ];
    }
    if (preg_match('/লাভ|প্রফিট|খরচের পর/u', $n)) {
        return [
            'title' => 'আজকের লাভ',
            'text' => 'ক্রয় ' . ame_smart_money($pr['cost']) . ' → বিক্রি ' . ame_smart_money($pr['sell'])
                . ' → ডিসকাউন্ট ' . ame_smart_money($pr['discount'])
                . ' → অন্যান্য খরচ ' . ame_smart_money($pr['other'])
                . '। প্রকৃত লাভ ' . ame_smart_money($pr['real']) . '।',
            'cards' => [
                ['k' => 'ক্রয়মূল্য', 'v' => ame_smart_money($pr['cost'])],
                ['k' => 'বিক্রয়মূল্য', 'v' => ame_smart_money($pr['sell'])],
                ['k' => 'প্রকৃত লাভ', 'v' => ame_smart_money($pr['real'])],
            ],
        ];
    }
    if (preg_match('/কম\s*স্টক|শেষ|স্টক\s*নেই|আলার্ট/u', $n)) {
        $week = $dash['week'];
        $names = [];
        foreach (array_slice($week, 0, 5) as $r) {
            $names[] = $r['name'] . ' (' . (int)$r['stock'] . ')';
        }
        return [
            'title' => 'কম স্টক',
            'text' => $week
                ? (count($week) . 'টা পণ্য শেষ হওয়ার পথে: ' . implode(', ', $names) . '। ক্রয় তালিকায় খুলুন।')
                : 'এখন জরুরি স্টক চাপ কম।',
            'cards' => [['k' => 'জরুরি', 'v' => (string)count($week)], ['k' => 'অ্যালার্ট লাইন', 'v' => (string)(int)$p['low_stock']]],
        ];
    }
    if (preg_match('/বেশি\s*বিক্রি|টপ|জনপ্রিয়|চালানো/u', $n)) {
        $top = ame_smart_top_products($db);
        $bits = [];
        foreach ($top as $t) {
            $bits[] = $t['name'] . ' · ' . (int)$t['q'] . 'টা';
        }
        return [
            'title' => 'বেশি বিক্রি হওয়া পণ্য',
            'text' => $bits ? ('গত ১৪ দিনে: ' . implode(', ', $bits) . '।') : 'এখনো যথেষ্ট বিক্রি জমেনি।',
            'cards' => array_map(static fn($t) => ['k' => $t['name'], 'v' => (int)$t['q'] . 'টা'], array_slice($top, 0, 4)),
        ];
    }
    if (preg_match('/বকেয়া|বাঁকি|বাকি|কালেকশন/u', $n)) {
        return [
            'title' => 'বকেয়া',
            'text' => 'মোট বকেয়া ' . ame_smart_money((float)$p['due_sum']) . '। '
                . (int)$p['due_over'] . ' জনের তারিখ পার, আজ ' . (int)$p['due_today'] . ' জনের কালেকশন দিন।',
            'cards' => [
                ['k' => 'মোট বকেয়া', 'v' => ame_smart_money((float)$p['due_sum'])],
                ['k' => 'পার', 'v' => (string)(int)$p['due_over']],
                ['k' => 'আজ', 'v' => (string)(int)$p['due_today']],
            ],
        ];
    }
    if (preg_match('/কিনতে|ক্রয়|পারচেজ|সাজেশ/u', $n)) {
        $week = $dash['week'];
        $bits = [];
        foreach (array_slice($week, 0, 6) as $r) {
            $bits[] = $r['name'] . ' · ' . (int)$r['suggest'] . 'টা';
        }
        return [
            'title' => 'এই সপ্তাহে কিনতে হবে',
            'text' => $bits ? implode(', ', $bits) . '।' : 'এখন কেনার তাড়া কম।',
            'cards' => [['k' => 'পণ্য', 'v' => (string)count($week)]],
        ];
    }

    $pred = ame_smart_product_search($db, $q, 3);
    if ($pred) {
        $row = $pred[0];
        $stock = (float)$row['stock'];
        return [
            'title' => $row['name'],
            'text' => 'স্টক ' . (int)$stock . 'টা। বিক্রি ' . ame_smart_money((float)$row['sale_price'])
                . ' · ক্রয় ' . ame_smart_money((float)$row['purchase_price']) . '।',
            'cards' => [
                ['k' => 'স্টক', 'v' => (string)(int)$stock],
                ['k' => 'কোড', 'v' => (string)($row['sku'] ?: $row['id'])],
            ],
        ];
    }

    return [
        'title' => 'দোকানের অবস্থা',
        'text' => $dash['script'],
        'cards' => [
            ['k' => 'আজকের বিক্রি', 'v' => ame_smart_money((float)$p['today_sales'])],
            ['k' => 'লাভ', 'v' => ame_smart_money($pr['real'])],
            ['k' => 'বকেয়া', 'v' => ame_smart_money((float)$p['due_sum'])],
            ['k' => 'কম স্টক', 'v' => (string)(int)$p['low_stock']],
        ],
    ];
}

function ame_smart_customer_label(string $cat): string
{
    return [
        'vip' => 'VIP',
        'regular' => 'নিয়মিত',
        'new' => 'নতুন',
        'inactive' => 'নিষ্ক্রিয়',
    ][$cat] ?? $cat;
}
