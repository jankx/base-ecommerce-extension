<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderItem;
use Jankx\Extensions\Ecommerce\Order\OrderModel;

class AccountTabOrdersBlock extends Block
{
    protected $blockId = 'jankx/account-tab-orders';

    public function render($attributes = [], $content = '', $block = null)
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $activeTab = get_query_var('jankx_account_page');
        if (empty($activeTab)) {
            $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        }

        $is_editor = defined('REST_REQUEST') && REST_REQUEST
            && !empty($_SERVER['REQUEST_URI'])
            && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false;

        if (!$is_editor && $activeTab !== 'orders') {
            return '';
        }

        // Order detail view: /tai-khoan-cua-toi/orders/OD-000003/
        $orderNumber = $this->getRequestedOrderNumber();
        if (!$is_editor && $orderNumber) {
            return $this->renderOrderDetailPage($orderNumber);
        }

        $orders = $this->getUserOrders(get_current_user_id());

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-orders',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h2 class="jankx-section-title">' . esc_html__('Your orders', 'jankx') . '</h2>';

        if (empty($orders)) {
            $output .= '<div class="jankx-empty-state">'
                . '<span class="jankx-empty-icon" aria-hidden="true">&#128203;</span>'
                . '<p>' . esc_html__('You have no orders yet.', 'jankx') . '</p>'
                . '</div>';
        } else {
            $output .= '<div class="jankx-orders-list">';
            foreach ($orders as $order) {
                $output .= $this->renderOrderCard($order);
            }
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderOrderDetailPage(string $orderNumber): string
    {
        $order = Order::findByOrderNumber($orderNumber);

        if (!$order || !$this->orderBelongsToUser($order, wp_get_current_user())) {
            return $this->renderOrderNotFound();
        }

        return $this->renderOrderDetail($order);
    }

    protected function renderOrderDetail(Order $order): string
    {
        $status = $order->getStatus();
        $dateCreated = date_i18n(get_option('date_format'), strtotime($order->getDateCreated()));
        $timeCreated = date_i18n('H:i', strtotime($order->getDateCreated()));
        $items = $order->getItems();
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item->getTotal();
        }

        $output = '<div class="jankx-tab-panel jankx-tab-orders">';
        $output .= '<div class="jankx-od">';

        // Back link
        $output .= '<div class="jankx-od-nav">'
            . '<a href="' . esc_url($this->getOrdersUrl()) . '" class="jankx-od-back">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>'
            . esc_html__('Back to orders', 'jankx')
            . '</a>'
            . '</div>';

        // Hero header
        $output .= '<div class="jankx-od-hero jankx-od-hero--' . esc_attr($status) . '">';
        $output .= '<div class="jankx-od-hero-bg">';
        $output .= '<svg class="jankx-od-hero-wave" viewBox="0 0 600 120" preserveAspectRatio="none"><path d="M0 60 Q150 0 300 60 T600 60 V120 H0Z" fill="rgba(255,255,255,0.06)"/></svg>';
        $output .= '<div class="jankx-od-hero-circle jankx-od-hero-circle--1"></div>';
        $output .= '<div class="jankx-od-hero-circle jankx-od-hero-circle--2"></div>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-hero-content">';
        $output .= '<div class="jankx-od-hero-row">';
        $output .= '<div class="jankx-od-hero-left">';
        $output .= '<span class="jankx-od-hero-label">' . esc_html__('Order', 'jankx') . '</span>';
        $output .= '<h1 class="jankx-od-hero-num">' . esc_html($order->getOrderNumber()) . '</h1>';
        $output .= '<span class="jankx-od-hero-date">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
            . esc_html($dateCreated) . ' &middot; ' . esc_html($timeCreated)
            . '</span>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-hero-right">';
        $output .= '<div class="jankx-od-hero-total-label">' . esc_html__('Total', 'jankx') . '</div>';
        $output .= '<div class="jankx-od-hero-total">' . esc_html($this->formatPrice($order->getTotal())) . '</div>';
        $output .= '<span class="jankx-od-pill jankx-od-pill--' . esc_attr($status) . '">'
            . $this->getStatusIcon($status)
            . esc_html($this->getStatusLabel($status))
            . '</span>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        // Status progress stepper
        $output .= $this->renderOrderProgress($status);

        // Pay now button for unpaid orders
        $output .= $this->renderPayNowButton($order);

        // Allow other extensions to add content after payment info (e.g. VietQR)
        $output .= apply_filters('jankx/ecommerce/order_detail/after_payment_info', '', $order);

        // Two-column layout
        $output .= '<div class="jankx-od-grid">';

        // ── Main column ──
        $output .= '<div class="jankx-od-main">';

        // Items panel
        $output .= '<div class="jankx-od-card">';
        $output .= '<div class="jankx-od-card-head">';
        $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
        $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Order items', 'jankx') . '</h3>';
        $output .= '<span class="jankx-od-card-badge">' . count($items) . '</span>';
        $output .= '</div>';

        if (empty($items)) {
            $output .= '<p class="jankx-od-empty">' . esc_html__('No items.', 'jankx') . '</p>';
        } else {
            $output .= '<div class="jankx-od-items">';
            foreach ($items as $idx => $item) {
                $output .= '<div class="jankx-od-item">';
                $output .= '<span class="jankx-od-item-idx">' . ($idx + 1) . '</span>';
                $output .= $this->getItemThumbnail($item);
                $output .= '<div class="jankx-od-item-body">';
                $output .= '<span class="jankx-od-item-name">' . esc_html($item->getName()) . '</span>';
                $output .= '<span class="jankx-od-item-meta">' . esc_html__('Qty', 'jankx') . ': ' . esc_html($item->getQuantity()) . '</span>';
                $output .= '</div>';
                $output .= '<div class="jankx-od-item-price">' . esc_html($this->formatPrice($item->getTotal())) . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';

            // Totals
            $output .= '<div class="jankx-od-totals">';
            $output .= '<div class="jankx-od-totals-row">'
                . '<span>' . esc_html__('Subtotal', 'jankx') . '</span>'
                . '<strong>' . esc_html($this->formatPrice($subtotal)) . '</strong>'
                . '</div>';
            if (abs($subtotal - $order->getTotal()) > 0.01) {
                $output .= '<div class="jankx-od-totals-row">'
                    . '<span>' . esc_html__('Shipping & handling', 'jankx') . '</span>'
                    . '<strong>' . esc_html($this->formatPrice($order->getTotal() - $subtotal)) . '</strong>'
                    . '</div>';
            }
            $output .= '<div class="jankx-od-totals-row jankx-od-totals-grand">'
                . '<span>' . esc_html__('Total', 'jankx') . '</span>'
                . '<strong>' . esc_html($this->formatPrice($order->getTotal())) . '</strong>'
                . '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';

        // Notes
        $notes = $order->getNotes(true);
        if (!empty($notes)) {
            $output .= '<div class="jankx-od-card">';
            $output .= '<div class="jankx-od-card-head">';
            $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
            $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Notes', 'jankx') . '</h3>';
            $output .= '</div>';
            $output .= '<div class="jankx-od-notes">';
            foreach ($notes as $note) {
                $output .= '<div class="jankx-od-note">';
                $output .= '<p>' . esc_html($note['content'] ?? $note['note'] ?? '') . '</p>';
                $output .= '<time>' . esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($note['date'] ?? $note['created_at'] ?? ''))) . '</time>';
                $output .= '</div>';
            }
            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '</div>'; // end main

        // ── Sidebar ──
        $output .= '<div class="jankx-od-aside">';

        // Customer card
        $output .= '<div class="jankx-od-card jankx-od-card--accent">';
        $output .= '<div class="jankx-od-card-head">';
        $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
        $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Customer', 'jankx') . '</h3>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-facts">';
        $output .= $this->renderFactRow('user', esc_html__('Name', 'jankx'), $order->getCustomerName());
        $output .= $this->renderFactRow('mail', esc_html__('Email', 'jankx'), $order->getCustomerEmail());
        if ($order->getCustomerPhone()) {
            $output .= $this->renderFactRow('phone', esc_html__('Phone', 'jankx'), $order->getCustomerPhone());
        }
        if ($order->getCustomerAddress()) {
            $output .= $this->renderFactRow('pin', esc_html__('Address', 'jankx'), $order->getCustomerAddress());
        }
        $output .= '</div>';
        $output .= '</div>';

        // Payment card
        $output .= '<div class="jankx-od-card">';
        $output .= '<div class="jankx-od-card-head">';
        $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
        $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Payment', 'jankx') . '</h3>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-facts">';
        $output .= '<div class="jankx-od-fact">';
        $output .= '<span class="jankx-od-fact-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>';
        $output .= '<div class="jankx-od-fact-body">';
        $output .= '<span class="jankx-od-fact-label">' . esc_html__('Method', 'jankx') . '</span>';
        $output .= '<strong class="jankx-od-fact-value">' . esc_html($this->getPaymentMethodLabel($order->getPaymentMethod())) . '</strong>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-fact">';
        $output .= '<span class="jankx-od-fact-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>';
        $output .= '<div class="jankx-od-fact-body">';
        $output .= '<span class="jankx-od-fact-label">' . esc_html__('Status', 'jankx') . '</span>';
        $output .= '<strong class="jankx-od-fact-value jankx-od-status--' . esc_attr($status) . '">'
            . $this->getStatusIcon($status)
            . esc_html($this->getStatusLabel($status))
            . '</strong>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        // Help card
        $output .= '<div class="jankx-od-card jankx-od-card--help">';
        $output .= '<div class="jankx-od-help-inner">';
        $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        $output .= '<p>' . esc_html__('Need help with this order?', 'jankx') . '</p>';
        $output .= '<a href="mailto:support@nibitour.vn" class="jankx-od-help-link">' . esc_html__('Contact support', 'jankx') . '</a>';
        $output .= '</div>';
        $output .= '</div>';

        $output .= '</div>'; // end aside
        $output .= '</div>'; // end grid

        $output .= '</div>'; // end jankx-od
        $output .= '</div>'; // end jankx-tab-panel

        return $output;
    }

    protected function renderFactRow(string $icon, string $label, string $value): string
    {
        $icons = [
            'user'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'mail'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'phone' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
            'pin'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        ];

        return '<div class="jankx-od-fact">'
            . '<span class="jankx-od-fact-icon">' . ($icons[$icon] ?? '') . '</span>'
            . '<div class="jankx-od-fact-body">'
            . '<span class="jankx-od-fact-label">' . $label . '</span>'
            . '<strong class="jankx-od-fact-value">' . $value . '</strong>'
            . '</div>'
            . '</div>';
    }

    protected function getStatusIcon(string $status): string
    {
        $icons = [
            Order::STATUS_PENDING    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            Order::STATUS_PROCESSING => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>',
            Order::STATUS_COMPLETED  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            Order::STATUS_FAILED     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            Order::STATUS_CANCELLED  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            Order::STATUS_REFUNDED   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>',
        ];

        return $icons[$status] ?? '';
    }

    protected function renderOrderProgress(string $status): string
    {
        $steps = [
            Order::STATUS_PENDING    => ['label' => __('Placed', 'jankx'),    'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
            Order::STATUS_PROCESSING => ['label' => __('Processing', 'jankx'), 'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>'],
            Order::STATUS_COMPLETED  => ['label' => __('Completed', 'jankx'),  'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
        ];

        $isTerminal = in_array($status, [Order::STATUS_FAILED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true);
        $currentIndex = array_search($status, array_keys($steps), true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $output = '<div class="jankx-od-progress">';
        if ($isTerminal) {
            $output .= '<div class="jankx-od-step jankx-od-step--terminal jankx-od-step--done">'
                . '<span class="jankx-od-step-dot">' . $this->getStatusIcon($status) . '</span>'
                . '<span class="jankx-od-step-label">' . esc_html($this->getStatusLabel($status)) . '</span>'
                . '</div>';
        } else {
            foreach ($steps as $stepStatus => $stepData) {
                $state = '';
                $stepIndex = array_search($stepStatus, array_keys($steps), true);
                if ($stepIndex < $currentIndex) {
                    $state = ' jankx-od-step--done';
                } elseif ($stepIndex === $currentIndex) {
                    $state = ' jankx-od-step--active';
                }
                $output .= '<div class="jankx-od-step' . $state . '">'
                    . '<span class="jankx-od-step-dot">' . $stepData['icon'] . '</span>'
                    . '<span class="jankx-od-step-label">' . esc_html($stepData['label']) . '</span>'
                    . '</div>';
            }
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render the "Pay Now" button for unpaid orders.
     */
    protected function renderPayNowButton(Order $order): string
    {
        $status = $order->getStatus();
        if (!in_array($status, [Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
            return '';
        }

        $gateway = $order->getPaymentMethod();
        if ($gateway === 'cod' || $gateway === 'bank_transfer') {
            // For COD and bank transfer, show info instead of pay button
            return $this->renderOfflinePaymentInfo($order, $gateway);
        }

        // Online payment: show pay button
        $restUrl = rest_url('jankx/ecommerce/v1/orders/' . $order->getOrderNumber() . '/pay');
        $nonce = wp_create_nonce('wp_rest');

        $output = '<div class="jankx-od-card jankx-od-card--action">';
        $output .= '<div class="jankx-od-pay-inner">';
        $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
        $output .= '<p>' . esc_html__('Chưa thanh toán. Nhấn nút bên dưới để hoàn tất thanh toán.', 'jankx') . '</p>';
        $output .= '<button class="jankx-od-btn jankx-od-btn--pay" '
            . 'data-rest-url="' . esc_attr($restUrl) . '" '
            . 'data-nonce="' . esc_attr($nonce) . '" '
            . 'data-order="' . esc_attr($order->getOrderNumber()) . '" '
            . 'onclick="jankxPayOrder(this)">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
            . esc_html__('Thanh toán ngay', 'jankx')
            . '</button>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render offline payment info (COD / bank transfer).
     */
    protected function renderOfflinePaymentInfo(Order $order, string $gateway): string
    {
        $output = '<div class="jankx-od-card jankx-od-card--info">';
        $output .= '<div class="jankx-od-info-inner">';

        if ($gateway === 'bank_transfer') {
            $bankConfig = get_option('jankx_built_in_gateway_bank_transfer', []);
            $bankName = $bankConfig['bank_name'] ?? '';
            $accountNumber = $bankConfig['account_number'] ?? '';
            $accountHolder = $bankConfig['account_holder'] ?? '';
            $transferContent = $bankConfig['transfer_content'] ?? __('Vui lòng ghi đúng nội dung chuyển khoản để chúng tôi xác nhận đơn hàng sớm nhất.', 'jankx');
            $instructions = $bankConfig['instructions'] ?? '';

            $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>';
            $output .= '<h4>' . esc_html__('Thông tin chuyển khoản', 'jankx') . '</h4>';
            $output .= '<div class="jankx-od-bank-info">';
            if ($bankName) {
                $output .= '<p><strong>' . esc_html__('Ngân hàng:', 'jankx') . '</strong> ' . esc_html($bankName) . '</p>';
            }
            if ($accountNumber) {
                $output .= '<p><strong>' . esc_html__('Số TK:', 'jankx') . '</strong> ' . esc_html($accountNumber) . '</p>';
            }
            if ($accountHolder) {
                $output .= '<p><strong>' . esc_html__('Chủ TK:', 'jankx') . '</strong> ' . esc_html($accountHolder) . '</p>';
            }
            $output .= '<p><strong>' . esc_html__('Nội dung CK:', 'jankx') . '</strong> <code>' . esc_html($order->getOrderNumber()) . '</code></p>';
            if ($transferContent) {
                $output .= '<p class="description">' . esc_html($transferContent) . '</p>';
            }
            if ($instructions) {
                $output .= '<p class="description">' . nl2br(esc_html($instructions)) . '</p>';
            }
            $output .= '</div>';
        } else {
            $codConfig = get_option('jankx_built_in_gateway_cod', []);
            $codDescription = $codConfig['description'] ?? __('Đơn hàng COD sẽ được xác nhận bởi nhân viên. Vui lòng đặt cọc nếu được yêu cầu.', 'jankx');

            $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
            $output .= '<h4>' . esc_html__('Thanh toán khi nhận hàng (COD)', 'jankx') . '</h4>';
            $output .= '<p>' . esc_html($codDescription) . '</p>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function getItemThumbnail(OrderItem $item): string
    {
        $url = get_the_post_thumbnail_url($item->getProductId(), 'thumbnail');

        if ($url) {
            return '<span class="jankx-od-item-thumb"><img src="' . esc_url($url) . '" alt="' . esc_attr($item->getName()) . '" loading="lazy"></span>';
        }

        return '<span class="jankx-od-item-thumb jankx-od-item-thumb--ph">'
            . esc_html(mb_substr($item->getName(), 0, 1, 'UTF-8'))
            . '</span>';
    }

    protected function getPaymentMethodLabel(string $method): string
    {
        $labels = [
            'cod'           => __('Cash on delivery', 'jankx'),
            'bank_transfer' => __('Bank transfer', 'jankx'),
        ];

        return $labels[$method] ?? ($method ?: '—');
    }

    protected function renderOrderNotFound(): string
    {
        $output = '<div class="jankx-tab-panel jankx-tab-orders">';
        $output .= '<div class="jankx-od-empty">';
        $output .= '<div class="jankx-od-empty-icon">';
        $output .= '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>';
        $output .= '</div>';
        $output .= '<h3>' . esc_html__('Order not found', 'jankx') . '</h3>';
        $output .= '<p>' . esc_html__('We couldn\'t find this order or you don\'t have permission to view it.', 'jankx') . '</p>';
        $output .= '<a href="' . esc_url($this->getOrdersUrl()) . '" class="jankx-od-btn">'
            . esc_html__('Back to orders', 'jankx')
            . '</a>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function renderOrderCard(Order $order): string
    {
        $status = $order->getStatus();
        $detailUrl = $this->getOrderDetailUrl($order->getOrderNumber());

        $output = '<a class="jankx-order-card" href="' . esc_url($detailUrl) . '">';
        $output .= '<div class="jankx-order-card-head">';
        $output .= '<span class="jankx-order-number">' . esc_html($order->getOrderNumber()) . '</span>';
        $output .= '<span class="jankx-badge jankx-badge-' . esc_attr($status) . '">'
            . esc_html($this->getStatusLabel($status)) . '</span>';
        $output .= '</div>';

        $output .= '<div class="jankx-order-card-meta">'
            . '<span>' . esc_html(date_i18n(get_option('date_format'), strtotime($order->getDateCreated()))) . '</span>'
            . '<span>' . esc_html($this->getItemSummary($order)) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-order-card-foot">'
            . '<span class="jankx-order-total">' . esc_html($this->formatPrice($order->getTotal())) . '</span>'
            . '<span class="jankx-order-view-link">' . esc_html__('View details', 'jankx') . ' &rarr;</span>'
            . '</div>';

        $output .= '</a>';

        return $output;
    }

    protected function getItemSummary(Order $order): string
    {
        $items = $order->getItems();
        $summary = [];

        foreach ($items as $item) {
            $summary[] = $item->getName() . ' &times; ' . $item->getQuantity();
        }

        return implode(', ', $summary);
    }

    protected function getStatusLabel(string $status): string
    {
        $labels = [
            Order::STATUS_PENDING    => __('Pending', 'jankx'),
            Order::STATUS_PROCESSING => __('Processing', 'jankx'),
            Order::STATUS_COMPLETED  => __('Completed', 'jankx'),
            Order::STATUS_FAILED     => __('Failed', 'jankx'),
            Order::STATUS_CANCELLED  => __('Cancelled', 'jankx'),
            Order::STATUS_REFUNDED   => __('Refunded', 'jankx'),
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    protected function getUserOrders(int $userId): array
    {
        $user = wp_get_current_user();

        $args = [
            'per_page'    => 10,
            'orderby'     => 'created_at',
            'order'       => 'DESC',
        ];

        // Orders created during guest checkout carry customer_id=0 but keep the
        // customer email, so match by id OR email to include them.
        if ($userId) {
            $args['customer_id'] = $userId;
        }
        if (!empty($user->user_email)) {
            $args['customer_email'] = $user->user_email;
        }

        $rows = OrderModel::query($args);

        return array_map(function ($row) {
            return new Order($row['id']);
        }, $rows);
    }

    /**
     * Extract the order number from /tai-khoan-cua-toi/orders/{number}/
     * (or the `order` GET param as a fallback).
     */
    protected function getRequestedOrderNumber(): string
    {
        if (!empty($_GET['order'])) {
            return sanitize_text_field($_GET['order']);
        }

        $requestUri = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if ($requestUri && preg_match('#/orders/([a-zA-Z0-9_-]+)/?$#i', '/' . $requestUri, $m)) {
            return sanitize_text_field($m[1]);
        }

        return '';
    }

    /**
     * Whether the given order belongs to the current user.
     */
    protected function orderBelongsToUser(Order $order, $user): bool
    {
        $customerId = $order->getCustomerId();
        if ($customerId && (int) $customerId === (int) $user->ID) {
            return true;
        }

        if (!empty($user->user_email)) {
            return strcasecmp($order->getCustomerEmail(), $user->user_email) === 0;
        }

        return false;
    }

    protected function getOrdersUrl(): string
    {
        $pageId = get_option('jankx_my_account_page_id', 0);
        $baseUrl = $pageId ? get_permalink($pageId) : home_url('/tai-khoan-cua-toi/');

        return rtrim($baseUrl, '/') . '/orders/';
    }

    protected function getOrderDetailUrl(string $orderNumber): string
    {
        return $this->getOrdersUrl() . $orderNumber . '/';
    }

    protected function formatPrice(float $price): string
    {
        return CurrencyManager::formatPrice($price);
    }
}
