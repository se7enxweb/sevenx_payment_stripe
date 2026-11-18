<?php
//
// Created on: <24-May-2026 05:45:42 gb>
//
// Copyright (C) 2001 - 2026 7x. All rights reserved.
//
// This file may be distributed and/or modified under the terms of the
// GNU General Public License v2.0 (or any later version), as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact license@se7enx.com if any conditions
// of this licencing isn't clear to you.
//

require_once __DIR__ . '/../../classes/sevenx_payment_stripeordercheck.php';
Sevenx_Payment_StripeOrderCheck::loadSdk();

require_once __DIR__ . '/../../classes/sevenx_payment_stripekeys.php';
$stripeSecretKey = Sevenx_Payment_StripeKeys::secretKey();
$stripe = new \Stripe\StripeClient( $stripeSecretKey );

// Default Stripe tax code — mirrors ezjscServerFunctionsStripeJs::DEFAULT_TAX_CODE.
// txcd_99999999 = General - Tangible Goods | txcd_10000000 = General - Services
// Full list: https://stripe.com/docs/tax/tax-codes
if ( !defined( 'STRIPE_DEFAULT_TAX_CODE' ) )
{
    define( 'STRIPE_DEFAULT_TAX_CODE', 'txcd_99999999' );
}

/**
 * Build Stripe Tax line items from an eZ Publish product collection.
 * Each item uses the ex-VAT unit price * quantity in cents.
 */
function buildLineItemsFromOrder($productItems) {
    $lineItems = [];
    foreach ($productItems as $item) {
        // eZ Publish may return objects or associative arrays depending on version/config
        if (is_array($item)) {
            $priceExVat = (float)($item['price_ex_vat'] ?? 0);
            $quantity   = max(1, (int)($item['count'] ?? 1));
            $reference  = (string)($item['object_id'] ?? 'item');
        } else {
            $priceExVat = (float)$item->attribute('price_ex_vat');
            $quantity   = max(1, (int)$item->attribute('count'));
            $reference  = (string)$item->attribute('object_id');
        }
        $amountCents = (int)round($priceExVat * $quantity * 100);
        if ($amountCents <= 0) {
            continue;
        }
        $lineItems[] = [
            'amount'    => $amountCents,
            'reference' => $reference ?: 'item',
            'tax_code'  => STRIPE_DEFAULT_TAX_CODE,
        ];
    }
    return $lineItems;
}

/**
 * Extract the shipping address from an eZ Publish order's account information.
 * Mirrors ezjscServerFunctionsStripeJs::buildShippingAddress().
 * Returns null if the order carries no country — Stripe Tax requires at least that.
 */
function buildShippingAddress( $order ) {
    $info = $order->attribute( 'account_information' );
    if ( !$info ) {
        return null;
    }
    // account_information may return an object or a plain associative array.
    if ( is_array( $info ) ) {
        $country    = trim( (string)( $info['country'] ?? '' ) );
        $line1      = trim( (string)( $info['address'] ?? '' ) );
        $city       = trim( (string)( $info['place']   ?? '' ) );
        $state      = trim( (string)( $info['state']   ?? '' ) );
        $postalCode = trim( (string)( $info['zip']     ?? '' ) );
    } else {
        $country    = trim( (string) $info->attribute( 'country' ) );
        $line1      = trim( (string) $info->attribute( 'address' ) );
        $city       = trim( (string) $info->attribute( 'place' ) );
        $state      = trim( (string) $info->attribute( 'state' ) );
        $postalCode = trim( (string) $info->attribute( 'zip' ) );
    }
    if ( empty( $country ) ) {
        return null;
    }
    $address = [ 'country' => $country ];
    if ( $line1 )      { $address['line1']       = $line1; }
    if ( $city )       { $address['city']        = $city; }
    if ( $state )      { $address['state']       = $state; }
    if ( $postalCode ) { $address['postal_code'] = $postalCode; }
    return $address;
}

/**
 * Call Stripe Tax Calculations API with pre-built line items.
 * Returns the calculation object or null on failure (so a tax API
 * issue never blocks the payment).
 */
