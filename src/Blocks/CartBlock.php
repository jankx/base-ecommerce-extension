<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\EcommerceExtension;

class CartBlock extends Block
{
    protected $blockId = 'jankx/cart';

    public function render($attributes, $content = '', $block = null)
    {
        $cart = Cart::get_instance();
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-cart-block',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if ($cart->isEmpty()) {
            $output .= $this->renderEmptyCart();
        } else {
            $output .= $this->renderItems($cart);
            $output .= $this->renderTotals($cart);
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderEmptyCart(): string
    {
        $continueUrl = (string) apply_filters(
            'jankx/ecommerce/cart/continue_shopping_url',
            home_url('/')
        );

        return '<div class="jankx-empty-state">'
            . '<span class="jankx-empty-icon" aria-hidden="true">&#128722;</span>'
            . '<h2 class="jankx-section-title">' . esc_html__('Your cart is empty', 'jankx') . '</h2>'
            . '<p>' . esc_html__('Add some products before checking out.', 'jankx') . '</p>'
            . '<a href="' . esc_url($continueUrl) . '" class="jankx-btn jankx-btn-primary">'
            . esc_html__('Continue shopping', 'jankx') . '</a>'
            . '</div>';
    }

    protected function renderItems(Cart $cart): string
    {
        $output = '<div class="jankx-cart-items">';
        $output .= '<div class="jankx-cart-item-head">'
            . '<span class="jankx-col-product">' . esc_html__('Product', 'jankx') . '</span>'
            . '<span class="jankx-col-price">' . esc_html__('Price', 'jankx') . '</span>'
            . '<span class="jankx-col-qty">' . esc_html__('Quantity', 'jankx') . '</span>'
            . '<span class="jankx-col-total">' . esc_html__('Total', 'jankx') . '</span>'
            . '<span class="jankx-col-action"></span>'
            . '</div>';

        foreach ($cart->getItems() as $itemKey => $item) {
            $productId = $item->getProductId();
            $thumbnail = get_the_post_thumbnail_url($productId, 'thumbnail');
            $productUrl = get_permalink($productId);

            $output .= '<div class="jankx-cart-item" data-item-key="' . esc_attr($itemKey) . '">';
            $output .= '<div class="jankx-col-product">';
            if ($thumbnail) {
                $output .= '<a class="jankx-cart-thumb" href="' . esc_url($productUrl) . '">'
                    . '<img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($item->getName()) . '"></a>';
            }
            $output .= '<div class="jankx-cart-name">';
            $output .= '<a href="' . esc_url($productUrl) . '">' . esc_html($item->getName()) . '</a>';
            foreach ($item->getArgs() as $argKey => $argValue) {
                if (is_string($argValue)) {
                    $output .= '<span class="jankx-cart-args">' . esc_html($argKey . ': ' . $argValue) . '</span>';
                }
            }
            $output .= '</div></div>';

            $output .= '<div class="jankx-col-price">' . esc_html($this->formatPrice($item->getUnitPrice())) . '</div>';
            $output .= '<div class="jankx-col-qty">' . esc_html($item->getQuantity()) . '</div>';
            $output .= '<div class="jankx-col-total">' . esc_html($this->formatPrice($item->getSubtotal())) . '</div>';
            $output .= '<div class="jankx-col-action">'
                . '<button type="button" class="jankx-cart-remove" data-item-key="' . esc_attr($itemKey) . '">'
                . esc_html__('Remove', 'jankx') . '</button>'
                . '</div>';

            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderTotals(Cart $cart): string
    {
        $checkoutUrl = EcommerceExtension::get_checkout_page_url();

        $output = '<div class="jankx-cart-totals">';
        $output .= '<h2 class="jankx-section-title">' . esc_html__('Cart totals', 'jankx') . '</h2>';

        $output .= $this->renderCouponSection($cart);

        if ($cart->getDiscount() > 0) {
            $output .= '<div class="jankx-total-row">'
                . '<span>' . esc_html__('Subtotal', 'jankx') . '</span>'
                . '<span>' . esc_html($this->formatPrice($cart->getSubtotal())) . '</span>'
                . '</div>';
            $output .= '<div class="jankx-total-row">'
                . '<span>' . esc_html__('Discount', 'jankx') . '</span>'
                . '<span>' . esc_html('-' . $this->formatPrice($cart->getDiscount())) . '</span>'
                . '</div>';
        }

        $output .= '<div class="jankx-total-row jankx-total-grand">'
            . '<span>' . esc_html__('Total', 'jankx') . '</span>'
            . '<span>' . esc_html($this->formatPrice($cart->getTotal())) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-cart-actions">'
            . '<a href="' . esc_url($checkoutUrl) . '" class="jankx-btn jankx-btn-primary jankx-btn-checkout">'
            . esc_html__('Proceed to checkout', 'jankx') . '</a>'
            . '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Coupon apply/remove UI. Rendered by the coupon-system extension when
     * active; harmless no-op otherwise.
     */
    protected function renderCouponSection(Cart $cart): string
    {
        if (!class_exists('\Jankx\Extensions\CouponSystem\CouponManager')) {
            return '';
        }

        $manager = \Jankx\Extensions\CouponSystem\CouponManager::get_instance();
        $applied = $manager->getApplied();
        $appliedCode = $applied ? $applied->getCode() : '';

        $output = '<div class="jankx-coupon-form" data-coupon-applied="' . esc_attr($appliedCode) . '">';

        if ($appliedCode) {
            $output .= '<div class="jankx-coupon-applied">'
                . '<span class="jankx-coupon-applied-code">' . esc_html($appliedCode) . '</span>'
                . '<button type="button" class="jankx-btn jankx-btn-outline jankx-coupon-remove">'
                . esc_html__('Gỡ mã', 'jankx') . '</button>'
                . '</div>';
        } else {
            $output .= '<div class="jankx-coupon-input-row">'
                . '<input type="text" class="jankx-coupon-code" placeholder="' . esc_attr__('Nhập mã giảm giá', 'jankx') . '" autocomplete="off">'
                . '<button type="button" class="jankx-btn jankx-btn-primary jankx-coupon-apply">'
                . esc_html__('Áp dụng', 'jankx') . '</button>'
                . '</div>';
            $output .= '<span class="jankx-coupon-message" role="status"></span>';
        }

        $output .= '</div>';

        return $output;
    }

    protected function formatPrice(float $price): string
    {
        return CurrencyManager::formatPrice($price);
    }
}
