<?php

/**
 * অতিরিক্ত জমা সাবেক বকেয়া থেকে কাটে।
 * চেকবক্স থাকলে পুরো অতিরিক্ত টাকা (বকেয়ার বেশি হলে অগ্রিম)।
 * চেকবক্স না থাকলেও সাবেক বকেয়া থাকলে সেই বকেয়া পর্যন্ত নিজে থেকে কাটে।
 */
function ame_sale_resolve_due_adjustment($customer_id, float $previous_balance, float $extra_payment, bool $adjust_requested): float
{
    $extra = round(max(0, $extra_payment), 2);
    if ($customer_id === null || (int)$customer_id <= 0 || $extra <= 0.009) {
        return 0.0;
    }
    if ($adjust_requested) {
        return $extra;
    }
    $open = round(max(0, $previous_balance), 2);
    if ($open <= 0.009) {
        return 0.0;
    }
    return round(min($extra, $open), 2);
}

function ame_sale_customer_ledger(
    $db,
    $sale_id,
    $customer_id,
    $grand_total,
    $paid_for_sale,
    $due_adjustment,
    $is_english,
    $exchange_amount = 0,
    $previous_balance = null
) {
    if ($customer_id === null) {
        return;
    }

    $reference = 'SALE-' . $sale_id;
    $exchange_amount = round((float)$exchange_amount, 2);
    $cash_credit = round((float)$paid_for_sale, 2);

    /*
     * সম্পূর্ণ Invoice Customer-এর Debit হবে।
     */
    add_ledger_entry(
        $db,
        $customer_id,
        $is_english ? 'Credit Sale' : 'বিক্রয়',
        $reference,
        $is_english
            ? 'Sales invoice #' . $sale_id
            : 'বিক্রয় ইনভয়েস #' . $sale_id,
        $grand_total,
        0
    );

    if ($exchange_amount > 0) {
        add_ledger_entry(
            $db,
            $customer_id,
            $is_english ? 'Battery Exchange' : 'ব্যাটারি এক্সচেঞ্জ',
            $reference,
            $is_english
                ? 'Old battery exchange against invoice #' . $sale_id
                : 'ইনভয়েস #' . $sale_id . '-এর পুরাতন ব্যাটারি এক্সচেঞ্জ',
            0,
            $exchange_amount
        );
    }

    /*
     * বর্তমান Invoice-এর Cash/Bank Payment Credit হবে।
     */
    if ($cash_credit > 0) {
        add_ledger_entry(
            $db,
            $customer_id,
            $is_english
                ? 'Sale Payment'
                : 'বিক্রয় পেমেন্ট',
            $reference,
            $is_english
                ? 'Deposit against invoice #' . $sale_id
                : 'ইনভয়েস #' . $sale_id . '-এ জমা',
            0,
            $cash_credit
        );
    }

    /*
     * অতিরিক্ত টাকা আগে পুরনো বাকি কাটে। বাকি না থাকলে অ্যাকাউন্টে জমা হয়।
     * স্টেটমেন্ট এই সারি আর বিক্রয় পেমেন্টকে এক জমায় দেখায়।
     */
    if ($due_adjustment > 0) {
        $open_due = $previous_balance === null
            ? $due_adjustment
            : max(0, round((float)$previous_balance, 2));
        $due_cut = min($due_adjustment, $open_due);
        $account_credit = round($due_adjustment - $due_cut, 2);
        if ($due_cut > 0.009) {
            add_ledger_entry(
                $db,
                $customer_id,
                $is_english
                    ? 'Previous Due Payment'
                    : 'পূর্বের বকেয়া',
                'DUE-PAY-' . $sale_id,
                $is_english
                    ? 'Extra payment cut from previous due'
                    : 'অতিরিক্ত টাকা সাবেক বকেয়া থেকে কাটা হয়েছে',
                0,
                $due_cut
            );
        }
        if ($account_credit > 0.009) {
            add_ledger_entry(
                $db,
                $customer_id,
                $is_english
                    ? 'Customer Advance'
                    : 'অ্যাকাউন্টে জমা',
                'ADV-SALE-' . $sale_id,
                $is_english
                    ? 'Extra payment kept on the customer account'
                    : 'অতিরিক্ত টাকা কাস্টমারের অ্যাকাউন্টে যোগ হয়েছে',
                0,
                $account_credit
            );
        }
    }
}

