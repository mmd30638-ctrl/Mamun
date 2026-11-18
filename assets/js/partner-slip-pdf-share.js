(function () {
    'use strict';

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            if (document.querySelector('script[src="' + src + '"]')) {
                resolve();
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = function () {
                resolve();
            };
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function toast(msg) {
        if (window.alert) {
            window.alert(msg);
        }
    }

    async function buildPdfFromElement(el, title) {
        await loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
        await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.2/jspdf.umd.min.js');
        var canvas = await window.html2canvas(el, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
        });
        var jsPDF = window.jspdf.jsPDF;
        var pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
        var pageW = pdf.internal.pageSize.getWidth();
        var pageH = pdf.internal.pageSize.getHeight();
        var imgW = pageW;
        var imgH = (canvas.height * imgW) / canvas.width;
        var imgData = canvas.toDataURL('image/jpeg', 0.92);
        var y = 0;
        pdf.addImage(imgData, 'JPEG', 0, y, imgW, imgH);
        var left = imgH - pageH;
        while (left > 0) {
            y = -(imgH - left);
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, y, imgW, imgH);
            left -= pageH;
        }
        if (title) {
            pdf.setProperties({ title: title });
        }
        return pdf.output('blob');
    }

    async function onPdfShare(btn) {
        var sel = btn.getAttribute('data-pa-pdf-share') || '#ame-slip';
        var file = btn.getAttribute('data-pa-pdf-file') || 'partner-statement';
        var title = btn.getAttribute('data-pa-pdf-title') || 'Partner statement';
        var el = document.querySelector(sel);
        if (!el) {
            toast('স্টেটমেন্ট খুঁজে পাওয়া যায়নি।');
            return;
        }
        btn.disabled = true;
        var old = btn.textContent;
        btn.textContent = 'তৈরি হচ্ছে…';
        try {
            var blob = await buildPdfFromElement(el, title);
            var pdfFile = new File([blob], file.replace(/[^\w\-]+/g, '_') + '.pdf', {
                type: 'application/pdf',
            });
            if (navigator.share && navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
                await navigator.share({
                    title: title,
                    text: title,
                    files: [pdfFile],
                });
            } else {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = pdfFile.name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                toast('PDF ডাউনলোড হয়েছে। WhatsApp বা অন্য অ্যাপ থেকে ফাইল শেয়ার করুন।');
            }
        } catch (err) {
            console.error(err);
            toast('PDF তৈরি বা শেয়ার করা যায়নি। পরে আবার চেষ্টা করুন।');
        } finally {
            btn.disabled = false;
            btn.textContent = old;
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pa-pdf-share]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        onPdfShare(btn);
    });
})();