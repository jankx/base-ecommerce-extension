<?php
namespace Jankx\Extensions\Ecommerce\Order\Contracts;

use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Interface OrderCreationStrategyInterface
 *
 * Defines the contract for different order creation strategies in the ecommerce system
 * (e.g. standard checkout from Cart vs direct form order with manual processing).
 *
 * @package Jankx\Extensions\Ecommerce\Order\Contracts
 */
interface OrderCreationStrategyInterface
{
    /**
     * Get the unique name / identifier of the strategy.
     */
    public function getName(): string;

    /**
     * Validate the incoming payload for this strategy.
     *
     * @param array $data Input payload.
     * @return array List of error messages (empty if valid).
     */
    public function validate(array $data): array;

    /**
     * Create the order based on this strategy.
     *
     * @param array $data Input payload.
     * @return Order|null Created Order instance, or null on failure.
     */
    public function createOrder(array $data): ?Order;
}
