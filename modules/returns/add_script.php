<script>
const returnLabels = <?php echo json_encode(
    current_lang() === 'en'
        ? [
            'sold' => 'Sold',
            'returned' => 'Returned',
            'remaining' => 'Returnable',
            'quantity' => 'Return quantity',
            'load_error' => 'Failed to load products.',
            'no_items' => 'No returnable products were found.'
        ]
        : [
            'sold' => 'বিক্রিত',
            'returned' => 'ফেরত হয়েছে',
            'remaining' => 'ফেরতযোগ্য',
            'quantity' => 'ফেরতের পরিমাণ',
            'load_error' => 'পণ্য লোড করতে সমস্যা হয়েছে।',
            'no_items' => 'কোনো ফেরতযোগ্য পণ্য পাওয়া যায়নি।'
        ],
    JSON_UNESCAPED_UNICODE
); ?>;

function escapeReturnHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value);
    return element.innerHTML;
}

function switchMode(mode) {
    document.getElementById('return_type').value = mode;

    document.getElementById('withInvoiceBox')
        .classList.toggle('rtn-hide', mode !== 'with_invoice');

    document.getElementById('withoutInvoiceBox')
        .classList.toggle('rtn-hide', mode !== 'without_invoice');

    document.getElementById('exchangeBox')
        .classList.toggle('rtn-hide', mode !== 'exchange');

    document.getElementById('amountReasonBox')
        .classList.toggle('rtn-hide', mode === 'exchange');

    document.getElementById('amount').required =
        mode !== 'exchange';

    document.querySelectorAll('#modeToggle label')
        .forEach(function (label) {
            label.classList.toggle(
                'rtn-tab-active',
                label.dataset.mode === mode
            );
        });
}

function selectReturnItem(index) {
    document.querySelectorAll('.invoice-return-quantity')
        .forEach(function (input, inputIndex) {
            const selected = inputIndex === index;

            input.disabled = !selected;
            input.required = selected;

            if (selected) {
                input.setAttribute('name', 'qty');
                input.focus();
            } else {
                input.removeAttribute('name');
                input.value = '';
            }
        });
}

function loadSaleItems() {
    const saleId = document.getElementById('sale_id').value;
    const box = document.getElementById('itemsResult');

    if (!saleId) {
        alert('<?php echo t("invoice_number"); ?>');
        return;
    }

    box.innerHTML =
        '<p style="color:#0D47A1;">⏳ Loading...</p>';

    fetch(
        'get_sale_items.php?sale_id='
        + encodeURIComponent(saleId),
        {credentials: 'same-origin'}
    )
    .then(function (response) {
        if (!response.ok) {
            throw new Error('Load failed');
        }

        return response.json();
    })
    .then(function (data) {
        if (
            !data.success
            || !Array.isArray(data.items)
            || data.items.length === 0
        ) {
            box.innerHTML =
                '<p style="color:#C62828;">❌ '
                + escapeReturnHtml(returnLabels.no_items)
                + '</p>';

            return;
        }

        let html = '';

        data.items.forEach(function (item, index) {
            const name = escapeReturnHtml(item.name);
            const sold = Number(item.sold_quantity);
            const returned = Number(item.returned_quantity);
            const remaining = Number(item.remaining_quantity);

            html += `
                <div class="rtn-item-row">
                    <input
                        type="radio"
                        name="product_id"
                        value="${Number(item.product_id)}"
                        onchange="selectReturnItem(${index})"
                        required
                    >

                    <div style="flex:1; min-width:0;">
                        <strong>${name}</strong>

                        <small style="
                            display:block;
                            margin-top:4px;
                            color:#555;
                        ">
                            ${returnLabels.sold}: ${sold}
                            |
                            ${returnLabels.returned}: ${returned}
                            |
                            <b style="color:#087F3D;">
                                ${returnLabels.remaining}: ${remaining}
                            </b>
                        </small>
                    </div>

                    <input
                        type="number"
                        class="invoice-return-quantity"
                        placeholder="${returnLabels.quantity}"
                        min="0.01"
                        max="${remaining}"
                        step="0.01"
                        disabled
                    >
                </div>
            `;
        });

        box.innerHTML = html;
    })
    .catch(function () {
        box.innerHTML =
            '<p style="color:#C62828;">❌ '
            + escapeReturnHtml(returnLabels.load_error)
            + '</p>';
    });
}

function calcExchange() {
    const oldSelect = document.getElementById('old_product_id');
    const newSelect = document.getElementById('new_product_id');

    const oldQty = parseFloat(
        document.getElementById('old_qty').value
    ) || 0;

    const newQty = parseFloat(
        document.getElementById('new_qty').value
    ) || 0;

    const oldPrice = parseFloat(
        oldSelect.options[oldSelect.selectedIndex]
            ?.dataset.price || 0
    );

    const newPrice = parseFloat(
        newSelect.options[newSelect.selectedIndex]
            ?.dataset.price || 0
    );

    const difference =
        (newPrice * newQty) - (oldPrice * oldQty);

    document.getElementById('price_difference').value =
        difference.toFixed(2);

    const box = document.getElementById('diffBox');

    if (difference > 0) {
        box.className = 'rtn-diff-box rtn-diff-positive';
        box.innerText = '💰 ৳' + difference.toFixed(2);
    } else if (difference < 0) {
        box.className = 'rtn-diff-box rtn-diff-negative';
        box.innerText = '↩️ ৳' + Math.abs(difference).toFixed(2);
    } else {
        box.className = 'rtn-diff-box rtn-diff-zero';
        box.innerText =
            '<?php echo t("price_difference_zero"); ?>';
    }
}

function updateAccountBalance() {
    const select = document.getElementById('account_id');
    const option = select.options[select.selectedIndex];
    const balance = parseFloat(option?.dataset.balance || 0);
    const hint = document.getElementById('accBalanceHint');

    hint.className = balance < 0
        ? 'rtn-acc-hint rtn-neg'
        : 'rtn-acc-hint rtn-pos';

    hint.innerText = '৳' + balance.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function () {
    switchMode('with_invoice');
    updateAccountBalance();
});
</script>