<?php
/* 10.98.741 */
$star_ids = $star_ids ?? [];
$today_outcomes = $today_outcomes ?? [];
$date_from = $date_from ?? '';
$date_to = $date_to ?? '';
$search = $search ?? '';
$overdue_count = $overdue_count ?? 0;
$today_count = $today_count ?? 0;
$upcoming_count = $upcoming_count ?? 0;
$watch_count = $watch_count ?? 0;
$outcome_labels = $outcome_labels ?? ['talked' => 'কথা হয়েছে', 'no_answer' => 'কল হয়নি', 'phone_off' => 'মোবাইল বন্ধ'];
$customers = $customers ?? [];
$today = $today ?? date('Y-m-d');
$last_pay_map = $last_pay_map ?? [];
$ame_due_groups = $ame_due_groups ?? [];
$ame_due_areas = $ame_due_areas ?? [];
$notify_enabled = $notify_enabled ?? true;
$ame_due_sms_csrf = $ame_due_sms_csrf ?? (function_exists('csrf_token') ? csrf_token() : '');
$ame_due_sms_default = $ame_due_sms_default ?? "বকেয়া পরিশোধের অনুস্মারক।\nনাম: {name}\nবাকি: {due}\nতারিখ: {date}\nধন্যবাদ।";
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.487', ENT_QUOTES, 'UTF-8');

$bn_date = static function ($value) {
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00') {
        return '—';
    }
    $t = strtotime($raw);
    return $t ? date('d/m/Y', $t) : $raw;
};
$range_label = 'সক্রিয় সব নোটিফিকেশন';
if ($date_from !== '' && $date_to !== '') {
    $range_label = $bn_date($date_from) . ' হতে ' . $bn_date($date_to) . ' তারিখে';
} elseif ($date_from !== '') {
    $range_label = $bn_date($date_from) . ' থেকে';
} elseif ($date_to !== '') {
    $range_label = $bn_date($date_to) . ' পর্যন্ত';
}

