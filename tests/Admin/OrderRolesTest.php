<?php
namespace {
    // Minimal WP_Role stand-in so the \WP_Role type-hint on OrderRoles::setCap()
    // is satisfied outside a full WordPress environment.
    if (!class_exists('WP_Role')) {
        class WP_Role
        {
        }
    }

    class FakeRole extends WP_Role
    {
        public $name;
        public $displayName;
        public $caps = [];

        public function __construct($name, $displayName = '', $caps = [])
        {
            $this->name = $name;
            $this->displayName = $displayName;
            $this->caps = $caps;
        }

        public function has_cap($cap)
        {
            return (bool) ($this->caps[$cap] ?? false);
        }

        public function add_cap($cap)
        {
            $this->caps[$cap] = true;
        }

        public function remove_cap($cap)
        {
            unset($this->caps[$cap]);
        }
    }
}

namespace Jankx\Extensions\Ecommerce\Tests\Admin {
    use PHPUnit\Framework\TestCase;
    use Jankx\Extensions\Ecommerce\Admin\OrderRoles;

    class OrderRolesTest extends TestCase
    {
        protected function setUp(): void
        {
            if (!function_exists('Brain\Monkey\Functions\when')) {
                require_once __DIR__ . '/../bootstrap.php';
            }
            stub_wp_ecommerce_functions();

            $GLOBALS['__wp_roles'] = [];

            \Brain\Monkey\Functions\when('get_role')->alias(function ($name) {
                return $GLOBALS['__wp_roles'][$name] ?? null;
            });

            \Brain\Monkey\Functions\when('add_role')->alias(function ($name, $displayName, $caps) {
                $role = new \FakeRole($name, $displayName, $caps);
                $GLOBALS['__wp_roles'][$name] = $role;
                return $role;
            });
        }

        protected function tearDown(): void
        {
            \Brain\Monkey\tearDown();
            parent::tearDown();
        }

        protected function seedRole(string $name, array $caps = []): \FakeRole
        {
            $role = new \FakeRole($name, ucfirst($name), $caps);
            $GLOBALS['__wp_roles'][$name] = $role;
            return $role;
        }

        public function test_setup_creates_missing_staff_roles(): void
        {
            $this->seedRole('administrator');

            (new OrderRoles())->setup();

            $manager = $GLOBALS['__wp_roles'][OrderRoles::ROLE_SHOP_MANAGER] ?? null;
            $sale = $GLOBALS['__wp_roles'][OrderRoles::ROLE_SALE] ?? null;
            $accountant = $GLOBALS['__wp_roles'][OrderRoles::ROLE_ACCOUNTANT] ?? null;

            $this->assertInstanceOf(\FakeRole::class, $manager);
            $this->assertInstanceOf(\FakeRole::class, $sale);
            $this->assertInstanceOf(\FakeRole::class, $accountant);

            $this->assertSame('Quản lý cửa hàng', $manager->displayName);
            $this->assertSame('Bộ phận bán hàng', $sale->displayName);
            $this->assertSame('Kế toán', $accountant->displayName);

            $this->assertTrue($sale->has_cap('read'));
            $this->assertTrue($accountant->has_cap('read'));
        }

        public function test_setup_does_not_recreate_existing_roles(): void
        {
            $existingSale = $this->seedRole(OrderRoles::ROLE_SALE, ['read' => true]);
            $existingAccountant = $this->seedRole(OrderRoles::ROLE_ACCOUNTANT, ['read' => true]);
            $existingManager = $this->seedRole(OrderRoles::ROLE_SHOP_MANAGER, ['read' => true]);

            (new OrderRoles())->setup();

            $this->assertSame($existingSale, $GLOBALS['__wp_roles'][OrderRoles::ROLE_SALE]);
            $this->assertSame($existingAccountant, $GLOBALS['__wp_roles'][OrderRoles::ROLE_ACCOUNTANT]);
            $this->assertSame($existingManager, $GLOBALS['__wp_roles'][OrderRoles::ROLE_SHOP_MANAGER]);
        }

        public function test_order_viewer_capabilities_matrix(): void
        {
            $this->seedRole('administrator');
            $editor = $this->seedRole('editor');
            $manager = $this->seedRole(OrderRoles::ROLE_SHOP_MANAGER);
            $sale = $this->seedRole(OrderRoles::ROLE_SALE);
            $accountant = $this->seedRole(OrderRoles::ROLE_ACCOUNTANT);

            (new OrderRoles())->setup();

            $administrator = $GLOBALS['__wp_roles']['administrator'];

            $this->assertTrue($administrator->has_cap('read_orders'));
            $this->assertTrue($administrator->has_cap('manage_orders'));

            $this->assertFalse($editor->has_cap('read_orders'));
            $this->assertFalse($editor->has_cap('manage_orders'));

            $this->assertTrue($manager->has_cap('read_orders'));
            $this->assertTrue($manager->has_cap('manage_orders'));

            $this->assertTrue($sale->has_cap('read_orders'));
            $this->assertTrue($sale->has_cap('manage_orders'));

            $this->assertTrue($accountant->has_cap('read_orders'));
            $this->assertFalse($accountant->has_cap('manage_orders'));
        }

        public function test_tour_editing_capabilities_matrix(): void
        {
            $this->seedRole('administrator');
            $editor = $this->seedRole('editor', ['edit_posts' => true]);
            $manager = $this->seedRole(OrderRoles::ROLE_SHOP_MANAGER, ['edit_posts' => true]);
            $sale = $this->seedRole(OrderRoles::ROLE_SALE);
            $accountant = $this->seedRole(OrderRoles::ROLE_ACCOUNTANT);

            (new OrderRoles())->setup();

            $this->assertTrue($editor->has_cap('edit_posts'));
            $this->assertTrue($manager->has_cap('edit_posts'));
            $this->assertTrue($manager->has_cap('upload_files'));

            $this->assertFalse($sale->has_cap('edit_posts'));
            $this->assertFalse($sale->has_cap('upload_files'));
            $this->assertFalse($accountant->has_cap('edit_posts'));
            $this->assertFalse($accountant->has_cap('upload_files'));
        }

        public function test_stale_order_caps_are_removed_from_editor(): void
        {
            $editor = $this->seedRole('editor', ['read_orders' => true, 'manage_orders' => true]);

            (new OrderRoles())->setup();

            $this->assertFalse($editor->has_cap('read_orders'));
            $this->assertFalse($editor->has_cap('manage_orders'));
        }
    }
}