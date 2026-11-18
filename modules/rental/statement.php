<?php
require_once __DIR__ . '/_boot.php';
$id = (int)($_GET['id'] ?? 0);
$owner = rental_owner($db, $id);
if (!$owner) {
    rental_flash('মালিক পাওয়া যায়নি', 'err');
    redirect('/modules/rental/owners.php');
}
$as_of = trim((string)($_GET['as_of'] ?? rental_today()));
if (!rental_parse_date($as_of)) {
    $as_of = rental_today();
}
$st = rental_owner_statement($db, $id, $as_of);
$contracts = rental_owner_contracts($db, $id);
$plate = trim((string)($owner['vehicle_no'] ?? ''));
$rental_page_title = 'স্টেটমেন্ট — ' . ($plate !== '' ? $plate . ' — ' : '') . $owner['name'];
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'rental-statement-' . $id . '-' . $as_of,
    'title' => $shop . ' · ' . ame_l('ভাড়া মালিক স্টেটমেন্ট', 'Rental owner statement'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ভাড়া মালিক স্টেটমেন্ট', 'Rental owner statement'),
        (string) $owner['name'],
        ame_l('তারিখ পর্যন্ত', 'As of') . ': ' . $as_of,
    ])),
    'shop' => $shop,
];
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
?>
<?php if ($plate === ''): ?>
<div class="rnt-warn no-print">গাড়ির নাম্বার খালি। <a href="/modules/rental/owners.php?id=<?php echo $id; ?>">সম্পাদনা</a> করে নীল বক্সে নাম্বার দিন।</div>
<?php endif; ?>
<form class="rnt-actions no-print" method="get">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <input type="date" name="as_of" value="<?php echo esc_html($as_of); ?>">
    <button class="rnt-btn ghost" type="submit">তারিখ অনুযায়ী দেখুন</button>
</form>
<?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
<div id="ame-slip">
<div class="wdr-shop-head" style="text-align:center;">
  <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
</div>
<div class="rnt-vehicle-box" style="margin-bottom:14px">
    <div class="rnt-vehicle-head">গাড়ি</div>
    <div style="font-size:26px;font-weight:800;letter-spacing:.04em;color:#0D47A1"><?php echo esc_html($plate !== '' ? $plate : 'নাম্বার নেই'); ?></div>
    <?php if (!empty($owner['vehicle_name'])): ?>
        <div><?php echo esc_html($owner['vehicle_name']); ?></div>
    <?php endif; ?>
    <div style="margin-top:8px"><strong><?php echo esc_html($shop); ?></strong></div>
    <div>মালিক: <?php echo esc_html($owner['name']); ?> · <?php echo esc_html($owner['phone'] ?? ''); ?></div>
    <div class="rnt-muted"><?php echo esc_html($owner['address'] ?? ''); ?></div>
    <div>এন্ট্রির তারিখ: <?php echo esc_html(rental_fmt_date(rental_owner_entry_date($owner))); ?>
        · <a class="no-print" href="/modules/rental/owners.php?id=<?php echo $id; ?>"><?php echo current_lang() === 'en' ? 'Edit date' : 'তারিখ বদলান'; ?></a>
    </div>
    <div>স্টেটমেন্ট তারিখ: <?php echo esc_html($as_of); ?></div>
</div>
<div class="rnt-grid">
    <div class="rnt-card"><?php echo format_bdt($st['billed']); ?><span>কাস্টমার থেকে ভাড়া</span></div>
    <div class="rnt-card"><?php echo format_bdt($st['accrued']); ?><span>মালিকের পাওনা</span></div>
    <div class="rnt-card"><?php echo format_bdt($st['paid']); ?><span>ইতিমধ্যে দেওয়া</span></div>
    <div class="rnt-card"><?php echo format_bdt($st['due']); ?><span>বর্তমান বাকি</span></div>
    <div class="rnt-card"><?php echo format_bdt($st['profit']); ?><span>আপনার লাভ</span></div>
</div>

