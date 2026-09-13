<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Cart\CartItem;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;
use Jankx\Extensions\Ecommerce\EcommerceExtension;
use Jankx\Extensions\Ecommerce\Blocks\CartTotalsBlock;
use Jankx\Extensions\Ecommerce\Blocks\CartEmptyBlock;

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
            $emptyInnerBlocks = [];
            if ($block && !empty($block->inner_blocks)) {
                foreach ($block->inner_blocks as $innerBlock) {
                    if ($innerBlock->name === 'jankx/cart-empty') {
                        $emptyInnerBlocks[] = $innerBlock;
                    }
                }
            }

            if (!empty($emptyInnerBlocks)) {
                foreach ($emptyInnerBlocks as $emptyBlock) {
                    $output .= $emptyBlock->render();
                }
            } elseif (!CartEmptyBlock::hasRendered() && !(function_exists('has_block') && has_block('jankx/cart-empty'))) {
                $output .= $this->renderEmptyCart();
            }
        } else {
            $itemInnerBlocks = [];
            $totalsInnerBlocks = [];

            if ($block && !empty($block->inner_blocks)) {
                foreach ($block->inner_blocks as $innerBlock) {
                    if ($innerBlock->name === 'jankx/cart-totals') {
                        $totalsInnerBlocks[] = $innerBlock;
                    } elseif ($innerBlock->name === 'jankx/cart-empty') {
                        continue;
                    } else {
                        $itemInnerBlocks[] = $innerBlock;
                    }
                }
            }

            $output .= $this->renderItems($cart, $itemInnerBlocks);

            if (!empty($totalsInnerBlocks)) {
                foreach ($totalsInnerBlocks as $totalsBlock) {
                    $output .= $totalsBlock->render();
                }
            } elseif (!CartTotalsBlock::hasRendered() && !(function_exists('has_block') && has_block('jankx/cart-totals'))) {
                $output .= $this->renderTotals($cart);
            }
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderEmptyCart(): string
    {
        $emptyBlock = new CartEmptyBlock();
        return $emptyBlock->renderEmptyHtml();
    }

    protected function renderItems(Cart $cart, $innerBlocksOrBlock = null): string
    {
        $itemInnerBlocks = [];
        if (is_array($innerBlocksOrBlock)) {
            $itemInnerBlocks = $innerBlocksOrBlock;
        } elseif ($innerBlocksOrBlock && !empty($innerBlocksOrBlock->inner_blocks)) {
            foreach ($innerBlocksOrBlock->inner_blocks as $innerBlock) {
                if ($innerBlock->name !== 'jankx/cart-totals' && $innerBlock->name !== 'jankx/cart-empty') {
                    $itemInnerBlocks[] = $innerBlock;
                }
            }
        }

        $output = '<div class="jankx-cart-items jankx-cart-items--list">';

        foreach ($cart->getItems() as $itemKey => $item) {
            CartItemContext::set($item);
            $output .= '<div class="jankx-cart-item" data-item-key="' . esc_attr($itemKey) . '">';

            if (count($itemInnerBlocks) > 0) {
                foreach ($itemInnerBlocks as $innerBlock) {
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
        $totalsBlock = new CartTotalsBlock();
        return $totalsBlock->renderTotalsHtml($cart);
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