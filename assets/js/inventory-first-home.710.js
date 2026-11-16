(function () {
    'use strict';
    if (window.__ameIfSkin710) return;
    window.__ameIfSkin710 = true;

    var painted = false;
    var EN = [
        ['মালিকের ব্যালেন্স', 'Owner balance'],
        ['তাজা মালিক', 'Fresh statement'],
        ['মালিক স্টেটমেন্ট', 'Owner statement'],
        ['বিক্রয় অর্ডার', 'Sales orders'],
        ['ক্রয় অর্ডার', 'Purchase orders'],
        ['ক্যাটাগরি', 'Categories'],
        ['মালিক / মাল', 'Owners'],
        ['ড্যাশবোর্ড', 'Dashboard'],
        ['বিক্রয়', 'Sales'],
        ['ক্রয়', 'Purchase'],
        ['পাওনা', 'Receivable'],
        ['দেওয়া', 'Payable'],
        ['দেয়া', 'Payable'],
        ['বকেয়া', 'Due'],
        ['বাকি', 'Due'],
        ['নগদ', 'Cash'],
        ['পণ্য', 'Products'],
        ['মালিক', 'Owners'],
        ['রিপোর্ট', 'Reports'],
        ['স্টেটমেন্ট', 'Statement']
    ];

    function path() {
        return String(window.location.pathname || '/').replace(/\/{2,}/g, '/');
    }
    function isLogin() {
        return path() === '/login.php' || !!document.querySelector('form[action*="process_login"]');
    }
    function clean(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }
    function isShopHome() {
        var p = path();
        return p === '/' || p === '/index.php' || p === '/index.html';
    }
    function isHomeDashboard() {
        if (isLogin() || isShopHome()) return false;
        return path().indexOf('/modules/pulse/index.php') !== -1;
    }
    function ensureCss() {
        if (document.getElementById('ame-if-css')) return;
        var link = document.createElement('link');
        link.id = 'ame-if-css';
        link.rel = 'stylesheet';
        link.href = '/assets/css/inventory-first-home.710.css';
        document.head.appendChild(link);
    }
    function englishFor(text) {
        for (var i = 0; i < EN.length; i += 1) {
            if (text.indexOf(EN[i][0]) !== -1) return EN[i][1];
        }
        return '';
    }
    function tag(el, name) {
        if (!el || !el.classList) return;
        el.classList.add(name);
    }

    function skinWelcome(root) {
        var nodes = root.querySelectorAll('h1,h2,h3,.page-title,.welcome,.hm-welcome');
        for (var i = 0; i < nodes.length; i += 1) {
            var t = clean(nodes[i].textContent);
            if (t.indexOf('ফিরে আসুন') === -1 && t.toLowerCase().indexOf('welcome') === -1) continue;
            tag(nodes[i], 'ame-if-hello');
            var wrap = nodes[i].parentElement;
            if (wrap && wrap !== root) tag(wrap, 'ame-if-hero');
            var next = nodes[i].nextElementSibling;
            if (next && next.tagName === 'P') tag(next, 'ame-if-sub');
            return;
        }
    }

    function skinTabs(root) {
        var links = root.querySelectorAll('a');
        var groups = [];
        links.forEach(function (a) {
            var t = clean(a.textContent);
            if (!t || t.length > 36) return;
            if (t.indexOf('ড্যাশবোর্ড') === -1 && t.indexOf('মালিক') === -1 && t.indexOf('ব্যালেন্স') === -1 && t.indexOf('রিপোর্ট') === -1) return;
            tag(a, 'ame-if-tab');
            var parent = a.parentElement;
            if (parent && groups.indexOf(parent) === -1) groups.push(parent);
        });
        groups.forEach(function (g) {
            if (g.querySelectorAll('a.ame-if-tab').length >= 2) tag(g, 'ame-if-tabs');
        });
    }

    function looksLikeKpi(el) {
        if (!el || el.closest('table, form, nav, .ame-if-tabs')) return false;
        var t = clean(el.textContent);
        if (!t || t.length > 72) return false;
        if (!/\d/.test(t)) return false;
        return /মালিক|ক্যাটাগরি|ক্রয়|বিক্রয়|পাওনা|দেওয়া|দেয়া|বাকি|বকেয়া|নগদ|পণ্য|Owner|Product|Purchase|Sales|Cash|Due/.test(t);
    }

    function skinKpis(root) {
        var boxes = root.querySelectorAll('.pd-kpi-box, .rd-kpi-box, .fd-kpi-box, .pf-kpi-box, .hd-card, .earn-kpi, .pulse-kpi, .smt-kpi, .rnt-card, .fam-kpi, .ws-stat, .card, [class*="kpi"]');
        Array.prototype.forEach.call(boxes, function (box) {
            if (looksLikeKpi(box) || box.matches('.pd-kpi-box, .rd-kpi-box, .fd-kpi-box, .pf-kpi-box, .hd-card')) {
                tag(box, 'ame-if-kpi');
            }
        });
        var nodes = root.querySelectorAll('div, article, li, section');
        for (var i = 0; i < nodes.length; i += 1) {
            var el = nodes[i];
            if (el.classList.contains('ame-if-kpi')) continue;
            if (!looksLikeKpi(el)) continue;
            var kids = 0;
            for (var c = 0; c < el.children.length; c += 1) {
                if (looksLikeKpi(el.children[c])) kids += 1;
            }
            if (kids >= 2) {
                tag(el, 'ame-if-kpis');
                continue;
            }
            if (kids === 0 && el.children.length <= 4) tag(el, 'ame-if-kpi');
        }
        var grids = root.querySelectorAll('.pd-kpi-grid, .rd-kpi-grid, .fd-kpi-grid, .pf-kpi-grid, .hd-grid, .pd-summary-grid');
        Array.prototype.forEach.call(grids, function (grid) { tag(grid, 'ame-if-kpis'); });
    }

    function skinTables(root) {
        root.querySelectorAll('table').forEach(function (table) {
            tag(table, 'ame-if-table');
            var wrap = table.closest('.card, .table-responsive, section, article, div');
            if (wrap && wrap !== root && wrap !== document.body) tag(wrap, 'ame-if-card');
        });
        root.querySelectorAll('h1,h2,h3,h4,.card-title').forEach(function (h) {
            var t = clean(h.textContent);
            if (t.indexOf('মালিক স্টেটমেন্ট') !== -1 || t.indexOf('তাজা মালিক') !== -1) {
                tag(h, 'ame-if-section-title');
            }
        });
        root.querySelectorAll('a, button').forEach(function (el) {
            var t = clean(el.textContent);
            if (t.indexOf('স্টেটমেন্ট') !== -1 || t.indexOf('Statement') !== -1) tag(el, 'ame-if-action');
        });
    }

    function skin() {
        if (painted || isLogin() || !document.body) return false;
        if (!isHomeDashboard()) return false;
        ensureCss();
        document.body.classList.add('ame-premium-ui', 'ame-if-dash', 'ame-if-live');
        var root = document.getElementById('main-content') || document.body;
        tag(root, 'ame-if-canvas');
        skinWelcome(root);
        skinTabs(root);
        skinKpis(root);
        skinTables(root);
        painted = true;
        return true;
    }

    function boot() {
        if (skin()) return;
        var n = 0;
        var timer = setInterval(function () {
            n += 1;
            if (skin() || n > 20) clearInterval(timer);
        }, 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', boot);
})();
