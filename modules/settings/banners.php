<?php
require_once '../../includes/config.php';
admin_only();

$flashOk = (string) ($_SESSION['ban_ok'] ?? $_SESSION['success_message'] ?? '');
$flashErr = (string) ($_SESSION['ban_err'] ?? $_SESSION['error_message'] ?? '');
unset($_SESSION['ban_ok'], $_SESSION['ban_err'], $_SESSION['success_message'], $_SESSION['error_message']);

$banners = $db->query('SELECT id, image_name FROM dashboard_banners ORDER BY id DESC');
$imgDir = dirname(__DIR__, 2) . '/assets/img';

include '../../partials/header.php';
?>
<style>
.ban-wrap{padding:15px;font-family:"Noto Sans Bengali","Segoe UI",sans-serif}
.ban-card{background:#fff !important;border:1px solid #E6ECF4;border-radius:14px;max-width:600px;margin:0 auto;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,.08)}
.ban-topbar{display:flex;height:6px}
.ban-topbar span{flex:1}
.ban-c1{background:#047857} .ban-c2{background:#1D4ED8} .ban-c3{background:#B45309}
.ban-header{text-align:center;padding:20px 15px 15px}
.ban-header h3{margin:0;font-size:19px;color:#047857}
.ban-header small{color:#1D4ED8;font-size:12px;font-weight:700}
.ban-flash{margin:0 20px 12px;padding:10px 12px;border-radius:10px;font-weight:800;background:#fff !important;border:1.5px solid #E6ECF4}
.ban-flash.is-ok{color:#047857;border-color:#86efac}
.ban-flash.is-err{color:#B91C1C;border-color:#FECACA}
.ban-upload-box{margin:0 20px 20px;padding:16px;background:#fff !important;border-radius:10px;border:1px dashed #1D4ED8}
.ban-upload-box label{display:block;font-weight:700;font-size:13px;color:#047857;margin-bottom:8px}
.ban-file-btn,.ban-upload-btn{width:100%;min-height:44px;padding:12px;margin-top:10px;border-radius:8px;font-weight:800;font-size:14px;cursor:pointer;background:#fff !important}
.ban-file-btn{border:1.5px solid #1D4ED8;color:#1D4ED8}
.ban-upload-btn{border:1.5px solid #047857;color:#047857}
.ban-upload-btn:disabled{opacity:.45;cursor:not-allowed}
.ban-fname{display:block;margin-top:8px;font-size:13px;font-weight:700;color:#7C3AED;word-break:break-all}
.ban-preview{display:none;margin-top:10px;width:100%;max-height:160px;object-fit:cover;border-radius:10px;border:1px solid #E6ECF4;background:#fff}
.ban-section-label{font-size:11px;font-weight:800;color:#7C3AED;letter-spacing:.4px;margin:0 20px 12px}
.ban-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 20px 20px}
.ban-item{position:relative;border:1px solid #E6ECF4;padding:5px;border-radius:10px;background:#fff !important}
.ban-item img{width:100%;height:90px;object-fit:cover;border-radius:8px;display:block;background:#fff}
.ban-del-btn{position:absolute;top:8px;right:8px;background:#fff !important;color:#B91C1C;border:1.5px solid #B91C1C;width:28px;height:28px;border-radius:50%;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;cursor:pointer}
.ban-empty{grid-column:1/-1;text-align:center;color:#64748B;font-size:13px;padding:20px}
.ban-back{display:block;text-align:center;margin-top:10px;padding-bottom:20px;color:#1D4ED8;text-decoration:none;font-size:13px;font-weight:700}
.ban-missing{font-size:11px;color:#B91C1C;font-weight:700;padding:6px}
</style>

<div class="ban-wrap">
    <div class="ban-card">
        <div class="ban-topbar"><span class="ban-c1"></span><span class="ban-c2"></span><span class="ban-c3"></span></div>
        <div class="ban-header">
            <h3>ড্যাশবোর্ড স্লাইডার ম্যানেজমেন্ট</h3>
            <small>হোম পেজের ব্যানার স্লাইড যোগ/মুছুন</small>
        </div>

        <?php if ($flashOk !== ''): ?><div class="ban-flash is-ok"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($flashErr !== ''): ?><div class="ban-flash is-err"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

        <form id="banUploadForm" action="process_banners.php" method="POST" enctype="multipart/form-data" class="ban-upload-box" autocomplete="off">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="upload" value="1">
            <label>নতুন স্লাইডার ছবি যোগ করুন (jpg / png / webp, সর্বোচ্চ ২০ MB)</label>
            <input id="banFile" type="file" name="banner_image" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif" hidden>
            <button type="button" class="ban-file-btn" id="banPick">ছবি বাছুন</button>
            <span class="ban-fname" id="banName">এখনো কোনো ছবি বাছা হয়নি</span>
            <img class="ban-preview" id="banPreview" alt="">
            <button type="submit" class="ban-upload-btn" id="banSend">ছবি আপলোড করুন</button>
        </form>

        <div class="ban-section-label">বর্তমান স্লাইডারসমূহ</div>
        <div class="ban-grid">
            <?php if ($banners && $banners->num_rows > 0): while ($row = $banners->fetch_assoc()):
                $fn = basename((string) ($row['image_name'] ?? ''));
                $onDisk = $fn !== '' && is_file($imgDir . '/' . $fn);
            ?>
            <div class="ban-item">
                <?php if ($onDisk): ?>
                <img src="/assets/img/<?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) @filemtime($imgDir . '/' . $fn); ?>" alt="">
                <?php else: ?>
                <div class="ban-missing">ফাইল পাওয়া যায়নি</div>
                <?php endif; ?>
                <form action="process_banners.php" method="post" onsubmit="return confirm('ডিলিট করবেন?');" style="position:absolute;top:8px;right:8px;margin:0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="delete" value="<?php echo (int) $row['id']; ?>">
                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="ban-del-btn">×</button>
                </form>
            </div>
            <?php endwhile; else: ?>
            <div class="ban-empty">এখনো কোনো স্লাইডার যোগ হয়নি।</div>
            <?php endif; ?>
        </div>
        <a href="dashboard_settings.php" class="ban-back">ফিরে যান</a>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('banUploadForm');
    var input = document.getElementById('banFile');
    var pick = document.getElementById('banPick');
    var nameEl = document.getElementById('banName');
    var preview = document.getElementById('banPreview');
    var send = document.getElementById('banSend');
    if (!form || !input || !pick) return;
    var snap = null;
    var pending = null;
    var prevUrl = '';

    function readBuf(file) {
        if (file.arrayBuffer) return file.arrayBuffer();
        return new Promise(function (resolve, reject) {
            var r = new FileReader();
            r.onload = function () { resolve(r.result); };
            r.onerror = reject;
            r.readAsArrayBuffer(file);
        });
    }

    pick.addEventListener('click', function () { input.click(); });

    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        snap = null;
        if (prevUrl) {
            try { URL.revokeObjectURL(prevUrl); } catch (e) {}
            prevUrl = '';
        }
        preview.style.display = 'none';
        if (!f) {
            nameEl.textContent = 'এখনো কোনো ছবি বাছা হয়নি';
            return;
        }
        if (f.size > 20 * 1024 * 1024) {
            nameEl.textContent = 'ছবি খুব বড়। ২০ MB-এর ছোট দিন।';
            input.value = '';
            return;
        }
        nameEl.textContent = 'নেওয়া হচ্ছে: ' + f.name;
        pending = readBuf(f).then(function (buf) {
            var type = f.type || 'image/jpeg';
            var fname = f.name || ('slide.' + (type.split('/')[1] || 'jpg'));
            try {
                snap = new File([buf], fname, { type: type, lastModified: Date.now() });
            } catch (e0) {
                snap = new Blob([buf], { type: type });
                snap.name = fname;
            }
            try {
                var dt = new DataTransfer();
                dt.items.add(snap);
                input.files = dt.files;
            } catch (e) {}
            try {
                prevUrl = URL.createObjectURL(snap);
                preview.src = prevUrl;
                preview.style.display = 'block';
            } catch (e2) {}
            nameEl.textContent = f.name + ' · প্রস্তুত';
        }).catch(function () {
            snap = null;
            nameEl.textContent = 'ছবি পড়া যায়নি। আবার বাছুন।';
        });
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        function post() {
            if (!snap && !(input.files && input.files[0])) {
                nameEl.textContent = 'আগে একটি ছবি বাছুন।';
                return;
            }
            var fd = new FormData(form);
            var payload = snap || input.files[0];
            fd.set('banner_image', payload, payload.name || 'slide.jpg');
            fd.set('upload', '1');
            send.disabled = true;
            send.textContent = 'আপলোড হচ্ছে…';
            fetch(form.getAttribute('action') || 'process_banners.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                redirect: 'manual'
            }).then(function (res) {
                var loc = res.headers.get('Location') || 'banners.php';
                if (res.type === 'opaqueredirect' || res.status === 0) {
                    window.location.href = 'banners.php';
                    return;
                }
                if (res.status === 301 || res.status === 302 || res.status === 303 || res.status === 307 || res.status === 308) {
                    window.location.href = loc;
                    return;
                }
                if (!res.ok) {
                    send.disabled = false;
                    send.textContent = 'ছবি আপলোড করুন';
                    nameEl.textContent = 'আপলোড হয়নি। আবার চেষ্টা করুন।';
                    return;
                }
                window.location.href = 'banners.php';
            }).catch(function () {
                send.disabled = false;
                send.textContent = 'ছবি আপলোড করুন';
                nameEl.textContent = 'নেট আটকেছে। আবার আপলোড করুন।';
            });
        }
        if (pending) pending.then(post).catch(post);
        else post();
    });
})();
</script>
<?php include '../../partials/footer.php'; ?>
