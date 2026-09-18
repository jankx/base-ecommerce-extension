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

        $builtIn = [
            'bank_transfer' => __('Chuyển khoản ngân hàng', 'jankx'),
            'cod' => __('Thanh toán khi nhận hàng (COD)', 'jankx'),
        ];

        $onlineGateways = [];
        if (class_exists('\Jankx\Extensions\PaymentSystem\Gateways\GatewayManager')) {
            $manager = \Jankx\Extensions\PaymentSystem\Gateways\GatewayManager::getInstance();
            foreach ($manager->getAvailable() as $slug => $gateway) {
                $onlineGateways[$slug] = $gateway->getName();
            }
        }

        $allGateways = array_merge($builtIn, $onlineGateways);

        if (!empty($enabledGateways)) {
            foreach ($enabledGateways as $slug) {
                if (isset($allGateways[$slug])) {
                    $methods[$slug] = $allGateways[$slug];
                }
            }
        } else {
            $methods = $allGateways;
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