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

    /**
     * Resolve the display payload (type / icon / text / icon position) of a
     * payment method.
     *
     * Registered gateways (AbstractGateway subclasses) provide their own
     * display via getDisplay() – including icon/text filters and the
     * icon-empty fallback. Built-in methods (bank transfer, COD) follow the
     * same contract with the block's own icons so every method on the
     * checkout page can be controlled through the same filter tags.
     */
    protected function getMethodDisplay(string $slug, string $label): array
    {
        $managerClass = '\Jankx\Extensions\PaymentSystem\Gateways\GatewayManager';
        $gatewayClass = '\Jankx\Extensions\PaymentSystem\Gateways\AbstractGateway';

        if (class_exists($managerClass) && class_exists($gatewayClass)) {
            $gateway = $managerClass::getInstance()->get($slug);
            if ($gateway instanceof $gatewayClass) {
                $display = $gateway->getDisplay();
                if (trim($display['text']) === '') {
                    $display['text'] = $label;
                } elseif ($display['text'] === $gateway->getName()) {
                    $display['text'] = $label;
                }
                return $display;
            }
        }

        $type = (string) apply_filters("jankx/payment/gateway/{$slug}/display_type", 'icon_text', $slug);
        if (!in_array($type, ['icon', 'text', 'icon_text'], true)) {
            $type = 'icon_text';
        }

        $position = (string) apply_filters("jankx/payment/gateway/{$slug}/icon_position", 'left', $slug);
        if (!in_array($position, ['left', 'right'], true)) {
            $position = 'left';
        }

        $icon = (string) apply_filters("jankx/payment/gateway/{$slug}/icon", $this->getMethodIcon($slug), $slug);
        $text = (string) apply_filters("jankx/payment/gateway/{$slug}/text", $label, $slug);

        if ($type === 'text') {
            $icon = '';
        }
        if (trim($icon) === '') {
            $type = 'text';
            $icon = '';
        }

        return [
            'type'         => $type,
            'icon'         => $icon,
            'text'         => $text,
            'icon_position' => $position,
        ];
    }

    /**
     * Render a payment method tab according to its display payload.
     */
    protected function renderMethodTab(string $slug, array $display, bool $isActive): string
    {
        $type = $display['type'];
        $text = $display['text'];

        $classes = [
            'jankx-payment-tab',
            'jankx-payment-tab--' . str_replace('_', '-', $type),
            'jankx-payment-tab--icon-' . $display['icon_position'],
        ];
        if ($isActive) {
            $classes[] = 'jankx-payment-tab--active';
        }

        $inner = '';
        if ($type !== 'text' && $display['icon'] !== '') {
            $inner .= '<span class="jankx-payment-tab-icon" aria-hidden="true">' . $display['icon'] . '</span>';
        }
        if ($type !== 'icon') {
            $inner .= '<span class="jankx-payment-tab-label">' . esc_html($text) . '</span>';
        }

        $attributes = ' type="button"'
            . ' role="tab"'
            . ' data-method="' . esc_attr($slug) . '"'
            . ' aria-selected="' . ($isActive ? 'true' : 'false') . '"'
            . ' title="' . esc_attr($text) . '"';
        if ($type === 'icon') {
            // Icon-only tab: keep an accessible name.
            $attributes .= ' aria-label="' . esc_attr($text) . '"';
        }

        return '<button' . $attributes . ' class="' . esc_attr(implode(' ', $classes)) . '">'
            . $inner
            . '</button>';
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
            $display = $this->getMethodDisplay($slug, $label);
            $output .= $this->renderMethodTab($slug, $display, $slug === $firstSlug);
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

        // Terms checkbox (only when required by the Payment settings)
        if (get_option('jankx_require_terms_acceptance')) {
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
        }

        $output .= '</div>'; // .jankx-section-body
        $output .= '</div>';

        return $output;
    }

    protected function isGatewayAvailable(string $slug): bool
    {
        $managerClass = '\Jankx\Extensions\PaymentSystem\Gateways\GatewayManager';
        $gatewayClass = '\Jankx\Extensions\PaymentSystem\Gateways\AbstractGateway';

        if (class_exists($managerClass) && class_exists($gatewayClass)) {
            $gateway = $managerClass::getInstance()->get($slug);
            if ($gateway instanceof $gatewayClass) {
                return $gateway->isAvailable();
            }
        }

        return true;
    }

    protected function renderMethodPanel(string $slug, string $label): string
    {
        switch ($slug) {
            case 'bank_transfer':
                return $this->renderBankTransferPanel();
            case 'qr_code':
                return $this->renderQrCodePanel();
            case 'qrviet':
                return '<div class="jankx-qrviet-panel">'
                    . '<p class="jankx-payment-desc">'
                    . esc_html__('Sau khi đặt hàng, bạn sẽ được chuyển đến màn hình chi tiết đơn hàng để quét mã QR thanh toán bằng ứng dụng ngân hàng.', 'base-ecommerce')
                    . '</p>'
                    . '</div>';
            case 'onepay':
                return $this->renderCreditCardPanel();
            case 'cod':
                return '<p class="jankx-payment-desc">'
                    . esc_html__('Thanh toán bằng tiền mặt khi nhận hàng/dịch vụ.', 'base-ecommerce')
                    . '</p>';
            default:
                if (!$this->isGatewayAvailable($slug)) {
                    if (current_user_can('manage_options')) {
                        return '<p class="jankx-payment-desc jankx-payment-desc--error">'
                            . sprintf(
                                /* translators: 1: payment method name, 2: settings url */
                                esc_html__('Phương thức %1$s chưa được cấu hình. <a href="%2$s">Cấu hình ngay</a>.', 'base-ecommerce'),
                                esc_html($label),
                                esc_url(admin_url('admin.php?page=jankx-ecommerce-settings&tab=payment&gateway=' . $slug))
                            )
                            . '</p>';
                    }
                    return '<p class="jankx-payment-desc jankx-payment-desc--error">'
                        . esc_html__('Phương thức thanh toán này hiện chưa khả dụng. Vui lòng liên hệ bộ phận hỗ trợ để được hỗ trợ.', 'base-ecommerce')
                        . '</p>';
                }
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
            // The brand badge is filled in by frontend.js as soon as the typed
            // IIN matches a known scheme - it stays empty for unknown cards.
            . '<div class="jankx-card-number-wrap">'
            . '<input type="text" id="jankx_card_number" name="card_number" class="jankx-input" '
            . 'placeholder="4220 2923 2332 1002" maxlength="19" autocomplete="cc-number" inputmode="numeric">'
            . '<span class="jankx-card-brand" role="img" hidden></span>'
            . '</div>'
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