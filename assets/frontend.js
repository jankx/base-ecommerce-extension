(function () {
    'use strict';

    function getJson(data) {
        return fetch(data.url, {
            method: data.method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: data.body ? JSON.stringify(data.body) : undefined
        }).then(function (response) {
            return response.json().then(function (json) {
                if (!response.ok) {
                    throw json;
                }
                return json;
            });
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.jankx-cart-remove');
        if (!button) {
            return;
        }

        event.preventDefault();
        var itemKey = button.getAttribute('data-item-key');
        var url = window.jankxEcommerce.restUrl + '/cart/items/' + encodeURIComponent(itemKey);

        button.disabled = true;
        getJson({ url: url, method: 'DELETE' }).then(function (response) {
            if (response.success) {
                window.location.reload();
                return;
            }
            alert(response.message || 'Failed to remove item.');
            button.disabled = false;
        }).catch(function () {
            alert('Failed to remove item.');
            button.disabled = false;
        });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.jankx-add-to-cart-form');
        if (!form) {
            return;
        }

        event.preventDefault();

        var productId = form.querySelector('[name="product_id"]').value;
        var quantityInput = form.querySelector('[name="quantity"]');
        var departureInput = form.querySelector('[name="departure_date"]');
        var statusBox = form.querySelector('.jankx-add-to-cart__status');
        var button = form.querySelector('button[type="submit"]');

        var body = {
            product_id: parseInt(productId, 10) || 0,
            quantity: quantityInput ? parseInt(quantityInput.value, 10) || 1 : 1
        };

        var args = {};
        if (departureInput && departureInput.value) {
            args.departure_date = departureInput.value;
        }

        // Group-wise quantities (e.g. date-based tour pricing): read every
        // input named group_qty[<group_id>] and pack them into args.group_qty.
        var groupQtyInputs = form.querySelectorAll('input[name^="group_qty["]');
        if (groupQtyInputs.length) {
            var groupQty = {};
            var totalGuests = 0;
            groupQtyInputs.forEach(function (input) {
                var match = input.name.match(/^group_qty\[([^\]]+)\]/);
                if (!match) {
                    return;
                }
                var qty = parseInt(input.value, 10) || 0;
                groupQty[match[1]] = Math.max(0, qty);
                totalGuests += Math.max(0, qty);
            });
            args.group_qty = groupQty;
            if (!quantityInput) {
                body.quantity = totalGuests || 1;
            }
        }

        if (Object.keys(args).length) {
            body.args = args;
        }

        if (statusBox) {
            statusBox.textContent = '';
        }
        button.disabled = true;

        getJson({
            url: window.jankxEcommerce.restUrl + '/cart/items',
            method: 'POST',
            body: body
        }).then(function (response) {
            if (!response.success) {
                if (statusBox) {
                    statusBox.textContent = response.message || 'Failed to add item.';
                }
                button.disabled = false;
                return;
            }

            if (document.querySelector('.jankx-mini-cart-toggle')) {
                document.dispatchEvent(new CustomEvent('jankx:cart-updated'));
                button.disabled = false;
                return;
            }
            if (window.jankxEcommerce.cartUrl) {
                window.location.href = window.jankxEcommerce.cartUrl;
                return;
            }
            if (statusBox) {
                statusBox.textContent = 'Added to cart.';
            }
            button.disabled = false;
        }).catch(function (error) {
            var message = error && error.message;
            if (statusBox) {
                statusBox.textContent = (Array.isArray(message) ? message.join(', ') : message) || 'Failed to add item.';
            }
            button.disabled = false;
        });
    });

    var checkoutForm = document.querySelector('.jankx-checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var errorBox = checkoutForm.querySelector('.jankx-checkout-error');
            var submitButton = checkoutForm.querySelector('.jankx-btn-place-order');

            function showError(message) {
                errorBox.textContent = message;
                errorBox.hidden = false;
            }

            errorBox.hidden = true;
            submitButton.disabled = true;

            var customer = {
                name: checkoutForm.querySelector('#jankx_customer_name').value,
                email: checkoutForm.querySelector('#jankx_customer_email').value,
                phone: checkoutForm.querySelector('#jankx_customer_phone').value,
                address: checkoutForm.querySelector('#jankx_customer_address').value
            };

            var gatewayInput = checkoutForm.querySelector('input[name="payment_method"]:checked');
            var gateway = gatewayInput ? gatewayInput.value : '';

            var createAccountCheckbox = checkoutForm.querySelector('#jankx_create_account');
            var createAccount = createAccountCheckbox ? createAccountCheckbox.checked : false;

            getJson({
                url: window.jankxEcommerce.restUrl + '/checkout',
                method: 'POST',
                body: { customer: customer, gateway: gateway, create_account: createAccount }
            }).then(function (response) {
                if (!response.success) {
                    var message = Array.isArray(response.message) ? response.message.join(', ') : response.message;
                    showError(message || 'Checkout failed.');
                    submitButton.disabled = false;
                    return;
                }

                // Redirect to payment gateway if needed
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                    return;
                }

                var redirect = window.jankxEcommerce.ordersUrl;
                if (redirect) {
                    window.location.href = redirect;
                    return;
                }

                checkoutForm.innerHTML = '<div class="jankx-checkout-success">'
                    + '<span class="jankx-empty-icon" aria-hidden="true">&#10004;</span>'
                    + '<h2 class="jankx-section-title">' + window.jankxEcommerce.i18n.successTitle + '</h2>'
                    + '<p>' + window.jankxEcommerce.i18n.successMessage.replace('%s', response.order.order_number) + '</p>'
                    + '</div>';
            }).catch(function (error) {
                var message = Array.isArray(error && error.message) ? error.message.join(', ') : (error && error.message);
                showError(message || 'Checkout failed.');
                submitButton.disabled = false;
            });
        });
    }

    // Expose pay order function globally for inline onclick
    window.jankxPayOrder = function (button) {
        var restUrl = button.getAttribute('data-rest-url');
        var nonce = button.getAttribute('data-nonce');
        var orderNumber = button.getAttribute('data-order');

        button.disabled = true;
        button.textContent = '...';

        fetch(restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            }
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (!data.success) {
                alert(data.message || 'Thanh toán thất bại.');
                button.disabled = false;
                button.textContent = 'Thanh toán ngay';
                return;
            }

            // Online payment: redirect
            if (data.type === 'online' && data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }

            // Bank transfer or COD: show message
            if (data.message) {
                var card = button.closest('.jankx-od-card');
                if (card) {
                    card.innerHTML = '<div class="jankx-od-info-inner">'
                        + '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                        + '<p>' + data.message.replace(/\n/g, '<br>') + '</p>'
                        + '</div>';
                }
            }
        })
        .catch(function (error) {
            alert('Lỗi: ' + (error.message || 'Vui lòng thử lại.'));
            button.disabled = false;
            button.textContent = 'Thanh toán ngay';
        });
    };
})();
