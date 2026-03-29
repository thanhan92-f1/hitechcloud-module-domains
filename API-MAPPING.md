# API Mapping

Tài liệu mapping giữa method trong module `HiTechCloud_Domains` và các endpoint API đang được sử dụng.

## 1. Authentication

### `ensureAuthenticated()`
- `POST /token`
- `POST /login`

### `getAccessToken()`
- dùng token cấu hình sẵn hoặc token runtime cache

## 2. Domain lifecycle

### `Register()`
- gọi nội bộ: `createDomainOrder('register')`
- endpoint: `POST /domain/order`

### `Transfer()`
- gọi nội bộ: `createDomainOrder('transfer')`
- endpoint: `POST /domain/order`

### `Renew()`
- endpoint: `POST /domain/:id/renew`

### `getDomainPrices()`
- endpoint: `GET /domain/order`
- dùng để lấy danh sách TLD và periods phục vụ import giá best-effort
- dữ liệu `GET /domain/order` được cache trong runtime request qua `pricingCache`
- kết quả normalize thêm:
  - `available_periods`
  - `register_periods`
  - `transfer_periods`
  - `renew_periods`
  - `supports_register`
  - `supports_transfer`
  - `supports_renew`

## 3. Lookup / Whois

### `lookupDomain($sld, $tld)`
- endpoint: `POST /domain/lookup`
- query: `name=<domain>`

### `lookupBulkDomains($sld, $tlds)`
- lặp qua nhiều TLD
- dùng lại `lookupDomain()`

### `suggestDomains($sld, $tld)`
- không gọi API riêng
- sinh suggestion nội bộ theo danh sách TLD mặc định
- ưu tiên TLD được yêu cầu
- có thể mở rộng danh sách suggestion từ dữ liệu `GET /domain/order` đã cache

### `whoisDomain($sld, $tld)`
- ưu tiên: `GET /whoislookup/:domain`
- fallback: `GET /whois/:domain`

## 4. Domain information

### `ListDomains()`
- endpoint: `GET /domain`
- normalize best-effort các key thường gặp như `name|domain|fqdn`, `status|domainstatus|state`, `expires|expiry|expiration|expire_date|next_due`, `autorenew|auto_renew`

### `resolveRemoteDomainId()`
- ưu tiên dữ liệu local/extended
- nếu cần gọi:
  - `GET /domain/name/:name`
  - fallback: `GET /domain`

### `getDomainDetailsByName()`
- endpoint: `GET /domain/name/:name`

### `getDomainDetailsById()`
- endpoint: `GET /domain/:id`

## 5. Nameservers

### `getNameServers()`
- ưu tiên cache/details nếu có
- endpoint fallback: `GET /domain/:id/ns`

### `updateNameServers()`
- endpoint: `PUT /domain/:id/ns`
- query: `nameservers=ns1,ns2,...`

### Glue records / child nameservers
- `getRegisterNameServers()`
- `registerNameServer()`
- `modifyNameServer()`
- `deleteNameServer()`
- hiện chưa map tới endpoint nào vì Postman chưa có API glue records

## 6. EPP / Lock / Privacy

### `getEppCode()`
- endpoint: `GET /domain/:id/epp`

### `getRegistrarLock()`
- ưu tiên cache/details nếu có
- endpoint fallback: `GET /domain/:id/reglock`

### `updateRegistrarLock()`
- endpoint: `PUT /domain/:id/reglock`
- query: `switch=true|false`

### `getIDProtection()`
- ưu tiên local extended
- ưu tiên cache/details nếu có
- endpoint fallback: `GET /domain/:id/idprotection`

### `updateIDProtection()`
- endpoint: `PUT /domain/:id/idprotection`
- query: `switch=true|false`

## 7. Contacts

### `getContactInfo()`
- ưu tiên cache/details nếu có
- endpoint fallback: `GET /domain/:id/contact`
- normalize best-effort các field contact thường gặp như `first_name`, `last_name`, `company`, `postalcode`, `phone_number`

### `updateContactInfo()`
- endpoint: `PUT /domain/:id/contact`
- query/payload: `contact_info=<json>`

## 8. Registry auto renew

### `getRegistryAutorenew()`
- ưu tiên cache/details nếu có
- endpoint fallback: `GET /domain/:id/autorenew`

### `updateRegistryAutorenew()`
- endpoint: `PUT /domain/:id/autorenew`
- query: `autorenew=true|false`

## 9. Email forwarding

### `getEmailForwarding()`
- endpoint: `GET /domain/:id/emforwarding`
- normalize thêm các key thường gặp như `source|alias|username`, `destination|target|email`, `forwardings|forwards|items|data`

### `updateEmailForwarding()`
- endpoint: `PUT /domain/:id/emforwarding`
- fields thường dùng:
  - `from`
  - `to`

## 10. DNS

### `getDNSmanagement()`
- endpoint: `GET /domain/:id/dns`
- normalize best-effort các key record thường gặp như `record_id|index`, `host|subdomain`, `record_type`, `value|target|address`

### `updateDNSManagement()`
- create: `POST /domain/:id/dns`
- update: `PUT /domain/:id/dns/:index`
- delete: `DELETE /domain/:id/dns/:index`

### `getDNSRecordTypes()`
- endpoint: `GET /domain/:id/dns/types`
- chuẩn hóa kết quả về danh sách type duy nhất và sắp xếp tự nhiên

## 11. DNSSEC

### `widget_dnssec_get()`
- `GET /domain/:id/dnssec`
- `GET /domain/:id/dnssec/flags`
- normalize best-effort key/flag response và trả thêm `key_count`

### `widget_dnssec_set($data)`
- add: `PUT /domain/:id/dnssec`
- delete: `DELETE /domain/:id/dnssec/:key`

### `widget_dnssec_form()`
- không gọi API riêng
- build schema field nội bộ cho UI

## 12. Connection test

### `testConnection()`
- endpoint: `GET /domain`
- log thêm chẩn đoán thành công theo `auth_mode` và số lượng domain nếu response có list

## 13. Cache behavior

### Dữ liệu cache hiện có
- `domainCache['by_id']`
- `domainCache['by_name']`

### Các flow dùng cache
- resolve remote domain ID
- id protection
- registrar lock
- nameservers
- contact info
- registry auto renew

### Các flow invalidate cache sau update
- nameservers
- registrar lock
- id protection
- contact info
- registry auto renew
- email forwarding
- DNS
- DNSSEC

## 14. Price import notes

- Dữ liệu giá hiện được suy ra từ danh sách `tlds[].periods[]`
- Các key đang map best-effort:
  - `register`
  - `transfer`
  - `renew`
  - `currency`
- Các kỳ hạn được chuẩn hóa và sắp xếp số học để giảm sai khác khi import vào HostBill
- Mỗi kỳ hạn có thể kèm cờ:
  - `register_available`
  - `transfer_available`
  - `renew_available`
- Nếu backend không trả `renew`, giá renew sẽ để `null`

## 15. Lưu ý

- Nhiều endpoint đang được dùng theo kiểu best-effort dựa trên Postman hiện có
- `Register()`, `Transfer()`, `Renew()` chưa chắc là registrar provisioning flow thật
- Glue record methods hiện log lỗi có kiểm soát và trả `false`
- Nếu API chính thức thay đổi schema, cần cập nhật lại các method normalize/parsing
