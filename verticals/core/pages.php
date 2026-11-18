<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function vx_lines_from_post(): array
{
    $out = [];
    $products = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];
    $units = $_POST['unit_id'] ?? [];
    $batches = $_POST['batch_no'] ?? [];
    $exps = $_POST['expiry_date'] ?? [];
    $mfgs = $_POST['mfg_date'] ?? [];
    $retails = $_POST['retail'] ?? [];
    foreach ($products as $i => $pid) {
        $out[] = [
            'product_id' => (int) $pid,
            'qty' => (float) ($qtys[$i] ?? 0),
            'price' => (float) ($prices[$i] ?? 0),
            'unit_id' => (int) ($units[$i] ?? 0),
            'batch_no' => (string) ($batches[$i] ?? ''),
            'expiry_date' => (string) ($exps[$i] ?? ''),
            'mfg_date' => (string) ($mfgs[$i] ?? ''),
            'retail' => (float) ($retails[$i] ?? 0),
        ];
    }
    return $out;
}

function vx_line_form(int $rows, bool $batch): string
{
    $units = vx_opts('units', 'name', 'status=1');
    $products = vx_opts('products', 'name', 'deleted_at IS NULL AND status=1');
    $html = '';
    for ($i = 0; $i < $rows; $i++) {
        $html .= '<div class="card grid two"><div><label>' . vx_e(t('product')) . '</label><select name="product_id[]"><option value="0">—</option>' . $products . '</select></div>';
        $html .= '<div><label>' . vx_e(t('unit')) . '</label><select name="unit_id[]"><option value="0">' . vx_e(t('piece')) . '</option>' . $units . '</select></div>';
        $html .= '<div><label>' . vx_e(t('qty')) . '</label><input name="qty[]" inputmode="decimal" value=""></div>';
        $html .= '<div><label>' . vx_e(t('price')) . '</label><input name="price[]" inputmode="decimal" value=""></div>';
        if ($batch) {
            $html .= '<div><label>' . vx_e(t('batch')) . '</label><input name="batch_no[]"></div>';
            $html .= '<div><label>' . vx_e(t('mfg_date')) . '</label><input type="date" name="mfg_date[]"></div>';
            $html .= '<div><label>' . vx_e(t('expiry_date')) . '</label><input type="date" name="expiry_date[]"></div>';
            $html .= '<div><label>' . vx_e(t('retail')) . '</label><input name="retail[]" inputmode="decimal"></div>';
        }
        $html .= '</div>';
    }
    return $html;
}

function vx_page_login(): void
{
    if (vx_user()) {
        vx_go('dashboard');
    }
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $name = trim((string) ($_POST['username'] ?? ''));
        $fails = vx_one(
            'SELECT COUNT(*) c FROM ' . vx_t('audit_log') . ' WHERE action="login_fail" AND detail=? AND created_at > (NOW() - INTERVAL 10 MINUTE)',
            's',
            [$name]
        );
        if ((int) ($fails['c'] ?? 0) >= 8) {
            $err = t('locked');
        } else {
            $user = vx_one('SELECT * FROM ' . vx_t('users') . ' WHERE username=? AND status=1', 's', [$name]);
            if ($user && password_verify((string) ($_POST['password'] ?? ''), (string) $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['vx_uid'] = (int) $user['id'];
                vx_audit('login', 'users', (int) $user['id'], $name);
                vx_go('dashboard');
            }
            vx_audit('login_fail', 'users', 0, $name);
            $err = t('login_failed');
        }
    }
    $body = '<form method="post" class="card">' . vx_csrf_field();
    $body .= '<label>' . vx_e(t('username')) . '</label><input name="username" autocomplete="username" required>';
    $body .= '<label>' . vx_e(t('password')) . '</label><input type="password" name="password" autocomplete="current-password" required>';
    $body .= '<button style="margin-top:12px" type="submit">' . vx_e(t('sign_in')) . '</button></form>';
    if ($err !== '') {
        $body = '<p class="alert bad">' . vx_e($err) . '</p>' . $body;
    }
    vx_layout('login', $body, true);
}

function vx_page_install(): void
{
    if (vx_env('DB_NAME') !== '' && vx_installed()) {
        vx_go('login');
    }
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        try {
            if (!is_file(VX_ROOT . '/.env')) {
                $host = trim((string) ($_POST['db_host'] ?? 'localhost'));
                $name = trim((string) ($_POST['db_name'] ?? ''));
                $user = trim((string) ($_POST['db_user'] ?? ''));
                $pass = (string) ($_POST['db_pass'] ?? '');
                $port = trim((string) ($_POST['db_port'] ?? '3306'));
                if ($name === '' || $user === '') {
                    throw new RuntimeException(t('required'));
                }
                $body = "DB_HOST={$host}\nDB_PORT={$port}\nDB_NAME={$name}\nDB_USER={$user}\nDB_PASS={$pass}\n";
                file_put_contents(VX_ROOT . '/.env', $body);
                chmod(VX_ROOT . '/.env', 0640);
                vx_load_env(VX_ROOT . '/.env');
            }
            $pass = (string) ($_POST['admin_pass'] ?? '');
            if (strlen($pass) < 8) {
                throw new RuntimeException(t('weak_password'));
            }
            vx_install(trim((string) ($_POST['business_name'] ?? '')), $pass);
            vx_flash(t('installed'));
            vx_go('login');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
    $hasEnv = is_file(VX_ROOT . '/.env');
    $html = '<form method="post" class="card">' . vx_csrf_field();
    $html .= '<p>' . vx_e(t('install_help')) . '</p>';
    $html .= '<label>' . vx_e(t('business_name')) . '</label><input name="business_name" value="' . vx_e(vx_app()['name'][vx_lang()] ?? '') . '">';
    if (!$hasEnv) {
        $html .= '<label>' . vx_e(t('db_host')) . '</label><input name="db_host" value="localhost">';
        $html .= '<label>DB port</label><input name="db_port" value="3306">';
        $html .= '<label>' . vx_e(t('db_name')) . '</label><input name="db_name" required>';
        $html .= '<label>' . vx_e(t('db_user')) . '</label><input name="db_user" required>';
        $html .= '<label>' . vx_e(t('db_pass')) . '</label><input name="db_pass" type="password">';
    }
    $html .= '<label>' . vx_e(t('admin_password')) . '</label><input name="admin_pass" type="password" required minlength="8">';
    $html .= '<button style="margin-top:12px" type="submit">' . vx_e(t('install')) . '</button></form>';
    if ($err !== '') {
        $html = '<p class="alert bad">' . vx_e($err) . '</p>' . $html;
    }
    vx_layout('install', $html, true);
}

function vx_page_dashboard(): void
{
    vx_require('dashboard');
    $sales = (float) (vx_one('SELECT COALESCE(SUM(net),0) s FROM ' . vx_t('sales') . ' WHERE DATE(created_at)=CURDATE()')['s'] ?? 0);
    $dueC = (float) (vx_one('SELECT COALESCE(SUM(receivable),0) s FROM ' . vx_t('parties') . ' WHERE deleted_at IS NULL')['s'] ?? 0);
    $dueS = (float) (vx_one('SELECT COALESCE(SUM(payable),0) s FROM ' . vx_t('parties') . ' WHERE deleted_at IS NULL')['s'] ?? 0);
    $cash = (float) (vx_one('SELECT COALESCE(SUM(balance),0) s FROM ' . vx_t('accounts') . " WHERE kind='cash' AND status=1")['s'] ?? 0);
    $low = (int) (vx_one('SELECT COUNT(*) c FROM ' . vx_t('products') . ' WHERE deleted_at IS NULL AND kind<>"service" AND stock<=min_stock')['c'] ?? 0);
    $html = '<div class="kpis">';
    foreach ([[$sales, 'sales_today'], [$dueC, 'customer_due'], [$dueS, 'supplier_due'], [$cash, 'cash']] as $kpi) {
        $html .= '<div class="kpi"><b>' . vx_e(vx_money($kpi[0])) . '</b><span>' . vx_e(t($kpi[1])) . '</span></div>';
    }
    $html .= '</div><div class="card"><p>' . vx_e(t('low_stock')) . ': <b>' . $low . '</b></p>';
    if (vx_feature('rooms')) {
        $occ = (int) (vx_one('SELECT COUNT(*) c FROM ' . vx_t('rooms') . " WHERE deleted_at IS NULL AND status='occupied'")['c'] ?? 0);
        $html .= '<p>' . vx_e(t('occupied')) . ': <b>' . $occ . '</b></p>';
    }
    if (vx_feature('batch')) {
        $exp = (int) (vx_one('SELECT COUNT(*) c FROM ' . vx_t('batches') . ' WHERE qty>0 AND expiry_date IS NOT NULL AND expiry_date<CURDATE()')['c'] ?? 0);
        $html .= '<p>' . vx_e(t('expired')) . ': <b>' . $exp . '</b></p>';
    }
    $html .= '<p class="row-actions"><a class="btn" href="index.php?r=sale">' . vx_e(t('sale')) . '</a><a class="btn alt" href="index.php?r=purchase">' . vx_e(t('purchase')) . '</a></p></div>';
    vx_layout('dashboard', $html);
}

function vx_page_parties(): void
{
    vx_require('parties');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $act = (string) ($_POST['act'] ?? 'save');
        if ($act === 'pay') {
            vx_party_payment((int) $_POST['party_id'], (int) $_POST['account_id'], (float) $_POST['amount'], (string) $_POST['direction']);
            vx_flash(t('saved'));
            vx_go('parties');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) $_POST['name']);
        if ($name === '') {
            throw new RuntimeException(t('required'));
        }
        if ($id > 0) {
            vx_exec('UPDATE ' . vx_t('parties') . ' SET kind=?, name=?, phone=?, address=?, status=? WHERE id=?', 'ssssii', [(string) $_POST['kind'], $name, trim((string) $_POST['phone']), trim((string) $_POST['address']), (int) $_POST['status'], $id]);
        } else {
            $id = vx_insert('INSERT INTO ' . vx_t('parties') . ' (kind, name, phone, address, status) VALUES (?,?,?,?,?)', 'ssssi', [(string) $_POST['kind'], $name, trim((string) $_POST['phone']), trim((string) $_POST['address']), 1]);
        }
        vx_audit('party', 'parties', $id, $name);
        vx_flash(t('saved'));
        vx_go('parties');
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $kind = trim((string) ($_GET['kind'] ?? ''));
    $page = vx_page();
    $per = 20;
    $where = 'deleted_at IS NULL';
    $types = '';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (name LIKE ? OR phone LIKE ?)';
        $types .= 'ss';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($kind !== '') {
        $where .= ' AND kind=?';
        $types .= 's';
        $params[] = $kind;
    }
    $total = (int) (vx_one('SELECT COUNT(*) c FROM ' . vx_t('parties') . " WHERE $where", $types, $params)['c'] ?? 0);
    $rows = vx_all('SELECT * FROM ' . vx_t('parties') . " WHERE $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $types, $params);
    $html = '<form class="filters card" method="get"><input type="hidden" name="r" value="parties"><input name="q" value="' . vx_e($q) . '" placeholder="' . vx_e(t('search')) . '"><select name="kind"><option value="">' . vx_e(t('filter')) . '</option><option value="customer">customer</option><option value="supplier">supplier</option><option value="guest">guest</option></select><button>' . vx_e(t('search')) . '</button></form>';
    $html .= '<form method="post" class="card grid two">' . vx_csrf_field();
    $html .= '<div><label>' . vx_e(t('name')) . '</label><input name="name" required></div><div><label>' . vx_e(t('phone')) . '</label><input name="phone"></div>';
    $html .= '<div><label>' . vx_e(t('kind')) . '</label><select name="kind"><option value="customer">' . vx_e(t('customer')) . '</option><option value="supplier">' . vx_e(t('supplier')) . '</option><option value="guest">' . vx_e(t('guest')) . '</option><option value="both">both</option></select></div>';
    $html .= '<div><label>' . vx_e(t('address')) . '</label><input name="address"></div><button type="submit">' . vx_e(t('save')) . '</button></form>';
    $html .= '<div class="table-wrap"><table><tr><th>' . vx_e(t('name')) . '</th><th>' . vx_e(t('phone')) . '</th><th>' . vx_e(t('customer_due')) . '</th><th>' . vx_e(t('supplier_due')) . '</th><th></th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['name']) . '<br><small>' . vx_e($row['kind']) . '</small></td><td>' . vx_e($row['phone']) . '</td><td>' . vx_e(vx_money((float) $row['receivable'])) . '</td><td>' . vx_e(vx_money((float) $row['payable'])) . '</td>';
        $html .= '<td><a href="index.php?r=party&id=' . (int) $row['id'] . '">' . vx_e(t('view')) . '</a></td></tr>';
    }
    $html .= '</table></div>' . vx_pager($page, $total, $per, 'parties', array_filter(['q' => $q, 'kind' => $kind]));
    vx_layout('parties', $html);
}

