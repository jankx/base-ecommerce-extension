<?php
namespace Jankx\Extensions\Ecommerce\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Verify that order-created hooks accept null cart without fatal errors.
 *
 * ManualFormOrderStrategy calls:
 *   do_action('jankx/ecommerce/order/created', $order, null);
 *
 * Both EcommerceExtension::on_order_created() and
 * GGSheetOrdersExtension::on_order_created() must accept ?Cart $cart.
 */
class OrderCreatedNullCartTest extends TestCase
{
    private static ?ReflectionMethod $ecommerceMethod = null;
    private static ?ReflectionMethod $ggsheetMethod = null;

    private static function getEcommerceMethod(): ReflectionMethod
    {
        if (self::$ecommerceMethod === null) {
            $classFile = dirname(__DIR__) . '/EcommerceExtension.php';
            if (!file_exists($classFile)) {
                self::markTestSkipped('EcommerceExtension.php not found');
            }
            require_once $classFile;

            self::$ecommerceMethod = new ReflectionMethod(
                \Jankx\Extensions\Ecommerce\EcommerceExtension::class,
                'on_order_created'
            );
        }
        return self::$ecommerceMethod;
    }

    private static function getGgsheetMethod(): ReflectionMethod
    {
        if (self::$ggsheetMethod === null) {
            $classFile = dirname(dirname(__DIR__)) . '/ggsheet-orders/GGSheetOrdersExtension.php';
            if (!file_exists($classFile)) {
                self::markTestSkipped('GGSheetOrdersExtension.php not found');
            }
            require_once $classFile;

            self::$ggsheetMethod = new ReflectionMethod(
                \Jankx\Extensions\GGSheetOrders\GGSheetOrdersExtension::class,
                'on_order_created'
            );
        }
        return self::$ggsheetMethod;
    }

    // ── EcommerceExtension ──────────────────────────────────

    public function test_ecommerce_on_order_created_accepts_null_cart(): void
    {
        $method = self::getEcommerceMethod();
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'on_order_created should have 2 parameters');
        $this->assertSame('order', $params[0]->getName());
        $this->assertSame('cart', $params[1]->getName());

        // Verify nullable type hint (?Cart $cart)
        $cartParam = $params[1];
        $type = $cartParam->getType();
        $this->assertNotNull($type, 'Cart parameter must have a type hint');
        $this->assertTrue($type->allowsNull(), 'Cart parameter must accept null (?Cart)');
        $this->assertStringContainsString('Cart', (string) $type);
    }

    // ── GGSheetOrdersExtension ──────────────────────────────

    public function test_ggsheet_on_order_created_accepts_null_cart(): void
    {
        $method = self::getGgsheetMethod();
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'on_order_created should have 2 parameters');
        $this->assertSame('order', $params[0]->getName());
        $this->assertSame('cart', $params[1]->getName());

        // Verify nullable type hint (?Cart $cart)
        $cartParam = $params[1];
        $type = $cartParam->getType();
        $this->assertNotNull($type, 'Cart parameter must have a type hint');
        $this->assertTrue($type->allowsNull(), 'Cart parameter must accept null (?Cart)');
        $this->assertStringContainsString('Cart', (string) $type);
    }

    // ── Both signatures match ───────────────────────────────

    public function test_both_methods_have_same_signature(): void
    {
        $ecommerce = self::getEcommerceMethod();
        $ggsheet = self::getGgsheetMethod();

        $ecommerceParams = $ecommerce->getParameters();
        $ggsheetParams = $ggsheet->getParameters();

        $this->assertCount(count($ecommerceParams), $ggsheetParams);

        for ($i = 0; $i < count($ecommerceParams); $i++) {
            $this->assertSame(
                (string) $ecommerceParams[$i]->getType(),
                (string) $ggsheetParams[$i]->getType(),
                "Parameter {$i} type mismatch"
            );
            $this->assertSame(
                $ecommerceParams[$i]->allowsNull(),
                $ggsheetParams[$i]->allowsNull(),
                "Parameter {$i} nullable mismatch"
            );
        }
    }
}
