(function () {
    var cfg = window.AME_SLIP || {};

    function isEn() {
        return window.AME_LANG === 'en';
    }

    /** bn/en জোড়া থেকে সেটিংসে বাছাই করা ভাষার লেখাটা দেয়। */
    function pick(bn, en) {
        return isEn() ? en : bn;
    }

    function target() {
        return document.querySelector(cfg.target || '#ame-slip');
    }

    function fileBase() {
        return String(cfg.file || 'receipt').replace(/[^\w.\-]+/g, '_');
    }

    function shopName() {
        return cfg.shop || window.AME_SHOP_NAME || '';
    }

    function shareTitle() {
        return cfg.title || (shopName() + ' · receipt');
    }

    function shareText() {
        return String(cfg.text || shareTitle());
    }

    function busyEl() {
        return document.getElementById('ameSlipBusy');
    }

    function setBusy(on, msg) {
        var el = busyEl();
        if (el) {
            el.hidden = !on;
            if (msg) el.textContent = msg;
        }
        document.querySelectorAll('.ame-slip-btn').forEach(function (btn) {
            btn.disabled = !!on;
        });
    }

    function showMsg(text) {
        var el = document.getElementById('ameSlipMsg');
        if (!el) return;
        el.hidden = false;
        el.textContent = text;
    }

    function libsOk() {
        return typeof html2canvas === 'function';
    }

    function capture() {
        var el = target();
        if (!el) {
            return Promise.reject(new Error('slip missing'));
        }
        if (!libsOk()) {
            return Promise.reject(new Error('html2canvas missing'));
        }
        return html2canvas(el, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
            scrollX: 0,
            scrollY: -window.scrollY,
            ignoreElements: function (element) {
                if (element.classList && (element.classList.contains('no-print') || element.classList.contains('no-memo-print'))) {
                    return true;
                }
                return false;
            }
        });
    }

    function clickDownload(href, name) {
        var a = document.createElement('a');
        a.href = href;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        if (href.indexOf('blob:') === 0) {
            setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
        }
    }

    function canvasToPdf(canvas) {
        var jsPDFLib = window.jspdf && window.jspdf.jsPDF;
        if (!jsPDFLib) {
            throw new Error('jspdf missing');
        }
        var pdf = new jsPDFLib({ orientation: 'p', unit: 'mm', format: 'a4' });
        var pageW = pdf.internal.pageSize.getWidth();
        var pageH = pdf.internal.pageSize.getHeight();
        var margin = 8;
        var imgW = pageW - margin * 2;
        var imgH = canvas.height * imgW / canvas.width;
        if (imgH <= pageH - margin * 2) {
            pdf.addImage(canvas.toDataURL('image/png'), 'PNG', margin, margin, imgW, imgH);
            return pdf;
        }
        var pageCanvasH = canvas.width * (pageH - margin * 2) / imgW;
        var srcY = 0;
        var first = true;
        while (srcY < canvas.height - 1) {
            if (!first) {
                pdf.addPage();
            }
            first = false;
            var sliceH = Math.min(pageCanvasH, canvas.height - srcY);
            var slice = document.createElement('canvas');
            slice.width = canvas.width;
            slice.height = Math.max(1, Math.floor(sliceH));
            var ctx = slice.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, slice.width, slice.height);
            ctx.drawImage(canvas, 0, srcY, canvas.width, slice.height, 0, 0, canvas.width, slice.height);
            var sliceImgH = slice.height * imgW / canvas.width;
            pdf.addImage(slice.toDataURL('image/png'), 'PNG', margin, margin, imgW, sliceImgH);
            srcY += slice.height;
        }
        return pdf;
    }

    function failPrint() {
        setBusy(false);
        window.print();
    }

    window.ameSlipPrint = function () {
        window.print();
    };

    window.ameSlipPdf = function () {
        setBusy(true, pick('PDF তৈরি হচ্ছে…', 'Preparing PDF…'));
        capture().then(function (canvas) {
            var pdf = canvasToPdf(canvas);
            pdf.save(fileBase() + '.pdf');
            setBusy(false);
        }).catch(function () {
            failPrint();
        });
    };

    window.ameSlipImage = function () {
        setBusy(true, pick('ছবি তৈরি হচ্ছে…', 'Preparing image…'));
        capture().then(function (canvas) {
            clickDownload(canvas.toDataURL('image/png'), fileBase() + '.png');
            setBusy(false);
        }).catch(function () {
            failPrint();
        });
    };

    window.ameSlipShareOpen = function () {
        var sheet = document.getElementById('ameSlipSheet');
        if (sheet) sheet.hidden = false;
    };

    window.ameSlipShareClose = function () {
        var sheet = document.getElementById('ameSlipSheet');
        if (sheet) sheet.hidden = true;
    };

    function shareFile(blob, kind) {
        var name = fileBase() + (kind === 'pdf' ? '.pdf' : '.png');
        var type = kind === 'pdf' ? 'application/pdf' : 'image/png';
        var file = new File([blob], name, { type: type });
        /* শুধু ছবি/PDF ফাইলটাই শেয়ার হবে — title/text দিলে হোয়াটসঅ্যাপ ইত্যাদিতে
           ছবির সাথে আলাদা ক্যাপশন টেক্সটও জুড়ে যায়, যেটা এখানে চাওয়া হচ্ছে না। */
        var payload = { files: [file] };
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            return navigator.share(payload);
        }
        if (navigator.share) {
            return navigator.share(payload).catch(function () {
                return navigator.share({ title: shareTitle(), text: shareText(), url: location.href });
            });
        }
        return Promise.reject(new Error('share unsupported'));
    }

    window.ameSlipShareNative = function () {
        setBusy(true, pick('শেয়ারের জন্য ছবি তৈরি হচ্ছে…', 'Preparing image to share…'));
        capture().then(function (canvas) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) reject(new Error('blob'));
                    else resolve(blob);
                }, 'image/png');
            });
        }).then(function (blob) {
            return shareFile(blob, 'png').then(function () {
                ameSlipShareClose();
            }).catch(function () {
                clickDownload(URL.createObjectURL(blob), fileBase() + '.png');
                showMsg(pick('ছবি নামানো হয়েছে — এখন যেকোনো অ্যাপে পাঠান।', 'Image downloaded — now send it from any app.'));
            });
        }).catch(function () {
            showMsg(pick('শেয়ার হয়নি। PDF বা ছবি নামিয়ে পাঠান।', 'Share failed. Please download the PDF or image and send it.'));
        }).then(function () {
            setBusy(false);
        });
    };

    window.ameSlipShareWhatsApp = function () {
        setBusy(true, pick('হোয়াটসঅ্যাপের জন্য ছবি তৈরি হচ্ছে…', 'Preparing image for WhatsApp…'));
        capture().then(function (canvas) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) reject(new Error('blob'));
                    else resolve(blob);
                }, 'image/png');
            });
        }).then(function (blob) {
            return shareFile(blob, 'png').then(function () {
                ameSlipShareClose();
            }).catch(function () {
                clickDownload(URL.createObjectURL(blob), fileBase() + '.png');
                window.open('https://wa.me/?text=' + encodeURIComponent(shareText()), '_blank', 'noopener');
                showMsg(pick('ছবি নামানো হয়েছে — হোয়াটসঅ্যাপে অ্যাটাচ করুন।', 'Image downloaded — attach it in WhatsApp.'));
            });
        }).catch(function () {
            window.open('https://wa.me/?text=' + encodeURIComponent(shareText()), '_blank', 'noopener');
        }).then(function () {
            setBusy(false);
        });
    };

    window.ameSlipShareTelegram = function () {
        setBusy(true, pick('টেলিগ্রামের জন্য ছবি প্রস্তুত হচ্ছে…', 'Preparing image for Telegram…'));
        capture().then(function (canvas) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) reject(new Error('blob'));
                    else resolve(blob);
                }, 'image/png');
            });
        }).then(function (blob) {
            return shareFile(blob, 'png').then(function () {
                ameSlipShareClose();
            }).catch(function () {
                clickDownload(URL.createObjectURL(blob), fileBase() + '.png');
                window.open('https://t.me/share/url?text=' + encodeURIComponent(shareText()) + '&url=' + encodeURIComponent(location.href), '_blank', 'noopener');
                showMsg(pick('ছবি নামানো হয়েছে — টেলিগ্রামে অ্যাটাচ করুন।', 'Image downloaded — attach it in Telegram.'));
            });
        }).catch(function () {
            window.open('https://t.me/share/url?text=' + encodeURIComponent(shareText()) + '&url=' + encodeURIComponent(location.href), '_blank', 'noopener');
        }).then(function () {
            setBusy(false);
        });
    };

    window.ameSlipShareCopy = function () {
        var text = shareText();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showMsg(pick('লেখা কপি হয়েছে। এখন পেস্ট করুন।', 'Text copied. Now paste it.'));
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
        try {
            document.execCommand('copy');
            showMsg(pick('লেখা কপি হয়েছে।', 'Text copied.'));
        } catch (e) {
            showMsg(pick('কপি হয়নি। হোয়াটসঅ্যাপ দিয়ে পাঠান।', 'Copy failed. Please send it via WhatsApp.'));
        }
        document.body.removeChild(box);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            ameSlipShareClose();
        }
    });
    var sheet = document.getElementById('ameSlipSheet');
    if (sheet) {
        sheet.addEventListener('click', function (e) {
            if (e.target === sheet) {
                ameSlipShareClose();
            }
        });
    }
})();

/* সব পুরনো slip action এখন একই PDF memo-image engine ব্যবহার করে। */
(function () {
    function run(action) {
        var cfg = window.AME_SLIP || {};
        var selector = cfg.target || '#ame-slip';
        var id = typeof window.ameMemoResolveTarget === 'function'
            ? window.ameMemoResolveTarget(selector)
            : '';
        if (!id || typeof window.ameMemoCapture !== 'function') {
            window.alert('মেমো তৈরি হয়নি — আবার চেষ্টা করুন।');
            return;
        }
        window.ameMemoCapture(action, String(cfg.file || 'receipt'), id);
    }

    window.ameSlipPdf = function () { run('download'); };
    window.ameSlipImage = function () { run('download-image'); };
    window.ameSlipShareNative = function () {
        if (typeof window.ameSlipShareClose === 'function') window.ameSlipShareClose();
        run('share');
    };
    window.ameSlipShareWhatsApp = function () {
        if (typeof window.ameSlipShareClose === 'function') window.ameSlipShareClose();
        run('wa');
    };
    window.ameSlipShareTelegram = function () {
        if (typeof window.ameSlipShareClose === 'function') window.ameSlipShareClose();
        run('share-telegram');
    };
})();
