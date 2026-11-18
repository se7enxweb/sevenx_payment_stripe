<?php
//
// Sevenx_Payment_StripeKeys — which Stripe keys the shop uses: test or live, kept apart.
//
// stripe.ini [Sevenx_Payment_Stripe_Settings] Mode=live|test (default live: the shop as it was).
//   live  SecretKey and PublishableKey of stripe.ini (settings/override), as before.
//   test  the test keys from the kernel's secret store (expSecrets), never from an INI file:
//         TestSecretKeySecret (default stripe-test-secret-key), TestPublishableKeySecret (stripe-test-publishable-key).
//         A test key set refuses a live key (sk_live_ / pk_live_), so a test run can never charge real cards.
// The same secret names are used by the Katalyst billing engine ([StripeSettings] of katalyst.ini), so one set of
// test keys serves both. No key is ever logged or shown.
//
// Copyright (C) 2001 - 2026 7x. GNU General Public License v2.0 (or any later version).
//

class Sevenx_Payment_StripeKeys
{
    const SECTION = 'Sevenx_Payment_Stripe_Settings';

    /** live or test (stripe.ini Mode; anything else counts as live, the shop's behavior before the setting). */
    public static function mode()
    {
        $ini = eZINI::instance( 'stripe.ini' );
        $mode = $ini->hasVariable( self::SECTION, 'Mode' ) ? strtolower( trim( (string)$ini->variable( self::SECTION, 'Mode' ) ) ) : 'live';
        if ( $mode !== 'test' && self::testBuyer() )
            return 'test';
        return $mode === 'test' ? 'test' : 'live';
    }

    /** The class a hook setting names, when it exists and has the method; '' otherwise. */
    protected static function handler( $setting, $method )
    {
        $ini = eZINI::instance( 'stripe.ini' );
        $class = $ini->hasVariable( self::SECTION, $setting ) ? trim( (string)$ini->variable( self::SECTION, $setting ) ) : '';
        return $class !== '' && class_exists( $class ) && method_exists( $class, $method ) ? $class : '';
    }

    /**
     * Whether the signed-in buyer pays with the test keys while Mode=live: stripe.ini ModeHandler=<class> with a static
     * testModeFor( $userId ) (a shop's staff test buyers). No handler: never.
     */
    public static function testBuyer()
    {
        $class = self::handler( 'ModeHandler', 'testModeFor' );
        return $class !== '' && class_exists( 'eZUser' ) && (bool)call_user_func( array( $class, 'testModeFor' ), (int)eZUser::currentUserID() );
    }

    /**
     * Extra PaymentIntent parameters of an order: stripe.ini IntentHandler=<class> with a static intentParams( $order )
     * (e.g. capture_method=manual for an order of which a part is only taken later). Only capture_method and
     * payment_method_options are taken. No handler: none.
     */
    public static function intentParams( $order )
    {
        $class = self::handler( 'IntentHandler', 'intentParams' );
        $p = $class !== '' ? (array)call_user_func( array( $class, 'intentParams' ), $order ) : array();
        return array_intersect_key( $p, array( 'capture_method' => 1, 'payment_method_options' => 1 ) );
    }

    protected static function setting( $name, $default )
    {
        $ini = eZINI::instance( 'stripe.ini' );
        return $ini->hasVariable( self::SECTION, $name ) && (string)$ini->variable( self::SECTION, $name ) !== '' ? (string)$ini->variable( self::SECTION, $name ) : $default;
    }

    /** A test key from the secret store; '' when missing or when it is not a test key. */
    protected static function testKey( $setting, $default, $pattern )
    {
        $value = class_exists( 'expSecrets' ) ? (string)expSecrets::get( self::setting( $setting, $default ) ) : '';
        return preg_match( $pattern, $value ) ? $value : '';
    }

    /** The secret key of the mode in use. */
    public static function secretKey()
    {
        if ( self::mode() === 'test' )
            return self::testKey( 'TestSecretKeySecret', 'stripe-test-secret-key', '/^(sk|rk)_test_[A-Za-z0-9]+$/D' );
        return (string)eZINI::instance( 'stripe.ini' )->variable( self::SECTION, 'SecretKey' );
    }

    /** The publishable key of the mode in use (for Stripe.js). */
    public static function publishableKey()
    {
        if ( self::mode() === 'test' )
            return self::testKey( 'TestPublishableKeySecret', 'stripe-test-publishable-key', '/^pk_test_[A-Za-z0-9]+$/D' );
        return (string)eZINI::instance( 'stripe.ini' )->variable( self::SECTION, 'PublishableKey' );
    }
}

?>
