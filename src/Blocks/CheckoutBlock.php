<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

class CheckoutBlock extends Block
{
    protected $blockId = 'jankx/checkout';

    public function render($attributes, $content = '', $block = null)
    {
        $cart = Cart::get_instance();
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-checkout-block',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if ($cart->isEmpty()) {
            $output .= $this->renderEmptyCart();
        } else {
            $output .= $this->renderCheckoutForm($cart);
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
            . '<p>' . esc_html__('Nothing to check out yet.', 'jankx') . '</p>'
            . '<a href="' . esc_url($continueUrl) . '" class="jankx-btn jankx-btn-primary">'
            . esc_html__('Continue shopping', 'jankx') . '</a>'
            . '</div>';
    }

    protected function renderCheckoutForm(Cart $cart): string
    {
        $user = wp_get_current_user();

        $output = '<form class="jankx-checkout-form" method="post" novalidate>';

        $output .= '<div class="jankx-checkout-cols">';

        $output .= '<div class="jankx-checkout-customer">';
        $output .= '<h2 class="jankx-section-title">' . esc_html__('Billing details', 'jankx') . '</h2>';

        $output .= '<div class="jankx-field">'
            . '<label for="jankx_customer_name">' . esc_html__('Full name', 'jankx') . ' <span class="jankx-required">*</span></label>'
            . '<input type="text" id="jankx_customer_name" name="customer_name" class="jankx-input" required '
            . 'value="' . esc_attr($user->display_name) . '">'
            . '</div>';

        $output .= '<div class="jankx-field">'
            . '<label for="jankx_customer_email">' . esc_html__('Email', 'jankx') . ' <span class="jankx-required">*</span></label>'
            . '<input type="email" id="jankx_customer_email" name="customer_email" class="jankx-input" required '
            . 'value="' . esc_attr($user->user_email) . '">'
            . '</div>';

        $phone = get_user_meta($user->ID, 'phone', true);
        $output .= '<div class="jankx-field">'
            . '<label for="jankx_customer_phone">' . esc_html__('Phone', 'jankx') . '</label>'
            . '<input type="tel" id="jankx_customer_phone" name="customer_phone" class="jankx-input" '
            . 'value="' . esc_attr($phone) . '">'
            . '</div>';

        $output .= '<div class="jankx-field">'
            . '<label for="jankx_customer_address">' . esc_html__('Address', 'jankx') . '</label>'
            . '<textarea id="jankx_customer_address" name="customer_address" class="jankx-input" rows="3"></textarea>'
            . '</div>';

        if (!is_user_logged_in()) {
            $output .= '<div class="jankx-field jankx-create-account-field">'
                . '<label class="jankx-checkbox">'
                . '<input type="checkbox" id="jankx_create_account" name="create_account" value="1" checked>'
                . '<span>' . esc_html__('Tạo tài khoản với email này', 'jankx') . '</span>'
                . '</label>'
                . '<p class="jankx-field-desc">' . esc_html__('Tạo mật khẩu sẽ được gửi qua email sau khi đặt hàng.', 'jankx') . '</p>'
                . '</div>';
        }

        $output .= $this->renderPaymentMethods();

        $output .= '</div>';

        $output .= '<div class="jankx-checkout-summary">';
        $output .= '<h2 class="jankx-section-title">' . esc_html__('Your order', 'jankx') . '</h2>';
        $output .= '<div class="jankx-order-review">';

        foreach ($cart->getItems() as $item) {
            $output .= '<div class="jankx-review-item">'
                . '<span class="jankx-review-name">' . esc_html($item->getName())
                . ' <span class="jankx-review-qty">&times; ' . esc_html($item->getQuantity()) . '</span></span>'
                . '<span class="jankx-review-price">' . esc_html($this->formatPrice($item->getSubtotal())) . '</span>'
                . '</div>';
        }

        if ($cart->getDiscount() > 0) {
            $output .= '<div class="jankx-review-total-row">'
                . '<span>' . esc_html__('Discount', 'jankx') . '</span>'
                . '<span>' . esc_html('-' . $this->formatPrice($cart->getDiscount())) . '</span>'
                . '</div>';
        }

        $output .= '<div class="jankx-review-total-row jankx-review-total">'
            . '<span>' . esc_html__('Total', 'jankx') . '</span>'
            . '<span>' . esc_html($this->formatPrice($cart->getTotal())) . '</span>'
            . '</div>';

        $output .= '</div>';

        $output .= '<div class="jankx-checkout-error" role="alert" hidden></div>';

        $output .= '<button type="submit" class="jankx-btn jankx-btn-primary jankx-btn-place-order">'
            . esc_html__('Place order', 'jankx') . '</button>';

        $output .= '</div>';

        $output .= '</div>';

        $output .= '</form>';

        return $output;
    }

    protected function renderPaymentMethods(): string
    {
        $gateways = $this->getPaymentMethods();

        $output = '<div class="jankx-field">';
        $output .= '<label>' . esc_html__('Payment method', 'jankx') . '</label>';
        $output .= '<div class="jankx-payment-methods">';

        foreach ($gateways as $slug => $label) {
            $output .= '<label class="jankx-payment-method">'
                . '<input type="radio" name="payment_method" value="' . esc_attr($slug) . '"'
                . ($slug === array_key_first($gateways) ? ' checked' : '') . '>'
                . '<span>' . esc_html($label) . '</span>'
                . '</label>';
        }

        $output .= '</div></div>';

        return $output;
    }

    protected function getPaymentMethods(): array
    {
        $methods = [];
        $enabledGateways = get_option('jankx_payment_gateways', []);

        // Built-in gateways
        $builtIn = [
            'bank_transfer' => __('Chuyển khoản ngân hàng', 'jankx'),
            'cod' => __('Thanh toán khi nhận hàng (COD)', 'jankx'),
        ];

        // Online gateways from payment-system extension
        $onlineGateways = [];
        if (class_exists('\Jankx\Extensions\PaymentSystem\Gateways\GatewayManager')) {
            $manager = \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance();
            foreach ($manager->getAvailable() as $slug => $gateway) {
                $onlineGateways[$slug] = $gateway->getName();
            }
        }

        $allGateways = array_merge($builtIn, $onlineGateways);

        // Filter by enabled gateways from admin settings
        if (!empty($enabledGateways)) {
            foreach ($enabledGateways as $slug) {
                if (isset($allGateways[$slug])) {
                    $methods[$slug] = $allGateways[$slug];
                }
            }
        } else {
            // No settings saved yet: show all available
            $methods = $allGateways;
        }

        return (array) apply_filters('jankx/ecommerce/checkout/payment_methods', $methods);
    }

    protected function formatPrice(float $price): string
    {
        $converterManager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
        return $converterManager->formatPriceWithConversion($price);
    }
}
