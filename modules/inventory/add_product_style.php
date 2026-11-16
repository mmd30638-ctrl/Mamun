<?php
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '5.82', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/product-studio.css?v=<?php echo $ver; ?>-ps82">
<style>
#productCreateForm button[type="submit"] {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: static !important;
    width: 100% !important;
    max-width: 100% !important;
    height: 56px !important;
    min-height: 56px !important;
}

/* ক্যাটেগরি/ব্র্যান্ড/ইউনিট — পেজ ছেড়ে না গিয়ে সরাসরি নতুন তৈরি করার "+" বাটন */
.ps-field-with-add {
    display: flex;
    align-items: stretch;
    gap: 8px;
}
.ps-field-with-add select {
    flex: 1;
    min-width: 0;
}
.ps-quick-add-btn {
    flex: 0 0 auto;
    width: 40px;
    border: 1px solid var(--brand-navy, #0B1E4D);
    border-radius: 8px;
    background: var(--brand-navy, #0B1E4D);
    color: var(--brand-white, #fff);
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    transition: filter .15s ease;
}
.ps-quick-add-btn:hover,
.ps-quick-add-btn:focus {
    filter: brightness(1.1);
    outline: none;
}
.ps-quick-add-btn:active {
    filter: brightness(0.9);
}
.ps-quick-add-btn[disabled] {
    opacity: .6;
    cursor: not-allowed;
}
</style>
