<?php
namespace Jankx\Extensions\Ecommerce\Abstracts;

use Jankx\Extensions\Ecommerce\Contracts\OrderInterface;
use Jankx\Extensions\Ecommerce\Order\OrderModel;

abstract class AbstractOrder implements OrderInterface
{
    protected $id;
    protected $data;

    public function __construct($orderId = 0)
    {
        if ($orderId > 0) {
            $this->id = absint($orderId);
            $this->data = OrderModel::findById($this->id);
        }
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function getOrderNumber(): string
    {
        if (!$this->data) {
            return '';
        }
        return $this->data['order_number'] ?: (string) $this->id;
    }

    public function getStatus(): string
    {
        return $this->data['status'] ?? '';
    }

    public function toArray(): array
    {
        return $this->data ?? [];
    }

    // Abstract methods to be implemented
    abstract public function getCustomerId(): int;
    abstract public function getTotal(): float;
    abstract public function getItems(): array;
    abstract public function addNote(string $note, bool $isCustomerNote = false): void;
    abstract public function updateStatus(string $newStatus, string $note = ''): bool;
}
