/* 10.98.724 */
'use strict';

/*
 * পেশাদার POS Cart, হিসাব ও বিক্রয় Engine
 */

let cart = [];
let searchTimer = null;
let searchRequest = null;
let searchHits = [];
let searchActive = -1;
let saleSubmitting = false;
let posSlotTarget = 2;
let blankSearchTimer = null;
let blankSearchRequest = null;
let blankHits = [];
let blankActive = -1;
let blankSearchInput = null;
let posFocusBlankAfterRender = false;

function posSellWithoutStock() {
    return window.AME_SELL_WITHOUT_STOCK === true;
}

function posBlankSlotCount(cartLength, slotTarget) {
    const minimum = 2;
    const filled = cartLength > 0 ? cartLength : 0;
    const grow = filled >= minimum ? filled + 1 : minimum;
    const target = Math.max(minimum, slotTarget, grow);
    return Math.max(0, target - filled);
}

function posElement(id) {
    return document.getElementById(id);
}

function posNumber(value) {
    const number = Number.parseFloat(value);
    return Number.isFinite(number) ? number : 0;
}

function posEnglishMode() {
    if (typeof window.AME_LANG === 'string' && window.AME_LANG !== '') {
        return window.AME_LANG === 'en';
    }

    return document.querySelector(
        'a[href*="switch_language.php?lang=bn"]'
    ) !== null;
}

const posText = posEnglishMode()
    ? {
        empty: 'The cart is empty.',
        searchFailed: 'Product search failed.',
        noProduct: 'No products were found.',
        stockUnavailable: 'Insufficient stock.',
        selectCustomer: 'Select a registered customer.',
        selectAccount: 'Select a payment account.',
        retailDue: 'A retail sale cannot have an unpaid balance.',
        serviceInfo: 'Select an engineer and enter the service description.',
        processing: 'Processing sale...',
        saleFailed: 'The sale could not be completed.',
        offline: 'Internet is unavailable. The sale was not saved.',
        cartEmpty: 'Cart is empty. Search for a product.',
        stock: 'Stock'
    }
    : {
        empty: 'কার্ট খালি।',
        searchFailed: 'পণ্য অনুসন্ধান করা যায়নি।',
        noProduct: 'কোনো পণ্য পাওয়া যায়নি।',
        stockUnavailable: 'পর্যাপ্ত স্টক নেই।',
        selectCustomer: 'নিবন্ধিত ক্রেতা নির্বাচন করুন।',
        selectAccount: 'টাকা জমার অ্যাকাউন্ট নির্বাচন করুন।',
        retailDue: 'রিটেইল বিক্রয়ে বকেয়া রাখা যাবে না।',
        serviceInfo: 'ইঞ্জিনিয়ার নির্বাচন এবং সার্ভিসের বিবরণ লিখুন।',
        processing: 'বিক্রয় প্রক্রিয়াধীন...',
        saleFailed: 'বিক্রয় সম্পন্ন করা যায়নি।',
        offline: 'ইন্টারনেট নেই। বিক্রয় সংরক্ষণ করা হয়নি।',
        cartEmpty: 'কার্ট খালি। পণ্য অনুসন্ধান করুন।',
        stock: 'স্টক'
    };

function selectedCustomerType() {
    const selected = document.querySelector(
        'input[name="cus_type"]:checked'
    );

    return selected ? selected.value : 'Retail';
}

function selectedPriceMode() {
    const selected = document.querySelector(
        'input[name="price_mode"]:checked'
    );

    return selected ? selected.value : 'retail';
}

function productPrice(product) {
    if (product && (product.is_custom || product.price_locked)) {
        return posNumber(product.current_price);
    }

    const mode = selectedPriceMode();

    if (mode === 'wholesale') {
        return posNumber(product.wholesale_price);
    }

    if (mode === 'dealer') {
        return posNumber(product.dealer_price);
    }

    return posNumber(product.sale_price);
}

function toggleCustomerMode() {
    const registered = selectedCustomerType() === 'Registered';
    const customerArea = posElement('reg-cus-area');

    if (customerArea) {
        customerArea.style.display = registered
            ? 'block'
            : 'none';
    }

    if (!registered) {
        posElement('customer_id').value = '';
        posElement('prev-due').value = '0.00';
        posElement('curr-due').value = '0.00';
        posElement('adjust-due').checked = false;
    }

    calculateAll();
}

function fetchCusBalance() {
    const select = posElement('customer_id');
    if (!select) {
        return;
    }

    const option = select.options[select.selectedIndex];

    const due = option
        ? posNumber(option.dataset.due)
        : 0;

    posElement('prev-due').value = due.toFixed(2);
    if (typeof window.ameUpdateDueStatus === 'function') {
        window.ameUpdateDueStatus();
    }
    calculateAll();
}