function vx_page_party(): void
{
    vx_require('parties');
    $id = (int) ($_GET['id'] ?? 0);
    $row = vx_one('SELECT * FROM ' . vx_t('parties') . ' WHERE id=? AND deleted_at IS NULL', 'i', [$id]);
    if (!$row) {
        throw new RuntimeException(t('not_found'));
    }
    $sales = vx_all('SELECT id, memo_no, net, due, created_at FROM ' . vx_t('sales') . ' WHERE party_id=? ORDER BY id DESC LIMIT 30', 'i', [$id]);
    $html = '<div class="card"><h2>' . vx_e($row['name']) . '</h2><p>' . vx_e($row['phone']) . ' · ' . vx_e($row['address']) . '</p>';
    $html .= '<p>' . vx_e(t('customer_due')) . ': <b>' . vx_e(vx_money((float) $row['receivable'])) . '</b><br>' . vx_e(t('supplier_due')) . ': <b>' . vx_e(vx_money((float) $row['payable'])) . '</b></p></div>';
    $accounts = vx_opts('accounts', 'name', 'status=1');
    $html .= '<form method="post" action="index.php?r=parties" class="card grid two">' . vx_csrf_field() . '<input type="hidden" name="act" value="pay"><input type="hidden" name="party_id" value="' . $id . '">';
    $html .= '<div><label>' . vx_e(t('amount')) . '</label><input name="amount" required></div><div><label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">' . $accounts . '</select></div>';
    $html .= '<div><label>' . vx_e(t('kind')) . '</label><select name="direction"><option value="receive">' . vx_e(t('receive')) . '</option><option value="pay">' . vx_e(t('pay')) . '</option></select></div>';
    $html .= '<button type="submit">' . vx_e(t('save')) . '</button></form>';
    $html .= '<div class="table-wrap"><table><tr><th>' . vx_e(t('memo')) . '</th><th>' . vx_e(t('total')) . '</th><th>' . vx_e(t('due')) . '</th></tr>';
    foreach ($sales as $s) {
        $html .= '<tr><td><a href="index.php?r=memo&type=sale&id=' . (int) $s['id'] . '">' . vx_e($s['memo_no']) . '</a></td><td>' . vx_e(vx_money((float) $s['net'])) . '</td><td>' . vx_e(vx_money((float) $s['due'])) . '</td></tr>';
    }
    $html .= '</table></div>';
    if (vx_feature('rooms')) {
        $books = vx_all('SELECT code, status, check_in, check_out, total FROM ' . vx_t('bookings') . ' WHERE party_id=? ORDER BY id DESC LIMIT 20', 'i', [$id]);
        $html .= '<div class="card"><b>' . vx_e(t('guest_history')) . '</b><ul>';
        foreach ($books as $b) {
            $html .= '<li>' . vx_e($b['code'] . ' ' . $b['status'] . ' ' . $b['check_in']) . '</li>';
        }
        $html .= '</ul></div>';
    }
    vx_layout('parties', $html);
}

function vx_page_products(): void
{
    vx_require('products');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $name = trim((string) $_POST['name']);
        $sku = trim((string) $_POST['sku']);
        if ($name === '' || $sku === '') {
            throw new RuntimeException(t('required'));
        }
        $id = (int) ($_POST['id'] ?? 0);
        $track = !empty($_POST['track_batch']) ? 1 : 0;
        $vals = [$sku, trim((string) ($_POST['barcode'] ?? '')), $name, trim((string) ($_POST['generic_name'] ?? '')), (int) $_POST['category_id'], (int) $_POST['brand_id'], (int) $_POST['unit_id'], (string) ($_POST['kind'] ?? 'product'), (float) $_POST['purchase_price'], (float) $_POST['retail_price'], (float) $_POST['wholesale_price'], (float) $_POST['dealer_price'], (float) $_POST['min_stock'], $track];
        if ($id > 0) {
            vx_exec('UPDATE ' . vx_t('products') . ' SET sku=?, barcode=?, name=?, generic_name=?, category_id=?, brand_id=?, unit_id=?, kind=?, purchase_price=?, retail_price=?, wholesale_price=?, dealer_price=?, min_stock=?, track_batch=? WHERE id=?', 'ssssiiisdddddi' . 'i', array_merge($vals, [$id]));
        } else {
            $id = vx_insert('INSERT INTO ' . vx_t('products') . ' (sku, barcode, name, generic_name, category_id, brand_id, unit_id, kind, purchase_price, retail_price, wholesale_price, dealer_price, min_stock, track_batch) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', 'ssssiiisdddddi', $vals);
        }
        if (vx_feature('variants') && trim((string) ($_POST['variant_name'] ?? '')) !== '') {
            vx_insert('INSERT INTO ' . vx_t('variants') . ' (product_id, name, sku, barcode, retail_price) VALUES (?,?,?,?,?)', 'isssd', [$id, trim((string) $_POST['variant_name']), $sku . '-V' . $id . rand(10, 99), trim((string) ($_POST['variant_barcode'] ?? '')), (float) $_POST['retail_price']]);
        }
        vx_flash(t('saved'));
        vx_go('products');
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = vx_page();
    $where = 'p.deleted_at IS NULL';
    $types = '';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.generic_name LIKE ?)';
        $types = 'ssss';
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like];
    }
    $total = (int) (vx_one('SELECT COUNT(*) c FROM ' . vx_t('products') . " p WHERE $where", $types, $params)['c'] ?? 0);
    $rows = vx_all('SELECT p.* FROM ' . vx_t('products') . " p WHERE $where ORDER BY p.id DESC LIMIT 20 OFFSET " . (($page - 1) * 20), $types, $params);
    $cats = '<option value="0">—</option>' . vx_opts('categories');
    $brands = '<option value="0">—</option>' . vx_opts('brands');
    $html = '<form class="card" method="get"><input type="hidden" name="r" value="products"><input name="q" value="' . vx_e($q) . '"><button>' . vx_e(t('search')) . '</button></form>';
    $html .= '<form method="post" class="card grid two">' . vx_csrf_field();
    $html .= '<div><label>SKU</label><input name="sku" required></div><div><label>' . vx_e(t('barcode')) . '</label><input name="barcode"></div>';
    $html .= '<div><label>' . vx_e(t('name')) . '</label><input name="name" required></div><div><label>' . vx_e(t('generic')) . '</label><input name="generic_name"></div>';
    $html .= '<div><label>' . vx_e(t('category')) . '</label><select name="category_id">' . $cats . '</select></div><div><label>' . vx_e(t('brand')) . '</label><select name="brand_id">' . $brands . '</select></div>';
    $html .= '<div><label>' . vx_e(t('purchase')) . '</label><input name="purchase_price" value="0"></div><div><label>' . vx_e(t('retail')) . '</label><input name="retail_price" value="0"></div>';
    $html .= '<div><label>' . vx_e(t('wholesale')) . '</label><input name="wholesale_price" value="0"></div><div><label>' . vx_e(t('dealer')) . '</label><input name="dealer_price" value="0"></div>';
    $html .= '<div><label>' . vx_e(t('min_stock')) . '</label><input name="min_stock" value="0"></div><div><label>' . vx_e(t('kind')) . '</label><select name="kind"><option value="product">product</option><option value="menu">menu</option><option value="medicine">medicine</option><option value="service">service</option></select></div>';
    $html .= '<input type="hidden" name="unit_id" value="0">';
    if (vx_feature('batch')) {
        $html .= '<label><input type="checkbox" name="track_batch" value="1" checked style="width:auto"> ' . vx_e(t('batch')) . '</label>';
    }
    if (vx_feature('variants')) {
        $html .= '<div><label>' . vx_e(t('variant')) . '</label><input name="variant_name"></div>';
    }
    $html .= '<button type="submit">' . vx_e(t('save')) . '</button></form>';
    $html .= '<p class="row-actions"><a class="btn alt" href="index.php?r=categories">' . vx_e(t('category')) . '</a> <a class="btn alt" href="index.php?r=brands">' . vx_e(t('brand')) . '</a></p>';
    $html .= '<div class="table-wrap"><table><tr><th>SKU</th><th>' . vx_e(t('name')) . '</th><th>' . vx_e(t('stock')) . '</th><th>' . vx_e(t('retail')) . '</th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['sku']) . '</td><td>' . vx_e($row['name']) . '<br><small>' . vx_e($row['generic_name']) . '</small></td><td>' . vx_e((string) $row['stock']) . '</td><td>' . vx_e(vx_money((float) $row['retail_price'])) . '</td></tr>';
    }
    $html .= '</table></div>' . vx_pager($page, $total, 20, 'products', ['q' => $q]);
    vx_layout('products', $html);
}

