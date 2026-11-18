<?php 
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);
include '../../partials/header.php'; 

$account_id = (int)($_GET['id'] ?? 0);
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// ⭐ তারিখ ফরম্যাট কড়াভাবে যাচাই — SQL Injection ঠেকাতে
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) { $start_date = date('Y-m-d', strtotime('-30 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) { $end_date = date('Y-m-d'); }

$accStmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
$accStmt->bind_param("i", $account_id);
$accStmt->execute();
$account_info = $accStmt->get_result()->fetch_assoc();

if (!$account_info) {
    die('❌ অ্যাকাউন্ট খুঁজে পাওয়া যায়নি। <a href="list.php">ফিরে যান</a>');
}

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'account-ledger-' . $account_id . '-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('অ্যাকাউন্ট লেজার', 'Account ledger'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('অ্যাকাউন্ট লেজার', 'Account ledger'),
        (string) ($account_info['account_name'] ?? ''),
        $start_date . ' — ' . $end_date,
    ])),
    'shop' => $shop,
];

// ⭐ prepared statement — SQL Injection থেকে সুরক্ষিত
$ledgerStmt = $db->prepare("SELECT l.*, ec.name AS category_name FROM account_ledger l 
               LEFT JOIN expense_categories ec ON ec.id = l.category_id
               WHERE l.account_id = ? 
               AND DATE(l.created_at) BETWEEN ? AND ? 
               ORDER BY l.id DESC");
$ledgerStmt->bind_param("iss", $account_id, $start_date, $end_date);
$ledgerStmt->execute();
$ledger = $ledgerStmt->get_result();
?>

<div style="padding: 15px;">
    <!-- তারিখ ফিল্টার ফরম -->
    <div class="card acc-print-hide" style="margin-bottom:15px; padding:10px;">
        <form method="GET" style="display:flex; gap:5px;">
            <input type="hidden" name="id" value="<?php echo (int)$account_id; ?>">
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="input-style" style="flex:1; padding:5px;">
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="input-style" style="flex:1; padding:5px;">
            <button type="submit" class="btn-success" style="padding:5px 10px;">দেখুন</button>
        </form>
    </div>

    <?php if (isset($_GET['reconciled'])): ?>
    <div class="card" style="background:#E8F5E9; color:#2E7D32; padding:12px; margin-bottom:10px; font-weight:bold;">✅ রিকনসাইল স্ট্যাটাস আপডেট হয়েছে।</div>
    <?php endif; ?>

    <?php include '../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <div class="card" style="background: var(--dark-blue); color: white; margin-bottom: 10px;">
        <h3 style="margin:0;"><?php echo htmlspecialchars($account_info['account_name']); ?></h3>
        <p style="margin:5px 0 0 0;">ব্যালেন্স: <?php echo ame_balance_label((float)($account_info['balance'] ?? 0)); ?></p>
        <p style="margin:5px 0 0 0; font-size:12px;"><?php echo htmlspecialchars($start_date . ' — ' . $end_date); ?></p>
    </div>

    <div class="card" style="padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse; font-size:12px;">
            <thead>
                <tr style="background:#f5f5f5; border-bottom:2px solid #ddd;">
                    <th style="padding:10px;">তারিখ</th>
                    <th style="padding:10px;">বিবরণ</th>
                    <th style="padding:10px; text-align:right;">জমা</th>
                    <th style="padding:10px; text-align:right;">খরচ</th>
                    <th class="acc-print-hide" style="padding:10px; text-align:center;">রিকনসাইল</th>
                    <th class="acc-print-hide" style="padding:10px; text-align:center;">কমান্ড</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ledger->num_rows > 0): while($row = $ledger->fetch_assoc()):
                    $is_reversal_entry = ($row['ref_type'] === 'reversal');
                    $is_reconciled = !empty($row['is_reconciled']);
                ?>
                <tr style="border-bottom: 1px solid #eee; <?php echo $is_reconciled ? 'background:#F1F8F4;' : ''; ?>">
                    <td style="padding:10px;"><?php echo date('d/m/y', strtotime($row['created_at'])); ?></td>
                    <td style="padding:10px;">
                        <?php echo htmlspecialchars($row['note']); ?>
                        <?php if (!empty($row['category_name'])): ?>
                        <br><span style="display:inline-block; margin-top:3px; padding:1px 7px; border-radius:10px; font-size:10px; background:#ECEFF1; color:#37474F;">🏷️ <?php echo htmlspecialchars($row['category_name']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px; text-align:right; color:var(--dark-green); font-weight:bold;">
                        <?php echo ($row['type'] == 'credit') ? number_format((float)($row['amount'] ?? 0), 2) : '-'; ?>
                    </td>
                    <td style="padding:10px; text-align:right; color:var(--red-alert); font-weight:bold;">
                        <?php echo ($row['type'] == 'debit') ? number_format((float)($row['amount'] ?? 0), 2) : '-'; ?>
                    </td>
                    <td class="acc-print-hide" style="padding:10px; text-align:center;">
                        <form method="POST" action="actions/toggle_reconciled.php" style="margin:0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="ledger_id" value="<?php echo (int)$row['id']; ?>">
                            <input type="hidden" name="account_id" value="<?php echo (int)$account_id; ?>">
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            <button type="submit" style="background:none; border:none; font-size:16px; cursor:pointer;" title="<?php echo $is_reconciled ? 'রিকনসাইল করা আছে — ক্লিক করে বাতিল করুন' : 'রিকনসাইল করতে ক্লিক করুন'; ?>">
                                <?php echo $is_reconciled ? '✅' : '⬜'; ?>
                            </button>
                        </form>
                    </td>
                    <td class="acc-print-hide" style="padding:10px; text-align:center;">
                        <a href="voucher.php?ledger_id=<?php echo (int)$row['id']; ?>" style="text-decoration:none; color:var(--dark-blue); border:1px solid var(--dark-blue); padding:3px 7px; border-radius:4px; font-size:11px; display:inline-block; margin-bottom:3px;">🧾 ভাউচার</a><br>
                        <?php if (!$is_reversal_entry): ?>
                        <form method="POST" action="actions/reverse_entry.php" style="margin:0; display:inline;"
                            onsubmit="return confirm('আপনি কি নিশ্চিত এই এন্ট্রিটি রিভার্স করতে চান? এটি একটি নতুন উল্টো এন্ট্রি তৈরি করবে।');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="ledger_id" value="<?php echo (int)$row['id']; ?>">
                            <input type="hidden" name="account_id" value="<?php echo (int)$account_id; ?>">
                            <button type="submit" style="background:none; border:1px solid var(--red-alert); color:var(--red-alert); padding:3px 7px; border-radius:4px; font-size:11px; cursor:pointer;">↩️ রিভার্স</button>
                        </form>
                        <?php else: ?>
                        <span style="color:#999; font-size:11px;">রিভার্সাল</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" style="padding:15px; text-align:center; color:#888;">এই সময়সীমায় কোনো লেনদেন পাওয়া যায়নি।</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
    <a href="list.php" class="btn-success acc-print-hide" style="display:block; text-align:center; margin-top:15px; text-decoration:none; background:var(--dark-blue);">ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
