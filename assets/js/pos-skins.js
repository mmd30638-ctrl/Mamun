(function () {
    var KEY = 'amePosSkin';
    var COOKIE = 'ame_pos_skin';
    var PALETTE_KEY = 'ameSitePalette';
    var PALETTE_COOKIE = 'ame_site_palette';
    var COLOR_KEY = 'ameSiteCustomColors';
    var SKIN_OVER_KEY = 'amePosSkinOverrides';
    var CLOCK_KEY = 'ameHdrClockColor';
    var CLOCK_COOKIE = 'ame_clock_color';
    var ids = ['workshop', 'navy', 'rose', 'lotus', 'teak', 'forest'];
    var alias = { emerald: 'teak', night: 'forest', green: 'teak' };
    var catalog = { palettes: [], custom: {}, varMap: {}, active: 'custom', skins: [], posUrl: '/modules/sales/pos.php' };
    var editSource = 'custom';
    var filling = false;

    function normalize(id) {
        id = String(id || '').toLowerCase();
        if (alias[id]) {
            id = alias[id];
        }
        return ids.indexOf(id) >= 0 ? id : 'workshop';
    }

    function writeCookie(name, value) {
        var parts = name + '=' + encodeURIComponent(value) + ';path=/;max-age=31536000;SameSite=Lax';
        if (location.protocol === 'https:') {
            parts += ';Secure';
        }
        document.cookie = parts;
    }

    function readCookie(name) {
        var esc = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var match = document.cookie.match(new RegExp('(?:^|; )' + esc + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function readCatalog() {
        var node = document.getElementById('ameSitePalettesJson');
        if (!node) {
            return;
        }
        try {
            catalog = JSON.parse(node.textContent || '{}') || catalog;
        } catch (e) {}
        catalog.palettes = catalog.palettes || [];
        catalog.custom = catalog.custom || {};
        catalog.varMap = catalog.varMap || {};
        catalog.active = catalog.active || 'custom';
        catalog.clockColor = catalog.clockColor || '#2DD4BF';
        catalog.clockPresets = catalog.clockPresets || [];
        catalog.skins = catalog.skins || [];
        catalog.posUrl = catalog.posUrl || '/modules/sales/pos.php';
        catalog.fields = catalog.fields || {};
        catalog.posFields = catalog.posFields || {};
        catalog.english = !!catalog.english;
        try {
            var savedColors = JSON.parse(localStorage.getItem(COLOR_KEY) || 'null');
            if (savedColors && typeof savedColors === 'object') {
                catalog.custom = Object.assign({}, catalog.custom, savedColors);
            }
        } catch (e2) {}
        try {
            catalog.skinOverrides = JSON.parse(localStorage.getItem(SKIN_OVER_KEY) || '{}') || {};
        } catch (e3) {
            catalog.skinOverrides = {};
        }
    }

    function skinMeta(id) {
        id = normalize(id);
        var found = null;
        for (var i = 0; i < catalog.skins.length; i += 1) {
            if (catalog.skins[i].id === id) {
                found = catalog.skins[i];
                break;
            }
        }
        var base = found || { id: id, name: id, tokens: {}, preview: '' };
        var over = window.AME_VD_ENGINE === 1 ? {} : ((catalog.skinOverrides || {})[id] || {});
        return {
            id: base.id,
            name: base.name,
            kind: base.kind,
            preview: base.preview,
            tokens: Object.assign({}, base.tokens || {}, over)
        };
    }

    function currentFromBody() {
        var body = document.body;
        for (var i = 0; i < ids.length; i += 1) {
            if (body.classList.contains('ame-pos-skin-' + ids[i])) {
                return ids[i];
            }
        }
        return '';
    }

    function onPosPage() {
        return document.body.classList.contains('ame-pos-terminal-page');
    }

    function paintButtons(id) {
        document.querySelectorAll('[data-pos-skin]').forEach(function (btn) {
            var on = btn.getAttribute('data-pos-skin') === id;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        var chip = document.getElementById('amePosSkinChip');
        if (chip) {
            chip.textContent = skinMeta(id).name || id;
        }
    }

    function paintPalettes(id) {
        document.querySelectorAll('[data-site-palette]').forEach(function (btn) {
            var on = btn.getAttribute('data-site-palette') === id;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function injectLiveCss(id) {
        var tokens = skinMeta(id).tokens || {};
        var el = document.getElementById('ame-pos-skin-live');
        if (!el) {
            el = document.createElement('style');
            el.id = 'ame-pos-skin-live';
            document.head.appendChild(el);
        }
        var css = '';
        if (tokens.page) {
            css += 'body.ame-pos-terminal-page.ame-pos-skin-' + id + '{';
            css += '--pos-skin-page:' + tokens.page + ';';
            css += '--pos-skin-card:' + (tokens.card || '#fff') + ';';
            css += '--pos-skin-border:' + (tokens.border || '#d9dee5') + ';';
            css += '--pos-skin-title-bg:' + (tokens.titleBg || '#e8edf2') + ';';
            css += '--pos-skin-title-fg:' + (tokens.titleFg || '#1f2937') + ';';
            css += '--pos-skin-btn:' + (tokens.btn || '#0d47a1') + ';';
            css += '--pos-skin-btn-fg:' + (tokens.btnFg || '#fff') + ';';
            css += '--pos-skin-radius:' + (tokens.radius || '4px') + ';';
            css += '--pos-skin-th:' + (tokens.th || tokens.titleBg || '#e8edf2') + ';';
            css += '--pos-skin-th-fg:' + (tokens.thFg || tokens.titleFg || '#1f2937') + ';';
            css += '--pos-skin-input:' + (tokens.input || '#fff') + ';';
            css += '--pos-skin-input-fg:' + (tokens.inputFg || '#111827') + ';';
            css += '--pos-skin-input-bd:' + (tokens.inputBd || '#cfd6de') + ';';
            css += '--pos-skin-text:' + (tokens.text || '#1f2937') + ';';
            css += '--pos-skin-muted:' + (tokens.muted || '#64748b') + ';';
            css += '--pos-skin-chip:' + (tokens.chip || '#e3f2fd') + ';';
            css += '--pos-skin-chip-fg:' + (tokens.chipFg || '#0d47a1') + ';';
            css += '--pos-skin-net:' + (tokens.net || '#0d47a1') + ';';
            css += '--pos-skin-photo:' + (tokens.photo && tokens.photo !== 'none' ? tokens.photo : 'none') + ';';
            css += '}';
        }
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-page,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-shell,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pc-wrap{';
        css += 'background:var(--pos-skin-page)!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-card,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pc-card,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .sidebar-calc,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-summary,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .card{';
        css += 'background:var(--pos-skin-card)!important;border-color:var(--pos-skin-border)!important;';
        css += 'border-radius:var(--pos-skin-radius)!important;color:var(--pos-skin-text)!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-card-title,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-customer-title,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .sidebar-calc h3,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-field-label,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-section h3,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-section h4{';
        css += 'background:var(--pos-skin-title-bg)!important;color:var(--pos-skin-title-fg)!important;';
        css += 'border-radius:calc(var(--pos-skin-radius) - 2px)!important;';
        css += 'display:block!important;width:100%!important;text-align:center!important;';
        css += 'font-size:17px!important;font-weight:800!important;padding:11px 14px!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-heading{';
        css += 'background:var(--pos-skin-card)!important;border:1px solid var(--pos-skin-border)!important;';
        css += 'border-radius:var(--pos-skin-radius)!important;color:var(--pos-skin-text)!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-heading h2,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-heading p{';
        css += 'background:var(--pos-skin-title-bg)!important;color:var(--pos-skin-title-fg)!important;';
        css += 'display:inline-block!important;padding:4px 10px!important;';
        css += 'border-radius:calc(var(--pos-skin-radius) - 2px)!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .table-pos th{';
        css += 'background:var(--pos-skin-th)!important;color:var(--pos-skin-th-fg)!important;}';
        css += 'body.ame-pos-terminal-page:not(.ame-pos-skin-forest) .table-pos td,';
        css += 'body.ame-pos-terminal-page:not(.ame-pos-skin-forest) .table-pos tbody tr:nth-child(even) td{';
        css += 'background:#ffffff!important;color:#000000!important;text-align:center!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] #p-search,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-input,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-select,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .input-style,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .calc-row input,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .calc-row select,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-line-input,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-recent-select,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] select.ame-pos-term-cash{';
        css += 'background:var(--pos-skin-input)!important;color:var(--pos-skin-input-fg)!important;';
        css += 'border-color:var(--pos-skin-input-bd)!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-service-add,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] button.ame-pos-service-add,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-exchange-addbtn{';
        css += 'background:var(--pos-skin-btn)!important;color:var(--pos-skin-btn-fg)!important;';
        css += 'border-color:var(--pos-skin-btn)!important;border-radius:var(--pos-skin-radius)!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] #net-payable,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .net-box,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-service-sum{';
        css += 'color:var(--pos-skin-net)!important;}';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .calc-row label,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-term-title{';
        css += 'color:var(--pos-skin-text)!important;}';
        css += 'body.ame-pos-terminal-page.ame-pos-skin-forest .ame-pos-page{';
        css += 'background-image:var(--pos-skin-photo)!important;background-size:cover!important;';
        css += 'background-position:center!important;}';
        css += 'body.ame-pos-terminal-page:not(.ame-pos-skin-forest) .ame-pos-page,';
        css += 'body.ame-pos-terminal-page:not(.ame-pos-skin-forest) .ame-pos-shell,';
        css += 'body.ame-pos-terminal-page:not(.ame-pos-skin-forest) .pc-wrap,';
        css += 'body.ame-pos-terminal-page:not(.ame-pos-skin-forest) #main-content{';
        css += 'background:#ffffff!important;background-image:none!important;}';
        css += 'body.ame-pos-terminal-page .ame-pos-exchange-lead,';
        css += 'body.ame-pos-terminal-page .ame-pos-exchange-empty{display:none!important;}';
        var ink = 'color:#000000!important;-webkit-text-fill-color:#000000!important;text-shadow:none!important;';
        css += 'body.ame-pos-terminal-page .ame-pos-complete,';
        css += 'body.ame-pos-terminal-page .ame-pos-complete.is-draft,';
        css += 'body.ame-pos-terminal-page button[onclick*="submitFinalSale"],';
        css += 'body.ame-pos-terminal-page .btn-success,';
        css += 'body.ame-pos-terminal-page .pos-payment-add,';
        css += 'body.ame-pos-terminal-page .ame-pos-pay-jump,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-complete,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-complete.is-draft,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] button[onclick*="submitFinalSale"],';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .btn-success,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-add{';
        css += 'background:#ffffff!important;border:1.5px solid #d8e1ec!important;' + ink + '}';
        css += 'body.ame-pos-terminal-page .pos-payment-heading,';
        css += 'body.ame-pos-terminal-page .pos-payment-heading h4,';
        css += 'body.ame-pos-terminal-page .pos-payment-heading small,';
        css += 'body.ame-pos-terminal-page .pos-payment-heading *,';
        css += 'body.ame-pos-terminal-page .pos-payment-summary-item,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-heading,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-heading *,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-summary-item{';
        css += 'background:#ffffff!important;border-color:#d8e1ec!important;' + ink + '}';
        css += 'body.ame-pos-terminal-page .pos-payment-heading small,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-heading small{';
        css += 'color:#9ca3af!important;-webkit-text-fill-color:#9ca3af!important;text-shadow:none!important;font-weight:500!important;}';
        css += 'body.ame-pos-terminal-page .pos-payment-summary-item span,';
        css += 'body.ame-pos-terminal-page .pos-payment-summary-item strong,';
        css += 'body.ame-pos-terminal-page .pos-payment-summary-item *,';
        css += 'body.ame-pos-terminal-page .ame-pos-total small,';
        css += 'body.ame-pos-terminal-page .ame-pos-total strong,';
        css += 'body.ame-pos-terminal-page #net-payable,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-summary-item span,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-summary-item strong,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .pos-payment-summary-item *,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-total small,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] .ame-pos-total strong,';
        css += 'body.ame-pos-terminal-page[class*="ame-pos-skin-"] #net-payable{' + ink + '}';
        css += 'body.ame-pos-terminal-page .ame-pos-home{background:#fff!important;border-color:#d8e1ec!important;color:#000000!important;}';
        el.textContent = css;
    }

    function toast(message) {
        var el = document.getElementById('ameThemeToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ameThemeToast';
            el.className = 'ame-theme-toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.classList.add('is-on');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(function () {
            el.classList.remove('is-on');
        }, 2400);
    }

    function apply(id, opts) {
        opts = opts || {};
        id = normalize(id);
        var body = document.body;
        ids.forEach(function (name) {
            body.classList.remove('ame-pos-skin-' + name);
        });
        body.classList.add('ame-pos-skin-' + id);
        try {
            localStorage.setItem(KEY, id);
        } catch (e) {}
        writeCookie(COOKIE, id);
        injectLiveCss(id);
        paintButtons(id);
        if (opts.announce !== false) {
            var name = skinMeta(id).name || id;
            toast((document.documentElement.lang === 'en' ? 'Counter look: ' : 'কাউন্টার ডিজাইন: ') + name);
        }
        if (opts.redirect && !onPosPage()) {
            window.location.href = catalog.posUrl || '/modules/sales/pos.php';
        }
    }

    function hexLum(hex) {
        hex = normHex(hex);
        if (!hex) {
            return 0;
        }
        var r = parseInt(hex.slice(1, 3), 16) / 255;
        var g = parseInt(hex.slice(3, 5), 16) / 255;
        var b = parseInt(hex.slice(5, 7), 16) / 255;
        function lin(c) {
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
    }

    function contrastRatio(a, b) {
        var L1 = hexLum(a);
        var L2 = hexLum(b);
        var hi = Math.max(L1, L2);
        var lo = Math.min(L1, L2);
        return (hi + 0.05) / (lo + 0.05);
    }

    function pickerHex(value) {
        var hex = normHex(value);
        if (hex) {
            return hex;
        }
        var m = String(value || '').match(/#([0-9a-fA-F]{6})/);
        return m ? ('#' + m[1].toUpperCase()) : '#FFFFFF';
    }

    function cloneColors(src) {
        var out = {};
        var fields = catalog.fields || {};
        Object.keys(fields).forEach(function (key) {
            out[key] = src && src[key] ? src[key] : (catalog.custom[key] || '#000000');
        });
        return out;
    }

    function persistCustom(colors) {
        catalog.custom = Object.assign({}, catalog.custom, colors || {});
        try {
            localStorage.setItem(COLOR_KEY, JSON.stringify(catalog.custom));
        } catch (e) {}
    }

    function persistSkinOverride(id, tokens) {
        catalog.skinOverrides = catalog.skinOverrides || {};
        catalog.skinOverrides[id] = Object.assign({}, catalog.skinOverrides[id] || {}, tokens);
        try {
            localStorage.setItem(SKIN_OVER_KEY, JSON.stringify(catalog.skinOverrides));
        } catch (e) {}
    }

    function currentWorkspaceColors() {
        return cloneColors(colorsFor(catalog.active || 'custom'));
    }

    function paintWorkspacePreview(colors) {
        colors = colors || currentWorkspaceColors();
        var preview = document.getElementById('ameDsPreview');
        if (!preview) {
            return;
        }
        preview.style.background = colors.bg_color || '#EAF4FF';
        var top = preview.querySelector('.ame-ds-preview-top');
        if (top) {
            top.style.background = colors.header_color || '#0B1E4D';
            top.style.color = colors.gold_color || '#C9A227';
        }
        var brand = document.getElementById('ameDsPreviewBrand');
        if (brand) {
            brand.textContent = catalog.shopName || 'APONSHOP';
            brand.style.color = colors.gold_color || '#C9A227';
        }
        var clock = document.getElementById('ameDsPreviewClock');
        if (clock) {
            clock.style.color = colors.clock_color || '#2DD4BF';
        }
        var btn = document.getElementById('ameDsPreviewBtn');
        if (btn) {
            btn.style.background = colors.accent_color || '#1D9BF0';
            btn.style.color = '#fff';
        }
        var gold = document.getElementById('ameDsPreviewGold');
        if (gold) {
            gold.style.background = colors.gold_color || '#C9A227';
            gold.style.color = colors.header_color || '#0B1E4D';
        }
        var danger = document.getElementById('ameDsPreviewDanger');
        if (danger) {
            danger.style.background = colors.danger_color || '#D32F2F';
            danger.style.color = '#fff';
        }
        var note = document.getElementById('ameDsContrast');
        if (note) {
            var t = contrastRatio(colors.text_color, colors.bg_color);
            var h = contrastRatio('#FFFFFF', colors.header_color);
            var grade = function (r) { return r >= 7 ? 'AAA' : (r >= 4.5 ? 'AA' : (catalog.english ? 'low' : 'কম')); };
            note.textContent = catalog.english
                ? ('Text/canvas ' + t.toFixed(1) + ':1 · ' + grade(t) + '   ·   Header/white ' + h.toFixed(1) + ':1 · ' + grade(h))
                : ('লেখা/ক্যানভাস ' + t.toFixed(1) + ':1 · ' + grade(t) + '   ·   হেডার/সাদা ' + h.toFixed(1) + ':1 · ' + grade(h));
        }
        var customCard = document.querySelector('[data-site-palette="custom"] .ame-ds-swatches');
        if (customCard) {
            var keys = ['header_color', 'accent_color', 'gold_color', 'bg_color', 'text_color'];
            var dots = customCard.querySelectorAll('i');
            keys.forEach(function (key, i) {
                if (dots[i] && colors[key]) {
                    dots[i].style.background = colors[key];
                }
            });
        }
    }

    function tokenRowHtml(key, meta, value, kind) {
        meta = meta || { name: key, hint: '' };
        var hex = pickerHex(value);
        return '<label class="ame-ds-token">' +
            '<input type="color" data-ame-token="' + key + '" data-ame-token-kind="' + kind + '" value="' + hex + '" aria-label="' + (meta.name || key) + '">' +
            '<span class="ame-ds-token-meta"><strong>' + (meta.name || key) + '</strong><small>' + (meta.hint || '') + '</small></span>' +
            '<input class="ame-ds-token-hex" data-ame-hex="' + key + '" data-ame-token-kind="' + kind + '" value="' + hex + '" maxlength="7" spellcheck="false">' +
            '</label>';
    }

    function fillWorkspaceEditor(colors) {
        var box = document.getElementById('ameDsTokens');
        if (!box) {
            return;
        }
        filling = true;
        colors = colors || currentWorkspaceColors();
        var fields = catalog.fields || {};
        var html = '';
        Object.keys(fields).forEach(function (key) {
            html += tokenRowHtml(key, fields[key], colors[key], 'workspace');
        });
        box.innerHTML = html;
        filling = false;
        paintWorkspacePreview(colors);
    }

    function fillPosEditor(id) {
        var box = document.getElementById('amePosTokens');
        if (!box) {
            return;
        }
        filling = true;
        id = normalize(id || currentFromBody() || catalog.skin || 'workshop');
        var tokens = skinMeta(id).tokens || {};
        var fields = catalog.posFields || {};
        var html = '';
        Object.keys(fields).forEach(function (key) {
            html += tokenRowHtml(key, fields[key], tokens[key], 'pos');
        });
        box.innerHTML = html;
        filling = false;
    }

    function readEditorColors(kind) {
        var out = {};
        document.querySelectorAll('input[data-ame-token-kind="' + kind + '"][data-ame-token]').forEach(function (el) {
            out[el.getAttribute('data-ame-token')] = pickerHex(el.value);
        });
        return out;
    }

    function syncTokenPair(key, hex, kind) {
        hex = pickerHex(hex);
        var colorEl = document.querySelector('input[data-ame-token="' + key + '"][data-ame-token-kind="' + kind + '"]');
        var hexEl = document.querySelector('input[data-ame-hex="' + key + '"][data-ame-token-kind="' + kind + '"]');
        filling = true;
        if (colorEl && colorEl.value.toUpperCase() !== hex) {
            colorEl.value = hex;
        }
        if (hexEl && hexEl.value.toUpperCase() !== hex) {
            hexEl.value = hex;
        }
        filling = false;
        return hex;
    }

    function onWorkspaceToken(key, hex) {
        if (window.AME_VD_ENGINE === 1) {
            return;
        }
        hex = syncTokenPair(key, hex, 'workspace');
        if (!hex) {
            return;
        }
        var next = currentWorkspaceColors();
        next[key] = hex;
        persistCustom(next);
        applyPalette('custom', { announce: false });
        paintWorkspacePreview(next);
        if (key === 'clock_color') {
            applyClockColor(hex, true);
        }
    }

    function onPosToken(key, hex) {
        if (window.AME_VD_ENGINE === 1) {
            return;
        }
        hex = syncTokenPair(key, hex, 'pos');
        var id = normalize(currentFromBody() || catalog.skin || 'workshop');
        var patch = {};
        patch[key] = hex;
        persistSkinOverride(id, patch);
        injectLiveCss(id);
    }

    function saveWorkspaceToServer() {
        if (window.AME_VD_ENGINE === 1) {
            return;
        }
        var colors = currentWorkspaceColors();
        persistCustom(colors);
        applyPalette('custom', { announce: false });
        var url = catalog.saveUrl;
        if (!url || !catalog.csrf) {
            toast(catalog.english ? 'Saved on this device' : 'এই ডিভাইসে সেভ হয়েছে');
            return;
        }
        var body = new URLSearchParams();
        body.set('ajax', '1');
        body.set('action', 'save');
        body.set('csrf_token', catalog.csrf);
        Object.keys(colors).forEach(function (key) {
            body.set(key, colors[key]);
        });
        fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: body,
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('save');
            }
            toast(catalog.english ? 'Identity saved' : 'পরিচয় সেভ হয়েছে');
        }).catch(function () {
            toast(catalog.english ? 'Saved on this device' : 'এই ডিভাইসে সেভ হয়েছে');
        });
    }

    function resetWorkspacePalette() {
        var id = editSource && editSource !== 'custom' ? editSource : 'atelier';
        var colors = cloneColors(colorsFor(id));
        persistCustom(colors);
        applyPalette(id, { announce: true });
        fillWorkspaceEditor(colors);
    }

    function resetPosSkin() {
        var id = normalize(currentFromBody() || catalog.skin || 'workshop');
        if (catalog.skinOverrides) {
            delete catalog.skinOverrides[id];
            try {
                localStorage.setItem(SKIN_OVER_KEY, JSON.stringify(catalog.skinOverrides));
            } catch (e) {}
        }
        injectLiveCss(id);
        fillPosEditor(id);
        toast(catalog.english ? 'Counter colours restored' : 'কাউন্টার রং ফিরেছে');
    }

    function colorsFor(id) {
        if (!id || id === 'custom') {
            return catalog.custom;
        }
        for (var i = 0; i < catalog.palettes.length; i += 1) {
            if (catalog.palettes[i].id === id) {
                return catalog.palettes[i].colors || {};
            }
        }
        return catalog.custom;
    }

    function applyPalette(id, opts) {
        if (window.AME_VD_ENGINE === 1) {
            return;
        }
        opts = opts || {};
        id = String(id || 'custom');
        var colors = colorsFor(id);
        var root = document.documentElement;
        var map = catalog.varMap || {};
        Object.keys(map).forEach(function (token) {
            var hex = colors[token];
            if (!hex) {
                return;
            }
            (map[token] || []).forEach(function (cssVar) {
                root.style.setProperty(cssVar, hex);
            });
        });
        if (colors.header_color && colors.accent_color) {
            root.style.setProperty(
                '--brand-gradient',
                'linear-gradient(135deg,' + colors.header_color + ',' + colors.accent_color + ')'
            );
        }
        if (colors.bg_color) {
            document.body.style.backgroundColor = colors.bg_color;
        }
        if (colors.text_color) {
            document.body.style.color = colors.text_color;
            root.style.color = colors.text_color;
        }
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta && colors.header_color) {
            meta.setAttribute('content', colors.header_color);
        }
        try {
            localStorage.setItem(PALETTE_KEY, id);
        } catch (e) {}
        writeCookie(PALETTE_COOKIE, id);
        catalog.active = id;
        paintPalettes(id);
        paintWorkspacePreview(colors);
        if (opts.announce !== false) {
            toast(document.documentElement.lang === 'en' ? 'Workspace colours applied' : 'ওয়ার্কস্পেস রং লাগু হয়েছে');
        }
    }

    function normHex(hex) {
        hex = String(hex || '').trim();
        if (/^#[0-9a-fA-F]{3}$/.test(hex)) {
            hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
        }
        return /^#[0-9a-fA-F]{6}$/.test(hex) ? hex.toUpperCase() : '';
    }

    function applyClockColor(hex, persist) {
        if (window.AME_VD_ENGINE === 1) {
            return;
        }
        if (typeof window.ameApplyClockColor === 'function') {
            window.ameApplyClockColor(hex, persist !== false);
        } else {
            hex = normHex(hex);
            if (!hex) {
                return;
            }
            document.documentElement.style.setProperty('--hdr-clock', hex);
            if (persist !== false) {
                try {
                    localStorage.setItem(CLOCK_KEY, hex);
                } catch (e) {}
                writeCookie(CLOCK_COOKIE, hex);
            }
        }
        hex = normHex(hex);
        catalog.clockColor = hex || catalog.clockColor;
        document.querySelectorAll('.ame-clock-chip[data-clock-color]').forEach(function (btn) {
            var on = normHex(btn.getAttribute('data-clock-color')) === catalog.clockColor;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        var studio = document.getElementById('ameClockStudioColor');
        if (studio && catalog.clockColor && studio.value.toUpperCase() !== catalog.clockColor) {
            studio.value = catalog.clockColor;
        }
        var live = document.getElementById('ameClockStudioPreview');
        if (live) {
            live.style.color = catalog.clockColor;
        }
    }

    function sheet() {
        return document.getElementById('ameThemeSheet');
    }

    function closeSidebarIfOpen() {
        var sidebar = document.getElementById('mySidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            if (backdrop) {
                backdrop.classList.remove('active');
            }
        }
    }

    function openSheet(tabName) {
        var el = sheet();
        if (!el) {
            return;
        }
        closeSidebarIfOpen();
        el.hidden = false;
        el.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        if (!tabName && onPosPage()) {
            tabName = 'counter';
        }
        if (tabName) {
            showTab(tabName);
        }
    }

    function closeSheet() {
        var el = sheet();
        if (!el) {
            return;
        }
        el.hidden = true;
        el.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function showTab(name) {
        document.querySelectorAll('[data-ame-ds-tab]').forEach(function (tab) {
            var on = tab.getAttribute('data-ame-ds-tab') === name;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('[data-ame-ds-panel]').forEach(function (panel) {
            var on = panel.getAttribute('data-ame-ds-panel') === name;
            panel.hidden = !on;
            panel.classList.toggle('is-active', on);
        });
    }

    document.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-ame-ds-tab]');
        if (tab) {
            event.preventDefault();
            showTab(tab.getAttribute('data-ame-ds-tab'));
            return;
        }
        var openBtn = event.target.closest('[data-ame-theme-open]');
        if (openBtn) {
            event.preventDefault();
            openSheet(openBtn.getAttribute('data-ame-theme-tab') || '');
            return;
        }
        var closeBtn = event.target.closest('[data-ame-theme-close]');
        if (closeBtn) {
            event.preventDefault();
            closeSheet();
            return;
        }
        var el = sheet();
        if (el && event.target === el) {
            closeSheet();
            return;
        }
        var pal = event.target.closest('[data-site-palette]');
        if (pal) {
            event.preventDefault();
            var palId = pal.getAttribute('data-site-palette');
            if (palId && palId !== 'custom') {
                editSource = palId;
            }
            applyPalette(palId);
            fillWorkspaceEditor(colorsFor(palId));
            return;
        }
        var clockBtn = event.target.closest('[data-clock-color]');
        if (clockBtn) {
            event.preventDefault();
            var persist = !clockBtn.closest('#ameClockEdit');
            applyClockColor(clockBtn.getAttribute('data-clock-color'), persist);
            return;
        }
        var saveBtn = event.target.closest('#ameDsSave');
        if (saveBtn) {
            event.preventDefault();
            saveWorkspaceToServer();
            return;
        }
        var resetBtn = event.target.closest('#ameDsReset');
        if (resetBtn) {
            event.preventDefault();
            resetWorkspacePalette();
            return;
        }
        var posReset = event.target.closest('#amePosReset');
        if (posReset) {
            event.preventDefault();
            resetPosSkin();
            return;
        }
        var btn = event.target.closest('[data-pos-skin]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var skinId = btn.getAttribute('data-pos-skin');
        var sheetOpen = el && el.classList.contains('is-open');
        apply(skinId, { redirect: !sheetOpen, announce: true });
        fillPosEditor(skinId);
        if (!sheetOpen) {
            closeSheet();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSheet();
        }
    });

    document.addEventListener('input', function (event) {
        var t = event.target;
        if (!t) {
            return;
        }
        if (t.id === 'ameClockStudioColor') {
            applyClockColor(t.value, true);
            return;
        }
        if (filling) {
            return;
        }
        var kind = t.getAttribute('data-ame-token-kind');
        var key = t.getAttribute('data-ame-token') || t.getAttribute('data-ame-hex');
        if (!kind || !key) {
            return;
        }
        var hex = t.value;
        if (t.getAttribute('data-ame-hex') && hex.length < 7) {
            return;
        }
        if (kind === 'pos') {
            onPosToken(key, hex);
        } else {
            onWorkspaceToken(key, hex);
        }
    });

    readCatalog();

    var cookieSkin = normalize(readCookie(COOKIE) || '');
    var bodySkin = currentFromBody();
    var lsSkin = '';
    try {
        lsSkin = localStorage.getItem(KEY) || '';
    } catch (e) {}
    var saved = normalize(cookieSkin || bodySkin || lsSkin || catalog.skin || 'workshop');
    apply(saved, { announce: false, redirect: false });

    var palSaved = readCookie(PALETTE_COOKIE) || '';
    try {
        palSaved = palSaved || localStorage.getItem(PALETTE_KEY) || '';
    } catch (e) {}
    palSaved = palSaved || catalog.active || 'custom';
    paintPalettes(palSaved);
    if (window.AME_VD_ENGINE !== 1 && palSaved && palSaved !== 'custom') {
        applyPalette(palSaved, { announce: false });
    }

    if (window.AME_VD_ENGINE !== 1) {
        var clockSaved = null;
        try {
            clockSaved = localStorage.getItem(CLOCK_KEY);
        } catch (e) {}
        applyClockColor(clockSaved || catalog.clockColor || '#2DD4BF', false);
    }
    fillWorkspaceEditor(colorsFor(palSaved));
    fillPosEditor(saved);
    if (palSaved && palSaved !== 'custom') {
        editSource = palSaved;
    }
})();
