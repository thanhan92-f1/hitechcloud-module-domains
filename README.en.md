# HiTechCloud Domains for HostBill

A HostBill domain module integrated with **HiTechCloud User API**, based on the provided Postman collection and HostBill core abstractions.

> Note: The available API appears to be more of a **user/client-facing API** than a pure registrar backend API. Because of that, some operations such as registration, transfer, and renewal are implemented in a **best-effort** way through order/user endpoints.

## Included Documentation

- `README.md`: Vietnamese documentation
- `ADMIN-GUIDE.md`: admin configuration guide
- `API-MAPPING.md`: method-to-endpoint API mapping
- `DEPLOYMENT-CHECKLIST.md`: deployment checklist
- `EXAMPLES.md`: usage and payload examples
- `ARCHITECTURE.md`: module architecture notes
- `TROUBLESHOOTING.md`: troubleshooting guide
- `ROADMAP.md`: proposed development roadmap
- `TEST-PLAN.md`: test planning document
- `HANDOVER.md`: internal handover notes
- `CHANGELOG.md`: change history
- `LICENSE`: copyright / usage information

## Supported Features

### Domain lifecycle
- Domain registration: `Register()`
- Domain renewal: `Renew()`
- Domain transfer: `Transfer()`

### Domain search
- Domain availability lookup: `lookupDomain()`
- Bulk lookup: `lookupBulkDomains()`
- Domain suggestions: `suggestDomains()`
- WHOIS lookup: `whoisDomain()`
- Best-effort premium domain detection from lookup responses

### Domain management
- Nameservers:
  - `getNameServers()`
  - `updateNameServers()`
- Glue records / child nameservers:
  - `getRegisterNameServers()`
  - `registerNameServer()`
  - `modifyNameServer()`
  - `deleteNameServer()`
  - currently implemented as safe stubs because no suitable API endpoint is documented
- EPP/Auth code:
  - `getEppCode()`
- Registrar lock:
  - `getRegistrarLock()`
  - `updateRegistrarLock()`
- ID protection / privacy:
  - `getIDProtection()`
  - `updateIDProtection()`
- Contact information:
  - `getContactInfo()`
  - `updateContactInfo()`
- Registry auto-renew:
  - `getRegistryAutorenew()`
  - `updateRegistryAutorenew()`
- Email forwarding:
  - `getEmailForwarding()`
  - `updateEmailForwarding()`
- DNS management:
  - `getDNSmanagement()`
  - `updateDNSManagement()`
  - `getDNSRecordTypes()`
- DNSSEC:
  - `widget_dnssec_form()`
  - `widget_dnssec_get()`
  - `widget_dnssec_set($data)`
- Domain listing:
  - `ListDomains()`
- Domain price import:
  - `getDomainPrices()`
- Connection test:
  - `testConnection()`

## Main File

- `class.hitechcloud_domains.php`: main HostBill module implementation

## Module Configuration

The module currently supports the following settings:

- `API URL`: base API URL, for example `https://api.example.com`
- `Username`: API login username/email
- `Password`: API login password
- `Access Token`: fixed token, if available
- `Refresh Token`: refresh token, if available
- `Use Bearer Token`: enable/disable Bearer token header
- `Verify SSL`: enable/disable SSL verification
- `Timeout`: HTTP timeout in seconds
- `Retry Count`: additional retries for transient failures
- `Retry Delay`: delay between retries in milliseconds
- If the API returns `Retry-After`, the module will prefer that delay for retryable responses
- `Debug Snapshots`: enable trimmed request/response snapshot logging for staging
- `Debug Snapshot Max Length`: maximum logged snapshot length
- `Default Payment Method`: required for register/transfer/renew order flow
- `Auto Login`: automatically login when no token is available

## Authentication Flow

The module attempts authentication in this order:
1. configured `Access Token`
2. configured `Refresh Token` via `POST /token`
3. configured `Username` + `Password` via `POST /login`

If `Use Bearer Token` is enabled, requests include:

- `Authorization: Bearer <token>`

