(function () {
    function phrases() {
        return window.AME_I18N_PHRASES || {};
    }

    function decorateLangButton() {
        var links = document.querySelectorAll("a.hdr-i-lang");
        for (var i = 0; i < links.length; i += 1) {
            var link = links[i];
            if (link.querySelector(".hdr-lang-label")) {
                continue;
            }
            var label = document.createElement("span");
            label.className = "hdr-lang-label";
            label.textContent = window.AME_LANG === "en" ? "বাং" : "EN";
            link.appendChild(label);
        }
    }

    function longestFirst(map) {
        return Object.keys(map).sort(function (a, b) {
            return b.length - a.length;
        });
    }

    function translateText(text, keys, map) {
        if (!text || !/[\u0980-\u09FF]/.test(text)) {
            return text;
        }
        var out = text;
        for (var i = 0; i < keys.length; i += 1) {
            var bn = keys[i];
            if (bn && out.indexOf(bn) !== -1) {
                out = out.split(bn).join(map[bn]);
            }
        }
        return out;
    }

    function translateTree(root) {
        if (window.AME_LANG !== "en") {
            return;
        }
        var map = phrases();
        var keys = longestFirst(map);
        if (!keys.length || !root) {
            return;
        }
        var skip = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, CODE: 1 };
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
        var node;
        while ((node = walker.nextNode())) {
            var parent = node.parentElement;
            if (!parent || skip[parent.tagName]) {
                continue;
            }
            var next = translateText(node.nodeValue, keys, map);
            if (next !== node.nodeValue) {
                node.nodeValue = next;
            }
        }
        var attrs = ["placeholder", "title", "aria-label", "alt"];
        var els = root.querySelectorAll ? root.querySelectorAll("[placeholder],[title],[aria-label],[alt]") : [];
        for (var e = 0; e < els.length; e += 1) {
            for (var a = 0; a < attrs.length; a += 1) {
                var name = attrs[a];
                if (!els[e].hasAttribute(name)) {
                    continue;
                }
                var val = els[e].getAttribute(name);
                var translated = translateText(val, keys, map);
                if (translated !== val) {
                    els[e].setAttribute(name, translated);
                }
            }
        }
    }

    function boot() {
        decorateLangButton();
        if (window.AME_LANG !== "en") {
            return;
        }
        translateTree(document.body);
        if (window.MutationObserver && document.body) {
            var pending = false;
            var observer = new MutationObserver(function () {
                if (pending) {
                    return;
                }
                pending = true;
                setTimeout(function () {
                    pending = false;
                    translateTree(document.body);
                }, 40);
            });
            observer.observe(document.body, { childList: true, subtree: true, characterData: true });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
