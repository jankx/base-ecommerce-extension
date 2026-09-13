<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CartItemCheckboxBlock extends Block
{
    protected $blockId = 'jankx/cart-item-checkbox';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        return sprintf(
            '<label class="jankx-cart-item__check"><input type="checkbox" class="jankx-cart-item__checkbox" data-item-key="%s" aria-label="%s"></label>',
            esc_attr($item->getItemKey()),
            esc_attr__('Chọn sản phẩm', 'jankx')
        );
    }
}