function ame_sale_post_payments(
    $db,
    $sale_id,
    $payments,
    $deposit_amount,
    $user_id,
    $is_english
) {
    $remaining = round(
        $deposit_amount,
        2
    );

    foreach ($payments as $payment) {
        if ($remaining <= 0) {
            break;
        }

        $account_id = (int)$payment['account_id'];

        $posted_amount = min(
            (float)$payment['amount'],
            $remaining
        );

        if ($posted_amount <= 0) {
            continue;
        }

        /*
         * প্রতিটি Account আলাদাভাবে Credit হবে।
         */
        account_credit(
            $account_id,
            $posted_amount,
            'sale',
            $sale_id,
            ($is_english
                ? 'Split payment — Invoice #'
                : 'বিভক্ত পরিশোধ — ইনভয়েস #')
                . $sale_id
        );

        $reference_number = (string)(
            $payment['reference_number'] ?? ''
        );

        $note = $is_english
            ? 'Payment received against sale'
            : 'বিক্রয়ের বিপরীতে টাকা গ্রহণ';

        /*
         * বিভক্ত Payment-এর বিস্তারিত সংরক্ষণ।
         */
        $stmt = $db->prepare(
            "INSERT INTO sale_payments (
                sale_id,
                account_id,
                amount,
                reference_number,
                payment_status,
                note,
                created_by
             ) VALUES (
                ?, ?, ?, ?, 'posted', ?, ?
             )"
        );

        $stmt->bind_param(
            'iidssi',
            $sale_id,
            $account_id,
            $posted_amount,
            $reference_number,
            $note,
            $user_id
        );

        $stmt->execute();
        $stmt->close();

        $remaining = round(
            $remaining - $posted_amount,
            2
        );
    }

    if ($remaining > 0.009) {
        throw new RuntimeException(
            $is_english
                ? 'Payment distribution is incomplete.'
                : 'পেমেন্টের সম্পূর্ণ টাকা বণ্টন হয়নি।'
        );
    }
}

function ame_sale_store_service(
    $db,
    $sale_id,
    $engineer_id,
    $service_type,
    $service_amount
) {
    if ($service_amount <= 0) {
        return 0;
    }

    $eng_name = '';
    if ($engineer_id > 0) {
        $ns = $db->prepare('SELECT name FROM engineers WHERE id = ? LIMIT 1');
        if ($ns) {
            $ns->bind_param('i', $engineer_id);
            $ns->execute();
            $nrow = $ns->get_result()->fetch_assoc();
            $ns->close();
            $eng_name = trim((string)($nrow['name'] ?? ''));
        }
    }

    $has_name = false;
    $col = @$db->query("SHOW COLUMNS FROM sale_services LIKE 'engineer_name'");
    if ($col && $col->fetch_assoc()) {
        $has_name = true;
    }

    if ($has_name) {
        $stmt = $db->prepare(
            'INSERT INTO sale_services (
                sale_id,
                engineer_id,
                service_type,
                amount,
                engineer_name
             ) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisds',
            $sale_id,
            $engineer_id,
            $service_type,
            $service_amount,
            $eng_name
        );
    } else {
        $stmt = $db->prepare(
            'INSERT INTO sale_services (
                sale_id,
                engineer_id,
                service_type,
                amount
             ) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisd',
            $sale_id,
            $engineer_id,
            $service_type,
            $service_amount
        );
    }

    $stmt->execute();
    $service_id = (int)$db->insert_id;
    $stmt->close();
    return $service_id;
}

/**
 * কার্ট লাইনের নেট (ছাড় সহ) — POS ameLineMath এর মতো।
 */