<h2>স্টেটমেন্ট</h2>
<div class="rnt-scroll">
<table class="rnt-table">
    <tr>
        <th>তারিখ</th>
        <th>বিবরণ</th>
        <th class="rnt-num">পাওনা</th>
        <th class="rnt-num">পরিশোধ</th>
        <th class="rnt-num">ব্যালেন্স</th>
    </tr>
    <?php if (!$st['lines']): ?>
    <tr><td colspan="5" class="rnt-muted">এই মালিকের কোনো চুক্তি বা পরিশোধ নেই। কাস্টমার প্রোফাইল থেকে ভাড়া চালু করে মালিক বেছে নিন।</td></tr>
    <?php endif; ?>
    <?php foreach ($st['lines'] as $ln): ?>
    <tr>
        <td><?php echo esc_html($ln['date']); ?></td>
        <td><?php echo esc_html($ln['desc']); ?></td>
        <td class="rnt-num"><?php echo ((float)$ln['debit'] > 0) ? format_bdt($ln['debit']) : '—'; ?></td>
        <td class="rnt-num"><?php echo ((float)$ln['credit'] > 0) ? format_bdt($ln['credit']) : '—'; ?></td>
        <td class="rnt-num"><?php echo format_bdt($ln['balance']); ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <th colspan="2">মোট</th>
        <th class="rnt-num"><?php echo format_bdt($st['accrued']); ?></th>
        <th class="rnt-num"><?php echo format_bdt($st['paid']); ?></th>
        <th class="rnt-num"><?php echo format_bdt($st['due']); ?></th>
    </tr>
</table>
</div>
</div>

<div class="rnt-card no-print" style="margin:16px 0">
    <strong><?php echo esc_html(rental_t('pay')); ?></strong>
    <p class="rnt-muted">মালিককে টাকা দিলে স্টেটমেন্টে পরিশোধ হিসেবে উঠবে।</p>
    <form class="rnt-form" method="post" action="/modules/rental/process_settlement.php" style="border:0;padding:0;margin-top:8px">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="owner_id" value="<?php echo $id; ?>">
        <label>তারিখ<input type="date" name="paid_at" value="<?php echo esc_html(rental_today()); ?>" required></label>
        <label>পরিমাণ<input type="number" step="0.01" min="0.01" name="amount" required></label>
        <label>মাধ্যম
            <select name="method">
                <option value="CASH">নগদ</option>
                <option value="BKASH">বিকাশ</option>
                <option value="NAGAD">নগদ (মোবাইল)</option>
                <option value="BANK">ব্যাংক</option>
            </select>
        </label>
        <label>রেফারেন্স / Txn ID<input name="ref"></label>
        <label class="full">নোট<input name="notes"></label>
        <div class="full"><button class="rnt-btn g" type="submit"><?php echo esc_html(rental_t('pay')); ?></button></div>
    </form>
</div>

<h2>যুক্ত ভাড়া চুক্তি</h2>
<div class="rnt-scroll">
<table class="rnt-table">
    <tr><th>নং</th><th>গাড়ি</th><th>কাস্টমার</th><th>পণ্য</th><th>কাস্টমার রেট</th><th>মালিক রেট</th><th>লাভ</th></tr>
    <?php foreach ($contracts as $c): $snap = rental_contract_snapshot($c, $as_of); ?>
    <tr>
        <td><?php echo esc_html($c['rental_no']); ?></td>
        <td><?php echo esc_html(($c['vehicle_no'] ?: $c['owner_vehicle_no']) ?: '—'); ?></td>
        <td><?php echo esc_html($c['customer_name'] ?? ''); ?></td>
        <td><?php echo esc_html($c['item_name']); ?></td>
        <td><?php echo format_bdt($c['rent_rate']); ?> / <?php echo esc_html($c['rate_type']); ?></td>
        <td><?php echo format_bdt($c['owner_rate']); ?> / <?php echo esc_html(rental_t($c['owner_cycle'] ?: 'monthly')); ?></td>
        <td><?php echo format_bdt($snap['profit']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
