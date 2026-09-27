<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Cart\CartItem;
use Jankx\Extensions\Ecommerce\Cart\CartItemContext;

class CheckoutOrderReviewBlock extends CheckoutSectionBlock
{
    protected $blockId = 'jankx/checkout-order-review';

    protected $defaultItemInnerBlocks = [
        'jankx/checkout-review-item-name',
        'jankx/checkout-review-item-price',
    ];

    public function render($attributes, $content = '', $block = null): string
    {
        $cart = Cart::get_active_cart();
        if ($cart->isEmpty()) {
            return '';
        }

        $output = sprintf(
            '<div %s>',
            get_block_wrapper_attributes([
                'class' => 'jankx-checkout-section jankx-checkout-order-review',
            ])
        );

        // Section header
        $output .= '<div class="jankx-section-header">'
            . '<span class="jankx-section-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span>'
            . '<h2 class="jankx-section-title">' . esc_html__('Thông tin đơn hàng', 'base-ecommerce') . '</h2>'
            . '</div>';

        $output .= '<div class="jankx-section-body">';

        // Cart items list
        $output .= '<div class="jankx-order-items">';
        foreach ($cart->getItems() as $item) {
            CartItemContext::set($item);
            $output .= $this->renderOrderItem($item);
        }
        CartItemContext::set(null);
        $output .= '</div>'; // .jankx-order-items

        // Coupon / discount code
        $output .= $this->renderCouponField($cart);

        // Totals
        $output .= $this->renderTotalsRows($cart);

        // Trust guarantee badge
        $output .= '<div class="jankx-guarantee-badge">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
            . '<div>'
            . '<strong>' . esc_html__('Cam kết hoàn tiền 100%', 'base-ecommerce') . '</strong>'
            . '<p>' . esc_html__('Nếu không hài lòng về dịch vụ', 'base-ecommerce') . '</p>'
            . '</div>'
            . '</div>';

        // Benefits row
        $output .= '<div class="jankx-checkout-benefits">'
            . '<div class="jankx-benefit-item">'
            . '<span class="jankx-benefit-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></span>'
            . '<span class="jankx-benefit-label">' . esc_html__('Cam kết', 'base-ecommerce') . '</span>'
            . '<span class="jankx-benefit-desc">' . esc_html__('Giá tốt nhất', 'base-ecommerce') . '</span>'
            . '</div>'
            . '<div class="jankx-benefit-item">'
            . '<span class="jankx-benefit-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>'
            . '<span class="jankx-benefit-label">' . esc_html__('Xác nhận ngay', 'base-ecommerce') . '</span>'
            . '<span class="jankx-benefit-desc">' . esc_html__('Sau 5 phút', 'base-ecommerce') . '</span>'
            . '</div>'
            . '<div class="jankx-benefit-item">'
            . '<span class="jankx-benefit-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>'
            . '<span class="jankx-benefit-label">' . esc_html__('Hỗ trợ 24/7', 'base-ecommerce') . '</span>'
            . '<span class="jankx-benefit-desc">19001234</span>'
            . '</div>'
            . '</div>'; // .jankx-checkout-benefits

        $output .= '</div>'; // .jankx-section-body
        $output .= '</div>';

        return $output;
    }

    /**
     * Render a single order item with thumbnail, name, meta and subtotal.
     */
    protected function renderOrderItem(CartItem $item): string
    {
        $productId = $item->getProductId();
        $thumb     = get_the_post_thumbnail_url($productId, 'thumbnail');
        if (!$thumb) {
            $thumb = '';
        }

        // Meta: try to get product type label
        $typeLabel = get_post_type_labels(get_post_type_object(get_post_type($productId)))->singular_name ?? '';

        // Date arg if passed
        $date  = !empty($item->getArgs()['date']) ? $item->getArgs()['date'] : '';
        $unitQty = $item->getQuantity();

        $output = '<div class="jankx-order-item">';

        // Thumbnail
        if ($thumb) {
            $output .= '<div class="jankx-order-item-thumb">'
                . '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($item->getName()) . '" width="64" height="64" loading="lazy">'
                . '</div>';
        } else {
            $output .= '<div class="jankx-order-item-thumb jankx-order-item-thumb--placeholder">'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'
                . '</div>';
        }

        // Info
        $output .= '<div class="jankx-order-item-info">';
        $output .= '<p class="jankx-order-item-name">' . esc_html($item->getName()) . '</p>';

        $output .= '<div class="jankx-order-item-meta">';
        if ($typeLabel) {
            $output .= '<span class="jankx-meta-row"><span class="jankx-meta-key">' . esc_html__('Loại tour', 'base-ecommerce') . '</span><span class="jankx-meta-val">' . esc_html($typeLabel) . '</span></span>';
        }
        if ($date) {
            $output .= '<span class="jankx-meta-row"><span class="jankx-meta-key">' . esc_html__('Ngày', 'base-ecommerce') . '</span><span class="jankx-meta-val">' . esc_html($date) . '</span></span>';
        }
        $output .= '<span class="jankx-meta-row"><span class="jankx-meta-key">' . esc_html__('Đơn vị', 'base-ecommerce') . '</span><span class="jankx-meta-val">' . esc_html(sprintf(__('Người × %d', 'base-ecommerce'), $unitQty)) . '</span></span>';
        $output .= '<span class="jankx-meta-row jankx-meta-row--total"><span class="jankx-meta-key">' . esc_html__('Tổng cộng', 'base-ecommerce') . '</span><span class="jankx-meta-val">' . esc_html($this->formatPrice($item->getSubtotal())) . '</span></span>';
        $output .= '</div>'; // .jankx-order-item-meta

        $output .= '</div>'; // .jankx-order-item-info
        $output .= '</div>'; // .jankx-order-item

        return $output;
    }