function vx_page_simple_name(string $table, string $perm, string $title): void
{
    vx_require($perm);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $name = trim((string) $_POST['name']);
        if ($name === '') {
            throw new RuntimeException(t('required'));
        }
        vx_insert('INSERT INTO ' . vx_t($table) . ' (name) VALUES (?)', 's', [$name]);
        vx_flash(t('saved'));
        vx_go($title);
    }
    $rows = vx_all('SELECT * FROM ' . vx_t($table) . ' WHERE deleted_at IS NULL ORDER BY name');
    $html = '<form method="post" class="card">' . vx_csrf_field() . '<label>' . vx_e(t('name')) . '</label><input name="name" required><button type="submit">' . vx_e(t('save')) . '</button></form><ul>';
    foreach ($rows as $row) {
        $html .= '<li>' . vx_e($row['name']) . '</li>';
    }
    vx_layout($title, $html . '</ul>');
}

function vx_page_purchase(): void
{
    vx_require('purchase');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $id = vx_post_purchase([
            'party_id' => (int) $_POST['party_id'],
            'account_id' => (int) $_POST['account_id'],
            'paid' => (float) $_POST['paid'],
            'note' => (string) ($_POST['note'] ?? ''),
            'lines' => vx_lines_from_post(),
        ]);
        vx_flash(t('saved'));
        vx_go('memo', ['type' => 'purchase', 'id' => $id]);
    }
    $html = '<form method="post">' . vx_csrf_field();
    $html .= '<div class="card grid two"><div><label>' . vx_e(t('supplier')) . '</label><select name="party_id"><option value="0">—</option>' . vx_opts('parties', 'name', "deleted_at IS NULL AND kind IN ('supplier','both')") . '</select></div>';
    $html .= '<div><label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select></div>';
    $html .= '<div><label>' . vx_e(t('paid')) . '</label><input name="paid" value="0"></div><div><label>' . vx_e(t('note')) . '</label><input name="note"></div></div>';
    $html .= vx_line_form(4, vx_feature('batch'));
    $html .= '<button type="submit">' . vx_e(t('save')) . '</button></form>';
    $rows = vx_all('SELECT id, memo_no, total, due, created_at FROM ' . vx_t('purchases') . ' ORDER BY id DESC LIMIT 15');
    $html .= '<div class="table-wrap"><table><tr><th>' . vx_e(t('memo')) . '</th><th>' . vx_e(t('total')) . '</th><th>' . vx_e(t('due')) . '</th><th></th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['memo_no']) . '</td><td>' . vx_e(vx_money((float) $row['total'])) . '</td><td>' . vx_e(vx_money((float) $row['due'])) . '</td><td class="row-actions"><a href="index.php?r=memo&type=purchase&id=' . (int) $row['id'] . '">' . vx_e(t('view')) . '</a><a href="index.php?r=purchase_return&id=' . (int) $row['id'] . '">' . vx_e(t('return')) . '</a></td></tr>';
    }
    vx_layout('purchase', $html . '</table></div>');
}

function vx_page_sale(): void
{
    vx_require('sale');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $id = vx_post_sale([
            'party_id' => (int) ($_POST['party_id'] ?? 0),
            'account_id' => (int) $_POST['account_id'],
            'paid' => (float) $_POST['paid'],
            'discount' => (float) ($_POST['discount'] ?? 0),
            'note' => (string) ($_POST['note'] ?? ''),
            'channel' => (string) ($_POST['channel'] ?? 'pos'),
            'booking_id' => (int) ($_POST['booking_id'] ?? 0),
            'doctor_id' => (int) ($_POST['doctor_id'] ?? 0),
            'patient_name' => (string) ($_POST['patient_name'] ?? ''),
            'rx_note' => (string) ($_POST['rx_note'] ?? ''),
            'employee_id' => (int) ($_POST['employee_id'] ?? 0),
            'commission_rate' => (float) ($_POST['commission_rate'] ?? 0),
            'lines' => vx_lines_from_post(),
        ]);
        vx_flash(t('saved'));
        vx_go('memo', ['type' => 'sale', 'id' => $id]);
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $html = '<form method="post">' . vx_csrf_field();
    $html .= '<div class="card grid two"><div><label>' . vx_e(t('customer')) . '</label><select name="party_id"><option value="0">' . vx_e(t('walk_in')) . '</option>' . vx_opts('parties', 'name', "deleted_at IS NULL AND kind IN ('customer','guest','both')") . '</select></div>';
    $html .= '<div><label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select></div>';
    $html .= '<div><label>' . vx_e(t('discount')) . '</label><input name="discount" value="0"></div><div><label>' . vx_e(t('paid')) . '</label><input name="paid" value="0"></div>';
    if (vx_feature('prescription')) {
        $html .= '<div><label>' . vx_e(t('doctor')) . '</label><select name="doctor_id"><option value="0">—</option>' . vx_opts('doctors', 'name', 'deleted_at IS NULL') . '</select></div>';
        $html .= '<div><label>' . vx_e(t('patient')) . '</label><input name="patient_name"></div><div><label>' . vx_e(t('prescription')) . '</label><input name="rx_note"></div>';
    }
    if (vx_feature('commission')) {
        $html .= '<div><label>' . vx_e(t('employee')) . '</label><select name="employee_id"><option value="0">—</option>' . vx_opts('employees', 'name', 'deleted_at IS NULL') . '</select></div>';
        $html .= '<div><label>' . vx_e(t('commission')) . ' %</label><input name="commission_rate" value="0"></div>';
    }
    $html .= '</div>';
    $html .= vx_line_form(5, false);
    $html .= '<button type="submit">' . vx_e(t('save')) . '</button></form>';
    $where = '1=1';
    $types = '';
    $params = [];
    if ($q !== '') {
        $where = 'memo_no LIKE ?';
        $types = 's';
        $params = ['%' . $q . '%'];
    }
    $rows = vx_all('SELECT id, memo_no, net, due, channel, created_at FROM ' . vx_t('sales') . " WHERE $where ORDER BY id DESC LIMIT 20", $types, $params);
    $html .= '<form class="card" method="get"><input type="hidden" name="r" value="sale"><input name="q" value="' . vx_e($q) . '" placeholder="' . vx_e(t('search')) . '"><button>' . vx_e(t('search')) . '</button></form>';
    $html .= '<div class="table-wrap"><table><tr><th>' . vx_e(t('memo')) . '</th><th>' . vx_e(t('total')) . '</th><th>' . vx_e(t('due')) . '</th><th></th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['memo_no']) . '</td><td>' . vx_e(vx_money((float) $row['net'])) . '</td><td>' . vx_e(vx_money((float) $row['due'])) . '</td><td class="row-actions"><a href="index.php?r=memo&type=sale&id=' . (int) $row['id'] . '&do=print">' . vx_e(t('print')) . '</a><a href="index.php?r=memo&type=sale&id=' . (int) $row['id'] . '&do=share">' . vx_e(t('share')) . '</a><a href="index.php?r=sale_return&id=' . (int) $row['id'] . '">' . vx_e(t('return')) . '</a></td></tr>';
    }
    vx_layout('sale', $html . '</table></div>');
}

function vx_page_return(string $kind): void
{
    vx_require($kind === 'sale' ? 'sale_return' : 'purchase_return');
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $qtys = [];
        foreach (($_POST['qty'] ?? []) as $itemId => $qty) {
            $qtys[(int) $itemId] = (float) $qty;
        }
        $rid = $kind === 'sale' ? vx_post_sale_return($id, $qtys) : vx_post_purchase_return($id, $qtys);
        vx_flash(t('saved'));
        vx_go('memo', ['type' => $kind . '_return', 'id' => $rid]);
    }
    if ($kind === 'sale') {
        $head = vx_one('SELECT * FROM ' . vx_t('sales') . ' WHERE id=?', 'i', [$id]);
        $items = vx_all('SELECT i.*, p.name FROM ' . vx_t('sale_items') . ' i JOIN ' . vx_t('products') . ' p ON p.id=i.product_id WHERE i.sale_id=?', 'i', [$id]);
    } else {
        $head = vx_one('SELECT * FROM ' . vx_t('purchases') . ' WHERE id=?', 'i', [$id]);
        $items = vx_all('SELECT i.*, p.name FROM ' . vx_t('purchase_items') . ' i JOIN ' . vx_t('products') . ' p ON p.id=i.product_id WHERE i.purchase_id=?', 'i', [$id]);
    }
    if (!$head) {
        throw new RuntimeException(t('not_found'));
    }
    $html = '<form method="post" class="card">' . vx_csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><p>' . vx_e($head['memo_no']) . '</p>';
    foreach ($items as $item) {
        $left = (float) $item['qty'] - (float) $item['returned_qty'];
        $html .= '<label>' . vx_e($item['name']) . ' (' . vx_e((string) $left) . ')</label><input name="qty[' . (int) $item['id'] . ']" value="0">';
    }
    $html .= '<button type="submit">' . vx_e(t('return')) . '</button></form>';
    vx_layout('return', $html);
}

