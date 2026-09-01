<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

use Jankx\Facades\Log;

/**
 * Free Exchange Rate Converter using exchangerate.host API
 *
 * Public API that requires NO API key.
 * Perfect as a fallback converter when paid APIs fail or aren't configured.
 *
 * Features:
 * - No authentication required
 * - ~150+ currencies supported
 * - Reliable, open-source backend
 * - Rate limit: ~1500 requests/hour per IP
 *
 * API: https://exchangerate.host
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class FreeExchangeRateConverter implements CurrencyConverterInterface
{
    private const API_BASE = 'https://open.er-api.com/v6/latest';
    private const API_LATEST = self::API_BASE;

    /**
     * Convert amount from one currency to another.
     *
     * @param float  $amount   Amount to convert
     * @param string $fromCode Source currency code (e.g., 'USD')
     * @param string $toCode   Target currency code (e.g., 'VND')
     * @return float|null Converted amount, or null if conversion fails
     */
    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        $rate = $this->getRate($fromCode, $toCode);

        if ($rate === null) {
            return null;
        }

        // Convert and round to 2 decimals
        return round($amount * $rate, 2);
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
        // Same currency, rate is 1
        if ($fromCode === $toCode) {
            return 1.0;
        }

        $response = $this->fetchRates($fromCode);

        if ($response === null) {
            return null;
        }

        return $response['rates'][$toCode] ?? null;
    }

    /**
     * Check if converter is ready (always true for free API).
     *
     * @return bool
     */
    public function isReady(): bool
    {
        return true; // No configuration needed
    }

    /**
     * Get converter name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Free Exchange Rate';
    }

    /**
     * Get converter description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Public exchange rates from exchangerate.host (no API key needed)';
    }

    /**
     * Fetch rates from API.
     *
     * @param string $baseCurrency Base currency code
     * @return array|null Response data with 'rates' key, or null on error
     */
    private function fetchRates(string $baseCurrency): ?array
    {
        $url = self::API_LATEST . '/' . rawurlencode($baseCurrency);

        Log::info('[FreeExchangeRateConverter] Fetching rates', ['url' => $url, 'base' => $baseCurrency]);

        $response = wp_remote_get($url, [
            'timeout' => 5,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            Log::error('[FreeExchangeRateConverter] WP_Error: ' . $response->get_error_message(), [
                'url' => $url,
            ]);
            return null;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            Log::error("[FreeExchangeRateConverter] HTTP $statusCode", ['url' => $url]);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['rates'])) {
            Log::error('[FreeExchangeRateConverter] Invalid response format', [
                'url' => $url,
                'body' => substr($body, 0, 500),
            ]);
            return null;
        }

        if (!empty($data['result']) && $data['result'] !== 'success') {
            Log::error('[FreeExchangeRateConverter] API result not success', [
                'result' => $data['result'],
                'error_type' => $data['error-type'] ?? 'unknown',
            ]);
            return null;
        }

        Log::info('[FreeExchangeRateConverter] Rates fetched OK', [
            'base' => $baseCurrency,
            'rates_count' => count($data['rates']),
        ]);

        return $data;
    }
}
