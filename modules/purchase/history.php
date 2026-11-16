<?php
require_once '../../includes/config.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/purchase_draft.php';
require_once '../../includes/lang_helper.php';
ame_purchase_require_access($db);
ensure_purchase_schema($db);
include '../../partials/header.php';

$rows = false;
try {
    $rows = @$db->query("
    SELECT
        CASE WHEN pu.memo_no IS NULL OR pu.memo_no = '' THEN pu.id ELSE 0 END AS lone_id,
        pu.memo_no,
        MIN(pu.created_at) AS created_at,
        MAX(pu.customer_id) AS customer_id,
        MAX(pu.supplier_id) AS supplier_id,
        MAX(c.name) AS customer_name,
        MAX(s.name) AS supplier_name,
        MAX(p.name) AS product_name,
        COUNT(*) AS line_count,
        SUM(pu.qty) AS qty,
        SUM(COALESCE(pu.total_cost, pu.qty * pu.purchase_price)) AS total_cost,
        SUM(COALESCE(pu.paid_amount, 0)) AS paid_amount
    FROM purchases pu
    LEFT JOIN products p ON p.id = pu.product_id
    LEFT JOIN suppliers s ON s.id = pu.supplier_id
    LEFT JOIN customers c ON c.id = pu.customer_id
    GROUP BY CASE WHEN pu.memo_no IS NULL OR pu.memo_no = '' THEN pu.id ELSE 0 END, pu.supplier_id, pu.memo_no
    ORDER BY MAX(pu.id) DESC
    LIMIT 200
    ");
} catch (Throwable $e) {
    error_log('purchase history query: ' . $e->getMessage());
    $rows = false;
}
if (!$rows) {
    try {
    $rows = @$db->query("
        SELECT
            CASE WHEN pu.memo_no IS NULL OR pu.memo_no = '' THEN pu.id ELSE 0 END AS lone_id,
            pu.memo_no,
            MIN(pu.created_at) AS created_at,
            0 AS customer_id,
            MAX(pu.supplier_id) AS supplier_id,
            '' AS customer_name,
            MAX(s.name) AS supplier_name,
            MAX(p.name) AS product_name,
            COUNT(*) AS line_count,
            SUM(pu.qty) AS qty,
            SUM(COALESCE(pu.total_cost, pu.qty * pu.purchase_price)) AS total_cost,
            SUM(COALESCE(pu.paid_amount, 0)) AS paid_amount
        FROM purchases pu
        LEFT JOIN products p ON p.id = pu.product_id
        LEFT JOIN suppliers s ON s.id = pu.supplier_id
        GROUP BY CASE WHEN pu.memo_no IS NULL OR pu.memo_no = '' THEN pu.id ELSE 0 END, pu.supplier_id, pu.memo_no
        ORDER BY MAX(pu.id) DESC
        LIMIT 200
    ");
    } catch (Throwable $e) {
        error_log('purchase history fallback: ' . $e->getMessage());
        $rows = false;
    }
}
$L = static function (string $bn, string $en): string {
    return function_exists('ame_l') ? ame_l($bn, $en) : $bn;
};
?>
<style>
.phist { width:100%; box-sizing:border-box; padding:12px 10px 24px; color:#111; background:#fff; }
.phist h3 { margin:0 0 6px; font-size:20px; font-weight:800; }
.phist p { margin:0 0 12px; color:#525252; font-size:13px; }
.phist-card { overflow-x:auto; border:1px solid #e5e5e5; border-radius:10px; }
.phist table { width:100%; border-collapse:collapse; font-size:14px; min-width:640px; }
.phist th { background:#111; color:#fff; text-align:left; padding:10px; font-weight:800; }
.phist td { padding:10px; border-bottom:1px solid #eee; vertical-align:top; }
.phist .acts { display:flex; flex-wrap:wrap; gap:6px; }
.phist .acts a { display:inline-flex; align-items:center; min-height:36px; padding:6px 10px; border-radius:8px; background:#111; color:#fff; text-decoration:none; font-size:13px; font-weight:800; }
.phist .acts a.danger { background:#b91c1c; }
html body #main-content #phist .acts a,
html body #phist .acts a { background:#1e3a8a !important; background-image:none !important; color:#fff !important; -webkit-text-fill-color:#fff !important; border:0 !important; box-shadow:none !important; opacity:1 !important; }
html body #main-content #phist .acts a.edit, html body #phist .acts a.edit { background:#1d4ed8 !important; }
html body #main-content #phist .acts a.danger, html body #phist .acts a.danger { background:#dc2626 !important; }
html body #main-content #phist .acts a.view, html body #phist .acts a.view { background:#0f766e !important; }
html body #main-content #phist .acts a.print, html body #phist .acts a.print { background:#475569 !important; }
html body #main-content #phist .acts a.share, html body #phist .acts a.share { background:#7c3aed !important; }
html body #main-content #phist .acts a.wa, html body #phist .acts a.wa { background:#16a34a !important; }
html body #main-content #phist .acts a:hover, html body #phist .acts a:hover { filter:brightness(1.1); }
html body #main-content #phist .acts a *, html body #phist .acts a * { color:#fff !important; -webkit-text-fill-color:#fff !important; }
html body #phist .phist-card { overflow-x:auto !important; -webkit-overflow-scrolling:touch; }
html body #phist table { display:table !important; }
html body #phist thead { display:table-header-group !important; }
html body #phist tbody { display:table-row-group !important; }
html body #phist tr { display:table-row !important; }
html body #phist th, html body #phist td { display:table-cell !important; text-align:left !important; }
html body #phist td::before { content:none !important; display:none !important; }
html body #phist th { background:#1e3a8a !important; color:#fff !important; -webkit-text-fill-color:#fff !important; }
html body #phist td:nth-child(1), html body #phist td:nth-child(3), html body #phist td:nth-child(4), html body #phist td:nth-child(5) { white-space:nowrap !important; }
html body #phist td:nth-child(2) { min-width:130px; }
html body #phist .acts { min-width:250px; }
@media (max-width:720px) {
    .phist { padding:10px 8px 20px; }
    .phist table { min-width:560px; font-size:13px; }
}
</style>
<div class="phist" id="phist">
    <h3><?php echo $L('ক্রয় ইতিহাস', 'Purchase history'); ?></h3>
    <p><?php echo $L('প্রতিটি মেমো থেকে দেখা, প্রিন্ট, শেয়ার ও WhatsApp।', 'Open, print, share, or send each memo on WhatsApp.'); ?></p>
    <div class="phist-card">
        <table>
            <thead>
                <tr>
                    <th><?php echo $L('তারিখ', 'Date'); ?></th>
                    <th><?php echo $L('সাপ্লায়ার', 'Supplier'); ?></th>
                    <th><?php echo $L('পরিমাণ', 'Quantity'); ?></th>
                    <th><?php echo $L('মোট', 'Total'); ?></th>
                    <th><?php echo $L('পরিশোধ', 'Paid'); ?></th>
                    <th><?php echo $L('মেমো', 'Memo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows && $rows->num_rows > 0): while ($r = $rows->fetch_assoc()):
                    $memoNo = trim((string)($r['memo_no'] ?? ''));
                    $when = (string)($r['created_at'] ?? '');
                    $product = (string)($r['product_name'] ?? '');
                    $lines = (int)($r['line_count'] ?? 1);
                    ?>
                <tr>
                    <td>
                        <?php echo $when !== '' ? date('d/m/y', strtotime($when)) : '—'; ?><br>
                        <small><?php echo $when !== '' ? date('h:i A', strtotime($when)) : ''; ?></small>
                    </td>
                    <td>
                        <?php if ((int)($r['supplier_id'] ?? 0) <= 0 && (!empty($r['customer_id']) || (!empty($r['customer_name']) && trim((string)$r['customer_name']) !== ''))): ?>
                        <b><?php echo htmlspecialchars((string)($r['customer_name'] ?? $r['supplier_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></b>
                        <span style="display:inline-block;margin-left:4px;padding:1px 6px;border-radius:4px;font-size:11px;background:#e0f2fe;color:#0369a1;font-weight:700;">[কাস্টমার]</span><br>
                        <?php else: ?>
                        <b><?php echo htmlspecialchars((string)($r['supplier_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></b><br>
                        <?php endif; ?>
                        <span style="color:#525252;font-size:12px;"><?php echo htmlspecialchars($product !== '' ? $product : '—', ENT_QUOTES, 'UTF-8'); ?><?php echo $lines > 1 ? ' +' . ($lines - 1) : ''; ?></span>
                    </td>
                    <td><?php echo number_format((float)($r['qty'] ?? 0), 2); ?></td>
                    <td style="font-weight:800;">৳<?php echo number_format((float)($r['total_cost'] ?? 0), 2); ?></td>
                    <?php
                        $paidRaw = (float)($r['paid_amount'] ?? 0);
                        $paidShow = function_exists('ame_purchase_display_paid')
                            ? ame_purchase_display_paid((float)($r['total_cost'] ?? 0), $paidRaw)
                            : $paidRaw;
                    ?>
                    <td><?php echo number_format($paidShow, 2); ?></td>
                    <td>
                        <?php if ($memoNo !== ''):
                            $q = urlencode($memoNo);
                            $sid = (int)($r['supplier_id'] ?? 0);
                            $scope = '&amp;supplier_id=' . $sid;
                            $paidNow = $paidRaw;
                        ?>
                        <div class="acts">
                            <a class="edit" href="add.php?edit_memo=<?php echo $q . $scope; ?>"><?php echo $L('এডিট', 'Edit'); ?></a>
                            <?php if ($paidNow > 0.009): ?>
                            <a class="danger" href="add.php?edit_memo=<?php echo $q . $scope; ?>&amp;void_pay=1"><?php echo $L('পরিশোধ বাতিল', 'Void payment'); ?></a>
                            <?php endif; ?>
                            <a class="view" href="memo_digital.php?memo=<?php echo $q . $scope; ?>"><?php echo $L('দেখুন', 'View'); ?></a>
                            <a class="print" href="memo_digital.php?memo=<?php echo $q . $scope; ?>&amp;do=print"><?php echo $L('প্রিন্ট', 'Print'); ?></a>
                            <a class="share" href="memo_digital.php?memo=<?php echo $q . $scope; ?>&amp;do=share"><?php echo $L('শেয়ার', 'Share'); ?></a>
                            <a class="wa" href="memo_digital.php?memo=<?php echo $q . $scope; ?>&amp;do=wa">WhatsApp</a>
                        </div>
                        <?php else:
                            $lone = (int)($r['lone_id'] ?? 0);
                            if ($lone > 0): ?>
                        <div class="acts">
                            <a class="edit" href="add.php?edit_id=<?php echo $lone; ?>"><?php echo $L('এডিট', 'Edit'); ?></a>
                        </div>
                        <?php else: ?>—<?php endif; endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" style="padding:16px;text-align:center;"><?php echo $L('কোনো ক্রয় নেই।', 'No purchases yet.'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="add.php" style="display:block;text-align:center;margin-top:14px;color:#111;font-weight:800;text-decoration:none;"><?php echo $L('নতুন ক্রয়', 'New purchase'); ?></a>
</div>
<?php
include '../../partials/footer.php';
if (function_exists('ame_purchase_repair_completed_customer_adjust')) {
    try {
        ame_purchase_repair_completed_customer_adjust($db);
    } catch (Throwable $e) {
        error_log('purchase history adjust: ' . $e->getMessage());
    }
}
?>