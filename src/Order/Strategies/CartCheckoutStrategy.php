<?php
namespace Jankx\Extensions\Ecommerce\Order\Strategies;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutManager;
use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Class CartCheckoutStrategy
 *
 * Implements Strategy Pattern for standard cart-based checkout.
 *
 * @package Jankx\Extensions\Ecommerce\Order\Strategies
 */
class CartCheckoutStrategy extends AbstractOrderCreationStrategy
{
    const STRATEGY_NAME = 'checkout';

    public function getName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function validate(array $data): array
    {
        $errors = $this->validateCustomerData($data['customer'] ?? $data);

        $cart = $data['cart'] ?? Cart::get_instance();
        if ($cart instanceof Cart && $cart->isEmpty()) {
            $errors[] = __('Giỏ hàng của bạn đang trống.', 'jankx');
        }

        return $errors;
    }

    public function createOrder(array $data): ?Order
    {
        $customer = $data['customer'] ?? $data;
        $options = $data['options'] ?? [];
        $cart = $data['cart'] ?? Cart::get_instance();

        $result = CheckoutManager::get_instance()->checkout($cart, $customer, $options);

        return $result['order'] ?? null;
    }
}
