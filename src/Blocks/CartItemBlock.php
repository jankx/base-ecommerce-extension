<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Blocks\MiniCart\CartToggleRenderer;
use Jankx\Extensions\Ecommerce\Blocks\MiniCart\MiniCartContext;
use Jankx\Extensions\Ecommerce\Blocks\MiniCart\MiniCartRendererFactory;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\EcommerceExtension;
use Jankx\Extensions\Ecommerce\Rest\EcommerceController;

/**
 * Mini cart block: cart icon + item counter in the site header.
 *
 * Variant rendering (drawer/dropdown) is delegated to a MiniCartRenderer
 * strategy resolved via MiniCartRendererFactory (see Blocks\MiniCart).
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
                'empty' => __('Giỏ hàng của bạn đang trống.', 'base-ecommerce'),
                'cart' => __('Giỏ hàng', 'base-ecommerce'),
                'viewCart' => __('Xem giỏ hàng', 'base-ecommerce'),
                'viewAll' => __('Xem tất cả', 'base-ecommerce'),
                'checkout' => __('Thanh toán', 'base-ecommerce'),
                'remove' => __('Xóa', 'base-ecommerce'),
                'removeError' => __('Không thể xóa sản phẩm.', 'base-ecommerce'),
                'total' => __('Tổng cộng', 'base-ecommerce'),
                'fail' => __('Không thể cập nhật giỏ hàng.', 'base-ecommerce'),
            ],
        ]);
    }

    public function render($attributes, $content = '', $block = null)
    {
        $context = new MiniCartContext(Cart::get_instance(), (array) $attributes);
        $renderer = MiniCartRendererFactory::create($context);

        $wrapperClass = 'jankx-mini-cart' . $renderer->wrapperClass();
        $customClasses = $context->getCustomClasses();
        if ($customClasses !== '') {
            $wrapperClass .= ' ' . $customClasses;
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => $wrapperClass,
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= CartToggleRenderer::render($context, (string) $content, $renderer->panelId());
        $output .= $renderer->render($context);
        $output .= '</div>';

        return $output;
    }
}