
/* ame device 10.98.92 — phone never half when "desktop site" */
(function () {
  function isPhone() {
    if (window.AME_SALE_DESKTOP_SITE) return false;
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
    if (window.AME_SALE_DESKTOP_SITE && typeof window.ameApplySaleDesktopViewport === 'function') {
      window.ameApplySaleDesktopViewport();
    }
    var m = window.AME_SALE_DESKTOP_SITE ? 'pc' : mode();
    var phone = window.AME_SALE_DESKTOP_SITE ? false : ((m === 'phone') || isPhone());
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
    var rows = document.querySelectorAll('#cartBody tr, .ame-pos-table-wrap tbody tr');
    var hasItems = false;
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].querySelector('.pc-empty-cart, .pos-draft-empty')) continue;
      if ((rows[i].textContent || '').indexOf('উপরে সার্চ') !== -1) continue;
      if (rows[i].cells && rows[i].cells.length > 1) { hasItems = true; break; }
    }
    document.body.classList.toggle('is-cart-empty', !hasItems);
    if (wrap) wrap.classList.toggle('is-empty', !hasItems);
  }
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

/* ame-sales-pro 10.98.92 — sticky dock sync for sales pos.php only */
(function () {
  if (!document.body || !document.body.classList.contains('ame-sales-pro')) {
    return;
  }

  function $(id) {
    return document.getElementById(id);
  }

  function ensureDock() {
    var page = document.querySelector('.ame-pos-page.ame-sale-pro');
    if (!page) {
      return null;
    }
    var dock = page.querySelector('.ame-sale-sticky-dock');
    if (dock) {
      return dock;
    }
    dock = document.createElement('div');
    dock.className = 'ame-sale-sticky-dock';
    dock.setAttribute('aria-label', 'বিক্রয় সারাংশ');
    dock.innerHTML =
      '<div class="ame-sale-dock-total" id="ameSaleDockTotal">৳0.00</div>' +
      '<button type="button" class="ame-sale-dock-go" id="ameSaleDockGo">নিচে যান</button>';
    page.appendChild(dock);
    var go = dock.querySelector('#ameSaleDockGo');
    if (go) {
      go.addEventListener('click', function () {
        var modeEl = $('amePosSaleMode');
        var completeBtn = $('amePosCompleteBtn');
        var isDraft =
          (typeof window.amePosIsDraftMemo === 'function' && window.amePosIsDraftMemo()) ||
          (modeEl && String(modeEl.value || '').toLowerCase() === 'draft') ||
          (completeBtn && completeBtn.classList.contains('is-draft'));
        if (isDraft && typeof window.ameSaveMemoDraft === 'function') {
          window.ameSaveMemoDraft();
          return;
        }
        var pay = $('amePosPay');
        var btn = $('amePosCompleteBtn');
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

    var src = $('net-payable') || $('sub-total');
    var label = $('ameSaleDockTotal');
    if (src && label) {
      var t = (src.textContent || src.value || '৳0.00').trim();
      label.textContent = t.indexOf('৳') >= 0 ? t : '৳' + t;
    }

    var modeEl = $('amePosSaleMode');
    var go = $('ameSaleDockGo');
    var completeBtn = $('amePosCompleteBtn');
    if (go) {
      var isDraft =
        (modeEl && String(modeEl.value || '').toLowerCase() === 'draft') ||
        (completeBtn && completeBtn.classList.contains('is-draft'));
      go.textContent = isDraft ? 'ড্রাফট সেভ' : 'পেমেন্ট / সম্পন্ন';
    }
  }

  var mo = null;
  function watch() {
    syncDock();
    var targets = ['net-payable', 'sub-total', 'amePosSaleMode', 'amePosCompleteBtn'];
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
})();
