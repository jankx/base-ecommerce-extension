# base-ecommerce-extension
Jankx E-Commerce Base Extension

## Mô tả

Extension nền tảng cho hệ thống e-commerce của Jankx (mô hình giống WooCommerce). Cung cấp các thành phần dùng chung cho mọi ngành/business model:

- **Giỏ hàng (Cart)** — giỏ hàng theo phiên (cookie + transient), tính giá thời điểm thực.
- **Checkout** — tạo đơn hàng từ giỏ hàng, xác thực thông tin khách hàng.
- **Đơn hàng (Order)** — CPT `jankx_order` dùng chung + API quản lý trạng thái.
- **Thanh toán (Payment)** — cầu nối tới extension **payment-system** (Transaction, gateway), đồng thời expose các action `jankx/ecommerce/payment/*`.
- **Product Registry** — API để mọi extension đăng ký post type của mình vào cart/checkout/payment/order.

## Cách extension con sử dụng

### 1. Khai báo dependency

Trong `manifest.json` của extension con (ví dụ `ecommerce-product`, `travel`):

```json
{
  "extension_id": "my-business",
  "dependencies": {
    "extensions": ["base-ecommerce"]
  }
}
```

### 2. Đăng ký post type là sản phẩm có thể mua

```php
use Jankx\Extensions\Ecommerce\EcommerceExtension;

// Trong register_hooks() hoặc hook jankx/ecommerce/register_product_types
EcommerceExtension::register_product_type('tour', MyTourProduct::class);
```

`MyTourProduct` phải implement `Jankx\Extensions\Ecommerce\Contracts\ProductInterface`:

```php
use Jankx\Extensions\Ecommerce\Abstracts\AbstractProduct;

class MyTourProduct extends AbstractProduct
{
    public function getPrice(): float { return (float) get_post_meta($this->id, '_tour_price', true); }
    public function getRegularPrice(): float { return 0; }
    public function getSalePrice(): float { return 0; }
    public function isPurchasable(): bool { return $this->post && 'publish' === $this->post->post_status && $this->getPrice() > 0; }
    public function isInStock(): bool { return true; }
    public function getProductType(): string { return 'tour'; }
}
```

### 3. Giỏ hàng

```php
$cart = EcommerceExtension::get_cart();
$cart->addItem($tourId, 2, ['departure_date' => '2026-09-01']); // args tuỳ chọn
$cart->updateItem($itemKey, 3);
$cart->removeItem($itemKey);
$cart->getTotal();
```

### 4. Checkout + thanh toán

```php
$result = EcommerceExtension::checkout([
    'name'  => 'Nguyễn Văn A',
    'email' => 'a@example.com',
    'phone' => '0987654321',
], [
    'gateway' => 'momo', // tuỳ chọn — nếu có payment-system
]);

// $result = [ 'success' => bool, 'errors' => [], 'order' => Order|null ]
```

Hoặc qua REST API:

```
GET    /wp-json/jankx/ecommerce/v1/cart
POST   /wp-json/jankx/ecommerce/v1/cart/items   { product_id, quantity, args? }
DELETE /wp-json/jankx/ecommerce/v1/cart/items/{item_key}
POST   /wp-json/jankx/ecommerce/v1/checkout     { customer: {...}, gateway? }
```

### 5. Quản lý đơn hàng

```php
$order = new \Jankx\Extensions\Ecommerce\Order\Order($orderId);
$order->getStatus();          // pending | processing | completed | failed | cancelled
$order->updateStatus('completed');
$order->getItems();
$order->addNote('Đã liên hệ khách.');
```

## Hooks

| Hook | Tham số | Mô tả |
|------|---------|-------|
| `jankx/ecommerce/init` | (none) | Khởi tạo ecommerce core |
| `jankx/ecommerce/register_product_types` | `$registry` | Đăng ký product type theo cách hook |
| `jankx/ecommerce/product_type_registered` | `$postType, $class` | Sau khi đăng ký product type |
| `jankx/ecommerce/cart/item_added` | `$itemKey, $cart` | Thêm sản phẩm vào giỏ |
| `jankx/ecommerce/order/created` | `$order, $cart` | Đơn hàng được tạo |
| `jankx/ecommerce/order/status_changed` | `$order, $new, $old` | Trạng thái đơn hàng thay đổi |
| `jankx/ecommerce/payment/created` | `$order, $gateway, $params` | Giao dịch được tạo |
| `jankx/ecommerce/payment/process` | `$order, $gateway, $params` | Bắt đầu xử lý thanh toán |
| `jankx/ecommerce/payment/paid` | `$order, $transactionId` | Thanh toán thành công |
| `jankx/ecommerce/checkout/completed` | `$order` | Hoàn tất checkout |
