<?php
namespace Jankx\Extensions\Ecommerce\Tests\Blocks\AddToCart;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Blocks\AddToCart\AddToCartMode;
use Jankx\Extensions\Ecommerce\Blocks\AddToCart\AddToCartStrategyFactory;
use Jankx\Extensions\Ecommerce\Blocks\AddToCart\NormalAddToCartStrategy;
use Jankx\Extensions\Ecommerce\Blocks\AddToCart\ContactAddToCartStrategy;
use Jankx\Extensions\Ecommerce\Blocks\AddToCart\ScrollToFormAddToCartStrategy;

class AddToCartStrategyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('Brain\Monkey\Functions\when')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }
        stub_wp_ecommerce_functions();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    // ── AddToCartMode ────────────────────────────────────────

    public function test_mode_normal(): void
    {
        $mode = new AddToCartMode(AddToCartMode::NORMAL);
        $this->assertTrue($mode->isNormal());
        $this->assertFalse($mode->isContact());
        $this->assertFalse($mode->isScrollToForm());
        $this->assertSame('normal', $mode->value());
    }

    public function test_mode_contact(): void
    {
        $mode = new AddToCartMode(AddToCartMode::CONTACT);
        $this->assertTrue($mode->isContact());
        $this->assertFalse($mode->isNormal());
        $this->assertSame('contact', $mode->value());
    }

    public function test_mode_scroll_to_form(): void
    {
        $mode = new AddToCartMode(AddToCartMode::SCROLL_TO_FORM);
        $this->assertTrue($mode->isScrollToForm());
        $this->assertFalse($mode->isNormal());
        $this->assertSame('scroll_to_form', $mode->value());
    }

    public function test_mode_invalid_defaults_to_normal(): void
    {
        $mode = new AddToCartMode('invalid');
        $this->assertTrue($mode->isNormal());
        $this->assertSame('normal', $mode->value());
    }

    public function test_mode_to_string(): void
    {
        $mode = new AddToCartMode(AddToCartMode::CONTACT);
        $this->assertSame('contact', (string) $mode);
    }

    // ── Strategy Factory ─────────────────────────────────────

    private function createMockProduct(int $id, float $price = 1500000.0, bool $purchasable = true): object
    {
        return new class ($id, $price, $purchasable) {
            private int $id;
            private float $price;
            private bool $purchasable;

            public function __construct(int $id, float $price, bool $purchasable)
            {
                $this->id = $id;
                $this->price = $price;
                $this->purchasable = $purchasable;
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getPrice(): float
            {
                return $this->price;
            }

            public function isPurchasable(): bool
            {
                return $this->purchasable;
            }

            public function getProductType(): string
            {
                return 'product';
            }
        };
    }

    public function test_factory_returns_contact_when_price_disabled(): void
    {
        \Brain\Monkey\Functions\when('get_post_meta')->justReturn('');
        \Brain\Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
            if ($tag === 'jankx/product/price_disabled') {
                return true;
            }
            if ($tag === 'jankx/product/add_to_cart_disabled') {
                return false;
            }
            return $value;
        });

        $product = $this->createMockProduct(1);
        $strategy = AddToCartStrategyFactory::resolve($product);
        $this->assertInstanceOf(ContactAddToCartStrategy::class, $strategy);
    }

    public function test_factory_returns_scroll_to_form_when_add_to_cart_disabled(): void
    {
        \Brain\Monkey\Functions\when('get_post_meta')->justReturn('');
        \Brain\Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
            if ($tag === 'jankx/product/price_disabled') {
                return false;
            }
            if ($tag === 'jankx/product/add_to_cart_disabled') {
                return true;
            }
            return $value;
        });

        $product = $this->createMockProduct(1);
        $strategy = AddToCartStrategyFactory::resolve($product);
        $this->assertInstanceOf(ScrollToFormAddToCartStrategy::class, $strategy);
    }

    public function test_factory_returns_normal_when_purchasable(): void
    {
        \Brain\Monkey\Functions\when('get_post_meta')->justReturn('');
        \Brain\Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
            if ($tag === 'jankx/product/price_disabled') {
                return false;
            }
            if ($tag === 'jankx/product/add_to_cart_disabled') {
                return false;
            }
            return $value;
        });

        $product = $this->createMockProduct(1, 1500000.0, true);
        $strategy = AddToCartStrategyFactory::resolve($product);
        $this->assertInstanceOf(NormalAddToCartStrategy::class, $strategy);
    }

    public function test_factory_returns_contact_when_not_purchasable(): void
    {
        \Brain\Monkey\Functions\when('get_post_meta')->justReturn('');
        \Brain\Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
            if ($tag === 'jankx/product/price_disabled') {
                return false;
            }
            if ($tag === 'jankx/product/add_to_cart_disabled') {
                return false;
            }
            return $value;
        });

        $product = $this->createMockProduct(1, 1500000.0, false);
        $strategy = AddToCartStrategyFactory::resolve($product);
        $this->assertInstanceOf(ContactAddToCartStrategy::class, $strategy);
    }

    // ── Strategy Render ──────────────────────────────────────

    public function test_contact_strategy_renders_text(): void
    {
        $strategy = new ContactAddToCartStrategy();
        $product = $this->createMockProduct(1);
        $html = $strategy->render(1, $product, []);

        $this->assertStringContainsString('jankx-add-to-cart--contact', $html);
        $this->assertStringContainsString('Liên hệ', $html);
    }

    public function test_scroll_to_form_strategy_renders_button(): void
    {
        \Brain\Monkey\Functions\when('get_post_meta')->justReturn('');
        \Brain\Monkey\Functions\when('number_format_i18n')->alias(fn($val) => number_format($val, 0, ',', '.'));

        $strategy = new ScrollToFormAddToCartStrategy();
        $product = $this->createMockProduct(42);
        $html = $strategy->render(42, $product, []);

        $this->assertStringContainsString('jankx-scroll-to-order-form', $html);
        $this->assertStringContainsString('data-target="jankx-product-order-card-42"', $html);
        $this->assertStringContainsString('Liên hệ báo giá', $html);
    }

    public function test_normal_strategy_renders_form(): void
    {
        \Brain\Monkey\Functions\when('get_post_meta')->justReturn('');
        \Brain\Monkey\Functions\when('number_format_i18n')->alias(fn($val) => number_format($val, 0, ',', '.'));
        \Brain\Monkey\Functions\when('current_time')->justReturn('2026-09-21');
        \Brain\Monkey\Functions\when('get_post_type')->justReturn('product');

        $strategy = new NormalAddToCartStrategy();
        $product = $this->createMockProduct(1);
        $html = $strategy->render(1, $product, []);

        $this->assertStringContainsString('jankx-add-to-cart-form', $html);
        $this->assertStringContainsString('Thêm vào giỏ hàng', $html);
        $this->assertStringContainsString('type="submit"', $html);
    }
}
