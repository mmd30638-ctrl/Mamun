<?php
require_once '../../includes/config.php';
require_once '../../includes/bloan_functions.php';
ame_bloan_ensure($db);
ame_bloan_require($db);
$rows = $db->query('SELECT d.*, a.loan_no, p.app_no FROM bloan_documents d LEFT JOIN bloan_accounts a ON a.id=d.loan_id LEFT JOIN bloan_applications p ON p.id=d.application_id ORDER BY d.id DESC');
$apps = $db->query('SELECT id, app_no FROM bloan_applications ORDER BY id DESC LIMIT 100');
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/modules/bloan/bloan.css?v=<?php echo AME_ASSET_VERSION; ?>">
<div class="bl">
    <?php ame_bloan_nav(); ?>
    <h1><?php echo ame_l('ডকুমেন্ট', 'Documents'); ?></h1>
    <p class="en"><?php echo ame_l('শুধু PDF বা ছবি। দোকানের আগের আপলোড বদলানো হয় না।', 'PDF or images only. Existing shop uploads are not changed.'); ?></p>
    <?php ame_bloan_flash(); ?>
    <form class="grid" method="post" action="actions.php" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="document">
        <input type="hidden" name="back" value="/modules/bloan/documents.php">
        <label>আবেদন · Application<select name="application_id"><option value="0">—</option><?php if ($apps) while ($a = $apps->fetch_assoc()): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo ame_bloan_h((string)$a['app_no']); ?></option><?php endwhile; ?></select></label>
        <label>নাম · Title<input name="title" required></label>
        <label>ফাইল<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
        <label>নোট<input name="notes"></label>
        <div class="wide"><button type="submit">সংরক্ষণ</button></div>
    </form>
    <table>
        <tr><th>নাম</th><th>আবেদন</th><th>ঋণ</th><th>ফাইল</th></tr>
        <?php if ($rows) while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?php echo ame_bloan_h((string)$r['title']); ?></td>
            <td><?php echo ame_bloan_h((string)($r['app_no'] ?? '')); ?></td>
            <td><?php echo ame_bloan_h((string)($r['loan_no'] ?? '')); ?></td>
            <td><?php if ($r['file_name'] !== ''): ?><a href="/uploads/business-loans/<?php echo rawurlencode((string)$r['file_name']); ?>">খুলুন</a><?php endif; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
