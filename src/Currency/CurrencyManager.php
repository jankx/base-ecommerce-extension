<?php
namespace Jankx\Extensions\Ecommerce\Currency;

class CurrencyManager
{
    const OPTION_ENABLED_CURRENCIES = 'jankx_enabled_currencies';
    const OPTION_DEFAULT_CURRENCY = 'jankx_default_currency';
    const OPTION_CURRENCY_POSITION = 'jankx_currency_position';
    const OPTION_THOUSAND_SEP = 'jankx_currency_thousand_sep';
    const OPTION_DECIMAL_SEP = 'jankx_currency_decimal_sep';
    const OPTION_DECIMALS = 'jankx_currency_decimals';
    const SESSION_KEY = 'jankx_current_currency';

    protected static $instance;

    protected static $allCurrencies = [
        'USD' => ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'flag' => '🇺🇸', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'VND' => ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'flag' => '🇻🇳', 'decimals' => 0, 'thousand_sep' => '.', 'decimal_sep' => ','],
        'EUR' => ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'flag' => '🇪🇺', 'decimals' => 2, 'thousand_sep' => '.', 'decimal_sep' => ','],
        'GBP' => ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'flag' => '🇬🇧', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'JPY' => ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'flag' => '🇯🇵', 'decimals' => 0, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'CNY' => ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'flag' => '🇨🇳', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'KRW' => ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩', 'flag' => '🇰🇷', 'decimals' => 0, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'THB' => ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿', 'flag' => '🇹🇭', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'SGD' => ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'flag' => '🇸🇬', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'AUD' => ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'flag' => '🇦🇺', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'CAD' => ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'flag' => '🇨🇦', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'CHF' => ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'flag' => '🇨🇭', 'decimals' => 2, 'thousand_sep' => "'", 'decimal_sep' => '.'],
        'HKD' => ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'flag' => '🇭🇰', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'TWD' => ['code' => 'TWD', 'name' => 'Taiwan Dollar', 'symbol' => 'NT$', 'flag' => '🇹🇼', 'decimals' => 0, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'MYR' => ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'flag' => '🇲🇾', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'IDR' => ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'flag' => '🇮🇩', 'decimals' => 0, 'thousand_sep' => '.', 'decimal_sep' => ','],
        'PHP' => ['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => '₱', 'flag' => '🇵🇭', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'INR' => ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'flag' => '🇮🇳', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'RUB' => ['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽', 'flag' => '🇷🇺', 'decimals' => 2, 'thousand_sep' => ' ', 'decimal_sep' => ','],
        'BRL' => ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'flag' => '🇧🇷', 'decimals' => 2, 'thousand_sep' => '.', 'decimal_sep' => ','],
        'AED' => ['code' => 'AED', 'name' => ' UAE Dirham', 'symbol' => 'د.إ', 'flag' => '🇦🇪', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'SAR' => ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'flag' => '🇸🇦', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'TRY' => ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'flag' => '🇹🇷', 'decimals' => 2, 'thousand_sep' => '.', 'decimal_sep' => ','],
        'NZD' => ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'flag' => '🇳🇿', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'SEK' => ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'flag' => '🇸🇪', 'decimals' => 2, 'thousand_sep' => ' ', 'decimal_sep' => ','],
        'NOK' => ['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr', 'flag' => '🇳🇴', 'decimals' => 2, 'thousand_sep' => ' ', 'decimal_sep' => ','],
        'DKK' => ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr', 'flag' => '🇩🇰', 'decimals' => 2, 'thousand_sep' => '.', 'decimal_sep' => ','],
        'PLN' => ['code' => 'PLN', 'name' => 'Polish Zloty', 'symbol' => 'zł', 'flag' => '🇵🇱', 'decimals' => 2, 'thousand_sep' => ' ', 'decimal_sep' => ','],
        'CZK' => ['code' => 'CZK', 'name' => 'Czech Koruna', 'symbol' => 'Kč', 'flag' => '🇨🇿', 'decimals' => 2, 'thousand_sep' => ' ', 'decimal_sep' => ','],
        'HUF' => ['code' => 'HUF', 'name' => 'Hungarian Forint', 'symbol' => 'Ft', 'flag' => '🇭🇺', 'decimals' => 0, 'thousand_sep' => ' ', 'decimal_sep' => ','],
        'ZAR' => ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'flag' => '🇿🇦', 'decimals' => 2, 'thousand_sep' => ' ', 'decimal_sep' => '.'],
        'MXN' => ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => '$', 'flag' => '🇲🇽', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'NGN' => ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'flag' => '🇳🇬', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'EGP' => ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'E£', 'flag' => '🇪🇬', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'PKR' => ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'flag' => '🇵🇰', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'BDT' => ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'flag' => '🇧🇩', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'LKR' => ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'symbol' => 'Rs', 'flag' => '🇱🇰', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'KHR' => ['code' => 'KHR', 'name' => 'Cambodian Riel', 'symbol' => '៛', 'flag' => '🇰🇭', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'MMK' => ['code' => 'MMK', 'name' => 'Myanmar Kyat', 'symbol' => 'K', 'flag' => '🇲🇲', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
        'NPR' => ['code' => 'NPR', 'name' => 'Nepalese Rupee', 'symbol' => 'Rs', 'flag' => '🇳🇵', 'decimals' => 2, 'thousand_sep' => ',', 'decimal_sep' => '.'],
    ];

    public static function get_instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function getAllCurrencies(): array
    {
        return self::$allCurrencies;
    }

    public static function getCurrency(string $code): ?array
    {
        return self::$allCurrencies[$code] ?? null;
    }

    public static function getEnabledCurrencies(): array
    {
        $enabled = get_option(self::OPTION_ENABLED_CURRENCIES, ['USD', 'VND']);
        if (!is_array($enabled)) {
            $enabled = ['USD', 'VND'];
        }
        return $enabled;
    }

    public static function setEnabledCurrencies(array $codes): void
    {
        update_option(self::OPTION_ENABLED_CURRENCIES, array_unique($codes));
    }

    public static function getDefaultCurrency(): string
    {
        return (string) get_option(self::OPTION_DEFAULT_CURRENCY, 'USD');
    }

    public static function setDefaultCurrency(string $code): void
    {
        update_option(self::OPTION_DEFAULT_CURRENCY, $code);
    }

    public static function getCurrencyPosition(): string
    {
        return (string) get_option(self::OPTION_CURRENCY_POSITION, 'left');
    }

    public static function getCurrentCurrency(): string
    {
        $default = self::getDefaultCurrency();

        // 1. Kiểm tra request trực tiếp để phản hồi nóng
        if (!empty($_GET['currency'])) {
            $code = strtoupper(sanitize_text_field($_GET['currency']));
            if (isset(self::$allCurrencies[$code]) && in_array($code, self::getEnabledCurrencies(), true)) {
                return $code;
            }
        }

        // 2. Kiểm tra User Meta nếu đăng nhập
        if (is_user_logged_in()) {
            $userCurrency = get_user_meta(get_current_user_id(), self::SESSION_KEY, true);
            if ($userCurrency && isset(self::$allCurrencies[$userCurrency])) {
                return $userCurrency;
            }
        }

        // 3. Fallback qua Cookie (Dùng Cookie đáng tin cậy hơn Session trên WP server)
        if (isset($_COOKIE[self::SESSION_KEY])) {
            $cookieCurrency = $_COOKIE[self::SESSION_KEY];
            if (isset(self::$allCurrencies[$cookieCurrency])) {
                return $cookieCurrency;
            }
        }

        // 4. Session (Dự phòng)
        if (isset($_SESSION[self::SESSION_KEY])) {
            $sessionCurrency = $_SESSION[self::SESSION_KEY];
            if (isset(self::$allCurrencies[$sessionCurrency])) {
                return $sessionCurrency;
            }
        }

        return $default;
    }

    public static function setCurrentCurrency(string $code): bool
    {
        if (!isset(self::$allCurrencies[$code])) {
            return false;
        }

        $enabled = self::getEnabledCurrencies();
        if (!in_array($code, $enabled, true)) {
            return false;
        }

        // Lưu cho user đăng nhập
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), self::SESSION_KEY, $code);
        }

        // Lưu vào Cookie (Live trong 30 ngày)
        if (!headers_sent()) {
            setcookie(self::SESSION_KEY, $code, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }

        $_SESSION[self::SESSION_KEY] = $code;

        do_action('jankx/ecommerce/currency/changed', $code);

        return true;
    }

    /**
     * Bắt request ?currency=XXX để thay đổi tiền tệ và cache lại.
     */
    public static function handleCurrencySwitchRequest(): void
    {
        if (!empty($_GET['currency'])) {
            $code = strtoupper(sanitize_text_field($_GET['currency']));
            self::setCurrentCurrency($code);
        }
    }

    public static function formatPriceRaw(float $price, ?string $currencyCode = null): string
    {
        $currencyCode = $currencyCode ?? self::getCurrentCurrency();
        $currency = self::getCurrency($currencyCode);

        if (!$currency) {
            return number_format($price, 2) . ' ' . $currencyCode;
        }

        // Đọc per-currency override, fallback về global setting rồi currency default
        $fmt = (array) get_option('jankx_currency_fmt_' . $currencyCode, []);
        $decimals = isset($fmt['decimals'])
            ? (int) $fmt['decimals']
            : (int) self::getOption(self::OPTION_DECIMALS, $currency['decimals']);
        $thousandSep = array_key_exists('thousand_sep', $fmt)
            ? $fmt['thousand_sep']
            : self::getOption(self::OPTION_THOUSAND_SEP, $currency['thousand_sep']);
        $decimalSep = array_key_exists('decimal_sep', $fmt)
            ? $fmt['decimal_sep']
            : self::getOption(self::OPTION_DECIMAL_SEP, $currency['decimal_sep']);

        // Per-currency position; rỗng = dùng global fallback
        $perPosition = $fmt['position'] ?? '';
        $position = ($perPosition !== '')
            ? $perPosition
            : self::getCurrencyPosition();

        $formatted = number_format($price, $decimals, $decimalSep, $thousandSep);

        switch ($position) {
            case 'left':
                return $currency['symbol'] . $formatted;
            case 'right':
                return $formatted . $currency['symbol'];
            case 'left_space':
                return $currency['symbol'] . ' ' . $formatted;
            case 'right_space':
                return $formatted . ' ' . $currency['symbol'];
            default:
                return $currency['symbol'] . $formatted;
        }
    }

    public static function formatPrice(float $price, ?string $currencyCode = null): string
    {
        $currencyCode = $currencyCode ?? self::getCurrentCurrency();
        $currency = self::getCurrency($currencyCode);
        $result = self::formatPriceRaw($price, $currencyCode);

        return (string) apply_filters('jankx/ecommerce/price_format', $result, $price, $currencyCode, $currency);
    }

    public static function getEnabledCurrenciesList(): array
    {
        $enabled = self::getEnabledCurrencies();
        $all = self::getAllCurrencies();
        $list = [];

        foreach ($enabled as $code) {
            if (isset($all[$code])) {
                $list[$code] = $all[$code];
            }
        }

        return $list;
    }

    /**
     * Format a price with automatic currency conversion.
     *
     * Prices are typically stored in the default/base currency (e.g., USD).
     * This method converts the price to the current user's currency and formats it.
     *
     * Example: Stored price 100 USD, user views in VND, displays as "2,400,000₫"
     *
     * @param float  $price           Raw price in base currency
     * @param string $sourceCurrency  Currency the price is stored in (default: base currency)
     * @param string $targetCurrency  Currency to display in (default: current user currency)
     * @return string Formatted price with symbol
     */
    public static function formatPriceWithConversion(
        float $price,
        ?string $sourceCurrency = null,
        ?string $targetCurrency = null
    ): string {
        // Use default/base currency if not specified
        if ($sourceCurrency === null) {
            $sourceCurrency = self::getDefaultCurrency();
        }

        // Use current user currency if not specified
        if ($targetCurrency === null) {
            $targetCurrency = self::getCurrentCurrency();
        }

        // Get the converter manager
        $converterClass = 'Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager';
        if (!class_exists($converterClass)) {
            // Fallback if converter not loaded - just format without conversion
            return self::formatPrice($price, $sourceCurrency);
        }

        $manager = $converterClass::getInstance();
        return $manager->formatPriceWithConversion($price, $sourceCurrency, $targetCurrency);
    }

    /**
     * Convert a price between two currencies.
     *
     * @param float  $price    Amount to convert
     * @param string $fromCode Source currency code
     * @param string $toCode   Target currency code
     * @return float Converted amount, or original if conversion unavailable
     */
    public static function convertPrice(float $price, string $fromCode, string $toCode): float
    {
        if ($fromCode === $toCode) {
            return $price;
        }

        $converterClass = 'Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager';
        if (!class_exists($converterClass)) {
            return $price;
        }

        $manager = $converterClass::getInstance();
        return $manager->convert($price, $fromCode, $toCode);
    }

    protected static function getOption(string $key, $default = '')
    {
        return get_option($key, $default);
    }
}
