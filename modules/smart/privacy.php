<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../../partials/header.php';

if (function_exists('ensure_user_schema')) {
    ensure_user_schema($db);
}
$want = [
    'admin' => 'Admin — সব খোলা',
    'manager' => 'Manager — দোকান চালানো',
    'employee' => 'Employee — দৈনন্দিন কাজ',
    'cashier' => 'Cashier — POS ও কালেকশন',
    'family' => 'Family Member — শুধু পারিবারিক ঘর',
    'viewer' => 'View Only — দেখা, সেভ নয়',
];
$have = [];
$res = @$db->query('SELECT role_key, role_label FROM user_roles');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $have[$row['role_key']] = $row['role_label'];
    }
}
foreach ($want as $key => $label) {
    if (!isset($have[$key])) {
        $st = $db->prepare('INSERT INTO user_roles (role_key, role_label) VALUES (?,?)');
        if ($st) {
            $st->bind_param('ss', $key, $label);
            $st->execute();
            $st->close();
            $have[$key] = $label;
        }
    }
}
$en = is_en();

ame_smart_open('smart_title_privacy', 'smart_lead_privacy');
?>
<div class="smt-page smt-if">
  <section class="smt-dash-kpis">
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon green">★</i>
      <small><?php echo $en ? 'Shop roles' : 'ব্যবসার রোল'; ?></small>
      <b><?php echo count($have); ?></b>
      <em><?php echo $en ? 'Kept and filled if missing' : 'না থাকলে নিজে যোগ হয়'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon blue">⌂</i>
      <small><?php echo $en ? 'Family' : 'পরিবার'; ?></small>
      <b><?php echo $en ? 'Separate' : 'আলাদা'; ?></b>
      <em><?php echo $en ? 'family_* tables only' : 'শুধু family_* টেবিল'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon purple">✓</i>
      <small>Admin</small>
      <b><?php echo $en ? 'Open' : 'খোলা'; ?></b>
      <em><?php echo $en ? 'Always full access' : 'সবসময় সব অনুমতি'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon amber">↺</i>
      <small><?php echo $en ? 'Offline' : 'অফলাইন'; ?></small>
      <b><?php echo $en ? 'Queue' : 'কিউ'; ?></b>
      <em><?php echo $en ? 'POS and payments sync later' : 'POS ও পেমেন্ট পরে সিঙ্ক'; ?></em>
    </article>
    <article class="smt-dash-kpi">
      <i class="smt-dash-icon red">✎</i>
      <small><?php echo $en ? 'Custom ticks' : 'কাস্টম টিক'; ?></small>
      <b><?php echo $en ? 'Kept' : 'আগের'; ?></b>
      <em><?php echo $en ? 'Old permissions stay' : 'পুরনো অনুমতি ভাঙে না'; ?></em>
    </article>
  </section>

  <section class="smt-dash-main">
    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Shop roles' : 'ব্যবসার রোল'; ?></h3>
        <a href="/modules/settings/role_permissions.php"><?php echo $en ? 'Permissions' : 'পারমিশন'; ?></a>
      </header>
      <div class="smt-dash-list">
        <?php foreach ($have as $key => $label): ?>
        <div>
          <span>
            <strong><?php echo esc_html((string)$label); ?></strong>
            <small><?php echo esc_html((string)$key); ?></small>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </article>
    <article class="smt-dash-panel">
      <header>
        <h3><?php echo $en ? 'Family stays apart' : 'পরিবার আলাদা'; ?></h3>
        <a href="/modules/family/access.php"><?php echo $en ? 'Family access' : 'পারিবারিক অ্যাক্সেস'; ?></a>
      </header>
      <p class="smt-dash-copy"><?php echo $en
        ? 'Family data lives in family_* tables. Shop sales and stock never appear there. Inner roles: Admin / Member / Limited / View — health and expense ticks stay separate.'
        : 'পারিবারিক ডেটা family_* টেবিলে। ব্যবসার সেল/স্টক এখানে আসে না। ভিতরের রোল: Admin / Member / Limited / View — স্বাস্থ্য ও খরচ আলাদা টিক।'; ?></p>
    </article>
  </section>

  <section class="smt-dash-detail">
    <header>
      <div>
        <h3><?php echo $en ? 'Offline' : 'অফলাইন'; ?></h3>
        <p><?php echo $en
          ? 'Without net, POS still opens and customer payments queue on the phone. When net returns, the queue syncs. This is not a full cloud platform — it stays safe on shared Hostinger hosting.'
          : 'নেট না থাকলে POS খোলা যায়, কাস্টমার পেমেন্ট ফোনে জমা হয়। নেট এলে কিউ সিঙ্ক হয়। পুরো ক্লাউড সিঙ্ক প্ল্যাটফর্ম নয় — Hostinger শেয়ার্ড হোস্টিংয়ে যা নিরাপদে চলে।'; ?></p>
      </div>
    </header>
  </section>
</div>
<?php ame_smart_close(); include __DIR__ . '/../../partials/footer.php'; ?>
