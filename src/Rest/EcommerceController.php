<?php
namespace Jankx\Extensions\Ecommerce\Rest;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutManager;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderCreationManager;
use Jankx\Extensions\Ecommerce\Payment\PaymentManager;

/**
 * REST API for the shared cart & checkout flow.
 *
 * Routes:
 *   GET    /wp-json/jankx/ecommerce/v1/cart
 *   POST   /wp-json/jankx/ecommerce/v1/cart/items
 *   DELETE /wp-json/jankx/ecommerce/v1/cart/items/{item_key}
 *   POST   /wp-json/jankx/ecommerce/v1/checkout
 *   POST   /wp-json/jankx/ecommerce/v1/orders/{order_number}/pay
 *
 * @package Jankx\Extensions\Ecommerce
 */
class EcommerceController
{
    const REST_NAMESPACE = 'jankx/ecommerce/v1';

    public function register_routes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/cart', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getCart'],
            'permission_callback' => '__return_true',
            'args'                => [
                'mode' => [
                    'type'              => 'string',
                    'default'           => 'normal',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/items', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'addCartItem'],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'quantity' => [
                    'default'           => 1,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'mode' => [
                    'type'              => 'string',
                    'default'           => 'normal',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/items/batch', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'addCartItemBatch'],
            'permission_callback' => '__return_true',
            'args'                => [
                'lines' => [
                    'required'          => true,
                    'type'              => 'array',
                    'sanitize_callback' => function ($value) {
                        return is_array($value) ? $value : [];
                    },
                ],
                'args' => [
                    'type'              => 'object',
                    'sanitize_callback' => function ($value) {
                        return is_array($value) ? $this->sanitizeArgs($value) : [];
                    },
                ],
                'mode' => [
                    'type'              => 'string',
                    'default'           => 'normal',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/items/(?P<item_key>[a-zA-Z0-9]+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'removeCartItem'],
            'permission_callback' => '__return_true',
            'args'                => [
                'item_key' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/cart/items/(?P<item_key>[a-zA-Z0-9]+)/quantity', [
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'updateCartItemQuantity'],
            'permission_callback' => '__return_true',
            'args'                => [
                'item_key' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'quantity' => [
                    'required' => true,
                    'type'     => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/checkout', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'checkout'],
            'permission_callback' => '__return_true',
            'args'                => [
                'mode' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/orders/(?P<order_number>[a-zA-Z0-9_-]+)/pay', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'payOrder'],
            'permission_callback' => [$this, 'payOrderPermissionCheck'],
            'args'                => [
                'order_number' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/currency', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getCurrencies'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::REST_NAMESPACE, '/currency/switch', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'switchCurrency'],
            'permission_callback' => '__return_true',
            'args'                => [
                'currency' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/orders/form', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'createFormOrder'],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'customer_name' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'customer_phone' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'customer_email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'note' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'quantity' => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function getCart(\WP_REST_Request $request): \WP_REST_Response
    {
        $mode = $request->get_param('mode');
        if ($mode === 'quick') {
            return rest_ensure_response(Cart::get_active_cart()->toArray());
        }

        return rest_ensure_response(Cart::get_instance()->toArray());
    }

    public function addCartItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $args = $request->get_param('args');
        $args = is_array($args) ? $this->sanitizeArgs($args) : [];
        $mode = $request->get_param('mode');

        if ($mode === 'quick') {
            $cart = Cart::get_active_cart();
            // Switch scope BEFORE emptying so we replace the quick session,
            // never the main cart.
            $cart->setScope(Cart::SCOPE_QUICK);
            $cart->emptyCart();

            $added = $cart->addItem(
                (int) $request->get_param('product_id'),
                (int) $request->get_param('quantity'),
                $args
            );

            if ($added) {
                Cart::enableQuickMode();
            }
        } else {
            $added = Cart::get_instance()->addItem(
                (int) $request->get_param('product_id'),
                (int) $request->get_param('quantity'),
                $args
            );

            $cart = Cart::get_instance();
        }

        if (!$added) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Sản phẩm không hợp lệ hoặc không thể mua.', 'base-ecommerce'),
            ], 400);
        }

        return rest_ensure_response([
            'success' => true,
            'mode'    => $mode,
            'cart'    => $cart->toArray(),
        ]);
    }

    /**
     * Recursively sanitize cart item args so nested maps (e.g. group_qty)
     * survive the request without being flattened.
     */
    protected function sanitizeArgs(array $args): array
    {
        $clean = [];
        foreach ($args as $key => $value) {
            if (is_array($value)) {
                $clean[sanitize_key((string) $key)] = $this->sanitizeArgs($value);
            } else {
                $clean[sanitize_key((string) $key)] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }

    /**
     * Add multiple cart lines in a single request – each variation (e.g.
     * adult/child ticket on a tour date) becomes its own line item.
     *
     * @param \WP_REST_Request $request
     */
    public function addCartItemBatch(\WP_REST_Request $request): \WP_REST_Response
    {
        $lines = (array) $request->get_param('lines');
        $commonArgs = $request->get_param('args');
        $commonArgs = is_array($commonArgs) ? $this->sanitizeArgs($commonArgs) : [];

        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int) ($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $normalized[] = [
                'product_id'      => $productId,
                'quantity'        => max(0, (int) ($line['quantity'] ?? $line['qty'] ?? 1)),
                'variation_id'    => sanitize_text_field((string) ($line['variation_id'] ?? '')),
                'variation_label' => sanitize_text_field((string) ($line['variation_label'] ?? '')),
            ];
        }

        if (empty($normalized)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không có sản phẩm nào để thêm vào giỏ hàng.', 'jankx'),
            ], 400);
        }

        $mode = $request->get_param('mode');
        if ($mode === 'quick') {
            $added = Cart::get_active_cart()->quickAddItems($normalized, $commonArgs);
            $cart = Cart::get_active_cart();
        } else {
            $added = Cart::get_instance()->addItems($normalized, $commonArgs);
            $cart = Cart::get_instance();
        }

        if ($added === 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Sản phẩm không hợp lệ hoặc không thể mua.', 'jankx'),
            ], 400);
        }

        return rest_ensure_response([
            'success' => true,
            'mode'    => $mode,
            'added'   => $added,
            'cart'    => $cart->toArray(),
        ]);
    }

    public function removeCartItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $removed = Cart::get_instance()->removeItem(
            (string) $request->get_param('item_key')
        );

        if (!$removed) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy sản phẩm trong giỏ hàng.', 'base-ecommerce'),
            ], 404);
        }

        return rest_ensure_response([
            'success' => true,
            'cart'    => Cart::get_instance()->toArray(),
        ]);
    }

    public function updateCartItemQuantity(\WP_REST_Request $request): \WP_REST_Response
    {
        $updated = Cart::get_instance()->updateItem(
            (string) $request->get_param('item_key'),
            (int) $request->get_param('quantity')
        );

        if (!$updated) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không tìm thấy sản phẩm trong giỏ hàng.', 'base-ecommerce'),
            ], 404);
        }

        return rest_ensure_response([
            'success' => true,
            'cart'    => Cart::get_instance()->toArray(),
        ]);
    }

    public function checkout(\WP_REST_Request $request): \WP_REST_Response
    {
        $customer = $request->get_param('customer');
        $customer = is_array($customer) ? array_map('sanitize_text_field', $customer) : [];

        $customer['id'] = get_current_user_id();

        $gateway = sanitize_key((string) $request->get_param('gateway'));
        $params = $request->get_param('payment_params');
        $params = is_array($params) ? $params : [];

        $createAccount = (bool) $request->get_param('create_account');

        $cart = Cart::get_active_cart();
        if ($request->get_param('mode') === 'quick') {
            $cart->setScope(Cart::SCOPE_QUICK);
        }

        $result = CheckoutManager::get_instance()->checkout(
            $cart,
            $customer,
            [
                'gateway'        => $gateway,
                'payment_params' => $params,
                'create_account' => $createAccount,
            ]
        );

        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['errors'],
            ], 400);
        }

        if ($cart->isQuickScope()) {
            Cart::disableQuickMode();
        }

        /** @var \Jankx\Extensions\Ecommerce\Order\Order $order */
        $order = $result['order'];

        $response = [
            'success' => true,
            'order'   => $order->toArray(),
        ];

        if (!empty($result['redirect_url'])) {
            $response['redirect_url'] = $result['redirect_url'];
        }

        return rest_ensure_response($response);
    }

    /**
     * Check if the current user can pay for the order.
     */
    public function payOrderPermissionCheck(\WP_REST_Request $request): bool
    {
        $orderNumber = $request->get_param('order_number');
        $order = Order::findByOrderNumber($orderNumber);

        if (!$order) {
            return false;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return false;
        }

        $customerId = (int) $order->getCustomerId();
        $userId = (int) $user->ID;

        // Allow if user owns the order or if customer_id is 0 (guest order matched by email)
        if ($customerId === 0) {
            return strcasecmp($order->getCustomerEmail(), $user->user_email) === 0;
        }

        return $customerId === $userId;
    }

    /**
     * Pay for an existing order.
     *
     * For online gateways: returns redirect_url.
     * For bank_transfer / COD: returns info message.
     */
    public function payOrder(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderNumber = $request->get_param('order_number');
        $order = Order::findByOrderNumber($orderNumber);

        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Đơn hàng không tồn tại.', 'base-ecommerce'),
            ], 404);
        }

        $status = $order->getStatus();
        if (!in_array($status, [Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Đơn hàng này không thể thanh toán lại.', 'base-ecommerce'),
            ], 400);
        }

        $gateway = $order->getPaymentMethod();
        $paymentManager = PaymentManager::get_instance();

        // Bank transfer: return info, no redirect
        if ($gateway === 'bank_transfer') {
            return rest_ensure_response([
                'success' => true,
                'type'    => 'bank_transfer',
                'message' => $this->getBankTransferInfo(),
            ]);
        }

        // COD: return confirmation message
        if ($gateway === 'cod') {
            return rest_ensure_response([
                'success' => true,
                'type'    => 'cod',
                'message' => sprintf(
                    __('Đơn hàng COD sẽ được xác nhận bởi nhân viên. Vui lòng đặt cọc %s nếu được yêu cầu.', 'base-ecommerce'),
                    CurrencyManager::formatPrice($order->getTotal() * 0.3)
                ),
            ]);
        }

        // Online payment: process via gateway
        $result = $paymentManager->process($order, $gateway, [
            'return_url' => add_query_arg('order', $order->getOrderNumber(), home_url('/tai-khoan-cua-toi/orders/')),
        ]);

        if (!empty($result['redirect_url'])) {
            return rest_ensure_response([
                'success'      => true,
                'type'         => 'online',
                'redirect_url' => $result['redirect_url'],
            ]);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => __('Không thể tạo liên kết thanh toán. Vui lòng thử lại.', 'base-ecommerce'),
        ], 500);
    }

    protected function getBankTransferInfo(): string
    {
        $config = get_option('jankx_built_in_gateway_bank_transfer', []);
        $bankName = $config['bank_name'] ?? '';
        $accountNumber = $config['account_number'] ?? '';
        $accountHolder = $config['account_holder'] ?? '';
        $branch = $config['branch'] ?? '';
        $transferContent = $config['transfer_content'] ?? __('Vui lòng ghi đúng nội dung chuyển khoản.', 'base-ecommerce');

        $lines = [];
        if ($bankName) {
            $lines[] = sprintf(__('Ngân hàng: %s', 'base-ecommerce'), $bankName);
        }
        if ($accountNumber) {
            $lines[] = sprintf(__('Số tài khoản: %s', 'base-ecommerce'), $accountNumber);
        }
        if ($accountHolder) {
            $lines[] = sprintf(__('Chủ tài khoản: %s', 'base-ecommerce'), $accountHolder);
        }
        if ($branch) {
            $lines[] = sprintf(__('Chi nhánh: %s', 'base-ecommerce'), $branch);
        }
        $lines[] = '';
        $lines[] = __('Nội dung CK: Mã đơn hàng của bạn', 'base-ecommerce');
        if ($transferContent) {
            $lines[] = '';
            $lines[] = $transferContent;
        }

        return implode("\n", $lines);
    }

    public function getCurrencies(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response([
            'current'  => CurrencyManager::getDefaultCurrency(),
            'selected' => CurrencyManager::getCurrentCurrency(),
            'enabled'  => CurrencyManager::getEnabledCurrenciesList(),
            'all'      => CurrencyManager::getAllCurrencies(),
            'position' => CurrencyManager::getCurrencyPosition(),
        ]);
    }

    public function switchCurrency(\WP_REST_Request $request): \WP_REST_Response
    {
        $currency = sanitize_text_field($request->get_param('currency'));

        if (CurrencyManager::setCurrentCurrency($currency)) {
            $response = rest_ensure_response([
                'success'  => true,
                'currency' => $currency,
                'symbol'   => CurrencyManager::getCurrency($currency)['symbol'] ?? $currency,
            ]);

            $cookiePath = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
            $cookieDomain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
            $cookieHeader = sprintf(
                '%s=%s; expires=%s; Max-Age=%d; path=%s; SameSite=Lax',
                CurrencyManager::SESSION_KEY,
                $currency,
                gmdate('D, d-M-Y H:i:s T', time() + 30 * DAY_IN_SECONDS),
                30 * DAY_IN_SECONDS,
                $cookiePath
            );
            if (!empty($cookieDomain)) {
                $cookieHeader .= '; domain=' . $cookieDomain;
            }
            if (is_ssl()) {
                $cookieHeader .= '; Secure';
            }
            $response->header('Set-Cookie', $cookieHeader, false);

            return $response;
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => __('Tiền tệ không hợp lệ.', 'base-ecommerce'),
        ], 400);
    }

    /**
     * Create an order directly from a product form submission.
     * Uses the ManualFormOrderStrategy via OrderCreationManager.
     */
    public function createFormOrder(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_params();
        $result = OrderCreationManager::getInstance()->process('manual_form', $params);

        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['errors'],
            ], 400);
        }

        /** @var \Jankx\Extensions\Ecommerce\Order\Order $order */
        $order = $result['order'];

        return rest_ensure_response([
            'success' => true,
            'message' => __('Gửi thông tin đặt sản phẩm thành công! Chúng tôi sẽ liên hệ với bạn trong thời gian sớm nhất.', 'base-ecommerce'),
            'order'   => $order ? $order->toArray() : null,
        ]);
    }
}

