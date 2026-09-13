<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CartItemPriceBlock extends Block
{
    protected $blockId = 'jankx/cart-item-price';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        $subtotal = $item->getSubtotal();

        return '<div class="jankx-cart-item__price">'
            . CartItemContext::formatPrice($subtotal)
            . '</div>';
    }
}
