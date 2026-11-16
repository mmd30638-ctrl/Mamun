<?php
/**
 * ক্রয়/বিক্রয় মেমোর সাবেক + এই ইনভয়েস − পরিশোধ = বর্তমান
 */

function ame_memo_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ame_memo_money($value)
{
    return number_format((float)$value, 2);
}

function ame_balance_book_norm_kind($kind): string
{
    $kind = strtolower(trim((string)$kind));
    if (in_array($kind, ['supplier', 'purchase', 'buy', 'purchase_supplier'], true)) {
        return 'purchase_supplier';
    }
    if (in_array($kind, ['customer', 'purchase_customer', 'purchase_from_customer', 'customer_purchase'], true)) {
        return 'purchase_customer';
    }
    return 'sale';
}

function ame_balance_book_is_blank($value): bool
{
    return $value === null || $value === '';
}

/**
 * ক্রয় মেমোর আসল পরিশোধ। যেটা paid_amount-এ আছে সেটাই — পুরো বিল পরিশোধ ০ করা হয় না।
 * সাবেক + ক্রয় − এই পরিশোধ = মোট।
 */
function ame_purchase_canonical_paid($bill, $paid): float
{
    unset($bill);
    $paid = round((float)$paid, 2);
    if ($paid < 0) {
        return 0.0;
    }
    return $paid;
}

/**
 * মেমো কাগজ ও ক্রয় তালিকায় ডাটাবেজের আসল পরিশোধই দেখায়।
 *
 * আগে বিলের সমান পরিশোধকে ০ দেখানো হতো। এতে সম্পূর্ণ পরিশোধ করা
 * কাস্টমার-ক্রয়ে ০ − বিল + ০ সূত্রে ভুল ঋণাত্মক ব্যালেন্স দেখা যেত।
 */
function ame_purchase_display_paid($bill, $paid): float
{
    return ame_purchase_canonical_paid($bill, $paid);
}

/**
 * মেমো কাগজ: আসল পরিশোধসহ সাপ্লায়ার লিস্টের বকেয়া = সর্বমোট।
 * সাবেক = সর্বমোট − ক্রয় + পরিশোধ, তাই প্রদর্শিত প্রতিটি সংখ্যা সূত্রের সাথে মেলে।
 * previous_supplier_due / paid_amount হিসাব-বইতে লেখা হয় না।
 *
 * @return array{previous: float, bill: float, paid: float, total: float}
 */
function ame_purchase_memo_paper_due(bool $is_customer, float $previous, float $bill, float $paid, $live_due = null): array
{
    $previous = round((float)$previous, 2);
    $bill = round((float)$bill, 2);
    $paid = ame_purchase_display_paid($bill, $paid);
    if (!function_exists('ame_memo_paper_looks_inflated')) {
        require_once __DIR__ . '/memo_paper.php';
    }
    if ($live_due !== null && $live_due !== '') {
        $total = round((float) $live_due, 2);
        $kind = $is_customer ? 'purchase_customer' : 'purchase_supplier';
        $previous = ame_balance_book_previous_amount($kind, $total, $bill, $paid);
        return [
            'previous' => $previous,
            'bill' => $bill,
            'paid' => $paid,
            'total' => $total,
        ];
    }
    if (ame_memo_paper_looks_inflated($previous, $bill)) {
        $previous = 0.0;
    }
    if ($is_customer) {
        return [
            'previous' => $previous,
            'bill' => $bill,
            'paid' => $paid,
            'total' => round($previous - $bill + $paid, 2),
        ];
    }
    return [
        'previous' => $previous,
        'bill' => $bill,
        'paid' => $paid,
        'total' => round($previous + $bill - $paid, 2),
    ];
}

function ame_balance_book_after_amount(string $kind, float $previous, float $bill, float $paid, float $extra = 0.0): float
{
    if ($kind === 'purchase_customer') {
        return round($previous - $bill + $paid, 2);
    }
    return round($previous + $bill - $paid - $extra, 2);
}

