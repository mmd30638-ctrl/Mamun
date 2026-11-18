<?php
/**
 * পুরনো POS ছবি ফাইল থাকলে ক্রেতার সাথে আবার জোড়া লাগায়।
 */

function ame_legacy_photo_rows(mysqli $db): array
{
    $byId = [];
    $mapFile = __DIR__ . '/legacy_customer_photos.php';
    if (is_file($mapFile)) {
        $rows = require $mapFile;
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $id = (int)($row['id'] ?? 0);
                $file = basename((string)($row['file'] ?? ''));
                if ($id <= 0 || $file === '') {
                    continue;
                }
                $byId[$id] = [
                    'id' => $id,
                    'code' => (string)($row['code'] ?? ''),
                    'file' => $file,
                ];
            }
        }
    }

    $legacy = @$db->query(
        'SELECT CusId, CusIdCode, CusPic FROM mae_legacy.customer
         WHERE CusPic IS NOT NULL AND TRIM(CusPic) != \'\''
    );
    if ($legacy) {
        while ($row = $legacy->fetch_assoc()) {
            $id = (int)$row['CusId'];
            $file = basename(str_replace('\\', '/', trim((string)$row['CusPic'])));
            if ($id <= 0 || $file === '') {
                continue;
            }
            $code = function_exists('mae_fit_customer_code')
                ? mae_fit_customer_code(trim((string)$row['CusIdCode']), $id)
                : trim((string)$row['CusIdCode']);
            $byId[$id] = ['id' => $id, 'code' => $code, 'file' => $file];
        }
    }

    return array_values($byId);
}

function ame_photo_file_present(string $basename): bool
{
    $base = basename($basename);
    if ($base === '') {
        return false;
    }
    foreach (ame_customer_photo_dirs() as $dir) {
        if (is_file($dir . '/' . $base)) {
            return true;
        }
    }
    return false;
}

function ame_extract_photo_zip(string $zipPath): array
{
    $dest = ame_webroot_path() . '/uploads/customers';
    if (!is_dir($dest) && !@mkdir($dest, 0755, true)) {
        throw new RuntimeException('uploads/customers ফোল্ডার তৈরি হয়নি।');
    }
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive নেই।');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('ZIP খোলা যায়নি।');
    }
    $saved = 0;
    $skipped = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false || str_ends_with($name, '/')) {
            continue;
        }
        $base = basename(str_replace('\\', '/', $name));
        if (!preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp|gif)$/i', $base)) {
            $skipped++;
            continue;
        }
        $stream = $zip->getStream($name);
        if ($stream === false) {
            $skipped++;
            continue;
        }
        $data = stream_get_contents($stream);
        fclose($stream);
        if ($data === false || $data === '' || strlen($data) < 24) {
            $skipped++;
            continue;
        }
        if (@file_put_contents($dest . '/' . $base, $data) === false) {
            $skipped++;
            continue;
        }
        $saved++;
    }
    $zip->close();
    return ['saved' => $saved, 'skipped' => $skipped, 'dir' => $dest];
}

function ame_relink_customer_photos(mysqli $db): array
{
    $rows = ame_legacy_photo_rows($db);
    $byCode = [];
    $res = $db->query('SELECT id, customer_code, photo FROM customers');
    while ($res && ($c = $res->fetch_assoc())) {
        $key = mb_strtolower(trim((string)$c['customer_code']), 'UTF-8');
        if ($key !== '') {
            $byCode[$key] = $c;
        }
    }

    $upd = $db->prepare('UPDATE customers SET photo = ? WHERE id = ? LIMIT 1');
    if (!$upd) {
        throw new RuntimeException('আপডেট স্টেটমেন্ট হয়নি: ' . $db->error);
    }

    $linked = 0;
    $already = 0;
    $missing_file = 0;
    $missing_customer = 0;

    foreach ($rows as $row) {
        $file = $row['file'];
        if (!ame_photo_file_present($file)) {
            $missing_file++;
            continue;
        }
        $keys = [
            mb_strtolower('MAE-C-' . (int)$row['id'], 'UTF-8'),
            mb_strtolower(trim((string)$row['code']), 'UTF-8'),
        ];
        $cust = null;
        foreach ($keys as $k) {
            if ($k !== '' && isset($byCode[$k])) {
                $cust = $byCode[$k];
                break;
            }
        }
        if ($cust === null) {
            $missing_customer++;
            continue;
        }
        $current = trim((string)($cust['photo'] ?? ''));
        if ($current === $file || basename($current) === $file) {
            $already++;
            continue;
        }
        $id = (int)$cust['id'];
        $upd->bind_param('si', $file, $id);
        $upd->execute();
        if ($upd->affected_rows >= 0) {
            $linked++;
            $cust['photo'] = $file;
            foreach ($keys as $k) {
                if ($k !== '' && isset($byCode[$k])) {
                    $byCode[$k]['photo'] = $file;
                }
            }
        }
    }
    $upd->close();

    return [
        'mapped' => count($rows),
        'linked' => $linked,
        'already' => $already,
        'missing_file' => $missing_file,
        'missing_customer' => $missing_customer,
    ];
}
