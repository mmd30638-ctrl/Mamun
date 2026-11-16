/**
 * Sales invoice PDF/image capture.
 * Capture a full A4-width clone so logo + text stay large (not phone-shrunk).
 */
(function () {
    var libsPromise = null;
    var A4_CSS_PX = 794;
    function ameMemoLang() {
        var match = document.cookie.match(/(?:^|; )app_lang=([^;]*)/);
        if (match && decodeURIComponent(match[1]).toLowerCase() === 'en') {
            return 'en';
        }
        var htmlLang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
        return htmlLang.indexOf('en') === 0 ? 'en' : 'bn';
    }
    function ameMemoSideText() {
        if (ameMemoLang() === 'en') {
            return '12% special discount on every product and service for 24 hours from Maghrib on the 12th of each Arabic month';
        }
        return 'প্রতি আরবি মাসের ১২ তারিখ মাগরিব থেকে ২৪ ঘন্টা ১২% বিশেষ ডিসকাউন্ট সমস্ত পণ্য ও সেবার উপর';
    }
    function ameMemoEidText() {
        if (ameMemoLang() === 'en') {
            return 'Blessed greetings of Eid';
        }
        return "সাইয়্যিদু সাইয়্যিদিল আ'ইয়াদ শরীফ, মোবারক হো";
    }
    function paintClearLine(el, color, size) {
        if (!el) {
            return;
        }
        el.style.setProperty('color', color, 'important');
        el.style.setProperty('-webkit-text-fill-color', color, 'important');
        el.style.setProperty('text-shadow', 'none', 'important');
        el.style.setProperty('-webkit-text-stroke', '0', 'important');
        el.style.setProperty('paint-order', 'normal', 'important');
        el.style.setProperty('font-weight', '900', 'important');
        el.style.setProperty('font-size', size, 'important');
        el.style.setProperty('line-height', '1.5', 'important');
        el.style.setProperty('letter-spacing', '0', 'important');
        el.style.setProperty('background', '#ffffff', 'important');
        el.style.setProperty('background-color', '#ffffff', 'important');
        el.style.setProperty('display', 'block', 'important');
        el.style.setProperty('width', '100%', 'important');
        el.style.setProperty('max-width', '100%', 'important');
        el.style.setProperty('box-sizing', 'border-box', 'important');
        el.style.setProperty('white-space', 'normal', 'important');
        el.style.setProperty('overflow', 'visible', 'important');
        el.style.setProperty('word-break', 'break-word', 'important');
        el.style.setProperty('padding', '0 8px', 'important');
    }

    function ensureMemoSideFont() {
        if (document.getElementById('ameMemoSideFont')) {
            return;
        }
        var link = document.createElement('link');
        link.id = 'ameMemoSideFont';
        link.rel = 'stylesheet';
        link.href = 'https://fonts.googleapis.com/css2?family=Noto+Serif+Bengali:wght@900&display=swap';
        (document.head || document.documentElement).appendChild(link);
    }

    function placeMemoSide() {
        var boxes = document.querySelectorAll('#memo-container, .memo-wrapper, .tm-card, .invoice-box');
        if (boxes.length) {
            ensureMemoSideFont();
        }
        Array.prototype.forEach.call(boxes, function (box) {
            if (!box) {
                return;
            }
            var side = box.querySelector('.ame-memo-side');
            if (!side) {
                side = document.createElement('div');
                side.className = 'ame-memo-side';
                side.setAttribute('aria-hidden', 'true');
            }
            side.textContent = ameMemoSideText();
            var eid = box.querySelector('.ame-logo-eid');
            if (eid) {
                eid.textContent = ameMemoEidText();
                paintClearLine(eid, '#9B1C1C', '18px');
            }
            if (eid && eid.parentNode) {
                if (side.previousElementSibling !== eid) {
                    eid.parentNode.insertBefore(side, eid.nextSibling);
                }
            } else {
                var head = box.querySelector('.memo-head, .tm-head, .memo-header');
                if (head && side.parentNode !== head) {
                    head.insertBefore(side, head.firstChild);
                } else if (!side.parentNode) {
                    box.insertBefore(side, box.firstChild);
                }
            }
            side.style.setProperty('position', 'static', 'important');
            side.style.setProperty('display', 'block', 'important');
            side.style.setProperty('width', '100%', 'important');
            side.style.setProperty('max-width', '100%', 'important');
            side.style.setProperty('box-sizing', 'border-box', 'important');
            side.style.setProperty('height', 'auto', 'important');
            side.style.setProperty('margin', '0 0 8px', 'important');
            side.style.setProperty('padding', '0 12px 2px', 'important');
            side.style.setProperty('writing-mode', 'horizontal-tb', 'important');
            side.style.setProperty('text-align', 'center', 'important');
            side.style.setProperty('font-family', '"Noto Serif Bengali", "SolaimanLipi", "Noto Sans Bengali", serif', 'important');
            side.style.setProperty('font-size', '16px', 'important');
            side.style.setProperty('font-weight', '800', 'important');
            side.style.setProperty('line-height', '1.5', 'important');
            side.style.setProperty('letter-spacing', '0', 'important');
            side.style.setProperty('white-space', 'normal', 'important');
            side.style.setProperty('overflow', 'visible', 'important');
            side.style.setProperty('left', 'auto', 'important');
            side.style.setProperty('top', 'auto', 'important');
            side.style.setProperty('bottom', 'auto', 'important');
            side.style.setProperty('color', '#0B3A82', 'important');
            side.style.setProperty('-webkit-text-fill-color', '#0B3A82', 'important');
            side.style.setProperty('text-shadow', 'none', 'important');
            box.style.setProperty('--ame-side-top', '0px');
            paintClearLine(box.querySelector('.memo-shop-desc'), '#111827', '15px');
            paintClearLine(box.querySelector('.memo-shop-phone'), '#111827', '15px');
        });
    }
    function ensureMemoClearCss() {
        if (document.getElementById('ameMemoClearCss')) {
            return;
        }
        var link = document.createElement('link');
        link.id = 'ameMemoClearCss';
        link.rel = 'stylesheet';
        link.href = '/assets/css/memo-clear.css?v=10.98.847';
        (document.body || document.head || document.documentElement).appendChild(link);
    }
    ensureMemoClearCss();
    placeMemoSide();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', placeMemoSide);
    }
    window.addEventListener('load', placeMemoSide);

    function isThermalPaper() {
        var body = document.body;
        if (!body) {
            return false;
        }
        var p = String(body.getAttribute('data-paper') || '').toLowerCase();
        if (p === 'a4' || body.classList.contains('ame-paper-a4')) {
            return false;
        }
        /* Xprinter XP-360B: 203 DPI, paper 20–82mm. 80mm roll prints as HTML, not an A4 picture. */
        if (p === '80mm' || p === '58mm' || body.classList.contains('ame-paper-80mm') || body.classList.contains('ame-paper-58mm')) {
            return true;
        }
        if (body.classList.contains('ame-memo-sheet-page')) {
            return false;
        }
        if (document.querySelector('#memo-container.memo-wrapper, .memo-wrapper[data-ame-capture], #memo-container[data-ame-capture]')) {
            return false;
        }
        return true;
    }

    function injectInvoiceType() {
        if (document.getElementById('ameInvoicePrintFix')) {
            return;
        }
        if (!document.getElementById('memo-container') && !document.querySelector('.invoice-box, .memo-wrapper, .tm-card, .receipt')) {
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/assets/css/invoice-print-fix.css?v=10.98.574';
        document.head.appendChild(link);
        var css = document.createElement('style');
        css.id = 'ameInvoicePrintFix';
        if (!isThermalPaper()) {
            css.textContent = [
                'body.ame-paper-a4:has(#memo-container), body.ame-paper-a4:has(.invoice-box){font-size:16px !important;}',
                'body.ame-paper-a4 #memo-container, body.ame-paper-a4 .memo-wrapper, body.ame-paper-a4 .invoice-box{max-width:794px !important;width:100% !important;box-sizing:border-box !important;}',
                'body.ame-paper-a4 img.ame-print-logo, body.ame-paper-a4 .memo-logo{height:96px !important;max-width:280px !important;width:auto !important;}',
                'body.ame-paper-a4 .memo-shop-name, body.ame-paper-a4 .invoice-box .title{font-size:28px !important;}'
            ].join('');
        } else {
            css.textContent = '/* thermal sizes live in invoice-print-fix.css 10.98.574 */';
        }
        document.head.appendChild(css);
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-ame-src="' + src + '"]');
            if (existing) {
                if (existing.dataset.ready === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', reject);
                return;
            }
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.dataset.ameSrc = src;
            script.onload = function () {
                script.dataset.ready = '1';
                resolve();
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    function libs() {
        if (typeof window.html2canvas === 'function' && pdfJsCtor()) {
            return Promise.resolve();
        }
        if (!libsPromise) {
            libsPromise = Promise.all([
                typeof window.html2canvas === 'function' ? Promise.resolve() : loadScript('/assets/js/html2canvas.min.js'),
                pdfJsCtor() ? Promise.resolve() : loadScript('/assets/js/jspdf.umd.min.js')
            ]);
        }
        return libsPromise;
    }

    function downloadBlob(blob, name) {
        if (!blob) {
            return;
        }
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = name || 'memo.bin';
        link.rel = 'noopener';
        link.target = '_blank';
        link.style.display = 'none';
        document.body.appendChild(link);
        try {
            link.click();
        } catch (e) {}
        window.setTimeout(function () {
            if (link.parentNode) {
                link.parentNode.removeChild(link);
            }
            URL.revokeObjectURL(url);
        }, 60000);
    }

    function fileFromBlob(blob, name, type) {
        try {
            return new File([blob], name, { type: type || blob.type || 'application/octet-stream' });
        } catch (e) {
            return null;
        }
    }

    function pdfFileOf(blob, name) {
        try {
            return new File([blob], name || 'memo.pdf', { type: 'application/pdf' });
        } catch (e) {
            return blob;
        }
    }

    function sharePdfNow(blob, name) {
        if (!blob) {
            return Promise.resolve();
        }
        var file = pdfFileOf(blob, name);
        var payload = { files: [file] };
        if (typeof navigator.share === 'function') {
            return navigator.share(payload).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
            });
        }
        return Promise.resolve();
    }

    function offerPdfSave(blob, name) {
        offerPdfReady(blob, name);
    }

    function offerPdfReady(blob, name) {
        if (!blob) {
            return;
        }
        name = name || 'memo.pdf';
        window.__ameShareImg = blob;
        window.__ameShareName = name;
        var old = document.getElementById('amePdfSheet');
        if (old && old.parentNode) {
            old.parentNode.removeChild(old);
        }
        var sheet = document.createElement('div');
        sheet.id = 'amePdfSheet';
        sheet.className = 'no-print';
        sheet.style.cssText = 'position:fixed;left:0;right:0;bottom:0;top:0;z-index:10003;display:flex;align-items:flex-end;justify-content:center;background:rgba(15,23,42,.5);padding:12px;';
        var card = document.createElement('div');
        card.style.cssText = 'width:min(420px,100%);background:#fff;border-radius:16px 16px 12px 12px;padding:16px;';
        card.innerHTML = '<strong style="display:block;margin:0 0 6px;font-size:17px;">পিডিএফ তৈরি হয়েছে</strong>'
            + '<p style="margin:0 0 12px;font-size:13px;color:#334155;line-height:1.45;">এখন শেয়ার চাপুন — টেলিগ্রাম বা হোয়াটসঅ্যাপে <b>ডকুমেন্ট</b> হিসেবে যাবে, লেখা ঝাপসা হবে না।</p>';
        function btn(label, color, fn) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            b.style.cssText = 'display:block;width:100%;margin:0 0 8px;min-height:48px;padding:12px;border:0;border-radius:12px;background:' + color + ';color:#fff;font:700 16px sans-serif;cursor:pointer;';
            b.addEventListener('click', fn);
            card.appendChild(b);
            return b;
        }
        btn('শেয়ার করুন', '#04933A', function () {
            sharePdfNow(blob, name);
        });
        var dl = document.createElement('a');
        dl.href = URL.createObjectURL(blob);
        dl.download = name;
        dl.rel = 'noopener';
        dl.textContent = 'পিডিএফ নামান';
        dl.style.cssText = 'display:block;width:100%;margin:0 0 8px;min-height:48px;padding:12px;border:0;border-radius:12px;background:#003F8F;color:#fff;font:700 16px sans-serif;cursor:pointer;text-align:center;text-decoration:none;box-sizing:border-box;';
        card.appendChild(dl);
        btn('পিডিএফ খুলুন', '#4F46E5', function () {
            window.open(dl.href, '_blank', 'noopener');
        });
        btn('বন্ধ', '#111827', function () {
            if (sheet.parentNode) {
                sheet.parentNode.removeChild(sheet);
            }
        });
        sheet.appendChild(card);
        sheet.addEventListener('click', function (e) {
            if (e.target === sheet && sheet.parentNode) {
                sheet.parentNode.removeChild(sheet);
            }
        });
        document.body.appendChild(sheet);
    }

    function pdfJsCtor() {
        if (window.jspdf && typeof window.jspdf.jsPDF === 'function') {
            return window.jspdf.jsPDF;
        }
        if (typeof window.jsPDF === 'function') {
            return window.jsPDF;
        }
        return null;
    }

    function pdfBlobSimple(canvas) {
        var JsPDF = pdfJsCtor();
        if (typeof JsPDF !== 'function') {
            throw new Error('jspdf-missing');
        }
        var pdf = new JsPDF({
            unit: 'mm',
            format: 'a4',
            orientation: 'portrait',
            compress: true
        });
        var pageW = pdf.internal.pageSize.getWidth();
        var pageH = pdf.internal.pageSize.getHeight();
        var margin = 8;
        var imgW = pageW - margin * 2;
        var srcH = canvas.height;
        var pageImgH = (pageH - margin * 2) * canvas.width / imgW;
        var canvasPage = document.createElement('canvas');
        var ctx = canvasPage.getContext('2d');
        var slicePx = Math.max(1, Math.floor(pageImgH));
        var sy = 0;
        var first = true;
        while (sy < srcH) {
            var sh = Math.min(slicePx, srcH - sy);
            canvasPage.width = canvas.width;
            canvasPage.height = sh;
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, sh);
            ctx.drawImage(canvas, 0, sy, canvas.width, sh, 0, 0, canvas.width, sh);
            var slice = canvasPage.toDataURL('image/jpeg', 0.92);
            var sliceHmm = imgW * sh / canvas.width;
            if (!first) {
                pdf.addPage('a4', 'portrait');
            }
            first = false;
            pdf.addImage(slice, 'JPEG', margin, margin, imgW, sliceHmm, undefined, 'FAST');
            sy += sh;
        }
        return pdf.output('blob');
    }

    function asciiBytes(s) {
        var a = new Uint8Array(s.length);
        var i;
        for (i = 0; i < s.length; i++) {
            a[i] = s.charCodeAt(i) & 0xff;
        }
        return a;
    }

    function joinBytes(list) {
        var n = 0;
        var i;
        var o = 0;
        for (i = 0; i < list.length; i++) {
            n += list[i].length;
        }
        var out = new Uint8Array(n);
        for (i = 0; i < list.length; i++) {
            out.set(list[i], o);
            o += list[i].length;
        }
        return out;
    }

    function pad10(n) {
        var s = String(n);
        while (s.length < 10) {
            s = '0' + s;
        }
        return s;
    }

    function canvasJpegBytes(canvas, quality) {
        return new Promise(function (resolve, reject) {
            var q = quality == null ? 0.95 : quality;
            var finish = function (blob) {
                if (!blob) {
                    reject(new Error('jpeg'));
                    return;
                }
                var reader = new FileReader();
                reader.onload = function () {
                    resolve(new Uint8Array(reader.result));
                };
                reader.onerror = reject;
                reader.readAsArrayBuffer(blob);
            };
            if (canvas && typeof canvas.toBlob === 'function') {
                try {
                    canvas.toBlob(finish, 'image/jpeg', q);
                    return;
                } catch (e) {}
            }
            try {
                var data = canvas.toDataURL('image/jpeg', q);
                var bin = atob(data.split(',')[1]);
                var u = new Uint8Array(bin.length);
                var i;
                for (i = 0; i < bin.length; i++) {
                    u[i] = bin.charCodeAt(i);
                }
                resolve(u);
            } catch (e2) {
                reject(e2);
            }
        });
    }

    function pdfFromJpegPages(pages) {
        var pageW = 595.28;
        var pageH = 841.89;
        var chunks = [];
        var offset = 0;
        var xref = [0];
        function add(x) {
            if (typeof x === 'string') {
                x = asciiBytes(x);
            }
            chunks.push(x);
            offset += x.length;
        }
        function objStart() {
            xref.push(offset);
        }
        var n = pages.length;
        var i;
        var kids = [];
        for (i = 0; i < n; i++) {
            kids.push((3 + i * 3) + ' 0 R');
        }
        add('%PDF-1.4\n%\x80\x80\x80\x80\n');
        objStart();
        add('1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n');
        objStart();
        add('2 0 obj<< /Type /Pages /Count ' + n + ' /Kids [' + kids.join(' ') + '] >>endobj\n');
        for (i = 0; i < n; i++) {
            var pageNo = 3 + i * 3;
            var imgNo = pageNo + 1;
            var contentNo = pageNo + 2;
            var p = pages[i];
            var margin = 18;
            var fitW = pageW - margin * 2;
            var fitH = fitW * p.h / p.w;
            if (fitH > pageH - margin * 2) {
                fitH = pageH - margin * 2;
                fitW = fitH * p.w / p.h;
            }
            var x = (pageW - fitW) / 2;
            var y = pageH - margin - fitH;
            var content = 'q ' + fitW.toFixed(2) + ' 0 0 ' + fitH.toFixed(2) + ' ' + x.toFixed(2) + ' ' + y.toFixed(2) + ' cm /Im' + i + ' Do Q\n';
            objStart();
            add(pageNo + ' 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pageW + ' ' + pageH + '] /Resources << /XObject << /Im' + i + ' ' + imgNo + ' 0 R >> >> /Contents ' + contentNo + ' 0 R >>endobj\n');
            objStart();
            add(imgNo + ' 0 obj<< /Type /XObject /Subtype /Image /Width ' + p.w + ' /Height ' + p.h + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + p.data.length + ' >>stream\n');
            add(p.data);
            add('\nendstream\nendobj\n');
            objStart();
            add(contentNo + ' 0 obj<< /Length ' + content.length + ' >>stream\n' + content + 'endstream\nendobj\n');
        }
        var xrefPos = offset;
        add('xref\n0 ' + xref.length + '\n');
        add('0000000000 65535 f \n');
        for (i = 1; i < xref.length; i++) {
            add(pad10(xref[i]) + ' 00000 n \n');
        }
        add('trailer<< /Size ' + xref.length + ' /Root 1 0 R >>\nstartxref\n' + xrefPos + '\n%%EOF\n');
        return new Blob([joinBytes(chunks)], { type: 'application/pdf' });
    }

    function pdfFromCanvas(canvas) {
        var a4h = Math.max(1, Math.floor(canvas.width * 841.89 / 595.28));
        var jobs = [];
        var y = 0;
        while (y < canvas.height) {
            var h = Math.min(a4h, canvas.height - y);
            var slice = document.createElement('canvas');
            slice.width = canvas.width;
            slice.height = h;
            var ctx = slice.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, slice.width, h);
            ctx.drawImage(canvas, 0, y, canvas.width, h, 0, 0, canvas.width, h);
            jobs.push((function (w, hh, c) {
                return canvasJpegBytes(c, 0.95).then(function (data) {
                    return { w: w, h: hh, data: data };
                });
            }(slice.width, h, slice)));
            y += h;
        }
        return Promise.all(jobs).then(function (pages) {
            if (!pages.length) {
                throw new Error('empty-pdf');
            }
            return pdfFromJpegPages(pages);
        }).catch(function () {
            return pdfBlobSimple(canvas);
        });
    }

    function printA4Image(canvas) {
        var img = canvas.toDataURL('image/jpeg', 0.95);
        var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>মেমো</title>'
            + '<style>@page{size:A4;margin:8mm}html,body{margin:0;background:#fff}'
            + 'img{display:block;width:100%;height:auto;max-width:100%}</style></head>'
            + '<body><img src="' + img + '" alt="memo"></body></html>';
        var old = document.getElementById('amePrintFrame');
        if (old && old.parentNode) {
            old.parentNode.removeChild(old);
        }
        var frame = document.createElement('iframe');
        frame.id = 'amePrintFrame';
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;';
        document.body.appendChild(frame);
        var doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
        if (!doc) {
            window.print();
            return;
        }
        doc.open();
        doc.write(html);
        doc.close();
        var go = function () {
            try {
                (frame.contentWindow || window).focus();
                (frame.contentWindow || window).print();
            } catch (e) {
                window.print();
            }
        };
        window.setTimeout(go, 500);
    }

    function waitImages(root) {
        var imgs = Array.prototype.slice.call(root.querySelectorAll('img'));
        if (!imgs.length) {
            return Promise.resolve();
        }
        return Promise.all(imgs.map(function (img) {
            if (img.complete && img.naturalWidth) {
                return Promise.resolve();
            }
            return new Promise(function (resolve) {
                var done = function () { resolve(); };
                img.addEventListener('load', done, { once: true });
                img.addEventListener('error', done, { once: true });
                window.setTimeout(done, 2500);
            });
        }));
    }

    function prepClone(node) {
        var clone = node.cloneNode(true);
        clone.id = 'ame-memo-capture-clone';
        clone.classList.remove('ame-stack-table');
        clone.querySelectorAll('table').forEach(function (table) {
            table.classList.remove('ame-stack-table');
        });
        clone.querySelectorAll('.ame-auto-scroll-wrap').forEach(function (wrap) {
            wrap.style.maxWidth = 'none';
            wrap.style.overflow = 'visible';
        });
        var spayShot = node && node.classList && node.classList.contains('spay-memo');
        clone.style.cssText = [
            'position:absolute',
            'left:0',
            'top:0',
            'z-index:-1',
            'width:' + (spayShot ? '420px' : (A4_CSS_PX + 'px')),
            'max-width:' + (spayShot ? '420px' : (A4_CSS_PX + 'px')),
            'background:#ffffff',
            'padding:' + (spayShot ? '16px 14px' : '28px 32px'),
            'box-sizing:border-box',
            'font-size:16px',
            'line-height:1.5',
            'zoom:1',
            'transform:none',
            'box-shadow:none',
            'overflow:visible'
        ].join(';');
        if (spayShot) {
            clone.style.setProperty('width', '420px', 'important');
            clone.style.setProperty('max-width', '420px', 'important');
            clone.style.setProperty('min-width', '0', 'important');
            clone.style.setProperty('box-sizing', 'border-box', 'important');
            clone.style.setProperty('overflow', 'visible', 'important');
            clone.style.setProperty('padding', '16px 14px', 'important');
        }
        clone.querySelectorAll('img.ame-print-logo, .memo-logo').forEach(function (img) {
            img.style.height = '96px';
            img.style.maxWidth = '280px';
            img.style.width = 'auto';
        });
        var inkShot = node && (node.id === 'memo-container' || node.id === 'spayMemo' || (node.getAttribute && node.getAttribute('data-ame-capture') === 'clone'));
        if (inkShot) {
            clone.setAttribute('data-sales-colors', '1');
        } else {
            clone.querySelectorAll('.memo-foot').forEach(function (el) {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            });
        }
        clone.querySelectorAll('.memo-table, table').forEach(function (el) {
            el.style.fontSize = '16px';
        });
        clone.querySelectorAll('*').forEach(function (el) {
            try {
                if (!el.classList || !el.classList.contains('ame-logo-eid')) {
                    el.style.webkitTextFillColor = 'unset';
                }
                el.style.animation = 'none';
                if (spayShot) {
                    el.style.setProperty('white-space', 'normal', 'important');
                    el.style.setProperty('overflow', 'visible', 'important');
                    el.style.setProperty('overflow-wrap', 'anywhere', 'important');
                    el.style.setProperty('word-break', 'break-word', 'important');
                    el.style.setProperty('max-width', '100%', 'important');
                }
            } catch (e) {}
        });
        clone.querySelectorAll('.ame-logo-eid').forEach(function (el) {
            el.style.animation = 'none';
            el.style.whiteSpace = 'normal';
            el.style.overflow = 'visible';
            el.style.wordBreak = 'break-word';
            el.style.fontWeight = '900';
            el.style.background = '#ffffff';
            el.style.backgroundColor = '#ffffff';
            el.style.backgroundImage = 'none';
            el.style.webkitTextStroke = '0';
            el.style.paintOrder = 'normal';
            paintClearLine(el, '#9B1C1C', '18px');
        });
        if (inkShot) {
            paintSeenColors(node, clone);
        }
        document.body.appendChild(clone);
        return clone;
    }

    function paintSeenColors(source, clone) {
        var from = [source].concat(Array.prototype.slice.call(source.querySelectorAll('*')));
        var to = [clone].concat(Array.prototype.slice.call(clone.querySelectorAll('*')));
        var i;
        var view;
        var color;
        var bg;
        for (i = 0; i < from.length && i < to.length; i++) {
            view = window.getComputedStyle(from[i]);
            if (!view) {
                continue;
            }
            color = view.color;
            bg = view.backgroundColor;
            if (color) {
                to[i].style.setProperty('color', color, 'important');
                to[i].style.setProperty('-webkit-text-fill-color', color, 'important');
            }
            if (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') {
                to[i].style.setProperty('background-color', bg, 'important');
            }
            if (from[i].classList && from[i].classList.contains('memo-customer-name')) {
                to[i].style.setProperty('font-weight', '900', 'important');
            }
        }
    }

    function capture(node) {
        var clone = prepClone(node);
            var scale = 2;
        var spayShot = clone.classList && clone.classList.contains('spay-memo');
        var boxW = spayShot ? Math.ceil(Math.max(clone.scrollWidth, clone.offsetWidth, 320)) : A4_CSS_PX;
        var boxH = Math.ceil(Math.max(clone.scrollHeight, clone.offsetHeight));
        return waitImages(clone).then(function () {
            return window.html2canvas(clone, {
                scale: scale,
                backgroundColor: '#ffffff',
                useCORS: true,
                allowTaint: true,
                logging: false,
                width: boxW,
                windowWidth: boxW,
                height: boxH,
                windowHeight: boxH,
                x: 0,
                y: 0,
                scrollX: 0,
                scrollY: 0,
                ignoreElements: function (element) {
                    if (!element.classList) {
                        return false;
                    }
                    if (element.classList.contains('no-print') || element.classList.contains('no-memo-print')) {
                        return true;
                    }
                    if (!element.classList.contains('memo-foot')) {
                        return false;
                    }
                    var host = element.closest ? element.closest('#ame-memo-capture-clone') : null;
                    return !(host && host.getAttribute('data-sales-colors') === '1');
                }
            });
        }).then(function (canvas) {
            if (clone.parentNode) {
                clone.parentNode.removeChild(clone);
            }
            return canvas;
        }, function (err) {
            if (clone.parentNode) {
                clone.parentNode.removeChild(clone);
            }
            throw err;
        });
    }

    function html2canvasReady() {
        if (typeof window.html2canvas === 'function') {
            return Promise.resolve();
        }
        return loadScript('/assets/js/html2canvas.min.js');
    }

    function sharePayload(title, text, files) {
        /* ছবি থাকলে শুধু ফাইল — title/text দিলে হোয়াটসঅ্যাপ/টেলিগ্রামে মেমোর নিচে ক্যাপশন যায়। */
        if (files && files.length) {
            var fileOnly = { files: files };
            if (navigator.share) {
                return navigator.share(fileOnly);
            }
            return Promise.reject(new Error('share-unavailable'));
        }
        var data = { title: title || 'ইনভয়েস', text: text || title || '' };
        if (navigator.share) {
            return navigator.share(data);
        }
        return Promise.reject(new Error('share-unavailable'));
    }

    function setShareBusy(on, msg) {
        var el = document.getElementById('ameMemoBusy');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ameMemoBusy';
            el.style.cssText = 'position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:10001;background:#111827;color:#fff;padding:10px 16px;border-radius:999px;font:700 13px/1.2 sans-serif;display:none;box-shadow:0 8px 24px rgba(0,0,0,.2);';
            document.body.appendChild(el);
        }
        el.textContent = msg || 'ছবি তৈরি হচ্ছে…';
        el.style.display = on ? 'block' : 'none';
    }

    function captureVisible(node) {
        return window.html2canvas(node, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
            scrollX: 0,
            scrollY: -window.scrollY,
            ignoreElements: function (element) {
                if (!element.classList) {
                    return false;
                }
                return element.classList.contains('no-print')
                    || element.classList.contains('no-memo-print')
                    || element.classList.contains('tm-btns')
                    || element.classList.contains('tm-nav')
                    || element.id === 'ameShareSheet'
                    || element.id === 'ameMemoBusy';
            }
        });
    }

    function captureMemoNode(node) {
        function withTime(job, ms) {
            return Promise.race([
                job,
                new Promise(function (_, reject) {
                    window.setTimeout(function () { reject(new Error('capture-timeout')); }, ms);
                })
            ]);
        }
        var preferClone = !!(node && node.getAttribute && node.getAttribute('data-ame-capture') === 'clone')
            || (node && node.classList && node.classList.contains('memo-wrapper'));
        if (preferClone) {
            return withTime(capture(node), 20000).catch(function () {
                return withTime(capture(node), 12000);
            });
        }
        return withTime(capture(node), 15000).catch(function () {
            return withTime(captureVisible(node), 8000);
        });
    }

    function blobFromCanvas(canvas, type, quality) {
        type = type || 'image/jpeg';
        quality = quality == null ? 0.92 : quality;
        return new Promise(function (resolve, reject) {
            if (!canvas || typeof canvas.toBlob !== 'function') {
                reject(new Error('no-canvas'));
                return;
            }
            var done = function (blob) {
                if (!blob) {
                    reject(new Error('empty-blob'));
                    return;
                }
                resolve(blob);
            };
            try {
                canvas.toBlob(done, type, quality);
            } catch (e) {
                reject(e);
            }
        });
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        return Promise.resolve();
    }

    function openShareSheet(title, text, wa, imgBlob, imgName) {
        var old = document.getElementById('ameShareSheet');
        if (old && old.parentNode) { old.parentNode.removeChild(old); }
        var url = window.location.href;
        var bodyText = (text || title || '') + (url ? ('\n' + url) : '');
        window.__ameShareImg = imgBlob || null;
        window.__ameShareName = imgName || 'memo.png';
        var sheet = document.createElement('div');
        sheet.id = 'ameShareSheet';
        sheet.style.cssText = 'position:fixed;left:0;right:0;bottom:0;top:68px;z-index:9999;display:flex;align-items:flex-end;justify-content:center;background:rgba(15,23,42,.45);padding:12px;';
        var card = document.createElement('div');
        card.style.cssText = 'width:min(420px,100%);background:#fff;border-radius:14px 14px 10px 10px;padding:14px;max-height:86vh;overflow:auto;';
        var isPdfFile = /\.pdf$/i.test(imgName || '');
        card.innerHTML = '<strong style="display:block;margin:0 0 6px;font-size:16px;">শেয়ার করুন</strong>'
            + '<p id="ameShareHint" style="margin:0 0 10px;font-size:12px;color:#64748b;">'
            + (isPdfFile ? 'PDF ডকুমেন্ট যাবে।' : 'শুধু পূর্ণ মেমোর পরিষ্কার ছবি যাবে।')
            + '</p>';
        function opt(label, fn, id) {
            var b = document.createElement('button');
            b.type = 'button';
            if (id) { b.id = id; }
            b.textContent = label;
            b.style.cssText = 'display:block;width:100%;margin:0 0 8px;padding:12px;border:1px solid #dbe3ea;border-radius:8px;background:#f8fafc;font:inherit;font-weight:700;cursor:pointer;text-align:left;';
            b.addEventListener('click', fn);
            card.appendChild(b);
            return b;
        }
        function currentFiles() {
            var blob = window.__ameShareImg;
            if (!blob) { return []; }
            var name = window.__ameShareName || 'memo.pdf';
            var type = blob.type || (/\.pdf$/i.test(name) ? 'application/pdf' : 'image/jpeg');
            return [new File([blob], name, { type: type })];
        }
        opt('ফোনের শেয়ার', function () {
            sharePayload(title, bodyText, currentFiles()).catch(function (err) {
                if (err && err.name === 'AbortError') { return; }
            });
        });
        opt('হোয়াটসঅ্যাপ', function () {
            var files = currentFiles();
            if (files.length) {
                sharePayload(title, '', files).catch(function (err) {
                    if (err && err.name === 'AbortError') { return; }
                });
                return;
            }
            window.open(wa || 'https://wa.me/', '_blank', 'noopener');
        });
        opt('টেলিগ্রাম', function () {
            var files = currentFiles();
            if (files.length) {
                sharePayload(title, '', files).catch(function (err) {
                    if (err && err.name === 'AbortError') { return; }
                });
                return;
            }
            window.open('https://t.me/share/url', '_blank', 'noopener');
        });
        opt('ফেসবুক', function () { window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url), '_blank', 'noopener'); });
        opt('লেখা কপি', function () { copyText(bodyText).then(function () { alert('কপি হয়েছে।'); }); });
        opt(imgBlob ? (isPdfFile ? 'PDF নামান' : 'মেমোর ছবি নামান') : 'ফাইল তৈরি হচ্ছে…', function () {
            if (window.__ameShareImg) {
                downloadBlob(window.__ameShareImg, window.__ameShareName || 'memo.jpg');
            }
        }, 'ameShareDl');
        opt('প্রিন্ট', function () { window.print(); });
        var close = document.createElement('button');
        close.type = 'button';
        close.textContent = 'বন্ধ';
        close.style.cssText = 'display:block;width:100%;margin-top:4px;padding:12px;border:0;border-radius:8px;background:#111827;color:#fff;font:inherit;font-weight:700;cursor:pointer;';
        close.addEventListener('click', function () { if (sheet.parentNode) sheet.parentNode.removeChild(sheet); });
        card.appendChild(close);
        sheet.appendChild(card);
        sheet.addEventListener('click', function (e) { if (e.target === sheet && sheet.parentNode) sheet.parentNode.removeChild(sheet); });
        document.body.appendChild(sheet);
    }

    function attachShareImage(imgBlob, imgName) {
        if (!imgBlob) { return; }
        window.__ameShareImg = imgBlob;
        window.__ameShareName = imgName || 'memo.png';
        var dl = document.getElementById('ameShareDl');
        if (dl) { dl.textContent = /\.pdf$/i.test(imgName || '') ? 'পিডিএফ নামান' : 'ছবি ডাউনলোড'; }
        var hint = document.getElementById('ameShareHint');
        if (hint) {
            hint.textContent = /\.pdf$/i.test(imgName || '')
                ? 'পিডিএফ তৈরি হয়েছে — হোয়াটসঅ্যাপে ডকুমেন্ট হিসেবে যাবে, লেখা স্পষ্ট থাকবে।'
                : 'ছবি তৈরি হয়েছে — শুধু মেমো যাবে।';
        }
    }

    window.ameMemoCanvasCapture = function (canvas, action, fileBase) {
        if (!canvas) {
            return Promise.resolve();
        }
        var wantShareImage = action === 'share' || action === 'wa'
            || action === 'share-telegram' || action === 'tg';
        var wantPdf = action === 'download' || action === 'download-pdf' || action === 'pdf';
        if (wantShareImage) {
            setShareBusy(true, 'মেমোর ছবি তৈরি হচ্ছে…');
            return blobFromCanvas(canvas, 'image/jpeg', 0.94).then(function (blob) {
                setShareBusy(false);
                openShareSheet(document.title || 'মেমো', '', '', blob, (fileBase || 'memo') + '.jpg');
            }).catch(function () {
                setShareBusy(false);
                window.alert('মেমোর ছবি তৈরি হয়নি — আবার চেষ্টা করুন।');
            });
        }
        if (!wantPdf) {
            return blobFromCanvas(canvas, 'image/jpeg', 0.92).then(function (blob) {
                downloadBlob(blob, (fileBase || 'memo') + '.jpg');
            });
        }
        var name = (fileBase || 'memo') + '.pdf';
        setShareBusy(true, 'পিডিএফ তৈরি হচ্ছে…');
        return libs().then(function () {
            return pdfFromCanvas(canvas);
        }).then(function (blob) {
            setShareBusy(false);
            if (!blob) {
                throw new Error('pdf-empty');
            }
            offerPdfReady(blob, name);
        }).catch(function () {
            setShareBusy(false);
            window.alert('পিডিএফ তৈরি হয়নি — আবার চেষ্টা করুন।');
        });
    };

    window.ameMemoResolveTarget = function (preferred) {
        var node = null;
        if (preferred) {
            node = typeof preferred === 'string' ? document.querySelector(preferred) : preferred;
        }
        node = node || document.querySelector('[data-ame-capture="clone"]')
            || document.getElementById('memo-container')
            || document.getElementById('ame-slip')
            || document.getElementById('voucherCapture')
            || document.getElementById('voucherCard')
            || document.getElementById('puShareSlip')
            || document.getElementById('rstkSheet')
            || document.querySelector('.ame-print-root, .memo-wrapper, .invoice-box, .tm-card, .receipt')
            || document.getElementById('main-content');
        if (!node) {
            return '';
        }
        node.setAttribute('data-ame-capture', 'clone');
        if (!node.id) {
            node.id = 'ameMemoUniversalTarget';
        }
        return node.id;
    };

    window.ameMemoCapture = function (action, fileBase, elementId) {
        if (action === 'print' || (action === 'print-image' && isThermalPaper())) {
            window.print();
            return Promise.resolve();
        }
        var node = document.getElementById(elementId || 'memo-container');
        var meta = window.AME_MEMO || {};
        var title = meta.title || document.title || 'ইনভয়েস';
        var text = meta.text || title;
        var wa = meta.wa || '';
        if (!node) {
            if (action === 'share' || action === 'wa' || action === 'share-telegram' || action === 'tg') {
                openShareSheet(title, text, wa, null, (fileBase || 'invoice') + '.png');
                return Promise.resolve();
            }
            return Promise.resolve();
        }
        var wantShareImage = action === 'share' || action === 'wa'
            || action === 'share-telegram' || action === 'tg';
        var wantPdf = action === 'download' || action === 'download-pdf' || action === 'pdf';
        if (wantShareImage) {
            var imageName = (fileBase || 'invoice') + '.jpg';
            setShareBusy(true, 'মেমোর ছবি তৈরি হচ্ছে…');
            return html2canvasReady().then(function () {
                return captureMemoNode(node);
            }).then(function (canvas) {
                return blobFromCanvas(canvas, 'image/jpeg', 0.94);
            }).then(function (imgBlob) {
                setShareBusy(false);
                var file = fileFromBlob(imgBlob, imageName, 'image/jpeg');
                var liveTap = navigator.userActivation && navigator.userActivation.isActive;
                if (file && liveTap && typeof navigator.share === 'function') {
                    navigator.share({ files: [file] }).catch(function (err) {
                        if (err && err.name === 'AbortError') {
                            return;
                        }
                        openShareSheet(title, text, wa, imgBlob, imageName);
                    });
                    return;
                }
                openShareSheet(title, text, wa, imgBlob, imageName);
            }).catch(function () {
                setShareBusy(false);
                window.alert('মেমোর ছবি তৈরি হয়নি — আবার চেষ্টা করুন।');
            });
        }
        if (wantPdf) {
            var pdfNameNow = (fileBase || 'invoice') + '.pdf';
            setShareBusy(true, 'পিডিএফ তৈরি হচ্ছে…');
            return html2canvasReady().then(function () {
                return captureMemoNode(node);
            }).then(function (canvas) {
                return pdfFromCanvas(canvas);
            }).then(function (pdfBlob) {
                setShareBusy(false);
                if (!pdfBlob) {
                    throw new Error('pdf-empty');
                }
                offerPdfReady(pdfBlob, pdfNameNow);
            }).catch(function () {
                setShareBusy(false);
                window.alert('পিডিএফ তৈরি হয়নি — আবার চেষ্টা করুন।');
            });
        }
        setShareBusy(true, 'ছবি তৈরি হচ্ছে…');
        return html2canvasReady().then(function () {
            return captureMemoNode(node);
        }).then(function (canvas) {
            if (action === 'print-image') {
                setShareBusy(false);
                printA4Image(canvas);
                return;
            }
            setShareBusy(false);
            return blobFromCanvas(canvas, 'image/jpeg', 0.92).then(function (imgBlob) {
                downloadBlob(imgBlob, (fileBase || 'invoice') + '.jpg');
            });
        }).catch(function () {
            setShareBusy(false);
            if (action === 'print-image') {
                window.print();
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectInvoiceType);
    } else {
        injectInvoiceType();
    }
})();
