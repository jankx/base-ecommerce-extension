<?php
namespace Jankx\Extensions\Ecommerce\Blocks;

use Jankx\Extensions\Ecommerce\Block;
use Jankx\Extensions\Ecommerce\Cart\Cart;

abstract class CheckoutSectionBlock extends Block
{
    protected function formatPrice(float $price): string
    {
        $converterManager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();

        return $converterManager->formatPriceWithConversion($price);
    }

    protected function getPaymentMethods(): array
    {
        $methods = [];
        $enabledGateways = get_option('jankx_payment_gateways', []);

        $shortNames = [
            'bank_transfer'   => __('Chuyển khoản', 'base-ecommerce'),
            'cod'             => __('COD', 'base-ecommerce'),
            'onepay'          => __('Thẻ quốc tế', 'base-ecommerce'),
            'onepay_domestic' => __('Thẻ nội địa', 'base-ecommerce'),
            'momo'            => __('Ví Momo', 'base-ecommerce'),
            'zalopay'         => __('Ví Zalopay', 'base-ecommerce'),
            'qrviet'          => __('Quét mã QR', 'base-ecommerce'),
        ];

        $resolveName = function (string $slug, string $fullName) use ($shortNames): string {
            $label = $shortNames[$slug] ?? $fullName;
            return (string) apply_filters("jankx/ecommerce/checkout/gateway_name/{$slug}", $label, $fullName);
        };

        $builtIn = [
            'bank_transfer' => __('Chuyển khoản ngân hàng', 'base-ecommerce'),
            'cod' => __('Thanh toán khi nhận hàng (COD)', 'base-ecommerce'),
        ];

        $onlineGateways = [];
        if (class_exists('\Jankx\Extensions\PaymentSystem\Gateways\GatewayManager')) {
            $manager = \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance();
            foreach ($manager->getAll() as $slug => $unused) {
                $gateway = $manager->get($slug);
                if ($gateway) {
                    $onlineGateways[$slug] = $gateway->getName();
                }
            }
        }

        $allGateways = array_merge($builtIn, $onlineGateways);

        $enabledGateways = get_option('jankx_payment_gateways', false);

        if ($enabledGateways === false) {
            // First install default: built-in methods only
            foreach ($builtIn as $slug => $fullName) {
                $methods[$slug] = $resolveName($slug, $fullName);
            }
        } else {
            foreach ((array) $enabledGateways as $slug) {
                if (isset($allGateways[$slug])) {
                    $methods[$slug] = $resolveName($slug, $allGateways[$slug]);
                }
            }
        }

        // Sort by admin-configured position (ascending), keeping the current
        // order for gateways without a position value.
        $positions = (array) get_option('jankx_payment_gateways_order', []);
        if (!empty($positions)) {
            $scores = [];
            $defaultPosition = 10000;
            foreach ($methods as $slug => $label) {
                $scores[$slug] = isset($positions[$slug]) ? (int) $positions[$slug] : $defaultPosition++;
            }
            $orderIndex = array_flip(array_keys($methods));
            uksort($methods, function ($a, $b) use ($scores, $orderIndex) {
                if ($scores[$a] === $scores[$b]) {
                    return $orderIndex[$a] <=> $orderIndex[$b];
                }
                return $scores[$a] <=> $scores[$b];
            });
        }

        return (array) apply_filters('jankx/ecommerce/checkout/payment_methods', $methods);
    }

    protected function getCreditsIntegration()
    {
        $class = '\Jankx\Extensions\UserCredits\Integration\CheckoutIntegration';
        if (!class_exists($class)) {
            return null;
        }

        return $class::get_instance();
    }

    protected function getCreditDiscount(Cart $cart): float
    {
        $integration = $this->getCreditsIntegration();
        if (!$integration) {
            return 0.0;
        }

        return (float) $integration->getAppliedCreditDiscount($cart);
    }
}