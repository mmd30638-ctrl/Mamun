/**
 * APONSHOP Origin Seal — tap to open home instantly.
 * Does not wipe PWA / Cache Storage. Does not wait. Does not cache-bust.
 * SPEED.txt: static cache stays; sale/stock/ledger/due HTML is never stored.
 */
(function () {
  'use strict';
  if (window.__ameFreshHome810) return;
  window.__ameFreshHome810 = true;

  var SEAL =
    '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
    '<circle cx="12" cy="12" r="9.1" stroke="currentColor" stroke-width="1.7"/>' +
    '<path d="M12 5.1c2.15 1.35 3.35 3.25 3.35 5.35S14.15 14.45 12 15.8C9.85 14.45 8.65 12.55 8.65 10.45S9.85 6.45 12 5.1Z" fill="currentColor" opacity=".16"/>' +
    '<path d="M7.15 13.15 12 8.7l4.85 4.45" stroke="currentColor" stroke-width="1.95" stroke-linecap="round" stroke-linejoin="round"/>' +
    '<path d="M9.05 12.55V17.1h5.9v-4.55" stroke="currentColor" stroke-width="1.95" stroke-linejoin="round"/>' +
    '<path d="M12 6.05V4.2" stroke="#B45309" stroke-width="1.8" stroke-linecap="round"/>' +
    '<circle cx="12" cy="3.35" r="1.05" fill="#B45309"/>' +
    '</svg>';

  var HOME = '/index.php';

  function ensureCss() {
    if (document.getElementById('ame-fresh-home-css')) return;
    var link = document.createElement('link');
    link.id = 'ame-fresh-home-css';
    link.rel = 'stylesheet';
    link.href = '/assets/css/fresh-home.810.css';
    document.head.appendChild(link);
  }

  function isHomeNow() {
    var path = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
    return path === '/' || path === '/index.php' || path === '/m' || path === '/index.html';
  }

  function goHome() {
    if (typeof window.AME_NAV_WARM === 'function') {
      window.AME_NAV_WARM(HOME);
    }
    if (isHomeNow() && !window.location.search) {
      try {
        window.scrollTo(0, 0);
      } catch (e) {}
      try {
        window.location.reload();
      } catch (e2) {
        window.location.href = HOME;
      }
      return;
    }
    try {
      window.location.replace(HOME);
    } catch (e) {
      window.location.href = HOME;
    }
  }

  window.ameFreshHome = function (ev) {
    if (ev) {
      ev.preventDefault();
      ev.stopPropagation();
    }
    var btn = document.querySelector('.hdr-i-fresh, .hdr-i-home');
    if (btn) btn.classList.add('is-spinning');
    goHome();
  };

  function paintSeal(btn) {
    if (!btn || btn.getAttribute('data-ame-fresh') === '1') return;
    btn.setAttribute('data-ame-fresh', '1');
    btn.classList.add('hdr-i-fresh');
    btn.setAttribute('title', 'ফ্রেশ হোম — তাৎক্ষণিক খুলবে');
    btn.setAttribute('aria-label', 'ফ্রেশ হোম');
    btn.removeAttribute('onclick');
    btn.onclick = null;
    btn.innerHTML = SEAL;
    btn.addEventListener('click', window.ameFreshHome);
    btn.addEventListener('pointerdown', function () {
      if (typeof window.AME_NAV_WARM === 'function') {
        window.AME_NAV_WARM(HOME);
      }
    }, { passive: true });
  }

  function mount() {
    ensureCss();
    var home = document.querySelector('.master-header-fixed .hdr-i-home');
    if (home) paintSeal(home);
  }

  document.addEventListener(
    'click',
    function (ev) {
      var t = ev.target && ev.target.closest
        ? ev.target.closest('.hdr-i-home, .hdr-i-fresh')
        : null;
      if (!t) return;
      window.ameFreshHome(ev);
    },
    true
  );

  document.addEventListener(
    'pointerdown',
    function (ev) {
      var t = ev.target && ev.target.closest
        ? ev.target.closest('.hdr-i-home, .hdr-i-fresh')
        : null;
      if (!t) return;
      if (typeof window.AME_NAV_WARM === 'function') {
        window.AME_NAV_WARM(HOME);
      }
    },
    true
  );

  function boot() {
    mount();
    var n = 0;
    var t = setInterval(function () {
      n += 1;
      mount();
      if (n > 30) clearInterval(t);
    }, 180);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
