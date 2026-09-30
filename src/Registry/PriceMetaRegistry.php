<?php
namespace Jankx\Extensions\Ecommerce\Registry;

use Jankx\Extensions\Ecommerce\Abstracts\AbstractProduct;

/**
 * Price Meta Registry
 *
 * The public API that answers one question for the whole theme: "which
 * post_meta key holds the price of this post type?"
 *
 * Every business extension (travel → tour, ecommerce-product → product,
 * place → place, ...) owns a distinct price meta key. Instead of every
 * consumer re-deriving that mapping on each render, extensions register
 * their key once here and all readers (Post Price, Post Starting Price,
 * admin meta boxes, advanced search) resolve through this single API.
 *
 * Definitions do not change at runtime, so resolved key lists are memoized
 * in static properties: a lookup is an array read instead of a
 * class_exists + registry scan + array_merge on every block render.
 *
 * Resolution order for a post type:
 *   1. An explicit definition registered through register().
 *   2. The constants of the ProductRegistry class for that post type
 *      (PRICE_META_KEY / LEGACY_PRICE_META_KEYS), so extensions that only
 *      register a product keep working without extra wiring.
 *   3. A generic "_{post_type}_*" derivation for post types that are not
 *      ecommerce products (keeps legacy data readable).
 *
 * @package Jankx\Extensions\Ecommerce
 */
class PriceMetaRegistry
{
    /**
     * @var PriceMetaRegistry|null
     */
    protected static $instance;

    /**
     * Map of post type => definition.
     *
     * @var array<string, array{price: string, legacy: string[], starting: string[], regular: string, sale: string}>
     */
    protected $definitions = [];

    /**
     * Whether register action has already been fired.
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * Memoized ordered read keys per post type, keyed by "postType" for the
     * charge price and "postType::starting" for the starting price.
     *
     * @var array<string, string[]>|null
     */
    protected static $resolvedKeys = null;

    /**
     * Memoized canonical write key per post type, keyed like $resolvedKeys.
     *
     * @var array<string, string>|null
     */
    protected static $resolvedWriteKey = null;

    /**
     * Generic keys that never identify a post type on their own, so the
     * canonical write key skips them.
     *
     * @var string[]
     */
    protected const GENERIC_KEYS = [
        '_jankx_price',
        '_jankx_regular_price',
        '_jankx_sale_price',
        '_price',
    ];

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

