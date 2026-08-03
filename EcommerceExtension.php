<?php
namespace Jankx\Extensions\Ecommerce;

use Jankx\Extensions\AbstractExtension;

/**
 * Base E-Commerce Extension for Jankx
 * 
 * Provides foundational logic, interfaces, and abstracts
 * for specific business implementations (e.g. travel tours, shop).
 */
class EcommerceExtension extends AbstractExtension
{
    protected static $instance;

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // Core ecommerce hooks that can be shared across models
        add_action('init', [$this, 'init_ecommerce_core']);
    }

    public function init_ecommerce_core(): void
    {
        do_action('jankx/ecommerce/init');
    }
}
