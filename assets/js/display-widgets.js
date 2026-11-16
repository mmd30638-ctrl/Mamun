(function () {
    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function visibleHeight(el) {
        if (!el) { return 0; }
        var style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') {
            return 0;
        }
        return Math.ceil(el.getBoundingClientRect().height) || el.offsetHeight || 0;
    }

    function seatTickerAboveIcons(header) {
        var ticker = document.getElementById('ameTickerBar');
        if (!ticker || !header) {
            return ticker;
        }
        if (window.getComputedStyle(ticker).position === 'fixed') {
            return ticker;
        }
        if (ticker.parentNode !== header) {
            header.insertBefore(ticker, header.firstChild);
        }
        header.style.setProperty('flex-wrap', 'wrap', 'important');
        header.style.setProperty('height', 'auto', 'important');
        ticker.style.setProperty('position', 'relative', 'important');
        ticker.style.setProperty('top', 'auto', 'important');
        ticker.style.setProperty('left', '0', 'important');
        ticker.style.setProperty('width', '100%', 'important');
        ticker.style.setProperty('order', '-10', 'important');
        ticker.style.setProperty('flex', '1 0 100%', 'important');
        return ticker;
    }

    function setChromeOffset() {
        var header = document.querySelector('.master-header-fixed');
        var ticker = seatTickerAboveIcons(header);
        var dock = document.getElementById('ameRunDock');
        var cds = document.querySelectorAll('.ame-tbar-cd');
        var salePage = document.body && document.body.classList.contains('ame-pos-terminal-page');
        if (dock && (ticker || salePage)) {
            dock.style.setProperty('display', 'none', 'important');
        } else if (dock && header && !ticker && !salePage && window.getComputedStyle(dock).position !== 'fixed') {
            if (dock.parentNode !== header) {
                header.insertBefore(dock, header.firstChild);
            }
            header.style.setProperty('flex-wrap', 'wrap', 'important');
            header.style.setProperty('height', 'auto', 'important');
            dock.style.setProperty('display', 'block', 'important');
            dock.style.setProperty('position', 'relative', 'important');
            dock.style.setProperty('top', 'auto', 'important');
            dock.style.setProperty('order', '-10', 'important');
            dock.style.setProperty('width', '100%', 'important');
            dock.style.setProperty('flex', '1 0 100%', 'important');
        }
        var headerH = header ? Math.ceil(header.getBoundingClientRect().bottom) : 0;
        var clock = document.getElementById('hdr-live-clock');
        if (clock) {
            var clockStyle = window.getComputedStyle(clock);
            if (clockStyle.display !== 'none' && clockStyle.visibility !== 'hidden') {
                headerH = Math.max(headerH, Math.ceil(clock.getBoundingClientRect().bottom));
            }
        }
        if (headerH < 1) {
            headerH = visibleHeight(header) || 60;
        }
        var top = headerH;
        var tickerInside = ticker && header && ticker.parentNode === header;
        var dockInside = dock && header && dock.parentNode === header;
        if (!tickerInside && ticker && visibleHeight(ticker)) {
            top += visibleHeight(ticker);
        } else if (!ticker && dock && !dockInside && visibleHeight(dock)) {
            dock.style.setProperty('top', '0px', 'important');
            top += visibleHeight(dock);
        }
        var tickerFixed = ticker && window.getComputedStyle(ticker).position === 'fixed';
        var dockFixed = dock && window.getComputedStyle(dock).position === 'fixed';
        if (tickerFixed || dockFixed) {
            top = headerH;
        }
        var cdTop = top;
        cds.forEach(function (bar) {
            if (bar.style.top !== cdTop + 'px') bar.style.top = cdTop + 'px';
            cdTop += visibleHeight(bar);
        });
        if (window.__ameChromeLock && Math.abs(cdTop - window.__ameChromeLock) < 8) return;
        window.__ameChromeLock = cdTop;
        document.documentElement.style.setProperty('--ame-header-h', headerH + 'px');
        document.documentElement.style.setProperty('--ame-chrome-h', cdTop + 'px');
        var main = document.getElementById('main-content');
        if (main) {
            main.style.setProperty('padding-top', cdTop + 'px', 'important');
        }
    }

    function tickOne(bar) {
        var end = Date.parse(bar.getAttribute('data-end') || '');
        if (!end) return;
        var hide = bar.getAttribute('data-hide') === '1';
        var endedMsg = bar.getAttribute('data-ended') || 'সময় শেষ হয়েছে';
        var left = end - Date.now();
        if (left <= 0) {
            if (hide) {
                bar.style.display = 'none';
                setChromeOffset();
                return;
            }
            var live = bar.querySelector('[data-cd-live]');
            if (live) live.style.display = 'none';
            var done = bar.querySelector('[data-cd-done]');
            if (done) {
                done.style.display = 'block';
                done.textContent = endedMsg;
            }
            return;
        }
        var s = Math.floor(left / 1000);
        var days = Math.floor(s / 86400); s -= days * 86400;
        var hours = Math.floor(s / 3600); s -= hours * 3600;
        var mins = Math.floor(s / 60); s -= mins * 60;
        var map = { days: days, hours: pad(hours), minutes: pad(mins), seconds: pad(s) };
        Object.keys(map).forEach(function (k) {
            var el = bar.querySelector('[data-cd="' + k + '"]');
            if (el) el.textContent = map[k];
        });
    }

    function boot() {
        setChromeOffset();
        var bars = document.querySelectorAll('.ame-tbar-cd');
        bars.forEach(tickOne);
        setInterval(function () {
            bars.forEach(tickOne);
        }, 1000);
        window.addEventListener('resize', function () {
            window.__ameChromeLock = 0;
            setChromeOffset();
        });
        window.addEventListener('orientationchange', function () {
            window.__ameChromeLock = 0;
            setTimeout(setChromeOffset, 320);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
