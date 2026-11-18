<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';

ensure_customer_tables($db);
if (function_exists('ame_customer_status_boot')) {
    ame_customer_status_boot($db);
}

/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function taka($value): string
{
    return number_format(
        (float)$value,
        2
    );
}

/* =========================================================
   CUSTOMER ID
========================================================= */

$customer_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$customer_id || $customer_id <= 0) {
    http_response_code(400);
    die('অবৈধ কাস্টমার আইডি!');
}

/* =========================================================
   AUTO RENTAL ACCRUAL
========================================================= */

if (function_exists('accrue_rental_charges')) {
    accrue_rental_charges($db);
}

/* =========================================================
   LOAD CUSTOMER
========================================================= */

$stmt = $db->prepare("
    SELECT *
    FROM customers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die('Customer query প্রস্তুত করা যায়নি।');
}

$stmt->bind_param(
    "i",
    $customer_id
);

$stmt->execute();

$result = $stmt->get_result();

$customer = $result->fetch_assoc();

$stmt->close();

if (!$customer) {
    http_response_code(404);
    die('কাস্টমার খুঁজে পাওয়া যায়নি!');
}

if (is_file(__DIR__ . '/../../includes/party_purge.php')) {
    require_once __DIR__ . '/../../includes/party_purge.php';
}
if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
$is_purged = function_exists('ame_customer_is_purged') && ame_customer_is_purged($customer);
$can_party_purge = function_exists('ame_party_purge_can') && ame_party_purge_can();

$can_toggle_wholesale_price = false;
if (!empty($customer['is_customer'])) {
    if ((function_exists('ame_is_super_admin') && ame_is_super_admin())
        || (function_exists('ame_is_admin_like') && ame_is_admin_like())
        || !empty($_SESSION['is_super_admin'])) {
        $can_toggle_wholesale_price = true;
    } else {
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        if (in_array($role, ['admin', 'super_admin', 'superadmin', 'owner'], true)) {
            $can_toggle_wholesale_price = true;
        }
    }
}
$always_wholesale = !empty($customer['always_wholesale']);

/* =========================================================
   LOAD LEDGER
========================================================= */

$ledgers = [];

$stmt = $db->prepare("
    SELECT *
    FROM customer_ledger
    WHERE customer_id = ?
    ORDER BY id ASC
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $customer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $ledgers[] = $row;
    }

    $stmt->close();
}

/* =========================================================
   LOAD INSTALLMENTS
========================================================= */

$installments = [];

$stmt = $db->prepare("
    SELECT *
    FROM installment_contracts
    WHERE customer_id = ?
    ORDER BY id DESC
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $customer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $installments[] = $row;
    }

    $stmt->close();
}

/* =========================================================
   LOAD RENTALS
========================================================= */

$rentals = [];

$stmt = $db->prepare("
    SELECT *
    FROM rental_contracts
    WHERE customer_id = ?
    ORDER BY id DESC
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $customer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rentals[] = $row;
    }

    $stmt->close();
}

/* =========================================================
   BALANCE
========================================================= */

$net_balance = (float)(
    $customer['receivable_balance'] ?? 0
);

$credit_limit = (float)(
    $customer['credit_limit'] ?? 0
);

/* =========================================================
   RISK
========================================================= */

$risk = [
    'level' => 'অজানা',
    'score' => 0
];

if (function_exists('calculate_risk_level')) {

    $risk_result = calculate_risk_level(
        $net_balance,
        $credit_limit
    );

    if (is_array($risk_result)) {

        $risk = array_merge(
            $risk,
            $risk_result
        );

    }
}

$risk_level = $risk['level'] ?? 'অজানা';

$risk_score = (int)(
    $risk['score'] ?? 0
);

$risk_score = max(
    0,
    min(100, $risk_score)
);

/* =========================================================
   RENTAL DEPOSIT
========================================================= */

$deposit_held = 0;

if (function_exists('get_current_deposit_held')) {

    $deposit_held = (float)
        get_current_deposit_held(
            $db,
            $customer_id
        );
}

$hire_summary = null;
if (is_file(__DIR__ . '/../../includes/hire_functions.php')) {
    require_once __DIR__ . '/../../includes/hire_functions.php';
    try {
        hire_boot($db);
        $hire_summary = hire_customer_summary($db, (int)$customer_id);
    } catch (Throwable $e) {
        error_log('hire summary: ' . $e->getMessage());
    }
}

/* =========================================================
   PROFILE PHOTO
========================================================= */

$photo = trim(
    (string)($customer['photo'] ?? '')
);

if ($photo !== '') {

    if (
        preg_match(
            '#^https?://#i',
            $photo
        ) ||
        str_starts_with(
            $photo,
            '/'
        )
    ) {

        $photo_src = $photo;

    } else {

        $photo_src =
            '/' . ltrim(
                $photo,
                '/'
            );
    }

} else {

    $photo_src = '';
}

$sign_src = function_exists('ame_customer_signature_path')
    ? ame_customer_signature_path($customer['agreement_signature'] ?? '')
    : '';
$sign_raw_when = trim((string)($customer['agreement_signed_at'] ?? ''));
$sign_when = ($sign_raw_when !== '' && strtotime($sign_raw_when))
    ? date('d/m/Y H:i', strtotime($sign_raw_when))
    : '';

/* =========================================================
   BADGE COLORS
========================================================= */

$module_badge_colors = [

    'বাকি বিক্রি'     => 'bg-cus',
    'কিস্তি'          => 'bg-ins',
    'ভাড়া'           => 'bg-ren',
    'ভাড়া জামানত'    => 'bg-ren',
    'PAYMENT'         => 'bg-sup',
    'সাবেক বকেয়া'    => 'bg-cus',
    'অগ্রিম জমা'      => 'bg-sup',
    'অতিরিক্ত বকেয়া' => 'bg-cus',
    'অতিরিক্ত জমা'   => 'bg-sup'

];

require_once __DIR__ . '/profile_view.php';
?>
