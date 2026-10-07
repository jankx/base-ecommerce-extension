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
        return class_exists('\\Jankx\\Extensions\\PaymentSystem\\Models\\Transaction')
            && class_exists('\\Jankx\\Extensions\\PaymentSystem\\Gateways\\GatewayManager');
    }

    /**
     * Start the payment for an order.
     *
     * @param Order  $order
     * @param string $gateway Gateway slug (e.g. "onepay", "onepay_domestic", "momo").
     * @param array  $params  Gateway params (return_url, cancel_url, ...).
     * @return array Result: [success, transaction_id, order_id, order_number, redirect_url, payment_status, payment_type, qr_image, qr_code, qr_link]
     */
    public function process(Order $order, string $gateway = '', array $params = []): array
    {
        $transactionId = 0;
        $paymentResult = [];

        if ($this->isPaymentSystemAvailable() && $gateway !== '') {
            $transactionId = $this->createTransaction($order, $gateway, $params);
            if ($transactionId) {
                $order->setPaymentTransactionId($transactionId);
            }

            // Call the gateway's purchase() to get the redirect URL / QR payload.
            $paymentResult = $this->callGatewayPurchase($order, $gateway, $transactionId, $params);
        }

        // Callback thanh toán là sự kiện hệ thống → bỏ qua kiểm tra luồng.
        $order->updateStatus(Order::STATUS_PENDING, '', 0, false);

        do_action('jankx/ecommerce/payment/created', $order, $gateway, $params);

        return [
            'success'              => true,
            'transaction_id'       => $transactionId,
            'order_id'             => $order->getId(),
            'order_number'         => $order->getOrderNumber(),
            'redirect_url'         => !empty($paymentResult['redirectUrl']) ? $paymentResult['redirectUrl'] : '',
            'payment_status'       => isset($paymentResult['status']) && is_string($paymentResult['status']) ? $paymentResult['status'] : '',
            'payment_type'         => ($paymentResult['status'] ?? '') === 'qr' ? 'qr' : 'online',
            'qr_image'             => isset($paymentResult['qrImage']) ? $paymentResult['qrImage'] : '',
            'qr_code'              => isset($paymentResult['qrCode']) ? $paymentResult['qrCode'] : '',
            'qr_link'              => isset($paymentResult['qrLink']) ? $paymentResult['qrLink'] : '',
            'qr_transfer_content'  => isset($paymentResult['transferContent']) ? $paymentResult['transferContent'] : '',
            'error'                => isset($paymentResult['message']) && is_string($paymentResult['message']) ? $paymentResult['message'] : '',
            'error_code'           => isset($paymentResult['code']) && is_string($paymentResult['code']) ? $paymentResult['code'] : '',
            'raw'                  => isset($paymentResult['raw']) && is_array($paymentResult['raw']) ? $paymentResult['raw'] : [],
        ];
    }

    /**
     * Call the gateway's purchase() method and return the full purchase result.
     *
     * Redirect-based gateways set `redirectUrl`; QR-based gateways (e.g. QrViet)
     * set `status` = "qr" plus `qrImage`/`qrCode`.
     */
    protected function callGatewayPurchase(Order $order, string $gatewaySlug, int $transactionId, array $params): array
    {
        $gatewayManager = \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance();

        $gateway = $gatewayManager->get($gatewaySlug);

        if (!$gateway) {
            return [
                'status'  => 'failed',
                'message' => __('Payment gateway is not registered.', 'base-ecommerce'),
                'code'    => 'GATEWAY_NOT_FOUND',
            ];
        }

        if (!$gateway->isAvailable()) {
            return [
                'status'  => 'failed',
                'message' => __('Payment gateway is not configured.', 'base-ecommerce'),
                'code'    => 'GATEWAY_NOT_AVAILABLE',
            ];
        }

        $accountUrl = function_exists('jankx_get_account_endpoint_url')
            ? jankx_get_account_endpoint_url('orders')
            : home_url('/my-account/orders/');

        $result = $gateway->purchase([
            'transactionId'  => $transactionId,
            'amount'         => $order->getTotal(),
            'currency'       => $order->getCurrency(),
            'returnUrl'      => rest_url('jankx/v1/payment/' . $transactionId . '/process'),
            'cancelUrl'      => add_query_arg('order', $order->getOrderNumber(), $accountUrl),
            'description'    => sprintf('%s-%s', \Jankx\Extensions\Ecommerce\jankx_payment_content_code(), $order->getOrderNumber()),
            'customer_email' => $order->getCustomerEmail(),
            'customer_phone' => $order->getCustomerPhone(),
            'customer_name'  => $order->getCustomerName(),
            // Card / extra checkout fields posted with the order. Namespaced so
            // a gateway can never shadow the core parameters above.
            'payment_params' => $params,
        ]);

        if (!is_array($result)) {
            return [
                'status'  => 'failed',
                'message' => __('Payment gateway returned an invalid response.', 'base-ecommerce'),
                'code'    => 'INVALID_GATEWAY_RESPONSE',
            ];
        }

        return $result;
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
            'title'          => sprintf(__('Payment for %s', 'base-ecommerce'), $order->getOrderNumber()),
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
        // Gateway xác nhận đã thu tiền → bỏ qua kiểm tra luồng (hệ thống).
        $order->updateStatus(Order::STATUS_COMPLETED, '', 0, false);

        do_action('jankx/ecommerce/payment/paid', $order, $transactionId);
    }

    /**
     * Mark an order as failed (e.g. cancelled gateway payment).
     */
    public function markFailed(Order $order, string $reason = ''): void
    {
        // Gateway báo thất bại → bỏ qua kiểm tra luồng (hệ thống).
        $order->updateStatus(Order::STATUS_FAILED, $reason, 0, false);

        do_action('jankx/ecommerce/payment/failed', $order, $reason);
    }
}
