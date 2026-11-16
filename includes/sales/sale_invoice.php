<?php
/*
 * সম্পন্ন বিক্রয় Invoice-এর সাধারণ তথ্য
 */
require_once __DIR__ . '/../memo_paper.php';

function ame_invoice_h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function ame_invoice_money($value)
{
    return number_format((float)$value, 2);
}

function ame_invoice_words($is_english)
{
    return $is_english
        ? [
            'missing' => 'This invoice was not found.',
            'back_pos' => 'Back to POS',
            'back_history' => 'Back to sales history',
            'receipt' => 'Sales Receipt',
            'invoice' => 'Invoice',
            'date' => 'Date',
            'customer' => 'Customer',
            'phone' => 'Mobile',
            'note' => 'Note',
            'code' => 'Customer ID',
            'registered' => 'Registered Customer',
            'retail' => 'Retail / Walk-in Customer',
            'item' => 'Item',
            'qty' => 'Qty',
            'price' => 'Price',
            'total' => 'Total',
            'service' => 'Service',
            'engineer' => 'Engineer',
            'work_bill' => 'Engineer & work bill',
            'work_desc' => 'Work details',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'vat' => 'VAT',
            'rounding' => 'Rounding',
            'payable' => 'Net payable',
            'paid' => 'Paid',
            'due' => 'Current due',
            'change' => 'Change',
            'payments' => 'Payments',
            'reference' => 'Ref',
            'previous_balance' => 'Previous balance',
            'due_adjustment' => 'Previous due paid',
            'new_balance' => 'New customer balance',
            'print' => 'Print',
            'download' => 'Download',
            'share' => 'Share',
            'footer' => 'This is a computer generated receipt.',
            'share_fail' => 'This browser cannot share the image. Please download it instead.',
            'phone_label' => 'Phone',
            'exchange' => 'Old battery received / Exchange',
            'exchange_rate' => 'Exchange rate',
            'exchange_note' => 'Customer returned old batteries.',
            'cash_paid' => 'Cash/Account paid',
            'total_sale' => 'Total sale',
            'total_paid' => 'Total paid / Total sale value',
            'net_cash' => 'Net cash/account due',
        ]
        : [
            'missing' => 'এই ইনভয়েস পাওয়া যায়নি।',
            'back_pos' => 'বিক্রয়ে ফিরে যান',
            'back_history' => 'বিক্রয় ইতিহাসে ফিরে যান',
            'receipt' => 'বিক্রয় রসিদ',
            'invoice' => 'ইনভয়েস',
            'date' => 'তারিখ',
            'customer' => 'ক্রেতা',
            'phone' => 'মোবাইল',
            'note' => 'নোট',
            'code' => 'ক্রেতা আইডি',
            'registered' => 'নিবন্ধিত ক্রেতা',
            'retail' => 'খুচরা ক্রেতা',
            'item' => 'পণ্য',
            'qty' => 'পরিমাণ',
            'price' => 'মূল্য',
            'total' => 'মোট',
            'service' => 'সার্ভিস',
            'engineer' => 'ইঞ্জিনিয়ার',
            'work_bill' => 'ইঞ্জিনিয়ার ও কাজের বিল',
            'work_desc' => 'কাজের বিবরণ',
            'subtotal' => 'মোট বিল',
            'discount' => 'ডিসকাউন্ট',
            'vat' => 'ভ্যাট',
            'rounding' => 'রাউন্ডিং',
            'payable' => 'নিট প্রদেয়',
            'paid' => 'পরিশোধ',
            'due' => 'বর্তমান বকেয়া',
            'change' => 'ফেরত',
            'payments' => 'পরিশোধের হিসাব',
            'reference' => 'রেফারেন্স',
            'previous_balance' => 'সাবেক ডিউ',
            'due_adjustment' => 'সাবেক ডিউ সমন্বয়',
            'new_balance' => 'মোট',
            'print' => 'প্রিন্ট',
            'download' => 'ডাউনলোড',
            'share' => 'শেয়ার',
            'footer' => 'এটি কম্পিউটার তৈরি রসিদ।',
            'share_fail' => 'এই ব্রাউজার সরাসরি শেয়ার করতে পারে না। ডাউনলোড করে পাঠান।',
            'phone_label' => 'ফোন',
            'exchange' => 'পুরাতন ব্যাটারি গ্রহণ / এক্সচেঞ্জ',
            'exchange_rate' => 'এক্সচেঞ্জ রেট',
            'exchange_note' => 'ক্রেতা পুরাতন ব্যাটারি ফেরত দিয়েছেন।',
            'cash_paid' => 'নগদ/অ্যাকাউন্ট পরিশোধ',
            'total_sale' => 'মোট বিক্রয়',
            'total_paid' => 'মোট পরিশোধ / মোট বিক্রয় মূল্য',
            'net_cash' => 'নগদ/অ্যাকাউন্ট বাকি',
        ];
}

