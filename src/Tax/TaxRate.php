<?php
namespace Jankx\Extensions\Ecommerce\Tax;

/**
 * Data Transfer Object cho một loại thuế.
 *
 * @package Jankx\Extensions\Ecommerce\Tax
 */
class TaxRate
{
    public string $id;
    public string $name;
    public float $rate; // VD: 0.1 cho 10%, 0.08 cho 8%
    public int $priority;

    public function __construct(string $id, string $name, float $rate, int $priority = 10)
    {
        $this->id = $id;
        $this->name = $name;
        $this->rate = apply_filters('jankx/ecommerce/tax/rate', $rate, $id);
        $this->priority = $priority;
    }
}
