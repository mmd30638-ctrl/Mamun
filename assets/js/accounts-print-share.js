(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var bar = document.getElementById('accShareBar');
        var banner = document.querySelector('.acc-print-banner');
        var main = document.getElementById('main-content');
        if (main) {
            if (bar && bar.parentNode !== main) {
                main.insertBefore(bar, main.firstChild);
            }
            if (banner && banner.parentNode !== main) {
                main.insertBefore(banner, main.firstChild);
            }
        }
    });

    function shopName() {
        return window.AME_SHOP_NAME || '';
    }

    function pageRoot() {
        return document.querySelector('.ame-print-root') || document.getElementById('main-content') || document.body;
    }

    function shareText() {
        var root = pageRoot();
        var clone = root.cloneNode(true);
        clone.querySelectorAll('.acc-share-bar, .acc-share-sheet, .no-print, .acc-print-hide, form, script, style').forEach(function (n) {
            n.remove();
        });
        var raw = (clone.innerText || '').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
        if (raw.length > 1800) {
            raw = raw.slice(0, 1800) + '…';
        }
        var parts = [shopName(), 'হিসাব নিকাশ', new Date().toLocaleString('bn-BD')];
        if (raw) {
            parts.push('', raw);
        }
        if (location.href) {
            parts.push('', location.href);
        }
        return parts.join('\n');
    }

    function showMsg(text) {
        var el = document.getElementById('accShareMsg');
        if (!el) return;
        el.hidden = false;
        el.textContent = text;
    }

    window.ameAccPrint = function () {
        window.print();
    };

    window.ameAccShareOpen = function () {
        var sheet = document.getElementById('accShareSheet');
        if (sheet) sheet.hidden = false;
    };

    window.ameAccShareClose = function () {
        var sheet = document.getElementById('accShareSheet');
        if (sheet) sheet.hidden = true;
    };

    window.ameAccShareNative = function () {
        sharePdf('share');
    };

    window.ameAccShareWhatsApp = function () {
        sharePdf('wa');
    };

    window.ameAccShareCopy = function () {
        var text = shareText();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showMsg('লেখা কপি হয়েছে। এখন পেস্ট করুন।');
            }).catch(function () {
                fallbackCopy(text);
            });
            return;
        }
        fallbackCopy(text);
    };

    function fallbackCopy(text) {
        var box = document.createElement('textarea');
        box.value = text;
        box.setAttribute('readonly', '');
        box.style.position = 'fixed';
        box.style.left = '-9999px';
        document.body.appendChild(box);
        box.select();
        try { document.execCommand('copy'); showMsg('লেখা কপি হয়েছে।'); }
        catch (e) { showMsg('কপি হয়নি। হোয়াটসঅ্যাপ দিয়ে পাঠান।'); }
        document.body.removeChild(box);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ameAccShareClose();
    });
    var sheet = document.getElementById('accShareSheet');
    if (sheet) {
        sheet.addEventListener('click', function (e) {
            if (e.target === sheet) ameAccShareClose();
        });
    }

    function sharePdf(action) {
        var id = typeof window.ameMemoResolveTarget === 'function'
            ? window.ameMemoResolveTarget('.ame-print-root')
            : '';
        if (!id || typeof window.ameMemoCapture !== 'function') {
            showMsg('মেমো PDF তৈরি হয়নি — আবার চেষ্টা করুন।');
            return;
        }
        ameAccShareClose();
        var file = 'hisab-' + new Date().toISOString().slice(0, 10);
        window.ameMemoCapture(action || 'share', file, id);
    }

})();
