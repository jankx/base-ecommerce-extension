<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

final class DrawerMiniCartRenderer extends AbstractMiniCartRenderer
{
    public function panelId(): string
    {
        return 'jankx-mini-cart-drawer';
    }

    public function wrapperClass(): string
    {
        return ' jankx-mini-cart--drawer';
    }

    public function render(MiniCartContext $context): string
    {
        $cart = $context->getCart();

        $output = '<div class="jankx-mini-cart-overlay" data-jankx-mini-cart-close></div>';
        $output .= '<aside class="jankx-mini-cart-drawer" id="' . esc_attr($this->panelId()) . '" role="dialog" '
            . 'aria-modal="true" aria-label="' . esc_attr__('Shopping cart', 'jankx') . '">';

        $output .= $this->renderHead();

        $output .= '<div class="jankx-mini-cart-body" data-jankx-drawer-items>';
        if ($cart->isEmpty()) {
            $output .= $this->renderEmptyState();
        } else {
            foreach ($cart->getItems() as $itemKey => $item) {
                $output .= CartRowRenderer::render($itemKey, $item);
            }
        }
        $output .= '</div>';

        $output .= $this->renderFooter('data-jankx-drawer-footer', $context);
        $output .= '</aside>';

        return $output;
    }
}