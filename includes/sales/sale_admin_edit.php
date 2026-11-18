<?php
/* 10.98.703 — ame_sale_edit_balance_preview (শুধু পড়া) */
/**
 * Admin invoice rewrite from the sales report.
 * Unwinds stock, ledger, accounts, and unpaid commissions, then writes the
 * same sale id again so the memo number stays and nothing is posted twice.
 */

function ame_sale_admin_is_admin(): bool
{
    if (!function_exists('ame_is_admin_like')) {
        $sa = dirname(__DIR__) . '/super_admin.php';
        if (is_file($sa)) {
            require_once $sa;
        }
    }
    if (function_exists('ame_is_admin_like')) {
        return ame_is_admin_like();
    }
    return isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
}

function ame_sale_admin_can_reopen(): bool
{
    if (!function_exists('ame_is_super_admin')) {
        $sa = dirname(__DIR__) . '/super_admin.php';
        if (is_file($sa)) {
            require_once $sa;
        }
    }
    return function_exists('ame_is_super_admin') && ame_is_super_admin();
}

function ame_sale_admin_effective_customer_id(array $data): int
{
    $type = trim((string)($data['customer_type'] ?? ''));
    if (strcasecmp($type, 'Retail') === 0) {
        return 0;
    }
    return max(0, (int)($data['customer_id'] ?? 0));
}

function ame_sale_admin_post_has_voided_lines(array $postItems): bool
{
    if (!is_array($postItems)) {
        return false;
    }
    foreach ($postItems as $row) {
        if (!is_array($row)) {
            continue;
        }
        $void = $row['void'] ?? null;
        if ($void === 1 || $void === '1' || $void === true) {
            return true;
        }
        if (is_string($void) && strtolower(trim($void)) === 'on') {
            return true;
        }
    }
    return false;
}

function ame_sale_admin_customer_identity_changed(array $sale, array $customerData): bool
{
    $old = max(0, (int)($sale['customer_id'] ?? 0));
    $new = ame_sale_admin_effective_customer_id($customerData);
  if ($old !== $new) {
        return true;
    }
    $old_type = strtolower(trim((string)($sale['customer_type'] ?? 'Registered')));
    $new_type = strtolower(trim((string)($customerData['customer_type'] ?? 'Registered')));
    if ($old_type !== $new_type && ($old <= 0 || $new <= 0)) {
        return true;
    }
    return false;
}

/**
 * @return list<array<string, mixed>>
 */
/**
 * @return list<array<string, mixed>>
 */
