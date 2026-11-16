'use strict';

(function () {
    var list = document.getElementById('ameExchangeBody');
    var items = [];

    var isEnglish = (typeof window.AME_LANG === 'string' && window.AME_LANG !== '')
        ? window.AME_LANG === 'en'
        : document.querySelector('a[href*="switch_language.php?lang=bn"]') !== null;

    var words = isEnglish
        ? {
            remove: 'Remove',
            exchange: 'Exchange',
            stockSkip: 'Old-battery exchange does not use shop stock.'
        }
        : {
            remove: 'বাদ',
            exchange: 'এক্সচেঞ্জ',
            stockSkip: 'পুরাতন ব্যাটারি এক্সচেঞ্জে দোকানের স্টক কাটে না।'
        };

    function money(n) {
        return '৳' + (Number(n) || 0).toFixed(2);
    }

    function totalOf(row) {
        return Math.max(0, Number(row.qty) || 0) * Math.max(0, Number(row.exchange_rate) || 0);
    }

    function exchangeTotal() {
        return items.reduce(function (sum, row) {
            return sum + totalOf(row);
        }, 0);
    }

    function notify() {
        if (typeof window.calculateAll === 'function') {
            window.calculateAll();
        }
        if (typeof window.renderCart === 'function' && (!window.ameGetCart || !window.ameGetCart().length)) {
            window.renderCart();
        }
    }

    function upsert(product, qty, rate) {
        var id = Number(product.id || product.product_id);
        if (!(id > 0)) {
            return;
        }
        qty = Math.max(1, parseInt(qty, 10) || 1);
        rate = Math.max(0, Number(rate));
        if (!Number.isFinite(rate)) {
            rate = Math.max(0, Number(product.sale_price || product.purchase_price || product.current_price) || 0);
        }
        var existing = items.find(function (row) {
            return Number(row.id) === id;
        });
        if (existing) {
            existing.qty += qty;
            if (rate > 0) {
                existing.exchange_rate = rate;
            }
        } else {
            items.push({
                id: id,
                sku: product.sku || '',
                name: product.name || product.item_name || '',
                qty: qty,
                exchange_rate: rate
            });
        }
        render();
    }

    function render() {
        if (!list) {
            notify();
            return;
        }
        list.replaceChildren();
        items.forEach(function (row, index) {
            var tr = document.createElement('tr');
            tr.className = 'ame-pos-ex-row';

            var serial = document.createElement('td');
            serial.className = 'ame-pos-col-sl';
            serial.textContent = 'এক্স';

            var check = document.createElement('td');
            check.className = 'ame-pos-col-check';

            var code = document.createElement('td');
            code.textContent = row.sku || String(row.id);

            var name = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'ame-pos-ex-badge';
            badge.textContent = words.exchange;
            name.appendChild(badge);
            name.appendChild(document.createTextNode(row.name));

            var priceCell = document.createElement('td');
            var price = document.createElement('input');
            price.type = 'number';
            price.className = 'ame-pos-line-input';
            price.min = '0';
            price.step = '0.01';
            price.value = Number(row.exchange_rate).toFixed(2);
            price.addEventListener('change', function () {
                row.exchange_rate = Math.max(0, Number.parseFloat(price.value) || 0);
                price.value = row.exchange_rate.toFixed(2);
                line.textContent = totalOf(row).toFixed(2);
                notify();
            });
            priceCell.appendChild(price);

            var qtyCell = document.createElement('td');
            var qty = document.createElement('input');
            qty.type = 'number';
            qty.className = 'ame-pos-line-input';
            qty.min = '1';
            qty.step = '1';
            qty.value = String(row.qty);
            qty.addEventListener('change', function () {
                row.qty = Math.max(1, parseInt(qty.value, 10) || 1);
                qty.value = String(row.qty);
                line.textContent = totalOf(row).toFixed(2);
                notify();
            });
            qtyCell.appendChild(qty);

            var line = document.createElement('td');
            line.textContent = totalOf(row).toFixed(2);
            line.style.textAlign = 'right';
            line.style.fontWeight = '800';

            var remove = document.createElement('td');
            remove.className = 'ame-pos-col-remove';
            remove.textContent = '✖';
            remove.title = words.remove;
            remove.addEventListener('click', function () {
                items.splice(index, 1);
                render();
            });

            tr.append(serial, check, code, name, priceCell, qtyCell, line, remove);
            list.appendChild(tr);
        });
        notify();
    }

    window.ameAddExchangeFromProduct = function (product) {
        if (!product) {
            return;
        }
        upsert(product, 1, Number(product.sale_price || product.purchase_price || 0) || 0);
    };

    window.ameGetExchange = function () {
        return items.map(function (row) {
            return {
                id: row.id,
                product_id: row.id,
                qty: Math.max(1, parseInt(row.qty, 10) || 1),
                exchange_rate: Math.max(0, Number(row.exchange_rate) || 0),
                name: row.name
            };
        });
    };

    window.ameSetExchange = function (rows) {
        items = Array.isArray(rows)
            ? rows.map(function (row) {
                return {
                    id: Number(row.id || row.product_id),
                    sku: row.sku || '',
                    name: row.name || row.item_name || '',
                    qty: Math.max(1, Number(row.qty) || 1),
                    exchange_rate: Math.max(0, Number(row.exchange_rate || row.rate) || 0)
                };
            }).filter(function (row) {
                return row.id > 0;
            })
            : [];
        render();
        return items;
    };

    window.ameExchangeTotal = exchangeTotal;
    window.ameHasExchange = function () {
        return items.length > 0;
    };

    render();
})();
