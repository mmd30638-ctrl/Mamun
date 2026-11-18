(function () {
    var button = document.getElementById('ameSellWithoutStock');
    if (!button) {
        return;
    }

    function paint(on) {
        button.classList.toggle('is-on', !!on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        window.AME_SELL_WITHOUT_STOCK = !!on;
        if (typeof window.renderCart === 'function') {
            window.renderCart();
        }
    }

    paint(window.AME_SELL_WITHOUT_STOCK === true);

    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var next = !button.classList.contains('is-on');
        var body = new FormData();
        body.append('csrf_token', window.AME_CSRF_TOKEN || '');
        body.append('on', next ? '1' : '0');
        fetch('/modules/sales/sell_without_stock.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data && data.ok) {
                paint(!!data.on);
            }
        }).catch(function () {});
    });
})();
