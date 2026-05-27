# eZCurlGateway — PHP 8 compatibility fork

`classes/ezcurlgateway.php` is a **modified copy** of the stock `eZCurlGateway` base class
originally shipped with the `ezcurlgateway` extension (v1.0.1, December 2005).

The stock version targets PHP 4 / early PHP 5 and cannot run on PHP 8.x without
modification. This fork modernises it for PHP 8.2+ while preserving the same
public interface so all subclasses (`Sevenx_Payment_StripeGateway`, etc.) are
unaffected.

---

## Changes vs stock ezcurlgateway v1.0.1

### PHP 4 → PHP 5+ constructor
| Stock | This fork |
|---|---|
| `function eZCurlGateway()` | `function __construct()` |

PHP 4-style constructors are removed in PHP 8.

### `useForm` property → method
| Stock | This fork |
|---|---|
| `var $useForm = true;` | `function useForm()` returning `true` in web, `false` in shell |

The method form allows shell jobs (`eZSys::isShellExecution()`) to skip
rendering the payment form, which avoids template errors in CLI cron contexts.

### Pass-by-reference calls removed
All `&$process` and `&$event` in `execute()` / `loadForm()` / `doCURL()` call
sites have been removed. Explicit call-time pass-by-reference (`call_func(&$var)`)
is a fatal error in PHP 8.

### Workflow status constants updated
| Stock | This fork |
|---|---|
| `EZ_WORKFLOW_TYPE_STATUS_FETCH_TEMPLATE_REPEAT` | `eZWorkflowEventType::STATUS_FETCH_TEMPLATE_REPEAT` |
| `EZ_WORKFLOW_TYPE_STATUS_ACCEPTED` | `eZWorkflowEventType::STATUS_ACCEPTED` |

The old `define()`-based constants were deprecated and the class constants are
the correct form for eZ Publish 4.7+.

### `include_once` removed
`include_once( 'kernel/shop/classes/ezpaymentgateway.php' )` is commented out.
The autoloader handles class loading in modern eZ Publish / Exponential CMS.

### `validateForm()` signature extended
| Stock | This fork |
|---|---|
| `validateForm()` | `validateForm( $process )` |

Subclasses need access to the process object to read order parameters.

### `storeHTTPInput()` stub added
A no-op `storeHTTPInput( &$process )` method is provided so subclasses can
override it to persist HTTP form input into the shop account handler without
the base class throwing a fatal "undefined method" error.

### Utility methods added
Three static helper methods used by the earlier Authorize.Net gateway
are retained for completeness and future use:

| Method | Purpose |
|---|---|
| `createDOMTreefromArray( $name, $array )` | Build a DOM tree from a flat associative array |
| `createArrayfromXML( $xmlDoc )` | Parse a simple XML document back to an array |
| `static gpgEncode( $value )` | Encrypt a value via eZGPG if available |
| `static gpgDecode( $value )` | Decrypt a value via eZGPG if available |

These are not called by the Stripe gateway and may be removed in a future
cleanup if no other gateway subclass uses them.

---

## Source lineage

| File | Version | Author |
|---|---|---|
| `extension/ezcurlgateway/classes/ezcurlgateway.php` | 1.0.1 — stock, unmodified | Dylan McDiarmid / Brookins Consulting |
| `extension/sevenx_payment_stripe/classes/ezcurlgateway.php` | PHP 8 fork | Graham Brookins / 7x |

The stock file is retained in `extension/ezcurlgateway/` for reference only.
Do **not** activate the `ezcurlgateway` extension alongside this one — the class
name `eZCurlGateway` would collide.