function ame_sale_cart_line_net(array $row): float
{
    $price = max(0, (float)($row['current_price'] ?? $row['sale_price'] ?? $row['price'] ?? 0));
    $qty = max(0, (float)($row['qty'] ?? $row['quantity'] ?? 0));
    $pct = min(100, max(0, (float)($row['discount_pct'] ?? $row['discount'] ?? 0)));
    if ($qty <= 0.009) {
        return 0.0;
    }
    $gross = $price * $qty;
    return round($gross - ($gross * $pct / 100), 2);
}

/**
 * পণ্য কোড EMP-#### → সক্রিয় ইঞ্জিনিয়ার id (employee_code মিল)।
 */
function ame_sale_engineer_id_by_emp_sku($db, string $sku): int
{
    if (!($db instanceof mysqli)) {
        return 0;
    }
    $sku = strtoupper(trim($sku));
    if ($sku === '' || !preg_match('/^EMP-\d+$/', $sku)) {
        return 0;
    }

    $employeeId = 0;
    $empTable = @$db->query("SHOW TABLES LIKE 'employees'");
    if ($empTable && $empTable->num_rows > 0) {
        $stmt = @$db->prepare(
            'SELECT id FROM employees WHERE UPPER(TRIM(employee_code)) = ? LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('s', $sku);
            $stmt->execute();
            $employeeId = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
            $stmt->close();
        }
    }

    $engTable = @$db->query("SHOW TABLES LIKE 'engineers'");
    if (!$engTable || $engTable->num_rows === 0) {
        return 0;
    }

    $purged = " AND (purged_at IS NULL OR purged_at = '')";
    $probe = @$db->query("SHOW COLUMNS FROM engineers LIKE 'purged_at'");
    if (!$probe || !$probe->fetch_assoc()) {
        $purged = '';
    }

    if ($employeeId > 0) {
        $stmt = @$db->prepare(
            'SELECT id FROM engineers WHERE employee_id = ?' . $purged . ' ORDER BY id ASC LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('i', $employeeId);
            $stmt->execute();
            $engId = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
            $stmt->close();
            if ($engId > 0) {
                return $engId;
            }
        }
    }

    return 0;
}

/**
 * POS কার্টে EMP-#### / কাজের বিল পণ্য থাকলে সার্ভিস লাইনে তোলে (প্যানেল ছাড়া সাবমিট)।
 *
 * @param list<array<string,mixed>> $existing
 * @return list<array<string,mixed>>
 */
function ame_sale_merge_cart_work_bills($db, $cart, array $existing, $is_english): array
{
    if (!is_array($cart) || $cart === []) {
        return $existing;
    }

    $fingerprints = [];
    foreach ($existing as $line) {
        $eng = (int)($line['engineer_id'] ?? 0);
        $amt = round((float)($line['amount'] ?? 0), 2);
        if ($eng > 0 && $amt > 0.009) {
            $fingerprints[$eng . ':' . number_format($amt, 2, '.', '')] = true;
        }
    }

    foreach ($cart as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sku = strtoupper(trim((string)($row['sku'] ?? $row['product_code'] ?? $row['code'] ?? '')));
        $name = trim((string)($row['name'] ?? $row['item_name'] ?? ''));
        if ($sku === '' || !preg_match('/^EMP-\d+$/', $sku)) {
            continue;
        }

        $amount = ame_sale_cart_line_net($row);
        if ($amount <= 0.009) {
            continue;
        }

        $engineer_id = ame_sale_engineer_id_by_emp_sku($db, $sku);
        if ($engineer_id <= 0) {
            ame_sale_fail(
                $is_english,
                'কার্টের ' . $sku . ' পণ্যের সাথে কোনো সক্রিয় ইঞ্জিনিয়ার মিলছে না। «ইঞ্জিনিয়ার ও মেরামত» থেকে বিল দিন।',
                'Cart item ' . $sku . ' is not linked to an active engineer. Use Engineer & Repair billing.'
            );
        }

        $fp = $engineer_id . ':' . number_format($amount, 2, '.', '');
        if (isset($fingerprints[$fp])) {
            continue;
        }
        $fingerprints[$fp] = true;

        $service_type = $name !== '' ? substr($name, 0, 255) : 'কাজের বিল';
        $existing[] = [
            'engineer_id' => $engineer_id,
            'service_type' => $service_type,
            'amount' => $amount,
            'engineer_query' => substr($sku, 0, 150),
        ];
    }

    return $existing;
}

