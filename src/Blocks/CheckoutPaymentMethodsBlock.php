<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

class CheckoutPaymentMethodsBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-payment-methods';

    /**
     * Icon SVG for each known payment method slug.
     */
    protected function getMethodIcon(string $slug): string
    {
        $icons = [
            'bank_transfer' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
            'cod'           => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
            'onepay'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
            'momo'          => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
            'zalopay'       => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
            'qr_code'       => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="5" y="5" width="3" height="3"/><rect x="16" y="5" width="3" height="3"/><rect x="16" y="16" width="3" height="3"/><rect x="5" y="16" width="3" height="3"/></svg>',
        ];

        return $icons[$slug] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
    }

    public function render($attributes, $content = '', $block = null): string
    {
        $methods = $this->getPaymentMethods();
        $firstSlug = !empty($methods) ? array_key_first($methods) : '';

        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-payment-methods',
            ])
        );

        // Section header
        $output .= '<div class="jankx-section-header">'
            . '<span class="jankx-section-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>'
            . '<h2 class="jankx-section-title">' . esc_html__('Nhập thông tin thanh toán', 'base-ecommerce') . '</h2>'
            . '<button type="button" class="jankx-section-toggle" aria-expanded="true" aria-label="' . esc_attr__('Thu gọn', 'base-ecommerce') . '">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>'
            . '</button>'
            . '</div>';

        $output .= '<div class="jankx-section-body">';

        // Payment method tabs
        $output .= '<div class="jankx-payment-tabs" role="tablist">';
        foreach ($methods as $slug => $label) {
            $isActive = ($slug === $firstSlug);
            $output .= sprintf(
                '<button type="button" class="jankx-payment-tab%s" role="tab" data-method="%s" aria-selected="%s">'
                    . '<span class="jankx-payment-tab-icon">%s</span>'
                    . '<span class="jankx-payment-tab-label">%s</span>'
                    . '</button>',
                $isActive ? ' jankx-payment-tab--active' : '',
                esc_attr($slug),
                $isActive ? 'true' : 'false',
                $this->getMethodIcon($slug),
                esc_html($label)
            );
        }
        $output .= '</div>'; // .jankx-payment-tabs

        // Hidden radio inputs (actual form values)
        $output .= '<div class="jankx-payment-radios" aria-hidden="true">';
        foreach ($methods as $slug => $label) {
            $output .= '<input type="radio" name="payment_method" value="' . esc_attr($slug) . '"'
                . ($slug === $firstSlug ? ' checked' : '')
                . ' class="jankx-payment-radio" id="pm_' . esc_attr($slug) . '">';
        }
        $output .= '</div>';

        // Payment method panels
        $output .= '<div class="jankx-payment-panels">';
        foreach ($methods as $slug => $label) {
            $isActive = ($slug === $firstSlug);
            $output .= sprintf(
                '<div class="jankx-payment-panel%s" data-method="%s" role="tabpanel" %s>',
                $isActive ? ' jankx-payment-panel--active' : '',
                esc_attr($slug),
                $isActive ? '' : 'hidden'
            );
            $output .= $this->renderMethodPanel($slug, $label);
            $output .= '</div>';
        }
        $output .= '</div>'; // .jankx-payment-panels

        // Terms checkbox
        $output .= '<div class="jankx-field jankx-field-checkbox jankx-terms-field">'
            . '<label class="jankx-checkbox-label">'
            . '<input type="checkbox" id="jankx_accept_terms" name="accept_terms" value="1" required>'
            . '<span class="jankx-checkbox-custom"></span>'
            . '<span class="jankx-checkbox-text">'
            . sprintf(
                /* translators: 1: terms link, 2: refund policy link */
                __('Tôi đã đọc và đồng ý với <a href="%1$s" target="_blank">Điều khoản sử dụng</a> và <a href="%2$s" target="_blank">Chính sách hoàn hủy</a> của Nobitour', 'base-ecommerce'),
                esc_url(get_privacy_policy_url() ?: '#'),
                esc_url(get_permalink(get_page_by_path('chinh-sach-hoan-huy')) ?: '#')
            )
            . '</span>'
            . '</label>'
            . '</div>';

        $output .= '</div>'; // .jankx-section-body
        $output .= '</div>';

        return $output;
    }

    protected function renderMethodPanel(string $slug, string $label): string
    {
        switch ($slug) {
            case 'bank_transfer':
                return $this->renderBankTransferPanel();
            case 'qr_code':
                return $this->renderQrCodePanel();
            case 'onepay':
                return $this->renderCreditCardPanel();
            case 'cod':
                return '<p class="jankx-payment-desc">'
                    . esc_html__('Thanh toán bằng tiền mặt khi nhận hàng/dịch vụ.', 'base-ecommerce')
                    . '</p>';
            default:
                // Online gateway: show redirect notice
                return '<p class="jankx-payment-desc">'
                    . sprintf(
                        /* translators: %s: payment method name */
                        esc_html__('Bạn sẽ được chuyển đến %s để hoàn tất thanh toán.', 'base-ecommerce'),
                        esc_html($label)
                    )
                    . '</p>';
        }
    }

    protected function renderBankTransferPanel(): string
    {
        return '<div class="jankx-bank-transfer-panel">'
            . '<p class="jankx-payment-desc">'
            . esc_html__('Bạn vui lòng chuyển khoản vào tài khoản ngân hàng của chúng tôi. Thông tin tài khoản sẽ được cung cấp sau khi bạn đặt hàng thành công.', 'base-ecommerce')
            . '</p>'
            . '</div>';
    }

    protected function renderCreditCardPanel(): string
    {
        return '<div class="jankx-credit-card-panel">'
            . '<div class="jankx-field-row">'
            . '<div class="jankx-field">'
            . '<label for="jankx_card_number">' . esc_html__('Thẻ tín dụng/Ghi nợ', 'base-ecommerce') . '</label>'
            . '<input type="text" id="jankx_card_number" name="card_number" class="jankx-input" '
            . 'placeholder="4220 2923 2332 1002" maxlength="19" autocomplete="cc-number">'
            . '</div>'
            . '</div>'
            . '<div class="jankx-field-row jankx-field-row--half">'
            . '<div class="jankx-field">'
            . '<label for="jankx_card_expiry">' . esc_html__('TT/NĂM', 'base-ecommerce') . '</label>'
            . '<input type="text" id="jankx_card_expiry" name="card_expiry" class="jankx-input" '
            . 'placeholder="02/09" maxlength="5" autocomplete="cc-exp">'
            . '</div>'
            . '<div class="jankx-field">'
            . '<label for="jankx_card_cvv">' . esc_html__('CVV', 'base-ecommerce') . '</label>'
            . '<input type="text" id="jankx_card_cvv" name="card_cvv" class="jankx-input" '
            . 'placeholder="283" maxlength="4" autocomplete="cc-csc">'
            . '</div>'
            . '</div>'
            . '<div class="jankx-field">'
            . '<label for="jankx_card_holder">' . esc_html__('Tên chủ thẻ', 'base-ecommerce') . '</label>'
            . '<input type="text" id="jankx_card_holder" name="card_holder" class="jankx-input" '
            . 'placeholder="NGUYEN VAN AN" style="text-transform:uppercase" autocomplete="cc-name">'
            . '</div>'
            . '<div class="jankx-security-notice">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
            . '<span>' . esc_html__('Thông tin thẻ của bạn đã được mã hóa và bảo mật tuyệt đối', 'base-ecommerce') . '</span>'
            . '</div>'
            . '</div>';
    }

    protected function renderQrCodePanel(): string
    {
        return '<div class="jankx-qr-panel">'
            . '<p class="jankx-payment-desc">'
            . esc_html__('Quét mã QR bằng ứng dụng ngân hàng để thanh toán. Mã QR sẽ hiển thị sau khi bạn xác nhận đơn hàng.', 'base-ecommerce')
            . '</p>'
            . '</div>';
    }
}