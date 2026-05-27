# Installation Guide — 7x Payment Gateway : Stripe

Extension: `sevenx_payment_stripe` | Version: 1.0.0

---

## Contents

1. [Overview](#1-overview)
2. [License](#2-license)
3. [Requirements](#3-requirements)
4. [Stripe account setup](#4-stripe-account-setup)
5. [Installing the extension](#5-installing-the-extension)
6. [Composer — Stripe PHP SDK](#6-composer--stripe-php-sdk)
7. [Enabling the extension in eZ Publish](#7-enabling-the-extension-in-ez-publish)
8. [Configuring the extension](#8-configuring-the-extension)
9. [Registering the payment gateway](#9-registering-the-payment-gateway)
10. [Verifying the installation](#10-verifying-the-installation)
11. [Going live](#11-going-live)
12. [Uninstalling](#12-uninstalling)

---

## 1. Overview

`sevenx_payment_stripe` is the official Stripe payment gateway for eZ Publish 4 / Exponential CMS. It integrates Stripe's **Payment Element** (cards, Apple Pay, Google Pay, Link, and all Stripe-supported methods) into the standard eZ Publish shop workflow via the `eZCurlGateway` base class.

It does **not** use Stripe Checkout (hosted page) — payment is captured inline in your eZ template via the Stripe.js Payment Element, giving you full control over the checkout experience.

For a feature overview see [FEATURES.md](FEATURES.md).

---

## 2. License

`sevenx_payment_stripe` is licensed under the **GNU General Public License v2.0 (or any later version)**. The full text is in the `LICENSE` file at the root of the extension.

---

## 3. Requirements

| Dependency | Minimum version | Notes |
|---|---|---|
| eZ Publish | 4.7 | Exponential CMS (PHP 8 fork) fully supported |
| PHP | 8.0 | PHP 8.2 recommended |
| Composer | any current | Used to install `stripe/stripe-php` |
| `stripe/stripe-php` | 14.0 | Installed via Composer |
| HTTPS | — | Required by Stripe for live payments. Test mode works over HTTP. |
| Stripe account | — | Free — [https://stripe.com](https://stripe.com) |

The extension has **no** cURL dependency of its own — it delegates all HTTP calls to the Stripe PHP SDK (which uses Guzzle/cURL internally).

---

## 4. Stripe account setup

Before installing the extension you need API keys from your Stripe Dashboard.

1. Log in at [https://dashboard.stripe.com](https://dashboard.stripe.com).
2. Go to **Developers → API keys**.
3. Note your **Publishable key** (`pk_test_...` or `pk_live_...`) and **Secret key** (`sk_test_...` or `sk_live_...`).
4. For local/development use, always use the **test** key pair.

If you want Stripe Tax (automatic tax calculation on checkout):

1. Go to **Settings → Tax** in the Dashboard.
2. Complete the Tax registration wizard for your jurisdiction(s).
3. Tax calculations will be triggered automatically by `ezjscserverfunctionsstripejs.php` when a valid customer address is available.

---

## 5. Installing the extension

### Option A — Git clone (recommended)

```bash
cd /path/to/ezpublish/extension
git clone https://github.com/se7enxweb/sevenx_payment_stripe.git sevenx_payment_stripe
```

### Option B — Download and extract

```bash
cd /path/to/ezpublish/extension
wget https://github.com/se7enxweb/sevenx_payment_stripe/archive/refs/heads/main.tar.gz
tar -zxvf main.tar.gz
mv sevenx_payment_stripe-main sevenx_payment_stripe
```

After extraction the directory structure should be:

```
extension/
└── sevenx_payment_stripe/
    ├── classes/
    ├── modules/
    ├── design/
    ├── settings/
    ├── composer.json
    ├── ezinfo.php
    └── ...
```

---

## 6. Composer — Stripe PHP SDK

The extension requires `stripe/stripe-php`. Install it from the **eZ Publish root** (not the extension directory):

```bash
cd /path/to/ezpublish
composer require stripe/stripe-php
```

Alternatively, if `composer.json` is already set up at the eZ Publish root:

```bash
composer install
```

Confirm the autoloader path. In `classes/ezjscserverfunctionsstripejs.php` and `modules/charge/confirm.php` the SDK is loaded with:

```php
require_once 'vendor/autoload.php';
```

This path is relative to the eZ Publish root (where PHP executes). If your `vendor/` directory is located elsewhere, update both files accordingly.

---

## 7. Enabling the extension in eZ Publish

Edit `settings/override/site.ini.append.php` (global) **or** the siteaccess-specific override (e.g. `settings/siteaccess/shop/site.ini.append.php`) and add:

```ini
[ExtensionSettings]
ActiveExtensions[]=sevenx_payment_stripe
```

If running multiple siteaccesses and only some should use Stripe:

```ini
[ExtensionSettings]
ActiveAccessExtensions[]=sevenx_payment_stripe
```

Clear the eZ Publish cache after making this change:

```bash
php bin/php/ezcache.php --clear-all
```

---

## 8. Configuring the extension

### 8.1 API keys

Open `extension/sevenx_payment_stripe/settings/stripe.ini.append.php`:

```ini
[Sevenx_Payment_Stripe_Settings]

# Stripe secret key.
# Use sk_test_... during development, sk_live_... in production.
# Never commit live keys to version control.
SecretKey=sk_test_YOUR_TEST_SECRET_KEY_HERE

# Show payment help text on the checkout form (true/false)
DisplayHelp=false
```

Replace `sk_test_YOUR_TEST_SECRET_KEY_HERE` with your actual test secret key.

> **Security note:** The secret key must never be exposed to the browser. It is read server-side only by `ezjscserverfunctionsstripejs.php` and `confirm.php`. Do not add the secret key to any JS file or template.

### 8.2 Publishable key

The publishable key is embedded in the frontend JavaScript. Open:

```
extension/sevenx_payment_stripe/design/standard/javascript/stripe-checkout.js
```

Locate the line:

```js
const stripe = Stripe("pk_test_...");
```

Replace the value with your actual test publishable key (`pk_test_...`).

### 8.3 Return URL

In `stripe-checkout.js`, locate the `confirmPayment()` call and verify the `return_url`:

```js
return_url: 'https://yourdomain.com/charge/confirm',
```

Replace `yourdomain.com` with your actual domain. This must be an absolute HTTPS URL in production. HTTP is acceptable for local development.

### 8.4 Per-siteaccess INI override (optional)

For multi-siteaccess installations, copy the INI file to your siteaccess settings override:

```bash
cp extension/sevenx_payment_stripe/settings/stripe.ini.append.php \
   settings/siteaccess/YOUR_SITEACCESS/stripe.ini.append.php
```

Then edit the copy with siteaccess-specific keys.

---

## 9. Registering the payment gateway

The gateway is registered automatically by `sevenx_payment_stripegateway.php` via:

```php
eZPaymentGatewayType::registerGateway(
    Sevenx_Payment_StripeGateway::GATEWAY_TYPE,
    "Sevenx_Payment_Stripegateway",
    "7x : Payment Gateway : Stripe"
);
```

To activate it in the shop workflow:

1. Log in to the eZ Publish admin interface.
2. Go to **Setup → Workflows**.
3. Edit the workflow attached to your shop's **Before order confirm** trigger.
4. Add or update the **payment gateway** event and select **7x : Payment Gateway : Stripe**.
5. Save the workflow.

---

## 10. Verifying the installation

1. Go to your storefront, add an item to the cart, and proceed to checkout.
2. At the payment step you should see the Stripe Payment Element (card field).
3. Use a [Stripe test card](https://stripe.com/docs/testing#cards) to complete a test payment:
   - Card: `4242 4242 4242 4242`
   - Expiry: any future date (e.g. `12/34`)
   - CVC: any 3 digits (e.g. `123`)
4. After submission Stripe should redirect to `/charge/confirm` and then back to `/shop/checkout`.
5. In the eZ Publish admin interface, the order should now show status **Paid** (ID 1000) with a status history entry.
6. In the [Stripe Dashboard → Payments](https://dashboard.stripe.com/test/payments), the PaymentIntent should appear as **Succeeded**.

---

## 11. Going live

When you are ready to accept real payments:

1. **Replace the secret key** in `settings/stripe.ini.append.php`:
   ```ini
   SecretKey=sk_live_YOUR_LIVE_SECRET_KEY
   ```
2. **Replace the publishable key** in `stripe-checkout.js`:
   ```js
   const stripe = Stripe("pk_live_YOUR_LIVE_PUBLISHABLE_KEY");
   ```
3. **Update the return URL** if your production domain differs from your test domain:
   ```js
   return_url: 'https://yourdomain.com/charge/confirm',
   ```
4. **Confirm HTTPS** is active and the SSL certificate is valid on your production domain.
5. **Verify Stripe Tax** settings in the Dashboard if you use automatic tax calculation.
6. Run a live end-to-end test with a real card and immediately issue a refund from the Dashboard.

---

## 12. Uninstalling

1. Remove `ActiveExtensions[]=sevenx_payment_stripe` (or `ActiveAccessExtensions[]`) from `site.ini.append.php`.
2. Remove the extension directory:
   ```bash
   rm -rf extension/sevenx_payment_stripe
   ```
3. Clear the eZ Publish cache:
   ```bash
   php bin/php/ezcache.php --clear-all
   ```
4. In the Stripe Dashboard, revoke any API keys that were used exclusively by this site.


   Make sure you have the proper settings in your
   Authorize.Net merchant login control panel.


5. Workflow & Trigger Setup
   ===========================
   A) In the eZ publish admin, go to Setup -> Workflows
      and click New Workflow Group.

   B) Name the group something like "All Payment Gateways" and click OK.
      The group will be added to the workflow groups.

   C) Click on the group you have just created, and in the
      Workflows box click the "New Workflow" button.

      1) Name the workflow "Payment Workflow".

      3) You will now see an Event select dropdown.
         1) Select "Event / Payment Gateway"

         2) Confirm you choice by clicking the "Add Event" button
            just to the right of the select dropdown.

      4) Enter a description "Authorize.Net Payment Gateway"

      5) Select a type from the multi-select list.

         If the extension has been properly installed,
         you will see Authorize.Net in the new multi-select
         box you are presented with.

         You can choose, "All" or "Authorize.Net" from the multi-select
         box to support one or many Payment Gatways.

	 a) Selecting "All" will allow the customer to select a payment
            method for themselves at checkout. This would allow the customer
            to pay using either Paypal or Authorize.Net
	 b) Selecting "Authorize.Net" will allow the customer to submit
            credit card payment using Authorize.Net


   G) Now click on the Triggers link in the left hand menu.

      In the table "Workflow Triggers" you will see a list of available triggers.

      On the line: shop, sheckout, before, (Workflow Drop Down)
      "Click" the dropdown select the payment gateway event
      you have just created.

   H) After you have followed all these steps, the extension's
      workflow settings should be activated, configured and functioning properly.


6. Shop Checkout: Testing Workflow & Trigger Setup
   ===========================
   After completing the above setup you should login to eZ publish
   add a product to your basket, proceed to checkout, enter your
   account information and confirm your order.

   Next, (depending on the Event Type selected in the worlkflow setup)
   you should be presented with a form.

   A) Either "Select gateway" (*) Paypal ( ) Authorize.Net
   B) Or "Payment Information" (Authorize.Net) input form 
      after selecting "Confirm" during the checkout process.

   C) Alternately if you would like your customers to be able to choose their
      method of payment, during step 5 you can select "All" instead of
      Authorize.Net in the multi select box. This will give the customer the
      choice of using paypal or authorize.net (or any other payment gateway you
      choose to add) when they click "Confirm" during checkout.

Usage
==============================
To switch test mode off, open up authorize.net.append.php
and switch "TestMode=TRUE" to "TestMode=FALSE".

Also make sure you have the proper settings in your
Authorize.Net merchant login control panel.


Troubleshooting
===============

1. Read the FAQ
   ------------

   Some problems are more common than others. The most common ones are listed
   in the the FAQ.

2. Support
   -------

   If you have find any problems not handled by this document or the FAQ you
   can contact Brookins Consulting trough the support system:
   http://brookinsconsulting.com/contact

