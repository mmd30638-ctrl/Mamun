<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 
?>
<div style="padding: 15px; background: #f8f9fa; min-height: 90vh;">
    <h3 style="color: var(--dark-blue); margin-bottom: 20px;">📊 রিপোর্ট সেন্টার (Business Intelligence)</h3>
    
    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
        <div class="card" style="border-radius:15px; background:white;">
            <h4 style="margin-top:0; color:#15803d;">◉ লাইভ ড্যাশবোর্ড</h4>
            <hr>
            <a href="live_work.php" style="display:block; padding:10px 0; text-decoration:none; color:#333; font-weight:800;">কে কী করলো — দিন, তারিখ, মাস ও বছর</a>
        </div>
        <!-- আর্থিক রিপোর্ট -->
        <div class="card" style="border-radius:15px; background:white;">
            <h4 style="margin-top:0; color:var(--dark-green);">💰 আর্থিক রিপোর্ট</h4>
            <hr>
            <a href="profit_loss.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">📈 নিট লাভ-ক্ষতি রিপোর্ট</a>
            <a href="../accounts/cash_book.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">📖 ক্যাশ বুক (জমা-খরচ)</a>
            <?php if (has_permission($db, 'm-sales-report')): ?>
            <a href="day_closing.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">📅 দৈনিক ক্লোজিং সামারি</a>
            <?php endif; ?>
        </div>

        <!-- ইনভেন্টরি রিপোর্ট -->
        <div class="card" style="border-radius:15px; background:white;">
            <h4 style="margin-top:0; color:var(--dark-blue);">📦 ইনভেন্টরি রিপোর্ট</h4>
            <hr>
            <a href="../inventory/stock_report.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">📉 বর্তমান স্টক রিপোর্ট</a>
            <a href="valuation.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">💎 স্টকের মোট আর্থিক মূল্য</a>
            <?php if (has_permission($db, 'm-sales-report')): ?>
            <a href="items_report.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">🔥 পণ্যভিত্তিক বিক্রয় বিশ্লেষণ</a>
            <?php endif; ?>
        </div>

        <!-- কাস্টমার ও সাপ্লায়ার -->
        <div class="card" style="border-radius:15px; background:white;">
            <h4 style="margin-top:0; color:#d48806;">👥 কন্টাক্ট রিপোর্ট</h4>
            <hr>
            <a href="../customers/index.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">👥 ক্রেতা বকেয়া তালিকা</a>
            <a href="../customers/withdrawals_report.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">🏧 জমা উত্তোলন রিপোর্ট</a>
            <a href="../suppliers/list.php" style="display:block; padding:10px 0; text-decoration:none; color:#333;">🤝 সাপ্লায়ার বকেয়া তালিকা</a>
        </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>