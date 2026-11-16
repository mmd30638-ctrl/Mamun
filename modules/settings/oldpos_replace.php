<?php
require_once '../../includes/config.php';
require_once '../../includes/ma_engineering_migrate.php';
require_once '../../includes/oldpos_replace.php';
admin_only();

$phrase = 'সব ডাটা মুছে পুরনো বসান';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        csrf_verify();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'status') {
            $job = mae_migration_find_latest_open_job();
            if (!$job || empty($job['options']['replace_shop'])) {
                echo json_encode(['ok' => true, 'has_job' => false], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $payload = mae_migration_job_response($job, 'আগের কাজ বাকি আছে');
            $payload['has_job'] = true;
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'cancel') {
            $job = mae_migration_resolve_job($_POST['job_id'] ?? null);
            if ($job && !empty($job['id'])) {
                mae_migration_delete_job((string) $job['id']);
            }
            echo json_encode(['ok' => true, 'message' => 'বাকি কাজ বাতিল'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'start' || $action === 'resume') {
            if ($action === 'resume') {
                $job = mae_migration_resolve_job($_POST['job_id'] ?? null);
                if (!$job) {
                    echo json_encode(['ok' => false, 'error' => 'চালিয়ে যাওয়ার কাজ পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                echo json_encode(mae_migration_job_response($job, 'আবার চালু'), JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (trim((string) ($_POST['confirm_text'] ?? '')) !== $phrase) {
                echo json_encode(['ok' => false, 'error' => 'নিশ্চিত করতে ঠিক এই কথা লিখুন।'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!ame_oldpos_sql_ready()) {
                echo json_encode(['ok' => false, 'error' => 'পুরনো ডাম্প এই আপডেটে নেই। আবার সহজ আপডেট করুন।'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $existing = mae_migration_find_latest_open_job();
            if ($existing && !empty($existing['options']['replace_shop']) && empty($_POST['force_new'])) {
                echo json_encode(mae_migration_job_response($existing, 'আগের কাজ চালিয়ে যাচ্ছি'), JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($existing && !empty($existing['id'])) {
                mae_migration_delete_job((string) $existing['id']);
            }
            $job = mae_migration_new_job([
                'import_settings' => false,
                'import_master' => true,
                'import_transactions' => true,
                'skip_existing' => false,
                'replace_shop' => true,
                'use_existing_tables' => false,
            ]);
            $job['sql_path'] = ame_oldpos_sql_path();
            $job['sql_owned'] = false;
            $job['phase'] = 'init';
            $job['label'] = 'Old POS replace';
            mae_migration_save_job($job);
            echo json_encode(mae_migration_job_response($job, 'শুরু হয়েছে। এই পেজ খোলা রাখুন।'), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'step') {
            $job = mae_migration_resolve_job($_POST['job_id'] ?? null);
            if (!$job) {
                echo json_encode(['ok' => false, 'error' => 'কাজ পাওয়া যায়নি। আবার শুরু করুন।'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(mae_migration_step($db, $job), JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'অজানা কাজ'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('oldpos replace: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'এই কাজটি এখন হয়নি। পেজ খোলা রেখে আবার চেষ্টা করুন।'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$ready = ame_oldpos_sql_ready();
include '../../partials/header.php';
?>
<div class="ds-sheet">
    <h1><?php echo ame_l('পুরনো সাইটের ডাটা বসান', 'Install the old site data'); ?></h1>
    <p class="ds-lead"><?php echo ame_l('সোর্স: ২৫ সেপ্টেম্বর ২০২৬, সন্ধ্যা ৬:৩৯-এর পুরনো পিওএস। phpMyAdmin-এ সেই SQL দেবেন না।', 'Source: the old POS dump from 25 September 2026, 6:39 PM. Do not import that SQL in phpMyAdmin.'); ?></p>
    <div class="ds-warn">
        <b><?php echo ame_l('এই বাটন চাপলে মুছে যাবে', 'This button deletes'); ?>:</b>
        <?php echo ame_l('নতুন সাইটের ক্রেতা, সাপ্লায়ার, পণ্য, স্টক, বিক্রয়, ক্রয়, রিটার্ন এবং ক্যাশ খাতার লাইন।', 'customers, suppliers, products, stock, sales, purchases, returns, and cash-book lines on the new site.'); ?>
        <br>
        <b><?php echo ame_l('থাকবে', 'Stays'); ?>:</b>
        <?php echo ame_l('আপনার লগইন, দোকানের নাম, বেতন, ব্যবসার ঋণ, খরচের এন্ট্রি এবং .env।', 'your login, shop name, salary, business loans, expense entries, and .env.'); ?>
        <br>
        <?php echo ame_l('সহজ আপডেট আগে ডাটাবেজের কপি রাখে। পেজ খোলা রাখুন। ফোনের স্ক্রিন ঘুমাতে দেবেন না। নেট কাটলে এই পেজ আবার খুললে বাকি কাজ চলবে।', 'Easy Update keeps a database copy first. Leave this page open and keep the phone screen awake. If the network drops, open this page again and the remaining work continues.'); ?>
    </div>
    <ul class="ds-stats">
        <li><?php echo ame_l('ক্রেতা', 'Customers'); ?> <b>1,357</b></li>
        <li><?php echo ame_l('সাপ্লায়ার', 'Suppliers'); ?> <b>69</b></li>
        <li><?php echo ame_l('পণ্য', 'Products'); ?> <b>704</b></li>
        <li><?php echo ame_l('বিক্রয়', 'Sales'); ?> <b>60,817</b></li>
        <li><?php echo ame_l('ক্রয়', 'Purchases'); ?> <b>1,924</b></li>
    </ul>
    <?php if (!$ready): ?>
        <p class="ds-bad"><?php echo ame_l('ডাম্প ফাইল এই আপডেটে নেই।', 'The dump file is not in this update.'); ?></p>
    <?php endif; ?>
    <p id="oprStatus" class="ds-info" hidden></p>
    <div id="oprBarWrap" hidden style="height:16px;background:#e3edf7;border-radius:999px;overflow:hidden;margin:12px 0;">
        <div id="oprBar" style="height:100%;width:0;background:#0B1E4D;"></div>
    </div>
    <form id="oprForm">
        <?php echo csrf_field(); ?>
        <label style="display:block;font-weight:800;margin-bottom:8px;"><?php echo ame_l('লিখুন', 'Type'); ?>: <?php echo htmlspecialchars($phrase, ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="oprConfirm" name="confirm_text" required autocomplete="off" inputmode="text">
        <button id="oprGo" class="ds-go" type="submit" <?php echo $ready ? '' : 'disabled'; ?>><?php echo ame_l('মুছে পুরনো ডাটা বসান', 'Delete and install the old data'); ?></button>
    </form>
    <p><a class="ds-back" href="tools.php"><?php echo ame_l('টুলসে ফিরে যান', 'Back to tools'); ?></a></p>
</div>
<script>
(function () {
    var form = document.getElementById('oprForm');
    var statusEl = document.getElementById('oprStatus');
    var barWrap = document.getElementById('oprBarWrap');
    var bar = document.getElementById('oprBar');
    var btn = document.getElementById('oprGo');
    var token = form.querySelector('input[name="csrf_token"]').value;
    var running = false;

    function show(text, bad) {
        statusEl.hidden = false;
        statusEl.style.background = bad ? '#ffebee' : '#e3f2fd';
        statusEl.style.borderColor = bad ? '#c62828' : '#1565c0';
        statusEl.textContent = text;
    }

    function post(fields) {
        var body = new FormData();
        body.append('ajax', '1');
        body.append('csrf_token', token);
        Object.keys(fields).forEach(function (key) { body.append(key, fields[key]); });
        return fetch('oldpos_replace.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); });
    }

    function paint(data) {
        var pct = data.progress && data.progress.percent ? data.progress.percent : 0;
        var label = data.progress && data.progress.label ? data.progress.label : '';
        barWrap.style.display = 'block';
        bar.style.width = pct + '%';
        var line = (data.message || label || '') + ' (' + pct + '%)';
        if (data.progress && data.progress.sales_total) {
            line += ' — ' + data.progress.sales_offset + '/' + data.progress.sales_total;
        }
        show(line, false);
    }

    function loop(jobId) {
        return post({ action: 'step', job_id: jobId }).then(function (data) {
            if (!data || !data.ok) {
                show((data && data.error) ? data.error : 'থামা অবস্থায় আছে। পেজ খুলে আবার চালু করুন।', true);
                btn.disabled = false;
                running = false;
                return;
            }
            paint(data);
            if (data.done) {
                show('হয়ে গেছে। ক্রেতা, পণ্য, বিক্রয় ও ক্রয় তালিকা খুলে মিলিয়ে নিন।', false);
                bar.style.width = '100%';
                btn.disabled = false;
                running = false;
                return;
            }
            return loop(data.job_id || jobId);
        }).catch(function () {
            show('নেট কেটেছে। এই পেজ আবার খুললে বাকি কাজ চলবে।', true);
            btn.disabled = false;
            running = false;
        });
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (running) return;
        running = true;
        btn.disabled = true;
        show('শুরু হচ্ছে। পেজ বন্ধ করবেন না।', false);
        post({
            action: 'start',
            confirm_text: document.getElementById('oprConfirm').value
        }).then(function (data) {
            if (!data || !data.ok) {
                show((data && data.error) ? data.error : 'শুরু হয়নি।', true);
                btn.disabled = false;
                running = false;
                return;
            }
            paint(data);
            if (data.done) {
                btn.disabled = false;
                running = false;
                return;
            }
            return loop(data.job_id);
        }).catch(function () {
            show('শুরু করা যায়নি।', true);
            btn.disabled = false;
            running = false;
        });
    });

    post({ action: 'status' }).then(function (data) {
        if (data && data.has_job && data.job_id) {
            running = true;
            btn.disabled = true;
            paint(data);
            loop(data.job_id);
        }
    }).catch(function () {});
})();
</script>
<?php include '../../partials/footer.php'; ?>
