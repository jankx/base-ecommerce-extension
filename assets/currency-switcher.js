(function () {
    'use strict';

    if (typeof jankxCurrencySwitcher === 'undefined') return;

    // Debug logging function
    var debugEnabled = localStorage.getItem('jankx_currency_debug') === '1';
    function debug(message, data) {
        if (debugEnabled) {
            console.log('[JankxCurrency]', message, data || '');
        }
    }

    // Helper function to switch currency
    function switchCurrency(currency) {
        if (!currency) return false;

        debug('Switching currency to:', currency);

        // Show visual feedback
        var switches = document.querySelectorAll('[data-jcs-action="switch"]');
        switches.forEach(function (el) {
            if (el.tagName === 'SELECT') {
                el.disabled = true;
            }
        });

        fetch(jankxCurrencySwitcher.restUrl + '/currency/switch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': jankxCurrencySwitcher.nonce
            },
            body: JSON.stringify({ currency: currency })
        })
        .then(function (res) {
            debug('API response status:', res.status);
            return res.json();
        })
        .then(function (data) {
            debug('API response data:', data);

            if (data.success) {
                debug('Currency switch successful, reloading page');
                // Add delay to ensure currency is saved in session/user meta
                setTimeout(function () {
                    location.reload();
                }, 100);
            } else {
                debug('Currency switch failed:', data.message);
                alert('❌ ' + (data.message || 'Failed to switch currency'));
                
                // Re-enable controls
                switches.forEach(function (el) {
                    if (el.tagName === 'SELECT') {
                        el.disabled = false;
                    }
                });
            }
        })
        .catch(function (error) {
            debug('API error:', error);
            console.error('Currency switch error:', error);
            alert('❌ Network error when switching currency. Check console for details.');
            
            // Re-enable controls
            switches.forEach(function (el) {
                if (el.tagName === 'SELECT') {
                    el.disabled = false;
                }
            });
        });
    }

    // Handle button clicks
    document.addEventListener('click', function (e) {
        var action = e.target.closest('[data-jcs-action="switch"]');
        if (!action) return;

        e.preventDefault();

        var currency = action.getAttribute('data-jcs-currency')
            || (action.tagName === 'SELECT' ? action.value : '');

        switchCurrency(currency);
    });

    // Handle select change
    document.querySelectorAll('.jcs-select[data-jcs-action="switch"]').forEach(function (select) {
        select.addEventListener('change', function () {
            switchCurrency(this.value);
        });
    });

    // Expose debug toggle to console
    window.jankxCurrencyToggleDebug = function () {
        var enabled = localStorage.getItem('jankx_currency_debug') === '1';
        localStorage.setItem('jankx_currency_debug', enabled ? '0' : '1');
        debugEnabled = !enabled;
        console.log('Jankx Currency Debug ' + (debugEnabled ? 'ENABLED' : 'DISABLED'));
        console.log('Reload page to see debug logs. Current currency:', jankxCurrencySwitcher.currentCurrency);
    };
})();
