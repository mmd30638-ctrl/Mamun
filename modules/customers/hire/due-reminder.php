<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');

$search = trim((string) ($_GET['search'] ?? ''));
$date_from = trim((string) ($_GET['date_from'] ?? ''));
$date_to = trim((string) ($_GET['date_to'] ?? ''));
$kind = strtolower(trim((string) ($_GET['kind'] ?? '')));
if (!in_array($kind, ['overdue', 'today', 'upcoming'], true)) {
    $kind = '';
}
$payload = function_exists('hire_due_notify_rows')
    ? hire_due_notify_rows($db, false)
    : ['rows' => [], 'overdue_count' => 0, 'today_count' => 0, 'upcoming_count' => 0, 'unpaid_count' => 0, 'csrf' => csrf_token()];

$rows = $payload['rows'] ?? [];
$today = (string) ($payload['today'] ?? date('Y-m-d'));
if ($search !== '' || $date_from !== '' || $date_to !== '') {
    $q = mb_strtolower($search);
    $rows = array_values(array_filter($rows, static function ($row) use ($q, $date_from, $date_to) {
        $follow = trim((string) ($row['follow_date'] ?? ''));
        if ($date_from !== '' && ($follow === '' || $follow < $date_from)) {
            return false;
        }
        if ($date_to !== '' && ($follow === '' || $follow > $date_to)) {
            return false;
        }
        if ($q === '') {
            return true;
        }
        $hay = mb_strtolower(implode(' ', [
            (string) ($row['rental_no'] ?? ''),
            (string) ($row['customer_name'] ?? ''),
            (string) ($row['customer_phone'] ?? ''),
            (string) ($row['customer_code'] ?? ''),
            (string) ($row['item_name'] ?? ''),
        ]));
        return str_contains($hay, $q);
    }));
}
if ($kind !== '') {
    $rows = array_values(array_filter($rows, static function ($row) use ($kind, $today) {
        $meta = $row['badge'] ?? (function_exists('hire_due_row_badge') ? hire_due_row_badge($row, $today) : []);
        return (string) ($meta['kind'] ?? '') === $kind;
    }));
}

$customers = $rows;
$overdue_count = (int) ($payload['overdue_count'] ?? 0);
$today_count = (int) ($payload['today_count'] ?? 0);
$upcoming_count = (int) ($payload['upcoming_count'] ?? 0);
$watch_count = (int) ($payload['unpaid_count'] ?? 0);
$here = 'due-reminder.php';

include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.756', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<link rel="stylesheet" href="/assets/css/hire-pro.css?v=<?php echo $av; ?>">
<link rel="stylesheet" href="/assets/css/hire-due-home.css?v=<?php echo $av; ?>">
<div class="hire-wrap hire-due-page">
  <?php echo hire_flash(); ?>
  <header class="hire-mast">
    <h1>বকেয়া তারিখ</h1>
    <p>তারিখ বদলালে সেই তারিখই থাকে। আগের খাতা বদলায় না।</p>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php require __DIR__ . '/due-reminder-view.php'; ?>
</div>
<?php require __DIR__ . '/due-reminder-edit-modal.php'; ?>
<script>
window.AME_HIRE_DUE = <?php echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.AME_CSRF_TOKEN = <?php echo json_encode($payload['csrf'] ?? (function_exists('csrf_token') ? csrf_token() : ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="/assets/js/hire-due-home.js?v=<?php echo $av; ?>"></script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
