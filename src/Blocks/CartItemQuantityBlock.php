<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CartItemQuantityBlock extends Block
{
    protected $blockId = 'jankx/cart-item-quantity';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        $key = esc_attr($item->getItemKey());
        $qty = $item->getQuantity();

        $output = '<div class="jankx-cart-item__qty">';
        $output .= '<button type="button" class="jankx-cart-item__qty-btn" data-step="-1" data-item-key="' . $key . '" aria-label="' . esc_attr__('Giảm số lượng', 'jankx') . '">&minus;</button>';
        $output .= '<span class="jankx-cart-item__qty-value" data-item-key="' . $key . '">' . (int) $qty . '</span>';
        $output .= '<button type="button" class="jankx-cart-item__qty-btn" data-step="1" data-item-key="' . $key . '" aria-label="' . esc_attr__('Tăng số lượng', 'jankx') . '">+</button>';
        $output .= '</div>';

        return $output;
    }
}