function ame_balance_book_previous_amount(string $kind, float $after, float $bill, float $paid, float $extra = 0.0): float
{
    if ($kind === 'purchase_customer') {
        return round($after + $bill - $paid, 2);
    }
    return round($after - $bill + $paid + $extra, 2);
}

/**
 * একমাত্র হিসাব-বই: ক্রয় মেমো, বিক্রয় মেমো, লেজার ও স্টেটমেন্ট।
 * বিক্রয়/সাপ্লায়ার ক্রয়: সাবেক + বিল − পরিশোধ − সমন্বয় = বর্তমান।
 * কাস্টমার থেকে ক্রয়: সাবেক − বিল + পরিশোধ = বর্তমান।
 * সর্বমোট কখনো সূত্রের বাইরে যায় না। সাবেক খালি/০ হলে after থেকে সাবেক ফেরত আনে।
 */
function ame_balance_book($kind, $previous, $bill, $paid, $after = null, $extra = 0.0): array
{
    $kind = ame_balance_book_norm_kind($kind);
    $bill = round((float)$bill, 2);
    $paid = round((float)$paid, 2);
    $extra = round((float)$extra, 2);
    if ($extra < 0) {
        $extra = 0.0;
    }

    $prev_blank = ame_balance_book_is_blank($previous);
    $previous = $prev_blank ? 0.0 : round((float)$previous, 2);
    $after_blank = ame_balance_book_is_blank($after);
    $after_val = $after_blank ? null : round((float)$after, 2);

    $from_prev = ame_balance_book_after_amount($kind, $previous, $bill, $paid, $extra);
    $reconstructed = false;

    if ($after_val !== null) {
        $delta_ok = abs($after_val - $from_prev) <= 0.02;
        $prev_useless = $prev_blank || (abs($previous) < 0.009 && !$delta_ok);
        if ($prev_useless && abs($after_val - $from_prev) > 0.02) {
            $previous = ame_balance_book_previous_amount($kind, $after_val, $bill, $paid, $extra);
            $from_prev = $after_val;
            $reconstructed = true;
        }
    }

    $total = ame_balance_book_after_amount($kind, $previous, $bill, $paid, $extra);

    return [
        'kind' => $kind,
        'previous' => $previous,
        'current' => $bill,
        'bill' => $bill,
        'paid' => $paid,
        'extra' => $extra,
        'total' => $total,
        'after' => $total,
        'reconstructed' => $reconstructed,
        'stamp_mismatch' => $after_val !== null && abs($after_val - $total) > 0.02,
        'formula' => $kind === 'purchase_customer'
            ? 'previous-bill+paid'
            : ($extra > 0.009 ? 'previous+bill-paid-extra' : 'previous+bill-paid'),
    ];
}

