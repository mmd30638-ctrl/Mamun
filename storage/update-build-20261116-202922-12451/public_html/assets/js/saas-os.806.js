/**
 * APONSHOP SaaS OS 8.06 — premium overlay on every page.
 */
(function () {
  'use strict';
  if (window.__ameSaasOs806) return;
  window.__ameSaasOs806 = true;
  window.__ameSaasOs804 = true;
  window.__ameSaasOs803 = true;
  window.__ameSaasOs800 = true;

  var proto = Element.prototype;
  if (!proto.__ameNoDataEn) {
    var origSet = proto.setAttribute;
    proto.setAttribute = function (name, value) {
      if (String(name).toLowerCase() === 'data-en') return;
      return origSet.apply(this, arguments);
    };
    proto.__ameNoDataEn = true;
  }

  function ensureCss() {
    if (document.getElementById('ame-saas-os-css')) return;
    var link = document.createElement('link');
    link.id = 'ame-saas-os-css';
    link.rel = 'stylesheet';
    link.href = '/assets/css/saas-os.806.css';
    document.head.appendChild(link);
  }

  function viewport() {
    var meta = document.querySelector('meta[name="viewport"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'viewport');
      document.head.appendChild(meta);
    }
    if (!window.AME_SALE_DESKTOP_SITE) {
      meta.setAttribute('content', 'width=device-width, initial-scale=1, viewport-fit=cover');
    }
    var root = document.documentElement;
    var vv = window.visualViewport;
    var w = Math.round((vv && vv.width) || window.innerWidth || 0);
    var h = Math.round((vv && vv.height) || window.innerHeight || 0);
    root.style.setProperty('--ame-screen-w', w + 'px');
    root.style.setProperty('--ame-vh', (h * 0.01) + 'px');
    if (document.body) {
      document.body.classList.toggle('ame-screen-phone', w <= 760);
      document.body.classList.toggle('ame-screen-tablet', w >= 576 && w < 992);
      document.body.classList.toggle('ame-screen-desktop', w >= 992);
      document.body.classList.toggle('ame-screen-landscape', w > h);
    }
  }

  function stripMix(root) {
    if (!root || !root.querySelectorAll) return;
    var nodes = root.querySelectorAll('[data-en]');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].removeAttribute('data-en');
    }
    var extra = root.querySelectorAll('.ifd-en');
    for (var j = 0; j < extra.length; j++) {
      extra[j].style.display = 'none';
    }
  }

  function isShopHomePath() {
    var p = String(window.location.pathname || '').replace(/\/{2,}/g, '/');
    return p === '/' || p === '/index.php' || p === '/index.html';
  }

  function hideMisplacedWelcome() {
    var p = String(window.location.pathname || '').replace(/\/{2,}/g, '/');
    var pulse = /\/modules\/pulse\/index\.php$/.test(p);
    var shopHome = isShopHomePath();
    if (document.body) {
      document.body.classList.toggle('ame-if-home', pulse);
      document.body.classList.toggle('ame-home-gallery', shopHome);
      if (shopHome) {
        document.body.classList.remove('ame-if-dash', 'ame-if-live');
      }
    }
    if (pulse) return;
    var nodes = document.querySelectorAll('.if-dash-welcome');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].style.setProperty('display', 'none', 'important');
    }
  }

  function markBrand() {
    var nodes = document.querySelectorAll('.hdr-brand-box h1, .sidebar-brand-header .brand-info h4, .login-card h2');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].classList.add('ame-brand-name');
    }
    var loginTag = document.querySelector('.login-card p.text-sm.font-semibold, .login-card > p.text-gray-600');
    if (loginTag) loginTag.classList.add('ame-brand-tag');
    var side = document.querySelector('.sidebar-brand-header .brand-info > small');
    if (side) side.classList.add('ame-brand-tag');
  }

  function ensureBrandCss() {
    if (document.getElementById('ame-brand-type-css')) return;
    var link = document.createElement('link');
    link.id = 'ame-brand-type-css';
    link.rel = 'stylesheet';
    link.href = '/assets/css/brand-type.php?v=8.08';
    document.head.appendChild(link);
  }

  function ensureFreshHome() {
    if (!document.querySelector('link[href*="fresh-home.810.css"]')) {
      var css = document.createElement('link');
      css.id = 'ame-fresh-home-css';
      css.rel = 'stylesheet';
      css.href = '/assets/css/fresh-home.810.css';
      document.head.appendChild(css);
    }
    if (document.querySelector('script[src*="fresh-home.810.js"]')) return;
    var js = document.createElement('script');
    js.id = 'ame-fresh-home-js';
    js.src = '/assets/js/fresh-home.810.js';
    js.defer = true;
    document.head.appendChild(js);
  }

  function paint() {
    if (!document.body) return false;
    ensureCss();
    ensureBrandCss();
    ensureFreshHome();
    viewport();
    markBrand();
    document.body.classList.add('ame-saas-os', 'ame-premium-ui', 'ame-ds-806');
    if (isShopHomePath()) {
      document.body.classList.add('ame-home-gallery');
      document.body.classList.remove('ame-if-dash', 'ame-if-live');
    } else if (!document.body.classList.contains('ame-pos-terminal-page')) {
      document.body.classList.add('ame-if-dash');
    }
    stripMix(document);
    hideMisplacedWelcome();
    return true;
  }

  function boot() {
    paint();
    var n = 0;
    var t = setInterval(function () {
      n += 1;
      paint();
      if (n > 40) clearInterval(t);
    }, 160);
    if (window.MutationObserver) {
      var mo = new MutationObserver(function () { stripMix(document); });
      mo.observe(document.documentElement, {
        subtree: true,
        attributes: true,
        attributeFilter: ['data-en']
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('resize', viewport, { passive: true });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', viewport, { passive: true });
  }
})();
