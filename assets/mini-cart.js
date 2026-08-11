(function () {
    'use strict';

    var CONFIG = window.jankxMiniCart || {};
    var restUrl = CONFIG.restUrl || '';

    function getJson(url, method, body) {
        return fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (response) {
            return response.json().then(function (json) {
                if (!response.ok) {
                    throw json;
                }
                return json;
            });
        });
    }

    function formatPrice(value) {
        return (Number(value) || 0).toLocaleString('vi-VN') + 'đ';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text == null ? '' : text);
        return div.innerHTML;
    }

    function getDrawer() {
        return document.querySelector('.jankx-mini-cart-drawer');
    }

    function updateBadge(count) {
        document.querySelectorAll('[data-jankx-cart-count]').forEach(function (el) {
            el.textContent = count;
            el.classList.toggle('is-empty', count === 0);
        });
    }

    function renderDrawer(cart) {
        var drawer = getDrawer();
        if (!drawer) {
            return;
        }

        var itemsEl = drawer.querySelector('[data-jankx-drawer-items]');
        var footEl = drawer.querySelector('[data-jankx-drawer-footer]');
        if (!itemsEl) {
            return;
        }

        if (!cart.items.length) {
            itemsEl.innerHTML = '<p class="jankx-mini-cart-empty">'
                + escapeHtml(CONFIG.i18n.empty) + '</p>';
            if (footEl) {
                footEl.hidden = true;
            }
            return;
        }

        var rows = cart.items.map(function (item) {
            return '<div class="jankx-mini-cart-row" data-item-key="' + escapeHtml(item.item_key) + '">'
                + '<div class="jankx-mini-cart-info">'
                + '<span class="jankx-mini-cart-name">' + escapeHtml(item.name) + '</span>'
                + '<span class="jankx-mini-cart-meta">' + (Number(item.quantity) || 0) + ' &times; '
                + escapeHtml(formatPrice(item.unit_price)) + '</span>'
                + '</div>'
                + '<div class="jankx-mini-cart-side">'
                + '<span class="jankx-mini-cart-price">' + escapeHtml(formatPrice(item.subtotal)) + '</span>'
                + '<button type="button" class="jankx-mini-cart-remove" data-item-key="'
                + escapeHtml(item.item_key) + '" aria-label="' + escapeHtml(CONFIG.i18n.remove) + '">&times;</button>'
                + '</div>'
                + '</div>';
        }).join('');

        itemsEl.innerHTML = rows;

        var totalEl = drawer.querySelector('[data-jankx-drawer-total]');
        if (totalEl) {
            totalEl.textContent = formatPrice(cart.total);
        }
        if (footEl) {
            footEl.hidden = false;
        }
    }

    function refreshCart() {
        if (!restUrl) {
            return Promise.resolve();
        }
        return getJson(restUrl + '/cart', 'GET').then(function (cart) {
            updateBadge(cart.count);
            renderDrawer(cart);
        }).catch(function () {
            return null;
        });
    }

    function openDrawer() {
        var drawer = getDrawer();
        var overlay = document.querySelector('.jankx-mini-cart-overlay');
        if (!drawer) {
            return;
        }

        document.body.classList.add('jankx-mini-cart-open');
        drawer.classList.add('is-open');
        if (overlay) {
            overlay.classList.add('is-visible');
        }
        var toggle = document.querySelector('.jankx-mini-cart-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
        refreshCart();
    }

    function closeDrawer() {
        var drawer = getDrawer();
        var overlay = document.querySelector('.jankx-mini-cart-overlay');
        document.body.classList.remove('jankx-mini-cart-open');
        if (drawer) {
            drawer.classList.remove('is-open');
        }
        if (overlay) {
            overlay.classList.remove('is-visible');
        }
        var toggle = document.querySelector('.jankx-mini-cart-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.jankx-mini-cart-toggle');
        if (toggle) {
            event.preventDefault();
            var drawer = getDrawer();
            if (drawer && drawer.classList.contains('is-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
            return;
        }

        if (event.target.closest('[data-jankx-mini-cart-close]')) {
            closeDrawer();
            return;
        }

        var removeButton = event.target.closest('.jankx-mini-cart-remove');
        if (removeButton) {
            event.preventDefault();
            var itemKey = removeButton.getAttribute('data-item-key');
            removeButton.disabled = true;

            getJson(restUrl + '/cart/items/' + encodeURIComponent(itemKey), 'DELETE').then(function (response) {
                if (response.success) {
                    refreshCart();
                    return;
                }
                alert(response.message || CONFIG.i18n.removeError);
                removeButton.disabled = false;
            }).catch(function () {
                alert(CONFIG.i18n.removeError);
                removeButton.disabled = false;
            });
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    // Refresh the badge/drawer whenever the cart changes elsewhere on the page.
    document.addEventListener('jankx:cart-updated', function () {
        refreshCart();
        openDrawer();
    });

    /**
     * Move the drawer and overlay to document.body so that `position: fixed`
     * is always relative to the viewport, not a Gutenberg layout container
     * (.is-layout-constrained) which acts as a new containing block and
     * constrains the drawer width/position.
     */
    function teleportToBody() {
        var overlay = document.querySelector('.jankx-mini-cart-overlay');
        var drawer = document.querySelector('.jankx-mini-cart-drawer');

        if (overlay && overlay.parentElement !== document.body) {
            document.body.appendChild(overlay);
        }
        if (drawer && drawer.parentElement !== document.body) {
            document.body.appendChild(drawer);
        }
    }

    // Initial sync on load.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            teleportToBody();
            refreshCart();
        });
    } else {
        teleportToBody();
        refreshCart();
    }
})();
