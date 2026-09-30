<?php

namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CartItemMetaBlock extends Block
{
    protected $blockId = 'jankx/cart-item-meta';

    public function render($attributes, $content = '', $block = null): string
    {
        $item = CartItemContext::get();
        if (!$item) {
            return '';
        }

        $lines = $this->buildMetaLines($item);
        if (empty($lines)) {
            return '';
        }

        $output = '<div class="jankx-cart-item__meta">';
        foreach ($lines as $line) {
            $output .= '<span class="jankx-cart-item__meta-line">' . $line . '</span>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Build the meta lines for a cart item:
     * - tour type label
     * - departure date
     * - passenger group breakdown
     */
    protected function buildMetaLines($item): array
    {
        $lines = [];
        $productId = $item->getProductId();
        $args = $item->getArgs();

        // Tour type label (filterable)
        $typeLabel = (string) apply_filters('jankx/ecommerce/cart/item/type_label', '', $item);
        if ($typeLabel === '') {
            $terms = get_the_terms($productId, 'tour_tag');
            if ($terms && !is_wp_error($terms)) {
                $typeLabel = implode(', ', wp_list_pluck($terms, 'name'));
            }
        }
        if ($typeLabel) {
            $lines[] = esc_html($typeLabel);
        }

        // Departure date
        $departureDate = (string) ($args['departure_date'] ?? '');
        if ($departureDate !== '') {
            $lines[] = esc_html(date_i18n(get_option('date_format'), strtotime($departureDate)));
        }

        // Variation (ticket group) – one passenger group per cart line.
        if ($item->hasVariation()) {
            $variationLabel = $item->getVariationLabel();
            if ($variationLabel !== '') {
                $lines[] = esc_html($variationLabel);
            }
        }

        // Passenger group quantities (legacy single-line model)
        $groupQty = is_array($args['group_qty'] ?? null) ? $args['group_qty'] : [];
        if (!empty($groupQty)) {
            $groupLabels = $this->getGroupLabels();
            foreach ($groupQty as $groupId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $label = $groupLabels[$groupId] ?? $groupId;
                $lines[] = sprintf(
                    '%s x %d',
                    esc_html($label),
                    $qty
                );
            }
        }

        return $lines;
    }

    /**
     * Get passenger group labels from the tour pricing extension.
     *
     * @return array<string, string> group ID => label
     */
    protected function getGroupLabels(): array
    {
        if (!class_exists('\Jankx\Extensions\TourPricing\Settings')) {
            return [];
        }

        $groups = \Jankx\Extensions\TourPricing\Settings::getGroups();
        $labels = [];
        foreach ($groups as $group) {
            $labels[$group['id']] = $group['label'] ?? $group['id'];
        }
        return $labels;
    }
}
