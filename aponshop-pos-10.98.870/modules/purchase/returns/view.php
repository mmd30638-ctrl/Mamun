<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/purchase_return_functions.php';
admin_only();
ensure_purchase_return_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare(
    "SELECT pr.*, uc.username as created_by_name, ua.username as approved_by_name
     FROM purchase_returns pr
     LEFT JOIN users uc ON pr.created_by = uc.id
     LEFT JOIN users ua ON pr.approved_by = ua.id
     WHERE pr.id = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$ret = $stmt->get_result()->fetch_assoc();

if (!$ret) {
    die('❌ রিটার্ন খুঁজে পাওয়া যায়নি। <a href="register.php">তালিকায় ফিরুন</a>');
}

$items_stmt = $db->prepare(
    "SELECT pri.*, p.name as product_name FROM purchase_return_items pri JOIN products p ON pri.product_id = p.id WHERE pri.return_id = ?"
);
$items_stmt->bind_param('i', $id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$timeline = get_return_timeline($db, $id);

include __DIR__ . '/../../../partials/header.php';

$status_meta = [
    'draft' => ['label' => 'খসড়া', 'bg' => '#F1F5F9', 'color' => '#64748B'],
    'pending_approval' => ['label' => '⏳ অনুমোদনের অপেক্ষায়', 'bg' => '#FFF3E0', 'color' => '#E65100'],
    'approved' => ['label' => '✅ অনুমোদিত (ডিসপ্যাচ/সম্পন্ন করা বাকি)', 'bg' => '#E1F5FE', 'color' => '#0091EA'],
    'completed' => ['label' => '🎉 সম্পন্ন', 'bg' => '#E8F5E9', 'color' => '#0B8043'],
    'cancelled' => ['label' => '❌ বাতিল', 'bg' => '#FFEBEE', 'color' => '#C62828'],
];
$meta = $status_meta[$ret['status']] ?? $status_meta['draft'];
?>
<style>
    .pv-wrap { padding: 15px; max-width: 620px; margin: 0 auto; }
    .pv-card { background:#fff; border-radius:18px; box-shadow: 0 12px 40px rgba(11,30,77,0.12); overflow:hidden; }
    .pv-header { background: linear-gradient(135deg, #7B1FA2, #AB47BC); color:#fff; padding: 20px 24px; }
    .pv-header h2 { margin:0 0 4px; font-size:18px; }
    .pv-badge { display:inline-block; margin-top:8px; padding:5px 12px; border-radius:20px; font-size:11.5px; font-weight:800; }
    .pv-body { padding: 22px 24px 26px; }
    .pv-info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px 16px; margin-bottom:18px; font-size:12.5px; }
    .pv-info-grid strong { color:#0B1E4D; }
    .pv-table { width:100%; border-collapse:collapse; font-size:12.5px; margin-bottom:16px; }
    .pv-table th { background:#7B1FA2; color:#fff; padding:8px; text-align:left; }
    .pv-table td { padding:8px; border-bottom:1px solid #F1F5F9; }
    .pv-total-row { background:#F3E5F5; font-weight:800; }
    .pv-section { border:1.5px solid #F3E5F5; border-radius:12px; padding:14px; margin-bottom:14px; }
    .pv-section h4 { margin:0 0 10px; color:#0B1E4D; font-size:13px; }
    .pv-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .pv-timeline { list-style:none; margin:0; padding:0; }
    .pv-timeline li { position:relative; padding:0 0 18px 26px; border-left:2px solid #F3E5F5; }
    .pv-timeline li:last-child { border-left:2px solid transparent; padding-bottom:0; }
    .pv-timeline li::before { content:''; position:absolute; left:-7px; top:2px; width:12px; height:12px; border-radius:50%; background:#7B1FA2; border:2px solid #fff; box-shadow:0 0 0 2px #F3E5F5; }
    .pv-timeline-action { font-weight:800; color:#0B1E4D; font-size:12.5px; }
    .pv-timeline-meta { font-size:11px; color:#94A3B8; margin-top:2px; }
    .pv-timeline-comment { font-size:12px; color:#334155; margin-top:3px; }
    .pv-btn { flex:1; min-width:120px; padding:11px; border:none; border-radius:10px; font-weight:800; font-size:12.5px; cursor:pointer; }
    .pv-btn-approve { background: linear-gradient(135deg,#0091EA,#4FC3F7); color:#fff; }
    .pv-btn-complete { background: linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; }
    .pv-btn-cancel { background:#FFEBEE; color:#C62828; }
    .pv-btn-secondary { background:#F3E5F5; color:#7B1FA2; }
    .pv-form-group input, .pv-form-group select, .pv-form-group textarea { width:100%; padding:9px; border:1.5px solid #F3E5F5; border-radius:8px; font-size:12.5px; margin-bottom:8px; box-sizing:border-box; }
    @media print { .no-print { display:none !important; } }
</style>

<div class="pv-wrap">
    <div class="pv-card memo-wrapper" id="memo-container" data-ame-capture="clone">
        <div class="pv-header">
            <h2><?php echo htmlspecialchars($ret['return_no']); ?></h2>
            <span style="font-size:12px; opacity:.85;">মেমো: <?php echo htmlspecialchars($ret['memo_no'] ?: '—'); ?></span>
            <br><span class="pv-badge" style="background:<?php echo $meta['bg']; ?>; color:<?php echo $meta['color']; ?>;"><?php echo $meta['label']; ?></span>
        </div>
        <div class="pv-body">
            <?php if (!empty($_SESSION['success'])): ?>
                <div style="background:#E8F5E9; color:#0B8043; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px;">
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
                <div style="background:#FFEBEE; color:#C62828; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px;">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="pv-info-grid">
                <div><strong>সাপ্লায়ার:</strong> <?php echo htmlspecialchars($ret['supplier_name'] ?: 'N/A'); ?></div>
                <div><strong>রিফান্ড ধরন:</strong> <?php
                    $rt_labels = ['cash_refund' => '💵 নগদ', 'bank_transfer' => '🏦 ব্যাংক ট্রান্সফার', 'mobile_banking' => '📱 মোবাইল ব্যাংকিং', 'supplier_credit' => '📝 ক্রেডিট নোট'];
                    echo $rt_labels[$ret['refund_type']] ?? htmlspecialchars($ret['refund_type']);
                ?></div>
                <div><strong>তৈরি করেছেন:</strong> <?php echo htmlspecialchars($ret['created_by_name'] ?? '—'); ?></div>
                <div><strong>অনুমোদন করেছেন:</strong> <?php echo htmlspecialchars($ret['approved_by_name'] ?? '—'); ?></div>
                <div><strong>তারিখ:</strong> <?php echo date('d M Y', strtotime($ret['created_at'])); ?></div>
                <div><strong>ক্রেডিট নোট নং:</strong> <?php echo htmlspecialchars($ret['credit_note_no'] ?: '—'); ?></div>
            </div>

            <div style="background:#FFF3E0; border-radius:10px; padding:10px 12px; margin-bottom:12px; font-size:12.5px;">
                <strong>কারণ:</strong> <?php echo htmlspecialchars($ret['reason'] ?: '—'); ?>
            </div>
            <?php if (!empty($ret['notes'])): ?>
            <div style="background:#EAF4FF; border-radius:10px; padding:10px 12px; margin-bottom:16px; font-size:12.5px;">
                <strong>📝 নোট:</strong> <?php echo nl2br(htmlspecialchars($ret['notes'])); ?>
            </div>
            <?php endif; ?>

            <table class="pv-table">
                <thead><tr><th>পণ্য</th><th style="text-align:right;">ফেরত পরিমাণ</th><th style="text-align:right;">ফ্রি</th><th style="text-align:right;">মূল্য</th><th style="text-align:right;">মোট</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$it['returned_qty'], 2); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)($it['free_qty'] ?? 0), 2); ?></td>
                        <td style="text-align:right;">৳<?php echo number_format((float)$it['unit_price'], 2); ?></td>
                        <td style="text-align:right;">৳<?php echo number_format((float)$it['line_total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="pv-total-row">
                        <td colspan="4">সর্বমোট</td>
                        <td style="text-align:right;">৳<?php echo number_format((float)$ret['return_amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="pur-ok-acts no-print" id="prOkActs">
                <button type="button" id="prShare">
                    <span class="pur-ok-ico"><svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="2.2"/><circle cx="6" cy="12" r="2.2"/><circle cx="18" cy="19" r="2.2"/><path d="M8 11.2 16 6.4M8 12.8l8 4.6"/></svg></span>
                    শেয়ার
                </button>
                <button type="button" id="prDownload">
                    <span class="pur-ok-ico"><svg viewBox="0 0 24 24"><path d="M12 4v11"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/></svg></span>
                    ডাউনলোড
                </button>
                <button type="button" id="prView">
                    <span class="pur-ok-ico"><svg viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></span>
                    দেখুন
                </button>
            </div>
            <a class="pur-ok-home no-print" href="/index.php">হোম পেজে যান</a>

            <div class="pv-section">
                <h4>🔍 Quality Inspection</h4>
                <p style="font-size:12.5px; margin:0 0 8px;">স্ট্যাটাস: <strong><?php echo htmlspecialchars($ret['inspection_status']); ?></strong><?php echo $ret['inspection_note'] ? ' — ' . htmlspecialchars($ret['inspection_note']) : ''; ?></p>
            </div>

            <div class="pv-section">
                <h4>🤝 সাপ্লায়ারের প্রতিক্রিয়া</h4>
                <p style="font-size:12.5px; margin:0 0 8px;">স্ট্যাটাস: <strong><?php echo htmlspecialchars($ret['supplier_response']); ?></strong><?php echo $ret['supplier_response_note'] ? ' — ' . htmlspecialchars($ret['supplier_response_note']) : ''; ?></p>
                <?php if (in_array($ret['status'], ['approved'], true)): ?>
                <form action="actions/update_supplier_response.php" method="POST" class="no-print">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
                    <select name="supplier_response" class="pv-form-group">
                        <option value="pending">অপেক্ষমাণ</option>
                        <option value="accepted">গৃহীত</option>
                        <option value="rejected">প্রত্যাখ্যাত</option>
                        <option value="disputed">Dispute</option>
                    </select>
                    <input type="text" name="note" class="pv-form-group" placeholder="নোট (ঐচ্ছিক)">
                    <button type="submit" class="pv-btn pv-btn-secondary" style="width:100%;">আপডেট করুন</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="pv-section">
                <h4>🕒 Return History Timeline</h4>
                <?php if (empty($timeline)): ?>
                    <p style="font-size:12px; color:#94A3B8; margin:0;">এখনো কোনো কার্যক্রম রেকর্ড হয়নি।</p>
                <?php else: ?>
                <ul class="pv-timeline">
                    <?php
                    $action_labels = [
                        'created' => '📝 তৈরি করা হয়েছে',
                        'approved' => '✅ অনুমোদিত হয়েছে',
                        'completed' => '🎉 সম্পন্ন হয়েছে',
                        'cancelled' => '❌ বাতিল হয়েছে',
                        'supplier_response' => '🤝 সাপ্লায়ারের প্রতিক্রিয়া',
                    ];
                    foreach ($timeline as $log):
                    ?>
                    <li>
                        <div class="pv-timeline-action"><?php echo $action_labels[$log['action']] ?? htmlspecialchars($log['action']); ?></div>
                        <div class="pv-timeline-meta"><?php echo htmlspecialchars($log['username'] ?? 'সিস্টেম'); ?> • <?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></div>
                        <?php if (!empty($log['comment'])): ?>
                            <div class="pv-timeline-comment"><?php echo htmlspecialchars($log['comment']); ?></div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <div class="pv-actions no-print">
                <?php if ($ret['status'] === 'pending_approval'): ?>
                    <form action="actions/approve.php" method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
                        <button type="submit" class="pv-btn pv-btn-approve" style="width:100%;">✅ অনুমোদন করুন</button>
                    </form>
                    <form action="actions/cancel.php" method="POST" style="flex:1;" onsubmit="return confirm('নিশ্চিত বাতিল করতে চান?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
                        <button type="submit" class="pv-btn pv-btn-cancel" style="width:100%;">❌ বাতিল করুন</button>
                    </form>
                <?php elseif ($ret['status'] === 'approved'): ?>
                    <form action="actions/complete.php" method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$ret['id']; ?>">
                        <button type="submit" class="pv-btn pv-btn-complete" style="width:100%;">🎉 সম্পন্ন করুন (স্টক ও হিসাব আপডেট)</button>
                    </form>
                <?php endif; ?>
            </div>

            <div style="margin-top:16px; text-align:center;" class="no-print">
                <a href="register.php" style="color:#7B1FA2; text-decoration:none; font-size:13px;">← রেজিস্টারে ফিরে যান</a>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    document.getElementById('prShare').addEventListener('click', function () {
        if (typeof window.ameMemoCapture === 'function') {
            window.ameMemoCapture('share', <?php echo json_encode('purchase-return-' . $ret['return_no']); ?>, 'memo-container');
        }
    });
    document.getElementById('prDownload').addEventListener('click', function () {
        if (typeof window.ameMemoCapture === 'function') {
            window.ameMemoCapture('download', <?php echo json_encode('purchase-return-' . $ret['return_no']); ?>, 'memo-container');
        }
    });
    document.getElementById('prView').addEventListener('click', function () {
        var box = document.getElementById('memo-container');
        if (box) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
