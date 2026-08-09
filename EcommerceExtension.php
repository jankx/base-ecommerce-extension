<?php
namespace Jankx\Extensions\Ecommerce;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderPostType;
use Jankx\Extensions\Ecommerce\Payment\PaymentManager;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;
use Jankx\Extensions\Ecommerce\Rest\EcommerceController;

/**
 * Base E-Commerce Extension for Jankx
 *
 * Provides the shared e-commerce core used by every business extension:
 *
 *  - Product registry: register any post type as a purchasable item
 *    (tour, product, membership, ...)
 *  - Cart: session based shopping cart
 *  - Checkout: turn the cart into an order
 *  - Order: shared order post type + management API
 *  - Payment: bridge to the payment-system extension
 *
 * @package Jankx\Extensions\Ecommerce
 */
class EcommerceExtension extends AbstractExtension
{
    protected static $instance;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\Ecommerce\\';
            $base_dir = __DIR__ . '/src/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // Shared order post type on every request.
        (new OrderPostType())->register();

        // Allow hook-based registration of product types on init.
        add_action('init', [ProductRegistry::get_instance(), 'boot'], 10);

        // REST API for cart & checkout.
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Core ecommerce hooks shared across models.
        add_action('init', [$this, 'init_ecommerce_core']);
    }

    public function register_rest_routes(): void
    {
        $controller = new EcommerceController();
        $controller->register_routes();
    }

    public function init_ecommerce_core(): void
    {
        do_action('jankx/ecommerce/init');
    }

    /**
     * ------------------------------------------------------------------
     * Public API for business extensions (travel, ecommerce-product, ...)
     * ------------------------------------------------------------------
     */

    /**
     * Register a post type as a purchasable product.
     *
     * @param string $postType     e.g. "tour" or "product".
     * @param string $productClass Concrete ProductInterface implementation.
     */
    public static function register_product_type(string $postType, string $productClass): bool
    {
        return ProductRegistry::get_instance()->register($postType, $productClass);
    }

    public static function unregister_product_type(string $postType): void
    {
        ProductRegistry::get_instance()->unregister($postType);
    }

    /**
     * Get the list of post types registered into the ecommerce flow.
     *
     * @return string[]
     */
    public static function get_supported_product_types(): array
    {
        return ProductRegistry::get_instance()->getSupportedPostTypes();
    }

    /**
     * Check whether a post/post-type is a supported product.
     *
     * @param \WP_Post|int|string $post
     */
    public static function is_product($post): bool
    {
        return ProductRegistry::get_instance()->isSupported($post);
    }

    /**
     * Get the shared cart instance.
     */
    public static function get_cart(): Cart
    {
        return Cart::get_instance();
    }

    /**
     * Get the checkout manager.
     */
    public static function checkout_manager(): CheckoutManager
    {
        return CheckoutManager::get_instance();
    }

    /**
     * Run the checkout flow from the current cart.
     *
     * @param array $customer Customer data: name, email, phone, address.
     * @param array $options  Optional: gateway, currency, payment_params.
     * @return array [success, errors, order]
     */
    public static function checkout(array $customer, array $options = []): array
    {
        return CheckoutManager::get_instance()->checkout(Cart::get_instance(), $customer, $options);
    }

    /**
     * Get the payment manager.
     */
    public static function payment_manager(): PaymentManager
    {
        return PaymentManager::get_instance();
    }
}
