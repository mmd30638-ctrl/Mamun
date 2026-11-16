<?php
declare(strict_types=1);

$app = $argv[1] ?? '';
if (!in_array($app, ['hotel', 'grocery', 'pharmacy'], true)) {
    fwrite(STDERR, "usage: smoke.php hotel|grocery|pharmacy\n");
    exit(1);
}
define('VX_ROOT', __DIR__ . '/' . $app);
define('VX_APP_FILE', VX_ROOT . '/app.php');
define('VX_CORE', __DIR__ . '/core');
require VX_CORE . '/engine.php';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3307';
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3307');
$map = ['hotel' => 'vx_hotel', 'grocery' => 'vx_groc', 'pharmacy' => 'vx_pharm'];
putenv('DB_NAME=' . $map[$app]);
putenv('DB_USER=vx');
putenv('DB_PASS=vxpass');
$_ENV['DB_NAME'] = $map[$app];
$_ENV['DB_USER'] = 'vx';
$_ENV['DB_PASS'] = 'vxpass';
$_SESSION['vx_uid'] = 1;

function check(bool $ok, string $msg): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL ' . $msg);
    }
    echo "ok $msg\n";
}

vx_install('Test ' . $app, 'Admin#2026');
check(vx_installed(), 'installed');

$supplier = vx_insert('INSERT INTO ' . vx_t('parties') . ' (kind, name, phone) VALUES ("supplier","Supply Co","01700000001")', '', []);
$customer = vx_insert('INSERT INTO ' . vx_t('parties') . ' (kind, name, phone) VALUES ("customer","Karim","01700000002")', '', []);
$cash = (int) (vx_one('SELECT id FROM ' . vx_t('accounts') . " WHERE kind='cash' LIMIT 1")['id'] ?? 0);
check($cash > 0, 'cash account');
vx_tx(function () use ($cash) {
    vx_cash($cash, 'in', 5000, 'open', 0, 'opening');
});

$track = vx_feature('batch') ? 1 : 0;
$sku = 'SKU-' . strtoupper($app);
$pid = vx_insert(
    'INSERT INTO ' . vx_t('products') . ' (sku, name, kind, purchase_price, retail_price, track_batch, min_stock) VALUES (?,?,?,?,?,?,?)',
    'sssddid',
    [$sku, 'Test item', 'product', 10, 15, $track, 1]
);

$purchase = vx_post_purchase([
    'party_id' => $supplier,
    'account_id' => $cash,
    'paid' => 200,
    'lines' => [[
        'product_id' => $pid,
        'qty' => 20,
        'price' => 10,
        'unit_id' => 0,
        'batch_no' => 'B1',
        'expiry_date' => date('Y-m-d', strtotime('+40 days')),
        'mfg_date' => date('Y-m-d', strtotime('-10 days')),
        'retail' => 15,
    ]],
]);
$stock = (float) (vx_one('SELECT stock FROM ' . vx_t('products') . ' WHERE id=?', 'i', [$pid])['stock'] ?? 0);
check(abs($stock - 20) < 0.01, "stock after purchase $stock");
$payable = (float) (vx_one('SELECT payable FROM ' . vx_t('parties') . ' WHERE id=?', 'i', [$supplier])['payable'] ?? 0);
check(abs($payable - 0) < 0.01, 'supplier due zero when fully paid? paid 200 of 200');

if (vx_feature('batch')) {
    vx_post_purchase([
        'party_id' => $supplier,
        'account_id' => $cash,
        'paid' => 50,
        'lines' => [[
            'product_id' => $pid,
            'qty' => 5,
            'price' => 10,
            'batch_no' => 'B0',
            'expiry_date' => date('Y-m-d', strtotime('+5 days')),
            'mfg_date' => date('Y-m-d'),
        ]],
    ]);
}

