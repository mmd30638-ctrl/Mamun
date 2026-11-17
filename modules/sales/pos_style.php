<link rel="stylesheet" href="/assets/css/ame-logo-spinner.css">
<style>
:root {
    /* NITHIUM পোস্টার থেকে অনুপ্রাণিত কালার প্যালেট (গাঢ় নেভি + সায়ান অ্যাকসেন্ট) */
    --pos-blue: var(--brand-navy);
    --pos-green: var(--brand-accent);   /* নামে "green" থাকলেও এটি সায়ান/ব্লু অ্যাকসেন্ট রঙ */
    --pos-black: var(--brand-navy-dark);
    --pos-red: var(--brand-danger);
    --pos-white: var(--brand-white);
    --pos-bg: var(--brand-bg);
    --pos-border: var(--brand-border);
    --pos-muted: var(--brand-muted);
}

.ame-pos-page {
    min-height: 100vh;
    padding: 12px;
    padding-bottom: 88px;
    box-sizing: border-box;
    background: var(--pos-bg);
}

.ame-pos-pay-jump {
    display: block;
    margin-top: 12px;
    padding: 12px;
    border: 2px solid var(--brand-navy);
    border-radius: 10px;
    background: var(--brand-white);
    color: var(--brand-navy) !important;
    text-align: center;
    text-decoration: none !important;
    font-weight: 800;
}

#amePosPay {
    scroll-margin-top: 90px;
}

.ame-pos-shell {
    max-width: 100%;
    width: 100%;
    margin: 0 auto;
}

.ame-pos-colorbar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    height: 7px;
    overflow: hidden;
    border-radius: 15px 15px 0 0;
}

.ame-pos-colorbar span:nth-child(1) { background: var(--pos-blue); }
.ame-pos-colorbar span:nth-child(2) { background: var(--pos-green); }
.ame-pos-colorbar span:nth-child(3) { background: var(--pos-black); }
.ame-pos-colorbar span:nth-child(4) { background: var(--pos-red); }

.ame-pos-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 18px;
    background: linear-gradient(135deg, var(--pos-c-050c1f), var(--pos-blue));
    color: white;
    border-radius: 0 0 15px 15px;
    box-shadow: 0 8px 22px rgba(13, 71, 161, 0.22);
}

.ame-pos-heading h2 {
    margin: 0 0 4px;
    font-size: clamp(20px, 4vw, 28px);
}

.ame-pos-heading p {
    margin: 0;
    color: var(--pos-c-dbeafe);
    font-size: 12px;
}

.ame-pos-status {
    padding: 8px 12px;
    border-radius: 20px;
    background: var(--pos-green);
    color: white;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
}

.ame-pos-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    margin-top: 14px;
}

.ame-pos-card {
    padding: 15px;
    box-sizing: border-box;
    border: 1px solid var(--pos-border);
    border-radius: 15px;
    background: white;
    box-shadow: 0 7px 20px rgba(15, 23, 42, 0.07);
}

.ame-pos-card-title {
    margin: 0 0 13px;
    padding-bottom: 9px;
    border-bottom: 2px solid var(--pos-blue);
    color: var(--pos-black);
    font-size: 16px;
}

.ame-pos-modes {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.ame-pos-mode-group {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    padding: 9px;
    border-radius: 11px;
    background: var(--pos-c-f1f5f9);
}

.ame-pos-mode {
    position: relative;
    cursor: pointer;
}

.ame-pos-mode input {
    position: absolute;
    opacity: 0;
}

.ame-pos-mode span {
    display: block;
    padding: 9px 12px;
    border: 1.5px solid var(--pos-c-bcc8d6);
    border-radius: 9px;
    background: white;
    color: var(--pos-black);
    font-size: 12px;
    font-weight: 800;
}

.ame-pos-mode input:checked + span {
    border-color: var(--pos-blue);
    background: var(--pos-blue);
    color: white;
}

.ame-pos-mode-toggle {
    display: inline-block;
}

.ame-pos-mode-arrow {
    margin-left: 5px;
    font-size: 10px;
    opacity: 0.85;
}

.ame-pos-mode-extra {
    display: none;
    flex-wrap: wrap;
    gap: 7px;
    width: 100%;
    margin-top: 7px;
    padding-top: 7px;
    border-top: 1px dashed var(--pos-c-bcc8d6);
}

.ame-pos-mode-extra.show {
    display: flex;
}
.ame-pos-input,
.ame-pos-select {
    display: block;
    width: 100%;
    min-width: 0;
    height: 48px;
    padding: 0 13px;
    box-sizing: border-box;
    border: 1.5px solid var(--pos-c-afbdcd);
    border-radius: 10px;
    background: white;
    color: var(--pos-black);
    font-size: 15px;
    outline: none;
}

.ame-pos-input:focus,
.ame-pos-select:focus {
    border-color: var(--pos-blue);
    box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.10);
}

