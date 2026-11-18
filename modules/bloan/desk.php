<?php
/* 10.98.688 */
declare(strict_types=1);
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/biz_loan_desk.php';

ame_biz_loan_ensure($db);
$userId = (int)($_SESSION['user_id'] ?? 0);
$flash = '';
$flashOk = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $act = (string)($_POST['act'] ?? '');
    try {
        if ($act === 'create') {
            ame_biz_loan_create($db, $_POST, $userId);
            $flash = 'ঋণ যোগ হয়েছে। টাকা নির্বাচিত অ্যাকাউন্টে জমা হয়েছে।';
            $flashOk = true;
        } elseif ($act === 'meta') {
            ame_biz_loan_update_meta($db, (int)($_POST['loan_id'] ?? 0), $_POST);
            $flash = 'ঋণের তথ্য সেভ হয়েছে। আসল ও অ্যাকাউন্টের টাকা বদলায়নি।';
            $flashOk = true;
        } elseif ($act === 'pay') {
            ame_biz_loan_pay($db, (int)($_POST['loan_id'] ?? 0), $_POST, $userId);
            $flash = (string)($_POST['kind'] ?? '') === 'savings_out'
                ? 'সঞ্চয় ফেরত নেওয়া হয়েছে। অ্যাকাউন্টে টাকা জমা হয়েছে।'
                : 'পেমেন্ট হয়েছে। অ্যাকাউন্ট থেকে টাকা কমেছে।';
            $flashOk = true;
        } elseif ($act === 'void') {
            ame_biz_loan_void($db, (int)($_POST['txn_id'] ?? 0), $userId);
            $flash = 'এন্ট্রি বাতিল। অ্যাকাউন্টের টাকা আগের জায়গায় ফিরেছে।';
            $flashOk = true;
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$open = (int)($_GET['loan'] ?? 0);
$print = isset($_GET['print']);
$rows = ame_biz_loan_list($db, $q, $type);
$sum = ame_biz_loan_summary($db);
$accounts = ame_active_accounts($db);
$openLoan = $open > 0 ? ame_biz_loan_row($db, $open) : null;
$txns = $openLoan ? ame_biz_loan_statement($db, $open) : [];
$book = $openLoan ? ame_biz_loan_book($txns, (float)($openLoan['savings_opening'] ?? 0)) : ['lines' => [], 'plus' => 0.0, 'minus' => 0.0, 'net' => 0.0, 'savings' => 0.0];
$bookNewest = array_reverse($book['lines']);
$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$bdDate = static function ($v): string {
    $raw = trim((string)$v);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        if ($m[1] === '0000' || $m[2] === '00' || $m[3] === '00') {
            return '—';
        }
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $raw !== '' ? $raw : '—';
};
$today = date('Y-m-d');

if ($print && $openLoan) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><title>ঋণ স্টেটমেন্ট</title>';
    echo '<style>body{font-family:sans-serif;margin:24px;color:#111}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ccc;padding:6px;text-align:left}@media print{.no-print{display:none}}</style></head><body>';
    echo '<button class="no-print" onclick="window.print()">প্রিন্ট / পিডিএফ</button>';
    echo '<h1>' . $h($openLoan['lender_name']) . '</h1>';
    echo '<p>' . $h(ame_biz_loan_source_label((string)$openLoan['source_type'])) . ' · ' . $h($bdDate((string)$openLoan['loan_date'])) . ' · আসল ' . ame_biz_loan_taka((float)$openLoan['principal']) . ' · বাকি আসল ' . ame_biz_loan_taka(ame_biz_loan_remaining($openLoan)) . '</p>';
    echo '<p>আগের জমা সঞ্চয় ' . ame_biz_loan_taka((float)($openLoan['savings_opening'] ?? 0)) . ' · এখন মোট জমা সঞ্চয় ' . ame_biz_loan_taka((float)$book['savings']) . '</p>';
    echo '<p>মোট প্লাস ' . ame_biz_loan_taka((float)$book['plus']) . ' · মোট মাইনাস ' . ame_biz_loan_taka((float)$book['minus']) . ' · প্লাস − মাইনাস ' . ame_biz_loan_taka((float)$book['net']) . '</p>';
    echo '<style>.bl-plus{color:#04933a;font-weight:800}.bl-minus{color:#9f1239;font-weight:800}</style>';
    echo '<table><tr><th>তারিখ</th><th>বিবরণ</th><th>প্লাস</th><th>মাইনাস</th><th>চলতি</th><th>বাকি আসল</th><th>সঞ্চয় ±</th><th>জমা সঞ্চয়</th></tr>';
    foreach ($bookNewest as $line) {
        $t = $line['txn'];
        $note = trim((string)($t['note'] ?? ''));
        echo '<tr><td>' . $h($bdDate((string)$t['txn_date'])) . '</td><td>' . $h(ame_biz_loan_txn_label($t) . ($note !== '' ? ' · ' . $note : '')) . '</td><td class="bl-plus">' . ame_biz_loan_taka((float)$line['plus']) . '</td><td class="bl-minus">' . ame_biz_loan_taka((float)$line['minus']) . '</td><td>' . ame_biz_loan_taka((float)$line['running']) . '</td><td>' . ame_biz_loan_taka((float)$line['principal_left']) . '</td><td>' . ((float)$line['savings_delta'] != 0.0 ? ((float)$line['savings_delta'] > 0 ? '+' : '−') . ame_biz_loan_taka(abs((float)$line['savings_delta'])) : '—') . '</td><td>' . ame_biz_loan_taka((float)$line['savings_balance']) . '</td></tr>';
    }
    echo '<tr><td colspan="2">মোট</td><td class="bl-plus">' . ame_biz_loan_taka((float)$book['plus']) . '</td><td class="bl-minus">' . ame_biz_loan_taka((float)$book['minus']) . '</td><td>' . ame_biz_loan_taka((float)$book['net']) . '</td><td>' . ame_biz_loan_taka(ame_biz_loan_remaining($openLoan)) . '</td><td></td><td>' . ame_biz_loan_taka((float)$book['savings']) . '</td></tr>';
    echo '</table></body></html>';
    exit;
}

include '../../partials/header.php';
?>
<style>
.bl{max-width:1100px;margin:0 auto;padding:12px 12px 48px;color:#142033}
.bl h1{margin:0 0 6px;font-size:26px}
.bl-lead{margin:0 0 12px;color:#526074}
.bl-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:0 0 12px}
.bl-cards article{background:#fff;border:1px solid #d5deea;border-radius:12px;padding:10px}
.bl-cards span{display:block;font-size:13px;color:#526074;font-weight:700}
.bl-cards b{display:block;margin-top:2px;font-size:18px}
.bl-bar{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.bl-bar input,.bl-bar select,.bl-form input,.bl-form select,.bl-form textarea{min-height:42px;border:1px solid #94a3b8;border-radius:10px;padding:8px 10px;font:inherit;background:#fff;color:#0f172a;width:100%;box-sizing:border-box}
.bl-btn{min-height:42px;border-radius:10px;border:1.5px solid #0f766e;background:#fff;color:#0f766e;font-weight:800;padding:8px 12px;cursor:pointer}
.bl-btn.go{background:#0f766e;color:#fff}
.bl-table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
.bl-table th,.bl-table td{padding:8px;border-bottom:1px solid #e8eef5;text-align:left;vertical-align:top}
.bl-table th{font-size:13px;color:#526074}
.bl-acts{display:flex;flex-wrap:wrap;gap:4px}
.bl-acts button,.bl-acts a{font-size:13px;font-weight:800;border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:4px 8px;text-decoration:none;color:#0f172a}
.bl-ok{background:#ecfdf5;color:#166534;padding:8px 10px;border-radius:10px}
.bl-err{background:#fef2f2;color:#991b1b;padding:8px 10px;border-radius:10px}
.bl-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:flex-start;justify-content:center;padding:max(12px,env(safe-area-inset-top,0px)) 12px max(12px,env(safe-area-inset-bottom,0px));z-index:10000060;overflow:auto;-webkit-overflow-scrolling:touch}
.bl-modal.open{display:flex}
.bl-sheet{background:#fff;width:min(560px,100%);max-width:min(560px,100%);min-width:0;flex:0 1 560px;align-self:center;max-height:none;overflow:visible;border-radius:14px;padding:14px;margin:8px auto 24px;box-sizing:border-box}
.bl-form{display:flex;flex-direction:column;flex-wrap:nowrap;gap:8px}
.bl-form label{font-weight:800;font-size:13px;color:#0e7490;display:flex;flex-direction:column;flex-wrap:nowrap;gap:4px}
.bl-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.bl-row3{grid-template-columns:1fr 1fr 1fr}
.bl-date-bd{display:block;font-size:14px;font-weight:800;color:#0f766e}
.tag-run{color:#9a3412;font-weight:800}.tag-paid{color:#166534;font-weight:800}
.bl-plus{color:#04933a;font-weight:800}.bl-minus{color:#9f1239;font-weight:800}.bl-sav{color:#0e7490;font-weight:800}
#blPayBox,#blBook{scroll-margin-top:calc(var(--ame-header-h, 64px) + 12px)}
@media(max-width:760px){
  html:not(.ame-phone-desktop) .bl-cards,html:not(.ame-phone-desktop) .bl-row,html:not(.ame-phone-desktop) .bl-row3{grid-template-columns:1fr}
}
</style>
<main class="bl">
    <h1>ব্যবসার ঋণ</h1>
    <p class="bl-lead">এক পাতায় ঋণ নিন, কিস্তি দিন, আসল ও লাভ আলাদা রাখুন। টাকা সরাসরি ব্যবসার অ্যাকাউন্টে ওঠানামা করে।</p>
    <?php if ($flash !== ''): ?><p class="<?php echo $flashOk ? 'bl-ok' : 'bl-err'; ?>"><?php echo $h($flash); ?></p><?php endif; ?>
    <section class="bl-cards">
        <article><span>মোট গ্রহণ করা ঋণ</span><b><?php echo ame_biz_loan_taka($sum['taken']); ?></b></article>
        <article><span>বর্তমানে মোট বকেয়া আসল</span><b><?php echo ame_biz_loan_taka($sum['left']); ?></b></article>
        <article><span>মোট পরিশোধিত আসল</span><b><?php echo ame_biz_loan_taka($sum['paid_p']); ?></b></article>
        <article><span>মোট পরিশোধিত লাভ/সুদ</span><b><?php echo ame_biz_loan_taka($sum['paid_i']); ?></b></article>
        <article><span>প্রতিষ্ঠানে মোট জমা সঞ্চয়</span><b class="bl-sav"><?php echo ame_biz_loan_taka($sum['savings']); ?></b></article>
        <article><span>মোট বকেয়া কিস্তি</span><b><?php echo (int)$sum['due_count']; ?> টি · <?php echo ame_biz_loan_taka($sum['due_sum']); ?></b></article>
        <article><span>পরবর্তী কিস্তি</span><b><?php echo $sum['next'] ? $h($bdDate((string)$sum['next']['due_date']) . ' · ' . $sum['next']['lender_name']) : 'নেই'; ?></b></article>
    </section>
    <form class="bl-bar" method="get">
        <input type="search" name="q" value="<?php echo $h($q); ?>" placeholder="ঋণদাতার নাম, মোবাইল, ঠিকানা">
        <select name="type">
            <option value="">সব ধরন</option>
            <?php foreach (['bank' => 'ব্যাংক', 'ngo' => 'এনজিও', 'person' => 'ব্যক্তি', 'other' => 'অন্যান্য'] as $k => $lab): ?>
            <option value="<?php echo $k; ?>"<?php echo $type === $k ? ' selected' : ''; ?>><?php echo $lab; ?></option>
            <?php endforeach; ?>
        </select>
        <button class="bl-btn" type="submit">খুঁজুন</button>
        <button class="bl-btn go" type="button" id="blAdd">ঋণ যোগ</button>
    </form>
    <table class="bl-table ame-stack-table">
        <thead><tr><th>ঋণদাতা</th><th>ধরন</th><th>তারিখ</th><th>মোট ঋণ</th><th>বাকি আসল</th><th>কিস্তি / লাভ</th><th>জমা সঞ্চয়</th><th>মোট পরিশোধ</th><th>পরের তারিখ</th><th>অবস্থা</th><th>কাজ</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td class="bl-empty" colspan="11" data-label="">এখনো কোনো ঋণ নেই।</td></tr><?php endif; ?>
        <?php foreach ($rows as $r):
            $left = ame_biz_loan_remaining($r);
            $paid = round((float)$r['principal_paid'] + (float)$r['interest_paid'], 2);
        ?>
        <tr>
            <td data-label="ঋণদাতা"><b><?php echo $h($r['lender_name']); ?></b><?php echo $r['mobile'] ? '<br>' . $h($r['mobile']) : ''; ?></td>
            <td data-label="ধরন"><?php echo $h(ame_biz_loan_source_label((string)$r['source_type'])); ?></td>
            <td data-label="তারিখ"><?php echo $h($bdDate((string)$r['loan_date'])); ?></td>
            <td data-label="মোট ঋণ"><?php echo ame_biz_loan_taka((float)$r['principal']); ?></td>
            <td data-label="বাকি আসল"><?php echo ame_biz_loan_taka($left); ?></td>
            <td data-label="কিস্তি / লাভ"><?php echo $h(ame_biz_loan_repay_label((string)$r['repay_type'])); ?><br><?php echo ame_biz_loan_taka((float)$r['installment_amount']); ?><?php if ((float)$r['savings_amount'] > 0): ?><br><small class="bl-sav">এর মধ্যে সঞ্চয় <?php echo ame_biz_loan_taka((float)$r['savings_amount']); ?></small><?php endif; ?></td>
            <td data-label="জমা সঞ্চয়" class="bl-sav"><?php echo ame_biz_loan_taka((float)$r['savings_balance']); ?></td>
            <td data-label="মোট পরিশোধ"><?php echo ame_biz_loan_taka($paid); ?></td>
            <td data-label="পরের তারিখ"><?php echo $h($bdDate((string)($r['due_date'] ?? ''))); ?></td>
            <td data-label="অবস্থা" class="<?php echo $r['status'] === 'paid' ? 'tag-paid' : 'tag-run'; ?>"><?php echo $r['status'] === 'paid' ? 'পরিশোধিত' : 'চলমান'; ?></td>
            <td class="bl-acts" data-label="কাজ">
                <?php
                $loanLink = ['loan' => (int)$r['id']];
                if ($q !== '') {
                    $loanLink['q'] = $q;
                }
                if ($type !== '') {
                    $loanLink['type'] = $type;
                }
                $loanHref = 'desk.php?' . http_build_query($loanLink);
                ?>
                <a href="<?php echo $h($loanHref); ?>#blBook">স্টেটমেন্ট</a>
                <?php if ($r['status'] !== 'paid'): ?><a href="<?php echo $h($loanHref); ?>#blPayBox">পেমেন্ট</a><?php endif; ?>
                <button type="button" data-edit="<?php echo (int)$r['id']; ?>" data-json="<?php echo $h(json_encode($r, JSON_UNESCAPED_UNICODE)); ?>">এডিট</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($openLoan):
        $leftNow = ame_biz_loan_remaining($openLoan);
        $openLink = ['loan' => (int)$openLoan['id']];
        if ($q !== '') {
            $openLink['q'] = $q;
        }
        if ($type !== '') {
            $openLink['type'] = $type;
        }
        $openHref = 'desk.php?' . http_build_query($openLink);
    ?>
    <section class="bl-sheet" id="blPayBox" style="margin-top:14px;border:1px solid #d5deea">
        <h2>পেমেন্ট · <?php echo $h($openLoan['lender_name']); ?></h2>
        <p class="bl-lead">এই ঋণদাতার পেমেন্টের ভিতরেই সম্পূর্ণ স্টেটমেন্ট। প্লাস মানে ঋণ এসেছে, মাইনাস মানে যা দেওয়া হয়েছে। চলতি = প্লাস − মাইনাস। শুধু লাভ দিলে বাকি আসল কমে না।</p>
        <p><?php echo $h((string)$openLoan['address']); ?> · <?php echo $h(ame_biz_loan_source_label((string)$openLoan['source_type'])); ?></p>
        <div class="bl-cards">
            <article><span>মোট প্লাস</span><b class="bl-plus"><?php echo ame_biz_loan_taka((float)$book['plus']); ?></b></article>
            <article><span>মোট মাইনাস</span><b class="bl-minus"><?php echo ame_biz_loan_taka((float)$book['minus']); ?></b></article>
            <article><span>প্লাস − মাইনাস</span><b><?php echo ame_biz_loan_taka((float)$book['net']); ?></b></article>
            <article><span>বাকি আসল</span><b><?php echo ame_biz_loan_taka($leftNow); ?></b></article>
            <article><span>আগের জমা সঞ্চয়</span><b class="bl-sav"><?php echo ame_biz_loan_taka((float)($openLoan['savings_opening'] ?? 0)); ?></b></article>
            <article><span>এখন মোট জমা সঞ্চয়</span><b class="bl-sav"><?php echo ame_biz_loan_taka((float)$book['savings']); ?></b></article>
        </div>
        <form class="bl-form bl-inline" method="post" action="<?php echo $h($openHref); ?>#blBook">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="pay">
            <input type="hidden" name="loan_id" value="<?php echo (int)$openLoan['id']; ?>">
            <label>তারিখ<input type="date" name="txn_date" lang="en-GB" value="<?php echo $h($today); ?>" required><span class="bl-date-bd"></span></label>
            <label>ধরন<select name="kind" id="blBoxKind">
                <?php if ((string)$openLoan['status'] !== 'paid'): ?>
                <option value="installment"<?php echo (float)($openLoan['savings_amount'] ?? 0) > 0 ? ' selected' : ''; ?>>কিস্তি (আসল + লাভ + সঞ্চয়)</option>
                <option value="principal">শুধু আসল</option><option value="interest">শুধু লাভ/সুদ</option>
                <?php endif; ?>
                <option value="savings">শুধু সঞ্চয় জমা দিলাম</option><option value="savings_out">সঞ্চয় ফেরত পেলাম</option>
            </select></label>
            <label>মোট টাকা<input type="number" name="amount" id="blBoxAmount" min="0.01" step="0.01" required value="<?php echo (float)$openLoan['installment_amount'] > 0 && (float)($openLoan['savings_amount'] ?? 0) > 0 && (string)$openLoan['status'] !== 'paid' ? $h((string)(float)$openLoan['installment_amount']) : ''; ?>"></label>
            <div class="bl-row bl-row3" id="blBoxSplit" data-sav="<?php echo $h((string)(float)($openLoan['savings_amount'] ?? 0)); ?>">
                <label>আসল অংশ<input type="number" name="principal_part" id="blBoxP" min="0" step="0.01" placeholder="খালি রাখলে মোট − লাভ − সঞ্চয়"></label>
                <label>লাভ অংশ<input type="number" name="interest_part" id="blBoxI" min="0" step="0.01"></label>
                <label>সঞ্চয় অংশ<input type="number" name="savings_part" id="blBoxS" min="0" step="0.01" value="<?php echo (float)($openLoan['savings_amount'] ?? 0) > 0 ? $h((string)(float)$openLoan['savings_amount']) : ''; ?>"></label>
            </div>
            <p class="bl-lead" id="blBoxHint">কিস্তি: যেমন মোট ৫৫০০, সঞ্চয় ৫০০ হলে আসল ৫০০০ কাটবে ও ৫০০ সঞ্চয়ে জমা হবে। সঞ্চয় জমা/ফেরত শুধু সঞ্চয় ব্যালেন্স বদলায়, আসল নয়।</p>
            <label>যে অ্যাকাউন্ট থেকে<select name="account_id" required><option value="">বাছুন</option><?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo $h(ame_account_option_label($a)); ?></option><?php endforeach; ?></select></label>
            <label>নোট<input name="note"></label>
            <div class="bl-inline-actions">
                <button class="bl-btn go" type="submit">পেমেন্ট দিন</button>
            </div>
        </form>
        <?php if ((string)$openLoan['status'] === 'paid'): ?>
        <p class="bl-ok">এই ঋণের আসল শেষ। নিচে সম্পূর্ণ প্লাস-মাইনাস খাতা। সঞ্চয় জমা/ফেরত এখনো দেওয়া যাবে।</p>
        <?php endif; ?>
        <h2 id="blBook">স্টেটমেন্ট · প্লাস মাইনাস</h2>
        <p><a class="bl-btn" href="<?php echo $h($openHref); ?>&amp;print=1" target="_blank">প্রিন্ট / পিডিএফ</a></p>
        <table class="bl-table ame-stack-table">
            <thead><tr><th>তারিখ</th><th>বিবরণ</th><th>প্লাস</th><th>মাইনাস</th><th>চলতি</th><th>বাকি আসল</th><th>সঞ্চয় ±</th><th>জমা সঞ্চয়</th><th>কাজ</th></tr></thead>
            <tbody>
            <?php if (!$bookNewest): ?>
            <tr><td class="bl-empty" colspan="9" data-label="">এই ঋণে এখনো কোনো এন্ট্রি নেই।</td></tr>
            <?php endif; ?>
            <?php foreach ($bookNewest as $line):
                $t = $line['txn'];
            ?>
            <tr>
                <td data-label="তারিখ"><?php echo $h($bdDate((string)$t['txn_date'])); ?></td>
                <td data-label="বিবরণ"><?php echo $h(ame_biz_loan_txn_label($t)); ?><?php echo !empty($t['note']) ? '<br>' . $h($t['note']) : ''; ?></td>
                <td class="bl-plus" data-label="প্লাস"><?php echo ame_biz_loan_taka((float)$line['plus']); ?></td>
                <td class="bl-minus" data-label="মাইনাস"><?php echo ame_biz_loan_taka((float)$line['minus']); ?></td>
                <td data-label="চলতি"><?php echo ame_biz_loan_taka((float)$line['running']); ?></td>
                <td data-label="বাকি আসল"><?php echo ame_biz_loan_taka((float)$line['principal_left']); ?></td>
                <td class="bl-sav" data-label="সঞ্চয় ±"><?php echo (float)$line['savings_delta'] != 0.0 ? ((float)$line['savings_delta'] > 0 ? '+' : '−') . ame_biz_loan_taka(abs((float)$line['savings_delta'])) : '—'; ?></td>
                <td class="bl-sav" data-label="জমা সঞ্চয়"><?php echo ame_biz_loan_taka((float)$line['savings_balance']); ?></td>
                <td class="bl-acts" data-label="কাজ"><?php if ((int)$t['voided'] !== 1): ?>
                    <form method="post" action="<?php echo $h($openHref); ?>#blBook"><?php echo csrf_field(); ?><input type="hidden" name="act" value="void"><input type="hidden" name="txn_id" value="<?php echo (int)$t['id']; ?>"><button class="bl-btn" type="submit">বাতিল</button></form>
                <?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($bookNewest): ?>
            <tr>
                <td data-label="" colspan="2"><b>মোট</b></td>
                <td class="bl-plus" data-label="মোট প্লাস"><?php echo ame_biz_loan_taka((float)$book['plus']); ?></td>
                <td class="bl-minus" data-label="মোট মাইনাস"><?php echo ame_biz_loan_taka((float)$book['minus']); ?></td>
                <td data-label="প্লাস − মাইনাস"><b><?php echo ame_biz_loan_taka((float)$book['net']); ?></b></td>
                <td data-label="বাকি আসল"><?php echo ame_biz_loan_taka($leftNow); ?></td>
                <td data-label=""></td>
                <td class="bl-sav" data-label="জমা সঞ্চয়"><b><?php echo ame_biz_loan_taka((float)$book['savings']); ?></b></td>
                <td data-label=""></td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>
</main>

<div class="bl-modal" id="blCreate">
    <form class="bl-sheet bl-form" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="create">
        <h2>নতুন ঋণ</h2>
        <label>ঋণের উৎস<select name="source_type"><option value="bank">ব্যাংক</option><option value="ngo">এনজিও</option><option value="person">ব্যক্তি</option><option value="other">অন্যান্য</option></select></label>
        <label>ঋণদাতার নাম<input name="lender_name" required></label>
        <label>ঠিকানা<input name="address"></label>
        <div class="bl-row">
            <label>মোবাইল<input name="mobile"></label>
            <label>ঋণের তারিখ<input type="date" name="loan_date" lang="en-GB" value="<?php echo $h($today); ?>" required><span class="bl-date-bd"></span></label>
        </div>
        <div class="bl-row">
            <label>মোট আসল<input type="number" name="principal" min="0.01" step="0.01" required></label>
            <label>যে অ্যাকাউন্টে জমা<select name="account_id" required><option value="">বাছুন</option><?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo $h(ame_account_option_label($a)); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="bl-row">
            <label>লাভ/সুদের হার %<input type="number" name="profit_rate" min="0" step="0.001" value="0"></label>
            <label>লাভের নোট<input name="profit_note" placeholder="যেমন মাসে ২০০০"></label>
        </div>
        <label>পরিশোধের ধরন<select name="repay_type"><option value="monthly">মাসিক কিস্তি</option><option value="profit_only">মাসিক শুধু লাভ</option><option value="lumpsum">এককালীন</option><option value="custom">নিজের মতো</option></select></label>
        <div class="bl-row">
            <label>কিস্তির টাকা<input type="number" name="installment_amount" min="0" step="0.01" value="0"></label>
            <label>কিস্তির তারিখ<input type="date" name="due_date" lang="en-GB"><span class="bl-date-bd"></span></label>
        </div>
        <div class="bl-row">
            <label>প্রতি কিস্তিতে সঞ্চয় (৳)<input type="number" name="savings_amount" min="0" step="0.01" value="0" placeholder="যেমন ৫০০"></label>
            <label>আগের জমা সঞ্চয় (৳)<input type="number" name="savings_opening" min="0" step="0.01" value="0"></label>
        </div>
        <p class="bl-lead">কিস্তির টাকার মধ্যেই সঞ্চয় ধরা হয়। যেমন কিস্তি ৫৫০০, সঞ্চয় ৫০০ হলে ৫০০০ আসল থেকে কাটবে, ৫০০ সঞ্চয়ে জমবে। আগের জমা সঞ্চয়ের জন্য অ্যাকাউন্টের টাকা বদলায় না।</p>
        <div class="bl-actions">
            <button class="bl-btn go" type="submit">ঋণ রাখুন</button>
            <button class="bl-btn" type="button" data-close>বন্ধ</button>
        </div>
    </form>
</div>

<div class="bl-modal" id="blPay">
    <form class="bl-sheet bl-form" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="pay">
        <input type="hidden" name="loan_id" id="blPayId">
        <h2 id="blPayTitle">পেমেন্ট</h2>
        <label>তারিখ<input type="date" name="txn_date" lang="en-GB" value="<?php echo $h($today); ?>" required><span class="bl-date-bd"></span></label>
        <label>ধরন<select name="kind" id="blKind"><option value="principal">শুধু আসল</option><option value="interest">শুধু লাভ/সুদ</option><option value="installment">কিস্তি (আসল + লাভ)</option></select></label>
        <label>মোট টাকা<input type="number" name="amount" min="0.01" step="0.01" required></label>
        <div class="bl-row" id="blSplit">
            <label>আসল অংশ<input type="number" name="principal_part" min="0" step="0.01" placeholder="খালি রাখলে আসল কমবে না"></label>
            <label>লাভ অংশ<input type="number" name="interest_part" min="0" step="0.01"></label>
        </div>
        <p class="bl-lead">কিস্তিতে শুধু মোট দিলে আসল কমে না। আসল ও লাভ আলাদা লিখলে সেই ভাগ ধরবে।</p>
        <label>যে অ্যাকাউন্ট থেকে<select name="account_id" required><option value="">বাছুন</option><?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo $h(ame_account_option_label($a)); ?></option><?php endforeach; ?></select></label>
        <label>নোট<input name="note"></label>
        <div class="bl-actions">
            <button class="bl-btn go" type="submit">পেমেন্ট দিন</button>
            <button class="bl-btn" type="button" data-close>বন্ধ</button>
        </div>
    </form>
</div>

<div class="bl-modal" id="blEdit">
    <form class="bl-sheet bl-form" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="meta">
        <input type="hidden" name="loan_id" id="blEditId">
        <h2>ঋণ এডিট</h2>
        <label>উৎস<select name="source_type" id="edType"><option value="bank">ব্যাংক</option><option value="ngo">এনজিও</option><option value="person">ব্যক্তি</option><option value="other">অন্যান্য</option></select></label>
        <label>নাম<input name="lender_name" id="edName" required></label>
        <label>ঠিকানা<input name="address" id="edAddress"></label>
        <label>মোবাইল<input name="mobile" id="edMobile"></label>
        <label>লাভের হার %<input type="number" name="profit_rate" id="edRate" step="0.001"></label>
        <label>লাভের নোট<input name="profit_note" id="edNote"></label>
        <label>পরিশোধ<select name="repay_type" id="edRepay"><option value="monthly">মাসিক কিস্তি</option><option value="profit_only">মাসিক শুধু লাভ</option><option value="lumpsum">এককালীন</option><option value="custom">নিজের মতো</option></select></label>
        <label>কিস্তির টাকা<input type="number" name="installment_amount" id="edInst" step="0.01"></label>
        <label>পরের তারিখ<input type="date" name="due_date" id="edDue" lang="en-GB"><span class="bl-date-bd"></span></label>
        <div class="bl-row">
            <label>প্রতি কিস্তিতে সঞ্চয় (৳)<input type="number" name="savings_amount" id="edSav" min="0" step="0.01"></label>
            <label>আগের জমা সঞ্চয় (৳)<input type="number" name="savings_opening" id="edSavOpen" min="0" step="0.01"></label>
        </div>
        <p class="bl-lead">আসল ও জমা অ্যাকাউন্ট এখান থেকে বদলায় না। ভুল পেমেন্ট স্টেটমেন্ট থেকে বাতিল করুন।</p>
        <div class="bl-actions">
            <button class="bl-btn go" type="submit">সেভ</button>
            <button class="bl-btn" type="button" data-close>বন্ধ</button>
        </div>
    </form>
</div>
<script>
(function () {
    function bd(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ''));
        if (!m || m[1] === '0000' || m[2] === '00' || m[3] === '00') return '';
        return m[3] + '/' + m[2] + '/' + m[1];
    }
    function paintDate(input) {
        var hint = input.parentElement && input.parentElement.querySelector('.bl-date-bd');
        if (!hint) return;
        var shown = bd(input.value);
        hint.textContent = shown ? ('দিন/মাস/বছর: ' + shown) : 'দিন/মাস/বছর';
    }
    function paintDates(root) {
        (root || document).querySelectorAll('input[type="date"]').forEach(paintDate);
    }
    document.querySelectorAll('input[type="date"]').forEach(function (input) {
        input.addEventListener('input', function () { paintDate(input); });
        input.addEventListener('change', function () { paintDate(input); });
    });
    function open(id) {
        var el = document.getElementById(id);
        if (el && el.parentElement !== document.body) document.body.appendChild(el);
        el.classList.add('open');
        document.documentElement.classList.add('bl-modal-open');
        paintDates(el);
    }
    function shut(el) {
        if (!el) return;
        el.classList.remove('open');
        if (!document.querySelector('.bl-modal.open')) {
            document.documentElement.classList.remove('bl-modal-open');
        }
    }
    document.getElementById('blAdd').addEventListener('click', function () { open('blCreate'); });
    document.querySelectorAll('[data-close]').forEach(function (b) {
        b.addEventListener('click', function () { shut(b.closest('.bl-modal')); });
    });
    document.querySelectorAll('.bl-modal').forEach(function (modal) {
        modal.addEventListener('click', function (ev) {
            if (ev.target === modal) shut(modal);
        });
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        document.querySelectorAll('.bl-modal.open').forEach(shut);
    });
    document.querySelectorAll('[data-pay]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('blPayId').value = b.getAttribute('data-pay');
            document.getElementById('blPayTitle').textContent = 'পেমেন্ট · ' + b.getAttribute('data-name');
            open('blPay');
        });
    });
    document.querySelectorAll('[data-edit]').forEach(function (b) {
        b.addEventListener('click', function () {
            var r = JSON.parse(b.getAttribute('data-json'));
            document.getElementById('blEditId').value = r.id;
            document.getElementById('edType').value = r.source_type;
            document.getElementById('edName').value = r.lender_name || '';
            document.getElementById('edAddress').value = r.address || '';
            document.getElementById('edMobile').value = r.mobile || '';
            document.getElementById('edRate').value = r.profit_rate || 0;
            document.getElementById('edNote').value = r.profit_note || '';
            document.getElementById('edRepay').value = r.repay_type;
            document.getElementById('edInst').value = r.installment_amount || 0;
            document.getElementById('edSav').value = r.savings_amount || 0;
            document.getElementById('edSavOpen').value = r.savings_opening || 0;
            document.getElementById('edDue').value = String(r.due_date || '').slice(0, 10);
            open('blEdit');
        });
    });
    var kind = document.getElementById('blKind');
    var split = document.getElementById('blSplit');
    function paintSplit() { split.style.display = kind.value === 'installment' ? 'grid' : 'none'; }
    kind.addEventListener('change', paintSplit);
    paintSplit();
    var boxKind = document.getElementById('blBoxKind');
    var boxSplit = document.getElementById('blBoxSplit');
    if (boxKind && boxSplit) {
        var bAmt = document.getElementById('blBoxAmount');
        var bP = document.getElementById('blBoxP');
        var bI = document.getElementById('blBoxI');
        var bS = document.getElementById('blBoxS');
        var defSav = parseFloat(boxSplit.getAttribute('data-sav') || '0') || 0;
        function paintBox() { boxSplit.style.display = boxKind.value === 'installment' ? 'grid' : 'none'; }
        function paintPrincipal() {
            if (boxKind.value !== 'installment') return;
            var amt = parseFloat(bAmt.value) || 0;
            var sav = parseFloat(bS.value) || 0;
            var int = parseFloat(bI.value) || 0;
            var p = Math.round((amt - sav - int) * 100) / 100;
            bP.placeholder = p > 0 ? ('স্বয়ংক্রিয় ' + p) : 'খালি রাখলে মোট − লাভ − সঞ্চয়';
        }
        boxKind.addEventListener('change', function () {
            paintBox();
            if (boxKind.value === 'installment' && !bS.value && defSav > 0) bS.value = defSav;
            paintPrincipal();
        });
        [bAmt, bI, bS].forEach(function (el) { el.addEventListener('input', paintPrincipal); });
        paintBox();
        paintPrincipal();
    }
    paintDates(document);
})();
</script>
<?php include '../../partials/footer.php'; ?>
