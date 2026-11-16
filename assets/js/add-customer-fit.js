(function () {
  var well = document.querySelector('.ame-ac-well');
  var card = document.querySelector('.form-card');
  if (!well || !card) return;

  function headerH() {
    var h = document.querySelector('.master-header-fixed');
    if (h) {
      var r = h.getBoundingClientRect();
      if (r.height > 20) return Math.ceil(r.height);
    }
    var css = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ame-header-h'));
    return css > 20 ? css : 0;
  }

  function fitCard() {
    if (!well || !card) return;
    var pad = window.innerWidth <= 900 ? 4 : 10;
    var top = headerH() + pad;
    well.style.setProperty('top', top + 'px', 'important');
    well.style.setProperty('bottom', pad + 'px', 'important');
    well.style.setProperty('left', pad + 'px', 'important');
    well.style.setProperty('right', pad + 'px', 'important');
    well.style.setProperty('overflow-x', 'hidden', 'important');
    card.style.setProperty('zoom', '1', 'important');
    card.style.setProperty('transform', 'none', 'important');
    card.style.setProperty('max-width', 'none', 'important');
    card.style.setProperty('width', '100%', 'important');
    card.style.setProperty('margin', '0', 'important');

    var boxW = well.clientWidth || 0;
    var boxH = well.clientHeight || 0;
    if (boxW < 40 || boxH < 40) {
      var vv = window.visualViewport;
      var w = vv && vv.width ? vv.width : window.innerWidth;
      var h = vv && vv.height ? vv.height : window.innerHeight;
      boxW = w - pad * 2;
      boxH = h - top - pad;
    }
    if (boxW < 40 || boxH < 40) return;

    // টিভি ও ল্যাপটপ: বকেয়া টেবিলের মতো জুম ছোট করে না — স্ক্রল চলে
    if (boxW >= 900) {
      well.style.setProperty('overflow-y', 'auto', 'important');
      return;
    }

    var needW = Math.max(card.scrollWidth, card.offsetWidth, 1);
    var needH = Math.max(card.scrollHeight, card.offsetHeight, 1);
    var z = Math.min(boxW / needW, boxH / needH) * 0.96;
    z = Math.max(0.5, Math.min(1, z));
    well.style.setProperty('overflow-y', needH * z > boxH + 8 ? 'auto' : 'hidden', 'important');
    card.style.setProperty('transform-origin', 'top left', 'important');
    card.style.setProperty('transform', 'scale(' + z + ')', 'important');
    card.style.setProperty('width', (100 / z) + '%', 'important');
  }

  window.ameAcFit = fitCard;

  function later() {
    requestAnimationFrame(function () {
      fitCard();
      requestAnimationFrame(fitCard);
    });
  }

  window.addEventListener('resize', later);
  window.addEventListener('orientationchange', function () { setTimeout(later, 80); });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', later);
  }
  document.addEventListener('change', later);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', later);
  } else {
    later();
  }
})();
