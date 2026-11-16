<?php
/**
 * Daily Complete Accounting & Audit Center — one page, read-only money.
 */
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/daily_audit.php';
require_once '../../includes/report_engine.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
if (!da_can_see($db)) {
    http_response_code(403);
    die('❌ এই হিসাব কেন্দ্র ব্যবহারের অনুমতি নেই।');
}

if (function_exists('ensure_accounts_schema')) {
    ensure_accounts_schema($db);
}
da_ensure_schema($db);

[$from, $to, $preset] = da_parse_range($_GET);
$tab = strtolower(trim((string)($_GET['tab'] ?? 'summary')));
$tabs = da_tabs();
if (!isset($tabs[$tab])) {
    $tab = 'summary';
}
$filters = da_filters_from_get($_GET);
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;
$offset = ($page - 1) * $per;
$accountFocus = (int)($_GET['acc'] ?? 0);
$userFocus = (int)($_GET['uid'] ?? 0);
$keep = [
    'range' => $preset,
    'start_date' => $from,
    'end_date' => $to,
    'tab' => $tab,
    'q' => $filters['q'],
    'account_id' => $filters['account'] ?: null,
    'user_id' => $filters['user'] ?: null,
    'txn_type' => $filters['type'] ?: null,
    'ref_type' => $filters['ref'] ?: null,
    'method' => $filters['method'] ?: null,
    'status' => $filters['status'] ?: null,
    'party' => $filters['party'] ?: null,
    'invoice' => $filters['invoice'] ?: null,
    'txn_id' => $filters['txn'] ?: null,
    'focus' => $filters['focus'] ?: null,
    'acc' => $accountFocus ?: null,
    'uid' => $userFocus ?: null,
    'report' => (string)($_GET['report'] ?? ''),
];
$keep = array_filter($keep, static fn($v) => $v !== null && $v !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['da_action'] ?? '') === 'close') {
    $token = (string)($_POST['da_token'] ?? '');
    if (!hash_equals((string)($_SESSION['da_close_token'] ?? ''), $token)) {
        $_SESSION['da_flash_err'] = 'নিরাপত্তা টোকেন মিলেনি। আবার চেষ্টা করুন।';
    } else {
        $cdate = trim((string)($_POST['close_date'] ?? $from));
        $physical = round((float)str_replace(',', '', (string)($_POST['physical_cash'] ?? '0')), 2);
        $note = trim((string)($_POST['difference_note'] ?? ''));
        $res = da_save_closing($db, $cdate, $physical, $note, (int)$_SESSION['user_id']);
        if (!empty($res['ok'])) {
            $_SESSION['da_flash_ok'] = 'ক্লোজিং সংরক্ষণ হয়েছে। অন্য মডিউলের টাকা অপরিবর্তিত।';
        } else {
            $_SESSION['da_flash_err'] = (string)($res['error'] ?? 'ক্লোজিং হয়নি।');
        }
    }
    header('Location: /modules/accounts/daily_audit.php' . da_qs($keep, ['tab' => 'close']));
    exit;
}

