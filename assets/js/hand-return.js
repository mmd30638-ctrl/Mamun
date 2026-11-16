(function () {
  var api = '/modules/returns/v2/actions/hand_return.php';
  var csrf = window.AME_CSRF_TOKEN || '';
  var sayAfter = null;
  var token = '';

  function freshToken() {
    token = 'hr-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    var hidden = document.getElementById('hrToken');
    if (hidden) hidden.value = token;
  }

  function say(message, opts) {
    opts = opts || {};
    var box = document.getElementById('hrSay');
    var btn = document.getElementById('hrSayBtn');
    if (!box || !btn) return;
    btn.textContent = String(message || '');
    btn.classList.toggle('is-ok', opts.tone === 'ok');
    sayAfter = typeof opts.after === 'function' ? opts.after : null;
    box.hidden = false;
    if (btn.focus) btn.focus();
  }

  function closeSay() {
    var box = document.getElementById('hrSay');
    var next = sayAfter;
    sayAfter = null;
    if (box) box.hidden = true;
    if (next) next();
  }

  function openReturn() {
    var draw = document.getElementById('hrDraw');
    if (!draw) return;
    freshToken();
    draw.hidden = false;
    var q = document.getElementById('hrProductQ');
    if (q) q.focus();
  }

  function closeReturn() {
    var draw = document.getElementById('hrDraw');
    if (draw) draw.hidden = true;
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }

  function bindSearch(inputId, listId, key, onPick) {
    var input = document.getElementById(inputId);
    var list = document.getElementById(listId);
    if (!input || !list) return;
    var timer = 0;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 1) {
        list.innerHTML = '';
        list.hidden = true;
        return;
      }
      timer = setTimeout(function () {
        fetch(api + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var rows = (data && data[key]) || [];
            list.innerHTML = rows.map(function (row) {
              var extra = key === 'products'
                ? 'স্টক ' + row.stock
                : (row.phone || '');
              return '<button type="button" data-id="' + row.id + '">' + esc(row.name) + (extra ? '<small>' + esc(extra) + '</small>' : '') + '</button>';
            }).join('');
            list.hidden = rows.length === 0;
            list.querySelectorAll('button').forEach(function (btn) {
              btn.addEventListener('click', function () {
                var id = parseInt(btn.getAttribute('data-id') || '0', 10);
                var row = rows.filter(function (item) { return item.id === id; })[0];
                if (row) onPick(row);
                list.hidden = true;
                list.innerHTML = '';
              });
            });
          })
          .catch(function () {
            list.hidden = true;
          });
      }, 180);
    });
  }

  function bind() {
    var draw = document.getElementById('hrDraw');
    var sayBox = document.getElementById('hrSay');
    if (draw && draw.parentNode !== document.body) document.body.appendChild(draw);
    if (sayBox && sayBox.parentNode !== document.body) document.body.appendChild(sayBox);
    var open = document.getElementById('hrOpen');
    var form = document.getElementById('hrForm');
    var sayBtn = document.getElementById('hrSayBtn');
    if (open) open.addEventListener('click', openReturn);
    if (sayBtn) sayBtn.addEventListener('click', closeSay);
    if (draw) {
      draw.addEventListener('click', function (ev) {
        if (ev.target === draw || ev.target.closest('[data-hr-close]')) closeReturn();
      });
    }
    bindSearch('hrProductQ', 'hrProductList', 'products', function (row) {
      document.getElementById('hrProductId').value = String(row.id);
      document.getElementById('hrProductQ').value = row.name;
    });
    bindSearch('hrCustomerQ', 'hrCustomerList', 'customers', function (row) {
      document.getElementById('hrCustomerId').value = String(row.id);
      document.getElementById('hrCustomerQ').value = row.name;
    });
    var clearCustomer = document.getElementById('hrCustomerClear');
    if (clearCustomer) {
      clearCustomer.addEventListener('click', function () {
        document.getElementById('hrCustomerId').value = '';
        document.getElementById('hrCustomerQ').value = '';
      });
    }
    if (!form) return;
    form.noValidate = true;
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var productId = (document.getElementById('hrProductId').value || '').trim();
      var qty = Number(form.querySelector('[name=qty]').value || 0);
      if (!productId || !(qty > 0)) {
        say('পণ্য ও পরিমাণ দিন।', { tone: 'warn' });
        return;
      }
      var submit = document.getElementById('hrSubmit');
      if (submit) submit.disabled = true;
      var body = new FormData(form);
      body.set('csrf_token', csrf);
      body.set('idempotency_token', token);
      fetch(api, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.ok) throw new Error(res.error || 'পণ্য ফেরত রাখা যায়নি।');
          say(res.message || 'পণ্য ফেরত নেওয়া হয়েছে। স্টক বেড়েছে।', {
            tone: 'ok',
            after: function () {
              if (!res.replay) {
                form.reset();
                document.getElementById('hrProductId').value = '';
                document.getElementById('hrCustomerId').value = '';
                freshToken();
              }
              closeReturn();
            }
          });
        })
        .catch(function (err) {
          say(err.message || 'পণ্য ফেরত রাখা যায়নি। স্টক আগের মতোই আছে।', { tone: 'warn' });
        })
        .then(function () {
          if (submit) submit.disabled = false;
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
