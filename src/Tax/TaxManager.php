<?php
namespace Jankx\Extensions\Ecommerce\Tax;

/**
 * Registry Pattern for Tax calculation.
 * 
 * Quản lý danh sách các loại thuế được đăng ký thông qua Filter/Action
 * và Chiến lược (Strategy) tính thuế thực tế của cửa hàng.
 */
class TaxManager
{
    /** @var self|null */
    protected static $instance = null;

    /** @var TaxRate[] */
    private array $rates = [];

    /** @var TaxStrategyInterface */
    private TaxStrategyInterface $strategy;

    public static function get_instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(?TaxStrategyInterface $strategy = null)
    {
        $this->strategy = $strategy ?? new InclusiveTaxStrategy();
        $this->init();
    }

    private function init(): void
    {
        if (get_option('jankx_tax_enabled', false)) {
            // Xác định chiến lược mặc định từ setting nếu có
            if (get_option('jankx_tax_strategy', 'inclusive') === 'exclusive') {
                $this->strategy = new ExclusiveTaxStrategy();
            }

            // Đọc và phân tích chuỗi cấu hình thô
            $ratesRaw = get_option('jankx_tax_rates_raw', '');
            if (!empty(trim($ratesRaw))) {
                foreach (explode("\n", $ratesRaw) as $line) {
                    $parts = array_map('trim', explode('|', $line));
                    if (count($parts) >= 2) {
                        $name = $parts[0];
                        $rate = (float) $parts[1] / 100; // Đổi % ra float
                        $priority = (int) ($parts[2] ?? 10);

                        $id = sanitize_title($name);
                        $this->addRate(new TaxRate($id, $name, $rate, $priority));
                    }
                }
            }
        }

        // Cung cấp hook cho phép thay đổi chiến lược thuế từ external extension (ghi đè logic trên)
        $this->strategy = apply_filters('jankx/ecommerce/tax/strategy', $this->strategy);

        // Cung cấp hook cho các extensions thêm tax class vào
        do_action('jankx/ecommerce/tax/register_rates', $this);
    }

    /**
     * Add a tax rate securely.
     */
    public function addRate(TaxRate $rate): self
    {
        $this->rates[$rate->id] = $rate;
        return $this;
    }

    public function removeRate(string $id): self
    {
        unset($this->rates[$id]);
        return $this;
    }

    /**
     * Lấy các tax rate, tự động sắp xếp theo Priority
     * @return TaxRate[]
     */
    public function getRates(): array
    {
        $sorted = $this->rates;
        uasort($sorted, function (TaxRate $a, TaxRate $b) {
            return $a->priority <=> $b->priority;
        });

        return array_values($sorted);
    }

    public function setStrategy(TaxStrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getStrategy(): TaxStrategyInterface
    {
        return $this->strategy;
    }

    /**
     * Chạy thuật toán tính các loại thuế
     */
    public function calculateTaxes(float $amount): array
    {
        if (empty($this->rates)) {
            return [];
        }
        return $this->strategy->calculateTaxes($amount, $this->getRates());
    }

    /**
     * Trả về tổng tiền thuế (Mọi loại thuế cộng lại).
     */
    public function getTaxTotalAmount(float $amount): float
    {
        return (float) array_sum($this->calculateTaxes($amount));
    }
}
