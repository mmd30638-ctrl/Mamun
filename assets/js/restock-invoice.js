'use strict';

(function () {
    if (window.AME_RSTK_SHEET) {
        return;
    }
    window.AME_RSTK_SHEET = true;

    var money = function (n) {
        var v = Number(n) || 0;
        return '৳' + v.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    var rows = function () {
        return Array.prototype.slice.call(document.querySelectorAll('#rstkList .rstk-row'));
    };

    var recalc = function () {
        var count = 0;
        var pcs = 0;
        var total = 0;
        rows().forEach(function (row) {
            var price = Number(row.getAttribute('data-price')) || 0;
            var input = row.querySelector('.rstk-qty-val');
            var qty = Math.max(1, parseInt(input && input.value, 10) || 1);
            if (input) {
                input.value = String(qty);
            }
            var hiddenQty = row.querySelector('input[name="qty[]"], .rstk-qty-hidden');
            if (hiddenQty) hiddenQty.value = String(qty);
            var line = price * qty;
            var lineEl = row.querySelector('.rstk-line');
            if (lineEl) {
                lineEl.textContent = money(line);
            }
            var numEl = row.querySelector('.rstk-qty-num');
            if (numEl) {
                numEl.textContent = String(qty);
            }
            count += 1;
            pcs += qty;
            total += line;
        });
        var countEl = document.getElementById('rstkCount');
        var pcsEl = document.getElementById('rstkPcs');
        var totalEl = document.getElementById('rstkTotal');
        if (countEl) countEl.textContent = String(count);
        if (pcsEl) pcsEl.textContent = String(pcs);
        if (totalEl) totalEl.textContent = money(total);

        var empty = document.getElementById('rstkEmpty');
        var list = document.getElementById('rstkList');
        var foot = document.getElementById('rstkFoot');
        if (count === 0) {
            if (empty) empty.hidden = false;
            if (list) list.hidden = true;
            if (foot) foot.hidden = true;
        } else {
            if (empty) empty.hidden = true;
            if (list) list.hidden = false;
            if (foot) foot.hidden = false;
        }
    };

    var sheet = document.getElementById('rstkSheet');
    if (!sheet) {
        return;
    }

    sheet.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-rstk]');
        if (!btn) return;
        var row = btn.closest('.rstk-row');
        if (!row) return;
        var action = btn.getAttribute('data-rstk');
        var input = row.querySelector('.rstk-qty-val');
        var qty = parseInt(input && input.value, 10) || 1;
        if (action === 'plus') {
            input.value = String(qty + 1);
            recalc();
            return;
        }
        if (action === 'minus') {
            input.value = String(Math.max(1, qty - 1));
            recalc();
            return;
        }
        if (action === 'del') {
            row.remove();
            recalc();
        }
    });

    sheet.addEventListener('change', function (ev) {
        if (ev.target.classList.contains('rstk-qty-val')) {
            recalc();
        }
    });

    var cfg = window.AME_RSTK_ADD || {};
    var esc = function (value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    };
    var addBox = document.getElementById('rstkAddQ');
    var addHits = document.getElementById('rstkAddHits');
    var addGo = document.getElementById('rstkAddGo');
    var addTimer = 0;
    var extraSeq = 1;
    var lastRows = [];
    var showHits = function (html) {
        if (!addHits) return;
        addHits.innerHTML = html;
        addHits.hidden = html === '';
    };
    var rowHtml = function (name, sub, price, qty, extra) {
        var priceHtml = extra
            ? '<div class="rstk-price rstk-extra-tag">এক্সট্রা</div>'
            : '<div class="rstk-price rstk-money">' + esc(cfg.buy || '') + ' ' + money(price) + '</div>';
        var lineHtml = extra
            ? '<div class="rstk-line rstk-money" hidden></div>'
            : '<div class="rstk-line rstk-money">' + money(price * qty) + '</div>';
        return '<div class="rstk-row-top"><span class="rstk-idx" aria-hidden="true"></span><div class="rstk-name-wrap"><p class="rstk-name">' + esc(name) + '</p><p class="rstk-sub">' + sub + '</p></div></div><div class="rstk-row-bot">' + priceHtml + '<div class="rstk-qty rstk-no-capture"><button type="button" data-rstk="minus" aria-label="-">−</button><input class="rstk-qty-val" type="number" min="1" step="1" value="' + qty + '" inputmode="numeric"><button type="button" data-rstk="plus" aria-label="+">+</button></div><div class="rstk-qty-print"><span class="rstk-qty-num">' + qty + '</span></div>' + lineHtml + '<button type="button" class="rstk-del rstk-no-capture" data-rstk="del" aria-label="' + esc(cfg.del || 'Remove') + '" onclick="return ameRstkDrop(this)">' + esc(cfg.cancel || 'মুছুন') + '</button></div>';
    };
    var findName = function (name) {
        var key = String(name || '').trim().toLowerCase();
        var found = null;
        rows().forEach(function (row) {
            var n = row.querySelector('.rstk-name');
            if (n && String(n.textContent || '').trim().toLowerCase() === key) found = row;
        });
        return found;
    };
    var placeRow = function (li) {
        var list = document.getElementById('rstkList');
        if (!list) return;
        list.appendChild(li);
        recalc();
        li.scrollIntoView({ block: 'center' });
    };
    var markDup = function (existing) {
        if (existing) existing.scrollIntoView({ block: 'center' });
        showHits('<div class="rstk-hit rstk-hit-empty">' + esc(cfg.dup || '') + '</div>');
    };
    var addRow = function (item) {
        var id = parseInt(item.id, 10) || 0;
        if (id <= 0) return;
        var existing = sheet.querySelector('.rstk-row[data-pid="' + id + '"]') || findName(item.name);
        if (existing) {
            markDup(existing);
            return;
        }
        var price = Number(item.price) || 0;
        var qty = Math.max(1, parseInt(item.qty, 10) || 1);
        var code = item.sku ? String(item.sku) : ('PRD-' + String(id).padStart(6, '0'));
        var unit = item.unit ? (' ' + item.unit) : '';
        var stockTxt = (cfg.stock || 'Stock') + ' ' + (item.stock == null ? '' : item.stock) + unit;
        var sub = '<span class="rstk-code">ID: ' + esc(code) + '</span><span class="rstk-hide-share"> · <span class="rstk-stock">' + esc(stockTxt) + '</span></span>';
        var li = document.createElement('li');
        li.className = 'rstk-row';
        li.setAttribute('data-pid', String(id));
        li.setAttribute('data-price', String(price));
        li.innerHTML = rowHtml(item.name, sub, price, qty, false);
        placeRow(li);
    };
    var addExtra = function (name) {
        name = String(name || '').trim();
        if (name === '') return;
        var existing = findName(name);
        if (existing) {
            markDup(existing);
            return;
        }
        var li = document.createElement('li');
        li.className = 'rstk-row rstk-extra';
        li.setAttribute('data-pid', 'x' + String(extraSeq++));
        li.setAttribute('data-price', '0');
        li.innerHTML = rowHtml(name, '<span class="rstk-code">এক্সট্রা পণ্য</span>', 0, 1, true);
        placeRow(li);
    };
    var extraButton = function (name) {
        return '<button type="button" class="rstk-hit rstk-hit-extra" data-extra="1">' + esc(cfg.extra || 'এক্সট্রা') + '<small>' + esc(name) + '</small></button>';
    };
    var commitTyped = function () {
        if (!addBox) return;
        var q = addBox.value.trim();
        if (q === '') return;
        var exact = null;
        lastRows.forEach(function (row) {
            if (String(row.name || '').trim().toLowerCase() === q.toLowerCase()) exact = row;
        });
        addBox.value = '';
        showHits('');
        if (exact) addRow(exact);
        else addExtra(q);
    };
    if (addBox && addHits) {
        addBox.addEventListener('input', function () {
            window.clearTimeout(addTimer);
            var q = addBox.value.trim();
            lastRows = [];
            if (q.length < 1) {
                showHits('');
                return;
            }
            addTimer = window.setTimeout(function () {
                var url = (cfg.search || '/modules/inventory/actions/restock_product_search.php') + '?q=' + encodeURIComponent(q);
                fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (found) {
                        if (addBox.value.trim() !== q) return;
                        lastRows = Array.isArray(found) ? found : [];
                        var html = lastRows.map(function (row, i) {
                            return '<button type="button" class="rstk-hit" data-idx="' + i + '"><span>' + esc(row.name) + '</span><small>' + esc(row.sku || row.id) + '</small><em>যোগ</em></button>';
                        }).join('');
                        if (!lastRows.length) {
                            html = '<div class="rstk-hit rstk-hit-empty">' + esc(cfg.none || '') + '</div>';
                        }
                        showHits(html + extraButton(q));
                    })
                    .catch(function () {
                        lastRows = [];
                        showHits('<div class="rstk-hit rstk-hit-empty">' + esc(cfg.none || '') + '</div>' + extraButton(q));
                    });
            }, 280);
        });
        addBox.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                commitTyped();
            }
        });
        addHits.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.rstk-hit');
            if (!btn) return;
            if (btn.getAttribute('data-extra') === '1') {
                commitTyped();
                return;
            }
            var idx = parseInt(btn.getAttribute('data-idx'), 10);
            if (!(idx >= 0) || !lastRows[idx]) return;
            var item = lastRows[idx];
            addBox.value = '';
            showHits('');
            addRow(item);
        });
    }
    if (addGo) addGo.addEventListener('click', commitTyped);

    var frontItems = function () {
        return rows().map(function (row) {
            var nameEl = row.querySelector('.rstk-name');
            var input = row.querySelector('.rstk-qty-val');
            return {
                name: nameEl ? String(nameEl.textContent || '').trim() : '',
                qty: Math.max(1, parseInt(input && input.value, 10) || 1)
            };
        }).filter(function (item) { return item.name !== ''; });
    };
    var clipLine = function (ctx, text, maxW) {
        var out = String(text);
        if (ctx.measureText(out).width <= maxW) return out;
        while (out.length > 1 && ctx.measureText(out + '…').width > maxW) {
            out = out.slice(0, -1);
        }
        return out + '…';
    };
    var knockLogo = function (img) {
        if (!img || !img.naturalWidth) return null;
        if (img.__rstkLogo) return img.__rstkLogo;
        var c = document.createElement('canvas');
        c.width = img.naturalWidth;
        c.height = img.naturalHeight;
        var x = c.getContext('2d', { willReadFrequently: true });
        x.drawImage(img, 0, 0);
        try {
            var data = x.getImageData(0, 0, c.width, c.height);
            var p = data.data;
            var n;
            for (n = 0; n < p.length; n += 4) {
                if (p[n] < 36 && p[n + 1] < 36 && p[n + 2] < 36) p[n + 3] = 0;
            }
            x.putImageData(data, 0, 0);
        } catch (err) {
            return img;
        }
        img.__rstkLogo = c;
        return c;
    };
    var frontCanvas = function (items, logoImg) {
        var W = 390;
        var rowH = 28;
        var logoH = 72;
        var headH = 12 + logoH + 48;
        var footH = 16;
        var maxH = 2200;
        var takeN = Math.max(1, Math.min(items.length, Math.floor((maxH - headH - footH) / rowH)));
        var take = items.slice(0, takeN);
        var H = headH + take.length * rowH + footH;
        var c = document.createElement('canvas');
        var scale = 2;
        c.width = W * scale;
        c.height = H * scale;
        var ctx = c.getContext('2d');
        ctx.scale(scale, scale);
        ctx.fillStyle = '#F4F7FB';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = '#0B1E4D';
        ctx.fillRect(0, 0, W, headH);
        var font = '"Noto Sans Bengali","Noto Sans","SolaimanLipi","Segoe UI",sans-serif';
        var src = knockLogo(logoImg);
        if (src && src.width && src.height) {
            var maxW = W - 36;
            var fit = Math.min(maxW / src.width, logoH / src.height);
            var dw = Math.round(src.width * fit);
            var dh = Math.round(src.height * fit);
            ctx.drawImage(src, Math.round((W - dw) / 2), 8, dw, dh);
        }
        var shopEl = document.querySelector('.rstk-head h1');
        var shop = shopEl ? String(shopEl.textContent || '').trim() : '';
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center';
        ctx.font = '800 16px ' + font;
        ctx.fillText(clipLine(ctx, shop, W - 24), W / 2, 12 + logoH + 16);
        ctx.fillStyle = '#D6E4FF';
        ctx.font = '700 12px ' + font;
        ctx.fillText('মাল ভালো মানে দেওয়া হয়', W / 2, 12 + logoH + 34);
        /* পুরনো দুই লাইন আর নিচের পণ্য-পিস গণনা আঁকা হয় না। ফ্রন্ট আকার শেয়ার একই ৩৯০ চওড়ায়। */
        var i;
        ctx.textAlign = 'left';
        for (i = 0; i < take.length; i++) {
            var y = headH + i * rowH;
            ctx.fillStyle = (i % 2 === 0) ? '#ffffff' : '#EEF4FF';
            ctx.fillRect(10, y, W - 20, rowH - 4);
            ctx.fillStyle = '#0B1E4D';
            ctx.font = '700 13px ' + font;
            ctx.fillText(clipLine(ctx, (i + 1) + '. ' + take[i].name, W - 78), 18, y + 16);
            ctx.textAlign = 'right';
            ctx.font = '800 16px ' + font;
            ctx.fillStyle = '#047857';
            ctx.fillText(String(take[i].qty), W - 18, y + 16);
            ctx.textAlign = 'left';
            ctx.fillStyle = '#0B1E4D';
        }
        return c;
    };
    var jpegBlob = function (canvas) {
        var data = canvas.toDataURL('image/jpeg', 0.9);
        var bin = atob((data.split(',')[1]) || '');
        var arr = new Uint8Array(bin.length);
        var i;
        for (i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        return new Blob([arr], { type: 'image/jpeg' });
    };
    var saveBlob = function (blob, name) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
    };
    var shareReady = function (items, kind) {
        var logo = document.querySelector('.rstk-head img.ame-print-logo');
        var go = function () {
            var blob = jpegBlob(frontCanvas(items, logo));
            if (kind === 'save') {
                saveBlob(blob, 'kroy-talika.jpg');
                return;
            }
            var file = new File([blob], 'kroy-talika.jpg', { type: 'image/jpeg' });
            if (navigator.share) {
                navigator.share({ files: [file] }).catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                });
            }
        };
        if (!logo || logo.complete) {
            go();
            return;
        }
        logo.addEventListener('load', go, { once: true });
        logo.addEventListener('error', go, { once: true });
    };
    window.ameRestockShare = function (kind) {
        var items = frontItems();
        if (!items.length) return false;
        shareReady(items, kind);
        return false;
    };

    window.ameRstkRecalc = recalc;
    recalc();
})();
