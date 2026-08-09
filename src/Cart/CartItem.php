<?php
namespace Jankx\Extensions\Ecommerce\Cart;

use Jankx\Extensions\Ecommerce\Contracts\ProductInterface;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

/**
 * A single line inside the cart.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class CartItem
{
    /** @var string */
    protected $itemKey;

    /** @var int */
    protected $productId;

    /** @var int */
    protected $quantity;

    /** @var array */
    protected $args;

    public function __construct(string $itemKey, int $productId, int $quantity, array $args = [])
    {
        $this->itemKey = $itemKey;
        $this->productId = $productId;
        $this->quantity = max(1, $quantity);
        $this->args = $args;
    }

    public function getItemKey(): string
    {
        return $this->itemKey;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = max(1, $quantity);
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Resolve the product instance from the registry.
     */
    public function getProduct(): ?ProductInterface
    {
        return ProductRegistry::get_instance()->createProduct($this->productId);
    }

    public function getName(): string
    {
        $product = $this->getProduct();

        return $product ? $product->getName() : get_the_title($this->productId);
    }

    public function getUnitPrice(): float
    {
        $product = $this->getProduct();
        $price = $product ? $product->getPrice() : 0.0;

        return apply_filters('jankx/ecommerce/cart/item/unit_price', (float) $price, $this);
    }

    public function getSubtotal(): float
    {
        return (float) round($this->getUnitPrice() * $this->quantity, 2);
    }

    public function toArray(): array
    {
        return [
            'item_key'   => $this->itemKey,
            'product_id' => $this->productId,
            'name'       => $this->getName(),
            'quantity'   => $this->quantity,
            'unit_price' => $this->getUnitPrice(),
            'subtotal'   => $this->getSubtotal(),
            'args'       => $this->args,
        ];
    }
}
