<?php
//
// Definition of ezjscServerFunctionsStripeJs class
//
// Created on: <16-Jun-2008 00:00:00 ar>
// Updated on: <25-May-2026>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ JSCore extension for eZ Publish
// SOFTWARE RELEASE: 1.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2026 7x
// SOFTWARE LICENSE: GNU General Public License v2.0 (or any later version)
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0 of the GNU General
//   Public License as published by the Free Software Foundation,
//   or (at your option) any later version.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of the GNU General Public License
//   along with this program; if not, write to the Free Software
//   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

class ezjscServerFunctionsStripeJs extends ezjscServerFunctions
{
    // Default Stripe tax code for all products.
    // txcd_99999999 = General - Tangible Goods | txcd_10000000 = General - Services
    // Full list: https://stripe.com/docs/tax/tax-codes
    const DEFAULT_TAX_CODE = 'txcd_99999999';

    /**
     * The JSON body of the request. Tests set it (with a mocked Stripe HTTP client); a request leaves it null and
     * the body is read from php://input.
     *
     * @var string|null
     */
    public static $requestBody = null;

    private static function requestBody()
    {
        return self::$requestBody !== null ? self::$requestBody : file_get_contents( 'php://input' );
    }

    /**
     * eZJSCore server function — creates a Stripe PaymentIntent and returns
     * the clientSecret for the Stripe Payment Element.
     *
     * Called via POST /ezjscore/call/ezjsc-stripe::confirm
     * Expects JSON body: { "orderId": <int> }
     * Expects header:    Accept: application/json
     *
     * API key: extension/sevenx_payment_stripe/settings/stripe.ini.append.php
     * Publishable key: stripe-checkout.js
     *
     * @param array $args  URL segments after ::confirm (unused; orderId comes from php://input)
     * @return array       ['clientSecret' => string] — eZJSCore encodes this as response content
     * @throws \InvalidArgumentException  on bad input
     * @throws \RuntimeException          on auth / lookup failure
     */
    public static function confirm( $args )
    {
        require_once __DIR__ . '/sevenx_payment_stripeordercheck.php';
        Sevenx_Payment_StripeOrderCheck::loadSdk();
        require_once __DIR__ . '/sevenx_payment_stripeordercheck.php';

        require_once __DIR__ . '/sevenx_payment_stripekeys.php';
        $stripeSecretKey = Sevenx_Payment_StripeKeys::secretKey();
        $stripe = new \Stripe\StripeClient( $stripeSecretKey );

        // Parse order ID from JSON request body
        $jsonObj = json_decode( self::requestBody() );
        $orderId = isset( $jsonObj->orderId ) ? (int)$jsonObj->orderId : 0;
        if ( $orderId <= 0 )
        {
            throw new \InvalidArgumentException( 'Invalid order ID.' );
        }

        // Fetch order — authoritative source for amount and line items
        $order = eZOrder::fetch( $orderId );
        if ( !$order )
        {
            throw new \RuntimeException( 'Order not found.' );
        }

        // Verify the order belongs to the currently logged-in user
        if ( (int)$order->attribute( 'user_id' ) !== (int)eZUser::currentUserID() )
        {
            throw new \RuntimeException( 'Access denied.' );
        }

        // An order already paid is never charged again.
        if ( (int)$order->attribute( 'status_id' ) === Sevenx_Payment_StripeOrderCheck::PAID_STATUS_ID )
        {
            throw new \RuntimeException( 'This order has already been paid.' );
        }

        // The amount is the order's own total in cents, computed on the server from the
        // order (coupons and shipping included); nothing posted by the browser counts.
        $amountCents = Sevenx_Payment_StripeOrderCheck::orderAmountCents( $order );
        $currency    = Sevenx_Payment_StripeOrderCheck::orderCurrency( $order );
        if ( $amountCents <= 0 )
        {
            throw new \InvalidArgumentException( 'Order amount is invalid.' );
        }

        // Read shipping address from the order's account information (entered during checkout).
        // Returns null if no usable address — calculateTax() skips silently in that case.
        $shippingAddress = self::buildShippingAddress( $order );

        // Build Stripe Tax line items from order products (non-blocking)
        try
        {
            $productItems = $order->attribute( 'product_items_ordered' );
            $lineItems    = self::buildLineItems( $productItems ?: [] );
        }
        catch ( \Throwable $e )
        {
            eZDebug::writeError( 'Failed to build line items: ' . $e->getMessage(), __CLASS__ );
            $lineItems = [];
        }

        // Attempt Stripe Tax Calculation (non-blocking)
        $taxCalculation = self::calculateTax( $stripe, $lineItems, $currency, $shippingAddress );

        // Metadata: always include order_id; add tax_calculation when available
        $metadata = ['order_id' => (string)$orderId];
        if ( $taxCalculation )
        {
            $metadata['tax_calculation'] = $taxCalculation->id;
        }

        $paymentIntent = $stripe->paymentIntents->create( [
            'amount'                     => $amountCents,
            'currency'                   => $currency,
            'automatic_payment_methods'  => ['enabled' => true],
            'metadata'                   => $metadata,
        ] );

        return ['clientSecret' => $paymentIntent->client_secret];
    }

