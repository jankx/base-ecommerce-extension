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
        add_action('admin_menu', [$this, 'addSubmenuPages'], 99);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Đăng ký các field mặc định cho tab Thuế (SSR). Cấu trúc giúp dễ dàng `remove_action` và custom field.
        add_action('jankx/ecommerce/settings/tax/render_fields', [$this, 'renderTaxFieldEnable'], 10);
        add_action('jankx/ecommerce/settings/tax/render_fields', [$this, 'renderTaxFieldStrategy'], 20);
        add_action('jankx/ecommerce/settings/tax/render_fields', [$this, 'renderTaxFieldRates'], 30);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Cài đặt Ecommerce', 'jankx'),
            __('Ecommerce', 'jankx'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            $this->getMenuIcon(),
            30
        );
    }

    public function addSubmenuPages(): void
    {
        $parent = self::PAGE_SLUG;

        add_submenu_page($parent, __('Cài đặt chung', 'jankx'), __('Cài đặt chung', 'jankx'), 'manage_options', 'jankx-ecommerce-general', [$this, 'renderGeneralPage']);
        add_submenu_page($parent, __('Tiền tệ', 'jankx'), __('Tiền tệ', 'jankx'), 'manage_options', 'jankx-ecommerce-currency', [$this, 'renderCurrencyPage']);
        add_submenu_page($parent, __('Phương thức thanh toán', 'jankx'), __('Thanh toán', 'jankx'), 'manage_options', 'jankx-ecommerce-payment', [$this, 'renderPaymentPage']);
        add_submenu_page($parent, __('Mã giảm giá', 'jankx'), __('Mã giảm giá', 'jankx'), 'manage_options', 'jankx-ecommerce-coupons', [$this, 'renderCouponsPage']);
        add_submenu_page($parent, __('Thuế', 'jankx'), __('Thuế', 'jankx'), 'manage_options', 'jankx-ecommerce-tax', [$this, 'renderTaxPage']);

        remove_submenu_page($parent, $parent);
    }

    public function renderGeneralPage(): void { $_GET['tab'] = 'general'; $this->renderPage(); }
    public function renderCurrencyPage(): void { $_GET['tab'] = 'currency'; $this->renderPage(); }
    public function renderPaymentPage(): void { $_GET['tab'] = 'payment'; $this->renderPage(); }
    public function renderCouponsPage(): void { $_GET['tab'] = 'coupons'; $this->renderPage(); }
    public function renderTaxPage(): void { $_GET['tab'] = 'tax'; $this->renderPage(); }

    protected function getMenuIcon(): string
    {
        $svg = '<svg width="21" height="17" viewBox="0 0 21 17" fill="none" xmlns="http://www.w3.org/2000/svg">'
            . '<path d="M0.526259 7.27514L1.91087 1.27514C2.01558 0.821409 2.41961 0.5 2.88527 0.5H5.03867C5.66481 0.5 6.13693 1.06888 6.02154 1.68429L4.89654 7.68429C4.80786 8.15726 4.39489 8.5 3.91367 8.5H1.50065C0.857565 8.5 0.381655 7.90176 0.526259 7.27514Z" stroke="#46b450" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M19.9609 7.27514L18.5763 1.27514C18.4716 0.821409 18.0676 0.5 17.6019 0.5H15.4485C14.8224 0.5 14.3503 1.06888 14.4657 1.68429L15.5907 7.68429C15.6793 8.15726 16.0923 8.5 16.5735 8.5H18.9865C19.6296 8.5 20.1055 7.90176 19.9609 7.27514Z" stroke="#46b450" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M4.99708 7.29427L6.25844 1.29427C6.35575 0.831393 6.76405 0.5 7.23705 0.5H9.2436C9.79588 0.5 10.2436 0.947715 10.2436 1.5V7.5C10.2436 8.05228 9.79588 8.5 9.2436 8.5H5.97569C5.34063 8.5 4.86643 7.91574 4.99708 7.29427Z" stroke="#46b450" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M15.4901 7.29427L14.2288 1.29427C14.1314 0.831393 13.7231 0.5 13.2501 0.5H11.2436C10.6913 0.5 10.2436 0.947715 10.2436 1.5V7.5C10.2436 8.05228 10.6913 8.5 11.2436 8.5H14.5115C15.1466 8.5 15.6208 7.91574 15.4901 7.29427Z" stroke="#46b450" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M3.2436 8.5V15.5C3.2436 16.0523 3.69131 16.5 4.2436 16.5H16.2436C16.7959 16.5 17.2436 16.0523 17.2436 15.5V8.5" stroke="#46b450" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M8.2436 16V13.5C8.2436 12.9477 8.69131 12.5 9.2436 12.5H11.2436C11.7959 12.5 12.2436 12.9477 12.2436 13.5V16" stroke="#46b450" stroke-linecap="round" stroke-linejoin="round"/>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function enqueueAssets(string $hook): void
    {
        $allowed = [
            'toplevel_page_' . self::PAGE_SLUG,
            'jankx_page_jankx-ecommerce-general',
            'jankx_page_jankx-ecommerce-currency',
            'jankx_page_jankx-ecommerce-payment',
            'jankx_page_jankx-ecommerce-coupons',
            'jankx_page_jankx-ecommerce-tax',
        ];

        if (!in_array($hook, $allowed)) {
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
            'sanitize_callback' => [$this, 'sanitizeStoreEmail'],
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

        // Taxes
        register_setting(self::OPTION_GROUP, 'jankx_tax_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ]);
        register_setting(self::OPTION_GROUP, 'jankx_tax_strategy', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'inclusive',
        ]);
        register_setting(self::OPTION_GROUP, 'jankx_tax_rates_raw', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => "VAT | 10 | 10",
        ]);

        // Per-currency format overrides (position, thousand_sep, decimal_sep, decimals riêng từng đồng)
        foreach (CurrencyManager::getAllCurrencies() as $code => $unused) {
            register_setting(self::OPTION_GROUP, 'jankx_currency_fmt_' . $code, [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitizeCurrencyFormatSettings'],
                'default'           => [],
            ]);
        }
    }

    /**
     * Sanitize per-currency format override array.
     * Trường 'position' rỗng có nghĩa "dùng global fallback".
     */
    public function sanitizeCurrencyFormatSettings($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $clean = [];
        $allowedPositions = ['left', 'right', 'left_space', 'right_space', ''];
        if (isset($value['position']) && in_array($value['position'], $allowedPositions, true)) {
            $clean['position'] = $value['position'];
        }
        if (array_key_exists('thousand_sep', $value)) {
            $clean['thousand_sep'] = sanitize_text_field($value['thousand_sep']);
        }
        if (array_key_exists('decimal_sep', $value)) {
            $clean['decimal_sep'] = sanitize_text_field($value['decimal_sep']);
        }
        if (isset($value['decimals'])) {
            $clean['decimals'] = absint($value['decimals']);
        }
        return $clean;
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

    public function sanitizeStoreEmail($value): string
    {
        // Handle null values - return the existing value or admin email
        if ($value === null || $value === '') {
            return get_option('admin_email', '');
        }
        return sanitize_email($value);
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
                    case 'tax':
                        $this->renderTaxTab();
                        break;
                    default:
                        $coreHandled = in_array($currentTab, ['general', 'currency', 'payment', 'coupons', 'tax'], true);

                        if (!$coreHandled) {
                            /**
                             * Action fired when the active tab is not a core tab.
                             *
                             * Third-party extensions render their tab content here:
                             *
                             *   add_action('jankx/ecommerce/settings/render_tab', function (string $tab) {
                             *       if ($tab !== 'my-tab') return;
                             *       // render HTML
                             *   });
                             *
                             * @param string $currentTab Active tab slug.
                             */
                            do_action('jankx/ecommerce/settings/render_tab', $currentTab);
                        } else {
                            $this->renderGeneralTab();
                        }
                }
                ?>
            </div>
        </div>
        <?php
    }

    protected function getTabs(): array
    {
        $tabs = [
            'general'  => __('Chung', 'jankx'),
            'currency' => __('Tiền tệ', 'jankx'),
            'payment'  => __('Thanh toán', 'jankx'),
            'coupons'  => __('Mã giảm giá', 'jankx'),
            'tax'      => __('Thuế', 'jankx'),
        ];

        /**
         * Filter the list of tabs shown on the Ecommerce Settings page.
         *
         * Third-party extensions can append their own tab by hooking here:
         *
         *   add_filter('jankx/ecommerce/settings/tabs', function (array $tabs) {
         *       $tabs['my-tab'] = __('My Tab', 'jankx');
         *       return $tabs;
         *   });
         *
         * @param array $tabs Associative array of slug => label.
         */
        return (array) apply_filters('jankx/ecommerce/settings/tabs', $tabs);
    }

    // ── GENERAL TAB ──────────────────────────────────────────────────

    protected function renderGeneralTab(): void
    {
        ?>
        <form method="post" action="options.php">
        <?php settings_fields(self::OPTION_GROUP); ?>
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
        ?>
        </form>
        <?php
    }

    // ── CURRENCY TAB ─────────────────────────────────────────────────

    protected function renderCurrencyTab(): void
    {

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

        <form method="post" action="options.php">
        <?php settings_fields(self::OPTION_GROUP); ?>

        <table class="widefat striped jankx-currency-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th style="width:50px;"><?php esc_html_e('Bật', 'jankx'); ?></th>
                    <th style="width:60px;"><?php esc_html_e('Mã', 'jankx'); ?></th>
                    <th style="width:50px;"></th>
                    <th><?php esc_html_e('Tên tiền tệ', 'jankx'); ?></th>
                    <th style="width:70px;"><?php esc_html_e('Ký hiệu', 'jankx'); ?></th>
                    <th style="width:100px;"><?php esc_html_e('Mặc định', 'jankx'); ?></th>
                    <th style="width:80px;" title="<?php esc_attr_e('Số chữ số thập phân', 'jankx'); ?>"><?php esc_html_e('Thập phân', 'jankx'); ?></th>
                    <th style="width:170px;"><?php esc_html_e('Vị trí ký hiệu', 'jankx'); ?></th>
                    <th style="width:70px;" title="<?php esc_attr_e('Dấu phân cách hàng nghìn', 'jankx'); ?>"><?php esc_html_e('Dấu nghìn', 'jankx'); ?></th>
                    <th style="width:70px;" title="<?php esc_attr_e('Dấu phân cách thập phân', 'jankx'); ?>"><?php esc_html_e('Dấu T.phân', 'jankx'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allCurrencies as $code => $currency):
                    $fmt           = (array) get_option('jankx_currency_fmt_' . $code, []);
                    $perDecimals   = isset($fmt['decimals'])     ? (int)$fmt['decimals']        : $currency['decimals'];
                    $perPosition   = $fmt['position']   ?? '';
                    $perThousand   = array_key_exists('thousand_sep', $fmt) ? $fmt['thousand_sep'] : $currency['thousand_sep'];
                    $perDecimalSep = array_key_exists('decimal_sep',  $fmt) ? $fmt['decimal_sep']  : $currency['decimal_sep'];
                ?>
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
                            <select name="jankx_currency_fmt_<?php echo esc_attr($code); ?>[decimals]" class="small-text">
                                <?php for ($i = 0; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($perDecimals, $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <td>
                            <select name="jankx_currency_fmt_<?php echo esc_attr($code); ?>[position]" class="small-text">
                                <option value="" <?php selected($perPosition, ''); ?>><?php esc_html_e('— Mặc định —', 'jankx'); ?></option>
                                <option value="left"        <?php selected($perPosition, 'left'); ?>><?php esc_html_e('Trước ($100)', 'jankx'); ?></option>
                                <option value="right"       <?php selected($perPosition, 'right'); ?>><?php esc_html_e('Sau (100$)', 'jankx'); ?></option>
                                <option value="left_space"  <?php selected($perPosition, 'left_space'); ?>><?php esc_html_e('Trước, cách ($ 100)', 'jankx'); ?></option>
                                <option value="right_space" <?php selected($perPosition, 'right_space'); ?>><?php esc_html_e('Sau, cách (100 $)', 'jankx'); ?></option>
                            </select>
                        </td>
                        <td>
                            <input type="text"
                                   name="jankx_currency_fmt_<?php echo esc_attr($code); ?>[thousand_sep]"
                                   value="<?php echo esc_attr($perThousand); ?>"
                                   class="small-text" style="width:45px;" maxlength="5">
                        </td>
                        <td>
                            <input type="text"
                                   name="jankx_currency_fmt_<?php echo esc_attr($code); ?>[decimal_sep]"
                                   value="<?php echo esc_attr($perDecimalSep); ?>"
                                   class="small-text" style="width:45px;" maxlength="5">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e('Định dạng mặc định (Fallback)', 'jankx'); ?></h3>
        <p class="description"><?php esc_html_e('Áp dụng khi đồng tiền không có tuỳ chỉnh riêng hoặc vị trí ký hiệu chọn "— Mặc định —".', 'jankx'); ?></p>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Vị trí ký hiệu mặc định', 'jankx'); ?></th>
                <td>
                    <select name="jankx_currency_position">
                        <option value="left"        <?php selected($position, 'left'); ?>><?php esc_html_e('Trước số tiền ($100)', 'jankx'); ?></option>
                        <option value="right"       <?php selected($position, 'right'); ?>><?php esc_html_e('Sau số tiền (100$)', 'jankx'); ?></option>
                        <option value="left_space"  <?php selected($position, 'left_space'); ?>><?php esc_html_e('Trước số tiền, cách ($ 100)', 'jankx'); ?></option>
                        <option value="right_space" <?php selected($position, 'right_space'); ?>><?php esc_html_e('Sau số tiền, cách (100 $)', 'jankx'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="jankx_currency_thousand_sep"><?php esc_html_e('Dấu phân cách hàng nghìn mặc định', 'jankx'); ?></label></th>
                <td>
                    <input type="text" id="jankx_currency_thousand_sep" name="jankx_currency_thousand_sep"
                           value="<?php echo esc_attr($thousandSep); ?>" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="jankx_currency_decimal_sep"><?php esc_html_e('Dấu phân cách thập phân mặc định', 'jankx'); ?></label></th>
                <td>
                    <input type="text" id="jankx_currency_decimal_sep" name="jankx_currency_decimal_sep"
                           value="<?php echo esc_attr($decimalSep); ?>" class="small-text">
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Xem trước', 'jankx'); ?></h3>
        <p class="description"><?php esc_html_e('Minh hoạ hiển thị số tiền "1.000.000" của đồng tiền mặc định (Base) sau khi áp dụng Format và Tỷ giá (nếu có cấu hình Converter).', 'jankx'); ?></p>
        <div class="jankx-price-preview" style="padding:16px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
            <?php 
            $managerConverer = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
            foreach ($enabled as $previewCode):
                $previewCurrency = CurrencyManager::getCurrency($previewCode);
                if (!$previewCurrency) continue;
            ?>
            <p>
                <strong><?php echo esc_html($previewCode); ?>:</strong>
                <?php echo esc_html($managerConverer->formatPriceWithConversion(1000000, $default, $previewCode)); ?>
            </p>
            <?php endforeach; ?>
        </div>

        <?php submit_button(); ?>
        </form>
        <?php
    }


    // ── PAYMENT TAB ──────────────────────────────────────────────────

    protected function renderPaymentTab(): void
    {
        // If a specific gateway is selected, show its settings form
        $gatewaySlug = sanitize_text_field($_GET['gateway'] ?? '');
        if ($gatewaySlug) {
            $this->renderGatewaySettings($gatewaySlug);
            return;
        }

        $enabledGateways = get_option('jankx_payment_gateways', []);

        // Get registered gateways from payment-system extension
        $registeredGateways = $this->getRegisteredGateways();
        ?>
        <h2><?php esc_html_e('Phương thức thanh toán', 'jankx'); ?></h2>
        <p class="description"><?php esc_html_e('Kích hoạt và cấu hình các phương thức thanh toán từ các extension.', 'jankx'); ?></p>

        <form method="post" action="options.php">
        <?php settings_fields(self::OPTION_GROUP); ?>

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
                $codConfig = get_option('jankx_built_in_gateway_cod', []);
                $bankConfig = get_option('jankx_built_in_gateway_bank_transfer', []);

                $builtInGateways = [
                    'cod' => [
                        'name' => __('Thanh toán khi nhận hàng (COD)', 'jankx'),
                        'description' => __('Khách hàng thanh toán bằng tiền mặt khi nhận hàng.', 'jankx'),
                        'icon' => '💵',
                        'available' => true,
                        'sandbox_mode' => false,
                        'settings_url' => admin_url('admin.php?page=jankx-ecommerce-settings&tab=payment&gateway=cod'),
                    ],
                    'bank_transfer' => [
                        'name' => __('Chuyển khoản ngân hàng', 'jankx'),
                        'description' => __('Khách hàng chuyển khoản trực tiếp vào tài khoản ngân hàng.', 'jankx'),
                        'icon' => '🏦',
                        'available' => !empty($bankConfig['bank_name']) && !empty($bankConfig['account_number']),
                        'sandbox_mode' => false,
                        'settings_url' => admin_url('admin.php?page=jankx-ecommerce-settings&tab=payment&gateway=bank_transfer'),
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
        </form>
        <?php
    }

    /**
     * Render the settings form for a specific gateway.
     */
    protected function renderGatewaySettings(string $gatewaySlug): void
    {
        // Handle built-in gateways (cod, bank_transfer)
        if (in_array($gatewaySlug, ['cod', 'bank_transfer'])) {
            $this->renderBuiltInGatewaySettings($gatewaySlug);
            return;
        }

        if (!class_exists('\\Jankx\\Extensions\\PaymentSystem\\Gateways\\GatewayManager')) {
            echo '<div class="wrap"><p>' . esc_html__('Payment System extension is not active.', 'jankx') . '</p></div>';
            return;
        }

        $manager = \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance();
        $gateway = $manager->get($gatewaySlug);

        if (!$gateway) {
            echo '<div class="wrap"><p>' . esc_html__('Gateway not found.', 'jankx') . '</p></div>';
            return;
        }

        // Handle form save
        if (isset($_POST['jankx_save_gateway_settings']) && check_admin_referer('jankx_gateway_settings_' . $gatewaySlug)) {
            $newConfig = $_POST['gateway'] ?? [];
            $oldConfig = $manager->getConfig($gatewaySlug);

            // Build field list from gateway
            $fields = $gateway->getSettingsFields();

            // For checkboxes: unchecked fields are missing from POST, set them to ''
            foreach ($fields as $key => $field) {
                if (($field['type'] ?? 'text') === 'checkbox' && !isset($newConfig[$key])) {
                    $newConfig[$key] = '';
                }
            }

            // Merge: saved values override defaults, but unchecked checkboxes clear old values
            $merged = array_merge($oldConfig, $newConfig);

            $saved = $manager->saveConfig($gatewaySlug, $merged);
            if ($saved) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'jankx') . '</p></div>';
                $gateway = $manager->get($gatewaySlug);
            }
        }

        $config = $manager->getConfig($gatewaySlug);
        $fields = $gateway->getSettingsFields();
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=payment')); ?>"
                   style="text-decoration:none;">&larr;</a>
                <?php
                printf(
                    esc_html__('Cài đặt %s', 'jankx'),
                    esc_html($gateway->getName())
                );
                ?>
            </h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=payment&gateway=' . $gatewaySlug)); ?>">
                <?php wp_nonce_field('jankx_gateway_settings_' . $gatewaySlug); ?>

                <table class="form-table">
                    <?php foreach ($fields as $key => $field): ?>
                        <tr>
                            <th scope="row">
                                <label for="gateway_<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($field['label'] ?? $key); ?>
                                </label>
                            </th>
                            <td>
                                <?php
                                $value = $config[$key] ?? ($field['default'] ?? '');
                                $inputId = 'gateway_' . $key;

                                switch ($field['type'] ?? 'text') {
                                    case 'checkbox':
                                        ?>
                                        <label>
                                            <input type="checkbox"
                                                   id="<?php echo esc_attr($inputId); ?>"
                                                   name="gateway[<?php echo esc_attr($key); ?>]"
                                                   value="1"
                                                   <?php checked(!empty($value)); ?>>
                                            <?php echo esc_html($field['description'] ?? ''); ?>
                                        </label>
                                        <?php
                                        break;

                                    case 'select':
                                        ?>
                                        <select id="<?php echo esc_attr($inputId); ?>"
                                                name="gateway[<?php echo esc_attr($key); ?>]">
                                            <?php foreach (($field['options'] ?? []) as $optVal => $optLabel): ?>
                                                <option value="<?php echo esc_attr($optVal); ?>"
                                                    <?php selected($value, $optVal); ?>>
                                                    <?php echo esc_html($optLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php
                                        break;

                                    case 'password':
                                        ?>
                                        <input type="password"
                                               id="<?php echo esc_attr($inputId); ?>"
                                               name="gateway[<?php echo esc_attr($key); ?>]"
                                               value="<?php echo esc_attr($value); ?>"
                                               class="regular-text"
                                               autocomplete="off">
                                        <?php
                                        break;

                                    default:
                                        ?>
                                        <input type="text"
                                               id="<?php echo esc_attr($inputId); ?>"
                                               name="gateway[<?php echo esc_attr($key); ?>]"
                                               value="<?php echo esc_attr($value); ?>"
                                               class="regular-text">
                                        <?php
                                }

                                if (!empty($field['description']) && ($field['type'] ?? 'text') !== 'checkbox'):
                                    ?>
                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                    <?php
                                endif;
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(__('Lưu cài đặt', 'jankx'), 'primary', 'jankx_save_gateway_settings'); ?>
            </form>
        </div>
        <?php
    }

    protected function renderBuiltInGatewaySettings(string $gatewaySlug): void
    {
        $optionKey = 'jankx_built_in_gateway_' . $gatewaySlug;

        // Handle form save
        if (isset($_POST['jankx_save_gateway_settings']) && check_admin_referer('jankx_gateway_settings_' . $gatewaySlug)) {
            update_option($optionKey, $_POST['gateway'] ?? []);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'jankx') . '</p></div>';
        }

        $config = get_option($optionKey, []);
        $fields = $this->getBuiltInGatewayFields($gatewaySlug);
        $gatewayName = $gatewaySlug === 'cod'
            ? __('Thanh toán khi nhận hàng (COD)', 'jankx')
            : __('Chuyển khoản ngân hàng', 'jankx');
        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=payment')); ?>"
                   style="text-decoration:none;">&larr;</a>
                <?php printf(esc_html__('Cài đặt %s', 'jankx'), esc_html($gatewayName)); ?>
            </h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&tab=payment&gateway=' . $gatewaySlug)); ?>">
                <?php wp_nonce_field('jankx_gateway_settings_' . $gatewaySlug); ?>

                <table class="form-table">
                    <?php foreach ($fields as $key => $field): ?>
                        <tr>
                            <th scope="row">
                                <label for="gateway_<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($field['label']); ?>
                                </label>
                            </th>
                            <td>
                                <?php
                                $value = $config[$key] ?? ($field['default'] ?? '');
                                $inputId = 'gateway_' . $key;

                                if (($field['type'] ?? 'text') === 'textarea'):
                                ?>
                                    <textarea id="<?php echo esc_attr($inputId); ?>"
                                              name="gateway[<?php echo esc_attr($key); ?>]"
                                              rows="4" cols="50"
                                              class="large-text"><?php echo esc_textarea($value); ?></textarea>
                                <?php else: ?>
                                    <input type="<?php echo esc_attr($field['type'] ?? 'text'); ?>"
                                           id="<?php echo esc_attr($inputId); ?>"
                                           name="gateway[<?php echo esc_attr($key); ?>]"
                                           value="<?php echo esc_attr($value); ?>"
                                           class="regular-text">
                                <?php endif; ?>

                                <?php if (!empty($field['description'])): ?>
                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(__('Lưu cài đặt', 'jankx'), 'primary', 'jankx_save_gateway_settings'); ?>
            </form>
        </div>
        <?php
    }

    protected function getBuiltInGatewayFields(string $gatewaySlug): array
    {
        if ($gatewaySlug === 'cod') {
            return [
                'description' => [
                    'label'       => __('Mô tả cho khách hàng', 'jankx'),
                    'type'        => 'textarea',
                    'default'     => __('Thanh toán bằng tiền mặt khi nhận hàng. Vui lòng chuẩn bị đúng số tiền.', 'jankx'),
                    'description' => __('Hiển thị trên trang thanh toán và email xác nhận.', 'jankx'),
                ],
                'extra_fee' => [
                    'label'       => __('Phí COD (₫)', 'jankx'),
                    'type'        => 'text',
                    'default'     => '0',
                    'description' => __('Phụ phí nếu có (0 = không phụ phí).', 'jankx'),
                ],
                'min_amount' => [
                    'label'       => __('Giá trị tối thiểu (₫)', 'jankx'),
                    'type'        => 'text',
                    'default'     => '0',
                    'description' => __('Không hiển thị COD nếu đơn hàng thấp hơn giá trị này (0 = luôn hiển thị).', 'jankx'),
                ],
                'max_amount' => [
                    'label'       => __('Giá trị tối đa (₫)', 'jankx'),
                    'type'        => 'text',
                    'default'     => '0',
                    'description' => __('Không hiển thị COD nếu đơn hàng cao hơn giá trị này (0 = không giới hạn).', 'jankx'),
                ],
            ];
        }

        // bank_transfer
        return [
            'bank_name' => [
                'label'   => __('Tên ngân hàng', 'jankx'),
                'type'    => 'text',
                'default' => '',
            ],
            'account_number' => [
                'label'   => __('Số tài khoản', 'jankx'),
                'type'    => 'text',
                'default' => '',
            ],
            'account_holder' => [
                'label'   => __('Chủ tài khoản', 'jankx'),
                'type'    => 'text',
                'default' => '',
            ],
            'branch' => [
                'label'   => __('Chi nhánh', 'jankx'),
                'type'    => 'text',
                'default' => '',
            ],
            'transfer_content' => [
                'label'       => __('Nội dung chuyển khoản', 'jankx'),
                'type'        => 'textarea',
                'default'     => __('Vui lòng ghi đúng nội dung chuyển khoản để chúng tôi xác nhận đơn hàng sớm nhất.', 'jankx'),
                'description' => __('Hướng dẫn khách hàng điền nội dung CK.', 'jankx'),
            ],
            'instructions' => [
                'label'       => __('Hướng dẫn chuyển khoản', 'jankx'),
                'type'        => 'textarea',
                'default'     => '',
                'description' => __('Thông tin bổ sung hiển thị cho khách hàng (ví dụ: thông tin chuyển khoản nhanh 24/7, QR code...).', 'jankx'),
            ],
        ];
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
        $enabled = get_option('jankx_coupons_enabled', true);
        ?>
        <h2><?php esc_html_e('Mã giảm giá', 'jankx'); ?></h2>

        <form method="post" action="options.php">
        <?php settings_fields(self::OPTION_GROUP); ?>

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
        </form>
        <?php
    }

    // ── TAX TAB ──────────────────────────────────────────────────────

    protected function renderTaxTab(): void
    {
        ?>
        <h2><?php esc_html_e('Cấu hình Thuế', 'jankx'); ?></h2>
        <p class="description"><?php esc_html_e('Định cấu hình cách tính thuế cho giỏ hàng và thanh toán.', 'jankx'); ?></p>

        <?php do_action('jankx/ecommerce/settings/tax/before_form'); ?>

        <?php
        /**
         * Filter cho phép các extensions (ví dụ React App) đè toàn bộ form.
         * Nếu Filter trả về true, ta sẽ render form từ custom action thay vì form SSR mặc định.
         */
        if (apply_filters('jankx/ecommerce/settings/tax/override_render', false)) {
            do_action('jankx/ecommerce/settings/tax/custom_render');
            do_action('jankx/ecommerce/settings/tax/after_form');
            return;
        }
        ?>

        <form method="post" action="options.php">
            <?php settings_fields(self::OPTION_GROUP); ?>
            <table class="form-table">
                <tbody>
                    <?php
                    /**
                     * Render từng field một cách cơ động.
                     * Dễ dàng gỡ bỏ: `remove_action('jankx/ecommerce/settings/tax/render_fields', [$admin, 'renderTaxFieldRates'], 30)` 
                     */
                    do_action('jankx/ecommerce/settings/tax/render_fields');
                    ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>

        <?php do_action('jankx/ecommerce/settings/tax/after_form'); ?>
        <?php
    }

    public function renderTaxFieldEnable(): void
    {
        $enabled = get_option('jankx_tax_enabled', false);
        ?>
        <tr>
            <th scope="row"><?php esc_html_e('Kích hoạt tính thuế', 'jankx'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="jankx_tax_enabled" value="1" <?php checked($enabled, true); ?>>
                    <?php esc_html_e('Bật việc tính toán thuế ở giỏ hàng và trang thanh toán', 'jankx'); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    public function renderTaxFieldStrategy(): void
    {
        $strategy = get_option('jankx_tax_strategy', 'inclusive');
        ?>
        <tr>
            <th scope="row"><?php esc_html_e('Chiến lược tính thuế', 'jankx'); ?></th>
            <td>
                <label style="display:block; margin-bottom:10px;">
                    <input type="radio" name="jankx_tax_strategy" value="inclusive" <?php checked($strategy, 'inclusive'); ?>>
                    <strong><?php esc_html_e('Đã bao gồm trong giá (Inclusive - Mặc định)', 'jankx'); ?></strong>
                    <p class="description" style="margin-top:2px;">
                        <?php esc_html_e('Giá hiển thị và giá khách phải trả là giá cuối cùng. Hệ thống tự tách số tiền thuế (Khuyên dùng để tránh làm khách hàng sợ vì tổng giá trị thanh toán cao lên).', 'jankx'); ?>
                    </p>
                </label>
                <label style="display:block;">
                    <input type="radio" name="jankx_tax_strategy" value="exclusive" <?php checked($strategy, 'exclusive'); ?>>
                    <strong><?php esc_html_e('Cộng thêm vào tổng đơn (Exclusive)', 'jankx'); ?></strong>
                    <p class="description" style="margin-top:2px;">
                        <?php esc_html_e('Tính thuế đè thêm vào giá các mặt hàng. Tổng thanh toán = Tổng giá trị món hàng + Từng loại Thuế.', 'jankx'); ?>
                    </p>
                </label>
            </td>
        </tr>
        <?php
    }

    public function renderTaxFieldRates(): void
    {
        $ratesRaw = get_option('jankx_tax_rates_raw', "VAT | 10 | 10");
        ?>
        <tr>
            <th scope="row">
                <label for="jankx_tax_rates_raw"><?php esc_html_e('Các loại thuế áp dụng', 'jankx'); ?></label>
            </th>
            <td>
                <textarea name="jankx_tax_rates_raw" id="jankx_tax_rates_raw" rows="5" class="large-text code"><?php echo esc_textarea($ratesRaw); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Nhập mỗi loại thuế nằm trên 1 dòng theo cú pháp (SSR Fallback): ', 'jankx'); ?> <code>Tên thuế | Tỉ lệ phần trăm | Độ ưu tiên tính</code><br>
                    <?php esc_html_e('Ví dụ: ', 'jankx'); ?> <code>VAT | 10 | 10</code> <?php esc_html_e('(Nghĩa là thuế VAT 10% với priority 10)', 'jankx'); ?>
                </p>
            </td>
        </tr>
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
