<?php
/**
 * Pulse 8.0 — existing sales / dues / stock / loans, one command layer.
 */

function ame_pulse_scalar(mysqli $db, string $sql, $fallback = 0)
{
    try {
        $res = $db->query($sql);
        if (!$res) {
            return $fallback;
        }
        $row = $res->fetch_assoc();
        if (!$row) {
            return $fallback;
        }
        $val = reset($row);
        return is_numeric($val) ? $val + 0 : $fallback;
    } catch (Throwable $e) {
        error_log('Pulse query failed: ' . $e->getMessage());
        return $fallback;
    }
}

function ame_pulse_rows(mysqli $db, string $sql, int $limit = 12): array
{
    try {
        $res = $db->query($sql);
        if (!$res) {
            return [];
        }
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    } catch (Throwable $e) {
        error_log('Pulse rows failed: ' . $e->getMessage());
        return [];
    }
}

function ame_wa_digits(string $phone): string
{
    $d = preg_replace('/\D+/', '', $phone);
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '880') && strlen($d) >= 13) {
        return $d;
    }
    if (str_starts_with($d, '0') && strlen($d) === 11) {
        return '88' . $d;
    }
    if (strlen($d) === 10 && str_starts_with($d, '1')) {
        return '880' . $d;
    }
    return $d;
}

function ame_pulse_data(mysqli $db): array
{
    $today_sales = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(net_payable),0) t FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'");
    $yday_sales = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(net_payable),0) t FROM sales WHERE DATE(sale_date)=DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status='completed'");
    $today_collected = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(amount),0) t FROM customer_payments WHERE DATE(payment_date)=CURDATE()");
    $today_bills = (float)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'");
    $low_stock = (int)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM products WHERE stock <= min_stock");
    $due_today = (int)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM customers WHERE next_due_date = CURDATE() AND receivable_balance > 0");
    $due_over = (int)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM customers WHERE next_due_date < CURDATE() AND next_due_date IS NOT NULL AND receivable_balance > 0");
    $due_sum = (float)ame_pulse_scalar($db, "SELECT COALESCE(SUM(receivable_balance),0) t FROM customers WHERE receivable_balance > 0");
    $loan_over = (int)ame_pulse_scalar($db, "SELECT COUNT(*) c FROM loan_repayment_schedule s JOIN loans l ON l.id=s.loan_id WHERE s.status IN ('pending','partial') AND s.due_date < CURDATE() AND l.status='open'");

    $delta = 0.0;
    if ($yday_sales > 0) {
        $delta = (($today_sales - $yday_sales) / $yday_sales) * 100;
    } elseif ($today_sales > 0) {
        $delta = 100.0;
    }

    $dues = ame_pulse_rows($db, "SELECT id, name, phone, customer_code, receivable_balance, next_due_date
        FROM customers
        WHERE receivable_balance > 0
        ORDER BY (next_due_date IS NULL) ASC, next_due_date ASC, receivable_balance DESC
        LIMIT 12");

    $restock = ame_pulse_rows($db, "SELECT p.id, p.name, p.stock, p.min_stock, p.purchase_price,
            COALESCE(SUM(si.qty),0) AS sold_14
        FROM products p
        LEFT JOIN sale_items si ON si.product_id = p.id
        LEFT JOIN sales s ON s.id = si.sale_id AND s.status='completed' AND DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY p.id, p.name, p.stock, p.min_stock, p.purchase_price
        HAVING COALESCE(SUM(si.qty),0) > 0
        ORDER BY (COALESCE(SUM(si.qty),0) / 14) / GREATEST(p.stock, 0.01) DESC
        LIMIT 16");

    foreach ($restock as &$r) {
        $sold = (float)$r['sold_14'];
        $stock = (float)$r['stock'];
        $daily = $sold / 14;
        $r['daily'] = $daily;
        $r['cover_days'] = $daily > 0 ? $stock / $daily : 999;
        $need = max(0, ($daily * 14) - $stock);
        if ($stock <= (float)$r['min_stock']) {
            $need = max($need, (float)$r['min_stock'] - $stock + ($daily * 7));
        }
        $r['suggest'] = (int)ceil($need);
        $r['urgent'] = $r['cover_days'] <= 7 || $stock <= (float)$r['min_stock'];
    }
    unset($r);

    usort($restock, static function ($a, $b) {
        if ($a['urgent'] !== $b['urgent']) {
            return $a['urgent'] ? -1 : 1;
        }
        return $a['cover_days'] <=> $b['cover_days'];
    });

    $actions = [];
    if ($due_over > 0) {
        $actions[] = [
            'tone' => 'danger',
            'title' => $due_over . ' জনের বকেয়া তারিখ পার',
            'lead' => 'হোয়াটসঅ্যাপে স্লিপ পাঠান বা পেমেন্ট নিন',
            'href' => '/modules/customers/due-reminder.php',
        ];
    }
    if ($due_today > 0) {
        $actions[] = [
            'tone' => 'gold',
            'title' => 'আজ ' . $due_today . ' জনের কালেকশন দিন',
            'lead' => 'রিমাইন্ডার লিস্টে নাম আছে',
            'href' => '/modules/customers/due-reminder.php',
        ];
    }
    $urgent_stock = 0;
    foreach ($restock as $r) {
        if (!empty($r['urgent'])) {
            $urgent_stock++;
        }
    }
    if ($urgent_stock > 0) {
        $actions[] = [
            'tone' => 'ink',
            'title' => $urgent_stock . 'টা পণ্য শেষ হওয়ার পথে',
            'lead' => '১৪ দিনের বিক্রি দেখে রিস্টক সাজেশন',
            'href' => '#restock',
        ];
    }
    if (!$actions) {
        $actions[] = [
            'tone' => 'ok',
            'title' => 'আজ চাপ কম',
            'lead' => 'নতুন সেল খুলুন বা স্টক গুছিয়ে রাখুন',
            'href' => '/modules/sales/pos.php',
        ];
    }

    return [
        'today_sales' => $today_sales,
        'yday_sales' => $yday_sales,
        'delta' => $delta,
        'today_collected' => $today_collected,
        'today_bills' => (int)$today_bills,
        'low_stock' => $low_stock,
        'due_today' => $due_today,
        'due_over' => $due_over,
        'due_sum' => $due_sum,
        'loan_over' => $loan_over,
        'dues' => $dues,
        'restock' => $restock,
        'actions' => $actions,
        'shop' => shop_name(),
        'shop_phone' => shop_phone(),
    ];
}

function ame_pulse_script(array $p): string
{
    $bits = [];
    if ($p['today_sales'] > 0) {
        $dir = $p['delta'] >= 0 ? t('pulse_more') : t('pulse_less');
        $bits[] = t('pulse_sales_vs', [
            'amt' => number_format((float)($p['today_sales'] ?? 0), 0),
            'pct' => number_format(abs($p['delta']), 0),
            'dir' => $dir,
        ]);
    } else {
        $bits[] = t('pulse_no_sales');
    }
    if ($p['due_over'] > 0) {
        $bits[] = t('pulse_overdue', ['n' => $p['due_over']]);
    } elseif ($p['due_today'] > 0) {
        $bits[] = t('pulse_collect_today', ['n' => $p['due_today']]);
    }
    if ($p['low_stock'] > 0) {
        $bits[] = t('pulse_low_stock', ['n' => $p['low_stock']]);
    }
    if ($p['today_collected'] > 0) {
        $bits[] = t('pulse_collected', ['amt' => number_format((float)($p['today_collected'] ?? 0), 0)]);
    }
    return implode('. ', $bits) . (is_en() ? '.' : '।');
}

function ame_can_see_pulse(mysqli $db): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
        return true;
    }
    return has_permission($db, 'm-pulse');
}

