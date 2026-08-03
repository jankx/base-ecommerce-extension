<?php
namespace Jankx\Extensions\Ecommerce\Contracts;

interface OrderInterface
{
    public function getId(): int;

    public function getOrderNumber(): string;

    public function getCustomerId(): int;

    public function getStatus(): string;

    public function getTotal(): float;

    public function getItems(): array;

    public function addNote(string $note, bool $isCustomerNote = false): void;

    public function updateStatus(string $newStatus, string $note = ''): bool;
}
