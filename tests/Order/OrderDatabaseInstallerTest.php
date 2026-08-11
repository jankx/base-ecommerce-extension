<?php
namespace Jankx\Extensions\Ecommerce\Tests\Order;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Order\OrderDatabaseInstaller;

class OrderDatabaseInstallerTest extends TestCase
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

    public function test_maybe_create_tables_skips_when_tables_exist(): void
    {
        \Brain\Monkey\Functions\when('get_option')->justReturn('1.0.0');

        $installer = $this->getMockBuilder(OrderDatabaseInstaller::class)
            ->setMethods(['tableExists', 'createTables', 'migrate'])
            ->getMock();

        $installer->expects($this->exactly(2))
            ->method('tableExists')
            ->willReturn(true);

        $installer->expects($this->never())
            ->method('createTables');

        $installer->maybeCreateTables();
    }

    public function test_maybe_create_tables_creates_when_tables_missing(): void
    {
        \Brain\Monkey\Functions\when('get_option')->justReturn(false);
        \Brain\Monkey\Functions\when('update_option')->justReturn(true);

        $installer = $this->getMockBuilder(OrderDatabaseInstaller::class)
            ->setMethods(['tableExists', 'createTables', 'migrate'])
            ->getMock();

        // First table doesn't exist -> short-circuits, second not checked
        $installer->expects($this->once())
            ->method('tableExists')
            ->willReturn(false);

        $installer->expects($this->once())
            ->method('createTables');

        $installer->maybeCreateTables();
    }

    public function test_maybe_create_tables_updates_version_option(): void
    {
        \Brain\Monkey\Functions\when('get_option')->justReturn(false);

        $updateCalled = false;
        $updateKey = '';
        \Brain\Monkey\Functions\when('update_option')
            ->alias(function ($key, $value) use (&$updateCalled, &$updateKey) {
                if ($key === 'jankx_order_db_version') {
                    $updateCalled = true;
                    $updateKey = $key;
                }
                return true;
            });

        $installer = $this->getMockBuilder(OrderDatabaseInstaller::class)
            ->setMethods(['tableExists', 'createTables', 'migrate'])
            ->getMock();

        $installer->method('tableExists')->willReturn(false);
        $installer->method('createTables');

        $installer->maybeCreateTables();

        $this->assertTrue($updateCalled);
        $this->assertSame('jankx_order_db_version', $updateKey);
    }

    public function test_maybe_create_tables_sets_version_when_tables_already_exist(): void
    {
        \Brain\Monkey\Functions\when('get_option')->justReturn(false);

        $updateCalled = false;
        \Brain\Monkey\Functions\when('update_option')
            ->alias(function ($key, $value) use (&$updateCalled) {
                if ($key === 'jankx_order_db_version') {
                    $updateCalled = true;
                }
                return true;
            });

        $installer = $this->getMockBuilder(OrderDatabaseInstaller::class)
            ->setMethods(['tableExists', 'createTables', 'migrate'])
            ->getMock();

        $installer->method('tableExists')->willReturn(true);
        $installer->expects($this->never())->method('createTables');

        $installer->maybeCreateTables();

        $this->assertTrue($updateCalled);
    }

    public function test_register_hooks(): void
    {
        $installer = new OrderDatabaseInstaller();
        $installer->register();
        $this->assertTrue(true);
    }
}
