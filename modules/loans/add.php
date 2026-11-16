<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';
require_once __DIR__ . '/../../includes/loan_functions.php';
admin_only();
ensure_bank_loan_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$accounts = ame_active_accounts($db);

include __DIR__ . '/../../partials/header.php';
?>
<style>
    :root { --ln-navy:#0B1E4D; --ln-indigo:#4F46E5; --ln-bg:#EEF2FF; --ln-border:#E0E7FF; }
    .ln-wrap { padding: 14px; max-width: 700px; margin: 0 auto; }
    .ln-card { background:#fff; border-radius:16px; box-shadow:0 4px 16px rgba(11,30,77,0.07); padding:16px; margin-bottom:14px; }
    .ln-card-title { font-weight:800; color:var(--ln-navy); font-size:13.5px; margin-bottom:12px; }
    .ln-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media (max-width:600px) { .ln-grid-2 { grid-template-columns:1fr; } }
    .ln-field { margin-bottom:12px; }
    .ln-field label { display:block; font-size:11px; font-weight:700; color:#334155; margin-bottom:5px; }
    .ln-field input, .ln-field select, .ln-field textarea {
        width:100%; padding:11px 13px; border:1.5px solid var(--ln-border); border-radius:10px; font-size:13px; box-sizing:border-box; font-family:inherit;
    }
    .ln-field input:focus, .ln-field select:focus, .ln-field textarea:focus { outline:none; border-color:var(--ln-indigo); box-shadow:0 0 0 3px rgba(79,70,229,0.12); }
    .ln-preview-box { background:var(--ln-bg); border-radius:12px; padding:12px 14px; font-size:12.5px; color:var(--ln-navy); margin-bottom:14px; }
    .ln-submit-btn { width:100%; padding:15px; border:none; border-radius:13px; background: linear-gradient(135deg,var(--ln-navy),var(--ln-indigo)); color:#fff; font-weight:800; font-size:15px; cursor:pointer; }
</style>

<div class="ln-wrap">
    <h2 style="color:var(--ln-navy); margin-bottom:4px;">➕ নতুন লোন যোগ করুন</h2>
    <p style="color:#64748B; font-size:12px; margin-bottom:16px;">ব্যাংক থেকে নেওয়া নতুন লোনের তথ্য দিন — সাথে সাথে ব্যাংক অ্যাকাউন্টে টাকা যোগ হয়ে যাবে</p>

    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px;">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form action="actions/save_loan.php" method="POST" id="loanForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="ln-card">
            <div class="ln-card-title">🏦 লোনের মূল তথ্য</div>
            <div class="ln-grid-2">
                <div class="ln-field"><label>ব্যাংক/আর্থিক প্রতিষ্ঠানের নাম *</label><input type="text" name="bank_name" required placeholder="যেমন: ইসলামী ব্যাংক"></div>
                <div class="ln-field"><label>লোনের নাম *</label><input type="text" name="loan_name" required placeholder="যেমন: ব্যবসায় সম্প্রসারণ"></div>
            </div>
            <div class="ln-grid-2">
                <div class="ln-field">
                    <label>লোনের ধরন</label>
                    <select name="loan_type">
                        <option value="ব্যবসায়িক লোন">ব্যবসায়িক লোন</option>
                        <option value="ওয়ার্কিং ক্যাপিটাল">ওয়ার্কিং ক্যাপিটাল</option>
                        <option value="ট্রেড লোন">ট্রেড লোন</option>
                        <option value="যন্ত্রপাতি ক্রয়">যন্ত্রপাতি ক্রয়</option>
                        <option value="স্বল্পমেয়াদী লোন">স্বল্পমেয়াদী লোন</option>
                        <option value="অন্যান্য">অন্যান্য</option>
                    </select>
                </div>
                <div class="ln-field"><label>লোনের পরিমাণ (৳) *</label><input type="number" name="principal_amount" id="principalInput" step="0.01" min="1" required oninput="calcPreview()"></div>
            </div>
            <div class="ln-grid-2">
                <div class="ln-field"><label>লোন নেওয়ার তারিখ *</label><input type="date" name="loan_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="ln-field">
                    <label>যে ব্যাংক অ্যাকাউন্টে টাকা এসেছে *</label>
                    <select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id" required>
                        <option value="">-- নির্বাচন করুন --</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars(ame_account_option_label($a)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="ln-card">
            <div class="ln-card-title">📅 কিস্তির তথ্য</div>
            <div class="ln-grid-2">
                <div class="ln-field"><label>সুদের হার (% বার্ষিক)</label><input type="number" name="interest_rate" step="0.01" min="0" value="0"></div>
                <div class="ln-field"><label>মেয়াদ (মাস)</label><input type="number" name="tenure_months" id="tenureInput" min="0" value="0" oninput="calcPreview()"></div>
            </div>
            <div class="ln-grid-2">
                <div class="ln-field"><label>কিস্তির সংখ্যা</label><input type="number" name="installment_count" id="countInput" min="0" value="0" oninput="calcPreview()"></div>
                <div class="ln-field"><label>প্রতি কিস্তির পরিমাণ (৳)</label><input type="number" name="installment_amount" id="installmentInput" step="0.01" min="0" value="0" readonly style="background:#F8FAFC;"></div>
            </div>
            <div class="ln-grid-2">
                <div class="ln-field"><label>প্রথম কিস্তির তারিখ</label><input type="date" name="first_installment_date" id="firstDateInput"></div>
                <div class="ln-field"><label>শেষ কিস্তির তারিখ (স্বয়ংক্রিয়)</label><input type="date" name="last_installment_date" id="lastDateInput" readonly style="background:#F8FAFC;"></div>
            </div>
            <div class="ln-preview-box" id="previewBox" style="display:none;"></div>
        </div>

        <div class="ln-card">
            <div class="ln-card-title">💰 অতিরিক্ত চার্জ ও নোট</div>
            <div class="ln-grid-2">
                <div class="ln-field"><label>প্রসেসিং ফি (৳)</label><input type="number" name="processing_fee" step="0.01" min="0" value="0"></div>
                <div class="ln-field"><label>অন্যান্য চার্জ (৳)</label><input type="number" name="other_charges" step="0.01" min="0" value="0"></div>
            </div>
            <div class="ln-field"><label>নোট (ঐচ্ছিক)</label><textarea name="notes" rows="3" maxlength="500" placeholder="লোন সম্পর্কিত অতিরিক্ত তথ্য..."></textarea></div>
        </div>

        <button type="submit" class="ln-submit-btn">✓ লোন সংরক্ষণ করুন</button>
        <a href="dashboard.php" style="display:block; text-align:center; margin-top:14px; color:var(--ln-navy); text-decoration:none; font-size:13px;">← ড্যাশবোর্ডে ফিরে যান</a>
    </form>
</div>

<script>
function calcPreview() {
    var principal = parseFloat(document.getElementById('principalInput').value) || 0;
    var count = parseInt(document.getElementById('countInput').value) || 0;
    var tenure = parseInt(document.getElementById('tenureInput').value) || 0;
    var firstDate = document.getElementById('firstDateInput').value;
    var box = document.getElementById('previewBox');

    if (count > 0 && tenure > 0 && count !== tenure) {
        // কিস্তি সংখ্যা খালি রাখলে মেয়াদ থেকে অনুমান
    }
    if (count <= 0 && tenure > 0) {
        document.getElementById('countInput').value = tenure;
        count = tenure;
    }

    if (principal > 0 && count > 0) {
        var perInstallment = principal / count;
        document.getElementById('installmentInput').value = perInstallment.toFixed(2);

        if (firstDate) {
            var d = new Date(firstDate);
            d.setMonth(d.getMonth() + (count - 1));
            document.getElementById('lastDateInput').value = d.toISOString().split('T')[0];
        }

        box.style.display = 'block';
        box.innerHTML = '📊 প্রতি কিস্তি আনুমানিক <strong>৳' + perInstallment.toFixed(2) + '</strong> — মোট ' + count + ' টি কিস্তিতে ৳' + principal.toFixed(2) + ' পরিশোধ হবে। (এটা সরল হিসাব; সুদ আলাদাভাবে যোগ হবে না, প্রতিটা কিস্তির সময় সুদ/আসল ভাগ আপনি নিজে দিতে পারবেন)';
    } else {
        box.style.display = 'none';
    }
}
document.getElementById('firstDateInput').addEventListener('change', calcPreview);

document.getElementById('loanForm').addEventListener('submit', function (e) {
    if (!confirm('আপনি কি এই লোনটি সংরক্ষণ করতে চান? সাথে সাথে ব্যাংক অ্যাকাউন্টে টাকা যোগ হয়ে যাবে।')) {
        e.preventDefault();
    }
});
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
