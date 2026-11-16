<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/invoice_functions.php';
admin_only();
ensure_invoice_designer_schema($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$template_id = (int)($_GET['id'] ?? 0);
$loaded_layout = null;
$template_name = 'নতুন টেমপ্লেট';

if ($template_id > 0) {
    $stmt = $db->prepare("SELECT * FROM invoice_layout_templates WHERE id = ?");
    $stmt->bind_param('i', $template_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $loaded_layout = $row['layout_json'];
        $template_name = $row['name'];
    }
}

$templates_result = $db->query("SELECT id, name, is_active FROM invoice_layout_templates ORDER BY id DESC");
$templates = $templates_result ? $templates_result->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../../../partials/header.php';
?>
<style>
    .ld-app { padding: 10px; }
    .ld-toolbar { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; align-items:center; }
    .ld-btn {
        border:none; border-radius:8px; padding:8px 12px; font-size:12px; font-weight:700;
        cursor:pointer; display:inline-flex; align-items:center; gap:5px; background:#EAF4FF; color:#0B1E4D;
    }
    .ld-btn.primary { background: linear-gradient(135deg,#0B1E4D,#0091EA); color:#fff; }
    .ld-btn.success { background: linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; }
    .ld-btn.danger { background:#FFEBEE; color:#C62828; }
    .ld-name-input { flex:1; min-width:140px; padding:8px 10px; border:1.5px solid #CFE3F7; border-radius:8px; font-size:12.5px; }

    .ld-layout { display:flex; flex-direction:column; gap:10px; }
    .ld-palette {
        display:flex; gap:6px; overflow-x:auto; padding:10px; background:#fff; border-radius:12px;
        box-shadow:0 4px 14px rgba(11,30,77,0.06);
    }
    .ld-comp-btn {
        flex:0 0 auto; display:flex; flex-direction:column; align-items:center; gap:4px;
        padding:8px 10px; border-radius:10px; border:1px solid #EAF4FF; background:#fff; cursor:pointer;
        font-size:10px; color:#0B1E4D; font-weight:700; min-width:64px;
    }
    .ld-comp-btn:active { background:#EAF4FF; }

    .ld-canvas-wrap {
        position:relative; background:#fff; border-radius:12px; box-shadow:0 4px 14px rgba(11,30,77,0.06);
        overflow:auto; height:60vh;
    }
    #ldCanvas {
        position:relative; width:900px; height:900px;
        background-image: linear-gradient(#F1F5F9 1px, transparent 1px), linear-gradient(90deg, #F1F5F9 1px, transparent 1px);
        background-size: 20px 20px;
    }
    .ld-block {
        position:absolute; border:1.5px solid #90CAF9; border-radius:6px; background:#fff;
        box-sizing:border-box; overflow:hidden; cursor:move; padding:6px 8px; font-size:11px;
    }
    .ld-block.selected { border-color:#0091EA; box-shadow:0 0 0 3px rgba(0,145,234,0.18); }
    .ld-block-title { font-weight:800; color:#0B1E4D; font-size:10.5px; margin-bottom:3px; display:flex; justify-content:space-between; }
    .ld-block-row { display:flex; justify-content:space-between; font-size:10px; color:#334155; padding:1px 0; }
    .ld-resize-handle {
        position:absolute; right:-5px; bottom:-5px; width:14px; height:14px; border-radius:50%;
        background:#0091EA; border:2px solid #fff; cursor:nwse-resize;
    }

    .ld-props { background:#fff; border-radius:12px; padding:12px; box-shadow:0 4px 14px rgba(11,30,77,0.06); }
    .ld-props h4 { margin:0 0 10px; color:#0B1E4D; font-size:13px; }
    .ld-prop-row { display:flex; gap:8px; margin-bottom:8px; }
    .ld-prop-row > div { flex:1; }
    .ld-prop-row label { display:block; font-size:10px; color:#64748B; font-weight:700; margin-bottom:3px; }
    .ld-prop-row input[type=text], .ld-prop-row input[type=number] {
        width:100%; padding:7px 8px; border:1.5px solid #CFE3F7; border-radius:6px; font-size:12px; box-sizing:border-box;
    }
    .ld-prop-row input[type=color] { width:100%; height:32px; border:1.5px solid #CFE3F7; border-radius:6px; padding:2px; }
    .ld-empty-props { color:#94A3B8; font-size:12px; text-align:center; padding:20px 0; }

    .ld-code-box {
        background:#0A0E27; color:#22D3EE; padding:12px; border-radius:10px; font-family:monospace;
        font-size:11px; white-space:pre-wrap; word-break:break-all; max-height:220px; overflow:auto; margin-top:10px;
    }
</style>

<div class="ld-app">
    <div class="ld-toolbar">
        <input type="text" id="ldTemplateName" class="ld-name-input" value="<?php echo htmlspecialchars($template_name); ?>" placeholder="টেমপ্লেটের নাম">
        <button class="ld-btn" onclick="ldAddBlock('invoice_info')">➕ বক্স</button>
        <button class="ld-btn" onclick="ldDuplicate()">📋 কপি</button>
        <button class="ld-btn danger" onclick="ldDelete()">🗑️ মুছুন</button>
        <button class="ld-btn" onclick="ldReorder(1)">⬆️ সামনে</button>
        <button class="ld-btn" onclick="ldReorder(-1)">⬇️ পেছনে</button>
        <button class="ld-btn" onclick="ldShowCode()">💻 কোড</button>
        <button class="ld-btn success" onclick="ldSave()">💾 সংরক্ষণ করুন</button>
    </div>

    <div class="ld-layout">
        <div class="ld-palette">
            <div class="ld-comp-btn" onclick="ldAddBlock('invoice_info')">🧾<span>Invoice Info</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('customer_info')">👤<span>Customer</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('product_table')">📦<span>Product Table</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('payment_info')">💳<span>Payment</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('summary_box')">🧮<span>Summary</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('discount')">🏷️<span>Discount</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('shipping')">🚚<span>Shipping</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('notes')">📝<span>Notes</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('terms')">📋<span>Terms</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('button_save')">💾<span>Save Btn</span></div>
            <div class="ld-comp-btn" onclick="ldAddBlock('button_print')">🖨️<span>Print Btn</span></div>
        </div>

        <div class="ld-canvas-wrap">
            <div id="ldCanvas"></div>
        </div>

        <div class="ld-props" id="ldPropsPanel">
            <h4>⚙️ প্রপার্টিজ</h4>
            <div class="ld-empty-props" id="ldEmptyProps">একটা বক্স নির্বাচন করুন</div>
            <div id="ldPropsBody" hidden>
                <div class="ld-prop-row">
                    <div><label>X</label><input type="number" id="propX"></div>
                    <div><label>Y</label><input type="number" id="propY"></div>
                </div>
                <div class="ld-prop-row">
                    <div><label>প্রস্থ (W)</label><input type="number" id="propW"></div>
                    <div><label>উচ্চতা (H)</label><input type="number" id="propH"></div>
                </div>
                <div class="ld-prop-row">
                    <div><label>ব্যাকগ্রাউন্ড</label><input type="color" id="propBg"></div>
                    <div><label>বর্ডার কালার</label><input type="color" id="propBorder"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="ldCodePreview" hidden>
        <h4 style="color:#0B1E4D; font-size:13px; margin:12px 0 4px;">💻 কোড প্রিভিউ (JSON লেআউট)</h4>
        <div class="ld-code-box" id="ldCodeBox"></div>
    </div>

    <?php if (!empty($templates)): ?>
    <div style="margin-top:16px; background:#fff; border-radius:12px; padding:14px; box-shadow:0 4px 14px rgba(11,30,77,0.06);">
        <h4 style="margin:0 0 10px; color:#0B1E4D; font-size:13px;">📂 সংরক্ষিত টেমপ্লেট</h4>
        <?php foreach ($templates as $t): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px;">
                <span><?php echo htmlspecialchars($t['name']); ?> <?php if ($t['is_active']): ?><span style="background:#E1F5FE;color:#0091EA;padding:2px 8px;border-radius:10px;font-size:10px;">সক্রিয়</span><?php endif; ?></span>
                <div style="display:flex; gap:6px;">
                    <a href="?id=<?php echo (int)$t['id']; ?>" style="color:#0091EA; text-decoration:none; font-weight:700;">এডিট</a>
                    <?php if (!$t['is_active']): ?>
                    <form method="post" action="actions/activate.php" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>"><button type="submit" style="border:0;background:none;padding:0;color:#0B8043;font-weight:700;cursor:pointer;">সক্রিয় করুন</button></form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
var CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
var TEMPLATE_ID = <?php echo (int)$template_id; ?>;

var blockDefaults = {
    invoice_info: { w: 260, h: 110, title: 'Invoice Info', rows: ['Invoice No: INV-0001', 'Invoice Date: 25-05-2024', 'Due Date: 08-06-2024'] },
    customer_info: { w: 260, h: 110, title: 'Customer Info', rows: ['Name: ---', 'Phone: ---', 'Address: ---'] },
    product_table: { w: 560, h: 160, title: 'Product Table', rows: ['SL | Product | Qty | Price | Total', '1  | ------- | --- | ----- | -----'] },
    payment_info: { w: 200, h: 70, title: 'Payment Info', rows: ['Method: Cash'] },
    summary_box: { w: 200, h: 120, title: 'Summary', rows: ['Sub Total: 0', 'Discount: 0', 'Grand Total: 0'] },
    discount: { w: 160, h: 60, title: 'Discount', rows: ['Discount %: 0'] },
    shipping: { w: 160, h: 60, title: 'Shipping', rows: ['Shipping Cost: 0'] },
    notes: { w: 400, h: 80, title: 'Notes', rows: ['Write any additional notes...'] },
    terms: { w: 260, h: 80, title: 'Terms & Condition', rows: ['Write terms and condition...'] },
    button_save: { w: 140, h: 40, title: '💾 Save', rows: [] },
    button_print: { w: 140, h: 40, title: '🖨️ Print', rows: [] }
};

var blocks = [];
var nextId = 1;
var selectedId = null;
var canvas = document.getElementById('ldCanvas');

function ldAddBlock(type) {
    var def = blockDefaults[type] || { w: 160, h: 60, title: type, rows: [] };
    var block = {
        id: nextId++,
        type: type,
        x: 20 + (blocks.length % 5) * 15,
        y: 20 + (blocks.length % 5) * 15,
        w: def.w,
        h: def.h,
        bg: '#ffffff',
        border: '#90caf9',
        z: blocks.length + 1
    };
    blocks.push(block);
    ldRender();
    ldSelect(block.id);
}

function ldRender() {
    canvas.innerHTML = '';
    blocks.forEach(function (b) {
        var def = blockDefaults[b.type] || { title: b.type, rows: [] };
        var el = document.createElement('div');
        el.className = 'ld-block' + (b.id === selectedId ? ' selected' : '');
        el.style.left = b.x + 'px';
        el.style.top = b.y + 'px';
        el.style.width = b.w + 'px';
        el.style.height = b.h + 'px';
        el.style.background = b.bg;
        el.style.borderColor = b.border;
        el.style.zIndex = b.z;
        el.dataset.id = b.id;

        var titleHtml = '<div class="ld-block-title">' + def.title + '</div>';
        var rowsHtml = def.rows.map(function (r) { return '<div class="ld-block-row">' + r + '</div>'; }).join('');
        el.innerHTML = titleHtml + rowsHtml + '<div class="ld-resize-handle"></div>';

        el.addEventListener('mousedown', function (e) {
            if (e.target.classList.contains('ld-resize-handle')) return;
            ldSelect(b.id);
            ldStartDrag(e, b);
        });
        el.addEventListener('touchstart', function (e) {
            if (e.target.classList.contains('ld-resize-handle')) return;
            ldSelect(b.id);
            ldStartDrag(e.touches[0], b);
        }, { passive: true });

        var handle = el.querySelector('.ld-resize-handle');
        handle.addEventListener('mousedown', function (e) {
            e.stopPropagation();
            ldSelect(b.id);
            ldStartResize(e, b);
        });

        canvas.appendChild(el);
    });
}

function ldStartDrag(e, b) {
    var startX = e.clientX, startY = e.clientY;
    var origX = b.x, origY = b.y;

    function move(ev) {
        var cx = ev.touches ? ev.touches[0].clientX : ev.clientX;
        var cy = ev.touches ? ev.touches[0].clientY : ev.clientY;
        b.x = Math.max(0, origX + (cx - startX));
        b.y = Math.max(0, origY + (cy - startY));
        ldRender();
        ldSelect(b.id);
    }
    function up() {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
        document.removeEventListener('touchmove', move);
        document.removeEventListener('touchend', up);
    }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
    document.addEventListener('touchmove', move);
    document.addEventListener('touchend', up);
}

function ldStartResize(e, b) {
    var startX = e.clientX, startY = e.clientY;
    var origW = b.w, origH = b.h;

    function move(ev) {
        b.w = Math.max(60, origW + (ev.clientX - startX));
        b.h = Math.max(30, origH + (ev.clientY - startY));
        ldRender();
        ldSelect(b.id);
    }
    function up() {
        document.removeEventListener('mousemove', move);
        document.removeEventListener('mouseup', up);
    }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
}

function ldSelect(id) {
    selectedId = id;
    ldRender();
    var b = blocks.find(function (x) { return x.id === id; });
    var empty = document.getElementById('ldEmptyProps');
    var body = document.getElementById('ldPropsBody');
    if (!b) {
        empty.hidden = false;
        body.hidden = true;
        return;
    }
    empty.hidden = true;
    body.hidden = false;
    document.getElementById('propX').value = b.x;
    document.getElementById('propY').value = b.y;
    document.getElementById('propW').value = b.w;
    document.getElementById('propH').value = b.h;
    document.getElementById('propBg').value = b.bg;
    document.getElementById('propBorder').value = b.border;
}

['propX', 'propY', 'propW', 'propH', 'propBg', 'propBorder'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', function () {
        var b = blocks.find(function (x) { return x.id === selectedId; });
        if (!b) return;
        b.x = parseInt(document.getElementById('propX').value) || 0;
        b.y = parseInt(document.getElementById('propY').value) || 0;
        b.w = Math.max(30, parseInt(document.getElementById('propW').value) || 60);
        b.h = Math.max(20, parseInt(document.getElementById('propH').value) || 30);
        b.bg = document.getElementById('propBg').value;
        b.border = document.getElementById('propBorder').value;
        ldRender();
    });
});

function ldDuplicate() {
    var b = blocks.find(function (x) { return x.id === selectedId; });
    if (!b) { alert('আগে একটা বক্স নির্বাচন করুন'); return; }
    var copy = Object.assign({}, b, { id: nextId++, x: b.x + 20, y: b.y + 20 });
    blocks.push(copy);
    ldRender();
    ldSelect(copy.id);
}

function ldDelete() {
    if (!selectedId) { alert('আগে একটা বক্স নির্বাচন করুন'); return; }
    blocks = blocks.filter(function (x) { return x.id !== selectedId; });
    selectedId = null;
    ldRender();
    ldSelect(null);
}

function ldReorder(dir) {
    var b = blocks.find(function (x) { return x.id === selectedId; });
    if (!b) return;
    b.z = Math.max(1, b.z + dir);
    ldRender();
}

function ldShowCode() {
    var box = document.getElementById('ldCodePreview');
    box.hidden = !box.hidden;
    document.getElementById('ldCodeBox').textContent = JSON.stringify(blocks, null, 2);
}

function ldSave() {
    var name = document.getElementById('ldTemplateName').value.trim();
    if (!name) { alert('টেমপ্লেটের একটা নাম দিন।'); return; }
    if (blocks.length === 0) { alert('কমপক্ষে একটা বক্স যোগ করুন।'); return; }

    fetch('actions/save_template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            id: TEMPLATE_ID,
            name: name,
            layout: blocks
        })
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.status === 'success') {
                alert('টেমপ্লেট সংরক্ষণ করা হয়েছে।');
                window.location.href = '?id=' + data.id;
            } else {
                alert(data.message || 'সংরক্ষণে সমস্যা হয়েছে।');
            }
        })
        .catch(function () {
            alert('সার্ভারে সংযোগে সমস্যা হয়েছে।');
        });
}

<?php if ($loaded_layout): ?>
try {
    blocks = <?php echo $loaded_layout; ?>;
    nextId = Math.max.apply(null, blocks.map(function (b) { return b.id; }).concat([0])) + 1;
    ldRender();
} catch (e) {
    console.error('টেমপ্লেট লোড করতে সমস্যা হয়েছে', e);
}
<?php else: ?>
ldRender();
<?php endif; ?>
</script>
