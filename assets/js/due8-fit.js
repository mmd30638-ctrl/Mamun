(function () {
  var wrap = document.querySelector('.d8-table-wrap');
  var table = wrap && wrap.querySelector('table.d8-table');
  var page = document.querySelector('.d8');
  if (!page) return;

  function fitTable() {
    if (!wrap || !table) return;
    var w = window.innerWidth;
    table.style.removeProperty('zoom');
    table.style.removeProperty('transform');
    table.style.setProperty('zoom', '1', 'important');
    table.style.setProperty('transform', 'none', 'important');
    wrap.style.setProperty('overflow-x', 'hidden', 'important');
    wrap.style.setProperty('overflow-y', 'auto', 'important');
    var tw = table.scrollWidth || table.offsetWidth;
    var ww = wrap.clientWidth;
    if (w >= 1500) {
      var top = wrap.getBoundingClientRect().top;
      wrap.style.setProperty('max-height', Math.max(240, window.innerHeight - top - 28) + 'px', 'important');
      if (tw > 40 && ww > tw + 24) {
        table.style.setProperty('transform-origin', 'top left', 'important');
        table.style.setProperty('transform', 'scale(' + Math.min(2.2, ww / tw) + ')', 'important');
      }
      return;
    }
    wrap.style.removeProperty('max-height');
    if (tw > ww && ww > 40) {
      var z = w >= 900 ? Math.max(0.72, ww / tw) : Math.max(0.28, ww / tw);
      table.style.setProperty('transform-origin', 'top left', 'important');
      table.style.setProperty('transform', 'scale(' + z + ')', 'important');
    }
  }

  function fitPage() {
    var well = document.querySelector('.ame-due8-well') || page;
    well.style.setProperty('zoom', '1', 'important');
    well.style.setProperty('transform', 'none', 'important');
    well.style.setProperty('overflow-y', 'auto', 'important');
    fitTable();
  }

  window.addEventListener('resize', fitPage);
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', fitPage);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fitPage);
  } else {
    fitPage();
  }
})();
