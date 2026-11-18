<?php
if (!isset($is_edit)) {
    $is_edit = false;
}
if (!isset($p) || !is_array($p)) {
    $p = [];
}

$val = static function ($key, $default = '') use ($p) {
    if (!array_key_exists($key, $p) || $p[$key] === null) {
        return $default;
    }
    return $p[$key];
};

if (!isset($product_categories) || !is_array($product_categories)) {
    $product_categories = [];
}
if (!isset($product_brands) || !is_array($product_brands)) {
    $product_brands = [];
}
if (!isset($product_units) || !is_array($product_units)) {
    $product_units = [];
}

$product_type = (string)$val('product_type', '');
if ($product_type === '' && (int)$val('is_old_battery', 0) === 1) {
    $product_type = 'old_battery';
}
if ($product_type === '') {
    $product_type = 'part';
}

$product_status = (string)$val('status', 'active');
if ($product_status === '') {
    $product_status = 'active';
}

$image_url = function_exists('product_studio_image_url')
    ? product_studio_image_url($val('image_path', ''))
    : '';

$form_action = $is_edit ? 'process_edit.php' : 'process_product.php';
$page_title = $is_edit ? $words['title_edit'] : $words['title_add'];
$page_sub = $is_edit ? $words['subtitle_edit'] : $words['subtitle_add'];
$save_label = $is_edit ? $words['save_edit'] : $words['save'];
$stock_label = $is_edit ? $words['stock_edit'] : $words['stock'];
$stock_value = $is_edit ? $val('stock', '0') : '0';
$min_stock_value = $is_edit ? $val('min_stock', '5') : '5';
$moq_value = $val('moq', '1');
if ($moq_value === '' || $moq_value === null) {
    $moq_value = '1';
}
?>
<div class="ps-page">
    <div class="ps-shell">
        <div class="ps-color-bar" aria-hidden="true">
            <span></span><span></span><span></span><span></span>
        </div>

        <header class="ps-heading">
            <p class="ps-kicker">APONSHOP · Product Studio</p>
            <h2><?php echo $is_edit ? '✏️' : '🛍️'; ?> <?php echo $escape($page_title); ?></h2>
            <p><?php echo $escape($page_sub); ?></p>
            <p class="ps-more-hint">💰 খুচরা · পাইকারি · রি-ডিলার &nbsp;|&nbsp; 📦 স্টক &nbsp;|&nbsp; 🖼️ ছবি &nbsp;|&nbsp; 📋 স্পেক — নিচের চিপসে ট্যাপ করুন</p>
        </header>

        <div id="psDraftBanner" class="ps-draft-banner" hidden>
            <?php echo $escape($words['draft_restored']); ?>
            <button type="button" id="psClearDraft" class="ps-text-btn"><?php echo $escape($words['clear_draft']); ?></button>
        </div>

        <div class="ps-topgrid">
            <section class="ps-score-card" aria-live="polite">
                <div class="ps-ring-wrap">
                    <div id="psScoreRing" class="ps-ring" style="--ps-score: 0">
                        <strong id="psScoreValue">0%</strong>
                    </div>
                </div>
                <div>
                    <h3><?php echo $escape($words['score']); ?></h3>
                    <p><?php echo $escape($words['score_hint']); ?></p>
                    <ul id="psMissingList" class="ps-missing"></ul>
                </div>
            </section>

            <section class="ps-preview-card" id="psPreviewCard">
                <h3><?php echo $escape($words['preview']); ?></h3>
                <div class="ps-preview-name" id="psPreviewName">—</div>
                <div class="ps-preview-meta" id="psPreviewMeta"></div>
                <div class="ps-kpd">
                    <div class="ps-kpd-item retail"><span>K</span><strong id="psPreviewRetail">৳0</strong></div>
                    <div class="ps-kpd-item wholesale"><span>P</span><strong id="psPreviewWholesale">৳0</strong></div>
                    <div class="ps-kpd-item dealer"><span>D</span><strong id="psPreviewDealer">৳0</strong></div>
                </div>
                <p class="ps-preview-stock" id="psPreviewStock"></p>
            </section>
        </div>

        <nav class="ps-jump" aria-label="Sections">
            <a href="#ps-identity"><?php echo $escape($words['jump_id']); ?></a>
            <a href="#ps-price"><?php echo $escape($words['jump_price']); ?></a>
            <a href="#ps-stock"><?php echo $escape($words['jump_stock']); ?></a>
            <a href="#ps-catalog"><?php echo $escape($words['jump_catalog']); ?></a>
            <a href="#ps-media"><?php echo $escape($words['jump_media']); ?></a>
            <a href="#ps-specs"><?php echo $escape($words['jump_specs']); ?></a>
            <a href="#ps-more"><?php echo $escape($words['jump_more']); ?></a>
            <a href="#ps-seo"><?php echo $escape($words['jump_seo']); ?></a>
        </nav>

        <form
            action="<?php echo $escape($form_action); ?>"
            method="POST"
            id="productCreateForm"
            class="ps-form"
            autocomplete="off"
            enctype="multipart/form-data"
            novalidate
        >
            <?php echo csrf_field(); ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo (int)$val('id', 0); ?>">
            <?php endif; ?>

