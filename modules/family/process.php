<?php
require_once __DIR__ . '/_boot.php';
require_post();
csrf_verify();

$do = (string)($_POST['do'] ?? '');
$back = (string)($_POST['back'] ?? '/modules/family/index.php');
if ($back === '' || !str_starts_with($back, '/modules/family/')) {
    $back = '/modules/family/index.php';
}

function fam_go(string $to, string $ok = '', string $err = ''): void
{
    if ($ok !== '') {
        $_SESSION['fam_ok'] = $ok;
    }
    if ($err !== '') {
        $_SESSION['fam_err'] = $err;
    }
    header('Location: ' . $to);
    exit;
}

function fam_post(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

try {
    $db = fam_db();

    if ($do === 'group_save') {
        fam_guard_write();
        $name = fam_post('name');
        $id = fam_int($_POST['id'] ?? 0);
        if ($name === '') {
            fam_go($back, '', 'গ্রুপের নাম লাগবে।');
        }
        if ($id > 0) {
            $st = $db->prepare('UPDATE family_groups SET name=? WHERE id=? AND is_system=0');
            $st->bind_param('si', $name, $id);
        } else {
            $st = $db->prepare('INSERT INTO family_groups (name, is_system) VALUES (?,0)');
            $st->bind_param('s', $name);
        }
        $st->execute();
        $st->close();
        fam_go($back, 'গ্রুপ সংরক্ষণ হয়েছে।');
    }

    if ($do === 'group_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $db->query('UPDATE family_contacts SET group_id=NULL WHERE group_id=' . $id);
        $st = $db->prepare('DELETE FROM family_groups WHERE id=? AND is_system=0');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'গ্রুপ মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'contact_save') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $gid = fam_id($_POST['group_id'] ?? 0);
        $name = fam_post('name');
        if ($name === '') {
            fam_go($back, '', 'নাম লাগবে।');
        }
        $fields = [$gid, $name, fam_post('mobile'), fam_post('alt_mobile'), fam_post('address'), fam_post('email'), fam_post('profession'), fam_post('organization'), fam_post('relation_note'), fam_post('notes')];
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_contacts SET group_id=NULLIF(?,0), name=?, mobile=?, alt_mobile=?, address=?, email=?, profession=?, organization=?, relation_note=?, notes=? WHERE id=?', 'isssssssssi', array_merge($fields, [$id]));
        } else {
            $st = fam_run($db, 'INSERT INTO family_contacts (group_id,name,mobile,alt_mobile,address,email,profession,organization,relation_note,notes) VALUES (NULLIF(?,0),?,?,?,?,?,?,?,?,?)', 'isssssssss', $fields);
            if ((int)$db->insert_id < 1) {
                $st->close();
                fam_go($back, '', 'পরিচিত সংরক্ষণ হয়নি। আবার চেষ্টা করুন।');
            }
        }
        $st->close();
        fam_go($back, 'পরিচিত সংরক্ষণ হয়েছে।');
    }

    if ($do === 'contact_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_contacts WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'পরিচিত মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'member_save') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $name = fam_post('name');
        if ($name === '') {
            fam_go($back, '', 'নাম লাগবে।');
        }
        $dob = fam_date_sql($_POST['birth_date'] ?? '');
        $rel = fam_post('relation_name');
        $mob = fam_post('mobile');
        $addr = fam_post('address');
        $notes = fam_post('notes');
        $en = fam_post('emergency_name');
        $ep = fam_post('emergency_phone');
        $imp = fam_post('important_info');
        $role = fam_role_from_relation($rel);
        $active = 1;
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_members SET name=?, relation_name=?, birth_date=NULLIF(?,\'\'), mobile=?, address=?, notes=?, emergency_name=?, emergency_phone=?, important_info=?, role=?, is_active=? WHERE id=?', 'sssssssssisi', [$name, $rel, $dob, $mob, $addr, $notes, $en, $ep, $imp, $role, $active, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_members (name,relation_name,birth_date,mobile,address,notes,emergency_name,emergency_phone,important_info,role,is_active) VALUES (?,?,NULLIF(?,\'\'),?,?,?,?,?,?,?,?)', 'ssssssssssi', [$name, $rel, $dob, $mob, $addr, $notes, $en, $ep, $imp, $role, $active]);
        }
        $mid = $id > 0 ? $id : (int)$db->insert_id;
        $st->close();
        if ($mid < 1) {
            fam_go($back, '', 'সদস্য সংরক্ষণ হয়নি। আবার চেষ্টা করুন।');
        }
        if ($dob) {
            try {
                $nb = fam_next_birthday($dob);
                if ($nb) {
                    fam_sync_event('birthday', 'জন্মদিন: ' . $name, $nb['next'], $mid, 'member', $mid);
                }
            } catch (Throwable $syncErr) {
                error_log('family member birthday sync: ' . $syncErr->getMessage());
            }
        }
        $dest = (str_contains($back, 'members.php') && $id < 1)
            ? $back
            : '/modules/family/member.php?id=' . $mid;
        fam_go($dest, 'সদস্য সংরক্ষণ হয়েছে।');
    }

    if ($do === 'member_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_members WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go('/modules/family/members.php', 'সদস্য মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'doctor_save') {
        fam_guard_write('health');
        $id = fam_int($_POST['id'] ?? 0);
        $mid = fam_id($_POST['member_id'] ?? 0);
        $name = fam_post('name');
        if ($name === '') {
            fam_go($back, '', 'ডাক্তারের নাম লাগবে।');
        }
        $spec = fam_post('specialist');
        $hosp = fam_post('hospital');
        $mob = fam_post('mobile');
        $addr = fam_post('address');
        $ch = fam_post('chamber_time');
        $ad = fam_date_sql($_POST['appointment_date'] ?? '');
        $fee = fam_money($_POST['visit_fee'] ?? 0);
        $nv = fam_date_sql($_POST['next_visit'] ?? '');
        $notes = fam_post('notes');
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_doctors SET member_id=NULLIF(?,0), name=?, specialist=?, hospital=?, mobile=?, address=?, chamber_time=?, appointment_date=NULLIF(?,\'\'), visit_fee=?, next_visit=NULLIF(?,\'\'), notes=? WHERE id=?', 'isssssssdssi', [$mid, $name, $spec, $hosp, $mob, $addr, $ch, $ad, $fee, $nv, $notes, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_doctors (member_id,name,specialist,hospital,mobile,address,chamber_time,appointment_date,visit_fee,next_visit,notes) VALUES (NULLIF(?,0),?,?,?,?,?,?,NULLIF(?,\'\'),?,NULLIF(?,\'\'),?)', 'isssssssdss', [$mid, $name, $spec, $hosp, $mob, $addr, $ch, $ad, $fee, $nv, $notes]);
        }
        $did = $id > 0 ? $id : (int)$db->insert_id;
        $st->close();
        $when = $ad ?: $nv;
        if ($when) {
            fam_sync_event('appointment', 'ডাক্তার: ' . $name, $when, $mid, 'doctor', $did, $ch);
        }
        fam_go($back, 'ডাক্তার সংরক্ষণ হয়েছে। ক্যালেন্ডার ও রিমাইন্ডারেও দেখাবে।');
    }

    if ($do === 'doctor_delete') {
        fam_guard_write('health');
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_doctors WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'ডাক্তার মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'medicine_save') {
        fam_guard_write('health');
        $id = fam_int($_POST['id'] ?? 0);
        $mid = fam_id($_POST['member_id'] ?? 0);
        $did = fam_id($_POST['doctor_id'] ?? 0);
        $name = fam_post('name');
        if ($name === '') {
            fam_go($back, '', 'ওষুধের নাম লাগবে।');
        }
        $gen = fam_post('generic_name');
        $co = fam_post('company');
        $str = fam_post('strength');
        $bq = fam_money($_POST['bought_qty'] ?? 0);
        $stq = fam_money($_POST['stock'] ?? 0);
        $when = fam_post('when_to_take');
        $tpd = max(1, fam_int($_POST['times_per_day'] ?? 1));
        $days = fam_int($_POST['days_for'] ?? 0);
        $rx = fam_date_sql($_POST['rx_date'] ?? '');
        $rf = fam_date_sql($_POST['refill_date'] ?? '');
        $ex = fam_date_sql($_POST['expiry_date'] ?? '');
        $notes = fam_post('notes');
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_medicines SET member_id=NULLIF(?,0), doctor_id=NULLIF(?,0), name=?, generic_name=?, company=?, strength=?, bought_qty=?, stock=?, when_to_take=?, times_per_day=?, days_for=?, rx_date=NULLIF(?,\'\'), refill_date=NULLIF(?,\'\'), expiry_date=NULLIF(?,\'\'), notes=? WHERE id=?', 'iissssddsiiisssi', [$mid, $did, $name, $gen, $co, $str, $bq, $stq, $when, $tpd, $days, $rx, $rf, $ex, $notes, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_medicines (member_id,doctor_id,name,generic_name,company,strength,bought_qty,stock,when_to_take,times_per_day,days_for,rx_date,refill_date,expiry_date,notes) VALUES (NULLIF(?,0),NULLIF(?,0),?,?,?,?,?,?,?,?,?,NULLIF(?,\'\'),NULLIF(?,\'\'),NULLIF(?,\'\'),?)', 'iissssddsiiisss', [$mid, $did, $name, $gen, $co, $str, $bq, $stq, $when, $tpd, $days, $rx, $rf, $ex, $notes]);
        }
        $meid = $id > 0 ? $id : (int)$db->insert_id;
        $st->close();
        if ($rf) {
            fam_sync_event('refill', 'ওষুধ কিনুন: ' . $name, $rf, $mid, 'medicine', $meid);
        }
        fam_go($back, 'ওষুধ সংরক্ষণ হয়েছে।');
    }

    if ($do === 'medicine_delete') {
        fam_guard_write('health');
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_medicines WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'ওষুধ মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'med_to_shop') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('SELECT * FROM family_medicines WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $m = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$m) {
            fam_go($back, '', 'ওষুধ পাওয়া যায়নি।');
        }
        $name = $m['name'];
        $mid = (int)$m['member_id'];
        $qty = max(1, (float)$m['times_per_day'] * 7);
        $cat = 'ওষুধ';
        $pr = 'জরুরি';
        $st = $db->prepare('INSERT INTO family_shopping (member_id,name,category,qty,priority,notes) VALUES (?,?,?,?,?,?)');
        $note = 'ওষুধের স্টক কম — স্বয়ংক্রিয় যোগ';
        $st->bind_param('isssss', $mid, $name, $cat, $qty, $pr, $note);
        $st->execute();
        $st->close();
        fam_go('/modules/family/shopping.php', 'বাজার তালিকায় ওষুধ যোগ হয়েছে।');
    }

    if ($do === 'med_buy_save') {
        fam_guard_write('money');
        $meid = fam_id($_POST['medicine_id'] ?? 0);
        $mid = fam_id($_POST['member_id'] ?? 0);
        $name = fam_post('name');
        $qty = fam_money($_POST['qty'] ?? 0);
        $price = fam_money($_POST['price'] ?? 0);
        $dt = fam_date_sql($_POST['buy_date'] ?? '') ?: date('Y-m-d');
        $ph = fam_post('pharmacy');
        $notes = fam_post('notes');
        $file = fam_upload('invoice_file') ?: '';
        if ($name === '' || $qty <= 0) {
            fam_go($back, '', 'নাম ও পরিমাণ লাগবে।');
        }
        $st = fam_run($db, 'INSERT INTO family_med_buys (medicine_id,member_id,buy_date,name,qty,price,pharmacy,invoice_file,notes) VALUES (NULLIF(?,0),NULLIF(?,0),?,?,?,?,?,?,?)', 'iissddsss', [$meid, $mid, $dt, $name, $qty, $price, $ph, $file, $notes]);
        $st->close();
        if ($meid) {
            $st = $db->prepare('UPDATE family_medicines SET stock=stock+?, bought_qty=bought_qty+? WHERE id=?');
            $st->bind_param('ddi', $qty, $qty, $meid);
            $st->execute();
            $st->close();
        }
        $total = $qty * $price;
        if ($total <= 0) {
            $total = $price;
        }
        fam_add_expense($dt, 'ওষুধ', $total, 'ওষুধ কেনা: ' . $name, $mid, $ph);
        if ($file) {
            $kind = 'বিল';
            $title = 'ওষুধ রসিদ: ' . $name;
            $st = fam_run($db, 'INSERT INTO family_documents (member_id,title,kind,file_name,notes) VALUES (NULLIF(?,0),?,?,?,?)', 'issss', [$mid, $title, $kind, $file, $ph]);
            $st->close();
        }
        fam_go($back, 'ওষুধ কেনা এন্ট্রি হয়েছে — স্টক ও খরচ আপডেট।');
    }

    if ($do === 'book_save') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $mid = fam_id($_POST['member_id'] ?? 0);
        $title = fam_post('title');
        if ($title === '') {
            fam_go($back, '', 'বইয়ের নাম লাগবে।');
        }
        $author = fam_post('author');
        $pub = fam_post('publisher');
        $sub = fam_post('subject');
        $lang = fam_post('language');
        $copies = max(1, fam_int($_POST['copies'] ?? 1));
        $bd = fam_date_sql($_POST['buy_date'] ?? '');
        $price = fam_money($_POST['price'] ?? 0);
        $src = fam_post('source');
        $loc = fam_post('location_note');
        $read = isset($_POST['is_read']) ? 1 : 0;
        $notes = fam_post('notes');
        $isNew = $id < 1;
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_books SET member_id=NULLIF(?,0), title=?, author=?, publisher=?, subject=?, language=?, copies=?, buy_date=NULLIF(?,\'\'), price=?, source=?, location_note=?, is_read=?, notes=? WHERE id=?', 'isssssisdssisi', [$mid, $title, $author, $pub, $sub, $lang, $copies, $bd, $price, $src, $loc, $read, $notes, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_books (member_id,title,author,publisher,subject,language,copies,buy_date,price,source,location_note,is_read,notes) VALUES (NULLIF(?,0),?,?,?,?,?,?,NULLIF(?,\'\'),?,?,?,?,?)', 'isssssisdssis', [$mid, $title, $author, $pub, $sub, $lang, $copies, $bd, $price, $src, $loc, $read, $notes]);
        }
        $bid = $id > 0 ? $id : (int)$db->insert_id;
        $st->close();
        if ($isNew && $price > 0) {
            fam_add_expense($bd ?: date('Y-m-d'), 'বই', $price, 'বই/কিতাব: ' . $title, $mid, $src);
        }
        fam_go($back, $isNew && $price > 0 ? 'বই লাইব্রেরি ও খরচ — দুই জায়গায় রেকর্ড হয়েছে।' : 'বই সংরক্ষণ হয়েছে।');
    }

    if ($do === 'book_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('UPDATE family_books SET archived_at=NOW() WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, ame_l('বই আর্কাইভ হয়েছে। ফাইল মুছে যায়নি।', 'The book was archived. The file was kept.'));
    }

    if ($do === 'shop_save') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $mid = fam_id($_POST['member_id'] ?? 0);
        $name = fam_post('name');
        if ($name === '') {
            fam_go($back, '', 'পণ্যের নাম লাগবে।');
        }
        $cat = fam_post('category');
        $qty = fam_post('qty');
        $est = fam_money($_POST['est_price'] ?? 0);
        $pr = fam_post('priority') ?: 'মাঝারি';
        $buyer = fam_post('buyer');
        $shop = fam_post('shop_name');
        $bought = isset($_POST['is_bought']) ? 1 : 0;
        $bd = fam_date_sql($_POST['buy_date'] ?? '');
        $notes = fam_post('notes');
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_shopping SET member_id=NULLIF(?,0), name=?, category=?, qty=?, est_price=?, priority=?, buyer=?, shop_name=?, is_bought=?, buy_date=NULLIF(?,\'\'), notes=? WHERE id=?', 'isssdsssissi', [$mid, $name, $cat, $qty, $est, $pr, $buyer, $shop, $bought, $bd, $notes, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_shopping (member_id,name,category,qty,est_price,priority,buyer,shop_name,is_bought,buy_date,notes) VALUES (NULLIF(?,0),?,?,?,?,?,?,?,?,NULLIF(?,\'\'),?)', 'isssdsssiss', [$mid, $name, $cat, $qty, $est, $pr, $buyer, $shop, $bought, $bd, $notes]);
        }
        $sid = $id > 0 ? $id : (int)$db->insert_id;
        $st->close();
        if ($pr === 'জরুরি' && !$bought) {
            fam_sync_event('shopping', 'বাজার: ' . $name, $bd ?: date('Y-m-d', strtotime('+1 day')), $mid, 'shopping', $sid);
        }
        fam_go($back, 'বাজার তালিকা আপডেট হয়েছে।');
    }

    if ($do === 'shop_bought') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $today = date('Y-m-d');
        $st = $db->prepare('UPDATE family_shopping SET is_bought=1, buy_date=? WHERE id=?');
        $st->bind_param('si', $today, $id);
        $st->execute();
        $st->close();
        $row = fam_one('SELECT * FROM family_shopping WHERE id=' . $id);
        if ($row && (float)$row['est_price'] > 0) {
            fam_add_expense($today, 'বাজার', (float)$row['est_price'], 'বাজার: ' . $row['name'], fam_null_int($row['member_id']), $row['shop_name'] ?? '');
        }
        fam_go($back, 'কেনা হয়েছে — খরচেও এন্ট্রি হয়েছে।');
    }

    if ($do === 'shop_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_shopping WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'বাজার আইটেম মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'inv_save') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $name = fam_post('name');
        if ($name === '') {
            fam_go($back, '', 'নাম লাগবে।');
        }
        $cat = fam_post('category');
        $qty = fam_post('qty') ?: '1';
        $bd = fam_date_sql($_POST['buy_date'] ?? '');
        $price = fam_money($_POST['price'] ?? 0);
        $cond = fam_post('condition_note') ?: 'ভালো';
        $loc = fam_post('location_note');
        $warr = fam_date_sql($_POST['warranty_until'] ?? '');
        $md = fam_date_sql($_POST['maintain_date'] ?? '');
        $notes = fam_post('notes');
        $file = fam_upload('receipt_file') ?: '';
        if ($id > 0) {
            if ($file) {
                $st = fam_run($db, 'UPDATE family_inventory SET name=?, category=?, qty=?, buy_date=NULLIF(?,\'\'), price=?, condition_note=?, location_note=?, warranty_until=NULLIF(?,\'\'), receipt_file=?, maintain_date=NULLIF(?,\'\'), notes=? WHERE id=?', 'ssssdssssssi', [$name, $cat, $qty, $bd, $price, $cond, $loc, $warr, $file, $md, $notes, $id]);
            } else {
                $st = fam_run($db, 'UPDATE family_inventory SET name=?, category=?, qty=?, buy_date=NULLIF(?,\'\'), price=?, condition_note=?, location_note=?, warranty_until=NULLIF(?,\'\'), maintain_date=NULLIF(?,\'\'), notes=? WHERE id=?', 'ssssdsssssi', [$name, $cat, $qty, $bd, $price, $cond, $loc, $warr, $md, $notes, $id]);
            }
        } else {
            $st = fam_run($db, 'INSERT INTO family_inventory (name,category,qty,buy_date,price,condition_note,location_note,warranty_until,receipt_file,maintain_date,notes) VALUES (?,?,?,NULLIF(?,\'\'),?,?,?,NULLIF(?,\'\'),?,NULLIF(?,\'\'),?)', 'ssssdssssss', [$name, $cat, $qty, $bd, $price, $cond, $loc, $warr, $file, $md, $notes]);
        }
        $iid = $id > 0 ? $id : (int)$db->insert_id;
        $st->close();
        if ($warr) {
            fam_sync_event('warranty', 'ওয়ারেন্টি: ' . $name, $warr, null, 'inventory', $iid);
        }
        if ($md) {
            fam_sync_event('other', 'রক্ষণাবেক্ষণ: ' . $name, $md, null, 'inventory_m', $iid);
        }
        if ($file) {
            $kind = 'warranty';
            $title = 'রসিদ: ' . $name;
            $st = $db->prepare('INSERT INTO family_documents (title,kind,file_name) VALUES (?,?,?)');
            $st->bind_param('sss', $title, $kind, $file);
            $st->execute();
            $st->close();
        }
        fam_go($back, 'ইনভেন্টরি সংরক্ষণ হয়েছে।');
    }

    if ($do === 'inv_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_inventory WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'ইনভেন্টরি মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'expense_save') {
        fam_guard_write('money');
        $id = fam_int($_POST['id'] ?? 0);
        $dt = fam_date_sql($_POST['exp_date'] ?? '') ?: date('Y-m-d');
        $cat = fam_post('category') ?: 'অন্যান্য';
        $amt = fam_money($_POST['amount'] ?? 0);
        $by = fam_post('spent_by');
        $pur = fam_post('purpose');
        $pay = fam_post('pay_method') ?: 'নগদ';
        $notes = fam_post('notes');
        $mid = fam_id($_POST['member_id'] ?? 0);
        if ($amt <= 0) {
            fam_go($back, '', 'অ্যামাউন্ট লাগবে।');
        }
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_expenses SET member_id=NULLIF(?,0), exp_date=?, category=?, amount=?, spent_by=?, purpose=?, pay_method=?, notes=? WHERE id=?', 'issdssssi', [$mid, $dt, $cat, $amt, $by, $pur, $pay, $notes, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_expenses (member_id,exp_date,category,amount,spent_by,purpose,pay_method,notes) VALUES (NULLIF(?,0),?,?,?,?,?,?,?)', 'issdssss', [$mid, $dt, $cat, $amt, $by, $pur, $pay, $notes]);
        }
        $st->close();
        if (function_exists('log_activity')) {
            $expId = $id > 0 ? $id : (int)$db->insert_id;
            log_activity(
                $id > 0 ? 'Family Expense Updated' : 'Family Expense Added',
                'Family',
                $cat . ' ৳' . number_format($amt, 2),
                [
                    'action_type' => $id > 0 ? 'edit' : 'add',
                    'entity_type' => 'family_expense',
                    'entity_id' => (string)$expId,
                    'amount_total' => (float)$amt,
                ]
            );
        }
        fam_go($back, 'খরচ এন্ট্রি হয়েছে।');
    }

    if ($do === 'expense_delete') {
        fam_guard_write('money');
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_expenses WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        if (function_exists('log_activity')) {
            log_activity('Family Expense Deleted', 'Family', '#' . $id, [
                'action_type' => 'delete',
                'entity_type' => 'family_expense',
                'entity_id' => (string)$id,
            ]);
        }
        fam_go($back, 'খরচ মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'event_save') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $title = fam_post('title');
        $kind = fam_post('kind') ?: 'other';
        $dt = fam_date_sql($_POST['event_date'] ?? '');
        $mid = fam_id($_POST['member_id'] ?? 0);
        $notes = fam_post('notes');
        if ($title === '' || $dt === '') {
            fam_go($back, '', 'শিরোনাম ও তারিখ লাগবে।');
        }
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_events SET title=?, kind=?, event_date=?, member_id=NULLIF(?,0), notes=? WHERE id=?', 'sssisi', [$title, $kind, $dt, $mid, $notes, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_events (title,kind,event_date,member_id,notes) VALUES (?,?,?,NULLIF(?,0),?)', 'sssis', [$title, $kind, $dt, $mid, $notes]);
        }
        $st->close();
        fam_go($back, 'তারিখ ক্যালেন্ডার ও রিমাইন্ডারে যোগ হয়েছে।');
    }

    if ($do === 'event_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_events WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'ইভেন্ট মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'note_save') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $title = fam_post('title') ?: 'নোট';
        $body = fam_post('body');
        $mid = fam_id($_POST['member_id'] ?? 0);
        $pin = isset($_POST['is_pinned']) ? 1 : 0;
        if ($id > 0) {
            $st = fam_run($db, 'UPDATE family_notes SET member_id=NULLIF(?,0), title=?, body=?, is_pinned=? WHERE id=?', 'issii', [$mid, $title, $body, $pin, $id]);
        } else {
            $st = fam_run($db, 'INSERT INTO family_notes (member_id,title,body,is_pinned) VALUES (NULLIF(?,0),?,?,?)', 'issi', [$mid, $title, $body, $pin]);
        }
        $st->close();
        fam_go($back, 'নোট সংরক্ষণ হয়েছে।');
    }

    if ($do === 'note_pin') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $db->query('UPDATE family_notes SET is_pinned = 1-is_pinned WHERE id=' . $id);
        fam_go($back, 'পিন আপডেট হয়েছে।');
    }

    if ($do === 'note_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_notes WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'নোট মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'rx_save') {
        fam_guard_write('health');
        $mid = fam_id($_POST['member_id'] ?? 0);
        $did = fam_id($_POST['doctor_id'] ?? 0);
        $rxDate = fam_date_sql($_POST['rx_date'] ?? '') ?: date('Y-m-d');
        $notes = fam_post('notes');
        $file = fam_upload('rx_file', true);
        $docName = '';
        if ($did) {
            $drow = fam_one('SELECT name FROM family_doctors WHERE id=' . (int)$did);
            $docName = (string)($drow['name'] ?? '');
        }
        $title = fam_post('title');
        if ($title === '') {
            $title = 'প্রেসক্রিপশন' . ($docName !== '' ? ' · ' . $docName : '') . ' · ' . $rxDate;
        }
        $kind = 'প্রেসক্রিপশন';
        $st = fam_run($db, 'INSERT INTO family_documents (member_id,doctor_id,title,kind,file_name,notes,rx_date) VALUES (NULLIF(?,0),NULLIF(?,0),?,?,?,?,NULLIF(?,\'\'))', 'iisssss', [$mid, $did, $title, $kind, $file, $notes, $rxDate]);
        $docId = (int)$db->insert_id;
        $st->close();
        $names = $_POST['med_name'] ?? [];
        $strengths = $_POST['med_strength'] ?? [];
        $whens = $_POST['med_when'] ?? [];
        $times = $_POST['med_times'] ?? [];
        $dayList = $_POST['med_days'] ?? [];
        if (!is_array($names)) {
            $names = [$names];
        }
        $added = 0;
        foreach ($names as $i => $rawName) {
            $medName = trim((string)$rawName);
            if ($medName === '' || $docId < 1) {
                continue;
            }
            $strength = trim((string)($strengths[$i] ?? ''));
            $when = trim((string)($whens[$i] ?? ''));
            $tpd = max(1, (int)($times[$i] ?? 1));
            $daysFor = max(0, (int)($dayList[$i] ?? 0));
            $refill = $daysFor > 0 ? date('Y-m-d', strtotime($rxDate . ' +' . $daysFor . ' days')) : '';
            $empty = '';
            $zero = 0.0;
            $st = fam_run($db, 'INSERT INTO family_medicines (document_id,member_id,doctor_id,name,generic_name,company,strength,bought_qty,stock,when_to_take,times_per_day,days_for,rx_date,refill_date,expiry_date,notes) VALUES (?,NULLIF(?,0),NULLIF(?,0),?,?,?,?,?,?,?,?,NULLIF(?,\'\'),NULLIF(?,\'\'),NULLIF(?,\'\'),?)', 'iiissssddsiiisss', [$docId, $mid, $did, $medName, $empty, $empty, $strength, $zero, $zero, $when, $tpd, $daysFor, $rxDate, $refill, $empty, $notes]);
            $meid = (int)$db->insert_id;
            $st->close();
            $st = fam_run($db, 'INSERT INTO family_rx_lines (document_id,medicine_id,name,strength,when_to_take,times_per_day,days_for) VALUES (?,?,?,?,?,?,?)', 'iisssii', [$docId, $meid, $medName, $strength, $when, $tpd, $daysFor]);
            $st->close();
            if ($refill !== '') {
                fam_sync_event('refill', ame_l('ওষুধ কিনুন: ', 'Buy medicine: ') . $medName, $refill, $mid, 'medicine', $meid);
            }
            $added++;
        }
        $msg = $added > 0
            ? ame_l('প্রেসক্রিপশন ও ' . $added . 'টি ওষুধ সেভ হয়েছে।', 'Prescription and ' . $added . ' medicines saved.')
            : ame_l('প্রেসক্রিপশনের ছবি সেভ হয়েছে।', 'The prescription photo was saved.');
        fam_go($back, $msg);
    }

    if ($do === 'rx_archive') {
        fam_guard_write('health');
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare("UPDATE family_documents SET archived_at=NOW() WHERE id=? AND kind='প্রেসক্রিপশন'");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, ame_l('প্রেসক্রিপশন আর্কাইভ হয়েছে। ছবি মুছে যায়নি।', 'Prescription archived. The photo was kept.'));
    }

    if ($do === 'doc_save') {
        fam_guard_write();
        $mid = fam_id($_POST['member_id'] ?? 0);
        $did = fam_id($_POST['doctor_id'] ?? 0);
        $title = fam_post('title');
        $kind = fam_post('kind') ?: 'অন্যান্য';
        $notes = fam_post('notes');
        $file = fam_upload('file_name') ?: '';
        if ($title === '') {
            fam_go($back, '', 'শিরোনাম লাগবে।');
        }
        $st = fam_run($db, 'INSERT INTO family_documents (member_id,doctor_id,title,kind,file_name,notes) VALUES (NULLIF(?,0),NULLIF(?,0),?,?,?,?)', 'iissss', [$mid, $did, $title, $kind, $file, $notes]);
        $st->close();
        fam_go($back, 'ডকুমেন্ট রেকর্ড হয়েছে।');
    }

    if ($do === 'doc_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $row = fam_one('SELECT file_name FROM family_documents WHERE id=' . $id);
        if ($row && !empty($row['file_name'])) {
            $p = dirname(__DIR__, 2) . '/uploads/family/' . basename($row['file_name']);
            if (is_file($p)) {
                @unlink($p);
            }
        }
        $st = $db->prepare('DELETE FROM family_documents WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'ডকুমেন্ট মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'bazaar_line_save') {
        fam_guard_write();
        $ym = fam_bazaar_month_norm(fam_post('ym') ?: date('Y-m'));
        $demand = fam_bazaar_demand($db, $ym);
        $did = (int) ($demand['id'] ?? 0);
        if ($did <= 0) {
            fam_go($back, '', 'এই মাসের চাহিদা খোলা যায়নি।');
        }
        $id = fam_int($_POST['id'] ?? 0);
        $names = $_POST['name'] ?? '';
        if (!is_array($names)) {
            $names = [$names];
            $catsIn = [fam_post('category')];
            $unitsIn = [fam_post('unit')];
            $qtys = [fam_post('qty')];
            $ests = [$_POST['est_price'] ?? 0];
            $notesIn = [fam_post('notes')];
        } else {
            $catsIn = is_array($_POST['category'] ?? null) ? $_POST['category'] : [];
            $unitsIn = is_array($_POST['unit'] ?? null) ? $_POST['unit'] : [];
            $qtys = is_array($_POST['qty'] ?? null) ? $_POST['qty'] : [];
            $ests = is_array($_POST['est_price'] ?? null) ? $_POST['est_price'] : [];
            $notesIn = is_array($_POST['notes'] ?? null) ? $_POST['notes'] : [];
        }
        if ($id > 0) {
            $name = trim((string) ($names[0] ?? ''));
            if ($name === '') {
                fam_go($back, '', 'পণ্যের নাম লাগবে।');
            }
            $cat = fam_bazaar_norm_cat((string) ($catsIn[0] ?? ''));
            $unit = trim((string) ($unitsIn[0] ?? ''));
            if (!fam_bazaar_unit_ok($unit)) {
                $unit = 'কেজি';
            }
            $qty = trim((string) ($qtys[0] ?? ''));
            $est = fam_money($ests[0] ?? 0);
            $notes = trim((string) ($notesIn[0] ?? ''));
            $st = fam_run(
                $db,
                'UPDATE family_bazaar_lines SET category=?, name=?, qty=?, unit=?, est_price=?, notes=? WHERE id=? AND demand_id=?',
                'ssssdsii',
                [$cat, $name, $qty, $unit, $est, $notes, $id, $did]
            );
            $st->close();
            fam_go($back, 'বাজার চাহিদা রাখা হয়েছে।');
        }
        $saved = 0;
        foreach ($names as $i => $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }
            $cat = fam_bazaar_norm_cat((string) ($catsIn[$i] ?? ''));
            $unit = trim((string) ($unitsIn[$i] ?? ''));
            if (!fam_bazaar_unit_ok($unit)) {
                $unit = 'কেজি';
            }
            $qty = trim((string) ($qtys[$i] ?? ''));
            $est = fam_money($ests[$i] ?? 0);
            $notes = trim((string) ($notesIn[$i] ?? ''));
            $st = fam_run(
                $db,
                'INSERT INTO family_bazaar_lines (demand_id,category,name,qty,unit,est_price,notes) VALUES (?,?,?,?,?,?,?)',
                'issssds',
                [$did, $cat, $name, $qty, $unit, $est, $notes]
            );
            $st->close();
            $saved++;
        }
        if ($saved < 1) {
            fam_go($back, '', 'অন্তত একটি পণ্যের নাম লাগবে।');
        }
        fam_go($back, $saved . 'টি পণ্য এই মাসের মেমোতে রাখা হয়েছে।');
    }

    if ($do === 'bazaar_seed_house') {
        fam_guard_write();
        $ym = fam_bazaar_month_norm(fam_post('ym') ?: date('Y-m'));
        $demand = fam_bazaar_demand($db, $ym);
        $n = fam_bazaar_seed_house($db, (int) ($demand['id'] ?? 0));
        if ($n < 1) {
            fam_go($back, 'ঘরের তালিকার পণ্য আগেই এই মেমোতে আছে।');
        }
        fam_go($back, $n . 'টি পণ্য এই মাসের মেমোতে রাখা হয়েছে।');
    }

    if ($do === 'bazaar_line_bought') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $paid = fam_money($_POST['paid_price'] ?? 0);
        $row = fam_one('SELECT * FROM family_bazaar_lines WHERE id=' . $id);
        if (!$row) {
            fam_go($back, '', 'আইটেম পাওয়া যায়নি।');
        }
        if (!empty($row['is_bought'])) {
            fam_go($back, '', 'এই আইটেম আগেই কেনা হয়েছে।');
        }
        if ((float) $paid < 0.009) {
            $paid = (float) $row['est_price'];
        }
        $today = date('Y-m-d');
        $st = fam_run(
            $db,
            'UPDATE family_bazaar_lines SET is_bought=1, paid_price=?, buy_date=? WHERE id=?',
            'dsi',
            [$paid, $today, $id]
        );
        $st->close();
        if ($paid > 0.009) {
            $purpose = (string) $row['category'] . ': ' . (string) $row['name'];
            fam_add_expense($today, 'বাজার', $paid, $purpose, null, 'বাজার চাহিদা #' . $id);
        }
        fam_go($back, 'কেনা হয়েছে — খরচেও এন্ট্রি হয়েছে।');
    }

    if ($do === 'bazaar_line_delete') {
        fam_guard_write();
        $id = fam_int($_POST['id'] ?? 0);
        $st = $db->prepare('DELETE FROM family_bazaar_lines WHERE id=?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        fam_go($back, 'চাহিদা মুছে দেওয়া হয়েছে।');
    }

    if ($do === 'access_save') {
        if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
            fam_go($back, '', 'শুধু অ্যাডমিন অ্যাক্সেস বদলাতে পারে।');
        }
        $uid = fam_int($_POST['user_id'] ?? 0);
        $role = fam_post('role_key');
        if (!in_array($role, ['admin', 'member', 'limited', 'view'], true)) {
            $role = 'member';
        }
        $ch = isset($_POST['can_health']) ? 1 : 0;
        $cm = isset($_POST['can_money']) ? 1 : 0;
        $st = $db->prepare('INSERT INTO family_access (user_id,role_key,can_health,can_money) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE role_key=VALUES(role_key), can_health=VALUES(can_health), can_money=VALUES(can_money)');
        $st->bind_param('isii', $uid, $role, $ch, $cm);
        $st->execute();
        $st->close();
        fam_go($back, 'অ্যাক্সেস আপডেট হয়েছে।');
    }

    fam_go($back, '', 'অজানা কাজ।');
} catch (Throwable $e) {
    error_log('family process: ' . $e->getMessage());
    $msg = $e->getMessage();
    if ($msg === '' || preg_match('/SQL |prepare|execute|bind|mysqli/i', $msg)) {
        $msg = ame_l('সেভ হয়নি। আবার চেষ্টা করুন।', 'Could not save. Please try again.');
    }
    fam_go($back, '', $msg);
}
