<?php
/**
 * Example: Testing and Using the Currency Converter System
 * 
 * This file demonstrates various ways to use the currency conversion system.
 * For production, include the actual extension files.
 * 
 * @package Jankx\Extensions\Ecommerce\Currency\Examples
 */

namespace Jankx\Extensions\Ecommerce\Currency\Examples;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;

// ============================================================================
// EXAMPLE 1: Basic Price Display with Conversion
// ============================================================================

function example_display_price_with_conversion() {
    // Scenario: A tour is stored at 150 USD, user is viewing in VND

    $priceInUSD = 150.00;
    $userCurrency = 'VND'; // User's current currency

    // Default currency is USD (configured in Ecommerce settings)
    $displayPrice = CurrencyManager::formatPriceWithConversion(
        $priceInUSD,
        CurrencyManager::getDefaultCurrency(), // 'USD'
        $userCurrency // 'VND'
    );

    echo "Price for user: " . $displayPrice; // e.g., "3.600.000₫"
}

// ============================================================================
// EXAMPLE 2: REST API Response with Price Data
// ============================================================================

function example_rest_api_price_response($post_id) {
    // Get stored price (in base currency)
    $price = (float) get_post_meta($post_id, '_tour_price', true);
    $sourceCurrency = get_post_meta($post_id, '_tour_currency', true) 
        ?: CurrencyManager::getDefaultCurrency();

    // Build response data
    $response = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'price' => [
            'raw' => $price,
            'source_currency' => $sourceCurrency,
            'formatted_display' => CurrencyManager::formatPriceWithConversion($price, $sourceCurrency),
            'current_currency' => CurrencyManager::getCurrentCurrency(),
        ],
    ];

    return $response;
    // Example output:
    // {
    //   "price": {
    //     "raw": 150,
    //     "source_currency": "USD",
    //     "formatted_display": "3.600.000₫",
    //     "current_currency": "VND"
    //   }
    // }
}

// ============================================================================
// EXAMPLE 3: Admin Hook - When User Changes Currency
// ============================================================================

function example_on_currency_changed() {
    add_action('jankx/ecommerce/currency/changed', function($new_currency) {
        // Fired when user switches their active currency

        // Clear tour price cache
        delete_transient('tour_prices_' . get_current_user_id());

        // Log the change
        error_log("User switched to currency: $new_currency");

        // Update UI cache, clear frontend caches, etc.
    });
}

// ============================================================================
// EXAMPLE 4: Custom Converter for Payment Gateway
// ============================================================================

namespace PaymentGateway;

use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterInterface;

/**
 * Example: Converter using OnePay payment gateway API
 * 
 * This shows how to integrate a payment gateway's exchange rates
 * for more accurate pricing.
 */
class OnepayExchangeRateConverter implements CurrencyConverterInterface
{
    private const API_ENDPOINT = 'https://api.onepay.vn/v1/exchange-rates';
    private const CACHE_TTL = 3600; // 1 hour - payment rates update frequently
    private const API_KEY_OPTION = 'onepay_merchant_id';

    private $merchantId;
    private $apiKey;

    public function __construct()
    {
        $this->merchantId = get_option(self::API_KEY_OPTION);
        $this->apiKey = get_option('onepay_merchant_key');
    }

    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        if (!$this->isReady()) {
            return null;
        }

        if ($fromCode === $toCode) {
            return $amount;
        }

        $rate = $this->getRate($fromCode, $toCode);
        return $rate !== null ? round($amount * $rate, 2) : null;
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        if ($fromCode === $toCode) {
            return 1.0;
        }

        // Check cache first
        $cacheKey = "onepay_rate_{$fromCode}_{$toCode}";
        $cached = wp_cache_get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Call OnePay API
        $response = wp_remote_post(self::API_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'from' => $fromCode,
                'to' => $toCode,
                'amount' => 1,
            ]),
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            error_log('OnePay API error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $rate = (float) ($body['rate'] ?? 0);

        // Cache for 1 hour
        wp_cache_set($cacheKey, $rate, '', self::CACHE_TTL);

        return $rate > 0 ? $rate : null;
    }

    public function isReady(): bool
    {
        return !empty($this->merchantId) && !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'OnePay Exchange Rates';
    }

    public function getDescription(): string
    {
        return 'Uses official exchange rates from OnePay payment gateway API';
    }
}

// Register the custom converter
namespace WordPress;

add_action('jankx/ecommerce/currency/register_converters', function($manager) {
    $manager->register('onepay', '\PaymentGateway\OnepayExchangeRateConverter');
});

// ============================================================================
// EXAMPLE 5: Checking Converter Status in Admin
// ============================================================================

function example_admin_converter_status() {
    $manager = CurrencyConverterManager::getInstance();
    $available = $manager->getAvailableConverters();

    echo '<table>';
    foreach ($available as $key => $info) {
        printf(
            '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
            esc_html($info['name']),
            $info['is_ready'] ? '✓ Ready' : '✗ Not Ready',
            esc_html($info['description'])
        );
    }
    echo '</table>';
}

// ============================================================================
// EXAMPLE 6: Block Implementation with Price Display
// ============================================================================

namespace TourBlock;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