function ame_sale_load_invoice_services($db, $sale_id, $fallback_amount = 0.0)
{
    $sale_id = (int)$sale_id;
    $services = [];

    if ($sale_id <= 0 || !is_object($db)) {
        return $services;
    }

    $table = @$db->query("SHOW TABLES LIKE 'sale_services'");
    if ($table && $table->num_rows > 0) {
        $service_stmt = $db->prepare(
            "SELECT
                ss.service_type,
                ss.amount,
                e.name AS engineer_name
             FROM sale_services ss
             LEFT JOIN engineers e
                ON e.id = ss.engineer_id
             WHERE ss.sale_id = ?
             ORDER BY ss.id ASC"
        );

        if ($service_stmt) {
            $service_stmt->bind_param('i', $sale_id);
            $service_stmt->execute();
            $service_result = $service_stmt->get_result();

            while ($service = $service_result->fetch_assoc()) {
                $amount = (float)($service['amount'] ?? 0);
                $type = trim((string)($service['service_type'] ?? ''));
                $engineer = trim((string)($service['engineer_name'] ?? ''));
                if ($amount <= 0 && $type === '' && $engineer === '') {
                    continue;
                }
                $services[] = [
                    'service_type' => $type,
                    'amount' => $amount,
                    'engineer_name' => $engineer,
                ];
            }

            $service_stmt->close();
        }
    }

    if (!$services && (float)$fallback_amount > 0.009) {
        $services[] = [
            'service_type' => 'কাজের বিল',
            'amount' => (float)$fallback_amount,
            'engineer_name' => '',
        ];
    }

    return $services;
}

function ame_invoice_datetime($sale)
{
    foreach ([
        'completed_at',
        'sale_date',
        'created_at',
        'updated_at'
    ] as $field) {
        $value = trim(
            (string)($sale[$field] ?? '')
        );

        if (
            $value === ''
            || $value === '0000-00-00 00:00:00'
        ) {
            continue;
        }

        $timestamp = strtotime($value);

        if ($timestamp) {
            return date(
                'd-m-Y h:i A',
                $timestamp
            );
        }
    }

    return date('d-m-Y h:i A');
}

function ame_sale_persist_balance_book($db, int $sale_id, array $book): void
{
    if (!$db instanceof mysqli || $sale_id <= 0) {
        return;
    }
    $should = !empty($book['reconstructed'])
        || !empty($book['stamp_mismatch'])
        || !empty($book['persist']);
    if (!$should) {
        return;
    }
    $prev = round((float)($book['previous'] ?? 0), 2);
    $after = round((float)($book['total'] ?? 0), 2);
    $st = @$db->prepare('UPDATE sales SET previous_customer_balance = ?, customer_balance_after = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('ddi', $prev, $after, $sale_id);
        $st->execute();
        $st->close();
    }
}

/**
 * এই ইনভয়েসের বিল — সাবেক বকেয়া বাদ।
 * net_payable একসময় সাবেক+বিল জমা হতো; প্রিন্ট সেটা আবার সাবেকের সাথে যোগ করে দ্বিগুণ হতো।
 */
function ame_sale_this_bill(array $sale): float
{
    $total = round((float)($sale['total_bill'] ?? 0), 2);
    $discount = round((float)($sale['discount'] ?? 0), 2);
    $rounding = round((float)($sale['rounding_amount'] ?? 0), 2);
    $service = round((float)($sale['service_amount'] ?? 0), 2);
    $from_parts = round($total - $discount + $rounding, 2);
    if ($service > 0.009) {
        $withSvc = round($from_parts + $service, 2);
        $netGuess = round((float)($sale['net_payable'] ?? 0), 2);
        if (abs($from_parts - $service) > 0.02
            && (abs($netGuess - $withSvc) <= 0.02 || $from_parts + 0.009 < $withSvc && $netGuess + 0.009 >= $withSvc)) {
            $from_parts = $withSvc;
        }
    }
    if ($from_parts > 0.009) {
        return $from_parts;
    }
    $net = round((float)($sale['net_payable'] ?? 0), 2);
    $prev = round((float)($sale['previous_customer_balance'] ?? 0), 2);
    if ($prev > 0.009 && $net > round($prev + 0.009, 2)) {
        $peeled = round($net - $prev, 2);
        if ($peeled > 0.009) {
            return $peeled;
        }
    }
    // সাবেক পাবে (−) আর নিট = সাবেক+বিল হলে বিল আলাদা করি। নিট আগে থেকেই এই-বিল হলে ছুঁই না।
    if ($prev < -0.009 && $net < abs($prev) - 0.009) {
        $peeled = round($net - $prev, 2);
        if ($peeled > 0.009) {
            return $peeled;
        }
    }
    return $net > 0 ? $net : 0.0;
}

