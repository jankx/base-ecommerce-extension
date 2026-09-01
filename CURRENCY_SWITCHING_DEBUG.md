# 🔍 Currency Switching - Prices Not Changing? Debug Guide

## Problem: "I change currency but prices don't update"

### Root Cause

By default, the currency conversion system uses **NoOpConverter** - a placeholder converter that does **NOT perform any conversion**. It returns the original price unchanged.

```
User changes currency USD → VND
↓
Price fetched from database (e.g., 100 USD)
↓
NoOpConverter processes it
↓
Returns 100 (no conversion!)
↓
User sees same price in VND
```

---

## Solution: Enable Currency Conversion

### Step 1: Get an API Key

Choose ONE of these services:

#### Option A: OpenExchangeRates.io (Recommended)
- Supports live exchange rates from USD base
- Free tier: 1,000 requests/month
- Sign up: https://openexchangerates.io/signup/free
- Provides immediate API key

#### Option B: Fixer.io
- Supports live exchange rates from EUR base  
- Free tier: 100 requests/month
- Sign up: https://fixer.io/
- Requires email verification before API key

### Step 2: Configure in WordPress Admin

1. Go to: **WordPress Admin → E-Commerce → Settings**
2. Click **"Currency Converter"** tab
3. You should see:
   - Converter Type dropdown
   - API Key field
   - Base Currency field
   - Status indicator

### Step 3: Select Converter Type

