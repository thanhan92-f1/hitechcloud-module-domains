# Changelog

All notable changes to this module should be documented in this file.

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
