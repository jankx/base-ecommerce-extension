<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CartItemTitleBlock extends Block
{
    protected $blockId = 'jankx/cart-item-title';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        $productId = $item->getProductId();
        $productUrl = get_permalink($productId);
        $name = $item->getName();

        return sprintf(
            '<a class="jankx-cart-item__title" href="%s">%s</a>',
            esc_url($productUrl),
            esc_html($name)
        );
    }
}
