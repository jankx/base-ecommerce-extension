# 🚀 Quick Fix: Currency Switcher Not Working

## TL;DR - 3-Step Fix

### Step 1: Get Free API Key
- OpenExchangeRates.io: https://openexchangerates.io/signup/free
- (Or Fixer.io: https://fixer.io)

### Step 2: Configure in WordPress
1. Admin → E-Commerce → Settings → Currency Converter
2. Select converter type: "OpenExchangeRates.io"  
3. Paste API key
4. Save

### Step 3: Test
1. Admin → E-Commerce → Currency Debug (check status is Ready)
2. Frontend: Use Currency Switcher block
3. Prices should now convert! ✅

---

## What Was Wrong?

Your system was using **"No Conversion"** mode by default - it showed the same price regardless of currency selection.

The fix enables live exchange rates so prices convert automatically.

---

## Debug Page Location

If prices still aren't changing after setup:

**WordPress Admin → E-Commerce → Currency Debug**

This page will show:
- ✅ or ❌ status of converter
- Test conversion result
- List of configured converters
- Troubleshooting steps

---

## Enable Debug Logging (Advanced)

In browser console (F12):
```javascript
jankxCurrencyToggleDebug()
```

Then refresh page and try switching currency. Console will show debug logs.

---

## Files Changed

- ✅ Initialized `CurrencyConverterManager` in extension startup
- ✅ Added `CurrencyDebugPage` for diagnostics
- ✅ Enhanced `currency-switcher.js` with better error handling & logging
- 📄 Created `CURRENCY_SWITCHING_DEBUG.md` (detailed guide)

---

**Need more help?** See: `CURRENCY_SWITCHING_DEBUG.md`
