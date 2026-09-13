<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CartItemImageBlock extends Block
{
    protected $blockId = 'jankx/cart-item-image';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        $productId = $item->getProductId();
        $thumbnail = get_the_post_thumbnail($productId, 'jankx-cart-thumb', [
            'class'   => 'jankx-cart-item__thumb-img',
            'loading' => 'lazy',
        ]);
        $productUrl = get_permalink($productId);
        $name = esc_attr($item->getName());

        if (!$thumbnail) {
            $thumbnail = '<span class="jankx-cart-item__thumb-img jankx-cart-item__thumb-placeholder"></span>';
        }

        return sprintf(
            '<a class="jankx-cart-item__thumb" href="%s" aria-label="%s">%s</a>',
            esc_url($productUrl),
            $name,
            $thumbnail
        );
    }
}
