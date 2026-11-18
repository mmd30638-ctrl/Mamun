<?php
if (!function_exists('ame_smart_dashboard')) {
    return;
}
try {
    $ame_smart_home = ame_smart_dashboard($db);
} catch (Throwable $e) {
    error_log('Smart home strip failed: ' . $e->getMessage());
    return;
}
$sp = $ame_smart_home['pulse'];
$spr = $ame_smart_home['profit'];
?>
<link rel="stylesheet" href="/assets/css/smart.css?v=<?php echo htmlspecialchars((string)($sys_version ?? '3.7'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="home-smart">
    <div class="k"><?php echo te('smart_page_kicker'); ?></div>
    <p style="margin:6px 0 0;font-weight:700;color:#0b1e4d;"><?php echo htmlspecialchars($ame_smart_home['script'], ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="home-smart-grid">
        <a href="/modules/smart/index.php"><span>আজকের বিক্রি</span><b><?php echo ame_smart_money((float)$sp['today_sales']); ?></b></a>
        <a href="/modules/smart/profit.php"><span>আজকের লাভ</span><b><?php echo ame_smart_money((float)$spr['real']); ?></b></a>
        <a href="/modules/customers/due-reminder.php"><span>মোট বকেয়া</span><b><?php echo ame_smart_money((float)$sp['due_sum']); ?></b></a>
        <a href="/modules/smart/purchase.php"><span>ক্রয় সাজেশন</span><b><?php echo count($ame_smart_home['week']); ?>টা</b></a>
    </div>
    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
        <a class="smt-btn" href="/modules/smart/index.php">স্মার্ট হাব</a>
        <a class="smt-ghost" href="/modules/smart/assistant.php">বাংলায় জিজ্ঞেস</a>
        <a class="smt-ghost" href="/modules/sales/pos.php"><?php echo te('smart_open_sales'); ?></a>
    </div>
</div>
