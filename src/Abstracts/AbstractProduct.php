<?php
namespace Jankx\Extensions\Ecommerce\Abstracts;

use Jankx\Extensions\Ecommerce\Contracts\ProductInterface;

abstract class AbstractProduct implements ProductInterface
{
    protected $id;
    protected $post;

    public function __construct($product = null)
    {
        if (is_numeric($product)) {
            $this->id = absint($product);
            $this->post = get_post($this->id);
        } elseif ($product instanceof \WP_Post) {
            $this->id = $product->ID;
            $this->post = $product;
        }
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function getName(): string
    {
        return $this->post ? get_the_title($this->post) : '';
    }

    // Abstract methods to be implemented by specific business types
    abstract public function getPrice(): float;
    abstract public function getRegularPrice(): float;
    abstract public function getSalePrice(): float;
    abstract public function isPurchasable(): bool;
    abstract public function isInStock(): bool;
    abstract public function getProductType(): string;
}
