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
    private const CACHE_TTL = 86400; // 24 hours

    private $converter;
    private $cacheEnabled;

    public function __construct(CurrencyConverterInterface $converter, bool $cacheEnabled = true)
    {
        $this->converter = $converter;
        $this->cacheEnabled = $cacheEnabled;
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
            wp_cache_set($cacheKey, $result, '', self::CACHE_TTL);
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
            wp_cache_set($cacheKey, $result, '', self::CACHE_TTL);
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
