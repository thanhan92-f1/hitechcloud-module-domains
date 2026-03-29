# Changelog

All notable changes to this module should be documented in this file.

## [1.6.8] - 2026-03-29

### Added
- Added nameserver list normalization for array, keyed, nested, and raw string responses
- Added shared boolean response normalization for lock, privacy, and autorenew lookups across nested API schemas

### Changed
- Improved `getNameServers()` to return a cleaner sorted unique list from more response formats
- Improved `getRegistrarLock()`, `getIDProtection()`, and `getRegistryAutorenew()` to better read nested `data` and `details` payloads
- Updated module version from `1.6.7` to `1.6.8`

## [1.6.7] - 2026-03-29

### Added
- Added best-effort WHOIS normalization for common structured fields such as `domain`, `registrar`, `created_at`, `updated_at`, `expires_at`, `statuses`, `nameservers`, and `contacts`
- Added raw WHOIS text parsing for common line-based responses from `/whoislookup/:domain` and `/whois/:domain`

### Changed
- Improved `whoisDomain()` to always return a more HostBill-friendly normalized structure when possible
- Updated module version from `1.6.6` to `1.6.7`

## [1.6.6] - 2026-03-29

### Added
- Added DNS update payload normalization for alternate input keys such as `host`, `subdomain`, `record_type`, `target`, `value`, `address`, and `ttl`

### Changed
- Improved `updateDNSManagement()` to normalize incoming `dns_record` data before create/update/delete requests
- Improved DNS update payload building to include `ttl` and better reuse `record_id|id|index`
- Updated module version from `1.6.5` to `1.6.6`

## [1.6.5] - 2026-03-29

### Added
- Added best-effort DNS record normalization across common alternate field names
- Added DNS record type normalization into a unique sorted list
- Added DNSSEC entry normalization and `key_count` summary in `widget_dnssec_get()`

### Changed
- Improved `getDNSmanagement()` to return more consistent DNS record rows
- Improved `getDNSRecordTypes()` to return normalized type names from mixed API schemas
- Improved DNSSEC key/flag normalization for more stable HostBill-side rendering
- Updated module version from `1.6.4` to `1.6.5`

## [1.6.4] - 2026-03-29

### Added
- Added best-effort normalization for contact responses across common alternate field names
- Added best-effort normalization for email forwarding responses across common alternate field names
- Added richer `testConnection()` success logging with detected auth mode and domain count summary

### Changed
- Improved `getContactInfo()` to return more consistent HostBill-friendly contact structures
- Improved `getEmailForwarding()` to normalize `from`, `to`, and forwarding list fields when available
- Updated module version from `1.6.3` to `1.6.4`

## [1.6.3] - 2026-03-29

### Added
- Added suggestion TLD generation from cached `GET /domain/order` data with configurable limit support
- Added best-effort normalization for `ListDomains()` fields such as `name`, `status`, `expires`, and `autorenew`

### Changed
- Improved `suggestDomains()` to prioritize the requested TLD and enrich candidates from available pricing/TLD metadata
- Improved `ListDomains()` to return more consistent HostBill-friendly domain rows when the API uses alternate field names
- Updated module version from `1.6.2` to `1.6.3`

## [1.6.2] - 2026-03-29

### Added
- Added in-request pricing cache for `GET /domain/order` via `pricingCache`
- Added normalized pricing summaries: `available_periods`, `register_periods`, `transfer_periods`, `renew_periods`
- Added per-operation support flags: `supports_register`, `supports_transfer`, `supports_renew`

### Changed
- Improved `getDomainPrices()` to reuse cached TLD pricing metadata during the same request lifecycle
- Improved price normalization with sorted numeric periods and per-period availability flags
- Updated module version from `1.6.0` to `1.6.2`

## [1.6.1] - 2026-03-29

### Added
- Added optional debug snapshot logging for request/response data
- Added `Debug Snapshots` configuration
- Added `Debug Snapshot Max Length` configuration

### Changed
- API request flow can now record trimmed response snapshots for staging diagnostics

## [1.6.0] - 2026-03-29

### Added
- Added `DomainModuleGluerecords` support as safe unsupported stubs
- Added controlled error/logging for glue-record actions when no API endpoint is available

### Changed
- Updated module version from `1.5.0` to `1.6.0`

## [1.5.0] - 2026-03-29

### Added
- Added best-effort `DomainPriceImport` support via `getDomainPrices()`
- Added TLD/period pricing normalization from `GET /domain/order`

### Changed
- Updated module version from `1.4.0` to `1.5.0`

## [1.4.1] - 2026-03-29

### Added
- Added `Retry-After` header support for retryable HTTP responses

### Changed
- Retry delay now prefers backend-provided `Retry-After` when available

## [1.4.0] - 2026-03-29

### Added
- Added configurable retry handling in the shared request layer
- Added `Retry Count` configuration
- Added `Retry Delay` configuration
- Added retry logging for transient HTTP/cURL failures

### Changed
- Updated module version from `1.3.0` to `1.4.0`
- Added linear backoff for retryable timeout and gateway-related failures

## [1.3.0] - 2026-03-29

### Added
- Added best-effort `DomainPremiumInterface` support
- Added premium domain detection from lookup responses
- Added premium order field mapping for `premium`, `premium_price`, and `currency`

### Changed
- Updated module version from `1.2.0` to `1.3.0`
- Clarified current lack of glue-record and dedicated premium endpoints in documentation

## [1.2.0] - 2026-03-29

### Added
- Added persistent runtime/storage helper for resolved `remote_domain_id`
- Added structured success logging helper for common management actions
- Added lifecycle success logging for register, transfer, and renew flows
- Added in-memory domain detail cache by ID and by name

### Changed
- Improved remote domain ID resolution to persist resolved values into `extended`
- Added action logging for nameserver, lock, privacy, contact, autorenew, email forwarding, DNS, and DNSSEC updates
- Improved normalization for contact, DNS, DNS record type, and domain list responses
- Reused cached domain details for selected lookup flows
- Added cache invalidation after remote update actions
- Updated module version from `1.1.0` to `1.2.0`

## [1.1.0] - 2026-03-29

### Added
- Added `DomainModuleDNSSEC` support
- Added `widget_dnssec_form()`
- Added `widget_dnssec_get()`
- Added `widget_dnssec_set($data)`
- Added `testConnection()`
- Added `getDNSRecordTypes()`
- Added direct lookup by `/domain/name/:name` in remote domain ID resolution

### Changed
- Improved `getIDProtection()` to query the API when possible
- Improved remote domain ID resolution with additional fallback logic
- Updated module version from `1.0.0` to `1.1.0`

## [1.0.0] - 2026-03-29

### Added
- Initial `HiTechCloud_Domains` HostBill module implementation
- Added support for:
  - register / renew / transfer
  - lookup / bulk lookup / suggestions / whois
  - nameservers
  - EPP code retrieval
  - registrar lock
  - ID protection
  - contacts
  - registry auto-renew
  - email forwarding
  - DNS management
  - domain listing
- Added token-based and login-based authentication flow
- Added basic API request abstraction using cURL

## Notes
- Current implementation is best-effort and based on the provided HiTechCloud User API Postman collection
- Register, transfer, and renew currently rely on order/user endpoints rather than a verified registrar provisioning API
