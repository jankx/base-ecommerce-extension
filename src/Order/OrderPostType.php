<?php
namespace Jankx\Extensions\Ecommerce\Order;

/**
 * Registers the shared "order" post type used by every ecommerce model
 * (tour bookings, product orders, ...).
 *
 * @package Jankx\Extensions\Ecommerce
 */
class OrderPostType
{
    const POST_TYPE = 'jankx_order';

    /**
     * Capability used to manage (view/edit/delete) orders.
     * Granted to administrators and to the roles returned by the
     * `jankx/ecommerce/order_manager_roles` filter.
     */
    const CAP_MANAGE = 'manage_orders';

    const CAP_READ = 'read_orders';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);

        // Grant order capabilities to the allowed roles (admin + staff/manager).
        add_action('admin_init', [$this, 'ensure_capabilities']);
    }

    public function register_post_type(): void
    {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        $labels = [
            'name'                  => __('Orders', 'jankx'),
            'singular_name'         => __('Order', 'jankx'),
            'menu_name'             => __('Orders', 'jankx'),
            'all_items'             => __('All Orders', 'jankx'),
            'add_new'               => __('Add Order', 'jankx'),
            'add_new_item'          => __('Add New Order', 'jankx'),
            'edit_item'             => __('Edit Order', 'jankx'),
            'new_item'              => __('New Order', 'jankx'),
            'view_item'             => __('View Order', 'jankx'),
            'view_items'            => __('View Orders', 'jankx'),
            'search_items'          => __('Search Orders', 'jankx'),
            'not_found'             => __('No orders found.', 'jankx'),
            'not_found_in_trash'    => __('No orders found in Trash.', 'jankx'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-cart',
            'menu_position'   => 30,
            'show_in_rest'    => true,
            'supports'        => ['title', 'custom-fields'],
            'capability_type' => 'post',
            'capabilities'    => [
                'create_posts'          => 'do_not_allow',
                'edit_post'             => self::CAP_MANAGE,
                'edit_posts'            => self::CAP_MANAGE,
                'edit_others_posts'     => self::CAP_MANAGE,
                'edit_published_posts'  => self::CAP_MANAGE,
                'edit_private_posts'    => self::CAP_MANAGE,
                'delete_post'           => self::CAP_MANAGE,
                'delete_posts'          => self::CAP_MANAGE,
                'delete_others_posts'   => self::CAP_MANAGE,
                'delete_published_posts' => self::CAP_MANAGE,
                'delete_private_posts'  => self::CAP_MANAGE,
                'read_post'             => self::CAP_READ,
                'read_private_posts'    => self::CAP_READ,
                'publish_posts'         => self::CAP_MANAGE,
            ],
            'map_meta_cap'    => false,
        ]);
    }

    /**
     * Grant the order capabilities to the administrator role and to any
     * staff/manager role allowed by the `jankx/ecommerce/order_manager_roles`
     * filter (default: administrator + editor).
     */
    public function ensure_capabilities(): void
    {
        $roles = apply_filters('jankx/ecommerce/order_manager_roles', ['administrator', 'editor']);

        foreach ($roles as $roleName) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }

            if (!$role->has_cap(self::CAP_MANAGE)) {
                $role->add_cap(self::CAP_MANAGE);
            }
            if (!$role->has_cap(self::CAP_READ)) {
                $role->add_cap(self::CAP_READ);
            }
        }
    }
}
