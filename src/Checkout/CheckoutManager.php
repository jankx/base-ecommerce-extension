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
     * @return Order|null
     */
    public function createOrder(Cart $cart, array $customer, array $options = []): ?Order
    {
        $order = Order::createFromCart($cart, $customer, $options);

        if ($order && !empty($options['gateway'])) {
            $gateway = (string) $options['gateway'];
            PaymentManager::get_instance()->process($order, $gateway, $options['payment_params'] ?? []);
        }

        return $order;
    }

    /**
     * Full checkout: validate, create order from cart, clear the cart.
     *
     * @return array [success, errors, order]
     */
    public function checkout(Cart $cart, array $customer, array $options = []): array
    {
        if ($cart->isEmpty()) {
            return [
                'success' => false,
                'errors'  => [__('Giỏ hàng của bạn đang trống.', 'jankx')],
                'order'   => null,
            ];
        }

        $errors = $this->validateCustomer($customer);
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'order'   => null,
            ];
        }

        $order = $this->createOrder($cart, $customer, $options);
        if (!$order) {
            return [
                'success' => false,
                'errors'  => [__('Không thể tạo đơn hàng, vui lòng thử lại.', 'jankx')],
                'order'   => null,
            ];
        }

        $cart->emptyCart();

        do_action('jankx/ecommerce/checkout/completed', $order);

        return [
            'success' => true,
            'errors'  => [],
            'order'   => $order,
        ];
    }
}