    /**
     * Coupon / promo code input field.
     */
    protected function renderCouponField(Cart $cart): string
    {
        // Only show if coupon system is available
        $couponEnabled = class_exists('\Jankx\Extensions\CouponSystem\CouponManager');

        $output = '<div class="jankx-coupon-field">';
        $output .= '<label for="jankx_coupon_code">' . esc_html__('Mã giảm giá', 'base-ecommerce') . '</label>';
        $output .= '<div class="jankx-coupon-row">';
        $output .= '<input type="text" id="jankx_coupon_code" name="coupon_code" class="jankx-input" '
            . 'placeholder="' . esc_attr__('Nhập mã khuyến mãi', 'base-ecommerce') . '">';
        $output .= '<button type="button" class="jankx-btn jankx-btn-coupon" id="jankx-apply-coupon">'
            . esc_html__('Áp dụng', 'base-ecommerce') . '</button>';
        $output .= '</div>';
        $output .= '<div class="jankx-coupon-message" role="status"></div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render totals: subtotal, tax, discount, credits, grand total.
     */
    protected function renderTotalsRows(Cart $cart): string
    {
        $creditDiscount = $this->getCreditDiscount($cart);
        $otherDiscount  = max(0, $cart->getDiscount() - $creditDiscount);
        $taxAmount      = $cart->getTaxAmount();
        $taxTotals      = $cart->getTaxTotals();

        $output = '<div class="jankx-order-totals">';

        // Subtotal row
        $output .= '<div class="jankx-total-row">'
            . '<span class="jankx-total-label">' . esc_html__('Tạm tính', 'base-ecommerce') . '</span>'
            . '<span class="jankx-total-value">' . esc_html($this->formatPrice($cart->getSubtotal())) . '</span>'
            . '</div>';

        // Discount row
        if ($otherDiscount > 0) {
            $output .= '<div class="jankx-total-row jankx-review-discount-row">'
                . '<span class="jankx-total-label">' . esc_html__('Giảm giá', 'base-ecommerce') . '</span>'
                . '<span class="jankx-total-value jankx-total-value--discount jankx-review-discount-value">'
                . esc_html('-' . $this->formatPrice($otherDiscount)) . '</span>'
                . '</div>';
        } else {
            $output .= '<div class="jankx-total-row jankx-review-discount-row" hidden>'
                . '<span class="jankx-total-label">' . esc_html__('Giảm giá', 'base-ecommerce') . '</span>'
                . '<span class="jankx-total-value jankx-total-value--discount jankx-review-discount-value"></span>'
                . '</div>';
        }

        // Credits row
        if ($creditDiscount > 0) {
            $output .= '<div class="jankx-total-row jankx-review-credit-row">'
                . '<span class="jankx-total-label">' . esc_html__('Số dư tín dụng', 'base-ecommerce') . '</span>'
                . '<span class="jankx-total-value jankx-total-value--discount jankx-review-credit-value">'
                . esc_html('-' . $this->formatPrice($creditDiscount)) . '</span>'
                . '</div>';
        } else {
            $output .= '<div class="jankx-total-row jankx-review-credit-row" hidden>'
                . '<span class="jankx-total-label">' . esc_html__('Số dư tín dụng', 'base-ecommerce') . '</span>'
                . '<span class="jankx-total-value jankx-total-value--discount jankx-review-credit-value"></span>'
                . '</div>';
        }

        // Tax rows
        foreach ($taxTotals as $tax) {
            if (empty($tax['amount'])) {
                continue;
            }
            $taxLabel = !empty($tax['label']) ? $tax['label'] : __('Thuế GTGT', 'base-ecommerce');
            $output .= '<div class="jankx-total-row jankx-total-row--tax">'
                . '<span class="jankx-total-label">' . esc_html($taxLabel) . '</span>'
                . '<span class="jankx-total-value">' . esc_html($this->formatPrice((float) $tax['amount'])) . '</span>'
                . '</div>';
        }

        // Grand total
        $output .= '<div class="jankx-total-row jankx-total-row--grand">'
            . '<span class="jankx-total-label">' . esc_html__('Tổng cộng', 'base-ecommerce') . '</span>'
            . '<span class="jankx-total-value jankx-total-value--grand jankx-review-total-value">'
            . esc_html($this->formatPrice($cart->getTotal())) . '</span>'
            . '</div>';

        $output .= '</div>'; // .jankx-order-totals

        return $output;
    }
}