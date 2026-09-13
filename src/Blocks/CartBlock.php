<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Cart\CartItem;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;
use Jankx\Extensions\Ecommerce\EcommerceExtension;

class CartBlock extends Block
{
    protected $blockId = 'jankx/cart';

    /**
     * Default inner block template used when the cart block has no saved
     * inner blocks (e.g. legacy `<!-- wp:jankx/cart /-->` content).
     *
     * @var string[]
     */
    protected $defaultItemInnerBlocks = [
        'jankx/cart-item-checkbox',
        'jankx/cart-item-image',
        'jankx/cart-item-title',
        'jankx/cart-item-meta',
        'jankx/cart-item-quantity',
        'jankx/cart-item-price',
        'jankx/cart-item-remove',
    ];

    public function render($attributes, $content = '', $block = null)
    {
        $cart = Cart::get_instance();
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-cart-block',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if ($cart->isEmpty()) {
            $output .= $this->renderEmptyCart();
        } else {
            $output .= $this->renderItems($cart, $block);
            $output .= $this->renderTotals($cart);
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderEmptyCart(): string
    {
        $continueUrl = (string) apply_filters(
            'jankx/ecommerce/cart/continue_shopping_url',
            home_url('/')
        );

        return '<div class="jankx-empty-state">'
            . '<span class="jankx-empty-icon" aria-hidden="true">&#128722;</span>'
            . '<h2 class="jankx-section-title">' . esc_html__('Your cart is empty', 'jankx') . '</h2>'
            . '<p>' . esc_html__('Add some products before checking out.', 'jankx') . '</p>'
            . '<a href="' . esc_url($continueUrl) . '" class="jankx-btn jankx-btn-primary">'
            . esc_html__('Continue shopping', 'jankx') . '</a>'
            . '</div>';
    }

    protected function renderItems(Cart $cart, $block = null): string
    {
        $innerBlocks = [];
        if ($block && !empty($block->inner_blocks)) {
            $innerBlocks = $block->inner_blocks;
        }

        $output = '<div class="jankx-cart-items jankx-cart-items--list">';

        foreach ($cart->getItems() as $itemKey => $item) {
            CartItemContext::set($item);
            $output .= '<div class="jankx-cart-item" data-item-key="' . esc_attr($itemKey) . '">';

            if (count($innerBlocks) > 0) {
                foreach ($innerBlocks as $innerBlock) {
                    $output .= $innerBlock->render();
                }
            } else {
                $output .= $this->renderDefaultItem();
            }

            $output .= '</div>';
        }

        CartItemContext::set(null);
        $output .= '</div>';

        return $output;
    }

    /**
     * Render the fallback item layout composed of the built-in sub-blocks.
     */
    protected function renderDefaultItem(): string
    {
        $output = '';
        foreach ($this->defaultItemInnerBlocks as $slug) {
            $blockClass = $this->resolveInnerBlockClass($slug);
            if (!$blockClass) {
                continue;
            }
            $blockInstance = new $blockClass();
            $output .= $blockInstance->render([]);
        }

        return $output;
    }

    protected function resolveInnerBlockClass(string $blockId): ?string
    {
        $map = [
            'jankx/cart-item-checkbox' => CartItemCheckboxBlock::class,
            'jankx/cart-item-image'    => CartItemImageBlock::class,
            'jankx/cart-item-title'    => CartItemTitleBlock::class,
            'jankx/cart-item-meta'     => CartItemMetaBlock::class,
            'jankx/cart-item-quantity' => CartItemQuantityBlock::class,
            'jankx/cart-item-price'    => CartItemPriceBlock::class,
            'jankx/cart-item-remove'   => CartItemRemoveBlock::class,
        ];

        return $map[$blockId] ?? null;
    }

    protected function renderTotals(Cart $cart): string
    {
        $checkoutUrl = EcommerceExtension::get_checkout_page_url();

        $output = '<div class="jankx-cart-totals">';
        $output .= '<h2 class="jankx-section-title">' . esc_html__('Cart totals', 'jankx') . '</h2>';

        $output .= $this->renderCouponSection($cart);

        if ($cart->getDiscount() > 0) {
            $output .= '<div class="jankx-total-row">'
                . '<span>' . esc_html__('Subtotal', 'jankx') . '</span>'
                . '<span>' . esc_html($this->formatPrice($cart->getSubtotal())) . '</span>'
                . '</div>';
            $output .= '<div class="jankx-total-row">'
                . '<span>' . esc_html__('Discount', 'jankx') . '</span>'
                . '<span>' . esc_html('-' . $this->formatPrice($cart->getDiscount())) . '</span>'
                . '</div>';
        }

        $output .= '<div class="jankx-total-row jankx-total-grand">'
            . '<span>' . esc_html__('Total', 'jankx') . '</span>'
            . '<span>' . esc_html($this->formatPrice($cart->getTotal())) . '</span>'
            . '</div>';

        $output .= '<div class="jankx-cart-actions">'
            . '<a href="' . esc_url($checkoutUrl) . '" class="jankx-btn jankx-btn-primary jankx-btn-checkout">'
            . esc_html__('Proceed to checkout', 'jankx') . '</a>'
            . '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Coupon apply/remove UI. Rendered by the coupon-system extension when
     * active; harmless no-op otherwise.
     */
    protected function renderCouponSection(Cart $cart): string
    {
        if (!class_exists('\Jankx\Extensions\CouponSystem\CouponManager')) {
            return '';
        }

        $manager = \Jankx\Extensions\CouponSystem\CouponManager::get_instance();
        $applied = $manager->getApplied();
        $appliedCode = $applied ? $applied->getCode() : '';

        $output = '<div class="jankx-coupon-form" data-coupon-applied="' . esc_attr($appliedCode) . '">';

        if ($appliedCode) {
            $output .= '<div class="jankx-coupon-applied">'
                . '<span class="jankx-coupon-applied-code">' . esc_html($appliedCode) . '</span>'
                . '<button type="button" class="jankx-btn jankx-btn-outline jankx-coupon-remove">'
                . esc_html__('Gỡ mã', 'jankx') . '</button>'
                . '</div>';
        } else {
            $output .= '<div class="jankx-coupon-input-row">'
                . '<input type="text" class="jankx-coupon-code" placeholder="' . esc_attr__('Nhập mã giảm giá', 'jankx') . '" autocomplete="off">'
                . '<button type="button" class="jankx-btn jankx-btn-primary jankx-coupon-apply">'
                . esc_html__('Áp dụng', 'jankx') . '</button>'
                . '</div>';
            $output .= '<span class="jankx-coupon-message" role="status"></span>';
        }

        $output .= '</div>';

        return $output;
    }

    protected function formatPrice(float $price): string
    {
        $converterManager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
        return $converterManager->formatPriceWithConversion($price);
    }
}