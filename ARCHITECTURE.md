# Architecture

Tài liệu mô tả kiến trúc hiện tại của module `hitechcloud_domains`.

## 1. Mục tiêu

Module này đóng vai trò cầu nối giữa HostBill domain module system và HiTechCloud User API để xử lý:
- tra cứu domain
- đăng ký / transfer / gia hạn
- nameserver
- EPP code
- lock / privacy
- contacts
- auto renew
- forwarding
- DNS
- DNSSEC

## 2. Thành phần chính

### `class.hitechcloud_domains.php`
Đây là entrypoint chính của module.

Class:
- `HiTechCloud_Domains`

Kế thừa từ:
- `DomainModule`

Implements:
- `DomainLookupInterface`
- `DomainWhoisInterface`
- `DomainBulkLookupInterface`
- `DomainSuggestionsInterface`
- `DomainHideFormInterface`
- `DomainPremiumInterface`
- `DomainPriceImport`
- `DomainModuleNameservers`
- `DomainModuleGluerecords`
- `DomainModuleAuth`
- `DomainModuleLock`
- `DomainModulePrivacy`
- `DomainModuleContacts`
- `DomainModuleRegistryAutorenew`
- `DomainModuleForwarding`
- `DomainModuleDNS`
- `DomainModuleDNSSEC`
- `DomainModuleListing`

## 3. Kiến trúc xử lý

Luồng xử lý tổng quát:

1. HostBill gọi method của module
2. Module đọc dữ liệu từ thuộc tính nội bộ như:
   - `$this->name`
   - `$this->domain_id`
   - `$this->details`
   - `$this->options`
   - `$this->domain_contacts`
3. Module resolve remote domain ID nếu cần
4. Module xác thực với API
5. Module gửi request HTTP qua cURL
6. Module parse response và map về format HostBill cần dùng
7. Module ghi log hoặc addError nếu có lỗi

## 4. Nhóm chức năng

### 4.1 Domain lifecycle
Các method:
- `Register()`
- `Transfer()`
- `Renew()`

Đặc điểm:
- `Register()` và `Transfer()` dùng `createDomainOrder()`
- `Renew()` gọi trực tiếp endpoint renew theo domain ID
- Hiện tại workflow mang tính best-effort theo order/user API

### 4.2 Lookup / Whois
Các method:
- `lookupDomain()`
- `lookupBulkDomains()`
- `suggestDomains()`
- `whoisDomain()`

Đặc điểm:
- lookup gọi `POST /domain/lookup`
- whois ưu tiên `/whoislookup/:domain`, fallback sang `/whois/:domain`

### 4.3 Domain management
Bao gồm:
- nameservers
- glue records / child nameservers (stub)
- EPP code
- registrar lock
- ID protection
- contacts
- registry auto renew
- email forwarding
- DNS
- DNSSEC
- import bảng giá domain

Tất cả đều đi qua lớp request chung.

## 5. Request layer

Method lõi:
- `request($method, $path, array $query = [], array $headers = [], $auth = true)`

Nhiệm vụ:
- chuẩn hóa URL
- build query string
- thêm header mặc định
- gắn Bearer token nếu có
- thực hiện request bằng cURL
- tách header/body
- decode JSON nếu có thể
- phát hiện lỗi HTTP/API

### Ưu điểm
- Tập trung toàn bộ HTTP logic tại một chỗ
- Dễ mở rộng logging/retry sau này
- Dễ chuẩn hóa error handling
- Có thể bật debug snapshot rút gọn để quan sát request/response khi test staging

### Hạn chế
- Chưa có structured logging chi tiết
- Chưa có body POST kiểu JSON, hiện chủ yếu gửi qua query string theo Postman hiện có

### Retry/backoff hiện có
- Có retry cấu hình được qua `Retry Count`
- Có delay giữa các lần retry qua `Retry Delay`
- Retry áp dụng cho lỗi tạm thời như timeout, `408`, `429`, `500`, `502`, `503`, `504`
- Có parse `Retry-After` header từ backend và ưu tiên dùng giá trị đó nếu có

## 6. Authentication layer

Method chính:
- `ensureAuthenticated()`
- `getAccessToken()`

