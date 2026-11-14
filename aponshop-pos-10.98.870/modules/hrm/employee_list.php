<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/attendance_functions.php';
require_once '../../includes/staff_duty.php';
require_once '../../includes/employee_hub.php';

ensure_hrm_schema($db);
ame_emp_hub_schema($db);
require_once '../../includes/worker_desk.php';
ame_worker_seed($db);
if (!ame_worker_can_manage()) {
    header('Location: worker_ledger.php');
    exit;
}

$ame_body_class = trim((string)($ame_body_class ?? '') . ' ame-emp-hub');

$search = trim((string)($_GET['q'] ?? ''));
$filters = [
    'q' => $search,
    'pay' => (string)($_GET['pay'] ?? ''),
    'role' => (string)($_GET['role'] ?? ''),
    'att' => (string)($_GET['att'] ?? ''),
    'range' => (string)($_GET['range'] ?? 'today'),
    'from' => (string)($_GET['from'] ?? ''),
    'to' => (string)($_GET['to'] ?? ''),
    'deleted' => isset($_GET['archive']) || (string)($_GET['status'] ?? '') === 'deleted',
    'status' => (string)($_GET['status'] ?? ''),
];
$data = ame_emp_hub_list($db, $filters);
$people = $data['people'];
$sum = $data['summary'];
$canFin = ame_emp_hub_can_finance($db);
$canAdv = ame_emp_hub_can_advance($db);
$canDel = function_exists('ame_emp_hub_can_delete') ? ame_emp_hub_can_delete($db) : $canFin;
$accounts = ($canFin || $canAdv) ? ame_emp_hub_accounts($db) : [];
foreach ($accounts as &$ameAccRow) {
    $ameAccRow['label'] = function_exists('ame_account_option_label')
        ? ame_account_option_label($ameAccRow)
        : trim((string)($ameAccRow['account_name'] ?? ''));
}
unset($ameAccRow);
$today = $sum['today'] ?? ame_emp_hub_today();

$flash = (string)($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);
if ($flash === '' && trim((string)($_GET['err'] ?? '')) !== '') {
    $flash = trim((string)$_GET['err']);
    $flash_ok = false;
}

$eh_money = static function ($n): string {
    if ($n === null) {
        return '—';
    }
    return '৳' . number_format((float)$n, 2);
};

include '../../partials/header.php';