function showSearchMessage(message, color) {
    const resultBox = posElement('search-res');
    resultBox.replaceChildren();

    const messageBox = document.createElement('div');
    messageBox.textContent = message;
    messageBox.style.padding = '15px';
    messageBox.style.color = color;
    messageBox.style.fontWeight = '700';

    resultBox.appendChild(messageBox);
}

function searchResultButtons() {
    const box = posElement('search-res');
    if (!box) {
        return [];
    }
    return Array.prototype.slice.call(box.querySelectorAll('.res-item'));
}

function paintSearchActive() {
    const buttons = searchResultButtons();
    buttons.forEach(function (button, index) {
        const on = index === searchActive;
        button.classList.toggle('is-active', on);
        button.setAttribute('aria-selected', on ? 'true' : 'false');
        if (on) {
            button.scrollIntoView({ block: 'nearest' });
        }
    });
    const input = posElement('p-search');
    if (!input) {
        return;
    }
    input.setAttribute('aria-expanded', buttons.length ? 'true' : 'false');
    if (searchActive >= 0 && buttons[searchActive]) {
        input.setAttribute('aria-activedescendant', buttons[searchActive].id);
    } else {
        input.removeAttribute('aria-activedescendant');
    }
}

function closeProductSearch() {
    searchHits = [];
    searchActive = -1;
    const box = posElement('search-res');
    if (box) {
        box.replaceChildren();
    }
    const input = posElement('p-search');
    if (input) {
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }
}
window.closeProductSearch = closeProductSearch;

function renderSearchResults(products) {
    const resultBox = posElement('search-res');
    resultBox.replaceChildren();
    searchHits = [];
    searchActive = -1;

    if (!Array.isArray(products) || products.length === 0) {
        showSearchMessage(posText.noProduct, '#c62828');
        paintSearchActive();
        return;
    }

    searchHits = products.slice();
    resultBox.setAttribute('role', 'listbox');

    products.forEach(function (product, index) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'res-item';
        item.id = 'pos-hit-' + index;
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', 'false');
        if (product.is_old_battery) {
            item.classList.add('is-exchange');
        }

        const information = document.createElement('span');
        information.className = 'res-copy';

        const name = document.createElement('span');
        name.className = 'res-name';
        name.textContent = product.name;

        const meta = document.createElement('span');
        meta.className = 'res-meta';
        meta.classList.add(posNumber(product.stock) > 0 ? 'is-in' : 'is-out');
        meta.textContent =
            (product.sku || 'ID-' + product.id)
            + ' | '
            + posText.stock
            + ': '
            + posNumber(product.stock)
            + (product.is_old_battery
                ? (posEnglishMode() ? ' · exchange' : ' · এক্সচেঞ্জ')
                : '');

        information.appendChild(name);
        information.appendChild(meta);

        const price = document.createElement('span');
        price.className = 'res-price';
        price.textContent = '৳' + productPrice(product).toFixed(2);

        item.appendChild(information);
        item.appendChild(price);

        item.addEventListener('click', function () {
            addToCart(product);
        });

        resultBox.appendChild(item);
    });

    paintSearchActive();
}

function localProductHits(query) {
    if (window.AmeOfflineCatalog && typeof AmeOfflineCatalog.searchProducts === 'function') {
        return AmeOfflineCatalog.searchProducts(query);
    }
    return [];
}

function applyProductHits(products, input) {
    if (!Array.isArray(products)) {
        showSearchMessage(posText.searchFailed, '#c62828');
        return;
    }
    if (products.length === 1) {
        const query = input.value.trim().toLowerCase();
        const match = products[0];
        const sku = String(match.sku || '').toLowerCase();
        const barcode = String(match.barcode || '').toLowerCase();
        if (sku === query || barcode === query || String(match.id) === query) {
            addToCart(match);
            return;
        }
    }
    renderSearchResults(products);
}

function searchProducts() {
    const input = posElement('p-search');
    const query = input.value.trim();

    if (query.length < 2 && !/^\d+$/.test(query)) {
        closeProductSearch();
        return;
    }

    if (searchRequest) {
        searchRequest.abort();
    }

    if (!navigator.onLine) {
        applyProductHits(localProductHits(query), input);
        return;
    }

    searchRequest = new AbortController();

    fetch(
        'search_product.php?q=' + encodeURIComponent(query),
        {
            credentials: 'same-origin',
            signal: searchRequest.signal
        }
    )
    .then(function (response) {
        if (!response.ok) {
            throw new Error('Search request failed');
        }

        return response.json();
    })
    .then(function (products) {
        applyProductHits(products, input);
    })
    .catch(function (error) {
        if (error.name === 'AbortError') {
            return;
        }
        const local = localProductHits(query);
        if (local.length) {
            applyProductHits(local, input);
            return;
        }
        showSearchMessage(posText.searchFailed, '#c62828');
    });
}

