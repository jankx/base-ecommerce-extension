<?php
namespace Jankx\Extensions\Ecommerce\Blocks\AddToCart;

class ContactAddToCartStrategy implements AddToCartStrategyInterface
{
    public function render(int $postId, $product, array $attributes): string
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-add-to-cart jankx-add-to-cart--contact',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if (!empty($attributes['title'])) {
            $output .= '<h3 class="jankx-add-to-cart__title">' . esc_html($attributes['title']) . '</h3>';
        }

        $output .= '<p class="jankx-add-to-cart__note">'
            . esc_html__('Liên hệ với chúng tôi để được tư vấn và báo giá.', 'jankx')
            . '</p>';

        $output .= '</div>';

        return $output;
    }
}
