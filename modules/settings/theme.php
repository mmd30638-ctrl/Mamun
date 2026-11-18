<?php
require_once '../../includes/config.php';
admin_only();
include '../../partials/header.php';
?>
<div class="studio-page" style="max-width:720px;margin:24px auto;padding:16px;">
    <h1><?php echo te('vd_help_title'); ?></h1>
    <?php if (isset($_GET['retired'])): ?>
        <p class="flash-ok"><?php echo te('vd_retired'); ?></p>
    <?php endif; ?>
    <p><?php echo te('vd_help_body'); ?></p>
</div>
<?php include '../../partials/footer.php'; ?>
