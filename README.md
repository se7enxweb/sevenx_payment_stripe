# 7x Payment Gateway : Stripe

A [Stripe](https://stripe.com) payment gateway extension for **Exponential**. Provides an alternative payment gateway using the modern **Stripe Payment Element** — a single, embedded UI that supports cards, Apple Pay, Google Pay, Link, and every Stripe-supported payment method with zero additional code.

---

## At a glance

| | |
|---|---|
| **Extension name** | `sevenx_payment_stripe` |
| **Version** | 1.0.0 |
| **License** | [GNU General Public License v2.0 (or any later version)](LICENSE) |
| **Requires** | Exponential CMS 4.7+ · PHP 8.0+ · Composer · **ezjscore** extension |
| **ezjscore** | Latest version — must be installed, activated, and working |
| **Stripe PHP SDK** | `stripe/stripe-php` v14+ (installed via Composer) |
| **Stripe.js** | v3 (loaded from `https://js.stripe.com/v3/`) |
| **Tested on** | PHP 8.2, Exponential CMS 4.7 |

---

## How it works

The extension integrates with the Exponential shop **workflow event** system. When a customer reaches the payment step, Exponential calls `loadForm()` on the gateway class, which renders the Stripe Payment Element. After Stripe confirms the payment in-page, the JS posts to `ezjsc-stripe::verify` which verifies the result server-side and marks the order as **Paid** (status 1000). The browser then navigates to a clean `/shop/checkout` URL where the workflow advances automatically.

### Payment flow

```
Browser                    Exponential / PHP             Stripe
  |                               |                         |
  |--- GET /shop/checkout ------->|                         |
  |   (pre_checkout workflow      |                         |
  |    triggers loadForm())       |                         |
  |<-- stripe_form.tpl -----------|                         |
  |   (Payment Element rendered)  |                         |
  |                               |                         |
  |--- POST ezjsc-stripe::confirm->|                         |
  |   { orderId }                  |--- createPaymentIntent->|
  |                                |<-- { clientSecret } ----|
  |<-- { clientSecret } ----------|                         |
  |                               |                         |
  |--- stripe.confirmPayment() ---|-------> Stripe.js       |
  |   redirect:"if_required"      |   (card / 3DS handled   |
  |   (stays on page for cards)   |    in-page or redirect) |
  |<-- paymentIntent.status       |                         |
  |    === "succeeded"             |                         |
  |                               |                         |
  |--- POST ezjsc-stripe::verify ->|                         |
  |   { paymentIntentId }          |--- retrieve PI -------->|
  |                                |<-- status: succeeded ---|
  |                                |                         |
  |                                |-- modifyStatus(1000)    |
  |                                |   (order marked Paid)   |
  |<-- { status: "paid" } --------|                         |
  |                               |                         |
  |--- window.location.replace -->|                         |
  |   /shop/checkout (clean URL)  |                         |
  |                               |                         |
  |--- GET /shop/checkout ------->|                         |
  |   (loadForm() finds           |                         |
  |    status_id === 1000)        |                         |
  |<-- STATUS_ACCEPTED -----------|                         |
  |   (Exponential advances,      |                         |
  |    order confirmation shown)  |                         |
```

### Step-by-step

1. **`loadForm()`** — `Sevenx_Payment_StripeGateway::loadForm()` checks whether the order is already at status 1000 (Paid). If so it returns `STATUS_ACCEPTED` immediately. If not, it renders `stripe_form.tpl` containing the Stripe Payment Element and returns `STATUS_FETCH_TEMPLATE_REPEAT`.
2. **`POST ezjsc-stripe::confirm`** — `stripe-checkout.js` posts `{"orderId": <int>}` to this eZJSCore endpoint. `ezjscServerFunctionsStripeJs::confirm()` validates ownership, calculates order totals, optionally calls the Stripe Tax API, and creates a `PaymentIntent`. Returns `{"error_text":"","content":{"clientSecret":"..."}}` to the browser.
3. **`stripe.confirmPayment()`** — Stripe.js collects the payment method, handles 3D Secure challenges in-page (`redirect: "if_required"`). Cards and most methods succeed without leaving the page. Methods that require a redirect use `return_url: window.location.origin + "/shop/checkout"` so the verify step runs on return.
4. **`POST ezjsc-stripe::verify`** — On `paymentIntent.status === 'succeeded'` (or on redirect-back via `checkStatus()`), the JS posts `{"paymentIntentId": "pi_..."}`. `ezjscServerFunctionsStripeJs::verify()` retrieves the `PaymentIntent` from Stripe server-side (never trusting client data alone), confirms `status === 'succeeded'` and `metadata.order_id` matches, then calls `$order->modifyStatus(1000)` which records the status change atomically.
5. **Workflow completion** — The JS calls `window.location.replace("/shop/checkout")` — a clean URL with no Stripe query parameters. Exponential calls `loadForm()` again; it detects `status_id === 1000` and returns `STATUS_ACCEPTED`, advancing the workflow to the order confirmation page.

---

## Quick start

See **[doc/INSTALL.md](doc/INSTALL.md)** for complete requirements and installation steps.

---

## Documentation

| Document | Contents |
|---|---|
| [doc/INSTALL.md](doc/INSTALL.md) | Requirements, installation, Composer setup, INI configuration |
| [doc/FEATURES.md](doc/FEATURES.md) | Full feature list — security, Stripe Tax, Payment Element |
| [doc/FAQ.md](doc/FAQ.md) | Troubleshooting and common questions |
| [doc/TODO.md](doc/TODO.md) | Roadmap and planned improvements |
| [doc/EZCURLGATEWAY.md](doc/EZCURLGATEWAY.md) | eZCurlGateway PHP 8 fork — diff vs stock, rationale |
| [doc/changelogs/](doc/changelogs/) | Version history |

---

## Extension structure

```
extension/sevenx_payment_stripe/
├── classes/
│   ├── ezcurlgateway.php                       Base cURL gateway (PHP 8 fork — see doc/EZCURLGATEWAY.md)
│   ├── sevenx_payment_stripegateway.php        loadForm(): renders form or advances workflow
│   └── ezjscserverfunctionsstripejs.php        eZJSCore endpoints — confirm (create PI) + verify (set status 1000)
├── modules/charge/
│   ├── module.php                              Registers 'charge' module views
│   ├── charge.php                              Fallback for ezjsc-stripe::confirm
│   ├── confirm.php                             Deprecated stub — redirects to /shop/checkout
│   └── verify.php                              Fallback for ezjsc-stripe::verify
├── design/standard/
│   ├── javascript/stripe-checkout.js        Payment Element JS, confirmPayment() call
│   ├── stylesheets/checkout.css                Checkout form styles
│   └── templates/workflow/eventtype/result/
│       └── stripe_form.tpl                     Checkout form template
├── settings/
│   └── stripe.ini.append.php                   SecretKey + DisplayHelp
├── composer.json
├── ezinfo.php
├── extension.xml
└── package.xml
```

---

## Configuration

### INI settings — `settings/override/stripe.ini.append.php`

Set real keys in the **site-level override** (never in the extension's own `settings/stripe.ini.append.php`, which is committed to version control and contains only placeholders).

```ini
[Sevenx_Payment_Stripe_Settings]
# Both keys must come from the same Stripe account.
# Use sk_test_... / pk_test_... for development, sk_live_... / pk_live_... for production.
SecretKey=sk_test_...
PublishableKey=pk_test_...

# Display help text on the payment form (true/false)
DisplayHelp=false
```

> Find your keys at [https://dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys).

---

## Going live (test → production)

1. Replace both `SecretKey` and `PublishableKey` in `settings/override/stripe.ini.append.php` with your `sk_live_...` and `pk_live_...` keys. Both must be from the same Stripe account.
2. Confirm `window.location.origin + "/shop/checkout"` resolves correctly for your domain — this is the `return_url` used for redirect-required payment methods (iDEAL, 3DS fallback, etc.).
4. If using Stripe Tax, verify that Tax is activated in the Stripe Dashboard for your account.
5. Test end-to-end in the Stripe Dashboard with a live card before announcing production.

---

## Requirements summary

- Exponential 6.0.x+ (PHP 8.2+)
- PHP 8.0 or higher
- **ezjscore** extension — latest version, installed and activated (the AJAX endpoints `ezjsc-stripe::confirm` and `ezjsc-stripe::verify` are registered via ezjscore's `FunctionList` mechanism; without it no payment can be initialised or verified)
- Composer (for the `stripe/stripe-php` SDK)
- A Stripe account (free — [stripe.com](https://stripe.com))
- HTTPS on the production domain (required by Stripe for live payments)

---

## License

Copyright (C) 2001–2026 [7x](https://se7enx.com). Released under the **GNU General Public License v2.0 (or any later version)** — see [LICENSE](LICENSE).

