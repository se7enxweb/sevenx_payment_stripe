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

require_once __DIR__ . '/../../classes/sevenx_payment_stripeordercheck.php';
Sevenx_Payment_StripeOrderCheck::loadSdk();

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

require_once __DIR__ . '/../../classes/sevenx_payment_stripekeys.php';
$stripeSecretKey = Sevenx_Payment_StripeKeys::secretKey();
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

// ── Check the intent against the checkout's order and mark it Paid ─────────────
// Sevenx_Payment_StripeOrderCheck compares status, metadata order_id, currency,
// amount and amount_received (as Stripe returned them) with the order's own total.
// The order is the session's MyTemporaryOrderID; a posted orderId is only a fallback.

require_once __DIR__ . '/../../classes/sevenx_payment_stripeordercheck.php';

$expectedOrderId = (int)eZHTTPTool::instance()->sessionVariable( 'MyTemporaryOrderID' );
if ( $expectedOrderId <= 0 )
{
    $expectedOrderId = (int)( $data['orderId'] ?? 0 );
}

$refusal = '';
$orderId = 0;
try
{
    $orderId = Sevenx_Payment_StripeOrderCheck::verifyAndMarkPaid( $paymentIntent, $expectedOrderId );
}
catch ( \RuntimeException $e )
{
    $refusal = $e->getMessage();
}

if ( $refusal !== '' )
{
    stripeVerifyError( 409, $refusal );
}

// ── Return success ────────────────────────────────────────────────────────────

http_response_code( 200 );
echo json_encode( [
    'orderId' => $orderId,
    'status'  => 'paid',
] );
eZExecution::cleanExit();
