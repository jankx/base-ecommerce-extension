<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

/**
 * Currency converter using OpenExchangeRates.io API.
 *
 * Free tier: 1,000 requests/month, base currency USD only
 * Paid tier: Higher limits, multiple base currencies
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 * @link https://openexchangerates.io/
 */
class OpenExchangeRatesConverter implements CurrencyConverterInterface
{
    private const API_URL = 'https://openexchangerates.io/api/latest.json';
    private const CACHE_TTL = 86400; // 24 hours
    private const OPTION_API_KEY = 'jankx_openexchangerates_api_key';
    private const OPTION_BASE_CURRENCY = 'jankx_openexchangerates_base_currency';

    private $apiKey;
    private $baseCurrency;
    private $rateCache = [];
    private $fetchError = null;

    public function __construct(?string $apiKey = null, ?string $baseCurrency = null)
    {
        $this->apiKey = $apiKey ?? get_option(self::OPTION_API_KEY);
        $this->baseCurrency = $baseCurrency ?? get_option(self::OPTION_BASE_CURRENCY, 'USD');
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
        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        if ($fromCode === $toCode) {
            return 1.0;
        }

        // Check cache first
        $cacheKey = "{$fromCode}_{$toCode}";
        if (isset($this->rateCache[$cacheKey])) {
            return $this->rateCache[$cacheKey];
        }

        // Fetch all rates in one API call
        $rates = $this->fetchRates($fromCode);
        if ($rates === null) {
            return null;
        }

        // Cache the rate
        $rate = (float) ($rates[$toCode] ?? null);
        if ($rate !== null) {
            $this->rateCache[$cacheKey] = $rate;
        }

        return $rate ?? null;
    }

    public function isReady(): bool
    {
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return __('OpenExchangeRates.io', 'jankx');
    }

    public function getDescription(): string
    {
        return __('Live exchange rates from OpenExchangeRates.io API. Requires API key (free tier available).', 'jankx');
    }

    /**
     * Fetch exchange rates from the API, with caching.
     *
     * @param string $baseCurrency Base currency for the rates
     * @return array|null Array of currency codes to rates, or null on error
     */
    private function fetchRates(string $baseCurrency): ?array
    {
        $cacheKey = 'jankx_oer_rates_' . $baseCurrency;
        $cached = wp_cache_get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 10,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'app_id' => $this->apiKey,
                'base' => $baseCurrency,
            ],
        ]);

        if (is_wp_error($response)) {
            $this->fetchError = $response->get_error_message();
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            $this->fetchError = sprintf(
                __('OpenExchangeRates API error: HTTP %d', 'jankx'),
                $statusCode
            );
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['rates'])) {
            $this->fetchError = __('Invalid response from OpenExchangeRates API', 'jankx');
            return null;
        }

        $rates = (array) $data['rates'];

        // Cache for 24 hours
        wp_cache_set($cacheKey, $rates, '', self::CACHE_TTL);

        return $rates;
    }

    /**
     * Set the API key for this converter.
     *
     * @param string $apiKey
     * @return void
     */
    public static function setApiKey(string $apiKey): void
    {
        update_option(self::OPTION_API_KEY, sanitize_text_field($apiKey));
        wp_cache_delete_group('jankx_oer_rates_');
    }

    /**
     * Set the base currency for this converter.
     *
     * @param string $currency Currency code (e.g., 'USD', 'EUR')
     * @return void
     */
    public static function setBaseCurrency(string $currency): void
    {
        update_option(self::OPTION_BASE_CURRENCY, strtoupper(sanitize_text_field($currency)));
        wp_cache_delete_group('jankx_oer_rates_');
    }

    /**
     * Get the last fetch error (for admin UI debugging).
     *
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->fetchError;
    }
}
