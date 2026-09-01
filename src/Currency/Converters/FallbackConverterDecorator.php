<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

/**
 * Fallback Converter Decorator
 *
 * Wraps a primary converter and provides a fallback converter.
 * If primary fails, automatically tries secondary.
 *
 * Example:
 * ```
 * $primary = new OpenExchangeRatesConverter();
 * $fallback = new FreeExchangeRateConverter();
 * $converter = new FallbackConverterDecorator($primary, $fallback);
 * // If OpenExchangeRates fails → uses FreeExchangeRate
 * ```
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class FallbackConverterDecorator implements CurrencyConverterInterface
{
    private $primary;
    private $fallback;
    private $primaryFailed = false;

    public function __construct(
        CurrencyConverterInterface $primary,
        CurrencyConverterInterface $fallback
    ) {
        $this->primary = $primary;
        $this->fallback = $fallback;
    }

    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        // Try primary first
        if (!$this->primaryFailed && $this->primary->isReady()) {
            $result = $this->primary->convert($amount, $fromCode, $toCode);

            if ($result !== null) {
                return $result;
            }

            // Mark primary as failed for this request
            $this->primaryFailed = true;
        }

        // Fallback to secondary
        if ($this->fallback->isReady()) {
            return $this->fallback->convert($amount, $fromCode, $toCode);
        }

        return null;
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        // Try primary first
        if (!$this->primaryFailed && $this->primary->isReady()) {
            $result = $this->primary->getRate($fromCode, $toCode);

            if ($result !== null) {
                return $result;
            }

            // Mark primary as failed for this request
            $this->primaryFailed = true;
        }

        // Fallback to secondary
        if ($this->fallback->isReady()) {
            return $this->fallback->getRate($fromCode, $toCode);
        }

        return null;
    }

    public function isReady(): bool
    {
        return $this->primary->isReady() || $this->fallback->isReady();
    }

    public function getName(): string
    {
        $primaryName = $this->primary->getName();
        $fallbackName = $this->fallback->getName();

        if ($this->primaryFailed) {
            return "$fallbackName (fallback)";
        }

        return "$primaryName (with $fallbackName fallback)";
    }

    public function getDescription(): string
    {
        $primaryDesc = $this->primary->getDescription();
        $fallbackDesc = $this->fallback->getDescription();

        return "$primaryDesc. If unavailable, falls back to: $fallbackDesc";
    }

    /**
     * Get the primary converter.
     *
     * @return CurrencyConverterInterface
     */
    public function getPrimaryConverter(): CurrencyConverterInterface
    {
        return $this->primary;
    }

    /**
     * Get the fallback converter.
     *
     * @return CurrencyConverterInterface
     */
    public function getFallbackConverter(): CurrencyConverterInterface
    {
        return $this->fallback;
    }

    /**
     * Check if primary converter has failed in this request.
     *
     * @return bool
     */
    public function isPrimaryFailed(): bool
    {
        return $this->primaryFailed;
    }
}