## Main Endpoint Mapping

### Auth
- `POST /login`
- `POST /token`

### Lookup / Whois
- `POST /domain/lookup`
- `GET /whoislookup/:domain`
- `GET /whois/:domain`

### Domain management
- `GET /domain`
- `GET /domain/:id`
- `GET /domain/name/:name`
- `GET/PUT /domain/:id/ns`
- `GET /domain/:id/epp`
- `GET/PUT /domain/:id/reglock`
- `GET/PUT /domain/:id/idprotection`
- `GET/PUT /domain/:id/contact`
- `GET/PUT /domain/:id/autorenew`
- `POST /domain/:id/renew`
- `GET/PUT /domain/:id/emforwarding`

### DNS
- `GET /domain/:id/dns`
- `POST /domain/:id/dns`
- `PUT /domain/:id/dns/:index`
- `DELETE /domain/:id/dns/:index`
- `GET /domain/:id/dns/types`

### DNSSEC
- `GET /domain/:id/dnssec`
- `PUT /domain/:id/dnssec`
- `DELETE /domain/:id/dnssec/:key`
- `GET /domain/:id/dnssec/flags`

### Order flow
- `POST /domain/order`

### Price import
- `GET /domain/order`

## Installation

1. Place the module folder in the proper HostBill domain module directory.
2. Make sure the main file is:
   - `hitechcloud_domains/class.hitechcloud_domains.php`
3. Enable the module in HostBill admin.
4. Fill in the API configuration.
5. Test the connection before using it in production.

## Basic Usage Notes

### Register / Transfer
The module creates an order through `POST /domain/order` using values such as:
- domain name
- registration period
- `tld_id`
- `pay_method`
- nameservers
- EPP code for transfer
- contact IDs if available

### Renew
Renewal is performed through:
- `POST /domain/:id/renew`

### DNS update
The module supports:
- creating a record
- updating an existing record
- deleting a record

The payload is mapped from `dns_record`, for example:
- `index` or `record_id`
- `name`
- `type`
- `priority`
- `content`
- `delete`

### DNSSEC update
`widget_dnssec_set($data)` supports:
- adding a key with default `add` action
- deleting a key with `action=delete`

Best-effort supported fields:
- `key`
- `flags`
- `alg`
- `digest_type`
- `digest`
- `pubkey`
- `protocol`

## Current Limitations

- No clear glue record / child nameserver endpoints were found in the provided API docs, so `DomainModuleGluerecords` currently returns controlled unsupported errors
- DNSSEC normalization is best-effort because the Postman collection does not provide a complete response schema
- No dedicated premium-domain endpoint was found in the Postman collection, so premium support currently depends on lookup response fields if the backend returns them
- Retry currently applies only to transient request-layer failures such as timeouts, `408`, `429`, `500`, `502`, `503`, and `504`
- If the backend returns a `Retry-After` header, that value is used before the configured `Retry Delay`
- Debug snapshots should be enabled only in staging/debug because they may increase log volume
- `Register()`, `Transfer()`, and `Renew()` currently rely on user/order API flow rather than confirmed registrar-side provisioning flow
- `hideContacts()` and `hideNameServers()` currently return `false`
- Glue record support is still not implemented
- Price import is currently best-effort from the TLD listing returned by `GET /domain/order`

## Suggested Next Improvements

- Persist `remote_domain_id` into `extended` after successful resolution
- Normalize DNS and DNSSEC responses more strictly
- Add more detailed request/action logging
- Implement glue records if more API documentation becomes available
- Add premium-domain handling if the API supports it

## Quick Test Checklist

Recommended tests after configuration:
- domain lookup
- test registration
- transfer with EPP code
- renewal
- nameserver get/update
- lock on/off
- privacy on/off
- contact get/update
- DNS create/update/delete
- DNSSEC add/delete/list

## Note

If HiTechCloud later provides a dedicated registrar backend API, these methods should be updated first:
- `Register()`
- `Transfer()`
- `Renew()`

This would allow moving from an order-based workflow to a real registrar provisioning workflow.
