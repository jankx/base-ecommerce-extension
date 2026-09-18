<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CheckoutReviewItemPriceBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-review-item-price';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        return '<span class="jankx-review-price">' . esc_html($this->formatPrice($item->getSubtotal())) . '</span>';
    }
}