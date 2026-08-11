<?php

namespace Jankx\Extensions\Ecommerce\Order;

class OrderModel
{
    protected static $ordersTable;
    protected static $orderPostsTable;

    public static function ordersTable(): string
    {
        if (!self::$ordersTable) {
            global $wpdb;
            self::$ordersTable = $wpdb->prefix . 'jankx_orders';
        }
        return self::$ordersTable;
    }

    public static function orderPostsTable(): string
    {
        if (!self::$orderPostsTable) {
            global $wpdb;
            self::$orderPostsTable = $wpdb->prefix . 'jankx_order_posts';
        }
        return self::$orderPostsTable;
    }

    // ── CREATE ────────────────────────────────────────────

    public static function create(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $defaults = [
            'order_number'          => '',
            'status'                => Order::STATUS_PENDING,
            'customer_id'           => 0,
            'customer_name'         => '',
            'customer_email'        => '',
            'customer_phone'        => '',
            'customer_address'      => '',
            'total'                 => 0,
            'currency'              => 'VND',
            'payment_method'        => '',
            'payment_transaction_id' => '',
            'handler_id'            => get_current_user_id(),
            'items'                 => '[]',
            'notes'                 => '[]',
            'history'               => '[]',
            'created_at'            => $now,
            'updated_at'            => $now,
        ];

        $data = array_merge($defaults, $data);

        // Encode arrays as JSON
        if (is_array($data['items'])) {
            $data['items'] = wp_json_encode($data['items']);
        }
        if (is_array($data['notes'])) {
            $data['notes'] = wp_json_encode($data['notes']);
        }
        if (is_array($data['history'])) {
            $data['history'] = wp_json_encode($data['history']);
        }

        $wpdb->insert(self::ordersTable(), $data);
        return (int) $wpdb->insert_id;
    }

    public static function createPostLink(int $orderId, int $postId, string $postType, int $quantity = 1, float $unitPrice = 0): int
    {
        global $wpdb;

        $wpdb->insert(self::orderPostsTable(), [
            'order_id'   => $orderId,
            'post_id'    => $postId,
            'post_type'  => $postType,
            'quantity'   => $quantity,
            'unit_price' => $unitPrice,
        ]);

        return (int) $wpdb->insert_id;
    }

    // ── READ ──────────────────────────────────────────────

    public static function findById(int $orderId): ?array
    {
        global $wpdb;

        $table = self::ordersTable();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $orderId),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return self::decodeJsonFields($row);
    }

    public static function findByOrderNumber(string $orderNumber): ?array
    {
        global $wpdb;

        $table = self::ordersTable();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE order_number = %s", $orderNumber),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return self::decodeJsonFields($row);
    }

    public static function query(array $args = []): array
    {
        global $wpdb;

        $table = self::ordersTable();
        $where = ['1=1'];
        $values = [];
        $orderBy = 'created_at DESC';
        $limit = '';
        $offset = 0;

        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ({$placeholders})";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if (!empty($args['customer_id'])) {
            $where[] = 'customer_id = %d';
            $values[] = (int) $args['customer_id'];
        }

        if (!empty($args['order_number'])) {
            $where[] = 'order_number = %s';
            $values[] = $args['order_number'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'];
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(customer_name LIKE %s OR customer_email LIKE %s OR order_number LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        if (!empty($args['orderby'])) {
            $allowed = ['id', 'order_number', 'status', 'total', 'created_at', 'updated_at'];
            $col = in_array($args['orderby'], $allowed, true) ? $args['orderby'] : 'created_at';
            $dir = strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $orderBy = "{$col} {$dir}";
        }

        if (!empty($args['per_page'])) {
            $perPage = (int) $args['per_page'];
            $offset = !empty($args['page']) ? ((int) $args['page'] - 1) * $perPage : 0;
            $limit = "LIMIT {$perPage} OFFSET {$offset}";
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM {$table} WHERE {$whereClause} ORDER BY {$orderBy} {$limit}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map([self::class, 'decodeJsonFields'], $rows);
    }

    public static function count(array $args = []): int
    {
        global $wpdb;

        $table = self::ordersTable();
        $where = ['1=1'];
        $values = [];

        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ({$placeholders})";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }

        if (!empty($args['customer_id'])) {
            $where[] = 'customer_id = %d';
            $values[] = (int) $args['customer_id'];
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$whereClause}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        return (int) $wpdb->get_var($sql);
    }

    public static function getOrderPosts(int $orderId): array
    {
        global $wpdb;

        $table = self::orderPostsTable();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d", $orderId),
            ARRAY_A
        );
    }

    // ── UPDATE ────────────────────────────────────────────

    public static function update(int $orderId, array $data): bool
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        // Encode arrays as JSON
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = wp_json_encode($data['items']);
        }
        if (isset($data['notes']) && is_array($data['notes'])) {
            $data['notes'] = wp_json_encode($data['notes']);
        }
        if (isset($data['history']) && is_array($data['history'])) {
            $data['history'] = wp_json_encode($data['history']);
        }

        $result = $wpdb->update(
            self::ordersTable(),
            $data,
            ['id' => $orderId],
            null,
            ['%d']
        );

        return $result !== false;
    }

    public static function updateStatus(int $orderId, string $newStatus, string $note = '', int $handlerId = 0): bool
    {
        $order = self::findById($orderId);
        if (!$order) {
            return false;
        }

        $oldStatus = $order['status'];
        $history = $order['history'] ?? [];
        $history[] = [
            'action'    => 'status_changed',
            'from'      => $oldStatus,
            'to'        => $newStatus,
            'note'      => $note,
            'user_id'   => $handlerId ?: get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];

        $updateData = [
            'status'    => $newStatus,
            'history'   => $history,
            'handler_id' => $handlerId ?: get_current_user_id(),
        ];

        if ($note) {
            $notes = $order['notes'] ?? [];
            $notes[] = [
                'note'       => $note,
                'customer'   => false,
                'user_id'    => $handlerId ?: get_current_user_id(),
                'created_at' => current_time('mysql'),
            ];
            $updateData['notes'] = $notes;
        }

        return self::update($orderId, $updateData);
    }

    public static function appendNote(int $orderId, string $note, bool $isCustomer = false): bool
    {
        $order = self::findById($orderId);
        if (!$order) {
            return false;
        }

        $notes = $order['notes'] ?? [];
        $notes[] = [
            'note'       => $note,
            'customer'   => $isCustomer,
            'user_id'    => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];

        return self::update($orderId, ['notes' => $notes]);
    }

    // ── DELETE ────────────────────────────────────────────

    public static function delete(int $orderId): bool
    {
        global $wpdb;

        $wpdb->delete(self::orderPostsTable(), ['order_id' => $orderId], ['%d']);
        $result = $wpdb->delete(self::ordersTable(), ['id' => $orderId], ['%d']);

        return $result !== false;
    }

    // ── HELPERS ───────────────────────────────────────────

    protected static function decodeJsonFields(array $row): array
    {
        foreach (['items', 'notes', 'history'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : [];
            }
        }
        return $row;
    }

    public static function generateOrderNumber(int $orderId): string
    {
        return apply_filters('jankx/ecommerce/order/number', sprintf('OD-%06d', $orderId));
    }
}
