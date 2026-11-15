<?php
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/party_purge.php';
require_once '../../includes/supplier_due_ui.php';
if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
$can_party_purge = function_exists('ame_party_purge_can') && ame_party_purge_can();

$id = (int)($_GET['id'] ?? 0);
$is_new = $id <= 0;
ame_supplier_schema_boot($db);
$sup = $is_new ? [] : ame_supplier_fetch($db, $id);
if (!$is_new && !$sup) {
    die('❌ সাপ্লায়ার খুঁজে পাওয়া যায়নি। <a href="list.php">তালিকায় ফিরে যান</a>');
}
$is_purged = !$is_new && ame_supplier_is_purged($sup);
if ($is_new && empty($sup['party_code'])) {
    $sup['party_code'] = ame_supplier_next_party_code($db);
}
$stats = $is_new ? null : ame_supplier_stats($db, $id);
$docs  = $is_new ? [] : ame_supplier_documents($db, $id);

function sv($sup, $key, $default = '')
{
    return htmlspecialchars((string)($sup[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

$flash_ok  = (string)($_GET['saved'] ?? '');
$flash_err = (string)($_GET['err'] ?? '');

include '../../partials/header.php';
if (!$is_new) {
    ame_supplier_due_print_assets();
}
?>
<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
.sup-profile-wrap { padding: 15px; max-width: 980px; margin: 0 auto; }
.sup-profile-head {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
    margin-bottom: 16px;
}
.sup-profile-head h3 { margin: 0; color: var(--dark-blue); }
.sup-back-link { color: var(--brand-accent); font-weight: 600; font-size: 13px; text-decoration: none; }

.sup-flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 13.5px; }
.sup-flash.ok { background: var(--brand-success-soft); color: var(--brand-success); }
.sup-flash.err { background: var(--brand-danger-soft); color: var(--brand-danger); }

.sup-tabbar {
    display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px;
    border-bottom: 2px solid var(--brand-border); padding-bottom: 0;
}
.sup-tab-btn {
    background: transparent; border: none; padding: 10px 16px; cursor: pointer;
    font-weight: 700; font-size: 13.5px; color: var(--brand-muted);
    border-bottom: 3px solid transparent; margin-bottom: -2px;
}
.sup-tab-btn.is-active { color: var(--brand-navy); border-bottom-color: var(--brand-accent); }
.sup-tab-btn:disabled { opacity: 0.45; cursor: not-allowed; }

.sup-tab-panel { display: none; }
.sup-tab-panel.is-active { display: block; }

.sup-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px 18px; }
.sup-field { margin-bottom: 4px; }
.sup-field label { margin-bottom: 5px; }
.sup-field textarea { min-height: 80px; resize: vertical; }
.sup-hint-note { font-size: 12px; color: var(--brand-muted); margin: -8px 0 14px; }

.sup-due-adjust-box {
    margin-top: 18px; padding: 16px; border-radius: var(--radius-md);
    background: var(--brand-warn-soft); border: 1px solid var(--brand-warn);
}
.sup-due-adjust-title { margin: 0 0 6px; font-weight: 800; color: var(--brand-warn-text); }

.sup-perf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 18px; }
.sup-perf-card { background: var(--brand-surface-soft); border: 1px solid var(--brand-border-soft); border-radius: var(--radius-md); padding: 14px; }
.sup-perf-card .l { font-size: 12px; color: var(--brand-muted); font-weight: 600; margin-bottom: 4px; }
.sup-perf-card .v { font-size: 18px; font-weight: 800; color: var(--brand-navy); }
.sup-rating-row { display:flex; align-items:center; gap: 14px; margin-bottom: 16px; flex-wrap: wrap; }
.sup-rating-row select { max-width: 160px; }

.sup-doc-row {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 0; border-bottom: 1px solid var(--brand-border);
}
.sup-doc-row:last-child { border-bottom: none; }
.sup-doc-name { font-weight: 700; color: var(--brand-navy); text-decoration: none; }
.sup-doc-meta { font-size: 12px; color: var(--brand-muted); }
.sup-doc-del { color: var(--brand-danger); text-decoration: none; font-size: 12.5px; font-weight: 700; }
.sup-doc-upload-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--brand-border); }
.sup-doc-upload-form input[type="text"] { flex: 1; min-width: 140px; }

