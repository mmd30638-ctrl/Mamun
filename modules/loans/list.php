<?php include '../../partials/header.php'; ?>
<div style="padding: 15px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <h3 style="color: var(--dark-blue); margin:0;">🏦 লোন/ঋণ ব্যবস্থাপনা</h3>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="dashboard.php" style="text-decoration:none; font-size:13px; align-self:center;">💳 ড্যাশবোর্ড</a>
            <a href="applications_add.php" class="btn-success" style="text-decoration:none; font-size:13px;">+ গ্রাহককে ধার দিন</a>
            <a href="add_taken.php" class="btn-danger" style="text-decoration:none; font-size:13px;">+ ধার নিন</a>
        </div>
    </div>
    <div class="card" style="padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse; text-align:left;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">নাম</th>
                    <th style="padding:12px;">ধরণ</th>
                    <th style="padding:12px; text-align:right;">মূল টাকা</th>
                    <th style="padding:12px; text-align:right;">বকেয়া</th>
                    <th style="padding:12px; text-align:center;">একশন</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $loans = $db->query("SELECT * FROM loans WHERE status='open' ORDER BY id DESC");
                while($l = $loans->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;"><b><?php echo htmlspecialchars($l['person_name'] ?? ''); ?></b></td>
                    <td style="padding:12px;"><?php echo $l['type'] == 'taken' ? 'গৃহীত ঋণ' : 'প্রদত্ত ঋণ'; ?></td>
                    <td style="padding:12px; text-align:right;"><?php echo number_format((float)($l['amount'] ?? 0), 2); ?></td>
                    <td style="padding:12px; text-align:right; font-weight:bold; color:var(--red-alert);">
                        <?php echo number_format($l['amount'] - $l['paid_amount'], 2); ?>
                    </td>
                    <td style="padding:12px; text-align:center;">
                        <a href="pay.php?id=<?php echo $l['id']; ?>" style="color:var(--dark-green); text-decoration:none; border:1px solid; padding:3px 8px; border-radius:4px; font-size:12px;">কিস্তি দিন</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>