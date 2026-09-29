<?php

namespace Jankx\Extensions\Ecommerce\Order;

/**
 * Holds the order currently being rendered inside the order list loop so the
 * inner blocks (order-number, order-status, order-date, order-total,
 * order-cancel) can read it without passing it through block attributes.
 */
class OrderItemContext
{
    protected static ?Order $order = null;

    public static function set(?Order $order): void
    {
        self::$order = $order;
    }

    public static function get(): ?Order
    {
        return self::$order;
    }
}
