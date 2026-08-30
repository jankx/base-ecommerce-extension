<?php
namespace Jankx\Extensions\Ecommerce\Order;

/**
 * A single line inside an order.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class OrderItem
{
    /** @var int */
    protected $productId;

    /** @var string */
    protected $name;

    /** @var string */
    protected $productType;

    /** @var int */
    protected $quantity;

    /** @var float */
    protected $unitPrice;

    /** @var array */
    protected $meta;

    public function __construct(array $data)
    {
        $this->productId = (int) ($data['product_id'] ?? 0);
        $this->name = (string) ($data['name'] ?? '');
        $this->productType = (string) ($data['product_type'] ?? '');
        $this->quantity = max(1, (int) ($data['quantity'] ?? 1));
        $this->unitPrice = (float) ($data['unit_price'] ?? 0);
        $this->meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProductType(): string
    {
        return $this->productType;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getTotal(): float
    {
        $total = (float) round($this->unitPrice * $this->quantity, 2);

        return (float) apply_filters('jankx/ecommerce/order/item_total', $total, $this);
    }

    public function toArray(): array
    {
        return [
            'product_id'   => $this->productId,
            'name'         => $this->name,
            'product_type' => $this->productType,
            'quantity'     => $this->quantity,
            'unit_price'   => $this->unitPrice,
            'total'        => $this->getTotal(),
            'meta'         => $this->meta,
        ];
    }
}
