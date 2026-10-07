<?php
namespace Jankx\Extensions\Ecommerce\Ajax\Controller;

use Jankx\Ajax\Controller\AbstractController;
use Jankx\Ajax\Middleware\NonceMiddleware;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutManager;
use Jankx\Extensions\Ecommerce\Checkout\CheckoutResponse;

/**
 * CheckoutController – Fast AJAX counterpart of POST /wp-json/.../checkout.
 *
 * Endpoint:
 *   POST /jankx-ajax/ecommerce/checkout/submit
 *
 * The browser posts the same body as the REST route and gets the same payload
 * back (CheckoutResponse), only wrapped in the Fast AJAX envelope
 * `{success, data}` instead of the flat REST object.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class CheckoutController extends AbstractController
{
    protected array $middlewares = [
        NonceMiddleware::class,
    ];

    /**
     * Create an order from the current cart and start the payment.
     */
    public function submit(array $params = []): void
    {
        if (!$this->isPost()) {
            $this->error(__('Chỉ chấp nhận phương thức POST.', 'base-ecommerce'), 405);
            return;
        }

        // Checkout talks to WordPress (options, users, REST helpers). The
        // standalone Fast AJAX boot loads only the package, so answer with a
        // clean error instead of dying half-way through order creation.
        if (!defined('WPINC')) {
            $this->error(
                __('Checkout cần Fast AJAX chạy ở chế độ rewrite (bật pretty permalink).', 'base-ecommerce'),
                503
            );
            return;
        }

        $input = $this->all();

        $customer = $this->sanitizeMap(is_array($input['customer'] ?? null) ? $input['customer'] : []);
        $customer['id'] = get_current_user_id();

        $paymentParams = $this->sanitizeMap(is_array($input['payment_params'] ?? null) ? $input['payment_params'] : []);

        // Terms acceptance is enforced only when required by the Payment settings.
        $termsError = __('Bạn phải đồng ý với Điều khoản sử dụng và Chính sách hoàn hủy để tạo đơn hàng.', 'base-ecommerce');
        if (get_option('jankx_require_terms_acceptance') && empty($input['accept_terms'])) {
            $this->error($termsError, 400, ['errors' => [$termsError]]);
            return;
        }

        // The posted mode decides the scope - never a leftover flag - so a
        // checkout submitted from the regular cart always orders the regular
        // cart. A quick checkout whose session vanished falls back to the
        // regular cart instead of creating an empty order.
        $cart = Cart::get_instance();
        if (($input['mode'] ?? '') === 'quick') {
            $quickCart = Cart::getQuickCart();
            if (!$quickCart->isEmpty()) {
                $cart = $quickCart;
            }
        }

        $result = CheckoutManager::get_instance()->checkout($cart, $customer, [
            'gateway'        => sanitize_key((string) ($input['gateway'] ?? '')),
            'payment_params' => $paymentParams,
            'create_account' => !empty($input['create_account']),
        ]);

        if (empty($result['success'])) {
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            $this->error(
                $errors ? implode(', ', $errors) : __('Không thể tạo đơn hàng, vui lòng thử lại.', 'base-ecommerce'),
                400,
                ['errors' => $errors]
            );
            return;
        }

        if ($cart->isQuickScope()) {
            Cart::disableQuickMode();
        }

        $this->success(CheckoutResponse::build($result));
    }

    /**
     * Recursively keep only scalar values so arbitrary request input never
     * reaches sanitize_text_field() as an array (TypeError on PHP 8).
     *
     * @param array $input
     * @return array<string, string>
     */
    protected function sanitizeMap(array $input): array
    {
        $clean = [];

        foreach ($input as $key => $value) {
            $key = sanitize_key((string) $key);

            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeMap($value);
            } elseif (is_scalar($value)) {
                $clean[$key] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }
}
