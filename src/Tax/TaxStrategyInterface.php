<?php
namespace Jankx\Extensions\Ecommerce\Tax;

/**
 * Interface chiến lược (Strategy) tính thuế.
 */
interface TaxStrategyInterface
{
    /**
     * Tính toán chi tiết các loại thuế.
     *
     * @param float $amount Số tiền cần tính thuế
     * @param TaxRate[] $rates Các mức thuế áp dụng
     * @return array<string, float> Map of tax_id => tax_amount
     */
    public function calculateTaxes(float $amount, array $rates): array;

    /**
     * Xác định xem chiến lược này có yêu cầu "cộng thêm" vào tổng tiền (Total) hay không.
     *
     * @return bool (True = Exclusive [Cộng thêm], False = Inclusive [Đã tính sẵn trong giá])
     */
    public function isExclusive(): bool;
}
