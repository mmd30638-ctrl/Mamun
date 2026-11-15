<?php
/**
 * Slip share bar — delegates to accounts_print_share (includes PDF শেয়ার).
 *
 * @var array{target?:string,file?:string,title?:string,text?:string,shop?:string}|null $ame_slip
 */
if (!isset($ame_slip) || !is_array($ame_slip)) {
    return;
}
$GLOBALS['ame_slip'] = $ame_slip;
$lib = __DIR__ . '/../includes/accounts_print_share.php';
if (is_file($lib)) {
    require_once $lib;
    if (function_exists('ame_slip_share_bar_render')) {
        ame_slip_share_bar_render();
    }
}
?>
<style id="pa-slip-visibility-guard">
#ame-slip.pa-slip-visible,
.pa-page #ame-slip {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    max-height: none !important;
    overflow: visible !important;
    height: auto !important;
}
</style>