<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;

/**
 * Checkout container block.
 *
 * Renders the checkout `<form>` wrapper and groups its inner section blocks
 * into the two-column layout (customer | summary) based on each section's
 * `jankxCheckoutColumn` attribute. When the cart is empty the form is
 * replaced by the `jankx/checkout-empty` inner block (or a default fallback).
 *
 * Legacy content like `<!-- wp:jankx/checkout /-->` (no inner blocks) keeps
 * rendering the default composed checkout form.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class CheckoutBlock extends Block
{
    protected $blockId = 'jankx/checkout';

    protected $sectionClassMap = [
        'jankx/checkout-customer-details' => CheckoutCustomerDetailsBlock::class,
        'jankx/checkout-payment-methods'  => CheckoutPaymentMethodsBlock::class,
        'jankx/checkout-order-review'     => CheckoutOrderReviewBlock::class,
        'jankx/checkout-credits'          => CheckoutCreditsBlock::class,
        'jankx/checkout-actions'          => CheckoutActionsBlock::class,
        'jankx/checkout-empty'            => CheckoutEmptyBlock::class,
    ];

    protected $defaultCustomerSections = [
        'jankx/checkout-customer-details',
        'jankx/checkout-payment-methods',
    ];

    protected $defaultSummarySections = [
        'jankx/checkout-order-review',
        'jankx/checkout-credits',
        'jankx/checkout-actions',
    ];

    public function render($attributes, $content = '', $block = null): string
    {
        $cart = Cart::get_instance();
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-checkout-block',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);

        if ($cart->isEmpty()) {
            $output .= $this->renderEmpty($block);
        } else {
            $output .= $this->renderForm($block);
        }

        $output .= '</div>';

        return $output;
    }

    protected function renderEmpty($block): string
    {
        $emptyBlocks = [];
        if ($block && !empty($block->inner_blocks)) {
            foreach ($block->inner_blocks as $innerBlock) {
                if ($innerBlock->name === 'jankx/checkout-empty') {
                    $emptyBlocks[] = $innerBlock;
                }
            }
        }

        if (!empty($emptyBlocks)) {
            $output = '';
            foreach ($emptyBlocks as $emptyBlock) {
                $output .= $emptyBlock->render();
            }

            return $output;
        }

        return (new CheckoutEmptyBlock())->renderEmptyHtml();
    }

    protected function renderForm($block): string
    {
        $innerBlocks = [];
        if ($block && !empty($block->inner_blocks)) {
            foreach ($block->inner_blocks as $innerBlock) {
                if ($innerBlock->name === 'jankx/checkout-empty') {
                    continue;
                }
                $innerBlocks[] = $innerBlock;
            }
        }

        if (empty($innerBlocks)) {
            return $this->renderDefaultForm();
        }

        $customerBlocks = [];
        $summaryBlocks = [];
        $otherBlocks = [];

        foreach ($innerBlocks as $innerBlock) {
            $column = $innerBlock->attributes['jankxCheckoutColumn'] ?? '';
            if ($column === 'customer') {
                $customerBlocks[] = $innerBlock;
            } elseif ($column === 'summary') {
                $summaryBlocks[] = $innerBlock;
            } else {
                $otherBlocks[] = $innerBlock;
            }
        }

        $output = '<form class="jankx-checkout-form" method="post" novalidate>';
        $output .= '<div class="jankx-checkout-cols">';

        $output .= '<div class="jankx-checkout-customer">';
        foreach ($customerBlocks as $innerBlock) {
            $output .= $innerBlock->render();
        }
        $output .= '</div>';

        $output .= '<div class="jankx-checkout-summary">';
        foreach ($summaryBlocks as $innerBlock) {
            $output .= $innerBlock->render();
        }
        $output .= '</div>';

        $output .= '</div>';

        foreach ($otherBlocks as $innerBlock) {
            $output .= $innerBlock->render();
        }

        $output .= '</form>';

        return $output;
    }

    protected function renderDefaultForm(): string
    {
        $output = '<form class="jankx-checkout-form" method="post" novalidate>';
        $output .= '<div class="jankx-checkout-cols">';

        $output .= '<div class="jankx-checkout-customer">';
        foreach ($this->defaultCustomerSections as $slug) {
            $output .= $this->renderSection($slug);
        }
        $output .= '</div>';

        $output .= '<div class="jankx-checkout-summary">';
        foreach ($this->defaultSummarySections as $slug) {
            $output .= $this->renderSection($slug);
        }
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</form>';

        return $output;
    }

    protected function renderSection(string $slug): string
    {
        $blockClass = $this->sectionClassMap[$slug] ?? null;
        if (!$blockClass) {
            return '';
        }

        return (new $blockClass())->render([]);
    }
}