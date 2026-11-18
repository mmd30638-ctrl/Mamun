<?php
/*
 * POS থেকে দ্রুত Registered Customer তৈরি
 */

require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';
require_once __DIR__ . '/../customers/customer_schema.php';
require_once __DIR__ . '/../customers/customer_identity.php';
ensure_customer_tables($db);

header('Content-Type: application/json; charset=UTF-8');

$is_english = current_lang() === 'en';

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
            ? 'Invalid customer information.'
            : 'Customer-এর তথ্য সঠিক নয়।'
    );
}

$session_token = (string)(
    $_SESSION['pos_customer_csrf'] ?? ''
);

$request_token = (string)(
    $data['csrf_token'] ?? ''
);

if (
    $session_token === ''
    || $request_token === ''
    || !hash_equals(
        $session_token,
        $request_token
    )
) {
    http_response_code(403);

    $respond(
        'error',
        $is_english
            ? 'The security token is invalid. Refresh the page.'
            : 'নিরাপত্তা Token সঠিক নয়। পেজ Refresh করুন।'
    );
}

$transaction_started = false;

try {
    $name = trim(
        (string)($data['name'] ?? '')
    );

    if (
        strlen($name) < 2
        || strlen($name) > 150
    ) {
        throw new InvalidArgumentException(
            $is_english
                ? 'Enter a valid customer name.'
                : 'সঠিক Customer নাম লিখুন।'
        );
    }

    $phone = preg_replace(
        '/(?!^\+)[^\d]/',
        '',
        trim((string)($data['phone'] ?? ''))
    );

    if (
        strlen($phone) < 7
        || strlen($phone) > 20
    ) {
        throw new InvalidArgumentException(
            $is_english
                ? 'Enter a valid mobile number.'
                : 'সঠিক Mobile নম্বর লিখুন।'
        );
    }

    $address = substr(
        trim((string)($data['address'] ?? '')),
        0,
        500
    );

    $opening_amount = max(
        0,
        round(
            (float)($data['opening_amount'] ?? 0),
            2
        )
    );

    $opening_type = (
        ($data['opening_type'] ?? 'due')
        === 'advance'
    )
        ? 'advance'
        : 'due';

    $next_due_date = trim(
        (string)($data['next_due_date'] ?? $data['due_date'] ?? '')
    );

    if (
        $next_due_date !== ''
        && !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $next_due_date
        )
    ) {
        throw new InvalidArgumentException(
            $is_english
                ? 'Enter a valid due date.'
                : 'সঠিক Due date নির্বাচন করুন।'
        );
    }

    if (
        $opening_type === 'due'
        && $opening_amount > 0
        && $next_due_date === ''
    ) {
        throw new InvalidArgumentException(
            $is_english
                ? 'Select a due date for the opening due.'
                : 'প্রারম্ভিক বকেয়ার জন্য Due date নির্বাচন করুন।'
        );
    }

    $note = substr(
        trim((string)($data['note'] ?? '')),
        0,
        255
    );

    $stored_phone = ame_customer_phone_store($phone);
    if ($stored_phone !== null) {
        $phone = $stored_phone;
    }

    $taken = ame_customer_identity_taken($db, '', $phone, '', 0);
    if ($taken !== null) {
        throw new InvalidArgumentException(
            $is_english
                ? 'A customer already uses this mobile number.'
                : $taken
        );
    }

    $db->begin_transaction();
    $transaction_started = true;

    $customer_code = generate_customer_code($db);
    $code_taken = ame_customer_identity_taken($db, $customer_code, '', '', 0);
    if ($code_taken !== null) {
        throw new InvalidArgumentException(
            $is_english
                ? 'This customer ID is already used.'
                : $code_taken
        );
    }

    $due_date_value = $next_due_date !== ''
        ? $next_due_date
        : null;

    $note_value = $note !== ''
        ? $note
        : null;

    $due_watch_checked = !empty($data['due_watch'])
        && $data['due_watch'] !== '0'
        && $data['due_watch'] !== false;
    [$due_watch, $due_watch_on] = ame_due_watch_from_post($due_watch_checked);

    $insert = $db->prepare(
        'INSERT INTO customers (
            customer_code,
            name,
            phone,
            address,
            is_customer,
            is_supplier,
            is_installment,
            is_rental,
            credit_limit,
            additional_notes,
            next_due_date,
            due_note,
            due_watch,
            due_watch_on
         ) VALUES (
            ?, ?, ?, ?,
            1, 0, 0, 0,
            0, ?, ?, ?, ?, ?
         )'
    );

    $insert->bind_param(
        'sssssssis',
        $customer_code,
        $name,
        $phone,
        $address,
        $note_value,
        $due_date_value,
        $note_value,
        $due_watch,
        $due_watch_on
    );

    $insert->execute();
    $customer_id = $insert->insert_id;
    $insert->close();

    if ($opening_amount > 0) {
        if ($opening_type === 'advance') {
            add_ledger_entry(
                $db,
                $customer_id,
                'OPENING_ADVANCE',
                'OPEN-' . $customer_id,
                'POS Quick Customer — Opening Advance',
                0,
                $opening_amount
            );
        } else {
            add_ledger_entry(
                $db,
                $customer_id,
                'OPENING_DUE',
                'OPEN-' . $customer_id,
                'POS Quick Customer — Opening Due',
                $opening_amount,
                0
            );
        }
    }

    log_activity(
        'Quick Customer Added',
        'Customers',
        'Customer ID: '
            . $customer_id
            . ', Code: '
            . $customer_code
    );

    $db->commit();
    $transaction_started = false;

    $balance = $opening_type === 'advance'
        ? -$opening_amount
        : $opening_amount;

    $respond(
        'success',
        $is_english
            ? 'Customer saved successfully.'
            : 'Customer সফলভাবে সংরক্ষণ হয়েছে।',
        [
            'customer' => [
                'id' => $customer_id,
                'customer_code' => $customer_code,
                'name' => $name,
                'phone' => $phone,
                'receivable_balance' => $balance
            ]
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
        'Quick customer save failed: '
        . $error->getMessage()
    );

    http_response_code(500);

    $respond(
        'error',
        $is_english
            ? 'The customer could not be saved.'
            : 'Customer সংরক্ষণ করা যায়নি। Error Monitor পরীক্ষা করুন।'
    );
}