<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\EcommerceExtension;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;
use Jankx\Extensions\Ecommerce\Blocks\AddToCart\AddToCartStrategyFactory;

/**
 * Reusable "Add to cart" block.
 *
 * Renders an add-to-cart form for the current post when its post type has
 * been registered into the shared ecommerce flow (tour, product, ...).
 * The form posts to the shared /cart/items REST route.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class AddToCartBlock extends Block
{
    const BLOCK_ID = 'jankx/add-to-cart';

    protected $blockId = self::BLOCK_ID;

    public function render($attributes, $content = '', $block = null)
    {
        $postId = $this->resolvePostId($block);

        if (!$postId || !EcommerceExtension::is_product($postId)) {
            if ($this->isEditorRequest()) {
                return $this->renderEditorPlaceholder($attributes);
            }

            return '';
        }

        $product = ProductRegistry::get_instance()->createProduct($postId);
        if (!$product) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : [];

        $strategy = AddToCartStrategyFactory::resolve($product);

        return $strategy->render($postId, $product, $attributes);
    }

    protected function renderEditorPlaceholder($attributes): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-add-to-cart jankx-add-to-cart--editor',
        ]);

        $attributes = is_array($attributes) ? $attributes : [];

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($attributes['title'])) {
            $output .= '<h3 class="jankx-add-to-cart__title">' . esc_html($attributes['title']) . '</h3>';
        }

        if (!empty($attributes['show_departure'])) {
            $output .= '<div class="jankx-add-to-cart__field">'
                . '<label>' . esc_html__('Ngày khởi hành', 'jankx') . '</label>'
                . '<input type="date" class="jankx-input" value="' . esc_attr(current_time('Y-m-d')) . '" tabindex="-1" aria-hidden="true" disabled>'
                . '</div>';
        }

        $output .= '<div class="jankx-add-to-cart__row">';

        if (!isset($attributes['show_quantity']) || !empty($attributes['show_quantity'])) {
            $output .= '<input type="number" class="jankx-input jankx-add-to-cart__qty" value="1" min="1"'
                . ' tabindex="-1" aria-hidden="true" disabled>';
        }

        $output .= '<button type="button" class="jankx-btn jankx-btn-primary jankx-add-to-cart__btn" tabindex="-1">'
            . esc_html__('Thêm vào giỏ hàng', 'jankx')
            . '</button>';

        $output .= '</div>';

        $output .= '<p class="jankx-add-to-cart__status">'
            . esc_html__('Bản xem trước trong trình soạn thảo — biểu mẫu thêm vào giỏ hàng cho sản phẩm hiện tại.', 'jankx')
            . '</p>';

        $output .= '</div>';

        return $output;
    }

    protected function isEditorRequest(): bool
    {
        return defined('REST_REQUEST') && REST_REQUEST
            && !empty($_SERVER['REQUEST_URI'])
            && strpos($_SERVER['REQUEST_URI'], '/block-renderer/') !== false;
    }

    protected function resolvePostId($block): int
    {
        if ($block && !empty($block->context['postId'])) {
            return (int) $block->context['postId'];
        }

        $post = get_post();
        if ($post instanceof \WP_Post) {
            return (int) $post->ID;
        }

        return (int) get_the_ID();
    }

    protected function resolvePostType($block, int $postId): string
    {
        if ($block && !empty($block->context['postType'])) {
            return (string) $block->context['postType'];
        }

        $type = get_post_type($postId);

        return $type ?: '';
    }
}
