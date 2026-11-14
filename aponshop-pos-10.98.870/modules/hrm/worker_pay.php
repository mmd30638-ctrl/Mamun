<?php
/* 10.98.712 */
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/staff_duty.php';
require_once '../../includes/employee_hub.php';
require_once '../../includes/worker_desk.php';

ame_emp_hub_schema($db);

if (!ame_worker_can_manage()) {
    header('Location: worker_ledger.php');
    exit;
}
ame_worker_seed($db);
$empId = (int)($_GET['emp'] ?? 0);
$people = ame_worker_people($db);
if ($empId <= 0 && $people) {
    $empId = (int)$people[0]['id'];
}
$statement = $empId > 0 ? ame_worker_desk_statement($db, $empId) : ['employee' => [], 'closing' => 0.0, 'mode_label' => '', 'mode' => ''];
$closing = round((float)$statement['closing'], 2);
$todayDate = function_exists('ame_staff_duty_today') ? ame_staff_duty_today() : date('Y-m-d');
$payDate = $todayDate;
$askDate = trim((string)($_GET['date'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $askDate) && strtotime($askDate) !== false && $askDate <= $todayDate) {
    $payDate = $askDate;
}
$isPastDate = $payDate !== $todayDate;
$mode = (string)($statement['mode'] ?? '');
$dayWage = 0.0;
$paidToday = 0.0;
$knownExtra = 0.0;
$payNote = '';
if ($empId > 0 && function_exists('ame_emp_hub_pending_pay_preview')) {
    $payPrev = ame_emp_hub_pending_pay_preview($db, $empId, $payDate);
    $mode = (string)($payPrev['pay_mode'] ?? $mode);
    $grossToday = round((float)($payPrev['today_salary'] ?? 0), 2);
    $boxExtra = function_exists('ame_emp_hub_box_sum') ? round((float) ame_emp_hub_box_sum($db, $empId, 'salary', $payDate), 2) : 0.0;
    $dayWage = round(max(0, $grossToday - max(0, $boxExtra)), 2);
    $knownExtra = round((float)($payPrev['today_snack'] ?? 0) + (float)($payPrev['today_bonus'] ?? 0), 2);
    if ($mode === 'daily' && empty($payPrev['present'])) {
        $dayWage = 0.0;
        $payNote = trim((string)($payPrev['note'] ?? ''));
    }
    if ($mode === 'monthly' && function_exists('get_attendance_row')) {
        $att = get_attendance_row($db, $empId, $payDate);
        $attStatus = (string)($att['status'] ?? '');
        $markedAbsent = in_array($attStatus, ['Absent', 'Leave', 'Off'], true);
        $onDuty = function_exists('ame_staff_is_duty_active') && ame_staff_is_duty_active($db, $empId);
        if ($markedAbsent && !$onDuty) {
            $dayWage = 0.0;
            $payNote = 'আজ হাজিরা নেই। মাসিক বেতনের আজকের অংশ আসবে না।';
        }
    }
}
if ($empId > 0) {
    $paidStmt = @$db->prepare(
        "SELECT COALESCE(SUM(amount),0) AS paid
         FROM salaries
         WHERE employee_id = ? AND payment_date = ?
           AND (status IS NULL OR status NOT IN ('reversed','cancelled'))"
    );
    if ($paidStmt) {
        $paidStmt->bind_param('is', $empId, $payDate);
        $paidStmt->execute();
        $paidToday = round((float)($paidStmt->get_result()->fetch_assoc()['paid'] ?? 0), 2);
        $paidStmt->close();
    }
}
$wageHints = [
    'daily' => 'দৈনিক বেতন',
    'monthly' => 'মাসিক বেতনের আজকের অংশ',
    'percentage' => 'আজকের শতাংশ ভাগ',
    'work' => 'আজকের কাজের ভাগ',
    'mixed' => 'আজকের মিশ্র বেতন',
];
$wageHint = $wageHints[$mode] ?? ($statement['mode_label'] !== '' ? (string)$statement['mode_label'] : 'আজকের বেতন');
$accounts = ame_worker_accounts($db);
$draftPack = $empId > 0 && function_exists('ame_employee_draft_rows') ? ame_employee_draft_rows($db, $empId) : ['rows' => [], 'total' => 0.0];
$draftRows = $draftPack['rows'] ?? [];
$draftTotal = round((float)($draftPack['total'] ?? 0), 2);
$advanceDue = $empId > 0 && function_exists('ame_employee_advance_balance') ? round((float) ame_employee_advance_balance($db, $empId), 2) : 0.0;
$oldCap = $advanceDue > 0.009 ? round(max(0, min($advanceDue, $closing + $advanceDue)), 2) : 0.0;
$advanceAll = round($advanceDue + $draftTotal, 2);
$owedToday = round(max(0, $dayWage - $paidToday), 2);
$advanceAdjust = ($draftTotal > 0.009 && $owedToday > 0.009) ? round(min($draftTotal, $owedToday), 2) : 0.0;
$handPay = round(max(0, $owedToday - $advanceAdjust), 2);
$todayBill = round($dayWage, 2);
$err = trim((string)($_GET['err'] ?? ''));
$ok = trim((string)($_GET['ok'] ?? ''));
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8');
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/worker-desk.css?v=<?php echo $ver; ?>">
<main class="wd wd-pay">
    <h1>কর্মীকে পেমেন্ট</h1>
    <p class="wd-lead">এই ঘরে শুধু আজকের বেতন। পার্সেন্টেজ ও নির্দিষ্ট বেতন একই নিয়মে। অতিরিক্ত ঘরে নাস্তা বা বোনাসের মতো টাকা বসান। এডভান্স দিনের যেকোনো সময় — বিকালে সমন্বয় করতেও পারেন, না-ও পারেন। আগের বকেয়া এখানে আসে না।</p>
    <nav class="wd-nav">
        <a href="worker_ledger.php?emp=<?php echo (int)$empId; ?>">লেজার</a>
        <a href="worker_history.php?emp=<?php echo (int)$empId; ?>">কাজের হিস্ট্রি</a>
        <a class="on" href="worker_pay.php?emp=<?php echo (int)$empId; ?>">পেমেন্ট</a>
    </nav>
    <?php if ($err !== ''): ?><p class="wd-empty"><?php echo $h($err); ?></p><?php endif; ?>
    <?php if ($ok !== ''): ?><p class="wd-empty"><?php echo $h($ok); ?></p><?php endif; ?>
    <div class="wd-split">
    <form class="wd-card wd-form" method="post" action="process_worker_pay.php" id="wdPay" autocomplete="off">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="day_wage" value="<?php echo $h(number_format($dayWage, 2, '.', '')); ?>">
        <label>বেতন দেওয়ার তারিখ
            <input type="date" name="pay_date" id="wdDate" value="<?php echo $h($payDate); ?>" max="<?php echo $h($todayDate); ?>" required>
        </label>
        <?php if ($isPastDate): ?><p class="wd-note">আপনি আগের তারিখ (<?php echo $h($payDate); ?>) বেছেছেন। বেতন ও হিসাব ওই তারিখে লেখা হবে।</p><?php endif; ?>
        <label>কর্মী
            <select name="employee_id" id="wdEmp">
                <?php foreach ($people as $person): ?>
                <option value="<?php echo (int)$person['id']; ?>"<?php echo (int)$person['id'] === $empId ? ' selected' : ''; ?>><?php echo $h($person['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="wd-advbox<?php echo $advanceAll > 0.009 ? ' has-adv' : ''; ?>" id="wdAdvBox">
            <span>এই কর্মীর অগ্রিম</span>
            <b id="wdAdvTotal">৳<?php echo $h(number_format($advanceAll, 2)); ?></b>
            <small>সাবেক ৳<?php echo $h(number_format($advanceDue, 2)); ?><?php echo $draftTotal > 0.009 ? ' + খোলা ড্রাফট ৳' . $h(number_format($draftTotal, 2)) : ''; ?></small>
        </div>
        <div class="wd-sum wd-day">
            <div class="is-wage">
                <span>আজকের বেতন<?php echo $wageHint !== '' ? ' · ' . $h($wageHint) : ''; ?></span>
                <b id="wdWage">৳<?php echo $h(number_format($dayWage, 2)); ?></b>
            </div>
            <label class="wd-extra">অতিরিক্ত
                <input type="number" name="extra_amount" id="wdExtra" min="0" step="0.01" inputmode="decimal" value="0.00" placeholder="নাস্তা, বোনাস">
            </label>
            <div class="is-total">
                <span>আজকের মোট</span>
                <b id="wdPrev">৳<?php echo $h(number_format($todayBill, 2)); ?></b>
            </div>
        </div>
        <?php if ($payNote !== ''): ?><p class="wd-note"><?php echo $h($payNote); ?></p><?php endif; ?>
        <?php if ($knownExtra > 0.009): ?><p class="wd-note">আজ লেজারে নাস্তা/বোনাস ৳<?php echo $h(number_format($knownExtra, 2)); ?> আছে। অতিরিক্ত ঘরে শুধু নতুন টাকা বসান।</p><?php endif; ?>
        <?php if ($paidToday > 0.009): ?><p class="wd-note">আজ ইতিমধ্যে ৳<?php echo $h(number_format($paidToday, 2)); ?> পেমেন্ট দেওয়া হয়েছে।</p><?php endif; ?>
        <p class="wd-note">লেজারের হিসাব ৳<?php echo $h(number_format($closing, 2)); ?>। এই পেমেন্ট সেই পুরো অঙ্ক বসায় না।</p>
        <section class="wd-adv">
            <h2>এডভান্স</h2>
            <label>এডভান্স দিন
                <input type="number" name="advance_give" id="wdGive" min="0" step="0.01" inputmode="decimal" value="0.00" placeholder="0">
            </label>
            <p class="wd-note">দিনের যেকোনো সময়। বেতনে সমন্বয় চাপলে বিকালে আজকের বেতন থেকে কাটবে। সমন্বয় না চাপলে এডভান্স আলাদা যাবে, বেতন পুরো থাকবে।</p>
            <?php if ($draftRows): ?>
            <ul class="wd-drafts">
                <?php foreach ($draftRows as $draft): ?>
                <li><?php echo $h((string)$draft['advance_date']); ?> · ৳<?php echo $h(number_format((float)$draft['amount'], 2)); ?><?php echo trim((string)$draft['note']) !== '' ? ' · ' . $h((string)$draft['note']) : ''; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="wd-note">এখন খোলা অগ্রিম নেই।</p>
            <?php endif; ?>
            <div class="wd-choice">
                <button type="button" id="wdAdjOff">সমন্বয় না</button>
                <button type="button" id="wdAdjOn">বেতনে সমন্বয়</button>
            </div>
            <label>সমন্বয়ের টাকা
                <input type="number" name="advance_adjust" id="wdAdjust" min="0" step="0.01" inputmode="decimal" value="<?php echo $h(number_format($advanceAdjust, 2, '.', '')); ?>">
            </label>
            <p class="wd-note" id="wdAdvNote">খোলা অগ্রিম এখানে। ০ রাখলে সমন্বয় হবে না। যে অংশ সমন্বয় হবে না সেটা খোলাই থাকবে।</p>
            <?php if ($advanceDue > 0.009): ?>
            <div class="wd-old">
                <h3>সাবেক অগ্রিম ৳<?php echo $h(number_format($advanceDue, 2)); ?></h3>
                <?php if ($oldCap > 0.009): ?>
                <div class="wd-choice">
                    <button type="button" id="wdOldNo">কাটব না</button>
                    <button type="button" id="wdOldAll">পুরো সাবেক অগ্রিম কাটুন</button>
                </div>
                <label>সাবেক অগ্রিম থেকে কাটুন
                    <input type="number" name="advance_old" id="wdOld" min="0" max="<?php echo $h(number_format($oldCap, 2, '.', '')); ?>" step="0.01" inputmode="decimal" value="0.00">
                </label>
                <p class="wd-note">আজকের বেতন আগে মিটে, বাকিটা লেজারের পাওনা থেকে কাটবে। কাটলে অগ্রিম কমে, ক্যাশ নড়বে না। এখন সর্বোচ্চ ৳<?php echo $h(number_format($oldCap, 2)); ?> কাটা যাবে।</p>
                <?php else: ?>
                <p class="wd-note">লেজারে কর্মীর পাওনা নেই, তাই এখন সাবেক অগ্রিম কাটা যাবে না। আগে বেতন বা কাজের হিসাব জমা হতে হবে।</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
        <label>অ্যাকাউন্ট
            <select class="eh-account ame-account-select" name="account_id" data-ame-account-select="1" data-ame-direction="pay" data-ame-initial="" data-ame-sheet="desk" required>
                <option value="">অ্যাকাউন্ট বাছুন</option>
                <?php foreach ($accounts as $account): ?>
                <option value="<?php echo (int)$account['id']; ?>"><?php echo $h(trim((string)$account['account_name'] . ' · ' . (string)($account['account_type'] ?? '') . ' · ৳' . number_format((float)($account['balance'] ?? 0), 2))); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>হাতে পেমেন্ট
            <input type="number" name="amount" id="wdAmount" min="0" step="0.01" inputmode="decimal" value="<?php echo $h(number_format($handPay > 0.009 ? $handPay : 0, 2, '.', '')); ?>" placeholder="0" autocomplete="off" required>
        </label>
        <label>নোট
            <input type="text" name="notes" maxlength="180" placeholder="আজকের বেতন" autocomplete="off">
        </label>
        <button class="wd-btn primary" type="submit">পেমেন্ট দিন</button>
    </form>
    <aside class="wd-side" id="wdSide">
        <h2 id="wdSideTitle">এই পেমেন্ট</h2>
        <p>আজকের পাওনা <strong id="wdPlus">৳<?php echo number_format($owedToday, 2); ?></strong></p>
        <p>সমন্বয় <strong id="wdCut">৳<?php echo number_format($advanceAdjust, 2); ?></strong></p>
        <p id="wdOldRow"<?php echo $oldCap > 0.009 ? '' : ' hidden'; ?>>সাবেক অগ্রিম কাটা <strong id="wdOldCut">৳0.00</strong></p>
        <p>অগ্রিম বাকি <strong id="wdAdvLeft">৳<?php echo number_format($advanceAll, 2); ?></strong></p>
        <p>হাতে <strong id="wdMinus">৳<?php echo number_format($handPay, 2); ?></strong></p>
        <p>বাকি</p>
        <p class="wd-live" id="wdLatest">৳<?php echo number_format(max(0, $owedToday - $handPay - $advanceAdjust), 2); ?></p>
        <p class="wd-lead" id="wdGiveLine"></p>
        <p class="wd-lead" id="wdLatestNote">সমন্বয় না করলে আজকের টাকা হাতে দিতে হয়। সমন্বয় করলে হাতের টাকা কমে।</p>
    </aside>
    </div>
</main>
<script>
(function () {
    var amount = document.getElementById('wdAmount');
    var latest = document.getElementById('wdLatest');
    var note = document.getElementById('wdLatestNote');
    var plus = document.getElementById('wdPlus');
    var minus = document.getElementById('wdMinus');
    var side = document.getElementById('wdSide');
    var title = document.getElementById('wdSideTitle');
    var wage = <?php echo json_encode($dayWage); ?>;
    var paidToday = <?php echo json_encode($paidToday); ?>;
    var drafts = <?php echo json_encode($draftTotal); ?>;
    var oldCap = <?php echo json_encode($oldCap); ?>;
    var advAll = <?php echo json_encode($advanceAll); ?>;
    var oldIn = document.getElementById('wdOld');
    var oldNo = document.getElementById('wdOldNo');
    var oldAll = document.getElementById('wdOldAll');
    var oldCutBox = document.getElementById('wdOldCut');
    var advLeftBox = document.getElementById('wdAdvLeft');
    var adjust = document.getElementById('wdAdjust');
    var extra = document.getElementById('wdExtra');
    var give = document.getElementById('wdGive');
    var wageBox = document.getElementById('wdWage');
    var totalBox = document.getElementById('wdPrev');
    var cutBox = document.getElementById('wdCut');
    var giveLine = document.getElementById('wdGiveLine');
    var off = document.getElementById('wdAdjOff');
    var on = document.getElementById('wdAdjOn');
    var amountTouched = false;
    var settleOn = <?php echo $advanceAdjust > 0.009 ? 'true' : 'false'; ?>;
    var emp = document.getElementById('wdEmp');
    function num(el) {
        var n = el ? parseFloat(el.value || '0') : 0;
        if (!isFinite(n) || n < 0) n = 0;
        return Math.round(n * 100) / 100;
    }
    function taka(n) {
        var v = Math.abs(n);
        return '৳' + v.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function extraNow() { return num(extra); }
    function giveNow() { return num(give); }
    function cutNow() { return num(adjust); }
    function oldNow() {
        if (!oldIn) return 0;
        return Math.min(num(oldIn), oldCap);
    }
    function owed() {
        return Math.max(0, Math.round((wage + extraNow() - paidToday) * 100) / 100);
    }
    function bill() {
        return Math.max(0, Math.round((wage + extraNow()) * 100) / 100);
    }
    function applySettle() {
        if (!adjust) return;
        var cap = Math.min(Math.max(0, drafts + giveNow()), owed());
        adjust.value = (Math.round(cap * 100) / 100).toFixed(2);
    }
    function markChoice() {
        var cut = cutNow();
        if (off) off.classList.toggle('on', !settleOn || cut <= 0.009);
        if (on) on.classList.toggle('on', settleOn && cut > 0.009);
    }
    function paint() {
        if (wageBox) wageBox.textContent = taka(wage);
        if (totalBox) totalBox.textContent = taka(bill());
        var remain = owed();
        if (!amountTouched && amount) {
            amount.value = Math.max(0, Math.round((remain - cutNow() - oldNow()) * 100) / 100).toFixed(2);
        }
        var paid = amount ? num(amount) : 0;
        var cut = cutNow();
        var old = oldNow();
        var left = Math.round((remain - paid - cut) * 100) / 100;
        if (left > 0.009) left = Math.max(0, Math.round((left - old) * 100) / 100);
        var settled = Math.abs(left) <= 0.009 && (remain > 0.009 || paid > 0.009 || cut > 0.009 || old > 0.009);
        var idle = remain <= 0.009 && paid <= 0.009 && cut <= 0.009 && old <= 0.009;
        [[oldNo, old <= 0.009], [oldAll, old > 0.009]].forEach(function (pair) {
            var b = pair[0];
            if (!b) return;
            b.classList.toggle('on', pair[1]);
            b.style.setProperty('background', pair[1] ? '#9a3412' : '#ffffff', 'important');
            b.style.setProperty('color', pair[1] ? '#ffffff' : '#9a3412', 'important');
            b.style.setProperty('-webkit-text-fill-color', pair[1] ? '#ffffff' : '#9a3412', 'important');
        });
        if (oldCutBox) oldCutBox.textContent = old > 0.009 ? ('−' + taka(old)) : taka(0);
        if (advLeftBox) {
            var left_adv = Math.max(0, Math.round((advAll + giveNow() - old - Math.min(cut, drafts + giveNow())) * 100) / 100);
            advLeftBox.textContent = taka(left_adv);
            advLeftBox.classList.toggle('is-zero', left_adv <= 0.009 && advAll > 0.009);
        }
        if (plus) plus.textContent = taka(remain);
        if (cutBox) cutBox.textContent = cut > 0.009 ? ('−' + taka(cut)) : taka(0);
        if (minus) minus.textContent = paid > 0.009 ? ('−' + taka(paid)) : taka(0);
        latest.textContent = idle ? taka(0) : (settled ? taka(0) : (left < -0.009 ? '−' + taka(left) : taka(left)));
        if (side) side.classList.toggle('is-zero', settled && !idle);
        if (title) title.textContent = settled && !idle ? 'মিটে যাবে' : 'এই পেমেন্ট';
        var g = giveNow();
        if (giveLine) {
            if (g > 0.009 && !settleOn) {
                giveLine.textContent = 'এডভান্স আলাদা ' + taka(g) + '। বেতন থেকে কাটবে না।';
            } else if (g > 0.009) {
                giveLine.textContent = 'এডভান্স সমন্বয়ের মধ্যে আছে।';
            } else {
                giveLine.textContent = '';
            }
        }
        if (idle && wage <= 0.009) {
            note.textContent = 'আজকের বেতন ০। অতিরিক্ত বা এডভান্স বসালে সেটাই যাবে। আগের বকেয়া এ ঘরে আসে না।';
        } else if (idle) {
            note.textContent = 'আজকের বেতন আগেই দেওয়া হয়েছে। আগের বকেয়া লেজারে থাকে।';
        } else if (settled && old > 0.009 && paid <= 0.009 && cut <= 0.009) {
            note.textContent = 'সাবেক অগ্রিম কেটে হিসাব মিটে যাবে। হাতে টাকা দিতে হবে না।';
        } else if (settled && cut > 0.009 && paid <= 0.009) {
            note.textContent = 'অগ্রিম সমন্বয়ে আজকের বেতন মিটে যাবে। হাতে টাকা দিতে সমন্বয় কমান।';
        } else if (settled) {
            note.textContent = 'আজকের বেতন মিটে যাবে। আগের বকেয়া লেজারে থাকে।';
        } else if (cut <= 0.009) {
            note.textContent = 'সমন্বয় বন্ধ। আজকের বাকি টাকা হাতে দিতে হবে।';
        } else {
            note.textContent = 'সমন্বয়ের পর বাকি টাকা হাতে দিন।';
        }
        markChoice();
    }
    if (off) off.addEventListener('click', function () {
        settleOn = false;
        if (adjust) adjust.value = '0.00';
        amountTouched = false;
        paint();
    });
    if (on) on.addEventListener('click', function () {
        settleOn = true;
        amountTouched = false;
        applySettle();
        paint();
    });
    if (amount) {
        amount.addEventListener('input', function () {
            amountTouched = true;
            paint();
        });
        amount.addEventListener('focus', function () { var el = this; setTimeout(function () { el.select(); }, 0); });
    }
    if (oldIn) {
        oldIn.addEventListener('input', function () {
            if (num(oldIn) > oldCap) oldIn.value = oldCap.toFixed(2);
            amountTouched = false;
            paint();
        });
    }
    if (oldNo) oldNo.addEventListener('click', function () {
        if (oldIn) oldIn.value = '0.00';
        amountTouched = false;
        paint();
    });
    if (oldAll) oldAll.addEventListener('click', function () {
        if (oldIn) oldIn.value = oldCap.toFixed(2);
        amountTouched = false;
        paint();
    });
    if (adjust) {
        adjust.addEventListener('input', function () {
            settleOn = cutNow() > 0.009;
            amountTouched = false;
            paint();
        });
    }
    function retouch(el) {
        if (!el) return;
        el.addEventListener('input', function () {
            amountTouched = false;
            if (settleOn) applySettle();
            paint();
        });
    }
    retouch(extra);
    retouch(give);
    if (emp) emp.addEventListener('change', function () {
        window.location = 'worker_pay.php?emp=' + encodeURIComponent(emp.value) + '&date=' + encodeURIComponent(dateEl ? dateEl.value : '');
    });
    var dateEl = document.getElementById('wdDate');
    if (dateEl) dateEl.addEventListener('change', function () {
        if (!dateEl.value) return;
        window.location = 'worker_pay.php?emp=' + encodeURIComponent(emp ? emp.value : '<?php echo (int)$empId; ?>') + '&date=' + encodeURIComponent(dateEl.value);
    });
    paint();
})();
</script>
<?php include '../../partials/footer.php'; ?>