.sup-footer-actions {
    display: flex; gap: 10px; margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--brand-border);
}
.sup-cancel-btn {
    padding: 12px 24px; border-radius: 10px; border: 1.5px solid var(--brand-border);
    background: var(--brand-white); color: var(--brand-muted) !important; font-weight: 700; text-decoration: none;
    display: inline-flex; align-items: center;
}

/* স্মরণিকা নীতি ২: এই পেজের লোডিং/স্পিনার (সাধারণ ঘোরানো রিং) সরিয়ে দোকানের লোগো ব্যবহার হচ্ছে */
.sup-profile-wrap .ame-wait-spin i {
    width: 30px !important; height: 30px !important;
    border: none !important; border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important; background-repeat: no-repeat !important; background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
</style>
<div class="sup-profile-wrap">
    <div class="sup-profile-head">
        <h3><?php
            if ($is_new) {
                echo '🤝 নতুন সাপ্লায়ার';
            } else {
                echo '🤝 সাপ্লায়ার প্রোফাইল: ' . sv($sup, 'name');
                $code_show = trim((string)($sup['party_code'] ?? ''));
                if ($code_show === '') {
                    $code_show = '#' . $id;
                }
                echo ' <small style="color:var(--brand-muted);font-weight:700;">নাম্বার ' . htmlspecialchars($code_show, ENT_QUOTES, 'UTF-8') . '</small>';
            }
        ?></h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <?php if (!$is_new): ?>
            <a href="statement.php?id=<?php echo (int)$id; ?>" class="sup-back-link">🏦 স্টেটমেন্ট</a>
            <a href="ledger.php?id=<?php echo (int)$id; ?>" class="sup-back-link">📒 লেজার</a>
            <a href="pay.php?id=<?php echo (int)$id; ?>" class="sup-back-link">💳 পেমেন্ট</a>
            <?php endif; ?>
            <a href="list.php" class="sup-back-link">⬅️ তালিকায় ফিরে যান</a>
        </div>
    </div>

    <?php if ($flash_ok === '1'): ?>
        <div class="sup-flash ok">✅ সাপ্লায়ারের তথ্য সংরক্ষণ করা হয়েছে।</div>
    <?php elseif ($flash_err !== ''): ?>
        <div class="sup-flash err">⚠️ <?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($is_purged): ?>
        <div class="sup-flash err">এই সাপ্লায়ার তালিকা থেকে চিরতরে সরানো। ক্রয়, পেমেন্ট ও লেজার আছে। এডিট যায় না — নতুন নাম্বার দিয়ে আবার যোগ করুন।</div>
    <?php endif; ?>

    <div class="card">
        <div class="sup-tabbar" role="tablist">
            <button type="button" class="sup-tab-btn is-active" data-sup-tab="basic">সাধারণ তথ্য</button>
            <button type="button" class="sup-tab-btn" data-sup-tab="business">ব্যবসার তথ্য</button>
            <button type="button" class="sup-tab-btn" data-sup-tab="payment">পেমেন্ট তথ্য</button>
            <button type="button" class="sup-tab-btn" data-sup-tab="supply">সরবরাহ তথ্য</button>
            <button type="button" class="sup-tab-btn" data-sup-tab="performance" <?php echo $is_new ? 'disabled title="প্রথমে সেভ করুন"' : ''; ?>>পারফরম্যান্স</button>
            <button type="button" class="sup-tab-btn" data-sup-tab="documents" <?php echo $is_new ? 'disabled title="প্রথমে সেভ করুন"' : ''; ?>>ডকুমেন্টস</button>
        </div>

        <form action="process_save.php" method="POST" id="supProfileForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

            <!-- ১. Basic Information -->
            <div class="sup-tab-panel is-active" data-sup-panel="basic">
                <div class="sup-grid-2">
                    <div class="sup-field">
                        <label>সাপ্লায়ারের নাম *</label>
                        <input type="text" name="name" required maxlength="150" value="<?php echo sv($sup, 'name'); ?>" placeholder="নাম লিখুন">
                    </div>
                    <div class="sup-field">
                        <label>মোবাইল নম্বর</label>
                        <input type="text" name="phone" maxlength="30" value="<?php echo sv($sup, 'phone'); ?>" placeholder="০১৭xxxxxxxx">
                    </div>
                    <div class="sup-field">
                        <label>বিকল্প মোবাইল নম্বর</label>
                        <input type="text" name="alt_phone" maxlength="30" value="<?php echo sv($sup, 'alt_phone'); ?>">
                    </div>
                    <div class="sup-field">
                        <label>ইমেইল</label>
                        <input type="email" name="email" maxlength="150" value="<?php echo sv($sup, 'email'); ?>">
                    </div>
                    <div class="sup-field" style="grid-column: 1 / -1;">
                        <label>ঠিকানা</label>
                        <textarea name="address" placeholder="ঠিকানা লিখুন"><?php echo sv($sup, 'address'); ?></textarea>
                    </div>
                    <div class="sup-field">
                        <label>স্ট্যাটাস</label>
                        <?php $sup_active = $is_new || ame_supplier_status_enabled($sup['status'] ?? null); ?>
                        <select name="status">
                            <option value="1" <?php echo $sup_active ? 'selected' : ''; ?>>সক্রিয়</option>
                            <option value="0" <?php echo $sup_active ? '' : 'selected'; ?>>নিষ্ক্রিয়</option>
                        </select>
                    </div>
                    <div class="sup-field" style="grid-column: 1 / -1;">
                        <label>পূর্বের ব্যালেন্স (সাবেক বকেয়া) ৳</label>
                        <input type="number" step="0.01" name="previous_balance" id="supPreviousBalance" value="<?php echo htmlspecialchars(number_format($is_new ? (float)($sup['opening_balance'] ?? 0) : (float)($sup['total_due'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if ($is_new): ?>
                        <p class="sup-hint-note">নতুন সাপ্লায়ার যোগ করার সময় সফটওয়্যারের আগে কত পাওনা ছিল, সেটা এখানে লিখুন। ধনাত্মক = দোকান দেবে, ঋণাত্মক = দোকান পাবে।</p>
                        <?php else: ?>
                        <p class="sup-hint-note">এটাই পরের ক্রয় মেমো, লেজার ও স্টেটমেন্টের সাবেক বকেয়া। এডিট করে সেভ করলে কাস্টমার তালিকার অ্যামাউন্টও মিলবে।</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ২. Business Information -->
            <div class="sup-tab-panel" data-sup-panel="business">
                <div class="sup-grid-2">
                    <div class="sup-field">
                        <label>ব্যবসার নাম</label>
                        <input type="text" name="business_name" maxlength="150" value="<?php echo sv($sup, 'business_name'); ?>">
                    </div>
                    <div class="sup-field">
                        <label>ব্যবসার ধরন</label>
                        <select name="business_type">
                            <option value="">— বাছাই করুন —</option>
                            <?php foreach (ame_supplier_business_types() as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($sup['business_type'] ?? '') === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sup-field">
                        <label>ট্যাক্স/টিআইএন/বিআইএন নম্বর</label>
                        <input type="text" name="tax_number" maxlength="60" value="<?php echo sv($sup, 'tax_number'); ?>">
                    </div>
                    <div class="sup-field">
                        <label>ট্রেড লাইসেন্স নম্বর</label>
                        <input type="text" name="trade_license_no" maxlength="60" value="<?php echo sv($sup, 'trade_license_no'); ?>">
                    </div>
                    <div class="sup-field" style="grid-column: 1 / -1;">
                        <label>ওয়েবসাইট</label>
                        <input type="text" name="website" maxlength="150" value="<?php echo sv($sup, 'website'); ?>" placeholder="https://...">
                    </div>
                </div>
            </div>

            <!-- ৩. Payment Information -->
            <div class="sup-tab-panel" data-sup-panel="payment">
                <div class="sup-grid-2">
                    <div class="sup-field">
                        <label><?php echo ame_l('সাপ্লায়ার নাম্বার', 'Supplier number'); ?></label>
                        <input type="text" name="party_code" maxlength="40" value="<?php echo sv($sup, 'party_code'); ?>" <?php echo $is_purged ? 'readonly' : ''; ?>>
                        <?php if ($is_new): ?>
                        <div class="sup-hint-note" style="margin:6px 0 0;">মুছে যাওয়া সাপ্লায়ারের পুরনো নাম্বার ব্যবহার হবে না। এই নতুন নাম্বার বদলানো যায়।</div>
                        <?php endif; ?>
                    </div>
                    <div class="sup-field">
                        <label><?php echo ame_l('ক্রেডিট সীমা', 'Credit limit'); ?></label>
                        <input type="number" step="0.01" min="0" name="credit_limit" value="<?php echo sv($sup, 'credit_limit', '0'); ?>">
                    </div>
                    <div class="sup-field">
                        <label>পেমেন্ট শর্ত</label>
                        <select name="payment_terms">
                            <option value="">— বাছাই করুন —</option>
                            <?php foreach (ame_supplier_payment_terms() as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($sup['payment_terms'] ?? '') === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sup-field">
                        <label>পছন্দের পেমেন্ট মাধ্যম</label>
                        <select name="preferred_payment_method">
                            <option value="">— বাছাই করুন —</option>
                            <?php foreach (ame_supplier_payment_methods() as $k => $label): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($sup['preferred_payment_method'] ?? '') === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sup-field">
                        <label>ব্যাংকের নাম</label>
                        <input type="text" name="bank_name" maxlength="120" value="<?php echo sv($sup, 'bank_name'); ?>">
                    </div>
                    <div class="sup-field">
                        <label>ব্যাংক শাখা</label>
                        <input type="text" name="bank_branch" maxlength="120" value="<?php echo sv($sup, 'bank_branch'); ?>">
                    </div>
                    <div class="sup-field">
                        <label>ব্যাংক অ্যাকাউন্ট নম্বর</label>
                        <input type="text" name="bank_account_no" maxlength="60" value="<?php echo sv($sup, 'bank_account_no'); ?>">
                    </div>
                </div>

                <?php if (!$is_new): ?>
                <div class="sup-due-adjust-box">
                    <p class="sup-due-adjust-title">🧾 সাবেক/বর্তমান পাওনা সংশোধন</p>
                    <p class="sup-hint-note" style="margin-top:0;">বর্তমান মোট বকেয়া: <?php echo ame_supplier_due_render_inline((float)($sup['total_due'] ?? 0)); ?> — এটা স্বাভাবিকভাবে ক্রয়/পেমেন্ট থেকে হিসাব হয়। শুধু ভুল সংশোধন বা পুরনো/সাবেক পাওনা বসানোর জন্য নিচেরটা ব্যবহার করুন।</p>
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" id="supAdjustDueToggle" style="width:auto;"> পাওনার পরিমাণ পরিবর্তন করতে চাই
                    </label>
                    <div id="supAdjustDueFields" class="sup-grid-2" style="display:none; margin-top:12px;">
                        <div class="sup-field">
                            <label>নতুন মোট পাওনা (৳)</label>
                            <input type="number" step="0.01" name="adjusted_total_due" id="supAdjustedDueInput" value="<?php echo sv($sup, 'total_due', '0'); ?>" disabled>
                        </div>
                        <div class="sup-field">
                            <label>কারণ (ঐচ্ছিক, তবে দেওয়া ভালো)</label>
                            <input type="text" name="due_adjust_reason" maxlength="255" placeholder="যেমন: সফটওয়্যার ব্যবহারের আগের বকেয়া বসানো হলো">
                        </div>
                    </div>
                    <input type="hidden" name="adjust_due_confirm" id="supAdjustDueConfirm" value="0">
                </div>
                <?php endif; ?>
            </div>

            <!-- ৪. Supply Information -->
            <div class="sup-tab-panel" data-sup-panel="supply">
                <div class="sup-grid-2">
                    <div class="sup-field" style="grid-column: 1 / -1;">
                        <label>যেসব পণ্য/ক্যাটাগরি সরবরাহ করেন</label>
                        <input type="text" name="supply_categories" maxlength="255" value="<?php echo sv($sup, 'supply_categories'); ?>" placeholder="যেমন: স্পেয়ার পার্টস, মেশিনারি (কমা দিয়ে আলাদা করুন)">
                    </div>
                    <div class="sup-field">
                        <label>সরবরাহের সময় (দিন)</label>
                        <input type="number" name="lead_time_days" min="0" value="<?php echo sv($sup, 'lead_time_days'); ?>" placeholder="যেমন: ৩">
                    </div>
                    <div class="sup-field">
                        <label>ন্যূনতম অর্ডার</label>
                        <input type="text" name="minimum_order_note" maxlength="120" value="<?php echo sv($sup, 'minimum_order_note'); ?>" placeholder="যেমন: ৫ পিস / ১০,০০০ ৳">
                    </div>
                    <div class="sup-field" style="grid-column: 1 / -1;">
                        <label>সরবরাহ সংক্রান্ত নোট</label>
                        <textarea name="supply_notes"><?php echo sv($sup, 'supply_notes'); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ৫. Performance -->
            <div class="sup-tab-panel" data-sup-panel="performance">
                <?php if ($is_new): ?>
                    <p class="sup-hint-note">প্রথমে সাপ্লায়ার সেভ করুন — এরপর এখানে ক্রয়ের হিসাব থেকে পারফরম্যান্স দেখাবে।</p>
                <?php else: ?>
                    <div class="sup-perf-grid">
                        <div class="sup-perf-card"><div class="l">মোট অর্ডার</div><div class="v"><?php echo (int)$stats['total_orders']; ?></div></div>
                        <div class="sup-perf-card"><div class="l">মোট ক্রয় (৳)</div><div class="v"><?php echo number_format($stats['total_purchase'], 2); ?></div></div>
                        <div class="sup-perf-card"><div class="l">মোট পরিশোধ (৳)</div><div class="v"><?php echo number_format($stats['total_paid'], 2); ?></div></div>
                        <div class="sup-perf-card"><div class="l">গড় অর্ডার মূল্য (৳)</div><div class="v"><?php echo number_format($stats['avg_order_value'], 2); ?></div></div>
                        <div class="sup-perf-card"><div class="l">এই মাসের অর্ডার</div><div class="v"><?php echo (int)$stats['orders_this_month']; ?></div></div>
                        <div class="sup-perf-card"><div class="l">সর্বশেষ অর্ডার</div><div class="v" style="font-size:14px;"><?php echo $stats['last_order_date'] ? htmlspecialchars(date('d M Y', strtotime($stats['last_order_date']))) : '—'; ?></div></div>
                    </div>
                    <div class="sup-rating-row">
                        <div>
                            <label style="margin-bottom:2px;">সরবরাহকারী রেটিং</label>
                            <select name="rating">
                                <option value="">রেট করা হয়নি</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo (int)($sup['rating'] ?? 0) === $i ? 'selected' : ''; ?>><?php echo str_repeat('⭐', $i); ?> (<?php echo $i; ?>/৫)</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div><?php echo ame_supplier_rating_stars($sup['rating'] ?? null); ?></div>
                    </div>
                    <div class="sup-field">
                        <label>পারফরম্যান্স নোট</label>
                        <textarea name="performance_note" placeholder="যেমন: সময়মতো ডেলিভারি দেন, মালের মান ভালো ইত্যাদি"><?php echo sv($sup, 'performance_note'); ?></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sup-footer-actions">
                <?php if (!$is_purged): ?>
                <button type="submit" class="btn-success">💾 সংরক্ষণ করুন</button>
                <?php endif; ?>
                <a href="list.php" class="sup-cancel-btn">✖️ বাতিল করুন</a>
            </div>
        </form>
        <?php if (!$is_new && !$is_purged && $can_party_purge): ?>
        <div class="sup-footer-actions">
            <button type="button" class="sup-cancel-btn" style="border-color:var(--brand-danger);color:var(--brand-danger) !important;" data-ame-party-purge="supplier" data-id="<?php echo (int)$id; ?>" data-name="<?php echo htmlspecialchars((string)($sup['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-action="process_purge.php">চিরতরে মুছুন</button>
        </div>
        <?php endif; ?>

        <!-- ৬. Documents -->
        <div class="sup-tab-panel" data-sup-panel="documents">
            <?php if ($is_new): ?>
                <p class="sup-hint-note">প্রথমে সাপ্লায়ার সেভ করুন — এরপর এখানে ডকুমেন্ট আপলোড করা যাবে।</p>
            <?php else: ?>
                <?php if (!$docs): ?>
                    <p class="sup-hint-note" style="margin-top:0;">এখনো কোনো ডকুমেন্ট আপলোড করা হয়নি।</p>
                <?php else: foreach ($docs as $d): ?>
                    <div class="sup-doc-row">
                        <div>
                            <a href="/<?php echo htmlspecialchars(ltrim($d['file_path'], '/'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="sup-doc-name">📄 <?php echo htmlspecialchars($d['title']); ?></a>
                            <div class="sup-doc-meta"><?php echo htmlspecialchars(date('d M Y', strtotime($d['created_at']))); ?> · <?php echo number_format(((int)$d['file_size']) / 1024, 0); ?> KB</div>
                        </div>
                        <form action="process_document.php" method="POST" onsubmit="return confirm('এই ডকুমেন্টটি মুছে ফেলবেন?');" style="margin:0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="supplier_id" value="<?php echo (int)$id; ?>">
                            <input type="hidden" name="doc_id" value="<?php echo (int)$d['id']; ?>">
                            <button type="submit" class="sup-doc-del" style="background:none; border:none; cursor:pointer;">মুছুন</button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>

                <form action="process_document.php" method="POST" enctype="multipart/form-data" class="sup-doc-upload-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="supplier_id" value="<?php echo (int)$id; ?>">
                    <input type="text" name="title" placeholder="ডকুমেন্টের নাম (যেমন: ট্রেড লাইসেন্স)" maxlength="150" required>
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    <button type="submit" class="btn-success" style="padding:10px 18px;">⬆️ আপলোড</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var buttons = document.querySelectorAll('[data-sup-tab]');
    var panels = document.querySelectorAll('[data-sup-panel]');
    function activate(name) {
        var btn = document.querySelector('[data-sup-tab="' + name + '"]');
        if (!btn || btn.disabled) { return; }
        buttons.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
        panels.forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-sup-panel') === name);
        });
    }
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activate(btn.getAttribute('data-sup-tab'));
        });
    });
    if (window.location.hash === '#documents') {
        activate('documents');
    }

    var dueToggle = document.getElementById('supAdjustDueToggle');
    var dueFields = document.getElementById('supAdjustDueFields');
    var dueInput = document.getElementById('supAdjustedDueInput');
    var dueConfirm = document.getElementById('supAdjustDueConfirm');
    if (dueToggle) {
        dueToggle.addEventListener('change', function () {
            var on = dueToggle.checked;
            dueFields.style.display = on ? 'grid' : 'none';
            dueInput.disabled = !on;
            dueConfirm.value = on ? '1' : '0';
        });
    }
})();
</script>
<?php
if (function_exists('ame_party_purge_assets')) {
    ame_party_purge_assets();
}
include '../../partials/footer.php';
?>
