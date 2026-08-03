<?php
namespace Jankx\Extensions\Ecommerce\Abstracts;

use Jankx\Extensions\Ecommerce\Contracts\OrderInterface;

abstract class AbstractOrder implements OrderInterface
{
    protected $id;
    protected $post;

    public function __construct($orderId = 0)
    {
        if ($orderId > 0) {
            $this->id = absint($orderId);
            $this->post = get_post($this->id);
        }
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->post ? get_post_meta($this->id, '_order_number', true) ?: (string) $this->id : '';
    }

    public function getStatus(): string
    {
        return $this->post ? $this->post->post_status : '';
    }

    // Abstract methods to be implemented
    abstract public function getCustomerId(): int;
    abstract public function getTotal(): float;
    abstract public function getItems(): array;
    abstract public function addNote(string $note, bool $isCustomerNote = false): void;
    abstract public function updateStatus(string $newStatus, string $note = ''): bool;
}
