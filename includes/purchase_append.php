<?php
/**
 * 10.98.343 — completed/draft ক্রয় মেমোতে নতুন পণ্য INSERT।
 * Native এডিট «খোলা নয়» মেমোতে সারি বাদ দিলেও এই খাতায় লাইন বসে।
 * কোনো SQL DELETE নেই।
 */

if (!function_exists('ame_db_table_exists')) {
    $ame_sa = __DIR__ . '/super_admin.php';
    if (is_file($ame_sa)) {
        require_once $ame_sa;
    }
}

if (!function_exists('ame_db_table_exists')) {
    function ame_db_table_exists(mysqli $db, string $table): bool
    {
        $esc = $db->real_escape_string($table);
        $res = @$db->query("SHOW TABLES LIKE '{$esc}'");
        $ok = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $ok;
    }
}

if (!function_exists('ame_db_columns')) {
    function ame_db_columns(mysqli $db, string $table): array
    {
        $cols = [];
        $esc = $db->real_escape_string($table);
        $res = @$db->query("SHOW COLUMNS FROM `{$esc}`");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $cols[strtolower((string) ($row['Field'] ?? ''))] = $row;
            }
            $res->free();
        }
        return $cols;
    }
}

if (!function_exists('ame_db_has_col')) {
    function ame_db_has_col(array $cols, string $name): bool
    {
        return isset($cols[strtolower($name)]);
    }
}

if (!function_exists('ame_col')) {
    function ame_col(array $cols, array $names): ?string
    {
        foreach ($names as $name) {
            if (ame_db_has_col($cols, $name)) {
                return $name;
            }
        }
        return null;
    }
}

if (!function_exists('ame_purchase_ident')) {
    function ame_purchase_ident(string $name): string
    {
        return '`' . str_replace('`', '', $name) . '`';
    }
}

