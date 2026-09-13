<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CartItemRemoveBlock extends Block
{
    protected $blockId = 'jankx/cart-item-remove';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        $key = esc_attr($item->getItemKey());

        return '<button type="button" class="jankx-cart-item__remove" data-item-key="' . $key . '" aria-label="' . esc_attr__('Xóa sản phẩm', 'jankx') . '">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>'
            . '</button>';
    }
}