In the "Converter Type" dropdown, choose:
- **"OpenExchangeRates.io"** (if using OpenExchangeRates)
- **"Fixer.io"** (if using Fixer)
- ~~"No Conversion" (skip this - it's the current broken state)~~

### Step 4: Paste Your API Key

Paste the API key you received from the service into the **"API Key"** field.

### Step 5: Verify Configuration

Look at the status box - it should show:
- ✅ "Ready" in green (converter is working)
- Test conversion displayed: "100 USD = X VND"

If it shows ❌ "Not Ready":
- Check API key is correct (no extra spaces)
- Verify API key is active in your account
- Wait 5-10 minutes for API key to activate
- Check your internet connection (API call must reach the service)

### Step 6: Test Currency Switching

1. Create/edit a tour with a price in your default currency
2. Visit the frontend
3. Use Currency Switcher block to change currency
4. Refresh - the price should now convert!

---

## How to Debug: Use Currency Debug Page

### Access Debug Page

WordPress Admin → E-Commerce → **Currency Debug**

This page shows:

| Item | What It Tells You |
|------|------------------|
| **Default Currency** | Which currency prices are stored in (usually USD) |
| **Current User Currency** | What currency the user is viewing (changes when they switch) |
| **Active Converter** | Which converter is running + Ready status |
| **Test Conversion** | Shows if conversion actually works (100 USD → X VND) |
| **Available Converters** | Lists all configured converters + their status |

### Reading the Debug Page

**Example 1: Problem State**
```
Active Converter: No Conversion [✗ Not Ready]
Test Conversion:
  Exchange Rate: Unable to fetch
  Converted Amount: 100 VND (⚠️ No conversion occurred)
```
→ Solution: Configure a real converter (OpenExchangeRates/Fixer)

**Example 2: Fixed State**
```
Active Converter: OpenExchangeRates [✓ Ready]
Test Conversion:
  Exchange Rate: 23.5 (USD → VND rate)
  Converted Amount: 2350 VND
```
→ Conversion working! Prices should update when currency changes.

---

## Common Issues & Solutions

### Issue: "Converter shows Ready but conversion still isn't working"

**Possible Causes:**

1. **Price stored in wrong currency**
   - Prices must be stored in DEFAULT currency
   - Check: WordPress Admin → Experience/Tour → Edit
   - Verify Currency field matches Default Currency

2. **No currency metadata saved**
   - Prices need `_experience_currency` meta field
   - Ensure tour was created with currency field selected
   - For existing tours: manually set currency in edit screen

3. **Session not persisting currency choice**
   - After currency switch, check browser console (F12 → Console)
   - Look for errors from `/wp-json/jankx/ecommerce/v1/currency/switch`
   - Clear browser cache and try again

### Issue: "Exchange rate seems wrong"

- Real exchange rates fluctuate constantly
- Rates are cached for 24 hours (reduces API calls)
- Manually clear cache: WordPress Admin → E-Commerce → Currency Debug
- Different services may have slightly different rates (±1-2%)

### Issue: "API key working on their site but not in my WordPress"

Possible reasons:
- API key not activated yet (wait 5-10 minutes after signup)
- IP address restricted in API key settings (check their dashboard)
- Firewall blocking outgoing HTTPS requests
- WordPress environment doesn't have `allow_url_fopen` enabled

### Issue: "I don't want to pay for an API - what are my options?"

Free options:
- **OpenExchangeRates.io**: 1,000 free requests/month
- **Fixer.io**: 100 free requests/month
- Both free tiers should work fine for a small site

If neither is sufficient, consider:
- Manually updating exchange rates (advanced setup)
- Using a payment gateway that provides rates (OnePay, etc.)
- Contact: [Your Team]

---

## Technical Explanation: How Currency Conversion Works

### User Switches Currency

```
1. User clicks Currency Switcher (dropdown/buttons)
   ↓
2. Frontend JavaScript sends:
   POST /wp-json/jankx/ecommerce/v1/currency/switch
   Body: { currency: "VND" }
   ↓
3. Backend sets:
   - Session: $_SESSION['jankx_currency'] = 'VND'
   - User Meta: user_meta['jankx_currency'] = 'VND'
   ↓
4. JavaScript reloads page: location.reload()
```

### Price Renders on Frontend

```
5. Page loads, block renders tour starting price
   ↓
6. Block reads: get_post_meta('_experience_starting_price') → 100
   ↓
7. Block reads: get_post_meta('_experience_currency') → 'USD'
   ↓
8. Block calls: CurrencyManager::formatPriceWithConversion(100, 'USD', 'VND')
   ↓
9. CurrencyManager calls: CurrencyConverterManager->convert(100, 'USD', 'VND')
   ↓
10. Active Converter processes:
    - If NoOp: return 100 (no conversion!)
    - If OpenExchangeRates: fetch rate from API, multiply, return 2350
    ↓
11. Result formatted: "2.350 VND" or "VND 2.350"
    ↓
12. User sees updated price!
```

**The Problem:** If step 10 uses NoOp converter, price stays 100.

---

## Checklist: Fix Currency Not Changing

- [ ] Go to WordPress Admin → E-Commerce → Currency Debug
- [ ] Check "Active Converter" status
  - [ ] If "No Conversion" or "Not Ready" → proceed to next steps
  - [ ] If "Ready" → see "Converter Ready but Not Working" section
- [ ] Get API key from OpenExchangeRates.io or Fixer.io
- [ ] Go to WordPress Admin → E-Commerce → Settings → Currency Converter
- [ ] Select converter type (OpenExchangeRates or Fixer)
- [ ] Paste API key
- [ ] Save settings
- [ ] Go back to Currency Debug page
- [ ] Verify "Active Converter" now shows "Ready"
- [ ] Test currency switching on frontend
- [ ] Prices should now change when you switch currencies!

---

## Need Help?

1. **Check Currency Debug page first** - it diagnoses 90% of issues
2. **Clear cache** - browser cache, WordPress cache, CDN
3. **Test in browser console** (F12):
   ```javascript
   // Check if REST API is accessible
   fetch('/wp-json/jankx/ecommerce/v1/currency')
     .then(r => r.json())
     .then(d => console.log(d))
   ```
4. **Check WordPress error logs** - may reveal API/network errors
5. **Contact support** - include:
   - Screenshot of Currency Debug page
   - Screenshots of Currency Converter settings
   - Browser console errors
   - WordPress error log entries

---

**Last Updated:** 2026-09-01  
**Related Documentation:** CURRENCY_CONVERSION_GUIDE.md, CURRENCY_CONVERSION_ARCHITECTURE.md
