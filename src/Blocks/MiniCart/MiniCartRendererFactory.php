<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

final class MiniCartRendererFactory
{
    public static function create(MiniCartContext $context): MiniCartRendererInterface
    {
        if ($context->isDropdown()) {
            return new DropdownMiniCartRenderer();
        }

        return new DrawerMiniCartRenderer();
    }
}