# Hướng dẫn tích hợp Currency Conversion cho các Extension

## Tóm tắt
- Giá được **lưu trữ** với **default currency** (thường là USD)
- Khi **hiển thị**, giá **tự động convert** sang **currency hiện tại** của user
- **Không cần** sửa code để đổi converter - chỉ cần cài API key

## Cho mỗi Extension

### flexible-tour-pricing
**Vị trí lưu giá:** `_tour_base_price`, `_tour_calendar_price` (meta)
**Vị trí hiển thị:** `PriceComputer`, `TourPricingController`

**Cần update:**
```php
// Thay vì hardcode VND, dùng:
use Jankx\Extensions\Ecommerce\Currency\CurrencyManager;

$baseCurrency = CurrencyManager::getDefaultCurrency(); // USD
$displayCurrency = CurrencyManager::getCurrentCurrency(); // VND, EUR, etc.

// Khi calculate giá
$price = $this->calculatePrice();
$displayPrice = CurrencyManager::convertPrice($price, $baseCurrency, $displayCurrency);
```

**File cần update:**
- `src/Pricing/PriceComputer.php` - thêm method `getPriceFormatted()`
- `src/Rest/TourPricingController.php` - format giá trước khi return JSON

### travel
**Vị trí lưu giá:** `_experience_starting_price`, `_tour_price` (meta)
**Vị trí hiển thị:** Blocks & Shortcodes

**Đã update:**
- ✅ `src/Blocks/PostStartingPriceBlock.php` - dùng `CurrencyManager::formatPriceWithConversion()`

**Cần update thêm:**
- `src/Forms/BookingRequestHandler.php` - format giá trong order confirmation
- Các block khác nếu hiển thị giá

### ecommerce-product
**Vị trí lưu giá:** `_product_price` (meta)
**Vị trí hiển thị:** Product blocks, single product page

**Cần update:**
- Tìm nơi hiển thị product price
- Dùng `CurrencyManager::formatPriceWithConversion($price)`

### coupon-system
**Vị trí lưu giá:** Discount amount (fixed) hoặc percentage
**Vị trí hiển thị:** Coupon list, discount display

**Cần update:**
- Nếu lưu discount amount cố định, cần convert nó cùng với giá hàng
- VD: Coupon -100000 VND, user xem bằng USD → convert -4 USD

```php
// Discount amount stored in default currency
$discountAmount = $coupon->getDiscount(); // e.g., 100000
$displayDiscount = CurrencyManager::formatPriceWithConversion(
    $discountAmount,
    CurrencyManager::getDefaultCurrency()
);
```

## Design Pattern sử dụng

### Strategy Pattern (Converters)
```
CurrencyConverterInterface
├── NoOpConverter (default)
├── OpenExchangeRatesConverter
├── FixerIOConverter
└── Custom converters (extensible)
```

### Decorator Pattern (Caching)
```
CacheDecoratorConverter
└── wraps any CurrencyConverterInterface
    └── caches results 24h
```

### Singleton Pattern (Managers)
```
CurrencyConverterManager::getInstance()
CurrencyManager::get_instance()
```

## API Design

### Display prices
```php
// Auto-convert from default to current currency
echo CurrencyManager::formatPriceWithConversion($price);

// With explicit currencies
echo CurrencyManager::formatPriceWithConversion($price, 'USD', 'VND');

// Just format without conversion (backward compat)
echo CurrencyManager::formatPrice($price, 'VND');
```

### Get raw converted value
```php
$converted = CurrencyManager::convertPrice(100, 'USD', 'VND');
// Returns float, no formatting
```

### Get current state
```php
CurrencyManager::getCurrentCurrency(); // 'VND'
CurrencyManager::getDefaultCurrency(); // 'USD'
CurrencyManager::getEnabledCurrencies(); // ['USD', 'VND', 'EUR']
```

## Thêm API converter custom (Payment Gateway)

Ví dụ: OnePay payment gateway có API lấy tỉ giá thực tế

```php
<?php
namespace Jankx\Extensions\OnepayPaymentGateway\Converters;

use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterInterface;

class OnepayRateConverter implements CurrencyConverterInterface
{
    private $apiKey;
    
    public function __construct() {
        $this->apiKey = get_option('onepay_api_key');
    }

    public function getRate(string $fromCode, string $toCode): ?float
    {
        // Call OnePay API để lấy tỉ giá
        $response = wp_remote_get('https://onepay.vn/api/rate', [
            'body' => ['from' => $fromCode, 'to' => $toCode],
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey]
        ]);
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['rate'] ?? null;
    }

    public function convert(float $amount, string $fromCode, string $toCode): ?float
    {
        $rate = $this->getRate($fromCode, $toCode);
        return $rate ? round($amount * $rate, 2) : null;
    }

    public function isReady(): bool {
        return !empty($this->apiKey);
    }

    public function getName(): string {
        return 'OnePay Exchange Rates';
    }

    public function getDescription(): string {
        return 'Official rates from OnePay payment gateway';
    }
}
```

Đăng ký trong OnepayPaymentGateway::__construct():
```php
add_action('jankx/ecommerce/currency/register_converters', function($manager) {
    $manager->register('onepay', OnepayRateConverter::class);
});
```

## Caching Strategy

1. **Exchange rates cached 24h** - quá đủ vì tỉ giá ít thay đổi trong ngày
2. **Per-conversion cached** - cache từng conversion result (100 USD → VND)
3. **Automatic invalidation** - khi user đổi converter hoặc admin update config

**Manuallly clear cache:**
```php
$manager = CurrencyConverterManager::getInstance();
$converter = $manager->getActiveConverter();
if ($converter instanceof CacheDecoratorConverter) {
    $converter->clearCache();
}
```

## Data Flow

### Khi user thêm tour (input)
1. Admin nhập giá: **100 USD**
2. Lưu vào DB: `_experience_price = 100`, `_experience_currency = USD`
3. ✅ Default currency config: USD

### Khi user xem tour (output)
1. Get từ DB: price = 100, currency = USD
2. Get current user currency: VND
3. Convert: 100 USD × 24000 = 2,400,000 VND
4. Format & display: **2.400.000₫**

### Khi admin change converter
1. Tạo order cũ: giá USD (original)
2. Tạo order mới: giá theo converter mới
3. Report có thể khác vì convert mới vs cũ

## Backward Compatibility

Các extension cũ vẫn hoạt động:
- `CurrencyManager::formatPrice()` - hoạt động như trước
- Các hardcode `number_format()` - vẫn work nhưng không convert
- Migration dần dần, không force

## Testing

```php
// Mock converter cho tests
use Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager;

add_action('jankx/ecommerce/currency/register_converters', function($manager) {
    $manager->register('test', TestMockConverter::class);
});

// In test
CurrencyConverterManager::getInstance()->setActiveConverter('test');
```

## Q&A

**Q: Giá cũ đã lưu theo VND, cần migrate?**
A: Không bắt buộc. Hãy đổi `_experience_currency = 'VND'` cho các tour cũ, rồi set default converter là NoOp (không convert).

**Q: Nhiều site, mỗi site dùng currency khác?**
A: Dùng multisite + custom converter logic. Mỗi site có option riêng cho converter.

**Q: Thêm currency mới?**
A: Update `CurrencyManager::$allCurrencies` + enable trong settings.

**Q: API lỗi, giá hiển thị sao?**
A: Fallback hiển thị giá original (không convert). Fail gracefully.
