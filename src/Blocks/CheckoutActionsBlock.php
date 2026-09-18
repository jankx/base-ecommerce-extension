<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

class CheckoutActionsBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-actions';

    public function render($attributes, $content = '', $block = null): string
    {
        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-actions',
            ])
        );

        $output .= '<div class="jankx-checkout-error" role="alert" hidden></div>';

        $output .= '<button type="submit" class="jankx-btn jankx-btn-primary jankx-btn-place-order">'
            . esc_html__('Place order', 'jankx') . '</button>';

        $output .= '</div>';

        return $output;
    }
}