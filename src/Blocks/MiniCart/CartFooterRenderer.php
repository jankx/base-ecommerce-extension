<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

final class CartFooterRenderer
{
    public static function render(MiniCartContext $context): string
    {
        $cartUrl = $context->getCartUrl();
        $checkoutUrl = $context->getCheckoutUrl();

        $output = '<div class="jankx-mini-cart-total-row">'
            . '<span>' . esc_html__('Tổng cộng', 'base-ecommerce') . '</span>'
            . '<strong data-jankx-drawer-total>' . esc_html($context->formatPrice($context->getCart()->getTotal())) . '</strong>'
            . '</div>';
        $output .= '<div class="jankx-mini-cart-actions">';
        if ($cartUrl) {
            $output .= '<a class="jankx-btn jankx-btn-outline jankx-mini-cart-link" href="' . esc_url($cartUrl) . '">'
                . esc_html__('Xem giỏ hàng', 'base-ecommerce') . '</a>';
        }
        if ($checkoutUrl) {
            $output .= '<a class="jankx-btn jankx-btn-primary jankx-mini-cart-link" href="' . esc_url($checkoutUrl) . '">'
                . esc_html__('Thanh toán', 'base-ecommerce') . '</a>';
        }
        $output .= '</div>';

        return $output;
    }
}