function ame_sale_memo_prev_label(float $prev): string
{
    if (function_exists('ame_memo_paper_labels')) {
        return ame_memo_paper_labels(['kind' => 'sale', 'previous' => $prev, 'total' => 0])['previous'];
    }
    if ($prev < -0.009) {
        return 'সাবেক জমা · দোকানের কাছে পাওনা ছিল';
    }
    if ($prev > 0.009) {
        return 'সাবেক · দোকান পাবে';
    }
    return 'সাবেক';
}

function ame_sale_memo_total_label(float $total): string
{
    if (function_exists('ame_memo_paper_labels')) {
        return ame_memo_paper_labels(['kind' => 'sale', 'previous' => 0, 'total' => $total])['total'];
    }
    if ($total < -0.009) {
        return 'কাস্টমার পাবে';
    }
    if ($total > 0.009) {
        return 'দোকান পাবে';
    }
    return 'সর্বমোট';
}

function ame_sale_ledger_refs(int $sale_id, string $invoice = ''): array
{
    $sale_id = (int)$sale_id;
    $refs = [
        'SALE-' . $sale_id,
        'SALE-' . $sale_id . '-PAY',
        'SALE-' . $sale_id . '-EX',
        'DUE-PAY-' . $sale_id,
        'ADV-SALE-' . $sale_id,
    ];
    $invoice = trim($invoice);
    if ($invoice !== '') {
        $refs[] = $invoice;
    }
    return $refs;
}

/**
 * এই সেলের আগে/পরে কাস্টমার লেজারের রানিং ব্যালেন্স।
 * @return array{previous:float,after:float,first_id:int,last_id:int}|null
 */
function ame_sale_ledger_snapshot($db, int $customer_id, int $sale_id, string $invoice = ''): ?array
{
    if (!$db instanceof mysqli || $customer_id <= 0 || $sale_id <= 0) {
        return null;
    }
    $tables = @$db->query("SHOW TABLES LIKE 'customer_ledger'");
    if (!$tables || $tables->num_rows === 0) {
        return null;
    }
    $refs = ame_sale_ledger_refs($sale_id, $invoice);
    $ph = implode(',', array_fill(0, count($refs), '?'));
    $sql = "SELECT MIN(id) AS first_id, MAX(id) AS last_id
            FROM customer_ledger
            WHERE customer_id = ? AND ref_no IN ($ph)";
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $types = 'i' . str_repeat('s', count($refs));
    $stmt->bind_param($types, $customer_id, ...$refs);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $first_id = (int)($row['first_id'] ?? 0);
    $last_id = (int)($row['last_id'] ?? 0);
    if ($first_id <= 0) {
        return null;
    }
    $anchor_stmt = @$db->prepare('SELECT txn_date FROM customer_ledger WHERE id = ? LIMIT 1');
    $anchor_date = '';
    if ($anchor_stmt) {
        $anchor_stmt->bind_param('i', $first_id);
        $anchor_stmt->execute();
        $anchor_row = $anchor_stmt->get_result()->fetch_assoc();
        $anchor_stmt->close();
        $anchor_date = substr((string)($anchor_row['txn_date'] ?? ''), 0, 10);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor_date)) {
        $anchor_date = '1970-01-01';
    }
    $end_stmt = @$db->prepare('SELECT txn_date FROM customer_ledger WHERE id = ? LIMIT 1');
    $end_date = $anchor_date;
    if ($end_stmt) {
        $end_stmt->bind_param('i', $last_id);
        $end_stmt->execute();
        $end_row = $end_stmt->get_result()->fetch_assoc();
        $end_stmt->close();
        $end_raw = substr((string)($end_row['txn_date'] ?? ''), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_raw)) {
            $end_date = $end_raw;
        }
    }
    $prev_stmt = @$db->prepare(
        'SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS net
         FROM customer_ledger
         WHERE customer_id = ? AND (txn_date < ? OR (txn_date = ? AND id < ?))'
    );
    if (!$prev_stmt) {
        return null;
    }
    $prev_stmt->bind_param('issi', $customer_id, $anchor_date, $anchor_date, $first_id);
    $prev_stmt->execute();
    $prev_row = $prev_stmt->get_result()->fetch_assoc();
    $prev_stmt->close();
    $after_stmt = @$db->prepare(
        'SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS net
         FROM customer_ledger
         WHERE customer_id = ? AND (txn_date < ? OR (txn_date = ? AND id <= ?))'
    );
    $sum_prev = round((float)($prev_row['net'] ?? 0), 2);
    $sum_after = $sum_prev;
    if ($after_stmt) {
        $after_stmt->bind_param('issi', $customer_id, $end_date, $end_date, $last_id);
        $after_stmt->execute();
        $after_row = $after_stmt->get_result()->fetch_assoc();
        $after_stmt->close();
        $sum_after = round((float)($after_row['net'] ?? 0), 2);
    }
    $from_running = false;
    $had_prior = false;
    $prev = $sum_prev;
    $after = $sum_after;
    $has_run = false;
    $col = @$db->query("SHOW COLUMNS FROM customer_ledger LIKE 'running_balance'");
    if ($col && $col->num_rows > 0) {
        $has_run = true;
    }
    if ($has_run) {
        $rb_prev = @$db->prepare(
            'SELECT running_balance FROM customer_ledger
             WHERE customer_id = ? AND (txn_date < ? OR (txn_date = ? AND id < ?))
             ORDER BY txn_date DESC, id DESC LIMIT 1'
        );
        if ($rb_prev) {
            $rb_prev->bind_param('issi', $customer_id, $anchor_date, $anchor_date, $first_id);
            $rb_prev->execute();
            $rb_row = $rb_prev->get_result()->fetch_assoc();
            $rb_prev->close();
            if ($rb_row) {
                $prev = round((float)($rb_row['running_balance'] ?? 0), 2);
                $from_running = true;
                $had_prior = true;
            } else {
                $prev = 0.0;
                $from_running = true;
                $had_prior = false;
            }
        }
        $rb_after = @$db->prepare(
            'SELECT running_balance FROM customer_ledger
             WHERE customer_id = ? AND id = ? LIMIT 1'
        );
        if ($rb_after) {
            $rb_after->bind_param('ii', $customer_id, $last_id);
            $rb_after->execute();
            $rb_a = $rb_after->get_result()->fetch_assoc();
            $rb_after->close();
            if ($rb_a) {
                $after = round((float)($rb_a['running_balance'] ?? 0), 2);
            }
        }
    }
    return [
        'previous' => $prev,
        'after' => $after,
        'first_id' => $first_id,
        'last_id' => $last_id,
        'sum_previous' => $sum_prev,
        'sum_after' => $sum_after,
        'from_running' => $from_running,
        'had_prior' => $had_prior,
    ];
}

