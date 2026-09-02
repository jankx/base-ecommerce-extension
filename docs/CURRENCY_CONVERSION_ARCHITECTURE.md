# Currency Conversion System - Visual Architecture

## System Flow Diagram

```mermaid
graph TB
    subgraph "Frontend"
        User["User (Current Currency)"]
        Block["Block/Template<br/>TourCard, Pricing"]
    end

    subgraph "Data Layer"
        PostMeta["Post Meta<br/>_tour_price: 100<br/>_currency: USD"]
    end

    subgraph "Currency Manager"
        CM["CurrencyManager<br/>- getDefaultCurrency()<br/>- getCurrentCurrency()<br/>- formatPrice()"]
        FCWC["formatPriceWithConversion()<br/>NEW Helper"]
    end

    subgraph "Converter System"
        CCM["CurrencyConverterManager<br/>Singleton"]
        
        subgraph "Converters (Strategy Pattern)"
            NoOp["NoOpConverter<br/>Always Ready"]
            OER["OpenExchangeRates<br/>Converter<br/>USD base<br/>API: oer.io"]
            Fixer["FixerIO<br/>Converter<br/>EUR base<br/>API: fixer.io"]
            Custom["Custom Converters<br/>Payment Gateways<br/>etc"]
        end

        subgraph "Decorator"
            Cache["CacheDecoratorConverter<br/>24h Cache<br/>Wraps all"]
        end
    end

    subgraph "External APIs"
        OERAPI["OpenExchangeRates.io<br/>API"]
        FixerAPI["Fixer.io<br/>API"]
    end

    subgraph "Storage"
        Options["wp_options<br/>jankx_currency_converter_type<br/>jankx_converter_config"]
        WPCache["wp_cache<br/>Exchange rates<br/>Conversion results"]
    end

    User -->|"Select Currency"| CM
    Block -->|"Get formatted price"| FCWC
    PostMeta -->|"price=100, currency=USD"| FCWC
    
    FCWC -->|"Convert USD to VND"| CCM
    FCWC -->|"Format with CurrencyManager"| CM
    
    CCM -->|"1. Check if ready"| Cache
    Cache -->|"2. Check cache"| WPCache
    WPCache -->|"Hit: Return"| CCM
    WPCache -->|"Miss: Fetch rate"| NoOp
    WPCache -->|"Miss: Fetch rate"| OER
    WPCache -->|"Miss: Fetch rate"| Fixer
    WPCache -->|"Miss: Fetch rate"| Custom
    
    OER -->|"Call API"| OERAPI
    Fixer -->|"Call API"| FixerAPI
    
    OERAPI -->|"Rate"| OER
    FixerAPI -->|"Rate"| Fixer
    
    OER -->|"Cache result"| WPCache
    Fixer -->|"Cache result"| WPCache
    Cache -->|"Result (or null)"| CCM
    
    CCM -->|"100 * 24000 = 2,400,000"| FCWC
    CM -->|"Format: 2.400.000₫"| Block
    Block -->|"Display"| User

    Options -->|"Select active"| CCM
    Options -->|"Store config"| CCM
```

## Class Diagram

