<?php
/* 10.98.789 */
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/txn_types.php';
admin_only();
ensure_accounts_schema($db);

function ame_cc_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$dbName = '';
$res = @$db->query('SELECT DATABASE() AS n');
if ($res && ($row = $res->fetch_assoc())) {
    $dbName = (string)$row['n'];
}

$tables = [];
$res = @$db->query("SHOW TABLES LIKE '%categor%'");
if ($res) {
    while ($row = $res->fetch_row()) {
        $tables[] = (string)$row[0];
    }
}

$files = [
    'modules/accounts/categories.php',
    'modules/accounts/add_transaction.php',
    'modules/accounts/actions/process_category.php',
    'modules/accounts/actions/process_transaction.php',
];
?><!DOCTYPE html>
<html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ধরন যাচাই</title>
<style>body{font-family:sans-serif;padding:14px;background:#f4f7fb;color:#111827}table{border-collapse:collapse;width:100%;background:#fff;margin:8px 0 18px}td,th{border:1px solid #d1d5db;padding:6px 8px;font-size:14px;text-align:left;word-break:break-all}h2{font-size:17px;margin:16px 0 4px}</style>
</head><body>
<h1 style="font-size:20px">ধরন যাচাই — ভার্সন <?php echo ame_cc_h(AME_APP_VERSION); ?></h1>
<p>ডাটাবেস: <b><?php echo ame_cc_h($dbName); ?></b></p>

<?php
$dropdown = ame_txn_types($db);
?>
<h2>সাধারণ লেনদেনের ড্রপডাউন যা পড়ে — <?php echo count($dropdown); ?> টি</h2>
<table><tr><th>id</th><th>নাম</th></tr>
<?php foreach ($dropdown as $d): ?><tr><td><?php echo ame_cc_h($d['id']); ?></td><td><?php echo ame_cc_h($d['name']); ?></td></tr><?php endforeach; ?>
</table>

<?php
$used = [];
$ur = @$db->query("SELECT al.category_id AS id, ec.name AS name, COUNT(*) AS c FROM account_ledger al LEFT JOIN expense_categories ec ON ec.id = al.category_id WHERE al.category_id IS NOT NULL GROUP BY al.category_id, ec.name ORDER BY c DESC");
if ($ur) {
    $used = $ur->fetch_all(MYSQLI_ASSOC);
}
?>
<h2>লেনদেনে ব্যবহৃত ধরন — <?php echo count($used); ?> টি</h2>
<table><tr><th>id</th><th>নাম</th><th>লেনদেন</th></tr>
<?php foreach ($used as $u): ?><tr><td><?php echo ame_cc_h($u['id']); ?></td><td><?php echo ame_cc_h($u['name'] ?? '(তালিকায় নেই)'); ?></td><td><?php echo ame_cc_h($u['c']); ?></td></tr><?php endforeach; ?>
</table>

<h2>ফাইলের অবস্থা</h2>
<table><tr><th>ফাইল</th><th>MD5</th><th>বদলানো</th></tr>
<?php foreach ($files as $rel):
    $path = dirname(__DIR__, 2) . '/' . $rel; ?>
<tr><td><?php echo ame_cc_h($rel); ?></td>
<td><?php echo is_file($path) ? ame_cc_h(substr(md5_file($path), 0, 10)) : 'নেই'; ?></td>
<td><?php echo is_file($path) ? ame_cc_h(date('d/m/y H:i', filemtime($path))) : '—'; ?></td></tr>
<?php endforeach; ?>
</table>

<?php foreach ($tables as $t):
    $safe = str_replace('`', '', $t);
    $cnt = 0;
    $rows = [];
    $r = @$db->query("SELECT COUNT(*) AS c FROM `{$safe}`");
    if ($r && ($x = $r->fetch_assoc())) {
        $cnt = (int)$x['c'];
    }
    $r = @$db->query("SELECT * FROM `{$safe}` ORDER BY 1 ASC LIMIT 40");
    if ($r) {
        $rows = $r->fetch_all(MYSQLI_ASSOC);
    } ?>
<h2><?php echo ame_cc_h($safe); ?> — <?php echo $cnt; ?> টি</h2>
<?php if ($rows): ?>
<table><tr><?php foreach (array_keys($rows[0]) as $col): ?><th><?php echo ame_cc_h($col); ?></th><?php endforeach; ?></tr>
<?php foreach ($rows as $row): ?><tr><?php foreach ($row as $val): ?><td><?php echo ame_cc_h($val); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</table>
<?php endif; ?>
<?php endforeach; ?>
</body></html>
