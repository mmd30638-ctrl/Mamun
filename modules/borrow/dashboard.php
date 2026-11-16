<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
$sum = ame_borrow_summary($db);
$loans = $db->query("SELECT * FROM biz_loans ORDER BY id DESC LIMIT 40");
$due = $db->query("SELECT s.*, l.provider_name FROM biz_loan_schedule s JOIN biz_loans l ON l.id = s.loan_id WHERE s.status <> 'paid' AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY s.due_date ASC LIMIT 12");
include '../../partials/header.php';
?>
<style>
.bw { padding:12px 12px 40px; }
.bw h1 { margin:0 0 8px; color:#0b1e4d; }
.bw-cards { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin:12px 0; }
.bw-cards div { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:12px; }
.bw-cards small { color:#64748b; display:block; }
.bw-cards b { font-size:18px; }
.bw table { width:100%; border-collapse:collapse; background:#fff; border-radius:14px; overflow:hidden; }
.bw td, .bw th { text-align:left; padding:8px; border-bottom:1px solid #e2e8f0; font-size:13px; }
.bw-note { background:#fff7ed; border:1px solid #fdba74; border-radius:12px; padding:10px; }
@media (max-width:800px) { .bw-cards { grid-template-columns:1fr 1fr; } }
</style>
<div class="bw">
    <h1>ব্যবসার ধার</h1>
    <?php if (isset($_GET['disabled'])): ?>
    <p class="bw-note">কাস্টমারকে লোন দেওয়ার পাতা বন্ধ। পুরনো রেকর্ড মুছে ফেলা হয়নি। এখানে শুধু দোকান যে টাকা ধার নেয়।</p>
    <?php endif; ?>
    <div class="bw-cards">
        <div><small>মোট নেওয়া</small><b>৳ <?php echo number_format((float)($sum['borrowed'] ?? 0), 2); ?></b></div>
        <div><small>মূল বাকি</small><b>৳ <?php echo number_format((float)($sum['outstanding'] ?? 0), 2); ?></b></div>
        <div><small>মূল ফেরত</small><b>৳ <?php echo number_format((float)($sum['repaid'] ?? 0), 2); ?></b></div>
        <div><small>সুদ</small><b>৳ <?php echo number_format((float)($sum['interest_paid'] ?? 0), 2); ?></b></div>
        <div><small>ব্যক্তির মাসিক ফি</small><b>৳ <?php echo number_format((float)($sum['fees'] ?? 0), 2); ?></b></div>
        <div><small>সঞ্চয়</small><b>৳ <?php echo number_format((float)($sum['savings'] ?? 0), 2); ?></b></div>
        <div><small>জামানত</small><b>৳ <?php echo number_format((float)($sum['security'] ?? 0), 2); ?></b></div>
    </div>
    <p><a href="loan_form.php">নতুন ধার</a> · <a href="pay.php">পেমেন্ট</a> · <a href="reports.php">রিপোর্ট</a></p>
    <table>
        <tr><th>উৎস</th><th>ধরন</th><th>মূল</th><th>বাকি</th><th>অবস্থা</th><th></th></tr>
        <?php if ($loans) while ($l = $loans->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($l['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($l['provider_type'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo number_format((float)$l['principal'], 2); ?></td>
            <td><?php echo number_format((float)$l['principal_outstanding'], 2); ?></td>
            <td><?php echo htmlspecialchars($l['status'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><a href="ledger.php?id=<?php echo (int)$l['id']; ?>">খাতা</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <h2>আসন্ন কিস্তি</h2>
    <table>
        <tr><th>তারিখ</th><th>উৎস</th><th>বাকি</th><th>অবস্থা</th></tr>
        <?php if ($due) while ($d = $due->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($d['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($d['provider_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo number_format((float)$d['remaining_amount'], 2); ?></td>
            <td><?php echo htmlspecialchars($d['due_date'] < date('Y-m-d') ? 'overdue' : 'upcoming', ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../../partials/footer.php'; ?>
