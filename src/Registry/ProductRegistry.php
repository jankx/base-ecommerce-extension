<?php
namespace Jankx\Extensions\Ecommerce\Registry;

use Jankx\Extensions\Ecommerce\Contracts\ProductInterface;

/**
 * Product Registry
 *
 * The public API that lets any business extension (travel → tour,
 * ecommerce-product → product, ...) register its post type as a
 * purchasable item for the shared cart, checkout, payment and order
 * flows provided by the base-ecommerce extension.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class ProductRegistry
{
    /**
     * @var ProductRegistry|null
     */
    protected static $instance;

    /**
     * Map of supported post type => ProductInterface implementation class.
     *
     * @var array<string, string>
     */
    protected $productClasses = [];

    /**
     * Whether the register action has already been fired.
     *
     * @var bool
     */
    protected $booted = false;

    protected function __construct()
    {
    }

    public static function get_instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the registry: allows hook-based registration.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        do_action('jankx/ecommerce/register_product_types', $this);
    }

    /**
     * Register a post type as a purchasable product.
     *
     * @param string $postType     Post type slug, e.g. "tour" or "product".
     * @param string $productClass Concrete class implementing ProductInterface.
     */
    public function register(string $postType, string $productClass): bool
    {
        if (!class_exists($productClass)) {
            return false;
        }

        if (!is_subclass_of($productClass, ProductInterface::class)) {
            return false;
        }

        $this->productClasses[$postType] = $productClass;

        do_action('jankx/ecommerce/product_type_registered', $postType, $productClass);

        return true;
    }

    public function unregister(string $postType): void
    {
        unset($this->productClasses[$postType]);
    }

    /**
     * Get all registered post types.
     *
     * @return string[]
     */
    public function getSupportedPostTypes(): array
    {
        return array_keys($this->productClasses);
    }

    /**
     * Get the product class registered for a post type.
     */
    public function getProductClass(string $postType): ?string
    {
        return $this->productClasses[$postType] ?? null;
    }

    /**
     * Check whether the given post/post-type is supported by ecommerce.
     *
     * @param \WP_Post|int|string $post
     */
    public function isSupported($post): bool
    {
        $postType = $post instanceof \WP_Post ? $post->post_type : get_post_type($post);

        return is_string($postType) && isset($this->productClasses[$postType]);
    }

    /**
     * Create a product instance for the given post.
     *
     * @param \WP_Post|int $post
     */
    public function createProduct($post): ?ProductInterface
    {
        $productPost = $post instanceof \WP_Post ? $post : get_post($post);
        if (!$productPost) {
            return null;
        }

        $class = $this->productClasses[$productPost->post_type] ?? null;
        if (!$class) {
            return null;
        }

        return new $class($productPost);
    }
}
