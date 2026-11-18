<?php
/*
 * Draft List ও Load করার Read-only API
 */

require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
require_once '../../includes/sales/sale_common.php';
ensure_sales_schema($db);
require_once '../../includes/sales/sale_draft.php';

header('Content-Type: application/json; charset=UTF-8');

$is_english = current_lang() === 'en';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$is_admin = strtolower(
    (string)($_SESSION['role'] ?? '')
) === 'admin';

$respond = static function (
    $status,
    $message,
    $extra = []
) {
    echo json_encode(
        array_merge([
            'status' => $status,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit();
};

if ($user_id <= 0) {
    http_response_code(401);
    $respond(
        'error',
        $is_english
            ? 'Please sign in first.'
            : 'আগে সাইন ইন করুন।'
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    $respond(
        'error',
        $is_english
            ? 'Invalid request method.'
            : 'অনুমোদিত অনুরোধ নয়।'
    );
}

$action = (string)($_GET['action'] ?? 'list');

try {
    /*
     * Draft তালিকা
     */
    if ($action === 'list') {
        $sql = "
            SELECT
                s.id,
                s.customer_id,
                s.invoice_number,
                c.customer_code,
                COALESCE(
                    NULLIF(c.name, ''),
                    NULLIF(s.guest_customer_name, ''),
                    'Retail Customer'
                ) AS customer_name
            FROM sales s
            LEFT JOIN customers c
                ON c.id = s.customer_id
            WHERE s.status = 'draft'
        ";

        $types = '';
        $params = [];
        $store_id = function_exists('current_store_id') ? current_store_id() : 0;
        $default_store = function_exists('ame_default_store_id') ? ame_default_store_id($db) : 0;
        if ($store_id > 0) {
            /* পুরনো/রি-ওপেন ড্রাফটে store_id NULL থাকতে পারে — সব কাউন্টারে দেখা উচিত */
            $sql .= ' AND (s.store_id = ? OR s.store_id IS NULL OR s.store_id = 0)';
            $types .= 'i';
            $params[] = $store_id;
        }

        $sql .= ' ORDER BY COALESCE(s.updated_at, s.created_at, s.sale_date) DESC LIMIT 80';

        $stmt = $db->prepare($sql);

        if ($types !== '') {
            $bind = [$types];
            foreach ($params as $i => $value) {
                $bind[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $drafts = [];

        while ($draft = $result->fetch_assoc()) {
            $label = (string)$draft['customer_name'];
            $inv = trim((string)($draft['invoice_number'] ?? ''));
            if ($inv !== '') {
                $label .= ' · ' . $inv;
            }
            $drafts[] = [
                'id' => (int)$draft['id'],
                'customer_id' => $draft['customer_id']
                    ? (int)$draft['customer_id']
                    : null,
                'customer_name' => $label,
                'customer_code' =>
                    (string)($draft['customer_code'] ?? '')
            ];
        }

        $stmt->close();

        $respond(
            'success',
            $is_english
                ? 'Draft list loaded.'
                : 'Draft তালিকা লোড হয়েছে।',
            ['drafts' => $drafts]
        );
    }

    /*
     * নির্দিষ্ট Draft Load
     */
    if ($action === 'load') {
        $draft_id = (int)($_GET['id'] ?? 0);

        if ($draft_id <= 0) {
            throw new InvalidArgumentException(
                $is_english
                    ? 'Select a valid draft.'
                    : 'সঠিক Draft নির্বাচন করুন।'
            );
        }

        $draft = ame_draft_find(
            $db,
            $draft_id,
            $user_id,
            $is_admin,
            false
        );

        if (!$draft) {
            http_response_code(404);

            $respond(
                'error',
                $is_english
                    ? 'Draft not found or permission denied.'
                    : 'Draft পাওয়া যায়নি অথবা অনুমতি নেই।'
            );
        }

        /*
         * Draft-এর Product
         */
        $item_stmt = $db->prepare(
            "SELECT
                si.product_id AS id,
                si.item_name,
                si.qty,
                si.sale_price AS saved_price,
                p.sku,
                p.name,
                p.stock,
                p.sale_price,
                p.wholesale_price,
                p.dealer_price
             FROM sale_items si
             LEFT JOIN products p
                ON p.id = si.product_id
             WHERE si.sale_id = ?
             ORDER BY si.id ASC"
        );

        $item_stmt->bind_param(
            'i',
            $draft_id
        );

        $item_stmt->execute();
        $item_result = $item_stmt->get_result();

        $items = [];
        $store_for_stock = function_exists('current_store_id') ? (int)current_store_id() : 0;
        $reserved_map = [];
        try {
            if (function_exists('ame_workshop_reserved_map')) {
                $reserved_map = ame_workshop_reserved_map($db, $store_for_stock);
            }
        } catch (Throwable $reserveError) {
            $reserved_map = [];
        }

        while ($item = $item_result->fetch_assoc()) {
            $is_custom = $item['id'] === null
                || $item['id'] === ''
                || (int)$item['id'] <= 0;
            $product_id = $is_custom ? 0 : (int)$item['id'];
            $on_hand = (float)$item['stock'];
            if ($product_id > 0 && $store_for_stock > 0 && function_exists('ame_store_stock_qty')) {
                $on_hand = (float)ame_store_stock_qty($db, $store_for_stock, $product_id);
            }
            $reserved = (float)($reserved_map[$product_id] ?? 0);

            $items[] = [
                'id' => $is_custom ? null : $product_id,
                'is_custom' => $is_custom,
                'sku' => $is_custom ? 'CUSTOM' : (string)($item['sku'] ?? ''),
                'name' => $is_custom
                    ? (string)($item['item_name'] ?? '')
                    : (string)$item['name'],
                'qty' => (float)$item['qty'],
                'stock' => $is_custom ? null : ame_stock_sellable($on_hand, $reserved),
                'saved_price' =>
                    (float)$item['saved_price'],
                'sale_price' => $is_custom
                    ? (float)$item['saved_price']
                    : (float)$item['sale_price'],
                'wholesale_price' => $is_custom
                    ? (float)$item['saved_price']
                    : (float)$item['wholesale_price'],
                'dealer_price' => $is_custom
                    ? (float)$item['saved_price']
                    : (float)$item['dealer_price']
            ];
        }

        $item_stmt->close();

        /*
         * Draft-এর Service — একটি ইনভয়েসে একাধিক ইঞ্জিনিয়ার বিল
         */
        $service_stmt = $db->prepare(
            'SELECT
                engineer_id,
                service_type,
                amount
             FROM sale_services
             WHERE sale_id = ?
             ORDER BY id ASC'
        );

        $service_stmt->bind_param(
            'i',
            $draft_id
        );

        $service_stmt->execute();
        $service_result = $service_stmt->get_result();

        $services = [];

        while ($service_row = $service_result->fetch_assoc()) {
            $services[] = [
                'engineer_id' =>
                    (int)$service_row['engineer_id'],
                'service_type' =>
                    (string)$service_row['service_type'],
                'amount' =>
                    (float)$service_row['amount']
            ];
        }

        $service_stmt->close();

        $service = $services[0] ?? null;

        /*
         * Draft-এর Planned Payment
         */
        $payment_stmt = $db->prepare(
            "SELECT
                sp.account_id,
                sp.amount,
                sp.reference_number,
                a.account_name
             FROM sale_payments sp
             LEFT JOIN accounts a
                ON a.id = sp.account_id
             WHERE sp.sale_id = ?
               AND sp.payment_status = 'planned'
             ORDER BY sp.id ASC"
        );

        $payment_stmt->bind_param(
            'i',
            $draft_id
        );

        $payment_stmt->execute();
        $payment_result = $payment_stmt->get_result();

        $payments = [];

        while ($payment = $payment_result->fetch_assoc()) {
            $account_id = (int)$payment['account_id'];
            $amount = (float)$payment['amount'];
            if ($account_id <= 0 || $amount <= 0) {
                continue;
            }
            $payments[] = [
                'account_id' => $account_id,
                'account_name' =>
                    (string)($payment['account_name'] ?? ''),
                'amount' => $amount,
                'reference_number' =>
                    (string)(
                        $payment['reference_number']
                        ?? ''
                    )
            ];
        }

        $payment_stmt->close();

        $exchange_items = ame_sale_load_exchange_items($db, $draft_id);

        $customer_name = '';
        $customer_phone = '';
        $customer_alt = '';
        $customer_code = '';
        $customer_due = 0.0;
        $customer_credit = 0.0;
        if (!empty($draft['customer_id'])) {
            $cid = (int)$draft['customer_id'];
            $customer_stmt = $db->prepare(
                "SELECT
                    name,
                    phone,
                    alt_phone,
                    customer_code,
                    receivable_balance,
                    credit_limit
                 FROM customers
                 WHERE id = ?
                 LIMIT 1"
            );
            if ($customer_stmt) {
                $customer_stmt->bind_param('i', $cid);
                $customer_stmt->execute();
                $customer_row = $customer_stmt->get_result()->fetch_assoc();
                $customer_stmt->close();
                if ($customer_row) {
                    $customer_name = (string)($customer_row['name'] ?? '');
                    $customer_phone = (string)($customer_row['phone'] ?? '');
                    $customer_alt = (string)($customer_row['alt_phone'] ?? '');
                    $customer_code = (string)(
                        $customer_row['customer_code']
                        ?? $cid
                    );
                    $customer_due = (float)($customer_row['receivable_balance'] ?? 0);
                    $customer_credit = (float)($customer_row['credit_limit'] ?? 0);
                }
            }
        }

        $respond(
            'success',
            $is_english
                ? 'Draft loaded successfully.'
                : 'Draft সফলভাবে লোড হয়েছে।',
            [
                'draft' => [
                    'id' => (int)$draft['id'],
                    'draft_number' =>
                        (string)$draft['invoice_number'],
                    'customer_id' =>
                        $draft['customer_id']
                            ? (int)$draft['customer_id']
                            : null,
                    'customer_type' =>
                        (string)$draft['customer_type'],
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'customer_alt' => $customer_alt,
                    'customer_code' => $customer_code,
                    'customer_due' => $customer_due,
                    'customer_credit' => $customer_credit,
                    'guest_customer_name' =>
                        (string)(
                            $draft['guest_customer_name']
                            ?? ''
                        ),
                    'guest_customer_phone' =>
                        (string)(
                            $draft['guest_customer_phone']
                            ?? ''
                        ),
                    'guest_customer_note' =>
                        (string)(
                            $draft['guest_customer_note']
                            ?? ''
                        ),
                    'discount' =>
                        (float)$draft['discount'],
                    'vat_percent' =>
                        (float)$draft['vat_percent'],
                    'rounding' =>
                        (float)$draft['rounding_amount'],
                    'draft_note' =>
                        (string)(
                            $draft['draft_note'] ?? ''
                        ),
                    'vehicle_no' =>
                        (string)(
                            $draft['vehicle_no'] ?? ''
                        ),
                    'adjust_due' =>
                        (float)$draft[
                            'due_adjustment_amount'
                        ] > 0,
                    'items' => $items,
                    'service' => $service,
                    'services' => $services,
                    'payments' => $payments,
                    'exchange' => $exchange_items,
                ]
            ]
        );
    }

    throw new InvalidArgumentException(
        $is_english
            ? 'Unsupported draft action.'
            : 'এই Draft কার্যক্রম সমর্থিত নয়।'
    );
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    $respond('error', $error->getMessage());
} catch (Throwable $error) {
    error_log(
        'Draft query failed: '
        . $error->getMessage()
    );

    http_response_code(500);

    $respond(
        'error',
        $is_english
            ? 'Draft information could not be loaded.'
            : 'Draft-এর তথ্য লোড করা যায়নি।'
    );
}