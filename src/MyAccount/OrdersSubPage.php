<?php

namespace Jankx\Extensions\Ecommerce\MyAccount;

use Jankx\Extensions\MyAccount\SubPage\AbstractSubPage;

class OrdersSubPage extends AbstractSubPage
{
    public function getSlug(): string
    {
        return 'orders';
    }

    public function getLabel(): string
    {
        return __('Orders', 'jankx');
    }

    public function getIcon(): string
    {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
    }

    public function getPriority(): int
    {
        return 15;
    }

    public function getExtension(): ?string
    {
        return 'base-ecommerce';
    }

    public function getContent(): string
    {
        return '<!-- wp:jankx/account-tab-orders /-->';
    }
}