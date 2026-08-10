<?php
namespace Jankx\Extensions\Ecommerce\Admin;

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
                $newColumns['customer']     = __('Customer', 'jankx');
                $newColumns['items']        = __('Items', 'jankx');
                $newColumns['total']        = __('Total', 'jankx');
                $newColumns['status']       = __('Status', 'jankx');
                $newColumns['handler']      = __('Handler', 'jankx');
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
                $name  = $order->getCustomerName();
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
                'key'   => '_order_status',
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

        echo '<div class="jankx-order-admin">';

        echo '<h3>' . esc_html__('Customer', 'jankx') . '</h3>';
        echo '<table class="widefat striped" style="max-width:560px">';
        echo '<tbody>';
        $this->detailRow(__('Name', 'jankx'), $order->getCustomerName());
        $this->detailRow(__('Email', 'jankx'), $order->getCustomerEmail() ? '<a href="mailto:' . esc_attr($order->getCustomerEmail()) . '">' . esc_html($order->getCustomerEmail()) . '</a>' : '');
        $this->detailRow(__('Phone', 'jankx'), $order->getCustomerPhone());
        $this->detailRow(__('Address', 'jankx'), $order->getCustomerAddress());
        $this->detailRow(__('Payment method', 'jankx'), $order->getPaymentMethod());
        $this->detailRow(__('Created', 'jankx'), $order->getDateCreated());
        $this->detailRow(__('Status', 'jankx'), $this->renderStatusBadge($order->getStatus()));
        echo '</tbody></table>';

        echo '<h3>' . esc_html__('Items', 'jankx') . '</h3>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Product', 'jankx') . '</th>'
            . '<th>' . esc_html__('Qty', 'jankx') . '</th>'
            . '<th>' . esc_html__('Unit price', 'jankx') . '</th>'
            . '<th>' . esc_html__('Total', 'jankx') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($order->getItems() as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item->getName()) . $this->renderItemArgs($item->getMeta()) . '</td>';
            echo '<td>' . (int) $item->getQuantity() . '</td>';
            echo '<td>' . esc_html($this->formatPrice($item->getUnitPrice())) . '</td>';
            echo '<td><strong>' . esc_html($this->formatPrice($item->getSubtotal())) . '</strong></td>';
            echo '</tr>';
        }

        echo '<tr><td colspan="3" style="text-align:right;font-weight:600">'
            . esc_html__('Total', 'jankx') . '</td>'
            . '<td><strong>' . esc_html($this->formatPrice($order->getTotal())) . '</strong></td></tr>';
        echo '</tbody></table>';

        echo '</div>';
    }

    public function renderStatusBox(\WP_Post $post): void
    {
        $order = new Order($post->ID);
        $allowed = $order->getAllowedStatusTransitions();

        wp_nonce_field('jankx_update_order_status', 'jankx_order_status_nonce');

        echo '<div class="jankx-order-admin jankx-order-status-box">';
        echo '<p>' . esc_html__('Current status', 'jankx') . ' '
            . $this->renderStatusBadge($order->getStatus()) . '</p>';

        if ($order->getHandler()) {
            echo '<p class="description">' . esc_html__('Last handled by', 'jankx') . ' '
                . esc_html($order->getHandler()) . '</p>';
        }

        if (empty($allowed)) {
            echo '<p><em>' . esc_html__('This order cannot be transitioned further.', 'jankx') . '</em></p>';
        } else {
            echo '<p><label for="jankx_order_new_status"><strong>'
                . esc_html__('Move to', 'jankx') . '</strong></label></p>';
            echo '<select name="jankx_order_new_status" id="jankx_order_new_status" style="width:100%">';
            foreach ($allowed as $status) {
                printf(
                    '<option value="%s">%s</option>',
                    esc_attr($status),
                    esc_html(Order::getStatusLabel($status))
                );
            }
            echo '</select>';

            echo '<p><label for="jankx_order_status_note"><strong>'
                . esc_html__('Note', 'jankx') . '</strong></label></p>';
            echo '<textarea name="jankx_order_status_note" id="jankx_order_status_note" rows="3" style="width:100%"></textarea>';

            echo '<p><button type="submit" class="button button-primary button-large" style="width:100%">'
                . esc_html__('Update status', 'jankx') . '</button></p>';
        }

        echo '</div>';
    }

    public function renderHistoryBox(\WP_Post $post): void
    {
        $order = new Order($post->ID);
        $history = $order->getHistory();

        if (empty($history)) {
            echo '<p><em>' . esc_html__('No history recorded for this order yet.', 'jankx') . '</em></p>';

            return;
        }

        echo '<ol class="jankx-order-history">';
        foreach (array_reverse($history) as $entry) {
            echo '<li class="jankx-history-entry">';

            $user = $entry['user_id'] ? get_userdata((int) $entry['user_id']) : null;
            $userName = $user ? $user->display_name : __('System', 'jankx');

            $action = $entry['action'] === 'note'
                ? __('added a note', 'jankx')
                : sprintf(
                    __('changed status from %1$s to %2$s', 'jankx'),
                    '<strong>' . esc_html(Order::getStatusLabel($entry['from'])) . '</strong>',
                    '<strong>' . esc_html(Order::getStatusLabel($entry['to'])) . '</strong>'
                );

            echo '<div class="jankx-history-head">';
            echo $this->renderAvatar((int) $entry['user_id']);
            echo '<span class="jankx-history-user">' . esc_html($userName) . '</span>';
            echo '<span class="jankx-history-action">' . $action . '</span>';
            echo '<span class="jankx-history-time">' . esc_html(mysql2date(get_option('date_format') . ' H:i', $entry['created_at'])) . '</span>';
            echo '</div>';

            if (!empty($entry['note'])) {
                echo '<div class="jankx-history-note">' . esc_html($entry['note']) . '</div>';
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

    protected function detailRow(string $label, string $value): void
    {
        echo '<tr>'
            . '<th scope="row" style="width:160px">' . esc_html($label) . '</th>'
            . '<td>' . ($value ?: '&mdash;') . '</td>'
            . '</tr>';
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
        $currency = (string) apply_filters('jankx/ecommerce/currency', 'VND');
        $symbol = $currency === 'VND' ? 'đ' : $currency;

        return (string) apply_filters(
            'jankx/ecommerce/price_format',
            number_format($price, 0, ',', '.') . $symbol,
            $price,
            $currency
        );
    }
}
