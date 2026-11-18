<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
header('Cache-Control: no-store');
$ame_body_class = 'ame-hire-agreement-page';

$agrId = (int)($_GET['id'] ?? 0);
$contractId = (int)($_GET['contract_id'] ?? 0);
$row = null;
$contract = null;
if ($agrId > 0) {
    $row = hire_agr_get($db, $agrId);
    if ($row) {
        $contract = hire_contract($db, (int)$row['contract_id']);
    }
} elseif ($contractId > 0) {
    $contract = hire_contract($db, $contractId);
    if ($contract) {
        $row = hire_agr_latest_for_contract($db, $contractId);
        if (!$row) {
            $row = hire_agr_blank_from_contract($db, $contract);
        }
    }
}

$here = 'agreements.php';
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.373', ENT_QUOTES, 'UTF-8');

if (!$row) {
    echo '<div class="hire-wrap agr-page"><p class="agr-empty">চুক্তিনামা পাওয়া যায়নি। ভাড়ার চুক্তি খুলে «চুক্তিনামা ও স্বাক্ষর» দিন।</p>';
    include __DIR__ . '/_nav.php';
    echo '<p class="no-print"><a class="agr-btn ame-calc-btn" href="agreements.php">সব চুক্তিনামা</a></p></div>';
    include __DIR__ . '/../../../partials/footer.php';
    return;
}

$locked = (int)($row['id'] ?? 0) > 0 && hire_agr_locked($row);
$canWrite = (bool)$contract && hire_can($db, 'edit') && !$locked;
$versions = trim((string)($row['agreement_no'] ?? '')) !== '' ? hire_agr_versions($db, (string)$row['agreement_no']) : [];
$maxVer = 0;
foreach ($versions as $verRow) {
    $maxVer = max($maxVer, (int)$verRow['version_no']);
}
$isCurrent = $maxVer > 0 && (int)$row['version_no'] === $maxVer;
$both = !empty($row['customer_signed_at']) && !empty($row['guarantor_signed_at']);
$terms = trim((string)($row['terms_snapshot'] ?? ''));
if ($terms === '') {
    $terms = hire_agr_terms();
}
$cPng = (int)$row['id'] > 0 ? hire_agr_png_uri($row, 'customer') : '';
$gPng = (int)$row['id'] > 0 ? hire_agr_png_uri($row, 'guarantor') : '';
$signs = (int)$row['id'] > 0 ? hire_agr_sign_log($db, (int)$row['id']) : [];
$shop = function_exists('shop_name') ? shop_name() : 'প্রতিষ্ঠান';
$dis = $canWrite ? '' : ' disabled';