$sum_taka = isset($sum_taka) ? (float)$sum_taka : 0.0;
$sum_due = isset($sum_due) ? (float)$sum_due : 0.0;
$due_page = (int)($due_page ?? 1);
$due_pages = (int)($due_pages ?? 1);
$due_total = (int)($due_total ?? count($customers));
$due_row_base = (int)($due_row_base ?? 0);
$due_page_url = $due_page_url ?? static function (int $target): string {
    return 'due-reminder.php' . ($target > 1 ? ('?page=' . $target) : '');
};
$code_slash = static function ($code, $id): string {
    $code = trim((string)$code);
    if ($code === '') {
        $code = (string)$id;
    }
    return substr($code, -1) === '/' ? $code : ($code . '/');
};
?>
<link rel="stylesheet" href="/assets/css/due-reminder.css?v=<?php echo $av; ?>">
<div class="ame-due-page ame-due-notify">
  <div class="ame-due-notify-head">
    <div>
      <h1>কাস্টমার বকেয়া নোটিফিকেশন</h1>
      <p>নির্ধারিত বকেয়া এবং টানা ৭ দিন পেমেন্ট না-পাওয়া সব কাস্টমারকে একই পেজে দেখাবে। ঘণ্টা বন্ধ করলেও তালিকা থাকবে।</p>
    </div>
    <form method="post" class="ame-due-notify-switch">
      <?php echo csrf_field(); ?>
      <button type="submit" name="due_notify_toggle" value="<?php echo $notify_enabled ? '0' : '1'; ?>" class="<?php echo $notify_enabled ? 'is-on' : 'is-off'; ?>">
        <?php echo $notify_enabled ? 'চালু · বন্ধ করুন' : 'বন্ধ · চালু করুন'; ?>
      </button>
    </form>
  </div>

  <div class="ame-due-summary">
    <article class="is-over"><span class="num"><?php echo (int)$overdue_count; ?></span><span class="lbl">মেয়াদ পার হয়েছে</span></article>
    <article class="is-today"><span class="num"><?php echo (int)$today_count; ?></span><span class="lbl">আজকের তারিখ</span></article>
    <article class="is-soon"><span class="num"><?php echo (int)$upcoming_count; ?></span><span class="lbl">আগামী দিনে</span></article>
    <article class="is-watch"><span class="num"><?php echo (int)$watch_count; ?></span><span class="lbl">৭ দিন পেমেন্ট নেই</span></article>
  </div>

  <form method="GET" class="ame-due-filter">
    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" title="শুরুর তারিখ">
    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" title="শেষ তারিখ">
    <input class="ame-due-search" id="ameDueSearch" type="search" name="search" autocomplete="off" placeholder="আইডি, নাম, মোবাইল, এরিয়া বা গ্রুপ — লিখলেই সামনে আসবে" value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit">সার্চ</button>
  </form>

  <?php if (!$customers): ?>
    <div class="ame-due-empty">এখন কোনো সক্রিয় বকেয়া নোটিফিকেশন নেই।</div>
  <?php else: ?>
  <div class="ame-due-banner" data-due-total="<?php echo (int)$due_total; ?>"><?php echo htmlspecialchars($range_label); ?> · মোট <?php echo (int)$due_total; ?> জন · প্রতি পাতায় ১০০ জন · পৃষ্ঠা <?php echo (int)$due_page; ?> / <?php echo (int)$due_pages; ?></div>
  <?php if ($due_pages > 1): ?>
  <nav class="ame-due-pages no-print" aria-label="বকেয়া পাতা">
    <?php if ($due_page > 1): ?><a href="<?php echo htmlspecialchars($due_page_url($due_page - 1), ENT_QUOTES, 'UTF-8'); ?>">আগের</a><?php endif; ?>
    <?php
    $due_from = max(1, $due_page - 2);
    $due_to = min($due_pages, $due_page + 2);
    for ($dpi = $due_from; $dpi <= $due_to; $dpi++):
    ?>
    <?php if ($dpi === $due_page): ?><span class="is-now"><?php echo (int)$dpi; ?></span><?php else: ?><a href="<?php echo htmlspecialchars($due_page_url($dpi), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int)$dpi; ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($due_page < $due_pages): ?><a href="<?php echo htmlspecialchars($due_page_url($due_page + 1), ENT_QUOTES, 'UTF-8'); ?>">পরের</a><?php endif; ?>
    <span class="ame-due-pages-meta">এই পাতায় <?php echo count($customers); ?> জন</span>
  </nav>
  <?php endif; ?>
  <div class="ame-due-table-wrap">
    <table class="ame-due-table" data-ame-no-page="1">
      <thead>
        <tr>
          <th class="ame-due-check"><input type="checkbox" id="ameDueCheckAll" title="এই পাতার সব"></th>
          <th>#</th>
          <th>আইডি</th>
          <th>নাম</th>
          <th>মোবাইল</th>
          <th>পরিশোধের তারিখ</th>
          <th>নোট</th>
          <th>টাকা</th>
          <th>বাকি</th>
          <th>সর্বশেষ পরিশোধ</th>
          <th>আজকের আদায়করণ</th>
          <th>অবস্থা</th>
          <th>কাজ</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($customers as $i => $c):
          $meta = ame_due_row_badge($c, $today);
          $cid = (int)$c['id'];
          $current_outcome = $today_outcomes[$cid] ?? '';
          $due_amount = $c['next_due_amount'] ?? '';
          $is_star = !empty($star_ids[$cid]);
          $bal = (float)($c['receivable_balance'] ?? 0);
          $due_now = $bal > 0 ? $bal : 0.0;
          $taka = ($due_amount !== null && $due_amount !== '') ? (float)$due_amount : $due_now;
          $last_pay = $last_pay_map[$cid] ?? null;
          $last_pay_text = $last_pay
              ? ('তারিখ:' . $bn_date($last_pay['payment_date'] ?? '') . ', টাকা:' . number_format((float)($last_pay['amount'] ?? 0), 0))
              : '';
          $phone = trim((string)($c['phone'] ?? ''));
          $name = (string)($c['name'] ?? '');
          $due_date = function_exists('ame_due_date_value')
              ? ame_due_date_value($c['next_due_date'] ?? '')
              : trim((string)($c['next_due_date'] ?? ''));
          $show_date = $due_date !== '' ? $due_date : (string)($meta['date'] ?? '');
          $code = $code_slash($c['customer_code'] ?? '', $cid);
          $note = trim((string)($c['due_note'] ?? ''));
          $row_class = 'ame-due-tr';
          if (($meta['badge'] ?? '') === 'badge-overdue' || !empty($meta['watch_ready'])) {
              $row_class .= ' is-overdue';
          } elseif (($meta['badge'] ?? '') === 'badge-today') {
              $row_class .= ' is-today';
          }
      ?>
        <tr class="<?php echo htmlspecialchars($row_class); ?>" id="row-<?php echo $cid; ?>" data-customer-id="<?php echo $cid; ?>"
            data-s-id="<?php echo $cid; ?>"
            data-s-code="<?php echo htmlspecialchars((string)($c['customer_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            data-s-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
            data-s-phone="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
            data-s-alt="<?php echo htmlspecialchars((string)($c['alt_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            data-s-address="<?php echo htmlspecialchars((string)($c['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            data-s-group="<?php echo htmlspecialchars((string)($ame_due_groups[(int)($c['group_id'] ?? 0)] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            data-s-area="<?php echo htmlspecialchars((string)($ame_due_areas[(int)($c['area_id'] ?? 0)] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <td class="ame-due-check" data-label="">
            <input type="checkbox" class="ame-due-row-check" value="<?php echo $cid; ?>"
              data-phone="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
              data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
              data-due="<?php echo htmlspecialchars((string)$due_now, ENT_QUOTES, 'UTF-8'); ?>"
              data-date="<?php echo htmlspecialchars($show_date, ENT_QUOTES, 'UTF-8'); ?>">
          </td>
          <td data-label="ক্রম"><?php echo (int)$due_row_base + (int)$i + 1; ?></td>
          <td data-label="আইডি">
            <button type="button" class="ame-due-code cus-id-link" data-due-info="<?php echo $cid; ?>"><?php echo htmlspecialchars($code); ?></button>
          </td>
          <td class="ame-due-name-cell" data-label="নাম"><?php echo htmlspecialchars($name); ?><?php if ($is_star): ?><span class="star-cus" title="স্টার কাস্টমার">★</span><?php endif; ?></td>
          <td data-label="মোবাইল"><?php echo $phone !== '' ? htmlspecialchars($phone) : ''; ?></td>
          <td data-label="পরিশোধের তারিখ"><span class="ame-due-date <?php echo htmlspecialchars((string)$meta['badge']); ?>"><?php echo htmlspecialchars($bn_date($show_date)); ?></span></td>
          <td class="ame-due-note-cell" data-label="নোট"><?php echo $note !== '' ? htmlspecialchars($note) : '—'; ?></td>
          <td class="ame-due-num" data-label="টাকা"><?php echo number_format($taka, 0); ?></td>
          <td class="ame-due-num" data-label="বাকি"><?php echo number_format($due_now, 0); ?></td>
          <td class="ame-due-last" data-label="সর্বশেষ পরিশোধ"><?php echo htmlspecialchars($last_pay_text !== '' ? $last_pay_text : '—'); ?></td>
          <td class="ame-due-today" id="today-<?php echo $cid; ?>" data-label="আজকের আদায়করণ"><?php echo htmlspecialchars($outcome_labels[$current_outcome] ?? ''); ?></td>
          <td class="ame-due-status" id="outcome-<?php echo $cid; ?>" data-label="অবস্থা">
            <label><input type="radio" name="out-<?php echo $cid; ?>" value="talked" <?php echo $current_outcome === 'talked' ? 'checked' : ''; ?> data-due-outcome="<?php echo $cid; ?>" data-outcome="talked"></label>
            <label><input type="radio" name="out-<?php echo $cid; ?>" value="no_answer" <?php echo $current_outcome === 'no_answer' ? 'checked' : ''; ?> data-due-outcome="<?php echo $cid; ?>" data-outcome="no_answer"></label>
            <label><input type="radio" name="out-<?php echo $cid; ?>" value="phone_off" <?php echo $current_outcome === 'phone_off' ? 'checked' : ''; ?> data-due-outcome="<?php echo $cid; ?>" data-outcome="phone_off"></label>
          </td>
          <td class="ame-due-cmd" data-label="কাজ">
            <button type="button" class="ame-due-edit"
              data-due-edit="1"
              data-id="<?php echo $cid; ?>"
              data-code="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
              data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
              data-total="<?php echo htmlspecialchars((string)$bal, ENT_QUOTES, 'UTF-8'); ?>"
              data-expected="<?php echo htmlspecialchars($due_amount !== null && $due_amount !== '' ? (string)$due_amount : '', ENT_QUOTES, 'UTF-8'); ?>"
              data-date="<?php echo htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'); ?>"
              data-note="<?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?>"
              title="সংশোধন">সংশোধন</button>
            <button type="button" class="ame-due-del" data-due-del="<?php echo $cid; ?>" data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" title="মুছুন">🗑</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="7" class="ame-due-foot-lab" data-label="মোট">সব পাতার মোট</td>
          <td class="ame-due-num" data-label="টাকা"><?php echo number_format($sum_taka, 0); ?></td>
          <td class="ame-due-num" data-label="বাকি"><?php echo number_format($sum_due, 0); ?></td>
          <td colspan="4" data-label=""></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php if ($due_pages > 1): ?>
  <nav class="ame-due-pages no-print" aria-label="বকেয়া পাতা নিচ">
    <?php if ($due_page > 1): ?><a href="<?php echo htmlspecialchars($due_page_url($due_page - 1), ENT_QUOTES, 'UTF-8'); ?>">আগের</a><?php endif; ?>
    <?php if ($due_page < $due_pages): ?><a href="<?php echo htmlspecialchars($due_page_url($due_page + 1), ENT_QUOTES, 'UTF-8'); ?>">পরের</a><?php endif; ?>
    <span class="ame-due-pages-meta">পৃষ্ঠা <?php echo (int)$due_page; ?> / <?php echo (int)$due_pages; ?></span>
  </nav>
  <?php endif; ?>

  <div class="ame-due-smsbar">
    <label class="ame-due-sms-tick">
      <input type="checkbox" id="ameDueSmsConfirm">
      নির্বাচিত গ্রাহকদের বার্তা পাঠানোর ঘরে টিক দিন
    </label>
    <div class="ame-due-sms-btns">
      <button type="button" class="ame-due-sms-edit" id="ameDueSmsEdit">বার্তা সংশোধন</button>
      <button type="button" class="ame-due-sms-send" id="ameDueSmsSend">বার্তা পাঠান</button>
    </div>
    <p class="ame-due-sms-msg" id="ameDueSmsMsg" hidden></p>
  </div>
  <?php endif; ?>
</div>

<div class="ame-due-info-overlay" id="ameDueInfoOverlay" aria-hidden="true">
  <div class="ame-due-info-card" role="dialog" aria-modal="true" aria-labelledby="ameDueInfoTitle">
    <button type="button" class="ame-due-info-x" data-due-info-close aria-label="বন্ধ">&times;</button>
    <h3 id="ameDueInfoTitle">ক্রেতার তথ্য</h3>
    <div class="ame-due-info-grid">
      <div class="ame-due-info-rows" id="ameDueInfoRows"></div>
      <div class="ame-due-info-photo" id="ameDueInfoPhotoWrap" hidden>
        <img id="ameDueInfoPhoto" alt="">
      </div>
    </div>
    <p class="ame-due-info-err" id="ameDueInfoErr" hidden></p>
    <div class="ame-due-info-bar">
      <button type="button" class="ame-due-info-close" id="ameDueInfoClose" data-due-info-close>বন্ধ</button>
    </div>
  </div>
</div>

<div class="ame-due-sms-overlay" id="ameDueSmsOverlay" aria-hidden="true">
  <div class="ame-due-sms-card" role="dialog" aria-modal="true" aria-labelledby="ameDueSmsTitle">
    <button type="button" class="ame-due-edit-x" id="ameDueSmsClose" aria-label="বন্ধ">&times;</button>
    <h3 id="ameDueSmsTitle">বকেয়া বার্তা</h3>
    <p class="ame-due-sms-hint">{name} {due} {date} — পাঠানোর সময় প্রতি গ্রাহকের নামে বসবে।</p>
    <textarea id="ameDueSmsText" rows="7"><?php echo htmlspecialchars($ame_due_sms_default); ?></textarea>
    <button type="button" class="ame-due-edit-save" id="ameDueSmsSave">বার্তা রাখুন</button>
  </div>
</div>

<?php require_once __DIR__ . '/due-reminder-edit-modal.php'; ?>
<style>
.ame-due-table tr.ame-due-front td{background:#E3F2FD!important}
.ame-due-table tr.ame-due-front{box-shadow:inset 4px 0 0 #0D47A1}
</style>
<script>
(function () {
  var csrf = <?php echo json_encode($ame_due_sms_csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var all = document.getElementById('ameDueCheckAll');
  var overlay = document.getElementById('ameDueSmsOverlay');
  var textEl = document.getElementById('ameDueSmsText');
  var msgEl = document.getElementById('ameDueSmsMsg');
  var labels = { talked: 'কথা হয়েছে', no_answer: 'কল হয়নি', phone_off: 'মোবাইল বন্ধ' };

  function checks() { return Array.prototype.slice.call(document.querySelectorAll('.ame-due-row-check')); }
  function selected() { return checks().filter(function (el) { return el.checked; }); }
  function setMsg(text, ok) {
    if (!msgEl) { return; }
    msgEl.hidden = !text;
    msgEl.textContent = text || '';
    msgEl.style.color = ok ? '#166534' : '#b91c1c';
  }
  if (all) {
    all.addEventListener('change', function () {
      checks().forEach(function (el) { el.checked = all.checked; });
    });
  }
  function openSms() {
    if (!overlay) { return; }
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    if (textEl) { try { textEl.focus(); } catch (e) {} }
  }
  function closeSms() {
    if (!overlay) { return; }
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
  }
  var editBtn = document.getElementById('ameDueSmsEdit');
  if (editBtn) { editBtn.addEventListener('click', openSms); }
  var closeBtn = document.getElementById('ameDueSmsClose');
  if (closeBtn) { closeBtn.addEventListener('click', closeSms); }
  if (overlay) {
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { closeSms(); } });
  }
  var saveBtn = document.getElementById('ameDueSmsSave');
    if (saveBtn) {
    saveBtn.addEventListener('click', function () { closeSms(); setMsg('বার্তা রাখা হয়েছে।', true); });
  }
  var sendBtn = document.getElementById('ameDueSmsSend');
  if (sendBtn) {
    sendBtn.addEventListener('click', function () {
      var confirmEl = document.getElementById('ameDueSmsConfirm');
      if (!confirmEl || !confirmEl.checked) {
        setMsg('আগে বার্তা পাঠানোর ঘরে টিক দিন।', false);
        return;
      }
      var rows = selected();
      if (!rows.length) {
        setMsg('কমপক্ষে একজন গ্রাহক নির্বাচন করুন।', false);
        return;
      }
      var tpl = textEl ? textEl.value : '';
      if (!String(tpl).trim()) {
        setMsg('বার্তা খালি। সংশোধন করে লিখুন।', false);
        openSms();
        return;
      }
      if (!window.confirm(rows.length + ' জন গ্রাহককে বার্তা পাঠাবেন?')) { return; }
      setMsg('পাঠানো হচ্ছে...', true);
      sendBtn.disabled = true;
      var fd = new FormData();
      fd.append('csrf_token', csrf);
      fd.append('message', tpl);
      rows.forEach(function (el) { fd.append('ids[]', el.value); });
      fetch('/modules/customers/actions/send_due_sms.php', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          sendBtn.disabled = false;
          if (data && data.ok) {
            setMsg((data.message || 'পাঠানো হয়েছে।') + ' (' + (data.sent || 0) + ')', true);
          } else {
            setMsg((data && data.message) ? data.message : 'পাঠানো যায়নি।', false);
          }
        })
        .catch(function () {
          sendBtn.disabled = false;
          setMsg('সার্ভার এরর। আবার চেষ্টা করুন।', false);
        });
    });
  }

  function infoRow(label, value) {
    var wrap = document.createElement('div');
    wrap.className = 'ame-due-info-row';
    var a = document.createElement('span');
    a.textContent = label;
    var b = document.createElement('span');
    b.textContent = value == null || value === '' ? '' : String(value);
    wrap.appendChild(a);
    wrap.appendChild(b);
    return wrap;
  }
  function infoPhoneRow(label, phone) {
    var wrap = infoRow(label, '');
    if (phone) {
      var a = document.createElement('a');
      a.href = 'tel:' + String(phone).replace(/\s+/g, '');
      a.textContent = phone;
      wrap.lastChild.appendChild(a);
    }
    return wrap;
  }
  function money(n) {
    var x = Number(n || 0);
    return x.toFixed(0);
  }
  var infoOverlay = document.getElementById('ameDueInfoOverlay');
  var infoRows = document.getElementById('ameDueInfoRows');
  var infoErr = document.getElementById('ameDueInfoErr');
  var infoPhotoWrap = document.getElementById('ameDueInfoPhotoWrap');
  var infoPhoto = document.getElementById('ameDueInfoPhoto');
  var infoRequest = null;
  function closeInfo() {
    if (!infoOverlay) { return; }
    if (infoRequest && typeof infoRequest.abort === 'function') {
      infoRequest.abort();
    }
    infoRequest = null;
    infoOverlay.classList.remove('is-open');
    infoOverlay.setAttribute('aria-hidden', 'true');
  }
  function openInfo(id) {
    if (!infoOverlay || !infoRows) { return; }
    if (infoOverlay.parentNode !== document.body) {
      document.body.appendChild(infoOverlay);
    }
    infoOverlay.classList.add('is-open');
    infoOverlay.setAttribute('aria-hidden', 'false');
    infoRows.textContent = 'তথ্য আসছে...';
    if (infoErr) { infoErr.hidden = true; }
    if (infoPhotoWrap) { infoPhotoWrap.hidden = true; }
    if (infoRequest && typeof infoRequest.abort === 'function') {
      infoRequest.abort();
    }
    infoRequest = typeof AbortController === 'function' ? new AbortController() : null;
    var requestOptions = { credentials: 'same-origin' };
    if (infoRequest) { requestOptions.signal = infoRequest.signal; }
    fetch('/modules/customers/actions/customer_card.php?id=' + encodeURIComponent(id), requestOptions)
      .then(function (res) { return res.json(); })
      .then(function (data) {
        infoRequest = null;
        infoRows.textContent = '';
        if (!data || !data.ok || !data.customer) {
          if (infoErr) {
            infoErr.hidden = false;
            infoErr.textContent = (data && data.message) ? data.message : 'তথ্য আসেনি।';
          }
          return;
        }
        var c = data.customer;
        var code = c.code ? (String(c.code).slice(-1) === '/' ? c.code : c.code + '/') : (c.id + '/');
        infoRows.appendChild(infoRow('আইডি', code));
        infoRows.appendChild(infoRow('নাম', c.name || ''));
        infoRows.appendChild(infoRow('পিতার নাম', c.father_name || ''));
        infoRows.appendChild(infoPhoneRow('মোবাইল', c.phone || ''));
        infoRows.appendChild(infoRow('ঠিকানা', c.address || ''));
        infoRows.appendChild(infoRow('দোকান', c.shop || ''));
        infoRows.appendChild(infoRow('জামিনদারের নাম', c.guarantor_name || ''));
        infoRows.appendChild(infoPhoneRow('জামিনদারের মোবাইল', c.guarantor_phone || ''));
        infoRows.appendChild(infoRow('NID', c.nid || ''));
        infoRows.appendChild(infoRow('পেশা', c.occupation || ''));
        infoRows.appendChild(infoRow('প্রত্যাশিত বাকি', c.next_due_amount != null ? money(c.next_due_amount) : ''));
        infoRows.appendChild(infoRow('বাকি', money(c.receivable)));
        var fileRow = infoRow('পিডিএফ/ছবি ফাইল', '');
        if (c.pdf_url) {
          var link = document.createElement('a');
          link.href = c.pdf_url;
          link.target = '_blank';
          link.rel = 'noopener';
          link.textContent = 'ফাইল দেখুন';
          fileRow.lastChild.appendChild(link);
        }
        infoRows.appendChild(fileRow);
        if (c.photo && infoPhoto && infoPhotoWrap) {
          infoPhoto.src = c.photo;
          infoPhotoWrap.hidden = false;
        }
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') { return; }
        infoRequest = null;
        infoRows.textContent = '';
        if (infoErr) { infoErr.hidden = false; infoErr.textContent = 'সার্ভার এরর।'; }
      });
  }
  var infoClose = document.getElementById('ameDueInfoClose');
  if (infoClose) { infoClose.addEventListener('click', closeInfo); }
  if (infoOverlay) {
    infoOverlay.addEventListener('click', function (e) { if (e.target === infoOverlay) { closeInfo(); } });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && infoOverlay && infoOverlay.classList.contains('is-open')) {
      closeInfo();
    }
  });

  window.setOutcome = function (customerId, outcome) {
    var fd = new FormData();
    fd.append('customer_id', customerId);
    fd.append('outcome', outcome);
    fd.append('csrf_token', csrf);
    fetch('/modules/customers/actions/set_call_outcome.php', { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !data.success) { return; }
        var cell = document.getElementById('today-' + customerId);
        if (cell) { cell.textContent = labels[outcome] || ''; }
      });
  };

  function cancelReminder(id) {
    if (!window.confirm('এই কাস্টমারকে বকেয়া নোটিফিকেশন থেকে সরাবেন? পরে নতুন তারিখ বা ওয়াচের অনুমতি না দেওয়া পর্যন্ত তিনি আর এখানে আসবেন না।')) { return; }
    var fd = new FormData();
    fd.append('customer_id', String(id));
    fd.append('csrf_token', csrf);
    fd.append('ajax', '1');
    fetch('/modules/customers/actions/turn_off_due_notify.php', { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.success) {
          var row = document.getElementById('row-' + id);
          if (row && row.parentNode) { row.parentNode.removeChild(row); }
        } else {
          window.alert((data && data.message) ? data.message : 'বাতিল হয়নি।');
        }
      })
      .catch(function () { window.alert('সার্ভার এরর।'); });
  }

  document.addEventListener('pointerdown', function (ev) {
    var t = ev.target;
    if (!infoOverlay || !infoOverlay.classList.contains('is-open') || !t || !t.closest) { return; }
    if (t.closest('[data-due-info-close]') || t === infoOverlay) {
      ev.preventDefault();
      closeInfo();
    }
  }, true);
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) { return; }
    if (t.closest('[data-due-info-close]')) {
      ev.preventDefault();
      closeInfo();
      return;
    }
    var infoBtn = t.closest('[data-due-info]');
    if (infoBtn) {
      ev.preventDefault();
      openInfo(infoBtn.getAttribute('data-due-info'));
      return;
    }
    var delBtn = t.closest('[data-due-del]');
    if (delBtn) {
      ev.preventDefault();
      cancelReminder(delBtn.getAttribute('data-due-del'));
    }
  });
  document.addEventListener('change', function (ev) {
    var t = ev.target;
    if (!t || !t.getAttribute) { return; }
    if (t.getAttribute('data-due-outcome')) {
      window.setOutcome(t.getAttribute('data-due-outcome'), t.getAttribute('data-outcome'));
    }
  });
  document.addEventListener('mouseover', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) { return; }
    var del = t.closest('.ame-due-del');
    var tr = del && del.closest('tr');
    if (tr) { tr.classList.add('is-del-hover'); }
  });
  document.addEventListener('mouseout', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) { return; }
    var del = t.closest('.ame-due-del');
    var tr = del && del.closest('tr');
    if (tr) { tr.classList.remove('is-del-hover'); }
  });
})();

