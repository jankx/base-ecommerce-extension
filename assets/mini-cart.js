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

    function getDropdown() {
        return document.querySelector('.jankx-mini-cart-dropdown');
    }

    function getPanel() {
        return getDrawer() || getDropdown();
    }

    function updateBadge(count) {
        document.querySelectorAll('[data-jankx-cart-count]').forEach(function (el) {
            el.textContent = count;
            el.classList.toggle('is-empty', count === 0);
        });
    }

    function itemRowHtml(item) {
        var unitPriceDisplay = item.formatted_unit_price || formatPrice(item.unit_price);
        var subtotalDisplay = item.formatted_subtotal || formatPrice(item.subtotal);

        return '<div class="jankx-mini-cart-row" data-item-key="' + escapeHtml(item.item_key) + '">'
            + '<div class="jankx-mini-cart-info">'
            + '<span class="jankx-mini-cart-name">' + escapeHtml(item.name) + '</span>'
            + '<span class="jankx-mini-cart-meta">' + (Number(item.quantity) || 0) + ' &times; '
            + escapeHtml(unitPriceDisplay) + '</span>'
            + '</div>'
            + '<div class="jankx-mini-cart-side">'
            + '<span class="jankx-mini-cart-price">' + escapeHtml(subtotalDisplay) + '</span>'
            + '<button type="button" class="jankx-mini-cart-remove" data-item-key="'
            + escapeHtml(item.item_key) + '" aria-label="' + escapeHtml(CONFIG.i18n.remove) + '">&times;</button>'
            + '</div>'
            + '</div>';
    }

    function emptyMarkup() {
        return '<p class="jankx-mini-cart-empty">' + escapeHtml(CONFIG.i18n.empty) + '</p>';
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
            itemsEl.innerHTML = emptyMarkup();
            if (footEl) {
                footEl.hidden = true;
            }
            return;
        }

        itemsEl.innerHTML = cart.items.map(itemRowHtml).join('');

        var totalEl = drawer.querySelector('[data-jankx-drawer-total]');
        if (totalEl) {
            totalEl.textContent = cart.formatted_total || formatPrice(cart.total);
        }
        if (footEl) {
            footEl.hidden = false;
        }
    }

    function renderDropdown(cart) {
        var dropdown = getDropdown();
        if (!dropdown) {
            return;
        }

        var itemsEl = dropdown.querySelector('[data-jankx-dropdown-items]');
        var footEl = dropdown.querySelector('[data-jankx-dropdown-footer]');
        if (!itemsEl) {
            return;
        }

        if (!cart.items.length) {
            itemsEl.innerHTML = emptyMarkup();
            if (footEl) {
                footEl.hidden = true;
            }
            return;
        }

        var limit = Number(dropdown.getAttribute('data-jankx-dropdown-limit')) || 3;
        var rows = cart.items.map(itemRowHtml);
        var visible = rows.slice(0, limit);
        var extra = rows.slice(limit);

        var viewAllBtn = extra.length
            ? '<button type="button" class="jankx-mini-cart-viewall" data-jankx-mini-cart-viewall aria-expanded="false">'
                + CONFIG.i18n.viewAll + ' (' + extra.length + ')</button>'
            : '';

        itemsEl.innerHTML = visible.join('')
            + viewAllBtn
            + (extra.length
                ? '<div class="jankx-mini-cart-extra" data-jankx-viewall-extra hidden>' + extra.join('') + '</div>'
                : '');

        var totalEl = dropdown.querySelector('[data-jankx-drawer-total]');
        if (totalEl) {
            totalEl.textContent = cart.formatted_total || formatPrice(cart.total);
        }
        if (footEl) {
            footEl.hidden = false;
        }
    }

    function renderPanel(cart) {
        renderDrawer(cart);
        renderDropdown(cart);
    }

    function refreshCart() {
        if (!restUrl) {
            return Promise.resolve();
        }
        return getJson(restUrl + '/cart', 'GET').then(function (cart) {
            updateBadge(cart.count);
            renderPanel(cart);
        }).catch(function () {
            return null;
        });
    }

    function isDropdownOpen() {
        var dropdown = getDropdown();
        return dropdown && dropdown.classList.contains('is-open');
    }

    function openPanel() {
        var panel = getPanel();
        if (!panel) {
            return;
        }

        if (!isDropdownOpen()) {
            document.body.classList.add('jankx-mini-cart-open');
        }
        panel.classList.add('is-open');
        var overlay = document.querySelector('.jankx-mini-cart-overlay');
        if (overlay) {
            overlay.classList.add('is-visible');
        }
        var toggle = document.querySelector('.jankx-mini-cart-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
        refreshCart();
    }

    function closePanel() {
        var panel = getPanel();
        var overlay = document.querySelector('.jankx-mini-cart-overlay');
        document.body.classList.remove('jankx-mini-cart-open');
        if (panel) {
            panel.classList.remove('is-open');
        }
        if (overlay) {
            overlay.classList.remove('is-visible');
        }
        var toggle = document.querySelector('.jankx-mini-cart-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function togglePanel() {
        var panel = getPanel();
        if (panel && panel.classList.contains('is-open')) {
            closePanel();
        } else {
            openPanel();
        }
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.jankx-mini-cart-toggle');
        if (toggle) {
            event.preventDefault();
            togglePanel();
            return;
        }

        if (event.target.closest('[data-jankx-mini-cart-close]')) {
            closePanel();
            return;
        }

        var viewAllButton = event.target.closest('.jankx-mini-cart-viewall');
        if (viewAllButton) {
            event.preventDefault();
            var dropdown = getDropdown();
            var extraEl = dropdown && dropdown.querySelector('[data-jankx-viewall-extra]');
            if (extraEl) {
                extraEl.hidden = false;
                viewAllButton.hidden = true;
            }
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
            return;
        }

        if (isDropdownOpen() && !event.target.closest('.jankx-mini-cart--dropdown')) {
            closePanel();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePanel();
        }
    });

    // Refresh the badge/panel whenever the cart changes elsewhere on the page.
    document.addEventListener('jankx:cart-updated', function () {
        refreshCart();
        openPanel();
    });

    /**
     * Move the drawer and overlay to document.body so that `position: fixed`
     * is always relative to the viewport, not a Gutenberg layout container
     * (.is-layout-constrained) which acts as a new containing block and
     * constrains the drawer width/position. The dropdown panel stays in place
     * so it can be positioned relative to its own wrapper.
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