<?php
namespace Jankx\Extensions\Ecommerce\Tests\Checkout;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutResponse;
use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * CheckoutResponse is the single payload builder shared by the REST route and
 * the Fast AJAX checkout controller, so every key the frontend branches on
 * (redirect_url, payment_type, qr_*, order.url) is asserted here.
 */
class CheckoutResponseTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('Brain\Monkey\Functions\when')) {
            require_once __DIR__ . '/../bootstrap.php';
        }
        stub_wp_ecommerce_functions();

        // Declared in the extension root, outside the PSR-4 src/ prefix.
        if (!class_exists(\Jankx\Extensions\Ecommerce\EcommerceExtension::class, false)) {
            require_once dirname(__DIR__, 2) . '/EcommerceExtension.php';
        }

        Functions\when('trailingslashit')->alias(static function ($url) {
            return rtrim((string) $url, '/') . '/';
        });
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function createOrder(array $overrides = []): Order
    {
        $data = array_merge([
            'id'               => 42,
            'order_number'     => 'OD-000042',
            'status'           => 'pending',
            'customer_id'      => 42,
            'customer_name'    => 'Nguyen Van A',
            'customer_email'   => 'a@example.com',
            'customer_phone'   => '0901234567',
            'customer_address' => 'Ha Noi, Vietnam',
            'total'            => 5000000,
            'currency'         => 'VND',
            'payment_method'   => 'qrviet',
            'handler_id'       => 1,
            'items'            => [
                ['product_id' => 10, 'name' => 'Tour Ha Long', 'product_type' => 'tour', 'quantity' => 2, 'unit_price' => 2500000, 'meta' => []],
            ],
            'notes'            => [],
            'history'          => [],
            'created_at'       => '2026-08-11 12:00:00',
            'updated_at'       => '2026-08-11 12:00:00',
        ], $overrides);

        $order = $this->getMockBuilder(Order::class)
            ->setMethods(null)
            ->setConstructorArgs([0])
            ->getMock();

        $reflection = new \ReflectionClass($order);
        $reflection->getProperty('id')->setValue($order, $data['id']);
        $reflection->getProperty('data')->setValue($order, $data);

        return $order;
    }

    private function qrResult(array $overrides = []): array
    {
        return array_merge([
            'success'              => true,
            'errors'               => [],
            'order'                => $this->createOrder(),
            'redirect_url'         => 'https://example.com/tai-khoan-cua-toi/orders/OD-000042/',
            'payment_status'       => 'qr',
            'payment_type'         => 'qr',
            'qr_image'             => 'https://img.example.com/qr.png',
            'qr_code'              => '00020101021230440010VNCom...',
            'qr_link'              => 'https://img.example.com/qr.png',
            'qr_transfer_content'  => 'OD000042 5000000 VND',
            'transaction_id'       => 0,
        ], $overrides);
    }

    public function test_build_returns_empty_without_order(): void
    {
        $this->assertSame([], CheckoutResponse::build(['success' => true]));
        $this->assertSame([], CheckoutResponse::build([]));
    }

    public function test_build_exposes_every_key_the_frontend_branches_on(): void
    {
        $payload = CheckoutResponse::build($this->qrResult());

        foreach ([
            'order',
            'order_url',
            'orders_url',
            'redirect_url',
            'payment_status',
            'payment_type',
            'qr_image',
            'qr_code',
            'qr_link',
            'qr_transfer_content',
            'transaction_id',
            'bank_info',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "Missing payload key: {$key}");
        }

        // The envelope flag is added by each transport, never by the builder.
        $this->assertArrayNotHasKey('success', $payload);
    }

    public function test_build_marks_qr_payment_type(): void
    {
        $payload = CheckoutResponse::build($this->qrResult());

        $this->assertSame('qr', $payload['payment_type']);
        $this->assertSame('qr', $payload['payment_status']);
        $this->assertSame('https://img.example.com/qr.png', $payload['qr_image']);
    }

    public function test_build_marks_online_payment_type_for_redirect_gateway(): void
    {
        $payload = CheckoutResponse::build($this->qrResult([
            'payment_status' => 'completed',
            'payment_type'   => 'online',
            'qr_image'       => '',
            'qr_code'        => '',
            'qr_link'        => '',
            'redirect_url'   => 'https://payment.example.com/pay?txn=1',
        ]));

        $this->assertSame('online', $payload['payment_type']);
        $this->assertSame('https://payment.example.com/pay?txn=1', $payload['redirect_url']);
        $this->assertArrayNotHasKey('bank_info', $payload);
    }

    public function test_build_keeps_payment_claim_empty_without_gateway(): void
    {
        $payload = CheckoutResponse::build([
            'success'      => true,
            'errors'       => [],
            'order'        => $this->createOrder(),
            'redirect_url' => '',
        ]);

        $this->assertSame('', $payload['payment_status']);
        $this->assertSame('', $payload['payment_type']);
        $this->assertSame('', $payload['qr_image']);
        $this->assertSame(0, $payload['transaction_id']);
        $this->assertArrayNotHasKey('bank_info', $payload);
        $this->assertArrayNotHasKey('error', $payload);
    }

    public function test_build_surfaces_gateway_error_with_order_still_attached(): void
    {
        $payload = CheckoutResponse::build($this->qrResult([
            'payment_status' => 'failed',
            'payment_type'   => 'online',
            'qr_image'       => '',
            'qr_code'        => '',
            'qr_link'        => '',
            'redirect_url'   => '',
            'error'          => 'Payment gateway is not configured.',
            'error_code'     => 'GATEWAY_NOT_AVAILABLE',
            'transaction_id' => 91,
        ]));

        $this->assertSame('Payment gateway is not configured.', $payload['error']);
        $this->assertSame('GATEWAY_NOT_AVAILABLE', $payload['error_code']);
        $this->assertSame('online', $payload['payment_type']);
        $this->assertSame(91, $payload['transaction_id']);
        $this->assertArrayHasKey('order', $payload);
    }

    public function test_build_attaches_order_details_and_formatted_total(): void
    {
        $payload = CheckoutResponse::build($this->qrResult());
        $order   = $payload['order'];

        $this->assertSame(42, $order['id']);
        $this->assertSame('OD-000042', $order['order_number']);
        $this->assertSame('Nguyen Van A', $order['customer_name']);
        $this->assertSame(5000000.0, $order['total']);
        $this->assertNotSame('', $order['formatted_total']);
        $this->assertNotEmpty($order['items']);
    }

    public function test_build_builds_order_urls_from_the_account_page(): void
    {
        $GLOBALS['__wp_options']['jankx_my_account_page_id'] = 7;

        $payload = CheckoutResponse::build($this->qrResult());

        $this->assertSame('http://example.com/?p=7/orders/', $payload['orders_url']);
        $this->assertSame('http://example.com/?p=7/orders/OD-000042/', $payload['order_url']);
        $this->assertSame('http://example.com/?p=7/orders/OD-000042/', $payload['order']['url']);
    }

    public function test_build_leaves_urls_empty_without_account_page(): void
    {
        $payload = CheckoutResponse::build($this->qrResult());

        $this->assertSame('', $payload['orders_url']);
        $this->assertSame('', $payload['order_url']);
        $this->assertSame('', $payload['order']['url']);
    }

    public function test_build_reads_bank_details_from_the_saved_gateway_config(): void
    {
        $GLOBALS['__wp_options']['jankx_payment_gateway_qrviet'] = [
            'production_bank_code'    => 'VCB',
            'production_bank_account' => '0123456789',
            'production_account_name' => 'CONG TY NOBITOUR',
        ];

        $payload = CheckoutResponse::build($this->qrResult());
        $bank    = $payload['bank_info'];

        $this->assertSame('VCB', $bank['bank_code']);
        $this->assertSame('Vietcombank', $bank['bank_name']);
        $this->assertSame('0123456789', $bank['bank_account']);
        $this->assertSame('CONG TY NOBITOUR', $bank['account_name']);
        // No transaction id → falls back to the content the gateway returned.
        $this->assertSame('OD000042 5000000 VND', $bank['transfer_content']);
    }
}
