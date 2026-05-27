<?php
//
// Sevenx_Payment_StripeGateway — eZ Publish payment gateway for Stripe.
//
// Payment flow:
//   1. loadForm()              — renders the Stripe Payment Element checkout template.
//   2. ezjsc-stripe::confirm   — JS POSTs the order ID; creates a Stripe PaymentIntent
//                                and returns the clientSecret.
//   3. stripe.confirmPayment() — Stripe processes the payment and redirects to
//                                /shop/checkout?payment_intent=pi_...
//   4. ezjsc-stripe::verify   — JS POSTs the paymentIntentId; retrieves the PI
//                                server-side, calls modifyStatus(1000).
//   5. JS window.location.replace('/shop/checkout') — clean URL, no GET params.
//   6. loadForm() Case 1        — order status is already 1000; returns STATUS_ACCEPTED.
//                                The kernel activates the order and redirects to orderview.
//

class Sevenx_Payment_StripeGateway extends eZCurlGateway
{
    const GATEWAY_TYPE = "Sevenx_Payment_Stripe";

    function __construct()
    {
    }

    function doCURL( &$process )
    {
        return false;
    }

    function execute( $process, $event )
    {
        // $http = eZHTTPTool::instance();
        // $processParameters = $process->attribute( 'parameter_list' );
        // $processID =  $process->attribute( 'id' );
        return $this->loadForm( $process, false );
    }

    function loadForm( &$process, $errors = 0 )
    {
        $processParams = $process->attribute( 'parameter_list' );
        $ini           = eZINI::instance( 'stripe.ini' );
        $order         = eZOrder::fetch( $processParams['order_id'] );

        // Case 1: Already Paid — advance workflow immediately.
        if ( $order && (int)$order->attribute( 'status_id' ) === 1000 )
        {
            return eZWorkflowEventType::STATUS_ACCEPTED;
        }

        // Case 2: Fresh checkout — render the Stripe Payment Element form.
        // ezjsc-stripe::verify (called by JS after Stripe redirect) already set
        // status_id to 1000 before the clean reload reaches here, so Case 1
        // above handles the return path — nothing to do in this case.

        $tplVars['s_display_help']  = $ini->variable( 'Sevenx_Payment_Stripe_Settings', 'DisplayHelp' );
        $tplVars['errors']          = $errors;
        $tplVars['order_id']        = $processParams['order_id'];
        $tplVars['publishable_key'] = $ini->variable( 'Sevenx_Payment_Stripe_Settings', 'PublishableKey' );

        $process->Template = array(
            'templateName' => 'design:workflow/eventtype/result/stripe_form.tpl',
            'templateVars' => $tplVars,
            'path'         => array( array( 'url' => false, 'text' => 'Payment Information' ) ),
        );

        return eZWorkflowEventType::STATUS_FETCH_TEMPLATE_REPEAT;
    }
}

eZPaymentGatewayType::registerGateway( Sevenx_Payment_StripeGateway::GATEWAY_TYPE, "Sevenx_Payment_Stripegateway", "7x : Payment Gateway : Stripe" );

?>