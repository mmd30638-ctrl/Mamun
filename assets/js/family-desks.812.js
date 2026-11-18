/**
 * Stamp a unique desk name on every Family page.
 */
(function () {
  'use strict';
  function stamp() {
    var app = document.querySelector('.fam-app');
    if (!app) return;
    var file = (location.pathname.split('/').pop() || 'index.php').replace(/\.php$/i, '') || 'index';
    var map = { index: 'home', member: 'members', medicine_buy: 'medicines' };
    app.setAttribute('data-desk', map[file] || file);
    document.body.classList.add('fam-desk-' + (map[file] || file));
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', stamp);
  } else {
    stamp();
  }
})();