```mermaid
classDiagram
    class CurrencyConverterInterface {
        <<interface>>
        +convert(float, string, string)* float|null
        +getRate(string, string)* float|null
        +isReady()* bool
        +getName()* string
        +getDescription()* string
    }

    class CurrencyConverterManager {
        -instance: CurrencyConverterManager
        -converter: CurrencyConverterInterface
        -converters: array
        +getInstance()$ CurrencyConverterManager
        +register(string, string): void
        +setActiveConverter(string): bool
        +getActiveConverter(): CurrencyConverterInterface
        +convert(float, string, string): float
        +getRate(string, string): float|null
        +formatPriceWithConversion(float, string, string): string
    }

    class NoOpConverter {
        +convert(float, string, string): float
        +getRate(string, string): float|null
        +isReady(): bool
        +getName(): string
        +getDescription(): string
    }

    class OpenExchangeRatesConverter {
        -apiKey: string
        -baseCurrency: string
        +convert(float, string, string): float|null
        +getRate(string, string): float|null
        +isReady(): bool
        +getName(): string
        +getDescription(): string
        +setApiKey(string)$ void
    }

    class FixerIOConverter {
        -apiKey: string
        -baseCurrency: string
        +convert(float, string, string): float|null
        +getRate(string, string): float|null
        +isReady(): bool
        +getName(): string
        +getDescription(): string
        +setApiKey(string)$ void
    }

    class CacheDecoratorConverter {
        -converter: CurrencyConverterInterface
        -cacheEnabled: bool
        +convert(float, string, string): float|null
        +getRate(string, string): float|null
        +getInnerConverter(): CurrencyConverterInterface
        +clearCache(): void
    }

    class CurrencyManager {
        +getDefaultCurrency()$ string
        +getCurrentCurrency()$ string
        +formatPrice(float, string)$ string
        +formatPriceWithConversion(float, string, string)$ string
        +convertPrice(float, string, string)$ float
    }

    CurrencyConverterInterface <|.. NoOpConverter
    CurrencyConverterInterface <|.. OpenExchangeRatesConverter
    CurrencyConverterInterface <|.. FixerIOConverter
    CurrencyConverterInterface <|.. CacheDecoratorConverter

    CurrencyConverterManager --> CurrencyConverterInterface : uses
    CacheDecoratorConverter --> CurrencyConverterInterface : wraps
    CurrencyManager --> CurrencyConverterManager : uses
```

## Data Flow Diagram

```mermaid
sequenceDiagram
    participant User
    participant Block as Block/Template
    participant CM as CurrencyManager
    participant CCM as CurrencyConverterManager
    participant Converter
    participant Cache
    participant API

    User->>Block: View tour with currency "VND"
    Block->>Block: Get price from DB: 100 USD
    Block->>CM: formatPriceWithConversion(100, 'USD')
    CM->>CCM: convert(100, 'USD', 'VND')
    
    CCM->>Cache: Check cache for USD→VND rate
    alt Cache Hit
        Cache-->>CCM: Rate 24000
    else Cache Miss
        CCM->>Converter: Check if ready
        alt Not Ready
            Converter-->>CCM: null
            CCM-->>CM: Return original 100
        else Ready
            CCM->>Converter: getRate('USD', 'VND')
            Converter->>API: Call OpenExchangeRates.io
            API-->>Converter: Rate 24000
            Converter-->>CCM: Rate 24000
            CCM->>Cache: Store rate 24000 (24h)
            Cache-->>CCM: OK
        end
    end
    
    CCM->>CCM: 100 × 24000 = 2,400,000
    CCM-->>CM: 2,400,000
    CM->>CM: Format: 2.400.000₫
    CM-->>Block: "2.400.000₫"
    Block-->>User: Display
```

## File Structure

```
base-ecommerce/
├── src/Currency/
│   ├── CurrencyManager.php (UPDATED)
│   │   ├── formatPriceWithConversion() [NEW]
│   │   └── convertPrice() [NEW]
│   │
│   ├── Converters/
│   │   ├── CurrencyConverterInterface.php [NEW]
│   │   ├── CurrencyConverterManager.php [NEW]
│   │   ├── NoOpConverter.php [NEW]
│   │   ├── OpenExchangeRatesConverter.php [NEW]
│   │   ├── FixerIOConverter.php [NEW]
│   │   └── CacheDecoratorConverter.php [NEW]
│   │
│   ├── Admin/
│   │   └── ConverterSettingsPage.php [NEW]
│   │
│   └── helpers.php [NEW]
│       ├── jankx_format_price_with_conversion()
│       ├── jankx_convert_price()
│       ├── jankx_get_converter_manager()
│       └── ... more helpers
│
├── CURRENCY_CONVERSION_GUIDE.md [NEW]
├── CURRENCY_CONVERSION_INTEGRATION.md [NEW]
├── CURRENCY_CONVERSION_SUMMARY.md [NEW]
└── CURRENCY_CONVERSION_EXAMPLES.php [NEW]

travel/src/Blocks/
└── PostStartingPriceBlock.php (UPDATED)
    └── Uses CurrencyManager::formatPriceWithConversion()
```

