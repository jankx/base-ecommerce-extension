<?php
namespace Jankx\Extensions\Ecommerce\Ajax\Controller;

use Jankx\Ajax\Controller\AbstractController;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Registry\ProductRegistry;

class CartController extends AbstractController
{
    public function get(array $params = []): void
    {
        $mode = $this->input('mode', 'normal');
        if ($mode === 'quick') {
            // Falls back to the regular cart when the quick session expired,
            // so a stale quick request never reports an empty cart.
            $this->success(Cart::get_active_cart()->toArray());
            return;
        }

        $this->success(Cart::get_instance()->toArray());
    }

    public function addItem(array $params = []): void
    {
        $productId = (int) $this->input('product_id', 0);
        $quantity = (int) $this->input('quantity', 1);
        $mode = $this->input('mode', 'normal');
        $args = $this->input('args', []);
        $args = is_array($args) ? $this->sanitizeArgs($args) : [];

        if ($mode === 'quick') {
            // Target the quick scope directly and replace the quick session,
            // never the main cart.
            $cart = Cart::getQuickCart();
            $cart->emptyCart();

            $added = $cart->addItem($productId, $quantity, $args);

            if ($added) {
                Cart::enableQuickMode();
            }
        } else {
            $added = Cart::get_instance()->addItem($productId, $quantity, $args);
            $cart = Cart::get_instance();
        }

        if (!$added) {
            $this->error($this->describeAddFailure([$productId]), 400);
            return;
        }

        $this->success([
            'mode' => $mode,
            'cart' => $cart->toArray(),
        ]);
    }

    public function addBatch(array $params = []): void
    {
        $lines = $this->input('lines', []);
        $lines = is_array($lines) ? $lines : [];
        $commonArgs = $this->input('args', []);
        $commonArgs = is_array($commonArgs) ? $this->sanitizeArgs($commonArgs) : [];
        
        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int) ($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $normalized[] = [
                'product_id'      => $productId,
                'quantity'        => max(0, (int) ($line['quantity'] ?? $line['qty'] ?? 1)),
                'variation_id'    => sanitize_text_field((string) ($line['variation_id'] ?? '')),
                'variation_label' => sanitize_text_field((string) ($line['variation_label'] ?? '')),
            ];
        }

        if (empty($normalized)) {
            $this->error(__('Không có sản phẩm nào để thêm vào giỏ hàng.', 'jankx'), 400);
            return;
        }

        $mode = $this->input('mode', 'normal');
        if ($mode === 'quick') {
            $cart = Cart::getQuickCart();
            $added = $cart->quickAddItems($normalized, $commonArgs);
        } else {
            $added = Cart::get_instance()->addItems($normalized, $commonArgs);
            $cart = Cart::get_instance();
        }

        if ($added === 0) {
            $this->error($this->describeAddFailure(array_column($normalized, 'product_id')), 400);
            return;
        }

        $this->success([
            'mode'  => $mode,
            'added' => $added,
            'cart'  => $cart->toArray(),
        ]);
    }

    public function removeItem(array $params = []): void
    {
        $itemKey = $params[0] ?? '';
        if (empty($itemKey)) {
            $itemKey = $this->input('item_key', '');
        }

        $removed = Cart::get_instance()->removeItem((string) $itemKey);

        if (!$removed) {
            $this->error(__('Không tìm thấy sản phẩm trong giỏ hàng.', 'base-ecommerce'), 404);
            return;
        }

        $this->success([
            'cart' => Cart::get_instance()->toArray(),
        ]);
    }

    public function updateQuantity(array $params = []): void
    {
        $itemKey = $params[0] ?? '';
        if (empty($itemKey)) {
            $itemKey = $this->input('item_key', '');
        }
        $quantity = (int) $this->input('quantity', 1);

        $updated = Cart::get_instance()->updateItem((string) $itemKey, $quantity);

        if (!$updated) {
            $this->error(__('Không tìm thấy sản phẩm trong giỏ hàng.', 'base-ecommerce'), 404);
            return;
        }

        $this->success([
            'cart' => Cart::get_instance()->toArray(),
        ]);
    }

    protected function sanitizeArgs(array $args): array
    {
        $clean = [];
        foreach ($args as $key => $value) {
            if (is_array($value)) {
                $clean[sanitize_key((string) $key)] = $this->sanitizeArgs($value);
            } else {
                $clean[sanitize_key((string) $key)] = sanitize_text_field((string) $value);
            }
        }
        return $clean;
    }

    protected function describeAddFailure(array $productIds): string
    {
        foreach (array_unique(array_map('intval', $productIds)) as $productId) {
            if ($productId <= 0) {
                continue;
            }

            $product = ProductRegistry::get_instance()->createProduct($productId);
            if ($product && $product->getPrice() <= 0) {
                return sprintf(
                    __('Sản phẩm "%s" chưa được cấu hình giá.', 'jankx'),
                    $product->getName()
                );
            }
        }

        return __('Sản phẩm không hợp lệ hoặc không thể mua.', 'jankx');
    }
}