Thứ tự xác thực:
1. Access Token cấu hình sẵn
2. Refresh Token qua `/token`
3. Username + Password qua `/login`

Token runtime được giữ trong:
- `$this->tokenCache`

## 7. Domain ID resolution

Method chính:
- `resolveRemoteDomainId()`

Thứ tự resolve:
1. `$this->options['remote_domain_id']`
2. `$this->details['extended']['remote_domain_id']`
3. `$this->domain_id`
4. `GET /domain/name/:name`
5. `GET /domain` rồi dò theo tên domain

## 7.1 Domain details cache

Module hiện có cache in-memory trong vòng đời request PHP:
- `by_id`
- `by_name`

Ngoài ra module còn có:
- `pricingCache`

Helper liên quan:
- `getDomainDetailsByName()`
- `getDomainDetailsById()`
- `getDomainDetailValue()`
- `cacheDomainDetailsFromResponse()`
- `forgetDomainCache()`

Mục đích:
- giảm gọi lặp lại tới endpoint domain details
- tái sử dụng dữ liệu đã resolve trước đó
- tăng hiệu quả cho các flow đọc trạng thái domain
- giảm gọi lặp lại tới `GET /domain/order` khi import hoặc normalize bảng giá

Cache hiện được invalidate sau các thao tác update quan trọng như:
- nameservers
- contacts
- autorenew
- email forwarding
- DNS
- DNSSEC

## 8. Dữ liệu đầu vào chính

Module đang phụ thuộc vào các nguồn dữ liệu HostBill như:
- `$this->name`
- `$this->period`
- `$this->options`
- `$this->details`
- `$this->client_data`
- `$this->domain_contacts`
- `$this->tld_id`

## 9. Error handling

Method hỗ trợ:
- `handleApiFailure()`
- `addError()` từ lớp cha

Chiến lược hiện tại:
- nếu HTTP status >= 400 => lỗi
- nếu response JSON có `success = false` => lỗi
- ưu tiên extract message từ các key:
  - `error`
  - `message`
  - `msg`
  - `detail`
  - `description`

## 10. DNS và DNSSEC

### DNS
- list records
- create record
- update record
- delete record
- get supported record types

### DNSSEC
- lấy danh sách keys
- lấy flags
- thêm key
- xóa key
- normalize payload/response theo kiểu best-effort

## 11. Điểm mở rộng phù hợp

Các điểm nên mở rộng tiếp:
- lưu persistent `remote_domain_id` vào extended data
- structured logging cho request/response
- retry/backoff cho lỗi tạm thời
- chuẩn hóa mapping response cho từng endpoint
- hỗ trợ glue records nếu có tài liệu API
- hỗ trợ premium domains
- hỗ trợ import bảng giá domain

## 11.1 Price import normalization

Flow `getDomainPrices()` hiện:
- gọi `GET /domain/order` qua helper cache nội bộ
- chuẩn hóa `tlds[].periods[]` thành các mức giá register/transfer/renew
- tổng hợp thêm:
  - `available_periods`
  - `register_periods`
  - `transfer_periods`
  - `renew_periods`
  - `supports_register`
  - `supports_transfer`
  - `supports_renew`
- sắp xếp kỳ hạn theo thứ tự số học để HostBill import ổn định hơn

## 12. Rủi ro kiến trúc hiện tại

- API nguồn có thể không phải registrar provisioning API thực sự
- Một số flow domain lifecycle có thể chỉ tạo order thay vì xử lý registrar action trực tiếp
- Schema response trong Postman chưa đầy đủ cho mọi endpoint
- Có thể tồn tại khác biệt giữa `domain_id` local của HostBill và remote ID phía API

## 13. Kết luận

Kiến trúc hiện tại phù hợp với mục tiêu:
- triển khai nhanh
- dễ đọc
- dễ mở rộng tiếp
- tận dụng tối đa API docs hiện có

Tuy nhiên, để vận hành production ổn định lâu dài, nên bổ sung thêm:
- tài liệu registrar API backend chính thức
- logging tốt hơn
- persistence cho remote mapping
- kiểm thử thực tế với toàn bộ flow domain lifecycle
