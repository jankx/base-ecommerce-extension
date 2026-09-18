<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

interface MiniCartRendererInterface
{
    public function panelId(): string;

    public function wrapperClass(): string;

    public function render(MiniCartContext $context): string;
}