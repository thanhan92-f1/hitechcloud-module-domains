# HiTechCloud Domains for HostBill

Module domain cho HostBill, tích hợp với **HiTechCloud User API** dựa trên các endpoint có trong Postman collection được cung cấp.

> Lưu ý: API hiện có dấu hiệu là **User API / client-facing API** của HostBill hơn là registrar backend API thuần. Vì vậy một số chức năng như đăng ký, transfer, renew đang được triển khai theo hướng **best-effort** qua order/user endpoints.

## Tài liệu đi kèm

- `README.en.md`: tài liệu tiếng Anh
- `ADMIN-GUIDE.md`: hướng dẫn cấu hình cho admin
- `API-MAPPING.md`: mapping giữa method module và endpoint API
- `DEPLOYMENT-CHECKLIST.md`: checklist triển khai
- `EXAMPLES.md`: ví dụ input/output và API mapping
- `ARCHITECTURE.md`: mô tả kiến trúc module
- `TROUBLESHOOTING.md`: hướng dẫn xử lý sự cố
- `ROADMAP.md`: lộ trình phát triển đề xuất
- `TEST-PLAN.md`: kế hoạch kiểm thử
- `HANDOVER.md`: tài liệu bàn giao nội bộ
- `CHANGELOG.md`: lịch sử thay đổi
- `LICENSE`: thông tin bản quyền / sử dụng

## Tính năng đã hỗ trợ

### Domain lifecycle
- Đăng ký domain: `Register()`
- Gia hạn domain: `Renew()`
- Transfer domain: `Transfer()`

### Domain search
- Kiểm tra domain: `lookupDomain()`
- Kiểm tra hàng loạt: `lookupBulkDomains()`
- Gợi ý domain: `suggestDomains()`
- `suggestDomains()` ưu tiên TLD được yêu cầu và có thể tận dụng danh sách TLD khả dụng từ `GET /domain/order`
- WHOIS: `whoisDomain()`
- Premium domain detection best-effort từ response lookup

### Domain management
- Quản lý nameserver:
  - `getNameServers()`
  - `updateNameServers()`
- Glue records / child nameserver:
  - `getRegisterNameServers()`
  - `registerNameServer()`
  - `modifyNameServer()`
  - `deleteNameServer()`
  - hiện là stub an toàn do chưa có endpoint API phù hợp
- Lấy EPP/Auth code: `getEppCode()`
- Registrar lock:
  - `getRegistrarLock()`
  - `updateRegistrarLock()`
- ID Protection / Privacy:
  - `getIDProtection()`
  - `updateIDProtection()`
- Contact info:
  - `getContactInfo()`
  - `updateContactInfo()`
  - normalize best-effort các field contact phổ biến như tên, email, địa chỉ, phone, company
- Registry auto renew:
  - `getRegistryAutorenew()`
  - `updateRegistryAutorenew()`
- Email forwarding:
  - `getEmailForwarding()`
  - `updateEmailForwarding()`
  - normalize thêm các key phổ biến như `from`, `to`, `forwardings`
- DNS records:
  - `getDNSmanagement()`
  - `updateDNSManagement()`
  - `getDNSRecordTypes()`
  - normalize best-effort các field record phổ biến như `id`, `name`, `type`, `content`, `priority`, `ttl`
  - danh sách record types được chuẩn hóa và sắp xếp
- DNSSEC:
  - `widget_dnssec_form()`
  - `widget_dnssec_get()`
  - `widget_dnssec_set($data)`
  - normalize best-effort key/flag và trả thêm `key_count`
- Listing domains:
  - `ListDomains()`
  - normalize thêm các field phổ biến như `name`, `status`, `expires`, `autorenew` nếu API dùng key khác
- Import bảng giá domain:
  - `getDomainPrices()`
  - trả thêm `available_periods`, `register_periods`, `transfer_periods`, `renew_periods`
  - trả thêm cờ hỗ trợ `supports_register`, `supports_transfer`, `supports_renew`
- Test kết nối:
  - `testConnection()`
  - ghi log chẩn đoán thành công với `auth_mode` và số domain đọc được nếu có

## File chính

- `class.hitechcloud_domains.php`: module chính của HostBill

## Cấu hình module

Trong file `class.hitechcloud_domains.php`, module hỗ trợ các cấu hình sau:

- `API URL`: URL gốc của API, ví dụ `https://api.example.com`
- `Username`: tài khoản đăng nhập API
- `Password`: mật khẩu API
- `Access Token`: token cố định nếu có
- `Refresh Token`: token làm mới nếu có
- `Use Bearer Token`: bật/tắt gửi header Bearer token
- `Verify SSL`: bật/tắt verify SSL
- `Timeout`: timeout request HTTP
- `Retry Count`: số lần retry thêm cho lỗi tạm thời
- `Retry Delay`: thời gian chờ giữa các lần retry (ms)
- Nếu API trả `Retry-After`, module sẽ ưu tiên thời gian này cho các response retryable
- `Debug Snapshots`: bật log snapshot request/response rút gọn cho staging
- `Debug Snapshot Max Length`: độ dài tối đa của snapshot được ghi log
- `Default Payment Method`: bắt buộc khi dùng flow tạo order cho register/transfer/renew
- `Auto Login`: tự login nếu chưa có token

