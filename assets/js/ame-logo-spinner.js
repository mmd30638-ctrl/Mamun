/**
 * ameShowBtnLoading(btn, label)
 * বাটনের ভিতরে দোকানের লোগো ঘুরিয়ে লোডিং ইন্ডিকেটর দেখানোর ছোট হেল্পার।
 * label দিলে লোগোর পাশে সেই লেখা দেখাবে, না দিলে বাটনের আগের লেখাই থাকবে।
 */
function ameShowBtnLoading(btn, label) {
    if (!btn) { return; }
    if (btn.dataset.ameLoading === '1') { return; }
    btn.dataset.ameLoading = '1';
    btn.classList.add('ame-btn-loading');
    var text = typeof label === 'string' ? label : btn.textContent;
    btn.innerHTML = '<span class="ame-logo-spin-wrap"><img src="/assets/img/ma-logo.png" alt=""></span><span>' +
        text.replace(/[<>&]/g, function (c) { return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]; }) +
        '</span>';
}
