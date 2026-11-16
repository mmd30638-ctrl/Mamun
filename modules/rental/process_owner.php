<?php
require_once __DIR__ . '/_boot.php';
require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$cycle = strtolower(trim((string)($_POST['pay_cycle'] ?? 'monthly')));
if (!in_array($cycle, rental_cycle_options(), true)) {
    $cycle = 'monthly';
}
$amount = max(0, (float)($_POST['pay_amount'] ?? 0));
$days = max(1, (int)($_POST['custom_days'] ?? 7));
$vehicle_no = trim((string)($_POST['vehicle_no'] ?? ''));
$vehicle_name = trim((string)($_POST['vehicle_name'] ?? ''));
$entry_date = rental_input_date((string)($_POST['entry_date'] ?? ''), rental_today());

if ($name === '') {
    rental_flash('মালিকের নাম লাগবে', 'err');
    redirect('/modules/rental/owners.php');
}
if ($vehicle_no === '') {
    rental_flash('গাড়ির নাম্বার লাগবে', 'err');
    redirect('/modules/rental/owners.php' . ($id ? ('?id=' . $id) : ''));
}

if ($id > 0) {
    $old = rental_owner($db, $id);
    if (!$old) {
        rental_flash('মালিক পাওয়া যায়নি', 'err');
        redirect('/modules/rental/owners.php');
    }
    rental_exec(
        $db,
        'UPDATE rental_owners SET name=?, phone=?, address=?, notes=?, pay_cycle=?, pay_amount=?, custom_days=?, vehicle_no=?, vehicle_name=?, entry_date=? WHERE id=?',
        'sssssdisssi',
        [$name, $phone, $address, $notes, $cycle, $amount, $days, $vehicle_no, $vehicle_name, $entry_date, $id]
    );
    rental_sync_owner_vehicle($db, $id, $vehicle_no, $vehicle_name);
    rental_audit($db, 'rental_owners', $id, 'update', $old, rental_owner($db, $id));
    rental_flash('মালিক আপডেট হয়েছে');
    redirect('/modules/rental/statement.php?id=' . $id);
}

rental_exec(
    $db,
    'INSERT INTO rental_owners (name, phone, address, notes, pay_cycle, pay_amount, custom_days, vehicle_no, vehicle_name, entry_date) VALUES (?,?,?,?,?,?,?,?,?,?)',
    'sssssdisss',
    [$name, $phone, $address, $notes, $cycle, $amount, $days, $vehicle_no, $vehicle_name, $entry_date]
);
$new_id = (int)$db->insert_id;
rental_sync_owner_vehicle($db, $new_id, $vehicle_no, $vehicle_name);
rental_audit($db, 'rental_owners', $new_id, 'create', null, rental_owner($db, $new_id));
rental_flash('মালিক যোগ হয়েছে');
redirect('/modules/rental/statement.php?id=' . $new_id);
