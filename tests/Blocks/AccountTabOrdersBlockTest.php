<?php
namespace Jankx\Extensions\Ecommerce\Tests\Blocks;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Blocks\AccountTabOrdersBlock;

class AccountTabOrdersBlockTest extends TestCase
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

    public function test_render_returns_empty_when_not_logged_in(): void
    {
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(false);

        $block = new AccountTabOrdersBlock();
        $result = $block->render([]);

        $this->assertSame('', $result);
    }

    public function test_render_returns_empty_when_not_orders_tab(): void
    {
        \Brain\Monkey\Functions\when('get_query_var')->justReturn('overview');
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        $block = new AccountTabOrdersBlock();
        $result = $block->render([]);

        $this->assertSame('', $result);
    }

    public function test_render_shows_empty_state_when_no_orders(): void
    {
        \Brain\Monkey\Functions\when('get_query_var')->alias(function ($var) {
            if ($var === 'jankx_account_page') {
                return 'orders';
            }
            return '';
        });
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);
        \Brain\Monkey\Functions\when('get_current_user_id')->justReturn(1);

        // Mock empty query result
        $block = $this->getMockBuilder(AccountTabOrdersBlock::class)
            ->setMethods(['getUserOrders'])
            ->getMock();
        $block->method('getUserOrders')->willReturn([]);

        $result = $block->render([]);

        $this->assertStringContainsString('jankx-empty-state', $result);
        $this->assertStringContainsString('You have no orders yet', $result);
    }

    public function test_render_shows_order_list_when_orders_exist(): void
    {
        \Brain\Monkey\Functions\when('get_query_var')->alias(function ($var) {
            if ($var === 'jankx_account_page') {
                return 'orders';
            }
            return '';
        });
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);
        \Brain\Monkey\Functions\when('get_current_user_id')->justReturn(1);
        \Brain\Monkey\Functions\when('esc_html')->returnArg();
        \Brain\Monkey\Functions\when('esc_attr')->returnArg();
        \Brain\Monkey\Functions\when('date_i18n')->justReturn('2026-08-11');
        \Brain\Monkey\Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'date_format') {
                return 'Y-m-d';
            }
            return $default;
        });

        // Create a mock order
        $mockOrder = $this->createMock(\Jankx\Extensions\Ecommerce\Order\Order::class);
        $mockOrder->method('getStatus')->willReturn('pending');
        $mockOrder->method('getOrderNumber')->willReturn('OD-000001');
        $mockOrder->method('getDateCreated')->willReturn('2026-08-11 12:00:00');
        $mockOrder->method('getTotal')->willReturn(5000000.0);

        $mockItem = $this->createMock(\Jankx\Extensions\Ecommerce\Order\OrderItem::class);
        $mockItem->method('getName')->willReturn('Tour Ha Long');
        $mockItem->method('getQuantity')->willReturn(2);
        $mockOrder->method('getItems')->willReturn([$mockItem]);

        $block = $this->getMockBuilder(AccountTabOrdersBlock::class)
            ->setMethods(['getUserOrders', 'formatPrice'])
            ->getMock();
        $block->method('getUserOrders')->willReturn([$mockOrder]);
        $block->method('formatPrice')->willReturn('₫5,000,000');

        $result = $block->render([]);

        $this->assertStringContainsString('jankx-orders-list', $result);
        $this->assertStringContainsString('OD-000001', $result);
        $this->assertStringContainsString('jankx-order-card', $result);
    }

    public function test_get_status_label_returns_correct_labels(): void
    {
        $block = new AccountTabOrdersBlock();

        $reflection = new \ReflectionMethod($block, 'getStatusLabel');
        $reflection->setAccessible(true);

        $this->assertSame('Pending', $reflection->invoke($block, 'pending'));
        $this->assertSame('Processing', $reflection->invoke($block, 'processing'));
        $this->assertSame('Completed', $reflection->invoke($block, 'completed'));
        $this->assertSame('Failed', $reflection->invoke($block, 'failed'));
        $this->assertSame('Cancelled', $reflection->invoke($block, 'cancelled'));
        $this->assertSame('Refunded', $reflection->invoke($block, 'refunded'));
    }

    public function test_get_status_label_unknown_status(): void
    {
        $block = new AccountTabOrdersBlock();

        $reflection = new \ReflectionMethod($block, 'getStatusLabel');
        $reflection->setAccessible(true);

        $this->assertSame('Custom_status', $reflection->invoke($block, 'custom_status'));
    }

    public function test_get_item_summary(): void
    {
        $mockOrder = $this->createMock(\Jankx\Extensions\Ecommerce\Order\Order::class);

        $mockItem1 = $this->createMock(\Jankx\Extensions\Ecommerce\Order\OrderItem::class);
        $mockItem1->method('getName')->willReturn('Tour Ha Long');
        $mockItem1->method('getQuantity')->willReturn(2);

        $mockItem2 = $this->createMock(\Jankx\Extensions\Ecommerce\Order\OrderItem::class);
        $mockItem2->method('getName')->willReturn('Tour Da Nang');
        $mockItem2->method('getQuantity')->willReturn(1);

        $mockOrder->method('getItems')->willReturn([$mockItem1, $mockItem2]);

        $block = new AccountTabOrdersBlock();

        $reflection = new \ReflectionMethod($block, 'getItemSummary');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($block, $mockOrder);

        $this->assertStringContainsString('Tour Ha Long', $result);
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('Tour Da Nang', $result);
    }

    public function test_get_item_summary_empty_items(): void
    {
        $mockOrder = $this->createMock(\Jankx\Extensions\Ecommerce\Order\Order::class);
        $mockOrder->method('getItems')->willReturn([]);

        $block = new AccountTabOrdersBlock();

        $reflection = new \ReflectionMethod($block, 'getItemSummary');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($block, $mockOrder);
        $this->assertSame('', $result);
    }

    public function test_render_always_shows_in_editor_mode(): void
    {
        // In editor/preview mode (REST_REQUEST + /block-renderer/), the block always renders
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }
        $_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/block-renderer/123';
        $_GET['tab'] = 'overview';

        \Brain\Monkey\Functions\when('get_query_var')->justReturn('overview');
        \Brain\Monkey\Functions\when('is_user_logged_in')->justReturn(true);

        // Need to set up $wpdb for OrderModel
        global $wpdb;
        $wpdb = $this->getMockBuilder('wpdb')
            ->onlyMethods(['prepare', 'get_row', 'get_results', 'get_var'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->method('get_results')->willReturn([]);
        $wpdb->method('prepare')->willReturn('SQL');

        $block = new AccountTabOrdersBlock();
        $result = $block->render([]);

        // In editor mode, block always renders (shows empty state when no orders)
        $this->assertStringContainsString('jankx-tab-orders', $result);
        $this->assertStringContainsString('Your orders', $result);

        unset($_SERVER['REQUEST_URI']);
        unset($_GET['tab']);
    }
}
