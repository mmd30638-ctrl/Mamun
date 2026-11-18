/* 10.98.693 */
(function () {
    function mount(root) {
        var canvas = root.querySelector('canvas');
        var hidden = root.querySelector('input[name="signature_png"]');
        var clearFlag = root.querySelector('input[name="signature_clear"]');
        var status = root.querySelector('[data-sign-status]');
        if (!canvas || !hidden || !canvas.getContext) {
            return;
        }
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var ink = false;
        var dpr = Math.min(window.devicePixelRatio || 1, 2);

        function paintBlank() {
            var w = canvas.width / dpr;
            var h = canvas.height / dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);
            ctx.strokeStyle = '#111111';
            ctx.lineWidth = 2.8;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }

        function resize() {
            var rect = canvas.getBoundingClientRect();
            var w = Math.max(280, Math.floor(rect.width || 320));
            var h = Math.max(180, Math.floor(rect.height || 200));
            canvas.width = Math.floor(w * dpr);
            canvas.height = Math.floor(h * dpr);
            paintBlank();
        }

        function point(ev) {
            var rect = canvas.getBoundingClientRect();
            return {
                x: ev.clientX - rect.left,
                y: ev.clientY - rect.top
            };
        }

        function down(ev) {
            if (ev.button != null && ev.button !== 0) {
                return;
            }
            drawing = true;
            var p = point(ev);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            if (ev.pointerId != null && canvas.setPointerCapture) {
                try { canvas.setPointerCapture(ev.pointerId); } catch (ignore) {}
            }
            ev.preventDefault();
        }

        function move(ev) {
            if (!drawing) {
                return;
            }
            var p = point(ev);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            ink = true;
            ev.preventDefault();
        }

        function up() {
            if (!drawing) {
                return;
            }
            drawing = false;
            if (!ink) {
                return;
            }
            try {
                hidden.value = canvas.toDataURL('image/png');
            } catch (ignore) {
                hidden.value = '';
            }
            if (clearFlag) {
                clearFlag.value = '0';
            }
            if (status) {
                status.textContent = 'সই রাখা হয়েছে। সেভ করলে চুক্তিতে জমা হবে।';
            }
        }

        function blankBoard() {
            drawing = false;
            ink = false;
            hidden.value = '';
            resize();
        }

        function wipe() {
            blankBoard();
            if (clearFlag) {
                clearFlag.value = '0';
            }
            if (status) {
                status.textContent = 'বোর্ড খালি। নতুন সই না দিলে আগের সই থাকবে।';
            }
        }

        function removeSavedNow(url, cid, btn) {
            var form = root.closest('form');
            var tokenInput = form && form.querySelector('input[name="csrf_token"]');
            var body = new URLSearchParams();
            body.set('id', cid);
            body.set('csrf_token', tokenInput ? tokenInput.value : '');
            btn.disabled = true;
            if (status) {
                status.textContent = 'পূর্বের সই বাতিল হচ্ছে...';
            }
            fetch(url, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        throw new Error((data && data.message) || 'সই বাতিল করা যায়নি।');
                    }
                    blankBoard();
                    if (clearFlag) {
                        clearFlag.value = '0';
                    }
                    var old = root.querySelector('[data-sign-old]');
                    if (old) {
                        old.hidden = true;
                    }
                    var meta = root.querySelector('[data-sign-meta]');
                    if (meta) {
                        meta.textContent = 'পূর্বের সই বাতিল';
                    }
                    btn.hidden = true;
                    root.removeAttribute('data-sign-delete-url');
                    if (status) {
                        status.textContent = data.message;
                    }
                })
                .catch(function (err) {
                    btn.disabled = false;
                    if (status) {
                        status.textContent = (err && err.message) ? err.message : 'সই বাতিল করা যায়নি। আবার চেষ্টা করুন।';
                    }
                });
        }

        function removeSaved(ev) {
            var url = root.getAttribute('data-sign-delete-url');
            var cid = root.getAttribute('data-sign-cid');
            var btn = ev && ev.currentTarget;
            if (url && cid && btn) {
                if (window.confirm('পূর্বের সই বাতিল করবেন? মুছে গেলে আর ফেরত আসবে না।')) {
                    removeSavedNow(url, cid, btn);
                }
                return;
            }
            blankBoard();
            if (clearFlag) {
                clearFlag.value = '1';
            }
            var old = root.querySelector('[data-sign-old]');
            if (old) {
                old.hidden = true;
            }
            if (status) {
                status.textContent = 'সেভ করলে রাখা সই মুছে যাবে।';
            }
        }

        canvas.addEventListener('pointerdown', down);
        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', up);
        canvas.addEventListener('pointercancel', up);
        root.querySelectorAll('[data-sign-wipe]').forEach(function (btn) {
            btn.addEventListener('click', wipe);
        });
        root.querySelectorAll('[data-sign-clear]').forEach(function (btn) {
            btn.addEventListener('click', removeSaved);
        });
        resize();
    }

    document.querySelectorAll('[data-sign-pad]').forEach(mount);
})();