function addToCart(product) {
    if (product && product.is_old_battery && typeof window.ameAddExchangeFromProduct === 'function') {
        window.ameAddExchangeFromProduct(product);
        posElement('p-search').value = '';
        closeProductSearch();
        return;
    }

    const availableStock = posNumber(product.stock);

    if (!posSellWithoutStock() && availableStock <= 0) {
        alert(posText.stockUnavailable);
        return;
    }

    const existing = cart.find(function (item) {
        return Number(item.id) === Number(product.id);
    });

    if (existing) {
        if (!posSellWithoutStock() && existing.qty + 1 > availableStock) {
            alert(posText.stockUnavailable);
            return;
        }

        existing.qty += 1;
        var existingAt = cart.indexOf(existing);
        if (existingAt >= 0) {
            cart.splice(existingAt, 1);
            cart.push(existing);
        }
    } else {
        cart.push({
            id: Number(product.id),
            sku: product.sku || '',
            name: product.name,
            unit: product.unit || product.unit_name || '',
            sale_price: posNumber(product.sale_price),
            wholesale_price: posNumber(product.wholesale_price),
            dealer_price: posNumber(product.dealer_price),
            stock: availableStock,
            current_price: productPrice(product),
            qty: 1,
            unit_id: 0,
            is_custom: false
        });
    }

    posElement('p-search').value = '';
    closeProductSearch();
    closeBlankHits();
    if (!product.is_custom && product.id) {
        fetch('/modules/inventory/unit_api.php?role=sale&product_id=' + Number(product.id))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                const line = cart.find(function (item) { return Number(item.id) === Number(product.id); });
                if (!line) return;
                line.units = data.units || [];
                line.unit_hint = data.hint || '';
                line.base_unit_id = Number(data.base_id || 0);
                if (line.units.length) line.unit_id = Number(line.units[0].unit_id);
                renderCart();
            })
            .catch(function () {});
    }

    renderCart();
    if (posFocusBlankAfterRender) {
        posFocusBlankAfterRender = false;
        const nextBlank = document.querySelector('#cart-body .ame-blank-search');
        if (nextBlank) {
            nextBlank.focus();
        }
    }
}

function addCustomItemToCart(name, price, qty) {
    const cleanName = String(name || '').trim();
    const cleanPrice = posNumber(price);
    const cleanQty = posNumber(qty) > 0 ? posNumber(qty) : 1;

    if (cleanName === '' || cleanPrice < 0) {
        alert(
            posEnglishMode()
                ? 'Enter a valid name and price for the custom item.'
                : 'কাস্টম পণ্যের সঠিক নাম ও দাম দিন।'
        );
        return;
    }

    cart.push({
        id: null,
        sku: 'CUSTOM',
        name: cleanName,
        sale_price: cleanPrice,
        wholesale_price: cleanPrice,
        dealer_price: cleanPrice,
        stock: null,
        current_price: cleanPrice,
        qty: cleanQty,
        is_custom: true
    });

    renderCart();
}

function updateQty(index, value) {
    const item = cart[index];
    if (!item) {
        return;
    }

    let quantity = posNumber(value);

    if (quantity <= 0) {
        quantity = 1;
    }

    if (!posSellWithoutStock() && !item.is_custom && quantity > item.stock) {
        quantity = item.stock;
        alert(posText.stockUnavailable);
    }

    item.qty = quantity;
    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    if (posSlotTarget > 20 && cart.length < posSlotTarget) {
        posSlotTarget = Math.max(2, cart.length);
    }
    renderCart();
}

function updateCartPrice(index, value) {
    const item = cart[index];
    if (!item) {
        return;
    }

    let price = posNumber(value);
    if (price < 0) {
        price = 0;
    }

    item.current_price = price;
    item.price_locked = true;
    item.sale_price = price;
    item.wholesale_price = price;
    item.dealer_price = price;
    renderCart();
}

function closeBlankHits() {
    blankHits = [];
    blankActive = -1;
    blankSearchInput = null;
    document.querySelectorAll('.ame-blank-hits').forEach(function (box) {
        box.replaceChildren();
        box.hidden = true;
    });
}

