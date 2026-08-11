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

        $output = '<div class="jankx-tab-panel jankx-tab-orders">';
        $output .= '<div class="jankx-order-detail">';

        // Back link
        $output .= '<div class="jankx-order-detail-nav">'
            . '<a href="' . esc_url($this->getOrdersUrl()) . '" class="jankx-order-back-link">'
            . '&larr; ' . esc_html__('Back to orders', 'jankx')
            . '</a>'
            . '</div>';

        // Hero header card
        $output .= '<div class="jankx-order-hero jankx-order-hero--' . esc_attr($status) . '">';
        $output .= '<div class="jankx-order-hero-top">';
        $output .= '<div class="jankx-order-hero-id">';
        $output .= '<span class="jankx-order-hero-label">' . esc_html__('Order', 'jankx') . '</span>';
        $output .= '<strong class="jankx-order-hero-number">' . esc_html($order->getOrderNumber()) . '</strong>';
        $output .= '</div>';
        $output .= '<span class="jankx-order-status-pill jankx-order-status-pill--' . esc_attr($status) . '">'
            . esc_html($this->getStatusLabel($status)) . '</span>';
        $output .= '</div>';
        $output .= '<div class="jankx-order-hero-foot">';
        $output .= '<div class="jankx-order-hero-date">'
            . '<span class="jankx-order-hero-label">' . esc_html__('Placed on', 'jankx') . '</span>'
            . '<strong>' . esc_html($dateCreated) . '</strong>'
            . '</div>';
        $output .= '<div class="jankx-order-hero-total">'
            . '<span class="jankx-order-hero-label">' . esc_html__('Total', 'jankx') . '</span>'
            . '<strong>' . esc_html($this->formatPrice($order->getTotal())) . '</strong>'
            . '</div>';
        $output .= '</div>';
        $output .= '</div>';

        // Status progress stepper
        $output .= $this->renderOrderProgress($status);

        // Two-column layout: items (main) + facts (sidebar)
        $output .= '<div class="jankx-order-detail-grid">';
        $output .= '<div class="jankx-order-detail-main">';

        // Items panel
        $output .= '<div class="jankx-order-panel">';
        $output .= '<h3 class="jankx-order-panel-title">' . esc_html__('Order items', 'jankx') . '</h3>';
        $items = $order->getItems();
        if (empty($items)) {
            $output .= '<p class="text-muted">' . esc_html__('No items.', 'jankx') . '</p>';
        } else {
            $subtotal = 0.0;
            $output .= '<div class="jankx-order-items">';
            foreach ($items as $item) {
                $subtotal += $item->getTotal();
                $output .= '<div class="jankx-order-item">';
                $output .= $this->getItemThumbnail($item);
                $output .= '<div class="jankx-order-item-info">';
                $output .= '<span class="jankx-order-item-name">' . esc_html($item->getName()) . '</span>';
                $output .= '<span class="jankx-order-item-qty">&times; ' . esc_html($item->getQuantity()) . '</span>';
                $output .= '</div>';
                $output .= '<div class="jankx-order-item-price">' . esc_html($this->formatPrice($item->getTotal())) . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';

            $output .= '<div class="jankx-order-totals">';
            $output .= '<div class="jankx-order-totals-row">'
                . '<span>' . esc_html__('Subtotal', 'jankx') . '</span>'
                . '<strong>' . esc_html($this->formatPrice($subtotal)) . '</strong>'
                . '</div>';
            if (abs($subtotal - $order->getTotal()) > 0.01) {
                $output .= '<div class="jankx-order-totals-row">'
                    . '<span>' . esc_html__('Shipping & handling', 'jankx') . '</span>'
                    . '<strong>' . esc_html($this->formatPrice($order->getTotal() - $subtotal)) . '</strong>'
                    . '</div>';
            }
            $output .= '<div class="jankx-order-totals-row jankx-order-totals-grand">'
                . '<span>' . esc_html__('Total', 'jankx') . '</span>'
                . '<strong>' . esc_html($this->formatPrice($order->getTotal())) . '</strong>'
                . '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';

        // Customer notes panel
        $notes = $order->getNotes(true);
        if (!empty($notes)) {
            $output .= '<div class="jankx-order-panel">';
            $output .= '<h3 class="jankx-order-panel-title">' . esc_html__('Notes', 'jankx') . '</h3>';
            $output .= '<ul class="jankx-order-notes">';
            foreach ($notes as $note) {
                $output .= '<li>'
                    . '<p>' . esc_html($note['content'] ?? '') . '</p>'
                    . '<span class="jankx-order-note-date">'
                    . esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($note['date'] ?? '')))
                    . '</span>'
                    . '</li>';
            }
            $output .= '</ul>';
            $output .= '</div>';
        }

        $output .= '</div>'; // end main

        // Sidebar facts
        $output .= '<div class="jankx-order-detail-aside">';

        $output .= '<div class="jankx-order-panel">';
        $output .= '<h3 class="jankx-order-panel-title">' . esc_html__('Customer', 'jankx') . '</h3>';
        $output .= '<ul class="jankx-order-facts">';
        $output .= '<li><span>' . esc_html__('Name', 'jankx') . '</span><strong>' . esc_html($order->getCustomerName()) . '</strong></li>';
        $output .= '<li><span>' . esc_html__('Email', 'jankx') . '</span><strong>' . esc_html($order->getCustomerEmail()) . '</strong></li>';
        if ($order->getCustomerPhone()) {
            $output .= '<li><span>' . esc_html__('Phone', 'jankx') . '</span><strong>' . esc_html($order->getCustomerPhone()) . '</strong></li>';
        }
        if ($order->getCustomerAddress()) {
            $output .= '<li><span>' . esc_html__('Address', 'jankx') . '</span><strong>' . esc_html($order->getCustomerAddress()) . '</strong></li>';
        }
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '<div class="jankx-order-panel">';
        $output .= '<h3 class="jankx-order-panel-title">' . esc_html__('Payment', 'jankx') . '</h3>';
        $output .= '<ul class="jankx-order-facts">';
        $output .= '<li><span>' . esc_html__('Method', 'jankx') . '</span><strong>' . esc_html($this->getPaymentMethodLabel($order->getPaymentMethod())) . '</strong></li>';
        $output .= '<li><span>' . esc_html__('Status', 'jankx') . '</span>'
            . '<strong class="jankx-order-status-text jankx-order-status-text--' . esc_attr($status) . '">'
            . esc_html($this->getStatusLabel($status)) . '</strong></li>';
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '</div>'; // end aside
        $output .= '</div>'; // end grid

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function renderOrderProgress(string $status): string
    {
        $steps = [
            Order::STATUS_PENDING    => __('Placed', 'jankx'),
            Order::STATUS_PROCESSING => __('Processing', 'jankx'),
            Order::STATUS_COMPLETED  => __('Completed', 'jankx'),
        ];

        $isTerminal = in_array($status, [Order::STATUS_FAILED, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true);

        $currentIndex = array_search($status, array_keys($steps), true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $output = '<div class="jankx-order-progress">';
        if ($isTerminal) {
            $output .= '<div class="jankx-order-step jankx-order-step--terminal jankx-order-step--done">'
                . '<span class="jankx-order-step-dot"></span>'
                . '<span class="jankx-order-step-label">' . esc_html($this->getStatusLabel($status)) . '</span>'
                . '</div>';
        } else {
            foreach ($steps as $stepStatus => $label) {
                $state = '';
                $stepIndex = array_search($stepStatus, array_keys($steps), true);
                if ($stepIndex < $currentIndex) {
                    $state = ' jankx-order-step--done';
                } elseif ($stepIndex === $currentIndex) {
                    $state = ' jankx-order-step--active';
                }
                $output .= '<div class="jankx-order-step' . $state . '">'
                    . '<span class="jankx-order-step-dot"></span>'
                    . '<span class="jankx-order-step-label">' . esc_html($label) . '</span>'
                    . '</div>';
            }
        }
        $output .= '</div>';

        return $output;
    }

    protected function getItemThumbnail(OrderItem $item): string
    {
        $url = get_the_post_thumbnail_url($item->getProductId(), 'thumbnail');
        $class = 'jankx-order-item-thumb';

        if ($url) {
            return '<span class="' . $class . '"><img src="' . esc_url($url) . '" alt="' . esc_attr($item->getName()) . '" loading="lazy"></span>';
        }

        return '<span class="' . $class . ' jankx-order-item-thumb--placeholder">'
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
        $output .= '<div class="jankx-empty-state">'
            . '<span class="jankx-empty-icon" aria-hidden="true">&#128203;</span>'
            . '<p>' . esc_html__('Order not found or you do not have permission to view it.', 'jankx') . '</p>'
            . '<a href="' . esc_url($this->getOrdersUrl()) . '" class="jankx-btn jankx-btn-outline">'
            . esc_html__('Back to orders', 'jankx')
            . '</a>'
            . '</div>';
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
