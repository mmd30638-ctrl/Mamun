'use strict';

(function () {
    var HTML2CANVAS = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    var JSPDF = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-ame-dynamic="' + src + '"]');
            if (existing) {
                if (existing.getAttribute('data-ame-ready') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', function () { reject(new Error('script load failed')); });
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.defer = true;
            s.setAttribute('data-ame-dynamic', src);
            s.onload = function () {
                s.setAttribute('data-ame-ready', '1');
                resolve();
            };
            s.onerror = function () { reject(new Error('script load failed: ' + src)); };
            document.head.appendChild(s);
        });
    }

    function resolveMemoRoot(containerId) {
        if (containerId) {
            var byId = document.getElementById(String(containerId));
            if (byId) {
                return byId;
            }
        }
        var fallback = ['spayMemo', 'receiptCard', 'memo-container', 'invoiceBox', 'ame-slip', 'wsSlip'];
        for (var i = 0; i < fallback.length; i++) {
            var el = document.getElementById(fallback[i]);
            if (el) {
                return el;
            }
        }
        return null;
    }

    function stripNoPrint(node) {
        if (!node) {
            return;
        }
        node.querySelectorAll('.no-memo-print, [data-ame-skip-capture="1"]').forEach(function (n) {
            n.parentNode.removeChild(n);
        });
    }

    function prepareNode(root) {
        var mode = String(root.getAttribute('data-ame-capture') || '').toLowerCase();
        var node = root;
        var cloned = false;
        if (mode === 'clone') {
            node = root.cloneNode(true);
            node.removeAttribute('id');
            var cs = window.getComputedStyle(root);
            node.style.cssText = 'position:fixed;left:-12000px;top:0;z-index:-1;background:#fff;'
                + 'width:' + cs.width + ';max-width:' + cs.maxWidth + ';';
            document.body.appendChild(node);
            cloned = true;
        }
        stripNoPrint(node);
        return {
            node: node,
            cleanup: function () {
                if (cloned && node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }
        };
    }

    function safeBase(name) {
        var s = String(name || 'memo').replace(/[^A-Za-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '');
        return s || 'memo';
    }

    async function capturePng(containerId) {
        await loadScript(HTML2CANVAS);
        if (typeof window.html2canvas !== 'function') {
            throw new Error('html2canvas unavailable');
        }
        var root = resolveMemoRoot(containerId);
        if (!root) {
            throw new Error('memo container missing');
        }
        var prep = prepareNode(root);
        try {
            var scale = Math.min(2.5, Math.max(1.5, window.devicePixelRatio || 1.5));
            var canvas = await window.html2canvas(prep.node, {
                scale: scale,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                logging: false,
                scrollX: 0,
                scrollY: -window.scrollY
            });
            return await new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    resolve(blob);
                }, 'image/png', 0.93);
            });
        } finally {
            prep.cleanup();
        }
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            a.remove();
        }, 800);
    }

    async function pngToPdf(blob, fileBase) {
        await loadScript(JSPDF);
        if (!window.jspdf || !window.jspdf.jsPDF) {
            downloadBlob(blob, safeBase(fileBase) + '.png');
            return;
        }
        var url = URL.createObjectURL(blob);
        try {
            var img = await new Promise(function (resolve, reject) {
                var im = new Image();
                im.onload = function () { resolve(im); };
                im.onerror = reject;
                im.src = url;
            });
            var pxW = img.naturalWidth;
            var pxH = img.naturalHeight;
            var mmW = 80;
            var mmH = Math.max(40, (pxH / pxW) * mmW);
            var PDF = window.jspdf.jsPDF;
            var pdf = new PDF({ unit: 'mm', format: [mmW, mmH] });
            pdf.addImage(img, 'PNG', 0, 0, mmW, mmH);
            pdf.save(safeBase(fileBase) + '.pdf');
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    function openWa(phone, text) {
        var digits = String(phone || '').replace(/\D+/g, '');
        var base = digits ? 'https://wa.me/' + digits : 'https://wa.me/';
        var href = text ? base + '?text=' + encodeURIComponent(text) : base;
        window.open(href, '_blank', 'noopener,noreferrer');
    }

    async function shareWithFiles(blob, fileBase, opts) {
        opts = opts || {};
        var file = new File([blob], safeBase(fileBase) + '.png', { type: 'image/png' });
        if (!navigator.share) {
            return false;
        }
        var payload = { files: [file] };
        if (opts.title) {
            payload.title = opts.title;
        }
        if (opts.text) {
            payload.text = opts.text;
        }
        if (navigator.canShare && !navigator.canShare(payload)) {
            return false;
        }
        await navigator.share(payload);
        return true;
    }

    function printBlob(blob) {
        var url = URL.createObjectURL(blob);
        var w = window.open('', '_blank', 'noopener');
        if (!w) {
            window.print();
            URL.revokeObjectURL(url);
            return;
        }
        w.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print</title>'
            + '<style>html,body{margin:0;padding:0}img{display:block;max-width:100%}</style></head><body>'
            + '<img src="' + url + '" alt="memo" onload="window.focus();window.print();"></body></html>'
        );
        w.document.close();
        setTimeout(function () { URL.revokeObjectURL(url); }, 120000);
    }

    window.ameMemoResolveContainer = resolveMemoRoot;

    window.ameMemoCapture = async function (act, fileBase, containerId, opts) {
        act = String(act || 'share');
        opts = opts || {};
        try {
            var blob = await capturePng(containerId);
            if (!blob) {
                return;
            }

            if (act === 'download-image') {
                downloadBlob(blob, safeBase(fileBase) + '.png');
                return;
            }

            if (act === 'download') {
                await pngToPdf(blob, fileBase);
                return;
            }

            if (act === 'print-image') {
                printBlob(blob);
                return;
            }

            if (act === 'share') {
                var ok = await shareWithFiles(blob, fileBase, {
                    title: safeBase(fileBase),
                    text: opts.text || ''
                });
                if (!ok) {
                    downloadBlob(blob, safeBase(fileBase) + '.png');
                }
                return;
            }

            if (act === 'share-whatsapp') {
                var caption = opts.text || 'পেমেন্ট রসিদ';
                var shared = await shareWithFiles(blob, fileBase, { text: caption, title: safeBase(fileBase) });
                if (!shared) {
                    downloadBlob(blob, safeBase(fileBase) + '.png');
                    var hint = caption + '\n\nরসিদের ছবি ডাউনলোড হয়েছে — হোয়াটসঅ্যাপে 📎 দিয়ে সংযুক্ত করুন।';
                    openWa(opts.phone || opts.waPhone || '', hint);
                }
                return;
            }

            if (act === 'share-telegram') {
                var tgOk = await shareWithFiles(blob, fileBase, {
                    text: opts.text || '',
                    title: safeBase(fileBase)
                });
                if (!tgOk) {
                    downloadBlob(blob, safeBase(fileBase) + '.png');
                    if (opts.telegramUrl) {
                        window.open(opts.telegramUrl, '_blank', 'noopener,noreferrer');
                    }
                }
                return;
            }
        } catch (err) {
            console.error('ameMemoCapture', err);
            window.alert('মেমো তৈরি করা যায়নি। আবার চেষ্টা করুন অথবা প্রিন্ট বাটন ব্যবহার করুন।');
        }
    };
})();