function ame_pulse_board_when(): string
{
    if (is_en()) {
        return date('l, j M Y');
    }
    $days = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
    return $days[(int) date('w')] . ' · ' . date('d/m/Y');
}

function ame_pulse_taka(float $n, int $dec = 0): string
{
    $txt = number_format(round($n, $dec), $dec);
    if (function_exists('is_en') && is_en()) {
        return 'Tk ' . $txt;
    }
    return $txt . ' টাকা';
}

function ame_pulse_ico(string $name): string
{
    $svg = [
        'sales' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9l8-6 8 6v10H4z"/><path d="M9 19v-6h6v6"/></svg>',
        'collect' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v8M9.2 10.4c.7-1 2.8-1.2 3.6 0 .9 1.3-.2 2.4-1.8 2.6-1.6.2-2.8 1.3-1.8 2.6.8 1.1 2.9.9 3.6 0"/></svg>',
        'due' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>',
        'stock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4 4 8v8l8 4 8-4V8l-8-4z"/><path d="M12 12 4 8M12 12l8-4M12 12v8"/></svg>',
        'out' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5M12 16.5v.5"/></svg>',
        'sale' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h15l-1.5 9H8L6 7z"/><path d="M6 7 5 4H2"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg>',
        'pay' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>',
        'buy' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16l-1 11H5L4 8z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M8 12.5 10.6 15 16 9.5"/></svg>',
    ];
    return $svg[$name] ?? '';
}
