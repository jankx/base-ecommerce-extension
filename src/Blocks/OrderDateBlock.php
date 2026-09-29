<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Order\OrderItemContext;

class OrderDateBlock extends Block
{
    protected $blockId = 'jankx/order-date';

    public function render($attributes = [], $content = '', $block = null)
    {
        $order = OrderItemContext::get();
        if (!$order) {
            return $this->editorPlaceholder();
        }

        $showLabel = !array_key_exists('showLabel', $attributes) || !empty($attributes['showLabel']);
        $timestamp = strtotime($order->getDateCreated());

        $output = '<div class="jankx-order-field jankx-order-field--date">';
        if ($showLabel) {
            $output .= '<span class="jankx-order-field__label">' . esc_html__('Ngày đặt hàng:', 'base-ecommerce') . '</span>';
        }
        $output .= '<span class="jankx-order-field__value">'
            . esc_html(date_i18n('H:i', $timestamp) . ' ' . date_i18n(get_option('date_format'), $timestamp))
            . '</span>';
        $output .= '</div>';

        return $output;
    }

    protected function editorPlaceholder(): string
    {
        return '<div class="jankx-order-field jankx-order-field--date">'
            . '<span class="jankx-order-field__label">' . esc_html__('Ngày đặt hàng:', 'base-ecommerce') . '</span>'
            . '<span class="jankx-order-field__value">10:00 16/10/2026</span>'
            . '</div>';
    }
}
