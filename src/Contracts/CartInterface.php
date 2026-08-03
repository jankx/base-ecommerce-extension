<?php
namespace Jankx\Extensions\Ecommerce\Contracts;

interface CartInterface
{
    public function getItems(): array;

    public function addItem(int $productId, int $quantity = 1, array $args = []): bool;

    public function updateItem(string $itemKey, int $quantity): bool;

    public function removeItem(string $itemKey): bool;

    public function emptyCart(): void;

    public function getSubtotal(): float;

    public function getTotal(): float;
}