function vx_page_memo(): void
{
    vx_require('memo');
    $type = (string) ($_GET['type'] ?? 'sale');
    $id = (int) ($_GET['id'] ?? 0);
    $settings = vx_one('SELECT * FROM ' . vx_t('settings') . ' WHERE id=1') ?: [];
    $lines = '';
    $title = t('memo');
    $phone = '';
    $meta = '';
    if ($type === 'sale') {
        $head = vx_one('SELECT s.*, p.name party, p.phone FROM ' . vx_t('sales') . ' s LEFT JOIN ' . vx_t('parties') . ' p ON p.id=s.party_id WHERE s.id=?', 'i', [$id]);
        $items = $head ? vx_all('SELECT i.*, p.name FROM ' . vx_t('sale_items') . ' i JOIN ' . vx_t('products') . ' p ON p.id=i.product_id WHERE sale_id=?', 'i', [$id]) : [];
        $totalLabel = (float) ($head['net'] ?? 0);
        $paid = (float) ($head['paid'] ?? 0);
        $due = (float) ($head['due'] ?? 0);
    } elseif ($type === 'purchase') {
        $head = vx_one('SELECT s.*, p.name party, p.phone FROM ' . vx_t('purchases') . ' s LEFT JOIN ' . vx_t('parties') . ' p ON p.id=s.party_id WHERE s.id=?', 'i', [$id]);
        $items = $head ? vx_all('SELECT i.*, p.name FROM ' . vx_t('purchase_items') . ' i JOIN ' . vx_t('products') . ' p ON p.id=i.product_id WHERE purchase_id=?', 'i', [$id]) : [];
        $totalLabel = (float) ($head['total'] ?? 0);
        $paid = (float) ($head['paid'] ?? 0);
        $due = (float) ($head['due'] ?? 0);
    } elseif ($type === 'sale_return') {
        $head = vx_one('SELECT * FROM ' . vx_t('sale_returns') . ' WHERE id=?', 'i', [$id]);
        $items = $head ? vx_all('SELECT ri.qty, ri.line_total, p.name FROM ' . vx_t('sale_return_items') . ' ri JOIN ' . vx_t('sale_items') . ' i ON i.id=ri.sale_item_id JOIN ' . vx_t('products') . ' p ON p.id=i.product_id WHERE ri.return_id=?', 'i', [$id]) : [];
        $totalLabel = (float) ($head['total'] ?? 0);
        $paid = 0;
        $due = 0;
    } else {
        $head = vx_one('SELECT * FROM ' . vx_t('purchase_returns') . ' WHERE id=?', 'i', [$id]);
        $items = $head ? vx_all('SELECT ri.qty, ri.line_total, p.name FROM ' . vx_t('purchase_return_items') . ' ri JOIN ' . vx_t('purchase_items') . ' i ON i.id=ri.purchase_item_id JOIN ' . vx_t('products') . ' p ON p.id=i.product_id WHERE ri.return_id=?', 'i', [$id]) : [];
        $totalLabel = (float) ($head['total'] ?? 0);
        $paid = 0;
        $due = 0;
    }
    if (!$head) {
        throw new RuntimeException(t('not_found'));
    }
    $phone = (string) ($head['phone'] ?? '');
    $meta = (string) ($head['memo_no'] ?? '');
    foreach ($items as $item) {
        $price = isset($item['price']) ? vx_money((float) $item['price']) : '';
        $lines .= '<tr><td>' . vx_e($item['name']) . '</td><td>' . vx_e((string) $item['qty']) . '</td><td>' . vx_e($price) . '</td><td>' . vx_e(vx_money((float) $item['line_total'])) . '</td></tr>';
    }
    $shareText = rawurlencode(($settings['business_name'] ?? '') . "\n" . $meta . "\n" . t('total') . ' ' . vx_money($totalLabel));
    $waPhone = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($waPhone, '0')) {
        $waPhone = '88' . substr($waPhone, 1);
    }
    $wa = 'https://wa.me/' . $waPhone . '?text=' . $shareText;
    $html = '<article class="sheet"><header class="memo-head"><div><b>' . vx_e((string) ($settings['business_name'] ?? '')) . '</b><br>' . vx_e((string) ($settings['address'] ?? '')) . '<br>' . vx_e((string) ($settings['phone'] ?? '')) . '</div><div>' . vx_e($meta) . '<br>' . vx_e((string) ($head['created_at'] ?? '')) . '</div></header>';
    $html .= '<p>' . vx_e((string) ($head['party'] ?? t('walk_in'))) . '</p><div class="table-wrap"><table><tr><th>' . vx_e(t('name')) . '</th><th>' . vx_e(t('qty')) . '</th><th>' . vx_e(t('price')) . '</th><th>' . vx_e(t('total')) . '</th></tr>' . $lines . '</table></div>';
    $html .= '<p>' . vx_e(t('total')) . ': <b>' . vx_e(vx_money($totalLabel)) . '</b><br>' . vx_e(t('paid')) . ': ' . vx_e(vx_money($paid)) . '<br>' . vx_e(t('due')) . ': ' . vx_e(vx_money($due)) . '</p>';
    $html .= '<p class="noprint row-actions"><button type="button" onclick="window.print()">' . vx_e(t('print')) . '</button><a class="btn" href="' . vx_e($wa) . '">' . vx_e(t('share')) . '</a><a class="btn alt" href="index.php?r=memo&type=' . vx_e($type) . '&id=' . $id . '">' . vx_e(t('reprint')) . '</a></p></article>';
    if (($_GET['do'] ?? '') === 'share') {
        header('Location: ' . $wa);
        exit;
    }
    vx_layout('memo', $html, true);
    if (($_GET['do'] ?? '') === 'print') {
        echo '<script>window.print()</script>';
    }
}

function vx_page_stock(): void
{
    vx_require('stock');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $delta = (float) $_POST['qty_delta'];
        vx_tx(function () use ($delta) {
            vx_move_stock((int) $_POST['product_id'], $delta, 'adjust', 'adjust', 0, null, (int) ($_POST['location_id'] ?? 1));
        });
        vx_audit('adjust', 'products', (int) $_POST['product_id'], (string) $delta);
        vx_flash(t('saved'));
        vx_go('stock');
    }
    $rows = vx_all('SELECT id, sku, name, stock, min_stock FROM ' . vx_t('products') . ' WHERE deleted_at IS NULL AND kind<>"service" ORDER BY name LIMIT 200');
    $html = '<form method="post" class="card grid two">' . vx_csrf_field();
    $html .= '<div><label>' . vx_e(t('product')) . '</label><select name="product_id">' . vx_opts('products', 'name', 'deleted_at IS NULL AND kind<>"service"') . '</select></div>';
    $html .= '<div><label>' . vx_e(t('qty')) . ' +/-</label><input name="qty_delta" required></div><button type="submit">' . vx_e(t('adjustment')) . '</button></form>';
    $html .= '<div class="table-wrap"><table><tr><th>SKU</th><th>' . vx_e(t('name')) . '</th><th>' . vx_e(t('stock')) . '</th><th>' . vx_e(t('min_stock')) . '</th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['sku']) . '</td><td>' . vx_e($row['name']) . '</td><td>' . vx_e((string) $row['stock']) . '</td><td>' . vx_e((string) $row['min_stock']) . '</td></tr>';
    }
    vx_layout('stock', $html . '</table></div>');
}

function vx_page_transfers(): void
{
    vx_require('transfers');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $qty = round((float) $_POST['qty'], 3);
        $from = (int) $_POST['from_location_id'];
        $to = (int) $_POST['to_location_id'];
        $pid = (int) $_POST['product_id'];
        if ($from === $to || $qty <= 0) {
            throw new RuntimeException(t('bad_qty'));
        }
        vx_tx(function () use ($pid, $from, $to, $qty) {
            vx_loc_add($pid, $from, -$qty);
            vx_loc_add($pid, $to, $qty);
            vx_insert('INSERT INTO ' . vx_t('transfers') . ' (product_id, from_location_id, to_location_id, qty, note, created_by) VALUES (?,?,?,?,?,?)', 'iiidsi', [$pid, $from, $to, $qty, trim((string) ($_POST['note'] ?? '')), (int) ($_SESSION['vx_uid'] ?? 0)]);
        });
        vx_flash(t('saved'));
        vx_go('transfers');
    }
    $locs = vx_opts('locations', 'name', 'status=1');
    $html = '<form method="post" class="card">' . vx_csrf_field();
    $html .= '<label>' . vx_e(t('product')) . '</label><select name="product_id">' . vx_opts('products', 'name', 'deleted_at IS NULL') . '</select>';
    $html .= '<label>' . vx_e(t('from')) . '</label><select name="from_location_id">' . $locs . '</select>';
    $html .= '<label>' . vx_e(t('to')) . '</label><select name="to_location_id">' . $locs . '</select>';
    $html .= '<label>' . vx_e(t('qty')) . '</label><input name="qty" required><button type="submit">' . vx_e(t('save')) . '</button></form>';
    vx_layout('transfers', $html);
}

