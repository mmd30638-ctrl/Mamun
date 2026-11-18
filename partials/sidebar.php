<?php
/**
 * Aponshop - সাইডবার (Core Frame)
 */
$sidebar_brand = $shop['shop_name'] ?? 'Aponshop';
$sidebar_initial = mb_strtoupper(mb_substr($sidebar_brand, 0, 1, 'UTF-8'), 'UTF-8');
?>

<!-- সাইডবার ওভারলে (মোবাইলের জন্য বাইরের অংশ ঝাপসা করা) -->
<div id="sidebarBackdrop" class="sidebar-backdrop" onclick="openNav()"></div>

<!-- মূল সাইডবার এলিমেন্ট -->
<aside id="mySidebar" class="custom-sidebar" data-vd="sidebar">

    <!-- ক্লোজ বাটন -->
    <button type="button" onclick="openNav()" class="sidebar-close-btn" title="<?php echo te('close_menu'); ?>">&times;</button>

    <!-- ব্র্যান্ডিং এরিয়া -->
    <div class="sidebar-brand-header">
        <div class="brand-logo"><?php echo htmlspecialchars($sidebar_initial, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="brand-info">
            <h4><?php echo htmlspecialchars($sidebar_brand, ENT_QUOTES, 'UTF-8'); ?></h4>
            <small style="display:block;color:#94A3B8;font-size:10px;font-weight:600;margin:2px 0 4px;line-height:1.35;"><?php echo htmlspecialchars(shop_tagline(), ENT_QUOTES, 'UTF-8'); ?></small>
            <small class="status-indicator"><span class="ping-dot"></span> <?php echo te('sidebar_status'); ?></small>
        </div>
    </div>

    <!-- মেনু লোডিং এরিয়া (sidebar-menu.php এখানে আসবে) -->
    <nav class="sidebar-menu-body">
        <?php
        $menu_file = __DIR__ . '/sidebar-menu.php';
        if (file_exists($menu_file)) {
            include $menu_file;
        } else {
            echo "<p style='color:#fb7185; padding:15px; font-size:12px;'>" . te('menu_missing') . "</p>";
        }
        ?>
    </nav>

    <!-- সাইডবার ফুটার (ইউজার প্রোফাইল) -->
    <div class="sidebar-footer">
        <div class="user-pill">
            <span class="user-pill-name"><?php echo htmlspecialchars($_SESSION['username'] ?? t('role_admin'), ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="/logout.php" title="<?php echo te('logout'); ?>" class="user-pill-logout">🚪</a>
        </div>
    </div>
</aside>