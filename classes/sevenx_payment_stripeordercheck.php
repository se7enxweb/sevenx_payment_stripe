<?php
//
// Sevenx_Payment_StripeOrderCheck — ties a Stripe PaymentIntent to one shop order.
//
// An order is marked Paid only when the PaymentIntent, as Stripe returns it to the
// server (never as the browser describes it):
//   - has the status "succeeded";
//   - names this order in its metadata (order_id is written server-side when the
//     intent is created, so an intent paid for one order cannot complete another);
//   - was charged in the shop's currency;
//   - was charged exactly the order's total in cents, computed on the server from the
//     order (products, shipping and order items such as coupons included).
//
// A mismatch is refused: the order keeps its status, the reason is logged with ids
// and amounts only (var/log/stripe-payment.log), and the buyer gets a clear message.
//
// Copyright (C) 2001 - 2026 7x. GNU General Public License v2.0 (or any later version).
//

class Sevenx_Payment_StripeOrderCheck
{
    const PAID_STATUS_ID = 1000;
    const DEFAULT_CURRENCY = 'usd';
    const LOG_FILE = 'stripe-payment.log';

    /**
     * Makes the Stripe SDK (stripe/stripe-php, in the installation's vendor/) available.
     *
     * The kernel already loads the installation's composer autoloader (autoload.php of the installation root, the
     * EZCBASE_ENABLED block: "/vendor/autoload.php" next to it), so \Stripe\StripeClient normally autoloads. A path
     * relative to this file (__DIR__ . '/../../../vendor') is wrong: the extension is a symlink into the site's
     * own clone, where __DIR__ resolves outside the installation. Fallback: the installation root, eZSys::rootDir().
     *
     * @throws RuntimeException when the SDK cannot be found
     */
    public static function loadSdk()
    {
        if ( class_exists( '\Stripe\StripeClient' ) )
            return;
        $autoload = rtrim( eZSys::rootDir(), '/' ) . '/vendor/autoload.php';
        if ( is_file( $autoload ) )
            require_once $autoload;
        if ( !class_exists( '\Stripe\StripeClient' ) )
        {
            eZDebug::writeError( 'The Stripe SDK (stripe/stripe-php) is not installed in vendor/', __METHOD__ );
            throw new \RuntimeException( 'Card payment is not available right now. Please try again later or contact us.' );
        }
    }

    /**
     * The currency the shop charges in, as Stripe writes it (lower case ISO 4217).
     * stripe.ini [Sevenx_Payment_Stripe_Settings] Currency, "usd" when not set.
     */
    public static function currency()
    {
        $currency = '';
        if ( class_exists( 'eZINI' ) )
        {
            $ini = eZINI::instance( 'stripe.ini' );
            if ( $ini->hasVariable( 'Sevenx_Payment_Stripe_Settings', 'Currency' ) )
            {
                $currency = (string)$ini->variable( 'Sevenx_Payment_Stripe_Settings', 'Currency' );
            }
        }
        $currency = strtolower( trim( $currency ) );
        return preg_match( '/^[a-z]{3}$/', $currency ) ? $currency : self::DEFAULT_CURRENCY;
    }

    /**
     * The amount to charge for an order, in cents, from the order's own total
     * (total_inc_vat includes the order items, so coupons and shipping count).
     */
    /**
     * The currency an order is charged in: the currency of its product collection (the buyer's currency when the
     * basket was priced, shop/setpreferredcurrency), as Stripe writes it (lower case). The amounts of the order
     * (total_inc_vat) are in that currency. Falls back to currency() for an order without one.
     */
    public static function orderCurrency( $order )
    {
        $collection = is_object( $order ) ? $order->attribute( 'productcollection' ) : null;
        $code = $collection ? strtolower( trim( (string)$collection->attribute( 'currency_code' ) ) ) : '';
        return preg_match( '/^[a-z]{3}$/', $code ) ? $code : self::currency();
    }

    public static function orderAmountCents( $order )
    {
        return self::amountToCents( $order->attribute( 'total_inc_vat' ) );
    }

    public static function amountToCents( $amount )
    {
        return (int)round( round( (float)$amount, 2 ) * 100 );
    }

    /**
     * Compares a PaymentIntent with the order it must pay. Pure: no database, no I/O.
     *
     * @param object|array $paymentIntent  \Stripe\PaymentIntent (or the same fields as an array)
     * @param int          $expectedOrderId
     * @param int          $expectedCents
     * @param string       $expectedCurrency  lower case ISO 4217
     * @return string  '' when the intent pays exactly this order, else a reason code
     */
    public static function mismatchReason( $paymentIntent, $expectedOrderId, $expectedCents, $expectedCurrency )
    {
        return self::mismatchReasonFor( $paymentIntent, 'order_id', $expectedOrderId, $expectedCents, $expectedCurrency );
    }

