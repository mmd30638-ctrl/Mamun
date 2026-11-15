<?php
/*
 * Professional Sale Completion Controller
 * নতুন Sale এবং Draft Completion
 */

require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/lang_helper.php';
require_once __DIR__ . '/../customers/customer_ledger_functions.php';

require_once '../../includes/sales/sale_common.php';
require_once '../../includes/integrity_functions.php';
ensure_sales_schema($db);
ensure_integrity_schema($db);
require_once '../../includes/sales/sale_inventory.php';
require_once '../../includes/sales/sale_finance.php';
require_once '../../includes/sales/sale_draft.php';

function ame_sale_store_next_pay(mysqli $db, int $saleId, int $customerId, string $raw, float $amount = 0.0): void
{
    $raw = trim($raw);
    if ($saleId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return;
    }
    foreach (['sales' => $saleId, 'customers' => $customerId] as $table => $id) {
        if ($id <= 0) {
            continue;
        }
        $col = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'next_pay_date'");
        if ($col && $col->num_rows === 0) {
            $db->query("ALTER TABLE `{$table}` ADD COLUMN next_pay_date DATE NULL");
        }
        $amtCol = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'next_pay_amount'");
        if ($amtCol && $amtCol->num_rows === 0) {
            $db->query("ALTER TABLE `{$table}` ADD COLUMN next_pay_amount DECIMAL(12,2) NULL DEFAULT 0.00");
        }
        if ($amount > 0) {
            $stmt = $db->prepare("UPDATE `{$table}` SET next_pay_date = ?, next_pay_amount = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('sdi', $raw, $amount, $id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $db->prepare("UPDATE `{$table}` SET next_pay_date = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $raw, $id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    $respond(
        'error',
        $is_english
            ? 'Invalid request method.'
            : 'অনুমোদিত অনুরোধ নয়।'
    );
}

$data = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($data)) {
    http_response_code(400);

    $respond(
        'error',
        $is_english
            ? 'Invalid sales information.'
            : 'বিক্রয়ের তথ্য সঠিক নয়।'
    );
}

// =====================================================================
// নিরাপত্তা ফিক্স: CSRF টোকেন যাচাই (আগে এই ফাইলে কোনো CSRF চেক ছিল না)
// এই এন্ডপয়েন্ট টাকা-পয়সার লেনদেন (বিক্রয় সম্পন্ন) করে, তাই এটি ছাড়া
// অন্য কোনো ওয়েবসাইট থেকে জোর করে সেল এন্ট্রি করানো সম্ভব ছিল।
//
// ফ্রন্টএন্ডে (যে JS ফাইল থেকে এই API-তে fetch/ajax কল হয়) অবশ্যই
// csrf_token()-এর মান request body-তে "csrf_token" নামে পাঠাতে হবে।
// =====================================================================
$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($data['csrf_token'] ?? '');

if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    http_response_code(403);

    $respond(
        'error',
        $is_english
            ? 'Security check failed. Please refresh the page and try again.'
            : 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।'
    );
}

// গাড়ির নম্বর (ঐচ্ছিক) — মেমোতে দেখানোর জন্য, হিসাবের কোনো কলামের সাথে সম্পর্কিত নয়
$vehicle_no = substr(trim((string)($data['vehicle_no'] ?? '')), 0, 40);

$offline_ref = substr(trim((string)($data['offline_client_ref'] ?? $data['client_ref'] ?? '')), 0, 80);
if ($offline_ref !== '') {
    ame_lock($db, 'sale_ref_' . $offline_ref);
    $existing_sale = find_sale_by_offline_ref($db, $offline_ref);
    if ($existing_sale > 0) {
        $respond(
            'success',
            $is_english ? 'This sale was already saved.' : 'এই বিক্রয় আগেই সংরক্ষিত হয়েছে।',
            ['sale_id' => $existing_sale, 'replayed' => true]
        );
    }
    $replay_sale = 0;
    if (!claim_idempotency_token($db, 'sale_submit', $offline_ref, $replay_sale)) {
        $existing_sale = $replay_sale > 0 ? $replay_sale : find_sale_by_offline_ref($db, $offline_ref);
        if ($existing_sale > 0) {
            $respond(
                'success',
                $is_english ? 'This sale was already saved.' : 'এই বিক্রয় আগেই সংরক্ষিত হয়েছে।',
                ['sale_id' => $existing_sale, 'replayed' => true]
            );
        }
    }
}

$action = (string)($data['action'] ?? 'complete');

if ($action !== 'complete') {
    http_response_code(422);

    $respond(
        'error',
        $is_english
            ? 'Unsupported sales action.'
            : 'এই বিক্রয় কার্যক্রম সমর্থিত নয়।'
    );
}

$transaction_started = false;

try {
    $draft_id = (int)($data['draft_id'] ?? 0);

    $customer_id = !empty($data['customer_id'])
        ? (int)$data['customer_id']
        : null;

    $customer_type = $customer_id === null
        ? 'Retail'
        : 'Registered';

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
            'প্রথমে পণ্য অথবা সার্ভিস যোগ করুন।',
            'Add a product or service first.'
        );
    }

    $confirmRapid = !empty($data['confirm_rapid_resubmit']) || !empty($data['confirm_repeat']);
    $saleFingerprint = function_exists('ame_sale_submit_fingerprint')
        ? ame_sale_submit_fingerprint($data)
        : '';
    if (!$confirmRapid && $draft_id <= 0 && $saleFingerprint !== '') {
        $rapidWin = ame_rapid_resubmit_window_seconds();
        $dupGuard = ame_sale_guard_find_recent($db, $user_id, $saleFingerprint, $rapidWin);
        if ($dupGuard !== null) {
            $memo = $dupGuard['invoice_number'] !== ''
                ? $dupGuard['invoice_number']
                : ('INV-' . (int)$dupGuard['id']);
            $respond(
                'confirm',
                ame_sale_duplicate_block_message($dupGuard, $is_english, $rapidWin),
                [
                    'needs_confirm' => true,
                    'blocked_duplicate' => true,
                    'code' => 'AME_SALE_DUPLICATE_MEMO',
                    'sale_id' => (int)$dupGuard['id'],
                    'invoice_number' => $memo,
                    'warnings' => [[
                        'worker_name' => 'বিক্রয়',
                        'service_type' => 'একই মেমো',
                        'amount' => 0,
                        'invoice_number' => $memo,
                        'sale_id' => (int)$dupGuard['id'],
                        'seconds_ago' => (int)$dupGuard['seconds_ago'],
                    ]],
                ]
            );
        }
    }
    if (!$confirmRapid && $draft_id <= 0) {
        $rapidWin = ame_rapid_resubmit_window_seconds();
        if (function_exists('ame_sale_rapid_service_warnings') && $service_lines !== []) {
            $rapidServices = ame_sale_rapid_service_warnings($db, $user_id, $service_lines, $rapidWin);
            if ($rapidServices !== []) {
                $respond(
                    'confirm',
                    ame_sale_rapid_service_message($rapidServices[0], $is_english, $rapidWin),
                    [
                        'needs_confirm' => true,
                        'code' => 'AME_SALE_RAPID_SERVICE',
                        'warnings' => $rapidServices,
                    ]
                );
            }
        }
        if (function_exists('ame_sale_rapid_product_warnings')) {
            $rapidProducts = ame_sale_rapid_product_warnings($db, $user_id, $data['cart'] ?? [], $rapidWin);
            if ($rapidProducts !== []) {
                $respond(
                    'confirm',
                    ame_sale_rapid_confirm_message($rapidProducts[0], $is_english, $rapidWin),
                    [
                        'needs_confirm' => true,
                        'code' => 'AME_SALE_RAPID_PRODUCT',
                        'warnings' => $rapidProducts,
                    ]
                );
            }
        }
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

    if (abs($rounding) > 1000) {
        ame_sale_fail(
            $is_english,
            'রাউন্ডিংয়ের পরিমাণ অতিরিক্ত বড়।',
            'The rounding amount is too large.'
        );
    }

    $db->begin_transaction();
    $transaction_started = true;

    /*
     * Draft হলে মালিকানা ও অবস্থা Lock করে যাচাই।
     */
    if ($draft_id > 0) {
        ame_draft_assert_editable(
            $db,
            $draft_id,
            $user_id,
            $is_admin,
            $is_english
        );
        $GLOBALS['ame_finish_draft_stock'] = true;
    }

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

    /*
     * Draft-এর পুরোনো Stock ব্যবহার না করে
     * Completion-এর সময় বর্তমান Stock যাচাই।
     */
    $inventory = ame_sale_prepare_items(
        $db,
        $data['cart'] ?? [],
        $is_english
    );

    $subtotal = (float)$inventory['subtotal'];

    $auto_flag = @$db->query("SELECT pos_discount_auto FROM invoice_settings WHERE id = 1 LIMIT 1");
    if ($auto_flag instanceof mysqli_result) {
        $auto_row = $auto_flag->fetch_assoc() ?: [];
        if (!empty($auto_row['pos_discount_auto'])) {
            $auto_off = round($subtotal * 12 / 100, 2);
            if ($discount + 0.009 < $auto_off) {
                $discount = $auto_off;
            }
        }
    }

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
            'চূড়ান্ত বিল শূন্যের বেশি হতে হবে।',
            'The final bill must be greater than zero.'
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
    $net_cash_due = (float)$settlement['net_cash_due'];

    if (
        $customer_id === null
        && $paid_for_sale + 0.001 < $grand_total
    ) {
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

    $previous_balance = $customer
        ? (isset($customer['effective_balance']) ? round((float)$customer['effective_balance'], 2) : round((float)$customer['receivable_balance'], 2))
        : 0;

    $credit_limit = $customer
        ? (float)($customer['credit_limit'] ?? 0)
        : 0;

    if ($customer && $credit_limit > 0.009) {
        $projected_due = $previous_balance + $current_due;
        if ($projected_due > $credit_limit + 0.009) {
            ame_sale_fail(
                $is_english,
                'ক্রেডিট লিমিট ছাড়িয়ে গেছে। লিমিট ৳'
                    . number_format($credit_limit, 2)
                    . ', বকেয়া হবে ৳'
                    . number_format($projected_due, 2)
                    . '। নগদ নিন অথবা লিমিট বাড়ান।',
                'Credit limit exceeded. Limit ৳'
                    . number_format($credit_limit, 2)
                    . ', projected due ৳'
                    . number_format($projected_due, 2)
                    . '.'
            );
        }
    }

    $extra_payment = (float)$settlement['extra_cash'];

    $due_adjustment = ame_sale_resolve_due_adjustment(
        $customer_id,
        (float)$previous_balance,
        $extra_payment,
        !empty($data['adjust_due'])
    );

    $change_amount = max(
        0,
        $extra_payment - $due_adjustment
    );

    $sale_date_raw = substr(trim((string)($data['sale_date'] ?? '')), 0, 10);
    $sale_is_admin = isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
    if (!$sale_is_admin || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale_date_raw)) {
        $sale_date_raw = date('Y-m-d');
    }
    $completed_at = $sale_date_raw . ' ' . date('H:i:s');

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

    ame_lock($db, 'sale_user_' . $user_id);
    $GLOBALS['ame_finish_draft_stock'] = $draft_id > 0;
    /*
     * দ্রুত একই পণ্যের বারবার বিক্রি বন্ধ করলে ব্যবসায় বাধা — শুধু offline_client_ref/idempotency
     * একই রিকোয়েস্টের ডাবল সাবমিট ঠেকায়; সময়ভিত্তিক ব্লক তুলে দেওয়া হয়েছে।
     */

    /*
     * Draft Completion অথবা নতুন Sale।
     */
    if ($draft_id > 0) {
        $sale_id = $draft_id;

        /*
         * Draft-এর পরিকল্পিত তথ্য সরিয়ে
         * Final তথ্য একই Sale ID-তে লেখা হবে।
         */
        $delete_items = $db->prepare(
            'DELETE FROM sale_items WHERE sale_id = ?'
        );

        $delete_items->bind_param(
            'i',
            $sale_id
        );

        $delete_items->execute();
        $delete_items->close();

        $delete_services = $db->prepare(
            'DELETE FROM sale_services WHERE sale_id = ?'
        );

        $delete_services->bind_param(
            'i',
            $sale_id
        );

        $delete_services->execute();
        $delete_services->close();

        $delete_payments = $db->prepare(
            "DELETE FROM sale_payments
             WHERE sale_id = ?
               AND payment_status = 'planned'"
        );

        $delete_payments->bind_param(
            'i',
            $sale_id
        );

        $delete_payments->execute();
        $delete_payments->close();

        ame_sale_delete_exchange_items($db, $sale_id);

        $invoice_number = 'INV-'
            . str_replace('-', '', $sale_date_raw)
            . '-'
            . str_pad(
                (string)$sale_id,
                6,
                '0',
                STR_PAD_LEFT
            );

        $update_sale = $db->prepare(
            "UPDATE sales SET
                invoice_number = ?,
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
                draft_note = NULL,
                previous_customer_balance = ?,
                due_adjustment_amount = ?,
                customer_balance_after = ?,
                change_amount = ?,
                completed_at = ?
             WHERE id = ?
               AND status = 'draft'"
        );

        $update_types = 'sis'
            . str_repeat('d', 4)
            . 'i'
            . str_repeat('d', 9)
            . 'si';

        $update_sale->bind_param(
            $update_types,
            $invoice_number,
            $customer_id,
            $customer_type,
            $gross_bill,
            $discount,
            $grand_total,
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
            $sale_id
        );

        $update_sale->execute();

        if ($update_sale->affected_rows !== 1) {
            $update_sale->close();

            throw new RuntimeException(
                $is_english
                    ? 'The draft could not be completed.'
                    : 'Draft থেকে বিক্রয় সম্পন্ন করা যায়নি।'
            );
        }

        $update_sale->close();
    } else {
        $temporary_invoice = 'TMP-'
            . strtoupper(
                bin2hex(random_bytes(6))
            );

        $insert_sale = $db->prepare(
            "INSERT INTO sales (
                invoice_number,
                customer_id,
                customer_type,
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
                previous_customer_balance,
                due_adjustment_amount,
                customer_balance_after,
                change_amount,
                created_by,
                completed_at
             ) VALUES (
                ?, ?, ?, 'completed',
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?
             )"
        );

        $insert_types = 'sis'
            . str_repeat('d', 4)
            . 'i'
            . str_repeat('d', 9)
            . 'is';

        $insert_sale->bind_param(
            $insert_types,
            $temporary_invoice,
            $customer_id,
            $customer_type,
            $gross_bill,
            $discount,
            $grand_total,
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
            $user_id,
            $completed_at
        );

        $insert_sale->execute();
        $sale_id = $insert_sale->insert_id;
        $insert_sale->close();

        $invoice_number = 'INV-'
            . str_replace('-', '', $sale_date_raw)
            . '-'
            . str_pad(
                (string)$sale_id,
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
            $invoice_number,
            $sale_id
        );

        $number_update->execute();
        $number_update->close();
    }

    // গাড়ির নম্বর — আলাদা, সরল UPDATE (হিসাবের মূল INSERT/UPDATE-কে স্পর্শ করে না)
    if ($vehicle_no !== '' && !empty($sale_id) && function_exists('ame_schema_columns')) {
        $sales_cols_for_vehicle = ame_schema_columns($db, 'sales');
        if (!empty($sales_cols_for_vehicle['vehicle_no'])) {
            $vehicle_update = $db->prepare('UPDATE sales SET vehicle_no = ? WHERE id = ?');
            if ($vehicle_update) {
                $vehicle_update->bind_param('si', $vehicle_no, $sale_id);
                $vehicle_update->execute();
                $vehicle_update->close();
            }
        }
    }

    if (!empty($offline_ref) && !empty($sale_id)) {
        attach_sale_offline_ref($db, (int)$sale_id, $offline_ref);
        $dup = find_sale_by_offline_ref($db, $offline_ref);
        if ($dup > 0 && $dup !== (int)$sale_id) {
            throw new RuntimeException(
                $is_english ? 'This sale was already saved.' : 'এই বিক্রয় আগেই সংরক্ষিত হয়েছে।'
            );
        }
    }

    /*
     * Final Stock, Service, Customer ও Payment posting।
     */
    ame_sale_store_items(
        $db,
        $sale_id,
        $inventory['items']
    );

    ame_sale_log_sale_out_movements(
        $db,
        $sale_id,
        $inventory['items'],
        $customer_id
    );

    ame_sale_store_exchange_items(
        $db,
        $sale_id,
        $exchange_items,
        true,
        $customer_id,
        $is_english
    );

    ame_sale_set_exchange_columns(
        $db,
        $sale_id,
        $exchange_amount,
        $cash_paid
    );

    $stored_service_lines = ame_sale_replace_services(
        $db,
        $sale_id,
        $service_lines
    );

    /*
     * WORKER_WORK_LOCK: কমিশন ame_sale_replace_services → ame_engineer_statement_post_stored এ একবার।
     * এখানে দ্বিতীয়বার record_service_commission চালালে কখনো কখনো ডাবল পোস্ট ঝুঁকি থাকে।
     */
    if (!function_exists('ame_engineer_statement_post_stored')) {
        foreach ($stored_service_lines as $service_line) {
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
        $grand_total,
        $cash_paid,
        $due_adjustment,
        $is_english,
        $exchange_amount,
        $previous_balance
    );
    if ($customer_id !== null && $sale_date_raw !== date('Y-m-d')) {
        $ledger_date = $db->prepare(
            "UPDATE customer_ledger
             SET txn_date = ?
             WHERE customer_id = ?
               AND ref_no IN (?, ?, ?)"
        );
        if ($ledger_date) {
            $sale_ref = 'SALE-' . $sale_id;
            $due_ref = 'DUE-PAY-' . $sale_id;
            $adv_ref = 'ADV-SALE-' . $sale_id;
            $ledger_customer = (int)$customer_id;
            $ledger_date->bind_param(
                'sisss',
                $sale_date_raw,
                $ledger_customer,
                $sale_ref,
                $due_ref,
                $adv_ref
            );
            $ledger_date->execute();
            $ledger_date->close();
        }
    }

    $deposit_amount =
        $cash_paid + $due_adjustment;

    if (function_exists('ame_sales_set_store')) {
        ame_sales_set_store($db, $sale_id);
    }

    ame_sale_post_payments(
        $db,
        $sale_id,
        $payment_data['items'],
        $deposit_amount,
        $user_id,
        $is_english
    );

    log_activity(
        $draft_id > 0
            ? 'Sale Draft Completed'
            : 'Sale Completed',
        'Sales',
        $invoice_number
            . ', Total: '
            . $grand_total,
        [
            'action_type' => 'add',
            'entity_type' => 'invoice',
            'entity_id' => (string)(int)$sale_id,
            'reference' => (string)$invoice_number,
            'party_type' => 'customer',
            'party_id' => $customer_id === null ? 0 : (int)$customer_id,
            'amount_total' => (float)$grand_total,
            'amount_paid' => (float)$paid_for_sale,
            'amount_due' => (float)$current_due,
            'amount_discount' => isset($discount_amount) ? (float)$discount_amount : null,
        ]
    );

    $db->commit();
    $transaction_started = false;
    ame_sale_store_next_pay(
        $db,
        (int)$sale_id,
        $customer_id === null ? 0 : (int)$customer_id,
        (string)($data['next_pay_date'] ?? ''),
        (float)($data['next_pay_amount'] ?? 0.0)
    );
    if (!empty($offline_ref) && !empty($sale_id)) {
        finish_idempotency_token($db, 'sale_submit', $offline_ref, (int)$sale_id);
    }
    try {
        ame_provenance_record($db, 'sale', (int)$sale_id, [
            'amount' => (float)$grand_total,
            'label' => (string)$invoice_number,
        ]);
    } catch (Throwable $e) {
        error_log('Sale provenance skipped: ' . $e->getMessage());
    }
    if ($saleFingerprint !== '' && !empty($sale_id) && function_exists('ame_sale_guard_record')) {
        ame_sale_guard_record($db, $user_id, $saleFingerprint, (int)$sale_id, (string)$invoice_number);
    }

    $respond(
        'success',
        $is_english
            ? 'Sale completed successfully.'
            : 'বিক্রয় সফলভাবে সম্পন্ন হয়েছে।',
        [
            'sale_id' => $sale_id,
            'invoice_number' => $invoice_number,
            'grand_total' => $grand_total,
            'paid_amount' => $paid_for_sale,
            'cash_paid_amount' => $cash_paid,
            'exchange_amount' => $exchange_amount,
            'due_amount' => $current_due,
            'previous_balance' => $previous_balance,
            'customer_balance_after' =>
                $customer_balance_after,
            'change_amount' => $change_amount
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
        'Sale processing failed: '
        . $error->getMessage()
    );

    http_response_code(500);

    $respond(
        'error',
        $is_english
            ? 'The sale could not be completed.'
            : 'বিক্রয় সম্পন্ন করা যায়নি। Error Monitor পরীক্ষা করুন।'
    );
}
