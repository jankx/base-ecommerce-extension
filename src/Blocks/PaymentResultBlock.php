<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;

class PaymentResultBlock extends Block
{
    const MAGIC_ORDER_NUMBER = '1234567890';

    protected $blockId = 'jankx/payment-result';

    public function render($attributes = [], $content = '', $block = null)
    {
        $is_editor = defined('REST_REQUEST') && REST_REQUEST
            && !empty($_SERVER['REQUEST_URI'])
            && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false;

        $orderNumber = isset($_GET['order_number']) ? sanitize_text_field($_GET['order_number']) : '';

        // Magic order: auto-generate demo data, no DB lookup required.
        if ($orderNumber === self::MAGIC_ORDER_NUMBER) {
            return $this->renderMagicSuccess();
        }

        // Public screen: no login needed, just show the order info.
        $order = $orderNumber ? Order::findByOrderNumber($orderNumber) : null;

        if (!$order) {
            return $this->renderAccessNotice(__('Không tìm thấy đơn hàng tương ứng.', 'base-ecommerce'), $is_editor);
        }

        $status = $order->getStatus();
        if (in_array($status, [Order::STATUS_COMPLETED], true)) {
            $state = 'success';
        } elseif (in_array($status, [Order::STATUS_FAILED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
            $state = 'failed';
        } else {
            $state = 'pending';
        }

        $rows = [
            [
                'label' => __('Mã đơn hàng', 'base-ecommerce'),
                'value' => '#' . $order->getOrderNumber(),
                'icon'  => 'hash',
            ],
            [
                'label' => __('Thời điểm giao dịch', 'base-ecommerce'),
                'value' => date_i18n('d/m/Y H:i:s', strtotime($order->getDateCreated())),
                'icon'  => 'clock',
            ],
            [
                'label' => __('Thông tin giao hàng', 'base-ecommerce'),
                'value' => trim(trim((string) $order->getCustomerName()) . ' - ' . trim((string) $order->getCustomerPhone()), ' -'),
                'icon'  => 'user',
            ],
            [
                'label' => __('Giá trị đơn hàng', 'base-ecommerce'),
                'value' => CurrencyManager::formatPrice($order->getTotal()),
                'icon'  => 'tag',
            ],
            [
                'label' => __('Tình trạng đơn hàng', 'base-ecommerce'),
                'value' => $this->getStatusLabel($status),
                'icon'  => 'box',
                'class' => 'jankx-payment-success-value--status jankx-payment-success-value--' . esc_attr($status),
            ],
            [
                'label' => __('Hình thức thanh toán', 'base-ecommerce'),
                'value' => $this->getPaymentMethodLabel($order->getPaymentMethod()),
                'icon'  => 'card',
            ],
        ];

        return $this->renderResultCard($rows, $state, $order->getOrderNumber(), self::get_orders_url());
    }

    /**
     * Render the result screen from a generated (fake) dataset. Used when the
     * magic order number is requested so the page can be tested without a real
     * order: every field is auto-generated on the fly.
     */
    protected function renderMagicSuccess(): string
    {
        $demoNames = ['Ngọc Mai', 'Minh Anh', 'Tuấn Kiệt', 'Thu Hà', 'Đức Huy', 'Phương Linh'];
        $demoTotals = [1100000, 2350000, 890000, 3600000, 1450000, 5200000];
        $demoMethods = [
            'OnePay – Visa/Mastercard',
            'Quét mã QR',
            'Chuyển khoản ngân hàng',
            'MoMo',
        ];

        $name = $demoNames[array_rand($demoNames)];
        $phone = '09' . random_int(1000000, 9999999);
        $total = $demoTotals[array_rand($demoTotals)];
        $method = $demoMethods[array_rand($demoMethods)];

        $rows = [
            [
                'label' => __('Mã đơn hàng', 'base-ecommerce'),
                'value' => '#' . self::MAGIC_ORDER_NUMBER,
                'icon'  => 'hash',
            ],
            [
                'label' => __('Thời điểm giao dịch', 'base-ecommerce'),
                'value' => date_i18n('d/m/Y H:i:s', current_time('timestamp')),
                'icon'  => 'clock',
            ],
            [
                'label' => __('Thông tin giao hàng', 'base-ecommerce'),
                'value' => $name . ' - ' . $phone,
                'icon'  => 'user',
            ],
            [
                'label' => __('Giá trị đơn hàng', 'base-ecommerce'),
                'value' => CurrencyManager::formatPrice($total),
                'icon'  => 'tag',
            ],
            [
                'label' => __('Tình trạng đơn hàng', 'base-ecommerce'),
                'value' => $this->getStatusLabel(Order::STATUS_COMPLETED),
                'icon'  => 'box',
                'class' => 'jankx-payment-success-value--status jankx-payment-success-value--completed',
            ],
            [
                'label' => __('Hình thức thanh toán', 'base-ecommerce'),
                'value' => $method,
                'icon'  => 'card',
            ],
        ];

        return $this->renderResultCard($rows, 'success', '', '');
    }

    /**
     * Render the result card for a given state.
     *
     * @param string $state One of: success | failed | pending.
     */
    protected function renderResultCard(array $rows, string $state, string $orderNumber, string $ordersUrl): string
    {
        if (!in_array($state, ['success', 'failed', 'pending'], true)) {
            $state = 'pending';
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-payment-success jankx-payment-success--' . $state,
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        // Hero
        if ($state === 'failed') {
            $heroIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="44" height="44"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            $heroTitle = __('Thanh toán thất bại!', 'base-ecommerce');
            $heroSub = __('Xin lỗi, đơn hàng của bạn chưa được thanh toán. Vui lòng thử lại thanh toán hoặc liên hệ bộ phận hỗ trợ.', 'base-ecommerce');
        } else {
            $heroIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="44" height="44"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            $heroTitle = $state === 'success'
                ? __('Thanh toán thành công!', 'base-ecommerce')
                : __('Thông tin thanh toán', 'base-ecommerce');
            $heroSub = $state === 'success'
                ? __('Cảm ơn bạn! Đơn hàng của bạn đã được thanh toán thành công.', 'base-ecommerce')
                : __('Cảm ơn bạn đã đặt hàng. Chi tiết thanh toán bên dưới.', 'base-ecommerce');
        }

        $output .= '<div class="jankx-payment-success-hero">';
        $output .= '<span class="jankx-payment-success-icon" aria-hidden="true">' . $heroIcon . '</span>';
        $output .= '<h1 class="jankx-payment-success-title">' . esc_html($heroTitle) . '</h1>';
        $output .= '<p class="jankx-payment-success-sub">' . esc_html($heroSub) . '</p>';
        $output .= '</div>';

        // Info panel
        $output .= '<div class="jankx-payment-success-card">';
        $output .= '<div class="jankx-payment-success-card-head">';
        $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
        $output .= '<h2 class="jankx-payment-success-card-title">' . esc_html__('Thông tin thanh toán', 'base-ecommerce') . '</h2>';
        $output .= '</div>';

        foreach ($rows as $row) {
            $output .= '<div class="jankx-payment-success-row">';
            $output .= '<span class="jankx-payment-success-row-icon">' . $this->getIcon($row['icon']) . '</span>';
            $output .= '<div class="jankx-payment-success-row-body">';
            $output .= '<span class="jankx-payment-success-row-label">' . esc_html($row['label']) . '</span>';
            $output .= '<strong class="jankx-payment-success-row-value ' . ($row['class'] ?? '') . '">' . esc_html($row['value']) . '</strong>';
            $output .= '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';

        // Actions
        $actions = [];
        if ($state === 'failed' && $orderNumber && $ordersUrl) {
            $actions[] = '<a href="' . esc_url(trailingslashit($ordersUrl) . $orderNumber . '/') . '" class="jankx-payment-success-btn jankx-payment-success-btn--primary">'
                . esc_html__('Thử lại thanh toán', 'base-ecommerce') . '</a>';
        } elseif ($orderNumber && $ordersUrl) {
            $actions[] = '<a href="' . esc_url(trailingslashit($ordersUrl) . $orderNumber . '/') . '" class="jankx-payment-success-btn jankx-payment-success-btn--primary">'
                . esc_html__('Xem đơn hàng', 'base-ecommerce') . '</a>';
        }
        $actions[] = '<a href="' . esc_url(home_url('/')) . '" class="jankx-payment-success-btn">'
            . esc_html__('Tiếp tục mua sắm', 'base-ecommerce') . '</a>';

        if (!empty($actions)) {
            $output .= '<div class="jankx-payment-success-actions">' . implode('', $actions) . '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    public static function get_orders_url(): string
    {
        $accountPageId = (int) get_option('jankx_my_account_page_id', 0);
        if (!$accountPageId) {
            return '';
        }

        $accountUrl = get_permalink($accountPageId);

        return $accountUrl ? trailingslashit($accountUrl) . 'orders/' : '';
    }

    protected function renderAccessNotice(string $message, bool $isEditor = false): string
    {
        if ($isEditor) {
            $message = __('Block chọn hiển thị trên trang kết quả thanh toán.', 'base-ecommerce');
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-payment-success jankx-payment-success--notice',
        ]);

        return '<div ' . $wrapperAttrs . '>'
            . '<span class="jankx-payment-success-icon" aria-hidden="true">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
            . '</span>'
            . '<p>' . esc_html($message) . '</p>'
            . '</div>';
    }

    protected function getStatusLabel(string $status): string
    {
        $labels = [
            Order::STATUS_PENDING    => __('Chờ thanh toán', 'base-ecommerce'),
            Order::STATUS_PROCESSING => __('Đang xử lý', 'base-ecommerce'),
            Order::STATUS_COMPLETED  => __('Đã thanh toán', 'base-ecommerce'),
            Order::STATUS_SHIPPING   => __('Đang vận chuyển', 'base-ecommerce'),
            Order::STATUS_FAILED     => __('Thanh toán thất bại', 'base-ecommerce'),
            Order::STATUS_CANCELLED  => __('Đã hủy', 'base-ecommerce'),
            Order::STATUS_REFUNDED   => __('Đã hoàn tiền', 'base-ecommerce'),
        ];

        return $labels[$status] ?? Order::getStatusLabel($status);
    }

    protected function getPaymentMethodLabel(string $method): string
    {
        $labels = [
            'cod'           => __('COD', 'base-ecommerce'),
            'bank_transfer' => __('Chuyển khoản ngân hàng', 'base-ecommerce'),
            'qrviet'        => __('Quét mã QR', 'base-ecommerce'),
        ];
        if (isset($labels[$method])) {
            return $labels[$method];
        }

        if ($method === '') {
            return '—';
        }

        // Fallback to the gateway display name when available.
        $gatewayManager = class_exists('\Jankx\Extensions\PaymentSystem\Gateways\GatewayManager')
            ? \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance()
            : null;
        $gateway = $gatewayManager ? $gatewayManager->get($method) : null;

        return $gateway && is_callable([$gateway, 'getName']) ? (string) $gateway->getName() : (string) $method;
    }

    protected function getIcon(string $name): string
    {
        $icons = [
            'hash'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>',
            'clock' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'user'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'tag'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
            'box'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
            'card'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        ];

        return $icons[$name] ?? '';
    }
}