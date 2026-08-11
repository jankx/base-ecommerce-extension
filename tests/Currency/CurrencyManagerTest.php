<?php
namespace Jankx\Extensions\Ecommerce\Tests\Currency;

use PHPUnit\Framework\TestCase;
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

class CurrencyManagerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('Brain\Monkey\Functions\when')) {
            require_once __DIR__ . '/../bootstrap.php';
        }
        stub_wp_ecommerce_functions();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_get_all_currencies_returns_array(): void
    {
        $currencies = CurrencyManager::getAllCurrencies();
        $this->assertIsArray($currencies);
        $this->assertArrayHasKey('USD', $currencies);
        $this->assertArrayHasKey('VND', $currencies);
    }

    public function test_get_currency_returns_currency_data(): void
    {
        $currency = CurrencyManager::getCurrency('VND');
        $this->assertNotNull($currency);
        $this->assertSame('VND', $currency['code']);
        $this->assertSame('₫', $currency['symbol']);
        $this->assertSame('Vietnamese Dong', $currency['name']);
    }

    public function test_get_currency_returns_null_for_unknown(): void
    {
        $currency = CurrencyManager::getCurrency('XYZ');
        $this->assertNull($currency);
    }

    public function test_get_enabled_currencies(): void
    {
        $enabled = CurrencyManager::getEnabledCurrencies();
        $this->assertIsArray($enabled);
        $this->assertContains('USD', $enabled);
        $this->assertContains('VND', $enabled);
    }

    public function test_format_price_raw_vnd_left_position(): void
    {
        $result = CurrencyManager::formatPriceRaw(1500000, 'VND');
        $this->assertSame('₫1.500.000', $result);
    }

    public function test_format_price_raw_vnd_zero_decimals(): void
    {
        $result = CurrencyManager::formatPriceRaw(100000, 'VND');
        $this->assertSame('₫100.000', $result);
    }

    public function test_format_price_raw_usd_two_decimals(): void
    {
        $result = CurrencyManager::formatPriceRaw(19.99, 'USD');
        $this->assertSame('$19.99', $result);
    }

    public function test_format_price_raw_usd_integer(): void
    {
        $result = CurrencyManager::formatPriceRaw(25.00, 'USD');
        $this->assertSame('$25.00', $result);
    }

    public function test_format_price_raw_eur(): void
    {
        $result = CurrencyManager::formatPriceRaw(1234.56, 'EUR');
        $this->assertSame('€1.234,56', $result);
    }

    public function test_format_price_raw_gbp(): void
    {
        $result = CurrencyManager::formatPriceRaw(99.99, 'GBP');
        $this->assertSame('£99.99', $result);
    }

    public function test_format_price_raw_jpy_no_decimals(): void
    {
        $result = CurrencyManager::formatPriceRaw(1500, 'JPY');
        $this->assertSame('¥1,500', $result);
    }

    public function test_format_price_raw_unknown_currency(): void
    {
        $result = CurrencyManager::formatPriceRaw(100, 'XYZ');
        $this->assertSame('100.00 XYZ', $result);
    }

    public function test_format_price_raw_right_position(): void
    {
        // Mock option to return 'right' position
        $GLOBALS['__wp_options']['jankx_currency_position'] = 'right';

        $result = CurrencyManager::formatPriceRaw(100, 'USD');
        $this->assertSame('100.00$', $result);
    }

    public function test_format_price_raw_left_space_position(): void
    {
        $GLOBALS['__wp_options']['jankx_currency_position'] = 'left_space';

        $result = CurrencyManager::formatPriceRaw(100, 'USD');
        $this->assertSame('$ 100.00', $result);
    }

    public function test_format_price_raw_right_space_position(): void
    {
        $GLOBALS['__wp_options']['jankx_currency_position'] = 'right_space';

        $result = CurrencyManager::formatPriceRaw(100, 'USD');
        $this->assertSame('100.00 $', $result);
    }

    public function test_format_price_applies_filter(): void
    {
        \Brain\Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
            if ($tag === 'jankx/ecommerce/price_format') {
                return 'FILTERED:' . $value;
            }
            return $value;
        });

        $result = CurrencyManager::formatPrice(100, 'USD');
        $this->assertSame('FILTERED:$100.00', $result);
    }

    public function test_format_price_raw_large_amount(): void
    {
        $result = CurrencyManager::formatPriceRaw(999999999, 'VND');
        $this->assertSame('₫999.999.999', $result);
    }

    public function test_format_price_raw_zero(): void
    {
        $result = CurrencyManager::formatPriceRaw(0, 'VND');
        $this->assertSame('₫0', $result);
    }

    public function test_format_price_raw_negative(): void
    {
        $result = CurrencyManager::formatPriceRaw(-50000, 'VND');
        $this->assertSame('₫-50.000', $result);
    }

    public function test_get_enabled_currencies_list(): void
    {
        $list = CurrencyManager::getEnabledCurrenciesList();
        $this->assertIsArray($list);
        $this->assertArrayHasKey('USD', $list);
        $this->assertArrayHasKey('VND', $list);
    }

    public function test_default_currency(): void
    {
        $default = CurrencyManager::getDefaultCurrency();
        $this->assertNotEmpty($default);
    }

    public function test_currency_position_default(): void
    {
        $position = CurrencyManager::getCurrencyPosition();
        $this->assertSame('left', $position);
    }

    public function test_sgd_format(): void
    {
        $result = CurrencyManager::formatPriceRaw(1234.56, 'SGD');
        $this->assertSame('S$1,234.56', $result);
    }

    public function test_myr_format(): void
    {
        $result = CurrencyManager::formatPriceRaw(1234.56, 'MYR');
        $this->assertSame('RM1,234.56', $result);
    }

    public function test_idr_format_no_decimals(): void
    {
        $result = CurrencyManager::formatPriceRaw(1500000, 'IDR');
        $this->assertSame('Rp1.500.000', $result);
    }
}