    /**
     * The same check for any payment a PaymentIntent must pay: metadata[$metadataKey] names it (the shop: order_id;
     * the Katalyst billing engine: katalyst_pay, its payment row). Pure: no database, no I/O.
     *
     * @return string  '' when the intent pays exactly this, else a reason code
     */
    public static function mismatchReasonFor( $paymentIntent, $metadataKey, $expectedOrderId, $expectedCents, $expectedCurrency )
    {
        $status   = (string)self::field( $paymentIntent, 'status' );
        $metadata = self::field( $paymentIntent, 'metadata' );
        $orderId  = 0;
        if ( is_array( $metadata ) || $metadata instanceof ArrayAccess )
        {
            $orderId = isset( $metadata[$metadataKey] ) ? (int)$metadata[$metadataKey] : 0;
        }
        elseif ( is_object( $metadata ) && isset( $metadata->$metadataKey ) )
        {
            $orderId = (int)$metadata->$metadataKey;
        }

        if ( $status !== 'succeeded' )
        {
            return 'status_not_succeeded';
        }
        if ( (int)$expectedOrderId <= 0 || $orderId !== (int)$expectedOrderId )
        {
            return 'order_mismatch';
        }
        if ( strtolower( (string)self::field( $paymentIntent, 'currency' ) ) !== strtolower( (string)$expectedCurrency ) )
        {
            return 'currency_mismatch';
        }
        if ( (int)$expectedCents <= 0 )
        {
            return 'order_amount_invalid';
        }
        if ( (int)self::field( $paymentIntent, 'amount' ) !== (int)$expectedCents )
        {
            return 'amount_mismatch';
        }
        if ( (int)self::field( $paymentIntent, 'amount_received' ) !== (int)$expectedCents )
        {
            return 'amount_received_mismatch';
        }
        return '';
    }

    /**
     * The message shown to the buyer for a refusal.
     */
    public static function buyerMessage( $reason )
    {
        switch ( $reason )
        {
            case 'status_not_succeeded':
                return 'Your payment has not been completed. No order was placed. Please try again.';
            case 'order_mismatch':
                return 'This payment does not belong to the order in your cart, so the order was not completed. Please contact us; quote the order number shown in your cart.';
            case 'currency_mismatch':
            case 'amount_mismatch':
            case 'amount_received_mismatch':
            case 'order_amount_invalid':
                return 'The amount paid does not match your order total, so the order was not completed. Please contact us before paying again; quote your order number.';
            case 'order_not_found':
            case 'access_denied':
            default:
                return 'Your order could not be confirmed. Please contact us; quote your order number.';
        }
    }

    /**
     * Checks a PaymentIntent retrieved from Stripe against the expected order and marks
     * the order Paid. Throws a RuntimeException with the buyer's message on any refusal.
     *
     * @param \Stripe\PaymentIntent $paymentIntent  retrieved server-side with the secret key
     * @param int                   $expectedOrderId the order this checkout is paying (session)
     * @return int  the order id
     */
    public static function verifyAndMarkPaid( $paymentIntent, $expectedOrderId )
    {
        $expectedOrderId = (int)$expectedOrderId;
        $intentId        = (string)self::field( $paymentIntent, 'id' );

        $order = $expectedOrderId > 0 ? eZOrder::fetch( $expectedOrderId ) : null;
        if ( !$order )
        {
            self::refuse( 'order_not_found', $expectedOrderId, $intentId );
        }
        if ( (int)$order->attribute( 'user_id' ) !== (int)eZUser::currentUserID() )
        {
            self::refuse( 'access_denied', $expectedOrderId, $intentId );
        }

        $expectedCents    = self::orderAmountCents( $order );
        $expectedCurrency = self::orderCurrency( $order );
        $reason = self::mismatchReason( $paymentIntent, $expectedOrderId, $expectedCents, $expectedCurrency );
        if ( $reason !== '' )
        {
            self::refuse( $reason, $expectedOrderId, $intentId, array(
                'expected_cents'    => $expectedCents,
                'expected_currency' => $expectedCurrency,
                'intent_order_id'   => self::intentOrderId( $paymentIntent ),
                'intent_status'     => (string)self::field( $paymentIntent, 'status' ),
                'intent_amount'     => (int)self::field( $paymentIntent, 'amount' ),
                'intent_received'   => (int)self::field( $paymentIntent, 'amount_received' ),
                'intent_currency'   => (string)self::field( $paymentIntent, 'currency' ),
            ) );
        }

        // Idempotent: an order already marked Paid stays as it is.
        if ( (int)$order->attribute( 'status_id' ) !== self::PAID_STATUS_ID )
        {
            $order->modifyStatus( self::PAID_STATUS_ID );
            self::log( 'paid order=' . $expectedOrderId . ' intent=' . $intentId . ' cents=' . $expectedCents . ' currency=' . $expectedCurrency );
        }
        return $expectedOrderId;
    }

    /**
     * The order id the PaymentIntent names in its metadata (0 when none).
     */
    public static function intentOrderId( $paymentIntent )
    {
        $metadata = self::field( $paymentIntent, 'metadata' );
        if ( ( is_array( $metadata ) || $metadata instanceof ArrayAccess ) && isset( $metadata['order_id'] ) )
        {
            return (int)$metadata['order_id'];
        }
        return 0;
    }

    private static function refuse( $reason, $orderId, $intentId, array $details = array() )
    {
        $line = 'refused reason=' . $reason . ' order=' . (int)$orderId . ' intent=' . $intentId;
        foreach ( $details as $key => $value )
        {
            $line .= ' ' . $key . '=' . $value;
        }
        self::log( $line );
        if ( class_exists( 'eZDebug' ) )
        {
            eZDebug::writeWarning( $line, __CLASS__ );
        }
        throw new \RuntimeException( self::buyerMessage( $reason ) );
    }

    private static function log( $line )
    {
        if ( class_exists( 'eZLog' ) )
        {
            eZLog::write( $line, self::LOG_FILE );
        }
    }

    private static function field( $object, $name )
    {
        if ( is_array( $object ) )
        {
            return isset( $object[$name] ) ? $object[$name] : null;
        }
        if ( is_object( $object ) )
        {
            return isset( $object->$name ) ? $object->$name : null;
        }
        return null;
    }
}

?>
