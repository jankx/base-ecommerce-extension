<?php
namespace Jankx\Extensions\Ecommerce\Tests\Order;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Order\OrderItem;

class OrderItemTest extends TestCase
{
    public function test_construct_with_full_data(): void
    {
        $item = new OrderItem([
            'product_id'   => 42,
            'name'         => 'Tour Ha Long Bay',
            'product_type' => 'tour',
            'quantity'     => 2,
            'unit_price'   => 1500000.50,
            'meta'         => ['date' => '2026-09-01'],
        ]);

        $this->assertSame(42, $item->getProductId());
        $this->assertSame('Tour Ha Long Bay', $item->getName());
        $this->assertSame('tour', $item->getProductType());
        $this->assertSame(2, $item->getQuantity());
        $this->assertSame(1500000.50, $item->getUnitPrice());
        $this->assertSame(['date' => '2026-09-01'], $item->getMeta());
    }

    public function test_construct_with_defaults(): void
    {
        $item = new OrderItem([]);

        $this->assertSame(0, $item->getProductId());
        $this->assertSame('', $item->getName());
        $this->assertSame('', $item->getProductType());
        $this->assertSame(1, $item->getQuantity());
        $this->assertSame(0.0, $item->getUnitPrice());
        $this->assertSame([], $item->getMeta());
    }

    public function test_quantity_minimum_is_one(): void
    {
        $item = new OrderItem(['quantity' => 0]);
        $this->assertSame(1, $item->getQuantity());

        $item = new OrderItem(['quantity' => -5]);
        $this->assertSame(1, $item->getQuantity());
    }

    public function test_get_total(): void
    {
        $item = new OrderItem([
            'quantity'   => 3,
            'unit_price' => 500000,
        ]);

        $this->assertSame(1500000.0, $item->getTotal());
    }

    public function test_get_total_with_decimals(): void
    {
        $item = new OrderItem([
            'quantity'   => 2,
            'unit_price' => 99.99,
        ]);

        $this->assertSame(199.98, $item->getTotal());
    }

    public function test_to_array(): void
    {
        $item = new OrderItem([
            'product_id'   => 10,
            'name'         => 'Test Product',
            'product_type' => 'service',
            'quantity'     => 2,
            'unit_price'   => 250000,
            'meta'         => ['key' => 'value'],
        ]);

        $expected = [
            'product_id'   => 10,
            'name'         => 'Test Product',
            'product_type' => 'service',
            'quantity'     => 2,
            'unit_price'   => 250000,
            'total'        => 500000.0,
            'meta'         => ['key' => 'value'],
        ];

        $this->assertEquals($expected, $item->toArray());
    }

    public function test_meta_defaults_to_empty_array_for_non_array(): void
    {
        $item = new OrderItem(['meta' => 'string']);
        $this->assertSame([], $item->getMeta());

        $item = new OrderItem(['meta' => null]);
        $this->assertSame([], $item->getMeta());
    }
}
