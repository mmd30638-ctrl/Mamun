<style>
/* ওয়ার্কশপ স্কিন: সবুজ ব্যাজ নয় — এক্সচেঞ্জ/ক্যালকুলেটরের মতো হালকা সাদা কার্ড + গাঢ় কালো লেখা */

body.ame-pos-skin-workshop .ame-pos-colorbar,
body.ame-pos-skin-workshop .pos-payment-colors,
body.ame-pos-skin-workshop .pos-customer-colors {
    background: transparent !important;
}

body.ame-pos-skin-workshop .ame-pos-colorbar span,
body.ame-pos-skin-workshop .pos-payment-colors span,
body.ame-pos-skin-workshop .pos-customer-colors span {
    background: var(--pos-c-e8edf2) !important;
}

body.ame-pos-skin-workshop .ame-pos-heading {
    background: var(--brand-white) !important;
    border: 1.5px solid var(--pos-c-d8e1ec) !important;
    box-shadow: none !important;
}

body.ame-pos-skin-workshop .ame-pos-heading h2,
body.ame-pos-skin-workshop .ame-pos-heading p {
    display: inline-block;
    background: transparent !important;
    color: var(--pos-c-000000) !important;
    text-shadow: none !important;
    padding: 4px 10px;
    border-radius: 6px;
}

body.ame-pos-skin-workshop .ame-pos-heading p {
    color: var(--pos-c-9ca3af) !important;
    font-weight: 500 !important;
}

body.ame-pos-skin-workshop .ame-pos-status {
    background: var(--brand-white) !important;
    border: 1.5px solid var(--pos-c-d8e1ec);
    color: var(--pos-c-000000) !important;
}

body.ame-pos-skin-workshop .ame-pos-total {
    background: var(--brand-white) !important;
    border: 1.5px solid var(--pos-c-d8e1ec) !important;
}

body.ame-pos-skin-workshop .ame-pos-total small,
body.ame-pos-skin-workshop .ame-pos-total strong {
    display: inline-block;
    background: transparent !important;
    color: var(--pos-c-000000) !important;
    text-shadow: none !important;
    padding: 4px 10px;
    border-radius: 6px;
}

/* বাগ ফিক্স: "সর্বমোট পাওনা"-র বড় সংখ্যাটা আগে লেবেলের মতোই সাদামাটা
   কালো রঙে দেখাত — ফলে পুরো বক্সটা একরকম দেখাচ্ছিল, গুরুত্বপূর্ণ
   পাওনার অঙ্কটা আলাদাভাবে চোখে পড়ত না। এখন এটা সবুজ (সফল/থিমের
   "সফল" রঙ) দিয়ে হাইলাইট করা হলো, যাতে অ্যাকাউন্টের ব্যাকগ্রাউন্ড/টেক্সট
   কালার যেভাবেই সেট করা থাকুক না কেন সংখ্যাটা স্পষ্ট আলাদা দেখা যায়। */
body.ame-pos-skin-workshop .ame-pos-total strong {
    color: var(--brand-success) !important;
    font-weight: 700 !important;
}

body.ame-pos-skin-workshop .pos-payment-heading {
    background: var(--brand-white) !important;
}

body.ame-pos-skin-workshop .pos-payment-heading h4,
body.ame-pos-skin-workshop .pos-payment-heading small {
    display: inline-block;
    background: transparent !important;
    color: var(--pos-c-000000) !important;
    text-shadow: none !important;
    padding: 4px 10px;
    border-radius: 6px;
}

body.ame-pos-skin-workshop .pos-payment-heading small {
    color: var(--pos-c-9ca3af) !important;
    font-weight: 500 !important;
}

body.ame-pos-skin-workshop .pos-payment-summary-item {
    background: var(--brand-white) !important;
    border: 1.5px solid var(--pos-c-d8e1ec) !important;
}

body.ame-pos-skin-workshop .pos-payment-summary-item span,
body.ame-pos-skin-workshop .pos-payment-summary-item strong {
    display: inline-block;
    background: transparent !important;
    color: var(--pos-c-000000) !important;
    text-shadow: none !important;
    padding: 3px 8px;
    border-radius: 5px;
}

body.ame-pos-skin-workshop .ame-pos-due {
    background: transparent !important;
    border: 1px solid var(--pos-c-fecaca) !important;
}

body.ame-pos-skin-workshop .pos-customer-tab.active {
    background: var(--brand-white) !important;
    color: var(--pos-c-000000) !important;
    border-color: var(--pos-c-d8e1ec) !important;
}

body.ame-pos-skin-workshop .ame-pos-mode input:checked + span {
    background: var(--brand-white) !important;
    border: 1.5px solid var(--pos-c-d8e1ec) !important;
    color: var(--pos-c-000000) !important;
    text-shadow: none !important;
}
</style>