## Configuration Flow

```mermaid
graph LR
    Admin["Admin Panel<br/>Ecommerce → Currency"]
    Select["1. Select Converter<br/>nopp | openexchangerates<br/>| fixerio | custom"]
    Config["2. Configure<br/>API Key<br/>Base Currency"]
    Store["3. Store in DB<br/>wp_options<br/>jankx_currency_converter_type<br/>jankx_converter_config"]
    Manager["4. Load in Manager<br/>CurrencyConverterManager<br/>getInstance()"]
    Active["5. Active Converter<br/>Wrapped with<br/>CacheDecoratorConverter"]
    Ready["6. Ready to Use<br/>formatPriceWithConversion()<br/>convertPrice()"]

    Admin -->|Select| Select
    Select -->|Add details| Config
    Config -->|Save| Store
    Store -->|Init| Manager
    Manager -->|Set| Active
    Active -->|Use| Ready
```

## Extension Integration Pattern

```mermaid
graph TD
    Extension["Your Extension<br/>flexible-tour-pricing<br/>ecommerce-product<br/>travel"]
    
    Old["OLD: Hardcode<br/>if currency==VND<br/>  format VND<br/>else<br/>  format USD"]
    
    New["NEW: Use Helper<br/>jankx_format_price_with_conversion($price)"]
    
    Manager["CurrencyManager<br/>formatPriceWithConversion()"]
    
    CCM["CurrencyConverterManager<br/>convert(amount, from, to)"]
    
    Converter["Active Converter<br/>get rate<br/>calculate"]
    
    Display["Display in<br/>User's Currency"]

    Extension --> Old
    Extension --> New
    
    Old -.->|DEPRECATED| Display
    New --> Manager
    Manager --> CCM
    CCM --> Converter
    Converter --> Display

    style Old fill:#ff9999
    style New fill:#99ff99
    style Extension fill:#99ccff
```

## Converter Selection Decision Tree

```mermaid
graph TD
    Start["Need Currency Conversion?"]
    
    Start -->|"No, single currency"| NoOp["Use NoOpConverter<br/>(default)<br/>No config needed"]
    
    Start -->|"Yes"| Choice["Choose API/Source"]
    
    Choice -->|"Public, Free"| OER["OpenExchangeRates.io<br/>- 1000 req/month free<br/>- USD base<br/>- Most popular"]
    
    Choice -->|"Public, Free"| Fixer["Fixer.io<br/>- 100 req/month free<br/>- EUR base<br/>- Smaller quota"]
    
    Choice -->|"Use Payment Gateway"| Custom["Implement Custom<br/>Converter<br/>OnePay, Stripe, etc."]
    
    OER --> Config["Get API Key<br/>Admin → Configure<br/>→ Save"]
    Fixer --> Config
    Custom --> Implement["Implement<br/>CurrencyConverterInterface<br/>Register via hook"]
    
    Config --> Active["Set Active in Admin<br/>Select dropdown"]
    Implement --> Active
    
    Active --> Test["Test Exchange Rate<br/>Display in admin<br/>Check conversion works"]
    
    Test --> Deploy["Deploy to Production<br/>Monitor API usage"]

    style NoOp fill:#ccffcc
    style OER fill:#ffffcc
    style Fixer fill:#ffffcc
    style Custom fill:#ffcccc
```

---

## Key Points

1. **Strategy Pattern** - Multiple converter implementations, easy to swap
2. **Decorator Pattern** - Caching automatically applied
3. **Singleton Pattern** - One manager instance per request
4. **Fail-safe** - No conversion = show original price
5. **Extensible** - Add custom converters without modifying core
6. **Performant** - 24h rate caching, per-request conversion caching
7. **Backward Compatible** - Old code still works
