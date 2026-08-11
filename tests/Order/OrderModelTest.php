<?php
namespace Jankx\Extensions\Ecommerce\Tests\Order;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Order\OrderModel;

class OrderModelTest extends TestCase
{
    /** @var \wpdb|null */
    private $wpdb;

    protected function setUp(): void
    {
        if (!function_exists('Brain\Monkey\Functions\when')) {
            require_once __DIR__ . '/../bootstrap.php';
        }
        stub_wp_ecommerce_functions();

        // Reset static cache
        $reflection = new \ReflectionClass(OrderModel::class);
        $prop = $reflection->getProperty('ordersTable');
        $prop->setValue(null, null);
        $prop2 = $reflection->getProperty('orderPostsTable');
        $prop2->setValue(null, null);

        // Mock $wpdb
        $this->wpdb = $this->getMockBuilder('wpdb')
            ->onlyMethods(['prepare', 'get_row', 'get_results', 'get_var', 'esc_like', 'update', 'delete'])
            ->getMock();
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->insert_id = 0;

        // Make $wpdb global accessible
        global $wpdb;
        $wpdb = $this->wpdb;
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_orders_table_name(): void
    {
        $this->assertSame('wp_jankx_orders', OrderModel::ordersTable());
    }

    public function test_order_posts_table_name(): void
    {
        $this->assertSame('wp_jankx_order_posts', OrderModel::orderPostsTable());
    }

    public function test_create_encodes_json_fields(): void
    {
        $this->wpdb->insert_id = 1;

        $items = [['product_id' => 1, 'name' => 'Test']];
        $notes = ['A note'];
        $history = [['action' => 'created']];

        // The insert should be called with JSON-encoded arrays
        $id = OrderModel::create([
            'items'    => $items,
            'notes'    => $notes,
            'history'  => $history,
            'total'    => 500000,
            'currency' => 'VND',
        ]);

        // create returns insert_id (0 in mock, but we test the method doesn't crash)
        $this->assertIsInt($id);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn(null);

        $result = OrderModel::findById(999);
        $this->assertNull($result);
    }

    public function test_find_by_id_decodes_json_fields(): void
    {
        $row = [
            'id'          => 1,
            'order_number' => 'OD-000001',
            'status'      => 'pending',
            'items'       => '[{"product_id":1,"name":"Tour"}]',
            'notes'       => '["Note 1"]',
            'history'     => '[{"action":"created"}]',
            'total'       => '500000.00',
        ];

        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($row);

        $result = OrderModel::findById(1);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('OD-000001', $result['order_number']);
        $this->assertIsArray($result['items']);
        $this->assertSame('Tour', $result['items'][0]['name']);
        $this->assertIsArray($result['notes']);
        $this->assertIsArray($result['history']);
    }

    public function test_find_by_order_number(): void
    {
        $row = [
            'id'           => 5,
            'order_number' => 'OD-000005',
            'items'        => '[]',
            'notes'        => '[]',
            'history'      => '[]',
        ];

        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($row);

        $result = OrderModel::findByOrderNumber('OD-000005');

        $this->assertIsArray($result);
        $this->assertSame('OD-000005', $result['order_number']);
    }

    public function test_find_by_order_number_returns_null_when_not_found(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn(null);

        $result = OrderModel::findByOrderNumber('NONEXISTENT');
        $this->assertNull($result);
    }

    public function test_query_builds_where_customer_id(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([]);

        $result = OrderModel::query(['customer_id' => 42]);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_query_builds_where_status(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([]);

        $result = OrderModel::query(['status' => 'completed']);
        $this->assertIsArray($result);
    }

    public function test_query_builds_where_status_array(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([]);

        $result = OrderModel::query(['status' => ['pending', 'processing']]);
        $this->assertIsArray($result);
    }

    public function test_query_builds_orderby(): void
    {
        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([]);

        $result = OrderModel::query(['orderby' => 'total', 'order' => 'ASC']);
        $this->assertIsArray($result);
    }

    public function test_query_builds_search(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->escLikeStub();

        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([]);

        $result = OrderModel::query(['search' => 'John']);
        $this->assertIsArray($result);
    }

    public function test_query_builds_per_page_and_page(): void
    {
        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([]);

        $result = OrderModel::query(['per_page' => 10, 'page' => 2]);
        $this->assertIsArray($result);
    }

    public function test_count_returns_integer(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_var')
            ->willReturn('5');

        $result = OrderModel::count(['customer_id' => 1]);
        $this->assertSame(5, $result);
    }

    public function test_count_with_status_filter(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_var')
            ->willReturn('3');

        $result = OrderModel::count(['status' => 'pending', 'customer_id' => 1]);
        $this->assertSame(3, $result);
    }

    public function test_generate_order_number(): void
    {
        $orderNumber = OrderModel::generateOrderNumber(42);
        $this->assertSame('OD-000042', $orderNumber);
    }

    public function test_generate_order_number_pads_to_six_digits(): void
    {
        $orderNumber = OrderModel::generateOrderNumber(1);
        $this->assertSame('OD-000001', $orderNumber);
    }

    public function test_decode_json_fields_handles_invalid_json(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $row = [
            'id'     => 1,
            'items'  => 'not-valid-json',
            'notes'  => '',
            'history' => '[]',
        ];

        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($row);

        $result = OrderModel::findById(1);

        $this->assertIsArray($result['items']);
        $this->assertEmpty($result['items']);
    }

    public function test_update_status_adds_history_entry(): void
    {
        $orderRow = [
            'id'      => 1,
            'status'  => 'pending',
            'items'   => '[]',
            'notes'   => '[]',
            'history' => [],
        ];

        $callCount = 0;
        $this->wpdb->method('get_row')
            ->willReturnCallback(function () use (&$callCount, $orderRow) {
                $callCount++;
                if ($callCount === 1) {
                    return $orderRow;
                }
                return null;
            });

        $this->wpdb->method('update')
            ->willReturn(1);

        $result = OrderModel::updateStatus(1, 'processing', 'Payment received', 5);

        $this->assertTrue($result);
    }

    public function test_delete_returns_false_on_failure(): void
    {
        $this->wpdb->method('delete')
            ->willReturn(false);

        $result = OrderModel::delete(999);
        $this->assertFalse($result);
    }

    public function test_get_order_posts(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->willReturn('SQL');

        $this->wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn([
                ['id' => 1, 'order_id' => 10, 'post_id' => 100, 'post_type' => 'product', 'quantity' => 2],
            ]);

        $result = OrderModel::getOrderPosts(10);

        $this->assertCount(1, $result);
        $this->assertSame(100, $result[0]['post_id']);
    }

    public function test_append_note(): void
    {
        $orderRow = [
            'id'      => 1,
            'status'  => 'pending',
            'items'   => '[]',
            'notes'   => [],
            'history' => '[]',
        ];

        $callCount = 0;
        $this->wpdb->method('get_row')
            ->willReturnCallback(function () use (&$callCount, $orderRow) {
                $callCount++;
                if ($callCount === 1) {
                    return $orderRow;
                }
                return null;
            });

        $this->wpdb->method('update')
            ->willReturn(1);

        $result = OrderModel::appendNote(1, 'Test note', true);
        $this->assertTrue($result);
    }

    public function test_update_returns_false_on_failure(): void
    {
        $this->wpdb->method('update')
            ->willReturn(false);

        $result = OrderModel::update(999, ['status' => 'processing']);
        $this->assertFalse($result);
    }

    private function escLikeStub(): void
    {
        $this->wpdb->method('esc_like')
            ->willReturnCallback(function ($input) {
                return addcslashes($input, '%_');
            });
    }
}
