<?php
namespace Jankx\Extensions\Ecommerce\Contracts;

interface ProductInterface
{
    public function getId(): int;

    public function getName(): string;

    public function getPrice(): float;

    public function getRegularPrice(): float;

    public function getSalePrice(): float;

    public function isPurchasable(): bool;

    public function isInStock(): bool;

    public function getProductType(): string;
}
