(function () {
    var boot = window.AME_TEXT_STUDIO || {};
    var data = boot.data || { pages: {} };
    data.pages = data.pages || {};
    var currentKey = boot.startPage || 'home';
    var currentUrl = boot.startUrl || '/index.php';

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
            data.pages[currentKey] = { scale: 1, color: '' };
        }
        return data.pages[currentKey];
    }

    function bnPct(scale) {
        return Math.round(scale * 100) + '%';
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
                fillControls();
                loadFrame();
            };
            wrap.appendChild(b);
        });
    }

    function fillControls() {
        var st = pageState();
        var scale = Number(st.scale);
        if (!scale || isNaN(scale)) scale = 1;
        $('ameScale').value = String(scale);
        $('ameScaleLabel').textContent = bnPct(scale);
        var colorOn = !!(st.color && /^#[0-9a-fA-F]{3,8}$/.test(st.color));
        $('ameColorOn').checked = colorOn;
        $('ameColor').value = colorOn ? st.color : '#111827';
        $('amePickLabel').textContent = currentKey + ' · ' + bnPct(scale);
    }

    function readControls() {
        var st = pageState();
        st.scale = Math.round(Number($('ameScale').value) * 100) / 100;
        if ($('ameColorOn').checked) {
            st.color = $('ameColor').value;
        } else {
            st.color = '';
        }
        data.pages[currentKey] = st;
        $('ameScaleLabel').textContent = bnPct(st.scale);
        $('amePickLabel').textContent = currentKey + ' · ' + bnPct(st.scale);
        pushCss();
    }

    function cssFor(store) {
        var css = '';
        Object.keys(store.pages || {}).forEach(function (key) {
            if (!/^[a-z0-9_\/-]{1,80}$/.test(key)) return;
            var page = store.pages[key] || {};
            var scale = Number(page.scale);
            if (!scale || isNaN(scale)) scale = 1;
            if (scale < 0.7) scale = 0.7;
            if (scale > 1.8) scale = 1.8;
            var scope = 'html body[data-ame-layout-page="' + key + '"]';
            if (Math.abs(scale - 1) >= 0.01) {
                css += scope + ' #main-content{zoom:' + scale + ';}';
            }
            if (page.color && /^#[0-9a-fA-F]{3,8}$/.test(page.color)) {
                css += scope + ' #main-content,' + scope +
                    ' #main-content :is(p,span,label,td,th,a,li,h1,h2,h3,h4,small,b,strong,input,select,textarea){color:' +
                    page.color + ' !important;}';
            }
        });
        return css;
    }

    function pushCss() {
        var frame = $('ameStage');
        if (!frame || !frame.contentWindow) return;
        frame.contentWindow.postMessage({ type: 'ame-text-css', css: cssFor(data) }, '*');
    }

    function loadFrame() {
        var join = currentUrl.indexOf('?') >= 0 ? '&' : '?';
        $('ameStage').src = currentUrl + join + 'ame_text_embed=1';
    }

    function post(action, extra) {
        var body = Object.assign({ action: action }, extra || {});
        if (action === 'save') {
            body.pages = data.pages;
        }
        return fetch(boot.api || '/modules/settings/text_style_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (res) { return res.json(); });
    }

    $('ameScale').addEventListener('input', readControls);
    $('ameColor').addEventListener('input', function () {
        $('ameColorOn').checked = true;
        readControls();
    });
    $('ameColorOn').addEventListener('change', readControls);
    $('ameBigger').addEventListener('click', function () {
        $('ameScale').value = String(Math.min(1.8, Number($('ameScale').value) + 0.05));
        readControls();
    });
    $('ameSmaller').addEventListener('click', function () {
        $('ameScale').value = String(Math.max(0.7, Number($('ameScale').value) - 0.05));
        readControls();
    });
    $('ameSave').addEventListener('click', function () {
        readControls();
        post('save').then(function (out) {
            status((out && out.message) || 'সেভ হয়েছে', !!(out && out.ok));
        }).catch(function () {
            status('সার্ভার এরর', false);
        });
    });
    $('ameResetPage').addEventListener('click', function () {
        delete data.pages[currentKey];
        fillControls();
        pushCss();
        post('reset_page', { pageKey: currentKey }).then(function (out) {
            status((out && out.message) || 'ফেরানো হয়েছে', !!(out && out.ok));
        });
    });
    $('ameReset').addEventListener('click', function () {
        data.pages = {};
        fillControls();
        pushCss();
        post('reset').then(function (out) {
            status((out && out.message) || 'ডিফল্ট', !!(out && out.ok));
        });
    });
    $('ameStage').addEventListener('load', function () {
        setTimeout(pushCss, 400);
    });

    renderPages();
    fillControls();
    loadFrame();
})();
