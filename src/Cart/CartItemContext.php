<?php

namespace Jankx\Extensions\Ecommerce\Cart;

/**
 * Thread-safe static holder for the "current cart item" during block rendering.
 *
 * Used by the parent jankx/cart block to pass per-item data to sub-blocks
 * (image, title, meta, quantity, price, remove). Because PHP rendering is
 * synchronous and single-threaded, a static holder is safe and avoids
 * threading issues.
 *
 * @package Jankx\Extensions\Ecommerce\Cart
 */
class CartItemContext
{
    /**
     * @var CartItem|null
     */
    protected static $currentItem = null;

    /**
     * Set the current cart item being rendered.
     */
    public static function set(?CartItem $item): void
    {
        self::$currentItem = $item;
    }

    /**
     * Get the current cart item (or null if not inside a cart loop).
     */
    public static function get(): ?CartItem
    {
        return self::$currentItem;
    }

    /**
     * Get the current item key, or empty string.
     */
    public static function getKey(): string
    {
        $item = self::$currentItem;
        return $item ? $item->getItemKey() : '';
    }

    /**
     * Get the current product ID, or 0.
     */
    public static function getProductId(): int
    {
        $item = self::$currentItem;
        return $item ? $item->getProductId() : 0;
    }

    /**
     * Format price using the currency converter.
     */
    public static function formatPrice(float $price): string
    {
        $converterManager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
        return $converterManager->formatPriceWithConversion($price);
    }
}