<section class="ps-section" id="ps-identity">
                <h3 class="ps-section-title">🆔 <?php echo $escape($words['identity']); ?></h3>

                <div class="ps-grid">
                    <div class="ps-field ps-span-2">
                        <label><?php echo $escape($words['name']); ?> <span class="ps-req">*</span></label>
                        <input type="text" name="name" id="productName" maxlength="255"
                               placeholder="<?php echo $escape($words['name_placeholder']); ?>"
                               value="<?php echo $escape($val('name')); ?>" required>
                    </div>
                </div>

                <?php include __DIR__ . '/add_product_code_section.php'; ?>

                <div class="ps-grid">
                    <div class="ps-field">
                        <label><?php echo $escape($words['barcode']); ?></label>
                        <div class="ps-inline">
                            <input type="text" name="barcode" id="productBarcode" maxlength="64"
                                   placeholder="<?php echo $escape($words['barcode_placeholder']); ?>"
                                   value="<?php echo $escape($val('barcode')); ?>">
                            <button type="button" id="barcodeGenerateBtn" class="ps-ghost"><?php echo $escape($words['barcode_auto']); ?></button>
                        </div>
                        <small class="ps-hint"><?php echo $escape($words['barcode_hint']); ?></small>
                        <div id="productBarcodeStatus" class="ps-status" aria-live="polite"></div>
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['status']); ?></label>
                        <select name="status" id="productStatus">
                            <option value="active"<?php echo $product_status === 'active' ? ' selected' : ''; ?>><?php echo $escape($words['status_active']); ?></option>
                            <option value="draft"<?php echo $product_status === 'draft' ? ' selected' : ''; ?>><?php echo $escape($words['status_draft']); ?></option>
                            <option value="inactive"<?php echo $product_status === 'inactive' ? ' selected' : ''; ?>><?php echo $escape($words['status_inactive']); ?></option>
                        </select>
                    </div>
                </div>

                <p class="ps-label"><?php echo $escape($words['type']); ?></p>
                <div class="ps-type-grid">
                    <?php
                    $types = [
                        'part' => $words['type_part'],
                        'new_battery' => $words['type_new_battery'],
                        'old_battery' => $words['type_old_battery'],
                        'accessory' => $words['type_accessory'],
                    ];
                    foreach ($types as $type_key => $type_label):
                    ?>
                    <label class="ps-type-card">
                        <input type="radio" name="product_type" value="<?php echo $escape($type_key); ?>"
                            <?php echo $product_type === $type_key ? ' checked' : ''; ?>>
                        <strong><?php echo $escape($type_label); ?></strong>
                    </label>
                    <?php endforeach; ?>
                </div>
            </section>

