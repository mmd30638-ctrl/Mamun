<?php 
require_once '../../includes/config.php';
include '../../partials/header.php'; 

$pid = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
$stmt->bind_param("i", $pid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

// বিক্রয় এবং ক্রয়ের ইতিহাস একসাথে নেওয়া
$moves_stmt = $db->prepare("
    (SELECT 'Sale' as type, s.sale_date as date, si.qty as qty, 'Out' as direction FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.product_id = ?)
    UNION 
    (SELECT 'Purchase' as type, created_at as date, amount as qty, 'In' as direction FROM account_ledger WHERE ref_type = 'purchase' AND ref_id = ?)
    ORDER BY date DESC LIMIT 30
");
$moves_stmt->bind_param("ii", $pid, $pid);
$moves_stmt->execute();
$moves = $moves_stmt->get_result();
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">🔄 পণ্যের মুভমেন্ট: <?php echo htmlspecialchars($p['name'] ?? ''); ?></h3>
    <div class="card" style="padding:0; overflow-x:auto; background:white; border-radius:15px;">
        <table style="width:100%; border-collapse: collapse; text-align:left;">
            <thead>
                <tr style="background: var(--dark-green); color: white;">
                    <th style="padding:12px;">তারিখ</th>
                    <th style="padding:12px;">ধরণ</th>
                    <th style="padding:12px; text-align:right;">পরিমাণ (Qty)</th>
                </tr>
            </thead>
            <tbody>
                <?php while($m = $moves->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:10px;"><?php echo date('d-m-Y', strtotime($m['date'])); ?></td>
                    <td style="padding:10px;"><?php echo htmlspecialchars($m['type'] ?? ''); ?></td>
                    <td style="padding:10px; text-align:right; font-weight:bold; color:<?php echo $m['direction']=='In'?'var(--dark-green)':'var(--red-alert)'; ?>;">
                        <?php echo ($m['direction']=='In'?'+':'-') . $m['qty']; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>