## Cách hoạt động xác thực

Module ưu tiên xác thực theo thứ tự:
1. `Access Token` cấu hình sẵn
2. `Refresh Token` qua endpoint `/token`
3. `Username` + `Password` qua endpoint `/login`

Nếu bật `Use Bearer Token`, token sẽ được gửi qua header:

- `Authorization: Bearer <token>`

## Mapping endpoint chính

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
- Module cache dữ liệu pricing/TLD trong cùng request để tránh gọi lặp lại không cần thiết

## Cài đặt

1. Đặt thư mục module vào đúng thư mục module domain của HostBill.
2. Đảm bảo file chính là:
   - `hitechcloud_domains/class.hitechcloud_domains.php`
3. Kích hoạt module trong phần quản trị HostBill.
4. Nhập cấu hình API tương ứng.
5. Test kết nối trước khi dùng thật.

## Cách dùng cơ bản

### Register / Transfer
Module tạo order qua endpoint `/domain/order` với các dữ liệu như:
- tên domain
- số năm
- `tld_id`
- `pay_method`
- nameserver
- EPP code với transfer
- contact IDs nếu có

### Renew
Gia hạn qua:
- `POST /domain/:id/renew`

### DNS update
Module hỗ trợ 3 kiểu thao tác:
- tạo record mới
- cập nhật record cũ
- xóa record

Payload được map từ `dns_record`, ví dụ:
- `index` hoặc `record_id`
- `name`
- `type`
- `priority`
- `content`
- `delete`

### DNSSEC update
`widget_dnssec_set($data)` hỗ trợ:
- thêm key với action mặc định `add`
- xóa key với `action=delete`

Các field hỗ trợ best-effort:
- `key`
- `flags`
- `alg`
- `digest_type`
- `digest`
- `pubkey`
- `protocol`

## Giới hạn hiện tại

- Chưa có tài liệu endpoint rõ ràng cho **glue records / child nameserver**, nên `DomainModuleGluerecords` hiện chỉ là stub trả lỗi có kiểm soát
- Chưa có schema response đầy đủ cho DNSSEC trong Postman, nên phần normalize đang ở mức best-effort
- Chưa có endpoint premium domain riêng trong Postman, nên premium hiện chỉ được detect/mapping theo response lookup nếu backend trả về
- Retry hiện chỉ áp dụng cho lỗi tạm thời ở request layer như timeout, `408`, `429`, `500`, `502`, `503`, `504`
- Nếu backend trả header `Retry-After`, module sẽ dùng giá trị đó trước `Retry Delay`
- Debug snapshot chỉ nên bật ở staging/debug vì có thể làm log lớn hơn
- `Register()`, `Transfer()`, `Renew()` hiện dựa trên user/order API, chưa chắc tương đương registrar provisioning thực tế
- `hideContacts()` và `hideNameServers()` đang trả về `false`
- Chưa hỗ trợ glue records do chưa có endpoint API phù hợp
- Import giá hiện là best-effort từ danh sách TLD trả về bởi `GET /domain/order`
- `getDomainPrices()` hiện chuẩn hóa thêm danh sách kỳ hạn khả dụng theo từng tác vụ register/transfer/renew để HostBill dễ map hơn
- `suggestDomains()` hiện không gọi API suggestion riêng, nhưng có thể sinh gợi ý tốt hơn từ TLD khả dụng đã cache

## Gợi ý nâng cấp tiếp theo

- Lưu `remote_domain_id` vào `extended` sau khi resolve thành công
- Chuẩn hóa response DNS và DNSSEC chi tiết hơn
- Bổ sung log chi tiết cho từng request/action
- Hỗ trợ glue records nếu có thêm API docs
- Thêm xử lý premium domain nếu API hỗ trợ

## Kiểm tra nhanh

Các điểm nên test sau khi cấu hình:
- lookup domain
- register domain test
- transfer với EPP code
- renew domain
- nameserver get/update
- lock on/off
- privacy on/off
- contact get/update
- DNS create/update/delete
- DNSSEC add/delete/list

## Ghi chú

Nếu HiTechCloud cung cấp thêm registrar API backend chuyên dụng, nên cập nhật lại các hàm:
- `Register()`
- `Transfer()`
- `Renew()`

để chuyển từ order-based flow sang provisioning flow chuẩn registrar.

---

Nếu cần, có thể bổ sung tiếp:
- README tiếng Anh
- file changelog
- hướng dẫn cài trực tiếp theo cấu trúc module HostBill thực tế
- tài liệu mapping input/output cho từng method
