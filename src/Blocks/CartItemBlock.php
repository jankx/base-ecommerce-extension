<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\EcommerceExtension;
use Jankx\Extensions\Ecommerce\Rest\EcommerceController;

/**
 * Mini cart block: cart icon + item counter in the site header that opens a
 * slide-out drawer listing the current cart contents.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class CartItemBlock extends Block
{
    const BLOCK_ID = 'jankx/cart-item';

    protected $blockId = 'jankx/cart-item';

    protected function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        $extension = EcommerceExtension::get_instance();
        if (!$extension) {
            return;
        }

        wp_enqueue_style(
            'jankx-mini-cart',
            $extension->get_extension_url() . '/assets/mini-cart.css',
            [],
            filemtime($extension->get_extension_path() . '/assets/mini-cart.css')
        );

        wp_enqueue_script(
            'jankx-mini-cart',
            $extension->get_extension_url() . '/assets/mini-cart.js',
            [],
            filemtime($extension->get_extension_path() . '/assets/mini-cart.js'),
            true
        );

        wp_localize_script('jankx-mini-cart', 'jankxMiniCart', [
            'restUrl' => esc_url_raw(rest_url(EcommerceController::REST_NAMESPACE)),
            'cartUrl' => EcommerceExtension::get_cart_page_url(),
            'checkoutUrl' => EcommerceExtension::get_checkout_page_url(),
            'i18n' => [
                'empty' => __('Giỏ hàng của bạn đang trống.', 'jankx'),
                'cart' => __('Giỏ hàng', 'jankx'),
                'viewCart' => __('Xem giỏ hàng', 'jankx'),
                'checkout' => __('Thanh toán', 'jankx'),
                'remove' => __('Xóa', 'jankx'),
                'removeError' => __('Không thể xóa sản phẩm.', 'jankx'),
                'total' => __('Tổng cộng', 'jankx'),
                'fail' => __('Không thể cập nhật giỏ hàng.', 'jankx'),
            ],
        ]);
    }

    public function render($attributes, $content = '', $block = null)
    {
        $cart = Cart::get_instance();

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-mini-cart',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= $this->renderToggle($cart);
        $output .= $this->renderDrawer($cart);
        $output .= '</div>';

        return $output;
    }

    protected function renderToggle(Cart $cart): string
    {
        $count = $cart->getItemCount();

        return '<button type="button" class="jankx-mini-cart-toggle" aria-expanded="false" '
            . 'aria-controls="jankx-mini-cart-drawer" aria-label="' . esc_attr__('Open cart', 'jankx') . '">'
            . '<span class="jankx-mini-cart-icon" aria-hidden="true">' . $this->cartIcon() . '</span>'
            . '<span class="jankx-mini-cart-count' . ($count ? '' : ' is-empty') . '" data-jankx-cart-count>'
            . (int) $count . '</span>'
            . '</button>';
    }

    protected function renderDrawer(Cart $cart): string
    {
        $cartUrl = EcommerceExtension::get_cart_page_url();
        $checkoutUrl = EcommerceExtension::get_checkout_page_url();

        $output = '<div class="jankx-mini-cart-overlay" data-jankx-mini-cart-close></div>';
        $output .= '<aside class="jankx-mini-cart-drawer" id="jankx-mini-cart-drawer" role="dialog" '
            . 'aria-modal="true" aria-label="' . esc_attr__('Shopping cart', 'jankx') . '">';

        $output .= '<div class="jankx-mini-cart-head">'
            . '<span class="jankx-mini-cart-title">' . esc_html__('Giỏ hàng', 'jankx') . '</span>'
            . '<button type="button" class="jankx-mini-cart-close" data-jankx-mini-cart-close aria-label="'
            . esc_attr__('Close cart', 'jankx') . '">&times;</button>'
            . '</div>';

        $output .= '<div class="jankx-mini-cart-body" data-jankx-drawer-items>';
        if ($cart->isEmpty()) {
            $output .= '<p class="jankx-mini-cart-empty">' . esc_html__('Giỏ hàng của bạn đang trống.', 'jankx') . '</p>';
        } else {
            foreach ($cart->getItems() as $itemKey => $item) {
                $productUrl = get_permalink($item->getProductId());
                $output .= '<div class="jankx-mini-cart-row" data-item-key="' . esc_attr($itemKey) . '">';
                $output .= '<div class="jankx-mini-cart-info">';
                $output .= '<a class="jankx-mini-cart-name" href="' . esc_url($productUrl ?: '#') . '">'
                    . esc_html($item->getName()) . '</a>';
                $output .= '<span class="jankx-mini-cart-meta">' . (int) $item->getQuantity()
                    . ' &times; ' . esc_html($this->formatPrice($item->getUnitPrice())) . '</span>';
                $output .= '</div>';
                $output .= '<div class="jankx-mini-cart-side">';
                $output .= '<span class="jankx-mini-cart-price">' . esc_html($this->formatPrice($item->getSubtotal())) . '</span>';
                $output .= '<button type="button" class="jankx-mini-cart-remove" data-item-key="' . esc_attr($itemKey)
                    . '" aria-label="' . esc_attr__('Remove', 'jankx') . '">&times;</button>';
                $output .= '</div>';
                $output .= '</div>';
            }
        }
        $output .= '</div>'; // End body

        // ALWAYS render the footer structure, just hide it if cart is empty.
        $output .= '<div class="jankx-mini-cart-foot" data-jankx-drawer-footer ' . ($cart->isEmpty() ? 'hidden' : '') . '>';
        $output .= '<div class="jankx-mini-cart-total-row">'
            . '<span>' . esc_html__('Tổng cộng', 'jankx') . '</span>'
            . '<strong data-jankx-drawer-total>' . esc_html($this->formatPrice($cart->getTotal())) . '</strong>'
            . '</div>';
        $output .= '<div class="jankx-mini-cart-actions">';
        if ($cartUrl) {
            $output .= '<a class="jankx-btn jankx-btn-outline jankx-mini-cart-link" href="' . esc_url($cartUrl) . '">'
                . esc_html__('Xem giỏ hàng', 'jankx') . '</a>';
        }
        if ($checkoutUrl) {
            $output .= '<a class="jankx-btn jankx-btn-primary jankx-mini-cart-link" href="' . esc_url($checkoutUrl) . '">'
                . esc_html__('Thanh toán', 'jankx') . '</a>';
        }
        $output .= '</div></div>';

        $output .= '</aside>';

        return $output;
    }

    protected function cartIcon(): string
    {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="9" cy="21" r="1"></circle>'
            . '<circle cx="20" cy="21" r="1"></circle>'
            . '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>'
            . '</svg>';
    }

    protected function formatPrice(float $price): string
    {
        return CurrencyManager::formatPrice($price);
    }
}
