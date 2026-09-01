<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

/**
 * No-operation converter - returns the original amount unchanged.
 * Useful as a default/fallback or for development/testing.
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class NoOpConverter implements CurrencyConverterInterface
{
    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        // Same currency: return as-is
        if ($fromCode === $toCode) {
            return $amount;
        }

        // Different currencies: return original without conversion
        // This prevents accidental loss of data if converter is misconfigured
        return $amount;
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        if ($fromCode === $toCode) {
            return 1.0;
        }

        // Not available in no-op converter
        return null;
    }

    public function isReady(): bool
    {
        // Always ready - no configuration needed
        return true;
    }

    public function getName(): string
    {
        return __('No Conversion (Default)', 'jankx');
    }

    public function getDescription(): string
    {
        return __('Prices are displayed in their original currency without conversion. Useful for single-currency sites or development.', 'jankx');
    }
}
