<?php 
// ১. নিরাপত্তা ও হেডার কানেক্ট করা
require_once '../../includes/config.php';
include '../../partials/header.php'; 

// ২. ডাটাবেজ থেকে বিক্রয়ের তথ্য নেওয়া
$sale_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT s.*, a.account_name FROM sales s JOIN accounts a ON s.account_id = a.id WHERE s.id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die('❌ বিক্রয় তথ্য পাওয়া যায়নি। <a href="history.php">ফিরে যান</a>');
}

$items_stmt = $db->prepare("SELECT si.*, COALESCE(NULLIF(si.item_name, ''), p.name) AS name FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?");
$items_stmt->bind_param("i", $sale_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>

<div style="padding: 15px; background: #f8f9fa; min-height: 90vh;">
    <h3 style="color: var(--dark-blue);">📄 বিক্রয় বিবরণী (#<?php echo $sale_id; ?>)</h3>
    
    <!-- সারসংক্ষেপ কার্ড -->
    <div class="card" style="border-left: 5px solid var(--dark-blue); border-radius: 12px; background: white;">
        <p style="margin: 5px 0;">তারিখ: <b><?php echo date('d-m-Y h:i A', strtotime($sale['sale_date'])); ?></b></p>
        <p style="margin: 5px 0;">পেমেন্ট মাধ্যম: <span style="color:var(--dark-green); font-weight:bold;"><?php echo htmlspecialchars($sale['account_name']); ?></span></p>
    </div>

    <!-- পণ্যের তালিকা টেবিল -->
    <div class="card" style="padding:0; margin-top:15px; overflow-x:auto; background:white; border-radius: 12px;">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background: #f1f1f1;">
                    <th style="padding:12px;">পণ্য</th>
                    <th style="padding:12px; text-align:center;">পরিমাণ</th>
                    <th style="padding:12px; text-align:right;">মূল্য</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $items->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:500;"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td style="padding:12px; text-align:center;"><?php echo htmlspecialchars($row['qty']); ?></td>
                    <td style="padding:12px; text-align:right; font-weight:bold;"><?php echo number_format($row['sale_price'] * $row['qty'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- হিসাবের সামারি কার্ড -->
    <div class="card" style="margin-top:15px; text-align:right; background: white; border-radius: 12px;">
        <p style="margin: 5px 0;">মোট বিল: <b><?php echo number_format((float)($sale['total_bill'] ?? 0), 2); ?> ৳</b></p>
        <p style="margin: 5px 0;">ডিসকাউন্ট: <b style="color:var(--red-alert);">- <?php echo number_format((float)($sale['discount'] ?? 0), 2); ?> ৳</b></p>
        <hr>
        <h4 style="margin: 5px 0; color:var(--dark-green);">পরিশোধিত: <?php echo number_format((float)($sale['paid_amount'] ?? 0), 2); ?> ৳</h4>
    </div>

    <!-- ধাপ ৯৫: প্রিন্ট করার বাটনসমূহ (সহজ সমাধান) -->
    <div style="margin-top:20px; display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
        <!-- থার্মাল প্রিন্ট বাটন (সবুজ) -->
        <a href="invoice.php?id=<?php echo $sale_id; ?>" class="btn-success" style="text-align:center; text-decoration:none; padding:15px; border-radius:10px; font-weight:bold;">
            🖨️ Thermal Print
        </a>
        <!-- A4 ইনভয়েস বাটন (গাঢ় নীল) -->
        <a href="invoice_a4.php?id=<?php echo $sale_id; ?>" class="btn-success" style="text-align:center; text-decoration:none; padding:15px; border-radius:10px; background:var(--dark-blue); font-weight:bold;">
            📄 A4 Invoice
        </a>
    </div>

    <!-- ফিরে যাওয়ার বাটন -->
    <div style="margin-top: 15px;">
        <a href="history.php" style="display:block; text-align:center; color: var(--dark-blue); font-weight:bold; text-decoration:none;">← বিক্রয় ইতিহাসে ফিরে যান</a>
    </div>
</div>

<?php include '../../partials/footer.php'; ?>