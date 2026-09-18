<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\EcommerceExtension;

class CartTotalsBlock extends Block
{
    protected $blockId = 'jankx/cart-totals';

    /**
     * Track whether a cart-totals block has been rendered during the current request.
     *
     * @var bool
     */
    protected static $rendered = false;

    public static function hasRendered(): bool
    {
        return self::$rendered;
    }

    public static function resetRendered(): void
    {
        self::$rendered = false;
    }

    public function render($attributes, $content = '', $block = null): string
    {
        $cart = Cart::get_instance();
        if ($cart->isEmpty()) {
            return '';
        }

        self::$rendered = true;

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-cart-totals',
        ]);

        return $this->renderTotalsHtml($cart, $wrapperAttrs);
    }

    public function renderTotalsHtml(Cart $cart, string $wrapperAttrs = ''): string
    {
        $checkoutUrl = EcommerceExtension::get_checkout_page_url();

        if (empty($wrapperAttrs)) {
            $output = '<div class="jankx-cart-totals">';
        } else {
            $output = sprintf('<div %s>', $wrapperAttrs);
        }

        $output .= '<h2 class="jankx-section-title">' . esc_html__('Cart totals', 'jankx') . '</h2>';
        $output .= $this->renderCouponSection($cart);
        $output .= $this->renderCreditsSection($cart);

        $creditDiscount = $this->getCreditDiscount($cart);
        $otherDiscount = max(0, $cart->getDiscount() - $creditDiscount);

        $output .= '<div class="jankx-total-row jankx-subtotal-row"' . ($cart->getDiscount() > 0 ? '' : ' hidden') . '>'
            . '<span>' . esc_html__('Subtotal', 'jankx') . '</span>'
            . '<span>' . esc_html($this->formatPrice($cart->getSubtotal())) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-total-row jankx-discount-row"' . ($otherDiscount > 0 ? '' : ' hidden') . '>'
            . '<span>' . esc_html__('Discount', 'jankx') . '</span>'
            . '<span class="jankx-discount-value">' . esc_html('-' . $this->formatPrice($otherDiscount)) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-total-row jankx-credit-discount-row"' . ($creditDiscount > 0 ? '' : ' hidden') . '>'
            . '<span>' . esc_html__('Credits', 'jankx') . '</span>'
            . '<span class="jankx-credit-discount-value">' . esc_html('-' . $this->formatPrice($creditDiscount)) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-total-row jankx-total-grand">'
            . '<span>' . esc_html__('Total', 'jankx') . '</span>'
            . '<span class="jankx-total-grand-value">' . esc_html($this->formatPrice($cart->getTotal())) . '</span>'
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
        $converterManager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
        return $converterManager->formatPriceWithConversion($price);
    }

    /**
     * Credit payment toggle. Rendered by the user-credits extension when
     * active; harmless no-op otherwise.
     */
    protected function renderCreditsSection(Cart $cart): string
    {
        $integration = $this->getCreditsIntegration();
        if (!$integration || !$integration->isEnabled() || !is_user_logged_in()) {
            return '';
        }

        $balance = $integration->getBalance();
        if ($balance <= 0) {
            return '';
        }

        $output = '<div class="jankx-credits-form">';
        $output .= '<label class="jankx-credits-toggle-label">'
            . '<input type="checkbox" class="jankx-credits-toggle" value="1"' . checked($integration->isApplied(), true, false) . '>'
            . '<span>' . esc_html($integration->getLabel()) . '</span>'
            . '</label>';
        $output .= '<p class="jankx-credits-balance">'
            . esc_html__('Số dư tín dụng:', 'jankx') . ' <strong class="jankx-credits-balance-value">'
            . esc_html($this->formatPrice($balance))
            . '</strong></p>';
        $output .= '<span class="jankx-credits-message" role="status"></span>';
        $output .= '</div>';

        return $output;
    }

    /**
     * @return object|null
     */
    protected function getCreditsIntegration()
    {
        $class = '\Jankx\Extensions\UserCredits\Integration\CheckoutIntegration';
        if (!class_exists($class)) {
            return null;
        }

        return $class::get_instance();
    }

    protected function getCreditDiscount(Cart $cart): float
    {
        $integration = $this->getCreditsIntegration();
        if (!$integration) {
            return 0.0;
        }

        return (float) $integration->getAppliedCreditDiscount($cart);
    }
}
