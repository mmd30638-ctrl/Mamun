
/* ame device 10.98.92 — phone never half when "desktop site" */
(function () {
  function isPhone() {
    var ua = navigator.userAgent || '';
    try { if (navigator.userAgentData && navigator.userAgentData.mobile) return true; } catch (e) {}
    if (/Android.+Mobile|iPhone|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua)) return true;
    if (/Android/i.test(ua) && !/Tablet|iPad/i.test(ua) && Math.min(screen.width||0, screen.height||0) <= 550) return true;
    try {
      if (window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches
          && Math.min(screen.width||0, screen.height||0) <= 550) return true;
    } catch (e2) {}
    return false;
  }
  function isTablet() {
    var ua = navigator.userAgent || '';
    if (/iPad|Android(?!.*Mobile)|Tablet|Silk/i.test(ua)) return true;
    try {
      if (window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches) {
        var s = Math.min(screen.width||0, screen.height||0);
        if (s > 550 && s <= 900) return true;
      }
    } catch (e) {}
    return false;
  }
  function mode() {
    if (isPhone()) return 'phone';
    if (isTablet()) return 'tablet';
    var w = window.innerWidth || document.documentElement.clientWidth || 0;
    if (w < 768) return 'phone';
    if (w < 1100) return 'tablet';
    return 'pc';
  }
  function applyDevice() {
    var m = mode();
    var phone = (m === 'phone') || isPhone();
    var root = document.documentElement;
    root.classList.remove('ame-device-phone', 'ame-device-tablet', 'ame-device-pc', 'ame-is-phone', 'ame-force-phone');
    root.classList.add('ame-device-' + (phone ? 'phone' : m));
    root.setAttribute('data-ame-device', phone ? 'phone' : m);
    if (phone) root.classList.add('ame-is-phone', 'ame-force-phone');
    if (document.body) {
      document.body.classList.remove('ame-device-phone', 'ame-device-tablet', 'ame-device-pc', 'ame-force-phone');
      document.body.classList.add('ame-device-' + (phone ? 'phone' : m));
      document.body.setAttribute('data-ame-device', phone ? 'phone' : m);
      if (phone) document.body.classList.add('ame-force-phone');
    }
  }
  function applyCartEmpty() {
    if (!document.body) return;
    var wrap = document.querySelector('.ame-pos-table-wrap');
    var inputs = document.querySelectorAll('#cartBody input.ame-pos-line-input, .ame-pos-table-wrap tbody input.ame-pos-line-input');
    var hasItems = inputs.length > 0;
    if (!hasItems) {
      var rows = document.querySelectorAll('#cartBody tr, .ame-pos-table-wrap tbody tr');
      for (var i = 0; i < rows.length; i++) {
        if (rows[i].querySelector('.pc-empty-cart, .pos-draft-empty')) continue;
        if ((rows[i].textContent || '').indexOf('উপরে সার্চ') !== -1) continue;
        if (rows[i].querySelector('input')) { hasItems = true; break; }
        if (rows[i].cells && rows[i].cells.length > 1) { hasItems = true; break; }
      }
    }
    document.body.classList.toggle('is-cart-empty', !hasItems);
    if (wrap) wrap.classList.toggle('is-empty', !hasItems);
  }
  window.amePcApplyCartEmpty = applyCartEmpty;
  function boot() { applyDevice(); applyCartEmpty(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.addEventListener('resize', applyDevice);
  window.addEventListener('orientationchange', function () { setTimeout(applyDevice, 50); });
  var mo = window.MutationObserver ? new MutationObserver(applyCartEmpty) : null;
  function watch() {
    var t = document.querySelector('#cartBody, .ame-pos-table-wrap tbody');
    if (mo && t) mo.observe(t, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', watch);
  else watch();
})();

/* ame-purchase-pro 10.98.92 — sticky dock sync for purchase add.php only */
(function () {
  if (!document.body || !document.body.classList.contains('ame-purchase-pro')) {
    return;
  }

  function $(id) {
    return document.getElementById(id);
  }

  function ensureDock() {
    var page = document.querySelector('.ame-pos-page.ame-pur-pro');
    if (!page) {
      return null;
    }
    var dock = page.querySelector('.ame-pur-sticky-dock');
    if (dock) {
      return dock;
    }
    dock = document.createElement('div');
    dock.className = 'ame-pur-sticky-dock';
    dock.setAttribute('aria-label', 'ক্রয় সারাংশ');
    dock.innerHTML =
      '<div class="ame-pur-dock-total" id="amePurDockTotal">৳0.00</div>' +
      '<button type="button" class="ame-pur-dock-go" id="amePurDockGo">নিচে যান</button>';
    page.appendChild(dock);
    var go = dock.querySelector('#amePurDockGo');
    if (go) {
      go.addEventListener('click', function () {
        var pay = $('amePosPay');
        var btn = $('amePcCompleteBtn');
        if (pay) {
          pay.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        if (btn) {
          setTimeout(function () {
            try {
              btn.focus();
            } catch (e) {}
          }, 280);
        }
      });
    }
    return dock;
  }

  function syncDock() {
    var dock = ensureDock();
    if (!dock) {
      return;
    }
    var narrow = window.matchMedia('(max-width: 900px)').matches;
    var pay = $('amePosPay');
    var payVisible = false;
    if (pay) {
      var r = pay.getBoundingClientRect();
      payVisible = r.top < window.innerHeight * 0.72 && r.bottom > 40;
    }
    dock.classList.toggle('is-on', narrow && !payVisible);

    var src =
      $('amePcCartSumValue') ||
      $('grandTotalDisplay') ||
      $('invoiceTotalDisplay');
    var label = $('amePurDockTotal');
    if (src && label) {
      var t = (src.textContent || src.value || '৳0.00').trim();
      label.textContent = t.indexOf('৳') >= 0 ? t : '৳' + t;
    }

    var modeEl = $('amePcMode');
    var go = $('amePurDockGo');
    if (go && modeEl) {
      go.textContent =
        modeEl.value === 'draft' ? 'ড্রাফট সেভ' : 'পেমেন্ট / সম্পন্ন';
    }
  }

  var mo = null;
  function watch() {
    syncDock();
    var targets = ['amePcCartSumValue', 'grandTotalDisplay', 'invoiceTotalDisplay', 'amePcMode'];
    if (window.MutationObserver && !mo) {
      mo = new MutationObserver(syncDock);
      targets.forEach(function (id) {
        var el = $(id);
        if (el) {
          mo.observe(el, {
            childList: true,
            characterData: true,
            subtree: true,
            attributes: true,
          });
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watch);
  } else {
    watch();
  }
  window.addEventListener('scroll', syncDock, { passive: true });
  window.addEventListener('resize', syncDock);
  setInterval(syncDock, 1200);
})();