$sale = vx_post_sale([
    'party_id' => $customer,
    'account_id' => $cash,
    'paid' => 20,
    'discount' => 0,
    'lines' => [[
        'product_id' => $pid,
        'qty' => 4,
        'price' => 15,
        'unit_id' => 0,
    ]],
]);
$stock2 = (float) (vx_one('SELECT stock FROM ' . vx_t('products') . ' WHERE id=?', 'i', [$pid])['stock'] ?? 0);
$expect = vx_feature('batch') ? 21 : 16;
check(abs($stock2 - $expect) < 0.01, "stock after sale $stock2 expected $expect");
$recv = (float) (vx_one('SELECT receivable FROM ' . vx_t('parties') . ' WHERE id=?', 'i', [$customer])['receivable'] ?? 0);
check(abs($recv - 40) < 0.01, "customer due $recv");
if (vx_feature('batch')) {
    $used = vx_one('SELECT batch_id FROM ' . vx_t('sale_items') . ' WHERE sale_id=? LIMIT 1', 'i', [$sale]);
    $batch = vx_one('SELECT batch_no FROM ' . vx_t('batches') . ' WHERE id=?', 'i', [(int) $used['batch_id']]);
    check(($batch['batch_no'] ?? '') === 'B0', 'FEFO used nearer expiry');
}
$item = vx_one('SELECT id, qty FROM ' . vx_t('sale_items') . ' WHERE sale_id=? LIMIT 1', 'i', [$sale]);
vx_post_sale_return($sale, [(int) $item['id'] => 1]);
$stock3 = (float) (vx_one('SELECT stock FROM ' . vx_t('products') . ' WHERE id=?', 'i', [$pid])['stock'] ?? 0);
check(abs($stock3 - ($expect + 1)) < 0.01, "stock after return $stock3");

vx_party_payment($customer, $cash, 10, 'receive');
$recv2 = (float) (vx_one('SELECT receivable FROM ' . vx_t('parties') . ' WHERE id=?', 'i', [$customer])['receivable'] ?? 0);
check($recv2 < $recv, 'due reduced');

if ($app === 'hotel') {
    $type = vx_insert('INSERT INTO ' . vx_t('room_types') . ' (name, base_rate) VALUES ("Deluxe", 1000)', '', []);
    $room = vx_insert('INSERT INTO ' . vx_t('rooms') . ' (room_no, room_type_id, rate, status) VALUES ("101", ?, 1000, "vacant")', 'i', [$type]);
    $guest = vx_insert('INSERT INTO ' . vx_t('parties') . ' (kind, name) VALUES ("guest","Guest A")', '', []);
    $in = date('Y-m-d');
    $out = date('Y-m-d', strtotime('+2 days'));
    $code = vx_next_memo('B');
    $bid = vx_insert(
        'INSERT INTO ' . vx_t('bookings') . ' (code, party_id, room_id, check_in, check_out, nights, rate, total, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
        'siissiddsi',
        [$code, $guest, $room, $in, $out, 2, 1000, 2000, 'checked_in', 1]
    );
    $roomProduct = (int) (vx_one('SELECT id FROM ' . vx_t('products') . " WHERE sku='ROOM'")['id'] ?? 0);
    check($roomProduct > 0, 'room service product');
    $folio = vx_post_sale([
        'party_id' => $guest,
        'account_id' => $cash,
        'paid' => 2000,
        'channel' => 'room',
        'booking_id' => $bid,
        'lines' => [['product_id' => $roomProduct, 'qty' => 2, 'price' => 1000, 'unit_id' => 0]],
    ]);
    check($folio > 0, 'folio sale');
    $still = (float) (vx_one('SELECT stock FROM ' . vx_t('products') . ' WHERE id=?', 'i', [$pid])['stock'] ?? 0);
    check(abs($still - $stock3) < 0.01, 'room charge did not change goods stock');
}

if ($app === 'grocery') {
    $from = 1;
    $to = vx_insert('INSERT INTO ' . vx_t('locations') . ' (name) VALUES ("গুদাম")', '', []);
    vx_tx(function () use ($pid, $from, $to) {
        vx_loc_add($pid, $from, -2);
        vx_loc_add($pid, $to, 2);
    });
    $loc = (float) (vx_one('SELECT qty FROM ' . vx_t('loc_stock') . ' WHERE product_id=? AND location_id=?', 'ii', [$pid, $to])['qty'] ?? 0);
    check(abs($loc - 2) < 0.01, 'transfer location qty');
    $totalStock = (float) (vx_one('SELECT stock FROM ' . vx_t('products') . ' WHERE id=?', 'i', [$pid])['stock'] ?? 0);
    check(abs($totalStock - $stock3) < 0.01, 'transfer keeps total stock');
}

echo "PASS $app\n";
