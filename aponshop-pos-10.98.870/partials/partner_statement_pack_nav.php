<?php
/** @var string $pa_pack_active profit|outflow|summary|ledger */
/** @var string $bounds_ym */
/** @var int $pa_partner_id */
/** @var array $dash */
$pa_pack_active = $pa_pack_active ?? 'ledger';
$bounds_ym = $bounds_ym ?? date('Y-m');
$pa_partner_id = (int)($pa_partner_id ?? 0);
$tabs = [
    'profit' => ['লাভ বিবরণী', 'partner_accounting_profit.php'],
    'outflow' => ['খরচ ও মাইনাস', 'partner_accounting_outflow.php'],
    'summary' => ['মাসিক সারাংশ', 'partner_accounting_summary.php'],
    'ledger' => ['পূর্ণ লেজার', 'partner_accounting_statement.php'],
];
?>
<nav class="pa-pack-nav no-print" aria-label="পার্টনার স্টেটমেন্ট প্যাকেজ">
    <?php foreach ($tabs as $key => [$label, $file]): ?>
        <?php
        $href = pa_stmt_nav_href($file, $bounds_ym, $pa_partner_id);
        $on = $key === $pa_pack_active;
        ?>
        <a class="pa-pack-tab pa-live-link<?php echo $on ? ' is-on' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label); ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($pa_partner_id < 1 && !empty($dash['active'])): ?>
    <section class="pa-panel no-print pa-pack-pick">
        <p><strong>পার্টনার বাছুন</strong> — প্রতিটি রিপোর্ট একজনের জন্য; নিচের তালিকা থেকে নামে ক্লিক করুন।</p>
        <div class="pa-pack-pick-grid">
            <?php foreach ($dash['active'] as $a):
                $pid = (int)($a['partner']['id'] ?? 0);
                $name = (string)($a['partner']['name'] ?? '');
                $href = pa_stmt_nav_href(basename((string)($_SERVER['SCRIPT_NAME'] ?? 'partner_accounting_statement.php')), $bounds_ym, $pid);
                ?>
                <a class="pa-pack-pick-card pa-live-link" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars($name); ?></span>
                    <small>শেয়ার <?php echo number_format((float)($a['share_pct'] ?? 0), 2); ?>%</small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>