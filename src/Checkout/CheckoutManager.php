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
            $errors[] = __('Vui lòng nhập họ tên.', 'jankx');
        }
        if (!$email || !is_email($email)) {
            $errors[] = __('Vui lòng nhập email hợp lệ.', 'jankx');
        }

        return apply_filters('jankx/ecommerce/checkout/validate_customer', $errors, $customer);
    }

    /**
     * Create an order from the cart.
     *
     * @param Cart   $cart
     * @param array  $customer Customer data: id, name, email, phone, address.
     * @param array  $options  Optional: gateway, currency.
     * @return array{order: Order|null, redirect_url: string}
     */
    public function createOrder(Cart $cart, array $customer, array $options = []): array
    {
        $order = Order::createFromCart($cart, $customer, $options);
        $redirectUrl = '';

        if ($order && !empty($options['gateway'])) {
            $gateway = (string) $options['gateway'];
            $paymentResult = PaymentManager::get_instance()->process($order, $gateway, $options['payment_params'] ?? []);
            if (!empty($paymentResult['redirect_url'])) {
                $redirectUrl = $paymentResult['redirect_url'];
            }
        }

        return [
            'order' => $order,
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * Full checkout: validate, create order from cart, clear the cart.
     *
     * @return array [success, errors, order, redirect_url]
     */
    public function checkout(Cart $cart, array $customer, array $options = []): array
    {
        if ($cart->isEmpty()) {
            return [
                'success' => false,
                'errors'  => [__('Giỏ hàng của bạn đang trống.', 'jankx')],
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
                'errors'  => [__('Không thể tạo đơn hàng, vui lòng thử lại.', 'jankx')],
                'order'   => null,
                'redirect_url' => '',
            ];
        }

        $cart->emptyCart();

        do_action('jankx/ecommerce/checkout/completed', $order);

        return [
            'success' => true,
            'errors'  => [],
            'order'   => $order,
            'redirect_url' => $result['redirect_url'],
        ];
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
