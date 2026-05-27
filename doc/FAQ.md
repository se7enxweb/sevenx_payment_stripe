# FAQ — 7x Payment Gateway : Stripe

Extension: `sevenx_payment_stripe` | Version: 1.0.0

---

## General

### 1. What version of Stripe's API does this extension use?

The extension uses the Stripe **Payment Intents API** with the **Payment Element** (Stripe.js v3). This is Stripe's current recommended integration path and supports Strong Customer Authentication (SCA / 3DS2) out of the box.

---

### 2. Which payment methods are supported?

All payment methods enabled in your Stripe Dashboard are shown automatically by the Payment Element. At minimum, cards (Visa, Mastercard, Amex, Discover, etc.) are always available. Additional methods (Apple Pay, Google Pay, Link, iDEAL, SEPA, Bancontact, etc.) appear based on your country, currency, and Dashboard settings. No code changes are required to add or remove methods.

---

### 3. Does the extension store card numbers on the server?

No. Card details are collected entirely within a Stripe-hosted iframe embedded by Stripe.js. Raw card numbers, CVCs, and expiry dates **never reach your server**. The only value that passes through your PHP code is the `PaymentIntent` ID (a `pi_...` string), which carries no sensitive payment information.

---

### 4. How do I switch between test mode and live mode?

**Two changes are required:**

1. Update `SecretKey` in `extension/sevenx_payment_stripe/settings/stripe.ini.append.php`:
   ```ini
   # Test:
   SecretKey=sk_test_...
   # Live:
   SecretKey=sk_live_...
   ```

2. Update the publishable key in `extension/sevenx_payment_stripe/design/standard/javascript/stripe-checkout.js`:
   ```js
   // Test:
   const stripe = Stripe("pk_test_...");
   // Live:
   const stripe = Stripe("pk_live_...");
   ```

Both keys in a pair must come from the same Stripe account. Mixing a test secret key with a live publishable key (or vice versa) will cause all API calls to fail.

---

### 5. Where do I find my Stripe API keys?

