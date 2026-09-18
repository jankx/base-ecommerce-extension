<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

final class DropdownMiniCartRenderer extends AbstractMiniCartRenderer
{
    public function panelId(): string
    {
        return 'jankx-mini-cart-dropdown';
    }

    public function wrapperClass(): string
    {
        return ' jankx-mini-cart--dropdown';
    }

    public function render(MiniCartContext $context): string
    {
        $cart = $context->getCart();
        $limit = $context->getLimit();

        $output = '<div class="jankx-mini-cart-dropdown" id="' . esc_attr($this->panelId()) . '" role="region" '
            . 'aria-label="' . esc_attr__('Shopping cart', 'jankx') . '" data-jankx-dropdown-limit="' . $limit . '">';

        $output .= $this->renderHead();

        $output .= '<div class="jankx-mini-cart-body" data-jankx-dropdown-items>';
        if ($cart->isEmpty()) {
            $output .= $this->renderEmptyState();
        } else {
            $count = 0;
            $extraRows = '';
            foreach ($cart->getItems() as $itemKey => $item) {
                $count++;
                $row = CartRowRenderer::render($itemKey, $item);
                if ($count <= $limit) {
                    $output .= $row;
                } else {
                    $extraRows .= $row;
                }
            }
            $remaining = $count - $limit;
            if ($remaining > 0) {
                $output .= '<button type="button" class="jankx-mini-cart-viewall" data-jankx-mini-cart-viewall aria-expanded="false">'
                    . sprintf(esc_html__('Xem tất cả (%d)', 'jankx'), $remaining) . '</button>';
                $output .= '<div class="jankx-mini-cart-extra" data-jankx-viewall-extra hidden>' . $extraRows . '</div>';
            }
        }
        $output .= '</div>';

        $output .= $this->renderFooter('data-jankx-dropdown-footer', $context);
        $output .= '</div>';

        return $output;
    }
}