/**
 * যেকোনো কাস্টমারের চলতি জমা/বকেয়া মেমো পিকে দেয় — JOIN ছাড়াও id দিয়ে।
 */
function ame_sale_hydrate_party_balances($db, array $sale): array
{
    $cid = (int)($sale['customer_id'] ?? 0);
    if (!($db instanceof mysqli) || $cid <= 0) {
        return $sale;
    }
    if (function_exists('ame_supplier_customer_link_boot')) {
        ame_supplier_customer_link_boot($db);
    }
    $stmt = @$db->prepare(
        'SELECT name, phone, receivable_balance, payable_balance, is_supplier, linked_supplier_id
         FROM customers WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        $stmt = @$db->prepare(
            'SELECT name, phone, receivable_balance, payable_balance, is_supplier
             FROM customers WHERE id = ? LIMIT 1'
        );
    }
    if (!$stmt) {
        return $sale;
    }
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return $sale;
    }
    $sale['receivable_balance'] = $row['receivable_balance'];
    $sale['payable_balance'] = $row['payable_balance'];
    $sale['is_supplier'] = $row['is_supplier'] ?? 0;
    if (array_key_exists('linked_supplier_id', $row)) {
        $sale['linked_supplier_id'] = $row['linked_supplier_id'];
    }
    if (trim((string)($sale['customer_name'] ?? '')) === '') {
        $sale['customer_name'] = $row['name'] ?? '';
    }
    if (trim((string)($sale['customer_phone'] ?? $sale['phone'] ?? '')) === '') {
        $sale['customer_phone'] = $row['phone'] ?? '';
        $sale['phone'] = $row['phone'] ?? '';
    }
    $sale['name'] = $sale['name'] ?? ($row['name'] ?? '');
    return $sale;
}

/**
 * এই মেমোর সাবেক — প্রিন্টের সময় মাত্র। সেলস/লেজার টেবিল আপডেট হয় না।
 * running_balance (এই সেলের আগের সারি) SUM(debit)-SUM(credit) থেকে আলাদা হতে পারে:
 * পুরনো সারিতে রানিং টোটাল ডেবিট থাকলে SUM ফুলে যায় (যেমন ৩৩৬৩৪০)।
 * শাহ উজ্জল / মামুন / যেকোনো কাস্টমার — একই পিক।
 */
