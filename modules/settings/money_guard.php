<?php
require_once '../../includes/config.php';
require_once '../../includes/money_guard_functions.php';
require_once '../customers/customer_ledger_functions.php';
admin_only();

/*
 * ⭐ কাস্টমার লেজার-অমিল "মিলান" বাটন।
 * লেজার টেবিল append-only ও প্রতিটা সারি সত্যিকারের লেনদেন — তাই এটাই সঠিক
 * উৎস ধরে receivable_balance-কে লেজারের যোগফলে বসানো হয় (দেখুন
 * ame_customer_balance_resync_from_ledger)। এটা receivable_balance-কে বদলায়,
 * লেজারের কোনো সারি মোছে/বদলায় না — তাই নিরাপদ ও উল্টানো যায় (আবার লেজার
 * চেক করলেই বোঝা যাবে)।
 */
$sync_flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sync_customer_id'])) {
    csrf_verify();
    $sync_id = (int)$_POST['sync_customer_id'];
    if ($sync_id > 0) {
        $result = ame_customer_balance_resync_from_ledger($db, $sync_id);
        $sync_flash = $result !== null ? 'ওকে' : 'ব্যর্থ';
        if (function_exists('log_activity')) {
            log_activity('Customer Balance Resync', 'Money Guard', 'কাস্টমার #' . $sync_id . ' — লেজার অনুযায়ী মিলানো হলো');
        }
    }
    header('Location: money_guard.php?synced=' . urlencode($sync_flash));
    exit();
}
$synced_msg = (string)($_GET['synced'] ?? '');

include '../../partials/header.php';

