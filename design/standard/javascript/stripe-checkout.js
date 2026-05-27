// stripe-checkout.js — Stripe Payment Element integration for eZ Publish / Exponential CMS
// Set DEBUG = true locally to enable console output; always false in production.
const DEBUG = false;

// ---------------------------------------------------------------------------
// Stripe public key — swap test/live key via stripe.ini SecretKey counterpart.
// ---------------------------------------------------------------------------
// const stripe = Stripe("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");
const stripe = Stripe("sk_test_72xAGBCEMAci996N2PhlT0iB9ZZeJiyCp6tCvbA89ABtfw4fgMmBa28A4f45d6sf4das6f4afd4dsa6fads6X2VWFrlrvGOq2H4choUl6hIRq1qsm00B4lGvXWG");

// Order ID is written by the template into data-order-id on #payment-form.
const orderId = parseInt(document.querySelector("#payment-form").dataset.orderId, 10);

let elements;
let initializePromise;

// On a Stripe redirect-back the URL carries ?payment_intent=...
// Skip form init — verify via POST, then replace URL with clean /shop/checkout.
if (new URLSearchParams(window.location.search).has("payment_intent")) {
    checkStatus();
} else {
    initializePromise = initialize();
    document.querySelector("#payment-form").addEventListener("submit", handleSubmit);
}

// ---------------------------------------------------------------------------
// initialize — create PaymentIntent server-side and mount the Payment Element.
// ---------------------------------------------------------------------------
async function initialize() {
    if (!orderId) {
        showMessage("No order found. Please return to your cart and try again.");
        return;
    }

    if (DEBUG) console.log("[stripe] initialize() orderId=", orderId);

    const response = await fetch("/ezjscore/call/ezjsc-stripe::confirm", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ orderId }),
    });

    const data = await response.json();

    if (DEBUG) console.log("[stripe] confirm response status=", response.status, "data=", data);

    if (!response.ok || data.error_text || !data.content || !data.content.clientSecret) {
        showMessage(data.error_text || "Failed to initialize payment. Please try again.");
        return;
    }

    elements = stripe.elements({ clientSecret: data.content.clientSecret });
    elements.create("payment", { layout: "tabs" }).mount("#payment-element");
}

// ---------------------------------------------------------------------------
// handleSubmit — confirm payment; on success verify server-side and redirect.
// ---------------------------------------------------------------------------
async function handleSubmit(e) {
    e.preventDefault();
    setLoading(true);

    if (DEBUG) console.log("[stripe] handleSubmit fired");

    await initializePromise;

    if (!elements) {
        showMessage("Payment form not ready. Please refresh and try again.");
        setLoading(false);
        return;
    }

    if (DEBUG) console.log("[stripe] calling stripe.confirmPayment");

    const { error, paymentIntent } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            // Fallback for redirect-required methods (iDEAL, 3DS, etc.).
            return_url: window.location.origin + "/shop/checkout",
        },
        // Cards / Apple Pay / etc. confirm without leaving the page.
        redirect: "if_required",
    });

    if (DEBUG) console.log("[stripe] confirmPayment result — paymentIntent=", paymentIntent, "error=", error);

    if (paymentIntent && paymentIntent.status === "succeeded") {
        await verifyAndRedirect(paymentIntent.id);
        return;
    }

    if (error) {
        showMessage(
            (error.type === "card_error" || error.type === "validation_error")
                ? error.message
                : "An unexpected error occurred."
        );
    }

    setLoading(false);
}

// ---------------------------------------------------------------------------
// checkStatus — handles redirect-back path (?payment_intent=... in URL).
// ---------------------------------------------------------------------------
async function checkStatus() {
    const paymentIntentId = new URLSearchParams(window.location.search).get("payment_intent");
    if (!paymentIntentId) return;
    showMessage("Confirming your payment\u2026");
    await verifyAndRedirect(paymentIntentId);
}

// ---------------------------------------------------------------------------
// verifyAndRedirect — POST to ezjsc-stripe::verify, set order status 1000,
// then navigate to clean /shop/checkout so loadForm() Case 1 fires (status
// 1000 → STATUS_ACCEPTED → orderview).
// ---------------------------------------------------------------------------
async function verifyAndRedirect(paymentIntentId) {
    if (DEBUG) console.log("[stripe] verifyAndRedirect paymentIntentId=", paymentIntentId);

    try {
        const response = await fetch("/ezjscore/call/ezjsc-stripe::verify", {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            body: JSON.stringify({ paymentIntentId }),
        });

        const data = await response.json();

        if (DEBUG) console.log("[stripe] verify response status=", response.status, "data=", data);

        if (!response.ok || data.error_text) {
            showMessage(data.error_text || "Payment verification failed. Please contact support.");
            setLoading(false);
            return;
        }

        window.location.replace("/shop/checkout");
    } catch (err) {
        showMessage("Payment verification failed. Please contact support.");
        setLoading(false);
    }
}

// ---------------------------------------------------------------------------
// UI helpers
// ---------------------------------------------------------------------------
function showMessage(messageText) {
    const el = document.querySelector("#payment-message");
    el.classList.remove("hidden");
    el.textContent = messageText;
    setTimeout(function () {
        el.classList.add("hidden");
        el.textContent = "";
    }, 4000);
}

function setLoading(isLoading) {
    document.querySelector("#submit").disabled = isLoading;
    document.querySelector("#spinner").classList.toggle("hidden", !isLoading);
    document.querySelector("#button-text").classList.toggle("hidden", isLoading);
}
