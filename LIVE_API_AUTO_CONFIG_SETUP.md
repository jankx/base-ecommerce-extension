# Currency Conversion - Setup for Live API with Auto-Config

## Quick Setup (No Manual Configuration!)

### Step 1: Set API Key in Environment

**Option A: Using .env file (Recommended)**
```
# In your .env or wp-config.php
OPENEXCHANGERATES_API_KEY=your_api_key_here
```

**Option B: Using wp-config.php**
```php
// In wp-config.php (before WordPress loads)
define('OPENEXCHANGERATES_API_KEY', 'your_api_key_here');
```

**Option C: Environment Variable**
```bash
export OPENEXCHANGERATES_API_KEY=your_api_key_here
```

### Step 2: Done! ✅

The system will:
1. Auto-detect your API key on init
2. Load OpenExchangeRates converter automatically
3. Start converting prices in real-time
4. No admin UI needed - completely automatic

---

## How It Works

### Initialization Flow
```
WordPress init
  ↓
AutoConfigConverter::autoDetectAndConfigure()
  ↓
Check for API key in: constants → environment variables
  ↓
If found: Load OpenExchangeRates + set as active converter
If not found: Use NoOp converter (no conversion)
  ↓
CurrencyConverterManager::getInstance()
  ↓
Ready to convert prices!
```

### Currency Switching Flow (SSR-based)
```
1. User clicks Currency Switcher dropdown/button
2. Frontend sends: POST /wp-json/jankx/ecommerce/v1/currency/switch
3. Backend: $_SESSION['jankx_currency'] = 'VND'
4. If logged in: user_meta['jankx_currency'] = 'VND'
5. Frontend reloads page
6. Server renders blocks with new currency
7. Block reads price from DB (default currency)
8. Block calls: formatPriceWithConversion()
9. Converter fetches/caches exchange rate
10. Converts price using cached rate
11. Returns formatted: "2.350.000 VND"
```

### Cache Strategy

Exchange rates cached by:
- **Rates**: 10 minutes (frequently updated market data)
- **Conversions**: 1 hour (calculations stable within a day)

Per-request de-duplication:
- Multiple conversions in same request use cache
- Prevents redundant API calls

Example:
```
User sees page with 10 tours
↓
Each tour price converted USD → VND
↓
First tour: API call → 100 USD = 2350 VND
↓
Tours 2-10: Use cached rate (no API calls)
↓
Total: 1 API call instead of 10
```

---

## Configuration Options

### Default Setup
```php
// Automatic - no code needed!
// Exchange rates: cached 10 minutes
// Conversions: cached 1 hour
```

### Custom Cache TTL (Advanced)
If you need different cache durations, you can customize by adding to functions.php:

```php
add_action('jankx/ecommerce/init', function () {
    $manager = \Jankx\Extensions\Ecommerce\Currency\Converters\CurrencyConverterManager::getInstance();
    $converter = $manager->getActiveConverter();
    
    // If it's wrapped with cache decorator
    if ($converter instanceof \Jankx\Extensions\Ecommerce\Currency\Converters\CacheDecoratorConverter) {
        // Cache exchange rates for 5 minutes instead of 10
        $converter->setRateCacheTTL(300);
        
        // Cache conversions for 30 minutes
        $converter->setConversionCacheTTL(1800);
    }
});
```

---

## Testing

### 1. Verify Converter is Loaded
**WordPress Admin → E-Commerce → Currency Debug**

Check:
- [ ] "Active Converter" shows "OpenExchangeRates" 
- [ ] Status shows "✓ Ready"
- [ ] "Test Conversion" shows real conversion (not same as input)

### 2. Test Currency Switching
1. Visit frontend page with tour/product
2. Use Currency Switcher block
3. Switch USD → VND
4. Page reloads
5. Price should show converted amount

### 3. Enable Debug Logging
In browser console (F12):
```javascript
jankxCurrencyToggleDebug()
// Reload page and switch currency
// Console will show debug logs
```

---

