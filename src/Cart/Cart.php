<?php
namespace Jankx\Extensions\Ecommerce\Cart;

use Jankx\Extensions\Ecommerce\Contracts\CartInterface;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

/**
 * Session based cart.
 *
 * Works for guests and logged-in users: a random cart key is stored in a
 * cookie and the cart payload lives in a transient. Prices are always
 * resolved at read time so the cart reflects the latest product prices.
 *
 * @package Jankx\Extensions\Ecommerce
 */
class Cart implements CartInterface
{
    const CART_COOKIE = 'jankx_cart_key';
    const CART_TRANSIENT_PREFIX = 'jankx_cart_';
    const CART_TTL = 30 * DAY_IN_SECONDS;

    /**
     * @var Cart|null
     */
    protected static $instance;

    /**
     * @var string
     */
    protected $cartKey;

    /**
     * Raw payload: itemKey => [product_id, quantity, args]
     *
     * @var array<string, array>
     */
    protected $items = [];

    /**
     * @var bool
     */
    protected $loaded = false;

    public static function get_instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getCartKey(): string
    {
        if (!$this->cartKey) {
            $this->cartKey = $this->readOrCreateCartKey();
        }

        return $this->cartKey;
    }

    protected function readOrCreateCartKey(): string
    {
        $key = isset($_COOKIE[self::CART_COOKIE])
            ? sanitize_key($_COOKIE[self::CART_COOKIE])
            : '';

        if (!$key) {
            $key = md5(uniqid('jankx_cart_', true));
            if (!headers_sent()) {
                setcookie(self::CART_COOKIE, $key, time() + self::CART_TTL, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }
        }

        return $key;
    }

    protected function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        $data = get_transient(self::CART_TRANSIENT_PREFIX . $this->getCartKey());
        $this->items = is_array($data) ? $data : [];
    }

    protected function save(): void
    {
        set_transient(self::CART_TRANSIENT_PREFIX . $this->getCartKey(), $this->items, self::CART_TTL);
    }

    protected function buildItemKey(int $productId, array $args = []): string
    {
        return md5($productId . '|' . wp_json_encode($args));
    }

    public function addItem(int $productId, int $quantity = 1, array $args = []): bool
    {
        $this->load();

        $product = ProductRegistry::get_instance()->createProduct($productId);
        if (!$product || !$product->isPurchasable()) {
            return false;
        }

        $quantity = max(1, absint($quantity));
        $itemKey = $this->buildItemKey($productId, $args);

        if (isset($this->items[$itemKey])) {
            $this->items[$itemKey]['quantity'] += $quantity;
        } else {
            $this->items[$itemKey] = [
                'product_id' => $productId,
                'quantity'   => $quantity,
                'args'       => $args,
            ];
        }

        $this->save();
        do_action('jankx/ecommerce/cart/item_added', $itemKey, $this);

        return true;
    }

    public function updateItem(string $itemKey, int $quantity): bool
    {
        $this->load();

        if (!isset($this->items[$itemKey])) {
            return false;
        }

        if ($quantity <= 0) {
            return $this->removeItem($itemKey);
        }

        $this->items[$itemKey]['quantity'] = absint($quantity);
        $this->save();
        do_action('jankx/ecommerce/cart/item_updated', $itemKey, $this);

        return true;
    }

    public function removeItem(string $itemKey): bool
    {
        $this->load();

        if (!isset($this->items[$itemKey])) {
            return false;
        }

        unset($this->items[$itemKey]);
        $this->save();
        do_action('jankx/ecommerce/cart/item_removed', $itemKey, $this);

        return true;
    }

    public function emptyCart(): void
    {
        $this->load();
        $this->items = [];
        $this->save();
        do_action('jankx/ecommerce/cart/emptied', $this);
    }

    /**
     * @return CartItem[]
     */
    public function getItems(): array
    {
        $this->load();

        $items = [];
        foreach ($this->items as $itemKey => $data) {
            $items[$itemKey] = new CartItem(
                $itemKey,
                (int) $data['product_id'],
                (int) $data['quantity'],
                is_array($data['args'] ?? null) ? $data['args'] : []
            );
        }

        return $items;
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->getItems() as $item) {
            $count += $item->getQuantity();
        }

        return $count;
    }

    public function isEmpty(): bool
    {
        return count($this->getItems()) === 0;
    }

    public function getSubtotal(): float
    {
        $subtotal = 0.0;
        foreach ($this->getItems() as $item) {
            $subtotal += $item->getSubtotal();
        }

        return (float) round($subtotal, 2);
    }

    public function getDiscount(): float
    {
        return (float) apply_filters('jankx/ecommerce/cart/discount', 0.0, $this);
    }

    public function getTotal(): float
    {
        $total = $this->getSubtotal() - $this->getDiscount();
        $total = apply_filters('jankx/ecommerce/cart/total', $total, $this);

        return (float) round($total, 2);
    }

    public function toArray(): array
    {
        return [
            'cart_key' => $this->getCartKey(),
            'items'    => array_map(function (CartItem $item) {
                return $item->toArray();
            }, array_values($this->getItems())),
            'subtotal' => $this->getSubtotal(),
            'discount' => $this->getDiscount(),
            'total'    => $this->getTotal(),
            'count'    => $this->getItemCount(),
        ];
    }
}
