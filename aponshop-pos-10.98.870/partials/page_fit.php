<?php
$ame_fit_v = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.847';
?>
<link rel="stylesheet" href="/assets/css/ame-page-fit.css?v=<?php echo htmlspecialchars($ame_fit_v, ENT_QUOTES, 'UTF-8'); ?>">
<script>
window.AME_PAGE_FIT = {
  url: '/modules/settings/actions/page_layout.php',
  csrf: <?php echo json_encode(function_exists('csrf_token') ? csrf_token() : '', JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="/assets/js/ame-page-fit.js?v=<?php echo htmlspecialchars($ame_fit_v, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
