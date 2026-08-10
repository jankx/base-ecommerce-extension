<?php
namespace Jankx\Extensions\Ecommerce\Admin;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

class EcommerceSettingsPage
{
    const OPTION_GROUP = 'jankx_ecommerce_settings';
    const PAGE_SLUG = 'jankx-ecommerce-settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Cài đặt Ecommerce', 'jankx'),
            __('Ecommerce', 'jankx'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-cart',
            30
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'jankx-ecommerce-settings',
            $this->getAssetsUrl('admin-settings.css'),
            [],
            filemtime($this->getAssetsPath('admin-settings.css'))
        );

        wp_enqueue_script(
            'jankx-ecommerce-settings',
            $this->getAssetsUrl('admin-settings.js'),
            ['jquery'],
            filemtime($this->getAssetsPath('admin-settings.js')),
            true
        );
    }

    public function registerSettings(): void
    {
        // General
        register_setting(self::OPTION_GROUP, 'jankx_store_name', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => get_bloginfo('name'),
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_store_address', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_store_phone', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting(self::OPTION_GROUP, 'jankx_store_email', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email'),
        ]);

        // Currency
        register_setting(self::OPTION_GROUP, CurrencyManager::OPTION_DEFAULT_CURRENCY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'USD',
        ]);

        register_setting(self::OPTION_GROUP, CurrencyManager::OPTION_ENABLED_CURRENCIES, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeEnabledCurrencies'],
            'default' => ['USD', 'VND'],
        ]);

        register_setting(self::OPTION_GROUP, CurrencyManager::OPTION_CURRENCY_POSITION, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'left',
        ]);

        register_setting(self::OPTION_GROUP, CurrencyManager::OPTION_THOUSAND_SEP, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ',',
        ]);

        register_setting(self::OPTION_GROUP, CurrencyManager::OPTION_DECIMAL_SEP, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '.',
        ]);

        register_setting(self::OPTION_GROUP, CurrencyManager::OPTION_DECIMALS, [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 2,
        ]);

        // Payment
        register_setting(self::OPTION_GROUP, 'jankx_payment_gateways', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizePaymentGateways'],
            'default' => [],
        ]);

        // Coupons
        register_setting(self::OPTION_GROUP, 'jankx_coupons_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
        ]);
    }

    public function sanitizeEnabledCurrencies($value): array
    {
        if (!is_array($value)) {
            return ['USD', 'VND'];
        }
        $all = CurrencyManager::getAllCurrencies();
        return array_intersect(array_map('sanitize_text_field', $value), array_keys($all));
    }

    public function sanitizePaymentGateways($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_map('sanitize_text_field', $value);
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $currentTab = sanitize_text_field($_GET['tab'] ?? 'general');
        $tabs = $this->getTabs();
        ?>
        <div class="wrap jankx-ecommerce-settings">
            <h1><?php esc_html_e('Cài đặt Ecommerce', 'jankx'); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=' . $slug)); ?>"
                       class="nav-tab <?php echo $currentTab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="jankx-settings-content">
                <?php
                switch ($currentTab) {
                    case 'currency':
                        $this->renderCurrencyTab();
                        break;
                    case 'payment':
                        $this->renderPaymentTab();
                        break;
                    case 'coupons':
                        $this->renderCouponsTab();
                        break;
                    default:
                        $this->renderGeneralTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    protected function getTabs(): array
    {
        return [
            'general'  => __('Chung', 'jankx'),
            'currency' => __('Tiền tệ', 'jankx'),
            'payment'  => __('Thanh toán', 'jankx'),
            'coupons'  => __('Mã giảm giá', 'jankx'),
        ];
    }

    // ── GENERAL TAB ──────────────────────────────────────────────────

    protected function renderGeneralTab(): void
    {
        settings_fields(self::OPTION_GROUP);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="jankx_store_name"><?php esc_html_e('Tên cửa hàng', 'jankx'); ?></label></th>
                <td>
                    <input type="text" id="jankx_store_name" name="jankx_store_name"
                           value="<?php echo esc_attr(get_option('jankx_store_name', get_bloginfo('name'))); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="jankx_store_address"><?php esc_html_e('Địa chỉ', 'jankx'); ?></label></th>
                <td>
                    <textarea id="jankx_store_address" name="jankx_store_address" rows="3" class="large-text"><?php
                        echo esc_textarea(get_option('jankx_store_address', ''));
                    ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="jankx_store_phone"><?php esc_html_e('Số điện thoại', 'jankx'); ?></label></th>
                <td>
                    <input type="tel" id="jankx_store_phone" name="jankx_store_phone"
                           value="<?php echo esc_attr(get_option('jankx_store_phone', '')); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="jankx_store_email"><?php esc_html_e('Email liên hệ', 'jankx'); ?></label></th>
                <td>
                    <input type="email" id="jankx_store_email" name="jankx_store_email"
                           value="<?php echo esc_attr(get_option('jankx_store_email', get_option('admin_email'))); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>
        <?php
        submit_button();
    }

    // ── CURRENCY TAB ─────────────────────────────────────────────────

    protected function renderCurrencyTab(): void
    {
        settings_fields(self::OPTION_GROUP);

        $allCurrencies = CurrencyManager::getAllCurrencies();
        $enabled = CurrencyManager::getEnabledCurrencies();
        $default = CurrencyManager::getDefaultCurrency();
        $position = CurrencyManager::getCurrencyPosition();
        $thousandSep = get_option(CurrencyManager::OPTION_THOUSAND_SEP, ',');
        $decimalSep = get_option(CurrencyManager::OPTION_DECIMAL_SEP, '.');
        $decimals = get_option(CurrencyManager::OPTION_DECIMALS, 2);

        // Sort by name
        uasort($allCurrencies, fn($a, $b) => strcmp($a['name'], $b['name']));
        ?>
        <h2><?php esc_html_e('Quản lý tiền tệ', 'jankx'); ?></h2>
        <p class="description"><?php esc_html_e('Chọn các loại tiền tệ muốn hiển thị trên trang web. Đánh dấu vào ô bên cạnh để bật/tắt.', 'jankx'); ?></p>

        <table class="widefat striped jankx-currency-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th style="width:50px;"><?php esc_html_e('Bật', 'jankx'); ?></th>
                    <th style="width:60px;"><?php esc_html_e('Mã', 'jankx'); ?></th>
                    <th style="width:50px;"></th>
                    <th><?php esc_html_e('Tên tiền tệ', 'jankx'); ?></th>
                    <th style="width:100px;"><?php esc_html_e('Ký hiệu', 'jankx'); ?></th>
                    <th style="width:120px;"><?php esc_html_e('Mặc định', 'jankx'); ?></th>
                    <th style="width:100px;"><?php esc_html_e('Số thập phân', 'jankx'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allCurrencies as $code => $currency): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="jankx_enabled_currencies[]"
                                   value="<?php echo esc_attr($code); ?>"
                                   <?php checked(in_array($code, $enabled)); ?>>
                        </td>
                        <td><strong><?php echo esc_html($code); ?></strong></td>
                        <td style="font-size:1.4em;"><?php echo esc_html($currency['flag']); ?></td>
                        <td><?php echo esc_html($currency['name']); ?></td>
                        <td><code><?php echo esc_html($currency['symbol']); ?></code></td>
                        <td>
                            <label>
                                <input type="radio" name="jankx_default_currency"
                                       value="<?php echo esc_attr($code); ?>"
                                       <?php checked($default, $code); ?>>
                            </label>
                        </td>
                        <td>
                            <select name="jankx_currency_decimals" class="small-text">
                                <?php for ($i = 0; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($decimals, $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e('Định dạng tiền tệ', 'jankx'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Vị trí ký hiệu', 'jankx'); ?></th>
                <td>
                    <select name="jankx_currency_position">
                        <option value="left" <?php selected($position, 'left'); ?>><?php esc_html_e('Trước số tiền ($100)', 'jankx'); ?></option>
                        <option value="right" <?php selected($position, 'right'); ?>><?php esc_html_e('Sau số tiền (100$)', 'jankx'); ?></option>
                        <option value="left_space" <?php selected($position, 'left_space'); ?>><?php esc_html_e('Trước số tiền, cách ($ 100)', 'jankx'); ?></option>
                        <option value="right_space" <?php selected($position, 'right_space'); ?>><?php esc_html_e('Sau số tiền, cách (100 $)', 'jankx'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="jankx_currency_thousand_sep"><?php esc_html_e('Dấu phân cách hàng nghìn', 'jankx'); ?></label></th>
                <td>
                    <input type="text" id="jankx_currency_thousand_sep" name="jankx_currency_thousand_sep"
                           value="<?php echo esc_attr($thousandSep); ?>" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="jankx_currency_decimal_sep"><?php esc_html_e('Dấu phân cách thập phân', 'jankx'); ?></label></th>
                <td>
                    <input type="text" id="jankx_currency_decimal_sep" name="jankx_currency_decimal_sep"
                           value="<?php echo esc_attr($decimalSep); ?>" class="small-text">
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Xem trước', 'jankx'); ?></h3>
        <div class="jankx-price-preview" style="padding:16px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
            <p>
                <strong>USD:</strong> <?php echo esc_html(CurrencyManager::formatPrice(1234567.89, 'USD')); ?>
            </p>
            <p>
                <strong>VND:</strong> <?php echo esc_html(CurrencyManager::formatPrice(1234567.89, 'VND')); ?>
            </p>
        </div>

        <?php submit_button(); ?>
        <?php
    }

    // ── PAYMENT TAB ──────────────────────────────────────────────────

    protected function renderPaymentTab(): void
    {
        settings_fields(self::OPTION_GROUP);

        $enabledGateways = get_option('jankx_payment_gateways', []);

        // Get registered gateways from payment-system extension
        $registeredGateways = $this->getRegisteredGateways();
        ?>
        <h2><?php esc_html_e('Phương thức thanh toán', 'jankx'); ?></h2>
        <p class="description"><?php esc_html_e('Kích hoạt và cấu hình các phương thức thanh toán từ các extension.', 'jankx'); ?></p>

        <table class="widefat striped" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th style="width:50px;"><?php esc_html_e('Bật', 'jankx'); ?></th>
                    <th style="width:50px;"></th>
                    <th><?php esc_html_e('Phương thức', 'jankx'); ?></th>
                    <th><?php esc_html_e('Mô tả', 'jankx'); ?></th>
                    <th style="width:100px;"><?php esc_html_e('Trạng thái', 'jankx'); ?></th>
                    <th style="width:100px;"><?php esc_html_e('Chế độ', 'jankx'); ?></th>
                    <th style="width:120px;"><?php esc_html_e('Thao tác', 'jankx'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Built-in gateways (fallback if payment-system not active)
                $builtInGateways = [
                    'cod' => [
                        'name' => __('Thanh toán khi nhận hàng (COD)', 'jankx'),
                        'description' => __('Khách hàng thanh toán bằng tiền mặt khi nhận hàng.', 'jankx'),
                        'icon' => '💵',
                    ],
                    'bank_transfer' => [
                        'name' => __('Chuyển khoản ngân hàng', 'jankx'),
                        'description' => __('Khách hàng chuyển khoản trực tiếp vào tài khoản ngân hàng.', 'jankx'),
                        'icon' => '🏦',
                    ],
                ];

                // Merge registered gateways from payment-system
                $allGateways = array_merge($builtInGateways, $registeredGateways);

                foreach ($allGateways as $id => $gateway): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="jankx_payment_gateways[]"
                                   value="<?php echo esc_attr($id); ?>"
                                   <?php checked(in_array($id, $enabledGateways)); ?>>
                        </td>
                        <td style="font-size:1.4em;"><?php echo esc_html($gateway['icon'] ?? '💳'); ?></td>
                        <td><strong><?php echo esc_html($gateway['name']); ?></strong></td>
                        <td><?php echo esc_html($gateway['description'] ?? ''); ?></td>
                        <td>
                            <?php if (!empty($gateway['available'])): ?>
                                <span class="jankx-status-badge jankx-status-active"><?php esc_html_e('Sẵn sàng', 'jankx'); ?></span>
                            <?php else: ?>
                                <span class="jankx-status-badge jankx-status-inactive"><?php esc_html_e('Chưa cấu hình', 'jankx'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($gateway['sandbox_mode'])): ?>
                                <span class="jankx-status-badge" style="background:#fff3cd; color:#856404;"><?php esc_html_e('Sandbox', 'jankx'); ?></span>
                            <?php else: ?>
                                <span class="jankx-status-badge" style="background:#d1ecf1; color:#0c5460;"><?php esc_html_e('Production', 'jankx'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($gateway['settings_url'])): ?>
                                <a href="<?php echo esc_url($gateway['settings_url']); ?>" class="button button-small">
                                    <?php esc_html_e('Cài đặt', 'jankx'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button(); ?>
        <?php
    }

    /**
     * Get registered gateways from payment-system extension
     */
    protected function getRegisteredGateways(): array
    {
        if (!class_exists('\\Jankx\\Extensions\\PaymentSystem\\Gateways\\GatewayManager')) {
            return [];
        }

        $manager = \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance();
        $allGateways = $manager->getAll();
        $modes = $manager->getGatewayModes();
        $result = [];

        foreach ($allGateways as $name => $class) {
            $gateway = $manager->get($name);
            if (!$gateway) {
                continue;
            }

            $result[$name] = [
                'name'         => $gateway->getName(),
                'description'  => $this->getGatewayDescription($name),
                'icon'         => $this->getGatewayIcon($name),
                'available'    => $gateway->isAvailable(),
                'sandbox_mode' => $modes[$name]['is_sandbox'] ?? false,
                'settings_url' => admin_url('admin.php?page=jankx-ecommerce-settings&tab=payment&gateway=' . $name),
            ];
        }

        return $result;
    }

    protected function getGatewayDescription(string $gatewayName): string
    {
        $descriptions = [
            'onepay'          => __('Thanh toán thẻ quốc tế (Visa, Mastercard, JCB) qua OnePay.', 'jankx'),
            'onepay_domestic' => __('Thanh toán thẻ nội địa (ATM/Napas) qua OnePay.', 'jankx'),
            'momo'            => __('Ví điện tử MoMo - thanh toán nhanh bằng QR code.', 'jankx'),
            'vnpay'           => __('Cổng thanh toán VNPay - hỗ trợ ATM, Visa, Mastercard.', 'jankx'),
            'zalopay'         => __('Ví điện tử ZaloPay - thanh toán bằng QR code.', 'jankx'),
            'stripe'          => __('Thanh toán quốc tế qua Stripe (Visa, Mastercard, AMEX).', 'jankx'),
            'paypal'          => __('Thanh toán quốc tế qua PayPal.', 'jankx'),
        ];

        return $descriptions[$gatewayName] ?? __('Phương thức thanh toán trực tuyến.', 'jankx');
    }

    protected function getGatewayIcon(string $gatewayName): string
    {
        $icons = [
            'onepay'          => '💳',
            'onepay_domestic' => '🏧',
            'momo'            => '📱',
            'vnpay'           => '🏦',
            'zalopay'         => '💜',
            'stripe'          => '💰',
            'paypal'          => '🅿️',
        ];

        return $icons[$gatewayName] ?? '💳';
    }

    // ── COUPONS TAB ──────────────────────────────────────────────────

    protected function renderCouponsTab(): void
    {
        settings_fields(self::OPTION_GROUP);
        $enabled = get_option('jankx_coupons_enabled', true);
        ?>
        <h2><?php esc_html_e('Mã giảm giá', 'jankx'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Cho phép sử dụng mã giảm giá', 'jankx'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="jankx_coupons_enabled" value="1" <?php checked($enabled, true); ?>>
                        <?php esc_html_e('Bật tính năng mã giảm giá trên trang thanh toán', 'jankx'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php if (class_exists('\\Jankx\\Extensions\\CouponSystem\\CouponSystemExtension')): ?>
            <p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=jankx_coupon')); ?>" class="button button-primary">
                    <?php esc_html_e('Quản lý mã giảm giá', 'jankx'); ?>
                </a>
            </p>
        <?php endif; ?>

        <?php submit_button(); ?>
        <?php
    }

    protected function getAssetsUrl(string $file): string
    {
        $ext = \Jankx\Extensions\Ecommerce\EcommerceExtension::get_instance();
        return $ext->get_extension_url() . '/assets/' . $file;
    }

    protected function getAssetsPath(string $file): string
    {
        $ext = \Jankx\Extensions\Ecommerce\EcommerceExtension::get_instance();
        return $ext->get_extension_path() . '/assets/' . $file;
    }
}
