<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
$here = 'settings';
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.319', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<div class="hire-wrap">
  <?php echo hire_flash(); ?>
  <header class="hire-mast">
    <p class="hire-kicker"><?php echo ame_pair('সিস্টেম', 'System'); ?></p>
    <h1><?php echo ame_pair('ভাড়া সেটিংস', 'Rental settings'); ?></h1>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>
  <form class="hire-panel" method="post" action="actions.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="settings">
    <div class="hire-grid">
      <label><?php echo ame_pair('মাসিক বিলিং পদ্ধতি', 'Monthly billing method'); ?>
        <select name="billing_method">
          <option value="calendar_month" <?php echo hire_setting($db, 'billing_method') === 'calendar_month' ? 'selected' : ''; ?>><?php echo ame_l('ক্যালেন্ডার মাস', 'Calendar month'); ?></option>
          <option value="billing_day" <?php echo hire_setting($db, 'billing_method') === 'billing_day' ? 'selected' : ''; ?>><?php echo ame_l('নির্দিষ্ট তারিখ', 'Fixed billing day'); ?></option>
        </select>
      </label>
      <label><?php echo ame_pair('ডিফল্ট বিলিং দিন', 'Default billing day'); ?>
        <input type="number" min="1" max="28" name="default_billing_day" value="<?php echo hire_h(hire_setting($db, 'default_billing_day', '1')); ?>">
      </label>
      <label><?php echo ame_pair('ডিফল্ট বিলম্ব ফি', 'Default late fee'); ?>
        <select name="late_fee_type">
          <?php foreach (['none' => ame_l('নেই', 'None'), 'per_day' => ame_l('প্রতিদিন', 'Per day'), 'fixed' => ame_l('নির্দিষ্ট', 'Fixed'), 'percent' => ame_l('শতাংশ', 'Percent')] as $k => $lab): ?>
            <option value="<?php echo $k; ?>" <?php echo hire_setting($db, 'late_fee_type') === $k ? 'selected' : ''; ?>><?php echo $lab; ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?php echo ame_pair('বিলম্ব ফি মান', 'Late fee value'); ?>
        <input type="number" step="0.01" min="0" name="late_fee_value" value="<?php echo hire_h(hire_setting($db, 'late_fee_value', '0')); ?>">
      </label>
    </div>
    <p class="hire-muted"><?php echo ame_pair('জামানত কখনো নিজে থেকে খাতায় ক্রেডিট হয় না।', 'Deposit never auto-credits the customer ledger.'); ?></p>
    <div class="hire-actions">
      <button class="hire-btn hire-btn-primary" type="submit"><?php echo ame_pair('সংরক্ষণ', 'Save'); ?></button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
