{* Name. *}
<div class="block">
<label>{'Name'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}:</label>
{let customer_user=fetch( content, object, hash( object_id, $order.user_id ) )}
<a href={$customer_user.main_node.url_alias|ezurl}>{$order.account_information.first_name}&nbsp;{$order.account_information.last_name}</a>
{/let}
</div>

{* Email. *}
<div class="block">
<label>{'E-mail'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}:</label>
<a href="mailto:{$order.account_information.email}">{$order.account_information.email}</a>
</div>

{* Address. *}
<div class="block">

<fieldset>
<legend>{'Address'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</legend>
{if ne($order.account_information.shipping,1)}
<table class="list" cellspacing="0">
<tr>
<td><b>Billingaddress</b></td>
<td><b>Shippingaddress</b></td>
</tr>
<tr>
<td>
{/if}
<table class="list" cellspacing="0">
<tr>
    <td>{'First Name'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.first_name}</td>
</tr>
<tr>
    <td>MI</td>
    <td>{$order.account_information.mi}</td>
</tr>
<tr>
    <td>{'Last Name'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.last_name}</td>
</tr>
<tr>
    <td>{'Address 1'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.address1}</td>
</tr>
<tr>
    <td>{'Address 2'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.address2}</td>
</tr>
<tr>
    <td>{'City'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.city}</td>
</tr>
<tr>
    <td>{'State'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.state}</td>
</tr>
<tr>
    <td>{'Zip Code'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.zip}</td>
</tr>
<tr>
    <td>{'Country'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.country}</td>
</tr>
<tr>
    <td>{'Phone'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.phone}</td>
</tr>
<tr>
    <td>{'Shipping Type'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>
        {if eq($order.account_information.shippingtype,1)}
            Next Day Service
        {elseif eq($order.account_information.shippingtype,2)}
            2nd Day Service
        {else}Standard Shipping
        {/if}
    </td>
</tr>
</table>
{if ne($order.account_information.shipping,1)}
</td>
<td>
<table class="list" cellspacing="0">
<tr>
    <td>{'First Name'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_first_name}</td>
</tr>
<tr>
    <td>MI</td>
    <td>{$order.account_information.s_mi}</td>
</tr>
<tr>
    <td>{'Last Name'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_last_name}</td>
</tr>
<tr>
    <td>{'Address 1'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_address1}</td>
</tr>
<tr>
    <td>{'Address 2'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_address2}</td>
</tr>
<tr>
    <td>{'City'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_city}</td>
</tr>
<tr>
    <td>{'State'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_state}</td>
</tr>
<tr>
    <td>{'Zip Code'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_zip}</td>
</tr>
<tr>
    <td>{'Country'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_country}</td>
</tr>
<tr>
    <td>{'Phone'|i18n( 'design/admin/shop/accounthandlers/html/ez' )}</td>
    <td>{$order.account_information.s_phone}</td>
</tr>
<tr>
    <td>Email</td>
    <td>{$order.account_information.s_email}</td>
</tr>
</table>
</td>
</tr>
</table>
{/if}
</fieldset>

{* The Authorize.net card details (eZAuthorize + eZGPG) were shown here; the gateway was retired on 2026-11-16 and its
   extension, with the ezgpg operators and ezauthorize.ini, is gone. *}

</div>
