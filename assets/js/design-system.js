/**
 * Shared shell behaviour. Does not change form posts, totals, or permissions.
 */
(function () {
  'use strict';

  var crumb = document.querySelector('[data-ds-crumb]');
  var nav = document.querySelector('.ds-crumb');
  var path = (location.pathname || '').replace(/\/+$/, '') || '/';
  if (nav && (path === '/' || path === '/index.php')) {
    nav.hidden = true;
  } else if (crumb) {
    var head = document.querySelector('#main-content h1, #main-content h2');
    var text = head ? (head.textContent || '').replace(/\s+/g, ' ').trim() : '';
    if (text) crumb.textContent = text.slice(0, 90);
    else if (nav) nav.hidden = true;
  }

  var skip = document.body && (
    document.body.classList.contains('ame-pos-terminal-page') ||
    document.body.classList.contains('ame-purchase-terminal-page')
  );
  if (skip) return;

  document.querySelectorAll('#main-content table').forEach(function (table) {
    if (table.closest('.ds-table-scroll, .ame-cf-card')) return;
    if (table.getAttribute('role') === 'presentation') return;
    var parent = table.parentNode;
    if (!parent) return;
    var wrap = document.createElement('div');
    wrap.className = 'ds-table-scroll';
    parent.insertBefore(wrap, table);
    wrap.appendChild(table);
  });
})();
