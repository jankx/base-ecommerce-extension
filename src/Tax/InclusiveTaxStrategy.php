<?php
namespace Jankx\Extensions\Ecommerce\Tax;

/**
 * Thuế đã bao gồm ở trong giá (Tax Inclusive) - Loại mặc định, phổ biến để tránh làm người dùng khó hiểu.
 *
 * Thuật toán: Giá Net = Tổng giá / (1 + Tổng % rate)
 * Giá trị thuế tương ứng = Giá Net * % rate
 */
class InclusiveTaxStrategy implements TaxStrategyInterface
{
    public function calculateTaxes(float $amount, array $rates): array
    {
        $sumRates = 0;
        foreach ($rates as $rate) {
            $sumRates += $rate->rate;
        }

        $taxes = [];
        if ($sumRates <= 0) {
            return $taxes;
        }

        $netAmount = $amount / (1 + $sumRates);

        foreach ($rates as $rate) {
            // Làm tròn giá trị thuế
            $taxes[$rate->id] = round($netAmount * $rate->rate, 2);
        }

        return $taxes;
    }

    public function isExclusive(): bool
    {
        return false;
    }
}
