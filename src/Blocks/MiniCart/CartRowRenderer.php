<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;

final class CartRowRenderer
{
    public static function render($itemKey, $item): string
    {
        $productUrl = get_permalink($item->getProductId());

        $output = '<div class="jankx-mini-cart-row" data-item-key="' . esc_attr($itemKey) . '">';
        $output .= '<div class="jankx-mini-cart-info">';
        $output .= '<a class="jankx-mini-cart-name" href="' . esc_url($productUrl ?: '#') . '">'
            . esc_html($item->getName()) . '</a>';
        $output .= '<span class="jankx-mini-cart-meta">' . (int) $item->getQuantity()
            . ' &times; ' . esc_html(self::formatPrice($item->getUnitPrice())) . '</span>';
        $output .= '</div>';
        $output .= '<div class="jankx-mini-cart-side">';
        $output .= '<span class="jankx-mini-cart-price">' . esc_html(self::formatPrice($item->getSubtotal())) . '</span>';
        $output .= '<button type="button" class="jankx-mini-cart-remove" data-item-key="' . esc_attr($itemKey)
            . '" aria-label="' . esc_attr__('Remove', 'jankx') . '">&times;</button>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    public static function formatPrice(float $price): string
    {
        return CurrencyConverterManager::getInstance()->formatPriceWithConversion($price);
    }
}