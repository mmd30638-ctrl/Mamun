<?php
/* 10.98.789 */
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/txn_types.php';
ensure_accounts_schema($db);
ame_txn_types_cleanup_once($db);

$ame_body_class = 'ame-txn-page';
$flash = (string) ($_SESSION['user_flash'] ?? '');
$flash_ok = !empty($_SESSION['user_flash_ok']);
unset($_SESSION['user_flash'], $_SESSION['user_flash_ok']);

$categories = ame_txn_types($db);
$can_admin = ame_txn_types_can_admin();
$type_count = count($categories);

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/txn-desk.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">

<div class="txn-wrap">
    <header class="txn-hero">
        <h1>খরচ / আয় ধরন</h1>
        <p>সাধারণ লেনদেনের সব ধরন এখান থেকে যোগ করুন, নাম বদলান বা মুছুন। নাম বদলালে টাকার হিসাব থাকে।</p>
    </header>

    <?php if ($flash !== ''): ?>
        <div class="txn-flash <?php echo $flash_ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <nav class="txn-toolbar">
        <a class="txn-btn ghost" href="add_transaction.php">সাধারণ লেনদেন</a>
        <a class="txn-btn ghost" href="category_report.php">আয় ব্যয় রিপোর্ট</a>
        <a class="txn-btn ghost" href="list.php">হিসাব তালিকা</a>
    </nav>

    <section class="txn-card">
        <h2>নতুন ধরন</h2>
        <form class="txn-form" action="actions/process_category.php" method="POST">
            <?php echo csrf_field(); ?>
            <label class="txn-field txn-full">
                নাম
                <input type="text" name="name" required maxlength="100" placeholder="যেমন: ভাড়া, বেতন" autocomplete="off">
            </label>
            <div class="txn-full">
                <button type="submit" class="txn-submit">যোগ করুন</button>
            </div>
        </form>
    </section>

    <section class="txn-card">
        <h2>ধরন তালিকা — <?php echo (int) $type_count; ?>টি</h2>
        <p class="txn-note">প্রতিটি ধরন সাধারণ লেনদেনে ব্যবহার করা যায়। নাম সংরক্ষণ করলে শুধু লেখা বদলায়।</p>
        <?php if (empty($categories)): ?>
            <p class="txn-empty">এখনো কোনো ধরন যোগ হয়নি।</p>
        <?php else: ?>
            <div class="txn-type-list" data-ame-no-page="1">
                <?php foreach ($categories as $c): ?>
                    <article class="txn-type-card">
                        <form class="txn-type-edit" action="actions/process_category.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                            <label class="txn-field">
                                ধরনের নাম
                                <input type="text" name="name" required maxlength="100" value="<?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <div class="txn-type-actions">
                                <button type="submit" class="txn-submit txn-type-save">সংরক্ষণ</button>
                            </div>
                        </form>
                        <?php if ($can_admin): ?>
                            <form action="actions/delete_category.php" method="POST" onsubmit="return confirm('এই ধরন মুছবেন? লেনদেনের টাকা বদলাবে না, শুধু ধরন সরে যাবে।');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                <button type="submit" class="txn-btn ghost">মুছুন</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include '../../partials/footer.php'; ?>
