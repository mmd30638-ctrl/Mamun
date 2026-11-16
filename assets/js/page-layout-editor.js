(function () {
    var boot = window.AME_LAYOUT_STUDIO || {};
    var data = boot.data || { iconOnAllPages: true, front: {}, pages: {} };
    data.pages = data.pages || {};
    var currentKey = boot.startPage || 'sales/pos';
    var currentUrl = boot.startUrl || '/modules/sales/pos.php';
    var picked = { kind: 'box', selector: '', data: null };

    var colorFields = [
        ['navy', 'নেভি'],
        ['blue', 'নীল'],
        ['green', 'টাকা সবুজ'],
        ['canvas', 'ফ্রন্ট ক্যানভাস'],
        ['text', 'লেখা'],
        ['muted', 'মৃদু'],
        ['surface', 'সারফেস'],
        ['line', 'লাইন'],
        ['danger', 'লাল']
    ];

    function $(id) {
        return document.getElementById(id);
    }

    function status(msg, ok) {
        var el = $('ameStatus');
        if (!el) return;
        el.textContent = msg;
        el.style.color = ok === false ? '#dc2626' : '#04933A';
    }

    function pageState() {
        if (!data.pages[currentKey]) {
            data.pages[currentKey] = { boxes: [], icons: [] };
        }
        return data.pages[currentKey];
    }

    function upsert(list, item) {
        var i = list.findIndex(function (x) { return x.selector === item.selector; });
        if (i >= 0) {
            list[i] = Object.assign({}, list[i], item);
        } else {
            list.push(item);
        }
    }

    function renderPages() {
        var wrap = $('amePages');
        wrap.innerHTML = '';
        (boot.catalog || []).forEach(function (item) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = item.label;
            b.className = item.key === currentKey ? 'on' : '';
            b.onclick = function () {
                currentKey = item.key;
                currentUrl = item.url;
                renderPages();
                loadFrame();
            };
            wrap.appendChild(b);
        });
    }

    function renderColors() {
        var wrap = $('ameColors');
        wrap.innerHTML = '';
        colorFields.forEach(function (pair) {
            var lab = document.createElement('label');
            lab.textContent = pair[1];
            var inp = document.createElement('input');
            inp.type = 'color';
            inp.value = data.front[pair[0]] || '#003F8F';
            inp.oninput = function () {
                data.front[pair[0]] = inp.value;
                pushCss();
            };
            lab.appendChild(inp);
            wrap.appendChild(lab);
        });
    }

    function cssFromData() {
        var f = data.front || {};
        var css = ':root{--desk-navy:' + (f.navy || '#003F8F') +
            ';--desk-blue:' + (f.blue || '#0057B8') +
            ';--desk-green:' + (f.green || '#04933A') +
            ';--desk-canvas:' + (f.canvas || '#F4F7FB') +
            ';--desk-text:' + (f.text || '#111827') +
            ';--desk-muted:' + (f.muted || '#667085') +
            ';--desk-surface:' + (f.surface || '#F8FAFC') +
            ';--desk-line:' + (f.line || '#D7DEE8') +
            ';--desk-danger:' + (f.danger || '#DC2626') + ';}';
        Object.keys(data.pages || {}).forEach(function (key) {
            var page = data.pages[key] || {};
            var scope = 'body[data-ame-layout-page="' + key + '"]';
            (page.boxes || []).forEach(function (box) {
                if (!box.selector) return;
                var rules = [];
                if (box.hidden) rules.push('display:none');
                if (box.bg) rules.push('background:' + box.bg);
                if (box.color) rules.push('color:' + box.color);
                ['width', 'height', 'left', 'top'].forEach(function (p) {
                    if (typeof box[p] === 'number') rules.push(p + ':' + box[p] + 'px');
                });
                if (box.moved) rules.push('position:relative');
                if (rules.length) css += scope + ' ' + box.selector + '{' + rules.join(';') + ' !important;}';
            });
            (page.icons || []).forEach(function (icon) {
                if (!icon.selector) return;
                var rules = [];
                if (icon.hidden) rules.push('display:none');
                if (icon.size) {
                    rules.push('width:' + icon.size + 'px');
                    rules.push('height:' + icon.size + 'px');
                    rules.push('font-size:' + icon.size + 'px');
                }
                if (icon.color) rules.push('color:' + icon.color);
                if (rules.length) css += scope + ' ' + icon.selector + '{' + rules.join(';') + ' !important;}';
            });
        });
        return css;
    }

    function pushCss() {
        var frame = $('ameStage');
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage({ type: 'ame-layout-css', css: cssFromData() }, '*');
        }
    }

    function loadFrame() {
        var frame = $('ameStage');
        var url = currentUrl;
        url += (url.indexOf('?') >= 0 ? '&' : '?') + 'ame_layout_embed=1';
        frame.src = url;
    }

    function fillPick(msg) {
        picked.kind = msg.icon ? 'icon' : 'box';
        picked.selector = msg.data.selector;
        picked.data = msg.data;
        $('amePickLabel').textContent = (picked.kind === 'icon' ? 'আইকন: ' : 'বক্স: ') +
            (msg.data.label || msg.data.selector);
        if (picked.kind === 'box') {
            $('boxW').value = msg.data.width;
            $('boxH').value = msg.data.height;
            $('boxX').value = msg.data.left;
            $('boxY').value = msg.data.top;
            $('boxBg').value = msg.data.bg || '#ffffff';
            $('boxFg').value = msg.data.color || '#111827';
        } else {
            $('iconSize').value = msg.data.size;
            $('iconColor').value = msg.data.color || '#003F8F';
        }
    }

    function writePick() {
        if (!picked.selector) {
            return;
        }
        var st = pageState();
        if (picked.kind === 'icon') {
            upsert(st.icons, {
                selector: picked.selector,
                size: Number($('iconSize').value) || 24,
                color: $('iconColor').value,
                hidden: $('iconHide').checked
            });
        } else {
            upsert(st.boxes, {
                selector: picked.selector,
                width: Number($('boxW').value) || 0,
                height: Number($('boxH').value) || 0,
                left: Number($('boxX').value) || 0,
                top: Number($('boxY').value) || 0,
                bg: $('boxBg').value,
                color: $('boxFg').value,
                hidden: $('boxHide').checked,
                moved: true
            });
        }
        pushCss();
    }

    function save() {
        data.iconOnAllPages = $('ameIconAll').checked;
        fetch(boot.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', iconOnAllPages: data.iconOnAllPages, front: data.front, pages: data.pages })
        }).then(function (r) { return r.json(); })
            .then(function (res) {
                status(res.message || 'সেভ হয়েছে', !!res.ok);
            })
            .catch(function () { status('সংরক্ষণ ব্যর্থ', false); });
    }

    ['boxW', 'boxH', 'boxX', 'boxY', 'boxBg', 'boxFg', 'boxHide', 'iconSize', 'iconColor', 'iconHide'].forEach(function (id) {
        var el = $(id);
        if (el) {
            el.addEventListener('input', writePick);
            el.addEventListener('change', writePick);
        }
    });

    $('ameIconAll').addEventListener('change', function () {
        data.iconOnAllPages = $('ameIconAll').checked;
        save();
    });
    $('ameSave').onclick = save;
    $('ameReset').onclick = function () {
        if (!confirm('ডিফল্ট রং ও লেআউটে ফিরবেন?')) return;
        fetch(boot.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset' })
        }).then(function (r) { return r.json(); })
            .then(function (res) {
                data = res.data || data;
                renderColors();
                pushCss();
                status(res.message || 'রিসেট', !!res.ok);
            });
    };

    window.addEventListener('message', function (ev) {
        if (!ev.data) return;
        if (ev.data.type === 'ame-layout-pick') {
            fillPick(ev.data);
        }
        if (ev.data.type === 'ame-layout-ready') {
            pushCss();
        }
    });

    renderPages();
    renderColors();
    loadFrame();
})();
