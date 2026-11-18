<?php
require_once '../../includes/config.php';
admin_only();
require_once '../../includes/lang_helper.php';

$range = trim((string)($_GET['range'] ?? 'month'));
if ($range === '') {
    $range = 'month';
}
$filters = [
    'user' => trim((string)($_GET['user'] ?? $_GET['username'] ?? '')),
    'module' => trim((string)($_GET['module'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? '')),
    'range' => $range,
    'from' => trim((string)($_GET['from'] ?? $_GET['start_date'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? $_GET['end_date'] ?? '')),
];
if ($filters['from'] !== '' && $filters['to'] !== '' && !isset($_GET['range'])) {
    $filters['range'] = 'custom';
}
$range = (string)$filters['range'];

$pack = ame_audit_fetch($db, $filters);
$rows = $pack['rows'];
$from = $pack['from'];
$to = $pack['to'];

if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/../../includes/report_engine.php';
    $csv = [];
    foreach ($rows as $r) {
        $who = (string)($r['user_name'] ?: ($r['username'] ?? t('log_system')));
        $csv[] = [
            $r['created_at'] ?? '',
            $who,
            $r['action'] ?? '',
            $r['module'] ?? '',
            $r['action_type'] ?? '',
            $r['reference'] ?? '',
            $r['details'] ?? '',
            $r['old_value'] ?? '',
            $r['new_value'] ?? '',
            $r['ip_address'] ?? '',
            $r['device_info'] ?? '',
        ];
    }
    report_export_csv('user_activity', [
        'সময়', 'ইউজার', 'কাজ', 'মডিউল', 'ধরন', 'রেফারেন্স', 'বিস্তারিত', 'আগে', 'পরে', 'IP', 'ডিভাইস',
    ], $csv);
}

if (($_GET['print'] ?? '') === '1' && function_exists('log_activity')) {
    log_activity('Activity Print', 'Settings', $from . ' — ' . $to, [
        'action_type' => 'print',
        'entity_type' => 'report',
        'reference' => 'activity_logs',
    ]);
}

include '../../partials/header.php';

$usernames = [];
$uq = @$db->query('SELECT id, username, role FROM users ORDER BY username ASC');
if ($uq) {
    while ($u = $uq->fetch_assoc()) {
        $usernames[] = $u;
    }
}

$modules = [
    '' => 'সব মডিউল',
    'Sales' => 'বিক্রয়',
    'Purchase' => 'ক্রয়',
    'Inventory' => 'পণ্য / স্টক',
    'Customers' => 'ক্রেতা',
    'Suppliers' => 'সাপ্লায়ার',
    'Accounts' => 'হিসাব',
    'Expense' => 'খরচ',
    'Settings' => 'সেটিংস',
    'Security' => 'লগইন / নিরাপত্তা',
    'Returns' => 'ফেরত',
    'Reports' => 'রিপোর্ট',
    'Family' => 'পারিবারিক',
    'HRM' => 'এইচআরএম',
];
$actions = [
    '' => 'সব কাজ',
    'add' => 'যোগ / তৈরি',
    'edit' => 'সম্পাদনা',
    'delete' => 'মুছে ফেলা',
    'payment' => 'পেমেন্ট',
    'print' => 'প্রিন্ট',
    'export' => 'এক্সপোর্ট',
    'login' => 'লগইন',
    'logout' => 'লগআউট',
    'login_fail' => 'ব্যর্থ লগইন',
    'approve' => 'অনুমোদন',
    'permission' => 'অনুমতি',
    'password' => 'পাসওয়ার্ড',
];

$n = count($rows);
$nLogin = 0;
$nEdit = 0;
$nDel = 0;
$nPay = 0;
$broken = 0;
foreach ($rows as $r) {
    $t = (string)($r['action_type'] ?? ame_audit_action_type((string)($r['action'] ?? '')));
    if ($t === 'login' || $t === 'login_fail') {
        $nLogin++;
    }
    if ($t === 'edit') {
        $nEdit++;
    }
    if ($t === 'delete') {
        $nDel++;
    }
    if ($t === 'payment') {
        $nPay++;
    }
    if (!ame_audit_verify_row($r)) {
        $broken++;
    }
}

$qs = http_build_query(array_filter([
    'user' => $filters['user'],
    'module' => $filters['module'],
    'action' => $filters['action'],
    'q' => $filters['q'],
    'range' => $filters['range'],
    'from' => $filters['from'],
    'to' => $filters['to'],
], static fn ($v) => $v !== ''));
?>
<link rel="stylesheet" href="/assets/css/audit-desk.819.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="aud-desk">
  <header class="aud-top">
    <p class="kicker"><?php echo ame_pair('ইউজার অ্যাক্টিভিটি', 'User activity'); ?></p>
    <h1><?php echo ame_l('কার্যক্রম খাতা', 'Audit log'); ?></h1>
    <p><?php echo ame_l('কে কখন কী করেছে — আগের ইউজার ও আগের লগ একই খাতায়। মুছা বা বদলানো যায় না।', 'Who did what, on the same user list and the same activity book. Nothing here can be edited or cleared.'); ?></p>
  </header>

  <div class="aud-kpis">
    <div class="aud-kpi"><span><?php echo ame_l('এই ফিল্টারে', 'In this view'); ?></span><b><?php echo (int)$n; ?></b></div>
    <div class="aud-kpi"><span><?php echo ame_l('লগইন / ব্যর্থ', 'Sign-in'); ?></span><b><?php echo (int)$nLogin; ?></b></div>
    <div class="aud-kpi"><span><?php echo ame_l('সম্পাদনা', 'Edits'); ?></span><b><?php echo (int)$nEdit; ?></b></div>
    <div class="aud-kpi"><span><?php echo ame_l('পেমেন্ট / মুছা', 'Pay / delete'); ?></span><b><?php echo (int)($nPay + $nDel); ?></b></div>
  </div>

  <form class="aud-filter" method="get">
    <div>
      <label><?php echo ame_l('ইউজার', 'User'); ?></label>
      <select name="user">
        <option value=""><?php echo ame_l('সব ইউজার', 'All users'); ?></option>
        <?php foreach ($usernames as $u): ?>
          <option value="<?php echo htmlspecialchars((string)$u['username'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['user'] === $u['username'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string)$u['username'] . ' · ' . (string)($u['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label><?php echo ame_l('তারিখ', 'Date'); ?></label>
      <select name="range" onchange="this.form.elements.from.disabled = this.value!=='custom'; this.form.elements.to.disabled = this.value!=='custom';">
        <option value="today" <?php echo $range === 'today' ? 'selected' : ''; ?>><?php echo ame_l('আজ', 'Today'); ?></option>
        <option value="yesterday" <?php echo $range === 'yesterday' ? 'selected' : ''; ?>><?php echo ame_l('গতকাল', 'Yesterday'); ?></option>
        <option value="week" <?php echo $range === 'week' ? 'selected' : ''; ?>><?php echo ame_l('এই সপ্তাহ', 'This week'); ?></option>
        <option value="month" <?php echo $range === 'month' ? 'selected' : ''; ?>><?php echo ame_l('এই মাস', 'This month'); ?></option>
        <option value="custom" <?php echo $range === 'custom' ? 'selected' : ''; ?>><?php echo ame_l('নিজের তারিখ', 'Custom'); ?></option>
      </select>
      <div class="aud-dates" style="margin-top:6px">
        <input type="date" name="from" value="<?php echo htmlspecialchars($filters['from'] !== '' ? $filters['from'] : $from, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $range !== 'custom' ? 'disabled' : ''; ?>>
        <input type="date" name="to" value="<?php echo htmlspecialchars($filters['to'] !== '' ? $filters['to'] : $to, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $range !== 'custom' ? 'disabled' : ''; ?>>
      </div>
    </div>
    <div>
      <label><?php echo ame_l('মডিউল', 'Module'); ?></label>
      <select name="module">
        <?php foreach ($modules as $k => $lab): ?>
          <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['module'] === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label><?php echo ame_l('কাজ', 'Action'); ?></label>
      <select name="action">
        <?php foreach ($actions as $k => $lab): ?>
          <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['action'] === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label><?php echo ame_l('খোঁজ', 'Search'); ?></label>
      <input name="q" value="<?php echo htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo ame_l('ইনভয়েস, নাম, মেমো', 'Invoice, name, memo'); ?>">
      <button class="aud-btn" type="submit" style="margin-top:8px;width:100%"><?php echo ame_l('ফিল্টার', 'Filter'); ?></button>
    </div>
  </form>

  <div class="aud-tools">
    <a class="aud-btn navy" href="/modules/reports/live_work.php"><?php echo ame_l('লাইভ ড্যাশবোর্ড', 'Live dashboard'); ?></a>
    <a class="aud-btn navy" href="?<?php echo htmlspecialchars($qs . ($qs !== '' ? '&' : '') . 'export=csv', ENT_QUOTES, 'UTF-8'); ?>"><?php echo ame_l('CSV নামান', 'Download CSV'); ?></a>
    <a class="aud-btn soft" href="?<?php echo htmlspecialchars($qs . ($qs !== '' ? '&' : '') . 'print=1', ENT_QUOTES, 'UTF-8'); ?>" onclick="window.print(); return true;"><?php echo ame_l('প্রিন্ট', 'Print'); ?></a>
    <?php if ($broken > 0): ?>
      <span class="aud-chip hot"><?php echo ame_l('সতর্কতা', 'Warning'); ?>: <?php echo (int)$broken; ?> <?php echo ame_l('সারি হ্যাশ মিলেনি', 'rows failed the seal'); ?></span>
    <?php else: ?>
      <span class="aud-chip ok"><?php echo ame_l('সিল ঠিক আছে', 'Seal intact'); ?></span>
    <?php endif; ?>
  </div>

  <div class="aud-card">
    <?php if (!$rows): ?>
      <div class="aud-empty"><?php echo ame_l('এই ফিল্টারে কোনো কার্যক্রম নেই।', 'No activity in this filter.'); ?></div>
    <?php else: ?>
    <table class="aud-table">
      <thead>
        <tr>
          <th><?php echo ame_l('ইউজার', 'User'); ?></th>
          <th><?php echo ame_l('তারিখ ও সময়', 'Date & time'); ?></th>
          <th><?php echo ame_l('মডিউল', 'Module'); ?></th>
          <th><?php echo ame_l('কাজ', 'Action'); ?></th>
          <th><?php echo ame_l('রেফারেন্স', 'Reference'); ?></th>
          <th><?php echo ame_l('বিস্তারিত', 'Details'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
            $who = (string)($r['user_name'] ?: ($r['username'] ?? t('log_system')));
            $type = (string)($r['action_type'] ?? ame_audit_action_type((string)($r['action'] ?? '')));
            $ok = ame_audit_verify_row($r);
            $when = !empty($r['created_at']) ? date('d-m-Y h:i A', strtotime((string)$r['created_at'])) : '';
            $ref = trim((string)($r['reference'] ?? ''));
            $old = trim((string)($r['old_value'] ?? ''));
            $new = trim((string)($r['new_value'] ?? ''));
            ?>
        <tr class="<?php echo $ok ? '' : 'aud-broken'; ?>">
          <td>
            <b><?php echo htmlspecialchars($who, ENT_QUOTES, 'UTF-8'); ?></b>
            <?php if (!empty($r['device_info']) || !empty($r['ip_address'])): ?>
              <div style="color:#667085;font-size:13px;font-weight:700"><?php echo htmlspecialchars(trim(($r['device_info'] ?? '') . ' · ' . ($r['ip_address'] ?? ''), ' ·'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?></td>
          <td><span class="aud-chip"><?php echo htmlspecialchars((string)($r['module'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
          <td>
            <span class="aud-chip <?php echo in_array($type, ['delete', 'login_fail'], true) ? 'hot' : ($type === 'add' || $type === 'login' ? 'ok' : ''); ?>">
              <?php echo htmlspecialchars(ame_audit_label_action($type, (string)($r['action'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <div style="margin-top:4px"><?php echo htmlspecialchars((string)($r['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
          </td>
          <td>
            <?php if ($ref !== ''): ?><div class="aud-ref"><?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if (!empty($r['product_name'])): ?><div><?php echo htmlspecialchars((string)$r['product_name'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if (!empty($r['party_name'])): ?><div><?php echo htmlspecialchars((string)$r['party_name'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($r['amount_total'] !== null && $r['amount_total'] !== ''): ?>
              <div class="aud-money"><?php echo ame_l('মোট', 'Total'); ?> <?php echo htmlspecialchars(ame_audit_money($r['amount_total']), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($r['amount_paid'] !== null && $r['amount_paid'] !== ''): ?>
              <div><?php echo ame_l('জমা', 'Paid'); ?> <?php echo htmlspecialchars(ame_audit_money($r['amount_paid']), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($r['amount_due'] !== null && $r['amount_due'] !== ''): ?>
              <div><?php echo ame_l('বকেয়া', 'Due'); ?> <?php echo htmlspecialchars(ame_audit_money($r['amount_due']), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php echo htmlspecialchars((string)($r['details'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($old !== '' || $new !== ''): ?>
              <div class="aud-diff">
                <?php if ($old !== ''): ?><div class="was"><?php echo ame_l('আগে', 'Before'); ?>: <?php echo nl2br(htmlspecialchars($old, ENT_QUOTES, 'UTF-8')); ?></div><?php endif; ?>
                <?php if ($new !== ''): ?><div class="now"><?php echo ame_l('পরে', 'After'); ?>: <?php echo nl2br(htmlspecialchars($new, ENT_QUOTES, 'UTF-8')); ?></div><?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if (!$ok): ?><div class="aud-chip hot"><?php echo ame_l('সিল ভাঙা', 'Seal broken'); ?></div><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <p class="aud-note"><?php echo ame_l('শুধু অ্যাডমিন এই খাতা দেখতে পারেন। সাধারণ ইউজার মুছতে, বদলাতে বা খালি করতে পারেন না। নতুন মডিউল থেকে log_activity() বা ame_audit() ডাকলেই এখানে উঠবে — আগের সব কলও একই খাতায় থাকে।', 'Only an admin can open this book. Staff cannot delete, edit, or empty it. New modules call log_activity() or ame_audit() and land here with every older entry.'); ?></p>
</div>
<?php include '../../partials/footer.php'; ?>
