# Roadmap — 7x Payment Gateway : Stripe

Extension: `sevenx_payment_stripe` | Version: 1.0.0

This document tracks planned improvements and known gaps. Items are loosely prioritised; earlier sections are higher priority.

---

## High priority

### Refund endpoint

Add a `/charge/refund` module view (admin-only) that issues a full or partial refund via `$stripe->refunds->create()` and records a status history entry in eZ Publish. Currently refunds must be issued manually from the Stripe Dashboard.

### Stripe Webhook handler

Add a `/charge/webhook` endpoint to consume Stripe webhook events (`payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, etc.) for asynchronous order status updates. This is important for payment methods that do not redirect immediately (e.g. bank transfers, BECS Direct Debit).

- Validate the `Stripe-Signature` header using `\Stripe\Webhook::constructEvent()`.
- Map Stripe event types to eZ Publish order status changes.
- Register the webhook endpoint in the Stripe Dashboard.

### Per-product Stripe tax codes

Currently all order items use `DEFAULT_TAX_CODE` (`txcd_99999999` — General Tangible Goods). Extend `buildLineItemsFromOrder()` to read a Stripe tax code from a custom eZ content class attribute on the product, falling back to the default.

---

## Medium priority

### Saved payment methods (Stripe Customer)

- Create or retrieve a Stripe `Customer` object keyed to the eZ Publish `user_id`.
- Pass the `Customer` ID to `PaymentIntents.create()` to enable saved cards and Link one-click checkout for returning customers.
- Provide a "my saved cards" page under the eZ Publish user account.

### Admin order view — Stripe payment details

Add a template override for the eZ Publish admin order detail view to display:

- Stripe `PaymentIntent` ID (stored in order metadata or a custom attribute).
- Payment method type (card, Apple Pay, etc.).
- Card brand and last four digits.
- Link to the payment in the Stripe Dashboard.

### Currency support

The extension currently assumes a single currency. Add support for multi-currency stores:

- Read the currency from the eZ Publish order or siteaccess configuration.
- Pass the correct ISO 4217 currency code to `PaymentIntents.create()`.

### Stripe Radar rules

Document and optionally configure Stripe Radar rules for fraud protection:

- Block payments from high-risk countries.
- Require 3DS for all transactions above a configurable threshold.
- Review mode for flagged transactions.

---

## Low priority / nice to have

### PHP 8.3+ compatibility audit

Verify all extension PHP files against PHP 8.3 deprecation notices and prepare for PHP 8.4.

### Unit tests

Add PHPUnit tests for:

- `buildLineItemsFromOrder()` — various item types, zero-price items, quantity edge cases.
- `confirm.php` — mock Stripe API responses for succeeded, failed, and pending statuses.
- `loadForm()` — workflow status transitions.

### Stripe Billing Portal integration

Allow customers to manage their saved payment methods and view invoices via the Stripe Customer Portal (`stripe->billingPortal->sessions->create()`).

### Apple Pay / Google Pay domain registration

Document the domain registration process required for Apple Pay in production (`.well-known/apple-developer-merchantid-domain-association`). Stripe handles Google Pay domain verification automatically.

### Stripe Financial Connections (bank payments)

Investigate adding ACH Direct Debit / BECS support via Stripe Financial Connections for US and Australian customers.

### Accessibility audit of stripe_form.tpl

Review the checkout template for WCAG 2.1 AA compliance: label associations, focus management, error announcements via `aria-live`.

---

## Completed (v1.0.0)

- ✅ Replace Authorize.Net integration with Stripe Payment Element
- ✅ Stripe PaymentIntent flow (`ezjsc-stripe::confirm` + `confirm.php`)
- ✅ Server-side PaymentIntent verification in `confirm.php`
- ✅ Order ownership check in `ezjscServerFunctionsStripeJs::confirm()`
- ✅ `loadForm()` STATUS_ACCEPTED on order status 1000
- ✅ `modifyStatus(1000)` with atomic order history entry
- ✅ Stripe Tax Calculations API integration (non-blocking)
- ✅ Secret key moved from source code to `stripe.ini.append.php`
- ✅ Generic error responses (no stack traces to client)
- ✅ PHP 8 deprecation fix for `eZOrder::active()` parameter order
- ✅ Dead Authorize.Net code removed from gateway class
- ✅ Legacy `cacert.pem`, `ezauthorizeaim.php`, `ezauthorizeinfo.php`, `ezauthorizerefund.php`, `ezcustomshopaccounthandler.php`, `shopaccount.ini.append(.php)` deleted


A few users of eZ Authorize may require further
extension of the default eZ publish store features.

If you are interested in sponsoring the development
of any of these features for your eZ publish web site,
please contact info@brookinsconsulting.com

Additional client and server input form validation:
* Client side form validation of required fields.
* Client validation of card holder name.
* Client validation of card type.
* Client validation of card number.
* Client validation of expiration date.

* Server form validation of required fields.
* Server validation of card holder name.
* Server validation of card type.
* Server validation of card number.
* Server validation of expiration date.


Additional features:
* Internationalization of error message text and template language usage
* Accounting software export / integration (Quickbooks or GNU Cash)
* SSL ADJAX client support for true transparent payment
  authorization without requiring a refresh of the end users page.
* SSL+ACL+Static IP+MAC Address Checking for remote transfers of encrypted
  payment information records to be downloaded in a standard file format
  (xml) and stored in encrypted form within an encrypted disk requiring two
  unique gpg/pgp keyring stored on a removable usb disk (key).


Extend gateway api support:
* eCheck.Net support
* Wells Fargo SecureSource support

Authorize.Net | Shopping Cart Certification (SCC)
* http://authorize.net/solutions/partnersolutions/shoppingcartprogram/
