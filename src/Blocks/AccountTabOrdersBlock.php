<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderItemContext;
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

        $result = $this->getUserOrders(get_current_user_id());
        $orders = $result['orders'];

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-tab-panel jankx-tab-orders',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<h2 class="jankx-section-title">' . esc_html__('Your orders', 'base-ecommerce') . '</h2>';

        // Classify saved inner blocks: list-level blocks render in their saved
        // order; the template block marks where the order loop goes and the
        // empty block marks where the "no orders" notice goes.
        $parts = [];
        $templateBlock = null;
        $emptyBlock = null;
        if ($block && !empty($block->inner_blocks)) {
            foreach ($block->inner_blocks as $innerBlock) {
                if ($innerBlock->name === 'jankx/account-tab-orders-template') {
                    $templateBlock = $innerBlock;
                    $parts[] = '{{JANKX_ORDER_LIST}}';
                } elseif ($innerBlock->name === 'jankx/account-tab-orders-empty') {
                    $emptyBlock = $innerBlock;
                    $parts[] = '{{JANKX_EMPTY}}';
                } elseif (in_array($innerBlock->name, ['jankx/account-tab-orders-filters', 'jankx/account-tab-orders-search'], true)) {
                    $parts[] = $innerBlock->render();
                }
            }
        } else {
            // Legacy `<!-- wp:jankx/account-tab-orders /-->` (no saved inner
            // blocks): render the default filters + search + list.
            $parts[] = (new AccountTabOrdersFiltersBlock())->render([]);
            $parts[] = (new AccountTabOrdersSearchBlock())->render([]);
        }

        if (!in_array('{{JANKX_ORDER_LIST}}', $parts, true)) {
            $parts[] = '{{JANKX_ORDER_LIST}}';
        }

        $isEmpty = empty($orders);

        if ($isEmpty) {
            // The customer picks the layout inside the empty block; pages saved
            // before it existed fall back to the default notice.
            $emptyHtml = $emptyBlock
                ? (string) $emptyBlock->render()
                : (new AccountTabOrdersEmptyBlock())->renderDefaultHtml();
        } else {
            $emptyHtml = '';
        }

        $listHtml = '';
        if (!$isEmpty) {
            $listHtml .= $this->renderList($orders, $templateBlock);
            $listHtml .= $this->renderPagination($result);
        }

        foreach ($parts as $part) {
            if ($part === '{{JANKX_ORDER_LIST}}') {
                // An explicit empty block replaces the list slot when empty.
                $output .= ($isEmpty && $emptyBlock) ? '' : ($isEmpty ? $emptyHtml : $listHtml);
            } elseif ($part === '{{JANKX_EMPTY}}') {
                $output .= $emptyHtml;
            } else {
                $output .= $part;
            }
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render the order loop: set the order context for each item and render
     * the template block (or the default template) per order.
     */
    protected function renderList(array $orders, $templateBlock = null): string
    {
        $output = '<div class="jankx-orders-list">';

        foreach ($orders as $order) {
            OrderItemContext::set($order);
            if ($templateBlock) {
                $output .= $templateBlock->render();
            } else {
                $output .= (new AccountTabOrdersTemplateBlock())->render([]);
            }
            OrderItemContext::set(null);
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
        $output .= '<div class="jankx-od" data-order-number="' . esc_attr($order->getOrderNumber()) . '" data-order-status="' . esc_attr($status) . '">';

        // Back link
        $output .= '<div class="jankx-od-nav">'
            . '<a href="' . esc_url($this->getOrdersUrl()) . '" class="jankx-od-back">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>'
            . esc_html__('Back to orders', 'base-ecommerce')
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
        $output .= '<span class="jankx-od-hero-label">' . esc_html__('Order', 'base-ecommerce') . '</span>';
        $output .= '<h1 class="jankx-od-hero-num">' . esc_html($order->getOrderNumber()) . '</h1>';
        $output .= '<span class="jankx-od-hero-date">'
            . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
            . esc_html($dateCreated) . ' &middot; ' . esc_html($timeCreated)
            . '</span>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-hero-right">';
        $output .= '<div class="jankx-od-hero-total-label">' . esc_html__('Total', 'base-ecommerce') . '</div>';
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
        $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Order items', 'base-ecommerce') . '</h3>';
        $output .= '<span class="jankx-od-card-badge">' . count($items) . '</span>';
        $output .= '</div>';

        if (empty($items)) {
            $output .= '<p class="jankx-od-empty">' . esc_html__('No items.', 'base-ecommerce') . '</p>';
        } else {
            $output .= '<div class="jankx-od-items">';
            foreach ($items as $idx => $item) {
                $output .= '<div class="jankx-od-item">';
                $output .= '<span class="jankx-od-item-idx">' . ($idx + 1) . '</span>';
                $output .= $this->getItemThumbnail($item);
                $output .= '<div class="jankx-od-item-body">';
                $productUrl = $item->getProductId() ? (string) get_permalink($item->getProductId()) : '';
                $itemName = esc_html($item->getName());
                $output .= $productUrl
                    ? '<a class="jankx-od-item-name" href="' . esc_url($productUrl) . '">' . $itemName . '</a>'
                    : '<span class="jankx-od-item-name">' . $itemName . '</span>';
                $output .= '<span class="jankx-od-item-meta">' . esc_html__('Qty', 'base-ecommerce') . ': ' . esc_html($item->getQuantity()) . '</span>';

                $itemMetaLines = $this->getOrderItemMetaLines($item);
                if (!empty($itemMetaLines)) {
                    foreach ($itemMetaLines as $metaLine) {
                        $output .= '<span class="jankx-od-item-meta jankx-od-item-meta--extra">' . esc_html($metaLine) . '</span>';
                    }
                }
                $output .= '</div>';
                $output .= '<div class="jankx-od-item-price">' . esc_html($this->formatPrice($item->getTotal())) . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';

            // Totals
            $output .= '<div class="jankx-od-totals">';
            $output .= '<div class="jankx-od-totals-row">'
                . '<span>' . esc_html__('Subtotal', 'base-ecommerce') . '</span>'
                . '<strong>' . esc_html($this->formatPrice($subtotal)) . '</strong>'
                . '</div>';
            if (abs($subtotal - $order->getTotal()) > 0.01) {
                $output .= '<div class="jankx-od-totals-row">'
                    . '<span>' . esc_html__('Shipping & handling', 'base-ecommerce') . '</span>'
                    . '<strong>' . esc_html($this->formatPrice($order->getTotal() - $subtotal)) . '</strong>'
                    . '</div>';
            }
            $output .= '<div class="jankx-od-totals-row jankx-od-totals-grand">'
                . '<span>' . esc_html__('Total', 'base-ecommerce') . '</span>'
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
            $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Notes', 'base-ecommerce') . '</h3>';
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
        $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Customer', 'base-ecommerce') . '</h3>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-facts">';
        $output .= $this->renderFactRow('user', esc_html__('Name', 'base-ecommerce'), $order->getCustomerName());
        $output .= $this->renderFactRow('mail', esc_html__('Email', 'base-ecommerce'), $order->getCustomerEmail());
        if ($order->getCustomerPhone()) {
            $output .= $this->renderFactRow('phone', esc_html__('Phone', 'base-ecommerce'), $order->getCustomerPhone());
        }
        if ($order->getCustomerAddress()) {
            $output .= $this->renderFactRow('pin', esc_html__('Address', 'base-ecommerce'), $order->getCustomerAddress());
        }
        $output .= '</div>';
        $output .= '</div>';

        // Payment card
        $output .= '<div class="jankx-od-card">';
        $output .= '<div class="jankx-od-card-head">';
        $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
        $output .= '<h3 class="jankx-od-card-title">' . esc_html__('Payment', 'base-ecommerce') . '</h3>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-facts">';
        $output .= '<div class="jankx-od-fact">';
        $output .= '<span class="jankx-od-fact-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>';
        $output .= '<div class="jankx-od-fact-body">';
        $output .= '<span class="jankx-od-fact-label">' . esc_html__('Method', 'base-ecommerce') . '</span>';
        $output .= '<strong class="jankx-od-fact-value">' . esc_html($this->getPaymentMethodLabel($order->getPaymentMethod())) . '</strong>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '<div class="jankx-od-fact">';
        $output .= '<span class="jankx-od-fact-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>';
        $output .= '<div class="jankx-od-fact-body">';
        $output .= '<span class="jankx-od-fact-label">' . esc_html__('Status', 'base-ecommerce') . '</span>';
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
        $output .= '<p>' . esc_html__('Need help with this order?', 'base-ecommerce') . '</p>';
        $output .= '<a href="mailto:support@nibitour.vn" class="jankx-od-help-link">' . esc_html__('Contact support', 'base-ecommerce') . '</a>';
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
            Order::STATUS_PENDING    => ['label' => __('Placed', 'base-ecommerce'),    'icon' => 'check-circle'],
            Order::STATUS_PROCESSING => ['label' => __('Processing', 'base-ecommerce'), 'icon' => 'spinner'],
            Order::STATUS_SHIPPING   => ['label' => __('Shipping', 'base-ecommerce'),   'icon' => 'truck'],
            Order::STATUS_COMPLETED  => ['label' => __('Completed', 'base-ecommerce'),  'icon' => 'check-circle'],
        ];

        $isTerminal = in_array($status, [Order::STATUS_FAILED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true);
        $currentIndex = array_search($status, array_keys($steps), true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $output = '<div class="wp-block-jankx-order-progress jankx-od-progress">';
        if ($isTerminal) {
            $terminalIcons = [
                Order::STATUS_FAILED     => 'x-circle',
                Order::STATUS_CANCELLED  => 'x-circle',
                Order::STATUS_REFUNDED   => 'rotate-ccw',
            ];
            $output .= $this->renderProgressStep($this->getStatusLabel($status), $terminalIcons[$status] ?? 'x-circle', 'terminal');
        } else {
            foreach ($steps as $stepStatus => $stepData) {
                $state = 'todo';
                $stepIndex = array_search($stepStatus, array_keys($steps), true);
                if ($stepIndex < $currentIndex) {
                    $state = 'done';
                } elseif ($stepIndex === $currentIndex) {
                    $state = 'active';
                }
                $output .= $this->renderProgressStep($stepData['label'], $stepData['icon'], $state);
            }
        }
        $output .= '</div>';

        return $output;
    }

    protected function renderProgressStep(string $label, string $icon, string $state): string
    {
        return render_block([
            'blockName'    => 'jankx/order-progress-step',
            'attrs'        => [
                'label' => $label,
                'icon'  => $icon,
                'state' => $state,
            ],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ]);
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
        if ($gateway === 'qrviet') {
            // If VietQR is not configured, hide the scan-QR pay card entirely.
            if (class_exists('Jankx\Extensions\PaymentSystem\Gateways\GatewayManager')) {
                $gatewayManager = \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance();
                $qrGateway = $gatewayManager->get('qrviet');
                if (!$qrGateway || !$qrGateway->isAvailable()) {
                    return '';
                }
            }

            // A dynamic QR is rendered via
            // `jankx/ecommerce/order_detail/after_payment_info`; hide the pay
            // button so there is a single, consistent payment entry point.
            $txnClass = 'Jankx\Extensions\PaymentSystem\Models\Transaction';
            $transactionId = $order->getPaymentTransactionId();
            if ($transactionId && class_exists($txnClass)) {
                $transaction = new $txnClass((int) $transactionId);
                if ($transaction->getId() && $transaction->getMeta('_qr_image') !== '') {
                    return '';
                }
            }
        }

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
        $output .= '<p>' . esc_html__('Chưa thanh toán. Nhấn nút bên dưới để hoàn tất thanh toán.', 'base-ecommerce') . '</p>';
        $output .= '<button class="jankx-od-btn jankx-od-btn--pay" '
            . 'data-rest-url="' . esc_attr($restUrl) . '" '
            . 'data-nonce="' . esc_attr($nonce) . '" '
            . 'data-order="' . esc_attr($order->getOrderNumber()) . '" '
            . 'onclick="jankxPayOrder(this)">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
            . esc_html__('Thanh toán ngay', 'base-ecommerce')
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
            $transferContent = $bankConfig['transfer_content'] ?? __('Vui lòng ghi đúng nội dung chuyển khoản để chúng tôi xác nhận đơn hàng sớm nhất.', 'base-ecommerce');
            $instructions = $bankConfig['instructions'] ?? '';

            $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>';
            $output .= '<h4>' . esc_html__('Thông tin chuyển khoản', 'base-ecommerce') . '</h4>';
            $output .= '<div class="jankx-od-bank-info">';
            if ($bankName) {
                $output .= '<p><strong>' . esc_html__('Ngân hàng:', 'base-ecommerce') . '</strong> ' . esc_html($bankName) . '</p>';
            }
            if ($accountNumber) {
                $output .= '<p><strong>' . esc_html__('Số TK:', 'base-ecommerce') . '</strong> ' . esc_html($accountNumber) . '</p>';
            }
            if ($accountHolder) {
                $output .= '<p><strong>' . esc_html__('Chủ TK:', 'base-ecommerce') . '</strong> ' . esc_html($accountHolder) . '</p>';
            }
            $output .= '<p><strong>' . esc_html__('Nội dung CK:', 'base-ecommerce') . '</strong> <code>' . esc_html($this->getPaymentContent($order)) . '</code></p>';
            if ($transferContent) {
                $output .= '<p class="description">' . esc_html($transferContent) . '</p>';
            }
            if ($instructions) {
                $output .= '<p class="description">' . nl2br(esc_html($instructions)) . '</p>';
            }
            $output .= '</div>';
        } else {
            $codConfig = get_option('jankx_built_in_gateway_cod', []);
            $codDescription = $codConfig['description'] ?? __('Đơn hàng COD sẽ được xác nhận bởi nhân viên. Vui lòng đặt cọc nếu được yêu cầu.', 'base-ecommerce');

            $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
            $output .= '<h4>' . esc_html__('Thanh toán khi nhận hàng (COD)', 'base-ecommerce') . '</h4>';
            $output .= '<p>' . esc_html($codDescription) . '</p>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function getItemThumbnail(OrderItem $item): string
    {
        $url = get_the_post_thumbnail_url($item->getProductId(), 'thumbnail');
        $link = $item->getProductId() ? (string) get_permalink($item->getProductId()) : '';
        $tag = $link ? 'a' : 'span';
        $href = $link ? ' href="' . esc_url($link) . '"' : '';

        if ($url) {
            return sprintf(
                '<%1$s class="jankx-od-item-thumb"%2$s><img src="%3$s" alt="%4$s" loading="lazy"></%1$s>',
                $tag,
                $href,
                esc_url($url),
                esc_attr($item->getName())
            );
        }

        return sprintf(
            '<%1$s class="jankx-od-item-thumb jankx-od-item-thumb--ph"%2$s>%3$s</%1$s>',
            $tag,
            $href,
            esc_html(mb_substr($item->getName(), 0, 1, 'UTF-8'))
        );
    }

    protected function getPaymentMethodLabel(string $method): string
    {
        $labels = [
            'cod'           => __('Cash on delivery', 'base-ecommerce'),
            'bank_transfer' => __('Bank transfer', 'base-ecommerce'),
            'qrviet'        => __('Quét mã QR', 'base-ecommerce'),
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
        $output .= '<h3>' . esc_html__('Order not found', 'base-ecommerce') . '</h3>';
        $output .= '<p>' . esc_html__('We couldn\'t find this order or you don\'t have permission to view it.', 'base-ecommerce') . '</p>';
        $output .= '<a href="' . esc_url($this->getOrdersUrl()) . '" class="jankx-od-btn">'
            . esc_html__('Back to orders', 'base-ecommerce')
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
            . '<span class="jankx-order-view-link">' . esc_html__('View details', 'base-ecommerce') . ' &rarr;</span>'
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

    /**
     * Extra order item metadata lines (departure date / variation / group).
     */
    protected function getOrderItemMetaLines(OrderItem $item): array
    {
        $meta = $item->getMeta();
        $lines = [];

        if (!empty($meta['departure_date'])) {
            $lines[] = date_i18n(get_option('date_format'), strtotime((string) $meta['departure_date']));
        }
        if (!empty($meta['variation_id'])) {
            $lines[] = (string) ($meta['variation_label'] ?? $meta['variation_id']);
        } elseif (!empty($meta['price_breakdown']['line_items']) && is_array($meta['price_breakdown']['line_items'])) {
            foreach ($meta['price_breakdown']['line_items'] as $lineItem) {
                $label = $lineItem['label'] ?? $lineItem['group'] ?? '';
                $qty = (int) ($lineItem['qty'] ?? 0);
                if ($label !== '' && $qty > 0) {
                    $lines[] = $label . ' x ' . $qty;
                }
            }
        }

        return $lines;
    }

    protected function getStatusLabel(string $status): string
    {
        $labels = [
            Order::STATUS_PENDING    => __('Pending', 'base-ecommerce'),
            Order::STATUS_PROCESSING => __('Processing', 'base-ecommerce'),
            Order::STATUS_COMPLETED  => __('Completed', 'base-ecommerce'),
            Order::STATUS_FAILED     => __('Failed', 'base-ecommerce'),
            Order::STATUS_CANCELLED  => __('Cancelled', 'base-ecommerce'),
            Order::STATUS_REFUNDED   => __('Refunded', 'base-ecommerce'),
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    protected function getUserOrders(int $userId): array
    {
        $user = wp_get_current_user();
        $perPage = 10;
        $currentPage = max(1, (int) ($_GET['page'] ?? 1));

        $args = [
            'per_page'    => $perPage,
            'page'        => $currentPage,
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

        // List filters (status tabs, search, date range).
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        if ($status && array_key_exists($status, Order::getStatusLabels())) {
            $args['status'] = $status;
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        if ($search !== '') {
            $args['search'] = $search;
        }

        $dateFrom = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        if ($dateFrom !== '' && strtotime($dateFrom) !== false) {
            $args['date_from'] = date('Y-m-d 00:00:00', strtotime($dateFrom));
        }

        $dateTo = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        if ($dateTo !== '' && strtotime($dateTo) !== false) {
            $args['date_to'] = date('Y-m-d 23:59:59', strtotime($dateTo));
        }

        $countArgs = $args;
        unset($countArgs['per_page'], $countArgs['page'], $countArgs['orderby'], $countArgs['order']);

        $total = OrderModel::count($countArgs);
        $rows = OrderModel::query($args);
        $totalPages = (int) ceil($total / $perPage);

        $orders = array_map(function ($row) {
            return new Order($row['id']);
        }, $rows);

        return [
            'orders'      => $orders,
            'total'       => $total,
            'total_pages' => $totalPages,
            'page'        => $currentPage,
            'per_page'    => $perPage,
        ];
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

    protected function renderPagination(array $result): string
    {
        $totalPages = $result['total_pages'];
        $currentPage = $result['page'];

        if ($totalPages <= 1) {
            return '';
        }

        $baseUrl = $this->getOrdersUrl();
        $listParams = $this->getListParams();
        $output = '<nav class="jankx-pagination" aria-label="' . esc_attr__('Orders pagination', 'base-ecommerce') . '">';
        $output .= '<div class="jankx-pagination-inner">';

        // Previous
        if ($currentPage > 1) {
            $output .= '<a class="jankx-pagination-btn jankx-pagination-prev" href="' . esc_url(add_query_arg(array_merge($listParams, ['page' => $currentPage - 1]), $baseUrl)) . '">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>'
                . '</a>';
        }

        // Page numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i === $currentPage) {
                $output .= '<span class="jankx-pagination-btn jankx-pagination-current">' . $i . '</span>';
            } else {
                $output .= '<a class="jankx-pagination-btn" href="' . esc_url(add_query_arg(array_merge($listParams, ['page' => $i]), $baseUrl)) . '">' . $i . '</a>';
            }
        }

        // Next
        if ($currentPage < $totalPages) {
            $output .= '<a class="jankx-pagination-btn jankx-pagination-next" href="' . esc_url(add_query_arg(array_merge($listParams, ['page' => $currentPage + 1]), $baseUrl)) . '">'
                . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>'
                . '</a>';
        }

        $output .= '</div>';
        $output .= '</nav>';

        return $output;
    }

    /**
     * Current list filter params so pagination links keep them.
     */
    protected function getListParams(): array
    {
        $params = [];
        foreach (['status', 's', 'date_from', 'date_to'] as $key) {
            if (!empty($_GET[$key])) {
                $params[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }

        return $params;
    }

    /**
     * Transfer content shown to the customer, prefixed with the fixed
     * site-specific payment content code.
     */
    protected function getPaymentContent(Order $order): string
    {
        if (function_exists('Jankx\\Extensions\\Ecommerce\\jankx_payment_content')) {
            return \Jankx\Extensions\Ecommerce\jankx_payment_content($order);
        }

        return $order->getOrderNumber();
    }
}