function vx_save_money(string $table): void
{
    $amount = round((float) $_POST['amount'], 2);
    $account = (int) $_POST['account_id'];
    $title = trim((string) $_POST['title']);
    $date = (string) ($_POST['on_date'] ?? date('Y-m-d'));
    $note = trim((string) ($_POST['note'] ?? ''));
    if ($title === '' || $amount <= 0) {
        throw new RuntimeException(t('required'));
    }
    vx_tx(function () use ($table, $title, $amount, $account, $date, $note) {
        $col = $table === 'expenses' ? 'spent_on' : 'received_on';
        $id = vx_insert(
            'INSERT INTO ' . vx_t($table) . " (title, amount, account_id, {$col}, note, created_by) VALUES (?,?,?,?,?,?)",
            'sdissi',
            [$title, $amount, $account, $date, $note, (int) ($_SESSION['vx_uid'] ?? 0)]
        );
        vx_cash($account, $table === 'expenses' ? 'out' : 'in', $amount, $table, $id, $title);
    });
}

function vx_page_expenses(): void
{
    vx_require('expenses');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        vx_save_money('expenses');
        vx_flash(t('saved'));
        vx_go('expenses');
    }
    $rows = vx_all('SELECT * FROM ' . vx_t('expenses') . ' ORDER BY id DESC LIMIT 30');
    $html = '<form method="post" class="card grid two">' . vx_csrf_field();
    $html .= '<div><label>' . vx_e(t('name')) . '</label><input name="title" required></div><div><label>' . vx_e(t('amount')) . '</label><input name="amount" required></div>';
    $html .= '<div><label>' . vx_e(t('date')) . '</label><input type="date" name="on_date" value="' . date('Y-m-d') . '"></div><div><label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select></div>';
    $html .= '<button type="submit">' . vx_e(t('save')) . '</button></form><ul>';
    foreach ($rows as $row) {
        $html .= '<li>' . vx_e($row['spent_on'] . ' ' . $row['title'] . ' ' . vx_money((float) $row['amount'])) . '</li>';
    }
    vx_layout('expenses', $html . '</ul>');
}

function vx_page_incomes(): void
{
    vx_require('incomes');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        vx_save_money('incomes');
        vx_flash(t('saved'));
        vx_go('incomes');
    }
    $rows = vx_all('SELECT * FROM ' . vx_t('incomes') . ' ORDER BY id DESC LIMIT 30');
    $html = '<form method="post" class="card">' . vx_csrf_field() . '<label>' . vx_e(t('name')) . '</label><input name="title" required><label>' . vx_e(t('amount')) . '</label><input name="amount" required><label>' . vx_e(t('date')) . '</label><input type="date" name="on_date" value="' . date('Y-m-d') . '"><label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select><button type="submit">' . vx_e(t('save')) . '</button></form>';
    foreach ($rows as $row) {
        $html .= '<p>' . vx_e($row['received_on'] . ' ' . $row['title'] . ' ' . vx_money((float) $row['amount'])) . '</p>';
    }
    vx_layout('incomes', $html);
}

function vx_page_accounts(): void
{
    vx_require('accounts');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        vx_insert('INSERT INTO ' . vx_t('accounts') . ' (name, kind) VALUES (?,?)', 'ss', [trim((string) $_POST['name']), (string) $_POST['kind']]);
        vx_flash(t('saved'));
        vx_go('accounts');
    }
    $rows = vx_all('SELECT * FROM ' . vx_t('accounts') . ' WHERE status=1 ORDER BY id');
    $html = '<form method="post" class="card grid two">' . vx_csrf_field() . '<div><label>' . vx_e(t('name')) . '</label><input name="name" required></div><div><label>' . vx_e(t('kind')) . '</label><select name="kind"><option value="cash">' . vx_e(t('cash')) . '</option><option value="bank">' . vx_e(t('bank')) . '</option></select></div><button type="submit">' . vx_e(t('save')) . '</button></form>';
    $html .= '<div class="table-wrap"><table><tr><th>' . vx_e(t('name')) . '</th><th>' . vx_e(t('kind')) . '</th><th>' . vx_e(t('balance')) . '</th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['name']) . '</td><td>' . vx_e($row['kind']) . '</td><td>' . vx_e(vx_money((float) $row['balance'])) . '</td></tr>';
    }
    $led = vx_all('SELECT * FROM ' . vx_t('ledger') . ' ORDER BY id DESC LIMIT 20');
    $html .= '</table></div><div class="card"><b>' . vx_e(t('ledger')) . '</b><ul>';
    foreach ($led as $row) {
        $html .= '<li>' . vx_e($row['created_at'] . ' ' . $row['direction'] . ' ' . vx_money((float) $row['amount']) . ' ' . $row['note']) . '</li>';
    }
    vx_layout('accounts', $html . '</ul></div>');
}

function vx_page_employees(): void
{
    vx_require('employees');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        if (($_POST['act'] ?? '') === 'salary') {
            $amount = round((float) $_POST['amount'], 2);
            $month = (string) $_POST['month_key'];
            $emp = (int) $_POST['employee_id'];
            $account = (int) $_POST['account_id'];
            vx_tx(function () use ($amount, $month, $emp, $account) {
                vx_insert('INSERT INTO ' . vx_t('salary_payments') . ' (employee_id, month_key, amount, account_id, created_by) VALUES (?,?,?,?,?)', 'isdii', [$emp, $month, $amount, $account, (int) ($_SESSION['vx_uid'] ?? 0)]);
                vx_cash($account, 'out', $amount, 'salary', $emp, $month);
            });
            vx_flash(t('saved'));
            vx_go('employees');
        }
        vx_insert('INSERT INTO ' . vx_t('employees') . ' (name, phone, job_title, salary) VALUES (?,?,?,?)', 'sssd', [trim((string) $_POST['name']), trim((string) $_POST['phone']), trim((string) $_POST['job_title']), (float) $_POST['salary']]);
        vx_flash(t('saved'));
        vx_go('employees');
    }
    $rows = vx_all('SELECT * FROM ' . vx_t('employees') . ' WHERE deleted_at IS NULL ORDER BY name');
    $html = '<form method="post" class="card grid two">' . vx_csrf_field() . '<div><label>' . vx_e(t('name')) . '</label><input name="name" required></div><div><label>' . vx_e(t('phone')) . '</label><input name="phone"></div><div><label>' . vx_e(t('job')) . '</label><input name="job_title"></div><div><label>' . vx_e(t('salary')) . '</label><input name="salary" value="0"></div><button type="submit">' . vx_e(t('save')) . '</button></form>';
    $html .= '<form method="post" class="card">' . vx_csrf_field() . '<input type="hidden" name="act" value="salary"><label>' . vx_e(t('employee')) . '</label><select name="employee_id">' . vx_opts('employees', 'name', 'deleted_at IS NULL') . '</select><label>' . vx_e(t('month')) . '</label><input name="month_key" placeholder="2026-09" required><label>' . vx_e(t('amount')) . '</label><input name="amount" required><label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="pay" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select><button type="submit">' . vx_e(t('salary')) . '</button></form><ul>';
    foreach ($rows as $row) {
        $html .= '<li>' . vx_e($row['name'] . ' · ' . $row['job_title'] . ' · ' . vx_money((float) $row['salary'])) . '</li>';
    }
    vx_layout('employees', $html . '</ul>');
}

function vx_page_reports(): void
{
    vx_require('reports');
    $from = (string) ($_GET['from'] ?? date('Y-m-01'));
    $to = (string) ($_GET['to'] ?? date('Y-m-d'));
    $sales = vx_one('SELECT COALESCE(SUM(net),0) net, COALESCE(SUM(due),0) due, COUNT(*) c FROM ' . vx_t('sales') . ' WHERE DATE(created_at) BETWEEN ? AND ?', 'ss', [$from, $to]);
    $cogs = vx_one('SELECT COALESCE(SUM(i.base_qty * i.cost_price),0) c FROM ' . vx_t('sale_items') . ' i JOIN ' . vx_t('sales') . ' s ON s.id=i.sale_id WHERE DATE(s.created_at) BETWEEN ? AND ?', 'ss', [$from, $to]);
    $exp = vx_one('SELECT COALESCE(SUM(amount),0) s FROM ' . vx_t('expenses') . ' WHERE spent_on BETWEEN ? AND ?', 'ss', [$from, $to]);
    $profit = (float) $sales['net'] - (float) $cogs['c'] - (float) $exp['s'];
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report.csv"');
        echo "\xEF\xBB\xBF";
        echo "from,to,sales,cogs,expense,profit\n";
        echo $from . ',' . $to . ',' . $sales['net'] . ',' . $cogs['c'] . ',' . $exp['s'] . ',' . $profit . "\n";
        exit;
    }
    $html = '<form class="filters card" method="get"><input type="hidden" name="r" value="reports"><input type="date" name="from" value="' . vx_e($from) . '"><input type="date" name="to" value="' . vx_e($to) . '"><button>' . vx_e(t('filter')) . '</button></form>';
    $html .= '<div class="kpis"><div class="kpi"><b>' . vx_e(vx_money((float) $sales['net'])) . '</b><span>' . vx_e(t('sales')) . '</span></div>';
    $html .= '<div class="kpi"><b>' . vx_e(vx_money((float) $cogs['c'])) . '</b><span>COGS</span></div>';
    $html .= '<div class="kpi"><b>' . vx_e(vx_money((float) $exp['s'])) . '</b><span>' . vx_e(t('expenses')) . '</span></div>';
    $html .= '<div class="kpi"><b>' . vx_e(vx_money($profit)) . '</b><span>' . vx_e(t('profit')) . '</span></div></div>';
    $html .= '<p class="noprint row-actions"><button type="button" onclick="window.print()">' . vx_e(t('print')) . '</button><a class="btn alt" href="index.php?r=reports&from=' . vx_e($from) . '&to=' . vx_e($to) . '&export=csv">' . vx_e(t('download')) . '</a></p>';
    vx_layout('reports', $html);
}

