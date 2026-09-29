<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Order\OrderItemContext;

/**
 * Loop item template: rendered once per order inside the orders list.
 * Its inner blocks (order-number, order-status, order-date, order-total,
 * order-cancel) define the layout of a single order row.
 */
class AccountTabOrdersTemplateBlock extends Block
{
    protected $blockId = 'jankx/account-tab-orders-template';

    /** Default child blocks used when the template has no saved inner blocks. */
    protected $defaultChildren = [
        'jankx/order-number',
        'jankx/order-status',
        'jankx/order-date',
        'jankx/order-total',
        'jankx/order-cancel',
    ];

    public function render($attributes = [], $content = '', $block = null)
    {
        $order = OrderItemContext::get();
        if (!$order) {
            // Standalone render (editor preview / block inserter preview).
            return '<div class="jankx-order-card jankx-order-card--template jankx-order-card--placeholder">'
                . esc_html__('Mẫu hiển thị cho một đơn hàng trong danh sách.', 'base-ecommerce')
                . '</div>';
        }

        $output = '<div class="jankx-order-card jankx-order-card--template" data-order="' . esc_attr($order->getOrderNumber()) . '">';

        if ($block && !empty($block->inner_blocks)) {
            foreach ($block->inner_blocks as $innerBlock) {
                $output .= $innerBlock->render();
            }
        } else {
            foreach ($this->defaultChildren as $childId) {
                $blockClass = $this->resolveChildClass($childId);
                if ($blockClass) {
                    $output .= (new $blockClass())->render([]);
                }
            }
        }

        $output .= '</div>';

        return $output;
    }

    protected function resolveChildClass(string $blockId): ?string
    {
        $map = [
            'jankx/order-number' => OrderNumberBlock::class,
            'jankx/order-status' => OrderStatusBlock::class,
            'jankx/order-date'   => OrderDateBlock::class,
            'jankx/order-total'  => OrderTotalBlock::class,
            'jankx/order-cancel' => OrderCancelBlock::class,
        ];

        return $map[$blockId] ?? null;
    }
}
