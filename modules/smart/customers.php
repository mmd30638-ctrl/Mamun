<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

$filter = preg_replace('/[^a-z]/', '', (string)($_GET['cat'] ?? 'all'));
$rows = ame_smart_customer_intel($db);
$counts = ['all' => count($rows), 'vip' => 0, 'regular' => 0, 'new' => 0, 'inactive' => 0];
$due_sum = 0.0;
foreach ($rows as $row) {
    $counts[$row['category']] = ($counts[$row['category']] ?? 0) + 1;
    $due_sum += (float)($row['due'] ?? 0);
}
$shown = $rows;
if ($filter !== 'all') {
    $shown = array_values(array_filter($rows, static fn($row) => $row['category'] === $filter));
}
$en = is_en();

ame_smart_open('smart_title_customers', 'smart_lead_customers');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <?php
    $cards = [
        ['all', $en ? 'All' : 'সব', 'green', '▤'],
        ['vip', 'VIP', 'blue', '★'],
        ['regular', $en ? 'Regular' : 'নিয়মিত', 'purple', '●'],
        ['new', $en ? 'New' : 'নতুন', 'amber', '+'],
        ['inactive', $en ? 'Inactive' : 'নিষ্ক্রিয়', 'red', '!'],
    ];
    foreach ($cards as [$key, $label, $tone, $icon]):
    ?>
    <a class="smt-dash-kpi<?php echo $filter === $key ? ' on' : ''; ?>" href="?cat=<?php echo $key; ?>">
      <i class="smt-dash-icon <?php echo $tone; ?>"><?php echo $icon; ?></i>
      <small><?php echo esc_html($label); ?></small>
      <b><?php echo (int)$counts[$key]; ?></b>
      <em><?php echo $key === 'all' ? ame_smart_money($due_sum) : ($en ? 'Customers' : 'ক্রেতা'); ?></em>
    </a>
    <?php endforeach; ?>
  </section>

  <section class="smt-dash-detail">
    <header>
      <div>
        <h3><?php echo $en ? 'Customer insight' : 'ক্রেতা বুদ্ধি'; ?></h3>
        <p><?php echo $en ? 'Bills, spend, dues and last visit — tap a name for the ledger.' : 'বিল, খরচ, বকেয়া ও শেষ আসা — নামে খাতা খুলবে।'; ?></p>
      </div>
      <a href="/modules/customers/add_customer.php"><?php echo $en ? 'New customer' : 'নতুন ক্রেতা'; ?></a>
    </header>
    <?php if (!$shown): ?>
    <div class="smt-dash-empty"><?php echo $en ? 'No customers in this group.' : 'এই ক্যাটাগরিতে ক্রেতা নেই।'; ?></div>
    <?php else: foreach ($shown as $customer): ?>
    <a class="smt-dash-row link" href="/modules/customers/ledger.php?id=<?php echo (int)$customer['id']; ?>">
      <div>
        <div class="nm"><?php echo esc_html((string)$customer['name']); ?></div>
        <div class="meta">
          <?php echo (int)$customer['bill_count']; ?><?php echo $en ? ' bills' : 'টা বিল'; ?>
          · <?php echo ame_smart_money((float)$customer['total_buy']); ?>
          · <?php echo $en ? 'Due ' : 'বকেয়া '; ?><?php echo ame_smart_money((float)$customer['due']); ?>
          · <?php echo $en ? 'Last ' : 'শেষ '; ?><?php echo $customer['last_buy'] ? esc_html(substr((string)$customer['last_buy'], 0, 10)) : ($en ? 'none' : 'নেই'); ?>
        </div>
      </div>
      <span class="smt-mini <?php echo $customer['category'] === 'vip' ? 'vip' : ($customer['category'] === 'inactive' ? 'hot' : ''); ?>">
        <?php echo esc_html(ame_smart_customer_label($customer['category'])); ?>
      </span>
    </a>
    <?php endforeach; endif; ?>
  </section>
</div>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
