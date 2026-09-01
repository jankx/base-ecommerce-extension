<?php
namespace Jankx\Extensions\Ecommerce\Tax;

/**
 * Thuế độc lập với giá (Tax Exclusive)
 *
 * Thuật toán: Giá trị thuế = Giá * % rate
 * Customer sẽ phải trả: Giá ban đầu + Tổng giá trị thuế.
 */
class ExclusiveTaxStrategy implements TaxStrategyInterface
{
    public function calculateTaxes(float $amount, array $rates): array
    {
        $taxes = [];

        foreach ($rates as $rate) {
            $taxes[$rate->id] = round($amount * $rate->rate, 2);
        }

        return $taxes;
    }

    public function isExclusive(): bool
    {
        return true;
    }
}
