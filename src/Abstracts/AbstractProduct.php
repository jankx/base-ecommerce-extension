<?php
namespace Jankx\Extensions\Ecommerce\Abstracts;

use Jankx\Extensions\Ecommerce\Contracts\ProductInterface;

/**
 * Abstract base for all product types.
 *
 * Template Method pattern:
 *   Concrete classes define meta keys + getProductType().
 *   Price reading logic lives here — no duplication.
 */
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

    // ── Price ───────────────────────────────────────────────

    public function getPrice(): float
    {
        return max(0.0, (float) $this->resolveMeta(
            static::PRICE_META_KEY,
            static::LEGACY_PRICE_META_KEYS
        ));
    }

    public function getRegularPrice(): float
    {
        return max(0.0, (float) $this->resolveMeta(
            static::REGULAR_PRICE_META_KEY,
            static::LEGACY_REGULAR_PRICE_META_KEYS
        ));
    }

    public function getSalePrice(): float
    {
        return max(0.0, (float) $this->resolveMeta(
            static::SALE_PRICE_META_KEY,
            static::LEGACY_SALE_PRICE_META_KEYS
        ));
    }

    // ── Purchasability ──────────────────────────────────────

    public function isPurchasable(): bool
    {
        return $this->post
            && $this->post->post_status === 'publish'
            && $this->getPrice() > 0
            && $this->isInStock();
    }

    public function isInStock(): bool
    {
        return (bool) $this->post;
    }

    // ── Internal ────────────────────────────────────────────

    /**
     * Read meta value: try primary key first, then legacy fallbacks.
     */
    protected function resolveMeta(string $primary, array $legacy = []): string
    {
        $value = get_post_meta($this->id, $primary, true);
        if ($value !== '' && $value !== false) {
            return $value;
        }

        foreach ($legacy as $key) {
            $value = get_post_meta($this->id, $key, true);
            if ($value !== '' && $value !== false) {
                return $value;
            }
        }

        return '';
    }
}
