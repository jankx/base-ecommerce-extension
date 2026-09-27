<?php
/**
 * Currency Conversion Debug Page
 *
 * Helps diagnose currency conversion issues.
 *
 * @package Jankx\Extensions\Ecommerce\Admin
 */

namespace Jankx\Extensions\Ecommerce\Admin;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;

class CurrencyDebugPage
{
    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenuPage']);
    }

    public function addMenuPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_submenu_page(
            'jankx-ecommerce',
            __('Currency Debug', 'base-ecommerce'),
            __('Currency Debug', 'base-ecommerce'),
            'manage_options',
            'jankx-currency-debug',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $manager = CurrencyConverterManager::getInstance();
        $activeConverter = $manager->getActiveConverter();
        $converterName = $activeConverter->getName() ?? 'Unknown';
        $converterReady = $activeConverter->isReady();
        $allConverters = $manager->getAvailableConverters();

        // Test conversion
        $testAmount = 100;
        $fromCurrency = 'USD';
        $toCurrency = 'VND';
        $convertedAmount = $manager->convert($testAmount, $fromCurrency, $toCurrency);
        $rate = $manager->getRate($fromCurrency, $toCurrency);

        ?>
        <div class="wrap">
            <h1><?php _e('Currency Conversion Debug', 'base-ecommerce'); ?></h1>

            <div style="background: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h2><?php _e('Status Overview', 'base-ecommerce'); ?></h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong><?php _e('Default Currency', 'base-ecommerce'); ?>:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><code><?php echo esc_html(CurrencyManager::getDefaultCurrency()); ?></code></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong><?php _e('Current User Currency', 'base-ecommerce'); ?>:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><code><?php echo esc_html(CurrencyManager::getCurrentCurrency()); ?></code></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong><?php _e('Active Converter', 'base-ecommerce'); ?>:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;">
                            <code><?php echo esc_html($converterName); ?></code>
                            <span style="margin-left: 10px; padding: 3px 8px; border-radius: 3px; background: <?php echo $converterReady ? '#90EE90' : '#FFB6C6'; ?>;">
                                <?php echo $converterReady ? __('✓ Ready', 'base-ecommerce') : __('✗ Not Ready', 'base-ecommerce'); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h2><?php _e('Test Conversion', 'base-ecommerce'); ?></h2>
                <p><?php printf(
                    __('Converting %d %s to %s:', 'base-ecommerce'),
                    $testAmount,
                    esc_html($fromCurrency),
                    esc_html($toCurrency)
                ); ?></p>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong><?php _e('Exchange Rate', 'base-ecommerce'); ?>:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;">
                            <?php if ($rate !== null) : ?>
                                <code><?php echo number_format($rate, 6); ?></code>
                            <?php else : ?>
                                <span style="color: red;"><?php _e('Unable to fetch (converter not ready or API error)', 'base-ecommerce'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong><?php _e('Converted Amount', 'base-ecommerce'); ?>:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;">
                            <code><?php echo number_format($convertedAmount, 2); ?> <?php echo esc_html($toCurrency); ?></code>
                            <?php if ($convertedAmount == $testAmount) : ?>
                                <span style="color: red; margin-left: 10px;">⚠️ <?php _e('No conversion occurred (same as input)', 'base-ecommerce'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h2><?php _e('Available Converters', 'base-ecommerce'); ?></h2>
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><?php _e('Name', 'base-ecommerce'); ?></th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><?php _e('Type', 'base-ecommerce'); ?></th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><?php _e('Status', 'base-ecommerce'); ?></th>
                            <th style="padding: 10px; text-align: left; border: 1px solid #ddd;"><?php _e('Description', 'base-ecommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allConverters as $key => $converter) : ?>
                            <tr style="border: 1px solid #ddd; background: <?php echo $key === get_option('jankx_currency_converter_type', 'noop') ? '#E8F5E9' : 'white'; ?>;">
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php echo esc_html($converter['name']); ?></strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><code><?php echo esc_html($key); ?></code></td>
                                <td style="padding: 10px; border: 1px solid #ddd;">
                                    <span style="padding: 3px 8px; border-radius: 3px; background: <?php echo $converter['is_ready'] ? '#90EE90' : '#FFB6C6'; ?>;">
                                        <?php echo $converter['is_ready'] ? __('Ready', 'base-ecommerce') : __('Not Ready', 'base-ecommerce'); ?>
                                    </span>
                                </td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($converter['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;">
                <h2><?php _e('Troubleshooting', 'base-ecommerce'); ?></h2>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><strong><?php _e('Converted amount equals input amount?', 'base-ecommerce'); ?></strong>
                        <br><?php _e('This means the converter returned the original amount. Possible causes:', 'base-ecommerce'); ?>
                        <ul style="list-style: circle; padding-left: 20px; margin-top: 5px;">
                            <li><?php _e('NoOpConverter is active (default, no conversion)', 'base-ecommerce'); ?></li>
                            <li><?php _e('Active converter is not ready (missing API key)', 'base-ecommerce'); ?></li>
                            <li><?php _e('API call failed (network error, invalid key, rate limit)', 'base-ecommerce'); ?></li>
                        </ul>
                    </li>
                    <li><strong><?php _e('How to enable conversion?', 'base-ecommerce'); ?></strong>
                        <br><?php _e('Go to WP Admin > E-Commerce > Settings > Currency Converter, then:', 'base-ecommerce'); ?>
                        <ul style="list-style: circle; padding-left: 20px; margin-top: 5px;">
                            <li><?php printf(
                                __('1. Sign up at <a href="%s" target="_blank">OpenExchangeRates.io</a> or <a href="%s" target="_blank">Fixer.io</a>', 'base-ecommerce'),
                                'https://openexchangerates.io/signup/free',
                                'https://fixer.io'
                            ); ?></li>
                            <li><?php _e('2. Copy your API key', 'base-ecommerce'); ?></li>
                            <li><?php _e('3. Select converter type in settings', 'base-ecommerce'); ?></li>
                            <li><?php _e('4. Paste API key', 'base-ecommerce'); ?></li>
                            <li><?php _e('5. Click "Test Connection" to verify', 'base-ecommerce'); ?></li>
                        </ul>
                    </li>
                    <li><strong><?php _e('Check API key?', 'base-ecommerce'); ?></strong>
                        <br><?php _e('Go to WP Admin > E-Commerce > Settings > Currency Converter to view/edit configuration.', 'base-ecommerce'); ?></li>
                </ul>
            </div>

            <div style="background: #d1ecf1; padding: 20px; margin: 20px 0; border-left: 4px solid #17a2b8;">
                <h2><?php _e('How Currency Switching Works', 'base-ecommerce'); ?></h2>
                <ol style="padding-left: 20px;">
                    <li><?php _e('User selects new currency in Currency Switcher block', 'base-ecommerce'); ?></li>
                    <li><?php _e('Frontend JavaScript POSTs to /wp-json/jankx/ecommerce/v1/currency/switch', 'base-ecommerce'); ?></li>
                    <li><?php _e('Backend saves currency to session/user meta', 'base-ecommerce'); ?></li>
                    <li><?php _e('Page reloads', 'base-ecommerce'); ?></li>
                    <li><?php _e('Block reads price from post meta (stored in default currency)', 'base-ecommerce'); ?></li>
                    <li><?php _e('Block calls formatPriceWithConversion() with new currency', 'base-ecommerce'); ?></li>
                    <li><?php _e('Converter converts price using active converter', 'base-ecommerce'); ?></li>
                    <li><?php _e('Formatted price displayed to user', 'base-ecommerce'); ?></li>
                </ol>
                <p style="margin-top: 15px; color: #004085;">
                    <strong><?php _e('Key Point:', 'base-ecommerce'); ?></strong>
                    <?php _e('If step 7 returns the original amount (no conversion), prices will appear unchanged. Check converter status above!', 'base-ecommerce'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
