<?php
namespace Jankx\Extensions\Ecommerce\Rest;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutManager;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderCreationManager;
use Jankx\Extensions\Ecommerce\Payment\PaymentManager;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

/**
 * REST API for the shared cart & checkout flow.
 *
 * Routes:
 *   GET    /wp-json/jankx/ecommerce/v1/cart
 *   POST   /wp-json/jankx/ecommerce/v1/cart/items
 *   DELETE /wp-json/jankx/ecommerce/v1/cart/items/{item_key}
 *   POST   /wp-json/jankx/ecommerce/v1/cart/items/{item_key}/quantity
 *   POST   /wp-json/jankx/ecommerce/v1/coupon/apply
 *   POST   /wp-json/jankx/ecommerce/v1/coupon/remove
 *   POST   /wp-json/jankx/ecommerce/v1/checkout
 *   GET    /wp-json/jankx/ecommerce/v1/orders/{order_number}
 *   POST   /wp-json/jankx/ecommerce/v1/orders/{order_number}/pay
 *   POST   /wp-json/jankx/ecommerce/v1/orders/{order_number}/cancel
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

        register_rest_route(self::REST_NAMESPACE, '/orders/(?P<order_number>[a-zA-Z0-9_-]+)/cancel', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'cancelOrder'],
            'permission_callback' => [$this, 'payOrderPermissionCheck'],
            'args'                => [
                'order_number' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/orders/(?P<order_number>[a-zA-Z0-9_-]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getOrder'],
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

        register_rest_route(self::REST_NAMESPACE, '/coupon/apply', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'applyCoupon'],
            'permission_callback' => '__return_true',
            'args'                => [
                'code' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/coupon/remove', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'removeCoupon'],
            'permission_callback' => '__return_true',
            'args'                => [
                'code' => [
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

    /**
     * Apply a coupon code to the current cart.
     *
     * The apply logic is provided by coupon extensions through the
     * `jankx/ecommerce/cart/coupon/apply` filter. The response shape is
     * stable so frontends can render the discounted total right away.
     */
    public function applyCoupon(\WP_REST_Request $request): \WP_REST_Response
    {
        $code = trim((string) $request->get_param('code'));
        if ($code === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Vui lòng nhập mã giảm giá.', 'base-ecommerce'),
            ], 400);
        }

        $result = Cart::get_instance()->applyCoupon($code);
        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return rest_ensure_response($this->couponResponse($result));
    }

    /**
     * Remove an applied coupon from the current cart.
     */
    public function removeCoupon(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = Cart::get_instance()->removeAppliedCoupon(
            (string) $request->get_param('code')
        );

        return rest_ensure_response($this->couponResponse($result));
    }

    /**
     * Build a normalized coupon response with the refreshed cart totals.
     *
     * @param array{success: bool, message: string} $result
     * @return array<string, mixed>
     */
    protected function couponResponse(array $result): array
    {
        $cart             = Cart::get_instance();
        $converterManager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();

        return array_merge($result, [
            'coupons'                    => $cart->getAppliedCoupons(),
            'coupon_discount'            => $cart->getCouponDiscount(),
            'coupon_discount_formatted'  => $converterManager->formatPriceWithConversion($cart->getCouponDiscount()),
            'discount_formatted'         => $converterManager->formatPriceWithConversion($cart->getDiscount()),
            'total_formatted'            => $converterManager->formatPriceWithConversion($cart->getTotal()),
            'cart'                       => $cart->toArray(),
        ]);
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
                'message' => $this->describeAddFailure([
                    (int) $request->get_param('product_id'),
                ]),
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
     * Friendlier rejection message when the cart refuses a line: a product
     * without a configured price is the common actionable case, everything
     * else keeps the generic message.
     *
     * @param int[] $productIds
     */
    protected function describeAddFailure(array $productIds): string
    {
        foreach (array_unique(array_map('intval', $productIds)) as $productId) {
            if ($productId <= 0) {
                continue;
            }

            $product = ProductRegistry::get_instance()->createProduct($productId);
            if ($product && $product->getPrice() <= 0) {
                return sprintf(
                    __('Sản phẩm "%s" chưa được cấu hình giá.', 'jankx'),
                    $product->getName()
                );
            }
        }

        return __('Sản phẩm không hợp lệ hoặc không thể mua.', 'jankx');
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
                'message' => $this->describeAddFailure(
                    array_column($normalized, 'product_id')
                ),
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

        // Terms acceptance is enforced only when required by the Payment settings.
        if (get_option('jankx_require_terms_acceptance') && !$request->get_param('accept_terms')) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => [__('Bạn phải đồng ý với Điều khoản sử dụng và Chính sách hoàn hủy để tạo đơn hàng.', 'base-ecommerce')],
            ], 400);
        }

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

        if (!empty($result['payment_status'])) {
            $response['payment_status'] = $result['payment_status'];
        }
        if (!empty($result['qr_image'])) {
            $response['qr_image'] = $result['qr_image'];
        }
        if (!empty($result['qr_code'])) {
            $response['qr_code'] = $result['qr_code'];
        }

        if (($result['payment_status'] ?? '') === 'qr') {
            $gatewayConfig = apply_filters('jankx/payment/gateway/qrviet/default_config', []);
            $savedConfig = get_option('jankx_payment_gateway_qrviet', []);
            $config = array_merge($gatewayConfig, $savedConfig);
            $isTest = !empty($config['testMode']);
            $prefix = $isTest ? 'sandbox' : 'production';

            $bankCode    = (string) ($config["{$prefix}_bank_code"]    ?? '');
            $bankAccount = (string) ($config["{$prefix}_bank_account"] ?? '');
            $accountName = (string) ($config["{$prefix}_account_name"] ?? '');

            $bankNames = [
                'ACB'  => 'ACB',        'VCB'  => 'Vietcombank', 'TCB' => 'Techcombank',
                'MB'   => 'MBBank',     'VPB'  => 'VPBank',      'VIB' => 'VIB',
                'MSB'  => 'MSB',        'TPB'  => 'TPBank',      'OCB' => 'OCB',
                'BIDV' => 'BIDV',       'VTB'  => 'Vietinbank',  'AGR' => 'Agribank',
                'SHB'  => 'SHB',        'HDB'  => 'HDBank',      'SCB' => 'SCB',
            ];
            $bankName = $bankNames[strtoupper($bankCode)] ?? strtoupper($bankCode);

            // Ưu tiên đọc nội dung chuyển khoản đã được lưu vào transaction meta
            // (ổn định theo session — không tính lại mỗi request).
            $transferContent = '';
            $transactionId = $result['transaction_id'] ?? 0;
            if ($transactionId > 0) {
                $transaction = new \Jankx\Extensions\PaymentSystem\Models\Transaction((int) $transactionId);
                if ($transaction->getId()) {
                    $transferContent = $transaction->getMeta('_transfer_content');
                }
            }
            // Fallback sang giá trị từ gateway nếu transaction chưa được persist
            if ($transferContent === '') {
                $transferContent = (string) ($result['qr_transfer_content'] ?? '');
            }

            $response['bank_info'] = [
                'bank_code'        => $bankCode,
                'bank_name'        => $bankName,
                'bank_account'     => $bankAccount,
                'account_name'     => $accountName,
                'transfer_content' => $transferContent,
            ];
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
     * Cancel an order owned by the current customer (pending/processing only).
     */
    public function cancelOrder(\WP_REST_Request $request): \WP_REST_Response
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
                'message' => __('Đơn hàng này không thể hủy.', 'base-ecommerce'),
            ], 400);
        }

        $allowed = Order::getAllowedStatusTransitionsFor($status);
        if (!in_array(Order::STATUS_CANCELLED, $allowed, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Đơn hàng này không thể hủy.', 'base-ecommerce'),
            ], 400);
        }

        $cancelled = $order->updateStatus(
            Order::STATUS_CANCELLED,
            __('Khách hàng yêu cầu hủy đơn hàng.', 'base-ecommerce')
        );

        if (!$cancelled) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không thể hủy đơn hàng. Vui lòng thử lại.', 'base-ecommerce'),
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Đơn hàng đã được hủy.', 'base-ecommerce'),
        ], 200);
    }

    /**
     * Get order summary (status) for the current owner.
     *
     * Used by the order detail page poller to detect when a payment
     * transitions to the "paid" state and redirect to the success screen.
     */
    public function getOrder(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderNumber = $request->get_param('order_number');
        $order = Order::findByOrderNumber($orderNumber);

        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Đơn hàng không tồn tại.', 'base-ecommerce'),
            ], 404);
        }

        return rest_ensure_response([
            'success' => true,
            'order'   => [
                'order_number' => $order->getOrderNumber(),
                'status'       => $order->getStatus(),
                'status_label' => Order::getStatusLabel($order->getStatus()),
            ],
        ]);
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
                'success'        => true,
                'type'           => 'bank_transfer',
                'message'        => $this->getBankTransferInfo($order),
                'order_number'   => $order->getOrderNumber(),
                'payment_content' => \Jankx\Extensions\Ecommerce\jankx_payment_content($order),
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

        // QR payment: return the QR payload to render inline (no redirect).
        if (!empty($result['payment_status']) && $result['payment_status'] === 'qr') {
            $qrBankInfo = apply_filters('jankx/ecommerce/qr_payment/bank_info', [], $gateway, $order);

            return rest_ensure_response([
                'success'           => true,
                'type'              => 'qr',
                'qr_image'          => $result['qr_image'] ?? '',
                'qr_code'           => $result['qr_code'] ?? '',
                'qr_transfer_content' => $result['qr_transfer_content'] ?? '',
                'order_number'      => $order->getOrderNumber(),
                'order'             => [
                    'order_number'    => $order->getOrderNumber(),
                    'total'           => $order->getTotal(),
                    'formatted_total' => number_format((int) $order->getTotal(), 0, ',', '.') . '₫',
                ],
                'bank_info'         => $qrBankInfo,
            ]);
        }

        // Failed gateway attempt: surface the real reason instead of a generic 500.
        if (($result['payment_status'] ?? '') === 'failed') {
            $code = (string) ($result['error_code'] ?? '');
            $message = (string) ($result['error'] ?? '');
            if ($code === 'GATEWAY_NOT_AVAILABLE' || $code === 'GATEWAY_NOT_FOUND') {
                $message = __('Cổng thanh toán chưa được cấu hình. Vui lòng liên hệ quản trị.', 'base-ecommerce');
            } elseif ($code === 'AUTH_FAILED') {
                $detail = trim((string) ($result['raw']['detail'] ?? ''));
                $message = $detail !== ''
                    ? sprintf(__('Không thể xác thực cổng thanh toán: %s', 'base-ecommerce'), $detail)
                    : __('Không thể xác thực cổng thanh toán. Vui lòng kiểm tra cấu hình gateway.', 'base-ecommerce');
            } elseif ($message === '') {
                $message = __('Cổng thanh toán tạm thời lỗi. Vui lòng thử lại sau.', 'base-ecommerce');
            }
            if ($code !== '') {
                $message .= ' (' . $code . ')';
            }

            error_log(sprintf(
                '[jankx/payOrder] order=%s gateway=%s code=%s message=%s raw=%s',
                $order->getOrderNumber(),
                $gateway,
                $code,
                (string) ($result['error'] ?? ''),
                wp_json_encode($result['raw'] ?? [])
            ));

            return new \WP_REST_Response([
                'success'    => false,
                'message'    => $message,
                'error_code' => $code,
            ], 400);
        }

        if (!empty($result['redirect_url'])) {
            return rest_ensure_response([
                'success'      => true,
                'type'         => 'online',
                'redirect_url' => $result['redirect_url'],
            ]);
        }

        $message = (string) ($result['error'] ?? '');
        $code = (string) ($result['error_code'] ?? '');

        error_log(sprintf(
            '[jankx/payOrder] order=%s gateway=%s code=%s message=%s raw=%s',
            $order->getOrderNumber(),
            $gateway,
            $code,
            $message,
            wp_json_encode($result['raw'] ?? [])
        ));

        if ($message !== '' || $code !== '') {
            return new \WP_REST_Response([
                'success'    => false,
                'message'    => $message !== '' ? $message : __('Không thể tạo liên kết thanh toán.', 'base-ecommerce'),
                'error_code' => $code,
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => __('Không thể tạo liên kết thanh toán. Vui lòng thử lại.', 'base-ecommerce'),
        ], 500);
    }

    protected function getBankTransferInfo(?Order $order = null): string
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
        $lines[] = $order
            ? sprintf(__('Nội dung CK: %s', 'base-ecommerce'), \Jankx\Extensions\Ecommerce\jankx_payment_content($order))
            : __('Nội dung CK: Mã đơn hàng của bạn', 'base-ecommerce');
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