function vx_page_alerts(): void
{
    vx_require('alerts');
    $low = vx_all('SELECT sku, name, stock, min_stock FROM ' . vx_t('products') . ' WHERE deleted_at IS NULL AND kind<>"service" AND stock<=min_stock ORDER BY stock LIMIT 50');
    $html = '<div class="card"><b>' . vx_e(t('low_stock')) . '</b><ul>';
    foreach ($low as $row) {
        $html .= '<li>' . vx_e($row['name'] . ' ' . $row['stock']) . '</li>';
    }
    $html .= '</ul></div>';
    if (vx_feature('batch')) {
        $near = vx_all('SELECT b.batch_no, b.expiry_date, b.qty, p.name FROM ' . vx_t('batches') . ' b JOIN ' . vx_t('products') . ' p ON p.id=b.product_id WHERE b.qty>0 AND b.expiry_date IS NOT NULL AND b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY b.expiry_date LIMIT 50');
        $html .= '<div class="card"><b>' . vx_e(t('near_expiry')) . '</b><ul>';
        foreach ($near as $row) {
            $html .= '<li>' . vx_e($row['name'] . ' ' . $row['batch_no'] . ' ' . $row['expiry_date']) . '</li>';
        }
        $html .= '</ul></div>';
    }
    vx_layout('alerts', $html);
}

function vx_page_files(): void
{
    vx_require('files');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['del'] ?? '') === '1') {
        vx_csrf_check();
        vx_exec('UPDATE ' . vx_t('files') . ' SET deleted_at=NOW() WHERE id=?', 'i', [(int) $_POST['id']]);
        vx_flash(t('saved'));
        vx_go('files');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        if (empty($_FILES['file']['tmp_name'])) {
            throw new RuntimeException(t('required'));
        }
        $tmp = (string) $_FILES['file']['tmp_name'];
        $size = (int) $_FILES['file']['size'];
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException(t('bad_file'));
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (!isset($map[$mime])) {
            throw new RuntimeException(t('bad_file'));
        }
        $folder = preg_replace('/[^a-z]/', '', (string) ($_POST['folder'] ?? 'docs')) ?: 'docs';
        $dir = VX_ROOT . '/uploads/' . $folder;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $stored = bin2hex(random_bytes(12)) . '.' . $map[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $stored)) {
            throw new RuntimeException(t('bad_file'));
        }
        vx_insert(
            'INSERT INTO ' . vx_t('files') . ' (folder, stored_name, original_name, mime, size_bytes, created_by) VALUES (?,?,?,?,?,?)',
            'ssssii',
            [$folder, $stored, mb_substr((string) $_FILES['file']['name'], 0, 180), $mime, $size, (int) ($_SESSION['vx_uid'] ?? 0)]
        );
        vx_flash(t('saved'));
        vx_go('files');
    }
    $rows = vx_all('SELECT * FROM ' . vx_t('files') . ' WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 40');
    $html = '<form method="post" enctype="multipart/form-data" class="card">' . vx_csrf_field();
    $html .= '<label>' . vx_e(t('folder')) . '</label><select name="folder"><option value="products">products</option><option value="guests">guests</option><option value="invoices">invoices</option><option value="suppliers">suppliers</option><option value="users">users</option><option value="logo">logo</option><option value="docs">docs</option></select>';
    $html .= '<label>' . vx_e(t('upload')) . '</label><input type="file" name="file" required accept="image/jpeg,image/png,image/webp,application/pdf"><button type="submit">' . vx_e(t('upload')) . '</button></form><ul>';
    foreach ($rows as $row) {
        $html .= '<li><a href="index.php?r=file&id=' . (int) $row['id'] . '">' . vx_e($row['original_name']) . '</a> <small>' . vx_e($row['folder']) . '</small> <form method="post" style="display:inline">' . vx_csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><input type="hidden" name="del" value="1"><button type="submit">' . vx_e(t('delete')) . '</button></form></li>';
    }
    vx_layout('files', $html . '</ul>');
}

function vx_page_file(): void
{
    vx_require('files');
    $row = vx_one('SELECT * FROM ' . vx_t('files') . ' WHERE id=? AND deleted_at IS NULL', 'i', [(int) ($_GET['id'] ?? 0)]);
    if (!$row) {
        throw new RuntimeException(t('not_found'));
    }
    $path = VX_ROOT . '/uploads/' . $row['folder'] . '/' . $row['stored_name'];
    $real = realpath($path);
    $root = realpath(VX_ROOT . '/uploads');
    if ($real === false || $root === false || !str_starts_with($real, $root)) {
        throw new RuntimeException(t('not_found'));
    }
    header('Content-Type: ' . $row['mime']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . rawurlencode((string) $row['original_name']) . '"');
    readfile($real);
    exit;
}

function vx_page_users(): void
{
    vx_require('users');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $pass = (string) $_POST['password'];
        if (strlen($pass) < 8) {
            throw new RuntimeException(t('weak_password'));
        }
        vx_insert(
            'INSERT INTO ' . vx_t('users') . ' (username, password_hash, name, role_slug, phone) VALUES (?,?,?,?,?)',
            'sssss',
            [trim((string) $_POST['username']), password_hash($pass, PASSWORD_DEFAULT), trim((string) $_POST['name']), (string) $_POST['role_slug'], trim((string) ($_POST['phone'] ?? ''))]
        );
        vx_flash(t('saved'));
        vx_go('users');
    }
    $rows = vx_all('SELECT id, username, name, role_slug, status FROM ' . vx_t('users') . ' ORDER BY id');
    $html = '<form method="post" class="card grid two">' . vx_csrf_field();
    $html .= '<div><label>' . vx_e(t('username')) . '</label><input name="username" required></div><div><label>' . vx_e(t('name')) . '</label><input name="name" required></div>';
    $html .= '<div><label>' . vx_e(t('password')) . '</label><input type="password" name="password" required minlength="8"></div>';
    $html .= '<div><label>' . vx_e(t('role')) . '</label><select name="role_slug"><option value="cashier">cashier</option><option value="manager">manager</option><option value="storekeeper">storekeeper</option><option value="admin">admin</option></select></div>';
    $html .= '<button type="submit">' . vx_e(t('save')) . '</button></form><ul>';
    foreach ($rows as $row) {
        $html .= '<li>' . vx_e($row['username'] . ' · ' . $row['role_slug']) . '</li>';
    }
    vx_layout('users', $html . '</ul>');
}

function vx_page_settings(): void
{
    vx_require('settings');
    $row = vx_one('SELECT * FROM ' . vx_t('settings') . ' WHERE id=1');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        if (($_POST['act'] ?? '') === 'password') {
            $me = vx_user();
            if (!$me || !password_verify((string) $_POST['old_password'], (string) $me['password_hash'])) {
                throw new RuntimeException(t('login_failed'));
            }
            $pass = (string) $_POST['new_password'];
            if (strlen($pass) < 8) {
                throw new RuntimeException(t('weak_password'));
            }
            vx_exec('UPDATE ' . vx_t('users') . ' SET password_hash=? WHERE id=?', 'si', [password_hash($pass, PASSWORD_DEFAULT), (int) $me['id']]);
            vx_flash(t('saved'));
            vx_go('settings');
        }
        vx_exec('UPDATE ' . vx_t('settings') . ' SET business_name=?, address=?, phone=?, invoice_note=? WHERE id=1', 'ssss', [trim((string) $_POST['business_name']), trim((string) $_POST['address']), trim((string) $_POST['phone']), trim((string) $_POST['invoice_note'])]);
        vx_flash(t('saved'));
        vx_go('settings');
    }
    $html = '<form method="post" class="card">' . vx_csrf_field();
    $html .= '<label>' . vx_e(t('business_name')) . '</label><input name="business_name" value="' . vx_e((string) ($row['business_name'] ?? '')) . '">';
    $html .= '<label>' . vx_e(t('address')) . '</label><input name="address" value="' . vx_e((string) ($row['address'] ?? '')) . '">';
    $html .= '<label>' . vx_e(t('phone')) . '</label><input name="phone" value="' . vx_e((string) ($row['phone'] ?? '')) . '">';
    $html .= '<label>' . vx_e(t('note')) . '</label><input name="invoice_note" value="' . vx_e((string) ($row['invoice_note'] ?? '')) . '">';
    $html .= '<button type="submit">' . vx_e(t('save')) . '</button></form>';
    $html .= '<form method="post" class="card">' . vx_csrf_field() . '<input type="hidden" name="act" value="password"><label>' . vx_e(t('old_password')) . '</label><input type="password" name="old_password" required><label>' . vx_e(t('new_password')) . '</label><input type="password" name="new_password" required minlength="8"><button type="submit">' . vx_e(t('change_password')) . '</button></form>';
    vx_layout('settings', $html);
}

function vx_page_audit(): void
{
    vx_require('audit');
    $rows = vx_all('SELECT * FROM ' . vx_t('audit_log') . ' ORDER BY id DESC LIMIT 80');
    $html = '<div class="table-wrap"><table><tr><th>' . vx_e(t('date')) . '</th><th>' . vx_e(t('action')) . '</th><th>' . vx_e(t('note')) . '</th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['created_at']) . '</td><td>' . vx_e($row['action']) . '</td><td>' . vx_e($row['detail']) . '</td></tr>';
    }
    vx_layout('audit', $html . '</table></div>');
}

function vx_page_barcode(): void
{
    vx_require('barcode');
    $rows = vx_all('SELECT sku, name, barcode, retail_price FROM ' . vx_t('products') . ' WHERE deleted_at IS NULL ORDER BY name LIMIT 60');
    $html = '<div class="grid two">';
    foreach ($rows as $row) {
        $code = $row['barcode'] !== '' ? $row['barcode'] : $row['sku'];
        $html .= '<div class="card">' . vx_code39((string) $code) . '<div>' . vx_e($row['name']) . '<br>' . vx_e(vx_money((float) $row['retail_price'])) . '</div></div>';
    }
    $html .= '</div><p class="noprint"><button type="button" onclick="window.print()">' . vx_e(t('print')) . '</button></p>';
    vx_layout('barcode', $html);
}

