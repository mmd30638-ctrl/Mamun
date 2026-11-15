<?php
/**
 * 10.98.348 — ক্রয় ডেস্ক ট্যাগ। JS PHP দিয়ে যায় (no-store)। আগের ৩৪০–৩৪৫ লিফট খুলে।
 */

if (!function_exists('ame_purchase_overlay_ver')) {
    function ame_purchase_overlay_ver(): string
    {
        static $v = null;
        if (is_string($v)) {
            return $v;
        }
        $f = dirname(__DIR__) . '/VERSION.txt';
        $v = is_file($f) ? trim((string) file_get_contents($f)) : '10.98.351';
        if ($v === '') {
            $v = '10.98.351';
        }
        return $v;
    }
}

if (!function_exists('ame_purchase_desk_tags')) {
    function ame_purchase_desk_tags(): string
    {
        $ver = htmlspecialchars(ame_purchase_overlay_ver(), ENT_QUOTES, 'UTF-8');
        return <<<HTML
<link rel="stylesheet" href="/assets/css/purchase-home.css?v={$ver}">
<style id="amePurchaseDeskCss">
html body.ame-purchase-desk,html body.ame-purchase-add-page,html body.ame-purchase-memo-page{background:#F7F1E4!important;color:#3D2A12!important}
.ame-purchase-src-hidden{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}
.ame-purchase-option-box{position:sticky;top:8px;z-index:10040;margin:0 0 14px;width:100%;max-width:100%;box-sizing:border-box;background:#fff;color:#111827;border:1px solid #d7dee8;border-radius:16px;box-shadow:0 10px 28px rgba(15,23,42,.08);overflow:hidden}
body.ame-purchase-editor-page .ame-purchase-option-box,body.ame-purchase-editor-page .ame-purchase-home-fab,body.ame-purchase-editor-page .ame-purchase-desktop-fab{display:none!important}
body.ame-purchase-terminal-page #amePurchaseOptionBox,body.ame-purchase-terminal-page #amePurchaseTopSubmit{display:none!important}
.ame-opt-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;background:#EFE6D4;color:#3D2A12;border-bottom:1px solid #D4C4A8}
.ame-opt-head h2{margin:0;font-size:15px;font-weight:800}
.ame-opt-pc,.ame-purchase-desktop-fab{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 10px;border:1px solid #D4C4A8;border-radius:999px;background:#EFE6D4;color:#3D2A12;font-weight:800;font-size:12px;cursor:pointer}
html.ame-phone-desktop .ame-opt-pc,html.ame-phone-desktop .ame-purchase-desktop-fab,body.ame-purchase-desktop .ame-purchase-desktop-fab{background:#04933a}
.ame-opt-grid{display:grid;grid-template-columns:1fr;gap:8px;padding:12px;background:#f4f7fb}
.ame-opt-submit-only{min-height:48px!important;width:100%;font-size:16px!important;background:#04933a!important;color:#fff!important;border:0!important;pointer-events:auto!important;position:relative;z-index:2}
body.ame-purchase-editor-page .pd-bar{z-index:90!important;pointer-events:auto!important}
body.ame-purchase-editor-page .pd-submit{pointer-events:auto!important}
.ame-purchase-money-supplier{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:8px 10px;padding:10px 12px;border-radius:10px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-weight:800;font-size:15px}
.ame-purchase-money-supplier strong{color:#047857;font-size:18px}
.ame-opt-btn{min-height:44px;padding:8px 10px;border-radius:12px;border:1px solid #d7dee8;background:#fff;color:#111827!important;font-weight:800;font-size:14px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.ame-opt-btn-complete{background:#04933a!important;color:#fff!important;border:0}
.ame-opt-editor-cta{display:grid;gap:10px;padding:14px;background:#fff}
.ame-opt-editor-cta p{margin:0;font-size:14px;font-weight:700;color:#334155;line-height:1.5}
#amePurchaseSubmitTop,.ame-purchase-submit-top{background:#fff!important;color:#111827!important;border:1px solid #d7dee8;border-radius:16px;padding:12px;display:grid!important;grid-template-columns:1fr 1fr;gap:8px}
.ame-purchase-home-fab,.ame-purchase-desktop-fab{position:fixed;top:calc(10px + env(safe-area-inset-top,0px));z-index:1000050;display:inline-flex;align-items:center;gap:6px;min-height:42px;padding:0 12px 0 10px;border-radius:999px;font-weight:800;font-size:13px;color:#fff!important;text-decoration:none!important;border:0;cursor:pointer}
.ame-purchase-home-fab{left:10px;background:linear-gradient(145deg,#38bdf8,#0369a1)}
.ame-purchase-desktop-fab{right:10px;background:#EFE6D4;color:#3D2A12!important;border:1px solid #D4C4A8;box-shadow:none}
@media print{.ame-purchase-option-box,.ame-purchase-home-fab,.ame-purchase-desktop-fab,.ame-purchase-submit-top{display:none!important}}
</style>
<script>
(function(){
  function unwind(){
    var bar=document.getElementById('amePurchaseSubmitTop');
    if(!bar||bar.classList.contains('ame-purchase-option-box'))return;
    var host=bar.parentNode;
    if(!host)return;
    Array.prototype.slice.call(bar.childNodes).forEach(function(n){
      if(n.nodeType===1&&(n.matches&&n.matches('button,input,a,label')||n.querySelector&&n.querySelector('button,input'))){
        host.insertBefore(n,bar);
      }
    });
    bar.remove();
  }
  function tick(){ try{ unwind(); }catch(e){} }
  [0,300,800,1300,2000,3500].forEach(function(t){ setTimeout(tick,t); });
  document.addEventListener('DOMContentLoaded',tick);
  window.addEventListener('load',tick);
  if(document.body){
    new MutationObserver(tick).observe(document.body,{childList:true,subtree:true});
  } else {
    document.addEventListener('DOMContentLoaded',function(){
      if(document.body) new MutationObserver(tick).observe(document.body,{childList:true,subtree:true});
    });
  }
})();
</script>
<script src="/assets/js/ame-pdesk-349.php?v={$ver}" defer></script>

HTML;
    }
}
