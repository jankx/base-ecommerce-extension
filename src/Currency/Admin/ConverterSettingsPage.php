<?php
namespace Jankx\Extensions\Ecommerce\Currency\Admin;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;
use Jankx\Extensions\Ecommerce\Currency\Converters\ManualRateConverter;
use Jankx\Extensions\Ecommerce\Currency\Converters\OpenExchangeRatesConverter;
use Jankx\Extensions\Ecommerce\Currency\Converters\FixerIOConverter;

/**
 * Admin settings page for currency converters.
 * 
 * Integrated into the Ecommerce Settings → Currency tab.
 * 
 * @package Jankx\Extensions\Ecommerce\Currency\Admin
 */
class ConverterSettingsPage
{
    private $manager;

    public function __construct()
    {
        $this->manager = CurrencyConverterManager::getInstance();
    }

    /**
     * Register hooks for admin UI.
     */
    public function register(): void
    {
        add_action('jankx/ecommerce/settings/currency/after_general', [$this, 'renderConverterSection'], 20);
        add_action('admin_init', [$this, 'handleFormSubmission']);
    }

    /**
     * Render the converter configuration section.
     */
    public function renderConverterSection(): void
    {
        $available = $this->manager->getAvailableConverters();
        $activeType = get_option('jankx_currency_converter_type', 'noop');
        $config = $this->manager->getActiveConverterConfig();
        ?>
        <div class="converter-settings-section">
            <h3><?php esc_html_e('Exchange Rate Converter', 'jankx'); ?></h3>

            <p class="description">
                <?php esc_html_e('Configure how currency conversion works on your site. Prices are stored in the default currency and converted to the user\'s selected currency for display.', 'jankx'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="converter_type"><?php esc_html_e('Converter Type', 'jankx'); ?></label>
                    </th>
                    <td>
                        <select id="converter_type" name="jankx_currency_converter_type" class="regular-text">
                            <?php foreach ($available as $key => $info): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $activeType); ?>>
                                    <?php echo esc_html($info['name']); ?>
                                    <?php if (!$info['is_ready'] && $key !== 'noop'): ?>
                                        (<?php esc_html_e('Not configured', 'jankx'); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html($available[$activeType]['description'] ?? ''); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php $this->renderConverterConfig($activeType, $available, $config); ?>

            <p class="submit">
                <button type="submit" class="button button-primary" name="save_converter_settings">
                    <?php esc_html_e('Save Converter Settings', 'jankx'); ?>
                </button>
            </p>
        </div>

        <hr />

        <?php $this->renderConverterStatus(); ?>
    <?php
    }

    /**
     * Render converter-specific configuration fields.
     */
    private function renderConverterConfig(string $activeType, array $available, array $config): void
    {
        if ($activeType === 'noop') {
            return;
        }

        echo '<table class="form-table">';

        if ($activeType === 'openexchangerates') {
            $this->renderOpenExchangeRatesConfig($config);
        } elseif ($activeType === 'fixerio') {
            $this->renderFixerIOConfig($config);
        } elseif ($activeType === 'manual') {
            $this->renderManualRateConfig($config);
        }

        echo '</table>';
    }

    /**
     * Render Manual configuration.
     */
    private function renderManualRateConfig(array $config): void
    {
        $baseCurrency = get_option(ManualRateConverter::OPTION_BASE, CurrencyManager::getDefaultCurrency());
        $rates = ManualRateConverter::getRatesMap();
        $enabled = CurrencyManager::getEnabledCurrencies();
        $all = CurrencyManager::getAllCurrencies();
        ?>
        <tr>
            <th scope="row">
                <label for="manual_base_currency"><?php esc_html_e('Đồng tiền cơ sở (Base Currency)', 'jankx'); ?></label>
            </th>
            <td>
                <select id="manual_base_currency" name="manual_base_currency">
                    <?php foreach ($enabled as $code): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($baseCurrency, $code); ?>>
                            <?php echo esc_html($code . ' - ' . ($all[$code]['name'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e('Tỷ giá của các đồng tiền khác sẽ được tính quy đổi qua đồng tiền cơ sở này (Base = 1).', 'jankx'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label><?php esc_html_e('Tỷ giá (Exchange Rates)', 'jankx'); ?></label>
            </th>
            <td>
                <table class="widefat striped" style="max-width: 400px; margin-top: 5px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Đồng tiền', 'jankx'); ?></th>
                            <th><?php esc_html_e('Tỷ giá (so với Base)', 'jankx'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enabled as $code):
                            $rate = isset($rates[$code]) ? $rates[$code] : ($code === $baseCurrency ? 1.0 : '');
                            // Base currency always has rate 1.0, user can't change it here easily without making it confusing.
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($code); ?></strong></td>
                                <td>
                                    <input type="number" step="any" min="0" name="manual_rates[<?php echo esc_attr($code); ?>]"
                                        value="<?php echo esc_attr($rate); ?>" class="regular-text" style="width:100%;" />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">
                    <?php esc_html_e('Ví dụ: Nếu Base là USD, tỷ giá VND = 25300 (Tức là 1 USD = 25300 VND). Mọi phép đổi từ EUR sang VND sẽ tự chia qua Base.', 'jankx'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render OpenExchangeRates configuration.
     */
    private function renderOpenExchangeRatesConfig(array $config): void
    {
        $apiKey = $config['openexchangerates_api_key'] ?? '';
        $baseCurrency = $config['openexchangerates_base_currency'] ?? 'USD';
        ?>
        <tr>
            <th scope="row">
                <label for="oer_api_key"><?php esc_html_e('API Key', 'jankx'); ?></label>
            </th>
            <td>
                <input type="password" id="oer_api_key" name="openexchangerates_api_key" class="regular-text"
                    value="<?php echo esc_attr($apiKey); ?>" />
                <p class="description">
                    <a href="https://openexchangerates.io/signup/free" target="_blank">
                        <?php esc_html_e('Get a free API key', 'jankx'); ?>
                    </a>
                    <?php esc_html_e('(Free tier: 1000 requests/month, USD base only)', 'jankx'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="oer_base_currency"><?php esc_html_e('Base Currency', 'jankx'); ?></label>
            </th>
            <td>
                <select id="oer_base_currency" name="openexchangerates_base_currency">
                    <option value="USD" <?php selected('USD', $baseCurrency); ?>>USD (Free)</option>
                    <option value="EUR" <?php selected('EUR', $baseCurrency); ?>>EUR (Paid)</option>
                    <option value="GBP" <?php selected('GBP', $baseCurrency); ?>>GBP (Paid)</option>
                </select>
                <p class="description">
                    <?php esc_html_e('Free tier limited to USD. Paid plans support other base currencies.', 'jankx'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render Fixer.io configuration.
     */
    private function renderFixerIOConfig(array $config): void
    {
        $apiKey = $config['fixerio_api_key'] ?? '';
        $baseCurrency = $config['fixerio_base_currency'] ?? 'EUR';
        ?>
        <tr>
            <th scope="row">
                <label for="fio_api_key"><?php esc_html_e('API Key', 'jankx'); ?></label>
            </th>
            <td>
                <input type="password" id="fio_api_key" name="fixerio_api_key" class="regular-text"
                    value="<?php echo esc_attr($apiKey); ?>" />
                <p class="description">
                    <a href="https://fixer.io/" target="_blank">
                        <?php esc_html_e('Get an API key from Fixer.io', 'jankx'); ?>
                    </a>
                    <?php esc_html_e('(Free tier: 100 requests/month, EUR base only)', 'jankx'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="fio_base_currency"><?php esc_html_e('Base Currency', 'jankx'); ?></label>
            </th>
            <td>
                <select id="fio_base_currency" name="fixerio_base_currency">
                    <option value="EUR" <?php selected('EUR', $baseCurrency); ?>>EUR (Free)</option>
                    <option value="USD" <?php selected('USD', $baseCurrency); ?>>USD (Paid)</option>
                    <option value="GBP" <?php selected('GBP', $baseCurrency); ?>>GBP (Paid)</option>
                </select>
                <p class="description">
                    <?php esc_html_e('Free tier limited to EUR. Paid plans support other base currencies.', 'jankx'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Render converter status information.
     */
    private function renderConverterStatus(): void
    {
        $activeType = get_option('jankx_currency_converter_type', 'noop');
        $converter = $this->manager->getActiveConverter();

        // Unwrap if cached
        if (method_exists($converter, 'getInnerConverter')) {
            $innerConverter = $converter->getInnerConverter();
        } else {
            $innerConverter = $converter;
        }
        ?>
        <h3><?php esc_html_e('Converter Status', 'jankx'); ?></h3>

        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Converter', 'jankx'); ?></th>
                    <th><?php esc_html_e('Status', 'jankx'); ?></th>
                    <th><?php esc_html_e('Details', 'jankx'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo esc_html($converter->getName()); ?></strong>
                    </td>
                    <td>
                        <?php if ($converter->isReady()): ?>
                            <span class="dashicons dashicons-yes" style="color: green;"></span>
                            <?php esc_html_e('Ready', 'jankx'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no" style="color: red;"></span>
                            <?php esc_html_e('Not Ready', 'jankx'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        if ($converter->isReady()) {
                            // Try a test conversion
                            $rate = $converter->getRate('USD', 'VND');
                            if ($rate !== null) {
                                printf(
                                    esc_html__('Exchange rate 1 USD = %.2f VND', 'jankx'),
                                    $rate
                                );
                            } else {
                                esc_html_e('Converter ready but rate not available', 'jankx');
                            }
                        } else {
                            if (method_exists($innerConverter, 'getLastError')) {
                                $error = $innerConverter->getLastError();
                                if ($error) {
                                    echo esc_html($error);
                                } else {
                                    esc_html_e('Missing configuration (e.g., API key)', 'jankx');
                                }
                            }
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top: 30px;"><?php esc_html_e('Usage Examples', 'jankx'); ?></h3>
        <p><?php esc_html_e('To use the currency converter in your code:', 'jankx'); ?></p>
        <pre><code><?php echo esc_html(
            '// Display price with automatic conversion
echo CurrencyManager::formatPriceWithConversion(100); // 100 USD → display in current currency

// Get raw converted value
$converted = CurrencyManager::convertPrice(100, "USD", "VND");
'
        ); ?></code></pre>
        <?php
    }

    /**
     * Handle form submission.
     */
    public function handleFormSubmission(): void
    {
        if (
            !isset($_POST['save_converter_settings']) ||
            !current_user_can('manage_options') ||
            !check_admin_referer('wp-nonce-your-form-name')
        ) {
            return;
        }

        $converterType = sanitize_text_field($_POST['jankx_currency_converter_type'] ?? 'noop');

        // Set active converter
        if (!$this->manager->setActiveConverter($converterType)) {
            add_settings_error(
                'converter_settings',
                'invalid_converter',
                __('Invalid converter selected', 'jankx')
            );
            return;
        }

        // Handle converter-specific config
        $config = [];

        if ($converterType === 'openexchangerates') {
            $apiKey = sanitize_text_field($_POST['openexchangerates_api_key'] ?? '');
            $baseCurrency = strtoupper(sanitize_text_field($_POST['openexchangerates_base_currency'] ?? 'USD'));

            if (empty($apiKey)) {
                add_settings_error(
                    'converter_settings',
                    'missing_api_key',
                    __('OpenExchangeRates API key is required', 'jankx')
                );
                return;
            }

            OpenExchangeRatesConverter::setApiKey($apiKey);
            OpenExchangeRatesConverter::setBaseCurrency($baseCurrency);
            $config['openexchangerates_api_key'] = $apiKey;
            $config['openexchangerates_base_currency'] = $baseCurrency;
        } elseif ($converterType === 'fixerio') {
            $apiKey = sanitize_text_field($_POST['fixerio_api_key'] ?? '');
            $baseCurrency = strtoupper(sanitize_text_field($_POST['fixerio_base_currency'] ?? 'EUR'));

            if (empty($apiKey)) {
                add_settings_error(
                    'converter_settings',
                    'missing_api_key',
                    __('Fixer.io API key is required', 'jankx')
                );
                return;
            }

            FixerIOConverter::setApiKey($apiKey);
            FixerIOConverter::setBaseCurrency($baseCurrency);
            $config['fixerio_api_key'] = $apiKey;
            $config['fixerio_base_currency'] = $baseCurrency;
        } elseif ($converterType === 'manual') {
            $baseCurrency = strtoupper(sanitize_text_field($_POST['manual_base_currency'] ?? CurrencyManager::getDefaultCurrency()));
            $rawRates = $_POST['manual_rates'] ?? [];

            $rates = [];
            foreach ($rawRates as $code => $rate) {
                $code = strtoupper(sanitize_key($code));
                $rate = (float) $rate;
                if ($rate > 0) {
                    $rates[$code] = $rate;
                }
            }

            // Ép đồng base phải là 1.0
            $rates[$baseCurrency] = 1.0;

            ManualRateConverter::saveRatesMap($rates);
            update_option(ManualRateConverter::OPTION_BASE, $baseCurrency);

            $config['manual_base_currency'] = $baseCurrency;
        }

        // Save config
        if (!empty($config)) {
            $this->manager->setConverterConfig($converterType, $config);
        }

        add_settings_error(
            'converter_settings',
            'settings_saved',
            __('Converter settings saved successfully', 'jankx'),
            'success'
        );

        do_action('jankx/ecommerce/currency/converter_configured', $converterType);
    }
}
