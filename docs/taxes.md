# Hệ Thống Thuế (Tax System) - Jankx Ecommerce

Hệ thống tính thuế của Jankx Ecommerce được thiết kế để xử lý linh hoạt mọi nghiệp vụ phức tạp của thương mại điện tử, đồng thời cung cấp kiến trúc rành mạch qua các Design Pattern (Strategy, Registry).

Tài liệu này hướng dẫn cách hệ thống Thuế hoạt động, cách cấu hình cơ bản, và cách Developers lập trình mở rộng nó.

---

## 1. Kiến trúc tổng quan (Architecture)

Quy trình tính toán thuế được phân rã thành các layer:

- **`TaxManager` (Registry)**: Trung tâm điều phối. Nó đọc cấu hình settings, đăng ký các loại thuế hiện có (`TaxRate`) vào mảng hệ thống và điều phối việc tính toán ở giỏ hàng.
- **`TaxStrategyInterface` (Strategy Pattern)**: Định nghĩa *thuật toán* tính thuế. Hệ thống cung cấp sẵn 2 loại:
  - **`InclusiveTaxStrategy` (Mặc định)**: Giá tiền của mặt hàng hiển thị cho khách ĐÃ BAO GỒM THUẾ. Hệ thống chỉ xử lý toán học để trích xuất khoản tiền thuế từ tổng tiền (Không làm khách hàng bỏ giỏ hàng vì thấy tổng giá tự nhiên bị cao lên ở bước chót).
  - **`ExclusiveTaxStrategy`**: Thuế được cộng đè lên trên tổng giá trị hàng hoá ở Giỏ hàng. Khách phải trả thêm phần tiền này.
- **`TaxRate` (DTO)**: Đối tượng lưu trữ ID, tên hiển thị, tỷ lệ (%), và priority tính toán.

---

## 2. Hướng dẫn Cấu hình cơ bản (End-users)

Quản trị viên có thể vào **Cài đặt chung -> Thuế** (Ecommerce Settings) để tuỳ chỉnh:

1. **Bật/Tắt tính toán thuế**: Nếu tắt, giỏ hàng sẽ không tạo ra field chi tiết thuế.
2. **Chọn Chiến lược tính**: "Đã bao gồm" hoặc "Cộng thêm".
3. **Khai báo các loại thuế**: Sử dụng form Textarea nguyên thuỷ (SSR Fallback) với cú pháp 1 dòng cho mỗi loại thuế:
   ```
   Tên loại thuế | Tỷ lệ phần trăm | Độ ưu tiên
   ```
   Ví dụ:
   ```
   VAT | 10 | 1
   City Tax | 5 | 2
   ```

---

## 3. Dành cho Developers (Mở rộng & Tích hợp CSR)

Hệ thống cung cấp một lượng lớn các WordPress Hooks để mở rộng theo cả Logic tính toán lẫn Giao diện cài đặt.

### 3.1. Dùng code đẩy các loại thuế vào tuỳ ý
Nếu extension của bạn tự động tính thuế dựa theo điều kiện riêng và không muốn Admin nhập tay, móc vào hook `jankx/ecommerce/tax/register_rates`.

```php
add_action('jankx/ecommerce/tax/register_rates', function ($taxManager) {
    // Tự động đẩy thuế môi trường 2% vào
    $taxManager->addRate(new \Jankx\Extensions\Ecommerce\Tax\TaxRate('env_tax', 'Thuế Môi Trường', 0.02, 100));
});
```

### 3.2. Override Chiến lược tính thuế động
Tuỳ theo quốc gia checkout của khách hàng, extension có thể biến đổi cửa hàng của bạn từ `Inclusive` sang `Exclusive` động.

```php
add_filter('jankx/ecommerce/tax/strategy', function ($currentStrategy) {
    if (jankx_get_customer_country() === 'US') { // Ví dụ Mỹ thì chuyên xài Exclusive tax
        return new \Jankx\Extensions\Ecommerce\Tax\ExclusiveTaxStrategy();
    }
    return $currentStrategy;
});
```

### 3.3. Thay đổi giao diện Cài Đặt (SSR Override từng phần)
Field `TaxRates` nhập tay bằng text có thể tiềm ẩn sai sót cho người dùng. Developer có thể gỡ thẻ `<textarea>` mặc định đi, và chèn một block giao diện đẹp hơn (ví dụ Dropdown/Repeater field) vào thay thế.

```php
// Hàm admin khởi tạo
add_action('admin_init', function () {
    $ecommerceSettingsInstance = // Get instance...

    // Xoá bỏ ô nhập text mặc định
    remove_action('jankx/ecommerce/settings/tax/render_fields', [$ecommerceSettingsInstance, 'renderTaxFieldRates'], 30);

    // Chèn ô nhập tuỳ chỉnh
    add_action('jankx/ecommerce/settings/tax/render_fields', 'my_custom_ssr_tax_rates_field', 30);
});

function my_custom_ssr_tax_rates_field() {
    // Render custom fields ở đây
    echo '<tr>...Custom Advanced UI...</tr>';
}
```

### 3.4. Overwrite Hoàn toàn bằng CSR (Custom React / Vue App)
Đối với hệ thống thiết lập thuế cực kỳ hoành tráng đòi hỏi giao diện CSR App chạy bằng Frontend Engine, hệ thống cho phép **xoá toàn bộ form SSR**:

```php
// 1. Tuyên bố ghi đè
add_filter('jankx/ecommerce/settings/tax/override_render', '__return_true');

// 2. Trả container rỗng, JavaScript file của React sẽ đảm nhiệm render
add_action('jankx/ecommerce/settings/tax/custom_render', function () {
    echo '<div id="jankx-advanced-tax-react-root"></div>';
});
```

Cấu trúc như vậy vừa đảm bảo sự nhẹ nhàng ở code core, vừa bảo vệ tính bảo mật, và đáp ứng tuyệt đối khả năng lập trình mở rộng!
