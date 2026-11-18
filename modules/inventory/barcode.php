<?php 
/**
 * সিরিয়াল ৬১: স্মার্ট বারকোড জেনারেটর (AME PRO)
 * প্রযুক্তি: Dynamic SKU Visualizer
 */
require_once '../../includes/config.php';
include '../../partials/header.php'; 

// ডাটাবেজ থেকে পণ্য নেওয়া
$prods = $db->query('SELECT id, name, sku, sale_price FROM products WHERE ' . ame_product_option_sql('status') . ' LIMIT 50');
?>

<div style="padding: 15px; text-align:center;">
    <h3 style="color: var(--primary); margin-bottom: 20px;">🎫 প্রোডাক্ট বারকোড প্রিন্টার</h3>
    
    <div class="card" style="background: white; border-radius: 15px; padding: 20px;">
        <p style="font-size:12px; color:#666;">নিচে আপনার ইনভেন্টরির সকল পণ্যের বারকোড তৈরি হয়েছে।</p>
        <hr style="border: 0.5px solid #eee;">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; margin-top:20px;">
            <?php while($p = $prods->fetch_assoc()): ?>
                <div style="border: 1px dashed #ccc; padding: 10px; background: #fff; border-radius: 5px;">
                    <small style="display:block; font-size:10px; font-weight:bold; overflow: hidden; white-space: nowrap;"><?php echo htmlspecialchars($p['name'] ?? ''); ?></small>
                    
                    <!-- বারকোড ভিজ্যুয়াল (প্রফেশনাল ফন্ট স্টাইল) -->
                    <div style="margin:10px 0; font-family: 'monospace'; letter-spacing: 2px; background:#f9f9f9; padding:5px; border-radius:3px;">
                        ||| <?php echo esc_html($p['sku'] ?: ('ID' . (int)$p['id'])); ?> |||
                    </div>
                    
                    <b style="color:var(--secondary); font-size: 13px;"><?php echo number_format((float)($p['sale_price'] ?? 0), 2); ?> ৳</b>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div style="margin-top:20px; display:flex; gap:10px;">
        <button onclick="window.print()" class="btn-success" style="flex:1; padding:15px; border-radius:10px;">🖨️ পেজ প্রিন্ট করুন</button>
        <a href="/index.php" class="btn-success" style="flex:1; background:var(--primary); text-decoration:none; padding:15px; border-radius:10px; display:block;">🏠 হোম ড্যাশবোর্ড</a>
    </div>
</div>

<style>
@media print {
    .top-header, .sidebar, .btn-success, .main-nav, .headline-bar, .scrolling-text, .ame-tbar { display: none !important; }
    body { background: white !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<?php include '../../partials/footer.php'; ?>