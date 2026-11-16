<?php
require_once '../../includes/config.php';
require_once '../../includes/photo_relink.php';
require_once '../../modules/customers/customer_schema.php';
admin_only();

$flash_ok = '';
$flash_err = '';
$report = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    try {
        if (!empty($_FILES['photo_zip']['tmp_name']) && is_uploaded_file($_FILES['photo_zip']['tmp_name'])) {
            $err = (int)($_FILES['photo_zip']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                throw new RuntimeException('ZIP আপলোড হয়নি (কোড ' . $err . ')। File Manager দিয়ে কপি করুন, অথবা ছোট ZIP দিন।');
            }
            $extracted = ame_extract_photo_zip($_FILES['photo_zip']['tmp_name']);
            $flash_ok = $extracted['saved'] . 'টি ছবি uploads/customers-এ রাখা হয়েছে।';
            if ($extracted['skipped'] > 0) {
                $flash_ok .= ' ' . $extracted['skipped'] . 'টি ফাইল বাদ (শুধু ছবি নেয়)।';
            }
        }
        $report = ame_relink_customer_photos($db);
        log_activity('relink_photos', 'customers', json_encode($report, JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {
        $flash_err = $e->getMessage();
    }
}

$preview = ['mapped' => 0, 'present' => 0];
try {
    $rows = ame_legacy_photo_rows($db);
    $preview['mapped'] = count($rows);
    foreach ($rows as $row) {
        if (ame_photo_file_present($row['file'])) {
            $preview['present']++;
        }
    }
} catch (Throwable $e) {
    $preview['error'] = $e->getMessage();
}

include '../../partials/header.php';
?>
<style>
.pho-wrap { padding: 15px; }
.pho-card { background:#fff; border-radius:14px; max-width:560px; margin:0 auto; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,.08); }
.pho-top { display:flex; height:6px; }
.pho-top span { flex:1; }
.pho-c1{background:#0B1E4D;} .pho-c2{background:#1D9BF0;} .pho-c3{background:#C9A227;}
.pho-h { text-align:center; padding:20px 16px 8px; }
.pho-h h3 { margin:0; color:#0B1E4D; font-size:19px; }
.pho-b { padding:8px 18px 22px; font-size:14px; line-height:1.65; color:#1e2937; }
.pho-step { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin:10px 0; }
.pho-step b { display:block; color:#0B1E4D; margin-bottom:4px; }
.pho-ok { background:#e8f5e9; border:1px solid #81c784; color:#1b5e20; border-radius:10px; padding:12px; margin:10px 0; }
.pho-err { background:#ffebee; border:1px solid #ef9a9a; color:#b71c1c; border-radius:10px; padding:12px; margin:10px 0; }
.pho-meta { background:#e8f4ff; border:1px solid #90caf9; border-radius:10px; padding:12px; margin:10px 0; font-size:13px; }
.pho-file { width:100%; box-sizing:border-box; padding:12px; border:2px dashed #1D9BF0; border-radius:10px; margin:8px 0; }
.pho-btn { display:block; width:100%; box-sizing:border-box; text-align:center; padding:14px; border:0; border-radius:10px; background:linear-gradient(135deg,#0B1E4D,#1D9BF0); color:#fff; font-weight:800; font-size:16px; cursor:pointer; text-decoration:none; }
.pho-back { display:block; text-align:center; margin-top:14px; color:#64748b; text-decoration:none; }
code { background:#f1f5f9; padding:1px 5px; border-radius:4px; }
</style>
<div class="pho-wrap">
  <div class="pho-card">
    <div class="pho-top"><span class="pho-c1"></span><span class="pho-c2"></span><span class="pho-c3"></span></div>
    <div class="pho-h">
      <h3>পুরনো ক্রেতা ছবি চালু করুন</h3>
    </div>
    <div class="pho-b">
      <p>ডাটাবেজে <b><?php echo (int)$preview['mapped']; ?></b>টি পুরনো ফাইলনেম আছে। সার্ভারে এখন ফাইল পাওয়া গেছে <b><?php echo (int)$preview['present']; ?></b>টি। ফাইল না থাকলে অবতারই থাকবে — ভাঙা ছবি নয়।</p>

      <?php if ($flash_ok !== ''): ?><div class="pho-ok"><?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      <?php if ($flash_err !== ''): ?><div class="pho-err"><?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      <?php if (is_array($report)): ?>
      <div class="pho-meta">
        নতুন লিংক: <b><?php echo (int)$report['linked']; ?></b>
        · আগেই ছিল: <?php echo (int)$report['already']; ?>
        · ফাইল নেই: <?php echo (int)$report['missing_file']; ?>
        · ক্রেতা মেলেনি: <?php echo (int)$report['missing_customer']; ?>
      </div>
      <?php endif; ?>

      <div class="pho-step">
        <b>১. Hostinger File Manager</b>
        Search-এ এই নামগুলোর একটা দিন: <code>121671884454.jpg</code> বা <code>834.jpg</code>।
        যে ফোল্ডারে পাবেন (সাধারণত পুরনো সাইটের <code>img/customer</code>) — সব <code>.jpg</code>
        কপি করে নতুন সাইটের <code>public_html/uploads/customers/</code>-এ পেস্ট করুন। পুরনো আপলোড মুছবেন না।
      </div>
      <div class="pho-step">
        <b>২. অথবা ZIP আপলোড</b>
        সেই ফোল্ডার ZIP করে নিচে দিন। শুধু ছবি বের হবে, PHP যাবে না। বড় হলে File Managerই ভালো (হোস্টিং আপলোড লিমিট)।
      </div>
      <div class="pho-step">
        <b>৩. এক ট্যাপে লিংক</b>
        ফাইল বসার পর এই বাটন — নাম অনুযায়ী ক্রেতার সাথে জোড়া লাগবে।
      </div>

      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input class="pho-file" type="file" name="photo_zip" accept=".zip,application/zip">
        <button class="pho-btn" type="submit">📷 ছবি চালু করুন</button>
      </form>
      <a class="pho-back" href="tools.php">← টুলসে ফিরে যান</a>
    </div>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
