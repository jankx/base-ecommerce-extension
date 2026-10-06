<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

/**
 * Decorator that adds caching capability to any currency converter.
 *
 * Wraps another converter and caches conversion results to reduce
 * API calls and improve performance.
 *
 * Cache strategy (two layers):
 *
 *  L1 – in-process PHP array ($rateCache / $convertCache).
 *     Survives even when Redis is down, prevents duplicate API calls
 *     within the same HTTP request.
 *
 *  L2 – WordPress Object Cache group "jankx_currency".
 *     The group is registered as NON-PERSISTENT via
 *     wp_cache_add_non_persistent_groups() so Redis/Memcached never
 *     receives these keys. This prevents the "Server has gone away"
 *     MySQL error that was triggered when Redis threw an exception
 *     during a long-running cart/checkout request.
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class CacheDecoratorConverter implements CurrencyConverterInterface
{
    /**
     * WP Object Cache group used for all currency cache entries.
     * Registered as non-persistent so Redis/Memcached never store it.
     */
    const CACHE_GROUP = 'jankx_currency';

    // Cache TTL in seconds
    // Exchange rates: 10 minutes (600s) - rates change frequently
    // Conversions: 1 hour (3600s) - same day prices usually stable
    private const RATE_CACHE_TTL = 600;      // 10 minutes
    private const CONVERSION_CACHE_TTL = 3600; // 1 hour

    private $converter;
    private $cacheEnabled;
    private $rateCacheTTL;
    private $conversionCacheTTL;

    /** @var array<string, float> L1 in-process rate cache */
    private $rateCache = [];

    /** @var array<string, float> L1 in-process conversion cache */
    private $convertCache = [];

    public function __construct(CurrencyConverterInterface $converter, bool $cacheEnabled = true)
    {
        $this->converter = $converter;
        $this->cacheEnabled = $cacheEnabled;
        $this->rateCacheTTL = self::RATE_CACHE_TTL;
        $this->conversionCacheTTL = self::CONVERSION_CACHE_TTL;
    }

    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        if (!$this->cacheEnabled) {
            return $this->converter->convert($amount, $fromCode, $toCode);
        }

        $cacheKey = $this->getCacheKey('convert', $amount, $fromCode, $toCode);

        // L1: in-process array
        if (isset($this->convertCache[$cacheKey])) {
            return $this->convertCache[$cacheKey];
        }

        // L2: WP Object Cache (non-persistent group, Redis-safe)
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== false && $cached !== null) {
            $this->convertCache[$cacheKey] = (float) $cached;
            return (float) $cached;
        }

        $result = $this->converter->convert($amount, $fromCode, $toCode);

        if ($result !== null) {
            $this->convertCache[$cacheKey] = $result;
            $this->cacheSet($cacheKey, $result, $this->conversionCacheTTL);
        }

        return $result;
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        if (!$this->cacheEnabled) {
            return $this->converter->getRate($fromCode, $toCode);
        }

        $cacheKey = $this->getCacheKey('rate', $fromCode, $toCode);

        // L1: in-process array
        if (isset($this->rateCache[$cacheKey])) {
            return $this->rateCache[$cacheKey];
        }

        // L2: WP Object Cache (non-persistent group, Redis-safe)
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== false && $cached !== null) {
            $this->rateCache[$cacheKey] = (float) $cached;
            return (float) $cached;
        }

        $result = $this->converter->getRate($fromCode, $toCode);

        if ($result !== null) {
            $this->rateCache[$cacheKey] = $result;
            $this->cacheSet($cacheKey, $result, $this->rateCacheTTL);
        }

        return $result;
    }

    public function isReady(): bool
    {
        return $this->converter->isReady();
    }

    public function getName(): string
    {
        return $this->converter->getName();
    }

    public function getDescription(): string
    {
        return $this->converter->getDescription();
    }

    /**
     * Get the underlying converter.
     *
     * @return CurrencyConverterInterface
     */
    public function getInnerConverter(): CurrencyConverterInterface
    {
        return $this->converter;
    }

    /**
     * Enable or disable caching.
     *
     * @param bool $enabled
     * @return void
     */
    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Clear all cached conversion results.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->rateCache    = [];
        $this->convertCache = [];
        try {
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group(self::CACHE_GROUP);
            }
        } catch (\Throwable $e) {
            // Redis unavailable – in-process caches already cleared above.
        }
    }

    /**
     * Set custom TTL for rate cache.
     *
     * @param int $ttl TTL in seconds
     * @return void
     */
    public function setRateCacheTTL(int $ttl): void
    {
        $this->rateCacheTTL = max(1, $ttl); // Minimum 1 second
    }

    /**
     * Set custom TTL for conversion result cache.
     *
     * @param int $ttl TTL in seconds
     * @return void
     */
    public function setConversionCacheTTL(int $ttl): void
    {
        $this->conversionCacheTTL = max(1, $ttl); // Minimum 1 second
    }

    /**
     * Get current rate cache TTL.
     *
     * @return int TTL in seconds
     */
    public function getRateCacheTTL(): int
    {
        return $this->rateCacheTTL;
    }

    /**
     * Get current conversion cache TTL.
     *
     * @return int TTL in seconds
     */
    public function getConversionCacheTTL(): int
    {
        return $this->conversionCacheTTL;
    }

    // ── Internal cache helpers (Redis-safe) ─────────────────────────────────

    /**
     * wp_cache_get wrapper that silences Redis exceptions.
     *
     * @return mixed The cached value, or false on miss/error.
     */
    private function cacheGet(string $key)
    {
        try {
            return wp_cache_get($key, self::CACHE_GROUP);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * wp_cache_set wrapper that silences Redis exceptions.
     *
     * @param mixed $value
     */
    private function cacheSet(string $key, $value, int $ttl): void
    {
        try {
            wp_cache_set($key, $value, self::CACHE_GROUP, $ttl);
        } catch (\Throwable $e) {
            // Redis unavailable – L1 in-process cache is still populated.
        }
    }

    /**
     * Generate a cache key for a conversion or rate lookup.
     *
     * @param string $operation 'convert' or 'rate'
     * @return string
     */
    private function getCacheKey(string $operation, ...$args): string
    {
        return 'jankx_currency_' . $operation . '_' . md5(implode('_', $args));
    }
}
