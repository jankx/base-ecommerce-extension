<?php
namespace Jankx\Extensions\Ecommerce\Checkout;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Payment\PaymentManager;

/**
 * Checkout manager.
 *
 * Turns the current cart into an order and optionally kicks off payment.
 * Any business extension can call this after registering its post type
 * with the ProductRegistry.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class CheckoutManager
{
    /**
     * @var CheckoutManager|null
     */
    protected static $instance;

    public static function get_instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Validate customer data.
     *
     * @return array List of error messages (empty = valid).
     */
    public function validateCustomer(array $customer): array
    {
        $errors = [];

        $name = trim($customer['name'] ?? '');
        $email = trim($customer['email'] ?? '');

        if (!$name) {
            $errors[] = __('Vui lòng nhập họ tên.', 'base-ecommerce');
        }
        if (!$email || !is_email($email)) {
            $errors[] = __('Vui lòng nhập email hợp lệ.', 'base-ecommerce');
        }

        return apply_filters('jankx/ecommerce/checkout/validate_customer', $errors, $customer);
    }

    /**
     * Create an order from the cart.
     *
     * @param Cart   $cart
     * @param array  $customer Customer data: id, name, email, phone, address.
     * @param array  $options  Optional: gateway, currency.
     * @return array{order: Order|null, redirect_url: string}&array<string, mixed>
     */
    public function createOrder(Cart $cart, array $customer, array $options = []): array
    {
        $order = Order::createFromCart($cart, $customer, $options);
        $redirectUrl = '';
        $paymentResult = [];

        if ($order && !empty($options['gateway'])) {
            $gateway = (string) $options['gateway'];
            $paymentResult = PaymentManager::get_instance()->process($order, $gateway, $options['payment_params'] ?? []);

            if (!empty($paymentResult['redirect_url'])) {
                $redirectUrl = $paymentResult['redirect_url'];
            }

            // QR payments have no browser redirect; send the customer to the
            // order detail page where the QR code is displayed for scanning.
            if (($paymentResult['payment_status'] ?? '') === 'qr') {
                $accountUrl = function_exists('jankx_get_account_endpoint_url')
                    ? jankx_get_account_endpoint_url('orders')
                    : home_url('/tai-khoan-cua-toi/orders/');

                $redirectUrl = rtrim($accountUrl, '/') . '/' . $order->getOrderNumber() . '/';
            }
        }

        return array_merge(
            [
                'order'        => $order,
                'redirect_url' => $redirectUrl,
            ],
            self::paymentFields($paymentResult)
        );
    }

    /**
     * Payment facts the browser needs to pick its next step (redirect, QR, ...).
     *
     * Empty values are dropped so callers keep reading missing keys as
     * "nothing happened" — the same contract as before only QR was forwarded.
     *
     * @param array $paymentResult Result of PaymentManager::process().
     * @return array<string, mixed>
     */
    protected static function paymentFields(array $paymentResult): array
    {
        $fields = [];

        $keys = [
            'payment_status',
            'payment_type',
            'qr_image',
            'qr_code',
            'qr_link',
            'qr_transfer_content',
            'transaction_id',
            'error',
            'error_code',
        ];

        foreach ($keys as $key) {
            $value = $paymentResult[$key] ?? '';

            if ($value === '' || $value === null || $value === false || $value === 0 || $value === []) {
                continue;
            }

            $fields[$key] = $value;
        }

        return $fields;
    }

    /**
     * Full checkout: validate, create order from cart, clear the cart.
     *
     * @return array{success: bool, errors: string[], order: Order|null, redirect_url: string}&array<string, mixed>
     *         Plus every non-empty payment field returned by createOrder()
     *         (payment_status, qr_*, transaction_id, error, ...).
     */
    public function checkout(Cart $cart, array $customer, array $options = []): array
    {
        if ($cart->isEmpty()) {
            return [
                'success' => false,
                'errors'  => [__('Giỏ hàng của bạn đang trống.', 'base-ecommerce')],
                'order'   => null,
                'redirect_url' => '',
            ];
        }

        $errors = $this->validateCustomer($customer);
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'order'   => null,
                'redirect_url' => '',
            ];
        }

        // Create account if requested and user is guest
        if (!empty($options['create_account']) && empty($customer['id'])) {
            $userId = $this->createAccountFromCheckout($customer);
            if ($userId) {
                $customer['id'] = $userId;
            }
        }

        $result = $this->createOrder($cart, $customer, $options);
        $order = $result['order'];

        if (!$order) {
            return [
                'success' => false,
                'errors'  => [__('Không thể tạo đơn hàng, vui lòng thử lại.', 'base-ecommerce')],
                'order'   => null,
                'redirect_url' => '',
            ];
        }

        $cart->emptyCart();

        do_action('jankx/ecommerce/checkout/completed', $order);

        // Checkouts without their own gateway/browser redirect (COD, bank
        // transfer, ...) may be redirected by extensions — e.g. straight to
        // the order detail page where payment info (VietQR) is shown.
        $redirectUrl = $result['redirect_url'];
        if ($redirectUrl === '') {
            $redirectUrl = (string) apply_filters('jankx/ecommerce/checkout/redirect_url', '', $order, $result);
        }

        return array_merge($result, [
            'success'      => true,
            'errors'       => [],
            // May have been redirected by the filter above, never by createOrder.
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Create a WordPress user account from checkout data.
     */
    protected function createAccountFromCheckout(array $customer): int
    {
        $email = sanitize_email($customer['email'] ?? '');
        $name = sanitize_text_field($customer['name'] ?? '');

        if (!$email || !is_email($email)) {
            return 0;
        }

        // Check if email already exists
        if (email_exists($email)) {
            return 0;
        }

        // Generate username from email
        $username = sanitize_user(substr($email, 0, strpos($email, '@')), true);
        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        // Generate random password
        $password = wp_generate_password(12, true);

        $userId = wp_create_user($username, $password, $email);

        if (is_wp_error($userId)) {
            return 0;
        }

        // Update display name
        wp_update_user([
            'ID' => $userId,
            'display_name' => $name ?: $username,
        ]);

        // Store phone if provided
        if (!empty($customer['phone'])) {
            update_user_meta($userId, 'phone', sanitize_text_field($customer['phone']));
        }

        // Send password email
        wp_new_user_notification($userId, $password);

        return (int) $userId;
    }
}