/**
 * সময়ের ক্রমে মেমো সারি — স্ট্যাম্প থাকলে সেটা, না থাকলে আগের মেমোর বর্তমান।
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function ame_balance_chain(array $rows, $kind, $opening = 0.0, bool $follow_run = false): array
{
    $kind = ame_balance_book_norm_kind($kind);
    $run = round((float)$opening, 2);
    $out = [];
    $index = 0;
    foreach ($rows as $row) {
        $index++;
        $bill = round((float)($row['bill'] ?? $row['current'] ?? 0), 2);
        $paid = round((float)($row['paid'] ?? 0), 2);
        $extra = round((float)($row['extra'] ?? $row['due_adjustment'] ?? 0), 2);
        $adj = round((float)($row['adjustment'] ?? 0), 2);
        $stamped_prev = array_key_exists('previous', $row)
            ? $row['previous']
            : ($row['previous_due'] ?? null);
        $stamped_after = array_key_exists('after', $row)
            ? $row['after']
            : ($row['due_after'] ?? null);
        if ($follow_run) {
            if ($index === 1) {
                $prev = ame_balance_book_is_blank($stamped_prev) ? $run : round((float)$stamped_prev, 2);
            } else {
                $prev = round($run - $adj, 2);
            }
            $book = ame_balance_book($kind, $prev, $bill, $paid, null, $extra);
        } else {
            $book = ame_balance_book($kind, $stamped_prev, $bill, $paid, $stamped_after, $extra);
            $has_signal = !ame_balance_book_is_blank($stamped_prev) || !ame_balance_book_is_blank($stamped_after);
            $prev = $has_signal ? $book['previous'] : $run;
            $book = ame_balance_book($kind, $prev, $bill, $paid, null, $extra);
        }
        $run = $book['total'];
        $row['book'] = $book;
        $row['previous'] = $book['previous'];
        $row['previous_due'] = $book['previous'];
        $row['due_after'] = $book['total'];
        $row['after'] = $book['total'];
        $row['total'] = $book['total'];
        $out[] = $row;
    }
    return $out;
}

function ame_balance_book_money($amount): string
{
    $n = round((float)$amount, 2);
    $sign = $n < -0.009 ? '−' : '';
    return $sign . '৳' . number_format(abs($n), 2);
}

function ame_balance_book_proof(array $book): string
{
    $prev = ame_balance_book_money($book['previous'] ?? 0);
    $bill = ame_balance_book_money($book['bill'] ?? $book['current'] ?? 0);
    $paid = ame_balance_book_money($book['paid'] ?? 0);
    $total = ame_balance_book_money($book['total'] ?? 0);
    if (($book['kind'] ?? '') === 'purchase_customer') {
        return $prev . ' − ' . $bill . ' + ' . $paid . ' = ' . $total;
    }
    if ((float)($book['extra'] ?? 0) > 0.009) {
        return $prev . ' + ' . $bill . ' − ' . $paid . ' − ' . ame_balance_book_money($book['extra']) . ' = ' . $total;
    }
    return $prev . ' + ' . $bill . ' − ' . $paid . ' = ' . $total;
}

function ame_memo_due_math($previous, $current, $paid, $after = null, $extra = 0.0)
{
    $book = ame_balance_book('sale', $previous, $current, $paid, $after, $extra);

    return [
        'previous' => $book['previous'],
        'current' => $book['current'],
        'paid' => $book['paid'],
        'total' => $book['total'],
        'extra' => $book['extra'],
        'kind' => $book['kind'],
        'reconstructed' => $book['reconstructed'],
        'book' => $book,
    ];
}

function ame_bn_memo_words()
{
    $en = function_exists('is_en') && is_en();
    if ($en) {
        return [
            'sale_title' => 'Sales memo',
            'purchase_title' => 'Purchase memo',
            'receipt' => 'Transaction receipt',
            'invoice_id' => 'Invoice number',
            'memo_no' => 'Memo number',
            'datetime' => 'Date and time',
            'customer' => 'Customer',
            'supplier' => 'Supplier',
            'walkin' => 'Walk-in customer',
            'phone' => 'Mobile',
            'payment' => 'Payment account',
            'engineer' => 'Engineer',
            'service' => 'Service',
            'work_bill' => 'Engineer & work bill',
            'work_desc' => 'Work details',
            'item' => 'Product',
            'qty' => 'Quantity',
            'rate' => 'Purchase rate',
            'sale_rate' => 'Sale rate',
            'line_total' => 'Total',
            'bill' => 'This invoice',
            'goods' => 'Goods total',
            'invoice_total' => 'Invoice total',
            'discount' => 'Discount',
            'vat' => 'VAT',
            'due' => 'Due',
            'paid' => 'Paid',
            'sabek' => 'Previous due',
            'current' => 'This invoice',
            'net' => 'Grand balance',
            'download' => 'Download',
            'share' => 'Share',
            'print' => 'Print',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'new_sale' => 'New sale',
            'new_purchase' => 'New purchase',
            'home' => 'Home',
            'go_back' => 'Back',
            'footer' => 'This is an electronic receipt.',
            'missing' => 'Memo was not found.',
            'share_fail' => 'This browser cannot share the file. Download it and send it.',
            'expense' => 'Extra cost',
            'formula' => 'Previous due +/− this invoice − paid = grand balance',
            'none' => '—',
            'exchange' => 'Old battery exchange',
            'cash_paid' => 'Cash or account paid',
            'total_sale' => 'Total sale',
            'total_paid' => 'Total paid',
            'exchange_note' => 'The customer returned an old battery.',
            'vehicle' => 'Vehicle number',
            'done' => 'Purchase saved',
        ];
    }
    return [
        'sale_title' => 'বিক্রয় মেমো',
        'purchase_title' => 'ক্রয় মেমো',
        'receipt' => 'লেনদেন রসিদ',
        'invoice_id' => 'ইনভয়েস নম্বর',
        'memo_no' => 'মেমো নম্বর',
        'datetime' => 'তারিখ ও সময়',
        'customer' => 'ক্রেতা',
        'supplier' => 'সাপ্লায়ার',
        'walkin' => 'খুচরা ক্রেতা',
        'phone' => 'মোবাইল',
        'payment' => 'পেমেন্ট মাধ্যম',
        'engineer' => 'ইঞ্জিনিয়ার',
        'service' => 'সার্ভিস',
        'work_bill' => 'ইঞ্জিনিয়ার ও কাজের বিল',
        'work_desc' => 'কাজের বিবরণ',
        'item' => 'পণ্য',
        'qty' => 'পরিমাণ',
        'rate' => 'ক্রয় মূল্য',
        'sale_rate' => 'বিক্রয় দর',
        'line_total' => 'মোট',
        'bill' => 'এই ইনভয়েস',
        'goods' => 'পণ্য মোট',
        'invoice_total' => 'ইনভয়েস মোট',
        'discount' => 'ডিসকাউন্ট',
        'vat' => 'ভ্যাট',
        'due' => 'বকেয়া',
        'paid' => 'পরিশোধ',
        'sabek' => 'সাবেক বকেয়া',
        'current' => 'এই ইনভয়েস',
        'net' => 'সর্বমোট ব্যালেন্স',
        'download' => 'ডাউনলোড',
        'share' => 'শেয়ার',
        'print' => 'প্রিন্ট',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram',
        'new_sale' => 'নতুন বিক্রয় করুন',
        'new_purchase' => 'নতুন ক্রয় করুন',
        'home' => 'হোম',
        'go_back' => 'পেছনে',
        'footer' => 'এটি ইলেকট্রনিক রসিদ।',
        'missing' => 'মেমো পাওয়া যায়নি।',
        'share_fail' => 'এই ব্রাউজার সরাসরি শেয়ার করে না। ডাউনলোড করে পাঠান।',
        'expense' => 'অতিরিক্ত খরচ',
        'formula' => 'সাবেক বকেয়া যোগ/বিয়োগ + এই ইনভয়েস − পরিশোধ = সর্বমোট ব্যালেন্স',
        'none' => '—',
        'exchange' => 'পুরাতন ব্যাটারি এক্সচেঞ্জ',
        'cash_paid' => 'নগদ/অ্যাকাউন্ট পরিশোধ',
        'total_sale' => 'মোট বিক্রয়',
        'total_paid' => 'মোট পরিশোধ',
        'exchange_note' => 'ক্রেতা পুরাতন ব্যাটারি ফেরত দিয়েছেন।',
        'vehicle' => 'গাড়ির নম্বর',
        'done' => 'ক্রয় সম্পন্ন — হোল্ড নয়',
    ];
}

function ame_memo_render_nav(array $opt = []): void
{
    $words = ame_bn_memo_words();
    $home = (string)($opt['home'] ?? '/index.php');
    $back = (string)($opt['back'] ?? '');
    $home_label = (string)($opt['home_label'] ?? $words['home']);
    $back_label = (string)($opt['back_label'] ?? $words['go_back']);
    echo '<nav class="tm-nav" aria-label="' . ame_memo_h($home_label) . '">';
    echo '<a class="tm-home" href="' . ame_memo_h($home) . '">' . ame_memo_h($home_label) . '</a>';
    if ($back !== '') {
        echo '<a class="tm-go-back" href="' . ame_memo_h($back) . '">' . ame_memo_h($back_label) . '</a>';
    } else {
        $fallback = (string)($opt['back_fallback'] ?? 'history.php');
        echo '<a class="tm-go-back" href="' . ame_memo_h($fallback) . '" onclick="if(window.history.length>1){history.back();return false;}">' . ame_memo_h($back_label) . '</a>';
    }
    echo '</nav>';
}

function ame_memo_render_actions(array $opt): void
{
    $words = ame_bn_memo_words();
    $file = (string) ($opt['file'] ?? 'memo.png');
    $title = (string) ($opt['title'] ?? '');
    $text = (string) ($opt['text'] ?? $title);
    $phone = preg_replace('/\D+/', '', (string) ($opt['phone'] ?? '')) ?? '';
    if (str_starts_with($phone, '00')) {
        $phone = substr($phone, 2);
    }
    if (str_starts_with($phone, '0') && strlen($phone) >= 11) {
        $phone = '880' . substr($phone, 1);
    } elseif ($phone !== '' && !str_starts_with($phone, '880') && strlen($phone) === 10) {
        $phone = '880' . $phone;
    }
    $wa = 'https://wa.me/' . ($phone !== '' ? $phone : '') . '?text=' . rawurlencode($text);
    $tg = 'https://t.me/share/url?text=' . rawurlencode($text);
    $do = (string) ($_GET['do'] ?? '');
    if (!in_array($do, ['print', 'share', 'wa', 'tg', 'download'], true)) {
        $do = '';
    }
    $file_base = preg_replace('/\.png$/i', '', $file);
    echo '<div class="tm-btns">';
    echo '<button class="tm-sh" type="button" onclick="ameMemoCapture(\'share\',' . json_encode($file_base) . ')">' . ame_memo_h($words['share']) . '</button>';
    echo '<button class="tm-pr" type="button" onclick="window.print()">' . ame_memo_h($words['print']) . '</button>';
    echo '<button class="tm-wa" type="button" onclick="ameMemoCapture(\'wa\',' . json_encode($file_base) . ')">' . ame_memo_h($words['whatsapp']) . '</button>';
    echo '<button class="tm-dl" type="button" onclick="ameMemoCapture(\'download\',' . json_encode($file_base) . ')">' . ame_memo_h($words['download']) . '</button>';
    foreach (($opt['links'] ?? []) as $link) {
        $href = (string) ($link['href'] ?? '');
        $label = (string) ($link['label'] ?? '');
        if ($href === '' || $label === '') {
            continue;
        }
        echo '<a class="tm-back" href="' . ame_memo_h($href) . '">' . ame_memo_h($label) . '</a>';
    }
    echo '</div>';
    $payload = json_encode([
        'file' => $file,
        'title' => $title,
        'text' => $text,
        'wa' => $wa,
        'do' => $do,
        'fail' => $words['share_fail'],
        'caption' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    $js_ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.451', ENT_QUOTES, 'UTF-8');
    echo '<script src="/assets/js/ame-memo-file.js?v=' . $js_ver . '"></script>';
    echo '<script>window.AME_MEMO=' . $payload . ';';
    echo <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var d = (window.AME_MEMO || {}).do || '';
    if (d === 'print') window.print();
    else if (d === 'tg') {
        var file = String((window.AME_MEMO || {}).file || 'invoice.pdf').replace(/\.png$/i, '');
        if (typeof window.ameMemoCapture === 'function') window.ameMemoCapture('share-telegram', file, 'memo-container');
    } else if (d === 'share' || d === 'wa' || d === 'download') {
        var file = String((window.AME_MEMO || {}).file || 'invoice.pdf').replace(/\.png$/i, '');
        if (typeof window.ameMemoCapture === 'function') window.ameMemoCapture(d === 'wa' ? 'share' : d, file, 'memo-container');
    }
});
JS;
    echo '</script>';
}

function ame_memo_taka_class($amount)
{
    $n = (float)$amount;
    if ($n > 0.009) {
        return 'due';
    }
    if ($n < -0.009) {
        return 'net';
    }
    return '';
}