function paintBlankActive() {
    if (!blankSearchInput) {
        return;
    }
    const box = blankSearchInput.parentElement
        ? blankSearchInput.parentElement.querySelector('.ame-blank-hits')
        : null;
    if (!box) {
        return;
    }
    const buttons = box.querySelectorAll('button');
    buttons.forEach(function (button, index) {
        button.classList.toggle('is-active', index === blankActive);
    });
    if (blankActive >= 0 && buttons[blankActive]) {
        buttons[blankActive].scrollIntoView({ block: 'nearest' });
    }
}

function renderBlankHits(products, input) {
    const box = input.parentElement
        ? input.parentElement.querySelector('.ame-blank-hits')
        : null;
    if (!box) {
        return;
    }
    box.replaceChildren();
    blankHits = Array.isArray(products) ? products.slice() : [];
    blankActive = blankHits.length ? 0 : -1;
    blankSearchInput = input;
    if (!blankHits.length) {
        const empty = document.createElement('div');
        empty.className = 'ame-blank-empty';
        empty.textContent = posText.noProduct;
        box.appendChild(empty);
        box.hidden = false;
        return;
    }
    blankHits.forEach(function (product) {
        const button = document.createElement('button');
        button.type = 'button';
        const name = document.createElement('span');
        name.textContent = product.name || '';
        const price = document.createElement('span');
        price.textContent = '৳' + productPrice(product).toFixed(2);
        button.append(name, price);
        button.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });
        button.addEventListener('click', function () {
            posFocusBlankAfterRender = true;
            addToCart(product);
        });
        box.appendChild(button);
    });
    box.hidden = false;
    paintBlankActive();
}

function applyBlankHits(products, input) {
    if (!Array.isArray(products)) {
        return;
    }
    if (products.length === 1) {
        const query = input.value.trim().toLowerCase();
        const match = products[0];
        const sku = String(match.sku || '').toLowerCase();
        const barcode = String(match.barcode || '').toLowerCase();
        if (sku === query || barcode === query || String(match.id) === query) {
            posFocusBlankAfterRender = true;
            addToCart(match);
            return;
        }
    }
    renderBlankHits(products, input);
}

function searchBlankRow(input) {
    const query = input.value.trim();
    if (query.length < 2 && !/^\d+$/.test(query)) {
        closeBlankHits();
        return;
    }
    if (blankSearchRequest) {
        blankSearchRequest.abort();
    }
    if (!navigator.onLine) {
        applyBlankHits(localProductHits(query), input);
        return;
    }
    blankSearchRequest = new AbortController();
    fetch(
        'search_product.php?q=' + encodeURIComponent(query),
        { credentials: 'same-origin', signal: blankSearchRequest.signal }
    )
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Search request failed');
            }
            return response.json();
        })
        .then(function (products) {
            if (!input.isConnected) {
                return;
            }
            applyBlankHits(products, input);
        })
        .catch(function (error) {
            if (error.name === 'AbortError') {
                return;
            }
            const local = localProductHits(query);
            if (local.length) {
                applyBlankHits(local, input);
            }
        });
}

function onBlankSearchKey(event) {
    const input = event.target;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!blankHits.length || blankSearchInput !== input) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        if (event.key === 'ArrowDown') {
            blankActive = blankActive < 0 ? 0 : (blankActive + 1) % blankHits.length;
        } else {
            blankActive = blankActive < 0
                ? blankHits.length - 1
                : (blankActive - 1 + blankHits.length) % blankHits.length;
        }
        paintBlankActive();
        return;
    }
    if (event.key === 'Enter') {
        if (blankHits.length && blankSearchInput === input) {
            event.preventDefault();
            event.stopPropagation();
            const index = blankActive >= 0 ? blankActive : 0;
            if (blankHits[index]) {
                posFocusBlankAfterRender = true;
                addToCart(blankHits[index]);
            }
        }
        return;
    }
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        closeBlankHits();
    }
}

function ameLineMath(item) {
    const price = posNumber(item.current_price);
    const qty = posNumber(item.qty);
    const pct = Math.min(100, Math.max(0, posNumber(item.discount_pct)));
    const gross = price * qty;
    const off = gross * pct / 100;
    return { gross: gross, off: off, net: gross - off, pct: pct };
}

function ameCartLinesNetTotal() {
    let sum = 0;
    cart.forEach(function (item) {
        sum += ameLineMath(item).net;
    });
    document.querySelectorAll('#cart-body tr.ame-cart-service-row').forEach(function (row) {
        const cells = row.querySelectorAll('td');
        if (cells.length < 8) {
            return;
        }
        sum += posNumber(cells[7].textContent);
    });
    return sum;
}