(function () {
  var input = document.getElementById('ameDueSearch');
  var tbody = document.querySelector('.ame-due-table tbody');
  if (!input || !tbody) return;
  var rows = [];
  var timer = null;
  function digits(v) {
    return String(v || '').replace(/[০-৯]/g, function (ch) { return String('০১২৩৪৫৬৭৮৯'.indexOf(ch)); });
  }
  function fold(v) { return digits(v).replace(/\s+/g, '').toLowerCase(); }
  function phoneKey(v) {
    var d = digits(v).replace(/\D/g, '');
    if (d.indexOf('880') === 0 && d.length > 11) { d = d.slice(3); if (d.charAt(0) !== '0') d = '0' + d; }
    if (d.length === 10 && d.charAt(0) === '1') d = '0' + d;
    return d;
  }
  function score(hay, needle) {
    if (!needle || !hay) return 0;
    if (hay === needle) return 100;
    if (hay.indexOf(needle) === 0) return 80;
    return hay.indexOf(needle) >= 0 ? 50 : 0;
  }
  function cache() {
    if (rows.length) return;
    var list = tbody.querySelectorAll('tr[data-s-id]');
    for (var i = 0; i < list.length; i++) {
      var tr = list[i];
      rows.push({
        tr: tr, ord: i,
        id: fold(tr.getAttribute('data-s-id')),
        code: fold(tr.getAttribute('data-s-code')),
        name: fold(tr.getAttribute('data-s-name')),
        addr: fold(tr.getAttribute('data-s-address')),
        group: fold(tr.getAttribute('data-s-group')),
        area: fold(tr.getAttribute('data-s-area')),
        phone: phoneKey(tr.getAttribute('data-s-phone')),
        alt: phoneKey(tr.getAttribute('data-s-alt'))
      });
    }
  }
  function rowScore(row, raw) {
    var q = fold(raw);
    var best = Math.max(score(row.name, q), score(row.code, q), score(row.addr, q), score(row.group, q), score(row.area, q));
    if (/^\d+$/.test(q) && row.id === q) best = Math.max(best, 100);
    if (raw.replace(/[০-৯0-9+\-\s]/g, '') === '') {
      var pq = phoneKey(raw);
      if (pq.length >= 3) {
        best = Math.max(best, score(row.phone, pq), score(row.alt, pq));
      }
    }
    return best;
  }
  function front() {
    cache();
    if (!rows.length) return;
    var raw = input.value.trim();
    var hits = [], rest = [];
    rows.forEach(function (row) {
      row.tr.classList.remove('ame-due-front');
      row.score = raw === '' ? 0 : rowScore(row, raw);
      (row.score > 0 ? hits : rest).push(row);
    });
    hits.sort(function (a, b) { return b.score !== a.score ? b.score - a.score : a.ord - b.ord; });
    rest.sort(function (a, b) { return a.ord - b.ord; });
    var frag = document.createDocumentFragment();
    hits.concat(rest).forEach(function (row) { frag.appendChild(row.tr); });
    tbody.appendChild(frag);
    if (hits[0]) {
      hits[0].tr.classList.add('ame-due-front');
      var top = hits[0].tr.getBoundingClientRect().top;
      if (top < 0 || top > 260) hits[0].tr.scrollIntoView({ block: 'center' });
    }
  }
  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(front, 40);
  });
  if (input.value.trim() !== '') front();
})();
</script>
