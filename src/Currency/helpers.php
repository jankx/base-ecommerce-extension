<?php
/**
 * Currency Conversion System Helper Functions
 * 
 * Place this in a file and include it, or use as reference for integrating
 * currency conversion into your extension.
 * 
 * @package Jankx\Extensions\Ecommerce\Currency
 */

namespace Jankx\Extensions\Ecommerce\Currency;

use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;

/**
 * Get a formatted price with automatic currency conversion.
 * 
 * This is the recommended way to display prices in your templates.
 * 
 * @param float  $price           The price in base/default currency
 * @param string $sourceCurrency  Currency the price is stored in (default: base currency)
 * @param string $targetCurrency  Currency to display in (default: current user's currency)
 * @return string Formatted price with currency symbol
 */
function jankx_format_price_with_conversion(
    float $price,
    ?string $sourceCurrency = null,
    ?string $targetCurrency = null
): string {
    return CurrencyManager::formatPriceWithConversion($price, $sourceCurrency, $targetCurrency);
}

/**
 * Convert a raw price from one currency to another.
 * 
 * Returns the numerical value without formatting or symbol.
 * 
 * @param float  $price    The amount to convert
 * @param string $fromCode Source currency code (e.g., 'USD')
 * @param string $toCode   Target currency code (e.g., 'VND')
 * @return float Converted amount, or original if conversion fails
 */
function jankx_convert_price(float $price, string $fromCode, string $toCode): float
{
    return CurrencyManager::convertPrice($price, $fromCode, $toCode);
}

/**
 * Get the current converter manager instance.
 * 
 * Advanced: Use this if you need low-level converter access.
 * 
 * @return CurrencyConverterManager
 */
function jankx_get_converter_manager(): CurrencyConverterManager
{
    return CurrencyConverterManager::getInstance();
}

/**
 * Get the active currency converter.
 * 
 * Advanced: Use if you need to check converter state or get exchange rates.
 * 
 * @return \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterInterface
 */
function jankx_get_currency_converter()
{
    return CurrencyConverterManager::getInstance()->getActiveConverter();
}

/**
 * Get available converters for admin selection.
 * 
 * @return array List of [key => ['name', 'description', 'is_ready']]
 */
function jankx_get_available_converters(): array
{
    return CurrencyConverterManager::getInstance()->getAvailableConverters();
}

/**
 * Check if a specific converter is ready to use.
 * 
 * @param string $converterKey The converter identifier (e.g., 'openexchangerates')
 * @return bool True if ready, false if missing config
 */
function jankx_is_converter_ready(string $converterKey): bool
{
    $available = jankx_get_available_converters();
    return !empty($available[$converterKey]['is_ready']);
}

/**
 * Set the active converter.
 * 
 * Admin settings UI should use this when user selects a converter.
 * 
 * @param string $converterKey Registered converter key
 * @return bool Success
 */
function jankx_set_active_converter(string $converterKey): bool
{
    return CurrencyConverterManager::getInstance()->setActiveConverter($converterKey);
}

/**
 * Get the current active converter key and info.
 * 
 * @return array ['key' => '...', 'name' => '...', 'description' => '...', 'is_ready' => bool]
 */
function jankx_get_active_converter_info(): array
{
    $manager = CurrencyConverterManager::getInstance();
    $converter = $manager->getActiveConverter();
    $available = $manager->getAvailableConverters();
    
    // Find the key of the current active converter
    foreach ($available as $key => $info) {
        if (get_class($info['class']) === get_class($converter)) {
            return array_merge(['key' => $key], $info);
        }
    }
    
    // Fallback to noop
    return array_merge(['key' => 'noop'], $available['noop'] ?? []);
}

/**
 * Format a price array for REST API responses.
 * 
 * Useful for returning consistent price data structure from API endpoints.
 * 
 * @param float  $price           The raw price
 * @param string $sourceCurrency  Currency the price is stored in
 * @return array ['raw' => float, 'formatted' => string, 'currency' => string, ...]
 */
function jankx_prepare_price_for_api(float $price, string $sourceCurrency = ''): array
{
    if (empty($sourceCurrency)) {
        $sourceCurrency = CurrencyManager::getDefaultCurrency();
    }

    $currentCurrency = CurrencyManager::getCurrentCurrency();
    $converted = CurrencyManager::convertPrice($price, $sourceCurrency, $currentCurrency);
    $formatted = CurrencyManager::formatPrice($converted, $currentCurrency);

    return [
        'raw' => $price,
        'converted' => $converted,
        'formatted' => $formatted,
        'source_currency' => $sourceCurrency,
        'target_currency' => $currentCurrency,
        'rate' => $converted > 0 ? round($converted / $price, 4) : 0,
    ];
}

/**
 * Hook: Initialize converter when needed.
 * 
 * Call this in your extension's setup if you need to ensure
 * the converter system is loaded and ready.
 */
function jankx_ensure_converter_loaded(): void
{
    // Simply accessing getInstance initializes everything
    CurrencyConverterManager::getInstance();
}

/**
 * Example: Update a tour's price field.
 * 
 * Shows recommended pattern for setting prices.
 * Prices are always stored in default currency.
 * 
 * @param int   $postId
 * @param float $price    Price amount
 * @return void
 */
function jankx_set_tour_price(int $postId, float $price): void
{
    $currency = CurrencyManager::getDefaultCurrency();
    
    update_post_meta($postId, '_tour_price', $price);
    update_post_meta($postId, '_tour_price_currency', $currency);
    
    // Useful for searching/filtering
    update_post_meta($postId, '_tour_price_usd', $price); // Always USD
}

/**
 * Example: Get a tour's price for display.
 * 
 * Shows recommended pattern for retrieving and formatting prices.
 * 
 * @param int $postId
 * @return string Formatted price with conversion
 */
function jankx_get_tour_price_display(int $postId): string
{
    $price = (float) get_post_meta($postId, '_tour_price', true);
    $currency = get_post_meta($postId, '_tour_price_currency', true) 
        ?: CurrencyManager::getDefaultCurrency();
    
    return jankx_format_price_with_conversion($price, $currency);
}

/**
 * Example: Get a tour's price for calculations.
 * 
 * Returns price in target currency for calculations.
 * 
 * @param int   $postId
 * @param string $targetCurrency  Currency code (default: user's current)
 * @return float
 */
function jankx_get_tour_price_value(int $postId, ?string $targetCurrency = null): float
{
    if ($targetCurrency === null) {
        $targetCurrency = CurrencyManager::getCurrentCurrency();
    }

    $price = (float) get_post_meta($postId, '_tour_price', true);
    $currency = get_post_meta($postId, '_tour_price_currency', true) 
        ?: CurrencyManager::getDefaultCurrency();
    
    return CurrencyManager::convertPrice($price, $currency, $targetCurrency);
}

/**
 * Admin notice for converter status.
 * 
 * Display this in your admin page if converter is not ready.
 * 
 * @return string HTML notice
 */
function jankx_admin_converter_notice(): string
{
    $info = jankx_get_active_converter_info();
    
    if (!$info['is_ready']) {
        return sprintf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            sprintf(
                __('Currency converter "%s" is not properly configured. Prices will not be converted. <a href="%s">Configure now</a>', 'jankx'),
                $info['name'],
                admin_url('admin.php?page=jankx-ecommerce-settings&tab=currency')
            )
        );
    }
    
    return '';
}