class TourCardBlock
{
    public function render($block, $content, $attributes)
    {
        $tourId = $attributes['tourId'] ?? 0;
        if (!$tourId) {
            return '';
        }

        $price = (float) get_post_meta($tourId, '_tour_price', true);
        $currency = get_post_meta($tourId, '_tour_currency', true) 
            ?: CurrencyManager::getDefaultCurrency();

        $formattedPrice = CurrencyManager::formatPriceWithConversion(
            $price,
            $currency,
            CurrencyManager::getCurrentCurrency()
        );

        return sprintf(
            '<div class="tour-card"><h3>%s</h3><p class="price">%s</p></div>',
            esc_html(get_the_title($tourId)),
            $formattedPrice
        );
    }
}

// ============================================================================
// EXAMPLE 7: Caching and Performance
// ============================================================================

function example_performance_optimization() {
    $manager = CurrencyConverterManager::getInstance();

    // All converters are automatically wrapped with cache
    $converter = $manager->getActiveConverter();

    // Same conversion called twice = only 1 API call (cached)
    $price1 = $manager->convert(100, 'USD', 'VND');
    $price2 = $manager->convert(100, 'USD', 'VND'); // From cache

    // Manual cache clear if needed
    wp_cache_flush();

    // Or clear specific converter cache
    wp_cache_delete_group('jankx_oer_rates_'); // OpenExchangeRates cache
}

// ============================================================================
// EXAMPLE 8: Using Helper Functions
// ============================================================================

function example_helper_functions() {
    // These global helper functions are available:

    // Format price with auto-conversion
    echo jankx_format_price_with_conversion(150);

    // Convert raw value
    $converted = jankx_convert_price(150, 'USD', 'VND');

    // Get converter manager
    $manager = jankx_get_converter_manager();

    // Get active converter
    $converter = jankx_get_currency_converter();

    // Check if converter ready
    if (jankx_is_converter_ready('openexchangerates')) {
        // Safe to use conversion
    }

    // Get active converter info
    $info = jankx_get_active_converter_info();
    echo "Active: " . $info['name'];

    // Prepare data for API response
    $apiData = jankx_prepare_price_for_api(150);
    // Returns: ['raw' => 150, 'converted' => 3600000, 'formatted' => '3.600.000₫', ...]
}

// ============================================================================
// EXAMPLE 9: Testing Converters
// ============================================================================

namespace Testing;

use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterInterface;

class MockConverter implements CurrencyConverterInterface
{
    private $rates = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'VND' => 24000,
        'GBP' => 0.79,
    ];

    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        $rate = $this->getRate($fromCode, $toCode);
        return $rate !== null ? round($amount * $rate, 2) : null;
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        if ($fromCode === $toCode) {
            return 1.0;
        }

        $fromRate = $this->rates[$fromCode] ?? null;
        $toRate = $this->rates[$toCode] ?? null;

        return ($fromRate && $toRate) ? $toRate / $fromRate : null;
    }

    public function isReady(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Mock Converter (Testing)';
    }

    public function getDescription(): string
    {
        return 'Fixed rates for testing - no API calls';
    }
}

// Use in tests
function setup_test_converter() {
    add_action('jankx/ecommerce/currency/register_converters', function($manager) {
        $manager->register('mock', MockConverter::class);
    });

    // Switch to mock converter
    \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance()
        ->setActiveConverter('mock');
}

// ============================================================================
// EXAMPLE 10: Advanced - Multi-Currency Product Pricing
// ============================================================================

function example_multi_currency_product_pricing($product_id) {
    // Scenario: Product has prices in different currencies stored
    // Use the converter to unify display

    $prices = [
        'USD' => (float) get_post_meta($product_id, '_price_usd', true),
        'EUR' => (float) get_post_meta($product_id, '_price_eur', true),
        'VND' => (float) get_post_meta($product_id, '_price_vnd', true),
    ];

    $userCurrency = CurrencyManager::getCurrentCurrency();
    $price = $prices[$userCurrency] ?? $prices['USD'];

    // Display
    echo CurrencyManager::formatPriceWithConversion($price, $userCurrency);
}

// ============================================================================
// EXAMPLE 11: Migration from Old System
// ============================================================================

namespace Migration;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

/**
 * Migrate old hardcoded formatPrice() calls to new system
 * 
 * OLD:
 * if ($currency === 'VND') {
 *     echo number_format($price, 0, '', '.') . '₫';
 * } else {
 *     echo '$' . number_format($price, 2, '.', ',');
 * }
 * 
 * NEW (single line):
 */
function old_format_price($price, $currency = 'VND') {
    // Replace this entire function with:
    return CurrencyManager::formatPrice($price, $currency);
}

/**
 * Or, if you want auto-conversion to user's currency:
 */
function new_format_price_with_conversion($price, $stored_currency = '') {
    return CurrencyManager::formatPriceWithConversion($price, $stored_currency);
}

// ============================================================================

// Example usage of all the above:

// Basic display
// echo example_display_price_with_conversion();

// API response
// $response = example_rest_api_price_response(123);

// Check status
// example_admin_converter_status();

// Use helpers
// example_helper_functions();

// Setup testing
// setup_test_converter();

// Real-world use case
// example_multi_currency_product_pricing(123);