if (!function_exists('ame_purchase_bind')) {
    function ame_purchase_bind(mysqli_stmt $stmt, string $types, array $vals): bool
    {
        $bind = [$types];
        foreach ($vals as $i => $_) {
            $bind[] = &$vals[$i];
        }
        return (bool) call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!function_exists('ame_purchase_fill_required')) {
    function ame_purchase_fill_required(array $cols, array $data): array
    {
        foreach ($cols as $name => $meta) {
            if (array_key_exists($name, $data)) {
                continue;
            }
            $extra = strtolower((string) ($meta['Extra'] ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                continue;
            }
            $nullable = strtoupper((string) ($meta['Null'] ?? '')) === 'YES';
            $default = $meta['Default'] ?? null;
            if ($default !== null || $nullable) {
                continue;
            }
            $type = strtolower((string) ($meta['Type'] ?? ''));
            if (preg_match('/int|decimal|float|double|numeric|real/', $type)) {
                $data[$name] = 0;
            } elseif (preg_match('/date|time/', $type)) {
                $data[$name] = date('Y-m-d H:i:s');
            } else {
                $data[$name] = '';
            }
        }
        return $data;
    }
}

if (!function_exists('ame_purchase_insert')) {
    function ame_purchase_insert(mysqli $db, string $table, array $data): int
    {
        $cols = [];
        $ph = [];
        $vals = [];
        $types = '';
        foreach ($data as $col => $val) {
            $cols[] = ame_purchase_ident((string) $col);
            $ph[] = '?';
            if (is_int($val)) {
                $types .= 'i';
                $vals[] = $val;
            } elseif (is_float($val) || is_numeric($val)) {
                $types .= 'd';
                $vals[] = (float) $val;
            } else {
                $types .= 's';
                $vals[] = (string) $val;
            }
        }
        $sql = 'INSERT INTO ' . ame_purchase_ident($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('সারি লেখা যায়নি: ' . $db->error);
        }
        if ($types !== '') {
            ame_purchase_bind($stmt, $types, $vals);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('সারি লেখা যায়নি: ' . $err);
        }
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('ame_purchase_exec')) {
    function ame_purchase_exec(mysqli $db, string $sql, string $types = '', array $vals = []): void
    {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($db->error);
        }
        if ($types !== '') {
            ame_purchase_bind($stmt, $types, $vals);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException($err);
        }
        $stmt->close();
    }
}

if (!function_exists('ame_purchase_logged_in')) {
    function ame_purchase_logged_in(): bool
    {
        if (function_exists('ame_current_user_id') && ame_current_user_id() > 0) {
            return true;
        }
        foreach (['user_id', 'id', 'uid', 'admin_id', 'employee_id', 'staff_id'] as $k) {
            if (!empty($_SESSION[$k])) {
                return true;
            }
        }
        foreach (['user', 'username', 'logged_in', 'admin', 'is_logged_in'] as $k) {
            if (!empty($_SESSION[$k])) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ame_purchase_map')) {
    function ame_purchase_map(mysqli $db): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }
        $headNames = [
            'purchases', 'purchase', 'purchase_invoices', 'purchase_invoice',
            'restock_invoices', 'restocks', 'stock_purchases', 'buy', 'buys',
        ];
        $itemNames = [
            'purchase_items', 'purchase_item', 'purchase_details', 'purchase_detail',
            'purchase_products', 'purchase_lines', 'restock_items', 'restock_details',
            'buy_items', 'buy_details',
        ];
        $productNames = ['products', 'product', 'items', 'stock_items', 'inventory'];
        $head = null;
        $headCols = [];
        foreach ($headNames as $t) {
            if (ame_db_table_exists($db, $t)) {
                $head = $t;
                $headCols = ame_db_columns($db, $t);
                break;
            }
        }
        if ($head === null) {
            $res = @$db->query('SHOW TABLES');
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_row()) {
                    $t = strtolower((string) ($row[0] ?? ''));
                    if ($t !== '' && preg_match('/^(purchase|restock|buy)s?$/', $t)) {
                        $head = (string) $row[0];
                        $headCols = ame_db_columns($db, $head);
                        break;
                    }
                }
                $res->free();
            }
        }
        $item = null;
        $itemCols = [];
        if ($head !== null) {
            foreach (array_unique(array_merge([
                $head . '_items', $head . '_item', $head . '_details', $head . '_detail',
                $head . '_products', $head . '_lines',
            ], $itemNames)) as $t) {
                if (ame_db_table_exists($db, $t)) {
                    $item = $t;
                    $itemCols = ame_db_columns($db, $t);
                    break;
                }
            }
        }
        $product = null;
        $productCols = [];
        foreach ($productNames as $t) {
            if (ame_db_table_exists($db, $t)) {
                $product = $t;
                $productCols = ame_db_columns($db, $t);
                break;
            }
        }
        $cached = [
            'head' => $head,
            'headCols' => $headCols,
            'item' => $item,
            'itemCols' => $itemCols,
            'product' => $product,
            'productCols' => $productCols,
        ];
        return $cached;
    }
}

