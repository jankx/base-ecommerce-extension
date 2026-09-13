<?php
namespace Jankx\Extensions\Ecommerce\Order\Strategies;

use Jankx\Extensions\Ecommerce\Order\Contracts\OrderCreationStrategyInterface;

/**
 * Class AbstractOrderCreationStrategy
 *
 * Base abstract class providing common validation and utilities for order creation strategies.
 *
 * @package Jankx\Extensions\Ecommerce\Order\Strategies
 */
abstract class AbstractOrderCreationStrategy implements OrderCreationStrategyInterface
{
    /**
     * Validate standard customer input data.
     */
    protected function validateCustomerData(array $data): array
    {
        $errors = [];

        $name = trim($data['customer_name'] ?? $data['name'] ?? '');
        $phone = trim($data['customer_phone'] ?? $data['phone'] ?? '');
        $email = trim($data['customer_email'] ?? $data['email'] ?? '');

        if ($name === '') {
            $errors[] = __('Vui lòng nhập họ và tên.', 'jankx');
        }

        if ($phone === '') {
            $errors[] = __('Vui lòng nhập số điện thoại.', 'jankx');
        }

        if ($email === '') {
            $errors[] = __('Vui lòng nhập địa chỉ email.', 'jankx');
        } elseif (!is_email($email)) {
            $errors[] = __('Địa chỉ email không hợp lệ.', 'jankx');
        }

        return $errors;
    }
}
