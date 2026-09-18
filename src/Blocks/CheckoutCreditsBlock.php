<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

class CheckoutCreditsBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-credits';

    public function render($attributes, $content = '', $block = null): string
    {
        $integration = $this->getCreditsIntegration();
        if (!$integration || !$integration->isEnabled() || !is_user_logged_in()) {
            return '';
        }

        $balance = $integration->getBalance();
        if ($balance <= 0) {
            return '';
        }

        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-credits',
            ])
        );

        $output .= '<div class="jankx-credits-form">';
        $output .= '<label class="jankx-credits-toggle-label">'
            . '<input type="checkbox" class="jankx-credits-toggle" value="1"' . checked($integration->isApplied(), true, false) . '>'
            . '<span>' . esc_html($integration->getLabel()) . '</span>'
            . '</label>';
        $output .= '<p class="jankx-credits-balance">'
            . esc_html__('Số dư tín dụng:', 'jankx') . ' <strong class="jankx-credits-balance-value">'
            . esc_html($this->formatPrice($balance))
            . '</strong></p>';
        $output .= '<span class="jankx-credits-message" role="status"></span>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}