<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderItemContext;

class OrderStatusBlock extends Block
{
    protected $blockId = 'jankx/order-status';

    public function render($attributes = [], $content = '', $block = null)
    {
        $order = OrderItemContext::get();
        if (!$order) {
            return $this->editorPlaceholder();
        }

        $status = $order->getStatus();
        $showLabel = !array_key_exists('showLabel', $attributes) || !empty($attributes['showLabel']);

        $output = '<div class="jankx-order-field jankx-order-field--status">';
        if ($showLabel) {
            $output .= '<span class="jankx-order-field__label">' . esc_html__('Trạng thái', 'base-ecommerce') . '</span>';
        }
        $output .= '<span class="jankx-badge jankx-badge-' . esc_attr($status) . '">'
            . esc_html(Order::getStatusLabel($status))
            . '</span>';
        $output .= '</div>';

        return $output;
    }

    protected function editorPlaceholder(): string
    {
        return '<div class="jankx-order-field jankx-order-field--status">'
            . '<span class="jankx-order-field__label">' . esc_html__('Trạng thái', 'base-ecommerce') . '</span>'
            . '<span class="jankx-badge jankx-badge-pending">' . esc_html__('Chờ thanh toán', 'base-ecommerce') . '</span>'
            . '</div>';
    }
}
