<?php
/**
 * Plain-text sales memo for clipboard / messenger.
 */

/**
 * @return array{ok: bool, text: string, invoice: string}
 */
function ame_sale_memo_plaintext(mysqli $db, int $sale_id, bool $is_english = false): array
{
    $sale_id = (int)$sale_id;
    $empty = ['ok' => false, 'text' => '', 'invoice' => ''];
    if ($sale_id <= 0) {
        return $empty;
    }
    $t = static function (string $bn, string $en) use ($is_english): string {
        return $is_english ? $en : $bn;
    };
  if (!function_exists('ensure_sales_schema')) {
        require_once __DIR__ . '/sale_common.php';
    }
    ensure_sales_schema($db);

    $stmt = $db->prepare(
        "SELECT s.*, COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name,
                c.phone AS customer_phone
         FROM sales s
         LEFT JOIN customers c ON c.id = s.customer_id
         WHERE s.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('i', $sale_id);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$sale) {
        return $empty;
    }

    $inv = trim((string)($sale['invoice_number'] ?? ''));
    if ($inv === '') {
        $inv = '#' . $sale_id;
    }
    $lines = [];
    $shop = function_exists('get_shop_settings') ? get_shop_settings() : [];
    $shop_name = trim((string)($shop['shop_name'] ?? 'Aponshop'));
    $lines[] = $shop_name;
    $lines[] = $t('বিক্রয় ইনভয়েস', 'Sales invoice') . ': ' . $inv;
    $lines[] = $t('স্ট্যাটাস', 'Status') . ': ' . (string)($sale['status'] ?? '');

    $ts = strtotime((string)($sale['completed_at'] ?: $sale['sale_date'] ?: $sale['created_at'] ?: ''));
    if ($ts) {
        $lines[] = $t('তারিখ', 'Date') . ': ' . date('d-m-Y H:i', $ts);
    }

    $cust = trim((string)($sale['customer_name'] ?? ''));
    if ($cust === '') {
        $cust = $t('খুচরা ক্রেতা', 'Walk-in');
    }
    $phone = trim((string)($sale['customer_phone'] ?? $sale['guest_customer_phone'] ?? ''));
    $lines[] = $t('ক্রেতা', 'Customer') . ': ' . $cust . ($phone !== '' ? ' · ' . $phone : '');

    $ist = @$db->prepare(
        "SELECT COALESCE(NULLIF(si.item_name, ''), p.name, '') AS name, si.qty, si.sale_price
         FROM sale_items si
         LEFT JOIN products p ON p.id = si.product_id
         WHERE si.sale_id = ?
         ORDER BY si.id ASC"
    );
    if ($ist) {
        $ist->bind_param('i', $sale_id);
        $ist->execute();
        $res = $ist->get_result();
        $lines[] = '';
        $lines[] = $t('পণ্য', 'Products') . ':';
        $n = 0;
        while ($row = $res->fetch_assoc()) {
            $n++;
            $qty = (float)($row['qty'] ?? 0);
            $price = (float)($row['sale_price'] ?? 0);
            $lines[] = $n . '. ' . trim((string)($row['name'] ?? ''))
                . ' × ' . rtrim(rtrim(sprintf('%.3f', $qty), '0'), '.')
                . ' @ ৳' . number_format($price, 2)
                . ' = ৳' . number_format($qty * $price, 2);
        }
        $ist->close();
        if ($n === 0) {
            $lines[] = $t('(কোনো পণ্য নেই)', '(No products)');
        }
    }

    $svc = @$db->prepare(
        'SELECT service_type, amount FROM sale_services WHERE sale_id = ? ORDER BY id ASC'
    );
    if ($svc) {
        $svc->bind_param('i', $sale_id);
        $svc->execute();
        $sres = $svc->get_result();
        $has_svc = false;
        while ($row = $sres->fetch_assoc()) {
            if (!$has_svc) {
                $lines[] = '';
                $lines[] = $t('সার্ভিস', 'Services') . ':';
                $has_svc = true;
            }
            $lines[] = '• ' . trim((string)($row['service_type'] ?? ''))
                . ' ৳' . number_format((float)($row['amount'] ?? 0), 2);
        }
        $svc->close();
    }

    $lines[] = '';
    $lines[] = $t('ছাড়', 'Discount') . ': ৳' . number_format((float)($sale['discount'] ?? 0), 2);
    $lines[] = $t('মোট বিল / নেট', 'Bill / net') . ': ৳' . number_format((float)($sale['net_payable'] ?? $sale['total_bill'] ?? 0), 2);
    $lines[] = $t('পরিশোধ', 'Paid') . ': ৳' . number_format((float)($sale['paid_amount'] ?? 0), 2);
    $lines[] = $t('বাকি', 'Due') . ': ৳' . number_format((float)($sale['due_amount'] ?? 0), 2);

    if (function_exists('ame_memo_public_url')) {
        $lines[] = $t('মেমো লিংক', 'Memo link') . ': ' . ame_memo_public_url($sale_id);
    }

    return [
        'ok' => true,
        'text' => implode("\n", $lines),
        'invoice' => $inv,
    ];
}