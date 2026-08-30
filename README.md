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

### 6. Luồng giá theo dữ liệu tuỳ biến (nhóm khách / theo ngày)

base-ecommerce cung cấp 4 điểm mở rộng để một extension con (ví dụ `flexible-tour-pricing`) thay được toàn bộ cách tính giá — theo nhóm khách, theo ngày khởi hành... — mà không cần đụng vào core giỏ hàng/đơn hàng. Luồng hoàn chỉnh gồm:

**1. Thay form "Thêm vào giỏ"** — lọc `jankx/ecommerce/add_to_cart/form` trả về toàn bộ field set thay thế (vẫn ở bên trong thẻ `<form>` nên submit/status được giữ nguyên):

```php
add_filter('jankx/ecommerce/add_to_cart/form', function ($formBody, $product, $postId, $productType, $attributes) {
    if ($productType !== 'tour') {
        return $formBody;
    }
    // Trả về HTML chứa <select name="departure_date">,
    // <input type="number" name="group_qty[adult]">, v.v.
    return $myCustomFields;
}, 10, 5);
```

**2. Giỏ hàng** — `assets/frontend.js` tự đọc mọi input `name="group_qty[<group_id>]"`, gộp vào `args.group_qty`, kèm `departure_date`, gửi lên `POST /cart/items`. `EcommerceController::sanitizeArgs()` sanitize **đệ quy** nên map lồng nhau (`group_qty[adult] => 2`) được giữ nguyên — không bị flatten như cách `array_map('sanitize_text_field', ...)` cũ. Lọc `jankx/ecommerce/cart/item/subtotal` để tính lại thành tiền của dòng:

```php
add_filter('jankx/ecommerce/cart/item/subtotal', function ($subtotal, $cartItem) {
    $args = $cartItem->getArgs();
    $date = $args['departure_date'] ?? '';
    $qty  = $args['group_qty'] ?? [];
    return PriceEngine::calculate($cartItem->getProductId(), $date, $qty);
}, 10, 2);
```

**3. Đơn hàng** — `Order::createFromCart` truyền toàn bộ `args` của cart item vào `meta` của order item, nên `departure_date` + `group_qty` tự động có trong đơn hàng. Lọc `jankx/ecommerce/order/item_data` làm giàu item trước khi lưu (ghi breakdown vào `meta`), và lọc `jankx/ecommerce/order/item_total` trả đúng tổng đã lưu khi đọc lại:

```php
add_filter('jankx/ecommerce/order/item_data', function ($item, $cartItem, $product) {
    $item['meta']['price_breakdown'] = PriceEngine::breakdown($cartItem);
    return $item;
}, 10, 3);

add_filter('jankx/ecommerce/order/item_total', function ($total, $orderItem) {
    $meta = $orderItem->getMeta();
    return !empty($meta['price_breakdown']['subtotal'])
        ? (float) $meta['price_breakdown']['subtotal']
        : $total;
}, 10, 2);
```

> Mẹo: `apply_filters` chỉ thay đổi giá trị *hiển thị* của cart/order. Muốn giữ dấu vết đầy đủ để báo cáo tài chính, hãy luôn ghi breakdown vào `meta` (như vd trên) thay vì chỉ override con số.

## Hooks

| Kiểu | Hook | Tham số | Mô tả |
|------|------|---------|-------|
| Action | `jankx/ecommerce/init` | (none) | Khởi tạo ecommerce core |
| Action | `jankx/ecommerce/register_product_types` | `$registry` | Đăng ký product type theo cách hook |
| Action | `jankx/ecommerce/product_type_registered` | `$postType, $class` | Sau khi đăng ký product type |
| Action | `jankx/ecommerce/cart/item_added` | `$itemKey, $cart` | Thêm sản phẩm vào giỏ |
| Action | `jankx/ecommerce/order/created` | `$order, $cart` | Đơn hàng được tạo |
| Action | `jankx/ecommerce/order/status_changed` | `$order, $new, $old` | Trạng thái đơn hàng thay đổi |
| Action | `jankx/ecommerce/payment/created` | `$order, $gateway, $params` | Giao dịch được tạo |
| Action | `jankx/ecommerce/payment/process` | `$order, $gateway, $params` | Bắt đầu xử lý thanh toán |
| Action | `jankx/ecommerce/payment/paid` | `$order, $transactionId` | Thanh toán thành công |
| Action | `jankx/ecommerce/checkout/completed` | `$order` | Hoàn tất checkout |
| Filter | `jankx/ecommerce/add_to_cart/form` | `$formBody, $product, $postId, $productType, $attributes` | Thay toàn bộ field set của form "Thêm vào giỏ" (bên trong `<form>`), giữ nguyên plumbing submit/status |
| Filter | `jankx/ecommerce/cart/item/subtotal` | `$subtotal, $cartItem` | Tính lại thành tiền của dòng giỏ hàng (vd theo nhóm khách / ngày) |
| Filter | `jankx/ecommerce/order/item_data` | `$item, $cartItem, $product` | Làm giàu dữ liệu order item trước khi lưu (thêm `meta`, `unit_price`, breakdown) |
| Filter | `jankx/ecommerce/order/item_total` | `$total, $orderItem` | Tính lại tổng của order item khi đọc ra (đồng bộ với breakdown đã lưu) |
