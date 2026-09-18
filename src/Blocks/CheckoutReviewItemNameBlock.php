<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CheckoutReviewItemNameBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-review-item-name';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        return '<span class="jankx-review-name">' . esc_html($item->getName())
            . ' <span class="jankx-review-qty">&times; ' . esc_html($item->getQuantity()) . '</span></span>';
    }
}