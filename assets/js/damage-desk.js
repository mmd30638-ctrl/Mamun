(function () {
    var charge = document.getElementById('dmgCharge');
    var hits = document.getElementById('dmgHits');
    var body = document.getElementById('dmgCartBody');
    var empty = document.getElementById('dmgCartEmpty');
    var total = document.getElementById('dmgCartTotal');
    var form = document.getElementById('dmgCartForm');
    var reasonsEl = document.getElementById('dmgReasonsJson');
    var reasons = {};
    if (reasonsEl) {
        try { reasons = JSON.parse(reasonsEl.textContent || '{}') || {}; } catch (e) { reasons = {}; }
    }
    var cart = [];
    var req = null;

    function money(n) {
        return '৳' + (Number(n) || 0).toFixed(2);
    }
    function qtyTxt(n) {
        var s = (Number(n) || 0).toFixed(3);
        return s.replace(/\.?0+$/, '');
    }
    function paintHits(list) {
        if (!hits) return;
        hits.replaceChildren();
        if (!list || !list.length) {
            hits.innerHTML = '<p class="dmg-hit-empty">এই খোঁজে পণ্য নেই। নাম বা কোড বদলে চার্জ করুন।</p>';
            hits.hidden = false;
            return;
        }
        list.forEach(function (p) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'res-item';
            var stock = Number(p.stock) || 0;
            btn.innerHTML = '<span class="res-copy"><span class="res-name"></span><span class="res-meta ' + (stock > 0 ? 'is-in' : 'is-out') + '"></span></span><span class="res-price"></span>';
            btn.querySelector('.res-name').textContent = p.name || '';
            btn.querySelector('.res-meta').textContent = (p.sku || p.code || ('ID-' + p.id)) + ' · স্টক ' + qtyTxt(stock);
            btn.querySelector('.res-price').textContent = money(p.unit_cost);
            btn.addEventListener('click', function () { addLine(p); });
            hits.appendChild(btn);
        });
        hits.hidden = false;
    }
    function addLine(p) {
        var id = Number(p.id);
        var stock = Number(p.stock) || 0;
        if (stock <= 0) {
            alert('স্টকে এই পণ্য নেই।');
            return;
        }
        var found = cart.find(function (x) { return x.id === id; });
        if (found) {
            if (found.qty + 1 > stock) {
                alert('স্টকের বেশি নেওয়া যাবে না। এখন আছে ' + qtyTxt(stock));
                return;
            }
            found.qty += 1;
        } else {
            cart.push({
                id: id,
                name: p.name || '',
                sku: p.sku || p.code || '',
                stock: stock,
                unit_cost: Number(p.unit_cost) || 0,
                qty: 1,
                reason: Object.keys(reasons)[0] || 'other',
                note: ''
            });
        }
        if (charge) charge.value = '';
        if (hits) { hits.replaceChildren(); hits.hidden = true; }
        renderCart();
    }
    function renderCart() {
        if (!body) return;
        body.replaceChildren();
        if (empty) empty.hidden = cart.length > 0;
        var loss = 0;
        cart.forEach(function (line, i) {
            loss += line.qty * line.unit_cost;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><strong></strong><small></small></td>'
                + '<td><input type="number" min="0.001" step="0.001" class="dmg-q"></td>'
                + '<td><select class="dmg-r"></select></td>'
                + '<td><input type="text" maxlength="255" class="dmg-n" placeholder="নোট"></td>'
                + '<td class="dmg-line-loss"></td>'
                + '<td><button type="button" class="dmg-x">বাতিল</button></td>';
            tr.querySelector('strong').textContent = line.name;
            tr.querySelector('small').textContent = line.sku + ' · স্টক ' + qtyTxt(line.stock);
            var q = tr.querySelector('.dmg-q');
            q.value = qtyTxt(line.qty);
            q.addEventListener('input', function () {
                var v = parseFloat(q.value || '0');
                if (!isFinite(v) || v <= 0) v = 0.001;
                if (v > line.stock) v = line.stock;
                line.qty = v;
                renderCart();
            });
            var sel = tr.querySelector('.dmg-r');
            Object.keys(reasons).forEach(function (k) {
                var o = document.createElement('option');
                o.value = k;
                o.textContent = reasons[k];
                if (k === line.reason) o.selected = true;
                sel.appendChild(o);
            });
            sel.addEventListener('change', function () { line.reason = sel.value; });
            var n = tr.querySelector('.dmg-n');
            n.value = line.note;
            n.addEventListener('input', function () { line.note = n.value; });
            tr.querySelector('.dmg-line-loss').textContent = money(line.qty * line.unit_cost);
            tr.querySelector('.dmg-x').addEventListener('click', function () {
                cart.splice(i, 1);
                renderCart();
            });
            body.appendChild(tr);
        });
        if (total) total.textContent = money(loss);
        syncHidden();
    }
    function syncHidden() {
        if (!form) return;
        form.querySelectorAll('input[data-dmg-line]').forEach(function (el) { el.remove(); });
        cart.forEach(function (line, i) {
            [['product_id', line.id], ['qty', line.qty], ['reason', line.reason], ['note', line.note]].forEach(function (pair) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'lines[' + i + '][' + pair[0] + ']';
                inp.value = String(pair[1]);
                inp.setAttribute('data-dmg-line', '1');
                form.appendChild(inp);
            });
        });
    }
    function search() {
        if (!charge) return;
        var q = charge.value.trim();
        if (q.length < 2 && !/^\d+$/.test(q)) {
            if (hits) { hits.replaceChildren(); hits.hidden = true; }
            return;
        }
        if (req) req.abort();
        req = new AbortController();
        fetch('damage_search.php?q=' + encodeURIComponent(q), { credentials: 'same-origin', signal: req.signal })
            .then(function (r) { if (!r.ok) throw new Error('search'); return r.json(); })
            .then(paintHits)
            .catch(function (e) {
                if (e.name === 'AbortError') return;
                paintHits([]);
            });
    }
    var timer = 0;
    if (charge) {
        charge.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(search, 180);
        });
        charge.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                var first = hits ? hits.querySelector('.res-item') : null;
                if (first) first.click();
            }
        });
    }
    var wipe = document.getElementById('dmgCancelAll');
    if (wipe) {
        wipe.addEventListener('click', function () {
            cart = [];
            if (charge) charge.value = '';
            if (hits) { hits.replaceChildren(); hits.hidden = true; }
            renderCart();
        });
    }
    if (form) {
        form.addEventListener('submit', function (ev) {
            if (!cart.length) {
                ev.preventDefault();
                alert('আগে চার্জ বক্সে পণ্য খুঁজে তালিকায় নিন।');
            }
        });
    }
    renderCart();
})();
