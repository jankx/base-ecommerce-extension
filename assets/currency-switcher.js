(function () {
    'use strict';

    if (typeof jankxCurrencySwitcher === 'undefined') return;

    document.addEventListener('click', function (e) {
        var action = e.target.closest('[data-jcs-action="switch"]');
        if (!action) return;

        e.preventDefault();

        var currency = action.getAttribute('data-jcs-currency')
            || (action.tagName === 'SELECT' ? action.value : '');

        if (!currency) return;

        fetch(jankxCurrencySwitcher.restUrl + '/currency/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': jankxCurrencySwitcher.nonce
            },
            body: JSON.stringify({ currency: currency })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                location.reload();
            }
        })
        .catch(function () {});
    });

    // Handle select change
    document.querySelectorAll('.jcs-select[data-jcs-action="switch"]').forEach(function (select) {
        select.addEventListener('change', function () {
            var currency = this.value;
            if (!currency) return;

            fetch(jankxCurrencySwitcher.restUrl + '/currency/switch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': jankxCurrencySwitcher.nonce
                },
                body: JSON.stringify({ currency: currency })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(function () {});
        });
    });
})();
