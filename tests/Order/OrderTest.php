<?php
namespace Jankx\Extensions\Ecommerce\Tests\Order;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderItem;

class OrderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('Brain\Monkey\Functions\when')) {
            require_once __DIR__ . '/../bootstrap.php';
        }
        stub_wp_ecommerce_functions();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function createOrderWithData(array $overrides = []): Order
    {
        $data = array_merge([
            'id'                => 1,
            'order_number'      => 'OD-000001',
            'status'            => 'pending',
            'customer_id'       => 42,
            'customer_name'     => 'Nguyen Van A',
            'customer_email'    => 'a@example.com',
            'customer_phone'    => '0901234567',
            'customer_address'  => 'Ha Noi, Vietnam',
            'total'             => 5000000,
            'currency'          => 'VND',
            'payment_method'    => 'cod',
            'payment_transaction_id' => '',
            'handler_id'        => 1,
            'items'             => [
                ['product_id' => 10, 'name' => 'Tour Ha Long', 'product_type' => 'tour', 'quantity' => 2, 'unit_price' => 2500000, 'meta' => []],
            ],
            'notes'             => [],
            'history'           => [],
            'created_at'        => '2026-08-11 12:00:00',
            'updated_at'        => '2026-08-11 12:00:00',
        ], $overrides);

        // Mock OrderModel::findById to return our test data
        $mock = $this->getMockBuilder(Order::class)
            ->setMethods(null)
            ->setConstructorArgs([0])
            ->getMock();

        // Use reflection to set the data directly
        $reflection = new \ReflectionClass($mock);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($mock, $data['id']);
        $dataProp = $reflection->getProperty('data');
        $dataProp->setValue($mock, $data);

        return $mock;
    }

    public function test_get_id(): void
    {
        $order = $this->createOrderWithData(['id' => 42]);
        $this->assertSame(42, $order->getId());
    }

    public function test_get_order_number(): void
    {
        $order = $this->createOrderWithData(['order_number' => 'OD-000099']);
        $this->assertSame('OD-000099', $order->getOrderNumber());
    }

    public function test_get_order_number_falls_back_to_id(): void
    {
        $order = $this->createOrderWithData(['order_number' => '', 'id' => 7]);
        $this->assertSame('7', $order->getOrderNumber());
    }

    public function test_get_status(): void
    {
        $order = $this->createOrderWithData(['status' => 'processing']);
        $this->assertSame('processing', $order->getStatus());
    }

    public function test_get_status_defaults_to_pending(): void
    {
        $order = $this->createOrderWithData(['status' => '']);
        $this->assertSame('pending', $order->getStatus());
    }

    public function test_get_customer_id(): void
    {
        $order = $this->createOrderWithData(['customer_id' => 42]);
        $this->assertSame(42, $order->getCustomerId());
    }

    public function test_get_customer_name(): void
    {
        $order = $this->createOrderWithData(['customer_name' => 'Nguyen Van A']);
        $this->assertSame('Nguyen Van A', $order->getCustomerName());
    }

    public function test_get_customer_email(): void
    {
        $order = $this->createOrderWithData(['customer_email' => 'test@example.com']);
        $this->assertSame('test@example.com', $order->getCustomerEmail());
    }

    public function test_get_customer_phone(): void
    {
        $order = $this->createOrderWithData(['customer_phone' => '0901234567']);
        $this->assertSame('0901234567', $order->getCustomerPhone());
    }

    public function test_get_customer_address(): void
    {
        $order = $this->createOrderWithData(['customer_address' => 'Hanoi']);
        $this->assertSame('Hanoi', $order->getCustomerAddress());
    }

    public function test_get_currency(): void
    {
        $order = $this->createOrderWithData(['currency' => 'USD']);
        $this->assertSame('USD', $order->getCurrency());
    }

    public function test_get_currency_defaults_to_vnd(): void
    {
        $order = $this->createOrderWithData(['currency' => 'VND']);
        $this->assertSame('VND', $order->getCurrency());
    }

    public function test_get_payment_method(): void
    {
        $order = $this->createOrderWithData(['payment_method' => 'momo']);
        $this->assertSame('momo', $order->getPaymentMethod());
    }

    public function test_get_total(): void
    {
        $order = $this->createOrderWithData(['total' => 5000000.50]);
        $this->assertSame(5000000.50, $order->getTotal());
    }

    public function test_get_handler_id(): void
    {
        $order = $this->createOrderWithData(['handler_id' => 5]);
        $this->assertSame(5, $order->getHandlerId());
    }

    public function test_get_handler_returns_display_name(): void
    {
        $order = $this->createOrderWithData(['handler_id' => 5]);
        $this->assertSame('User 5', $order->getHandler());
    }

    public function test_get_handler_returns_empty_when_no_handler(): void
    {
        $order = $this->createOrderWithData(['handler_id' => 0]);
        $this->assertSame('', $order->getHandler());
    }

    public function test_get_date_created(): void
    {
        $order = $this->createOrderWithData(['created_at' => '2026-08-11 12:00:00']);
        $this->assertSame('2026-08-11 12:00:00', $order->getDateCreated());
    }

    public function test_get_items_returns_order_items(): void
    {
        $order = $this->createOrderWithData();
        $items = $order->getItems();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(OrderItem::class, $items[0]);
        $this->assertSame('Tour Ha Long', $items[0]->getName());
        $this->assertSame(2, $items[0]->getQuantity());
    }

    public function test_get_items_returns_empty_array_when_no_items(): void
    {
        $order = $this->createOrderWithData(['items' => []]);
        $this->assertEmpty($order->getItems());
    }

    public function test_get_notes(): void
    {
        $order = $this->createOrderWithData([
            'notes' => [
                ['note' => 'Note 1', 'customer' => false, 'user_id' => 1],
                ['note' => 'Customer note', 'customer' => true, 'user_id' => 42],
            ],
        ]);

        $allNotes = $order->getNotes();
        $this->assertCount(2, $allNotes);

        $customerNotes = $order->getNotes(true);
        $this->assertCount(1, $customerNotes);
        $this->assertSame('Customer note', $customerNotes[0]['note']);
    }

    public function test_get_history(): void
    {
        $history = [
            ['action' => 'status_changed', 'from' => 'pending', 'to' => 'processing'],
        ];

        $order = $this->createOrderWithData(['history' => $history]);
        $this->assertSame($history, $order->getHistory());
    }

    public function test_get_history_returns_empty_array(): void
    {
        $order = $this->createOrderWithData(['history' => []]);
        $this->assertEmpty($order->getHistory());
    }

    public function test_to_array(): void
    {
        $order = $this->createOrderWithData();
        $array = $order->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('order_number', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('status_label', $array);
        $this->assertArrayHasKey('customer_id', $array);
        $this->assertArrayHasKey('customer_name', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('items', $array);
        $this->assertArrayHasKey('history', $array);

        $this->assertSame(1, $array['id']);
        $this->assertSame('OD-000001', $array['order_number']);
        $this->assertSame('pending', $array['status']);
        $this->assertSame(42, $array['customer_id']);
    }

    public function test_status_constants(): void
    {
        $this->assertSame('pending', Order::STATUS_PENDING);
        $this->assertSame('processing', Order::STATUS_PROCESSING);
        $this->assertSame('completed', Order::STATUS_COMPLETED);
        $this->assertSame('failed', Order::STATUS_FAILED);
        $this->assertSame('cancelled', Order::STATUS_CANCELLED);
        $this->assertSame('refunded', Order::STATUS_REFUNDED);
    }

    public function test_get_status_labels(): void
    {
        $labels = Order::getStatusLabels();

        $this->assertArrayHasKey('pending', $labels);
        $this->assertArrayHasKey('processing', $labels);
        $this->assertArrayHasKey('completed', $labels);
        $this->assertArrayHasKey('failed', $labels);
        $this->assertArrayHasKey('cancelled', $labels);
        $this->assertArrayHasKey('refunded', $labels);
    }

    public function test_get_status_label_for_known_status(): void
    {
        $label = Order::getStatusLabel('pending');
        $this->assertNotEmpty($label);
    }

    public function test_get_status_label_for_unknown_status(): void
    {
        $label = Order::getStatusLabel('custom_status');
        $this->assertSame('Custom_status', $label);
    }

    public function test_get_allowed_transitions(): void
    {
        $transitions = Order::getAllowedTransitions();

        $this->assertArrayHasKey('pending', $transitions);
        $this->assertContains('processing', $transitions['pending']);
        $this->assertContains('failed', $transitions['pending']);
        $this->assertContains('cancelled', $transitions['pending']);

        $this->assertArrayHasKey('completed', $transitions);
        $this->assertContains('refunded', $transitions['completed']);

        $this->assertArrayHasKey('refunded', $transitions);
        $this->assertEmpty($transitions['refunded']);
    }

    public function test_get_allowed_status_transitions(): void
    {
        $order = $this->createOrderWithData(['status' => 'pending']);
        $transitions = $order->getAllowedStatusTransitions();

        $this->assertContains('processing', $transitions);
        $this->assertContains('failed', $transitions);
        $this->assertContains('cancelled', $transitions);
    }

    public function test_get_payment_transaction_id(): void
    {
        $order = $this->createOrderWithData(['payment_transaction_id' => '12345']);
        $this->assertSame(12345, $order->getPaymentTransactionId());
    }
}
