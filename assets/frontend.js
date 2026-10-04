(function () {
    'use strict';

    function nonceFor(url) {
        // Two transports, two different nonce actions — and they are not
        // interchangeable:
        //   fast AJAX → NonceMiddleware verifies against the 'jankx_ajax' action
        //   REST      → rest_cookie_check_errors() verifies against 'wp_rest'
        // Sending the fast-AJAX nonce to a REST route fails cookie auth with
        // 403 rest_cookie_invalid_nonce, so pick per target rather than globally.
        var fastBase = window.JankxAjax && window.JankxAjax.url;
        var isFastAjax = !!(fastBase && typeof url === 'string' && url.indexOf(fastBase) === 0);

        if (isFastAjax) {
            return (window.JankxAjax && window.JankxAjax.nonce) || '';
        }

        return (window.jankxEcommerce && window.jankxEcommerce.nonce) || '';
    }

    function getJson(data) {
        var headers = {
            'Content-Type': 'application/json'
        };

        var nonce = nonceFor(data.url);
        if (nonce) {
            headers['X-WP-Nonce'] = nonce;
        }

        return fetch(data.url, {
            method: data.method,
            headers: headers,
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
        var button = event.target.closest('.jankx-cart-remove, .jankx-cart-item__remove');
        if (!button) {
            return;
        }

        event.preventDefault();
        var itemKey = button.getAttribute('data-item-key');
        var url = window.JankxAjax 
            ? window.JankxAjax.url + '/ecommerce/cart/remove-item/' + encodeURIComponent(itemKey) 
            : window.jankxEcommerce.restUrl + '/cart/items/' + encodeURIComponent(itemKey);

        button.disabled = true;
        getJson({ url: url, method: 'POST' }).then(function (response) {
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

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.jankx-cart-item__qty-btn');
        if (!button) {
            return;
        }

        event.preventDefault();
        var itemKey = button.getAttribute('data-item-key');
        var step = parseInt(button.getAttribute('data-step'), 10) || 0;
        var valueEl = document.querySelector('.jankx-cart-item__qty-value[data-item-key="' + itemKey + '"]');
        if (!valueEl) {
            return;
        }

        var current = parseInt(valueEl.textContent, 10) || 1;
        var next = Math.max(1, current + step);

        var url = window.JankxAjax
            ? window.JankxAjax.url + '/ecommerce/cart/update-quantity/' + encodeURIComponent(itemKey)
            : window.jankxEcommerce.restUrl + '/cart/items/' + encodeURIComponent(itemKey) + '/quantity';

        button.disabled = true;
        getJson({
            url: url,
            method: 'POST',
            body: { quantity: next }
        }).then(function (response) {
            if (response.success) {
                window.location.reload();
                return;
            }
            alert(response.message || 'Failed to update quantity.');
            button.disabled = false;
        }).catch(function () {
            alert('Failed to update quantity.');
            button.disabled = false;
        });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.jankx-add-to-cart-form');
        if (!form) {
            return;
        }

        event.preventDefault();

        var mode = form.getAttribute('data-jankx-mode') || 'normal';
        var isQuick = mode === 'quick';
        var productId = form.querySelector('[name="product_id"]').value;
        var quantityInput = form.querySelector('[name="quantity"]');
        var departureInput = form.querySelector('[name="departure_date"]');
        var statusBox = form.querySelector('.jankx-add-to-cart__status');
        var button = form.querySelector('button[type="submit"]');

        var body = {
            product_id: parseInt(productId, 10) || 0,
            quantity: quantityInput ? parseInt(quantityInput.value, 10) || 1 : 1,
            mode: mode
        };

        // Group-wise quantities (e.g. date-based tour pricing): read every
        // input named group_qty[<group_id>] and pack them into args.group_qty.
        var groupQtyInputs = form.querySelectorAll('input[name^="group_qty["]');
        if (groupQtyInputs.length) {
            // Variation model: each passenger group becomes its own cart line.
            var lines = [];
            var totalGuests = 0;
            groupQtyInputs.forEach(function (input) {
                var match = input.name.match(/^group_qty\[([^\]]+)\]/);
                if (!match) {
                    return;
                }
                var qty = parseInt(input.value, 10) || 0;
                qty = Math.max(0, qty);
                if (qty <= 0) {
                    return;
                }
                lines.push({
                    product_id: parseInt(productId, 10) || 0,
                    variation_id: match[1],
                    quantity: qty
                });
                totalGuests += qty;
            });

            if (totalGuests > 0) {
                var batchBody = {
                    lines: lines,
                    args: (departureInput && departureInput.value)
                        ? { departure_date: departureInput.value }
                        : {},
                    mode: mode
                };

                if (statusBox) {
                    statusBox.textContent = '';
                }
                button.disabled = true;
                button.classList.add('is-loading');
                var batchOriginalText = button.textContent;
                button.textContent = window.jankxEcommerce.i18n ? window.jankxEcommerce.i18n.adding : 'Đang thêm...';

                var url = window.JankxAjax
                    ? window.JankxAjax.url + '/ecommerce/cart/add-batch'
                    : window.jankxEcommerce.restUrl + '/cart/items/batch';

                getJson({
                    url: url,
                    method: 'POST',
                    body: batchBody
                }).then(function (response) {
                    if (!response.success) {
                        if (statusBox) {
                            statusBox.textContent = response.message || 'Failed to add item.';
                        }
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = batchOriginalText;
                        return;
                    }

                    button.classList.remove('is-loading');
                    button.textContent = window.jankxEcommerce.i18n ? window.jankxEcommerce.i18n.added : 'Đã thêm ✓';

                    if (document.querySelector('.jankx-mini-cart-toggle') && !isQuick) {
                        document.dispatchEvent(new CustomEvent('jankx:cart-updated'));
                        setTimeout(function () {
                            button.textContent = batchOriginalText;
                            button.disabled = false;
                        }, 1500);
                        return;
                    }
                    var batchRedirect = isQuick ? window.jankxEcommerce.checkoutUrl : window.jankxEcommerce.cartUrl;
                    if (batchRedirect) {
                        window.location.href = batchRedirect;
                        return;
                    }
                    if (statusBox) {
                        statusBox.textContent = 'Added to cart.';
                    }
                    button.textContent = batchOriginalText;
                    button.disabled = false;
                }).catch(function (error) {
                    var batchMessage = error && error.message;
                    if (statusBox) {
                        statusBox.textContent = (Array.isArray(batchMessage) ? batchMessage.join(', ') : batchMessage) || 'Failed to add item.';
                    }
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    button.textContent = batchOriginalText;
                });
                return;
            }
        }

        var args = {};
        if (departureInput && departureInput.value) {
            args.departure_date = departureInput.value;
        }

        if (statusBox) {
            statusBox.textContent = '';
        }
        button.disabled = true;
        button.classList.add('is-loading');
        var originalText = button.textContent;
        button.textContent = window.jankxEcommerce.i18n ? window.jankxEcommerce.i18n.adding : 'Đang thêm...';

        var url = window.JankxAjax
            ? window.JankxAjax.url + '/ecommerce/cart/add-item'
            : window.jankxEcommerce.restUrl + '/cart/items';

        getJson({
            url: url,
            method: 'POST',
            body: body
        }).then(function (response) {
            if (!response.success) {
                if (statusBox) {
                    statusBox.textContent = response.message || 'Failed to add item.';
                }
                button.disabled = false;
                button.classList.remove('is-loading');
                button.textContent = originalText;
                return;
            }

            button.classList.remove('is-loading');
            button.textContent = window.jankxEcommerce.i18n ? window.jankxEcommerce.i18n.added : 'Đã thêm ✓';

            if (document.querySelector('.jankx-mini-cart-toggle') && !isQuick) {
                document.dispatchEvent(new CustomEvent('jankx:cart-updated'));
                setTimeout(function () {
                    button.textContent = originalText;
                    button.disabled = false;
                }, 1500);
                return;
            }
            var redirectUrl = isQuick ? window.jankxEcommerce.checkoutUrl : window.jankxEcommerce.cartUrl;
            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }
            if (statusBox) {
                statusBox.textContent = 'Added to cart.';
            }
            button.textContent = originalText;
            button.disabled = false;
        }).catch(function (error) {
            var message = error && error.message;
            if (statusBox) {
                statusBox.textContent = (Array.isArray(message) ? message.join(', ') : message) || 'Failed to add item.';
            }
            button.disabled = false;
            button.classList.remove('is-loading');
            button.textContent = originalText;
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
                address: (checkoutForm.querySelector('#jankx_customer_address') || {}).value || ''
            };

            var gatewayInput = checkoutForm.querySelector('input[name="payment_method"]:checked');
            var gateway = gatewayInput ? gatewayInput.value : '';

            var createAccountCheckbox = checkoutForm.querySelector('#jankx_create_account');
            var createAccount = createAccountCheckbox ? createAccountCheckbox.checked : false;

            var termsCheckbox = checkoutForm.querySelector('#jankx_accept_terms');
            if (termsCheckbox && !termsCheckbox.checked) {
                showError(window.jankxEcommerce.i18n.termsRequired || 'Vui lòng đồng ý Điều khoản sử dụng và Chính sách hoàn hủy.');
                submitButton.disabled = false;
                return;
            }

            var checkoutMode = checkoutForm.getAttribute('data-jankx-checkout-mode') || 'normal';
            var checkoutBody = {
                customer: customer,
                gateway: gateway,
                create_account: createAccount,
                accept_terms: termsCheckbox ? termsCheckbox.checked : false
            };
            if (checkoutMode !== 'normal') {
                checkoutBody.mode = checkoutMode;
            }

            getJson({
                url: window.jankxEcommerce.restUrl + '/checkout',
                method: 'POST',
                body: checkoutBody
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

                // QR payment without redirect: show QR modal
                if (response.payment_status === 'qr' && response.qr_image && response.order) {
                    submitButton.disabled = false;
                    showQrModal(response);
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

                // QR payment: render QR code inline for scanning
                if (data.type === 'qr') {
                    var qrCard = button.closest('.jankx-od-card');
                    var qrBody;
                    if (data.qr_image) {
                        qrBody = '<div class="jankx-od-qr">'
                            + '<p>' + (data.message || 'Quét mã QR bên dưới bằng ứng dụng ngân hàng để thanh toán.') + '</p>'
                            + '<div class="jankx-qrviet-image"><img src="' + data.qr_image + '" alt="VietQR - ' + (data.order_number || '') + '" width="280" height="280"></div>'
                            + '<p class="description">Đơn hàng ' + (data.order_number || '') + ' - đơn hàng sẽ tự động cập nhật sau khi thanh toán. Tải lại trang để kiểm tra.</p>'
                            + '</div>';
                    } else {
                        qrBody = '<div class="jankx-od-info-inner"><p>' + (data.message || 'Vui lòng thử lại trong ít phút.') + '</p></div>';
                    }
                    if (qrCard) {
                        qrCard.innerHTML = qrBody;
                    }
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

    // Expose cancel order function globally for inline onclick
    window.jankxCancelOrder = function (button) {
        var restUrl = button.getAttribute('data-rest-url');
        var nonce = button.getAttribute('data-nonce');
        var orderNumber = button.getAttribute('data-order');

        if (!window.confirm('Bạn có chắc muốn hủy đơn hàng #' + orderNumber + '?')) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Đang hủy...';

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
                alert(data.message || 'Không thể hủy đơn hàng.');
                button.disabled = false;
                button.textContent = 'Hủy đơn hàng';
                return;
            }

            alert(data.message || 'Đơn hàng đã được hủy.');
            window.location.reload();
        })
        .catch(function (error) {
            alert('Lỗi: ' + (error.message || 'Vui lòng thử lại.'));
            button.disabled = false;
            button.textContent = 'Hủy đơn hàng';
        });
    };

    // -------------------------------------------------------
    // Payment method tabs (new checkout redesign)
    // -------------------------------------------------------
    var checkoutBlocks = document.querySelectorAll('.jankx-checkout-payment-methods');
    checkoutBlocks.forEach(function (block) {
        var tabs   = block.querySelectorAll('.jankx-payment-tab');
        var panels = block.querySelectorAll('.jankx-payment-panel');
        var radios = block.querySelectorAll('.jankx-payment-radio');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var method = tab.getAttribute('data-method');

                // Update tabs
                tabs.forEach(function (t) {
                    t.classList.remove('jankx-payment-tab--active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('jankx-payment-tab--active');
                tab.setAttribute('aria-selected', 'true');

                // Update panels
                panels.forEach(function (p) {
                    if (p.getAttribute('data-method') === method) {
                        p.classList.add('jankx-payment-panel--active');
                        p.hidden = false;
                    } else {
                        p.classList.remove('jankx-payment-panel--active');
                        p.hidden = true;
                    }
                });

                // Check the corresponding radio
                radios.forEach(function (r) {
                    r.checked = (r.value === method);
                });
            });
        });
    });

    // -------------------------------------------------------
    // Section toggle (collapse / expand)
    // -------------------------------------------------------
    document.querySelectorAll('.jankx-section-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var section = btn.closest('.jankx-checkout-section');
            if (!section) return;
            var body = section.querySelector('.jankx-section-body');
            if (!body) return;
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            body.hidden = expanded;
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            // Rotate chevron
            btn.style.transform = expanded ? 'rotate(180deg)' : '';
        });
    });

    // -------------------------------------------------------
    // Submit button: show total from hidden grand-total element
    // (already embedded in button HTML via PHP)
    // -------------------------------------------------------
    var placeOrderBtn = document.querySelector('.jankx-btn-place-order');
    if (placeOrderBtn) {
        // Listen to credits/coupon updates and refresh the displayed total
        document.addEventListener('jankx:cart:updated', function (e) {
            var totalEl = document.querySelector('.jankx-review-total-value');
            var btnTotal = placeOrderBtn.querySelector('.jankx-btn-total');
            if (totalEl && btnTotal) {
                btnTotal.textContent = totalEl.textContent;
            }
        });
    }

    // Coupon "Áp dụng" button (hook into existing coupon REST if available)
    var couponBtn = document.getElementById('jankx-apply-coupon');
    if (couponBtn) {
        couponBtn.addEventListener('click', function () {
            var input   = document.getElementById('jankx_coupon_code');
            var msgEl   = document.querySelector('.jankx-coupon-message');
            if (!input || !input.value.trim()) return;

            couponBtn.disabled = true;
            if (msgEl) { msgEl.textContent = ''; }

            getJson({
                url: window.jankxEcommerce.restUrl + '/coupon/apply',
                method: 'POST',
                body: { code: input.value.trim() }
            }).then(function (response) {
                couponBtn.disabled = false;
                if (!response.success) {
                    if (msgEl) { msgEl.textContent = response.message || 'Mã không hợp lệ.'; }
                    return;
                }
                // Refresh discount rows
                var discountRow = document.querySelector('.jankx-review-discount-row');
                var discountVal = document.querySelector('.jankx-review-discount-value');
                if (discountRow && discountVal && response.discount_formatted) {
                    discountVal.textContent = '-' + response.discount_formatted;
                    discountRow.hidden = false;
                }
                var totalVal = document.querySelector('.jankx-review-total-value');
                if (totalVal && response.total_formatted) {
                    totalVal.textContent = response.total_formatted;
                    var btnTotal = document.querySelector('.jankx-btn-total');
                    if (btnTotal) btnTotal.textContent = response.total_formatted;
                }
                if (msgEl) {
                    msgEl.style.color = '#27ae60';
                    msgEl.textContent = response.message || 'Áp dụng thành công!';
                }
                document.dispatchEvent(new CustomEvent('jankx:cart:updated'));
            }).catch(function () {
                couponBtn.disabled = false;
                if (msgEl) { msgEl.textContent = 'Có lỗi xảy ra. Vui lòng thử lại.'; }
            });
        });
    }

    // -------------------------------------------------------
    // Payment result redirect
    // On the order detail page while a payment is still pending,
    // poll the order status. When it reaches a final state (paid,
    // failed, cancelled, refunded), redirect to the payment
    // result page which renders the matching block.
    // -------------------------------------------------------
    var odEl = document.querySelector('.jankx-od');
    if (odEl) {
        var orderNumber = odEl.getAttribute('data-order-number');
        var orderStatus = odEl.getAttribute('data-order-status');
        var resultUrl = window.jankxEcommerce.resultUrl;

        var pollingStatuses = ['pending', 'processing', 'shipping'];
        var terminalStatuses = ['completed', 'failed', 'cancelled', 'refunded'];
        var MAX_ATTEMPTS = 120;

        function initPaymentResultPoller(number, status) {
            if (!number || !resultUrl) {
                return;
            }

            var attempts = 0;
            var timer = null;

            function stop() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            function check() {
                getJson({
                    url: window.jankxEcommerce.restUrl + '/orders/' + encodeURIComponent(number),
                    method: 'GET'
                }).then(function (data) {
                    if (!data.success || !data.order) {
                        stop();
                        return;
                    }
                    if (terminalStatuses.indexOf(data.order.status) !== -1) {
                        stop();
                        window.location.href = resultUrl
                            + (resultUrl.indexOf('?') !== -1 ? '&' : '?')
                            + 'order_number=' + encodeURIComponent(number);
                        return;
                    }
                }).catch(function () {
                    attempts += 1;
                    if (attempts >= MAX_ATTEMPTS) {
                        stop();
                    }
                });
            }

            check();
            timer = setInterval(check, 5000);
        }

        if (orderNumber && pollingStatuses.indexOf(orderStatus) !== -1) {
            initPaymentResultPoller(orderNumber, orderStatus);
        }
    }

    // ── QR Viet Payment Modal ──────────────────────────────────────────────────

    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(function () {
            var orig = btn.innerHTML;
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
            setTimeout(function () { btn.innerHTML = orig; }, 1500);
        });
    }

    function buildCopyBtn(text) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'jankx-qr-modal__copy-btn';
        btn.title = 'Sao chép';
        btn.setAttribute('aria-label', 'Sao chép');
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
        btn.addEventListener('click', function () { copyToClipboard(text, btn); });
        return btn;
    }

    function showQrModal(response) {
        var order = response.order || {};
        var bank = response.bank_info || {};
        var qrImage = response.qr_image || '';
        var orderNumber = order.order_number || '';
        var amount = order.formatted_total || (order.total ? Number(order.total).toLocaleString('vi-VN') + '₫' : '');
        var bankName = bank.bank_name || bank.bank_code || '';
        var bankAccount = bank.bank_account || '';
        var accountName = bank.account_name || '';
        var transferContent = bank.transfer_content || response.qr_code || '';
        var ordersUrl = (window.jankxEcommerce && window.jankxEcommerce.ordersUrl) || '';

        var backdrop = document.createElement('div');
        backdrop.className = 'jankx-qr-modal__backdrop';

        var modal = document.createElement('div');
        modal.className = 'jankx-qr-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Thanh toán QR');

        var countdownTimer, pollTimer;
        var closeModal = function () {
            if (countdownTimer) { clearInterval(countdownTimer); }
            if (pollTimer)      { clearInterval(pollTimer); }
            if (document.body.contains(backdrop)) { document.body.removeChild(backdrop); }
            if (ordersUrl) { window.location.href = ordersUrl; }
        };
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) { closeModal(); }
        });
        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', escHandler); }
        });

        var header = document.createElement('div');
        header.className = 'jankx-qr-modal__header';
        var headerLeft = document.createElement('div');
        headerLeft.innerHTML = '<strong class="jankx-qr-modal__title">Thanh toán QR</strong>'
            + '<span class="jankx-qr-modal__subtitle">Thông tin thanh toán được cập nhật tự động.</span>';
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'jankx-qr-modal__close';
        closeBtn.setAttribute('aria-label', 'Đóng');
        closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        closeBtn.addEventListener('click', closeModal);
        header.appendChild(headerLeft);
        header.appendChild(closeBtn);

        var body = document.createElement('div');
        body.className = 'jankx-qr-modal__body';

        var leftCol = document.createElement('div');
        leftCol.className = 'jankx-qr-modal__left';
        var amountEl = document.createElement('div');
        amountEl.className = 'jankx-qr-modal__amount';
        amountEl.textContent = amount;
        var qrImg = document.createElement('img');
        qrImg.src = qrImage;
        qrImg.alt = 'QR - ' + orderNumber;
        qrImg.width = 220;
        qrImg.height = 220;
        qrImg.className = 'jankx-qr-modal__qr-img';
        var scanHint = document.createElement('p');
        scanHint.className = 'jankx-qr-modal__scan-hint';
        scanHint.textContent = 'Quét mã bằng ứng dụng ngân hàng';
        var countdown = document.createElement('span');
        countdown.className = 'jankx-qr-modal__countdown';
        leftCol.appendChild(amountEl);
        leftCol.appendChild(qrImg);
        leftCol.appendChild(scanHint);
        leftCol.appendChild(countdown);

        var rightCol = document.createElement('div');
        rightCol.className = 'jankx-qr-modal__right';
        var infoTitle = document.createElement('p');
        infoTitle.className = 'jankx-qr-modal__info-title';
        infoTitle.textContent = 'Thông tin chuyển khoản';
        rightCol.appendChild(infoTitle);

        function makeRow(label, value, copyable) {
            var row = document.createElement('div');
            row.className = 'jankx-qr-modal__info-row';
            var lbl = document.createElement('span');
            lbl.className = 'jankx-qr-modal__info-label';
            lbl.textContent = label;
            var valWrap = document.createElement('div');
            valWrap.className = 'jankx-qr-modal__info-val-wrap';
            var val = document.createElement('span');
            val.className = 'jankx-qr-modal__info-val';
            val.textContent = value || '-';
            valWrap.appendChild(val);
            if (copyable && value) { valWrap.appendChild(buildCopyBtn(value)); }
            row.appendChild(lbl);
            row.appendChild(valWrap);
            return row;
        }

        if (bankName)        rightCol.appendChild(makeRow('Ngân hàng', bankName, false));
        if (accountName)     rightCol.appendChild(makeRow('Chủ tài khoản', accountName, false));
        if (bankAccount)     rightCol.appendChild(makeRow('Số tài khoản', bankAccount, true));
        if (amount)          rightCol.appendChild(makeRow('Số tiền', amount, true));
        if (transferContent) rightCol.appendChild(makeRow('Nội dung chuyển khoản', transferContent, true));

        body.appendChild(leftCol);
        body.appendChild(rightCol);
        modal.appendChild(header);
        modal.appendChild(body);
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        var totalSeconds = 15 * 60;
        countdown.textContent = 'Còn 15 phút 00 giây';
        countdownTimer = setInterval(function () {
            totalSeconds -= 1;
            if (totalSeconds <= 0) { clearInterval(countdownTimer); countdown.textContent = 'Mã QR đã hết hạn'; return; }
            var m = Math.floor(totalSeconds / 60);
            var s = totalSeconds % 60;
            countdown.textContent = 'Còn ' + m + ' phút ' + (s < 10 ? '0' : '') + s + ' giây';
        }, 1000);

        if (orderNumber) {
            var pollCount = 0;
            pollTimer = setInterval(function () {
                pollCount += 1;
                if (pollCount >= 180) { clearInterval(pollTimer); return; }
                var pollUrl = window.jankxEcommerce.restUrl + '/orders/' + encodeURIComponent(orderNumber);
                getJson({ url: pollUrl, method: 'GET' }).then(function (data) {
                    var st = (data.order || {}).status || '';
                    if (['completed', 'processing', 'failed', 'cancelled'].indexOf(st) !== -1) {
                        clearInterval(pollTimer);
                        clearInterval(countdownTimer);
                        if (document.body.contains(backdrop)) { document.body.removeChild(backdrop); }
                        if (ordersUrl) { window.location.href = ordersUrl; }
                    }
                }).catch(function () {});
            }, 5000);
        }
    }

})();
