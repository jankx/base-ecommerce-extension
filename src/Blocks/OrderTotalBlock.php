<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\OrderItemContext;

class OrderTotalBlock extends Block
{
    protected $blockId = 'jankx/order-total';

    public function render($attributes = [], $content = '', $block = null)
    {
        $order = OrderItemContext::get();
        if (!$order) {
            return $this->editorPlaceholder();
        }

        $showLabel = !array_key_exists('showLabel', $attributes) || !empty($attributes['showLabel']);

        $output = '<div class="jankx-order-field jankx-order-field--total">';
        if ($showLabel) {
            $output .= '<span class="jankx-order-field__label">' . esc_html__('Tổng tiền:', 'base-ecommerce') . '</span>';
        }
        $output .= '<strong class="jankx-order-field__value">'
            . esc_html(CurrencyManager::formatPrice($order->getTotal()))
            . '</strong>';
        $output .= '</div>';

        return $output;
    }

    protected function editorPlaceholder(): string
    {
        return '<div class="jankx-order-field jankx-order-field--total">'
            . '<span class="jankx-order-field__label">' . esc_html__('Tổng tiền:', 'base-ecommerce') . '</span>'
            . '<strong class="jankx-order-field__value">1.100.000đ</strong>'
            . '</div>';
    }
}
