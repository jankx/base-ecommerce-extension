<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

/**
 * Decorator that adds caching capability to any currency converter.
 *
 * Wraps another converter and caches conversion results to reduce
 * API calls and improve performance.
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class CacheDecoratorConverter implements CurrencyConverterInterface
{
    // Cache TTL in seconds
    // Exchange rates: 10 minutes (600s) - rates change frequently
    // Conversions: 1 hour (3600s) - same day prices usually stable
    private const RATE_CACHE_TTL = 600;      // 10 minutes
    private const CONVERSION_CACHE_TTL = 3600; // 1 hour

    private $converter;
    private $cacheEnabled;
    private $rateCacheTTL;
    private $conversionCacheTTL;

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
        $cached = wp_cache_get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $result = $this->converter->convert($amount, $fromCode, $toCode);

        if ($result !== null) {
            wp_cache_set($cacheKey, $result, '', $this->conversionCacheTTL);
        }

        return $result;
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        if (!$this->cacheEnabled) {
            return $this->converter->getRate($fromCode, $toCode);
        }

        $cacheKey = $this->getCacheKey('rate', $fromCode, $toCode);
        $cached = wp_cache_get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $result = $this->converter->getRate($fromCode, $toCode);

        if ($result !== null) {
            wp_cache_set($cacheKey, $result, '', $this->rateCacheTTL);
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
        wp_cache_flush();
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
