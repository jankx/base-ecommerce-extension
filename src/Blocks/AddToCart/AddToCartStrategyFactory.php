<?php
namespace Jankx\Extensions\Ecommerce\Blocks\AddToCart;

use Jankx\Extensions\Product\EcommerceProductExtension;

class AddToCartStrategyFactory
{
    /**
     * Resolve the rendering strategy for a product.
     *
     * Priority:
     *  1. Price disabled  → Contact text (no price, no form)
     *  2. Add-to-cart disabled via product meta → Scroll to form (price + button)
     *  3. Not purchasable (out of stock, etc.) → Contact text
     *  4. Default → Normal add-to-cart
     */
    public static function resolve($product): AddToCartStrategyInterface
    {
        $postId = $product->getId();

        // Price completely disabled — show contact text only
        if (EcommerceProductExtension::is_price_disabled($postId)) {
            return new ContactAddToCartStrategy();
        }

        // Per-product "disable add to cart" meta → show price + scroll to form
        if (EcommerceProductExtension::is_add_to_cart_disabled($postId)) {
            return new ScrollToFormAddToCartStrategy();
        }

        // Not purchasable (out of stock, draft, price = 0, etc.)
        if (!$product->isPurchasable()) {
            return new ContactAddToCartStrategy();
        }

        return new NormalAddToCartStrategy();
    }
}
