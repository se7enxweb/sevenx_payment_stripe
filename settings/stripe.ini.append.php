[Sevenx_Payment_Stripe_Settings]

# Stripe API keys — both must belong to the SAME Stripe account.
# Use test keys during development, live keys in production.
# Set real values in settings/override/stripe.ini.append.php, never here.
# SecretKey=sk_test_REPLACE_WITH_SECRET_KEY
# PublishableKey=pk_test_REPLACE_WITH_PUBLISHABLE_KEY

# Which keys: live (SecretKey/PublishableKey above, in settings/override) or test. Test keys are never kept in an
# INI file: they come from the secret store (expSecrets; Setup > Secrets) by these names, and a test set refuses a live
# key. The Katalyst billing engine uses the same names for its test mode ([StripeSettings] of katalyst.ini).
Mode=live
TestSecretKeySecret=stripe-test-secret-key
TestPublishableKeySecret=stripe-test-publishable-key

# Show payment help text on the checkout form.
DisplayHelp=false

# Currency every PaymentIntent is created in and checked against (lower case
# ISO 4217). An intent paid in another currency never completes an order.
Currency=usd