function vx_page_rooms(): void
{
    vx_require('rooms');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        if (($_POST['act'] ?? '') === 'type') {
            vx_insert('INSERT INTO ' . vx_t('room_types') . ' (name, base_rate) VALUES (?,?)', 'sd', [trim((string) $_POST['name']), (float) $_POST['base_rate']]);
        } else {
            vx_insert('INSERT INTO ' . vx_t('rooms') . ' (room_no, room_type_id, floor_no, rate, status) VALUES (?,?,?,?,?)', 'sisds', [trim((string) $_POST['room_no']), (int) $_POST['room_type_id'], trim((string) $_POST['floor_no']), (float) $_POST['rate'], 'vacant']);
        }
        vx_flash(t('saved'));
        vx_go('rooms');
    }
    $types = vx_all('SELECT * FROM ' . vx_t('room_types') . ' WHERE deleted_at IS NULL');
    $rooms = vx_all('SELECT r.*, t.name type_name FROM ' . vx_t('rooms') . ' r JOIN ' . vx_t('room_types') . ' t ON t.id=r.room_type_id WHERE r.deleted_at IS NULL ORDER BY r.room_no');
    $html = '<form method="post" class="card grid two">' . vx_csrf_field() . '<input type="hidden" name="act" value="type"><div><label>' . vx_e(t('room_type')) . '</label><input name="name" required></div><div><label>' . vx_e(t('rate')) . '</label><input name="base_rate" value="0"></div><button>' . vx_e(t('save')) . '</button></form>';
    $opts = '';
    foreach ($types as $type) {
        $opts .= '<option value="' . (int) $type['id'] . '">' . vx_e($type['name']) . '</option>';
    }
    $html .= '<form method="post" class="card grid two">' . vx_csrf_field() . '<div><label>' . vx_e(t('room_no')) . '</label><input name="room_no" required></div><div><label>' . vx_e(t('room_type')) . '</label><select name="room_type_id">' . $opts . '</select></div><div><label>' . vx_e(t('floor')) . '</label><input name="floor_no"></div><div><label>' . vx_e(t('rate')) . '</label><input name="rate" value="0"></div><button>' . vx_e(t('save')) . '</button></form>';
    $html .= '<div class="table-wrap"><table><tr><th>' . vx_e(t('room_no')) . '</th><th>' . vx_e(t('room_type')) . '</th><th>' . vx_e(t('status')) . '</th><th>' . vx_e(t('rate')) . '</th></tr>';
    foreach ($rooms as $room) {
        $html .= '<tr><td>' . vx_e($room['room_no']) . '</td><td>' . vx_e($room['type_name']) . '</td><td>' . vx_e(t($room['status'])) . '</td><td>' . vx_e(vx_money((float) $room['rate'])) . '</td></tr>';
    }
    vx_layout('rooms', $html . '</table></div>');
}

function vx_page_bookings(): void
{
    vx_require('bookings');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $act = (string) ($_POST['act'] ?? 'book');
        if ($act === 'book') {
            $in = (string) $_POST['check_in'];
            $out = (string) $_POST['check_out'];
            $room = (int) $_POST['room_id'];
            if (strtotime($out) <= strtotime($in)) {
                throw new RuntimeException(t('bad_qty'));
            }
            $nights = max(1, (int) ((strtotime($out) - strtotime($in)) / 86400));
            $clash = vx_one('SELECT id FROM ' . vx_t('bookings') . " WHERE room_id=? AND status IN ('booked','checked_in') AND check_in < ? AND check_out > ?", 'iss', [$room, $out, $in]);
            if ($clash) {
                throw new RuntimeException(t('room_busy'));
            }
            $rate = (float) $_POST['rate'];
            $code = vx_next_memo('B');
            vx_insert(
                'INSERT INTO ' . vx_t('bookings') . ' (code, party_id, room_id, check_in, check_out, nights, rate, total, status, note, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                'siissiddssi',
                [$code, (int) $_POST['party_id'], $room, $in, $out, $nights, $rate, round($nights * $rate, 2), 'booked', trim((string) ($_POST['note'] ?? '')), (int) ($_SESSION['vx_uid'] ?? 0)]
            );
            vx_flash(t('saved'));
            vx_go('bookings');
        }
        if ($act === 'in') {
            $id = (int) $_POST['id'];
            $b = vx_one('SELECT * FROM ' . vx_t('bookings') . ' WHERE id=?', 'i', [$id]);
            vx_exec('UPDATE ' . vx_t('bookings') . " SET status='checked_in' WHERE id=?", 'i', [$id]);
            vx_exec('UPDATE ' . vx_t('rooms') . " SET status='occupied' WHERE id=?", 'i', [(int) $b['room_id']]);
            vx_flash(t('saved'));
            vx_go('bookings');
        }
        if ($act === 'out') {
            $id = (int) $_POST['id'];
            $b = vx_one('SELECT * FROM ' . vx_t('bookings') . ' WHERE id=?', 'i', [$id]);
            $roomProduct = vx_one('SELECT id FROM ' . vx_t('products') . " WHERE sku='ROOM' LIMIT 1");
            $saleId = 0;
            if ($roomProduct && (float) $b['total'] > 0 && (int) $b['sale_id'] === 0) {
                $saleId = vx_post_sale([
                    'party_id' => (int) $b['party_id'],
                    'account_id' => (int) $_POST['account_id'],
                    'paid' => (float) $_POST['paid'],
                    'discount' => 0,
                    'channel' => 'room',
                    'booking_id' => $id,
                    'lines' => [[
                        'product_id' => (int) $roomProduct['id'],
                        'qty' => (float) $b['nights'],
                        'price' => (float) $b['rate'],
                        'unit_id' => 0,
                    ]],
                ]);
            }
            vx_exec('UPDATE ' . vx_t('bookings') . " SET status='checked_out', sale_id=IF(sale_id=0,?,sale_id) WHERE id=?", 'ii', [$saleId, $id]);
            vx_exec('UPDATE ' . vx_t('rooms') . " SET status='dirty' WHERE id=?", 'i', [(int) $b['room_id']]);
            vx_insert('INSERT INTO ' . vx_t('housekeeping') . ' (room_id, task, status) VALUES (?,?,?)', 'iss', [(int) $b['room_id'], 'checkout clean', 'open']);
            vx_flash(t('saved'));
            vx_go($saleId ? 'memo' : 'bookings', $saleId ? ['type' => 'sale', 'id' => $saleId] : []);
        }
    }
    $html = '<form method="post" class="card grid two">' . vx_csrf_field() . '<input type="hidden" name="act" value="book">';
    $html .= '<div><label>' . vx_e(t('guest')) . '</label><select name="party_id">' . vx_opts('parties', 'name', 'deleted_at IS NULL') . '</select></div>';
    $html .= '<div><label>' . vx_e(t('room_no')) . '</label><select name="room_id">' . vx_opts('rooms', 'room_no', 'deleted_at IS NULL') . '</select></div>';
    $html .= '<div><label>' . vx_e(t('check_in')) . '</label><input type="date" name="check_in" required></div><div><label>' . vx_e(t('check_out')) . '</label><input type="date" name="check_out" required></div>';
    $html .= '<div><label>' . vx_e(t('rate')) . '</label><input name="rate" value="0"></div><button>' . vx_e(t('save')) . '</button></form>';
    $rows = vx_all('SELECT b.*, r.room_no, p.name guest FROM ' . vx_t('bookings') . ' b JOIN ' . vx_t('rooms') . ' r ON r.id=b.room_id JOIN ' . vx_t('parties') . ' p ON p.id=b.party_id ORDER BY b.id DESC LIMIT 30');
    $html .= '<div class="table-wrap"><table><tr><th>' . vx_e(t('memo')) . '</th><th>' . vx_e(t('guest')) . '</th><th>' . vx_e(t('room_no')) . '</th><th>' . vx_e(t('status')) . '</th><th></th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['code']) . '</td><td>' . vx_e($row['guest']) . '</td><td>' . vx_e($row['room_no']) . '</td><td>' . vx_e(t($row['status'])) . '</td><td>';
        if ($row['status'] === 'booked') {
            $html .= '<form method="post">' . vx_csrf_field() . '<input type="hidden" name="act" value="in"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button>' . vx_e(t('check_in')) . '</button></form>';
        }
        if ($row['status'] === 'checked_in') {
            $html .= '<form method="post">' . vx_csrf_field() . '<input type="hidden" name="act" value="out"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select><input name="paid" value="' . vx_e((string) $row['total']) . '"><button>' . vx_e(t('check_out')) . '</button></form>';
        }
        $html .= '</td></tr>';
    }
    vx_layout('bookings', $html . '</table></div>');
}

function vx_page_housekeeping(): void
{
    vx_require('housekeeping');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $id = (int) $_POST['id'];
        $task = vx_one('SELECT * FROM ' . vx_t('housekeeping') . ' WHERE id=?', 'i', [$id]);
        vx_exec('UPDATE ' . vx_t('housekeeping') . " SET status='done' WHERE id=?", 'i', [$id]);
        if ($task) {
            vx_exec('UPDATE ' . vx_t('rooms') . " SET status='vacant' WHERE id=? AND status='dirty'", 'i', [(int) $task['room_id']]);
        }
        vx_flash(t('saved'));
        vx_go('housekeeping');
    }
    $rows = vx_all('SELECT h.*, r.room_no FROM ' . vx_t('housekeeping') . ' h JOIN ' . vx_t('rooms') . ' r ON r.id=h.room_id ORDER BY h.id DESC LIMIT 40');
    $html = '<div class="table-wrap"><table><tr><th>' . vx_e(t('room_no')) . '</th><th>' . vx_e(t('task')) . '</th><th>' . vx_e(t('status')) . '</th><th></th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['room_no']) . '</td><td>' . vx_e($row['task']) . '</td><td>' . vx_e($row['status']) . '</td><td>';
        if ($row['status'] !== 'done') {
            $html .= '<form method="post">' . vx_csrf_field() . '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><button>' . vx_e(t('done')) . '</button></form>';
        }
        $html .= '</td></tr>';
    }
    vx_layout('housekeeping', $html . '</table></div>');
}

