(function () {
  function hex(v, fb) {
    return v && /^#/.test(v) ? v : fb;
  }
  function theme() {
    var s = getComputedStyle(document.documentElement);
    return {
      navy: hex(s.getPropertyValue('--brand-navy').trim(), '#0B1E4D'),
      gold: hex(s.getPropertyValue('--brand-gold').trim(), '#C9A227'),
      accent: hex(s.getPropertyValue('--brand-accent').trim(), '#1D9BF0'),
      paper: '#FFFDF8',
      ink: '#14110E'
    };
  }
  function wrap(ctx, text, x, y, max, lineH) {
    var words = String(text).split(/\s+/);
    var line = '';
    for (var i = 0; i < words.length; i++) {
      var test = line ? line + ' ' + words[i] : words[i];
      if (ctx.measureText(test).width > max && line) {
        ctx.fillText(line, x, y);
        line = words[i];
        y += lineH;
      } else {
        line = test;
      }
    }
    if (line) ctx.fillText(line, x, y);
    return y;
  }
  function drawSlip(data) {
    var c = document.createElement('canvas');
    c.width = 1080;
    c.height = 1350;
    var ctx = c.getContext('2d');
    var t = theme();
    ctx.fillStyle = t.paper;
    ctx.fillRect(0, 0, 1080, 1350);
    ctx.fillStyle = t.navy;
    ctx.fillRect(0, 0, 1080, 220);
    ctx.fillStyle = t.gold;
    ctx.fillRect(0, 220, 1080, 8);
    ctx.fillStyle = '#fff';
    ctx.font = '600 28px "IBM Plex Sans", sans-serif';
    ctx.fillText('MAISON LEDGER  ·  DUE SLIP', 72, 80);
    ctx.font = '600 54px "Cormorant Garamond", Georgia, serif';
    wrap(ctx, data.shop || 'Aponshop', 72, 150, 920, 58);
    ctx.fillStyle = t.ink;
    ctx.font = '500 26px "IBM Plex Sans", sans-serif';
    ctx.fillText('বকেয়া স্লিপ', 72, 310);
    ctx.font = '600 64px "Cormorant Garamond", Georgia, serif';
    wrap(ctx, data.name || 'ক্রেতা', 72, 390, 920, 70);
    if (data.code) {
      ctx.font = '500 24px "IBM Plex Sans", sans-serif';
      ctx.fillStyle = '#6b6258';
      ctx.fillText(data.code, 72, 470);
    }
    ctx.fillStyle = t.ink;
    ctx.font = '500 22px "IBM Plex Sans", sans-serif';
    ctx.fillText('বকেয়া পরিমাণ', 72, 560);
    ctx.font = '600 96px "Cormorant Garamond", Georgia, serif';
    ctx.fillText((data.amount || '0') + ' ৳', 72, 670);
    ctx.font = '500 24px "IBM Plex Sans", sans-serif';
    ctx.fillStyle = '#6b6258';
    ctx.fillText(data.due ? ('তারিখ  ·  ' + data.due) : 'তারিখ নির্ধারিত নয়', 72, 750);
    ctx.fillStyle = t.accent;
    ctx.fillRect(72, 820, 200, 4);
    ctx.fillStyle = '#6b6258';
    ctx.font = '400 26px "IBM Plex Sans", sans-serif';
    wrap(ctx, data.note || 'এই স্লিপ হোয়াটসঅ্যাপে পাঠানো হয়েছে। দোকানে এসে বা অনলাইনে জমা দিতে পারেন।', 72, 880, 920, 40);
    if (data.phone) {
      ctx.fillText('দোকান  ·  ' + data.phone, 72, 1040);
    }
    ctx.fillStyle = t.navy;
    ctx.fillRect(0, 1260, 1080, 90);
    ctx.fillStyle = t.gold;
    ctx.font = '600 22px "IBM Plex Sans", sans-serif';
    ctx.fillText('APONSHOP PULSE  ·  2026', 72, 1314);
    return c;
  }
  function waUrl(digits, text) {
    var q = encodeURIComponent(text);
    return digits ? ('https://wa.me/' + digits + '?text=' + q) : ('https://wa.me/?text=' + q);
  }
  async function shareSlip(data) {
    var canvas = drawSlip(data);
    if (typeof window.ameMemoCanvasCapture === 'function') {
      await window.ameMemoCanvasCapture(canvas, 'share', 'due-slip-' + String(data.code || 'customer'));
      return;
    }
    window.alert('মেমো PDF তৈরি হয়নি — আবার চেষ্টা করুন।');
  }
  window.amePulseShare = function (btn) {
    var d = {
      shop: btn.getAttribute('data-shop'),
      name: btn.getAttribute('data-name'),
      code: btn.getAttribute('data-code'),
      amount: btn.getAttribute('data-amount'),
      due: btn.getAttribute('data-due'),
      phone: btn.getAttribute('data-shop-phone'),
      wa: btn.getAttribute('data-wa'),
      note: btn.getAttribute('data-note')
    };
    shareSlip(d);
  };
  window.amePulseWa = function (btn) {
    window.amePulseShare(btn);
  };
})();
