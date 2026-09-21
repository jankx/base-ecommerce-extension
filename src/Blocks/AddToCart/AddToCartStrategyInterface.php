<?php
namespace Jankx\Extensions\Ecommerce\Blocks\AddToCart;

interface AddToCartStrategyInterface
{
    /**
     * Render the add-to-cart area for a product.
     *
     * @param int   $postId     Product post ID.
     * @param mixed $product    Product model instance.
     * @param array $attributes Block attributes.
     * @return string           Rendered HTML.
     */
    public function render(int $postId, $product, array $attributes): string;
}