.ame-pos-customer {
    margin-top: 12px;
    padding: 12px;
    border-left: 5px solid var(--pos-green);
    border-radius: 10px;
    background: var(--pos-c-f0fdf4);
}

.ame-pos-search {
    position: relative;
    margin-top: 12px;
}

#search-res {
    position: absolute;
    z-index: 500;
    top: 50px;
    left: 0;
    width: 100%;
    max-height: 340px;
    overflow-y: auto;
    flex-direction: column;
    border-radius: 0 0 12px 12px;
    background: white;
    box-shadow: 0 16px 35px rgba(15, 23, 42, 0.22);
}

#search-res:not(:empty) {
    display: flex;
}

#search-res:empty {
    display: none;
}

#search-res .res-item {
    display: flex !important;
    width: 100% !important;
    margin: 0 !important;
    min-height: 48px;
    box-sizing: border-box;
}

.ame-pos-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--pos-border);
    border-radius: 12px;
}

.table-pos {
    width: 100%;
    min-width: 700px;
    border-collapse: collapse;
}

.table-pos th {
    padding: 12px;
    background: var(--pos-black);
    color: white;
    text-align: left;
    font-size: 12px;
}

.table-pos td {
    padding: 11px;
    border-bottom: 1px solid var(--pos-c-e7edf4);
    color: var(--pos-black);
    font-size: 13px;
}

.table-pos tbody tr:hover {
    background: var(--brand-surface-soft);
}

.ame-pos-service {
    border-top: 0;
}

.ame-pos-service-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.ame-pos-summary {
    background: white;
    border-top: 6px solid var(--pos-black);
}

.ame-pos-calc-row {
    display: grid;
    grid-template-columns: 1fr 130px;
    align-items: center;
    gap: 10px;
    margin-bottom: 9px;
}

.ame-pos-calc-row label {
    color: var(--pos-black);
    font-size: 12px;
    font-weight: 800;
}

.ame-pos-calc-row input {
    width: 100%;
    height: 40px;
    padding: 0 9px;
    box-sizing: border-box;
    border: 1.5px solid var(--pos-c-afbdcd);
    border-radius: 8px;
    text-align: right;
    font-weight: 850;
}

.ame-balance-status {
    display: block;
    grid-column: 1 / -1;
    margin-top: -4px;
    margin-bottom: 2px;
    font-size: 12px;
    font-weight: 800;
    text-align: right;
}

#posCustomerInfoField.has-customer {
    background: var(--pos-c-f0f9ff);
    color: var(--brand-navy);
    font-size: 12px;
    font-weight: 700;
}

.ame-return-registry-hint {
    grid-column: 1 / -1;
    margin: 4px 0 0;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    color: #0f766e;
}

.ame-pos-total {
    margin: 16px 0;
    padding: 18px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--pos-blue), var(--pos-c-050c1f));
    color: white;
    text-align: center;
}

.ame-pos-total small {
    color: var(--pos-c-dbeafe);
}

.ame-pos-total strong {
    display: block;
    margin-top: 5px;
    color: var(--pos-c-69f0ae);
    font-size: 29px;
}

.ame-pos-due[hidden],
#ameAdjustDueBox[hidden] {
    display: none !important;
}

.ame-pos-due {
    margin: 15px 0;
    padding: 13px;
    border: 1px solid var(--pos-c-fecaca);
    border-left: 5px solid var(--pos-red);
    border-radius: 11px;
    background: var(--pos-c-fff7f7);
}

.ame-pos-due-check {
    display: block;
    margin-bottom: 11px;
    color: var(--pos-black);
    font-size: 12px;
    font-weight: 750;
    cursor: pointer;
}

.ame-pos-complete {
    width: 100%;
    min-height: 56px;
    border: 1.5px solid var(--pos-c-d8e1ec);
    border-radius: 12px;
    background: var(--brand-white);
    color: var(--pos-c-000000);
    -webkit-text-fill-color: var(--pos-c-000000);
    text-shadow: none;
    font-size: 17px;
    font-weight: 900;
    cursor: pointer;
    box-shadow: none;
}

