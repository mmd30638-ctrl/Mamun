(function () {
    var sign = document.getElementById('agrSign');
    var canvas = document.getElementById('agrPad');
    var who = document.getElementById('agrSignWho');
    var ctx = null;
    var drawing = false;
    var role = 'customer';

    if (!sign || !canvas) return;
    if (sign.parentNode !== document.body) document.body.appendChild(sign);

    function resizePad() {
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

    function blank() {
        if (!ctx) return true;
        var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        var i;
        for (i = 0; i < data.length; i += 16) {
            if (data[i] < 248 || data[i + 1] < 248 || data[i + 2] < 248) return false;
        }
        return true;
    }

    function openPad(next) {
        role = next;
        if (who) who.textContent = next === 'guarantor' ? 'জামিনদারের স্বাক্ষর' : 'কাস্টমারের স্বাক্ষর';
        sign.hidden = false;
        document.body.classList.add('agr-signing');
        requestAnimationFrame(resizePad);
    }

    function closePad() {
        sign.hidden = true;
        document.body.classList.remove('agr-signing');
    }

    canvas.addEventListener('pointerdown', function (ev) {
        if (!ctx) resizePad();
        drawing = true;
        try { canvas.setPointerCapture(ev.pointerId); } catch (e) {}
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
    function stop() { drawing = false; }
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);

    var clearBtn = document.getElementById('agrClear');
    var rewriteBtn = document.getElementById('agrRewrite');
    if (clearBtn) clearBtn.addEventListener('click', resizePad);
    if (rewriteBtn) rewriteBtn.addEventListener('click', resizePad);

    function keep() {
        if (blank()) {
            window.alert('অনুগ্রহ করে আগে সাদা জায়গায় স্বাক্ষর দিন।');
            return;
        }
        var url = canvas.toDataURL('image/png');
        var hidden = document.getElementById(role === 'guarantor' ? 'agrGuarantorSign' : 'agrCustomerSign');
        var img = document.getElementById(role === 'guarantor' ? 'agrGuaPreview' : 'agrCusPreview');
        var wait = document.getElementById(role === 'guarantor' ? 'agrGuaWait' : 'agrCusWait');
        if (hidden) hidden.value = url;
        if (img) {
            img.src = url;
            img.hidden = false;
        }
        if (wait) wait.hidden = true;
        closePad();
    }

    var submitBtn = document.getElementById('agrSubmit');
    var completeBtn = document.getElementById('agrComplete');
    if (submitBtn) submitBtn.addEventListener('click', keep);
    if (completeBtn) completeBtn.addEventListener('click', keep);

    var cus = document.getElementById('agrOpenCustomer');
    var gua = document.getElementById('agrOpenGuarantor');
    if (cus) cus.addEventListener('click', function () { openPad('customer'); });
    if (gua) {
        gua.addEventListener('click', function () {
            var name = document.getElementById('agrGName');
            if (name && !String(name.value || '').trim()) {
                window.alert('আগে জামিনদারের পূর্ণ নাম দিন।');
                if (name) name.focus();
                return;
            }
            openPad('guarantor');
        });
    }
})();