<section class="ps-section" id="ps-price">
                <h3 class="ps-section-title">💰 <?php echo $escape($words['pricing']); ?></h3>
                <p class="ps-note"><?php echo $escape($words['pricing_note']); ?></p>

                <div class="ps-price-grid">
                    <div class="ps-price-box cost">
                        <div class="ps-field">
                            <label><?php echo $escape($words['purchase']); ?> <span class="ps-req">*</span></label>
                            <input id="purchasePrice" type="number" name="purchase_price" min="0" step="0.01"
                                   inputmode="decimal" required value="<?php echo $escape($val('purchase_price')); ?>">
                        </div>
                    </div>
                    <div class="ps-price-box retail">
                        <div class="ps-badge">K · খুচরা</div>
                        <div class="ps-field">
                            <label><?php echo $escape($words['retail']); ?> <span class="ps-req">*</span></label>
                            <input id="retailPrice" type="number" name="sale_price" min="0" step="0.01"
                                   inputmode="decimal" required value="<?php echo $escape($val('sale_price')); ?>">
                        </div>
                    </div>
                    <div class="ps-price-box wholesale">
                        <div class="ps-badge">P · পাইকারি</div>
                        <div class="ps-field">
                            <label><?php echo $escape($words['wholesale']); ?> <span class="ps-req">*</span></label>
                            <input id="wholesalePrice" type="number" name="wholesale_price" min="0" step="0.01"
                                   inputmode="decimal" required value="<?php echo $escape($val('wholesale_price')); ?>">
                        </div>
                    </div>
                    <div class="ps-price-box dealer">
                        <div class="ps-badge">D · রি-ডিলার</div>
                        <div class="ps-field">
                            <label><?php echo $escape($words['dealer']); ?> <span class="ps-req">*</span></label>
                            <input id="dealerPrice" type="number" name="dealer_price" min="0" step="0.01"
                                   inputmode="decimal" required value="<?php echo $escape($val('dealer_price')); ?>">
                        </div>
                    </div>
                </div>

                <div class="ps-grid">
                    <div class="ps-field">
                        <label><?php echo $escape($words['vat']); ?></label>
                        <input type="number" name="vat_percent" min="0" step="0.01" inputmode="decimal"
                               value="<?php echo $escape($val('vat_percent', '0')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['discount']); ?></label>
                        <input type="number" name="discount_percent" min="0" step="0.01" inputmode="decimal"
                               value="<?php echo $escape($val('discount_percent', '0')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['moq']); ?></label>
                        <input type="number" name="moq" min="0" step="0.01" inputmode="decimal"
                               value="<?php echo $escape($moq_value); ?>">
                    </div>
                </div>

                <div class="ps-profit">
                    <div class="ps-profit-title">📊 <?php echo $escape($words['profit_preview']); ?></div>
                    <div class="ps-profit-grid">
                        <div class="ps-profit-item"><span><?php echo $escape($words['retail_profit']); ?></span><span id="retailProfit" class="ps-profit-value">৳0.00</span></div>
                        <div class="ps-profit-item"><span><?php echo $escape($words['wholesale_profit']); ?></span><span id="wholesaleProfit" class="ps-profit-value">৳0.00</span></div>
                        <div class="ps-profit-item"><span><?php echo $escape($words['dealer_profit']); ?></span><span id="dealerProfit" class="ps-profit-value">৳0.00</span></div>
                    </div>
                    <p id="profitStatus" class="ps-profit-status"><?php echo $escape($words['profit_ok']); ?></p>
                </div>
            </section>

            <div id="productSaveWrap" style="display:block!important;visibility:visible!important;margin:16px 0;padding:14px;background:#e8f5e9;border:2px solid #087f3d;border-radius:14px;">
                <button type="submit" id="productSaveButton" name="save_product" value="1" class="ps-save"
                    style="display:block!important;visibility:visible!important;width:100%!important;max-width:100%!important;height:56px;min-height:56px;background:#087f3d;color:#fff;border:0;border-radius:12px;font-size:18px;font-weight:800;">
                    💾 <?php echo $escape($save_label); ?>
                </button>
            </div>

<section class="ps-section" id="ps-stock">
                <h3 class="ps-section-title">📦 <?php echo $escape($words['inventory']); ?></h3>
                <div class="ps-grid">
                    <div class="ps-field">
                        <label><?php echo $escape($stock_label); ?> <span class="ps-req">*</span></label>
                        <input id="productStock" type="number" name="stock" min="0" step="0.01" inputmode="decimal"
                               required value="<?php echo $escape($stock_value); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['minimum_stock']); ?> <span class="ps-req">*</span></label>
                        <input id="productMinStock" type="number" name="min_stock" min="0" step="0.01" inputmode="decimal"
                               required value="<?php echo $escape($min_stock_value); ?>">
                    </div>
                </div>
            </section>

