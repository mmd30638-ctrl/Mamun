'use strict';

/*
 * সার্ভিস চার্জ Cart টেবিলে ভিজ্যুয়ালি একটা লাইন হিসেবে দেখানো
 * (এটা আসল cart অ্যারেতে যোগ হয় না, শুধু দেখার জন্য)
 */

function ameRefreshServicePreview() {
    var body = document.getElementById('cart-body');
    var typeInput = document.getElementById('service_type');
    var amountInput = document.getElementById('service_amount');
    var engSelect = document.getElementById('eng_id');

    if (!body || !typeInput || !amountInput) {
        return;
    }

    var existingRow = document.getElementById('service-preview-row');
    if (existingRow) {
        existingRow.remove();
    }

    var amount = parseFloat(amountInput.value);
    if (!amount || amount <= 0) {
        return;
    }

    var isEmptyOnly = body.children.length === 1
        && body.children[0].children.length === 1
        && body.children[0].children[0].hasAttribute('colspan');

    if (isEmptyOnly) {
        body.replaceChildren();
    }

    var row = document.createElement('tr');
    row.id = 'service-preview-row';
    row.style.background = '#e8f5e9';

    var slCell = document.createElement('td');
    slCell.className = 'ame-pos-col-sl';
    slCell.textContent = '—';

    var checkCell = document.createElement('td');
    checkCell.className = 'ame-pos-col-check';
    checkCell.textContent = '';

    var codeCell = document.createElement('td');
    codeCell.textContent = '';
    if (engSelect && engSelect.selectedIndex > 0) {
        var chosen = engSelect.options[engSelect.selectedIndex].textContent || '';
        var coded = chosen.match(/\(([^)]+)\)\s*$/);
        codeCell.textContent = coded ? coded[1] : chosen;
    }

    var nameCell = document.createElement('td');
    var nameStrong = document.createElement('strong');
    nameStrong.textContent = typeInput.value.trim() || 'সার্ভিস চার্জ';
    nameCell.appendChild(nameStrong);

    var priceCell = document.createElement('td');
    priceCell.textContent = amount.toFixed(2);
    priceCell.style.textAlign = 'right';

    var qtyCell = document.createElement('td');
    qtyCell.textContent = '1';
    qtyCell.style.textAlign = 'center';

    var discCell = document.createElement('td');
    discCell.textContent = '';

    var totalCell = document.createElement('td');
    totalCell.textContent = amount.toFixed(2);
    totalCell.style.textAlign = 'right';
    totalCell.style.fontWeight = '800';

    var removeCell = document.createElement('td');
    removeCell.textContent = '—';
    removeCell.style.textAlign = 'center';
    removeCell.style.color = '#94a3b8';

    row.append(slCell, checkCell, codeCell, nameCell, qtyCell, priceCell, discCell, totalCell, removeCell);

    if (body.children.length === 0) {
        body.appendChild(row);
    } else {
        body.insertBefore(row, body.firstChild);
    }
}

window.ameRefreshServicePreview = ameRefreshServicePreview;

function ameLiveServiceBill() {
    ameRefreshServicePreview();
    if (typeof window.calculateAll === 'function') {
        window.calculateAll();
    }
    if (typeof window.ameRefreshDeskTotals === 'function') {
        window.ameRefreshDeskTotals();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var typeInput = document.getElementById('service_type');
    var amountInput = document.getElementById('service_amount');
    var engSelect = document.getElementById('eng_id');
    var addButton = document.getElementById('ameAddServiceToCartView');

    if (typeInput) {
        typeInput.addEventListener('input', ameLiveServiceBill);
    }
    if (amountInput) {
        amountInput.addEventListener('input', ameLiveServiceBill);
        amountInput.addEventListener('change', ameLiveServiceBill);
        amountInput.addEventListener('keyup', ameLiveServiceBill);
    }

    if (engSelect) {
        engSelect.addEventListener('change', ameLiveServiceBill);
    }

    if (addButton) {
        addButton.addEventListener('click', ameLiveServiceBill);
    }
});