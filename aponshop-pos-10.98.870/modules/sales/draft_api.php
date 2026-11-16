<?php
/*
 * বিক্রয় Draft Save এবং Cancel API
 */

require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
require_once '../../includes/sales/sale_common.php';
ensure_sales_schema($db);
require_once '../../includes/sales/sale_inventory.php';
require_once '../../includes/sales/sale_finance.php';
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
        $is_english ? 'Please sign in first.' : 'আগে সাইন ইন করুন।'
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    $respond(
        'error',
        $is_english
            ? 'Invalid request method.'
            : 'অনুমোদিত অনুরোধ নয়।'
    );
}

if (function_exists('ame_party_balance_allow_write')) {
    ame_party_balance_allow_write($db);
}

$data = json_decode(
    (string)file_get_contents('php://input'),
    true
);

if (!is_array($data)) {
    http_response_code(400);

    $respond(
        'error',
        $is_english
            ? 'Invalid draft information.'
            : 'Draft-এর তথ্য সঠিক নয়।'
    );
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf === '') {
    $csrf = (string)($data['csrf_token'] ?? '');
}
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    $respond(
        'error',
        $is_english ? 'Security check failed. Refresh the page.' : 'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করুন।'
    );
}

$action = (string)($data['action'] ?? 'save');
$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    /*
     * Draft বাতিল
     */
    if ($action === 'cancel') {
        $draft_id = (int)($data['draft_id'] ?? 0);

        if ($draft_id <= 0) {
            ame_sale_fail(
                $is_english,
                'সঠিক Draft নির্বাচন করুন।',
                'Select a valid draft.'
            );
        }

        ame_draft_cancel(
            $db,
            $draft_id,
            $user_id,
            $is_admin,
            $is_english
        );

        log_activity(
            'Sale Draft Cancelled',
            'Sales',
            'Draft ID: ' . $draft_id
        );

        $db->commit();
        $transaction_started = false;

        $respond(
            'success',
            $is_english
                ? 'Draft cancelled successfully.'
                : 'Draft সফলভাবে বাতিল হয়েছে।',
            ['draft_id' => $draft_id]
        );
    }

    if ($action !== 'save') {
        ame_sale_fail(
            $is_english,
            'এই Draft কার্যক্রম সমর্থিত নয়।',
            'Unsupported draft action.'
        );
    }

    /*
     * Draft-এর সাধারণ তথ্য
     */
    $draft_id = (int)($data['draft_id'] ?? 0);

    $customer_id = !empty($data['customer_id'])
        ? (int)$data['customer_id']
        : null;

    $customer_type = $customer_id === null
        ? 'Retail'
        : 'Registered';

    $guest = ame_sale_guest_snapshot(
        $data,
        $customer_id,
        $is_english
    );

    $guest_customer_name = $guest['name'];
    $guest_customer_phone = $guest['phone'];
    $guest_customer_note = $guest['note'];

    $draft_note = substr(
        trim((string)($data['draft_note'] ?? '')),
        0,
        255
    );

    // গাড়ির নম্বর — আগে এখানে সংরক্ষণই করা হতো না, তাই Draft সেভ করে পরে এডিট
    // করতে আনলে ফিল্ডটা খালি দেখাতো (শুধু Complete Sale-এর সময় process_sale.php
    // এটা সংরক্ষণ করত)। এখন Draft সেভের সময়ও এটা রাখা হচ্ছে।
    $vehicle_no = substr(
        trim((string)($data['vehicle_no'] ?? '')),
        0,
        40
    );

    $parsed_services = ame_sale_parse_services(
        $data,
        $is_english,
        $db
    );
    $service_lines = $parsed_services['lines'];
    $service_amount = $parsed_services['total'];

    if (
        empty($data['cart'])
        && $service_amount <= 0
    ) {
        ame_sale_fail(
            $is_english,
            'Draft Save করার আগে পণ্য অথবা সার্ভিস যোগ করুন।',
            'Add a product or service before saving the draft.'
        );
    }

    ame_sale_assert_service_engineers(
        $db,
        $service_lines,
        $is_english
    );

    $vat_percent = round(
        (float)($data['vat_percent'] ?? 0),
        2
    );

    $discount = max(
        0,
        round(
            (float)($data['discount'] ?? 0),
            2
        )
    );

    $rounding = round(
        (float)($data['rounding'] ?? 0),
        2
    );

    if ($vat_percent < 0 || $vat_percent > 100) {
        ame_sale_fail(
            $is_english,
            'ভ্যাট ০ থেকে ১০০-এর মধ্যে হতে হবে।',
            'VAT must be between 0 and 100.'
        );
    }

    /*
     * Customer, Payment ও Product যাচাই
     */
    $customer = ame_sale_customer(
        $db,
        $customer_id,
        $is_english
    );

    $payment_data = ame_sale_payments(
        $db,
        $data,
        $is_english
    );

    $inventory = ame_sale_prepare_items(
        $db,
        $data['cart'] ?? [],
        $is_english,
        false
    );

    $subtotal = (float)$inventory['subtotal'];

    $vat_amount = round(
        ($subtotal * $vat_percent) / 100,
        2
    );

    $gross_bill = round(
        $subtotal
        + $vat_amount
        + $service_amount,
        2
    );

    $grand_total = round(
        $gross_bill
        + $rounding
        - $discount,
        2
    );

    if ($grand_total <= 0) {
        ame_sale_fail(
            $is_english,
            'Draft-এর মোট বিল শূন্যের বেশি হতে হবে।',
            'The draft total must be greater than zero.'
        );
    }

    $payment_total = (float)$payment_data['total'];

    $exchange_pack = ame_sale_prepare_exchange(
        $db,
        $data['exchange'] ?? $data['exchange_items'] ?? [],
        $is_english
    );
    $exchange_items = $exchange_pack['items'];
    $exchange_amount = (float)$exchange_pack['total'];

    if ($exchange_amount - 0.009 > $grand_total) {
        ame_sale_fail(
            $is_english,
            'এক্সচেঞ্জ মূল্য বিক্রয়ের মোটের বেশি হতে পারে না।',
            'Exchange value cannot exceed the sale total.'
        );
    }

    $settlement = ame_sale_exchange_settlement(
        $grand_total,
        $exchange_amount,
        $payment_total
    );
    $paid_for_sale = (float)$settlement['paid_for_sale'];
    $current_due = (float)$settlement['current_due'];
    $cash_paid = (float)$settlement['cash_paid'];

    $previous_balance = $customer
        ? (isset($customer['effective_balance']) ? round((float)$customer['effective_balance'], 2) : round((float)$customer['receivable_balance'], 2))
        : 0;

    $extra_payment = (float)$settlement['extra_cash'];

    $due_adjustment = function_exists('ame_sale_resolve_due_adjustment')
        ? ame_sale_resolve_due_adjustment($customer_id, (float)$previous_balance, $extra_payment, !empty($data['adjust_due']))
        : (($customer_id !== null && !empty($data['adjust_due'])) ? $extra_payment : 0);

    $change_amount = max(
        0,
        $extra_payment - $due_adjustment
    );

    $customer_balance_after = ame_balance_book(
        'sale',
        $previous_balance,
        $grand_total,
        $paid_for_sale,
        null,
        $due_adjustment
    )['total'];

    $primary_account_id =
        $payment_data['primary_account_id'];

    $customer_id_bind = (int)($customer_id ?? 0);
    $account_id_bind = (int)($primary_account_id ?? 0);
    $guest_customer_name = (string)$guest_customer_name;
    $guest_customer_phone = (string)$guest_customer_phone;
    $guest_customer_note = (string)$guest_customer_note;
    $draft_note = (string)$draft_note;
    $customer_type = (string)$customer_type;

    /*
     * পুরোনো Draft Update
     */
    if ($draft_id > 0) {
        $existing_draft = ame_draft_assert_editable(
            $db,
            $draft_id,
            $user_id,
            $is_admin,
            $is_english
        );

        $update = $db->prepare(
            "UPDATE sales SET
                customer_id = NULLIF(?, 0),
                customer_type = ?,
                guest_customer_name = ?,
                guest_customer_phone = ?,
                guest_customer_note = ?,
                total_bill = ?,
                discount = ?,
                net_payable = ?,
                paid_amount = ?,
                account_id = NULLIF(?, 0),
                vat_percent = ?,
                vat_amount = ?,
                service_amount = ?,
                rounding_amount = ?,
                due_amount = ?,
                draft_note = ?,
                previous_customer_balance = ?,
                due_adjustment_amount = ?,
                customer_balance_after = ?,
                change_amount = ?,
                completed_at = NULL
             WHERE id = ?
               AND status = 'draft'"
        );

        if (!$update) {
            throw new RuntimeException(
                'Draft update prepare failed: ' . $db->error
            );
        }

        $types = 'is'
            . str_repeat('s', 3)
            . str_repeat('d', 4)
            . 'i'
            . str_repeat('d', 5)
            . 's'
            . str_repeat('d', 4)
            . 'i';

        $update->bind_param(
            $types,
            $customer_id_bind,
            $customer_type,
            $guest_customer_name,
            $guest_customer_phone,
            $guest_customer_note,
            $gross_bill,
            $discount,
            $grand_total,
            $paid_for_sale,
            $account_id_bind,
            $vat_percent,
            $vat_amount,
            $service_amount,
            $rounding,
            $current_due,
            $draft_note,
            $previous_balance,
            $due_adjustment,
            $customer_balance_after,
            $change_amount,
            $draft_id
        );

        if (!$update->execute()) {
            throw new RuntimeException(
                $update->error ?: $db->error
            );
        }
        $update->close();

        $draft_number =
            $existing_draft['invoice_number'];
    } else {
        /*
         * নতুন Draft তৈরি
         */
        $temporary_number = 'DRF-TMP-'
            . strtoupper(bin2hex(random_bytes(5)));

        $insert = $db->prepare(
            "INSERT INTO sales (
                invoice_number,
                customer_id,
                customer_type,
                guest_customer_name,
                guest_customer_phone,
                guest_customer_note,
                status,
                total_bill,
                discount,
                net_payable,
                paid_amount,
                account_id,
                vat_percent,
                vat_amount,
                service_amount,
                rounding_amount,
                due_amount,
                draft_note,
                created_by,
                previous_customer_balance,
                due_adjustment_amount,
                customer_balance_after,
                change_amount,
                completed_at
             ) VALUES (
                ?, NULLIF(?, 0), ?, ?, ?, ?, 'draft',
                ?, ?, ?, ?, NULLIF(?, 0),
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                NULL
             )"
        );

        if (!$insert) {
            throw new RuntimeException(
                'Draft insert prepare failed: ' . $db->error
            );
        }

        $types = 'sis'
            . str_repeat('s', 3)
            . str_repeat('d', 4)
            . 'i'
            . str_repeat('d', 5)
            . 'si'
            . str_repeat('d', 4);

        $insert->bind_param(
            $types,
            $temporary_number,
            $customer_id_bind,
            $customer_type,
            $guest_customer_name,
            $guest_customer_phone,
            $guest_customer_note,
            $gross_bill,
            $discount,
            $grand_total,
            $paid_for_sale,
            $account_id_bind,
            $vat_percent,
            $vat_amount,
            $service_amount,
            $rounding,
            $current_due,
            $draft_note,
            $user_id,
            $previous_balance,
            $due_adjustment,
            $customer_balance_after,
            $change_amount
        );

        if (!$insert->execute()) {
            throw new RuntimeException(
                $insert->error ?: $db->error
            );
        }
        $draft_id = (int)$insert->insert_id;
        $insert->close();

        if ($draft_id <= 0) {
            throw new RuntimeException(
                $is_english
                    ? 'The draft row was not created.'
                    : 'Draft রো তৈরি হয়নি।'
            );
        }

        $draft_number = 'DRF-'
            . date('Ymd')
            . '-'
            . str_pad(
                (string)$draft_id,
                6,
                '0',
                STR_PAD_LEFT
            );

        $number_update = $db->prepare(
            'UPDATE sales
             SET invoice_number = ?
             WHERE id = ?'
        );

        $number_update->bind_param(
            'si',
            $draft_number,
            $draft_id
        );

        $number_update->execute();
        $number_update->close();
    }

    /*
     * Draft-এর বিস্তারিত সংরক্ষণ
     */
    if (function_exists('ame_sales_set_store')) {
        ame_sales_set_store($db, $draft_id);
    }

    // গাড়ির নম্বর — insert/update দুটো ক্ষেত্রেই (নতুন Draft ও পুরোনো Draft
    // এডিট দুই অবস্থাতেই) $draft_id এতক্ষণে নিশ্চিত, তাই এখানেই একবারে সেট করা
    // হচ্ছে (খালি করে দিলে খালিও সংরক্ষণ হবে, ঠিক অন্যান্য ফিল্ডের মতোই)।
    if (function_exists('ame_schema_columns')) {
        $sales_cols_for_vehicle = ame_schema_columns($db, 'sales');
        if (!empty($sales_cols_for_vehicle['vehicle_no'])) {
            $vehicle_update = $db->prepare('UPDATE sales SET vehicle_no = ? WHERE id = ?');
            if ($vehicle_update) {
                $vehicle_update->bind_param('si', $vehicle_no, $draft_id);
                $vehicle_update->execute();
                $vehicle_update->close();
            }
        }
    }

    ame_draft_store_items(
        $db,
        $draft_id,
        $inventory['items']
    );

    ame_sale_store_exchange_items(
        $db,
        $draft_id,
        $exchange_items,
        false,
        $customer_id,
        $is_english
    );

    ame_sale_set_exchange_columns(
        $db,
        $draft_id,
        $exchange_amount,
        $cash_paid
    );

    ame_draft_store_services(
        $db,
        $draft_id,
        $service_lines
    );

    ame_draft_store_payments(
        $db,
        $draft_id,
        $payment_data['items'],
        $user_id
    );

    log_activity(
        'Sale Draft Saved',
        'Sales',
        $draft_number
    );

    $db->commit();
    $transaction_started = false;

    $respond(
        'success',
        $is_english
            ? 'Draft saved successfully.'
            : 'Draft সফলভাবে সংরক্ষণ হয়েছে।',
        [
            'draft_id' => $draft_id,
            'draft_number' => $draft_number,
            'grand_total' => $grand_total
        ]
    );
} catch (InvalidArgumentException $error) {
    if ($transaction_started) {
        $db->rollback();
    }

    http_response_code(422);
    $respond('error', $error->getMessage());
} catch (Throwable $error) {
    if ($transaction_started) {
        $db->rollback();
    }

    error_log(
        'Sale draft failed: '
        . $error->getMessage()
    );

    http_response_code(500);

    $respond(
        'error',
        $is_english
            ? 'The draft could not be saved.'
            : 'Draft সংরক্ষণ করা যায়নি। Error Monitor পরীক্ষা করুন।'
    );
}