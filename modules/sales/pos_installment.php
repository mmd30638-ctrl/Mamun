<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 
$customers = $db->query("SELECT * FROM customers WHERE status='active'");
?>

<div class="card" style="margin: 10px; border-top: 5px solid #6a1b9a;">
    <h3 style="color:#6a1b9a;">🏦 কিস্তিতে বিক্রয় (Hire Purchase)</h3>
    <form id="installmentForm">
        <label>ক্রেতা নির্বাচন করুন</label>
        <select name="customer_id" class="input-style" required>
            <?php while ($c = $customers->fetch_assoc()): ?>
            <option value="<?php echo (int)$c['id']; ?>"><?php echo esc_html($c['name']); ?> (ID: <?php echo esc_html((string)($c['cus_id_code'] ?? '')); ?>)</option>
            <?php endwhile; ?>
        </select>

        <label>পণ্যের মোট মূল্য (৳)</label>
        <input type="number" id="total_val" class="input-style" required oninput="updateInstallment()">

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;">
            <div>
                <label>ডাউন পেমেন্ট (জমা)</label>
                <input type="number" id="down_pay" class="input-style" value="0" oninput="updateInstallment()">
            </div>
            <div>
                <label>কিস্তির সংখ্যা (মাস)</label>
                <select id="months" class="input-style" onchange="updateInstallment()">
                    <option value="3">৩ মাস</option>
                    <option value="6" selected>৬ মাস</option>
                    <option value="12">১২ মাস</option>
                    <option value="24">২৪ মাস</option>
                </select>
            </div>
        </div>

        <div id="schedule_preview" style="margin-top:20px; background:#f3e5f5; padding:15px; border-radius:10px; font-size:13px;">
            <!-- কিস্তির তালিকা এখানে অটো দেখাবে -->
        </div>

        <button type="button" onclick="alert('পেমেন্ট গেটওয়ে সিঙ্ক হচ্ছে...')" class="btn-success" style="width:100%; margin-top:20px; background:#6a1b9a;">✔️ কিস্তি বিক্রয় নিশ্চিত করুন</button>
    </form>
</div>

<script>
function updateInstallment() {
    let total = parseFloat(document.getElementById('total_val').value) || 0;
    let down = parseFloat(document.getElementById('down_pay').value) || 0;
    let months = parseInt(document.getElementById('months').value);
    
    let remaining = total - down;
    let per_month = (remaining / months).toFixed(2);
    
    let html = `<b>বাকি টাকা: ${remaining.toFixed(2)} ৳</b><hr>`;
    html += `<p>প্রতি মাসের কিস্তি: <b style="color:red;">${per_month} ৳</b></p>`;
    html += `<small>প্রথম কিস্তির তারিখ: ${new Date(new Date().setMonth(new Date().getMonth() + 1)).toLocaleDateString('bn-BD')}</small>`;
    
    document.getElementById('schedule_preview').innerHTML = html;
}
</script>
<?php include '../../partials/footer.php'; ?>