<?php
/**
 * Public JS — auto-connect marked payment account until the user changes a select.
 * Independent .env mysqli. No login redirect.
 */
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: public, max-age=15');
header('X-Content-Type-Options: nosniff');

$pm = dirname(__DIR__, 2) . '/includes/pay_mark.php';
$id = 0;
if (is_file($pm)) {
    require_once $pm;
    $id = function_exists('ame_pay_mark_get') ? (int)ame_pay_mark_get() : 0;
}
?>
(function () {
  'use strict';
  var id = <?php echo (int)$id; ?>;
  if (!id) return;
  if (window.__amePayMark808) return;
  window.__amePayMark808 = true;

  function isPaySelect(sel) {
    if (!sel || sel.tagName !== 'SELECT') return false;
    if (sel.getAttribute('data-ame-pay-skip') === '1') return false;
    if (sel.classList.contains('pos-payment-account')) return true;
    var name = sel.getAttribute('name') || '';
    if (name === 'advance_account_id') return true;
    if (name !== 'account_id') return false;
    var form = sel.form || sel.closest('form');
    if (form && String(form.method || 'get').toLowerCase() === 'get') return false;
    return true;
  }

  function fill(sel) {
    if (!isPaySelect(sel)) return;
    if (sel.getAttribute('data-ame-pay-locked') === '1') return;
    var current = String(sel.value || '');
    if (current !== '' && current !== '0') return;
    var opt = sel.querySelector('option[value="' + id + '"]');
    if (!opt) return;
    sel.value = String(id);
  }

  function apply(root) {
    root = root || document;
    var sels = root.querySelectorAll('select');
    for (var i = 0; i < sels.length; i++) fill(sels[i]);
  }

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!isPaySelect(t)) return;
    t.setAttribute('data-ame-pay-locked', '1');
  }, true);

  function boot() {
    apply(document);
    if (!window.__amePayMarkObs && document.body) {
      window.__amePayMarkObs = new MutationObserver(function () { apply(document); });
      window.__amePayMarkObs.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
