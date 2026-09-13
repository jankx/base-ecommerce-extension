<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;

class CartEmptyBlock extends Block
{
    protected $blockId = 'jankx/cart-empty';

    /**
     * Track whether an empty cart block has been rendered during the current request.
     *
     * @var bool
     */
    protected static $rendered = false;

    public static function hasRendered(): bool
    {
        return self::$rendered;
    }

    public static function resetRendered(): void
    {
        self::$rendered = false;
    }

    public function render($attributes, $content = '', $block = null): string
    {
        $cart = Cart::get_instance();
        if (!$cart->isEmpty()) {
            return '';
        }

        self::$rendered = true;

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-empty-state',
        ]);

        if (!empty(trim((string) $content))) {
            return sprintf('<div %s>%s</div>', $wrapperAttrs, $content);
        }

        return $this->renderEmptyHtml($attributes, $wrapperAttrs);
    }

    public function renderEmptyHtml(array $attributes = [], string $wrapperAttrs = ''): string
    {
        $defaultUrl = (string) apply_filters(
            'jankx/ecommerce/cart/continue_shopping_url',
            home_url('/')
        );

        $title = !empty($attributes['title'])
            ? $attributes['title']
            : __('Your cart is empty', 'jankx');

        $desc = !empty($attributes['description'])
            ? $attributes['description']
            : __('Add some products before checking out.', 'jankx');

        $btnText = !empty($attributes['buttonText'])
            ? $attributes['buttonText']
            : __('Continue shopping', 'jankx');

        $btnUrl = !empty($attributes['buttonUrl'])
            ? $attributes['buttonUrl']
            : $defaultUrl;

        if (empty($wrapperAttrs)) {
            $output = '<div class="jankx-empty-state">';
        } else {
            $output = sprintf('<div %s>', $wrapperAttrs);
        }

        $output .= '<span class="jankx-empty-icon" aria-hidden="true">&#128722;</span>';
        $output .= '<h2 class="jankx-section-title">' . esc_html($title) . '</h2>';
        $output .= '<p>' . esc_html($desc) . '</p>';
        $output .= '<a href="' . esc_url($btnUrl) . '" class="jankx-btn jankx-btn-primary">'
            . esc_html($btnText)
            . '</a>';
        $output .= '</div>';

        return $output;
    }
}
