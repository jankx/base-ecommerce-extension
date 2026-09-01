<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

/**
 * Auto-configures the best available converter based on environment.
 *
 * Priority:
 * 1. Check for API keys in environment/constants
 * 2. Load and configure the converter
 * 3. Fallback to NoOp if none available
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class AutoConfigConverter
{
    /**
     * Auto-detect and configure the best converter.
     *
     * Checks environment variables and WordPress constants:
     * - OPENEXCHANGERATES_API_KEY or JANKX_OPENEXCHANGERATES_API_KEY
     * - FIXERIO_API_KEY or JANKX_FIXERIO_API_KEY
     *
     * @return void
     */
    public static function autoDetectAndConfigure(): void
    {
        $manager = CurrencyConverterManager::getInstance();

        // Check for OpenExchangeRates API key
        $apiKey = self::getOpenExchangeRatesApiKey();
        if ($apiKey) {
            OpenExchangeRatesConverter::setApiKey($apiKey);
            $manager->setActiveConverter('openexchangerates');
            return;
        }

        // Check for Fixer.io API key
        $apiKey = self::getFixerIOApiKey();
        if ($apiKey) {
            FixerIOConverter::setApiKey($apiKey);
            $manager->setActiveConverter('fixerio');
            return;
        }

        // No API key found, stay with NoOp
    }

    /**
     * Get OpenExchangeRates API key from environment or constant.
     *
     * Checks in order:
     * - JANKX_OPENEXCHANGERATES_API_KEY constant
     * - OPENEXCHANGERATES_API_KEY constant
     * - jankx_openexchangerates_api_key environment variable
     * - openexchangerates_api_key environment variable
     *
     * @return string|null API key if found
     */
    public static function getOpenExchangeRatesApiKey(): ?string
    {
        // Check constants first
        if (defined('JANKX_OPENEXCHANGERATES_API_KEY') && JANKX_OPENEXCHANGERATES_API_KEY) {
            return (string) JANKX_OPENEXCHANGERATES_API_KEY;
        }

        if (defined('OPENEXCHANGERATES_API_KEY') && OPENEXCHANGERATES_API_KEY) {
            return (string) OPENEXCHANGERATES_API_KEY;
        }

        // Check environment variables
        $apiKey = getenv('JANKX_OPENEXCHANGERATES_API_KEY');
        if ($apiKey) {
            return (string) $apiKey;
        }

        $apiKey = getenv('OPENEXCHANGERATES_API_KEY');
        if ($apiKey) {
            return (string) $apiKey;
        }

        return null;
    }

    /**
     * Get Fixer.io API key from environment or constant.
     *
     * Checks in order:
     * - JANKX_FIXERIO_API_KEY constant
     * - FIXERIO_API_KEY constant
     * - jankx_fixerio_api_key environment variable
     * - fixerio_api_key environment variable
     *
     * @return string|null API key if found
     */
    public static function getFixerIOApiKey(): ?string
    {
        // Check constants first
        if (defined('JANKX_FIXERIO_API_KEY') && JANKX_FIXERIO_API_KEY) {
            return (string) JANKX_FIXERIO_API_KEY;
        }

        if (defined('FIXERIO_API_KEY') && FIXERIO_API_KEY) {
            return (string) FIXERIO_API_KEY;
        }

        // Check environment variables
        $apiKey = getenv('JANKX_FIXERIO_API_KEY');
        if ($apiKey) {
            return (string) $apiKey;
        }

        $apiKey = getenv('FIXERIO_API_KEY');
        if ($apiKey) {
            return (string) $apiKey;
        }

        return null;
    }
}
