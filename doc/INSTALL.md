# Installation — 7x Payment Gateway : Stripe

Extension: `sevenx_payment_stripe` | Version: 1.0.0

---

## 1. Requirements

| Requirement | Minimum version |
|---|---|
| **Exponential CMS** | 4.7+ (PHP 8.0+) |
| **PHP** | 8.0 (8.2+ recommended) |
| **Composer** | Any recent version |
| **ezjscore extension** | Latest — must be installed, activated, and working |
| **Stripe PHP SDK** | `stripe/stripe-php` v14+ (installed via Composer) |
| **Stripe account** | Free — sign up at [https://stripe.com](https://stripe.com) |

---

## 2. Download the extension

Clone or copy the extension into your `extension/` directory:

```bash
cd /path/to/exponential
git clone https://github.com/se7enxweb/sevenx_payment_stripe.git \
    extension/sevenx_payment_stripe
```

Or copy the files manually so the directory structure is:

```
extension/sevenx_payment_stripe/
    classes/
    design/
    modules/
    settings/
    composer.json
    ezinfo.php
    ...
```

---

## 3. Prerequisites — ezjscore

This extension routes `confirm` and `verify` through **eZJSCore**. The `ezjscore` extension **must** be installed and active before this extension will work.

1. Confirm `ezjscore` is present in `extension/`:
   ```bash
   ls extension/ezjscore/
   ```
2. Confirm it is listed in `ActiveExtensions[]` in `settings/override/site.ini.append.php`.
3. Confirm the autoload array includes `ezjscServerFunctions` — if in doubt, run:
   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   ```

---

## 4. Generate extension autoloads

Run the autoload generator from the Exponential root to register the new classes:

```bash
php bin/php/ezpgenerateautoloads.php -e
```

This updates `var/autoload/ezp_extension.php`. You must re-run this command any time a new class is added.

---

## 5. Directory structure after install

```
extension/sevenx_payment_stripe/
├── classes/
│   ├── ezcurlgateway.php                   PHP 8 fork — see doc/EZCURLGATEWAY.md
│   ├── sevenx_payment_stripegateway.php    loadForm(): renders form or advances workflow
│   └── ezjscserverfunctionsstripejs.php    eZJSCore endpoints — confirm + verify
├── modules/charge/
│   ├── module.php                          Registers charge / confirm / verify views
│   ├── charge.php                          Fallback for ezjsc-stripe::confirm
│   ├── confirm.php                         Deprecated stub — redirects to /shop/checkout
│   └── verify.php                          Fallback for ezjsc-stripe::verify
├── design/standard/
│   ├── javascript/stripe-checkout.js       Payment Element JS
│   ├── stylesheets/checkout.css            Checkout form styles
│   └── templates/workflow/eventtype/result/
│       └── stripe_form.tpl                 Checkout form template
├── settings/
│   ├── stripe.ini.append.php               Placeholder API keys + DisplayHelp
│   └── ezjscore.ini                        Registers ezjscServerFunctionsStripeJs
├── composer.json
├── ezinfo.php
├── extension.xml
└── package.xml
```

---

## 6. Composer — Stripe PHP SDK

The extension requires `stripe/stripe-php` v14+. Install it from the **Exponential root** (not the extension directory):

```bash
cd /path/to/exponential
composer require stripe/stripe-php
```

Or if `composer.json` is already configured at the Exponential root:

```bash
composer install
```

The SDK is loaded in the extension classes using an absolute path:

```php
require_once __DIR__ . '/../../../vendor/autoload.php';
```

This resolves to `<exponential-root>/vendor/autoload.php`. If your `vendor/` directory is elsewhere, update the `require_once` path in `classes/ezjscserverfunctionsstripejs.php`, `modules/charge/charge.php`, and `modules/charge/verify.php`.

---

## 7. Enabling the extension

Edit `settings/override/site.ini.append.php` (global) **or** a siteaccess-specific override and add:

```ini
[ExtensionSettings]
ActiveExtensions[]=ezjscore
ActiveExtensions[]=sevenx_payment_stripe
```

> `ezjscore` must appear **before** `sevenx_payment_stripe` in the list.

For a multi-siteaccess installation where only certain siteaccesses use Stripe:

```ini
[ExtensionSettings]
ActiveAccessExtensions[]=sevenx_payment_stripe
```

Clear the cache after any change:

```bash
php bin/php/ezcache.php --clear-all
```

---

## 8. Configuring the extension

### 8.1 API keys

**Never put real keys in the extension's own `settings/stripe.ini.append.php`** — that file is committed to version control and contains only placeholders. Instead, create or edit the site-level override:

```
settings/override/stripe.ini.append.php
```

or a siteaccess-specific override:

```
settings/siteaccess/YOUR_SITEACCESS/stripe.ini.append.php
```

Both the **secret key** and the **publishable key** belong in the same INI group and must come from the same Stripe account:

```ini
[Sevenx_Payment_Stripe_Settings]

# Secret key — never sent to the browser.
SecretKey=sk_test_YOUR_TEST_SECRET_KEY

# Publishable key — output to the page by stripe_form.tpl.
PublishableKey=pk_test_YOUR_TEST_PUBLISHABLE_KEY
```

Use `sk_test_...` / `pk_test_...` during development and `sk_live_...` / `pk_live_...` in production. Both keys in a pair must come from the **same Stripe account** — mixing keys across accounts causes all Stripe API calls to fail.

> **Find your keys:** [https://dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys)

### 8.2 DisplayHelp

```ini
[Sevenx_Payment_Stripe_Settings]
# Show a help image on the checkout form (true/false)
DisplayHelp=false
```

### 8.3 Per-siteaccess override (optional)

For multi-siteaccess installations:

```bash
cp extension/sevenx_payment_stripe/settings/stripe.ini.append.php \
   settings/siteaccess/YOUR_SITEACCESS/stripe.ini.append.php
```

Then edit the copy with the keys for that siteaccess.

---

## 9. Registering the payment gateway

The gateway registers itself automatically when the extension is loaded. To activate it in the shop workflow:

1. Log in to the Exponential admin interface.
2. Go to **Setup → Workflows**.
3. Edit the workflow attached to your shop's **Before order confirm** trigger.
4. Add or update the **Payment Gateway** event and select **7x : Payment Gateway : Stripe**.
5. Save the workflow.

Then verify the trigger:

1. Go to **Setup → Triggers**.
2. On the **shop / checkout / before** row, confirm the payment workflow is selected.

---

## 10. Verifying the installation

1. Add an item to the cart and proceed to checkout.
2. At the payment step you should see the Stripe Payment Element (card field, Apple Pay/Google Pay if available).
3. Use a [Stripe test card](https://stripe.com/docs/testing#cards) to complete a test payment:
   - Card number: `4242 4242 4242 4242`
   - Expiry: any future date (`12/34`)
   - CVC: any 3 digits (`123`)
4. After submission the JS calls `POST /ezjscore/call/ezjsc-stripe::verify`, which sets the order to Paid. The browser navigates to a clean `/shop/checkout` URL.
5. In the Exponential admin interface, the order should show status **Paid** (ID 1000).
6. In the [Stripe Dashboard → Payments](https://dashboard.stripe.com/test/payments) the PaymentIntent should show **Succeeded**.

---

## 11. Going live

When ready to accept real payments:

1. **Replace keys** in your override INI (`settings/override/stripe.ini.append.php`):
   ```ini
   SecretKey=sk_live_YOUR_LIVE_SECRET_KEY
   PublishableKey=pk_live_YOUR_LIVE_PUBLISHABLE_KEY
   ```
2. **Confirm HTTPS** is active and the SSL certificate is valid. Stripe's Payment Element refuses to load over plain HTTP in live mode.
3. **Verify the return URL** — `stripe-checkout.js` uses `window.location.origin + "/shop/checkout"` as `return_url`. Confirm `/shop/checkout` resolves correctly on your production domain.
4. **Stripe Tax** — if using automatic tax, verify Tax is activated in the Stripe Dashboard under **Settings → Tax**.
5. **End-to-end test** — run one live transaction with a real card and immediately issue a refund from the Dashboard before announcing production.

---

## 12. Uninstalling

1. Remove `ActiveExtensions[]=sevenx_payment_stripe` from `site.ini.append.php`.
2. Remove the extension directory:
   ```bash
   rm -rf extension/sevenx_payment_stripe
   ```
3. Clear the cache:
   ```bash
   php bin/php/ezcache.php --clear-all
   ```
4. In the Stripe Dashboard, revoke any API keys used exclusively by this site.