function amePaintCartLinesTotal() {
    const foot = document.getElementById('cart-lines-foot');
    const out = document.getElementById('cartLinesTotal');
    if (!foot || !out) {
        return;
    }
    const hasLines = cart.length > 0
        || document.querySelectorAll('#cart-body tr.ame-cart-service-row').length > 0;
    const total = ameCartLinesNetTotal();
    out.textContent = total.toFixed(2);
    foot.hidden = !hasLines;
}

window.amePaintCartLinesTotal = amePaintCartLinesTotal;

function ameRefreshDeskTotals() {
    let gross = 0;
    let off = 0;
    cart.forEach(function (item) {
        const math = ameLineMath(item);
        gross += math.gross;
        off += math.off;
    });
    window.ameLineDiscountTaka = off;
    amePaintCartLinesTotal();
    const subtotalEl = posElement('sub-total');
    if (subtotalEl) {
        subtotalEl.value = gross.toFixed(2);
    }
    const runner = window.calculateAll;
    if (typeof runner === 'function') {
        runner();
    } else if (typeof calculateAll === 'function') {
        calculateAll();
    }
}

const ameMarkedItems = new WeakSet();
const ameMarkedBlankRows = new Set();

function ameSyncSelectAll() {
    const selectAllBox = document.getElementById('posSelectAll');
    if (!selectAllBox) {
        return;
    }
    const boxes = document.querySelectorAll('#cart-body .pos-line-check');
    let checked = 0;
    boxes.forEach(function (box) {
        if (box.checked) {
            checked += 1;
        }
    });
    selectAllBox.checked = boxes.length > 0 && checked === boxes.length;
    selectAllBox.indeterminate = checked > 0 && checked < boxes.length;
}

function ameMatchCell(item, blankRowNo) {
    const cell = document.createElement('td');
    cell.className = 'ame-pos-col-check';
    const box = document.createElement('input');
    box.type = 'checkbox';
    box.className = 'pos-line-check';
    box.setAttribute('aria-label', document.documentElement.lang === 'en' ? 'Match' : 'মিল');
    box.checked = item ? ameMarkedItems.has(item) : ameMarkedBlankRows.has(blankRowNo);
    box.addEventListener('change', function () {
        if (item) {
            if (box.checked) { ameMarkedItems.add(item); } else { ameMarkedItems.delete(item); }
        } else if (box.checked) {
            ameMarkedBlankRows.add(blankRowNo);
        } else {
            ameMarkedBlankRows.delete(blankRowNo);
        }
        ameSyncSelectAll();
    });
    cell.appendChild(box);
    return cell;
}

function appendBlankRows(body, startIndex) {
    const count = posBlankSlotCount(cart.length, posSlotTarget);
    Array.from(ameMarkedBlankRows).forEach(function (rowNo) {
        if (rowNo <= startIndex) {
            ameMarkedBlankRows.delete(rowNo);
        }
    });
    for (let slot = 0; slot < count; slot += 1) {
        const row = document.createElement('tr');
        row.className = 'ame-pos-blank-row';
        row.dataset.blank = '1';

        const serialCell = document.createElement('td');
        serialCell.className = 'ame-pos-col-sl';
        serialCell.textContent = String(startIndex + slot + 1);

        const codeCell = document.createElement('td');
        const nameCell = document.createElement('td');
        const quantityCell = document.createElement('td');
        const priceCell = document.createElement('td');
        const discountCell = document.createElement('td');
        const totalCell = document.createElement('td');

        const removeCell = document.createElement('td');
        removeCell.className = 'ame-pos-col-remove';
        removeCell.textContent = '';

        row.append(
            serialCell,
            ameMatchCell(null, startIndex + slot + 1),
            codeCell,
            nameCell,
            quantityCell,
            priceCell,
            discountCell,
            totalCell,
            removeCell
        );
        body.appendChild(row);
    }
}