if (!function_exists('ame_purchase_find')) {
    function ame_purchase_find(mysqli $db, int $id): ?array
    {
        $map = ame_purchase_map($db);
        $head = $map['head'];
        $cols = $map['headCols'];
        if ($head === null || $id <= 0) {
            return null;
        }
        $idCol = ame_col($cols, ['id', 'purchase_id', 'invoice_id', 'pid']);
        $noCol = ame_col($cols, ['invoice_no', 'invoice_number', 'memo_no', 'memo_number', 'purchase_no', 'bill_no', 'ref_no', 'number']);
        if ($idCol === null) {
            return null;
        }
        $where = ame_purchase_ident($idCol) . ' = ?';
        $types = 'i';
        $vals = [$id];
        if ($noCol !== null) {
            $where .= ' OR ' . ame_purchase_ident($noCol) . ' = ?';
            $types .= 's';
            $vals[] = (string) $id;
        }
        $sql = 'SELECT * FROM ' . ame_purchase_ident($head) . ' WHERE ' . $where . ' LIMIT 1';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return null;
        }
        ame_purchase_bind($stmt, $types, $vals);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('ame_purchase_search_products')) {
    function ame_purchase_search_products(mysqli $db, string $q): array
    {
        $map = ame_purchase_map($db);
        $table = $map['product'];
        $cols = $map['productCols'];
        if ($table === null || $q === '') {
            return [];
        }
        $idCol = ame_col($cols, ['id', 'product_id']);
        $nameCol = ame_col($cols, ['name', 'product_name', 'title', 'item_name']);
        $codeCol = ame_col($cols, ['sku', 'code', 'barcode', 'product_code', 'item_code']);
        $priceCol = ame_col($cols, ['purchase_price', 'buy_price', 'cost_price', 'cost', 'price', 'unit_price']);
        $stockCol = ame_col($cols, ['stock', 'quantity', 'qty', 'current_stock', 'available']);
        if ($idCol === null || $nameCol === null) {
            return [];
        }
        $like = '%' . $q . '%';
        $where = ame_purchase_ident($nameCol) . ' LIKE ?';
        $types = 's';
        $vals = [$like];
        if ($codeCol !== null) {
            $where .= ' OR ' . ame_purchase_ident($codeCol) . ' LIKE ?';
            $types .= 's';
            $vals[] = $like;
        }
        $sel = [ame_purchase_ident($idCol) . ' AS id', ame_purchase_ident($nameCol) . ' AS name'];
        if ($codeCol) {
            $sel[] = ame_purchase_ident($codeCol) . ' AS code';
        }
        if ($priceCol) {
            $sel[] = ame_purchase_ident($priceCol) . ' AS price';
        }
        if ($stockCol) {
            $sel[] = ame_purchase_ident($stockCol) . ' AS stock';
        }
        $statusCol = ame_col($cols, ['status']);
        $statusSql = '';
        if ($statusCol !== null) {
            if (!function_exists('ame_product_option_sql')) {
                require_once __DIR__ . '/helpers.php';
            }
            $statusSql = ' AND ' . ame_product_option_sql($statusCol);
        }
        $sql = 'SELECT ' . implode(', ', $sel) . ' FROM ' . ame_purchase_ident($table)
            . ' WHERE (' . $where . ')' . $statusSql . ' LIMIT 20';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        ame_purchase_bind($stmt, $types, $vals);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'code' => (string) ($row['code'] ?? ''),
                    'price' => (float) ($row['price'] ?? 0),
                    'stock' => $row['stock'] ?? '',
                ];
            }
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('ame_purchase_load_product')) {
    function ame_purchase_load_product(mysqli $db, int $pid): ?array
    {
        if ($pid <= 0) {
            return null;
        }
        $map = ame_purchase_map($db);
        $table = $map['product'];
        $cols = $map['productCols'];
        if ($table === null) {
            return null;
        }
        $idCol = ame_col($cols, ['id', 'product_id']);
        if ($idCol === null) {
            return null;
        }
        $stmt = $db->prepare('SELECT * FROM ' . ame_purchase_ident($table) . ' WHERE ' . ame_purchase_ident($idCol) . ' = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        ame_purchase_bind($stmt, 'i', [$pid]);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('ame_purchase_append_item')) {
    function ame_purchase_append_item(mysqli $db, int $invoiceId, array $input): array
    {
        $map = ame_purchase_map($db);
        $head = $map['head'];
        $headCols = $map['headCols'];
        $item = $map['item'];
        $itemCols = $map['itemCols'];
        if ($head === null) {
            throw new RuntimeException('ক্রয় খাতা টেবিল পাওয়া যায়নি।');
        }
        $invoice = ame_purchase_find($db, $invoiceId);
        if (!$invoice) {
            throw new RuntimeException('মেমো ' . $invoiceId . ' পাওয়া যায়নি।');
        }
        $headIdCol = ame_col($headCols, ['id', 'purchase_id', 'invoice_id', 'pid']) ?? 'id';
        $headPk = (int) ($invoice[$headIdCol] ?? $invoiceId);
        $pid = (int) ($input['product_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        $qty = (float) ($input['qty'] ?? 1);
        if ($qty <= 0) {
            $qty = 1;
        }
        $price = (float) ($input['price'] ?? 0);
        $product = $pid > 0 ? ame_purchase_load_product($db, $pid) : null;
        $pCols = $map['productCols'];
        if ($product) {
            if ($name === '') {
                $name = (string) ($product[ame_col($pCols, ['name', 'product_name', 'title']) ?? 'name'] ?? '');
            }
            if ($code === '') {
                $c = ame_col($pCols, ['sku', 'code', 'barcode', 'product_code']);
                $code = $c ? (string) ($product[$c] ?? '') : '';
            }
            if ($price <= 0) {
                $pc = ame_col($pCols, ['purchase_price', 'buy_price', 'cost_price', 'cost', 'price']);
                $price = $pc ? (float) ($product[$pc] ?? 0) : 0;
            }
        }
        if ($name === '' && $pid <= 0) {
            throw new RuntimeException('পণ্য বেছে নিন।');
        }
        $lineTotal = round($qty * $price, 2);
        $db->begin_transaction();
        try {
            $itemId = 0;
            if ($item !== null) {
                $fk = ame_col($itemCols, ['purchase_id', 'invoice_id', 'restock_id', 'buy_id', 'pid', 'purchase_invoice_id']) ?? 'purchase_id';
                $row = [];
                $row[$fk] = $headPk;
                $pcol = ame_col($itemCols, ['product_id', 'item_id', 'pid']);
                if ($pcol && $pid > 0) {
                    $row[$pcol] = $pid;
                }
                $ncol = ame_col($itemCols, ['product_name', 'name', 'item_name', 'title']);
                if ($ncol) {
                    $row[$ncol] = $name;
                }
                $ccol = ame_col($itemCols, ['sku', 'code', 'product_code', 'barcode']);
                if ($ccol) {
                    $row[$ccol] = $code;
                }
                $qcol = ame_col($itemCols, ['quantity', 'qty', 'qty_purchased', 'pcs']);
                if ($qcol) {
                    $row[$qcol] = $qty;
                }
                $prcol = ame_col($itemCols, ['purchase_price', 'buy_price', 'price', 'rate', 'unit_price', 'cost']);
                if ($prcol) {
                    $row[$prcol] = $price;
                }
                $tcol = ame_col($itemCols, ['total', 'subtotal', 'amount', 'line_total', 'net_total']);
                if ($tcol) {
                    $row[$tcol] = $lineTotal;
                }
                $created = ame_col($itemCols, ['created_at', 'added_at', 'date']);
                if ($created) {
                    $row[$created] = date('Y-m-d H:i:s');
                }
                $row = ame_purchase_fill_required($itemCols, $row);
                $itemId = ame_purchase_insert($db, $item, $row);
            } else {
                $jsonCol = ame_col($headCols, ['items', 'item_json', 'products_json', 'cart', 'lines']);
                if ($jsonCol === null) {
                    throw new RuntimeException('ক্রয় সারির টেবিল পাওয়া যায়নি।');
                }
                $raw = (string) ($invoice[$jsonCol] ?? '[]');
                $list = json_decode($raw, true);
                if (!is_array($list)) {
                    $list = [];
                }
                $list[] = [
                    'product_id' => $pid,
                    'name' => $name,
                    'code' => $code,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $lineTotal,
                ];
                ame_purchase_exec(
                    $db,
                    'UPDATE ' . ame_purchase_ident($head) . ' SET ' . ame_purchase_ident($jsonCol) . ' = ? WHERE ' . ame_purchase_ident($headIdCol) . ' = ?',
                    'si',
                    [json_encode($list, JSON_UNESCAPED_UNICODE), $headPk]
                );
            }

            $totalCol = ame_col($headCols, ['grand_total', 'net_total', 'total_amount', 'total', 'amount', 'payable']);
            $subCol = ame_col($headCols, ['subtotal', 'sub_total', 'item_total']);
            $dueCol = ame_col($headCols, ['due', 'due_amount', 'balance', 'payable_due']);
            $paidCol = ame_col($headCols, ['paid', 'paid_amount', 'payment', 'pay']);
            $sets = [];
            $types = '';
            $vals = [];
            if ($subCol) {
                $sets[] = ame_purchase_ident($subCol) . ' = IFNULL(' . ame_purchase_ident($subCol) . ',0) + ?';
                $types .= 'd';
                $vals[] = $lineTotal;
            }
            if ($totalCol) {
                $sets[] = ame_purchase_ident($totalCol) . ' = IFNULL(' . ame_purchase_ident($totalCol) . ',0) + ?';
                $types .= 'd';
                $vals[] = $lineTotal;
            }
            if ($dueCol) {
                $sets[] = ame_purchase_ident($dueCol) . ' = IFNULL(' . ame_purchase_ident($dueCol) . ',0) + ?';
                $types .= 'd';
                $vals[] = $lineTotal;
            } elseif ($totalCol && $paidCol) {
                $sets[] = ame_purchase_ident($totalCol) . ' = IFNULL(' . ame_purchase_ident($totalCol) . ',0) + 0';
            }
            $updCol = ame_col($headCols, ['updated_at', 'modified_at']);
            if ($updCol) {
                $sets[] = ame_purchase_ident($updCol) . ' = NOW()';
            }
            if ($sets) {
                $vals[] = $headPk;
                $types .= 'i';
                ame_purchase_exec(
                    $db,
                    'UPDATE ' . ame_purchase_ident($head) . ' SET ' . implode(', ', $sets)
                    . ' WHERE ' . ame_purchase_ident($headIdCol) . ' = ?',
                    $types,
                    $vals
                );
            }

            if ($pid > 0 && $map['product'] !== null) {
                $stockCol = ame_col($pCols, ['stock', 'quantity', 'qty', 'current_stock']);
                $pIdCol = ame_col($pCols, ['id', 'product_id']);
                if ($stockCol && $pIdCol) {
                    ame_purchase_exec(
                        $db,
                        'UPDATE ' . ame_purchase_ident($map['product']) . ' SET ' . ame_purchase_ident($stockCol)
                        . ' = IFNULL(' . ame_purchase_ident($stockCol) . ',0) + ? WHERE ' . ame_purchase_ident($pIdCol) . ' = ?',
                        'di',
                        [$qty, $pid]
                    );
                }
            }

            $partyId = 0;
            $partyCol = ame_col($headCols, ['supplier_id', 'vendor_id', 'customer_id', 'party_id']);
            if ($partyCol) {
                $partyId = (int) ($invoice[$partyCol] ?? 0);
            }
            if ($partyId > 0) {
                foreach (['suppliers', 'customers', 'vendors'] as $pt) {
                    if (!ame_db_table_exists($db, $pt)) {
                        continue;
                    }
                    $pc = ame_db_columns($db, $pt);
                    $due = ame_col($pc, ['total_due', 'due', 'balance', 'payable']);
                    $idc = ame_col($pc, ['id']);
                    if ($due && $idc) {
                        ame_purchase_exec(
                            $db,
                            'UPDATE ' . ame_purchase_ident($pt) . ' SET ' . ame_purchase_ident($due)
                            . ' = IFNULL(' . ame_purchase_ident($due) . ',0) + ? WHERE ' . ame_purchase_ident($idc) . ' = ?',
                            'di',
                            [$lineTotal, $partyId]
                        );
                        break;
                    }
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        $fresh = ame_purchase_find($db, $headPk) ?: $invoice;
        $totalCol = ame_col($headCols, ['grand_total', 'net_total', 'total_amount', 'total', 'amount']) ?? 'total';
        return [
            'ok' => true,
            'item_id' => $itemId,
            'invoice_id' => $headPk,
            'name' => $name,
            'code' => $code,
            'qty' => $qty,
            'price' => $price,
            'line_total' => $lineTotal,
            'invoice_total' => (float) ($fresh[$totalCol] ?? 0),
            'message' => 'পণ্য এই মেমোতে যোগ হয়েছে।',
        ];
    }
}

if (!function_exists('ame_purchase_boot')) {
    function ame_purchase_boot(): void
    {
        static $started = false;
        if ($started) {
            return;
        }
        $started = true;
        $uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? '') . ' ' . (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('#(_api\.php|append_api|/ajax/|/api/)#', $uri)) {
            return;
        }
        if (!preg_match('#(/m(/|$|\\?)|/m\\.php|purchase|restock|invoice|memo)#', $uri)) {
            return;
        }
        $injectLib = __DIR__ . '/purchase_desk_inject.php';
        if (is_file($injectLib)) {
            require_once $injectLib;
        }
        ob_start(static function (string $html): string {
            if ($html === '' || str_contains($html, 'ame-pdesk-348.php') || str_contains($html, 'ame-pdesk-346.php') || str_contains($html, 'ame-pdesk-343.php') || str_contains($html, 'id="amePurchaseDeskCss"')) {
                return $html;
            }
            if (!preg_match('/<\/body>|<\/html>|<div id="memo-container"|ক্রয় মেমো/u', $html)) {
                return $html;
            }
            if (str_starts_with(ltrim($html), '{') || str_starts_with(ltrim($html), '[')) {
                return $html;
            }
            $inject = function_exists('ame_purchase_desk_tags')
                ? ame_purchase_desk_tags()
                : "<script src=\"/assets/js/ame-pdesk-348.php?v=10.98.348\" defer></script>\n";
            if (str_contains($html, '</body>')) {
                return str_replace('</body>', $inject . '</body>', $html);
            }
            return $html . $inject;
        });
    }
}