function ame_sale_choose_memo_balances(array $sale, float $this_bill, $paid, $extra, ?array $ledger_snap): array
{
    if (!function_exists('ame_memo_paper_pick')) {
        require_once dirname(__DIR__) . '/memo_paper.php';
    }
    $fromRun = $ledger_snap !== null && !empty($ledger_snap['from_running']);
    $hadPrior = $ledger_snap !== null && !empty($ledger_snap['had_prior']);
    $runPrev = $fromRun && $ledger_snap !== null ? ($ledger_snap['previous'] ?? null) : null;
    if ($runPrev !== null && abs((float) $runPrev) < 0.009 && !$hadPrior) {
        $runPrev = null;
    }
    $rec = $sale['receivable_balance'] ?? $sale['registered_receivable_balance'] ?? null;
    $pay = $sale['payable_balance'] ?? $sale['registered_payable_balance'] ?? null;
    $sup = $sale['supplier_due'] ?? $sale['total_due'] ?? $sale['registered_supplier_due'] ?? null;
    $party = $sale['party_net'] ?? null;
    $paper = ame_memo_paper_pick('sale', [
        'bill' => $this_bill,
        'paid' => $paid,
        'extra' => $extra,
        'stamp_prev' => array_key_exists('previous_customer_balance', $sale) ? $sale['previous_customer_balance'] : null,
        'stamp_after' => array_key_exists('customer_balance_after', $sale) ? $sale['customer_balance_after'] : null,
        'run_prev' => $runPrev,
        'run_after' => $fromRun && $ledger_snap !== null ? ($ledger_snap['after'] ?? null) : null,
        'sum_prev' => $ledger_snap['sum_previous'] ?? null,
        'sum_after' => $ledger_snap['sum_after'] ?? null,
        'payable' => $pay,
        'receivable' => $rec,
        'supplier_due' => $sup,
        'party_net' => $party,
    ]);
    return [
        'previous' => (float)$paper['previous'],
        'after' => (float)$paper['total'],
        'src' => (string)($paper['src'] ?? 'paper'),
        'paper' => $paper,
    ];
}

function ame_sale_balance_apply($db, array $sale): array
{
    if (!function_exists('ame_balance_book')) {
        require_once __DIR__ . '/../memo_functions.php';
    }
    if (!function_exists('ame_resolve_party_sale_balance')) {
        require_once __DIR__ . '/sale_common.php';
    }
    $this_bill = ame_sale_this_bill($sale);
    $paid = $sale['paid_amount'] ?? 0;
    $extra = $sale['due_adjustment_amount'] ?? $sale['due_adjustment'] ?? 0;
    $prev = array_key_exists('previous_customer_balance', $sale)
        ? $sale['previous_customer_balance']
        : null;
    $after = array_key_exists('customer_balance_after', $sale)
        ? $sale['customer_balance_after']
        : null;

    $sale = ame_sale_hydrate_party_balances($db, $sale);
    $customer_id = (int)($sale['customer_id'] ?? 0);
    $sale_id = (int)($sale['id'] ?? 0);
    if ($db instanceof mysqli && $customer_id > 0) {
        $ledgerFile = dirname(__DIR__, 2) . '/modules/customers/customer_ledger_functions.php';
        if (!function_exists('ame_customer_sale_return_sync') && is_file($ledgerFile)) {
            require_once $ledgerFile;
        }
        if (function_exists('ame_customer_sale_return_sync')) {
            ame_customer_sale_return_sync($db, $customer_id);
        }
        if (function_exists('ame_ledger_recompute_running')) {
            ame_ledger_recompute_running($db, $customer_id);
        }
    }
    $ledger_snap = null;
    if ($db instanceof mysqli && $customer_id > 0 && $sale_id > 0) {
        $ledger_snap = ame_sale_ledger_snapshot(
            $db,
            $customer_id,
            $sale_id,
            (string)($sale['invoice_number'] ?? '')
        );
    }
    if (function_exists('ame_resolve_party_sale_balance')) {
        $partyRow = [
            'receivable_balance' => $sale['receivable_balance'] ?? $sale['registered_receivable_balance'] ?? 0,
            'payable_balance' => $sale['payable_balance'] ?? $sale['registered_payable_balance'] ?? 0,
            'is_supplier' => $sale['is_supplier'] ?? $sale['registered_is_supplier'] ?? 0,
            'phone' => $sale['customer_phone'] ?? $sale['registered_phone'] ?? $sale['phone'] ?? '',
            'name' => $sale['customer_name'] ?? $sale['registered_name'] ?? $sale['name'] ?? '',
            'linked_supplier_id' => $sale['linked_supplier_id'] ?? $sale['registered_linked_supplier_id'] ?? 0,
            'total_due' => $sale['total_due'] ?? $sale['supplier_due'] ?? $sale['registered_supplier_due'] ?? 0,
        ];
        $sale['party_net'] = ame_resolve_party_sale_balance($partyRow, $db instanceof mysqli ? $db : null);
        if (function_exists('ame_party_supplier_due')) {
            $sale['supplier_due'] = ame_party_supplier_due($partyRow, $db instanceof mysqli ? $db : null);
        }
    }
    $picked = ame_sale_choose_memo_balances($sale, $this_bill, $paid, $extra, $ledger_snap);
    $prev = $picked['previous'];
    $after = $picked['after'];
    $src = (string) ($picked['src'] ?? 'paper');

    if ($db instanceof mysqli && $customer_id > 0) {
        $sale = ame_sale_hydrate_party_balances($db, $sale);
    }
    if ($customer_id > 0) {
        $liveRaw = $sale['receivable_balance'] ?? $sale['registered_receivable_balance'] ?? null;
        if ($liveRaw !== null && $liveRaw !== '') {
            $live = round((float) $liveRaw, 2);
            $after = $live;
            $prev = ame_balance_book_previous_amount('sale', $live, (float) $this_bill, (float) $paid, (float) $extra);
            $src = 'live';
        }
    }

    $book = ame_balance_book('sale', $prev, $this_bill, $paid, $after, $extra);
    $book['src'] = $src;
    $book['ledger_aligned'] = $ledger_snap !== null;
    $book['persist'] = false;
    $book['reconstructed'] = false;
    $book['stamp_mismatch'] = false;
    $sale['invoice_bill'] = $this_bill;
    $sale['previous_customer_balance'] = $book['previous'];
    $sale['customer_balance_after'] = $book['total'];
    $sale['balance_book'] = $book;
    return $sale;
}

