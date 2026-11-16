<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
ensure_accounts_schema($db);

$ame_body_class = 'ame-txn-page';
$flash = (string)($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$members = [];
$memRes = @$db->query('SELECT id, name, role, is_active FROM family_members ORDER BY is_active DESC, name ASC');
if ($memRes) {
    $members = $memRes->fetch_all(MYSQLI_ASSOC);
}
$role_labels = ['father' => 'বাবা', 'mother' => 'মা', 'brother' => 'ভাই', 'sister' => 'বোন', 'other' => 'অন্যান্য'];

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/txn-desk.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">

<div class="txn-wrap">
    <header class="txn-hero">
        <h1>পরিবারের সদস্য</h1>
        <p>কে খরচ করেছেন — সাধারণ লেনদেনে এই নামগুলো বেছে নেওয়া যায়।</p>
    </header>

    <?php if ($flash !== ''): ?>
        <div class="txn-flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <nav class="txn-toolbar">
        <a class="txn-btn ghost" href="add_transaction.php">সাধারণ লেনদেন</a>
    </nav>

    <section class="txn-card">
        <h2>নতুন সদস্য</h2>
        <form class="txn-form" action="actions/process_family_member.php" method="POST">
            <?php echo csrf_field(); ?>
            <label class="txn-field">
                নাম
                <input type="text" name="name" required maxlength="100" placeholder="নাম">
            </label>
            <label class="txn-field">
                সম্পর্ক
                <select name="role">
                    <?php foreach ($role_labels as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="txn-full">
                <button type="submit" class="txn-submit">সদস্য যোগ করুন</button>
            </div>
        </form>
    </section>

    <section class="txn-card">
        <h2>সদস্য তালিকা</h2>
        <?php if (empty($members)): ?>
            <p class="txn-empty">এখনো কোনো সদস্য যোগ করা হয়নি।</p>
        <?php else: ?>
            <div class="txn-table-wrap">
                <table class="txn-table">
                    <thead>
                        <tr><th>নাম</th><th>সম্পর্ক</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$m['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($role_labels[$m['role'] ?? ''] ?? (string)($m['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include '../../partials/footer.php'; ?>
