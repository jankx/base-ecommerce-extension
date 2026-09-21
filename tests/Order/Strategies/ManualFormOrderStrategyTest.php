<?php
namespace Jankx\Extensions\Ecommerce\Tests\Order\Strategies;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Order\Strategies\ManualFormOrderStrategy;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

class ManualFormOrderStrategyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('Brain\Monkey\Functions\when')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }
        stub_wp_ecommerce_functions();

        // Reset singleton
        $reflection = new \ReflectionClass(ProductRegistry::get_instance());
        $prop = $reflection->getProperty('productClasses');
        $prop->setValue(ProductRegistry::get_instance(), []);
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function registerTestProduct(int $id, float $price, string $type = 'product'): void
    {
        $product = new class ($id, $price, $type) {
            private int $id;
            private float $price;
            private string $type;

            public function __construct(int $id, float $price, string $type)
            {
                $this->id = $id;
                $this->price = $price;
                $this->type = $type;
            }

            public function getId(): int { return $this->id; }
            public function getName(): string { return 'Test Product ' . $this->id; }
            public function getPrice(): float { return $this->price; }
            public function getRegularPrice(): float { return $this->price; }
            public function getSalePrice(): float { return 0.0; }
            public function isPurchasable(): bool { return $this->price > 0; }
            public function isInStock(): bool { return true; }
            public function getProductType(): string { return $this->type; }
        };

        ProductRegistry::get_instance()->register('product', get_class($product));

        // Hack: inject the instance by re-registering with the anonymous class name
        $reflection = new \ReflectionClass(ProductRegistry::get_instance());
        $prop = $reflection->getProperty('productClasses');
        $prop->setValue(ProductRegistry::get_instance(), [
            'product' => get_class($product),
        ]);
    }

    public function test_validate_requires_product_id(): void
    {
        $strategy = new ManualFormOrderStrategy();
        $errors = $strategy->validate([
            'customer_name'  => 'Test',
            'customer_phone' => '0901234567',
            'customer_email' => 'test@example.com',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Sản phẩm không hợp lệ', $errors[0]);
    }

    public function test_validate_requires_customer_name(): void
    {
        \Brain\Monkey\Functions\when('get_post')->justReturn((object) ['post_status' => 'publish']);

        $strategy = new ManualFormOrderStrategy();
        $errors = $strategy->validate([
            'product_id'     => 1,
            'customer_name'  => '',
            'customer_phone' => '0901234567',
            'customer_email' => 'test@example.com',
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_validate_rejects_unpublished_product(): void
    {
        \Brain\Monkey\Functions\when('get_post')->justReturn((object) ['post_status' => 'draft']);

        $strategy = new ManualFormOrderStrategy();
        $errors = $strategy->validate([
            'product_id'     => 1,
            'customer_name'  => 'Test',
            'customer_phone' => '0901234567',
            'customer_email' => 'test@example.com',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('chưa được công khai', $errors[0]);
    }

    public function test_strategy_name_is_manual_form(): void
    {
        $strategy = new ManualFormOrderStrategy();
        $this->assertSame('manual_form', $strategy->getName());
    }
}
