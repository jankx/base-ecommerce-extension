<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

class CheckoutPaymentMethodsBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-payment-methods';

    public function render($attributes, $content = '', $block = null): string
    {
        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-payment-methods',
            ])
        );

        $output .= '<div class="jankx-field">';
        $output .= '<label>' . esc_html__('Payment method', 'jankx') . '</label>';
        $output .= '<div class="jankx-payment-methods">';

        $methods = $this->getPaymentMethods();
        foreach ($methods as $slug => $label) {
            $output .= '<label class="jankx-payment-method">'
                . '<input type="radio" name="payment_method" value="' . esc_attr($slug) . '"'
                . ($slug === array_key_first($methods) ? ' checked' : '') . '>'
                . '<span>' . esc_html($label) . '</span>'
                . '</label>';
        }

        $output .= '</div></div>';
        $output .= '</div>';

        return $output;
    }
}