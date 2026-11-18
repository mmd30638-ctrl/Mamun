<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);

$banks = $db->query("SELECT * FROM banks ORDER BY bank_name ASC")->fetch_all(MYSQLI_ASSOC);
include '../../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.oln-page-wrap { padding: 15px; }
.oln-add-btn {
    display:inline-flex; align-items:center; gap:6px; background: var(--brand-gradient); color: var(--brand-white) !important;
    padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13.5px; white-space:nowrap;
}
.oln-table-wrap { overflow-x:auto; }
.oln-table { width:100%; border-collapse: collapse; text-align:left; }
.oln-table thead tr { background: var(--brand-navy); color: var(--brand-white); }
.oln-table th { padding:12px; font-size:13px; }
.oln-table td { padding:12px; border-bottom:1px solid var(--brand-border); font-size:13.5px; }
.oln-status { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11.5px; font-weight:700; }
.oln-status.on { background: var(--brand-success-soft); color: var(--brand-success); }
.oln-status.off { background: var(--brand-danger-soft); color: var(--brand-danger); }
.oln-actions a { display:inline-block; text-decoration:none; border:1px solid var(--brand-border); padding:4px 9px; border-radius:6px; font-size:12px; margin:2px; color: var(--brand-accent); }
.oln-empty { text-align:center; padding:30px 0; color: var(--brand-muted); }
.oln-page-wrap .ame-wait-spin i {
    width: 28px !important; height: 28px !important; border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="oln-page-wrap">
    <div class="card" style="padding:0; overflow:visible;">
        <div style="padding:18px 18px 0;">
            <h3 style="color: var(--brand-navy); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <span>🏦 <?php echo te('nav_oloan_banks'); ?></span>
                <a href="bank_form.php" class="oln-add-btn">+ <?php echo ame_l('নতুন ব্যাংক', 'New bank'); ?></a>
            </h3>
        </div>
        <div class="oln-table-wrap">
        <table class="oln-table">
            <thead><tr>
                <th><?php echo ame_l('ব্যাংক', 'Bank'); ?></th>
                <th><?php echo ame_l('শাখা', 'Branch'); ?></th>
                <th><?php echo ame_l('হিসাব তথ্য', 'Account info'); ?></th>
                <th><?php echo ame_l('যোগাযোগ', 'Contact'); ?></th>
                <th><?php echo ame_l('অবস্থা', 'Status'); ?></th>
                <th><?php echo ame_l('অ্যাকশন', 'Action'); ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$banks): ?>
                <tr><td colspan="6" class="oln-empty"><?php echo ame_l('কোনো ব্যাংক যোগ করা হয়নি।', 'No banks added yet.'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($banks as $b): ?>
                <tr>
                    <td><strong><?php echo esc_html($b['bank_name']); ?></strong></td>
                    <td><?php echo esc_html($b['branch_name']); ?></td>
                    <td><?php echo esc_html($b['account_info']); ?></td>
                    <td><?php echo esc_html($b['contact_info']); ?></td>
                    <td><span class="oln-status <?php echo (int)$b['status'] === 1 ? 'on' : 'off'; ?>"><?php echo (int)$b['status'] === 1 ? ame_l('সক্রিয়', 'Active') : ame_l('নিষ্ক্রিয়', 'Inactive'); ?></span></td>
                    <td class="oln-actions">
                        <a href="bank_form.php?id=<?php echo (int)$b['id']; ?>"><?php echo ame_l('এডিট', 'Edit'); ?></a>
                        <form action="process_bank_save.php" method="POST" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                            <button type="submit" style="border:1px solid var(--brand-border); background:var(--brand-white); border-radius:6px; padding:4px 9px; font-size:12px; cursor:pointer; color: var(--brand-accent);">
                                <?php echo (int)$b['status'] === 1 ? ame_l('নিষ্ক্রিয় করুন', 'Deactivate') : ame_l('সক্রিয় করুন', 'Activate'); ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php include '../../../partials/footer.php'; ?>
