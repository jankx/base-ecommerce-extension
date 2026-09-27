<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Cart\Cart;

class CheckoutActionsBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-actions';

    public function render($attributes, $content = '', $block = null): string
    {
        $cart  = Cart::get_active_cart();
        $total = $this->formatPrice($cart->getTotal());

        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-actions',
            ])
        );

        // Error container
        $output .= '<div class="jankx-checkout-error" role="alert" hidden></div>';

        // Submit button with lock icon + total amount
        $output .= '<button type="submit" class="jankx-btn jankx-btn-primary jankx-btn-place-order">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
            . '<span>'
            . sprintf(
                /* translators: %s formatted total price */
                esc_html__('Xác nhận thanh toán %s', 'base-ecommerce'),
                '<strong class="jankx-btn-total">' . esc_html($total) . '</strong>'
            )
            . '</span>'
            . '</button>';

        // Payment logos strip
        $output .= '<div class="jankx-payment-logos">'
            . $this->renderPaymentLogos()
            . '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Render inline SVG / text logos for accepted payment methods.
     * Styled as small pill badges.
     */
    protected function renderPaymentLogos(): string
    {
        $logos = [
            'Mastercard' => '<svg xmlns="http://www.w3.org/2000/svg" width="38" height="24" viewBox="0 0 38 24"><rect width="38" height="24" rx="4" fill="#252525"/><circle cx="15" cy="12" r="7" fill="#EB001B"/><circle cx="23" cy="12" r="7" fill="#F79E1B"/><path d="M19 6.8a7 7 0 0 1 0 10.4A7 7 0 0 1 19 6.8z" fill="#FF5F00"/></svg>',
            'VISA'        => '<svg xmlns="http://www.w3.org/2000/svg" width="46" height="24" viewBox="0 0 46 24"><rect width="46" height="24" rx="4" fill="#1A1F71"/><text x="23" y="16" font-family="Arial,sans-serif" font-size="11" font-weight="bold" fill="white" text-anchor="middle" letter-spacing="1">VISA</text></svg>',
            'NAPAS'       => '<svg xmlns="http://www.w3.org/2000/svg" width="52" height="24" viewBox="0 0 52 24"><rect width="52" height="24" rx="4" fill="#E30713"/><text x="26" y="16" font-family="Arial,sans-serif" font-size="9" font-weight="bold" fill="white" text-anchor="middle" letter-spacing="0.5">NAPAS</text></svg>',
            'JCB'         => '<svg xmlns="http://www.w3.org/2000/svg" width="38" height="24" viewBox="0 0 38 24"><rect width="38" height="24" rx="4" fill="#0E4C96"/><text x="19" y="16" font-family="Arial,sans-serif" font-size="10" font-weight="bold" fill="white" text-anchor="middle">JCB</text></svg>',
        ];

        $output = '';
        foreach ($logos as $name => $svg) {
            $output .= '<span class="jankx-payment-logo" title="' . esc_attr($name) . '">' . $svg . '</span>';
        }

        return $output;
    }
}