if ((string)($_GET['ajax'] ?? '') === 'detail') {
    header('Content-Type: text/html; charset=UTF-8');
    $kind = (string)($_GET['kind'] ?? 'ledger');
    $id = (int)($_GET['id'] ?? 0);
    echo '<button type="button" class="da-btn ghost" data-da-close>বন্ধ</button>';
    if ($kind === 'ledger' && $id > 0) {
        $row = da_all(
            $db,
            "SELECT l.*, a.account_name, a.account_type FROM account_ledger l
             LEFT JOIN accounts a ON a.id = l.account_id WHERE l.id = ?",
            'i',
            [$id]
        )[0] ?? null;
        if (!$row) {
            echo '<p class="da-empty">লেনদেন পাওয়া যায়নি।</p>';
            exit;
        }
        echo '<h3>লেনদেন #' . (int)$row['id'] . '</h3><dl class="da-kv">';
        $pairs = [
            'তারিখ' => substr((string)$row['created_at'], 0, 16),
            'ধরন' => $row['type'] === 'credit' ? 'ক্রেডিট / জমা' : 'ডেবিট / খরচ',
            'পরিমাণ' => da_taka((float)$row['amount']),
            'অ্যাকাউন্ট' => (string)$row['account_name'],
            'পদ্ধতি' => da_account_kind((string)$row['account_type']),
            'উৎস' => da_ref_label((string)$row['ref_type']),
            'রেফারেন্স' => (string)$row['ref_id'],
            'ব্যালেন্স পরে' => da_taka((float)$row['balance_after']),
            'মন্তব্য' => (string)$row['note'],
        ];
        foreach ($pairs as $k => $v) {
            echo '<dt>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</dt><dd>' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</dd>';
        }
        echo '</dl>';
        exit;
    }
    if ($kind === 'user' && $id > 0) {
        echo '<h3>' . htmlspecialchars(da_user_name($db, $id), ENT_QUOTES, 'UTF-8') . '</h3>';
        $acts = da_user_activity($db, $id, $from, $to, 40);
        $logs = da_login_sessions($db, $id, $from, $to);
        if ($logs) {
            echo '<p class="da-note">লগইন / লগআউট</p><ul>';
            foreach ($logs as $lg) {
                echo '<li>' . htmlspecialchars($lg['created_at'] . ' · ' . $lg['action'], ENT_QUOTES, 'UTF-8') . '</li>';
            }
            echo '</ul>';
        }
        if (!$acts) {
            echo '<p class="da-empty">এই সময়ে কোনো কর্মকাণ্ড নেই।</p>';
            exit;
        }
        echo '<div class="da-table-wrap"><table class="da-table"><thead><tr><th>সময়</th><th>কাজ</th><th>বিবরণ</th></tr></thead><tbody>';
        foreach ($acts as $act) {
            echo '<tr><td>' . htmlspecialchars((string)$act['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)$act['action'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($act['details'] ?? $act['party_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            if (!empty($act['old_value']) || !empty($act['new_value'])) {
                echo '<details class="da-more"><summary>পরিবর্তন</summary><p>আগে: '
                    . htmlspecialchars((string)$act['old_value'], ENT_QUOTES, 'UTF-8')
                    . '<br>পরে: ' . htmlspecialchars((string)$act['new_value'], ENT_QUOTES, 'UTF-8') . '</p></details>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        exit;
    }
    if ($kind === 'day') {
        $d = (string)($_GET['date'] ?? $from);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $d = $from;
        }
        $sum = da_summary($db, $d, $d);
        echo '<h3>' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</h3><dl class="da-kv">';
        foreach (['sales' => 'বিক্রয়', 'collection' => 'আদায়', 'expense' => 'ব্যয়', 'deposit' => 'জমা', 'withdraw' => 'উত্তোলন', 'transfer' => 'ট্রান্সফার', 'opening' => 'শুরু', 'closing' => 'শেষ'] as $k => $lab) {
            echo '<dt>' . $lab . '</dt><dd>' . da_taka((float)$sum[$k]) . '</dd>';
        }
        echo '</dl>';
        $rows = da_ledger_rows($db, $d, $d, da_filters_from_get([]), 40, 0);
        if ($rows) {
            echo '<div class="da-table-wrap"><table class="da-table"><thead><tr><th>সময়</th><th>বিবরণ</th><th>টাকা</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $cls = $r['type'] === 'credit' ? 'da-cr' : 'da-dr';
                echo '<tr><td>' . htmlspecialchars(substr((string)$r['created_at'], 11, 8), ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars(da_ref_label((string)$r['ref_type']) . ' · ' . (string)$r['note'], ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td class="' . $cls . '">' . da_taka((float)$r['amount']) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        exit;
    }
    echo '<p class="da-empty">বিস্তারিত নেই।</p>';
    exit;
}

$export = strtolower((string)($_GET['export'] ?? ''));
if (in_array($export, ['csv', 'xlsx'], true)) {
    $kind = (string)($_GET['report'] ?? $tab);
    $headers = da_export_headers($kind);
    $rows = [];
    if ($kind === 'ledger') {
        foreach (da_ledger_rows($db, $from, $to, $filters, 200, 0) as $i => $r) {
            $rows[] = [
                $i + 1,
                substr((string)$r['created_at'], 0, 10),
                substr((string)$r['created_at'], 11, 8),
                $r['id'],
                $r['type'] === 'credit' ? 'ক্রেডিট' : 'ডেবিট',
                da_ref_label((string)$r['ref_type']),
                $r['note'],
                $r['type'] === 'debit' ? $r['amount'] : '',
                $r['type'] === 'credit' ? $r['amount'] : '',
                $r['account_name'],
                'সম্পন্ন',
            ];
        }
    } elseif ($kind === 'accounts') {
        foreach (da_accounts_statement($db, $from, $to) as $a) {
            $rows[] = [$a['name'], $a['kind'], $a['opening'], $a['deposit'], $a['withdraw'], $a['transfer_in'], $a['transfer_out'], $a['closing'], $a['current']];
        }
    } elseif ($kind === 'transfers') {
        foreach (da_transfer_rows($db, $from, $to) as $t) {
            $rows[] = [substr($t['created_at'], 0, 10), substr($t['created_at'], 11, 8), $t['from_name'], $t['to_name'], $t['amount'], $t['note']];
        }
    } elseif ($kind === 'users') {
        foreach (da_user_rows($db, $from, $to) as $u) {
            $rows[] = [$u['name'], $u['id'], $u['role'], $u['actions'], $u['sales'], $u['received'], $u['paid'], $u['invoices'], $u['edits'], $u['cancels']];
        }
    } elseif ($kind === 'hundred') {
        foreach (da_days($db, 100) as $d) {
            $rows[] = [$d['date'], $d['opening'], $d['income'], $d['collection'], $d['expenses'], $d['payments'], $d['transfers'], $d['refunds'], $d['outstanding'], $d['closing'], $d['txn'], $d['users']];
        }
    } elseif ($kind === 'received') {
        foreach (da_received_rows($db, $from, $to)['ledger'] as $r) {
            $rows[] = [substr($r['created_at'], 0, 10), substr($r['created_at'], 11, 8), da_ref_label((string)$r['ref_type']), '', $r['amount'], $r['account_name'], $r['ref_id'], $r['note']];
        }
        foreach (da_received_rows($db, $from, $to)['payments'] as $r) {
            $rows[] = [$r['payment_date'], substr((string)$r['created_at'], 11, 8), 'কাস্টমার আদায়', $r['customer_name'], $r['amount'], $r['account_name'], $r['reference_no'], $r['note']];
        }
    } elseif ($kind === 'paid') {
        foreach (da_paid_rows($db, $from, $to)['ledger'] as $r) {
            $rows[] = [substr($r['created_at'], 0, 10), substr($r['created_at'], 11, 8), $r['category_name'] ?: da_ref_label((string)$r['ref_type']), $r['note'], $r['amount'], $r['account_name'], $r['id']];
        }
    } else {
        $sum = da_summary($db, $from, $to);
        foreach (da_card_meta() as $k => $meta) {
            $rows[] = [$meta[0], $sum[$k] ?? 0];
        }
    }
    $prefix = 'aponshop_audit_' . $kind . '_' . $from . '_' . $to;
    if ($export === 'xlsx') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $prefix . '.xls"');
        echo "\xEF\xBB\xBF<table border='1'><tr>";
        foreach ($headers as $h) {
            echo '<th>' . htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        exit;
    }
    report_export_csv($prefix, $headers, $rows);
}

$_SESSION['da_close_token'] = bin2hex(random_bytes(16));
$flashOk = (string)($_SESSION['da_flash_ok'] ?? '');
$flashErr = (string)($_SESSION['da_flash_err'] ?? '');
unset($_SESSION['da_flash_ok'], $_SESSION['da_flash_err']);

$summary = da_summary($db, $from, $to);
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$asset_v = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1';
$ame_body_class = 'ame-txn-page ame-daily-audit';
$rangeLabel = $from === $to ? $from : ($from . ' — ' . $to);

$presetLinks = [
    'today' => 'আজ',
    'yesterday' => 'গতকাল',
    '7' => '৭ দিন',
    '15' => '১৫ দিন',
    '30' => '৩০ দিন',
    '60' => '৬০ দিন',
    '100' => '১০০ দিন',
    'this_month' => 'এই মাস',
    'last_month' => 'আগের মাস',
    'custom' => 'কাস্টম',
];

$accounts = da_table_exists($db, 'accounts')
    ? da_all($db, "SELECT id, account_name, account_type FROM accounts ORDER BY account_name")
    : [];
$usersList = [];
if (da_table_exists($db, 'users')) {
    $uWhere = da_has_col($db, 'users', 'removed_at') ? ' WHERE removed_at IS NULL' : '';
    $usersList = da_all($db, "SELECT id, COALESCE(NULLIF(full_name,''), username) name FROM users{$uWhere} ORDER BY name");
}

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/daily-audit.css?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>">

<div class="da-wrap" id="ame-slip">
  <section class="da-hero">
    <h1>দৈনিক সম্পূর্ণ হিসাব ও অডিট সেন্টার</h1>
    <p><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?> — Daily Accounting & Audit Center</p>
    <div class="da-meta"><?php echo htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'); ?> · বাংলাদেশ সময় (Asia/Dhaka) · শুধু পড়া — অন্য মডিউলের টাকা অপরিবর্তিত</div>
  </section>

  <?php if ($flashOk): ?><div class="da-ok"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="da-error"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

  <form class="da-filters no-print" method="get">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="da-presets">
      <?php foreach ($presetLinks as $key => $lab):
        $href = da_qs(array_merge($keep, ['range' => $key, 'tab' => $tab]));
        if ($key !== 'custom') {
            $href = da_qs(['range' => $key, 'tab' => $tab]);
        }
      ?>
      <a class="<?php echo $preset === $key ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endforeach; ?>
    </div>
    <div class="da-filter-row">
      <div>
        <label>শুরু</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div>
        <label>শেষ</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div>
        <label>সার্চ</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="আইডি, নোট, অ্যাকাউন্ট">
      </div>
      <div>
        <label>অ্যাকাউন্ট</label>
        <select name="account_id">
          <option value="">সব</option>
          <?php foreach ($accounts as $acc): ?>
          <option value="<?php echo (int)$acc['id']; ?>" <?php echo $filters['account'] === (int)$acc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$acc['account_name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>ইউজার</label>
        <select name="user_id">
          <option value="">সব</option>
          <?php foreach ($usersList as $u): ?>
          <option value="<?php echo (int)$u['id']; ?>" <?php echo $filters['user'] === (int)$u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$u['name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>ধরন</label>
        <select name="txn_type">
          <option value="">সব</option>
          <option value="credit" <?php echo $filters['type'] === 'credit' ? 'selected' : ''; ?>>জমা / ক্রেডিট</option>
          <option value="debit" <?php echo $filters['type'] === 'debit' ? 'selected' : ''; ?>>খরচ / ডেবিট</option>
        </select>
      </div>
      <div>
        <label>লেনদেনের উৎস</label>
        <select name="ref_type">
          <option value="">সব</option>
          <?php foreach (['sale' => 'বিক্রয়', 'manual_income' => 'আয়', 'manual_expense' => 'খরচ', 'purchase' => 'ক্রয়', 'supplier_payment' => 'সাপ্লায়ার', 'transfer' => 'ট্রান্সফার', 'reversal' => 'রিভার্সাল'] as $rk => $rl): ?>
          <option value="<?php echo $rk; ?>" <?php echo $filters['ref'] === $rk ? 'selected' : ''; ?>><?php echo $rl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>ইনভয়েস / Txn</label>
        <input type="text" name="txn_id" value="<?php echo htmlspecialchars($filters['txn'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="ID">
      </div>
      <div>
        <input type="hidden" name="range" value="custom">
        <button type="submit" class="da-btn">ফিল্টার</button>
      </div>
    </div>
  </form>

  <div class="da-actions no-print">
    <a class="da-btn ghost" href="<?php echo htmlspecialchars(da_qs($keep, ['export' => 'csv', 'report' => $tab]), ENT_QUOTES, 'UTF-8'); ?>">CSV</a>
    <a class="da-btn ghost" href="<?php echo htmlspecialchars(da_qs($keep, ['export' => 'xlsx', 'report' => $tab]), ENT_QUOTES, 'UTF-8'); ?>">Excel</a>
    <button type="button" class="da-btn ghost" onclick="window.print()">প্রিন্ট / PDF</button>
    <button type="button" class="da-btn ghost" onclick="navigator.clipboard.writeText(location.href).then(function(){alert('লিংক কপি হয়েছে');})">শেয়ার লিংক</button>
  </div>

  <div class="da-cards">
    <?php foreach (da_card_meta() as $key => $meta):
      $href = da_qs(['range' => $preset, 'start_date' => $from, 'end_date' => $to, 'tab' => $meta[1], 'focus' => $meta[2] ?: null]);
      $color = (string)($meta[4] ?? '#0F172A');
    ?>
    <a class="da-card" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" style="color:<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>">
      <small style="color:<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($meta[0], ENT_QUOTES, 'UTF-8'); ?></small>
      <b class="da-amt" style="color:<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>"><?php echo da_taka((float)($summary[$key] ?? 0)); ?></b>
    </a>
    <?php endforeach; ?>
  </div>

<?php
$story = da_cash_story($db, $from, $to);
$dayTitle = $from === $to ? ('দৈনিক · ' . $from) : ($from . ' — ' . $to);
?>
  <div class="da-day-grid">
    <article class="da-day-box">
      <h2 style="color:#047857">ক্যাশ হিসাব · <?php echo htmlspecialchars($dayTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
      <div class="da-day-row"><span style="color:#047857">আজকের মোট বিক্রয়</span><b class="da-amt" style="color:#047857"><?php echo da_taka((float)$story['sales']); ?></b></div>
      <div class="da-day-row"><span style="color:#0F766E">নগদ</span><b class="da-amt" style="color:#0F766E"><?php echo da_taka((float)$story['sales_paid']); ?></b></div>
      <div class="da-day-row"><span style="color:#EA580C">বাকি</span><b class="da-amt" style="color:#EA580C"><?php echo da_taka((float)$story['sales_due']); ?></b></div>
      <div class="da-day-row"><span style="color:#64748B">বিল সংখ্যা</span><b style="color:#1E3A8A"><?php echo (int)$story['sales_count']; ?></b></div>
      <?php foreach (($story['sale_acc'] ?? []) as $row):
        if ((float)$row['received'] < 0.01) {
            continue;
        }
      ?>
      <div class="da-day-row">
        <span style="color:#0369A1"><?php echo htmlspecialchars((string)$row['account_name'] . ' · নগদ', ENT_QUOTES, 'UTF-8'); ?></span>
        <b class="da-amt" style="color:#0369A1"><?php echo da_taka((float)$row['received']); ?></b>
      </div>
      <?php endforeach; ?>
      <div class="da-day-row"><span style="color:#0369A1">ক্যাশের মাধ্যমে বকেয়া আদায়</span><b class="da-amt" style="color:#0369A1"><?php echo da_taka((float)$story['cash_due']); ?></b></div>
      <div class="da-day-row"><span style="color:#1D4ED8">নগদ + ক্যাশ আদায়</span><b class="da-amt" style="color:#1D4ED8"><?php echo da_taka((float)$story['cash_total']); ?></b></div>
      <div class="da-day-row"><span style="color:#DC2626">ক্যাশ থেকে খরচ</span><b class="da-amt" style="color:#DC2626"><?php echo da_taka((float)$story['cash_out']); ?></b></div>
      <div class="da-day-row"><span style="color:#1E3A8A">ক্যাশে রয়েছে</span><b class="da-amt" style="color:#1E3A8A"><?php echo da_taka((float)$story['cash_remain']); ?></b></div>
    </article>
    <article class="da-day-box">
      <h2 style="color:#0369A1">কাস্টমার থেকে বকেয়া আদায় — কোন অ্যাকাউন্টে</h2>
      <div class="da-day-row"><span style="color:#0369A1">মোট বকেয়া আদায়</span><b class="da-amt" style="color:#0369A1"><?php echo da_taka((float)$summary['collection']); ?></b></div>
      <?php if (!$story['due_rows']): ?>
      <div class="da-empty">এই দিনে বকেয়া আদায় নেই।</div>
      <?php else: foreach ($story['due_rows'] as $row):
        $method = trim((string)($row['method'] ?? ''));
        $lab = (string)$row['account_name'] . ' · ' . da_kind_bn((string)$row['account_type']);
        if ($method !== '') {
            $lab .= ' · ' . $method;
        }
        $ink = da_account_kind((string)$row['account_type']) === 'cash' ? '#047857' : '#4F46E5';
      ?>
      <div class="da-day-row">
        <span style="color:<?php echo $ink; ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></span>
        <b class="da-amt" style="color:<?php echo $ink; ?>"><?php echo da_taka((float)$row['received']); ?></b>
      </div>
      <?php endforeach; endif; ?>
    </article>
    <article class="da-day-box wide">
      <h2 style="color:#0F766E">আজকের আয় ও ব্যয়</h2>
      <div class="da-day-row"><span style="color:#0F766E">আজকের আয়</span><b class="da-amt" style="color:#0F766E"><?php echo da_taka((float)$summary['income']); ?></b></div>
      <div class="da-day-row"><span style="color:#DC2626">আজকের ব্যয়</span><b class="da-amt" style="color:#DC2626"><?php echo da_taka((float)$summary['expense']); ?></b></div>
      <div class="da-day-row"><span style="color:#047857">আয় − ব্যয়</span><b class="da-amt" style="color:#047857"><?php echo da_taka((float)$summary['income'] - (float)$summary['expense']); ?></b></div>
      <div class="da-day-row"><span style="color:#1D4ED8">সব অ্যাকাউন্টে মোট রয়েছে</span><b class="da-amt" style="color:#1D4ED8"><?php echo da_taka((float)$summary['closing']); ?></b></div>
      <p class="da-note">আয় = বিক্রয় + অন্য আয়। ব্যয় = খরচের খাত। ট্রান্সফার আয় বা ব্যয় নয়।</p>
    </article>
    <article class="da-day-box wide">
      <h2 style="color:#1E3A8A">কোন অ্যাকাউন্টে কত আছে · <?php echo htmlspecialchars($dayTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
      <?php
      $dayAccs = $story['accounts'] ?? [];
      if (!$dayAccs):
      ?>
      <div class="da-empty">অ্যাকাউন্ট নেই।</div>
      <?php else:
        $kindOrder = ['cash' => 'ক্যাশ', 'bank' => 'ব্যাংক', 'mfs' => 'মোবাইল ব্যাংকিং'];
        $byKind = ['cash' => [], 'bank' => [], 'mfs' => []];
        foreach ($dayAccs as $a) {
            $k = $a['kind'] ?? 'cash';
            if (!isset($byKind[$k])) {
                $k = 'cash';
            }
            $byKind[$k][] = $a;
        }
        foreach ($kindOrder as $kind => $kindLab):
            if (!$byKind[$kind]) {
                continue;
            }
            $ink = match ($kind) {
                'bank' => '#4F46E5',
                'mfs' => '#C026D3',
                default => '#047857',
            };
      ?>
      <h3 style="margin:10px 0 4px;font-size:13px;color:<?php echo $ink; ?>"><?php echo htmlspecialchars($kindLab, ENT_QUOTES, 'UTF-8'); ?></h3>
      <?php foreach ($byKind[$kind] as $a): ?>
      <div class="da-day-row">
        <span style="color:<?php echo $ink; ?>"><?php echo htmlspecialchars($a['name'] . ' · ' . da_kind_bn((string)$a['type']), ENT_QUOTES, 'UTF-8'); ?></span>
        <span>
          <small style="color:#64748B">আজ জমা <?php echo da_taka((float)$a['deposit']); ?> · খরচ <?php echo da_taka((float)$a['withdraw']); ?></small>
          <b class="da-amt" style="color:<?php echo $ink; ?>">রয়েছে <?php echo da_taka((float)$a['closing']); ?></b>
        </span>
      </div>
      <?php endforeach; endforeach; endif; ?>
      <p class="da-note">রয়েছে = এই অ্যাকাউন্টে এখন কত। আজ জমা/খরচ শুধু এই তারিখের। ট্রান্সফার আয় নয়।</p>
    </article>
  </div>

  <nav class="da-tabs no-print">
    <?php foreach ($tabs as $k => $lab): ?>
    <a class="<?php echo $tab === $k ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars(da_qs(array_merge($keep, ['tab' => $k, 'page' => 1])), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></a>
    <?php endforeach; ?>
  </nav>

<?php if ($tab === 'summary'):
    $chart = da_chart_daily($db, $from, $to);
    $maxInc = 1;
    $maxExp = 1;
    foreach ($chart as $c) {
        $maxInc = max($maxInc, (float)$c['income'], (float)$c['in']);
        $maxExp = max($maxExp, (float)$c['expense'], (float)$c['out']);
    }
    $accs = da_accounts_statement($db, $from, $to);
    $userCol = da_user_collection_chart($db, $from, $to);
    $userMax = 1;
    foreach ($userCol as $uc) {
        $userMax = max($userMax, (float)$uc['n']);
    }
?>
  <section class="da-charts">
    <article class="da-panel">
      <h2>দৈনিক আয় বনাম ব্যয় / জমা বনাম উত্তোলন</h2>
      <?php if (!$chart): ?>
      <div class="da-empty">এই সময়ে কোনো লেনদেন নেই।</div>
      <?php else: ?>
      <div class="da-bars">
        <?php foreach ($chart as $c):
          $h1 = max(6, ((float)$c['income'] / $maxInc) * 110);
          $h2 = max(6, ((float)$c['expense'] / max($maxExp, 1)) * 110);
        ?>
        <div class="da-bar" title="<?php echo htmlspecialchars($c['date'] . ' আয় ' . $c['income'] . ' ব্যয় ' . $c['expense'], ENT_QUOTES, 'UTF-8'); ?>">
          <i style="height:<?php echo (int)$h1; ?>px"></i>
          <i class="exp" style="height:<?php echo (int)$h2; ?>px"></i>
          <span><?php echo htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="da-note">নীল = আয়/জমা · লাল = ব্যয়/উত্তোলন · ট্রান্সফার আয়-ব্যয়ে গণনা হয় না</p>
      <?php endif; ?>
    </article>
    <article class="da-panel">
      <h2>অ্যাকাউন্ট ব্যালেন্স</h2>
      <div class="da-legend">
        <?php if (!$accs): ?><div class="da-empty">অ্যাকাউন্ট নেই।</div><?php endif; ?>
        <?php foreach ($accs as $a): ?>
        <div><?php echo htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8'); ?> <small>(<?php echo htmlspecialchars($a['kind'], ENT_QUOTES, 'UTF-8'); ?>)</small> <b><?php echo da_taka((float)$a['current']); ?></b></div>
        <?php endforeach; ?>
      </div>
      <?php if ($userCol): ?>
      <h2 style="margin-top:16px">ইউজার অনুযায়ী আদায়</h2>
      <div class="da-legend">
        <?php foreach ($userCol as $uc): ?>
        <div><?php echo htmlspecialchars($uc['name'], ENT_QUOTES, 'UTF-8'); ?> <b><?php echo da_taka((float)$uc['n']); ?></b></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>
  </section>
  <article class="da-panel">
    <h2>হিসাবের নিয়ম</h2>
    <p class="da-note">ট্রান্সফার আয় বা ব্যয় নয়। কাস্টমার পাওনা আদায় আলাদা, বিক্রয় আয় আলাদা। ক্যাশ, ব্যাংক ও মোবাইল ব্যাংকিং মেশানো হয়নি। বাতিল/পেন্ডিং আলাদা ট্যাবে। সংখ্যাগুলো লাইভ খাতা থেকে।</p>
  </article>

<?php elseif ($tab === 'ledger'):
    $totalRows = da_ledger_count($db, $from, $to, $filters);
    $rows = da_ledger_rows($db, $from, $to, $filters, $per, $offset);
    $pages = max(1, (int)ceil($totalRows / $per));
?>
  <article class="da-panel">
    <h2>সম্পূর্ণ লেনদেনের ইতিহাস (<?php echo (int)$totalRows; ?>)</h2>
    <?php if (!$rows): ?>
    <div class="da-empty">এই ফিল্টারে কোনো লেনদেন নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap">
      <table class="da-table">
        <thead>
          <tr>
            <th>#</th><th>তারিখ</th><th>সময়</th><th>ID</th><th>ধরন</th><th>মডিউল</th>
            <th>বিবরণ</th><th>ডেবিট</th><th>ক্রেডিট</th><th>অ্যাকাউন্ট</th><th>পদ্ধতি</th><th>স্ট্যাটাস</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r):
          $sn = $offset + $i + 1;
          $detail = da_qs($keep, ['ajax' => 'detail', 'kind' => 'ledger', 'id' => (int)$r['id']]);
        ?>
          <tr data-da-detail="<?php echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'); ?>">
            <td><?php echo $sn; ?></td>
            <td><?php echo htmlspecialchars(substr((string)$r['created_at'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(substr((string)$r['created_at'], 11, 8), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo $r['type'] === 'credit' ? 'ক্রেডিট' : 'ডেবিট'; ?></td>
            <td><?php echo htmlspecialchars(da_ref_label((string)$r['ref_type']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$r['note'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="da-dr"><?php echo $r['type'] === 'debit' ? da_taka((float)$r['amount']) : ''; ?></td>
            <td class="da-cr"><?php echo $r['type'] === 'credit' ? da_taka((float)$r['amount']) : ''; ?></td>
            <td><?php echo htmlspecialchars((string)$r['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(da_account_kind((string)$r['account_type']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>সম্পন্ন</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="da-pager">
      <?php if ($page > 1): ?><a class="da-btn ghost" href="<?php echo htmlspecialchars(da_qs($keep, ['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>">আগে</a><?php endif; ?>
      <span class="da-note"><?php echo $page; ?> / <?php echo $pages; ?></span>
      <?php if ($page < $pages): ?><a class="da-btn ghost" href="<?php echo htmlspecialchars(da_qs($keep, ['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>">পরে</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </article>

<?php elseif ($tab === 'received'):
    $recv = da_received_rows($db, $from, $to, 120);
?>
  <article class="da-panel">
    <h2>সম্পূর্ণ টাকা গ্রহণ</h2>
    <?php if (!$recv['ledger'] && !$recv['payments']): ?>
    <div class="da-empty">এই সময়ে কোনো জমা নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap">
      <table class="da-table">
        <thead><tr><th>তারিখ-সময়</th><th>উৎস</th><th>কাস্টমার</th><th>পরিমাণ</th><th>অ্যাকাউন্ট</th><th>পদ্ধতি</th><th>রেফারেন্স</th><th>মন্তব্য</th></tr></thead>
        <tbody>
        <?php foreach ($recv['payments'] as $p): ?>
          <tr>
            <td><?php echo htmlspecialchars($p['payment_date'] . ' ' . substr((string)$p['created_at'], 11, 8), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>কাস্টমার আদায়</td>
            <td><?php echo htmlspecialchars((string)$p['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="da-cr"><?php echo da_taka((float)$p['amount']); ?></td>
            <td><?php echo htmlspecialchars((string)$p['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$p['method'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$p['reference_no'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$p['note'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($recv['ledger'] as $p): ?>
          <tr data-da-detail="<?php echo htmlspecialchars(da_qs($keep, ['ajax' => 'detail', 'kind' => 'ledger', 'id' => (int)$p['id']]), ENT_QUOTES, 'UTF-8'); ?>">
            <td><?php echo htmlspecialchars((string)$p['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(da_ref_label((string)$p['ref_type']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>—</td>
            <td class="da-cr"><?php echo da_taka((float)$p['amount']); ?></td>
            <td><?php echo htmlspecialchars((string)$p['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(da_account_kind((string)$p['account_type']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$p['ref_id']; ?></td>
            <td><?php echo htmlspecialchars((string)$p['note'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </article>

<?php elseif ($tab === 'paid'):
    $paid = da_paid_rows($db, $from, $to, 120);
?>
  <article class="da-panel">
    <h2>সম্পূর্ণ টাকা ব্যয় / পেমেন্ট</h2>
    <?php if (!$paid['ledger'] && !$paid['suppliers']): ?>
    <div class="da-empty">এই সময়ে কোনো খরচ নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap">
      <table class="da-table">
        <thead><tr><th>তারিখ-সময়</th><th>ক্যাটাগরি</th><th>বিবরণ</th><th>পরিমাণ</th><th>অ্যাকাউন্ট</th><th>প্রাপক</th><th>ভাউচার</th></tr></thead>
        <tbody>
        <?php foreach ($paid['suppliers'] as $p): ?>
          <tr>
            <td><?php echo htmlspecialchars($p['payment_date'] . ' ' . substr((string)$p['created_at'], 11, 8), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>সাপ্লায়ার পেমেন্ট</td>
            <td><?php echo htmlspecialchars((string)$p['note'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="da-dr"><?php echo da_taka((float)$p['amount']); ?></td>
            <td><?php echo htmlspecialchars((string)$p['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$p['supplier_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$p['payment_no'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($paid['ledger'] as $p): ?>
          <tr data-da-detail="<?php echo htmlspecialchars(da_qs($keep, ['ajax' => 'detail', 'kind' => 'ledger', 'id' => (int)$p['id']]), ENT_QUOTES, 'UTF-8'); ?>">
            <td><?php echo htmlspecialchars((string)$p['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)($p['category_name'] ?: da_ref_label((string)$p['ref_type'])), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$p['note'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="da-dr"><?php echo da_taka((float)$p['amount']); ?></td>
            <td><?php echo htmlspecialchars((string)$p['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td>—</td>
            <td>#<?php echo (int)$p['id']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </article>

<?php elseif ($tab === 'accounts'):
    $accs = da_accounts_statement($db, $from, $to);
    $lines = $accountFocus ? da_account_lines($db, $accountFocus, $from, $to) : [];
?>
  <article class="da-panel">
    <h2>অ্যাকাউন্ট অনুযায়ী সম্পূর্ণ স্টেটমেন্ট</h2>
    <div class="da-acc-grid">
      <?php if (!$accs): ?><div class="da-empty">অ্যাকাউন্ট নেই।</div><?php endif; ?>
      <?php foreach ($accs as $a): ?>
      <a class="da-acc" href="<?php echo htmlspecialchars(da_qs($keep, ['tab' => 'accounts', 'acc' => $a['id']]), ENT_QUOTES, 'UTF-8'); ?>">
        <h3><?php echo htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($a['kind'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <dl>
          <dt>শুরুর ব্যালেন্স</dt><dd><?php echo da_taka((float)$a['opening']); ?></dd>
          <dt>জমা</dt><dd class="da-cr"><?php echo da_taka((float)$a['deposit']); ?></dd>
          <dt>উত্তোলন</dt><dd class="da-dr"><?php echo da_taka((float)$a['withdraw']); ?></dd>
          <dt>ট্রান্সফার ইন</dt><dd><?php echo da_taka((float)$a['transfer_in']); ?></dd>
          <dt>ট্রান্সফার আউট</dt><dd><?php echo da_taka((float)$a['transfer_out']); ?></dd>
          <dt>শেষ ব্যালেন্স</dt><dd><?php echo da_taka((float)$a['closing']); ?></dd>
          <dt>বর্তমান</dt><dd><?php echo da_taka((float)$a['current']); ?></dd>
        </dl>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if ($accountFocus && $lines): ?>
    <h2 style="margin-top:16px">স্টেটমেন্ট</h2>
    <div class="da-table-wrap">
      <table class="da-table">
        <thead><tr><th>তারিখ</th><th>ধরন</th><th>উৎস</th><th>বিবরণ</th><th>ডেবিট</th><th>ক্রেডিট</th><th>ব্যালেন্স</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo $r['type'] === 'credit' ? 'ক্রেডিট' : 'ডেবিট'; ?></td>
            <td><?php echo htmlspecialchars(da_ref_label((string)$r['ref_type']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$r['note'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="da-dr"><?php echo $r['type'] === 'debit' ? da_taka((float)$r['amount']) : ''; ?></td>
            <td class="da-cr"><?php echo $r['type'] === 'credit' ? da_taka((float)$r['amount']) : ''; ?></td>
            <td><?php echo da_taka((float)$r['balance_after']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </article>

<?php elseif ($tab === 'transfers'):
    $xf = da_transfer_rows($db, $from, $to);
?>
  <article class="da-panel">
    <h2>অ্যাকাউন্ট ট্রান্সফার — একবারই গণনা</h2>
    <p class="da-note">প্রতিটি ট্রান্সফার আয়/ব্যয় নয়। ডেবিট পাশ থেকে একবার দেখানো হচ্ছে।</p>
    <?php if (!$xf): ?>
    <div class="da-empty">এই সময়ে কোনো ট্রান্সফার নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap">
      <table class="da-table">
        <thead><tr><th>তারিখ-সময়</th><th>থেকে</th><th>প্রতি</th><th>পরিমাণ</th><th>রেফারেন্স</th></tr></thead>
        <tbody>
        <?php foreach ($xf as $t): ?>
          <tr>
            <td><?php echo htmlspecialchars((string)$t['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$t['from_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$t['to_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo da_taka((float)$t['amount']); ?></td>
            <td><?php echo htmlspecialchars((string)$t['note'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </article>

<?php elseif ($tab === 'bills'):
    $bills = da_bill_rows($db, $from, $to);
    $outst = da_outstanding($db);
?>
  <article class="da-panel">
    <h2>বিল পরিশোধ ও পাওনা আদায়</h2>
    <h3>কাস্টমার আদায়</h3>
    <?php if (!$bills['collections']): ?><div class="da-empty">আদায় নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap"><table class="da-table"><thead><tr><th>তারিখ</th><th>কাস্টমার</th><th>পরিমাণ</th><th>অ্যাকাউন্ট</th><th>রসিদ</th></tr></thead><tbody>
      <?php foreach ($bills['collections'] as $p): ?>
      <tr>
        <td><?php echo htmlspecialchars((string)$p['payment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$p['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="da-cr"><?php echo da_taka((float)$p['amount']); ?></td>
        <td><?php echo htmlspecialchars((string)$p['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$p['reference_no'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <h3>সাপ্লায়ার / বিল</h3>
    <?php if (!$bills['supplier'] && !$bills['expenses']): ?><div class="da-empty">বিল পেমেন্ট নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap"><table class="da-table"><thead><tr><th>তারিখ</th><th>প্রাপক</th><th>পরিমাণ</th><th>অ্যাকাউন্ট</th><th>ভাউচার</th></tr></thead><tbody>
      <?php foreach ($bills['supplier'] as $p): ?>
      <tr>
        <td><?php echo htmlspecialchars((string)$p['payment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$p['supplier_name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="da-dr"><?php echo da_taka((float)$p['amount']); ?></td>
        <td><?php echo htmlspecialchars((string)$p['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$p['payment_no'], ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <?php endforeach; ?>
      <?php foreach ($bills['expenses'] as $p): ?>
      <tr>
        <td><?php echo htmlspecialchars((string)$p['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars(da_ref_label((string)$p['ref_type']), ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="da-dr"><?php echo da_taka((float)$p['amount']); ?></td>
        <td><?php echo htmlspecialchars((string)$p['account_name'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td>#<?php echo (int)$p['id']; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <h3>বর্তমান পাওনা / দেনা</h3>
    <div class="da-acc-grid">
      <div>
        <p class="da-note">কাস্টমার পাওনা (খাতার ব্যালেন্স — নগদ জমা নয়)</p>
        <?php foreach ($outst['customers'] as $c): ?>
        <div><?php echo htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'); ?> <b><?php echo da_taka((float)$c['receivable_balance']); ?></b></div>
        <?php endforeach; ?>
        <?php if (!$outst['customers']): ?><div class="da-empty">পাওনা নেই।</div><?php endif; ?>
      </div>
      <div>
        <p class="da-note">সাপ্লায়ার দেনা</p>
        <?php foreach ($outst['suppliers'] as $c): ?>
        <div><?php echo htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'); ?> <b><?php echo da_taka((float)($c['payable_balance'] ?? 0)); ?></b></div>
        <?php endforeach; ?>
        <?php if (!$outst['suppliers']): ?><div class="da-empty">দেনা নেই।</div><?php endif; ?>
      </div>
    </div>
  </article>

<?php elseif ($tab === 'users'):
    $ulist = da_user_rows($db, $from, $to);
    $uacts = $userFocus ? da_user_activity($db, $userFocus, $from, $to, 60) : [];
    $ulogin = $userFocus ? da_login_sessions($db, $userFocus, $from, $to) : [];
?>
  <article class="da-panel">
    <h2>ইউজার অনুযায়ী আর্থিক অডিট</h2>
    <?php if (!$ulist): ?><div class="da-empty">এই সময়ে ইউজার কর্মকাণ্ড নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap">
      <table class="da-table">
        <thead><tr><th>নাম</th><th>ID</th><th>রোল</th><th>কাজ</th><th>লগইন</th><th>বিক্রয়</th><th>গ্রহণ</th><th>ব্যয়</th><th>ইনভয়েস</th><th>সম্পাদনা</th><th>বাতিল</th></tr></thead>
        <tbody>
        <?php foreach ($ulist as $u):
          if ($filters['user'] && $filters['user'] !== (int)$u['id']) {
              continue;
          }
        ?>
          <tr data-da-detail="<?php echo htmlspecialchars(da_qs($keep, ['ajax' => 'detail', 'kind' => 'user', 'id' => (int)$u['id']]), ENT_QUOTES, 'UTF-8'); ?>">
            <td><a href="<?php echo htmlspecialchars(da_qs($keep, ['tab' => 'users', 'uid' => (int)$u['id']]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
            <td><?php echo (int)$u['id']; ?></td>
            <td><?php echo htmlspecialchars((string)$u['role'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$u['actions']; ?></td>
            <td><?php echo (int)$u['login']; ?></td>
            <td><?php echo da_taka((float)$u['sales']); ?></td>
            <td class="da-cr"><?php echo da_taka((float)$u['received']); ?></td>
            <td class="da-dr"><?php echo da_taka((float)$u['paid']); ?></td>
            <td><?php echo (int)$u['invoices']; ?></td>
            <td><?php echo (int)$u['edits']; ?></td>
            <td><?php echo (int)$u['cancels']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <?php if ($userFocus): ?>
    <h2 style="margin-top:16px"><?php echo htmlspecialchars(da_user_name($db, $userFocus), ENT_QUOTES, 'UTF-8'); ?> — বিস্তারিত</h2>
    <?php if ($ulogin): ?>
    <p class="da-note">লগইন/লগআউট</p>
    <ul><?php foreach ($ulogin as $lg): ?><li><?php echo htmlspecialchars($lg['created_at'] . ' · ' . $lg['action'], ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if (!$uacts): ?><div class="da-empty">কর্মকাণ্ড নেই।</div>
    <?php else: ?>
    <div class="da-table-wrap"><table class="da-table"><thead><tr><th>সময়</th><th>মডিউল</th><th>কাজ</th><th>পক্ষ</th><th>টাকা</th><th>পরিবর্তন</th></tr></thead><tbody>
      <?php foreach ($uacts as $act): ?>
      <tr>
        <td><?php echo htmlspecialchars((string)$act['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$act['module'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$act['action'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)($act['party_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo $act['amount_paid'] !== null ? da_taka((float)$act['amount_paid']) : ($act['amount_total'] !== null ? da_taka((float)$act['amount_total']) : ''); ?></td>
        <td><?php
          if (!empty($act['old_value']) || !empty($act['new_value']) || !empty($act['old_json'])) {
              echo '<details class="da-more"><summary>দেখুন</summary><p>আগে: '
                . htmlspecialchars((string)($act['old_value'] ?: $act['old_json']), ENT_QUOTES, 'UTF-8')
                . '<br>পরে: ' . htmlspecialchars((string)($act['new_value'] ?: $act['new_json']), ENT_QUOTES, 'UTF-8') . '</p></details>';
          }
        ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php endif; ?>
  </article>

<?php elseif ($tab === 'hundred'):
    $days = da_days($db, 100);
    $pick = (string)($_GET['day'] ?? '');
?>
  <article class="da-panel">
    <h2>গত ১০০ দিনের সম্পূর্ণ হিসাব</h2>
    <form class="da-filter-row no-print" method="get" style="margin-bottom:10px">
      <input type="hidden" name="tab" value="hundred">
      <input type="hidden" name="range" value="100">
      <div>
        <label>নির্দিষ্ট দিন</label>
        <input type="date" name="day" value="<?php echo htmlspecialchars($pick, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div><button type="submit">দিন খুলুন</button></div>
    </form>
    <?php if ($pick && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pick)):
      $ds = da_summary($db, $pick, $pick);
    ?>
    <div class="da-ok"><?php echo htmlspecialchars($pick, ENT_QUOTES, 'UTF-8'); ?> · আয় <?php echo da_taka((float)$ds['income']); ?> · ব্যয় <?php echo da_taka((float)$ds['expense']); ?> · শেষ <?php echo da_taka((float)$ds['closing']); ?></div>
    <?php endif; ?>
    <div class="da-table-wrap">
      <table class="da-table">
        <thead><tr><th>তারিখ</th><th>শুরু</th><th>আয়</th><th>আদায়</th><th>ব্যয়</th><th>পেমেন্ট</th><th>ট্রান্সফার</th><th>রিফান্ড</th><th>পাওনা</th><th>শেষ</th><th>লেনদেন</th><th>ইউজার</th></tr></thead>
        <tbody>
        <?php foreach ($days as $d): ?>
          <tr data-da-detail="<?php echo htmlspecialchars(da_qs($keep, ['ajax' => 'detail', 'kind' => 'day', 'date' => $d['date']]), ENT_QUOTES, 'UTF-8'); ?>">
            <td><?php echo htmlspecialchars($d['date'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo da_taka((float)$d['opening']); ?></td>
            <td class="da-cr"><?php echo da_taka((float)$d['income']); ?></td>
            <td><?php echo da_taka((float)$d['collection']); ?></td>
            <td class="da-dr"><?php echo da_taka((float)$d['expenses']); ?></td>
            <td><?php echo da_taka((float)$d['payments']); ?></td>
            <td><?php echo da_taka((float)$d['transfers']); ?></td>
            <td><?php echo da_taka((float)$d['refunds']); ?></td>
            <td><?php echo da_taka((float)$d['outstanding']); ?></td>
            <td><?php echo da_taka((float)$d['closing']); ?></td>
            <td><?php echo (int)$d['txn']; ?></td>
            <td><?php echo (int)$d['users']; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>

<?php elseif ($tab === 'close'):
    $cdate = $from === $to ? $from : da_today();
    $cv = da_closing_view($db, $cdate);
    $s = $cv['summary'];
    $diffWarn = $cv['difference'] !== null && abs((float)$cv['difference']) >= 0.01;
?>
  <article class="da-panel">
    <h2>দৈনিক ক্লোজিং · <?php echo htmlspecialchars($cdate, ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php if ($diffWarn): ?><div class="da-warn">সতর্কতা: ক্যাশ অমিল <?php echo da_taka((float)$cv['difference'], true); ?></div><?php endif; ?>
    <dl class="da-kv">
      <dt>Opening</dt><dd><?php echo da_taka((float)$s['opening']); ?></dd>
      <dt>Cash received</dt><dd><?php echo da_taka((float)$s['cash_in']); ?></dd>
      <dt>Bank received</dt><dd><?php echo da_taka((float)$s['bank_in']); ?></dd>
      <dt>Mobile received</dt><dd><?php echo da_taka((float)$s['mfs_in']); ?></dd>
      <dt>Expenses</dt><dd><?php echo da_taka((float)$s['expense']); ?></dd>
      <dt>Payments</dt><dd><?php echo da_taka((float)$s['withdraw']); ?></dd>
      <dt>Transfers in</dt><dd><?php echo da_taka((float)$s['transfer_in']); ?></dd>
      <dt>Transfers out</dt><dd><?php echo da_taka((float)$s['transfer_out']); ?></dd>
      <dt>System closing</dt><dd><?php echo da_taka((float)$s['closing']); ?></dd>
      <?php if ($cv['physical'] !== null): ?>
      <dt>Physical cash</dt><dd><?php echo da_taka((float)$cv['physical']); ?></dd>
      <dt>Difference</dt><dd><?php echo da_taka((float)$cv['difference'], true); ?></dd>
      <?php endif; ?>
    </dl>
    <p class="da-note">ক্লোজিং শুধু স্ন্যাপশট রাখে। ক্যাশ/ব্যাংক/বিক্রয় খাতায় কোনো এন্ট্রি হয় না। পুরোনো দিন লক।</p>
    <form method="post" class="da-filter-row no-print" style="margin-top:12px">
      <input type="hidden" name="da_action" value="close">
      <input type="hidden" name="da_token" value="<?php echo htmlspecialchars((string)$_SESSION['da_close_token'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="close_date" value="<?php echo htmlspecialchars($cdate, ENT_QUOTES, 'UTF-8'); ?>">
      <div>
        <label>হাতে গোনা ক্যাশ</label>
        <input type="number" step="0.01" name="physical_cash" value="<?php echo htmlspecialchars((string)($cv['physical'] ?? $s['closing']), ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div>
        <label>অমিলের কারণ</label>
        <input type="text" name="difference_note" maxlength="400" placeholder="অমিল থাকলে বাধ্যতামূলক">
      </div>
      <div><button type="submit" class="da-btn">ক্লোজিং সংরক্ষণ</button></div>
    </form>
  </article>

<?php else:
    $report = (string)($_GET['report'] ?? 'daily');
    $reports = [
        'daily' => 'Daily Financial Report',
        'ledger' => 'Date Wise Transaction Report',
        'cash' => 'Cash Book',
        'bank' => 'Bank Book',
        'accounts' => 'Account Wise Ledger',
        'income' => 'Income Report',
        'expense' => 'Expense Report',
        'collection' => 'Customer Collection Report',
        'supplier' => 'Supplier Payment Report',
        'bills' => 'Bill Payment Report',
        'users' => 'User Wise Financial Report',
        'activity' => 'User Activity Report',
        'transfers' => 'Account Transfer Report',
        'close' => 'Daily Closing Report',
        'recon' => 'Cash Reconciliation Report',
        'outstanding' => 'Outstanding Report',
        'refund' => 'Refund Report',
        'cancelled' => 'Cancelled Transaction Report',
        'edited' => 'Edited Transaction Report',
        'audit' => 'Audit Trail Report',
    ];
    $edits = da_audit_edits($db, $from, $to);
    $canc = da_cancelled_rows($db, $from, $to);
    $outst = da_outstanding($db);
?>
  <article class="da-panel">
    <h2>রিপোর্ট — একই পেজ</h2>
    <div class="da-presets">
      <?php foreach ($reports as $rk => $rl): ?>
      <a class="<?php echo $report === $rk ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars(da_qs($keep, ['tab' => 'reports', 'report' => $rk]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rl, ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (in_array($report, ['cancelled', 'edited', 'audit'], true)): ?>
      <h3>বাতিল / সম্পাদনা / অডিট ট্রেইল</h3>
      <?php if ($canc['sales'] || $canc['ledger']): ?>
      <div class="da-table-wrap"><table class="da-table"><thead><tr><th>তারিখ</th><th>ধরন</th><th>বিবরণ</th><th>টাকা</th></tr></thead><tbody>
        <?php foreach ($canc['sales'] as $r): ?>
        <tr><td><?php echo htmlspecialchars((string)$r['sale_date'], ENT_QUOTES, 'UTF-8'); ?></td><td>বাতিল বিক্রয়</td><td><?php echo htmlspecialchars((string)$r['invoice_number'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo da_taka((float)$r['net_payable']); ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($canc['ledger'] as $r): ?>
        <tr><td><?php echo htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(da_ref_label((string)$r['ref_type']), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)$r['note'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo da_taka((float)$r['amount']); ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
      <?php if ($edits): ?>
      <div class="da-table-wrap"><table class="da-table"><thead><tr><th>সময়</th><th>ইউজার</th><th>কাজ</th><th>আগে</th><th>পরে</th></tr></thead><tbody>
        <?php foreach ($edits as $e): ?>
        <tr>
          <td><?php echo htmlspecialchars((string)$e['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string)$e['username'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string)$e['action'] . ' / ' . $e['module'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string)($e['old_value'] ?: $e['old_json']), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string)($e['new_value'] ?: $e['new_json']), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php else: ?><div class="da-empty">এই সময়ে সম্পাদনা/বাতিল রেকর্ড নেই।</div><?php endif; ?>
    <?php elseif ($report === 'outstanding'): ?>
      <?php foreach ($outst['customers'] as $c): ?>
      <div><?php echo htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'); ?> · পাওনা <?php echo da_taka((float)$c['receivable_balance']); ?></div>
      <?php endforeach; ?>
      <?php if (!$outst['customers']): ?><div class="da-empty">পাওনা নেই।</div><?php endif; ?>
    <?php else: ?>
      <dl class="da-kv">
        <?php foreach (da_card_meta() as $k => $meta): ?>
        <dt><?php echo htmlspecialchars($meta[0], ENT_QUOTES, 'UTF-8'); ?></dt>
        <dd><?php echo da_taka((float)($summary[$k] ?? 0)); ?></dd>
        <?php endforeach; ?>
      </dl>
      <p class="da-note">বিস্তারিত সারি উপরের ট্যাব ও CSV/Excel থেকে পাবেন। PDF-এর জন্য প্রিন্ট ব্যবহার করুন — প্রতিষ্ঠানের নাম ও তারিখ শিরোনামে আছে।</p>
    <?php endif; ?>
  </article>
<?php endif; ?>

  <p class="da-note">রিপোর্ট নং DA-<?php echo htmlspecialchars(str_replace('-', '', $from) . '-' . str_replace('-', '', $to), ENT_QUOTES, 'UTF-8'); ?> · তৈরি <?php echo date('d-m-Y H:i'); ?> (Asia/Dhaka) · <?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<div class="da-drawer" id="da-drawer">
  <div class="da-drawer-card" id="da-drawer-body"></div>
</div>
<script src="/assets/js/daily-audit.js?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php include '../../partials/footer.php'; ?>
