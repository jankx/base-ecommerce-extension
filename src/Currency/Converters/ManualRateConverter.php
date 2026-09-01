<?php
namespace Jankx\Extensions\Ecommerce\Currency\Converters;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

/**
 * Manual exchange rate converter.
 *
 * Admin nhập tỷ giá thủ công vào wp_options.
 * Lý tưởng cho các site không muốn dùng API bên ngoài, muốn kiểm soát
 * tỷ giá hối đoái tùy ý.
 *
 * Dữ liệu lưu ở option `jankx_manual_exchange_rates`:
 *   [
 *     'USD' => 1.0,          // đồng base giá trị = 1
 *     'VND' => 25300.0,      // 1 USD = 25300 VND
 *     'EUR' => 0.92,
 *   ]
 *
 * @package Jankx\Extensions\Ecommerce\Currency\Converters
 */
class ManualRateConverter implements CurrencyConverterInterface
{
    const OPTION_RATES = 'jankx_manual_exchange_rates';
    const OPTION_BASE = 'jankx_manual_exchange_base';

    /**
     * Cache in-memory để tránh gọi get_option() nhiều lần.
     */
    private static ?array $cachedRates = null;

    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        if ($fromCode === $toCode) {
            return $amount;
        }

        $rates = $this->getRatesMap();
        $base = $this->getBaseCurrency();

        // Mọi rate đều tính theo base currency
        // Direct: from→base→to
        if (!isset($rates[$fromCode]) || !isset($rates[$toCode])) {
            return null;
        }

        // Rate của fromCode theo base: bao nhiêu base = 1 fromCode
        $rateFrom = (float) $rates[$fromCode]; // VD: rates[VND] = 25300 → 1 USD = 25300 VND → rateFrom = 25300
        $rateTo = (float) $rates[$toCode];

        if ($rateFrom <= 0) {
            return null;
        }

        // Quy về base rồi đổi sang toCode
        // amount_in_base = amount / rateFrom (nếu rates là: 1 base = X foreign)
        // Bởi vì rates['USD'] = 1, rates['VND'] = 25300 → format "1 base = N units"
        $amountInBase = $amount / $rateFrom;
        $amountInTarget = $amountInBase * $rateTo;

        return round($amountInTarget, 10);
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        if ($fromCode === $toCode) {
            return 1.0;
        }
        $rates = $this->getRatesMap();
        if (!isset($rates[$fromCode]) || !isset($rates[$toCode])) {
            return null;
        }
        $rateFrom = (float) $rates[$fromCode];
        $rateTo = (float) $rates[$toCode];
        if ($rateFrom <= 0) {
            return null;
        }
        return round($rateTo / $rateFrom, 8);
    }

    public function isReady(): bool
    {
        $rates = $this->getRatesMap();
        return count($rates) >= 2;
    }

    public function getName(): string
    {
        return __('Tỷ giá thủ công (Manual Exchange Rates)', 'jankx');
    }

    public function getDescription(): string
    {
        return __('Nhập tỷ giá hối đoái thủ công. Phù hợp với site kiểm soát giá cố định, không cần API bên ngoài.', 'jankx');
    }

    // ── Static helpers ────────────────────────────────────────────────

    public static function getRatesMap(): array
    {
        if (self::$cachedRates === null) {
            $raw = get_option(self::OPTION_RATES, []);
            self::$cachedRates = is_array($raw) ? $raw : [];
        }
        return self::$cachedRates;
    }

    public static function getBaseCurrency(): string
    {
        return (string) get_option(self::OPTION_BASE, \Jankx\Extensions\Ecommerce\Currency\CurrencyManager::getDefaultCurrency());
    }

    public static function saveRatesMap(array $rates): void
    {
        $clean = [];
        foreach ($rates as $code => $rate) {
            $code = strtoupper(sanitize_key($code));
            $rate = (float) $rate;
            if ($code && $rate > 0) {
                $clean[$code] = $rate;
            }
        }
        update_option(self::OPTION_RATES, $clean);
        self::$cachedRates = $clean;
    }

    public static function invalidateCache(): void
    {
        self::$cachedRates = null;
    }
}
