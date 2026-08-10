<?php
namespace Jankx\Extensions\Ecommerce\Admin;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Order\OrderPostType;

/**
 * Professional Orders management screen (wp-admin).
 *
 * Adds a rich list table (order number, customer, items, total, status,
 * handler, date), a status filter, a status-transition meta box that records
 * who handled the order, and a full history timeline.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class OrderAdmin
{
    public function register(): void
    {
        $postType = OrderPostType::POST_TYPE;

        add_filter("manage_{$postType}_posts_columns", [$this, 'registerColumns']);
        add_action("manage_{$postType}_posts_custom_column", [$this, 'renderColumn'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'renderStatusFilter']);
        add_filter('parse_query', [$this, 'applyStatusFilter']);
        add_action("add_meta_boxes_{$postType}", [$this, 'registerMetaBoxes']);
        add_action("save_post_{$postType}", [$this, 'saveOrder'], 10, 2);
    }

    /**
     * List table columns.
     */
    public function registerColumns(array $columns): array
    {
        $newColumns = [];

        foreach ($columns as $key => $label) {
            if ($key === 'title') {
                $newColumns['order_number'] = __('Order', 'jankx');
                $newColumns['customer'] = __('Customer', 'jankx');
                $newColumns['items'] = __('Items', 'jankx');
                $newColumns['total'] = __('Total', 'jankx');
                $newColumns['status'] = __('Status', 'jankx');
                $newColumns['handler'] = __('Handler', 'jankx');
                continue;
            }
            if ($key === 'date') {
                continue;
            }
            $newColumns[$key] = $label;
        }

        $newColumns['date'] = __('Date', 'jankx');

        return $newColumns;
    }

    public function renderColumn(string $column, int $postId): void
    {
        $order = new Order($postId);

        switch ($column) {
            case 'order_number':
                $editLink = esc_url(get_edit_post_link($postId));
                echo '<a href="' . $editLink . '"><strong>' . esc_html($order->getOrderNumber()) . '</strong></a>';
                echo '<div class="row-actions">'
                    . '<span class="edit"><a href="' . $editLink . '">' . esc_html__('View / Manage', 'jankx') . '</a></span>'
                    . '</div>';
                break;

            case 'customer':
                $name = $order->getCustomerName();
                $email = $order->getCustomerEmail();
                $phone = $order->getCustomerPhone();

                if ($name) {
                    echo '<strong>' . esc_html($name) . '</strong><br>';
                }
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a><br>';
                }
                if ($phone) {
                    echo '<span class="description">' . esc_html($phone) . '</span>';
                }
                break;

            case 'items':
                $summaries = [];
                foreach ($order->getItems() as $item) {
                    $summaries[] = esc_html($item->getName()) . ' &times; ' . (int) $item->getQuantity();
                }
                echo $summaries ? implode('<br>', $summaries) : '&mdash;';
                break;

            case 'total':
                echo '<strong>' . esc_html($this->formatPrice($order->getTotal())) . '</strong>';
                if ($order->getPaymentMethod()) {
                    echo '<div class="description">' . esc_html($order->getPaymentMethod()) . '</div>';
                }
                break;

            case 'status':
                echo $this->renderStatusBadge($order->getStatus());
                break;

            case 'handler':
                $handlerId = $order->getHandlerId();
                if (!$handlerId) {
                    echo '<span class="description">' . esc_html__('Not handled yet', 'jankx') . '</span>';
                    break;
                }
                echo $this->renderUser($handlerId);
                break;
        }
    }

    /**
     * Status filter dropdown in the list table.
     */
    public function renderStatusFilter(string $postType): void
    {
        if ($postType !== OrderPostType::POST_TYPE) {
            return;
        }

        $current = isset($_GET['order_status']) ? sanitize_key($_GET['order_status']) : '';

        echo '<select name="order_status">';
        echo '<option value="">' . esc_html__('All statuses', 'jankx') . '</option>';
        foreach (Order::getStatusLabels() as $status => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($status),
                selected($current, $status, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function applyStatusFilter(\WP_Query $query): void
    {
        global $pagenow;

        if (
            $pagenow !== 'edit.php' ||
            !isset($_GET['post_type']) ||
            $_GET['post_type'] !== OrderPostType::POST_TYPE ||
            empty($_GET['order_status']) ||
            !$query->is_main_query()
        ) {
            return;
        }

        $query->query_vars['meta_query'] = [
            [
                'key' => '_order_status',
                'value' => sanitize_key($_GET['order_status']),
            ],
        ];
    }

    /**
     * Meta boxes on the order edit screen.
     */
    public function registerMetaBoxes(): void
    {
        $postType = OrderPostType::POST_TYPE;

        add_meta_box(
            'jankx_order_details',
            __('Order Details', 'jankx'),
            [$this, 'renderDetailsBox'],
            $postType,
            'normal',
            'high'
        );

        add_meta_box(
            'jankx_order_status',
            __('Update Status', 'jankx'),
            [$this, 'renderStatusBox'],
            $postType,
            'side',
            'high'
        );

        add_meta_box(
            'jankx_order_history',
            __('Order History', 'jankx'),
            [$this, 'renderHistoryBox'],
            $postType,
            'normal',
            'default'
        );
    }

    public function renderDetailsBox(\WP_Post $post): void
    {
        $order = new Order($post->ID);
        $items = $order->getItems();
        $itemCount = count($items);

        // ── Summary bar ──
        echo '<div class="jankx-order-summary-bar">';
        echo '<div class="jankx-order-summary-bar__item">';
        echo '<div class="jankx-order-summary-bar__label">' . esc_html__('Status', 'jankx') . '</div>';
        echo '<div class="jankx-order-summary-bar__value">' . $this->renderStatusBadge($order->getStatus()) . '</div>';
        echo '</div>';

        echo '<div class="jankx-order-summary-bar__item">';
        echo '<div class="jankx-order-summary-bar__label">' . esc_html__('Order Total', 'jankx') . '</div>';
        echo '<div class="jankx-order-summary-bar__value jankx-order-summary-bar__value--highlight">' . esc_html($this->formatPrice($order->getTotal())) . '</div>';
        echo '</div>';

        echo '<div class="jankx-order-summary-bar__item">';
        echo '<div class="jankx-order-summary-bar__label">' . esc_html__('Items', 'jankx') . '</div>';
        echo '<div class="jankx-order-summary-bar__value">' . $itemCount . '</div>';
        echo '</div>';

        echo '<div class="jankx-order-summary-bar__item">';
        echo '<div class="jankx-order-summary-bar__label">' . esc_html__('Payment', 'jankx') . '</div>';
        echo '<div class="jankx-order-summary-bar__value">' . esc_html($order->getPaymentMethod() ?: '—') . '</div>';
        echo '</div>';

        echo '<div class="jankx-order-summary-bar__item">';
        echo '<div class="jankx-order-summary-bar__label">' . esc_html__('Created', 'jankx') . '</div>';
        echo '<div class="jankx-order-summary-bar__value">' . esc_html(mysql2date(get_option('date_format') . ' H:i', $order->getDateCreated())) . '</div>';
        echo '</div>';
        echo '</div>'; // /.jankx-order-summary-bar

        // ── Customer card ──
        echo '<div class="jankx-order-card">';
        echo '<div class="jankx-order-card__header">';
        echo '<span class="jankx-order-card__header-icon">👤</span>';
        echo '<h3 class="jankx-order-card__title">' . esc_html__('Customer Information', 'jankx') . '</h3>';
        echo '</div>';
        echo '<div class="jankx-order-card__body">';
        echo '<div class="jankx-customer-grid">';
        $this->customerGridItem(__('Full Name', 'jankx'), $order->getCustomerName());
        $this->customerGridItem(__('Email', 'jankx'), $order->getCustomerEmail() ? '<a href="mailto:' . esc_attr($order->getCustomerEmail()) . '">' . esc_html($order->getCustomerEmail()) . '</a>' : '');
        $this->customerGridItem(__('Phone', 'jankx'), $order->getCustomerPhone());
        $this->customerGridItem(__('Address', 'jankx'), $order->getCustomerAddress());
        echo '</div>'; // /.jankx-customer-grid
        echo '</div>'; // /.jankx-order-card__body
        echo '</div>'; // /.jankx-order-card

        // ── Items card ──
        echo '<div class="jankx-order-card">';
        echo '<div class="jankx-order-card__header">';
        echo '<span class="jankx-order-card__header-icon">🛒</span>';
        echo '<h3 class="jankx-order-card__title">' . esc_html__('Order Items', 'jankx') . '</h3>';
        echo '</div>';
        echo '<div class="jankx-order-card__body">';
        echo '<table class="jankx-order-items-table">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Product', 'jankx') . '</th>'
            . '<th>' . esc_html__('Qty', 'jankx') . '</th>'
            . '<th>' . esc_html__('Unit Price', 'jankx') . '</th>'
            . '<th>' . esc_html__('Total', 'jankx') . '</th>'
            . '</tr></thead>';
        echo '<tbody>';

        foreach ($items as $item) {
            echo '<tr>';
            echo '<td><div class="item-name">' . esc_html($item->getName()) . '</div>' . $this->renderItemArgs($item->getMeta()) . '</td>';
            echo '<td><span class="qty-badge">' . (int) $item->getQuantity() . '</span></td>';
            echo '<td><span class="unit-price">' . esc_html($this->formatPrice($item->getUnitPrice())) . '</span></td>';
            echo '<td class="item-total">' . esc_html($this->formatPrice($item->getTotal())) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr>';
        echo '<td colspan="3" class="total-label">' . esc_html__('Order Total', 'jankx') . '</td>';
        echo '<td class="total-value">' . esc_html($this->formatPrice($order->getTotal())) . '</td>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
        echo '</div>'; // /.jankx-order-card__body
        echo '</div>'; // /.jankx-order-card
    }

    public function renderStatusBox(\WP_Post $post): void
    {
        $order = new Order($post->ID);
        $allowed = $order->getAllowedStatusTransitions();

        wp_nonce_field('jankx_update_order_status', 'jankx_order_status_nonce');

        // Current status row
        echo '<div class="jankx-status-current">';
        echo '<span class="jankx-status-current__label">' . esc_html__('Current status', 'jankx') . '</span>';
        echo $this->renderStatusBadge($order->getStatus());
        echo '</div>';

        if (empty($allowed)) {
            echo '<div class="jankx-status-no-transition">' . esc_html__('This order cannot be transitioned further.', 'jankx') . '</div>';
        } else {
            echo '<div class="jankx-status-form">';

            echo '<div class="form-group">';
            echo '<label for="jankx_order_new_status">' . esc_html__('Move to', 'jankx') . '</label>';
            echo '<select name="jankx_order_new_status" id="jankx_order_new_status">';
            foreach ($allowed as $status) {
                printf(
                    '<option value="%s">%s</option>',
                    esc_attr($status),
                    esc_html(Order::getStatusLabel($status))
                );
            }
            echo '</select>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label for="jankx_order_status_note">' . esc_html__('Note', 'jankx') . '</label>';
            echo '<textarea name="jankx_order_status_note" id="jankx_order_status_note" rows="3" placeholder="' . esc_attr__('Optional note…', 'jankx') . '"></textarea>';
            echo '</div>';

            echo '<button type="submit" class="button-update">' . esc_html__('Update Status', 'jankx') . '</button>';

            echo '</div>'; // /.jankx-status-form
        }

        if ($order->getHandler()) {
            echo '<div class="jankx-handler-info">';
            echo $this->renderAvatar($order->getHandlerId());
            echo '<span>' . esc_html__('Last handled by', 'jankx') . ' <strong>' . esc_html($order->getHandler()) . '</strong></span>';
            echo '</div>';
        }
    }

    public function renderHistoryBox(\WP_Post $post): void
    {
        $order = new Order($post->ID);
        $history = $order->getHistory();

        if (empty($history)) {
            echo '<div class="jankx-history-empty">' . esc_html__('No history recorded for this order yet.', 'jankx') . '</div>';

            return;
        }

        echo '<ol class="jankx-order-history">';
        foreach (array_reverse($history) as $entry) {
            echo '<li class="jankx-history-entry">';

            $user = !empty($entry['user_id']) ? get_userdata((int) $entry['user_id']) : null;
            $userName = $user ? $user->display_name : __('System', 'jankx');

            if ($entry['action'] === 'note') {
                $action = __('added a note', 'jankx');
            } else {
                $action = sprintf(
                    /* translators: 1: old status, 2: new status */
                    __('changed status from %1$s to %2$s', 'jankx'),
                    '<strong>' . esc_html(Order::getStatusLabel($entry['from'] ?? '')) . '</strong>',
                    '<strong>' . esc_html(Order::getStatusLabel($entry['to'] ?? '')) . '</strong>'
                );
            }

            echo '<div class="jankx-history-head">';
            echo $this->renderAvatar((int) ($entry['user_id'] ?? 0));
            echo '<span class="jankx-history-user">' . esc_html($userName) . '</span>';
            echo '<span class="jankx-history-action">' . $action . '</span>';
            echo '<span class="jankx-history-time">' . esc_html(mysql2date(get_option('date_format') . ' H:i', $entry['created_at'] ?? '')) . '</span>';
            echo '</div>';

            if (!empty($entry['note'])) {
                echo '<div class="jankx-history-note">' . nl2br(esc_html($entry['note'])) . '</div>';
            }

            echo '</li>';
        }
        echo '</ol>';
    }

    /**
     * Handle the status transition submitted from the edit screen.
     */
    public function saveOrder(int $postId, \WP_Post $post): void
    {
        if (
            !isset($_POST['jankx_order_status_nonce']) ||
            !wp_verify_nonce($_POST['jankx_order_status_nonce'], 'jankx_update_order_status')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can(OrderPostType::CAP_MANAGE)) {
            return;
        }

        if (!isset($_POST['jankx_order_new_status'])) {
            return;
        }

        $newStatus = sanitize_key($_POST['jankx_order_new_status']);
        $note = isset($_POST['jankx_order_status_note']) ? sanitize_textarea_field($_POST['jankx_order_status_note']) : '';

        if (!$newStatus) {
            return;
        }

        $order = new Order($postId);
        if (!in_array($newStatus, $order->getAllowedStatusTransitions(), true)) {
            return;
        }

        $order->updateStatus($newStatus, $note, get_current_user_id());

        add_action(
            'redirect_post_location',
            function (string $location) {
                return add_query_arg('jankx_order_updated', '1', $location);
            }
        );
    }

    /**
     * Render a 2-column customer grid item.
     */
    protected function customerGridItem(string $label, string $value): void
    {
        $isEmpty = ($value === '' || $value === null);
        echo '<div class="jankx-customer-grid__item">';
        echo '<div class="jankx-customer-grid__label">' . esc_html($label) . '</div>';
        if ($isEmpty) {
            echo '<div class="jankx-customer-grid__value jankx-customer-grid__value--empty">&mdash;</div>';
        } else {
            echo '<div class="jankx-customer-grid__value">' . $value . '</div>';
        }
        echo '</div>';
    }

    /**
     * @deprecated Use customerGridItem() instead.
     */
    protected function detailRow(string $label, string $value): void
    {
        $this->customerGridItem($label, $value);
    }

    protected function renderItemArgs(array $args): string
    {
        $lines = [];
        foreach ($args as $key => $value) {
            if (is_scalar($value) && $value !== '') {
                $lines[] = esc_html($key . ': ' . $value);
            }
        }

        return $lines ? '<div class="description">' . implode('<br>', $lines) . '</div>' : '';
    }

    protected function renderUser(int $userId): string
    {
        $user = get_userdata($userId);
        $name = $user ? $user->display_name : '';

        return $this->renderAvatar($userId)
            . '<span>' . esc_html($name) . '</span>';
    }

    protected function renderAvatar(int $userId): string
    {
        return get_avatar($userId, 24) ?: '';
    }

    protected function renderStatusBadge(string $status): string
    {
        $label = Order::getStatusLabel($status);

        return '<span class="jankx-order-badge jankx-order-badge--' . esc_attr($status) . '">'
            . esc_html($label) . '</span>';
    }

    protected function formatPrice(float $price): string
    {
        return CurrencyManager::formatPrice($price);
    }
}
