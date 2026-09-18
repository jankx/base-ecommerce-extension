<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

abstract class AbstractMiniCartRenderer implements MiniCartRendererInterface
{
    protected function renderHead(): string
    {
        return '<div class="jankx-mini-cart-head">'
            . '<span class="jankx-mini-cart-title">' . esc_html__('Giỏ hàng', 'jankx') . '</span>'
            . '<button type="button" class="jankx-mini-cart-close" data-jankx-mini-cart-close aria-label="'
            . esc_attr__('Close cart', 'jankx') . '">&times;</button>'
            . '</div>';
    }

    protected function renderEmptyState(): string
    {
        return '<p class="jankx-mini-cart-empty">' . esc_html__('Giỏ hàng của bạn đang trống.', 'jankx') . '</p>';
    }

    protected function renderFooter(string $footerAttr, MiniCartContext $context): string
    {
        $hidden = $context->getCart()->isEmpty() ? ' hidden' : '';

        return '<div class="jankx-mini-cart-foot" ' . $footerAttr . $hidden . '>'
            . CartFooterRenderer::render($context)
            . '</div>';
    }
}