<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

class CheckoutCustomerDetailsBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-customer-details';

    public function render($attributes, $content = '', $block = null): string
    {
        $user   = wp_get_current_user();
        $phone  = get_user_meta($user->ID, 'phone', true);

        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-customer-details',
            ])
        );

        // Section header
        $output .= '<div class="jankx-section-header">'
            . '<span class="jankx-section-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>'
            . '<h2 class="jankx-section-title">' . esc_html__('Thông tin liên lạc', 'base-ecommerce') . '</h2>'
            . '<button type="button" class="jankx-section-toggle" aria-expanded="true" aria-label="' . esc_attr__('Thu gọn', 'base-ecommerce') . '">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>'
            . '</button>'
            . '</div>';

        $output .= '<div class="jankx-section-body">';

        // Row 1: Name + Phone (2 columns)
        $output .= '<div class="jankx-field-row">';

        $output .= '<div class="jankx-field">'
            . '<label for="jankx_customer_name">'
            . '<span class="jankx-field-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>'
            . esc_html__('Họ và tên', 'base-ecommerce') . ' <span class="jankx-required">*</span>'
            . '</label>'
            . '<input type="text" id="jankx_customer_name" name="customer_name" class="jankx-input" required '
            . 'placeholder="' . esc_attr__('Nhập họ và tên', 'base-ecommerce') . '" '
            . 'value="' . esc_attr($user->display_name) . '">'
            . '</div>';

        $output .= '<div class="jankx-field">'
            . '<label for="jankx_customer_phone">'
            . '<span class="jankx-field-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>'
            . esc_html__('Số điện thoại', 'base-ecommerce') . ' <span class="jankx-required">*</span>'
            . '</label>'
            . '<input type="tel" id="jankx_customer_phone" name="customer_phone" class="jankx-input" required '
            . 'placeholder="' . esc_attr__('Nhập số điện thoại', 'base-ecommerce') . '" '
            . 'value="' . esc_attr($phone) . '">'
            . '</div>';

        $output .= '</div>'; // .jankx-field-row

        // Row 2: Email (full width)
        $output .= '<div class="jankx-field">'
            . '<label for="jankx_customer_email">'
            . '<span class="jankx-field-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>'
            . esc_html__('Email', 'base-ecommerce') . ' <span class="jankx-required">*</span>'
            . '</label>'
            . '<input type="email" id="jankx_customer_email" name="customer_email" class="jankx-input" required '
            . 'placeholder="' . esc_attr__('Nhập địa chỉ email', 'base-ecommerce') . '" '
            . 'value="' . esc_attr($user->user_email) . '">'
            . '</div>';

        // Checkbox: send order confirmation via email
        $output .= '<div class="jankx-field jankx-field-checkbox">'
            . '<label class="jankx-checkbox-label">'
            . '<input type="checkbox" id="jankx_send_email_confirm" name="send_email_confirm" value="1" checked>'
            . '<span class="jankx-checkbox-custom"></span>'
            . '<span class="jankx-checkbox-text">' . esc_html__('Gửi xác nhận đơn hàng qua email', 'base-ecommerce') . '</span>'
            . '</label>'
            . '</div>';

        if (!is_user_logged_in()) {
            $output .= '<div class="jankx-field jankx-create-account-field jankx-field-checkbox">'
                . '<label class="jankx-checkbox-label">'
                . '<input type="checkbox" id="jankx_create_account" name="create_account" value="1" checked>'
                . '<span class="jankx-checkbox-custom"></span>'
                . '<span class="jankx-checkbox-text">' . esc_html__('Tạo tài khoản với email này', 'base-ecommerce') . '</span>'
                . '</label>'
                . '<p class="jankx-field-desc">' . esc_html__('Mật khẩu sẽ được gửi qua email sau khi đặt hàng.', 'base-ecommerce') . '</p>'
                . '</div>';
        }

        $output .= '</div>'; // .jankx-section-body
        $output .= '</div>';

        return $output;
    }
}