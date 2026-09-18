<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CheckoutOrderReviewBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-order-review';

    protected $defaultItemInnerBlocks = [
        'jankx/checkout-review-item-name',
        'jankx/checkout-review-item-price',
    ];

    public function render($attributes, $content = '', $block = null): string
    {
        $cart = Cart::get_instance();
        if ($cart->isEmpty()) {
            return '';
        }

        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-order-review',
            ])
        );

        $output .= '<h2 class="jankx-section-title">' . esc_html__('Your order', 'jankx') . '</h2>';
        $output .= '<div class="jankx-order-review">';

        $itemInnerBlocks = [];
        if ($block && !empty($block->inner_blocks)) {
            foreach ($block->inner_blocks as $innerBlock) {
                $itemInnerBlocks[] = $innerBlock;
            }
        }

        foreach ($cart->getItems() as $item) {
            CartItemContext::set($item);
            $output .= '<div class="jankx-review-item">';

            if (count($itemInnerBlocks) > 0) {
                foreach ($itemInnerBlocks as $innerBlock) {
                    $output .= $innerBlock->render();
                }
            } else {
                $output .= $this->renderDefaultItem();
            }

            $output .= '</div>';
        }

        CartItemContext::set(null);

        $output .= $this->renderTotalsRows($cart);
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function renderDefaultItem(): string
    {
        $output = '';
        foreach ($this->defaultItemInnerBlocks as $slug) {
            $blockClass = 'jankx/checkout-review-item-name' === $slug
                ? CheckoutReviewItemNameBlock::class
                : CheckoutReviewItemPriceBlock::class;
            $output .= (new $blockClass())->render([]);
        }

        return $output;
    }

    protected function renderTotalsRows(Cart $cart): string
    {
        $creditDiscount = $this->getCreditDiscount($cart);
        $otherDiscount = max(0, $cart->getDiscount() - $creditDiscount);

        $output = '<div class="jankx-review-total-row jankx-review-discount-row"' . ($otherDiscount > 0 ? '' : ' hidden') . '>'
            . '<span>' . esc_html__('Discount', 'jankx') . '</span>'
            . '<span class="jankx-review-discount-value">' . esc_html('-' . $this->formatPrice($otherDiscount)) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-review-total-row jankx-review-credit-row"' . ($creditDiscount > 0 ? '' : ' hidden') . '>'
            . '<span>' . esc_html__('Credits', 'jankx') . '</span>'
            . '<span class="jankx-review-credit-value">' . esc_html('-' . $this->formatPrice($creditDiscount)) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-review-total-row jankx-review-total">'
            . '<span>' . esc_html__('Total', 'jankx') . '</span>'
            . '<span class="jankx-review-total-value">' . esc_html($this->formatPrice($cart->getTotal())) . '</span>'
            . '</div>';

        return $output;
    }
}