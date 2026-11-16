<?php
// Aponshop Master Menu Grid
?>
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">

    <a href="/modules/pulse/index.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">◉</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_pulse'); ?></b>
    </a>
    <a href="/modules/reports/live_work.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">◉</span><br><b style="color:var(--dark-blue); font-size:11px;">লাইভ ড্যাশবোর্ড</b>
    </a>
    <a href="/modules/smart/index.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">✦</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_smart'); ?></b>
    </a>
    
    <!-- সারি ১ -->
    <a href="modules/sales/pos.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">🧾</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_sales'); ?></b>
    </a>
    <a href="modules/purchase/add.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">🚚</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_buy'); ?></b>
    </a>
    <a href="modules/inventory/list.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">📦</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_stock'); ?></b>
    </a>

    <!-- সারি ২ -->
    <a href="modules/customers/index.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">👥</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_due'); ?></b>
    </a>
    <a href="modules/suppliers/list.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">🤝</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_supplier'); ?></b>
    </a>
    <a href="modules/accounts/list.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">💼</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_accounts'); ?></b>
    </a>
    <a href="modules/accounts/transfer.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">⇄</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('nav_txn_transfer'); ?></b>
    </a>

    <!-- সারি ৩ -->
    <a href="modules/hrm/employee_list.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">👷</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('grid_staff'); ?></b>
    </a>

    <!-- সারি ৪ -->
    <a href="modules/inventory/categories.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">📁</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('nav_category'); ?></b>
    </a>
    <a href="modules/inventory/brands.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">🏷️</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('nav_brand'); ?></b>
    </a>
    <a href="modules/inventory/barcode.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">🎫</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('nav_barcode'); ?></b>
    </a>

    <!-- সারি ৫ -->
    <a href="modules/returns/history.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">🔄</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('nav_sale_return'); ?></b>
    </a>
    <a href="modules/inventory/damage.php" class="card" style="text-decoration:none; text-align:center; padding:15px 5px; border-radius:12px; background:white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <span style="font-size:22px;">⚠️</span><br><b style="color:var(--dark-blue); font-size:11px;"><?php echo te('nav_damaged'); ?></b>
    </a>
</div>