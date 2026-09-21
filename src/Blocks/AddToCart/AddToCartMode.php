<?php
namespace Jankx\Extensions\Ecommerce\Blocks\AddToCart;

class AddToCartMode
{
    const NORMAL        = 'normal';
    const CONTACT       = 'contact';
    const SCROLL_TO_FORM = 'scroll_to_form';

    private string $mode;

    public function __construct(string $mode)
    {
        $allowed = [self::NORMAL, self::CONTACT, self::SCROLL_TO_FORM];
        if (!in_array($mode, $allowed, true)) {
            $mode = self::NORMAL;
        }
        $this->mode = $mode;
    }

    public function isNormal(): bool
    {
        return $this->mode === self::NORMAL;
    }

    public function isContact(): bool
    {
        return $this->mode === self::CONTACT;
    }

    public function isScrollToForm(): bool
    {
        return $this->mode === self::SCROLL_TO_FORM;
    }

    public function value(): string
    {
        return $this->mode;
    }

    public function __toString(): string
    {
        return $this->mode;
    }
}
