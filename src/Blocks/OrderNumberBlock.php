<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Order\OrderItemContext;

class OrderNumberBlock extends Block
{
    protected $blockId = 'jankx/order-number';

    public function render($attributes = [], $content = '', $block = null)
    {
        $order = OrderItemContext::get();
        if (!$order) {
            return $this->editorPlaceholder();
        }

        $showLabel = !array_key_exists('showLabel', $attributes) || !empty($attributes['showLabel']);
        $detailUrl = $this->getOrderDetailUrl($order->getOrderNumber());

        $output = '<div class="jankx-order-field jankx-order-field--number">';
        if ($showLabel) {
            $output .= '<span class="jankx-order-field__label">' . esc_html__('Mã đơn hàng', 'base-ecommerce') . '</span>';
        }
        $output .= '<a class="jankx-order-number" href="' . esc_url($detailUrl) . '">'
            . esc_html('#' . $order->getOrderNumber())
            . '</a>';
        $output .= '</div>';

        return $output;
    }

    protected function editorPlaceholder(): string
    {
        return '<div class="jankx-order-field jankx-order-field--number">'
            . '<span class="jankx-order-field__label">' . esc_html__('Mã đơn hàng', 'base-ecommerce') . '</span>'
            . '<span class="jankx-order-number">#103204872</span>'
            . '</div>';
    }

    protected function getOrderDetailUrl(string $orderNumber): string
    {
        $pageId = get_option('jankx_my_account_page_id', 0);
        $baseUrl = $pageId ? get_permalink($pageId) : home_url('/tai-khoan-cua-toi/');

        return rtrim($baseUrl, '/') . '/orders/' . $orderNumber . '/';
    }
}
