<?php
namespace Jankx\Extensions\Ecommerce\Admin;

use Jankx\Extensions\Ecommerce\Order\OrderPostType;

/**
 * Registers the staff roles (shop_manager, sale, accountant) and keeps the
 * order + tour capability matrix in sync on every admin request:
 *
 *   - editor        : create/edit content + tours only (no order access)
 *   - accountant    : view orders only (no edits, no tour editing)
 *   - sale          : view + edit orders (no tour editing)
 *   - shop_manager  : edit tours + view/edit orders
 *
 * Caps are applied idempotently (add when allowed, remove when not) so stale
 * capabilities granted by older versions are cleaned up automatically.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class OrderRoles
{
    const ROLE_SHOP_MANAGER = 'shop_manager';
    const ROLE_SALE         = 'sale';
    const ROLE_ACCOUNTANT   = 'accountant';

    /**
     * Roles whose tour/editorial capabilities are synchronised by this class.
     */
    const SYNCED_ROLES = [
        'administrator',
        'editor',
        self::ROLE_SHOP_MANAGER,
        self::ROLE_SALE,
        self::ROLE_ACCOUNTANT,
    ];

    /**
     * Capabilities required to manage content (posts/pages/tours).
     */
    const TOUR_EDITOR_CAPS = [
        'edit_posts',
        'edit_others_posts',
        'edit_published_posts',
        'edit_private_posts',
        'publish_posts',
        'read_private_posts',
        'edit_pages',
        'edit_others_pages',
        'edit_published_pages',
        'edit_private_pages',
        'publish_pages',
        'read_private_pages',
        'delete_posts',
        'delete_others_posts',
        'delete_published_posts',
        'delete_private_posts',
        'delete_pages',
        'delete_others_pages',
        'delete_published_pages',
        'delete_private_pages',
        'upload_files',
    ];

    public function register(): void
    {
        add_action('admin_init', [$this, 'setup']);
    }

    public function setup(): void
    {
        $this->ensureRoles();
        $this->syncOrderCaps();
        $this->syncTourCaps();
    }

    protected function ensureRoles(): void
    {
        $definitions = [
            self::ROLE_SHOP_MANAGER => __('Quản lý cửa hàng', 'base-ecommerce'),
            self::ROLE_SALE         => __('Bộ phận bán hàng', 'base-ecommerce'),
            self::ROLE_ACCOUNTANT   => __('Kế toán', 'base-ecommerce'),
        ];

        $baseCaps = [
            'read'    => true,
            'level_0' => true,
        ];

        foreach ($definitions as $roleName => $displayName) {
            if (get_role($roleName)) {
                continue;
            }

            add_role($roleName, $displayName, $baseCaps);
        }
    }

    protected function syncOrderCaps(): void
    {
        $viewerRoles = (array) apply_filters('jankx/ecommerce/order_viewer_roles', [
            'administrator',
            self::ROLE_SHOP_MANAGER,
            self::ROLE_SALE,
            self::ROLE_ACCOUNTANT,
        ]);

        $editorRoles = (array) apply_filters('jankx/ecommerce/order_editor_roles', [
            'administrator',
            self::ROLE_SHOP_MANAGER,
            self::ROLE_SALE,
        ]);

        $roster = array_values(array_unique(array_merge(self::SYNCED_ROLES, $viewerRoles, $editorRoles)));

        foreach ($roster as $roleName) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }

            $this->setCap($role, OrderPostType::CAP_READ, in_array($roleName, $viewerRoles, true));
            $this->setCap($role, OrderPostType::CAP_MANAGE, in_array($roleName, $editorRoles, true));
        }
    }

    protected function syncTourCaps(): void
    {
        $tourEditorRoles = (array) apply_filters('jankx/ecommerce/tour_editor_roles', [
            'administrator',
            'editor',
            self::ROLE_SHOP_MANAGER,
        ]);

        foreach (self::SYNCED_ROLES as $roleName) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }

            $allowed = in_array($roleName, $tourEditorRoles, true);
            foreach (self::TOUR_EDITOR_CAPS as $cap) {
                $this->setCap($role, $cap, $allowed);
            }
        }
    }

    protected function setCap(\WP_Role $role, string $cap, bool $allowed): void
    {
        if ($allowed) {
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
            return;
        }

        if ($role->has_cap($cap)) {
            $role->remove_cap($cap);
        }
    }
}