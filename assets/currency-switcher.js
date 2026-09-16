(function () {
    'use strict';

    var config = (typeof jankxCurrencySwitcher !== 'undefined') ? jankxCurrencySwitcher : null;

    // Debug logging function
    var debugEnabled = localStorage.getItem('jankx_currency_debug') === '1';
    function debug(message, data) {
        if (debugEnabled) {
            console.log('[JankxCurrency]', message, data || '');
        }
    }

    // Helper function to switch currency
    function switchCurrency(currency, fallbackUrl) {
        if (!currency) return false;

        debug('Switching currency to:', currency);

        // Immediately set cookie in client JS as a reliable persistence guarantee
        try {
            var maxAge = 30 * 86400;
            var secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = 'jankx_currency=' + encodeURIComponent(currency) + '; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
            localStorage.setItem('jankx_currency', currency);
        } catch (e) {
            debug('Error setting cookie/localStorage:', e);
        }

        // Show visual feedback
        var switches = document.querySelectorAll('[data-jcs-action="switch"]');
        switches.forEach(function (el) {
            if (el.tagName === 'SELECT') {
                el.disabled = true;
            }
        });

        // If config is available, call REST API
        if (config && config.restUrl) {
            fetch(config.restUrl + '/currency/switch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce || ''
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
                    setTimeout(function () {
                        location.reload();
                    }, 100);
                } else {
                    debug('Currency switch failed via REST, falling back to URL redirect');
                    fallbackRedirect(currency, fallbackUrl);
                }
            })
            .catch(function (error) {
                debug('API error, falling back to URL redirect:', error);
                fallbackRedirect(currency, fallbackUrl);
            });
        } else {
            // No config loaded, fallback directly
            fallbackRedirect(currency, fallbackUrl);
        }
    }

    function fallbackRedirect(currency, fallbackUrl) {
        if (fallbackUrl && fallbackUrl !== '#' && fallbackUrl.indexOf('javascript:') === -1) {
            window.location.href = fallbackUrl;
        } else {
            var url = new URL(window.location.href);
            url.searchParams.set('currency', currency);
            window.location.href = url.toString();
        }
    }

    // Handle dropdown button toggle (click to open/close)
    document.addEventListener('click', function (e) {
        var dropdownToggle = e.target.closest('.jcs-dropdown');
        if (dropdownToggle) {
            var wrapper = dropdownToggle.closest('.jcs-dropdown-wrapper');
            if (wrapper) {
                e.preventDefault();
                var isOpen = wrapper.classList.contains('is-open');
                // Close any other open dropdowns first
                document.querySelectorAll('.jcs-dropdown-wrapper.is-open').forEach(function (w) {
                    w.classList.remove('is-open');
                    var btn = w.querySelector('.jcs-dropdown');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });
                if (!isOpen) {
                    wrapper.classList.add('is-open');
                    dropdownToggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }
        }

        // Close dropdown when clicking outside
        if (!e.target.closest('.jcs-dropdown-wrapper')) {
            document.querySelectorAll('.jcs-dropdown-wrapper.is-open').forEach(function (w) {
                w.classList.remove('is-open');
                var btn = w.querySelector('.jcs-dropdown');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Handle action links (clicks on currencies)
    document.addEventListener('click', function (e) {
        var action = e.target.closest('[data-jcs-action="switch"]');
        if (!action) return;

        e.preventDefault();

        var currency = action.getAttribute('data-jcs-currency')
            || (action.tagName === 'SELECT' ? action.value : '');
        var fallbackUrl = action.getAttribute('href') || '';

        switchCurrency(currency, fallbackUrl);
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
        console.log('Current currency in config:', config ? config.currentCurrency : 'N/A');
    };
})();