function renderCart() {
    const body = posElement('cart-body');
    if (!body) {
        return;
    }
    closeBlankHits();
    body.replaceChildren();

    cart.forEach(function (item, index) {
        if (!item.is_custom && !item.price_locked) {
            item.current_price = productPrice(item);
        } else {
            item.current_price = productPrice(item);
        }

        const math = ameLineMath(item);
        const lineTotal = math.net;

        const row = document.createElement('tr');

        const serialCell = document.createElement('td');
        serialCell.className = 'ame-pos-col-sl';
        serialCell.textContent = String(index + 1);

        const codeCell = document.createElement('td');
        codeCell.textContent = item.sku || item.id || '';

        const nameCell = document.createElement('td');
        const unitPart = item.unit ? '(' + item.unit + ')' : '';
        const stockPart = item.is_custom
            ? ''
            : '(' + posNumber(item.stock) + ')';
        nameCell.textContent = item.name + unitPart + stockPart;

        const priceCell = document.createElement('td');
        const priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.className = 'ame-pos-line-input';
        priceInput.step = '0.01';
        priceInput.min = '0';
        priceInput.value = item.current_price;
        priceInput.addEventListener('input', function () {
            let livePrice = posNumber(priceInput.value);
            if (livePrice < 0) {
                livePrice = 0;
            }
            item.current_price = livePrice;
            item.price_locked = true;
            item.sale_price = livePrice;
            item.wholesale_price = livePrice;
            item.dealer_price = livePrice;
            totalCell.textContent = ameLineMath(item).net.toFixed(2);
            ameRefreshDeskTotals();
        });
        priceInput.addEventListener('change', function () {
            let settled = posNumber(priceInput.value);
            if (settled < 0) {
                settled = 0;
            }
            item.current_price = settled;
            item.price_locked = true;
            item.sale_price = settled;
            item.wholesale_price = settled;
            item.dealer_price = settled;
            if (priceInput.value !== '' && posNumber(priceInput.value) !== settled) {
                priceInput.value = settled;
            }
            totalCell.textContent = ameLineMath(item).net.toFixed(2);
            ameRefreshDeskTotals();
        });
        priceCell.appendChild(priceInput);

        const quantityCell = document.createElement('td');
        const quantity = document.createElement('input');
        quantity.type = 'number';
        quantity.className = 'ame-pos-line-input';
        quantity.value = item.qty;
        quantity.min = '0.01';
        if (!item.is_custom && !posSellWithoutStock()) {
            quantity.max = item.stock;
        }
        if (item.units && item.units.length > 1) {
            quantity.removeAttribute('max');
        }
        quantity.step = '0.01';
        quantity.addEventListener('input', function () {
            let quantityValue = posNumber(quantity.value);
            if (quantityValue < 0) {
                quantityValue = 0;
            }
            item.qty = quantityValue;
            const live = ameLineMath(item);
            totalCell.textContent = live.net.toFixed(2);
            ameRefreshDeskTotals();
        });
        quantity.addEventListener('change', function () {
            const settledQty = posNumber(quantity.value);
            const overStock = !posSellWithoutStock() && !item.is_custom && settledQty > item.stock;
            if (settledQty <= 0 || overStock) {
                updateQty(index, quantity.value);
                return;
            }
            item.qty = settledQty;
            totalCell.textContent = ameLineMath(item).net.toFixed(2);
            ameRefreshDeskTotals();
        });
        quantityCell.appendChild(quantity);
        if (!item.is_custom && item.units && item.units.length > 1) {
            const unitPick = document.createElement('select');
            unitPick.className = 'ame-pos-line-input';
            item.units.forEach(function (unit) {
                const option = document.createElement('option');
                option.value = unit.unit_id;
                option.textContent = unit.unit_name;
                if (Number(item.unit_id) === Number(unit.unit_id)) option.selected = true;
                unitPick.appendChild(option);
            });
            unitPick.addEventListener('change', function () {
                item.unit_id = Number(unitPick.value);
                item.units.forEach(function (unit) {
                    if (Number(unit.unit_id) === item.unit_id && unit.sale_price) {
                        item.sale_price = posNumber(unit.sale_price);
                        item.current_price = posNumber(unit.sale_price);
                        item.price_locked = true;
                    }
                });
                renderCart();
            });
            quantityCell.appendChild(unitPick);
        }
        if (item.unit_hint) {
            const hint = document.createElement('small');
            hint.textContent = item.unit_hint;
            quantityCell.appendChild(hint);
        }

        const discountCell = document.createElement('td');
        const discountInput = document.createElement('input');
        discountInput.type = 'number';
        discountInput.className = 'ame-pos-line-input';
        discountInput.min = '0';
        discountInput.max = '100';
        discountInput.step = '0.01';
        discountInput.value = math.pct ? String(math.pct) : '';
        discountInput.placeholder = '0';
        discountInput.addEventListener('input', function () {
            let pct = posNumber(discountInput.value);
            if (pct < 0) {
                pct = 0;
            }
            if (pct > 100) {
                pct = 100;
            }
            item.discount_pct = pct;
            const live = ameLineMath(item);
            totalCell.textContent = live.net.toFixed(2);
            ameRefreshDeskTotals();
        });
        discountCell.appendChild(discountInput);

        const totalCell = document.createElement('td');
        totalCell.textContent = lineTotal.toFixed(2);
        totalCell.style.textAlign = 'right';
        totalCell.style.fontWeight = '800';

        const removeCell = document.createElement('td');
        removeCell.className = 'ame-pos-col-remove';
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'ame-desk-remove';
        removeButton.textContent = '✕';
        removeButton.title = posEnglishMode() ? 'Remove' : 'বাদ';
        removeButton.addEventListener('click', function () {
            removeFromCart(index);
        });
        removeCell.appendChild(removeButton);

        row.append(
            serialCell,
            ameMatchCell(item),
            codeCell,
            nameCell,
            quantityCell,
            priceCell,
            discountCell,
            totalCell,
            removeCell
        );

        body.appendChild(row);
    });

    var serviceRows = 0;
    if (typeof window.ameRenderServiceCartRows === 'function') {
        serviceRows = window.ameRenderServiceCartRows(body, cart.length) || 0;
    }

    appendBlankRows(body, cart.length + serviceRows);
    ameSyncSelectAll();

    ameRefreshDeskTotals();
}