function vx_page_laundry(): void
{
    vx_require('laundry');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $id = vx_insert('INSERT INTO ' . vx_t('laundry') . ' (party_id, room_id, item_name, qty, amount) VALUES (?,?,?,?,?)', 'iisdd', [(int) $_POST['party_id'], (int) $_POST['room_id'], trim((string) $_POST['item_name']), (float) $_POST['qty'], (float) $_POST['amount']]);
        $product = vx_one('SELECT id FROM ' . vx_t('products') . " WHERE sku='LAUNDRY' LIMIT 1");
        if ($product) {
            $sale = vx_post_sale([
                'party_id' => (int) $_POST['party_id'],
                'account_id' => (int) $_POST['account_id'],
                'paid' => (float) $_POST['paid'],
                'channel' => 'laundry',
                'lines' => [['product_id' => (int) $product['id'], 'qty' => (float) $_POST['qty'], 'price' => ((float) $_POST['qty'] > 0 ? (float) $_POST['amount'] / (float) $_POST['qty'] : 0), 'unit_id' => 0]],
            ]);
            vx_exec('UPDATE ' . vx_t('laundry') . ' SET sale_id=?, status="billed" WHERE id=?', 'ii', [$sale, $id]);
        }
        vx_flash(t('saved'));
        vx_go('laundry');
    }
    $html = '<form method="post" class="card">' . vx_csrf_field();
    $html .= '<label>' . vx_e(t('guest')) . '</label><select name="party_id">' . vx_opts('parties') . '</select>';
    $html .= '<label>' . vx_e(t('room_no')) . '</label><select name="room_id">' . vx_opts('rooms', 'room_no', 'deleted_at IS NULL') . '</select>';
    $html .= '<label>' . vx_e(t('name')) . '</label><input name="item_name" required><label>' . vx_e(t('qty')) . '</label><input name="qty" value="1"><label>' . vx_e(t('amount')) . '</label><input name="amount" value="0">';
    $html .= '<label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select><label>' . vx_e(t('paid')) . '</label><input name="paid" value="0"><button>' . vx_e(t('save')) . '</button></form>';
    vx_layout('laundry', $html);
}

function vx_page_restaurant(): void
{
    vx_require('restaurant');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        if (($_POST['act'] ?? '') === 'table') {
            vx_insert('INSERT INTO ' . vx_t('rest_tables') . ' (name, seats) VALUES (?,?)', 'si', [trim((string) $_POST['name']), (int) $_POST['seats']]);
            vx_flash(t('saved'));
            vx_go('restaurant');
        }
        $kot = vx_insert('INSERT INTO ' . vx_t('kots') . ' (table_id, status, note) VALUES (?,"sent",?)', 'is', [(int) $_POST['table_id'], trim((string) ($_POST['note'] ?? ''))]);
        $lines = vx_lines_from_post();
        foreach ($lines as $line) {
            if ((int) $line['product_id'] <= 0 || (float) $line['qty'] <= 0) {
                continue;
            }
            vx_insert('INSERT INTO ' . vx_t('kot_items') . ' (kot_id, product_id, qty, price) VALUES (?,?,?,?)', 'iidd', [$kot, (int) $line['product_id'], (float) $line['qty'], (float) $line['price']]);
        }
        if (($_POST['settle'] ?? '') === '1') {
            $sale = vx_post_sale([
                'party_id' => (int) ($_POST['party_id'] ?? 0),
                'account_id' => (int) $_POST['account_id'],
                'paid' => (float) $_POST['paid'],
                'discount' => (float) ($_POST['discount'] ?? 0),
                'channel' => 'restaurant',
                'lines' => $lines,
            ]);
            vx_exec('UPDATE ' . vx_t('kots') . " SET sale_id=?, status='done' WHERE id=?", 'ii', [$sale, $kot]);
            vx_go('memo', ['type' => 'sale', 'id' => $sale]);
        }
        vx_flash(t('saved'));
        vx_go('restaurant');
    }
    $html = '<form method="post" class="card grid two">' . vx_csrf_field() . '<input type="hidden" name="act" value="table"><div><label>' . vx_e(t('table')) . '</label><input name="name" required></div><div><label>' . vx_e(t('seats')) . '</label><input name="seats" value="4"></div><button>' . vx_e(t('save')) . '</button></form>';
    $html .= '<form method="post">' . vx_csrf_field();
    $html .= '<div class="card grid two"><div><label>' . vx_e(t('table')) . '</label><select name="table_id">' . vx_opts('rest_tables', 'name', '1=1') . '</select></div>';
    $html .= '<div><label>' . vx_e(t('account')) . '</label><select class="ame-account-select" data-ame-account-select="1" data-ame-direction="receive" name="account_id">' . vx_opts('accounts', 'name', 'status=1') . '</select></div>';
    $html .= '<div><label>' . vx_e(t('paid')) . '</label><input name="paid" value="0"></div><div><label><input style="width:auto" type="checkbox" name="settle" value="1" checked> ' . vx_e(t('settle')) . '</label></div></div>';
    $html .= vx_line_form(4, false);
    $html .= '<button>' . vx_e(t('kitchen')) . '</button></form>';
    vx_layout('restaurant', $html);
}

function vx_page_doctors(): void
{
    vx_require('doctors');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        vx_insert('INSERT INTO ' . vx_t('doctors') . ' (name, phone, specialty) VALUES (?,?,?)', 'sss', [trim((string) $_POST['name']), trim((string) $_POST['phone']), trim((string) $_POST['specialty'])]);
        vx_flash(t('saved'));
        vx_go('doctors');
    }
    $rows = vx_all('SELECT * FROM ' . vx_t('doctors') . ' WHERE deleted_at IS NULL ORDER BY name');
    $html = '<form method="post" class="card">' . vx_csrf_field() . '<label>' . vx_e(t('name')) . '</label><input name="name" required><label>' . vx_e(t('phone')) . '</label><input name="phone"><label>' . vx_e(t('specialty')) . '</label><input name="specialty"><button>' . vx_e(t('save')) . '</button></form><ul>';
    foreach ($rows as $row) {
        $html .= '<li>' . vx_e($row['name'] . ' · ' . $row['specialty']) . '</li>';
    }
    vx_layout('doctors', $html . '</ul>');
}

function vx_page_expired(): void
{
    vx_require('alerts');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        vx_csrf_check();
        $batch = vx_one('SELECT * FROM ' . vx_t('batches') . ' WHERE id=?', 'i', [(int) $_POST['batch_id']]);
        if ($batch && (float) $batch['qty'] > 0) {
            vx_tx(function () use ($batch) {
                vx_move_stock((int) $batch['product_id'], -1 * (float) $batch['qty'], 'expired', 'batch', (int) $batch['id'], (int) $batch['id'], 1);
            });
        }
        vx_flash(t('saved'));
        vx_go('expired');
    }
    $rows = vx_all('SELECT b.*, p.name FROM ' . vx_t('batches') . ' b JOIN ' . vx_t('products') . ' p ON p.id=b.product_id WHERE b.qty>0 AND b.expiry_date IS NOT NULL AND b.expiry_date<CURDATE() ORDER BY b.expiry_date');
    $html = '<div class="table-wrap"><table><tr><th>' . vx_e(t('name')) . '</th><th>' . vx_e(t('batch')) . '</th><th>' . vx_e(t('expiry_date')) . '</th><th>' . vx_e(t('qty')) . '</th><th></th></tr>';
    foreach ($rows as $row) {
        $html .= '<tr><td>' . vx_e($row['name']) . '</td><td>' . vx_e($row['batch_no']) . '</td><td>' . vx_e((string) $row['expiry_date']) . '</td><td>' . vx_e((string) $row['qty']) . '</td><td><form method="post">' . vx_csrf_field() . '<input type="hidden" name="batch_id" value="' . (int) $row['id'] . '"><button>' . vx_e(t('write_off')) . '</button></form></td></tr>';
    }
    vx_layout('expired', $html . '</table></div>');
}

function vx_dispatch(): void
{
    $route = (string) ($_GET['r'] ?? '');
    if ($route === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php?r=login');
        exit;
    }
    $needsDb = vx_env('DB_NAME') !== '';
    if (!$needsDb || $route === 'install' || ($needsDb && !vx_installed() && $route !== 'login')) {
        if (!$needsDb || !vx_installed()) {
            vx_page_install();
            return;
        }
    }
    if (!vx_installed()) {
        vx_page_install();
        return;
    }
    if ($route === '' || $route === 'login') {
        vx_page_login();
        return;
    }
    $map = [
        'dashboard' => 'vx_page_dashboard',
        'parties' => 'vx_page_parties',
        'party' => 'vx_page_party',
        'products' => 'vx_page_products',
        'categories' => static fn () => vx_page_simple_name('categories', 'products', 'categories'),
        'brands' => static fn () => vx_page_simple_name('brands', 'products', 'brands'),
        'purchase' => 'vx_page_purchase',
        'sale' => 'vx_page_sale',
        'sale_return' => static fn () => vx_page_return('sale'),
        'purchase_return' => static fn () => vx_page_return('purchase'),
        'memo' => 'vx_page_memo',
        'stock' => 'vx_page_stock',
        'transfers' => 'vx_page_transfers',
        'expenses' => 'vx_page_expenses',
        'incomes' => 'vx_page_incomes',
        'accounts' => 'vx_page_accounts',
        'employees' => 'vx_page_employees',
        'reports' => 'vx_page_reports',
        'alerts' => 'vx_page_alerts',
        'files' => 'vx_page_files',
        'file' => 'vx_page_file',
        'users' => 'vx_page_users',
        'settings' => 'vx_page_settings',
        'audit' => 'vx_page_audit',
        'barcode' => 'vx_page_barcode',
        'rooms' => 'vx_page_rooms',
        'bookings' => 'vx_page_bookings',
        'housekeeping' => 'vx_page_housekeeping',
        'laundry' => 'vx_page_laundry',
        'restaurant' => 'vx_page_restaurant',
        'doctors' => 'vx_page_doctors',
        'expired' => 'vx_page_expired',
    ];
    if (!isset($map[$route])) {
        http_response_code(404);
        vx_layout('not_found', '<p>' . vx_e(t('not_found')) . '</p>');
        return;
    }
    $map[$route]();
}
