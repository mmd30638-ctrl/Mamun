<?php
/**
 * Header shortcuts that now live in the sidebar menu.
 */
$ame_sb_admin = $is_admin ?? $is_admin_hdr ?? false;
$ame_sb_lang_return = $ame_language_return ?? '/index.php';
$ame_sb_due = (int)($ame_due_notification_count ?? 0);
$ame_sb_stock = (int)($ame_stock_alert_count ?? 0);
$ame_sb_stock_ok = !empty($ame_stock_alert_can_view);
$ame_sb_en = function_exists('current_lang') && current_lang() === 'en';
$ame_sb_stores = [];
if (isset($db) && $db instanceof mysqli && function_exists('ame_stores_for_user')) {
    $ame_sb_stores = ame_stores_for_user($db);
}
$ame_sb_store_id = function_exists('current_store_id') ? current_store_id() : 0;
?>
<div class="sb-quick">
    <p class="sb-quick-label"><?php echo $ame_sb_en ? 'Shortcuts' : 'শর্টকাট'; ?></p>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <button type="button" class="direct-link" id="amePageFitToggle" aria-pressed="false">
        <?php echo ame_sb_icon('appearance'); ?>
        <span class="ame-fit-label"><?php echo $ame_sb_en ? 'Arrange this page' : 'পেজ সাজান'; ?></span>
    </button>
    <?php endif; ?>
    <button type="button" class="direct-link" onclick="window.ameGoBack ? window.ameGoBack() : window.location.assign('/index.php')">
        <?php echo ame_sb_icon('back'); ?> <?php echo te('go_back'); ?>
    </button>
    <?php if ($ame_sb_admin || (isset($db) && has_permission($db, 'm-pulse'))): ?>
    <a href="/modules/pulse/index.php" class="direct-link">
        <?php echo ame_sb_icon('pulse'); ?> <?php echo te('shop_pulse'); ?>
    </a>
    <?php endif; ?>
    <?php if ($ame_sb_admin || (isset($db) && (has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report')))): ?>
    <a href="/modules/reports/live_work.php" class="direct-link">
        <?php echo ame_sb_icon('reports'); ?> লাইভ ড্যাশবোর্ড
    </a>
    <?php endif; ?>
    <button type="button" class="direct-link" onclick="typeof toggleFullScreen === 'function' && toggleFullScreen()">
        <?php echo ame_sb_icon('fullscreen'); ?> <?php echo te('fullscreen'); ?>
    </button>
    <?php if ($ame_sb_admin || (isset($db) && (has_permission($db, 'm-transactions') || has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report')))): ?>
    <a href="/modules/accounts/daily_audit.php" class="direct-link">
        <?php echo ame_sb_icon('transactions'); ?> <?php echo te('nav_daily_audit'); ?>
    </a>
    <?php endif; ?>
    <?php if ($ame_sb_admin || (isset($db) && has_permission($db, 'm-transactions'))): ?>
    <a href="/modules/accounts/add_transaction.php" class="direct-link">
        <?php echo ame_sb_icon('transactions'); ?> <?php echo te('nav_txn_general'); ?>
    </a>
    <a href="/modules/accounts/transfer.php" class="direct-link">
        <?php echo ame_sb_icon('transactions'); ?> <?php echo te('nav_txn_transfer'); ?>
    </a>
    <?php endif; ?>
    <?php if ($ame_sb_stock_ok): ?>
    <a href="/modules/inventory/restock_invoice.php" class="direct-link">
        <?php echo ame_sb_icon('inventory'); ?> <?php echo te('stock_alert'); ?>
        <?php if ($ame_sb_stock > 0): ?>
        <span class="sb-quick-badge sb-quick-badge-stock"><?php echo $ame_sb_stock > 99 ? '99+' : $ame_sb_stock; ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
    <a href="/actions/switch_language.php?lang=<?php echo current_lang() === 'bn' ? 'en' : 'bn'; ?>&amp;return=<?php echo rawurlencode($ame_sb_lang_return); ?>" class="direct-link">
        <?php echo ame_sb_icon('lang'); ?> <?php echo te('lang_switch'); ?>
    </a>
    <button type="button" class="direct-link" onclick="typeof ameOpenCalculator === 'function' && ameOpenCalculator()">
        <?php echo ame_sb_icon('calc'); ?> <?php echo te('calculator'); ?>
    </button>
    <button type="button" id="ame-net-icon" class="direct-link" title="<?php echo te('connection_status'); ?>">
        <?php echo ame_sb_icon('net'); ?> <?php echo te('connection_status'); ?>
        <span id="ame-net-dot" class="sb-quick-dot"></span>
    </button>
    <?php if (count($ame_sb_stores) > 1): ?>
    <form class="sb-store-switch" method="post" action="/actions/switch_store.php">
        <?php echo csrf_field(); ?>
        <label><?php echo ame_sb_icon('stores'); ?> <?php echo $ame_sb_en ? 'Store' : 'স্টোর'; ?>
            <select name="store_id" onchange="this.form.submit()">
                <?php foreach ($ame_sb_stores as $ame_sb_store): ?>
                    <option value="<?php echo (int)$ame_sb_store['id']; ?>" <?php echo (int)$ame_sb_store['id'] === (int)$ame_sb_store_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$ame_sb_store['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php endif; ?>
    <?php if ($ame_sb_admin): ?>
    <a href="/modules/settings/stores.php" class="direct-link">
        <?php echo ame_sb_icon('stores'); ?> <?php echo $ame_sb_en ? 'Stores' : 'স্টোর'; ?>
    </a>
    <?php endif; ?>
</div>