    /**
     * eZJSCore server function — verifies a Stripe PaymentIntent after the
     * customer returns from the Stripe redirect and marks the eZ Publish order
     * as Paid (status 1000) if the payment succeeded.
     *
     * Called via POST /ezjscore/call/ezjsc-stripe::verify
     * Expects JSON body: { "paymentIntentId": "pi_..." }
     * Expects header:    Accept: application/json
     * Returns JSON:      { "orderId": int, "status": "paid" }
     *
     * The PaymentIntent is always retrieved server-side from Stripe — the
     * client-supplied ID is used only as a lookup key, never trusted for status.
     *
     * @param array $args  URL segments after ::verify (unused)
     * @return array       ['orderId' => int, 'status' => 'paid']
     * @throws \InvalidArgumentException  on bad input
     * @throws \RuntimeException          on lookup / status failure
     */
    public static function verify( $args )
    {
        require_once __DIR__ . '/sevenx_payment_stripeordercheck.php';
        Sevenx_Payment_StripeOrderCheck::loadSdk();
        require_once __DIR__ . '/sevenx_payment_stripeordercheck.php';

        require_once __DIR__ . '/sevenx_payment_stripekeys.php';
        $stripeSecretKey = Sevenx_Payment_StripeKeys::secretKey();
        $stripe = new \Stripe\StripeClient( $stripeSecretKey );

        $jsonObj         = json_decode( self::requestBody() );
        $paymentIntentId = isset( $jsonObj->paymentIntentId ) ? trim( (string)$jsonObj->paymentIntentId ) : '';

        if ( !preg_match( '/^pi_[A-Za-z0-9]+$/', $paymentIntentId ) )
        {
            throw new \InvalidArgumentException( 'Missing paymentIntentId.' );
        }

        // The order this checkout pays is the one the shop put in the session at
        // shop/confirmorder. The id the page posts is only a fallback for a lost
        // session, and the order must still belong to the current user.
        $expectedOrderId = self::checkoutOrderId( $jsonObj );

        // Retrieve from Stripe: status, amount, currency and metadata come from
        // Stripe's answer to the server, never from the browser.
        $paymentIntent = $stripe->paymentIntents->retrieve( $paymentIntentId );

        $orderId = Sevenx_Payment_StripeOrderCheck::verifyAndMarkPaid( $paymentIntent, $expectedOrderId );

        return ['orderId' => $orderId, 'status' => 'paid'];
    }

    /**
     * The order the current checkout is paying: the session's MyTemporaryOrderID,
     * else the orderId the page posted (checked against the user later).
     *
     * @param object|null $jsonObj
     * @return int
     */
    private static function checkoutOrderId( $jsonObj )
    {
        $sessionOrderId = (int)eZHTTPTool::instance()->sessionVariable( 'MyTemporaryOrderID' );
        if ( $sessionOrderId > 0 )
        {
            return $sessionOrderId;
        }
        return isset( $jsonObj->orderId ) ? (int)$jsonObj->orderId : 0;
    }

