<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Currency\Converters\ManualRateConverter;

/**
 * Manages currency converters with singleton pattern.
 *
 * Provides:
 * - Converter selection and registration
 * - Configuration management
 * - Fallback to NoOp converter if primary fails
 * - Extensibility via hooks
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class CurrencyConverterManager
{
    private const OPTION_CONVERTER_TYPE = 'jankx_currency_converter_type';
    private const OPTION_CONVERTER_CONFIG = 'jankx_currency_converter_config';

    private static $instance;
    private $converter;
    private $converters = [];

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->registerDefaultConverters();
        $this->loadActiveConverter();
    }

    /**
     * Register the built-in converters.
     *
     * @return void
     */
    private function registerDefaultConverters(): void
    {
        $this->register('noop', NoOpConverter::class);
        $this->register('manual', ManualRateConverter::class);
        $this->register('openexchangerates', OpenExchangeRatesConverter::class);
        $this->register('fixerio', FixerIOConverter::class);
        $this->register('free', FreeExchangeRateConverter::class); // Public API, no key needed

        // Allow plugins/extensions to register their own converters
        do_action('jankx/ecommerce/currency/register_converters', $this);
    }

    /**
     * Load and initialize the active converter.
     *
     * @return void
     */
    private function loadActiveConverter(): void
    {
        $converterType = get_option(self::OPTION_CONVERTER_TYPE, 'noop');

        // Validate converter type exists
        if (!isset($this->converters[$converterType])) {
            $converterType = 'noop';
        }

        $converterClass = $this->converters[$converterType];
        $this->converter = new $converterClass();

        // Wrap with cache decorator if not already cached
        if (!($this->converter instanceof CacheDecoratorConverter)) {
            $this->converter = new CacheDecoratorConverter($this->converter, true);
        }
    }

    /**
     * Register a converter class.
     *
     * @param string $key      Unique identifier (e.g., 'openexchangerates')
     * @param string $class    Fully qualified class name implementing CurrencyConverterInterface
     * @return void
     */
    public function register(string $key, string $class): void
    {
        if (!class_exists($class)) {
            trigger_error(
                sprintf('Currency converter class not found: %s', $class),
                E_USER_WARNING
            );
            return;
        }

        $this->converters[$key] = $class;
    }

    /**
     * Set the active converter type.
     *
     * @param string $converterType Registered converter key
     * @return bool True if successful, false if converter not found
     */
    public function setActiveConverter(string $converterType): bool
    {
        if (!isset($this->converters[$converterType])) {
            return false;
        }

        update_option(self::OPTION_CONVERTER_TYPE, $converterType);
        $this->loadActiveConverter();

        do_action('jankx/ecommerce/currency/converter_changed', $converterType, $this->converter);

        return true;
    }

    /**
     * Set the active converter instance directly.
     *
     * Useful for complex setups like fallback decorators.
     * Automatically wraps with cache decorator if needed.
     *
     * @param CurrencyConverterInterface $converter
     * @return void
     */
    public function setActiveConverterInstance(CurrencyConverterInterface $converter): void
    {
        // Wrap with cache decorator if not already cached
        if (!($converter instanceof CacheDecoratorConverter)) {
            $converter = new CacheDecoratorConverter($converter, true);
        }

        $this->converter = $converter;

        do_action('jankx/ecommerce/currency/converter_changed', 'custom', $this->converter);
    }

    /**
     * Get the currently active converter.
     *
     * @return CurrencyConverterInterface
     */
    public function getActiveConverter(): CurrencyConverterInterface
    {
        return $this->converter;
    }

    /**
     * Get all registered converters metadata for admin UI.
     *
     * @return array<string, array> [key => ['class' => ..., 'name' => ..., 'description' => ...]]
     */
    public function getAvailableConverters(): array
    {
        $available = [];

        foreach ($this->converters as $key => $class) {
            $instance = new $class();
            $available[$key] = [
                'class' => $class,
                'name' => $instance->getName(),
                'description' => $instance->getDescription(),
                'is_ready' => $instance->isReady(),
            ];
        }

        return $available;
    }

    /**
     * Convert a price from one currency to another.
     *
     * Falls back to original amount if:
     * - Same currency
     * - Converter not ready
     * - Conversion fails
     *
     * @param float  $amount    Amount to convert
     * @param string $fromCode  Source currency code
     * @param string $toCode    Target currency code
     * @return float Converted amount, or original if conversion unavailable
     */
    public function convert(float $amount, string $fromCode, string $toCode): float
    {
        // No conversion needed
        if ($fromCode === $toCode) {
            return $amount;
        }

        // Active converter not ready
        if (!$this->converter->isReady()) {
            return $amount;
        }

        // Attempt conversion
        $result = $this->converter->convert($amount, $fromCode, $toCode);

        if ($result !== null) {
            return $result;
        }

        // Fallback: return original amount unchanged
        return $amount;
    }

    /**
     * Get exchange rate between two currencies.
     *
     * @param string $fromCode Source currency code
     * @param string $toCode   Target currency code
     * @return float|null Exchange rate, or null if unavailable
     */
    public function getRate(string $fromCode, string $toCode): ?float
    {
        if ($fromCode === $toCode) {
            return 1.0;
        }

        if (!$this->converter->isReady()) {
            return null;
        }

        return $this->converter->getRate($fromCode, $toCode);
    }

    /**
     * Format a price with automatic currency conversion.
     *
     * Intended use: stored prices are in base currency (e.g., USD),
     * display them in the current user currency with conversion.
     *
     * @param float  $price           Raw price in base/source currency
     * @param string $sourceCurrency  Currency the price is stored in
     * @param string $targetCurrency  Currency to display in (defaults to current)
     * @return string Formatted price with symbol
     */
    public function formatPriceWithConversion(
        float $price,
        string $sourceCurrency = '',
        ?string $targetCurrency = null
    ): string {
        // Use default/base currency if not specified
        if (empty($sourceCurrency)) {
            $sourceCurrency = CurrencyManager::getDefaultCurrency();
        }

        // Use current user currency if not specified
        if ($targetCurrency === null) {
            $targetCurrency = CurrencyManager::getCurrentCurrency();
        }

        // Convert the price
        $convertedPrice = $this->convert($price, $sourceCurrency, $targetCurrency);

        // Format using CurrencyManager
        return CurrencyManager::formatPrice($convertedPrice, $targetCurrency);
    }

    /**
     * Get configuration for the active converter (for admin UI).
     *
     * @return array
     */
    public function getActiveConverterConfig(): array
    {
        return (array) get_option(self::OPTION_CONVERTER_CONFIG, []);
    }

    /**
     * Update configuration for a converter.
     *
     * @param string $converterType Converter key
     * @param array  $config        Configuration array
     * @return bool
     */
    public function setConverterConfig(string $converterType, array $config): bool
    {
        if (!isset($this->converters[$converterType])) {
            return false;
        }

        $config = array_map('sanitize_text_field', $config);
        update_option(self::OPTION_CONVERTER_CONFIG, $config);

        // Reload if this is the active converter
        $activeType = get_option(self::OPTION_CONVERTER_TYPE, 'noop');
        if ($activeType === $converterType) {
            $this->loadActiveConverter();
        }

        return true;
    }

    /**
     * Reset to default converter (NoOp).
     *
     * @return void
     */
    public function resetToDefault(): void
    {
        $this->setActiveConverter('noop');
        delete_option(self::OPTION_CONVERTER_CONFIG);
    }
}
