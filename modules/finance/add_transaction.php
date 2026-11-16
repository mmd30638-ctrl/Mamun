<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/pf_functions.php';
admin_only();
ensure_pf_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('ame_active_accounts')) {
    require_once __DIR__ . '/../../includes/account_functions.php';
}
$income_categories = $db->query("SELECT * FROM pf_categories WHERE type = 'income' AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
$expense_categories = $db->query("SELECT * FROM pf_categories WHERE type = 'expense' AND is_active = 1 ORDER BY parent_group")->fetch_all(MYSQLI_ASSOC);
$merchants = $db->query("SELECT id, name FROM pf_merchants WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$members = $db->query("SELECT id, name FROM pf_family_members WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../../partials/header.php';
?>
<style>
    :root { --pf-navy:#0B1E4D; }
    .pf-wrap { padding: 14px; max-width: 560px; margin: 0 auto; }
    .pf-tabs { display:flex; gap:6px; margin-bottom:14px; background:#fff; padding:6px; border-radius:12px; box-shadow:0 4px 14px rgba(11,30,77,0.06); }
    .pf-tab { flex:1; text-align:center; padding:10px 4px; border-radius:9px; font-weight:800; font-size:12px; cursor:pointer; color:#64748B; }
    .pf-tab.active-income { background:#E8F5E9; color:#0B8043; }
    .pf-tab.active-expense { background:#FFEBEE; color:#C62828; }
    .pf-tab.active-transfer { background:#E1F5FE; color:#0091EA; }
    .pf-tab.active-refund { background:#F3E5F5; color:#7B1FA2; }
    .pf-card { background:#fff; border-radius:16px; padding:18px; box-shadow:0 4px 18px rgba(11,30,77,0.08); }
    .pf-field { margin-bottom:13px; }
    .pf-field label { display:block; font-size:11.5px; font-weight:700; color:#334155; margin-bottom:5px; }
    .pf-field input, .pf-field select, .pf-field textarea { width:100%; padding:11px 13px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:13.5px; box-sizing:border-box; font-family:inherit; }
    .pf-submit-btn { width:100%; padding:14px; border:none; border-radius:12px; color:#fff; font-weight:800; font-size:15px; cursor:pointer; }
</style>

<div class="pf-wrap">
    <div class="pf-tabs">
        <div class="pf-tab active-income" id="tab-income" onclick="switchType('income')">⬆️ আয়</div>
        <div class="pf-tab" id="tab-expense" onclick="switchType('expense')">⬇️ খরচ</div>
        <div class="pf-tab" id="tab-transfer" onclick="switchType('transfer')">↔️ ট্রান্সফার</div>
        <div class="pf-tab" id="tab-refund" onclick="switchType('refund')">↩️ রিফান্ড</div>
    </div>

    <div class="pf-card">
        <?php if (!empty($_SESSION['error'])): ?>
            <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:14px; font-size:13px;"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form action="actions/save_transaction.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="type" id="typeInput" value="income">

            <div class="pf-field"><label>পরিমাণ (৳) *</label><input type="number" step="0.01" min="0.01" name="amount" required></div>
            <div class="pf-field"><label>তারিখ *</label><input type="date" name="txn_date" value="<?php echo date('Y-m-d'); ?>" required></div>

            <div id="incomeFields">
                <div class="pf-field"><label>উৎস / বিবরণ *</label><input type="text" name="source_label" placeholder="যেমন: বেতন, ব্যবসা আয়"></div>
                <div class="pf-field"><label>কোন অ্যাকাউন্টে জমা হবে *</label><?php include __DIR__ . '/_account_select.php'; ?></div>
                <div class="pf-field"><label>ক্যাটাগরি</label>
                    <select name="category_id_income">
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php foreach ($income_categories as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo esc_html(($c['icon'] ?? '') . ' ' . $c['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="expenseFields" style="display:none;">
                <div class="pf-field"><label>ক্যাটাগরি *</label>
                    <select name="category_id_expense">
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php $current_group = ''; foreach ($expense_categories as $c): if ($c['parent_group'] !== $current_group): $current_group = $c['parent_group']; ?>
                            <optgroup label="<?php echo htmlspecialchars($current_group); ?>">
                        <?php endif; ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo esc_html(($c['icon'] ?? '') . ' ' . $c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pf-field"><label>কোন অ্যাকাউন্ট থেকে *</label><?php $ame_fin_direction = 'pay'; include __DIR__ . '/_account_select.php'; unset($ame_fin_direction); ?></div>
                <div class="pf-field"><label>দোকান/মার্চেন্ট (ঐচ্ছিক)</label>
                    <input type="text" name="merchant_name" list="merchantList" placeholder="যেমন: Rahman Store">
                    <datalist id="merchantList"><?php foreach ($merchants as $m): ?><option value="<?php echo htmlspecialchars($m['name']); ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="pf-field"><label>কে খরচ করেছে (ঐচ্ছিক)</label>
                    <select name="member_id">
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php foreach ($members as $m): ?><option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="transferFields" style="display:none;">
                <div class="pf-field"><label>কোন অ্যাকাউন্ট থেকে *</label>
                    <?php echo ame_account_select($db, ['name' => 'from_account_id', 'direction' => 'pay', 'required' => true, 'placeholder' => '-- নির্বাচন করুন --']); ?>
                </div>
                <div class="pf-field"><label>কোন অ্যাকাউন্টে *</label>
                    <?php echo ame_account_select($db, ['name' => 'to_account_id', 'direction' => 'receive', 'required' => true, 'placeholder' => '-- নির্বাচন করুন --']); ?>
                </div>
            </div>

            <div id="refundFields" style="display:none;">
                <div class="pf-field"><label>কোন অ্যাকাউন্টে ফেরত এসেছে *</label>
                    <?php echo ame_account_select($db, ['name' => 'refund_account_id', 'direction' => 'receive', 'required' => true, 'placeholder' => '-- নির্বাচন করুন --']); ?>
                </div>
                <div class="pf-field"><label>কোন খরচের বিপরীতে (ঐচ্ছিক নোট)</label><input type="text" name="refund_note" placeholder="যেমন: ভুল বিলের টাকা ফেরত"></div>
            </div>

            <div class="pf-field"><label>নোট (ঐচ্ছিক)</label><textarea name="note" rows="2" maxlength="255"></textarea></div>

            <button type="submit" class="pf-submit-btn" id="submitBtn" style="background:linear-gradient(135deg,#0B8043,#2ECC71);">✅ সংরক্ষণ করুন</button>
        </form>
    </div>
    <div style="text-align:center; margin-top:14px;"><a href="dashboard.php" style="color:#0091EA; text-decoration:none; font-size:13px;">← ড্যাশবোর্ডে ফিরে যান</a></div>
</div>

<script>
function switchType(type) {
    document.getElementById('typeInput').value = type;
    ['income', 'expense', 'transfer', 'refund'].forEach(function (t) {
        document.getElementById('tab-' + t).className = 'pf-tab' + (t === type ? ' active-' + type : '');
        var fieldsEl = document.getElementById(t + 'Fields');
        if (fieldsEl) fieldsEl.style.display = (t === type) ? 'block' : 'none';
    });
    var btn = document.getElementById('submitBtn');
    var colors = { income: '#0B8043,#2ECC71', expense: '#C62828,#E57373', transfer: '#0091EA,#4FC3F7', refund: '#7B1FA2,#AB47BC' };
    btn.style.background = 'linear-gradient(135deg,' + colors[type] + ')';
}
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
