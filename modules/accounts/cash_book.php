<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);

$ame_body_class = 'ame-txn-page';
$rows = [];
$logs = @$db->query(
    "SELECT al.*, a.account_name
     FROM account_ledger al
     LEFT JOIN accounts a ON a.id = al.account_id
     ORDER BY al.id DESC
     LIMIT 100"
);
if ($logs) {
    $rows = $logs->fetch_all(MYSQLI_ASSOC);
}

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/txn-desk.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">

<div class="txn-wrap">
    <header class="txn-hero">
        <h1>ক্যাশ বই</h1>
        <p>সব হিসাবের জমা ও খরচ — ফোন, ট্যাবলেট ও ডেস্কটপে একই রং।</p>
    </header>

    <nav class="txn-toolbar">
        <a class="txn-btn ghost" href="add_transaction.php">সাধারণ লেনদেন</a>
        <a class="txn-btn ghost" href="list.php">হিসাব তালিকা</a>
    </nav>

    <section class="txn-card">
        <h2>সামগ্রিক লেনদেন</h2>
        <?php if (empty($rows)): ?>
            <p class="txn-empty">কোনো লেনদেন নেই।</p>
        <?php else: ?>
            <div class="txn-table-wrap">
                <table class="txn-table">
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>বিবরণ</th>
                            <th>জমা (+)</th>
                            <th>খরচ (-)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $l):
                        $is_in = ($l['type'] ?? '') === 'credit';
                        $when = (string)($l['created_at'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <?php echo $when !== '' ? date('d/m/y', strtotime($when)) : '—'; ?>
                                <span class="txn-note"><?php echo $when !== '' ? date('h:i A', strtotime($when)) : ''; ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string)($l['account_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                <span class="txn-note"><?php echo htmlspecialchars((string)($l['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="in"><?php echo $is_in ? number_format((float)($l['amount'] ?? 0), 2) : '—'; ?></td>
                            <td class="out"><?php echo $is_in ? '—' : number_format((float)($l['amount'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include '../../partials/footer.php'; ?>
