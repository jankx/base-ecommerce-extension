<?php
namespace Jankx\Extensions\Ecommerce\Admin;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderModel;

/**
 * Custom Orders admin page using dedicated database tables.
 * Matches the old CPT-based UI layout.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class OrderAdmin
{
    const PAGE_SLUG = 'jankx-orders';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'handleActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Đơn hàng', 'jankx'),
            __('Đơn hàng', 'jankx'),
            'edit_posts',
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

        $extensionDir = dirname(__DIR__, 2);
        $cssFile = $extensionDir . '/assets/admin.css';

        if (file_exists($cssFile)) {
            $cssUrl = get_stylesheet_directory_uri() . '/extensions/base-ecommerce/assets/admin.css';
            wp_enqueue_style('jankx-ecommerce-admin', $cssUrl, [], filemtime($cssFile));
        }
    }

    public function handleActions(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== self::PAGE_SLUG) {
            return;
        }

        if (isset($_GET['view'])) {
            $orderId = absint($_GET['view']);
            if ($orderId && isset($_POST['jankx_update_order_status']) && check_admin_referer('jankx_update_order_status_' . $orderId)) {
                $this->handleStatusUpdate($orderId);
            }
        }
    }

    protected function handleStatusUpdate(int $orderId): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $newStatus = sanitize_text_field($_POST['order_status'] ?? '');
        $note = sanitize_textarea_field($_POST['order_note'] ?? '');
        $trackingNumber = sanitize_text_field($_POST['tracking_number'] ?? '');

        if ($newStatus) {
            OrderModel::updateStatus($orderId, $newStatus, $note);
        }

        // Save tracking number if provided
        if ($trackingNumber !== '') {
            OrderModel::update($orderId, ['tracking_number' => $trackingNumber]);
        }

        wp_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=' . $orderId . '&updated=1'));
        exit;
    }

    public function renderPage(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if (isset($_GET['view'])) {
            $this->renderDetailPage(absint($_GET['view']));
        } else {
            $this->renderListPage();
        }
    }

    // ── LIST PAGE ─────────────────────────────────────────

    protected function renderListPage(): void
    {
        $currentPage = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;
        $statusFilter = sanitize_text_field($_GET['order_status'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');

        $args = [
            'per_page' => $perPage,
            'page'     => $currentPage,
        ];

        if ($statusFilter) {
            $args['status'] = $statusFilter;
        }

        if ($search) {
            $args['search'] = $search;
        }

        $orders = Order::query($args);
        $total = Order::countOrders($args);
        $totalPages = ceil($total / $perPage);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Đơn hàng', 'jankx'); ?></h1>

            <div style="float: right;">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
                    <select name="order_status" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e('Tất cả trạng thái', 'jankx'); ?></option>
                        <?php foreach (Order::getStatusLabels() as $status => $label): ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($statusFilter, $status); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <form method="get" style="display: inline-block; margin-left: 8px;">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Tìm kiếm...', 'jankx'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Tìm', 'jankx'); ?></button>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-order_number" style="width: 120px;"><?php esc_html_e('Mã đơn', 'jankx'); ?></th>
                        <th class="column-customer"><?php esc_html_e('Khách hàng', 'jankx'); ?></th>
                        <th class="column-items"><?php esc_html_e('Sản phẩm', 'jankx'); ?></th>
                        <th class="column-total" style="width: 120px;"><?php esc_html_e('Tổng', 'jankx'); ?></th>
                        <th class="column-status" style="width: 100px;"><?php esc_html_e('Trạng thái', 'jankx'); ?></th>
                        <th class="column-date" style="width: 150px;"><?php esc_html_e('Ngày tạo', 'jankx'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="6"><?php esc_html_e('Không có đơn hàng nào.', 'jankx'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=' . $order->getId())); ?>">
                                        <strong><?php echo esc_html($order->getOrderNumber()); ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($order->getCustomerName()): ?>
                                        <strong><?php echo esc_html($order->getCustomerName()); ?></strong><br>
                                    <?php endif; ?>
                                    <?php if ($order->getCustomerEmail()): ?>
                                        <a href="mailto:<?php echo esc_attr($order->getCustomerEmail()); ?>"><?php echo esc_html($order->getCustomerEmail()); ?></a><br>
                                    <?php endif; ?>
                                    <?php if ($order->getCustomerPhone()): ?>
                                        <span class="description"><?php echo esc_html($order->getCustomerPhone()); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $summaries = [];
                                    foreach ($order->getItems() as $item) {
                                        $summaries[] = esc_html($item->getName()) . ' &times; ' . (int) $item->getQuantity();
                                    }
                                    echo $summaries ? implode('<br>', $summaries) : '&mdash;';
                                    ?>
                                </td>
                                <td style="text-align: right;">
                                    <strong><?php echo esc_html(CurrencyManager::formatPrice($order->getTotal())); ?></strong>
                                    <?php if ($order->getPaymentMethod()): ?>
                                        <div class="description"><?php echo esc_html($order->getPaymentMethod()); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="jankx-order-badge jankx-order-badge--<?php echo esc_attr($order->getStatus()); ?>">
                                        <?php echo esc_html(Order::getStatusLabel($order->getStatus())); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($order->getDateCreated()); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base'    => add_query_arg('paged', '%#%'),
                            'format'  => '',
                            'current' => $currentPage,
                            'total'   => $totalPages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── DETAIL PAGE (matches old CPT edit screen) ─────────

    protected function renderDetailPage(int $orderId): void
    {
        $order = new Order($orderId);
        if (!$order->getId()) {
            echo '<div class="wrap"><p>' . esc_html__('Đơn hàng không tồn tại.', 'jankx') . '</p></div>';
            return;
        }

        $updated = isset($_GET['updated']);
        $items = $order->getItems();
        $history = $order->getHistory();
        $notes = $order->getNotes();
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(sprintf(__('Edit Order "%s"', 'jankx'), $order->getOrderNumber())); ?>
            </h1>

            <?php if ($updated): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Đơn hàng đã được cập nhật.', 'jankx'); ?></p></div>
            <?php endif; ?>

            <div id="poststuff">
                <div class="jankx-order-layout">

                    <!-- Main content -->
                    <div class="jankx-order-main">

                        <!-- Order Details Meta Box -->
                        <div id="jankx_order_details" class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Order Details', 'jankx'); ?></span></h2>
                            <div class="inside">
                                <!-- Summary bar -->
                                <div class="jankx-order-summary-bar">
                                    <div class="jankx-order-summary-bar__item">
                                        <span class="jankx-order-summary-bar__label"><?php esc_html_e('STATUS', 'jankx'); ?></span>
                                        <span class="jankx-order-summary-bar__value">
                                            <span class="jankx-order-badge jankx-order-badge--<?php echo esc_attr($order->getStatus()); ?>">
                                                <?php echo esc_html(strtoupper(Order::getStatusLabel($order->getStatus()))); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="jankx-order-summary-bar__item">
                                        <span class="jankx-order-summary-bar__label"><?php esc_html_e('ORDER TOTAL', 'jankx'); ?></span>
                                        <span class="jankx-order-summary-bar__value jankx-order-summary-bar__value--highlight">
                                            <?php echo esc_html(CurrencyManager::formatPrice($order->getTotal())); ?>
                                        </span>
                                    </div>
                                    <div class="jankx-order-summary-bar__item">
                                        <span class="jankx-order-summary-bar__label"><?php esc_html_e('ITEMS', 'jankx'); ?></span>
                                        <span class="jankx-order-summary-bar__value"><?php echo count($items); ?></span>
                                    </div>
                                    <div class="jankx-order-summary-bar__item">
                                        <span class="jankx-order-summary-bar__label"><?php esc_html_e('PAYMENT', 'jankx'); ?></span>
                                        <span class="jankx-order-summary-bar__value"><?php echo esc_html($order->getPaymentMethod() ?: '—'); ?></span>
                                    </div>
                                    <div class="jankx-order-summary-bar__item">
                                        <span class="jankx-order-summary-bar__label"><?php esc_html_e('CREATED', 'jankx'); ?></span>
                                        <span class="jankx-order-summary-bar__value">
                                            <?php
                                            $date = $order->getDateCreated();
                                            if ($date) {
                                                echo esc_html(date_i18n(get_option('date_format'), strtotime($date)));
                                                echo '<br><small>' . esc_html(date_i18n(get_option('time_format'), strtotime($date))) . '</small>';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($order->getTrackingNumber()): ?>
                                        <div class="jankx-order-summary-bar__item">
                                            <span class="jankx-order-summary-bar__label"><?php esc_html_e('TRACKING', 'jankx'); ?></span>
                                            <span class="jankx-order-summary-bar__value jankx-order-summary-bar__value--highlight">
                                                <?php echo esc_html($order->getTrackingNumber()); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Customer Information -->
                                <div class="jankx-order-card" style="margin: 12px;">
                                    <div class="jankx-order-card__header">
                                        <span class="jankx-order-card__header-icon">&#128100;</span>
                                        <h3 class="jankx-order-card__title"><?php esc_html_e('CUSTOMER INFORMATION', 'jankx'); ?></h3>
                                    </div>
                                    <div class="jankx-order-card__body">
                                        <div class="jankx-customer-grid">
                                            <div class="jankx-customer-grid__item">
                                                <div class="jankx-customer-grid__label"><?php esc_html_e('FULL NAME', 'jankx'); ?></div>
                                                <div class="jankx-customer-grid__value <?php echo !$order->getCustomerName() ? 'jankx-customer-grid__value--empty' : ''; ?>">
                                                    <?php echo esc_html($order->getCustomerName() ?: '—'); ?>
                                                </div>
                                            </div>
                                            <div class="jankx-customer-grid__item">
                                                <div class="jankx-customer-grid__label"><?php esc_html_e('EMAIL', 'jankx'); ?></div>
                                                <div class="jankx-customer-grid__value <?php echo !$order->getCustomerEmail() ? 'jankx-customer-grid__value--empty' : ''; ?>">
                                                    <?php if ($order->getCustomerEmail()): ?>
                                                        <a href="mailto:<?php echo esc_attr($order->getCustomerEmail()); ?>"><?php echo esc_html($order->getCustomerEmail()); ?></a>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="jankx-customer-grid__item">
                                                <div class="jankx-customer-grid__label"><?php esc_html_e('PHONE', 'jankx'); ?></div>
                                                <div class="jankx-customer-grid__value <?php echo !$order->getCustomerPhone() ? 'jankx-customer-grid__value--empty' : ''; ?>">
                                                    <?php echo esc_html($order->getCustomerPhone() ?: '—'); ?>
                                                </div>
                                            </div>
                                            <div class="jankx-customer-grid__item">
                                                <div class="jankx-customer-grid__label"><?php esc_html_e('ADDRESS', 'jankx'); ?></div>
                                                <div class="jankx-customer-grid__value <?php echo !$order->getCustomerAddress() ? 'jankx-customer-grid__value--empty' : ''; ?>">
                                                    <?php echo esc_html($order->getCustomerAddress() ?: '—'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Items -->
                                <div class="jankx-order-card" style="margin: 12px;">
                                    <div class="jankx-order-card__header">
                                        <span class="jankx-order-card__header-icon">&#128722;</span>
                                        <h3 class="jankx-order-card__title"><?php esc_html_e('ORDER ITEMS', 'jankx'); ?></h3>
                                    </div>
                                    <div class="jankx-order-card__body">
                                        <table class="jankx-order-items-table">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('PRODUCT', 'jankx'); ?></th>
                                                    <th><?php esc_html_e('QTY', 'jankx'); ?></th>
                                                    <th><?php esc_html_e('UNIT PRICE', 'jankx'); ?></th>
                                                    <th><?php esc_html_e('TOTAL', 'jankx'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($items)): ?>
                                                    <tr><td colspan="4" style="text-align: center; color: #a7aaad;"><?php esc_html_e('No items.', 'jankx'); ?></td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($items as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="item-name"><?php echo esc_html($item->getName()); ?></span>
                                                                <?php if ($item->getProductId()): ?>
                                                                    <div class="item-meta">ID: <?php echo esc_html($item->getProductId()); ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><span class="qty-badge"><?php echo esc_html($item->getQuantity()); ?></span></td>
                                                            <td class="unit-price"><?php echo esc_html(CurrencyManager::formatPrice($item->getUnitPrice())); ?></td>
                                                            <td class="item-total"><?php echo esc_html(CurrencyManager::formatPrice($item->getTotal())); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="total-label"><?php esc_html_e('ORDER TOTAL', 'jankx'); ?></td>
                                                    <td class="total-value"><?php echo esc_html(CurrencyManager::formatPrice($order->getTotal())); ?></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <!-- Order History -->
                                <?php if (!empty($history)): ?>
                                    <div id="jankx_order_history" class="postbox">
                                        <h2 class="hndle"><span><?php esc_html_e('Order History', 'jankx'); ?></span></h2>
                                        <div class="inside">
                                            <ul class="jankx-order-history">
                                                <?php foreach (array_reverse($history) as $entry): ?>
                                                    <li class="jankx-history-entry">
                                                        <div class="jankx-history-head">
                                                            <?php
                                                            $userId = $entry['user_id'] ?? 0;
                                                            if ($userId) {
                                                                $user = get_userdata($userId);
                                                                if ($user) {
                                                                    echo get_avatar($user->ID, 24);
                                                                }
                                                            }
                                                            ?>
                                                            <span class="jankx-history-user">
                                                                <?php
                                                                if ($userId) {
                                                                    $user = get_userdata($userId);
                                                                    echo $user ? esc_html($user->display_name) : '#' . $userId;
                                                                }
                                                                ?>
                                                            </span>
                                                            <span class="jankx-history-action">
                                                                <?php
                                                                $action = $entry['action'] ?? '';
                                                                if ($action === 'status_changed') {
                                                                    printf(
                                                                        esc_html__('changed status from %s to %s', 'jankx'),
                                                                        '<strong>' . esc_html(Order::getStatusLabel($entry['from'] ?? '')) . '</strong>',
                                                                        '<strong>' . esc_html(Order::getStatusLabel($entry['to'] ?? '')) . '</strong>'
                                                                    );
                                                                } else {
                                                                    echo esc_html(ucfirst($action));
                                                                }
                                                                ?>
                                                            </span>
                                                            <span class="jankx-history-time"><?php echo esc_html($entry['created_at'] ?? ''); ?></span>
                                                        </div>
                                                        <?php if (!empty($entry['note'])): ?>
                                                            <div class="jankx-history-note"><?php echo esc_html($entry['note']); ?></div>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="jankx-order-sidebar">

                        <!-- Update Status Meta Box -->
                        <div id="jankx_order_status" class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Update Status', 'jankx'); ?></span></h2>
                            <div class="inside">
                                <div class="jankx-status-current">
                                    <span class="jankx-status-current__label"><?php esc_html_e('CURRENT STATUS', 'jankx'); ?></span>
                                    <span class="jankx-order-badge jankx-order-badge--<?php echo esc_attr($order->getStatus()); ?>">
                                        <?php echo esc_html(strtoupper(Order::getStatusLabel($order->getStatus()))); ?>
                                    </span>
                                </div>
                                <form method="post" class="jankx-status-form">
                                    <?php wp_nonce_field('jankx_update_order_status_' . $orderId); ?>
                                    <div class="form-group">
                                        <label for="order_status"><?php esc_html_e('MOVE TO', 'jankx'); ?></label>
                                        <select name="order_status" id="order_status">
                                            <?php foreach (Order::getAllowedStatusTransitionsFor($order->getStatus()) as $status): ?>
                                                <option value="<?php echo esc_attr($status); ?>" <?php selected($order->getStatus(), $status); ?>>
                                                    <?php echo esc_html(Order::getStatusLabel($status)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Tracking Number (visible when current status is shipping or target is shipping/completed) -->
                                    <?php
                                    $showTracking = $order->getStatus() === Order::STATUS_SHIPPING
                                        || in_array(Order::STATUS_SHIPPING, Order::getAllowedStatusTransitionsFor($order->getStatus()), true);
                                    ?>
                                    <div class="form-group" id="tracking-number-group" style="<?php echo $showTracking ? '' : 'display:none;'; ?>">
                                        <label for="tracking_number"><?php esc_html_e('MÃ VẬN ĐƠN', 'jankx'); ?></label>
                                        <input type="text" name="tracking_number" id="tracking_number"
                                               value="<?php echo esc_attr($order->getTrackingNumber()); ?>"
                                               placeholder="<?php esc_attr_e('Nhập mã vận đơn...', 'jankx'); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="order_note"><?php esc_html_e('NOTE', 'jankx'); ?></label>
                                        <textarea name="order_note" id="order_note" rows="4" placeholder="<?php esc_attr_e('Optional note...', 'jankx'); ?>"></textarea>
                                    </div>
                                    <button type="submit" name="jankx_update_order_status" class="button-update"><?php esc_html_e('Update Status', 'jankx'); ?></button>
                                </form>
                            </div>
                        </div>

                        <!-- Publish Meta Box (mimics WP Publish box) -->
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Publish', 'jankx'); ?></span></h2>
                            <div class="inside">
                                <div style="padding: 12px 14px;">
                                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <span>&#128273;</span>
                                        <span><?php esc_html_e('Status:', 'jankx'); ?> <strong><?php echo esc_html(ucfirst($order->getStatus())); ?></strong></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                        <span>&#128065;</span>
                                        <span><?php esc_html_e('Visibility:', 'jankx'); ?> <strong><?php esc_html_e('Public', 'jankx'); ?></strong></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                                        <span>&#128197;</span>
                                        <span>
                                            <?php
                                            $date = $order->getDateCreated();
                                            if ($date) {
                                                printf(
                                                    esc_html__('Published on: %s', 'jankx'),
                                                    esc_html(date_i18n(get_option('date_format') . ' \a\t ' . get_option('time_format'), strtotime($date)))
                                                );
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var statusSelect = document.getElementById('order_status');
            var trackingGroup = document.getElementById('tracking-number-group');
            if (!statusSelect || !trackingGroup) return;

            var showStatuses = ['shipping'];
            statusSelect.addEventListener('change', function(){
                trackingGroup.style.display = showStatuses.indexOf(this.value) !== -1 ? '' : 'none';
            });
        })();
        </script>
        <?php
    }

    protected function getAssetUrl(string $file): string
    {
        $extensionUrl = defined('JANKX_ECOMMERCE_EXT_URL')
            ? JANKX_ECOMMERCE_EXT_URL
            : plugins_url('assets/' . $file, dirname(__DIR__, 2) . '/EcommerceExtension.php');

        return $extensionUrl . '/assets/' . $file;
    }

    protected function getAssetPath(string $file): string
    {
        return dirname(__DIR__, 2) . '/assets/' . $file;
    }
}
