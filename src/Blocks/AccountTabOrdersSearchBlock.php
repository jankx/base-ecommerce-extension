<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;

/**
 * Search + date range form for the orders list:
 * Tìm kiếm đơn hàng / Từ ngày / Đến ngày.
 */
class AccountTabOrdersSearchBlock extends Block
{
    protected $blockId = 'jankx/account-tab-orders-search';

    public function render($attributes = [], $content = '', $block = null)
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $dateFrom = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $dateTo = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

        $output = '<form class="jankx-order-search" method="get" action="' . esc_url($this->getOrdersUrl()) . '">';

        if (!empty($_GET['status'])) {
            $output .= '<input type="hidden" name="status" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['status']))) . '">';
        }

        $output .= '<input type="search" class="jankx-order-search__input" name="s" '
            . 'placeholder="' . esc_attr__('Tìm kiếm đơn hàng', 'base-ecommerce') . '" '
            . 'value="' . esc_attr($search) . '">';

        $output .= '<label class="jankx-order-search__date">'
            . '<span>' . esc_html__('Từ ngày', 'base-ecommerce') . '</span>'
            . '<input type="date" name="date_from" value="' . esc_attr($dateFrom) . '">'
            . '</label>';

        $output .= '<label class="jankx-order-search__date">'
            . '<span>' . esc_html__('Đến ngày', 'base-ecommerce') . '</span>'
            . '<input type="date" name="date_to" value="' . esc_attr($dateTo) . '">'
            . '</label>';

        $output .= '<button type="submit" class="jankx-btn jankx-btn-primary jankx-order-search__submit">'
            . esc_html__('Tìm kiếm', 'base-ecommerce')
            . '</button>';

        $output .= '<a class="jankx-order-search__reset" href="' . esc_url($this->getOrdersUrl()) . '">'
            . esc_html__('Xóa bộ lọc', 'base-ecommerce')
            . '</a>';

        $output .= '</form>';

        return $output;
    }

    protected function getOrdersUrl(): string
    {
        $pageId = get_option('jankx_my_account_page_id', 0);
        $baseUrl = $pageId ? get_permalink($pageId) : home_url('/tai-khoan-cua-toi/');

        return rtrim($baseUrl, '/') . '/orders/';
    }
}