Log in at [https://dashboard.stripe.com](https://dashboard.stripe.com) and go to **Developers → API keys**.

---

## Installation

### 6. The Payment Element does not appear on the checkout page.

Check the following:

- The `sevenx_payment_stripe` extension is listed in `ActiveExtensions[]` in `site.ini.append.php`.
- The eZ Publish cache has been cleared after enabling the extension.
- `stripe_form.tpl` is present at `extension/sevenx_payment_stripe/design/standard/templates/workflow/eventtype/result/stripe_form.tpl`.
- The template loads `stripe-checkout.js` via `{"javascript/stripe-checkout.js"|ezdesign()}`.
- There are no JavaScript errors in the browser console (open DevTools → Console tab).
- The Stripe.js script tag `<script src="https://js.stripe.com/v3/"></script>` is present in the page `<head>`.

---

### 7. The eZJSCore confirm endpoint returns an error. How do I debug it?

1. Check the eZ Publish debug log (enable `DebugOutput=enabled` in `site.ini` temporarily).
2. Look for `eZDebug::writeError` entries tagged `ezjscServerFunctionsStripeJs`.
3. Common causes:
   - `vendor/autoload.php` not found — run `composer install` from the eZ Publish root.
   - `SecretKey` is empty or missing from `stripe.ini.append.php`.
   - The `stripe/stripe-php` package is not installed or is the wrong version (requires v14+).

---

### 8. After payment, the customer is redirected back but the order is still not marked as Paid.

This means `confirm.php` did not successfully call `modifyStatus(1000)`. Possible causes:

- **Key mismatch** — `ezjscserverfunctionsstripejs.php` and `confirm.php` must use the same Stripe secret key. If they use keys from different accounts, `confirm.php` cannot retrieve the `PaymentIntent` created by `ezjscserverfunctionsstripejs.php`. Verify both files read from the same INI setting.
- **`payment_intent` missing from URL** — The `return_url` in `stripe-checkout.js` must point to your actual domain. If the domain is wrong, Stripe may redirect somewhere that is not `confirm.php`.
- **Order not found** — The `order_id` stored in `PaymentIntent.metadata` is not matching a valid eZ order. Check the eZ debug log for entries from `confirm.php`.
- **Status already 1000** — If the order was already marked Paid (e.g. on a previous visit), `confirm.php` intentionally skips the update. This is not an error.

---

### 9. The workflow does not advance after payment. The payment form keeps appearing.

This means `loadForm()` in `sevenx_payment_stripegateway.php` is returning `STATUS_FETCH_TEMPLATE_REPEAT` instead of `STATUS_ACCEPTED`.

`STATUS_ACCEPTED` is returned only when `$order->attribute('status_id') === 1000`. If the order status was not updated by `confirm.php`, the workflow will not advance. See FAQ #8 for diagnosis.

---

## Stripe Tax

### 10. Tax is not being calculated on checkout.

- Verify that Stripe Tax is **activated** in your Stripe Dashboard under **Settings → Tax**.
- Tax calculation requires a customer shipping address. If the eZ Publish order does not have a shipping address, the tax calculation is skipped and checkout proceeds without tax.
- Check the eZ debug log for entries from `ezjscServerFunctionsStripeJs` tagged `Stripe Tax calculation failed`.
- Tax calculation failures are **non-blocking** — checkout continues regardless. If you see no error and no tax, check that the currency and country combination has a registered tax rate in your Dashboard.

---

### 11. I want to use a different tax code for a product.

The default tax code is defined as a class constant in `ezjscserverfunctionsstripejs.php`:

```php
const DEFAULT_TAX_CODE = 'txcd_99999999'; // General - Tangible Goods
```

To use a different code for specific products, modify `buildLineItems()` to read a tax code from the eZ content object or order item, and pass it as `tax_code` in the line item array. A full list of Stripe tax codes is at [https://stripe.com/docs/tax/tax-codes](https://stripe.com/docs/tax/tax-codes).

---

## Troubleshooting

### 12. I see "Access denied." on the payment endpoint.

`ezjscServerFunctionsStripeJs::confirm()` throws a `RuntimeException` (returned as `error_text` in the JSON response) if the currently logged-in eZ Publish user does not own the order whose ID was submitted. This prevents one user from creating a `PaymentIntent` against another user's order.

Ensure the customer is logged in and that the `orderId` sent from the browser matches their own active order.

---

### 13. I see "Invalid order ID." on the payment endpoint.

`ezjscServerFunctionsStripeJs::confirm()` throws an `InvalidArgumentException` (returned as `error_text`) if the `orderId` in the POST body is missing, zero, or non-numeric. Check that `stripe-checkout.js` is correctly reading the order ID from the template data attribute and sending it as `orderId` in the JSON body.

---

### 14. How do I issue a refund?

Refunds are issued from the [Stripe Dashboard → Payments](https://dashboard.stripe.com/payments). Find the payment, open it, and click **Refund**. Full and partial refunds are supported. The extension does not currently provide a refund endpoint — see [TODO.md](TODO.md).

---

### 15. Where can I get support?

- Review this FAQ and [doc/INSTALL.md](INSTALL.md) first.
- Check the [Stripe Documentation](https://stripe.com/docs) for Stripe-specific questions.
- For eZ Publish / Exponential CMS questions, refer to the Exponential CMS community resources.
- For issues specific to this extension, open an issue at [https://github.com/se7enxweb/sevenx_payment_stripe](https://github.com/se7enxweb/sevenx_payment_stripe).
- For commercial support and customization, contact [info@se7enx.com](mailto:info@se7enx.com).


1.1 Authorize.Net Resources
=================================================
http://authorize.net/

General Information:
http://developer.authorize.net/
http://authorize.net/resources/documentlibrary/

API Guide:
http://www.authorize.net/support/AIM_guide.pdf


2. Required Fields
=================================================
* Oddly enough, 3 of the most standard fields on
  a payment form are not actually required by the
  authorize.net payment gateway:

  Name on Card, Card Type, and Security Number (CVV2)

  Reasons

  - The Name just doesn't matter

  - The card type is determined automatically
    based on the card number,

  - The security number is analyzed but it isn't
    currently handled as an error if it doesn't match.


3. Default Delimiter
=================================================
* The pipe "|" character is the default delimiter,
  which is currently hardcoded in the class.


4. Payment Type Support
=================================================
* eZ Authorize supports only credit card
  transactions right now.

* eZ Authorize does not impliment support for 
  the eCHECK or WellsFargo SecurePay APIs.


5. Select Gateway
=================================================
* This extension overrides the following template
  and customized to simplify the checkout process 
  for the customer by making the default action
  to continue forward in the checkout process.

Default Template design/standard/templates/workflow/selectgateway.tpl
eZ Authorize Template: extension/ezauthorize/design/standard/templates/workflow/selectgateway.tpl

To disable this template override all you must do is rename the file.


6. Test Mode
=================================================
* To switch test mode off, open up ezauthorize.ini.append.php
  and switch "TestMode=TRUE" to "TestMode=FALSE".

Also make sure you have the proper settings in your
Authorize.Net merchant login control panel.


7. Referencs
=================================================
* This implimentation was based originally in part on the 
  Cetrel Payment Gatway by Bertrand Dunogier from Interact S.A.

* Portions of this the file classes/ezauthorizeaim.php 
  are based on Micah Carrick's Authorize.Net php class, 
  available at: http://www.micahcarrick.com/v2/content/view/2/3/

* Christian Grey provided the eZ publish community with
  improved php 4.4+ and eZ publish 3.7+ compatibility by
  alerting us to the need to upgrade eZ authorize to
  support php 4.4.4 and eZ publish 3.8 by removing
  deprecated call-time pass-by-reference(s) in v1.1.0

* Bjorn Dieding (xrow) provided the eZ publish community 
  with improved php 4.4+ support, sslverify support + cacert bundle,
  and optional fields to better track a transaction.


8. Testing
=================================================
* This has only been completely tested using 
  test mode, and has not yet been used for live transactions.

