<?php
namespace Jankx\Extensions\Ecommerce\Blocks\AddToCart;

use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

class NormalAddToCartStrategy implements AddToCartStrategyInterface
{
    public function render(int $postId, $product, array $attributes): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-add-to-cart',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($attributes['title'])) {
            $output .= '<h3 class="jankx-add-to-cart__title">' . esc_html($attributes['title']) . '</h3>';
        }

        $output .= '<form class="jankx-add-to-cart-form">';
        $output .= '<input type="hidden" name="product_id" value="' . esc_attr($postId) . '">';

        $formBody = '';

        $productType = $product->getProductType();
        $showDeparture = !empty($attributes['show_departure'])
            || ($productType === 'tour' && !empty(get_post_meta($postId, '_tour_departures', true)));

        if ($showDeparture) {
            $formBody .= '<div class="jankx-add-to-cart__field">'
                . '<label for="jankx-departure-' . esc_attr($postId) . '">'
                . esc_html__('Ngày khởi hành', 'jankx') . '</label>'
                . '<input type="date" id="jankx-departure-' . esc_attr($postId)
                . '" name="departure_date" class="jankx-input" min="' . esc_attr(current_time('Y-m-d')) . '">'
                . '</div>';
        }

        $formBody .= '<div class="jankx-add-to-cart__row">';
        $formBody .= '<span class="jankx-add-to-cart__price">'
            . esc_html(CurrencyManager::formatPrice($product->getPrice()))
            . '</span>';

        if (!isset($attributes['show_quantity']) || !empty($attributes['show_quantity'])) {
            $formBody .= '<input type="number" name="quantity" value="1" min="1" class="jankx-input jankx-add-to-cart__qty"'
                . ' aria-label="' . esc_attr__('Số lượng', 'jankx') . '">';
        }

        $formBody .= '<button type="submit" class="jankx-btn jankx-btn-primary jankx-add-to-cart__btn">'
            . esc_html__('Thêm vào giỏ hàng', 'jankx') . '</button>';

        $formBody .= '</div>';

        $output .= apply_filters(
            'jankx/ecommerce/add_to_cart/form',
            $formBody,
            $product,
            $postId,
            $productType,
            $attributes
        );

        $output .= '<p class="jankx-add-to-cart__status" role="status"></p>';
        $output .= '</form>';
        $output .= '</div>';

        return $output;
    }
}