$chip = static function (string $key, string $val, string $label) use ($filters): void {
    $on = (($filters[$key] ?? '') === $val) || ($key === 'range' && ($filters['range'] ?? 'today') === $val && $val !== '');
    if ($key === 'status' && $val === 'deleted') {
        $on = !empty($filters['deleted']);
    }
    $q = $_GET;
    if ($val === '' && $key !== 'range') {
        unset($q[$key]);
        if ($key === 'status') {
            unset($q['archive']);
        }
    } else {
        $q[$key] = $val;
        if ($key === 'status' && $val === 'deleted') {
            $q['archive'] = '1';
        } else {
            unset($q['archive']);
        }
    }
    $href = 'employee_list.php' . ($q ? ('?' . http_build_query($q)) : '');
    echo '<a class="eh-chip' . ($on ? ' on' : '') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
};
?>
<link rel="stylesheet" href="/assets/css/employee-hub.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/worker-desk.css?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>">
<style>
.eh-stats .eh-due.is-paid b { color: #0d9488; font-size: 0.82rem; line-height: 1.25; }
.eh-stats .eh-due.is-paid small { display: block; color: #64748b; font-weight: 600; font-size: 0.78rem; }
.eh-stats .eh-due.is-open b { color: #b45309; }
</style>

<div class="eh">
    <div class="eh-head">
        <div>
            <h1><?php echo !empty($filters['deleted']) ? 'ঐতিহাসিক কর্মী' : 'কর্মী'; ?></h1>
            <p>এক প্রোফাইল। আজকের ডিউটি, লেজার, কাজের হিস্ট্রি ও পেমেন্ট — এই পেজ থেকে।</p>
        </div>
        <?php if (empty($filters['deleted'])): ?>
        <a class="eh-add" href="worker_ledger.php">লেজার ও হিস্ট্রি</a>
        <button type="button" class="eh-add" data-eh="new">+ নতুন কর্মী</button>
        <?php endif; ?>
    </div>

    <?php if ($flash !== ''): ?>
    <div class="eh-flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (empty($filters['deleted'])): ?>
    <section class="wd-home" id="ameWorkerDeskHome">
        <h2>আজকের ডিউটি ও লেজার</h2>
        <p>ডিউটিতে এন্ট্রি না দিলে বিক্রয় বা মেরামত বন্ধ। রাত ১২:০০টায় (বাংলাদেশ সময়) সবাই অফ-ডিউটি। আয় ক্রেডিট, অগ্রিম ও বেতন ডেবিট।</p>
        <nav class="wd-home-nav">
            <a href="worker_ledger.php">লেজার</a>
            <a class="is-hist" href="worker_history.php">কাজের হিস্ট্রি</a>
            <a class="is-pay" href="worker_pay.php">পেমেন্ট</a>
        </nav>
    </section>
    <?php endif; ?>

    <div class="eh-kpis">
        <div class="eh-kpi"><span>মোট কর্মী</span><b><?php echo (int)$sum['total']; ?></b></div>
        <div class="eh-kpi is-on"><span>উপস্থিত</span><b><?php echo (int)$sum['present']; ?></b></div>
        <div class="eh-kpi is-off"><span>অনুপস্থিত</span><b><?php echo (int)$sum['absent']; ?></b></div>
        <div class="eh-kpi"><span>আজকের কাজ</span><b><?php echo $eh_money($canFin ? $sum['work'] : null); ?></b></div>
        <div class="eh-kpi"><span>আজকের বেতন</span><b><?php echo $eh_money($canFin ? $sum['salary'] : null); ?></b></div>
        <div class="eh-kpi"><span>ভাগের টাকা</span><b><?php echo $eh_money($canFin ? $sum['commission'] : null); ?></b></div>
        <div class="eh-kpi"><span>বোনাস</span><b><?php echo $eh_money($canFin ? $sum['bonus'] : null); ?></b></div>
        <div class="eh-kpi"><span>নাস্তা</span><b><?php echo $eh_money($canFin ? $sum['snack'] : null); ?></b></div>
        <div class="eh-kpi"><span>আজকের অগ্রিম</span><b><?php echo $eh_money($canFin ? ($sum['advance_today'] ?? 0) : null); ?></b></div>
        <div class="eh-kpi"><span>কর্মী জমা</span><b><?php echo $eh_money($canFin ? $sum['deposit'] : null); ?></b></div>
        <div class="eh-kpi"><span>শ্রম খরচ</span><b><?php echo $eh_money($canFin ? $sum['labour'] : null); ?></b></div>
        <div class="eh-kpi"><span>দোকানের অংশ</span><b><?php echo $eh_money($canFin ? $sum['shop'] : null); ?></b></div>
    </div>

    <form class="eh-toolbar" method="get">
        <?php if (!empty($filters['deleted'])): ?><input type="hidden" name="archive" value="1"><?php endif; ?>
        <input class="eh-search" type="search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="নাম, মোবাইল, কর্মী আইডি, পদ, ক্রেতা, গাড়ির নম্বর">
        <div class="eh-chips">
            <?php
            $chip('range', 'today', 'আজ');
            $chip('range', 'yesterday', 'গতকাল');
            $chip('range', 'week', 'এই সপ্তাহ');
            $chip('range', 'month', 'এই মাস');
            $chip('range', 'custom', 'নিজের তারিখ');
            $chip('att', 'present', 'উপস্থিত');
            $chip('att', 'absent', 'অনুপস্থিত');
            $chip('pay', 'daily', 'দৈনিক');
            $chip('pay', 'monthly', 'মাসিক');
            $chip('pay', 'percentage', 'শতাংশ');
            $chip('role', 'engineer', 'ইঞ্জিনিয়ার');
            $chip('role', 'repair', 'মেরামত');
            $chip('role', 'service', 'সেবা');
            $chip('status', 'active', 'সক্রিয়');
            $chip('status', 'deleted', 'ঐতিহাসিক');
            ?>
        </div>
        <?php if (($filters['range'] ?? '') === 'custom'): ?>
        <div class="eh-dates">
            <input type="date" name="from" value="<?php echo htmlspecialchars((string)($filters['from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="date" name="to" value="<?php echo htmlspecialchars((string)($filters['to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="range" value="custom">
            <button type="submit">তারিখ অনুযায়ী দেখুন</button>
        </div>
        <?php endif; ?>
    </form>

    <div class="eh-list">
        <?php if (!$people): ?>
        <div class="eh-empty"><?php echo !empty($filters['deleted']) ? 'ঐতিহাসিক কর্মী নেই।' : 'কোনো কর্মী নেই। নতুন কর্মী যোগ করুন।'; ?></div>
        <?php endif; ?>
        <?php foreach ($people as $p): ?>
        <article class="eh-card" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="eh-card-top" data-eh="edit" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <img class="eh-photo" src="<?php echo htmlspecialchars($p['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.src='/assets/img/user-placeholder.png'">
                <div>
                    <h2 class="eh-name"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="eh-meta"><?php echo htmlspecialchars($p['role'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($p['pay_label'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="eh-ids"><?php
                        $bits = [];
                        if (trim((string)$p['code']) !== '') {
                            $bits[] = (string)$p['code'];
                        }
                        if (!empty($p['user_id'])) {
                            $bits[] = '#' . (int)$p['user_id'];
                        }
                        if (trim((string)$p['phone']) !== '') {
                            $bits[] = (string)$p['phone'];
                        }
                        echo htmlspecialchars(implode(' · ', $bits), ENT_QUOTES, 'UTF-8');
                        if ($p['login_name'] !== '') {
                            echo ' · ব্যবহারকারী ' . htmlspecialchars($p['login_name'], ENT_QUOTES, 'UTF-8');
                        }
                    ?></p>
                </div>
            </div>
            <?php if (empty($p['deleted'])): ?>
            <div class="eh-att">
                <button type="button" class="<?php echo $p['present'] ? 'on' : ''; ?>" data-eh="on" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">উপস্থিত / চালু</button>
                <button type="button" class="<?php echo $p['present'] ? '' : 'off'; ?>" data-eh="off" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">অনুপস্থিত / বন্ধ</button>
            </div>
            <?php endif; ?>
            <?php
            $deskClose = 0.0;
            $deskDuty = !empty($p['present']);
            $deskSettled = false;
            if (empty($p['deleted'])) {
                $deskStmt = ame_worker_desk_statement($db, (int)$p['id']);
                $deskClose = (float)($deskStmt['closing'] ?? 0);
                $deskSettled = abs($deskClose) <= 0.009;
            }
            $deskSign = $deskClose < -0.009 ? '−' : ($deskClose > 0.009 ? '+' : '');
            ?>
            <div class="eh-stats">
                <?php
                $todayEarn = (float) ($p['today_commission'] ?? $p['today_salary'] ?? 0);
                $todayBal = isset($p['today_balance']) ? (float)$p['today_balance'] : round($todayEarn + (float) ($p['today_bonus'] ?? 0) + (float) ($p['today_snack'] ?? 0), 2);
                $ehBox = static function (string $box, string $label, $amount) use ($p): void {
                    $id = (int)$p['id'];
                    $name = htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8');
                    $num = round((float)$amount, 2);
                    echo '<div data-eh="box" data-box="' . $box . '" data-id="' . $id . '" data-name="' . $name . '" data-amount="' . htmlspecialchars((string)$num, ENT_QUOTES, 'UTF-8') . '" style="cursor:pointer;">';
                    echo '<span>' . $label . '</span><b>৳' . number_format($num, 2) . '</b></div>';
                };
                $ehBox('work', 'আজকের কাজ', $p['today_work']);
                $ehBox('salary', 'আজকের বেতন', (float) ($p['today_pay'] ?? ((float) ($p['today_salary'] ?? 0) + (float) ($p['today_snack'] ?? 0))));
                ?>
                <div><span>হাজিরা</span><b><?php echo $p['present'] ? 'চালু' : 'বন্ধ'; ?></b></div>
                <?php
                $ehBox('advance', 'অগ্রিম', $p['advance']);
                $ehBox('deposit', 'জমা', $p['deposit']);
                $ehBox('balance', 'ব্যালেন্স', $todayBal);
                $dueAmt = (float)($p['today_due'] ?? 0);
                $paidToday = !empty($p['salary_paid_today']);
                echo '<div class="eh-due' . ($paidToday ? ' is-paid' : ' is-open') . '">';
                echo '<span>আজকের পাওনা</span>';
                if ($paidToday) {
                    echo '<b>বেতন দেওয়া হয়েছে</b><small>৳0.00</small>';
                } else {
                    echo '<b>৳' . number_format($dueAmt, 2) . '</b>';
                }
                echo '</div>';
                if (isset($p['advance_previous']) && $p['advance_previous'] !== null) {
                    echo '<div class="eh-prev-due" title="আজকের আগে পর্যন্ত কর্মীর কাছে দোকানের পাওনা (অগ্রিম)"><span>সাবেক পাওনা</span><b>৳' . number_format((float)$p['advance_previous'], 2) . '</b></div>';
                }
                ?>
            </div>
            <?php if (empty($p['deleted'])): ?>
            <div class="eh-desk">
                <a href="worker_ledger.php?emp=<?php echo (int)$p['id']; ?>">লেজার<small data-eh="box" data-box="balance" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>" data-amount="<?php echo htmlspecialchars((string)round($deskClose, 2), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $deskSettled ? 'সমাধান' : ($deskSign . '৳' . number_format(abs($deskClose), 2)); ?></small></a>
                <a class="is-hist" href="worker_history.php?emp=<?php echo (int)$p['id']; ?>">কাজের হিস্ট্রি<small><?php echo $deskDuty ? 'ডিউটিতে' : 'এন্ট্রি হয়নি'; ?></small></a>
                <a class="is-pay" href="worker_pay.php?emp=<?php echo (int)$p['id']; ?>">পেমেন্ট<small>অ্যাকাউন্ট থেকে</small></a>
            </div>
            <?php endif; ?>
            <div class="eh-actions">
                <?php if (empty($p['deleted'])): ?>
                <button type="button" data-eh="attendance" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">হাজিরা</button>
                <button type="button" data-eh="work" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">কাজ</button>
                <?php endif; ?>
                <button type="button" data-eh="ledger" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">হিসাব</button>
                <button type="button" data-eh="report" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">খতিয়ান</button>
                <?php if (empty($p['deleted'])): ?>
                <a href="/modules/settings/user_profile.php?emp=<?php echo (int)$p['id']; ?>">প্রোফাইল এডিট</a>
                <button type="button" data-eh="edit" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">সংশোধন</button>
                <button type="button" data-eh="advance" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">অগ্রিম</button>
                <button type="button" data-eh="salary" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">বেতন</button>
                <button type="button" data-eh="more" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">আরও</button>
                <?php endif; ?>
                <?php if ($canDel): ?>
                <button type="button" class="eh-forever" data-eh="delete" data-id="<?php echo (int)$p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">চিরতরে মুছুন</button>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>

<div class="eh-draw" id="ehDraw" aria-hidden="true">
    <div class="eh-panel" id="ehPanel"></div>
</div>
<div class="eh-toast" id="ehToast"></div>
<script>
window.EH_BOOT = <?php echo json_encode([
    'today' => $today,
    'nowTime' => substr(ame_emp_hub_now(), 11, 5),
    'month' => substr($today, 0, 7),
    'open' => (string)($_GET['open'] ?? ''),
    'emp' => (int)($_GET['emp'] ?? $_GET['id'] ?? 0),
    'accounts' => $accounts,
    'canFinance' => $canFin,
    'canAdvance' => $canAdv,
    'canDelete' => $canDel,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="/assets/js/ame-resubmit-guard.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="/assets/js/employee-hub.js?v=<?php echo htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include '../../partials/footer.php'; ?>
