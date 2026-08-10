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

        if (departureInput && departureInput.value) {
            body.args = { departure_date: departureInput.value };
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
})();
