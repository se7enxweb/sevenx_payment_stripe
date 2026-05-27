{* 
  Credit Card Form for eZ Authorize
  (could also be default form for other eZCurlGateway based class)
 
  You MUST have a post variable called 'validate' 
  if you wish eZCurlGateway to catch the form once it's been posted. 

  By default, it is the submit button.
*}

          <!-- hero starts -->
      <section class="pt-24 pb-12 sm:pb-16 lg:pb-20 sm:pt-28">
        <div class="lg:container mx-auto px-4">
          <!-- breadeadcrumb starts -->
          <div
            class="print:hidden flex flex-wrap items-center gap-y-1 text-gray-400 text-sm mb-6"
          >
            <a
              href="/shop/cart"
              class="text-gray-500 duration-300 hover:underline hover:text-gray-900"
            >
              Shopping cart
            </a>
            <span>
              <svg
                class="h-5 w-5 leading-none text-gray-300"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </span>
            <a
              href="/shop/userregister"
              class="text-gray-500 duration-300 hover:underline hover:text-gray-900"
            >
              Account information
            </a>
            <span>
              <svg
                class="h-5 w-5 leading-none text-gray-300"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </span>
            <span class="text-gray-500 duration-300"> Confirm Payment Details </span>
          </div>
          <!-- breadcrumb ends -->


          <!-- user info starts -->
          <div class="grid grid-cols-2 gap-6 mb-20">

<h2>Payment Information</h2>

<p>Please enter your credit card information</p>

{if ne($errors, 0)}
<b>There were errors on the form: </b><br />
<ul>
{foreach $errors as $errmsg}
<li>{$errmsg}</li>
{/foreach}
</ul>
{/if}


<link rel="stylesheet" href={"stylesheets/checkout.css"|ezdesign()} />
<script src="https://js.stripe.com/v3/"></script>
<script src={"javascript/stripe-checkout.js"|ezdesign()} defer></script>

    <!-- Display a payment form -->
    <form id="payment-form" data-order-id="{$order_id}" data-publishable-key="{$publishable_key|wash}">
      <div id="payment-element">
        <!--Stripe.js injects the Payment Element-->
      </div>
      <button id="submit">
        <div class="spinner hidden" id="spinner"></div>
        <span id="button-text">Pay now</span>
      </button>
      <div id="payment-message" class="hidden"></div>
    </form>

          </div>
          <!-- user info ends -->
</section>

{*
<form name="ezauthorizeForm" action={"/shop/checkout"|ezurl} method="post">

<input type=hidden name="orderid" value="{$order_id}" />
<input type=hidden name="amount" value="{$amount}" />
<input type=hidden name="currency" value="US" />
<input type="hidden" name="lang" value="en" />

<table border="0" cellpadding="4" cellspacing="4" summary="Enter Credit Card details">
<tr>
<td>Name on Card: </td>
<td><input type="text" size="32" name="CardName" value="{$cardname}" /></td>
</tr>
<tr>
<td>Card Type: </td>
<td>
<select name="CardType">
  <option value="visa">Visa</option>
  <option value="mastercard" {if eq($cardtype, 'mastercard')}selected{/if}>MasterCard</option>
  <option value="amercianexpress" {if eq($cardtype, 'americanexpress')}selected{/if}>American Express</option>
  <option value="discover" {if eq($cardtype, 'discover')}selected{/if}>Discover</option>
  <option value="amex" {if eq($cardtype, 'amex')}selected{/if}>Amex</option>
</select>
</tr>
<tr>
<td>Card Number: </td>
<td><input type="text" size="32" name="CardNumber" value="{$cardnumber}" /></td>
</tr>
<tr>
<td>Security Number: </td>
<td><input type="text" size="5" name="SecurityNumber" value="{$securitynumber}" /></td>
</tr>
<tr>
<td>Expiration Date: </td>
<td>
  <select name="ExpirationMonth">
    <option value="">Month</option>
    {for 1 to 12 as $month}
      {if $month|lt(10)}{set $month=concat('0',$month)}{/if}
    <option value="{$month}"{if eq($expirationmonth, $month)} selected="selected"{/if}>{$month}</option>
    {/for}
  </select>
  <select name="ExpirationYear">
    <option value="">Year</option>
    {def $curr_year=currentdate()|datetime( 'custom', '%y' )|int }
    {for $curr_year to $curr_year|sum(10) as $year}
      {if $year|lt(10)}{set $year=concat('0',$year)}{/if}
    <option value="{$year}"{if eq($expirationyear, $year)} selected="selected"{/if}>{$year}</option>
    {/for}
  </select>
</td>
</tr>
<tr>
<td colspan="2"><input class="defaultbutton" type="submit" name="validate" value="Submit" /><input class="defaultbutton" type="submit" name="CancelButton" value="{'Cancel'|i18n('design/standard/workflow')}" /></td>
</tr>
</table>
*}

{def $s_display_help = ezini( 'eZAuthorizeSettings', 'DisplayHelp', 'ezauthorize.ini' )}
{if eq( $s_display_help, true )}
<img src="http://usa.visa.com/img/cards/cardart_3Digit_code.gif" alt="" style="margin-top:10px;" />
{/if}

</form> 

</div>
</div>
</div>
