(function () {
    var cfg = window.AGR || {};
    var form = document.getElementById('agrForm');
    var sheet = document.getElementById('agrSheet');
    var sign = document.getElementById('agrSign');
    var canvas = document.getElementById('agrPad');
    var msg = document.getElementById('agrMsg');
    var busy = document.getElementById('agrBusy');
    var who = document.getElementById('agrSignWho');
    var ctx = null;
    var drawing = false;
    var role = 'customer';

    if (sign && sign.parentNode !== document.body) {
        document.body.appendChild(sign);
    }
    if (busy && busy.parentNode !== document.body) {
        document.body.appendChild(busy);
    }

    function say(text, bad) {
        if (!msg) return;
        msg.hidden = !text;
        msg.textContent = text || '';
        msg.classList.toggle('is-bad', !!bad);
    }

    function setBusy(on) {
        if (!busy) return;
        busy.hidden = !on;
    }

    async function readJson(res) {
        var text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('সার্ভারের উত্তর পড়া যায়নি।');
        }
    }

    async function postForm(fd) {
        var res = await fetch(cfg.api, { method: 'POST', body: fd, credentials: 'same-origin' });
        var data = await readJson(res);
        if (!data.ok) {
            throw new Error(data.error || 'কাজ হয়নি।');
        }
        return data;
    }

    async function ensureSaved() {
        if (!form || !cfg.canWrite) {
            if (!cfg.id) throw new Error('আগে চুক্তিনামা সংরক্ষণ করুন।');
            return cfg.id;
        }
        var fd = new FormData(form);
        fd.set('do', 'save');
        fd.set('csrf_token', cfg.csrf);
        var data = await postForm(fd);
        cfg.id = data.id;
        var hidden = form.querySelector('[name="agreement_id"]');
        if (hidden) hidden.value = String(data.id);
        return data.id;
    }

    function resizePad() {
        if (!canvas) return;
        var dpr = Math.max(1, window.devicePixelRatio || 1);
        var w = canvas.clientWidth || 320;
        var h = canvas.clientHeight || 280;
        canvas.width = Math.max(1, Math.floor(w * dpr));
        canvas.height = Math.max(1, Math.floor(h * dpr));
        ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#1c3354';
        ctx.lineWidth = 2.6 * dpr;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        drawing = false;
    }

    function point(ev) {
        var rect = canvas.getBoundingClientRect();
        return {
            x: (ev.clientX - rect.left) * (canvas.width / rect.width),
            y: (ev.clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function padBlank() {
        if (!ctx) return true;
        var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        var i;
        for (i = 0; i < data.length; i += 16) {
            if (data[i] < 248 || data[i + 1] < 248 || data[i + 2] < 248) {
                return false;
            }
        }
        return true;
    }

    function openPad(nextRole) {
        role = nextRole;
        if (who) {
            who.textContent = nextRole === 'guarantor' ? 'জামিনদারের স্বাক্ষর' : 'কাস্টমারের স্বাক্ষর';
        }
        sign.hidden = false;
        document.body.classList.add('agr-signing');
        requestAnimationFrame(resizePad);
    }

    function closePad() {
        sign.hidden = true;
        document.body.classList.remove('agr-signing');
    }

    if (canvas) {
        canvas.addEventListener('pointerdown', function (ev) {
            if (!ctx) resizePad();
            drawing = true;
            canvas.setPointerCapture(ev.pointerId);
            var p = point(ev);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ev.preventDefault();
        });
        canvas.addEventListener('pointermove', function (ev) {
            if (!drawing || !ctx) return;
            var p = point(ev);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            ev.preventDefault();
        });
        function stopDraw() { drawing = false; }
        canvas.addEventListener('pointerup', stopDraw);
        canvas.addEventListener('pointercancel', stopDraw);
    }

    var clearBtn = document.getElementById('agrClear');
    var rewriteBtn = document.getElementById('agrRewrite');
    if (clearBtn) clearBtn.addEventListener('click', resizePad);
    if (rewriteBtn) rewriteBtn.addEventListener('click', resizePad);

    async function finishSign() {
        if (padBlank()) {
            say('অনুগ্রহ করে আগে সাদা জায়গায় স্বাক্ষর দিন।', true);
            return;
        }
        setBusy(true);
        try {
            var id = await ensureSaved();
            var fd = new FormData();
            fd.set('do', 'sign');
            fd.set('agreement_id', String(id));
            fd.set('contract_id', String(cfg.contractId || ''));
            fd.set('role', role);
            fd.set('png', canvas.toDataURL('image/png'));
            fd.set('csrf_token', cfg.csrf);
            var data = await postForm(fd);
            var next = 'agreement.php?id=' + encodeURIComponent(data.id);
            if (data.both) next += '&makepdf=1';
            window.location.href = next;
        } catch (err) {
            setBusy(false);
            say(err.message || 'স্বাক্ষর হয়নি।', true);
        }
    }

    var submitBtn = document.getElementById('agrSubmit');
    var completeBtn = document.getElementById('agrComplete');
    if (submitBtn) submitBtn.addEventListener('click', finishSign);
    if (completeBtn) completeBtn.addEventListener('click', finishSign);

    function val(name) {
        if (!form) return '';
        var el = form.querySelector('[name="' + name + '"]');
        return el ? String(el.value || '').trim() : '';
    }

    var cusBtn = document.getElementById('agrSignCustomer');
    var guaBtn = document.getElementById('agrSignGuarantor');
    if (cusBtn) {
        cusBtn.addEventListener('click', function () {
            if (!val('customer_name')) {
                say('কাস্টমারের পূর্ণ নাম দিন।', true);
                return;
            }
            say('');
            openPad('customer');
        });
    }
    if (guaBtn) {
        guaBtn.addEventListener('click', function () {
            if (!val('guarantor_name')) {
                say('জামিনদারের পূর্ণ নাম দিন।', true);
                return;
            }
            say('');
            openPad('guarantor');
        });
    }

    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            setBusy(true);
            ensureSaved().then(function (id) {
                window.location.href = 'agreement.php?id=' + encodeURIComponent(id) + '&ok=saved';
            }).catch(function (err) {
                setBusy(false);
                say(err.message || 'সংরক্ষণ হয়নি।', true);
            });
        });
    }

    function canvasToPdf(shot) {
        var JsPDF = window.jspdf && window.jspdf.jsPDF;
        if (!JsPDF) throw new Error('PDF লাইব্রেরি লোড হয়নি।');
        var pdf = new JsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
        var pageW = pdf.internal.pageSize.getWidth();
        var pageH = pdf.internal.pageSize.getHeight();
        var margin = 8;
        var imgW = pageW - margin * 2;
        var pageCanvasH = shot.width * (pageH - margin * 2) / imgW;
        var srcY = 0;
        var first = true;
        while (srcY < shot.height - 1) {
            if (!first) pdf.addPage();
            first = false;
            var sliceH = Math.min(pageCanvasH, shot.height - srcY);
            var slice = document.createElement('canvas');
            slice.width = shot.width;
            slice.height = Math.max(1, Math.floor(sliceH));
            var sctx = slice.getContext('2d');
            sctx.fillStyle = '#ffffff';
            sctx.fillRect(0, 0, slice.width, slice.height);
            sctx.drawImage(shot, 0, srcY, shot.width, slice.height, 0, 0, shot.width, slice.height);
            pdf.addImage(slice.toDataURL('image/jpeg', 0.86), 'JPEG', margin, margin, imgW, slice.height * imgW / shot.width);
            srcY += slice.height;
        }
        return pdf;
    }

    async function makePdf() {
        if (!sheet || typeof window.html2canvas !== 'function') {
            say('PDF তৈরির সরঞ্জাম লোড হয়নি।', true);
            return;
        }
        setBusy(true);
        say('');
        try {
            if (document.fonts && document.fonts.ready) {
                await document.fonts.ready;
            }
            var shot = await window.html2canvas(sheet, {
                scale: 1.5,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false
            });
            var pdf = canvasToPdf(shot);
            var blob = pdf.output('blob');
            var res = await fetch(cfg.api + '?do=pdf&id=' + encodeURIComponent(cfg.id), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/pdf',
                    'X-CSRF-Token': cfg.csrf
                },
                body: blob
            });
            var data = await readJson(res);
            if (!data.ok) throw new Error(data.error || 'PDF সেভ হয়নি।');
            window.location.href = 'agreement.php?id=' + encodeURIComponent(cfg.id) + '&ok=saved';
        } catch (err) {
            setBusy(false);
            say(err.message || 'PDF হয়নি। আবার চেষ্টা করুন।', true);
        }
    }

    var pdfBtn = document.getElementById('agrMakePdf');
    if (pdfBtn) pdfBtn.addEventListener('click', makePdf);
    if (cfg.makepdf && cfg.both && !cfg.locked) {
        makePdf();
    }

    var printBtn = document.getElementById('agrPrint');
    if (printBtn) printBtn.addEventListener('click', function () { window.print(); });
    if (cfg.print) window.addEventListener('load', function () { window.print(); });

    var shareBtn = document.getElementById('agrShare');
    if (shareBtn) {
        shareBtn.addEventListener('click', async function () {
            var pdfBlob = null;
            var pdfName = (cfg.agreementNo || 'agreement') + '.pdf';
            try {
                var res = await fetch('agreement_file.php?id=' + encodeURIComponent(cfg.id) + '&kind=pdf&dl=1', { credentials: 'same-origin' });
                if (!res.ok) throw new Error('pdf');
                pdfBlob = await res.blob();
                var file = new File([pdfBlob], pdfName, { type: 'application/pdf' });
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({ files: [file] });
                    return;
                }
            } catch (err) {
                if (err && err.name === 'AbortError') return;
            }
            if (pdfBlob) {
                var link = document.createElement('a');
                link.href = URL.createObjectURL(pdfBlob);
                link.download = pdfName;
                document.body.appendChild(link);
                link.click();
                link.remove();
                return;
            }
            window.alert('চুক্তিনামার PDF তৈরি হয়নি — আবার চেষ্টা করুন।');
        });
    }

    var verBtn = document.getElementById('agrVersion');
    if (verBtn) {
        verBtn.addEventListener('click', function () {
            if (!window.confirm('নতুন ভার্সন তৈরি হবে। এই চূড়ান্ত চুক্তিনামা বদলাবে না।')) return;
            setBusy(true);
            var fd = new FormData();
            fd.set('do', 'version');
            fd.set('agreement_id', String(cfg.id));
            fd.set('csrf_token', cfg.csrf);
            postForm(fd).then(function (data) {
                window.location.href = 'agreement.php?id=' + encodeURIComponent(data.id);
            }).catch(function (err) {
                setBusy(false);
                say(err.message || 'নতুন ভার্সন হয়নি।', true);
            });
        });
    }
})();