.ame-pos-complete:disabled {
    opacity: 0.6;
    cursor: wait;
}

.ame-pos-complete.is-draft {
    background: var(--brand-white);
    color: var(--pos-c-000000);
    border-color: var(--pos-c-d8e1ec);
    box-shadow: none;
}

.ame-pos-home {
    display: block;
    margin-top: 11px;
    padding: 11px;
    border: 1.5px solid var(--pos-c-d8e1ec);
    border-radius: 10px;
    background: var(--brand-white);
    color: var(--pos-c-000000);
    text-align: center;
    text-decoration: none;
    font-weight: 800;
}

.ame-pos-fold-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    margin: 12px 0 16px;
}

.ame-pos-custom-item {
    width: 100%;
    margin: 0;
}

.ame-pos-custom-item-toggle {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    box-sizing: border-box;
    width: 100%;
    min-height: 48px;
    padding: 11px 12px;
    border: 1.5px dashed var(--pos-blue);
    border-radius: 10px;
    background: var(--pos-c-eef6ff);
    color: var(--pos-blue);
    font-weight: 800;
    cursor: pointer;
}

.ame-pos-custom-item-box {
    margin-top: 8px;
    padding: 12px;
    border: 1px solid #D9CBB3;
    border-radius: 10px;
    background: #EFE6D4;
}

.ame-pos-custom-item-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin: 8px 0;
}

.ame-pos-custom-item-btn {
    width: 100%;
    min-height: 44px;
    border: 0;
    border-radius: 9px;
    background: var(--pos-blue);
    color: var(--brand-white);
    font-weight: 800;
    cursor: pointer;
}

@media (min-width: 750px) {
    .ame-pos-modes {
        grid-template-columns: 1fr 1fr;
    }

    .ame-pos-service-grid {
        grid-template-columns: 1fr 1.4fr 110px auto;
    }
}

@media (min-width: 1150px) {
    .ame-pos-grid {
        grid-template-columns: 1fr;
        align-items: start;
    }
}

@media (max-width: 480px) {
    .ame-pos-page {
        padding: 7px;
    }

    .ame-pos-heading,
    .ame-pos-card {
        padding: 12px;
    }

    .ame-pos-heading {
        align-items: flex-start;
    }

    .ame-pos-calc-row {
        grid-template-columns: 1fr 110px;
    }
}

/* ⭐ "সম্পন্ন করুন" বাটনে সাদামাটা ঘোরন্ত রিং-এর বদলে দোকানের লোগো ঘুরিয়ে
   লোডিং দেখানো — assets/css/ame-logo-spinner.css এর ameLogoSpin
   অ্যানিমেশন পুনঃব্যবহার করা হয়েছে। !important ব্যবহার করা হয়েছে কারণ
   submit-spinner.css (ডাবল-সাবমিট-গার্ড দিয়ে) পরে ইনজেক্ট হতে পারে। */
.ame-pos-complete.ame-is-waiting .ame-wait-spin i {
    width: 34px !important;
    height: 34px !important;
    border: none !important;
    border-radius: 50% !important;
    background-image: url('/assets/img/ma-logo.png') !important;
    background-size: contain !important;
    background-repeat: no-repeat !important;
    background-position: center !important;
    animation: ameLogoSpin 1s linear infinite !important;
}
.ame-pos-title-line { display: none !important; }
.ame-pos-term-title.ame-sale-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 8px;
    padding: 0;
    background: transparent;
    border: 0;
}
.ame-pos-term-title.ame-sale-brand img { width: 36px; height: 36px; object-fit: contain; }
.ame-pos-term-title.ame-sale-brand strong { color: #10233f; font-size: 15px; line-height: 1.25; font-weight: 800; }
.ame-pos-term-radios label,
.ame-pos-radios label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 6px 10px;
    border-radius: 8px;
    background: #eef3f8;
    color: #10233f;
    font-size: 15px;
    font-weight: 700;
}
.ame-pos-term-hist,
.ame-pos-recent-select,
.ame-pos-draft-complete {
    min-height: 42px;
    font-size: 15px;
    font-weight: 800;
    color: #10233f;
    background: #e7eef6;
    border: 1px solid #c5d2e2;
}
</style>