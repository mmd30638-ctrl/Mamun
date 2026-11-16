<?php
/**
 * কনফার্ম / প্রিন্ট পাতায় শেয়ার, ডাউনলোড, দেখুন, হোম।
 * ছবির নিচের চারটি অপশনের মতো — সাদা ঘর, লাল বৃত্ত।
 */
if (!empty($GLOBALS['ame_confirm_actions_sent'])) {
    return;
}
$GLOBALS['ame_confirm_actions_sent'] = true;
?>
<style id="ame-confirm-actions-css">
.ame-confirm-acts { margin: 14px auto 18px; max-width: 440px; padding: 8px 12px 4px; background: #fff; }
.ame-confirm-acts .ame-c-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.ame-confirm-acts .ame-c-row.has-newsale { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.ame-confirm-acts .ame-c-row.has-newsale .ame-c-ico { width: 62px; height: 62px; }
.ame-confirm-acts .ame-c-row.has-newsale a.ame-c-hit { font-size: 12px; line-height: 1.25; text-align: center; }
@media (max-width: 520px) {
  .ame-confirm-acts .ame-c-row.has-newsale { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.ame-confirm-acts button, .ame-confirm-acts a.ame-c-hit {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  background: #fff !important; color: #e11d48 !important; -webkit-text-fill-color: #e11d48 !important;
  border: 0 !important; font: inherit; font-weight: 800; font-size: 14px; cursor: pointer; text-decoration: none;
}
.ame-confirm-acts .ame-c-ico {
  width: 74px; height: 74px; border-radius: 50%; border: 2px solid #fb7185; background: #fff;
  display: flex; align-items: center; justify-content: center;
}
.ame-confirm-acts .ame-c-ico svg { width: 30px; height: 30px; stroke: #e11d48; fill: none; stroke-width: 2; }
.ame-confirm-acts .ame-c-home {
  display: block; margin-top: 16px; padding: 14px 16px; text-align: center; text-decoration: none;
  color: #e11d48 !important; -webkit-text-fill-color: #e11d48 !important; background: #fff !important;
  font-weight: 800; font-size: 18px; border: 2px solid #fb7185 !important; border-radius: 12px;
}
a.ame-new-sale {
  display: inline-flex !important; align-items: center !important; justify-content: center !important;
  flex: 1 1 100% !important; min-height: 48px !important; margin: 4px 0 8px !important; padding: 10px 16px !important;
  border-radius: 12px !important; border: 2px solid #065f46 !important; background: #047857 !important;
  color: #ffffff !important; -webkit-text-fill-color: #ffffff !important; font-weight: 800 !important;
  font-size: 16px !important; text-decoration: none !important; text-align: center !important;
}
@media print { .ame-confirm-acts, a.ame-new-sale { display: none !important; } }
</style>
<script>
(function () {
  if (document.querySelector('.pur-ok-acts') || document.getElementById('ameConfirmActs')) return;
  if (document.getElementById('ameSlipBar') || document.querySelector('.inv-action-bar')) {
    ensureNewSale();
    return;
  }
  function svgShare() {
    return '<svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="2.2"/><circle cx="6" cy="12" r="2.2"/><circle cx="18" cy="19" r="2.2"/><path d="M8 11.2 16 6.4M8 12.8l8 4.6"/></svg>';
  }
  function svgDown() {
    return '<svg viewBox="0 0 24 24"><path d="M12 4v11"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/></svg>';
  }
  function svgView() {
    return '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.6"/></svg>';
  }
  function svgNewSale() {
    return '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>';
  }
  function isSaleInvoice() {
    return /\/modules\/sales\/invoice\.php$/i.test(location.pathname || '');
  }
  function ensureNewSale() {
    if (!isSaleInvoice() || document.querySelector('[data-ame-new-sale]')) return;
    var bar = document.querySelector('.inv-action-bar');
    if (!bar) return;
    var link = document.createElement('a');
    link.className = 'inv-btn ame-new-sale';
    link.href = '/modules/sales/pos.php';
    link.setAttribute('data-ame-new-sale', '1');
    link.textContent = 'নতুন বিক্রয় শুরু করুন';
    bar.insertBefore(link, bar.firstChild);
  }
  function bar() {
    var sale = isSaleInvoice();
    var newsale = sale
      ? '<a class="ame-c-hit" href="/modules/sales/pos.php"><span class="ame-c-ico">' + svgNewSale() + '</span>নতুন বিক্রয় শুরু করুন</a>'
      : '';
    return '<div class="ame-confirm-acts no-print" id="ameConfirmActs">'
      + '<div class="ame-c-row' + (sale ? ' has-newsale' : '') + '">'
      + '<button type="button" data-ame-c="share"><span class="ame-c-ico">' + svgShare() + '</span>শেয়ার</button>'
      + '<button type="button" data-ame-c="down"><span class="ame-c-ico">' + svgDown() + '</span>ডাউনলোড</button>'
      + '<button type="button" data-ame-c="view"><span class="ame-c-ico">' + svgView() + '</span>দেখুন</button>'
      + newsale
      + '</div>'
      + '<a class="ame-c-home" href="/index.php">হোম পেজে যান</a>'
      + '</div>';
  }
  function host() {
    var bars = document.querySelector('.inv-action-bar, .rv-actions, .pay-actions, .tm-actions, .acc-share-bar');
    if (bars) return bars;
    var nodes = document.querySelectorAll('button, a');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (!el || (el.closest && el.closest('.master-header-fixed, .custom-sidebar, #ameConfirmActs, .pur-ok-acts, .ame-confirm-acts'))) continue;
      var blob = ((el.getAttribute('onclick') || '') + ' ' + (el.getAttribute('href') || '') + ' ' + (el.className || '') + ' ' + (el.textContent || ''));
      if (/window\.print|প্রিন্ট|export=pdf|export=print|inv-btn-print|print-btn|btn-print/i.test(blob)) return el;
    }
    return null;
  }
  var anchor = host();
  if (!anchor) return;
  var box = anchor.closest('.inv-action-bar, .rv-actions, .pay-actions, .tm-actions, .acc-share-bar');
  if (box && box.classList.contains('rv-actions')) {
    box.insertAdjacentHTML('afterend', bar());
    box.querySelectorAll('button, a').forEach(function (el) {
      var blob = ((el.getAttribute('onclick') || '') + ' ' + (el.id || '') + ' ' + (el.textContent || ''));
      if (/print|প্রিন্ট|শেয়ার|share|pdf|ডাউনলোড/i.test(blob)) el.style.display = 'none';
    });
  } else if (box) {
    box.insertAdjacentHTML('afterend', bar());
    box.style.display = 'none';
  } else {
    var row = (anchor.closest && anchor.closest('p, .no-print, .wd-nav')) || anchor;
    row.insertAdjacentHTML('beforebegin', bar());
    if (anchor.matches && anchor.matches('button, a')) anchor.style.display = 'none';
  }
  var root = document.getElementById('ameConfirmActs');
  if (!root) return;
  function outsideBar(node) {
    return node && (!node.closest || !node.closest('#ameConfirmActs'));
  }
  function firstOutside(selector) {
    var list = document.querySelectorAll(selector);
    var i;
    for (i = 0; i < list.length; i++) {
      if (outsideBar(list[i])) return list[i];
    }
    return null;
  }
  function oldMemoButton(kind) {
    var sel = kind === 'down'
      ? '[data-inv-act="download"], [data-pay-act="download"], [data-inv-act="download-image"], [data-pay-act="download-image"], #purDl, .tm-dl'
      : '[data-inv-act="share"], [data-pay-act="share"], #shareBtn, #purShare, #purImg, .tm-sh';
    var hit = firstOutside(sel);
    if (hit) return hit;
    var nodes = document.querySelectorAll('button, a');
    var needle = kind === 'down' ? /ameMemoCapture\(\s*['"]download/ : /ameMemoCapture\(\s*['"]share/;
    var j;
    for (j = 0; j < nodes.length; j++) {
      if (!outsideBar(nodes[j])) continue;
      if (needle.test(nodes[j].getAttribute('onclick') || '')) return nodes[j];
    }
    return null;
  }
  function memoTargetId() {
    if (typeof window.ameMemoResolveTarget === 'function') {
      return window.ameMemoResolveTarget();
    }
    var node = document.querySelector('[data-ame-capture="clone"]')
      || document.getElementById('memo-container')
      || document.getElementById('voucherCapture')
      || document.getElementById('ame-slip')
      || document.querySelector('.ame-print-root, .memo-wrapper, .invoice-box, .tm-card')
      || document.getElementById('main-content');
    if (!node) return '';
    if (!node.id) node.id = 'ameMemoShareTarget';
    return node.id;
  }
  function memoFileBase() {
    var meta = window.AME_MEMO || {};
    if (meta.file) return String(meta.file);
    var title = String(document.title || 'invoice').replace(/[^\w\-]+/g, '-').replace(/^-+|-+$/g, '');
    return title || 'aponshop-memo';
  }
  root.addEventListener('click', function (ev) {
    var hit = ev.target.closest('[data-ame-c]');
    if (!hit) return;
    var act = hit.getAttribute('data-ame-c');
    if (act === 'share') {
      var oldShare = oldMemoButton('share');
      if (oldShare) { oldShare.click(); return; }
      var shareId = memoTargetId();
      if (shareId && typeof window.ameMemoCapture === 'function') {
        window.ameMemoCapture('share', memoFileBase(), shareId);
        return;
      }
      window.alert('মেমো PDF তৈরি হয়নি — আবার চেষ্টা করুন।');
      return;
    }
    if (act === 'down') {
      var oldDown = oldMemoButton('down');
      if (oldDown) { oldDown.click(); return; }
      var downId = memoTargetId();
      if (downId && typeof window.ameMemoCapture === 'function') {
        window.ameMemoCapture('download', memoFileBase(), downId);
        return;
      }
      window.alert('মেমো PDF তৈরি হয়নি — আবার চেষ্টা করুন।');
      return;
    }
    var memo = document.getElementById('memo-container') || document.querySelector('.memo-wrapper, .rv-sheet, article');
    if (memo && memo.scrollIntoView) memo.scrollIntoView({ behavior: 'smooth', block: 'start' });
    else window.print();
  });
})();
</script>
