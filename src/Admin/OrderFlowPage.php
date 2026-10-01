<?php
namespace Jankx\Extensions\Ecommerce\Admin;

use Jankx\Extensions\Ecommerce\Admin\EcommerceSettingsPage;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderFlow;

/**
 * Trang admin cấu hình luồng trạng thái đơn hàng (Kanban/Trello, React).
 * Sub-menu trực thuộc trang cài đặt Ecommerce.
 */
class OrderFlowPage
{
    const PAGE_SLUG = 'jankx-ecommerce-flow';
    const HANDLE    = 'jankx-order-flow';

    public function register(): void
    {
        // Sau EcommerceSettingsPage (priority 99) để nằm sau các sub-menu gốc.
        add_action('admin_menu', [$this, 'addSubmenuPage'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addSubmenuPage(): void
    {
        add_submenu_page(
            EcommerceSettingsPage::PAGE_SLUG,
            __('Luồng trạng thái', 'base-ecommerce'),
            __('Luồng trạng thái', 'base-ecommerce'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'base-ecommerce'));
        }
        ?>
        <div class="wrap jankx-order-flow-wrap">
            <h1><?php esc_html_e('Luồng trạng thái đơn hàng', 'base-ecommerce'); ?></h1>
            <p class="description">
                <?php esc_html_e('Kéo-thả trạng thái giữa các nhóm, bật/tắt mũi tên chuyển đổi giữa nhóm, rồi bấm Lưu. Đơn hàng chỉ được chuyển trạng thái theo luồng này.', 'base-ecommerce'); ?>
            </p>
            <div id="jankx-order-flow-app" class="jankx-order-flow-app">
                <div class="jankx-order-flow-loading"><?php esc_html_e('Đang tải bảng luồng…', 'base-ecommerce'); ?></div>
            </div>
        </div>
        <?php
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        $extRoot = dirname(__DIR__, 2);
        $jsFile  = $extRoot . '/admin/order-flow/build/index.js';
        $cssFile = $extRoot . '/assets/order-flow.css';

        // URL của extension: nằm trong theme đang active (xem OrderAdmin::enqueueAssets).
        $extUrl = defined('JANKX_ECOMMERCE_EXT_URL')
            ? JANKX_ECOMMERCE_EXT_URL
            : false;
        if ($extUrl === false) {
            $themeDir = get_stylesheet_directory();
            if (strpos($extRoot, $themeDir) === 0) {
                $extUrl = get_stylesheet_directory_uri() . substr($extRoot, strlen($themeDir));
            } else {
                $extUrl = get_template_directory_uri() . '/extensions/base-ecommerce';
            }
        }

        $dependencies = ['react', 'react-dom'];
        $version      = null;
        $assetFile    = $extRoot . '/admin/order-flow/build/index.asset.php';
        if (file_exists($assetFile)) {
            $asset = include $assetFile;
            $dependencies = $asset['dependencies'] ?? $dependencies;
            $version      = $asset['version'] ?? null;
        }
        if ($version === null) {
            $version = file_exists($jsFile) ? filemtime($jsFile) : null;
        }

        wp_enqueue_script(
            self::HANDLE,
            $extUrl . '/admin/order-flow/build/index.js',
            $dependencies,
            $version,
            true
        );

        wp_enqueue_style(
            self::HANDLE . '-style',
            $extUrl . '/assets/order-flow.css',
            [],
            file_exists($cssFile) ? filemtime($cssFile) : null
        );

        wp_localize_script(self::HANDLE, 'JankxOrderFlow', [
            'restUrl' => rest_url('jankx/ecommerce/v1/order-flow'),
            'nonce'   => wp_create_nonce('wp_rest'),
            'flow'    => OrderFlow::getFlow(),
            'defaultFlow' => OrderFlow::defaultFlow(),
            'statuses' => Order::getStatusLabels(),
            'statusKeys' => array_keys(Order::getStatusLabels()),
        ]);
    }
}
