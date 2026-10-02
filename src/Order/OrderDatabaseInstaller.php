<?php

namespace Jankx\Extensions\Ecommerce\Order;

class OrderDatabaseInstaller
{
    /**
     * Bumped whenever createTables()/migrate() changes, so existing installs
     * pick up new schema without re-running DDL inspection on every request.
     */
    protected const DB_VERSION = '1.0.1';

    public function register(): void
    {
        add_action('init', [$this, 'maybeCreateTables']);
    }

    public function maybeCreateTables(): void
    {
        global $wpdb;

        // SHOW TABLES + DESCRIBE cost a round trip each on every page load.
        // Once the stored version matches, the schema is known-good and the
        // frontend has no business checking it again.
        if (get_option('jankx_order_db_version') === self::DB_VERSION) {
            return;
        }

        $ordersTable = $wpdb->prefix . 'jankx_orders';
        $orderPostsTable = $wpdb->prefix . 'jankx_order_posts';

        // Check if tables already exist
        if ($this->tableExists($ordersTable) && $this->tableExists($orderPostsTable)) {
            // Run migrations for existing tables
            $this->migrate($ordersTable);
            // Update version if needed
            update_option('jankx_order_db_version', self::DB_VERSION);
            return;
        }

        $this->createTables($ordersTable, $orderPostsTable);
        update_option('jankx_order_db_version', self::DB_VERSION);
    }

    protected function tableExists(string $table): bool
    {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return $result === $table;
    }

    protected function createTables(string $ordersTable, string $orderPostsTable): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $sqlOrders = "CREATE TABLE {$ordersTable} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_number varchar(20) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            customer_name varchar(100) NOT NULL DEFAULT '',
            customer_email varchar(100) NOT NULL DEFAULT '',
            customer_phone varchar(30) NOT NULL DEFAULT '',
            customer_address text NOT NULL,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT 'VND',
            payment_method varchar(50) NOT NULL DEFAULT '',
            payment_transaction_id varchar(100) NOT NULL DEFAULT '',
            tracking_number varchar(100) NOT NULL DEFAULT '',
            handler_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            items longtext NOT NULL,
            notes longtext NOT NULL,
            history longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_number (order_number),
            KEY status (status),
            KEY customer_id (customer_id),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        $sqlOrderPosts = "CREATE TABLE {$orderPostsTable} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            post_id bigint(20) UNSIGNED NOT NULL,
            post_type varchar(50) NOT NULL DEFAULT '',
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(12,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY post_id (post_id),
            KEY post_type (post_type)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sqlOrders);
        dbDelta($sqlOrderPosts);
    }

    /**
     * Add missing columns to the orders table for existing installations.
     */
    protected function migrate(string $ordersTable): void
    {
        global $wpdb;

        $columns = $wpdb->get_col("DESCRIBE {$ordersTable}", 0);

        if (!in_array('tracking_number', $columns, true)) {
            $wpdb->query("ALTER TABLE {$ordersTable} ADD COLUMN tracking_number varchar(100) NOT NULL DEFAULT '' AFTER payment_transaction_id");
        }
    }
}
