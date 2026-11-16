<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);
if (is_file(__DIR__ . '/../../../includes/rental_schema.php')) {
    require_once __DIR__ . '/../../../includes/rental_schema.php';
    require_once __DIR__ . '/../../../includes/rental_functions.php';
    ensure_rental_schema($db);
}
require_once __DIR__ . '/../../../includes/hire_functions.php';
hire_boot($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$customer_id      = (int)($_POST['customer_id'] ?? 0);
$item_name        = trim($_POST['item_name'] ?? '');
$security_deposit = (float)($_POST['security_deposit'] ?? 0);
$rent_rate        = (float)($_POST['rent_rate'] ?? 0);
$rate_type        = strtoupper(trim($_POST['rate_type'] ?? 'DAILY'));
$owner_id         = (int)($_POST['owner_id'] ?? 0);
$owner_rate       = max(0, (float)($_POST['owner_rate'] ?? 0));
$owner_cycle      = strtolower(trim((string)($_POST['owner_cycle'] ?? 'monthly')));
$owner_days       = max(1, (int)($_POST['owner_custom_days'] ?? 7));
$vehicle_no       = trim((string)($_POST['vehicle_no'] ?? ''));

if (!in_array($rate_type, ['DAILY', 'HOURLY', 'WEEKLY', 'MONTHLY'], true)) {
    $rate_type = 'DAILY';
}
if (!in_array($owner_cycle, ['daily', 'weekly', 'monthly', 'custom'], true)) {
    $owner_cycle = 'monthly';
}

if ($customer_id <= 0 || $item_name === '' || $security_deposit < 0 || $rent_rate <= 0) {
    die('❌ প্রয়োজনীয় তথ্য দিন। <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}

try {
    $db->begin_transaction();
    $contract_id = hire_create_contract($db, [
        'customer_id' => $customer_id,
        'item_name' => $item_name,
        'security_deposit' => $security_deposit,
        'rent_rate' => $rent_rate,
        'rate_type' => $rate_type,
        'start_date' => trim((string)($_POST['start_date'] ?? date('Y-m-d'))),
        'expected_return' => trim((string)($_POST['expected_return'] ?? '')),
        'advance_usage' => (int)($_POST['advance_usage'] ?? 0),
        'account_id' => (int)($_POST['account_id'] ?? 0),
        'payment_method' => trim((string)($_POST['payment_method'] ?? 'CASH')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
        'serial_no' => trim((string)($_POST['serial_no'] ?? '')),
        'product_sku' => trim((string)($_POST['product_sku'] ?? '')),
        'status' => 'ACTIVE',
    ]);

    if ($owner_id > 0 && $contract_id > 0) {
        if (function_exists('rental_owner') && !rental_owner($db, $owner_id)) {
            throw new mysqli_sql_exception('মালিক পাওয়া যায়নি');
        }
        $start = date('Y-m-d');
        if ($vehicle_no === '' && function_exists('rental_owner')) {
            $ow = rental_owner($db, $owner_id);
            $vehicle_no = trim((string)($ow['vehicle_no'] ?? ''));
        }
        $upd = $db->prepare(
            'UPDATE rental_contracts
             SET owner_id=?, owner_rate=?, owner_cycle=?, owner_custom_days=?, owner_start_date=?, vehicle_no=?
             WHERE id=?'
        );
        if ($upd) {
            $upd->bind_param('idsissi', $owner_id, $owner_rate, $owner_cycle, $owner_days, $start, $vehicle_no, $contract_id);
            $upd->execute();
            $upd->close();
        }
    }

    $db->commit();
    header('Location: /modules/customers/hire/contract.php?id=' . $contract_id . '&ok=created');
    exit;
} catch (Throwable $e) {
    @$db->rollback();
    error_log('process_rental failed: ' . $e->getMessage());
    die('❌ ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' <a href="../profile.php?id=' . $customer_id . '">ফিরে যান</a>');
}
