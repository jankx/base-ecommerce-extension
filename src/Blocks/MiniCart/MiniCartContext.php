<?php
namespace Jankx\Extensions\Ecommerce\Blocks\MiniCart;

use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;
use Jankx\Extensions\Ecommerce\EcommerceExtension;

final class MiniCartContext
{
    protected Cart $cart;

    protected array $attributes;

    public function __construct(Cart $cart, array $attributes = [])
    {
        $this->cart = $cart;
        $this->attributes = $attributes;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getLimit(): int
    {
        if (empty($this->attributes['limit'])) {
            return 3;
        }

        return max(1, (int) $this->attributes['limit']);
    }

    public function isDropdown(): bool
    {
        return !empty($this->attributes['className'])
            && strpos((string) $this->attributes['className'], 'is-style-dropdown') !== false;
    }

    public function getCustomClasses(): string
    {
        if (empty($this->attributes['className'])) {
            return '';
        }

        return trim((string) $this->attributes['className']);
    }

    public function getCartUrl(): string
    {
        return EcommerceExtension::get_cart_page_url();
    }

    public function getCheckoutUrl(): string
    {
        return EcommerceExtension::get_checkout_page_url();
    }

    public function getBadgeStyleAttribute(): string
    {
        $a = $this->attributes;
        $badgeStyles = [];

        $styleMap = [
            'badgeColor'        => 'color',
            'badgeBgColor'      => 'background-color',
            'badgeTop'          => 'top',
            'badgeRight'        => 'right',
            'badgeWidth'        => 'min-width',
            'badgeHeight'       => 'height',
            'badgeFontSize'     => 'font-size',
            'badgeBorderColor'  => 'border-color',
            'badgeBorderRadius' => 'border-radius',
        ];
        foreach ($styleMap as $attr => $prop) {
            if (!empty($a[$attr])) {
                $badgeStyles[] = $prop . ': ' . $a[$attr];
            }
        }

        if (!empty($a['badgeHeight'])) {
            $badgeStyles[] = 'line-height: ' . $a['badgeHeight'];
        }
        if (!empty($a['badgeBorderWidth'])) {
            $badgeStyles[] = 'border-width: ' . $a['badgeBorderWidth'] . '; border-style: solid';
        }
        $badgeStyles = array_merge($badgeStyles, $this->flattenSpacing('badgePadding', 'padding'));
        $badgeStyles = array_merge($badgeStyles, $this->flattenSpacing('badgeMargin', 'margin'));

        if (empty($badgeStyles)) {
            return '';
        }

        return ' style="' . esc_attr(implode('; ', $badgeStyles)) . '"';
    }

    public function formatPrice(float $price): string
    {
        return CurrencyConverterManager::getInstance()->formatPriceWithConversion($price);
    }

    protected function flattenSpacing(string $attr, string $property): array
    {
        if (empty($this->attributes[$attr])) {
            return [];
        }

        $value = $this->attributes[$attr];
        if (is_string($value)) {
            return [$property . ': ' . $value];
        }
        if (!is_array($value)) {
            return [];
        }

        $rules = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (isset($value[$side]) && $value[$side] !== '') {
                $rules[] = $property . '-' . $side . ': ' . $value[$side];
            }
        }

        return $rules;
    }
}