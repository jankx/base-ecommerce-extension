<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderItemContext;

class OrderCancelBlock extends Block
{
    protected $blockId = 'jankx/order-cancel';

    public function render($attributes = [], $content = '', $block = null)
    {
        $order = OrderItemContext::get();
        if (!$order) {
            return $this->editorPlaceholder();
        }

        // Only unpaid, cancellable orders owned by the current user see the button.
        if (!in_array($order->getStatus(), [Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
            return '';
        }

        $user = wp_get_current_user();
        $customerId = (int) $order->getCustomerId();
        $isOwner = $customerId === (int) $user->ID
            || ($customerId === 0 && strcasecmp($order->getCustomerEmail(), (string) $user->user_email) === 0);

        if (!$isOwner) {
            return '';
        }

        $restUrl = rest_url('jankx/ecommerce/v1/orders/' . $order->getOrderNumber() . '/cancel');
        $nonce = wp_create_nonce('wp_rest');

        return '<div class="jankx-order-field jankx-order-field--cancel">'
            . '<button type="button" class="jankx-btn jankx-btn-outline jankx-order-cancel" '
            . 'data-rest-url="' . esc_attr($restUrl) . '" '
            . 'data-nonce="' . esc_attr($nonce) . '" '
            . 'data-order="' . esc_attr($order->getOrderNumber()) . '" '
            . 'onclick="jankxCancelOrder(this)">'
            . esc_html__('Hủy đơn hàng', 'base-ecommerce')
            . '</button>'
            . '</div>';
    }

    protected function editorPlaceholder(): string
    {
        return '<div class="jankx-order-field jankx-order-field--cancel">'
            . '<button type="button" class="jankx-btn jankx-btn-outline jankx-order-cancel">'
            . esc_html__('Hủy đơn hàng', 'base-ecommerce')
            . '</button>'
            . '</div>';
    }
}