/**
 * পুরনো সেলে net_payable = সাবেক+বিল ছিল। লেজার ডেবিটও কখনো সেই মোট।
 * একবার চালিয়ে এই-ইনভয়েস বিল ও লেজার ডেবিট মেলানো হয়।
 */
function ame_sale_align_net_with_ledger($db): int
{
    if (!$db instanceof mysqli) {
        return 0;
    }
    $stamp = null;
    $dirs = [];
    if (defined('AME_PRIVATE_DATA_DIR')) {
        $dirs[] = AME_PRIVATE_DATA_DIR;
    }
    $dirs[] = sys_get_temp_dir();
    foreach ($dirs as $dir) {
        if ($dir !== '' && is_dir($dir) && is_writable($dir)) {
            $stamp = rtrim($dir, '/') . '/ame_sale_ledger_print_559.ok';
            break;
        }
    }
    if ($stamp && is_file($stamp)) {
        return 0;
    }

    $changed = 0;
    $has_round = false;
    $col = @$db->query("SHOW COLUMNS FROM sales LIKE 'rounding_amount'");
    if ($col && $col->num_rows > 0) {
        $has_round = true;
    }
    $bill_sql = $has_round
        ? 'ROUND(total_bill - discount + COALESCE(rounding_amount, 0), 2)'
        : 'ROUND(total_bill - discount, 2)';
    $sale_bill_sql = $has_round
        ? 'ROUND(s.total_bill - s.discount + COALESCE(s.rounding_amount, 0), 2)'
        : 'ROUND(s.total_bill - s.discount, 2)';
    $sql = "UPDATE sales
            SET net_payable = {$bill_sql}
            WHERE (status IS NULL OR status = '' OR status IN ('completed','draft'))
              AND {$bill_sql} > 0.009
              AND ABS(net_payable - (COALESCE(previous_customer_balance, 0) + {$bill_sql})) < 0.051";
    if (@$db->query($sql)) {
        $changed += (int)$db->affected_rows;
    }

    $tables = @$db->query("SHOW TABLES LIKE 'customer_ledger'");
    if ($tables && $tables->num_rows > 0) {
        $cust_ids = [];
        $q = "SELECT cl.id, cl.customer_id, cl.debit, {$sale_bill_sql} AS this_bill,
                     COALESCE(s.previous_customer_balance, 0) AS prev_due
              FROM customer_ledger cl
              INNER JOIN sales s ON s.id = CAST(SUBSTRING(cl.ref_no, 6) AS UNSIGNED)
                AND s.customer_id = cl.customer_id
              WHERE cl.ref_no LIKE 'SALE-%'
                AND cl.ref_no NOT LIKE 'SALE-%-%'
                AND cl.debit > 0.009
                AND {$sale_bill_sql} > 0.009
                AND ABS(cl.debit - (COALESCE(s.previous_customer_balance, 0) + {$sale_bill_sql})) < 0.051";
        $res = @$db->query($q);
        if ($res) {
            $upd = @$db->prepare('UPDATE customer_ledger SET debit = ? WHERE id = ?');
            while ($row = $res->fetch_assoc()) {
                $lid = (int)$row['id'];
                $bill = round((float)$row['this_bill'], 2);
                $cid = (int)$row['customer_id'];
                if ($upd && $lid > 0 && $bill > 0.009) {
                    $upd->bind_param('di', $bill, $lid);
                    if ($upd->execute()) {
                        $changed++;
                        $cust_ids[$cid] = true;
                    }
                }
            }
            if ($upd) {
                $upd->close();
            }
        }
        if ($cust_ids !== []) {
            if (!function_exists('ame_ledger_recompute_running')) {
                $fn = dirname(__DIR__, 2) . '/modules/customers/customer_ledger_functions.php';
                if (is_file($fn)) {
                    require_once $fn;
                }
            }
            foreach (array_keys($cust_ids) as $cid) {
                if (function_exists('ame_ledger_recompute_running')) {
                    ame_ledger_recompute_running($db, (int)$cid);
                }
                if (function_exists('ame_customer_balance_resync_from_ledger')) {
                    ame_customer_balance_resync_from_ledger($db, (int)$cid);
                }
            }
        }
    }

    if ($stamp) {
        @file_put_contents($stamp, (string)time());
    }
    return $changed;
}

