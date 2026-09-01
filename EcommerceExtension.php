<?php
namespace Jankx\Extensions\Ecommerce;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\Ecommerce\Blocks\AccountTabOrdersBlock;
use Jankx\Extensions\Ecommerce\Blocks\AddToCartBlock;
use Jankx\Extensions\Ecommerce\Blocks\CartBlock;
use Jankx\Extensions\Ecommerce\Blocks\CartItemBlock;
use Jankx\Extensions\Ecommerce\Blocks\CheckoutBlock;
use Jankx\Extensions\Ecommerce\Blocks\CurrencySwitcherBlock;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutManager;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;
use Jankx\Extensions\Ecommerce\Currency\Converters\AutoConfigConverter;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderDatabaseInstaller;
use Jankx\Extensions\Ecommerce\Admin\OrderAdmin;
use Jankx\Extensions\Ecommerce\Admin\EcommerceSettingsPage;
use Jankx\Extensions\Ecommerce\Admin\CurrencyDebugPage;
use Jankx\Extensions\Ecommerce\Payment\PaymentManager;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;
use Jankx\Extensions\Ecommerce\Rest\EcommerceController;
use Jankx\Extensions\NotificationSystem\NotificationService;

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
        // Install order database tables on first run.
        (new OrderDatabaseInstaller())->register();

        // Professional Orders management screen (wp-admin).
        if (is_admin()) {
            (new OrderAdmin())->register();
            (new EcommerceSettingsPage())->register();
            (new CurrencyDebugPage())->register();
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }

        // Allow hook-based registration of product types on init.
        add_action('init', [ProductRegistry::get_instance(), 'boot'], 10);

        // REST API for cart & checkout.
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Core ecommerce hooks shared across models.
        add_action('init', [$this, 'init_ecommerce_core']);

        // Gutenberg blocks for cart, checkout and account orders. Registered
        // on init because register_block_type_from_metadata() calls wp_script_is()
        // which is not allowed before that hook.
        add_action('init', [$this, 'register_blocks']);

        // Editor integration for the blocks.
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);

        // Frontend assets on the cart/checkout pages and single product pages.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Auto render an "Add to cart" area on single pages of supported
        // product types (tour, product, ...).
        add_filter('the_content', [$this, 'append_add_to_cart_to_content']);

        // Inject the "Orders" sub-page into My Account.
        add_action('jankx/my_account/register_sub_pages', [$this, 'register_my_account_sub_pages']);

        // Send notifications on order lifecycle events.
        add_action('jankx/ecommerce/order/created', [$this, 'on_order_created'], 10, 2);
        add_action('jankx/ecommerce/order/status_changed', [$this, 'on_order_status_changed'], 10, 4);
    }

    public function register_blocks(): void
    {
        $blocksDir = __DIR__ . '/blocks';
        if (!is_dir($blocksDir)) {
            return;
        }

        $blockClasses = [
            'cart'               => CartBlock::class,
            'cart-item'          => CartItemBlock::class,
            'checkout'           => CheckoutBlock::class,
            'account-tab-orders' => AccountTabOrdersBlock::class,
            'add-to-cart'        => AddToCartBlock::class,
            'currency-switcher'  => CurrencySwitcherBlock::class,
        ];

        foreach ($blockClasses as $blockName => $blockClass) {
            $blockPath = $blocksDir . '/' . $blockName;
            if (!is_dir($blockPath)) {
                continue;
            }

            $blockJson = json_decode((string) file_get_contents($blockPath . '/block.json'), true);
            $registeredName = $blockJson['name'] ?? '';
            if ($registeredName && \WP_Block_Type_Registry::get_instance()->is_registered($registeredName)) {
                continue;
            }

            $block = new $blockClass($blockPath);
            $block->setBlockPath($blockPath);
            $block->boot();
            $block->register();
        }
    }

    public function enqueue_block_editor_assets(): void
    {
        wp_enqueue_script(
            'jankx-ecommerce-blocks-editor',
            $this->get_extension_url() . '/assets/blocks-editor.js',
            ['wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-server-side-render', 'wp-hooks'],
            filemtime($this->get_extension_path() . '/assets/blocks-editor.js'),
            true
        );

        $blocksDir = $this->get_extension_path() . '/blocks';
        $blockMetadata = [];
        foreach (['cart', 'cart-item', 'checkout', 'account-tab-orders', 'add-to-cart', 'currency-switcher'] as $slug) {
            $blockJson = $blocksDir . '/' . $slug . '/block.json';
            if (file_exists($blockJson)) {
                $metadata = json_decode(file_get_contents($blockJson), true);
                if ($metadata) {
                    $blockMetadata[$metadata['name']] = $metadata;
                }
            }
        }
        wp_localize_script('jankx-ecommerce-blocks-editor', 'jankxEcommerceBlockMetadata', $blockMetadata);

        wp_enqueue_style(
            'jankx-ecommerce-blocks-editor',
            $this->get_extension_url() . '/assets/blocks-editor.css',
            ['jankx-mini-cart'],
            filemtime($this->get_extension_path() . '/assets/blocks-editor.css')
        );

        wp_enqueue_style(
            'jankx-mini-cart',
            $this->get_extension_url() . '/assets/mini-cart.css',
            [],
            filemtime($this->get_extension_path() . '/assets/mini-cart.css')
        );
    }

    /**
     * Enqueue admin styles used by the Orders management screen.
     */
    public function enqueue_admin_assets(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Enqueue on our custom orders page
        if ($screen->id === 'admin_page_jankx-orders') {
            wp_enqueue_style(
                'jankx-ecommerce-admin',
                $this->get_extension_url() . '/assets/admin.css',
                [],
                filemtime($this->get_extension_path() . '/assets/admin.css')
            );
        }
    }

    public function enqueue_frontend_assets(): void
    {
        $productTypes = self::get_supported_product_types();
        $isProductPage = !empty($productTypes) && is_singular($productTypes);
        $isMyAccount = is_page(get_option('jankx_my_account_page_id', 0));

        if (
            !is_page(self::get_cart_page_id()) &&
            !is_page(self::get_checkout_page_id()) &&
            !$isProductPage &&
            !$isMyAccount
        ) {
            return;
        }

        wp_enqueue_style(
            'jankx-ecommerce',
            $this->get_extension_url() . '/assets/frontend.css',
            [],
            filemtime($this->get_extension_path() . '/assets/frontend.css')
        );

        wp_enqueue_script(
            'jankx-ecommerce',
            $this->get_extension_url() . '/assets/frontend.js',
            [],
            filemtime($this->get_extension_path() . '/assets/frontend.js'),
            true
        );

        wp_localize_script('jankx-ecommerce', 'jankxEcommerce', [
            'restUrl'   => esc_url_raw(rest_url(EcommerceController::REST_NAMESPACE)),
            'cartUrl'   => self::get_cart_page_url(),
            'ordersUrl' => self::get_orders_page_url(),
            'i18n'      => [
                'successTitle'   => __('Order placed successfully!', 'jankx'),
                'successMessage' => __('Your order number is %s.', 'jankx'),
            ],
        ]);
    }

    /**
     * Append an "Add to cart" area after the content of single pages whose
     * post type is registered into the ecommerce flow (tour, product, ...).
     *
     * Skipped when the jankx/add-to-cart block is already placed in content.
     */
    public function append_add_to_cart_to_content(string $content): string
    {
        if (is_admin() || !is_singular()) {
            return $content;
        }

        $post = get_post();
        if (!$post || !self::is_product($post)) {
            return $content;
        }

        if (has_block(AddToCartBlock::BLOCK_ID, $post)) {
            return $content;
        }

        return $content . (new AddToCartBlock())->render([]);
    }

    /**
     * Register the "Orders" sub-page inside the My Account extension.
     */
    public function register_my_account_sub_pages(): void
    {
        if (!class_exists('\Jankx\Extensions\MyAccount\MyAccountExtension')) {
            return;
        }

        \Jankx\Extensions\MyAccount\MyAccountExtension::registerSubPage('orders', [
            'label'       => __('Orders', 'jankx'),
            'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
            'priority'    => 15,
            'extension'   => 'my-account',
            'show_in_nav' => true,
            'callback'    => [new AccountTabOrdersBlock(), 'render'],
        ]);
    }

    /**
     * Create the Cart and Checkout pages on install.
     */
    public function install(): bool
    {
        $this->create_cart_page();
        $this->create_checkout_page();

        flush_rewrite_rules();

        return parent::install();
    }

    protected function create_cart_page(): void
    {
        $pageId = get_option('jankx_cart_page_id', 0);
        if ($pageId && get_post_status($pageId) === 'publish') {
            return;
        }

        $pageId = wp_insert_post([
            'post_title'   => __('Giỏ hàng', 'jankx'),
            'post_content' => '<!-- wp:jankx/cart {"align":"wide"} /-->',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ]);

        if ($pageId && !is_wp_error($pageId)) {
            update_option('jankx_cart_page_id', $pageId);
        }
    }

    protected function create_checkout_page(): void
    {
        $pageId = get_option('jankx_checkout_page_id', 0);
        if ($pageId && get_post_status($pageId) === 'publish') {
            return;
        }

        $pageId = wp_insert_post([
            'post_title'   => __('Thanh toán', 'jankx'),
            'post_content' => '<!-- wp:jankx/checkout {"align":"wide"} /-->',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ]);

        if ($pageId && !is_wp_error($pageId)) {
            update_option('jankx_checkout_page_id', $pageId);
        }
    }

    public static function get_cart_page_id(): int
    {
        return (int) get_option('jankx_cart_page_id', 0);
    }

    public static function get_checkout_page_id(): int
    {
        return (int) get_option('jankx_checkout_page_id', 0);
    }

    public static function get_cart_page_url(): string
    {
        $pageId = self::get_cart_page_id();

        return $pageId ? (string) get_permalink($pageId) : '';
    }

    public static function get_checkout_page_url(): string
    {
        $pageId = self::get_checkout_page_id();

        return $pageId ? (string) get_permalink($pageId) : '';
    }

    public static function get_orders_page_url(): string
    {
        $accountPageId = (int) get_option('jankx_my_account_page_id', 0);
        if (!$accountPageId) {
            return '';
        }

        $accountUrl = get_permalink($accountPageId);

        return $accountUrl ? trailingslashit($accountUrl) . 'orders/' : '';
    }

    public function register_rest_routes(): void
    {
        $controller = new EcommerceController();
        $controller->register_routes();
    }

    public function init_ecommerce_core(): void
    {
        // Auto-detect and configure converter from environment/constants
        // (before getting manager instance)
        AutoConfigConverter::autoDetectAndConfigure();

        // Initialize currency conversion system - ensure it's loaded before any price formatting
        CurrencyConverterManager::getInstance();

        // Initialize currency manager
        add_filter('jankx/ecommerce/currency', function ($currency) {
            return CurrencyManager::getCurrentCurrency();
        });

        add_filter('jankx/ecommerce/price_format', function ($formatted, $price, $currency) {
            return CurrencyManager::formatPriceRaw($price, $currency);
        }, 10, 3);

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

    // ── Order notification hooks ───────────────────────────

    /**
     * Send a "order created" notification when a new order is placed.
     */
    public function on_order_created(Order $order, Cart $cart): void
    {
        $userId = $order->getCustomerId();
        if (!$userId) {
            return;
        }

        $labels = Order::getStatusLabels();
        $total  = function_exists('jankx_currency_format')
            ? jankx_currency_format($order->getTotal())
            : number_format($order->getTotal(), 0, ',', '.') . ' ' . $order->getCurrency();

        NotificationService::send(
            $userId,
            'order.created',
            sprintf(__('Đơn hàng #%s đã được tạo', 'jankx'), $order->getOrderNumber()),
            sprintf(
                __('Đơn hàng của bạn với tổng %s đã được tiếp nhận và đang chờ xử lý.', 'jankx'),
                $total
            ),
            [
                'order_id'     => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'status'       => $order->getStatus(),
                'total'        => $order->getTotal(),
                'currency'     => $order->getCurrency(),
                'action_url'   => $this->get_order_url($order),
            ]
        );
    }

    /**
     * Send a "status changed" notification when an order moves to a new status.
     */
    public function on_order_status_changed(Order $order, string $newStatus, string $oldStatus, int $handlerId): void
    {
        $userId = $order->getCustomerId();
        if (!$userId) {
            return;
        }

        $labels = Order::getStatusLabels();
        $newLabel = $labels[$newStatus] ?? ucfirst($newStatus);

        $total = function_exists('jankx_currency_format')
            ? jankx_currency_format($order->getTotal())
            : number_format($order->getTotal(), 0, ',', '.') . ' ' . $order->getCurrency();

        $message = sprintf(
            __('Trạng thái đơn hàng #%s đã chuyển từ "%s" sang "%s".', 'jankx'),
            $order->getOrderNumber(),
            $labels[$oldStatus] ?? ucfirst($oldStatus),
            $newLabel
        );

        // Include tracking number when order is being shipped
        $data = [
            'order_id'     => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'total'        => $order->getTotal(),
            'currency'     => $order->getCurrency(),
            'action_url'   => $this->get_order_url($order),
        ];

        if ($newStatus === Order::STATUS_SHIPPING && $order->getTrackingNumber()) {
            $message .= sprintf(
                __(" Mã vận đơn: %s", 'jankx'),
                $order->getTrackingNumber()
            );
            $data['tracking_number'] = $order->getTrackingNumber();
        }

        NotificationService::send(
            $userId,
            'order.status_changed',
            sprintf(
                __('Đơn hàng #%s: %s', 'jankx'),
                $order->getOrderNumber(),
                $newLabel
            ),
            $message,
            $data
        );
    }

    /**
     * Build the frontend URL for an order detail page.
     */
    private function get_order_url(Order $order): string
    {
        $accountUrl = function_exists('jankx_get_account_endpoint_url')
            ? jankx_get_account_endpoint_url('orders')
            : home_url('/my-account/orders/');

        return add_query_arg('order', $order->getOrderNumber(), $accountUrl);
    }
}
