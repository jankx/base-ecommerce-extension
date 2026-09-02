# Currency Conversion System - Developer Guide

## Overview

The Jankx Ecommerce platform now includes a flexible currency conversion system that supports:
- Live exchange rates from public APIs (OpenExchangeRates, Fixer.io)
- Custom converters (e.g., payment gateway rates)
- Caching for performance
- Fallback to original price if conversion fails

## Quick Start

### Basic Usage

```php
<?php
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

// Format price with automatic conversion from default to current currency
echo CurrencyManager::formatPriceWithConversion(100.00); // 100 USD → displays in current user currency

// With explicit source currency
echo CurrencyManager::formatPriceWithConversion(100.00, 'USD', 'VND');

// Format without conversion (uses CurrencyManager settings)
echo CurrencyManager::formatPrice(100.00);
```

### Using in Blocks/Templates

```php
<?php
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

$price = get_post_meta($post_id, '_price', true);
$stored_currency = get_post_meta($post_id, '_currency', true) ?: CurrencyManager::getDefaultCurrency();

// Display with conversion to current user's currency
echo CurrencyManager::formatPriceWithConversion($price, $stored_currency);
```

### Using in REST API

```php
<?php
add_filter('rest_prepare_post', function($response, $post) {
    $price = get_post_meta($post->ID, '_price', true);
    
    $response->data['price_display'] = CurrencyManager::formatPriceWithConversion((float) $price);
    $response->data['price_raw'] = $price;
    $response->data['price_currency'] = CurrencyManager::getDefaultCurrency();
    
    return $response;
}, 10, 2);
```

## Converter Configuration

### Admin Settings

Converters are configured at: **Ecommerce Settings → Currency → Converter**

Available converters:
- **No Conversion** (Default) - No conversion, displays original price
- **OpenExchangeRates.io** - Live rates, free tier 1000 req/month
- **Fixer.io** - Live rates, free tier 100 req/month (EUR base only)

### Configuring OpenExchangeRates

1. Get free API key at https://openexchangerates.io/signup/free
2. Add to wp-config.php or via admin:
```php
OpenExchangeRatesConverter::setApiKey('your-api-key');
OpenExchangeRatesConverter::setBaseCurrency('USD'); // Default base currency
```

### Configuring Fixer.io

1. Get free API key at https://fixer.io/
2. Add to wp-config.php or via admin:
```php
FixerIOConverter::setApiKey('your-api-key');
FixerIOConverter::setBaseCurrency('EUR'); // Free tier limited to EUR
```

## Extending: Custom Converters

Create a custom converter by implementing `CurrencyConverterInterface`:

```php
<?php
namespace MyPlugin\Converters;

use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterInterface;

class MyPaymentGatewayConverter implements CurrencyConverterInterface
{
    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        // Implement your conversion logic
        // Return null if conversion not possible
        $rate = $this->getPaymentGatewayRate($fromCode, $toCode);
        return $rate !== null ? round($amount * $rate, 2) : null;
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        // Return exchange rate or null
    }

    public function isReady(): bool
    {
        // Check if converter is properly configured
        return !empty(get_option('my_gateway_api_key'));
    }

    public function getName(): string
    {
        return __('My Payment Gateway', 'my-domain');
    }

    public function getDescription(): string
    {
        return __('Uses exchange rates from My Payment Gateway API', 'my-domain');
    }
}
```

### Register Your Converter

```php
<?php
add_action('jankx/ecommerce/currency/register_converters', function($manager) {
    $manager->register('my_gateway', MyPaymentGatewayConverter::class);
});
```

## Hooks & Actions

### Filter: `jankx/ecommerce/currency/changed`
Fired when user changes their active currency.

```php
add_action('jankx/ecommerce/currency/changed', function($currency_code, $converter) {
    // Clear related caches, update UI, etc.
    error_log("User switched to: $currency_code");
}, 10, 2);
```

### Action: `jankx/ecommerce/currency/register_converters`
Hook point to register custom converters.

```php
add_action('jankx/ecommerce/currency/register_converters', function($manager) {
    // Register your converters here
});
```

### Filter: `jankx/ecommerce/price_format`
Modify price formatting (existing hook, now works with conversion).

```php
add_filter('jankx/ecommerce/price_format', function($formatted, $price, $currency_code, $currency_info) {
    // Customize formatting
    return $formatted;
}, 10, 4);
```

## Architecture

```
CurrencyManager (singleton)
├── Stores default & enabled currencies
├── Tracks current user currency
└── Uses CurrencyConverterManager for conversions

CurrencyConverterManager (singleton)
├── Manages converter instances
├── Handles active converter selection
├── Wraps converters with CacheDecoratorConverter
└── Provides fallback to original price on error

Converters (Strategy Pattern):
├── CurrencyConverterInterface (contract)
├── NoOpConverter (default)
├── OpenExchangeRatesConverter (API-based)
├── FixerIOConverter (API-based)
├── CacheDecoratorConverter (wrapper for performance)
└── Custom implementations (extensible)
```

## Performance Considerations

### Caching
- Exchange rates are cached for 24 hours by default
- Cached at WordPress cache layer (wp_cache_* functions)
- Automatic cache invalidation when converter is changed

### API Calls
- Each unique currency pair conversion makes 1 API call per cache miss
- Results are cached across all price formatting in a request
- Fallback to original price if API fails (no broken prices)

### Database Queries
- Minimal overhead - converter configuration stored in options table
- User currency preferences stored in user_meta (with caching)

## Migration from Old System

If your extension was using hardcode formatPrice():

**Before:**
```php
// Old hardcode
if ($currency === 'VND') {
    echo number_format($price, 0, '', '.') . '₫';
} else {
    echo '$' . number_format($price, 2, '.', ',');
}
```

**After:**
```php
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

echo CurrencyManager::formatPriceWithConversion($price, $currency);
```

## Troubleshooting

### Prices not converting
1. Check converter is enabled: Ecommerce → Currency → Converter
2. Check API key is correct
3. Check API rate limits haven't been exceeded
4. Check server can reach external APIs (firewall/proxy issues)

### API key validation
```php
$manager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
$converter = $manager->getActiveConverter();
if (!$converter->isReady()) {
    echo "Converter not ready: missing configuration";
}
```

## Testing

Use NoOpConverter in development:
```php
$manager = CurrencyConverterManager::getInstance();
$manager->setActiveConverter('noop');
```

Mock exchange rates for tests:
```php
class MockConverter implements CurrencyConverterInterface {
    private $rates = ['USD' => 1.0, 'VND' => 24000];
    
    public function getRate($from, $to) {
        return ($this->rates[$to] ?? 1) / ($this->rates[$from] ?? 1);
    }
    // ...
}
```

## Related Documentation

- [WordPress Currency Manager](./CurrencyManager.php)
- [Converter Interface](./Converters/CurrencyConverterInterface.php)
- [OpenExchangeRates.io API Docs](https://openexchangerates.io/documentation)
- [Fixer.io API Docs](https://fixer.io/documentation)