<section class="ps-section" id="ps-catalog">
                <h3 class="ps-section-title">🏷️ <?php echo $escape($words['catalog']); ?></h3>
                <div class="ps-grid">
                    <div class="ps-field">
                        <label><?php echo $escape($words['category']); ?></label>
                        <div class="ps-field-with-add">
                            <select name="category_id" id="productCategory">
                                <option value="0"><?php echo $escape($words['select']); ?></option>
                                <?php foreach ($product_categories as $category): ?>
                                    <option value="<?php echo (int)$category['id']; ?>"
                                        <?php echo (int)$val('category_id', 0) === (int)$category['id'] ? ' selected' : ''; ?>>
                                        <?php echo $escape($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="ps-quick-add-btn" data-quick-add="category" data-target="productCategory" title="নতুন ক্যাটেগরি যোগ করুন">+</button>
                        </div>
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['sub_category']); ?></label>
                        <input type="text" name="sub_category" maxlength="100"
                               placeholder="<?php echo $escape($words['sub_placeholder']); ?>"
                               value="<?php echo $escape($val('sub_category')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['brand']); ?></label>
                        <div class="ps-field-with-add">
                            <select name="brand_id" id="productBrand">
                                <option value="0"><?php echo $escape($words['select']); ?></option>
                                <?php foreach ($product_brands as $brand): ?>
                                    <option value="<?php echo (int)$brand['id']; ?>"
                                        <?php echo (int)$val('brand_id', 0) === (int)$brand['id'] ? ' selected' : ''; ?>>
                                        <?php echo $escape($brand['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="ps-quick-add-btn" data-quick-add="brand" data-target="productBrand" title="নতুন ব্র্যান্ড যোগ করুন">+</button>
                        </div>
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['manufacturer']); ?></label>
                        <input type="text" name="manufacturer_name" maxlength="150"
                               placeholder="<?php echo $escape($words['mfr_placeholder']); ?>"
                               value="<?php echo $escape($val('manufacturer_name')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['unit']); ?></label>
                        <div class="ps-field-with-add">
                            <select name="unit_id" id="productUnit">
                                <option value="0"><?php echo $escape($words['select']); ?></option>
                                <?php foreach ($product_units as $unit): ?>
                                    <option value="<?php echo (int)$unit['id']; ?>"
                                        <?php echo (int)$val('unit_id', 0) === (int)$unit['id'] ? ' selected' : ''; ?>>
                                        <?php echo $escape($unit['unit_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="ps-quick-add-btn" data-quick-add="unit" data-target="productUnit" title="নতুন ইউনিট যোগ করুন">+</button>
                        </div>
                    </div>
                </div>
            </section>

<section class="ps-section" id="ps-media">
                <h3 class="ps-section-title">🖼️ <?php echo $escape($words['media']); ?></h3>
                <div class="ps-media">
                    <div class="ps-photo-frame">
                        <img id="productImagePreview" alt=""
                             src="<?php echo $escape($image_url); ?>"
                             <?php echo $image_url === '' ? 'hidden' : ''; ?>>
                        <span id="productImageEmpty" class="ps-photo-empty" <?php echo $image_url !== '' ? 'hidden' : ''; ?>>📷</span>
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['photo']); ?></label>
                        <input type="file" name="product_image" id="productImageInput" accept="image/jpeg,image/png,image/webp,image/jpg">
                        <small class="ps-hint"><?php echo $escape($words['photo_hint']); ?></small>
                        <?php if ($image_url !== ''): ?>
                            <label class="ps-check">
                                <input type="checkbox" name="remove_image" value="1">
                                <?php echo $escape($words['remove_photo']); ?>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

<section class="ps-section" id="ps-specs">
                <h3 class="ps-section-title">📋 <?php echo $escape($words['specs']); ?></h3>
                <div class="ps-grid">
                    <div class="ps-field">
                        <label><?php echo $escape($words['shelf']); ?></label>
                        <input type="text" name="shelf_location" maxlength="100"
                               placeholder="<?php echo $escape($words['shelf_placeholder']); ?>"
                               value="<?php echo $escape($val('shelf_location')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['oem']); ?></label>
                        <input type="text" name="oem_code" maxlength="80"
                               placeholder="<?php echo $escape($words['oem_placeholder']); ?>"
                               value="<?php echo $escape($val('oem_code')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['supplier_code']); ?></label>
                        <input type="text" name="supplier_code" maxlength="80"
                               placeholder="<?php echo $escape($words['supplier_placeholder']); ?>"
                               value="<?php echo $escape($val('supplier_code')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['model']); ?></label>
                        <input type="text" name="model_name" maxlength="120" value="<?php echo $escape($val('model_name')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['size']); ?></label>
                        <input type="text" name="size_label" maxlength="80" value="<?php echo $escape($val('size_label')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['color']); ?></label>
                        <input type="text" name="color_label" maxlength="80" value="<?php echo $escape($val('color_label')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['weight']); ?></label>
                        <input type="number" name="weight_kg" min="0" step="0.001" inputmode="decimal"
                               value="<?php echo $escape($val('weight_kg')); ?>">
                    </div>
                    <div class="ps-field ps-span-2">
                        <label><?php echo $escape($words['short_desc']); ?></label>
                        <textarea name="short_description" id="productShort" rows="2"
                                  placeholder="<?php echo $escape($words['short_placeholder']); ?>"><?php echo $escape($val('short_description')); ?></textarea>
                    </div>
                    <div class="ps-field ps-span-2">
                        <label><?php echo $escape($words['highlights']); ?></label>
                        <textarea name="highlights" rows="3"><?php echo $escape($val('highlights')); ?></textarea>
                    </div>
                    <div class="ps-field ps-span-2">
                        <label><?php echo $escape($words['full_desc']); ?></label>
                        <textarea name="description" rows="4"><?php echo $escape($val('description')); ?></textarea>
                    </div>
                </div>
            </section>

<section class="ps-section" id="ps-more">
                <h3 class="ps-section-title">🛡️ <?php echo $escape($words['more']); ?></h3>
                <div class="ps-grid">
                    <div class="ps-field">
                        <label><?php echo $escape($words['warranty_months']); ?></label>
                        <input type="number" name="warranty_months" min="0" step="1" inputmode="numeric"
                               value="<?php echo $escape($val('warranty_months')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['warranty_type']); ?></label>
                        <input type="text" name="warranty_type" maxlength="80"
                               placeholder="<?php echo $escape($words['warranty_placeholder']); ?>"
                               value="<?php echo $escape($val('warranty_type')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['origin']); ?></label>
                        <input type="text" name="country_origin" maxlength="80" value="<?php echo $escape($val('country_origin')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['hs']); ?></label>
                        <input type="text" name="hs_code" maxlength="32" value="<?php echo $escape($val('hs_code')); ?>">
                    </div>
                    <div class="ps-field ps-span-2">
                        <label><?php echo $escape($words['return_policy']); ?></label>
                        <textarea name="return_policy" rows="2"><?php echo $escape($val('return_policy')); ?></textarea>
                    </div>
                </div>
            </section>

<section class="ps-section" id="ps-seo">
                <h3 class="ps-section-title">🔎 <?php echo $escape($words['seo']); ?></h3>
                <div class="ps-grid">
                    <div class="ps-field">
                        <label><?php echo $escape($words['tags']); ?></label>
                        <input type="text" name="tags" maxlength="255" value="<?php echo $escape($val('tags')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['reference']); ?></label>
                        <input type="text" name="reference_no" maxlength="80" value="<?php echo $escape($val('reference_no')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['seo_title']); ?></label>
                        <input type="text" name="seo_title" maxlength="180" value="<?php echo $escape($val('seo_title')); ?>">
                    </div>
                    <div class="ps-field">
                        <label><?php echo $escape($words['seo_keywords']); ?></label>
                        <input type="text" name="seo_keywords" maxlength="255" value="<?php echo $escape($val('seo_keywords')); ?>">
                    </div>
                    <div class="ps-field ps-span-2">
                        <label><?php echo $escape($words['notes']); ?></label>
                        <textarea name="internal_notes" rows="3"><?php echo $escape($val('internal_notes')); ?></textarea>
                    </div>
                </div>
            </section>

            <div class="ps-footer-spacer"></div>
            <div style="display:block!important;visibility:visible!important;margin:8px 0 24px;padding:14px;background:#e8f5e9;border:2px solid #087f3d;border-radius:14px;">
                <button type="submit" id="productSaveButtonEnd" name="save_product" value="1" class="ps-save"
                    style="display:block!important;visibility:visible!important;width:100%!important;max-width:100%!important;height:56px;min-height:56px;background:#087f3d;color:#fff;border:0;border-radius:12px;font-size:18px;font-weight:800;">
                    💾 <?php echo $escape($save_label); ?>
                </button>
            </div>
        </form>
    </div>
</div>
