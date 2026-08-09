<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\EcommerceExtension;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

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
        $postType = $this->resolvePostType($block, $postId);

        if (!$postId || !EcommerceExtension::is_product($postId)) {
            return '';
        }

        $product = ProductRegistry::get_instance()->createProduct($postId);
        if (!$product) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : [];

        if (!$product->isPurchasable()) {
            return $this->renderUnavailable($postId);
        }

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-add-to-cart',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($attributes['title'])) {
            $output .= '<h3 class="jankx-add-to-cart__title">' . esc_html($attributes['title']) . '</h3>';
        }

        $output .= '<form class="jankx-add-to-cart-form">';
        $output .= '<input type="hidden" name="product_id" value="' . esc_attr($postId) . '">';

        $showDeparture = !empty($attributes['show_departure'])
            || ($postType === 'tour' && !empty(get_post_meta($postId, '_tour_departures', true)));

        if ($showDeparture) {
            $output .= '<div class="jankx-add-to-cart__field">'
                . '<label for="jankx-departure-' . esc_attr($postId) . '">'
                . esc_html__('Ngày khởi hành', 'jankx') . '</label>'
                . '<input type="date" id="jankx-departure-' . esc_attr($postId)
                . '" name="departure_date" class="jankx-input" min="' . esc_attr(current_time('Y-m-d')) . '">'
                . '</div>';
        }

        $output .= '<div class="jankx-add-to-cart__row">';

        $output .= '<span class="jankx-add-to-cart__price">' . esc_html($this->formatPrice($product->getPrice())) . '</span>';

        if (!isset($attributes['show_quantity']) || !empty($attributes['show_quantity'])) {
            $output .= '<input type="number" name="quantity" value="1" min="1" class="jankx-input jankx-add-to-cart__qty"'
                . ' aria-label="' . esc_attr__('Số lượng', 'jankx') . '">';
        }

        $output .= '<button type="submit" class="jankx-btn jankx-btn-primary jankx-add-to-cart__btn">'
            . esc_html__('Thêm vào giỏ hàng', 'jankx') . '</button>';

        $output .= '</div>';
        $output .= '<p class="jankx-add-to-cart__status" role="status"></p>';
        $output .= '</form>';
        $output .= '</div>';

        return $output;
    }

    protected function renderUnavailable(int $postId): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-add-to-cart jankx-add-to-cart--unavailable',
        ]);

        return sprintf('<div %s>', $wrapperAttrs)
            . '<p class="jankx-add-to-cart__note">'
            . esc_html__('Liên hệ với chúng tôi để được tư vấn và báo giá.', 'jankx')
            . '</p>'
            . '</div>';
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
