<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Status filter tabs for the orders list:
 * Tất cả / Chờ thanh toán / Đã hoàn thành / Đã hủy.
 */
class AccountTabOrdersFiltersBlock extends Block
{
    protected $blockId = 'jankx/account-tab-orders-filters';

    public function render($attributes = [], $content = '', $block = null)
    {
        $tabs = [
            ''            => __('Tất cả', 'base-ecommerce'),
            'pending'     => Order::getStatusLabel('pending'),
            'completed'   => Order::getStatusLabel('completed'),
            'cancelled'   => Order::getStatusLabel('cancelled'),
        ];

        $current = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        if (!array_key_exists($current, $tabs)) {
            $current = '';
        }

        $output = '<div class="jankx-order-filters">';
        foreach ($tabs as $slug => $label) {
            $url = $slug === ''
                ? remove_query_arg('status', $this->getOrdersUrl())
                : add_query_arg('status', $slug, $this->getOrdersUrl());
            $active = $slug === $current;

            $output .= sprintf(
                '<a class="jankx-order-filter%s" href="%s"%s>%s</a>',
                $active ? ' is-active' : '',
                esc_url($url),
                $active ? ' aria-current="page"' : '',
                esc_html($label)
            );
        }
        $output .= '</div>';

        return $output;
    }

    protected function getOrdersUrl(): string
    {
        $pageId = get_option('jankx_my_account_page_id', 0);
        $baseUrl = $pageId ? get_permalink($pageId) : home_url('/tai-khoan-cua-toi/');

        return rtrim($baseUrl, '/') . '/orders/';
    }
}
