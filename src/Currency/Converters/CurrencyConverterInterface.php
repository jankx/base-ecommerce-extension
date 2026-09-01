<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

/**
 * Contract for currency converters.
 *
 * Implementations can convert prices between currencies using various strategies:
 * - Live API rates (OpenExchangeRates, Fixer.io)
 * - Payment gateway rates (OnePay, Stripe, etc.)
 * - Manual/fixed rates
 * - No conversion (for testing)
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
interface CurrencyConverterInterface
{
    /**
     * Convert a price from one currency to another.
     *
     * @param float  $amount    The amount to convert
     * @param string $fromCode  Source currency code (e.g., 'USD', 'VND')
     * @param string $toCode    Target currency code
     * @return float|null The converted amount, or null if conversion fails
     */
    public function convert(float $amount, string $fromCode, string $toCode): ?float;

    /**
     * Get the exchange rate between two currencies.
     *
     * @param string $fromCode Source currency code
     * @param string $toCode   Target currency code
     * @return float|null The exchange rate, or null if not available
     */
    public function getRate(string $fromCode, string $toCode): ?float;

    /**
     * Check if this converter is properly configured and ready to use.
     *
     * @return bool True if ready, false if missing required config
     */
    public function isReady(): bool;

    /**
     * Get human-readable name of this converter.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get a description of this converter (for admin UI).
     *
     * @return string
     */
    public function getDescription(): string;
}
