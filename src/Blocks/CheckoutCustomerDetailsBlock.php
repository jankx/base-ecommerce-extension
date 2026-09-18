<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

class CheckoutCustomerDetailsBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-customer-details';

    public function render($attributes, $content = '', $block = null): string
    {
        $user = wp_get_current_user();
        $phone = get_user_meta($user->ID, 'phone', true);

        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-customer-details',
            ])
        );

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

        $output .= '</div>';

        return $output;
    }
}