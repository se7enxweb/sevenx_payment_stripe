<?php
/**
 * Stripe PaymentIntent verification — module view.
 *
 * Mirrors ezjscServerFunctionsStripeJs::verify() but uses direct HTTP output
 * (header / http_response_code / eZExecution::cleanExit) instead of eZJSCore's
 * return-value / exception convention.  The business logic is identical.
 *
 * Endpoint (registered in module.php):  /charge/verify
 * Active eZJSCore endpoint:             POST /ezjscore/call/ezjsc-stripe::verify
 *
 * This module view acts as a fallback / server-rendered path.  The primary
 * production path runs through eZJSCore.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

header( 'Content-Type: application/json' );

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Emit a JSON error response and halt execution.
 *
 * @param int    $code    HTTP status code
 * @param string $message Human-readable error description
 */
function stripeVerifyError( $code, $message )
{
    http_response_code( $code );
    echo json_encode( [ 'error' => $message ] );
    eZExecution::cleanExit();
}

// ── Read request body ─────────────────────────────────────────────────────────

$rawBody = file_get_contents( 'php://input' );
$data    = json_decode( $rawBody, true );

if ( !$rawBody || json_last_error() !== JSON_ERROR_NONE )
{
    stripeVerifyError( 400, 'Invalid JSON body.' );
}

$paymentIntentId = trim( $data['paymentIntentId'] ?? '' );

if ( !$paymentIntentId )
{
    stripeVerifyError( 400, 'Missing paymentIntentId.' );
}

// ── Stripe client ─────────────────────────────────────────────────────────────

$stripeSecretKey = eZINI::instance( 'stripe.ini' )->variable( 'Sevenx_Payment_Stripe_Settings', 'SecretKey' );
$stripe          = new \Stripe\StripeClient( $stripeSecretKey );

// ── Retrieve and validate the PaymentIntent ───────────────────────────────────

try
{
    $paymentIntent = $stripe->paymentIntents->retrieve( $paymentIntentId );
}
catch ( \Throwable $e )
{
    eZDebug::writeError( 'Stripe verify retrieve error: ' . $e->getMessage(), 'charge/verify' );
    stripeVerifyError( 502, 'Failed to retrieve PaymentIntent from Stripe.' );
}

if ( $paymentIntent->status !== 'succeeded' )
{
    stripeVerifyError( 400, 'PaymentIntent has not succeeded (status: ' . $paymentIntent->status . ').' );
}

$orderId = isset( $paymentIntent->metadata['order_id'] )
    ? (int) $paymentIntent->metadata['order_id']
    : 0;

if ( $orderId <= 0 )
{
    stripeVerifyError( 400, 'Invalid or missing order_id in PaymentIntent metadata.' );
}

// ── Load and guard the order ──────────────────────────────────────────────────

$order = eZOrder::fetch( $orderId );

if ( !$order )
{
    stripeVerifyError( 404, 'Order not found: ' . $orderId );
}

// Idempotency: skip modifyStatus() if the order is already marked Paid (1000).
if ( (int) $order->attribute( 'status_id' ) !== 1000 )
{
    $order->modifyStatus( 1000 );
}

// ── Return success ────────────────────────────────────────────────────────────

http_response_code( 200 );
echo json_encode( [
    'orderId' => $orderId,
    'status'  => 'paid',
] );
eZExecution::cleanExit();
