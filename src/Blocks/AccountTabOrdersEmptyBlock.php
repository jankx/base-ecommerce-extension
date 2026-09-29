<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;

/**
 * Empty state of the orders list.
 *
 * Renders the blocks saved inside it (icon, heading, text, call to action) so
 * the "no orders yet" notice can be fully customised from the editor, and wraps
 * them in a container that accepts the full block style panel (background,
 * border, spacing, typography, ...).
 *
 * The parent `jankx/account-tab-orders` block is the only caller: it renders
 * this block in place of the list when the customer has no orders. Pages saved
 * before this block existed keep working thanks to `renderDefaultHtml()`.
 */
class AccountTabOrdersEmptyBlock extends Block
{
    protected $blockId = 'jankx/account-tab-orders-empty';

    public function render($attributes = [], $content = '', $block = null)
    {
        $wrapperAttrs = get_block_wrapper_attributes([
            'class' => 'jankx-empty-state jankx-tab-orders-empty',
        ]);

        if (trim((string) $content) !== '') {
            return sprintf('<div %s>%s</div>', $wrapperAttrs, $content);
        }

        return $this->renderDefaultHtml($wrapperAttrs);
    }

    /**
     * Fallback markup used when the block has no saved inner content.
     */
    public function renderDefaultHtml(string $wrapperAttrs = ''): string
    {
        $output = $wrapperAttrs !== ''
            ? sprintf('<div %s>', $wrapperAttrs)
            : '<div class="jankx-empty-state">';

        $output .= '<span class="jankx-empty-icon" aria-hidden="true">&#128203;</span>';
        $output .= '<p>' . esc_html__('You have no orders yet.', 'base-ecommerce') . '</p>';
        $output .= '</div>';

        return $output;
    }
}