        do_action('jankx/ecommerce/register_price_meta', $this);
    }

    /**
     * Register the price meta keys owned by a post type.
     *
     * @param string $postType Post type slug, e.g. "tour" or "product".
     * @param string $priceKey The canonical price meta key.
     * @param array  $args {
     *     Optional. Older keys to read as fallbacks, and the regular/sale keys.
     *
     *     @type string[] $legacy  Ordered fallback keys, highest priority first.
     *     @type string[] $starting Keys holding the "from" price, read before
     *                              the charge price by starting price blocks.
     *     @type string   $regular Regular (compare-at) price meta key.
     *     @type string   $sale    Sale price meta key.
     * }
     */
    public function register(string $postType, string $priceKey, array $args = []): bool
    {
        $postType = trim($postType);
        $priceKey = trim($priceKey);

        if ($postType === '' || $priceKey === '') {
            return false;
        }

        $this->definitions[$postType] = [
            'price'    => $priceKey,
            'legacy'   => $this->sanitizeKeys($args['legacy'] ?? []),
            'starting' => $this->sanitizeKeys($args['starting'] ?? []),
            'regular'  => (string) ($args['regular'] ?? ''),
            'sale'     => (string) ($args['sale'] ?? ''),
        ];

        self::flushCache($postType);

        do_action('jankx/ecommerce/price_meta_registered', $postType, $priceKey);

        return true;
    }

    public function unregister(string $postType): void
    {
        unset($this->definitions[$postType]);

        self::flushCache($postType);
    }

    /**
     * All post types with an explicit price meta definition.
     *
     * @return string[]
     */
    public function getRegisteredPostTypes(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Ordered list of meta keys holding the price a customer actually pays,
     * highest priority first. Consumed by AbstractProduct::getPrice() so the
     * charge price stays independent from the advertised "from" price.
     *
     * @param string $postType
     * @return string[]
     */
    public function getPriceKeys(string $postType): array
    {
        return $this->resolveKeys($postType, false);
    }

    /**
     * Ordered list of meta keys holding the advertised starting ("from")
     * price, highest priority first.
     *
     * A starting price block must read this instead of getPriceKeys(): the
     * charge price is not the price the card starts at, and reading it first
     * would advertise the wrong number.
     *
     * @param string $postType
     * @return string[]
     */
    public function getStartingPriceKeys(string $postType): array
    {
        return $this->resolveKeys($postType, true);
    }

    /**
     * The meta key an admin/meta box should write for a post type.
     *
     * Prefers the first type specific key (e.g. _tour_price) over the
     * generic _jankx_price so external readers (advanced search, tour
     * pricing) keep seeing the updated value.
     *
     * @param string $postType
     * @return string
     */
    public function getPriceKey(string $postType): string
    {
        return $this->resolveWriteKey($postType, false);
    }

    /**
     * The meta key an admin/meta box should write for the starting price.
     *
     * @param string $postType
     * @return string
     */
    public function getStartingPriceKey(string $postType): string
    {
        return $this->resolveWriteKey($postType, true);
    }

    /**
     * Regular (compare-at) price meta key for a post type, or '' when the
     * post type does not declare one.
     */
    public function getRegularPriceKey(string $postType): string
    {
        $definition = $this->definitions[$postType] ?? $this->definitionFromProduct($postType);

        return (string) ($definition['regular'] ?? '');
    }

    /**
     * Sale price meta key for a post type, or '' when the post type does
     * not declare one.
     */
    public function getSalePriceKey(string $postType): string
    {
        $definition = $this->definitions[$postType] ?? $this->definitionFromProduct($postType);

        return (string) ($definition['sale'] ?? '');
    }

    /**
     * Drop memoized lookups so the next call re-resolves. Used by tests and
     * whenever a registration changes mid request.
     *
     * @param string|null $postType Limit the flush to one post type.
     */
    public static function flushCache(?string $postType = null): void
    {
        if ($postType === null) {
            self::$resolvedKeys = null;
            self::$resolvedWriteKey = null;

            return;
        }

        unset(self::$resolvedKeys[$postType], self::$resolvedWriteKey[$postType]);
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * Normalize a list of meta keys, dropping empty/non-scalar entries.
     *
     * @param mixed $keys
     * @return string[]
     */
    protected function sanitizeKeys($keys): array
    {
        $clean = [];

        foreach ((array) $keys as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $clean[] = $key;
            }
        }

        return $clean;
    }

    /**
     * Resolve (and memoize) the ordered read list for a post type.
     *
     * @return string[]
     */
    protected function resolveKeys(string $postType, bool $starting): array
    {
        if (self::$resolvedKeys === null) {
            self::$resolvedKeys = [];
        }

        $cacheKey = $postType . ($starting ? '::starting' : '');

        if (isset(self::$resolvedKeys[$cacheKey])) {
            return self::$resolvedKeys[$cacheKey];
        }

        if ($postType === '') {
            $keys = ['_price'];
        } else {
            $keys = $this->buildKeys($postType, $starting);
        }

        // Preserve resolution order while dropping duplicates and blanks.
        self::$resolvedKeys[$cacheKey] = array_values(array_unique(array_filter($keys)));

        return self::$resolvedKeys[$cacheKey];
    }

    /**
     * Resolve (and memoize) the canonical write key for a post type.
     */
    protected function resolveWriteKey(string $postType, bool $starting): string
    {
        if (self::$resolvedWriteKey === null) {
            self::$resolvedWriteKey = [];
        }

        $cacheKey = $postType . ($starting ? '::starting' : '');

        if (isset(self::$resolvedWriteKey[$cacheKey])) {
            return self::$resolvedWriteKey[$cacheKey];
        }

        $key = '_price';

        foreach ($this->resolveKeys($postType, $starting) as $candidate) {
            if (!in_array($candidate, self::GENERIC_KEYS, true)) {
                $key = $candidate;
                break;
            }
        }

        self::$resolvedWriteKey[$cacheKey] = $key;

        return $key;
    }

    /**
     * Build the ordered read list for a post type.
     *
     * @return string[]
     */
    protected function buildKeys(string $postType, bool $starting): array
    {
        $definition = $this->definitions[$postType] ?? $this->definitionFromProduct($postType);

        if ($definition === null) {
            return $this->deriveGenericKeys($postType, $starting);
        }

        if (!$starting) {
            return array_merge(
                [$definition['price']],
                $definition['legacy'],
                array_filter([$definition['regular'], $definition['sale']])
            );
        }

        // Starting price first, then the charge price as a fallback so a post
        // type that only stores one price still renders.
        return array_merge(
            $definition['starting'],
            [$definition['price']],
            $definition['legacy'],
            array_filter([$definition['regular'], $definition['sale']])
        );
    }

    /**
     * Derive a definition from the product class registered for a post type.
     *
     * @return array{price: string, legacy: string[], starting: string[], regular: string, sale: string}|null
     */
    protected function definitionFromProduct(string $postType): ?array
    {
        if (!class_exists(ProductRegistry::class)) {
            return null;
        }

        $productClass = ProductRegistry::get_instance()->getProductClass($postType);
        if (!$productClass || !is_subclass_of($productClass, AbstractProduct::class)) {
            return null;
        }

        return [
            'price'    => (string) $productClass::PRICE_META_KEY,
            'legacy'   => $this->sanitizeKeys($productClass::LEGACY_PRICE_META_KEYS),
            'starting' => $this->startingKeysFromProduct($productClass),
            'regular'  => (string) ($productClass::REGULAR_PRICE_META_KEY ?? ''),
            'sale'     => (string) ($productClass::SALE_PRICE_META_KEY ?? ''),
        ];
    }

    /**
     * Read the starting price constants a product class may declare.
     *
     * @return string[]
     */
    protected function startingKeysFromProduct(string $productClass): array
    {
        $keys = [];

        if (defined($productClass . '::STARTING_PRICE_META_KEY')) {
            $keys[] = constant($productClass . '::STARTING_PRICE_META_KEY');
        }

        if (defined($productClass . '::LEGACY_STARTING_PRICE_META_KEYS')) {
            $keys = array_merge($keys, (array) constant($productClass . '::LEGACY_STARTING_PRICE_META_KEYS'));
        }

        return $this->sanitizeKeys($keys);
    }

    /**
     * Generic key order for post types without a price definition.
     *
     * @return string[]
     */
    protected function deriveGenericKeys(string $postType, bool $starting): array
    {
        $specific = '_' . $postType . '_price';
        $keys = [];

        if ($starting) {
            $keys[] = '_' . $postType . '_starting_price';
        }

        $keys[] = '_jankx_price';
        $keys[] = '_jankx_regular_price';

        if ($specific !== '_price') {
            $keys[] = $specific;
        }

        $keys[] = '_price';

        return $keys;
    }
}