function calculateTax($stripe, array $lineItems, $currency, ?array $customerAddress) {
    if (empty($lineItems) || $customerAddress === null) {
        return null;
    }
    try {
        return $stripe->tax->calculations->create([
            'currency'         => $currency,
            'customer_details' => [
                'address'        => $customerAddress,
                'address_source' => 'shipping',
            ],
            'line_items' => $lineItems,
        ]);
    } catch (\Throwable $e) {
        // Tax calculation is best-effort; log and continue. An address Stripe cannot place is the buyer's input,
        // not a fault (the payment goes on without tax): a notice. Everything else is an error.
        if (method_exists($e, 'getStripeCode') && $e->getStripeCode() === 'customer_tax_location_invalid') {
            eZDebug::writeNotice('Stripe Tax: no tax location for the address given, paid without tax: ' . $e->getMessage(), 'charge.php');
        } else {
            eZDebug::writeError('Stripe Tax calculation failed: ' . $e->getMessage(), 'charge.php');
        }
        return null;
    }
}

header('Content-Type: application/json');

try {
    $jsonStr = file_get_contents('php://input');
    $jsonObj = json_decode($jsonStr);

    // Validate order ID sent by JS
    $orderId = isset($jsonObj->orderId) ? (int)$jsonObj->orderId : 0;
    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid order ID.']);
        eZExecution::cleanExit();
    }

    // Fetch the eZ Publish order — this is the authoritative source of price/tax
    $order = eZOrder::fetch($orderId);
    if (!$order) {
        http_response_code(400);
        echo json_encode(['error' => 'Order not found.']);
        eZExecution::cleanExit();
    }

    // Verify the order belongs to the currently logged-in user.
    // Prevents one user from creating a PaymentIntent against another user's order.
    if ( (int)$order->attribute( 'user_id' ) !== (int)eZUser::currentUserID() ) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied.']);
        eZExecution::cleanExit();
    }

    // The amount is the order's own total in cents (coupons and shipping included),
    // computed on the server; nothing posted by the browser counts.
    require_once __DIR__ . '/../../classes/sevenx_payment_stripeordercheck.php';
    if ( (int)$order->attribute( 'status_id' ) === Sevenx_Payment_StripeOrderCheck::PAID_STATUS_ID ) {
        http_response_code(409);
        echo json_encode(['error' => 'This order has already been paid.']);
        eZExecution::cleanExit();
    }
    $amountCents  = Sevenx_Payment_StripeOrderCheck::orderAmountCents( $order );
    $currency     = Sevenx_Payment_StripeOrderCheck::orderCurrency( $order );

    if ($amountCents <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Order amount is invalid.']);
        eZExecution::cleanExit();
    }

    // Read shipping address from the order's account information (entered during checkout).
    // Returns null if no usable address — calculateTax() skips silently in that case.
    $shippingAddress = buildShippingAddress( $order );

    // Build Stripe Tax line items from actual order products (non-blocking)
    try {
        $productItems = $order->attribute('product_items_ordered');
        $lineItems    = buildLineItemsFromOrder($productItems ?: []);
    } catch (\Throwable $e) {
        eZDebug::writeError('Failed to build line items: ' . $e->getMessage(), 'charge.php');
        $lineItems = [];
    }

    // Attempt Stripe Tax Calculation (non-blocking)
    $taxCalculation = calculateTax($stripe, $lineItems, $currency, $shippingAddress);

    // Metadata: always include order_id; add tax_calculation when available
    $metadata = ['order_id' => (string)$orderId];
    if ($taxCalculation) {
        $metadata['tax_calculation'] = $taxCalculation->id;
    }

    // Create the PaymentIntent using the eZ Publish authoritative total
    $paymentIntent = $stripe->paymentIntents->create([
        'amount'   => $amountCents,
        'currency' => $currency,
        'automatic_payment_methods' => ['enabled' => true],
        'metadata' => $metadata,
    ] + Sevenx_Payment_StripeKeys::intentParams( $order ));

    echo json_encode(['clientSecret' => $paymentIntent->client_secret]);

} catch (\Throwable $e) {
    eZDebug::writeError( $e->getMessage(), 'charge.php' );
    http_response_code(500);
    echo json_encode(['error' => 'An internal error occurred. Please try again.']);
}

// Webhook handler: invoke when `payment_intent.succeeded` is received.
// Creates a finalized Tax Transaction in Stripe for the completed payment.
function handlePaymentIntentSucceeded($stripe, $paymentIntent) {
    if (empty($paymentIntent->metadata['tax_calculation'])) {
        return;
    }
    $orderId = isset($paymentIntent->metadata['order_id']) ? $paymentIntent->metadata['order_id'] : 'unknown';
    $stripe->tax->transactions->createFromCalculation([
        'calculation' => $paymentIntent->metadata['tax_calculation'],
        'reference'   => 'order_' . $orderId,
    ]);
}

eZExecution::cleanExit();

?>