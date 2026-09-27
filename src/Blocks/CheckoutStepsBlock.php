<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;

class CheckoutStepsBlock extends Block
{
    protected $blockId = 'jankx/checkout-steps';

    public function render($attributes, $content = '', $block = null): string
    {
        $currentStep = isset($attributes['currentStep']) ? (int) $attributes['currentStep'] : 2;

        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-checkout-steps alignwide',
        ]);

        $output = sprintf('<div %s>', $wrapperAttrs);
        $output .= '<div class="jankx-steps-container">';

        // Step 1: Chọn đơn hàng (Cart)
        $output .= $this->renderStep(1, __('Chọn đơn hàng', 'base-ecommerce'), $currentStep);
        $output .= '<div class="jankx-step-line ' . ($currentStep >= 2 ? 'is-active' : '') . '"></div>';

        // Step 2: Điền thông tin (Checkout details)
        $output .= $this->renderStep(2, __('Điền thông tin', 'base-ecommerce'), $currentStep);
        $output .= '<div class="jankx-step-line ' . ($currentStep >= 3 ? 'is-active' : '') . '"></div>';

        // Step 3: Thanh toán (Payment success/gateway)
        $output .= $this->renderStep(3, __('Thanh toán', 'base-ecommerce'), $currentStep);

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function renderStep(int $stepNumber, string $label, int $currentStep): string
    {
        $classes = ['jankx-step'];
        if ($currentStep >= $stepNumber) {
            $classes[] = 'is-active';
        }
        if ($currentStep > $stepNumber) {
            $classes[] = 'is-completed';
        }

        $iconContent = (string) $stepNumber;
        if ($currentStep > $stepNumber) {
            $iconContent = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        } elseif ($currentStep === $stepNumber && $stepNumber === 2) {
             // as per design, step 2 active has a three-dot icon
             $iconContent = '<span class="jankx-dots">...</span>';
        } elseif ($currentStep === $stepNumber && $stepNumber === 1) {
             $iconContent = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        }

        return sprintf(
            '<div class="%s">
                <div class="jankx-step-icon">%s</div>
                <div class="jankx-step-label">%s</div>
            </div>',
            esc_attr(implode(' ', $classes)),
            $iconContent,
            esc_html($label)
        );
    }
}
