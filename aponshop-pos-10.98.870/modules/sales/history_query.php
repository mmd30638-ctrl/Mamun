<?php
/*
 * সম্পূর্ণ (কমপ্লিট) বিক্রয় তালিকা — POS Recent Sales flyout
 */

require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
require_once '../../includes/sales/sale_common.php';
require_once '../../includes/sales/sale_finance.php';
ensure_sales_schema($db);

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

try {
    $sql = "
        SELECT
            s.id,
            s.invoice_number,
            s.net_payable,
            s.sale_date,
            s.created_at,
            s.completed_at,
            COALESCE(
                NULLIF(c.name, ''),
                NULLIF(s.guest_customer_name, ''),
                'Retail Customer'
            ) AS customer_name
        FROM sales s
        LEFT JOIN customers c
            ON c.id = s.customer_id
        WHERE s.status = 'completed'
    ";

    $types = '';
    $params = [];
    $store_id = function_exists('current_store_id') ? current_store_id() : 0;
    $default_store = function_exists('ame_default_store_id') ? ame_default_store_id($db) : 0;
    if ($store_id > 0) {
        if ($store_id === $default_store) {
            $sql .= ' AND (s.store_id = ? OR s.store_id IS NULL)';
        } else {
            $sql .= ' AND s.store_id = ?';
        }
        $types .= 'i';
        $params[] = $store_id;
    }

    /* Every signed-in cashier can print or share every completed memo.
     * Edit stays blocked on the POS list (print + share only). */
    $sql .= ' ORDER BY s.id DESC LIMIT 50';

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

    $sales = [];

    while ($row = $result->fetch_assoc()) {
        $when = (string)($row['completed_at'] ?: $row['sale_date'] ?: '');
        $sales[] = [
            'id' => (int)$row['id'],
            'invoice_number' => (string)$row['invoice_number'],
            'customer_name' => (string)$row['customer_name'],
            'grand_total' => (float)$row['net_payable'],
            'sold_at' => $when,
            'sold_at_label' => ame_sale_history_datetime_label($row),
        ];
    }

    $stmt->close();

    $respond(
        'success',
        $is_english
            ? 'Completed sales loaded.'
            : 'সম্পূর্ণ বিক্রয় লোড হয়েছে।',
        ['sales' => $sales]
    );
} catch (Throwable $error) {
    error_log('Completed sales query failed: ' . $error->getMessage());
    http_response_code(500);
    $respond(
        'error',
        $is_english
            ? 'Completed sales could not be loaded.'
            : 'সম্পূর্ণ বিক্রয় লোড করা যায়নি।'
    );
}
