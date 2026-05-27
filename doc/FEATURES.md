# Features — 7x Payment Gateway : Stripe

Extension: `sevenx_payment_stripe` | Version: 1.0.0

---

## Payment methods

The Stripe **Payment Element** is a single embedded UI component that dynamically displays the payment methods enabled in your Stripe Dashboard. No code changes are required to add or remove methods.

| Method | Notes |
|---|---|
| Credit and debit cards | Visa, Mastercard, Amex, Discover, JCB, UnionPay, Diners Club, and more |
| Apple Pay | Shown automatically on compatible Safari/iOS devices |
| Google Pay | Shown automatically in Chrome and Android browsers |
| Link | Stripe's saved-payment-details network — one-click checkout for returning customers |
| SEPA Direct Debit | EU bank transfers (requires EUR currency) |
| iDEAL | Dutch bank redirect |
| Bancontact | Belgian bank redirect |
| Other regional methods | Enable/disable any method from the Stripe Dashboard |

---

## Security

| Feature | Detail |
|---|---|
| **Card data never touches your server** | The Stripe.js Payment Element collects card details entirely within a Stripe-hosted iframe. Raw card numbers, CVCs, and expiry dates are never sent to or stored on your server. |
| **Server-side PaymentIntent verification** | `ezjsc-stripe::verify` always retrieves the `PaymentIntent` from Stripe's API before marking an order as Paid — the client-supplied `paymentIntentId` is used as a lookup key only, never trusted for status. |
| **Order ownership check** | `ezjscServerFunctionsStripeJs::confirm()` verifies that the authenticated Exponential user owns the order before creating a `PaymentIntent`. An exception (returned as `error_text` in the JSON response) is thrown for any mismatch. |
| **Secret key in INI, not source** | The Stripe secret key is stored in `stripe.ini.append.php` and read server-side only. It is never sent to the browser. |
| **Idempotent status updates** | `ezjsc-stripe::verify` checks `status_id` before calling `modifyStatus()`, preventing duplicate Paid history entries if the verify endpoint is called more than once. |
| **Generic error responses** | PHP exceptions from the Stripe SDK are logged via `eZDebug::writeError()` and only a generic message is returned to the client — no stack traces or internal details are exposed. |
| **HTTPS enforced in production** | Stripe's Payment Element refuses to operate over HTTP in live mode. Test mode permits HTTP for development. |
| **3D Secure support** | Stripe automatically handles 3DS2 challenges as part of `stripe.confirmPayment()`. No additional integration is required. |

---

## Tax

| Feature | Detail |
|---|---|
| **Stripe Tax integration** | `ezjscServerFunctionsStripeJs::confirm()` calls the Stripe Tax Calculations API to compute applicable tax for each line item based on the customer's shipping address. |
| **Non-blocking calculation** | If the Stripe Tax API call fails (network error, missing address, etc.) the payment continues without tax — tax errors never block checkout. |
| **Per-product tax codes** | The default tax code (`txcd_99999999` — General Tangible Goods) is applied to all items. Override per product by modifying the `DEFAULT_TAX_CODE` constant or adding per-product logic. |
| **Tax included in PaymentIntent** | When a Stripe Tax calculation succeeds, the tax amounts are attached to the `PaymentIntent` for accurate reporting in the Stripe Dashboard. |

---

## Exponential workflow integration

| Feature | Detail |
|---|---|
| **Native workflow event** | The extension plugs into the standard Exponential shop workflow via the `eZCurlGateway` base class. No core files are modified. |
| **`loadForm()` gateway method** | Renders the `stripe_form.tpl` template and returns `STATUS_FETCH_TEMPLATE_REPEAT` while awaiting payment. |
| **Automatic workflow advancement** | After `ezjsc-stripe::verify` sets `status_id = 1000`, the next call to `loadForm()` detects the Paid status and returns `STATUS_ACCEPTED`, advancing the workflow to the order confirmation page. |
| **Order status history** | `$order->modifyStatus(1000)` is called atomically — it creates a status history entry and persists the order in one operation, as required by Exponential. |
| **eZJSCore routing** | PaymentIntent creation is dispatched via eZJSCore: `POST /ezjscore/call/ezjsc-stripe::confirm` — registered in `settings/ezjscore.ini` under `[ezjscServer_ezjsc-stripe]`. The return URL handler (`/charge/confirm`) remains a standard Exponential module view. |

---

## Developer experience

| Feature | Detail |
|---|---|
| **Stripe PHP SDK v14+** | Installed via Composer. The extension does not re-implement any HTTP or signing logic. |
| **INI-based configuration** | All environment-specific settings (`SecretKey`, `DisplayHelp`) are in `stripe.ini.append.php`, compatible with Exponential siteaccess overrides. |
| **Minimal gateway class** | `sevenx_payment_stripegateway.php` is ~55 lines. Only `loadForm()` is implemented — `execute()`, `doCURL()`, and `validateForm()` are inherited from the base class and intentionally unused. |
| **Stripe.js v3** | The Payment Element configuration is in `stripe-checkout.js`. The publishable key is read from `data-publishable-key` on the form element, which the template populates from `stripe.ini`. Switching test ↔ production requires only an INI change — no JS edits. |
| **Debug logging** | All server-side errors and non-success payment states are written to the Exponential debug log via `eZDebug::writeError()` / `eZDebug::writeNotice()`. |
| **No vendor lock-in beyond Stripe** | No proprietary SDKs, no third-party analytics, no external dependencies beyond the official `stripe/stripe-php` package. |

---

## What was removed in v1.0.0

The following Authorize.Net components from v1.x were removed during the Stripe rewrite:

| Removed | Reason |
|---|---|
| `classes/ezauthorizeaim.php` | Authorize.Net AIM cURL client — replaced by Stripe PHP SDK |
| `classes/ezauthorizeinfo.php` | Credit card validation — handled by Stripe.js |
| `classes/ezauthorizerefund.php` | Authorize.Net refund handler — use Stripe Dashboard or Stripe API |
| `classes/ezcustomshopaccounthandler.php` | Billing/shipping XML reader — not needed by Stripe flow |
| `cacert.pem` | CA bundle for Authorize.Net cURL — Stripe SDK uses PHP's own CA store |
| `settings/shopaccount.ini.append(.php)` | Shop account handler config — not used |
| `doCURL()` / `validateForm()` | Gateway class methods for direct cURL POST — replaced by `PaymentIntent` flow |

