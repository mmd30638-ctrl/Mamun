<?php
require_once __DIR__ . '/_boot.php';

$kind = (string)($_GET['kind'] ?? '');
$month = (string)($_GET['m'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

function fam_csv(string $name, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

if ($kind === 'expenses' && fam_can_see('money')) {
    $rows = fam_rows("SELECT exp_date, category, amount, spent_by, purpose, pay_method FROM family_expenses WHERE exp_date LIKE '" . fam_db()->real_escape_string($month) . "%' ORDER BY exp_date");
    $out = [];
    foreach ($rows as $r) {
        $out[] = [$r['exp_date'], $r['category'], $r['amount'], $r['spent_by'], $r['purpose'], $r['pay_method']];
    }
    fam_csv('family-expenses-' . $month . '.csv', ['তারিখ', 'ক্যাটাগরি', 'অ্যামাউন্ট', 'কে', 'কী জন্য', 'পেমেন্ট'], $out);
}
if ($kind === 'medicines') {
    $rows = fam_rows('SELECT name, generic_name, stock, bought_qty, refill_date FROM family_medicines ORDER BY name');
    $out = [];
    foreach ($rows as $r) {
        $out[] = [$r['name'], $r['generic_name'], $r['stock'], $r['bought_qty'], $r['refill_date']];
    }
    fam_csv('family-medicines.csv', ['নাম', 'Generic', 'স্টক', 'কেনা', 'refill'], $out);
}
if ($kind === 'inventory') {
    $rows = fam_rows('SELECT name, category, qty, price, location_note, warranty_until FROM family_inventory ORDER BY name');
    $out = [];
    foreach ($rows as $r) {
        $out[] = [$r['name'], $r['category'], $r['qty'], $r['price'], $r['location_note'], $r['warranty_until']];
    }
    fam_csv('family-inventory.csv', ['নাম', 'ক্যাটাগরি', 'পরিমাণ', 'দাম', 'স্থান', 'ওয়ারেন্টি'], $out);
}
if ($kind === 'contacts') {
    $rows = fam_rows('SELECT name, mobile, alt_mobile, email, profession, organization, relation_note FROM family_contacts ORDER BY name');
    $out = [];
    foreach ($rows as $r) {
        $out[] = [$r['name'], $r['mobile'], $r['alt_mobile'], $r['email'], $r['profession'], $r['organization'], $r['relation_note']];
    }
    fam_csv('family-contacts.csv', ['নাম', 'মোবাইল', 'বিকল্প', 'ইমেইল', 'পেশা', 'প্রতিষ্ঠান', 'সম্পর্ক'], $out);
}
if ($kind === 'backup') {
    $tables = ['family_groups', 'family_contacts', 'family_members', 'family_doctors', 'family_medicines', 'family_med_buys', 'family_books', 'family_shopping', 'family_inventory', 'family_expenses', 'family_events', 'family_documents', 'family_notes', 'family_access'];
    $pack = ['version' => '9.0', 'at' => date('c'), 'tables' => []];
    foreach ($tables as $t) {
        $pack['tables'][$t] = fam_rows("SELECT * FROM `$t`");
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="family-backup-' . date('Ymd') . '.json"');
    echo json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_export', 'fam_lead_export');
?>
<section class="fam-panel">
  <h2>নামান</h2>
  <div class="fam-actions">
    <a class="fam-btn" href="export.php?kind=contacts">পরিচিত CSV</a>
    <a class="fam-btn" href="export.php?kind=expenses&m=<?php echo date('Y-m'); ?>">এই মাসের খরচ CSV</a>
    <a class="fam-btn" href="export.php?kind=medicines">ওষুধ CSV</a>
    <a class="fam-btn" href="export.php?kind=inventory">ইনভেন্টরি CSV</a>
    <a class="fam-btn" href="export.php?kind=backup">পুরো ব্যাকআপ JSON</a>
    <a class="fam-btn soft" href="reports.php">রিপোর্ট থেকে প্রিন্ট/PDF</a>
  </div>
  <p class="fam-muted" style="margin-top:1rem">Excel-এ খুলতে CSVই যথেষ্ট। PDF-এর জন্য রিপোর্ট পেজে প্রিন্ট চাপুন, তারপর Save as PDF।</p>
</section>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
