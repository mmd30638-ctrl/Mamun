(function () {
    'use strict';

    (function loadHome() {
        var p = String(window.location.pathname || '/').replace(/\/{2,}/g, '/');
        if (p === '/' || p === '/index.php' || p === '/index.html') return;
        if (document.querySelector('script[data-ame-if-home]')) return;
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = '/assets/css/inventory-first-home.710.css';
        (document.head || document.documentElement).appendChild(css);
        var script = document.createElement('script');
        script.src = '/assets/js/inventory-first-home.710.js';
        script.defer = true;
        script.setAttribute('data-ame-if-home', '1');
        (document.head || document.documentElement).appendChild(script);
    })();

    function appPath() {
        return String(window.location.pathname || '/').replace(/\/{2,}/g, '/');
    }

    function isLoginPage() {
        return !!document.querySelector('form[action*="process_login"]')
            || appPath() === '/login.php';
    }

    function isHomePath(path) {
        return path === '/' || path === '/index.php' || path === '/m' || path === '/m/';
    }

    function isInnerPage(path) {
        if (isHomePath(path) || path === '/login.php') return false;
        if (/(forgot_password|reset_password|verify)\.php$/i.test(path)) return false;
        if (path.indexOf('/actions/') !== -1 || /\/process_[^/]+\.php$/i.test(path)) return false;
        if (/\/(invoice|memo|slip)(_[a-z0-9-]+)?\.php$/i.test(path)) return false;
        return /\.php$/i.test(path) || path.indexOf('/modules/') === 0;
    }

    function safeAppReferrer() {
        if (!document.referrer) return false;
        try {
            var referrer = new URL(document.referrer);
            var path = referrer.pathname || '/';
            return referrer.origin === window.location.origin
                && (path === '/' || /\.php$/i.test(path) || path.indexOf('/modules/') === 0)
                && path.indexOf('/actions/') === -1
                && !/\/process_[^/]+\.php$/i.test(path);
        } catch (e) {
            return false;
        }
    }

    function currentRoute() {
        return appPath() + String(window.location.search || '');
    }

    function readNavStack() {
        try {
            var parsed = JSON.parse(sessionStorage.getItem('ame_nav_stack') || '[]');
            if (!Array.isArray(parsed)) return [];
            return parsed.filter(function (route) {
                return typeof route === 'string'
                    && route.charAt(0) === '/'
                    && route.indexOf('//') !== 0
                    && route.indexOf('/actions/') === -1
                    && !/\/process_[^/?]+\.php/i.test(route);
            }).slice(-40);
        } catch (e) {
            return [];
        }
    }

    function writeNavStack(stack) {
        try {
            sessionStorage.setItem('ame_nav_stack', JSON.stringify(stack.slice(-40)));
        } catch (e) {}
    }

    function rememberPage() {
        var route = currentRoute();
        var stack = readNavStack();
        if (stack[stack.length - 1] !== route) {
            stack.push(route);
            writeNavStack(stack);
        }
    }

    function goBack() {
        var current = currentRoute();
        var stack = readNavStack();
        while (stack.length && stack[stack.length - 1] === current) {
            stack.pop();
        }
        var previous = stack.length ? stack[stack.length - 1] : '';
        if (previous) {
            writeNavStack(stack);
            window.location.assign(previous);
            return;
        }
        if (safeAppReferrer() && window.history.length > 1) {
            window.history.back();
        } else {
            window.location.assign('/index.php');
        }
    }

    window.ameGoBack = goBack;

    function pageTitle(root) {
        var heading = root.querySelector('h1, h2, h3');
        var title = heading ? String(heading.textContent || '').replace(/\s+/g, ' ').trim() : '';
        if (!title) title = String(document.title || '').replace(/\s+/g, ' ').trim();
        return title || (document.documentElement.lang === 'en' ? 'Application page' : 'অ্যাপ্লিকেশন পেইজ');
    }

    function addPageNavigation() {
        var path = appPath();
        var root = document.getElementById('main-content') || document.body;
        if (!root || !isInnerPage(path) || root.querySelector('.ame-page-nav')) return;
        if (document.body.classList.contains('ame-if-live')) return;

        var nav = document.createElement('nav');
        nav.className = 'ame-page-nav';
        nav.setAttribute('aria-label', 'পেইজ নেভিগেশন');

        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'ame-page-nav__back';
        back.innerHTML = '<span aria-hidden="true">←</span><span>পেছনে</span>';
        back.addEventListener('click', goBack);

        var title = document.createElement('span');
        title.className = 'ame-page-nav__title';
        title.textContent = pageTitle(root);

        var home = document.createElement('a');
        home.className = 'ame-page-nav__home';
        home.href = '/index.php';
        home.innerHTML = '<span aria-hidden="true">⌂</span><span>হোম</span>';

        nav.appendChild(back);
        nav.appendChild(title);
        nav.appendChild(home);
        root.insertBefore(nav, root.firstChild);
    }

    function upgradeLegacyBackButtons() {
        document.querySelectorAll('a[href], button[onclick]').forEach(function (control) {
            var text = String(control.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            var classes = String(control.className || '').toLowerCase();
            var looksBack = /(^|\s)(back|পেছনে|ফিরে)/i.test(text)
                || /(^|[-_\s])(back|return)([-_\s]|$)/i.test(classes);
            var explicitlyHome = /(হোম|home|dashboard|ড্যাশবোর্ড)/i.test(text);
            if (!looksBack || explicitlyHome) return;

            var href = control.getAttribute('href') || '';
            var onclick = control.getAttribute('onclick') || '';
            var pointsHome = /(^|\/)(index\.php)?([?#].*)?$/i.test(href)
                || /(?:location|href)[^;]*\/index\.php/i.test(onclick)
                || href === '#'
                || href === '';
            if (!pointsHome) return;

            control.removeAttribute('onclick');
            if (control.tagName === 'A') control.setAttribute('href', '#');
            control.setAttribute('data-ame-smart-back', '1');
            control.addEventListener('click', function (event) {
                event.preventDefault();
                goBack();
            });
        });
    }

    function markCurrentSidebarLink() {
        var current = appPath();
        document.querySelectorAll('.sidebar-menu-body a[href], .ame-if-side a[href]').forEach(function (link) {
            var href = link.getAttribute('href') || '';
            if (!href || href.charAt(0) !== '/') return;
            var linkPath;
            try {
                linkPath = new URL(href, window.location.origin).pathname;
            } catch (e) {
                return;
            }
            if (linkPath === current) {
                link.classList.add('active', 'on');
                var list = link.closest('.menu-list');
                if (list) list.classList.remove('hidden');
            }
        });
    }

    function init() {
        if (!document.body || isLoginPage()) return;
        rememberPage();
        document.body.classList.add('ame-premium-ui');
        if (isHomePath(appPath())) {
            document.body.classList.add('ame-home-gallery');
            document.body.classList.remove('ame-if-dash', 'ame-if-live');
        }
        addPageNavigation();
        upgradeLegacyBackButtons();
        markCurrentSidebarLink();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
