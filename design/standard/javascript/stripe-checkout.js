// stripe-checkout.js — Stripe Payment Element integration for eZ Publish / Exponential CMS
// Set DEBUG = true locally to enable console output; always false in production.
const DEBUG = false;

// ---------------------------------------------------------------------------
// Stripe public key — read from data-publishable-key set by stripe_form.tpl.
// The template gets the value from stripe.ini PublishableKey so test/live
// switching is done entirely in INI config with no JS edits needed.
// ---------------------------------------------------------------------------
const publishableKey = document.querySelector("#payment-form").dataset.publishableKey;
if (!publishableKey) { throw new Error("[stripe] data-publishable-key not set on #payment-form"); }
const stripe = Stripe(publishableKey);

// Order ID is written by the template into data-order-id on #payment-form.
const orderId = parseInt(document.querySelector("#payment-form").dataset.orderId, 10);

// ezformtoken (CSRF protection): every POST of a signed-in session must carry the form token. ezformtoken writes
// it into the page head as <meta name="csrf-token" content="..."> and into each POST form as ezxform_token, and
// accepts it from a script in the X-CSRF-Token header (extension/ezformtoken/event/ezxformtoken.php: the header is
// read at line 158, the meta tags are written at line 262), as the kernel's own scripts send it
// (design/standard/javascript/graphql_playground.js and expdebugbar.js).
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const field = document.querySelector('input[name="ezxform_token"]');
    return field ? field.value : "";
}

function jsonHeaders() {
    const headers = { "Content-Type": "application/json", "Accept": "application/json" };
    const token = csrfToken();
    if (token) headers["X-CSRF-Token"] = token;
    return headers;
}

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
        headers: jsonHeaders(),
        body: JSON.stringify({ orderId }),
    });

    const data = await response.json();

    if (DEBUG) console.log("[stripe] confirm response status=", response.status, "data=", data);

    if (!response.ok || data.error_text || !data.content || !data.content.clientSecret) {
        showMessage(data.error_text || "Failed to initialize payment. Please try again.");
        return;
    }

    elements = stripe.elements({ clientSecret: data.content.clientSecret });
    const paymentElement = elements.create("payment", { layout: "tabs" });
    paymentElement.on("loaderror", (event) => {
        if (DEBUG) console.error("[stripe] Payment Element loaderror", event.error);
        showMessage(event.error?.message || "Payment form failed to load. Please refresh and try again.");
    });
    paymentElement.mount("#payment-element");
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
            headers: jsonHeaders(),
            // The server checks the intent against the order in the session; orderId
            // is only a fallback for a lost session and is checked against the user.
            body: JSON.stringify({ paymentIntentId, orderId }),
        });

        const data = await response.json();

        if (DEBUG) console.log("[stripe] verify response status=", response.status, "data=", data);

        if (!response.ok || data.error_text) {
            // A refused payment stays on screen: the buyer must read it.
            showMessage(data.error_text || "Payment verification failed. Please contact support.", true);
            setLoading(false);
            return;
        }

        window.location.replace("/shop/checkout");
        console.log("[stripe] verify SUCCESS — would redirect to /shop/checkout");
    } catch (err) {
        showMessage("Payment verification failed. Please contact support.");
        setLoading(false);
    }
}

// ---------------------------------------------------------------------------
// UI helpers
// ---------------------------------------------------------------------------
function showMessage(messageText, persistent) {
    const el = document.querySelector("#payment-message");
    el.classList.remove("hidden");
    el.textContent = messageText;
    if (persistent) {
        return;
    }
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