## Troubleshooting

### Problem: Converter shows "Not Ready"

**Check 1: API Key Missing?**
```php
// Add to wp-config.php and verify
define('OPENEXCHANGERATES_API_KEY', 'your_key');

// OR check environment
echo getenv('OPENEXCHANGERATES_API_KEY'); // Should show key
```

**Check 2: API Key Invalid**
- Log into OpenExchangeRates.io account
- Verify API key in dashboard (copy exactly)
- Check for extra spaces or wrong key
- Wait 5-10 minutes if key was just created

**Check 3: API Rate Limit**
- Free tier: 1,000 requests/month
- Check dashboard for remaining requests
- Default cache (10 min rates) prevents most overages

### Problem: Prices not converting

**Debug Steps:**
1. Go to "Currency Debug" page
2. Check "Test Conversion" shows different amount
3. If same amount:
   - Converter might not be ready
   - Check API key is configured
   - Check API key is valid (test in OpenExchangeRates dashboard)

**Still stuck?** Check logs:
```php
// In functions.php temporarily
error_log('Active converter: ' . get_option('jankx_currency_converter_type', 'noop'));
error_log('API key set: ' . (defined('OPENEXCHANGERATES_API_KEY') ? 'yes' : 'no'));
```

---

## API Key Options

### OpenExchangeRates.io (Recommended)
- **Website**: https://openexchangerates.io
- **Free Tier**: 1,000 requests/month
- **Base Currency**: USD
- **Setup Time**: Immediate (no email verification needed)
- **Sign Up**: https://openexchangerates.io/signup/free

### Fixer.io (Alternative)
- **Website**: https://fixer.io
- **Free Tier**: 100 requests/month
- **Base Currency**: EUR (limited to EUR base on free tier)
- **Setup Time**: 5-10 minutes (email verification)
- **Sign Up**: https://fixer.io
- **Environment Variable**: `FIXERIO_API_KEY`
- **Config**: `define('FIXERIO_API_KEY', 'your_key');`

---

## Environment Variable Names

The system checks for API keys in this order:

### OpenExchangeRates
1. Constant: `JANKX_OPENEXCHANGERATES_API_KEY`
2. Constant: `OPENEXCHANGERATES_API_KEY`
3. Env var: `jankx_openexchangerates_api_key`
4. Env var: `openexchangerates_api_key`

### Fixer.io
1. Constant: `JANKX_FIXERIO_API_KEY`
2. Constant: `FIXERIO_API_KEY`
3. Env var: `jankx_fixerio_api_key`
4. Env var: `fixerio_api_key`

**Tip**: Use `JANKX_*` prefix to avoid conflicts with other plugins

---

## How AutoConfigConverter Works

```php
AutoConfigConverter::autoDetectAndConfigure()
  ↓
Check API keys in environment
  ↓
1. Try OpenExchangeRates
   ├─ If found: Configure & enable
   └─ Return
  ↓
2. Try Fixer.io
   ├─ If found: Configure & enable
   └─ Return
  ↓
3. No API key found
   └─ Stay with NoOp (no conversion)
```

This runs on `init` hook with priority 10, before any price rendering.

---

## Performance Impact

### With Caching (Default)
- First request for USD→VND rate: ~200ms (API call)
- Remaining 9 requests in same page: ~1ms each (cache hit)
- Total page load: +200ms for 10 conversions

### After Cache Expiry
- Cache expires: 10 minutes for rates, 1 hour for conversions
- Next rate fetch: ~200ms, then cache hits for hour
- Typical pattern: 4-5 API calls per hour, ~1KB data per call

### Database Impact
- Uses WordPress `wp_cache` (transient-friendly)
- No additional tables or queries
- Automatic cleanup after TTL

---

## Security Notes

- API keys stored in environment, not in code or database
- Keys never logged or exposed in debug pages
- Exchange rates are public data (no sensitive info)
- All API calls are HTTPS

---

**Last Updated**: 2026-09-01  
**Status**: Production Ready
