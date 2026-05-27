<?php
//
// DEPRECATED stub — this view is not used in the active payment flow.
//
// Current flow:
//   1. stripe.confirmPayment() runs client-side with redirect:"if_required"
//   2. On success the JS calls POST /ezjscore/call/ezjsc-stripe::verify  ← primary path
//      Fallback module path: POST /charge/verify  ← mirrors the eZJSCore method
//   3. On success the JS redirects to /shop/checkout (no server-side relay needed)
//
// Stripe's return_url is /shop/checkout.  If Stripe does redirect (3DS etc.) the
// JS running on /shop/checkout detects payment_intent in the URL and calls verify
// automatically.  This view is therefore never invoked and exists only to satisfy
// the registered module route.
//
// GNU General Public License v2.0 (or any later version)
//

$module->redirectTo( '/shop/checkout' );
return;