function ame_invoice_load($db, $sale_id)
{
    $sale_id = (int)$sale_id;

    if ($sale_id <= 0) {
        return null;
    }

    if ($db instanceof mysqli && function_exists('ame_supplier_customer_link_boot')) {
        ame_supplier_customer_link_boot($db);
    }

    $stmt = $db->prepare(
        "SELECT
            s.*,
            a.account_name,
            c.name AS registered_name,
            c.phone AS registered_phone,
            c.customer_code AS registered_code,
            c.is_supplier AS registered_is_supplier,
            c.linked_supplier_id AS registered_linked_supplier_id,
            c.payable_balance AS registered_payable_balance,
            c.receivable_balance AS registered_receivable_balance
         FROM sales s
         LEFT JOIN accounts a
            ON a.id = s.account_id
         LEFT JOIN customers c
            ON c.id = s.customer_id
         WHERE s.id = ?
           AND s.status IN ('completed', 'draft')
         LIMIT 1"
    );
    if (!$stmt) {
        $stmt = $db->prepare(
            "SELECT
                s.*,
                a.account_name,
                c.name AS registered_name,
                c.phone AS registered_phone,
                c.customer_code AS registered_code,
                c.is_supplier AS registered_is_supplier
             FROM sales s
             LEFT JOIN accounts a
                ON a.id = s.account_id
             LEFT JOIN customers c
                ON c.id = s.customer_id
             WHERE s.id = ?
               AND s.status IN ('completed', 'draft')
             LIMIT 1"
        );
    }

    $stmt->bind_param('i', $sale_id);
    $stmt->execute();

    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sale) {
        return null;
    }

    $item_stmt = $db->prepare(
        "SELECT
            si.qty,
            si.sale_price,
            COALESCE(NULLIF(si.item_name, ''), p.name, 'পণ্য') AS name
         FROM sale_items si
         LEFT JOIN products p
            ON p.id = si.product_id
         WHERE si.sale_id = ?
         ORDER BY si.id ASC"
    );

    $item_stmt->bind_param('i', $sale_id);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();

    $items = [];

    while ($item = $item_result->fetch_assoc()) {
        $qty = (float)$item['qty'];
        $price = (float)$item['sale_price'];

        $items[] = [
            'name' => (string)$item['name'],
            'qty' => $qty,
            'sale_price' => $price,
            'line_total' => round($qty * $price, 2)
        ];
    }

    $item_stmt->close();

    $services = ame_sale_load_invoice_services(
        $db,
        $sale_id,
        (float)($sale['service_amount'] ?? 0)
    );

    $include_planned = strtolower((string)($sale['status'] ?? '')) === 'draft';
    $payment_sql = "
        SELECT
            sp.amount,
            sp.reference_number,
            a.account_name
         FROM sale_payments sp
         INNER JOIN accounts a
            ON a.id = sp.account_id
         WHERE sp.sale_id = ?
    ";
    if (!$include_planned) {
        $payment_sql .= " AND sp.payment_status <> 'planned'";
    }
    $payment_sql .= ' ORDER BY sp.id ASC';
    $payment_stmt = $db->prepare($payment_sql);

    $payment_stmt->bind_param('i', $sale_id);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();

    $payments = [];

    while ($payment = $payment_result->fetch_assoc()) {
        $payments[] = [
            'account_name' =>
                (string)$payment['account_name'],
            'amount' => (float)$payment['amount'],
            'reference_number' =>
                (string)(
                    $payment['reference_number'] ?? ''
                )
        ];
    }

    $payment_stmt->close();

    if (!function_exists('ame_sale_load_exchange_items')) {
        require_once __DIR__ . '/sale_exchange.php';
    }
    $exchange_items = ame_sale_load_exchange_items($db, $sale_id);
    $exchange_amount = round((float)($sale['exchange_amount'] ?? 0), 2);
    if ($exchange_amount < 0.009 && $exchange_items) {
        $exchange_amount = 0.0;
        foreach ($exchange_items as $ex_row) {
            $exchange_amount += (float)$ex_row['line_total'];
        }
        $exchange_amount = round($exchange_amount, 2);
    }
    $cash_paid_amount = round((float)($sale['cash_paid_amount'] ?? 0), 2);
    if ($cash_paid_amount < 0.009 && $exchange_amount > 0.009) {
        $cash_paid_amount = max(
            0,
            round((float)($sale['paid_amount'] ?? 0) - $exchange_amount, 2)
        );
    } elseif ($cash_paid_amount < 0.009) {
        $cash_paid_amount = round((float)($sale['paid_amount'] ?? 0), 2);
    }

    if (
        !$payments
        && (float)$sale['paid_amount'] > 0
        && !empty($sale['account_name'])
    ) {
        $payments[] = [
            'account_name' =>
                (string)$sale['account_name'],
            'amount' => (float)$sale['paid_amount'],
            'reference_number' => ''
        ];
    }

    $registered = !empty($sale['customer_id']);
    $guest_name = trim(
        (string)($sale['guest_customer_name'] ?? '')
    );
    $guest_phone = trim(
        (string)($sale['guest_customer_phone'] ?? '')
    );
    $guest_note = trim(
        (string)($sale['guest_customer_note'] ?? '')
    );

    $invoice_number = trim(
        (string)($sale['invoice_number'] ?? '')
    );

    if (
        $invoice_number === ''
        || strpos($invoice_number, 'TMP-') === 0
    ) {
        $invoice_number = 'INV-' . $sale_id;
    }

    $customer_name = $registered
        ? trim((string)($sale['registered_name'] ?? ''))
        : $guest_name;

    if ($customer_name === '') {
        $customer_name = $registered
            ? 'নিবন্ধিত ক্রেতা'
            : 'খুচরা ক্রেতা';
    }

    $customer_phone = $registered
        ? trim((string)($sale['registered_phone'] ?? ''))
        : $guest_phone;

    if (!isset($sale['payable_balance']) && array_key_exists('registered_payable_balance', $sale)) {
        $sale['payable_balance'] = $sale['registered_payable_balance'];
    }
    if (!isset($sale['receivable_balance']) && array_key_exists('registered_receivable_balance', $sale)) {
        $sale['receivable_balance'] = $sale['registered_receivable_balance'];
    }
    if (!isset($sale['linked_supplier_id']) && array_key_exists('registered_linked_supplier_id', $sale)) {
        $sale['linked_supplier_id'] = $sale['registered_linked_supplier_id'];
    }
    if (!isset($sale['is_supplier']) && array_key_exists('registered_is_supplier', $sale)) {
        $sale['is_supplier'] = $sale['registered_is_supplier'];
    }
    $sale = ame_sale_balance_apply($db, $sale);
    $book = $sale['balance_book'];

    return [
        'sale' => $sale,
        'items' => $items,
        'services' => $services,
        'payments' => $payments,
        'invoice_number' => $invoice_number,
        'sold_at' => ame_invoice_datetime($sale),
        'is_registered' => $registered,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'customer_note' => $registered ? '' : $guest_note,
        'customer_code' => $registered
            ? trim(
                (string)($sale['registered_code'] ?? '')
            )
            : '',
        'is_supplier' => !empty($sale['registered_is_supplier']),
        'book' => $book,
        'previous_balance' =>
            (float)$book['previous'],
        'due_adjustment' =>
            (float)($sale['due_adjustment_amount'] ?? 0),
        'customer_balance_after' =>
            (float)$book['total'],
        'change_amount' =>
            (float)($sale['change_amount'] ?? 0),
        'due_amount' =>
            (float)($sale['due_amount'] ?? 0),
        'paid_amount' =>
            (float)($sale['paid_amount'] ?? 0),
        'net_payable' =>
            (float)($book['current'] ?? $sale['invoice_bill'] ?? ame_sale_this_bill($sale)),
        'invoice_bill' =>
            (float)($book['current'] ?? $sale['invoice_bill'] ?? ame_sale_this_bill($sale)),
        'total_bill' =>
            (float)($sale['total_bill'] ?? 0),
        'discount' =>
            (float)($sale['discount'] ?? 0),
        'vat_percent' =>
            (float)($sale['vat_percent'] ?? 0),
        'vat_amount' =>
            (float)($sale['vat_amount'] ?? 0),
        'service_amount' =>
            (float)($sale['service_amount'] ?? 0),
        'rounding_amount' =>
            (float)($sale['rounding_amount'] ?? 0),
        'exchange_items' => $exchange_items,
        'exchange_amount' => $exchange_amount,
        'cash_paid_amount' => $cash_paid_amount,
        'created_by' => (int)($sale['created_by'] ?? 0),
    ];
}