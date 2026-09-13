<?php
namespace Jankx\Extensions\Ecommerce\Order;

use Jankx\Extensions\Ecommerce\Order\Contracts\OrderCreationStrategyInterface;
use Jankx\Extensions\Ecommerce\Order\Strategies\CartCheckoutStrategy;
use Jankx\Extensions\Ecommerce\Order\Strategies\ManualFormOrderStrategy;

/**
 * Class OrderCreationManager
 *
 * Context & Registry for the Order Creation Strategy Pattern.
 * Manages available strategies and delegates order creation requests.
 *
 * @package Jankx\Extensions\Ecommerce\Order
 */
class OrderCreationManager
{
    protected static ?self $instance = null;

    /**
     * @var array<string, OrderCreationStrategyInterface>
     */
    protected array $strategies = [];

    public function __construct()
    {
        $this->registerDefaultStrategies();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function registerDefaultStrategies(): void
    {
        $this->registerStrategy(new ManualFormOrderStrategy());
        $this->registerStrategy(new CartCheckoutStrategy());

        do_action('jankx/ecommerce/order/register_strategies', $this);
    }

    public function registerStrategy(OrderCreationStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getName()] = $strategy;
    }

    public function getStrategy(string $name): ?OrderCreationStrategyInterface
    {
        return $this->strategies[$name] ?? null;
    }

    /**
     * @return array<string, OrderCreationStrategyInterface>
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Execute order creation using the specified strategy.
     *
     * @param string $strategyName Name of the strategy to use (e.g. 'manual_form', 'checkout').
     * @param array  $data         Payload for the strategy.
     * @return array{success: bool, errors: array, order: ?Order}
     */
    public function process(string $strategyName, array $data): array
    {
        $strategy = $this->getStrategy($strategyName);
        if (!$strategy) {
            return [
                'success' => false,
                'errors'  => [sprintf(__('Chiến lược tạo đơn hàng "%s" không tồn tại.', 'jankx'), $strategyName)],
                'order'   => null,
            ];
        }

        $errors = $strategy->validate($data);
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'order'   => null,
            ];
        }

        $order = $strategy->createOrder($data);
        if (!$order) {
            return [
                'success' => false,
                'errors'  => [__('Không thể tạo đơn hàng, vui lòng thử lại sau.', 'jankx')],
                'order'   => null,
            ];
        }

        return [
            'success' => true,
            'errors'  => [],
            'order'   => $order,
        ];
    }
}
