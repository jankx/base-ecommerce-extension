<?php
namespace Jankx\Extensions\Ecommerce\Rest;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutManager;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
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

        register_rest_route(self::REST_NAMESPACE, '/checkout', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'checkout'],
            'permission_callback' => '__return_true',
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
    }

    public function getCart(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response(Cart::get_instance()->toArray());
    }

    public function addCartItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $args = $request->get_param('args');
        $args = is_array($args) ? array_map('sanitize_text_field', $args) : [];

        $added = Cart::get_instance()->addItem(
            (int) $request->get_param('product_id'),
            (int) $request->get_param('quantity'),
            $args
        );

        if (!$added) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Sản phẩm không hợp lệ hoặc không thể mua.', 'jankx'),
            ], 400);
        }

        return rest_ensure_response([
            'success' => true,
            'cart'    => Cart::get_instance()->toArray(),
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
                'message' => __('Không tìm thấy sản phẩm trong giỏ hàng.', 'jankx'),
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

        $result = CheckoutManager::get_instance()->checkout(
            Cart::get_instance(),
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
                'message' => __('Đơn hàng không tồn tại.', 'jankx'),
            ], 404);
        }

        $status = $order->getStatus();
        if (!in_array($status, [Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Đơn hàng này không thể thanh toán lại.', 'jankx'),
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
                    __('Đơn hàng COD sẽ được xác nhận bởi nhân viên. Vui lòng đặt cọc %s nếu được yêu cầu.', 'jankx'),
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
            'message' => __('Không thể tạo liên kết thanh toán. Vui lòng thử lại.', 'jankx'),
        ], 500);
    }

    protected function getBankTransferInfo(): string
    {
        $config = get_option('jankx_built_in_gateway_bank_transfer', []);
        $bankName = $config['bank_name'] ?? '';
        $accountNumber = $config['account_number'] ?? '';
        $accountHolder = $config['account_holder'] ?? '';
        $branch = $config['branch'] ?? '';
        $transferContent = $config['transfer_content'] ?? __('Vui lòng ghi đúng nội dung chuyển khoản.', 'jankx');

        $lines = [];
        if ($bankName) {
            $lines[] = sprintf(__('Ngân hàng: %s', 'jankx'), $bankName);
        }
        if ($accountNumber) {
            $lines[] = sprintf(__('Số tài khoản: %s', 'jankx'), $accountNumber);
        }
        if ($accountHolder) {
            $lines[] = sprintf(__('Chủ tài khoản: %s', 'jankx'), $accountHolder);
        }
        if ($branch) {
            $lines[] = sprintf(__('Chi nhánh: %s', 'jankx'), $branch);
        }
        $lines[] = '';
        $lines[] = __('Nội dung CK: Mã đơn hàng của bạn', 'jankx');
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
            return rest_ensure_response([
                'success'  => true,
                'currency' => $currency,
                'symbol'   => CurrencyManager::getCurrency($currency)['symbol'] ?? $currency,
            ]);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => __('Tiền tệ không hợp lệ.', 'jankx'),
        ], 400);
    }
}
