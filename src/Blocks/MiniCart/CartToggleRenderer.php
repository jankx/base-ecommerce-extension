<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

final class CartToggleRenderer
{
    const CART_ICON_SVG = '<svg width="24" height="40" viewBox="0 0 24 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<path d="M8 24L16.7201 23.2733C19.4486 23.046 20.0611 22.45 20.3635 19.7289L21 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
        . '<path d="M6 14H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
        . '<circle cx="6" cy="28" r="2" stroke="currentColor" stroke-width="1.5"/>'
        . '<circle cx="17" cy="28" r="2" stroke="currentColor" stroke-width="1.5"/>'
        . '<path d="M8 28L15 28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
        . '<path d="M2 10H2.966C3.91068 10 4.73414 10.6246 4.96326 11.5149L7.93852 23.0765C8.08887 23.6608 7.9602 24.2797 7.58824 24.7616L6.63213 26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
        . '</svg>';

    public static function render(MiniCartContext $context, string $innerBlocksContent = '', string $panelId = 'jankx-mini-cart-drawer'): string
    {
        $count = $context->getCart()->getItemCount();

        $iconHtml = !empty($innerBlocksContent)
            ? '<span class="jankx-mini-cart-icon jankx-mini-cart-icon--custom" aria-hidden="true">' . $innerBlocksContent . '</span>'
            : '<span class="jankx-mini-cart-icon" aria-hidden="true">' . self::CART_ICON_SVG . '</span>';

        return '<button type="button" class="jankx-mini-cart-toggle" aria-expanded="false" '
            . 'aria-controls="' . esc_attr($panelId) . '" aria-label="' . esc_attr__('Open cart', 'jankx') . '">'
            . $iconHtml
            . '<span class="jankx-mini-cart-count' . ($count ? '' : ' is-empty') . '" data-jankx-cart-count'
            . $context->getBadgeStyleAttribute() . '>'
            . (int) $count . '</span>'
            . '</button>';
    }
}