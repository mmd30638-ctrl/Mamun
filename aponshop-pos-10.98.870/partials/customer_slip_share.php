<?php
/**
 * Print / PDF / share bar — must NOT hide $ame_slip['target'] (partner statements stay visible).
 *
 * @var array{target?:string,file?:string,title?:string,text?:string,shop?:string}|null $ame_slip
 */
if (!isset($ame_slip) || !is_array($ame_slip)) {
    return;
}
$target = (string)($ame_slip['target'] ?? '#ame-slip');
$file = (string)($ame_slip['file'] ?? 'statement');
$title = (string)($ame_slip['title'] ?? 'Statement');
?>
<div class="acc-share-bar no-print pa-slip-share" data-slip-target="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" role="toolbar" aria-label="প্রিন্ট ও শেয়ার">
    <button type="button" class="pa-slip-btn pa-slip-print" onclick="window.print();" title="প্রিন্ট">প্রিন্ট</button>
    <button type="button" class="pa-slip-btn pa-slip-pdf" onclick="window.print();" title="PDF (প্রিন্টে Save as PDF)">PDF</button>
    <button type="button" class="pa-slip-btn pa-slip-img" data-pa-capture="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" title="ছবি">ছবি</button>
    <button type="button" class="pa-slip-btn pa-slip-btn-share" data-pa-share="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>" title="শেয়ার">শেয়ার</button>
</div>
<style id="pa-slip-visibility-guard">
/* পুরনো থিমে #ame-slip লুকানো থাকলেও স্টেটমেন্ট দেখা যাবে */
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