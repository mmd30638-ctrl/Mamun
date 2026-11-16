<?php include '../../partials/header.php';
require_once __DIR__ . '/../../includes/workshop_functions.php';
ensure_workshop_schema($db);
$serial = trim((string)($_GET['serial'] ?? ''));
$row = null;
$serial_row = null;
$jobs = [];
if ($serial !== '') {
    $stmt = $db->prepare(
        "SELECT sw.serial_no, sw.expiry_date, p.name AS prod_name, s.sale_date
         FROM sale_warranties sw
         LEFT JOIN products p ON sw.product_id = p.id
         LEFT JOIN sales s ON sw.sale_id = s.id
         WHERE sw.serial_no = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $serial);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    $ps = $db->prepare(
        "SELECT s.*, p.name AS prod_name, c.name AS customer_name
         FROM product_serials s
         LEFT JOIN products p ON p.id = s.product_id
         LEFT JOIN customers c ON c.id = s.customer_id
         WHERE s.serial_no = ?
         LIMIT 1"
    );
    if ($ps) {
        $ps->bind_param('s', $serial);
        $ps->execute();
        $serial_row = $ps->get_result()->fetch_assoc();
        $ps->close();
    }
    $jobs = [];
    $js = $db->prepare(
        'SELECT id, job_no, customer_name, item_name, status, due_amount
         FROM workshop_jobs
         WHERE serial_no = ?
         ORDER BY id DESC
         LIMIT 10'
    );
    if ($js) {
        $js->bind_param('s', $serial);
        $js->execute();
        $jobs = $js->get_result()->fetch_all(MYSQLI_ASSOC);
        $js->close();
    }
}
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'warranty-check-' . ($serial !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '', $serial) : date('Y-m-d')),
    'title' => $shop . ' · ' . ame_l('ওয়ারেন্টি চেক', 'Warranty Check'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ওয়ারেন্টি চেক', 'Warranty Check'),
        $serial !== '' ? (ame_l('সিরিয়াল', 'Serial') . ': ' . $serial) : '',
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <h3 style="color: var(--dark-blue);">🔍 ওয়ারেন্টি চেক</h3>
    <div class="card no-print">
        <form method="GET">
            <input type="text" name="serial" value="<?php echo htmlspecialchars($serial, ENT_QUOTES, 'UTF-8'); ?>" placeholder="পণ্যের সিরিয়াল / IMEI দিন" class="input-style" required style="width:100%; padding:10px; margin-bottom:10px;">
            <button type="submit" class="btn-success">সার্চ করুন</button>
        </form>
    </div>

    <?php if ($serial !== ''): ?>
    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <p style="text-align:center; font-size:12px; color:#64748B;"><?php echo ame_l('সিরিয়াল', 'Serial'); ?>: <?php echo htmlspecialchars($serial, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($row): ?>
        <div class="card" style="margin-top:20px; border: 2px solid var(--dark-green);">
            <h4 style="color:var(--dark-green);">✅ বিক্রয় ওয়ারেন্টি পাওয়া গেছে</h4>
            <p>পণ্য: <b><?php echo htmlspecialchars($row['prod_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></b></p>
            <?php if (!empty($row['sale_date'])): ?>
            <p>বিক্রয়ের তারিখ: <?php echo htmlspecialchars(date('d-m-Y', strtotime($row['sale_date'])), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if (!empty($row['expiry_date'])): ?>
            <p>মেয়াদ শেষ: <b style="color:var(--red-alert);"><?php echo htmlspecialchars(date('d-m-Y', strtotime($row['expiry_date'])), ENT_QUOTES, 'UTF-8'); ?></b></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($serial_row): ?>
        <div class="card" style="margin-top:20px; border: 2px solid var(--dark-blue);">
            <h4 style="color:var(--dark-blue);">🔢 সিরিয়াল খাতা</h4>
            <p>পণ্য: <b><?php echo htmlspecialchars($serial_row['prod_name'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></b></p>
            <p>অবস্থা: <b><?php echo htmlspecialchars($serial_row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></b></p>
            <?php if (!empty($serial_row['customer_name'])): ?>
            <p>ক্রেতা: <?php echo htmlspecialchars($serial_row['customer_name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if (!empty($serial_row['warranty_expiry'])): ?>
            <p>ওয়ারেন্টি শেষ: <b><?php echo htmlspecialchars($serial_row['warranty_expiry'], ENT_QUOTES, 'UTF-8'); ?></b></p>
            <?php endif; ?>
            <?php if ((int)($serial_row['job_id'] ?? 0) > 0): ?>
            <p><a href="/modules/workshop/job_view.php?id=<?php echo (int)$serial_row['job_id']; ?>">সংযুক্ত জব দেখুন</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($jobs)): ?>
        <div class="card" style="margin-top:20px;">
            <h4 style="color:var(--dark-blue);">🛠️ ওয়ার্কশপ জব</h4>
            <?php foreach ($jobs as $wj): ?>
                <p>
                    <a href="/modules/workshop/job_view.php?id=<?php echo (int)$wj['id']; ?>">
                        <?php echo htmlspecialchars($wj['job_no'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    — <?php echo htmlspecialchars($wj['item_name'] . ' · ' . ($wj['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars(function_exists('ame_workshop_status_label') ? ame_workshop_status_label((string)$wj['status']) : $wj['status'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!$row && !$serial_row && empty($jobs)): ?>
            <p class="text-danger" style="margin-top:20px;">❌ ওয়ারেন্টি বা সিরিয়াল খাতায় পাওয়া যায়নি।</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