$dateVal = static function ($v): string {
    $v = substr(trim((string)$v), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
};
$showDate = static function ($v) use ($dateVal): string {
    $v = $dateVal($v);
    if ($v === '') {
        return '—';
    }
    $t = strtotime($v);
    return $t ? date('d-m-Y', $t) : $v;
};
$showWhen = static function ($v): string {
    $v = trim((string)$v);
    if ($v === '') {
        return '—';
    }
    $t = strtotime($v);
    return $t ? date('d-m-Y h:i A', $t) : hire_h($v);
};
$taka = static function ($n): string {
    return '৳' . number_format((float)$n, 2);
};
$qtyText = rtrim(rtrim(number_format((float)($row['qty'] ?? 1), 3, '.', ''), '0'), '.');
if ($qtyText === '') {
    $qtyText = '0';
}
$field = static function (string $name, string $label, $value, string $type = 'text', bool $span = false, string $extra = '') use ($dis): void {
    $cls = $span ? 'agr-span' : '';
    echo '<label class="' . $cls . '"><span>' . $label . '</span>';
    if ($type === 'textarea') {
        echo '<textarea name="' . $name . '"' . $dis . '>' . hire_h($value) . '</textarea>';
    } else {
        echo '<input type="' . $type . '" name="' . $name . '" value="' . hire_h($value) . '"' . $dis . $extra . '>';
    }
    echo '</label>';
};
$line = static function (string $label, string $value): void {
    echo '<p><b>' . $label . '</b><span>' . ($value !== '' ? hire_h($value) : '—') . '</span></p>';
};

$boot = [
    'id' => (int)($row['id'] ?? 0),
    'contractId' => (int)($row['contract_id'] ?? 0),
    'csrf' => csrf_token(),
    'api' => 'agreement_api.php',
    'locked' => $locked,
    'canWrite' => $canWrite,
    'both' => $both,
    'makepdf' => (string)($_GET['makepdf'] ?? '') === '1',
    'print' => (string)($_GET['print'] ?? '') === '1',
    'hasPdf' => $locked && trim((string)($row['pdf_path'] ?? '')) !== '',
    'agreementNo' => (string)($row['agreement_no'] ?? ''),
    'verify' => (string)($row['verify_ref'] ?? ''),
];
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<div class="hire-wrap agr-page">
  <?php echo hire_flash(); ?>
  <header class="agr-hero">
    <p class="agr-kicker"><?php echo hire_h($shop); ?></p>
    <h1>ভাড়া চুক্তিনামা</h1>
    <p>কাস্টমার ও জামিনদারের তথ্য, শর্ত এবং ডিজিটাল স্বাক্ষর একই চুক্তিতে থাকবে। চূড়ান্ত হওয়ার পর সরাসরি এডিট হবে না — বদলাতে নতুন ভার্সন তৈরি হবে।</p>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="agr-tools no-print">
    <a class="agr-btn ame-calc-btn" href="contract.php?id=<?php echo (int)$row['contract_id']; ?>">ভাড়ার চুক্তি</a>
    <a class="agr-btn ame-calc-btn" href="agreements.php?customer_id=<?php echo (int)$row['customer_id']; ?>">এই কাস্টমারের চুক্তিনামা</a>
    <a class="agr-btn ame-calc-btn" href="/modules/customers/profile.php?id=<?php echo (int)$row['customer_id']; ?>">কাস্টমার প্রোফাইল</a>
    <?php if ((int)$row['id'] > 0): ?>
      <button type="button" class="agr-btn ame-calc-btn" id="agrPrint">প্রিন্ট</button>
    <?php endif; ?>
    <?php if ($boot['hasPdf']): ?>
      <a class="agr-btn ame-calc-btn" target="_blank" href="agreement_file.php?id=<?php echo (int)$row['id']; ?>&amp;kind=pdf">PDF দেখুন</a>
      <a class="agr-btn ame-calc-btn" href="agreement_file.php?id=<?php echo (int)$row['id']; ?>&amp;kind=pdf&amp;dl=1">PDF ডাউনলোড</a>
      <button type="button" class="agr-btn ame-calc-btn" id="agrShare">শেয়ার</button>
    <?php endif; ?>
    <?php if ($canWrite && $both): ?>
      <button type="button" class="agr-btn agr-btn-go ame-calc-btn" id="agrMakePdf">PDF তৈরি করুন</button>
    <?php endif; ?>
    <?php if ($locked && $isCurrent && hire_can($db, 'edit')): ?>
      <button type="button" class="agr-btn agr-btn-go ame-calc-btn" id="agrVersion">নতুন ভার্সন</button>
    <?php endif; ?>
  </div>
  <p id="agrMsg" class="agr-msg no-print" hidden></p>

  <section class="agr-card no-print">
    <h2>১. কাস্টমার, পণ্য ও জামিনদার</h2>
    <?php if ($locked): ?>
      <p class="agr-note">এই ভার্সন চূড়ান্ত। সাধারণ এডিট বন্ধ। বদলাতে «নতুন ভার্সন» তৈরি করুন। পুরোনো স্বাক্ষর ও PDF থাকবে।</p>
    <?php endif; ?>
    <form id="agrForm" method="post" action="agreement_api.php" autocomplete="off">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="agreement_id" value="<?php echo (int)$row['id']; ?>">
      <input type="hidden" name="contract_id" value="<?php echo (int)$row['contract_id']; ?>">

      <h3>কাস্টমার</h3>
      <div class="agr-grid">
        <label><span>Customer ID</span><input value="<?php echo hire_h((string)$row['customer_id'] . ' · ' . (string)$row['customer_code']); ?>" readonly></label>
        <?php
          $field('customer_name', 'পূর্ণ নাম', $row['customer_name'] ?? '');
          $field('father_or_spouse', 'পিতা/স্বামীর নাম', $row['father_or_spouse'] ?? '');
          $field('nid', 'NID/পরিচয়পত্র নম্বর', $row['nid'] ?? '');
          $field('phone', 'মোবাইল নম্বর', $row['phone'] ?? '', 'tel');
          $field('phone_alt', 'বিকল্প মোবাইল নম্বর', $row['phone_alt'] ?? '', 'tel');
          $field('address', 'পূর্ণ ঠিকানা', $row['address'] ?? '', 'textarea', true);
          $field('present_address', 'বর্তমান ঠিকানা', $row['present_address'] ?? '', 'textarea', true);
          $field('occupation', 'পেশা/ব্যবসার তথ্য', $row['occupation'] ?? '');
          $field('customer_extra', 'অন্যান্য কাস্টমার তথ্য', $row['customer_extra'] ?? '', 'textarea', true);
        ?>
      </div>

      <h3>ভাড়ার পণ্য</h3>
      <div class="agr-grid">
        <label><span>Agreement ID / চুক্তি নম্বর</span><input value="<?php echo hire_h((string)($row['agreement_no'] ?: 'সংরক্ষণের পর তৈরি হবে')); ?>" readonly></label>
        <label><span>ভার্সন</span><input value="<?php echo (int)$row['version_no']; ?> — <?php echo $isCurrent || $maxVer === 0 ? 'বর্তমান' : 'আগের ভার্সন'; ?>" readonly></label>
        <?php $field('agreement_date', 'চুক্তির তারিখ', $dateVal($row['agreement_date'] ?? ''), 'date'); ?>
        <?php $field('item_name', 'পণ্যের নাম', $row['item_name'] ?? ''); ?>
        <?php $field('product_sku', 'পণ্যের কোড/SKU', $row['product_sku'] ?? ''); ?>
        <?php $field('serial_no', 'Serial / Asset নম্বর', $row['serial_no'] ?? ''); ?>
        <?php $field('qty', 'Quantity', $qtyText, 'number', false, ' step="0.001" min="0.001"'); ?>
        <?php $field('item_condition', 'পণ্যের বর্তমান অবস্থা', $row['item_condition'] ?? ''); ?>
        <?php $field('security_deposit', 'Advance/Security Deposit', number_format((float)($row['security_deposit'] ?? 0), 2, '.', ''), 'number', false, ' step="0.01" min="0"'); ?>
        <?php $field('rent_rate', 'ভাড়ার পরিমাণ', number_format((float)($row['rent_rate'] ?? 0), 2, '.', ''), 'number', false, ' step="0.01" min="0"'); ?>
        <label>
          <span>দৈনিক/সাপ্তাহিক/মাসিক</span>
          <select name="rate_type"<?php echo $dis; ?>>
            <?php foreach (['DAILY' => 'দৈনিক', 'WEEKLY' => 'সাপ্তাহিক', 'MONTHLY' => 'মাসিক'] as $key => $label): ?>
              <option value="<?php echo $key; ?>"<?php echo strtoupper((string)($row['rate_type'] ?? 'DAILY')) === $key ? ' selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php $field('rent_pay_on', 'ভাড়া প্রদানের তারিখ', $row['rent_pay_on'] ?? ''); ?>
        <?php $field('start_date', 'চুক্তির শুরু তারিখ', $dateVal($row['start_date'] ?? ''), 'date'); ?>
        <?php $field('end_date', 'চুক্তির শেষ তারিখ', $dateVal($row['end_date'] ?? ''), 'date'); ?>
        <?php $field('other_charges', 'অন্যান্য প্রযোজ্য চার্জ', $row['other_charges'] ?? '', 'textarea', true); ?>
      </div>
      <p class="agr-hint">চুক্তিনামার ভাড়ার ধরন এই দলিলে থাকে। দোকানের দৈনিক হিসাব আগের ভাড়া চুক্তি থেকেই চলবে।</p>

      <h3>জামিনদার</h3>
      <div class="agr-grid">
        <label><span>Guarantor ID</span><input value="<?php echo hire_h((string)($row['guarantor_code'] ?: 'সংরক্ষণের পর তৈরি হবে')); ?>" readonly></label>
        <?php
          $field('guarantor_name', 'পূর্ণ নাম', $row['guarantor_name'] ?? '');
          $field('guarantor_father', 'পিতা/স্বামীর নাম', $row['guarantor_father'] ?? '');
          $field('guarantor_nid', 'NID/পরিচয়পত্র নম্বর', $row['guarantor_nid'] ?? '');
          $field('guarantor_phone', 'মোবাইল নম্বর', $row['guarantor_phone'] ?? '', 'tel');
          $field('guarantor_phone_alt', 'বিকল্প মোবাইল নম্বর', $row['guarantor_phone_alt'] ?? '', 'tel');
          $field('guarantor_address', 'পূর্ণ ঠিকানা', $row['guarantor_address'] ?? '', 'textarea', true);
          $field('guarantor_occupation', 'পেশা/ব্যবসার তথ্য', $row['guarantor_occupation'] ?? '');
          $field('guarantor_relation', 'কাস্টমারের সঙ্গে সম্পর্ক', $row['guarantor_relation'] ?? '');
          $field('guarantor_extra', 'অন্যান্য তথ্য', $row['guarantor_extra'] ?? '', 'textarea', true);
        ?>
      </div>

      <h3>চুক্তির অন্যান্য বিশেষ শর্ত</h3>
      <div class="agr-grid">
        <?php $field('special_terms', 'ব্যবসার প্রয়োজন অনুযায়ী অতিরিক্ত শর্ত', $row['special_terms'] ?? '', 'textarea', true); ?>
      </div>

      <?php if ($canWrite): ?>
        <div class="agr-actions">
          <button class="agr-btn agr-btn-save ame-calc-btn" type="submit">খসড়া সংরক্ষণ</button>
          <button class="agr-btn agr-btn-cus ame-calc-btn" type="button" id="agrSignCustomer">Customer Signature নিন</button>
          <button class="agr-btn agr-btn-gua ame-calc-btn" type="button" id="agrSignGuarantor">Guarantor Signature নিন</button>
        </div>
      <?php endif; ?>
    </form>
  </section>

  <article class="agr-sheet" id="agrSheet">
    <header class="agr-sheet-head">
      <p><?php echo hire_h($shop); ?></p>
      <h2>ভাড়া/রেন্টাল চুক্তিনামা</h2>
      <p><?php echo $isCurrent || $maxVer === 0 ? 'বর্তমান ভার্সন' : 'আগের ভার্সন'; ?> · <?php echo $locked ? 'চূড়ান্ত' : 'খসড়া'; ?></p>
    </header>
    <div class="agr-facts">
      <?php
        $line('Agreement Number', (string)($row['agreement_no'] ?: '—'));
        $line('Agreement ID', (int)$row['id'] > 0 ? (string)(int)$row['id'] : '—');
        $line('Version', (string)(int)$row['version_no']);
        $line('Agreement Date', $showDate($row['agreement_date'] ?? ''));
        $line('Verification / Reference ID', (string)($row['verify_ref'] ?: 'স্বাক্ষর ও PDF-এর পর তৈরি হবে'));
      ?>
    </div>

    <h3>Customer Information</h3>
    <div class="agr-facts">
      <?php
        $line('Customer ID', trim((string)$row['customer_id'] . ' · ' . (string)$row['customer_code']));
        $line('পূর্ণ নাম', (string)($row['customer_name'] ?? ''));
        $line('পিতা/স্বামীর নাম', (string)($row['father_or_spouse'] ?? ''));
        $line('NID/পরিচয়পত্র', (string)($row['nid'] ?? ''));
        $line('মোবাইল', (string)($row['phone'] ?? ''));
        $line('বিকল্প মোবাইল', (string)($row['phone_alt'] ?? ''));
        $line('পূর্ণ ঠিকানা', (string)($row['address'] ?? ''));
        $line('বর্তমান ঠিকানা', (string)($row['present_address'] ?? ''));
        $line('পেশা/ব্যবসা', (string)($row['occupation'] ?? ''));
        $line('অন্যান্য', (string)($row['customer_extra'] ?? ''));
      ?>
    </div>

    <h3>Rental Product</h3>
    <div class="agr-facts">
      <?php
        $line('পণ্যের নাম', (string)($row['item_name'] ?? ''));
        $line('কোড/SKU', (string)($row['product_sku'] ?? ''));
        $line('Serial / Asset', (string)($row['serial_no'] ?? ''));
        $line('Quantity', $qtyText);
        $line('পণ্যের অবস্থা', (string)($row['item_condition'] ?? ''));
        $line('Advance/Security Deposit', $taka($row['security_deposit'] ?? 0));
        $line('ভাড়া', $taka($row['rent_rate'] ?? 0) . ' (' . hire_agr_rate_label((string)($row['rate_type'] ?? 'DAILY')) . ')');
        $line('ভাড়া প্রদানের তারিখ', (string)($row['rent_pay_on'] ?? ''));
        $line('চুক্তির শুরু', $showDate($row['start_date'] ?? ''));
        $line('চুক্তির শেষ', $showDate($row['end_date'] ?? ''));
        $line('অন্যান্য চার্জ', (string)($row['other_charges'] ?? ''));
      ?>
    </div>

    <h3>Guarantor Information</h3>
    <div class="agr-facts">
      <?php
        $line('Guarantor ID', (string)($row['guarantor_code'] ?? ''));
        $line('পূর্ণ নাম', (string)($row['guarantor_name'] ?? ''));
        $line('পিতা/স্বামীর নাম', (string)($row['guarantor_father'] ?? ''));
        $line('NID/পরিচয়পত্র', (string)($row['guarantor_nid'] ?? ''));
        $line('মোবাইল', (string)($row['guarantor_phone'] ?? ''));
        $line('বিকল্প মোবাইল', (string)($row['guarantor_phone_alt'] ?? ''));
        $line('পূর্ণ ঠিকানা', (string)($row['guarantor_address'] ?? ''));
        $line('পেশা/ব্যবসা', (string)($row['guarantor_occupation'] ?? ''));
        $line('সম্পর্ক', (string)($row['guarantor_relation'] ?? ''));
        $line('অন্যান্য', (string)($row['guarantor_extra'] ?? ''));
      ?>
    </div>

    <h3>শর্তাবলি</h3>
    <div class="agr-terms"><?php echo nl2br(hire_h($terms)); ?></div>
    <h3>চুক্তির অন্যান্য বিশেষ শর্ত</h3>
    <div class="agr-terms"><?php echo trim((string)($row['special_terms'] ?? '')) !== '' ? nl2br(hire_h($row['special_terms'])) : 'কোনো অতিরিক্ত শর্ত যোগ করা হয়নি।'; ?></div>

    <div class="agr-sign-row">
      <div class="agr-sign-box">
        <b>Customer Digital Signature</b>
        <?php if ($cPng !== ''): ?>
          <img src="<?php echo htmlspecialchars($cPng, ENT_QUOTES, 'UTF-8'); ?>" alt="কাস্টমারের স্বাক্ষর">
        <?php else: ?>
          <em>স্বাক্ষর বাকি</em>
        <?php endif; ?>
        <small>Signature Date &amp; Time: <?php echo $showWhen($row['customer_signed_at'] ?? ''); ?></small>
      </div>
      <div class="agr-sign-box">
        <b>Guarantor Digital Signature</b>
        <?php if ($gPng !== ''): ?>
          <img src="<?php echo htmlspecialchars($gPng, ENT_QUOTES, 'UTF-8'); ?>" alt="জামিনদারের স্বাক্ষর">
        <?php else: ?>
          <em>স্বাক্ষর বাকি</em>
        <?php endif; ?>
        <small>Signature Date &amp; Time: <?php echo $showWhen($row['guarantor_signed_at'] ?? ''); ?></small>
      </div>
    </div>
    <footer class="agr-sheet-foot">
      <p>Created: <?php echo $showWhen($row['created_at'] ?? ''); ?> · Created By: <?php echo hire_h(hire_agr_user_label($db, (int)($row['created_by'] ?? 0)) ?: '—'); ?></p>
      <p>Updated By: <?php echo hire_h(hire_agr_user_label($db, (int)($row['updated_by'] ?? 0)) ?: '—'); ?> · Agreement Date: <?php echo $showDate($row['agreement_date'] ?? ''); ?></p>
    </footer>
  </article>

  <section class="agr-card no-print">
    <h2>Agreement History</h2>
    <?php if (!$versions): ?>
      <p class="agr-hint">সংরক্ষণের পর ভার্সন ইতিহাস এখানে দেখা যাবে।</p>
    <?php else: ?>
      <div class="agr-history">
        <?php foreach ($versions as $ver): ?>
          <?php $on = (int)$ver['id'] === (int)$row['id']; ?>
          <a class="agr-ver<?php echo $on ? ' is-on' : ''; ?>" href="agreement.php?id=<?php echo (int)$ver['id']; ?>">
            <b>ভার্সন <?php echo (int)$ver['version_no']; ?></b>
            <span><?php echo (int)$ver['version_no'] === $maxVer ? 'বর্তমান ভার্সন' : 'আগের ভার্সন'; ?> · <?php echo strtoupper((string)$ver['status']) === 'SIGNED' ? 'চূড়ান্ত' : 'খসড়া'; ?></span>
            <small><?php echo hire_h((string)($ver['verify_ref'] ?: $ver['created_at'])); ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="agr-card no-print">
    <h2>স্বাক্ষরের অডিট</h2>
    <?php if (!$signs): ?>
      <p class="agr-hint">এখনও কোনো স্বাক্ষর রেকর্ড নেই।</p>
    <?php else: ?>
      <div class="agr-audit">
        <?php foreach ($signs as $sign): ?>
          <article>
            <b><?php echo $sign['role'] === 'guarantor' ? 'জামিনদার' : 'কাস্টমার'; ?> · <?php echo hire_h((string)$sign['signer_name']); ?></b>
            <span><?php echo $showWhen($sign['signed_at'] ?? ''); ?></span>
            <small>Agreement <?php echo (int)$row['id']; ?> · ভার্সন <?php echo (int)$row['version_no']; ?> · IP <?php echo hire_h((string)$sign['ip']); ?> · <?php echo hire_h(substr((string)$sign['sha256'], 0, 12)); ?> · <?php echo hire_h(hire_agr_user_label($db, (int)$sign['created_by'])); ?></small>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div id="agrSign" class="agr-sign" hidden>
  <p class="agr-sign-who" id="agrSignWho">স্বাক্ষর</p>
  <p class="agr-sign-ask">অনুগ্রহ করে নিচের সাদা জায়গায় আপনার স্বাক্ষর দিন।</p>
  <canvas id="agrPad" width="800" height="420"></canvas>
  <div class="agr-sign-actions">
    <button type="button" class="agr-btn agr-btn-clear ame-calc-btn" id="agrClear">মুছে ফেলুন</button>
    <button type="button" class="agr-btn agr-btn-clear ame-calc-btn" id="agrRewrite">আবার লিখুন</button>
    <button type="button" class="agr-btn agr-btn-save ame-calc-btn" id="agrSubmit">Submit</button>
    <button type="button" class="agr-btn agr-btn-go ame-calc-btn" id="agrComplete">Complete</button>
  </div>
</div>
<p id="agrBusy" class="agr-busy no-print" hidden>চুক্তিনামার PDF তৈরি হচ্ছে…</p>
<script>window.AGR = <?php echo json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="/assets/js/html2canvas.min.js?v=<?php echo $av; ?>"></script>
<script src="/assets/js/jspdf.umd.min.js?v=<?php echo $av; ?>"></script>
<script src="/assets/js/hire-agreement.js?v=<?php echo $av; ?>"></script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