    /**
     * Extract the shipping address from an eZ Publish order's account information.
     *
     * Maps standard eZ Publish checkout fields to a Stripe-compatible address array.
     * Returns null if the order carries no country — Stripe Tax requires at least that.
     *
     * @param eZOrder $order
     * @return array|null
     */
    private static function buildShippingAddress( $order )
    {
        $info = $order->attribute( 'account_information' );
        if ( !$info )
        {
            return null;
        }

        // account_information may return an eZOrderAccountInformation object
        // or a plain associative array depending on the eZ Publish version.
        if ( is_array( $info ) )
        {
            $country    = trim( (string)( $info['country']  ?? '' ) );
            $line1      = trim( (string)( $info['address']  ?? '' ) );
            $city       = trim( (string)( $info['place']    ?? '' ) );
            $state      = trim( (string)( $info['state']    ?? '' ) );
            $postalCode = trim( (string)( $info['zip']      ?? '' ) );
        }
        else
        {
            $country    = trim( (string) $info->attribute( 'country' ) );
            $line1      = trim( (string) $info->attribute( 'address' ) );
            $city       = trim( (string) $info->attribute( 'place' ) );
            $state      = trim( (string) $info->attribute( 'state' ) );
            $postalCode = trim( (string) $info->attribute( 'zip' ) );
        }

        if ( empty( $country ) )
        {
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
     * Build Stripe Tax line items from an eZ Publish product collection.
     *
     * @param array $productItems
     * @return array
     */
    private static function buildLineItems( array $productItems )
    {
        $lineItems = [];
        foreach ( $productItems as $item )
        {
            if ( is_array( $item ) )
            {
                $priceExVat = (float)( $item['price_ex_vat'] ?? 0 );
                $quantity   = max( 1, (int)( $item['count'] ?? 1 ) );
                $reference  = (string)( $item['object_id'] ?? 'item' );
            }
            else
            {
                $priceExVat = (float)$item->attribute( 'price_ex_vat' );
                $quantity   = max( 1, (int)$item->attribute( 'count' ) );
                $reference  = (string)$item->attribute( 'object_id' );
            }
            $amountCents = (int)round( $priceExVat * $quantity * 100 );
            if ( $amountCents <= 0 )
            {
                continue;
            }
            $lineItems[] = [
                'amount'    => $amountCents,
                'reference' => $reference ?: 'item',
                'tax_code'  => self::DEFAULT_TAX_CODE,
            ];
        }
        return $lineItems;
    }

    /**
     * Call Stripe Tax Calculations API. Returns the calculation object or null
     * on failure — a tax API issue never blocks the payment.
     *
     * @param \Stripe\StripeClient $stripe
     * @param array                $lineItems
     * @param string               $currency
     * @param array|null           $customerAddress  null skips the calculation
     * @return \Stripe\Tax\Calculation|null
     */
    private static function calculateTax( $stripe, array $lineItems, $currency, ?array $customerAddress )
    {
        if ( empty( $lineItems ) || $customerAddress === null )
        {
            return null;
        }
        try
        {
            return $stripe->tax->calculations->create( [
                'currency'         => $currency,
                'customer_details' => [
                    'address'        => $customerAddress,
                    'address_source' => 'shipping',
                ],
                'line_items' => $lineItems,
            ] );
        }
        catch ( \Throwable $e )
        {
            eZDebug::writeError( 'Stripe Tax calculation failed: ' . $e->getMessage(), __CLASS__ );
            return null;
        }
    }

    /**
     * Finalise a Stripe Tax Transaction after a successful payment.
     *
     * Call this from a Stripe webhook handler when the
     * `payment_intent.succeeded` event is received. Creates a finalised
     * Tax Transaction in Stripe so the tax liability is recorded correctly.
     *
     * Mirrors charge.php::handlePaymentIntentSucceeded().
     * Not called in the normal synchronous payment flow.
     *
     * @param \Stripe\StripeClient   $stripe
     * @param \Stripe\PaymentIntent  $paymentIntent
     * @return void
     */
    public static function handlePaymentIntentSucceeded( $stripe, $paymentIntent )
    {
        if ( empty( $paymentIntent->metadata['tax_calculation'] ) )
        {
            return;
        }
        $orderId = $paymentIntent->metadata['order_id'] ?? 'unknown';
        try
        {
            $stripe->tax->transactions->createFromCalculation( [
                'calculation' => $paymentIntent->metadata['tax_calculation'],
                'reference'   => 'order_' . $orderId,
            ] );
        }
        catch ( \Throwable $e )
        {
            eZDebug::writeError( 'Stripe Tax transaction finalisation failed: ' . $e->getMessage(), __CLASS__ );
        }
    }
}

?>

