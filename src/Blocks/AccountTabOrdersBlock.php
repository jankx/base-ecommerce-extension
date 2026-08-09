<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderPostType;

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
            foreach ($orders as $orderPost) {
                $output .= $this->renderOrderCard(new Order($orderPost->ID));
            }
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderOrderCard(Order $order): string
    {
        $status = $order->getStatus();

        $output = '<div class="jankx-order-card">';
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
            . '</div>';

        $output .= '</div>';

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
        if (!post_type_exists(OrderPostType::POST_TYPE)) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => OrderPostType::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_customer_id',
                    'value' => $userId,
                    'compare' => '=',
                ],
            ],
        ]);

        return $query->posts;
    }

    protected function formatPrice(float $price): string
    {
        $currency = (string) apply_filters('jankx/ecommerce/currency', 'VND');
        $symbol = $currency === 'VND' ? 'đ' : $currency;

        return (string) apply_filters(
            'jankx/ecommerce/price_format',
            number_format($price, 0, ',', '.') . $symbol,
            $price,
            $currency
        );
    }
}