/*
 * ⭐ এই বেসিক calculateAll() ফাংশনটা pos-payment.js লোড হওয়ার সাথে সাথেই
 * window.calculateAll = calculatePaymentSummary; দিয়ে নিরাপদে override হয়ে যায়।
 * এটা এখানে শুধু fallback হিসেবে থাকছে, যাতে pos-payment.js কোনো কারণে
 * লোড না হলেও পেজ পুরোপুরি ভেঙে না পড়ে।
 */
function calculateAll() {
    const subtotalEl = posElement('sub-total');
    const netPayableEl = posElement('net-payable');

    if (!subtotalEl || !netPayableEl) {
        return;
    }

    const subtotal = posNumber(subtotalEl.value);
    const vatPercent = posNumber(posElement('vat-per')?.value);
    const discount = posNumber(posElement('discount')?.value);
    const rounding = posNumber(posElement('rounding')?.value);
    const service = posNumber(posElement('service_amount')?.value);

    const vatAmount = (subtotal * vatPercent) / 100;

    const total = Math.max(
        0,
        subtotal + vatAmount + service + rounding - discount
    );

    netPayableEl.textContent = total.toFixed(2) + ' ৳';

    return {
        subtotal: subtotal,
        vatPercent: vatPercent,
        discount: discount,
        rounding: rounding,
        service: service,
        total: total
    };
}

const productSearch = posElement('p-search');

if (productSearch) {
    productSearch.setAttribute('role', 'combobox');
    productSearch.setAttribute('aria-autocomplete', 'list');
    productSearch.setAttribute('aria-controls', 'search-res');
    productSearch.setAttribute('aria-expanded', 'false');
    productSearch.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(searchProducts, 300);
    });
    productSearch.addEventListener('keydown', function (event) {
        const buttons = searchResultButtons();
        if (event.key === 'ArrowDown') {
            if (!buttons.length) {
                return;
            }
            event.preventDefault();
            searchActive = searchActive < 0 ? 0 : (searchActive + 1) % buttons.length;
            paintSearchActive();
            return;
        }
        if (event.key === 'ArrowUp') {
            if (!buttons.length) {
                return;
            }
            event.preventDefault();
            searchActive = searchActive < 0
                ? buttons.length - 1
                : (searchActive - 1 + buttons.length) % buttons.length;
            paintSearchActive();
            return;
        }
        if (event.key === 'Enter') {
            if (!buttons.length || !searchHits.length) {
                return;
            }
            event.preventDefault();
            const index = searchActive >= 0 ? searchActive : 0;
            if (searchHits[index]) {
                addToCart(searchHits[index]);
            }
            return;
        }
        if (event.key === 'Escape' && buttons.length) {
            event.preventDefault();
            closeProductSearch();
        }
    });
}

/* Enter completes or submits through enter-submit.js.
   Product search and the customer list still take Enter while they are open. */

const customerSelect = posElement('customer_id');

if (customerSelect) {
    customerSelect.addEventListener('change', fetchCusBalance);
}
if (window.AmeOfflineCatalog && typeof AmeOfflineCatalog.fillPosSelects === 'function') {
    AmeOfflineCatalog.fillPosSelects();
}

document.querySelectorAll('input[name="price_mode"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        cart.forEach(function (item) {
            if (!item.is_custom) {
                item.price_locked = false;
            }
        });
    });
});

const selectAll = posElement('posSelectAll');
if (selectAll) {
    selectAll.addEventListener('change', function () {
        const wanted = selectAll.checked;
        document.querySelectorAll('#cart-body .pos-line-check').forEach(function (box) {
            if (box.checked !== wanted) {
                box.checked = wanted;
                box.dispatchEvent(new Event('change'));
            }
        });
        ameSyncSelectAll();
    });
}

window.renderCart = renderCart;

window.ameGetCart = function () {
    return Array.isArray(cart) ? cart : [];
};