function ame_sale_admin_snapshot_sale_items(mysqli $db, int $sale_id): array
{
    $sale_id = (int)$sale_id;
    $st = @$db->prepare(
        'SELECT product_id, item_name, qty, sale_price, purchase_price, unit_id, base_qty
         FROM sale_items WHERE sale_id = ? ORDER BY id ASC'
    );
    if (!$st) {
        $st = @$db->prepare(
            'SELECT product_id, item_name, qty, sale_price, purchase_price
             FROM sale_items WHERE sale_id = ? ORDER BY id ASC'
        );
    }
    if (!$st) {
        return [];
    }
    $st->bind_param('i', $sale_id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $st->close();
    return $rows;
}

function ame_sale_admin_restore_draft_item_rows(mysqli $db, int $sale_id, array $rows): void
{
    $sale_id = (int)$sale_id;
    if (!function_exists('ame_draft_store_items')) {
        require_once __DIR__ . '/sale_draft.php';
    }
    $items = [];
    foreach ($rows as $row) {
        $pid = (int)($row['product_id'] ?? 0);
        $qty = (float)($row['qty'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $items[] = [
            'product_id' => $pid > 0 ? $pid : null,
            'name' => (string)($row['item_name'] ?? ''),
            'quantity' => $qty,
            'purchase_price' => (float)($row['purchase_price'] ?? 0),
            'sale_price' => (float)($row['sale_price'] ?? 0),
            'is_custom' => $pid <= 0,
            'unit_id' => (int)($row['unit_id'] ?? 0),
            'base_qty' => isset($row['base_qty']) ? (float)$row['base_qty'] : $qty,
        ];
    }
    ame_draft_store_items($db, $sale_id, $items);
}

function ame_sale_admin_cart_from_sale_db(mysqli $db, int $sale_id): array
{
    $sale_id = (int)$sale_id;
    $cart = [];
    $st = @$db->prepare(
        "SELECT si.product_id, si.qty, si.sale_price,
                COALESCE(NULLIF(si.item_name, ''), p.name, '') AS item_name
         FROM sale_items si
         LEFT JOIN products p ON p.id = si.product_id
         WHERE si.sale_id = ?
         ORDER BY si.id ASC"
    );
    if (!$st) {
        return $cart;
    }
    $st->bind_param('i', $sale_id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $st->close();
    foreach ($rows as $row) {
        $pid = (int)($row['product_id'] ?? 0);
        $qty = (float)($row['qty'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $cart[] = [
            'product_id' => $pid,
            'id' => $pid > 0 ? $pid : 0,
            'qty' => $qty,
            'current_price' => round((float)($row['sale_price'] ?? 0), 2),
            'name' => (string)($row['item_name'] ?? ''),
            'is_custom' => $pid <= 0,
        ];
    }
    return $cart;
}

/**
 * Full rewrite payload from DB (customer switch with ledger reversal via rewrite).
 *
 * @param array<string, mixed> $customerData
 * @return array<string, mixed>
 */
function ame_sale_admin_rewrite_payload_from_sale(
    mysqli $db,
    array $sale,
    array $customerData,
    bool $is_english
): array {
    $sale_id = (int)($sale['id'] ?? 0);
    $cart = ame_sale_admin_cart_from_sale_db($db, $sale_id);
    $services = ame_sale_admin_services_payload_from_sale($db, $sale_id);
    if ($cart === [] && $services === []) {
        throw new InvalidArgumentException(
            $is_english
                ? 'This invoice has no product or service lines to save.'
                : 'এই ইনভয়েসে কোনো পণ্য বা সার্ভিস লাইন নেই।'
        );
    }
    $payments = ame_sale_admin_payments_payload_from_sale($db, $sale);
    $exchange = [];
    if (!function_exists('ame_sale_load_exchange_items') && is_file(__DIR__ . '/sale_exchange.php')) {
        require_once __DIR__ . '/sale_exchange.php';
    }
    if (function_exists('ame_sale_load_exchange_items')) {
        $exchange = ame_sale_load_exchange_items($db, $sale_id);
    }
    return array_merge(
        $customerData,
        $payments,
        [
            'cart' => $cart,
            'services' => $services,
            'discount' => round((float)($sale['discount'] ?? 0), 2),
            'vat_percent' => round((float)($sale['vat_percent'] ?? 0), 2),
            'rounding' => round((float)($sale['rounding_amount'] ?? 0), 2),
            'exchange_items' => $exchange,
            'adjust_due' => !empty($customerData['adjust_due'])
                || abs((float)($sale['due_adjustment_amount'] ?? 0)) > 0.009,
        ]
    );
}

/**
 * সুপার এডমিন: সম্পন্ন মেমো → ড্রাফট (লেজার/স্টক/ক্যাশ রিভার্স), POS থেকে পুনঃসম্পন্ন।
 *
 * @param array<string, mixed> $sale
 * @return array{sale_id: int, invoice_number: string}
 */
function ame_sale_admin_reopen_to_draft(mysqli $db, array $sale, int $user_id, bool $is_english): array
{
    if (!function_exists('ame_draft_store_services')) {
        require_once __DIR__ . '/sale_draft.php';
    }
    if (!function_exists('ame_sale_replace_services')) {
        require_once __DIR__ . '/sale_finance.php';
    }
    $sale_id = (int)($sale['id'] ?? 0);
    if ($sale_id <= 0) {
        throw new RuntimeException($is_english ? 'Invoice not found.' : 'ইনভয়েস পাওয়া যায়নি।');
    }
    $status = strtolower(trim((string)($sale['status'] ?? '')));
    if ($status !== 'completed') {
        throw new InvalidArgumentException(
            $is_english
                ? 'Only a completed invoice can be reopened as draft.'
                : 'শুধু সম্পন্ন ইনভয়েস ড্রাফটে আনা যায়।'
        );
    }
    $returned = ame_sale_admin_returned_qty_map($db, $sale_id);
    foreach ($returned as $rq) {
        if ($rq > 0.0001) {
            throw new InvalidArgumentException(
                $is_english
                    ? 'Invoices with product returns cannot be reopened here — handle via return memo first.'
                    : 'ফেরতসহ ইনভয়েস এখানে ড্রাফটে আনা যায় না — আগে ফেরত মেমো ঠিক করুন।'
            );
        }
    }

    $item_snapshot = ame_sale_admin_snapshot_sale_items($db, $sale_id);
    $services = ame_sale_admin_services_payload_from_sale($db, $sale_id, $sale);
    $payments = ame_sale_admin_payments_payload_from_sale($db, $sale);
    $exchange = [];
    if (!function_exists('ame_sale_load_exchange_items') && is_file(__DIR__ . '/sale_exchange.php')) {
        require_once __DIR__ . '/sale_exchange.php';
    }
    if (function_exists('ame_sale_load_exchange_items')) {
        $exchange = ame_sale_load_exchange_items($db, $sale_id);
    }

    ame_sale_admin_unwind($db, $sale, $is_english);

    $old_invoice = trim((string)($sale['invoice_number'] ?? ''));
    if ($old_invoice === '') {
        $old_invoice = 'INV-' . $sale_id;
    }
    $draft_number = 'DRF-' . str_replace('-', '', date('Ymd')) . '-' . str_pad((string)$sale_id, 6, '0', STR_PAD_LEFT);
    $invoice_number = $draft_number;
    $note = trim((string)($sale['draft_note'] ?? ''));
    $stamp = date('Y-m-d H:i:s');
    $reopen_line = ($is_english ? 'Reopened to draft ' : 'ড্রাফটে আনা ') . $stamp . ' (user ' . $user_id . ')'
        . ($is_english ? ' | was ' : ' | পূর্বে ') . $old_invoice;
    $draft_note = $note === '' ? $reopen_line : ($note . "\n" . $reopen_line);

    $storeFile = dirname(__DIR__) . '/store_functions.php';
    if (is_file($storeFile)) {
        require_once $storeFile;
    }
    if (function_exists('ame_sales_set_store')) {
        ame_sales_set_store($db, $sale_id);
    }

    $sales_cols = function_exists('ame_schema_columns')
        ? ame_schema_columns($db, 'sales')
        : [];
    $has_updated = !empty($sales_cols['updated_at']);
    if ($has_updated) {
        $up = $db->prepare(
            "UPDATE sales SET
                status = 'draft',
                invoice_number = ?,
                completed_at = NULL,
                draft_note = ?,
                updated_at = NOW()
             WHERE id = ?"
        );
    } else {
        $up = $db->prepare(
            "UPDATE sales SET
                status = 'draft',
                invoice_number = ?,
                completed_at = NULL,
                draft_note = ?
             WHERE id = ?"
        );
    }
    if (!$up) {
        throw new RuntimeException($is_english ? 'Could not mark draft.' : 'ড্রাফট স্থিতি সেট করা যায়নি।');
    }
    $up->bind_param('ssi', $draft_number, $draft_note, $sale_id);
    $up->execute();
    $up->close();

    if ($item_snapshot !== []) {
        ame_sale_admin_restore_draft_item_rows($db, $sale_id, $item_snapshot);
    }
    ame_draft_restore_service_lines($db, $sale_id, $services);
    if (!empty($payments['payments'])) {
        ame_draft_store_payments($db, $sale_id, $payments['payments'], $user_id);
    }
    if ($exchange !== [] && function_exists('ame_sale_store_exchange_items')) {
        $cust = (int)($sale['customer_id'] ?? 0);
        ame_sale_store_exchange_items($db, $sale_id, $exchange, false, $cust, $is_english);
        if (function_exists('ame_sale_set_exchange_columns')) {
            $ex_total = 0.0;
            foreach ($exchange as $ex) {
                $ex_total += (float)($ex['line_total'] ?? 0);
            }
            ame_sale_set_exchange_columns($db, $sale_id, $ex_total, (float)($sale['cash_paid_amount'] ?? 0));
        }
    }

    if (function_exists('log_activity')) {
        log_activity(
            $is_english ? 'Sale reopened to draft' : 'বিক্রয় ড্রাফটে আনা',
            'Sales',
            $invoice_number,
            [
                'action_type' => 'reopen',
                'entity_type' => 'invoice',
                'entity_id' => (string)$sale_id,
                'reference' => $invoice_number,
            ]
        );
    }

    return [
        'sale_id' => $sale_id,
        'invoice_number' => $invoice_number,
    ];
}

/**
 * এই ইনভয়েসের বিল। সাবেক বকেয়া আলাদা ঘরে থাকে।
 * সাবেক এখানে যোগ করলে মেমোতে মোট দ্বিগুণ হয়।
 */
function ame_sale_collect_total($sale_bill, $previous_balance = 0.0): float
{
    return round((float)$sale_bill, 2);
}

function ame_sale_admin_returned_qty_map(mysqli $db, int $sale_id): array
{
    $map = [];
    $sale_id = (int)$sale_id;
    if ($sale_id <= 0) {
        return $map;
    }
    $add = static function (array &$map, int $pid, float $qty): void {
        if ($pid <= 0 || $qty <= 0) {
            return;
        }
        $map[$pid] = ($map[$pid] ?? 0.0) + $qty;
    };
    $chk = @$db->query("SHOW TABLES LIKE 'sale_return_items'");
    $has_items = $chk && $chk->num_rows > 0;
    if ($chk) {
        $chk->close();
    }
    if ($has_items) {
        $v2 = @$db->query("SHOW TABLES LIKE 'sale_returns_v2'");
        $has_v2 = $v2 && $v2->num_rows > 0;
        if ($v2) {
            $v2->close();
        }
        if ($has_v2) {
            $st = @$db->prepare(
                'SELECT sri.product_id, SUM(sri.returned_qty) AS q
                 FROM sale_return_items sri
                 INNER JOIN sale_returns_v2 sr ON sr.id = sri.return_id
                 WHERE sr.sale_id = ?
                 GROUP BY sri.product_id'
            );
            if ($st) {
                $st->bind_param('i', $sale_id);
                $st->execute();
                $res = $st->get_result();
                while ($row = $res->fetch_assoc()) {
                    $add($map, (int)$row['product_id'], (float)$row['q']);
                }
                $st->close();
            }
        }
    }
    $old = @$db->query("SHOW TABLES LIKE 'sale_returns'");
    $has_old = $old && $old->num_rows > 0;
    if ($old) {
        $old->close();
    }
    if ($has_old) {
        $st = @$db->prepare(
            'SELECT product_id, SUM(qty) AS q FROM sale_returns WHERE sale_id = ? GROUP BY product_id'
        );
        if ($st) {
            $st->bind_param('i', $sale_id);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $add($map, (int)$row['product_id'], (float)$row['q']);
            }
            $st->close();
        }
    }
    return $map;
}

/**
 * @return array{cart: list<array<string, mixed>>, voided: int, kept: int}
 */
function ame_sale_admin_cart_from_post_items(mysqli $db, int $sale_id, array $postItems, bool $is_english): array
{
    $sale_id = (int)$sale_id;
    $cart = [];
    $voided = 0;
    $kept = 0;
    if (!is_array($postItems)) {
        return ['cart' => [], 'voided' => 0, 'kept' => 0];
    }
  if (function_exists('get_already_returned_qty') === false && is_file(__DIR__ . '/../return_v2_functions.php')) {
        require_once __DIR__ . '/../return_v2_functions.php';
    }
    foreach ($postItems as $row) {
        if (!is_array($row)) {
            continue;
        }
        $void = $row['void'] ?? null;
        $is_void = $void === 1 || $void === '1' || $void === true
            || (is_string($void) && strtolower(trim($void)) === 'on');
        if ($is_void) {
            $voided++;
            continue;
        }
        $qty = (float)($row['qty'] ?? 0);
        $price = (float)($row['price'] ?? 0);
        $pid = (int)($row['product_id'] ?? 0);
        $custom = !empty($row['is_custom']) || $pid <= 0;
        $name = trim((string)($row['name'] ?? ''));
        if ($qty <= 0 || $price < 0) {
            continue;
        }
        if (!$custom && $pid > 0) {
            $sold = $qty;
            $returned = function_exists('get_already_returned_qty')
                ? (float)get_already_returned_qty($db, $sale_id, $pid)
                : 0.0;
            if ($returned > $sold + 0.0001) {
                throw new InvalidArgumentException(
                    $is_english
                        ? 'Returned quantity exceeds the sold line. Fix returns first.'
                        : 'ফেরত পরিমাণ বিক্রয় সারির চেয়ে বেশি। আগে ফেরত ঠিক করুন।'
                );
            }
        }
        $kept++;
        $cart[] = [
            'id' => $pid,
            'product_id' => $pid,
            'qty' => $qty,
            'current_price' => $price,
            'name' => $name,
            'is_custom' => $custom,
        ];
    }
    return ['cart' => $cart, 'voided' => $voided, 'kept' => $kept];
}

/**
 * @param array<string, mixed> $sale
 * @return list<array<string, mixed>>
 */
function ame_sale_admin_services_payload_from_sale(mysqli $db, int $sale_id, array $sale = []): array
{
    $sale_id = (int)$sale_id;
    $out = [];
    $st = @$db->prepare(
        'SELECT ss.engineer_id, ss.service_type, ss.amount,
                COALESCE(NULLIF(TRIM(e.name), ""), NULLIF(TRIM(ss.engineer_name), ""), "") AS engineer_name
         FROM sale_services ss
         LEFT JOIN engineers e ON e.id = ss.engineer_id
         WHERE ss.sale_id = ?
         ORDER BY ss.id ASC'
    );
    if (!$st) {
        $st = @$db->prepare(
            'SELECT engineer_id, service_type, amount FROM sale_services WHERE sale_id = ? ORDER BY id ASC'
        );
    }
    if ($st) {
        $st->bind_param('i', $sale_id);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $st->close();
        foreach ($rows as $row) {
            $amount = round((float)($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $eng = trim((string)($row['engineer_name'] ?? ''));
            $out[] = [
                'engineer_id' => (int)($row['engineer_id'] ?? 0),
                'service_type' => (string)($row['service_type'] ?? ''),
                'amount' => $amount,
                'engineer_name' => $eng,
                'engineer_query' => $eng,
            ];
        }
    }

    if ($out === []) {
        $out = ame_sale_admin_services_from_commissions($db, $sale_id);
    }

    if ($out === [] && $sale !== []) {
        $fallback = round((float)($sale['service_amount'] ?? 0), 2);
        if ($fallback > 0.009) {
            $out[] = [
                'engineer_id' => 0,
                'service_type' => 'কাজের বিল',
                'amount' => $fallback,
                'engineer_name' => '',
                'engineer_query' => '',
            ];
        }
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function ame_sale_admin_services_from_commissions(mysqli $db, int $sale_id): array
{
    $sale_id = (int)$sale_id;
    $out = [];
    $chk = @$db->query("SHOW TABLES LIKE 'service_commissions'");
    if (!$chk || $chk->num_rows === 0) {
        if ($chk) {
            $chk->close();
        }
        return $out;
    }
    $chk->close();
    $st = @$db->prepare(
        'SELECT engineer_id, service_type, service_amount AS amount
         FROM service_commissions
         WHERE sale_id = ?
         ORDER BY id ASC'
    );
    if (!$st) {
        return $out;
    }
    $st->bind_param('i', $sale_id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $st->close();
    foreach ($rows as $row) {
        $amount = round((float)($row['amount'] ?? 0), 2);
        if ($amount <= 0) {
            continue;
        }
        $type = trim((string)($row['service_type'] ?? ''));
        if ($type === '') {
            $type = 'কাজের বিল';
        }
        $out[] = [
            'engineer_id' => (int)($row['engineer_id'] ?? 0),
            'service_type' => $type,
            'amount' => $amount,
            'engineer_name' => '',
            'engineer_query' => '',
        ];
    }
    return $out;
}

/**
 * @return array{paid: float, account_id: int, payments: list<array<string, mixed>>}
 */
function ame_sale_admin_payments_payload_from_sale(mysqli $db, array $sale): array
{
    $sale_id = (int)($sale['id'] ?? 0);
    $payload = [
        'paid' => round((float)($sale['paid_amount'] ?? 0), 2),
        'account_id' => (int)($sale['account_id'] ?? 0),
        'payments' => [],
    ];
    $chk = @$db->query("SHOW TABLES LIKE 'sale_payments'");
    $has = $chk && $chk->num_rows > 0;
    if ($chk) {
        $chk->close();
    }
    if (!$has || $sale_id <= 0) {
        return $payload;
    }
    $st = @$db->prepare(
        "SELECT account_id, amount, COALESCE(reference_number, '') AS reference_number
         FROM sale_payments
         WHERE sale_id = ?
           AND (payment_status IS NULL OR payment_status = '' OR payment_status = 'posted')
         ORDER BY id ASC"
    );
    if (!$st) {
        return $payload;
    }
    $st->bind_param('i', $sale_id);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $st->close();
    foreach ($rows as $row) {
        $amount = round((float)($row['amount'] ?? 0), 2);
        $account_id = (int)($row['account_id'] ?? 0);
        if ($amount <= 0 || $account_id <= 0) {
            continue;
        }
        $payload['payments'][] = [
            'account_id' => $account_id,
            'amount' => $amount,
            'reference_number' => (string)($row['reference_number'] ?? ''),
        ];
    }
    return $payload;
}

/**
 * @param array<string, mixed> $customerData
 * @return array<string, mixed>
 */
function ame_sale_admin_rewrite_payload_line_void(
    mysqli $db,
    array $sale,
    array $customerData,
    array $postItems,
    bool $is_english
): array {
    $sale_id = (int)($sale['id'] ?? 0);
    $cartPack = ame_sale_admin_cart_from_post_items($db, $sale_id, $postItems, $is_english);
    if ($cartPack['voided'] < 1) {
        throw new InvalidArgumentException(
            $is_english
                ? 'Mark at least one product line as cancelled before saving.'
                : 'সেভ করার আগে কমপক্ষে একটি পণ্য বাতিল চিহ্নিত করুন।'
        );
    }
    $services = ame_sale_admin_services_payload_from_sale($db, $sale_id);
    if ($cartPack['cart'] === [] && $services === []) {
        throw new InvalidArgumentException(
            $is_english
                ? 'Every product cannot be cancelled without a service line.'
                : 'সব পণ্য বাতিল করা যাবে না — অন্তত একটি পণ্য বা সার্ভিস লাইন থাকতে হবে।'
        );
    }
    $payments = ame_sale_admin_payments_payload_from_sale($db, $sale);
    $exchange = [];
    if (!function_exists('ame_sale_load_exchange_items') && is_file(__DIR__ . '/sale_exchange.php')) {
        require_once __DIR__ . '/sale_exchange.php';
    }
    if (function_exists('ame_sale_load_exchange_items')) {
        $exchange = ame_sale_load_exchange_items($db, $sale_id);
    }
    return array_merge(
        $customerData,
        $payments,
        [
            'cart' => $cartPack['cart'],
            'services' => $services,
            'discount' => round((float)($sale['discount'] ?? 0), 2),
            'vat_percent' => round((float)($sale['vat_percent'] ?? 0), 2),
            'rounding' => round((float)($sale['rounding_amount'] ?? 0), 2),
            'exchange_items' => $exchange,
            'adjust_due' => !empty($customerData['adjust_due']),
        ]
    );
}

function ame_sale_admin_restore_item_stock(mysqli $db, array $item, float $restore_qty, bool $is_english): void
{
    $product_id = (int)($item['product_id'] ?? 0);
    if ($product_id <= 0 || $restore_qty <= 0.0001) {
        return;
    }
    $unit_id = (int)($item['unit_id'] ?? 0);
    if ($unit_id > 0 && is_file(__DIR__ . '/../unit_engine.php')) {
        require_once __DIR__ . '/../unit_engine.php';
        if (function_exists('ame_unit_move') && function_exists('ame_unit_num')) {
            ame_unit_move($db, $product_id, ame_unit_num((string)$restore_qty), [
                'kind' => 'sale_edit_restore',
                'entered_qty' => $restore_qty,
                'unit_id' => $unit_id,
                'ref_type' => 'sale_edit',
                'ref_id' => (int)($item['sale_id'] ?? 0),
                'note' => (string)($item['item_name'] ?? $item['name'] ?? ''),
            ], false);
            return;
        }
    }
    ame_sale_stock_in_product($db, $product_id, $restore_qty, $is_english);
}

function ame_sale_admin_unwind(mysqli $db, array $sale, bool $is_english): void
{
    $sale_id = (int)($sale['id'] ?? 0);
    if ($sale_id <= 0) {
        throw new RuntimeException($is_english ? 'Invoice not found.' : 'ইনভয়েস পাওয়া যায়নি।');
    }
    $old_customer = (int)($sale['customer_id'] ?? 0);
    $returned = ame_sale_admin_returned_qty_map($db, $sale_id);

    $items = [];
    $ist = @$db->prepare(
        'SELECT id, product_id, item_name, qty, sale_price, purchase_price, unit_id, base_qty
         FROM sale_items WHERE sale_id = ?'
    );
    if (!$ist) {
        $ist = $db->prepare('SELECT id, product_id, item_name, qty, sale_price, purchase_price FROM sale_items WHERE sale_id = ?');
    }
    if ($ist) {
        $ist->bind_param('i', $sale_id);
        $ist->execute();
        $items = $ist->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $ist->close();
    }
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $sold = (float)($item['base_qty'] ?? $item['qty'] ?? 0);
        $back = max(0.0, round($sold - (float)($returned[$pid] ?? 0), 3));
        $item['sale_id'] = $sale_id;
        ame_sale_admin_restore_item_stock($db, $item, $back, $is_english);
        if (function_exists('ame_sale_log_movement')) {
            $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
            ame_sale_log_movement(
                $db,
                $pid,
                $store_id,
                $back,
                0,
                (float)($item['sale_price'] ?? 0),
                'sale_edit_in',
                'sale',
                $sale_id,
                $old_customer > 0 ? $old_customer : null,
                (string)($item['item_name'] ?? '')
            );
        }
        if (isset($returned[$pid])) {
            $returned[$pid] = max(0.0, $returned[$pid] - $sold);
        }
    }

    $ex_chk = @$db->query("SHOW TABLES LIKE 'sale_exchange_items'");
    $has_ex = $ex_chk && $ex_chk->num_rows > 0;
    if ($ex_chk) {
        $ex_chk->close();
    }
    if ($has_ex) {
        $ex = @$db->prepare('SELECT product_id, item_name, qty, exchange_rate FROM sale_exchange_items WHERE sale_id = ?');
        if ($ex) {
            $ex->bind_param('i', $sale_id);
            $ex->execute();
            $ex_rows = $ex->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $ex->close();
            foreach ($ex_rows as $row) {
                $pid = (int)($row['product_id'] ?? 0);
                $qty = (float)($row['qty'] ?? 0);
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
                if ($store_id > 0 && function_exists('ame_store_stock_change')) {
                    ame_store_stock_change($db, $store_id, $pid, -$qty, false);
                } else {
                    $upd = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('di', $qty, $pid);
                        $upd->execute();
                        $upd->close();
                    }
                }
                if (function_exists('ame_sale_log_movement')) {
                    ame_sale_log_movement(
                        $db,
                        $pid,
                        $store_id,
                        0,
                        $qty,
                        (float)($row['exchange_rate'] ?? 0),
                        'sale_edit_exchange_out',
                        'sale',
                        $sale_id,
                        $old_customer > 0 ? $old_customer : null,
                        (string)($row['item_name'] ?? '')
                    );
                }
            }
        }
        if (function_exists('ame_sale_delete_exchange_items')) {
            ame_sale_delete_exchange_items($db, $sale_id);
        }
    }

    $pay_chk = @$db->query("SHOW TABLES LIKE 'sale_payments'");
    $has_pay = $pay_chk && $pay_chk->num_rows > 0;
    if ($pay_chk) {
        $pay_chk->close();
    }
    if ($has_pay) {
        $pst = @$db->prepare(
            "SELECT id, account_id, amount FROM sale_payments
             WHERE sale_id = ? AND (payment_status IS NULL OR payment_status = '' OR payment_status = 'posted')"
        );
        if ($pst) {
            $pst->bind_param('i', $sale_id);
            $pst->execute();
            $pays = $pst->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $pst->close();
            foreach ($pays as $pay) {
                $amt = round((float)($pay['amount'] ?? 0), 2);
                $acc = (int)($pay['account_id'] ?? 0);
                if ($amt > 0.009 && $acc > 0 && function_exists('account_debit')) {
                    account_debit(
                        $acc,
                        $amt,
                        'sale_reversal',
                        $sale_id,
                        ($is_english ? 'Invoice edit reversal #' : 'ইনভয়েস সংশোধন রিভার্স #') . $sale_id,
                        true
                    );
                }
            }
        }
        $delp = $db->prepare('DELETE FROM sale_payments WHERE sale_id = ?');
        if ($delp) {
            $delp->bind_param('i', $sale_id);
            $delp->execute();
            $delp->close();
        }
    }

    if ($old_customer > 0 && function_exists('add_ledger_entry')) {
        $refs = ['SALE-' . $sale_id, 'DUE-PAY-' . $sale_id, 'ADV-SALE-' . $sale_id];
        foreach ($refs as $ref) {
            $sum = @$db->prepare(
                'SELECT COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c
                 FROM customer_ledger WHERE customer_id = ? AND ref_no = ?'
            );
            if (!$sum) {
                continue;
            }
            $sum->bind_param('is', $old_customer, $ref);
            $sum->execute();
            $row = $sum->get_result()->fetch_assoc() ?: [];
            $sum->close();
            $net = round((float)($row['d'] ?? 0) - (float)($row['c'] ?? 0), 2);
            if (abs($net) < 0.01) {
                continue;
            }
            $note = ($is_english ? 'Invoice edit reversal #' : 'ইনভয়েস সংশোধন রিভার্স #') . $sale_id;
            if ($net > 0) {
                add_ledger_entry($db, $old_customer, $is_english ? 'Sale edit reverse' : 'বিক্রয় সংশোধন', $ref, $note, 0, $net);
            } else {
                add_ledger_entry($db, $old_customer, $is_english ? 'Sale edit reverse' : 'বিক্রয় সংশোধন', $ref, $note, abs($net), 0);
            }
        }
    }

    $sc = @$db->query("SHOW TABLES LIKE 'service_commissions'");
    if ($sc && $sc->num_rows > 0) {
        $delc = @$db->prepare(
            "DELETE FROM service_commissions
             WHERE sale_id = ? AND (status IS NULL OR status = '' OR status = 'pending')"
        );
        if ($delc) {
            $delc->bind_param('i', $sale_id);
            $delc->execute();
            $delc->close();
        }
    }
    if ($sc) {
        $sc->close();
    }
    $we = @$db->query("SHOW TABLES LIKE 'worker_earnings'");
    if ($we && $we->num_rows > 0) {
        $delw = @$db->prepare(
            "DELETE FROM worker_earnings
             WHERE sale_id = ?
               AND COALESCE(paid_amount, 0) < 0.01
               AND (locked IS NULL OR locked = 0)"
        );
        if ($delw) {
            $delw->bind_param('i', $sale_id);
            $delw->execute();
            $delw->close();
        }
    }
    if ($we) {
        $we->close();
    }

    $deli = $db->prepare('DELETE FROM sale_items WHERE sale_id = ?');
    if ($deli) {
        $deli->bind_param('i', $sale_id);
        $deli->execute();
        $deli->close();
    }
    if (function_exists('ame_sale_replace_services')) {
        ame_sale_replace_services($db, $sale_id, []);
    } else {
        $dels = @$db->prepare('DELETE FROM sale_services WHERE sale_id = ?');
        if ($dels) {
            $dels->bind_param('i', $sale_id);
            $dels->execute();
            $dels->close();
        }
    }
}

/**
 * ইনভয়েস এডিট পাতার জন্য শুধু-পড়া হিসাব: সেভ করলে এই ক্রেতার সাবেক বকেয়া কত ধরা হবে।
 * একই ক্রেতা হলে ইনভয়েসে রাখা সাবেক, অন্য ক্রেতা হলে তার এখনকার ব্যালেন্স (সেভ-এর নিয়মই)।
 * ধনাত্মক = বাকি, ঋণাত্মক = জমা/দোকানের দেনা।
 *
 * @return array<string,mixed>
 */
function ame_sale_edit_balance_preview(mysqli $db, array $sale, int $customer_id): array
{
    $out = [
        'ok' => false, 'previous' => 0.0, 'live' => 0.0, 'same' => false,
        'id' => $customer_id, 'name' => '', 'phone' => '', 'code' => '', 'supplier' => false,
    ];
    if ($customer_id <= 0) {
        return $out;
    }
    if (!function_exists('ame_resolve_party_sale_balance')) {
        require_once __DIR__ . '/sale_common.php';
    }
    $ledgerFile = dirname(__DIR__, 2) . '/modules/customers/customer_ledger_functions.php';
    if (!function_exists('ame_ledger_net') && is_file($ledgerFile)) {
        require_once $ledgerFile;
    }
    $row = null;
    $stmt = @$db->prepare(
        'SELECT id, name, phone, customer_code, receivable_balance, payable_balance, is_supplier, linked_supplier_id
         FROM customers WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        $stmt = @$db->prepare('SELECT id, name, phone, customer_code, receivable_balance, payable_balance, is_supplier FROM customers WHERE id = ? LIMIT 1');
    }
    if ($stmt) {
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$row) {
        return $out;
    }
    if (function_exists('ame_ledger_net')) {
        $has = @$db->prepare('SELECT id FROM customer_ledger WHERE customer_id = ? LIMIT 1');
        if ($has) {
            $has->bind_param('i', $customer_id);
            $has->execute();
            $any = $has->get_result()->fetch_assoc();
            $has->close();
            if ($any) {
                $row['receivable_balance'] = ame_ledger_net($db, $customer_id);
            }
        }
    }
    $live = round((float)ame_resolve_party_sale_balance($row, $db), 2);
    $same = $customer_id === (int)($sale['customer_id'] ?? 0);
    $out['ok'] = true;
    $out['same'] = $same;
    $out['live'] = $live;
    $out['previous'] = $same ? round((float)($sale['previous_customer_balance'] ?? 0), 2) : $live;
    $out['name'] = (string)($row['name'] ?? '');
    $out['phone'] = (string)($row['phone'] ?? '');
    $out['code'] = (string)($row['customer_code'] ?? '');
    $out['supplier'] = !empty($row['is_supplier']);
    return $out;
}

function ame_sale_admin_stamp_date(mysqli $db, int $sale_id, string $completed_at): void
{
    if ($sale_id <= 0 || $completed_at === '') {
        return;
    }
    $st = @$db->prepare('UPDATE sales SET sale_date = ?, completed_at = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('ssi', $completed_at, $completed_at, $sale_id);
        $st->execute();
        $st->close();
        return;
    }
    $st = @$db->prepare('UPDATE sales SET sale_date = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('si', $completed_at, $sale_id);
        $st->execute();
        $st->close();
    }
}

/**
 * এই ইনভয়েসের লেজার রেফ। টাকা নতুন করে লেখা হয় না — শুধু মালিক বদলায়।
 *
 * @return list<string>
 */
function ame_sale_admin_ledger_refs(int $sale_id): array
{
    $sale_id = (int)$sale_id;
    return [
        'SALE-' . $sale_id,
        'SALE-' . $sale_id . '-PAY',
        'SALE-' . $sale_id . '-EX',
        'DUE-PAY-' . $sale_id,
        'ADV-SALE-' . $sale_id,
    ];
}

function ame_sale_admin_refresh_customer_balance(mysqli $db, int $customer_id): void
{
    $customer_id = (int)$customer_id;
    if ($customer_id <= 0) {
        return;
    }
    $ledgerFile = dirname(__DIR__, 2) . '/modules/customers/customer_ledger_functions.php';
    if (!function_exists('ame_ledger_net') && is_file($ledgerFile)) {
        require_once $ledgerFile;
    }
    if (!function_exists('ame_ledger_net')) {
        return;
    }
    if (function_exists('ame_ledger_recompute_running')) {
        ame_ledger_recompute_running($db, $customer_id);
    }
    $net = ame_ledger_net($db, $customer_id);
    $st = @$db->prepare('UPDATE customers SET receivable_balance = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('di', $net, $customer_id);
        $st->execute();
        $st->close();
    }
    if (function_exists('ame_sync_supplier_due_from_customer')) {
        ame_sync_supplier_due_from_customer($db, $customer_id, $net);
    }
}

/**
 * মেমোতে কাস্টমার পুরো বদল: বিল/পরিশোধ/স্টক/ক্যাশ অ্যাকাউন্ট অপরিবর্তিত।
 * নিবন্ধিত → নিবন্ধিত হলে এই ইনভয়েসের লেজার সারি একই টাকায় নতুন ক্রেতায় যায়।
 *
 * @param array<string,mixed> $sale
 * @param array<string,mixed> $data
 * @return array{sale_id:int,invoice_number:string}
 */
function ame_sale_admin_reassign(mysqli $db, array $sale, array $data, bool $is_english): array
{
    $sale_id = (int)($sale['id'] ?? 0);
    if ($sale_id <= 0) {
        throw new RuntimeException($is_english ? 'Invoice not found.' : 'ইনভয়েস পাওয়া যায়নি।');
    }
    $invoice_number = (string)($sale['invoice_number'] ?? ('#' . $sale_id));
    $old = (int)($sale['customer_id'] ?? 0);
    $type = trim((string)($data['customer_type'] ?? ''));
    $new = (int)($data['customer_id'] ?? 0);
    if (strcasecmp($type, 'Retail') === 0) {
        $new = 0;
    }
    if ($new < 0) {
        $new = 0;
    }

    if ($new > 0) {
        $stmt = @$db->prepare(
            'SELECT id, name, phone, status, purged_at FROM customers WHERE id = ? LIMIT 1'
        );
        $row = null;
        if ($stmt) {
            $stmt->bind_param('i', $new);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        if (!$row) {
            throw new InvalidArgumentException(
                $is_english ? 'The selected customer was not found.' : 'নির্বাচিত কাস্টমার পাওয়া যায়নি।'
            );
        }
        if ($new !== $old && function_exists('ame_customer_row_operable') && !ame_customer_row_operable($row)) {
            throw new InvalidArgumentException(
                $is_english
                    ? 'Inactive customers cannot be assigned. Enable them from the customer list.'
                    : 'বন্ধ কাস্টমার বসানো যায় না। কাস্টমার তালিকা থেকে চালু করুন।'
            );
        }
    }

    $customer_type = $new > 0 ? 'Registered' : 'Retail';
    $guest_name = substr(trim((string)($data['guest_customer_name'] ?? '')), 0, 150);
    $guest_phone = substr(trim((string)($data['guest_customer_phone'] ?? '')), 0, 30);
    $guest_note = substr(trim((string)($data['guest_customer_note'] ?? '')), 0, 255);
    $vehicle = substr(trim((string)($data['vehicle_no'] ?? '')), 0, 40);
    if ($new > 0) {
        $guest_name = '';
        $guest_phone = '';
    }

    if ($old !== $new && $old > 0 && $new > 0) {
        foreach (ame_sale_admin_ledger_refs($sale_id) as $ref) {
            $mv = @$db->prepare(
                'UPDATE customer_ledger SET customer_id = ? WHERE customer_id = ? AND ref_no = ?'
            );
            if ($mv) {
                $mv->bind_param('iis', $new, $old, $ref);
                $mv->execute();
                $mv->close();
            }
        }
        $payCol = @$db->query("SHOW COLUMNS FROM sale_payments LIKE 'customer_id'");
        if ($payCol && $payCol->num_rows > 0) {
            $up = @$db->prepare('UPDATE sale_payments SET customer_id = ? WHERE sale_id = ?');
            if ($up) {
                $up->bind_param('ii', $new, $sale_id);
                $up->execute();
                $up->close();
            }
        }
        if ($payCol) {
            $payCol->close();
        }
        ame_sale_admin_refresh_customer_balance($db, $old);
        ame_sale_admin_refresh_customer_balance($db, $new);
    }

    if ($new > 0) {
        $st = @$db->prepare(
            'UPDATE sales SET customer_id = ?, customer_type = ?, guest_customer_name = ?, guest_customer_phone = ?, guest_customer_note = ?, vehicle_no = ? WHERE id = ?'
        );
        if ($st) {
            $st->bind_param(
                'isssssi',
                $new,
                $customer_type,
                $guest_name,
                $guest_phone,
                $guest_note,
                $vehicle,
                $sale_id
            );
            $st->execute();
            $st->close();
        }
    } else {
        $st = @$db->prepare(
            'UPDATE sales SET customer_id = NULL, customer_type = ?, guest_customer_name = ?, guest_customer_phone = ?, guest_customer_note = ?, vehicle_no = ? WHERE id = ?'
        );
        if ($st) {
            $st->bind_param(
                'sssssi',
                $customer_type,
                $guest_name,
                $guest_phone,
                $guest_note,
                $vehicle,
                $sale_id
            );
            $st->execute();
            $st->close();
        }
    }

    $day = trim((string)($data['sale_date'] ?? ''));
    $tm = trim((string)($data['sale_time'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $tm)) {
            $existing = (string)($sale['sale_date'] ?? $sale['completed_at'] ?? '');
            $tm = strlen($existing) >= 19 ? substr($existing, 11, 8) : date('H:i:s');
        }
        if (strlen($tm) === 5) {
            $tm .= ':00';
        }
        ame_sale_admin_stamp_date($db, $sale_id, $day . ' ' . $tm);
    }

    return [
        'sale_id' => $sale_id,
        'invoice_number' => $invoice_number,
    ];
}

function ame_sale_admin_rewrite(mysqli $db, array $sale, array $data, int $user_id, bool $is_english): array
{
    $sale_id = (int)($sale['id'] ?? 0);
    if ($sale_id <= 0) {
        throw new RuntimeException($is_english ? 'Invoice not found.' : 'ইনভয়েস পাওয়া যায়নি।');
    }
    $invoice_number = (string)($sale['invoice_number'] ?? ('#' . $sale_id));
    $kept_previous = round((float)($sale['previous_customer_balance'] ?? 0), 2);

    ame_sale_admin_unwind($db, $sale, $is_english);

    $customer_id = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
    if ($customer_id !== null && $customer_id <= 0) {
        $customer_id = null;
    }
    $customer_type = $customer_id === null ? 'Retail' : (string)($data['customer_type'] ?? 'Registered');
    if ($customer_type === '') {
        $customer_type = 'Registered';
    }

    $parsed_services = ame_sale_parse_services($data, $is_english);
    $service_lines = $parsed_services['lines'];
    $service_amount = $parsed_services['total'];
    if (empty($data['cart']) && $service_amount <= 0) {
        ame_sale_fail($is_english, 'প্রথমে পণ্য অথবা সার্ভিস যোগ করুন।', 'Add a product or service first.');
    }
    ame_sale_assert_service_engineers($db, $service_lines, $is_english);

    $vat_percent = round((float)($data['vat_percent'] ?? 0), 2);
    $discount = max(0, round((float)($data['discount'] ?? 0), 2));
    $rounding = round((float)($data['rounding'] ?? 0), 2);
    if ($vat_percent < 0 || $vat_percent > 100) {
        ame_sale_fail($is_english, 'ভ্যাট ০ থেকে ১০০-এর মধ্যে হতে হবে।', 'VAT must be between 0 and 100.');
    }

    $customer = ame_sale_customer($db, $customer_id, $is_english);
    $payment_data = ame_sale_payments($db, $data, $is_english);
    $inventory = ame_sale_prepare_items($db, $data['cart'] ?? [], $is_english, false);
    $subtotal = (float)$inventory['subtotal'];
    $vat_amount = round(($subtotal * $vat_percent) / 100, 2);
    $gross_bill = round($subtotal + $vat_amount + $service_amount, 2);
    $sale_bill = round($gross_bill + $rounding - $discount, 2);
    if ($sale_bill <= 0) {
        ame_sale_fail($is_english, 'চূড়ান্ত বিল শূন্যের বেশি হতে হবে।', 'The final bill must be greater than zero.');
    }

    $payment_total = (float)$payment_data['total'];
    $exchange_pack = ame_sale_prepare_exchange($db, $data['exchange'] ?? $data['exchange_items'] ?? [], $is_english);
    $exchange_items = $exchange_pack['items'];
    $exchange_amount = (float)$exchange_pack['total'];
    if ($exchange_amount - 0.009 > $sale_bill) {
        ame_sale_fail($is_english, 'এক্সচেঞ্জ মূল্য বিক্রয়ের মোটের বেশি হতে পারে না।', 'Exchange value cannot exceed the sale total.');
    }
    $settlement = ame_sale_exchange_settlement($sale_bill, $exchange_amount, $payment_total);
    $paid_for_sale = (float)$settlement['paid_for_sale'];
    $current_due = (float)$settlement['current_due'];
    $cash_paid = (float)$settlement['cash_paid'];

    if ($customer_id === null && $paid_for_sale + 0.001 < $sale_bill) {
        ame_sale_fail(
            $is_english,
            'রিটেইল বিক্রয়ে বকেয়া রাখা যাবে না। নগদ নিন অথবা এক্সচেঞ্জ দিন।',
            'A retail sale cannot have an unpaid balance.'
        );
    }
    if ($payment_total > 0.009 && $payment_data['items'] === []) {
        ame_sale_fail(
            $is_english,
            'নগদ/ব্যাংক পেমেন্টের জন্য অ্যাকাউন্ট নির্বাচন করুন।',
            'Select a payment account for cash or bank payment.'
        );
    }

    $same_customer = $customer_id !== null && $customer_id === (int)($sale['customer_id'] ?? 0);
    $live_balance = $customer
        ? (isset($customer['effective_balance']) ? round((float)$customer['effective_balance'], 2) : round((float)$customer['receivable_balance'], 2))
        : 0.0;
    $previous_balance = $same_customer ? $kept_previous : $live_balance;
    $grand_total = ame_sale_collect_total($sale_bill, $previous_balance);
    $extra_payment = (float)$settlement['extra_cash'];
    $due_adjustment = function_exists('ame_sale_resolve_due_adjustment')
        ? ame_sale_resolve_due_adjustment($customer_id, (float)$previous_balance, $extra_payment, !empty($data['adjust_due']))
        : (($customer_id !== null && !empty($data['adjust_due'])) ? $extra_payment : 0.0);
    $change_amount = max(0, $extra_payment - $due_adjustment);
    $customer_balance_after = ame_balance_book(
        'sale',
        $previous_balance,
        $sale_bill,
        $paid_for_sale,
        null,
        $due_adjustment
    )['total'];

    $sale_date_raw = substr(trim((string)($data['sale_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale_date_raw)) {
        $old_when = (string)($sale['sale_date'] ?? $sale['completed_at'] ?? $sale['created_at'] ?? '');
        $sale_date_raw = preg_match('/^\d{4}-\d{2}-\d{2}/', $old_when) ? substr($old_when, 0, 10) : date('Y-m-d');
    }
    $sale_time_raw = substr(trim((string)($data['sale_time'] ?? '')), 0, 8);
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $sale_time_raw)) {
        $old_when = (string)($sale['sale_date'] ?? $sale['completed_at'] ?? '');
        $sale_time_raw = preg_match('/\d{2}:\d{2}:\d{2}/', $old_when, $m) ? $m[0] : date('H:i:s');
    }
    if (strlen($sale_time_raw) === 5) {
        $sale_time_raw .= ':00';
    }
    $completed_at = $sale_date_raw . ' ' . $sale_time_raw;
    $primary_account_id = $payment_data['primary_account_id'];

    $guest = ame_sale_guest_snapshot($data, $customer_id, $is_english);
    $guest_name = $guest['name'];
    $guest_phone = $guest['phone'];
    $guest_note = $guest['note'];

    $upd = $db->prepare(
        "UPDATE sales SET
            customer_id = ?,
            customer_type = ?,
            status = 'completed',
            total_bill = ?,
            discount = ?,
            net_payable = ?,
            paid_amount = ?,
            account_id = ?,
            vat_percent = ?,
            vat_amount = ?,
            service_amount = ?,
            rounding_amount = ?,
            due_amount = ?,
            previous_customer_balance = ?,
            due_adjustment_amount = ?,
            customer_balance_after = ?,
            change_amount = ?,
            sale_date = ?,
            completed_at = ?,
            guest_customer_name = ?,
            guest_customer_phone = ?,
            guest_customer_note = ?
         WHERE id = ?"
    );
    if (!$upd) {
        throw new RuntimeException($is_english ? 'The invoice could not be updated.' : 'ইনভয়েস আপডেট করা যায়নি।');
    }
    $upd->bind_param(
        'isddddidddddddddsssssi',
        $customer_id,
        $customer_type,
        $gross_bill,
        $discount,
        $sale_bill,
        $paid_for_sale,
        $primary_account_id,
        $vat_percent,
        $vat_amount,
        $service_amount,
        $rounding,
        $current_due,
        $previous_balance,
        $due_adjustment,
        $customer_balance_after,
        $change_amount,
        $completed_at,
        $completed_at,
        $guest_name,
        $guest_phone,
        $guest_note,
        $sale_id
    );
    $upd->execute();
    $upd->close();
    if ($customer_id === null) {
        $db->query('UPDATE sales SET customer_id = NULL WHERE id = ' . (int)$sale_id);
    }
    if ($primary_account_id === null || (int)$primary_account_id <= 0) {
        $db->query('UPDATE sales SET account_id = NULL WHERE id = ' . (int)$sale_id);
    }

    $vehicle_no = substr(trim((string)($data['vehicle_no'] ?? '')), 0, 40);
    if (function_exists('ame_schema_columns')) {
        $sales_cols = ame_schema_columns($db, 'sales');
        if (!empty($sales_cols['vehicle_no'])) {
            $vup = $db->prepare('UPDATE sales SET vehicle_no = ? WHERE id = ?');
            if ($vup) {
                $vup->bind_param('si', $vehicle_no, $sale_id);
                $vup->execute();
                $vup->close();
            }
        }
    }

    ame_sale_store_items($db, $sale_id, $inventory['items']);
    ame_sale_log_sale_out_movements($db, $sale_id, $inventory['items'], $customer_id);
    ame_sale_store_exchange_items($db, $sale_id, $exchange_items, true, $customer_id, $is_english);
    ame_sale_set_exchange_columns($db, $sale_id, $exchange_amount, $cash_paid);

    $stored_service_lines = ame_sale_replace_services($db, $sale_id, $service_lines);
    foreach ($stored_service_lines as $service_line) {
        if (function_exists('record_service_commission')) {
            record_service_commission(
                $db,
                $sale_id,
                (int)$service_line['engineer_id'],
                (float)$service_line['amount'],
                (string)($service_line['service_type'] ?? ''),
                'sale_service',
                (int)($service_line['sale_service_id'] ?? 0)
            );
        }
    }

    ame_sale_customer_ledger(
        $db,
        $sale_id,
        $customer_id,
        $sale_bill,
        $cash_paid,
        $due_adjustment,
        $is_english,
        $exchange_amount,
        $previous_balance
    );
    if ($customer_id !== null) {
        $ledger_date = $db->prepare(
            'UPDATE customer_ledger SET txn_date = ? WHERE customer_id = ? AND ref_no IN (?, ?, ?)'
        );
        if ($ledger_date) {
            $sale_ref = 'SALE-' . $sale_id;
            $due_ref = 'DUE-PAY-' . $sale_id;
            $adv_ref = 'ADV-SALE-' . $sale_id;
            $ledger_customer = (int)$customer_id;
            $ledger_date->bind_param('sisss', $sale_date_raw, $ledger_customer, $sale_ref, $due_ref, $adv_ref);
            $ledger_date->execute();
            $ledger_date->close();
        }
    }

    $deposit_amount = $cash_paid + $due_adjustment;
    if (function_exists('ame_sales_set_store')) {
        ame_sales_set_store($db, $sale_id);
    }
    ame_sale_post_payments($db, $sale_id, $payment_data['items'], $deposit_amount, $user_id, $is_english);
    ame_sale_admin_stamp_date($db, $sale_id, $completed_at);

    if (function_exists('log_activity')) {
        log_activity(
            'Sale Invoice Edited',
            'Sales',
            $invoice_number . ', Total: ' . $sale_bill,
            [
                'action_type' => 'edit',
                'entity_type' => 'invoice',
                'entity_id' => (string)$sale_id,
                'reference' => $invoice_number,
                'party_type' => 'customer',
                'party_id' => $customer_id === null ? 0 : (int)$customer_id,
                'amount_total' => (float)$sale_bill,
                'amount_paid' => (float)$paid_for_sale,
                'amount_due' => (float)$current_due,
                'amount_discount' => (float)$discount,
            ]
        );
    }

    return [
        'sale_id' => $sale_id,
        'invoice_number' => $invoice_number,
        'net_payable' => $sale_bill,
        'paid_amount' => $paid_for_sale,
        'due_amount' => $current_due,
    ];
}
