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

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        $labels = [
            'name'                  => __('Orders', 'jankx'),
            'singular_name'         => __('Order', 'jankx'),
            'menu_name'             => __('E-Commerce Orders', 'jankx'),
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
            'show_in_menu'    => false,
            'show_in_rest'    => true,
            'menu_icon'       => 'dashicons-cart',
            'menu_position'   => 30,
            'supports'        => ['title', 'custom-fields'],
            'capability_type' => 'post',
            'capabilities'    => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap'    => true,
        ]);
    }
}
