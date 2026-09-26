<?php
namespace Jankx\Extensions\Ecommerce\Blocks\AddToCart;

class ScrollToFormAddToCartStrategy implements AddToCartStrategyInterface
{
    public function render(int $postId, $product, array $attributes): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-add-to-cart jankx-add-to-cart--scroll-to-form',
        ]);

        $formTarget = 'jankx-product-order-card-' . $postId;

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($attributes['title'])) {
            $output .= '<h3 class="jankx-add-to-cart__title">' . esc_html($attributes['title']) . '</h3>';
        }

        $output .= '<div class="jankx-add-to-cart__row">';
        $output .= sprintf(
            '<button type="button" class="jankx-btn jankx-btn-primary jankx-add-to-cart__btn jankx-scroll-to-order-form" data-target="%s">',
            esc_attr($formTarget)
        );
        $output .= esc_html__('Gửi yêu cầu đặt hàng', 'jankx');
        $output .= '</button>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }
}