window.ameSetCart = function (items) {
    cart = Array.isArray(items) ? items : [];
    return cart;
};

window.ameClearPosSale = function () {
    cart = [];
    ameMarkedBlankRows.clear();
    try {
        sessionStorage.removeItem('ame_pos_sale_client_ref');
    } catch (eRef) { /* ignore */ }

    var search = posElement('p-search');
    if (search) {
        search.value = '';
    }
    closeProductSearch();

    var infoField = posElement('posCustomerInfoField');
    if (infoField) {
        infoField.value = '';
    }

    var customerSelect = posElement('customer_id');
    if (customerSelect) {
        customerSelect.value = '';
    }

    var vehicleNo = posElement('posVehicleNo');
    if (vehicleNo) {
        vehicleNo.value = '';
    }

    ['guestCustomerName', 'guestCustomerPhone', 'guestCustomerNote',
        'guestCustomerNameModal', 'guestCustomerPhoneModal', 'guestCustomerNoteModal'
    ].forEach(function (id) {
        var field = posElement(id);
        if (field) {
            field.value = '';
        }
    });

    var retail = posElement('posRetailCustomerMode');
    if (retail) {
        retail.checked = true;
    }
    var toolbarRetail = document.querySelector('input[name="cus_type_toolbar"][value="Retail"]');
    if (toolbarRetail) {
        toolbarRetail.checked = true;
    }

    var registeredInfo = posElement('registeredCustomerInfo');
    if (registeredInfo) {
        registeredInfo.style.display = 'none';
    }
    var summary = posElement('posCustomerSummary');
    if (summary) {
        summary.textContent = summary.getAttribute('data-default') || '';
        summary.classList.remove('has-value');
    }

    var priceRetail = document.querySelector('input[name="price_mode"][value="retail"]');
    if (priceRetail) {
        priceRetail.checked = true;
    }
    var extraModes = posElement('extraPriceModes');
    if (extraModes) {
        extraModes.hidden = true;
        extraModes.classList.remove('show', 'is-open');
    }
    var priceToggle = posElement('priceModeToggle');
    if (priceToggle) {
        priceToggle.setAttribute('aria-expanded', 'false');
    }

    posSlotTarget = 2;

    if (typeof window.ameSetServiceRows === 'function') {
        window.ameSetServiceRows([]);
    }
    var serviceAmount = posElement('service_amount');
    if (serviceAmount) {
        serviceAmount.value = '';
    }

    var vat = posElement('vat-per');
    if (vat) {
        vat.value = '0';
    }
    var discount = posElement('discount');
    if (discount) {
        discount.value = '0';
    }
    var rounding = posElement('rounding');
    if (rounding) {
        rounding.value = '0';
    }
    var adjustDue = posElement('adjust-due');
    if (adjustDue) {
        adjustDue.checked = false;
    }
    var sendSms = posElement('send-sms');
    if (sendSms) {
        sendSms.checked = false;
    }

    var draftId = posElement('currentDraftId');
    if (draftId) {
        draftId.value = '';
    }
    var draftNote = posElement('draftNote');
    if (draftNote) {
        draftNote.value = '';
    }
    var draftLabel = posElement('currentDraftLabel');
    if (draftLabel) {
        draftLabel.textContent = '';
        draftLabel.hidden = true;
    }

    var paymentRows = document.querySelectorAll('#posPaymentRows .pos-payment-row');
    paymentRows.forEach(function (row, index) {
        if (index === 0) {
            var account = row.querySelector('.pos-payment-account');
            var amount = row.querySelector('.pos-payment-amount');
            var reference = row.querySelector('.pos-payment-reference');
            if (account) {
                account.value = '';
            }
            if (amount) {
                amount.value = '0';
            }
            if (reference) {
                reference.value = '';
            }
        } else {
            row.remove();
        }
    });

    if (typeof window.ameSetExchange === 'function') {
        window.ameSetExchange([]);
    }

    if (typeof renderCart === 'function') {
        renderCart();
    } else if (typeof calculateAll === 'function') {
        calculateAll();
    }

    return true;
};

document.addEventListener('click', function (event) {
    if (!event.target.closest('.ame-blank-search-wrap')) {
        closeBlankHits();
    }
});

const addProductRow = posElement('ameAddProductRow');
if (addProductRow) {
    addProductRow.addEventListener('click', function () {
        posSlotTarget += 1;
        renderCart();
        const blanks = document.querySelectorAll('#cart-body .ame-blank-search');
        if (blanks.length) {
            blanks[blanks.length - 1].focus();
        }
    });
}

if (posElement('cart-body')) {
    renderCart();
}