$r = ame_money_guard_report($db);
$ok = !empty($r['ok']);
?>
<style>
.mg { padding: 14px; max-width: 720px; margin: 0 auto; }
.mg-card { background:#fff; border-radius:16px; box-shadow:0 8px 24px rgba(11,30,77,.08); overflow:hidden; margin-bottom:14px; }
.mg-head { padding:18px 16px 10px; }
.mg-head h3 { margin:0; color:#0B1E4D; font-size:18px; }
.mg-head p { margin:6px 0 0; color:#64748B; font-size:13px; line-height:1.5; }
.mg-formula { margin:12px 16px 16px; padding:12px 14px; border-radius:12px; background:#EEF6FF; color:#0B1E4D; font-weight:800; font-size:15px; }
.mg-row { display:flex; justify-content:space-between; gap:12px; padding:11px 16px; border-top:1px solid #F1F5F9; font-size:13px; }
.mg-row b { color:#0B1E4D; }
.mg-ok { color:#0E8F52; font-weight:800; }
.mg-bad { color:#C62828; font-weight:800; }
.mg-warn { color:#B45309; font-weight:800; }
.mg-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.mg-table th, .mg-table td { padding:8px 12px; text-align:left; border-top:1px solid #F1F5F9; }
.mg-table th { color:#64748B; font-weight:700; }
.mg-table td.num { text-align:right; font-variant-numeric: tabular-nums; }
.mg-body { padding:4px 16px 16px; color:#334155; font-size:13.5px; line-height:1.65; }
.mg-body ul { margin:0; padding-left:1.2em; }
.mg-btns { display:grid; gap:8px; padding:0 16px 16px; }
.mg-btns a { display:block; text-align:center; padding:12px; border-radius:10px; background:#0B1E4D; color:#fff; font-weight:800; text-decoration:none; font-size:14px; }
.mg-btns a.quiet { background:#fff; color:#0B1E4D; border:1px solid #90caf9; }
.mg-empty { padding:12px 16px 16px; color:#0E8F52; font-weight:700; font-size:13.5px; }
</style>
<div class="mg">
  <div class="mg-card">
    <div class="mg-head">
      <h3>৳ টাকা মিল</h3>
      <p>কাস্টমার বকেয়া, ক্যাশ/ব্যাংক ও সাপ্লায়ার হিসাব একই অঙ্ক কিনা — এই পেজ শুধু দেখায়, কিছু কাটে না।</p>
    </div>
    <div class="mg-formula"><?php echo htmlspecialchars($r['formula']); ?></div>
    <div class="mg-row">
      <b>সব মিলছে</b>
      <span class="<?php echo $ok ? 'mg-ok' : 'mg-bad'; ?>"><?php echo $ok ? 'হ্যাঁ' : 'না — নিচে দেখুন'; ?></span>
    </div>
    <div class="mg-row">
      <b>কাস্টমার লেজার অমিল</b>
      <span class="<?php echo $r['counts']['customers'] ? 'mg-bad' : 'mg-ok'; ?>"><?php echo (int)$r['counts']['customers']; ?></span>
    </div>
    <div class="mg-row">
      <b>ক্যাশ/ব্যাংক অমিল</b>
      <span class="<?php echo $r['counts']['accounts'] ? 'mg-bad' : 'mg-ok'; ?>"><?php echo (int)$r['counts']['accounts']; ?></span>
    </div>
    <div class="mg-row">
      <b>পেমেন্ট ক্যাশে যায়নি</b>
      <span class="<?php echo $r['counts']['orphans'] ? 'mg-bad' : 'mg-ok'; ?>"><?php echo (int)$r['counts']['orphans']; ?></span>
    </div>
    <div class="mg-row">
      <b>ভুয়া ক্রয় লেজার সারি</b>
      <span class="<?php echo $r['counts']['phantom'] ? 'mg-warn' : 'mg-ok'; ?>"><?php echo (int)$r['counts']['phantom']; ?></span>
    </div>
    <div class="mg-row">
      <b>সাপ্লায়ার আলাদা ক্রেডিট নোট</b>
      <span class="<?php echo $r['counts']['split_credit'] ? 'mg-warn' : 'mg-ok'; ?>"><?php echo (int)$r['counts']['split_credit']; ?></span>
    </div>
  </div>

  <div class="mg-card">
    <div class="mg-head">
      <h3>কাস্টমার — বকেয়া vs লেজার</h3>
      <p>receivable_balance = লেজারের ডেবিট − ক্রেডিট। প্লাস মানে দোকান পাবে, মাইনাস মানে কাস্টমারের জমা।</p>
      <p>কেন অমিল হয়: একই কাস্টমারের দুটো লেনদেন (যেমন বিক্রয় ও পেমেন্ট) হুবহু একই মুহূর্তে দুই স্টাফ করলে আগে একটা আপডেট হারিয়ে যেতে পারতো — এই বাগ এখন কোডে ঠিক করা হয়েছে। নিচের "🔄 মিলান" বাটনে চাপলে ওই কাস্টমারের জমা-খরচ লেজার আবার মিলিয়ে দেবে (নিরাপদ — receivable_balance বদলায় না, লেজারে যা বাকি ছিল তা বসায়)।</p>
    </div>
    <?php if ($synced_msg === 'ওকে'): ?>
      <div class="mg-row"><b class="mg-ok">মিলিয়ে দেওয়া হয়েছে — নিচে আবার চেক করুন</b></div>
    <?php elseif ($synced_msg === 'ব্যর্থ'): ?>
      <div class="mg-row"><b class="mg-bad">মিলাতে সমস্যা হয়েছে — error log দেখুন</b></div>
    <?php endif; ?>
    <?php if (!$r['customer_mismatches']): ?>
      <div class="mg-empty">কাস্টমার হিসাব মিলেছে।</div>
    <?php else: ?>
      <table class="mg-table">
        <thead><tr><th>কাস্টমার</th><th class="num">কার্ড</th><th class="num">লেজার</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($r['customer_mismatches'] as $c): ?>
          <tr>
            <td><a href="/modules/customers/ledger.php?id=<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></a><br><small><?php echo htmlspecialchars($c['code']); ?></small></td>
            <td class="num"><?php echo number_format($c['receivable'], 2); ?></td>
            <td class="num"><?php echo number_format($c['ledger_net'], 2); ?></td>
            <td>
              <form method="post" action="money_guard.php" onsubmit="return confirm('এই কাস্টমারের লেজার মিলিয়ে দেবেন?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sync_customer_id" value="<?php echo (int)$c['id']; ?>">
                <button type="submit" style="border:none;background:#0B1E4D;color:#fff;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;">🔄 মিলান</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="mg-card">
    <div class="mg-head">
      <h3>ক্যাশ / ব্যাংক — ব্যালেন্স vs শেষ লেজার</h3>
      <p>accounts.balance আর শেষ account_ledger.balance_after একই হতে হবে।</p>
    </div>
    <?php if (!$r['account_mismatches']): ?>
      <div class="mg-empty">অ্যাকাউন্ট হিসাব মিলেছে।</div>
    <?php else: ?>
      <table class="mg-table">
        <thead><tr><th>অ্যাকাউন্ট</th><th class="num">ব্যালেন্স</th><th class="num">লেজার শেষ</th></tr></thead>
        <tbody>
        <?php foreach ($r['account_mismatches'] as $a): ?>
          <tr>
            <td><a href="/modules/accounts/ledger.php?id=<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></a></td>
            <td class="num"><?php echo ame_balance_label((float)$a['balance']); ?></td>
            <td class="num"><?php echo $a['ledger_after'] === null ? '—' : ame_balance_label((float)$a['ledger_after']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="mg-card">
    <div class="mg-head">
      <h3>কাস্টমার পেমেন্ট যেগুলো ক্যাশে বসেনি</h3>
      <p>পেমেন্ট নিলে অবশ্যই ক্যাশ/ব্যাংক বাছাই করতে হবে — তাহলে এখানে সারি আসবে না।</p>
    </div>
    <?php if (!$r['orphan_payments']): ?>
      <div class="mg-empty">সব পেমেন্ট ক্যাশ/ব্যাংকে জমা হয়েছে।</div>
    <?php else: ?>
      <table class="mg-table">
        <thead><tr><th>রসিদ</th><th>কাস্টমার</th><th class="num">টাকা</th></tr></thead>
        <tbody>
        <?php foreach ($r['orphan_payments'] as $p): ?>
          <tr>
            <td><a href="/modules/customers/receipt.php?payment_id=<?php echo (int)$p['id']; ?>">PAY-<?php echo str_pad((string)$p['id'], 6, '0', STR_PAD_LEFT); ?></a><br><small><?php echo htmlspecialchars($p['date'] . ' · ' . $p['method']); ?></small></td>
            <td><?php echo htmlspecialchars($p['customer']); ?></td>
            <td class="num"><?php echo number_format($p['amount'], 2); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="mg-card">
    <div class="mg-head">
      <h3>সাপ্লায়ার</h3>
      <p>ক্রয়ে অ্যাকাউন্ট খালি = পরিশোধ ০, বকেয়া সাবেকের সাথে যোগ। অ্যাকাউন্ট বাছাই = টাকা কাটে। ক্রয় ফেরত ক্রেডিট নোট এখন বকেয়াও কমায়।</p>
    </div>
    <?php if ((int)$r['phantom_purchase_rows'] > 0): ?>
      <div class="mg-row"><b>ভুয়া ক্রয় সারি (পুরনো বাগ)</b><span class="mg-warn"><?php echo (int)$r['phantom_purchase_rows']; ?>টা — ক্যাশ কাটেনি, শুধু লেজারে ০ ব্যালেন্স</span></div>
    <?php endif; ?>
    <?php if (!$r['suppliers_with_split_credit']): ?>
      <div class="mg-empty">আলাদা ক্রেডিট নোট নেই — বকেয়া এক ঘরে।</div>
    <?php else: ?>
      <table class="mg-table">
        <thead><tr><th>সাপ্লায়ার</th><th class="num">বকেয়া</th><th class="num">ক্রেডিট নোট</th><th class="num">কার্যকর</th></tr></thead>
        <tbody>
        <?php foreach ($r['suppliers_with_split_credit'] as $s): ?>
          <tr>
            <td><a href="/modules/suppliers/ledger.php?id=<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></a></td>
            <td class="num"><?php echo number_format($s['total_due'], 2); ?></td>
            <td class="num"><?php echo number_format($s['credit_balance'], 2); ?></td>
            <td class="num"><?php echo number_format($s['effective'], 2); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="mg-card">
    <div class="mg-head"><h3>দোকানে যা করবেন</h3></div>
    <div class="mg-body">
      <ul>
        <li>কাস্টমারের টাকা নিলে <b>ক্যাশ/ব্যাংক অ্যাকাউন্ট</b> বাছাই করুন — না হলে পেমেন্ট সেভ হয় না।</li>
        <li>সাপ্লায়ারকে দিলেও অ্যাকাউন্ট বাছাই — খালি রাখলে বকেয়া বাড়ে, ক্যাশ কাটে না।</li>
        <li>একই ইনভয়েস দুবার দেবেন না; খুচরা মূল্য ক্রয়ের কম হবে না।</li>
        <li>এই পেজ অমিল দেখালে SQL ব্যাকআপ নিয়ে তারপর সংশোধন করুন — এখান থেকে অটো-ফিক্স নেই।</li>
      </ul>
    </div>
    <div class="mg-btns">
      <a href="/modules/customers/payment-receive.php">💵 কাস্টমার পেমেন্ট</a>
      <a class="quiet" href="/modules/suppliers/pay.php">💳 সাপ্লায়ার পেমেন্ট</a>
      <a class="quiet" href="project_care.php">🛡️ প্রজেক্ট রক্ষা</a>
      <a class="quiet" href="system_health.php">🔍 সিস্টেম হেলথ</a>
    </div>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
