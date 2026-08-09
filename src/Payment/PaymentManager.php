<?php
namespace Jankx\Extensions\Ecommerce\Payment;

use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Payment manager.
 *
 * Bridges the ecommerce order flow to the payment-system extension
 * (transaction CPT, gateways, webhooks) when it is available, and always
 * exposes jankx/ecommerce payment actions so business extensions can hook
 * their own gateway logic (MoMo, VNPay, bank transfer, ...).
 *
 * @package Jankx\Extensions\Ecommerce
 */
class PaymentManager
{
    /**
     * @var PaymentManager|null
     */
    protected static $instance;

    public static function get_instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Whether the payment-system extension is loaded.
     */
    public function isPaymentSystemAvailable(): bool
    {
        return class_exists('\\Jankx\\Extensions\\PaymentSystem\\Models\\Transaction');
    }

    /**
     * Start the payment for an order.
     *
     * @param Order  $order
     * @param string $gateway Gateway slug (e.g. "momo", "vnpay", "bank_transfer").
     * @param array  $params  Gateway params (return_url, cancel_url, ...).
     * @return array Result: [success, transaction_id, payment]
     */
    public function process(Order $order, string $gateway = '', array $params = []): array
    {
        $transactionId = 0;

        if ($this->isPaymentSystemAvailable()) {
            $transactionId = $this->createTransaction($order, $gateway, $params);
            if ($transactionId) {
                $order->setPaymentTransactionId($transactionId);
            }
        }

        $order->updateStatus(Order::STATUS_PENDING);

        do_action('jankx/ecommerce/payment/created', $order, $gateway, $params);
        do_action('jankx/ecommerce/payment/process', $order, $gateway, $params);

        $payment = apply_filters('jankx/ecommerce/payment/result', [
            'status' => 'pending',
        ], $order, $gateway);

        return [
            'success'        => true,
            'transaction_id' => $transactionId,
            'order_id'       => $order->getId(),
            'order_number'   => $order->getOrderNumber(),
            'payment'        => $payment,
        ];
    }

    /**
     * Record a transaction via the payment-system extension.
     */
    protected function createTransaction(Order $order, string $gateway, array $params): int
    {
        $class = '\\Jankx\\Extensions\\PaymentSystem\\Models\\Transaction';
        if (!class_exists($class)) {
            return 0;
        }

        $currency = $order->getCurrency() ?: 'VND';

        $transaction = $class::create([
            'title'          => sprintf(__('Payment for %s', 'jankx'), $order->getOrderNumber()),
            'gateway'        => $gateway,
            'amount'         => $order->getTotal(),
            'currency'       => $currency,
            'status'         => 'pending',
            'order_id'       => $order->getId(),
            'customer_email' => $order->getCustomerEmail(),
            'customer_name'  => $order->getCustomerName(),
            'raw_request'    => $params,
        ]);

        return $transaction->getId();
    }

    /**
     * Mark an order as paid (e.g. from a gateway callback / webhook).
     */
    public function markPaid(Order $order, string $transactionId = ''): void
    {
        $order->updateStatus(Order::STATUS_COMPLETED);

        do_action('jankx/ecommerce/payment/paid', $order, $transactionId);
    }

    /**
     * Mark an order as failed (e.g. cancelled gateway payment).
     */
    public function markFailed(Order $order, string $reason = ''): void
    {
        $order->updateStatus(Order::STATUS_FAILED, $reason);

        do_action('jankx/ecommerce/payment/failed', $order, $reason);
    }
}