function ame_sale_parse_services($data, $is_english, $db = null)
{
    $lines = [];

    if (!empty($data['services']) && is_array($data['services'])) {
        foreach ($data['services'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $engineer_id = (int)($row['engineer_id'] ?? 0);
            $service_type = substr(
                trim((string)($row['service_type'] ?? '')),
                0,
                255
            );
            $amount = max(
                0,
                round((float)($row['amount'] ?? 0), 2)
            );

            if ($engineer_id <= 0 && $service_type === '' && $amount <= 0) {
                continue;
            }

            if ($amount <= 0) {
                ame_sale_fail(
                    $is_english,
                    'ইঞ্জিনিয়ারের কাজের বিলে টাকা দিন।',
                    'Enter the engineer work bill amount.'
                );
            }

            if ($service_type === '') {
                $service_type = 'কাজের বিল';
            }

            $lines[] = [
                'engineer_id' => $engineer_id,
                'service_type' => $service_type,
                'amount' => $amount,
                'engineer_query' => substr(trim((string)($row['engineer_query'] ?? '')), 0, 150),
            ];
        }
    } else {
        $amount = max(
            0,
            round((float)($data['service_amount'] ?? 0), 2)
        );
        $engineer_id = (int)($data['engineer_id'] ?? 0);
        $service_type = substr(
            trim((string)($data['service_type'] ?? '')),
            0,
            255
        );

        if ($amount > 0) {
            if ($service_type === '') {
                $service_type = 'কাজের বিল';
            }

            $lines[] = [
                'engineer_id' => $engineer_id,
                'service_type' => $service_type,
                'amount' => $amount,
                'engineer_query' => substr(trim((string)($data['engineer_query'] ?? '')), 0, 150),
            ];
        }
    }

    if ($db instanceof mysqli && !empty($data['cart']) && is_array($data['cart'])) {
        $lines = ame_sale_merge_cart_work_bills($db, $data['cart'], $lines, $is_english);
    }

    $total = 0.0;
    foreach ($lines as $line) {
        $total += (float)$line['amount'];
    }

    return [
        'lines' => $lines,
        'total' => round($total, 2),
    ];
}

function ame_sale_assert_service_engineers($db, $lines, $is_english)
{
    if (!is_array($lines) || $lines === []) {
        return;
    }

    $table = @$db->query("SHOW TABLES LIKE 'engineers'");
    if (!$table || $table->num_rows === 0) {
        ame_sale_fail(
            $is_english,
            'ইঞ্জিনিয়ার তালিকা পাওয়া যায়নি।',
            'The engineer list is not available.'
        );
    }

    $stmt = $db->prepare(
        "SELECT id
         FROM engineers
         WHERE id = ?
           AND (purged_at IS NULL OR purged_at = '')
         LIMIT 1"
    );

    foreach ($lines as $line) {
        $engineer_id = (int)($line['engineer_id'] ?? 0);
        if ($engineer_id <= 0) {
            continue;
        }
        $stmt->bind_param('i', $engineer_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows === 1;

        if (!$exists) {
            $stmt->close();
            ame_sale_fail(
                $is_english,
                'নির্বাচিত ইঞ্জিনিয়ার সক্রিয় নয়।',
                'The selected engineer is inactive.'
            );
        }
    }

    $stmt->close();
}

function ame_sale_replace_services($db, $sale_id, $lines)
{
    if (!is_array($lines)) {
        $lines = [];
    }
    if (!function_exists('ame_engineer_statement_resolve_lines')) {
        $statementPost = dirname(__DIR__) . '/engineer_statement_post.php';
        if (is_file($statementPost)) {
            require_once $statementPost;
        }
    }
    if (function_exists('ame_engineer_statement_resolve_lines')) {
        $lines = ame_engineer_statement_resolve_lines($db, $lines);
    }

    $delete = $db->prepare(
        'DELETE FROM sale_services WHERE sale_id = ?'
    );
    if (!$delete) {
        throw new RuntimeException(
            'sale_services table is missing: ' . $db->error
        );
    }
    $delete->bind_param('i', $sale_id);
    $delete->execute();
    $delete->close();

    $stored = [];
    foreach ($lines as $line) {
        $service_id = ame_sale_store_service(
            $db,
            $sale_id,
            (int)($line['engineer_id'] ?? 0),
            (string)($line['service_type'] ?? ''),
            (float)($line['amount'] ?? 0)
        );
        if ($service_id > 0) {
            $line['sale_service_id'] = $service_id;
            $stored[] = $line;
        }
    }
    if (function_exists('ame_engineer_statement_post_stored')) {
        ame_engineer_statement_post_stored($db, (int)$sale_id, $stored);
    }
    return $stored;
}

/** দ্রুত ডুপ্লিকেট সাবমিট কনফার্ম উইন্ডো (সেকেন্ড) */
function ame_rapid_resubmit_window_seconds(): int
{
    return 15;
}

function ame_sale_norm_service_type(string $type): string
{
    $t = mb_strtolower(trim($type));
    if ($t === '' || $t === 'কাজের বিল' || $t === 'work bill') {
        return '__work__';
    }
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

    return $t;
}

function ame_sale_service_types_match(string $a, string $b): bool
{
    $na = ame_sale_norm_service_type($a);
    $nb = ame_sale_norm_service_type($b);
    if ($na === $nb) {
        return true;
    }
    if ($na === '__work__' || $nb === '__work__') {
        return $na === '__work__' && $nb === '__work__';
    }

    return $na === $nb
        || str_contains($na, $nb)
        || str_contains($nb, $na);
}

function ame_sale_rapid_event_seconds(string $dt): int
{
    $ts = strtotime($dt);
    if ($ts === false || $ts <= 0) {
        return 999;
    }

    return max(0, time() - $ts);
}

/**
 * কার্টের পণ্য নাম যেগুলো গত কয়েক সেকেন্ডে একই ব্যবহারকারী আবার বিক্রি করেছে।
 *
 * @return list<array{product_name:string,amount:float,qty:float,sale_id:int,invoice_number:string,seconds_ago:int}>
 */
function ame_sale_rapid_product_warnings(mysqli $db, int $userId, array $cart, ?int $windowSeconds = null): array
{
    if ($windowSeconds === null) {
        $windowSeconds = ame_rapid_resubmit_window_seconds();
    }
    if ($userId <= 0 || $windowSeconds < 1) {
        return [];
    }
    $cart = is_array($cart) ? $cart : [];
    $names = [];
    foreach ($cart as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row['name'] ?? $row['item_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $key = mb_strtolower($name);
        if (isset($names[$key])) {
            continue;
        }
        $names[$key] = [
            'product_name' => $name,
            'amount' => round((float)($row['price'] ?? $row['unit_price'] ?? $row['sell_price'] ?? 0), 2),
            'qty' => round((float)($row['qty'] ?? $row['quantity'] ?? 1), 3),
        ];
    }
    if ($names === []) {
        return [];
    }
    $warnings = [];
    $winSql = function_exists('ame_sql_interval_seconds') ? ame_sql_interval_seconds($windowSeconds) : max(1, min(3600, (int)$windowSeconds));
    $stmt = @$db->prepare(
        "SELECT s.id AS sale_id, s.invoice_number,
                COALESCE(NULLIF(s.completed_at, '0000-00-00 00:00:00'), s.created_at) AS event_at,
                COALESCE(NULLIF(TRIM(si.item_name), ''), p.name, '') AS product_name,
                si.unit_price, si.quantity
         FROM sales s
         INNER JOIN sale_items si ON si.sale_id = s.id
         LEFT JOIN products p ON p.id = si.product_id
         WHERE s.created_by = ? AND s.status = 'completed'
           AND COALESCE(NULLIF(s.completed_at, '0000-00-00 00:00:00'), s.created_at)
               >= (NOW() - INTERVAL {$winSql} SECOND)
         ORDER BY s.id DESC, si.id ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $seen = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $pname = trim((string)($r['product_name'] ?? ''));
        $key = mb_strtolower($pname);
        if ($pname === '' || !isset($names[$key]) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $sec = ame_sale_rapid_event_seconds((string)($r['event_at'] ?? ''));
        $warnings[] = [
            'product_name' => $names[$key]['product_name'],
            'amount' => round((float)($r['unit_price'] ?? $names[$key]['amount']), 2),
            'qty' => round((float)($r['quantity'] ?? $names[$key]['qty']), 3),
            'sale_id' => (int)($r['sale_id'] ?? 0),
            'invoice_number' => (string)($r['invoice_number'] ?? ''),
            'seconds_ago' => $sec,
        ];
    }
    $stmt->close();

    return array_values(array_filter($warnings, static function (array $w) use ($windowSeconds): bool {
        return (int)($w['seconds_ago'] ?? 999) <= $windowSeconds;
    }));
}

/**
 * @param list<array<string,mixed>> $lines
 * @return list<array{engineer_id:int,worker_name:string,service_type:string,amount:float,sale_id:int,invoice_number:string,seconds_ago:int}>
 */
function ame_sale_rapid_service_warnings(mysqli $db, int $userId, array $lines, ?int $windowSeconds = null): array
{
    if ($windowSeconds === null) {
        $windowSeconds = ame_rapid_resubmit_window_seconds();
    }
    if ($userId <= 0 || $windowSeconds < 1 || $lines === []) {
        return [];
    }
    $want = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $eng = (int)($line['engineer_id'] ?? 0);
        $amt = round((float)($line['amount'] ?? 0), 2);
        $stype = trim((string)($line['service_type'] ?? ''));
        if ($eng <= 0 || $amt <= 0.009) {
            continue;
        }
        $want[] = ['engineer_id' => $eng, 'amount' => $amt, 'service_type' => $stype !== '' ? $stype : 'কাজের বিল'];
    }
    if ($want === []) {
        return [];
    }
    $winSql = function_exists('ame_sql_interval_seconds') ? ame_sql_interval_seconds($windowSeconds) : max(1, min(3600, (int)$windowSeconds));
    $stmt = @$db->prepare(
        "SELECT s.id AS sale_id, s.invoice_number,
                COALESCE(NULLIF(s.completed_at, '0000-00-00 00:00:00'), s.created_at) AS event_at,
                ss.engineer_id, ss.service_type, ss.amount, e.name AS engineer_name
         FROM sales s
         INNER JOIN sale_services ss ON ss.sale_id = s.id
         LEFT JOIN engineers e ON e.id = ss.engineer_id
         WHERE s.created_by = ? AND s.status = 'completed'
           AND COALESCE(NULLIF(s.completed_at, '0000-00-00 00:00:00'), s.created_at)
               >= (NOW() - INTERVAL {$winSql} SECOND)
         ORDER BY s.id DESC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $warnings = [];
    while ($res && ($r = $res->fetch_assoc())) {
        foreach ($want as $w) {
            if ((int)$r['engineer_id'] !== $w['engineer_id']) {
                continue;
            }
            if (abs((float)$r['amount'] - $w['amount']) > 0.009) {
                continue;
            }
            $rType = trim((string)($r['service_type'] ?? ''));
            $wType = $w['service_type'];
            if (!ame_sale_service_types_match($rType, $wType)) {
                continue;
            }
            $sec = ame_sale_rapid_event_seconds((string)($r['event_at'] ?? ''));
            $warnings[] = [
                'engineer_id' => (int)$r['engineer_id'],
                'worker_name' => trim((string)($r['engineer_name'] ?? 'ইঞ্জিনিয়ার')),
                'service_type' => $wType !== '' ? $wType : ($rType !== '' ? $rType : 'কাজের বিল'),
                'amount' => $w['amount'],
                'sale_id' => (int)($r['sale_id'] ?? 0),
                'invoice_number' => (string)($r['invoice_number'] ?? ''),
                'seconds_ago' => $sec,
            ];
            break 2;
        }
    }
    $stmt->close();

    return array_values(array_filter($warnings, static function (array $w) use ($windowSeconds): bool {
        return (int)($w['seconds_ago'] ?? 999) <= $windowSeconds;
    }));
}

function ame_sale_rapid_service_message(array $warning, bool $isEnglish, ?int $windowSeconds = null): string
{
    if ($windowSeconds === null) {
        $windowSeconds = ame_rapid_resubmit_window_seconds();
    }
    $name = (string)($warning['worker_name'] ?? 'কর্মী');
    $svc = (string)($warning['service_type'] ?? 'কাজের বিল');
    $amt = number_format((float)($warning['amount'] ?? 0), 2);
    $memo = trim((string)($warning['invoice_number'] ?? ''));
    if ($memo === '' && (int)($warning['sale_id'] ?? 0) > 0) {
        $memo = '#' . (int)$warning['sale_id'];
    }
    $sec = (int)($warning['seconds_ago'] ?? $windowSeconds);
    if ($sec > $windowSeconds) {
        $sec = $windowSeconds;
    }
    if ($isEnglish) {
        return "⚠️ Rapid resubmit ({$sec}s ago)\nWorker: {$name}\nWork: «{$svc}» · ৳{$amt}\nMemo {$memo}\n\nSubmit again, or Cancel?";
    }

    return "⚠️ দ্রুত দ্বিতীয় সাবমিট (প্রায় {$sec} সেকেন্ড আগে)\n"
        . "কর্মী/ইঞ্জিনিয়ার: {$name}\n"
        . "কাজ: «{$svc}»\n"
        . "টাকা: ৳{$amt}\n"
        . "আগের বিক্রয়: মেমো {$memo}\n\n"
        . "এখন আবার সাবমিট করবেন নাকি বাতিল করবেন?";
}

function ame_sale_rapid_confirm_message(array $warning, bool $isEnglish, ?int $windowSeconds = null): string
{
    if ($windowSeconds === null) {
        $windowSeconds = ame_rapid_resubmit_window_seconds();
    }
    $name = (string)($warning['product_name'] ?? 'পণ্য');
    $amt = number_format((float)($warning['amount'] ?? 0), 2);
    $qty = (float)($warning['qty'] ?? 1);
    $memo = trim((string)($warning['invoice_number'] ?? ''));
    if ($memo === '' && (int)($warning['sale_id'] ?? 0) > 0) {
        $memo = '#' . (int)$warning['sale_id'];
    }
    $sec = (int)($warning['seconds_ago'] ?? $windowSeconds);
    if ($sec > $windowSeconds) {
        $sec = $windowSeconds;
    }
    if ($sec < 1) {
        $sec = 1;
    }
    if ($isEnglish) {
        return "⚠️ Rapid resubmit ({$sec}s ago)\nProduct: «{$name}»\nAmount: ৳{$amt}" . ($qty > 0 ? " × {$qty}" : '')
            . "\nAlready sold on memo {$memo}.\n\nSubmit this sale again, or Cancel?";
    }

    return "⚠️ দ্রুত দ্বিতীয় সাবমিট (প্রায় {$sec} সেকেন্ড আগে)\n"
        . "পণ্য: «{$name}»\n"
        . "টাকা: ৳{$amt}" . ($qty > 0 ? " × {$qty}" : '') . "\n"
        . "আগের বিক্রয়: মেমো {$memo}\n\n"
        . "এখন আবার সাবমিট করবেন নাকি বাতিল করবেন?";
}

function ame_sale_guard_schema(mysqli $db): void
{
    @$db->query(
        "CREATE TABLE IF NOT EXISTS sale_submit_guards (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            fingerprint CHAR(64) NOT NULL,
            sale_id INT UNSIGNED NOT NULL,
            invoice_number VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sale_guard_user_fp (user_id, fingerprint, created_at),
            KEY idx_sale_guard_sale (sale_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ame_sale_submit_fingerprint(array $data): string
{
    $cart = [];
    foreach ((array)($data['cart'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $cart[] = [
            'sku' => strtoupper(trim((string)($row['sku'] ?? $row['product_code'] ?? ''))),
            'name' => trim((string)($row['name'] ?? $row['item_name'] ?? '')),
            'qty' => round((float)($row['qty'] ?? $row['quantity'] ?? 1), 3),
            'price' => round((float)($row['current_price'] ?? $row['price'] ?? $row['unit_price'] ?? 0), 2),
            'disc' => round((float)($row['discount_pct'] ?? 0), 2),
        ];
    }
    usort($cart, static fn($a, $b) => strcmp($a['sku'] . $a['name'], $b['sku'] . $b['name']));
    $services = [];
    foreach ((array)($data['services'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $services[] = [
            'e' => (int)($row['engineer_id'] ?? 0),
            't' => function_exists('mb_strtolower')
                ? mb_strtolower(trim((string)($row['service_type'] ?? '')))
                : strtolower(trim((string)($row['service_type'] ?? ''))),
            'a' => round((float)($row['amount'] ?? 0), 2),
        ];
    }
    usort($services, static fn($a, $b) => ($a['e'] <=> $b['e']) ?: strcmp($a['t'], $b['t']));
    $payload = [
        'c' => $cart,
        's' => $services,
        'cust' => (int)($data['customer_id'] ?? 0),
        'veh' => trim((string)($data['vehicle_no'] ?? '')),
    ];

    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** @return array{id:int,invoice_number:string,seconds_ago:int}|null */
function ame_sale_guard_find_recent(mysqli $db, int $userId, string $fingerprint, ?int $windowSeconds = null): ?array
{
    if ($userId <= 0 || strlen($fingerprint) !== 64) {
        return null;
    }
    if ($windowSeconds === null) {
        $windowSeconds = ame_rapid_resubmit_window_seconds();
    }
    ame_sale_guard_schema($db);
    $winSql = function_exists('ame_sql_interval_seconds') ? ame_sql_interval_seconds($windowSeconds) : max(1, min(3600, $windowSeconds));
    $st = @$db->prepare(
        "SELECT g.sale_id, g.invoice_number, g.created_at
         FROM sale_submit_guards g
         INNER JOIN sales s ON s.id = g.sale_id AND s.status = 'completed'
         WHERE g.user_id = ? AND g.fingerprint = ?
           AND g.created_at >= (NOW() - INTERVAL {$winSql} SECOND)
         ORDER BY g.id DESC LIMIT 1"
    );
    if (!$st) {
        return null;
    }
    $st->bind_param('is', $userId, $fingerprint);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) {
        return null;
    }
    $ts = strtotime((string)($row['created_at'] ?? '')) ?: time();

    return [
        'id' => (int)($row['sale_id'] ?? 0),
        'invoice_number' => trim((string)($row['invoice_number'] ?? '')),
        'seconds_ago' => max(0, time() - $ts),
    ];
}

function ame_sale_guard_record(mysqli $db, int $userId, string $fingerprint, int $saleId, string $invoiceNumber): void
{
    if ($userId <= 0 || strlen($fingerprint) !== 64 || $saleId <= 0) {
        return;
    }
    ame_sale_guard_schema($db);
    $inv = substr(trim($invoiceNumber), 0, 64);
    $st = @$db->prepare(
        'INSERT INTO sale_submit_guards (user_id, fingerprint, sale_id, invoice_number) VALUES (?, ?, ?, ?)'
    );
    if (!$st) {
        return;
    }
    $st->bind_param('isis', $userId, $fingerprint, $saleId, $inv);
    $st->execute();
    $st->close();
}

function ame_sale_duplicate_block_message(array $hit, bool $isEnglish, int $windowSeconds): string
{
    $memo = $hit['invoice_number'] !== '' ? $hit['invoice_number'] : ('#' . (int)$hit['id']);
    $sec = (int)($hit['seconds_ago'] ?? $windowSeconds);
    if ($isEnglish) {
        return "Duplicate sale blocked\nMemo {$memo} was just created ({$sec}s ago).\n\nSame cart cannot be submitted twice without confirmation.";
    }

    return "ডুপ্লিকেট বিক্রয় আটকানো হয়েছে\nমেমো {$memo} ইতিমধ্যে তৈরি হয়েছে ({$sec} সেকেন্ড আগে)।\n\nএকই মেমো দ্বিতীয়বার সাবমিট করা যাবে না — কনফার্ম ছাড়া ব্লক।";
}