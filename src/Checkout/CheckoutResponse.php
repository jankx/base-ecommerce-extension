<?php
namespace Jankx\Extensions\Ecommerce\Checkout;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\EcommerceExtension;
use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Builds the payload the browser gets after a successful checkout.
 *
 * Shared by the REST route and the Fast AJAX controller so both transports
 * return exactly the same keys — the frontend branches on them either way
 * (redirect_url → gateway, qr → modal, otherwise → order list).
 *
 * @package Jankx\Extensions\Ecommerce
 */
class CheckoutResponse
{
    /**
     * @param array $result Successful result of CheckoutManager::checkout().
     * @return array Flat, transport-independent payload.
     */
    public static function build(array $result): array
    {
        $order = $result['order'] ?? null;

        if (!$order instanceof Order) {
            return [];
        }

        $orderUrl   = self::orderUrl($order);
        $orderArray = $order->toArray();

        $orderArray['url']             = $orderUrl;
        $orderArray['order_url']       = $orderUrl;
        $orderArray['formatted_total'] = self::formattedTotal($order);

        $status = (string) ($result['payment_status'] ?? '');

        $payload = [
            'order'                => $orderArray,
            'order_url'            => $orderUrl,
            'orders_url'           => EcommerceExtension::get_orders_page_url(),
            'redirect_url'         => (string) ($result['redirect_url'] ?? ''),
            'payment_status'       => $status,
            'payment_type'         => self::paymentType($status),
            'qr_image'             => (string) ($result['qr_image'] ?? ''),
            'qr_code'              => (string) ($result['qr_code'] ?? ''),
            'qr_link'              => (string) ($result['qr_link'] ?? ''),
            'qr_transfer_content'  => (string) ($result['qr_transfer_content'] ?? ''),
            'transaction_id'       => (int) ($result['transaction_id'] ?? 0),
        ];

        if (!empty($result['error'])) {
            $payload['error']      = (string) $result['error'];
            $payload['error_code'] = (string) ($result['error_code'] ?? '');
        }

        if ($status === 'qr') {
            $payload['bank_info'] = self::bankInfo($result);
        }

        return $payload;
    }

    /**
     * How the browser should settle the payment: "qr" renders the modal,
     * "online" follows redirect_url, "" means the order needs nothing more.
     */
    protected static function paymentType(string $status): string
    {
        if ($status === '') {
            return '';
        }

        return $status === 'qr' ? 'qr' : 'online';
    }

    /**
     * Absolute URL of the order detail page (empty when the account page
     * is not configured yet).
     */
    protected static function orderUrl(Order $order): string
    {
        $ordersUrl = EcommerceExtension::get_orders_page_url();

        if ($ordersUrl === '') {
            return '';
        }

        return trailingslashit($ordersUrl) . $order->getOrderNumber() . '/';
    }

    protected static function formattedTotal(Order $order): string
    {
        $currency = (string) $order->getCurrency();

        if ($currency !== '' && class_exists(CurrencyManager::class)) {
            return CurrencyManager::formatPrice((float) $order->getTotal(), $currency);
        }

        return number_format((float) $order->getTotal(), 0, ',', '.') . ($currency === 'VND' ? '₫' : $currency);
    }

    /**
     * Bank details shown next to the QR so the customer can transfer
     * manually when the QR image cannot be scanned.
     */
    protected static function bankInfo(array $result): array
    {
        $gatewayConfig = apply_filters('jankx/payment/gateway/qrviet/default_config', []);
        $savedConfig   = get_option('jankx_payment_gateway_qrviet', []);
        $config        = array_merge((array) $gatewayConfig, (array) $savedConfig);
        $prefix        = !empty($config['testMode']) ? 'sandbox' : 'production';

        $bankCode    = (string) ($config["{$prefix}_bank_code"] ?? '');
        $bankAccount = (string) ($config["{$prefix}_bank_account"] ?? '');
        $accountName = (string) ($config["{$prefix}_account_name"] ?? '');

        $bankNames = [
            'ACB'  => 'ACB',        'VCB'  => 'Vietcombank', 'TCB' => 'Techcombank',
            'MB'   => 'MBBank',     'VPB'  => 'VPBank',      'VIB' => 'VIB',
            'MSB'  => 'MSB',        'TPB'  => 'TPBank',      'OCB' => 'OCB',
            'BIDV' => 'BIDV',       'VTB'  => 'Vietinbank',  'AGR' => 'Agribank',
            'SHB'  => 'SHB',        'HDB'  => 'HDBank',      'SCB' => 'SCB',
        ];
        $bankName = $bankNames[strtoupper($bankCode)] ?? strtoupper($bankCode);

        // Ưu tiên đọc nội dung chuyển khoản đã được lưu vào transaction meta
        // (ổn định theo session — không tính lại mỗi request).
        $transferContent = '';
        $transactionId    = (int) ($result['transaction_id'] ?? 0);
        $transactionClass = '\\Jankx\\Extensions\\PaymentSystem\\Models\\Transaction';

        if ($transactionId > 0 && class_exists($transactionClass)) {
            $transaction = new $transactionClass($transactionId);
            if ($transaction->getId()) {
                $transferContent = (string) $transaction->getMeta('_transfer_content');
            }
        }
        // Fallback sang giá trị từ gateway nếu transaction chưa được persist
        if ($transferContent === '') {
            $transferContent = (string) ($result['qr_transfer_content'] ?? '');
        }

        return [
            'bank_code'        => $bankCode,
            'bank_name'        => $bankName,
            'bank_account'     => $bankAccount,
            'account_name'     => $accountName,
            'transfer_content' => $transferContent,
        ];
    }
}
