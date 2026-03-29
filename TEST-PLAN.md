# Test Plan

Kế hoạch kiểm thử cho module `hitechcloud_domains`.

## 1. Mục tiêu

Đảm bảo các chức năng chính của module hoạt động đúng với HostBill và HiTechCloud API trong môi trường test/staging trước khi production.

## 2. Phạm vi kiểm thử

### Bao gồm
- xác thực API
- lookup / whois
- register / transfer / renew
- nameservers
- EPP code
- registrar lock
- ID protection
- contacts
- auto renew
- email forwarding
- DNS
- DNSSEC
- list domains
- test connection

### Không bao gồm
- billing flow tổng thể ngoài phạm vi module
- provisioning ngoài các endpoint hiện có
- premium domain nếu API chưa hỗ trợ
- glue records nếu chưa có endpoint

## 3. Môi trường test

- HostBill staging hoặc môi trường dev
- Tài khoản API test hợp lệ
- Domain test hoặc sandbox domain nếu có
- SSL/test endpoint có thể truy cập từ server HostBill

## 4. Dữ liệu chuẩn bị

- 1 domain có sẵn để test manage
- 1 domain khả dụng để test register
- 1 domain có thể transfer để test EPP
- payment method hợp lệ
- contact data mẫu
- DNS records mẫu
- DNSSEC sample data nếu backend hỗ trợ

## 5. Test cases

## 5.1 Authentication

### TC-AUTH-01
- Mục tiêu: xác thực bằng access token
- Bước:
  1. cấu hình `Access Token`
  2. gọi `testConnection()`
- Kỳ vọng:
  - request thành công

### TC-AUTH-02
- Mục tiêu: xác thực bằng refresh token
- Bước:
  1. cấu hình `Refresh Token`
  2. bật `Auto Login`
  3. gọi `testConnection()`
- Kỳ vọng:
  - module lấy được access token mới
  - request thành công

### TC-AUTH-03
- Mục tiêu: xác thực bằng username/password
- Bước:
  1. cấu hình `Username` và `Password`
  2. bật `Auto Login`
  3. gọi `testConnection()`
- Kỳ vọng:
  - login thành công

## 5.2 Lookup / Whois

### TC-LOOKUP-01
- lookup domain khả dụng
- Kỳ vọng: `available = true`

### TC-LOOKUP-02
- lookup domain đã tồn tại
- Kỳ vọng: `available = false`

### TC-WHOIS-01
- whois domain
- Kỳ vọng: có dữ liệu trả về hoặc text hợp lệ

## 5.3 Register / Transfer / Renew

### TC-REG-01
- register domain test
- Kỳ vọng:
  - tạo order thành công
  - HostBill ghi log action

### TC-TRF-01
- transfer domain với EPP hợp lệ
- Kỳ vọng:
  - request thành công
  - order/flow transfer được tạo

### TC-RNW-01
- renew domain hiện có
- Kỳ vọng:
  - API renew thành công
  - HostBill cộng thêm kỳ hạn nếu flow phù hợp

## 5.4 Nameservers

### TC-NS-01
- lấy nameservers
- Kỳ vọng: trả về danh sách nameserver

### TC-NS-02
- cập nhật nameservers
- Kỳ vọng: nameserver đổi thành công phía API

## 5.5 EPP / Lock / Privacy

### TC-EPP-01
- lấy EPP code
- Kỳ vọng: nhận được auth code hợp lệ

### TC-LOCK-01
- bật registrar lock
- Kỳ vọng: trạng thái lock = true

### TC-LOCK-02
- tắt registrar lock
- Kỳ vọng: trạng thái lock = false

### TC-PRIV-01
- bật privacy
- Kỳ vọng: update thành công

### TC-PRIV-02
- tắt privacy
- Kỳ vọng: update thành công

## 5.6 Contacts

### TC-CONTACT-01
- lấy contact info
- Kỳ vọng: nhận đúng cấu trúc dữ liệu

### TC-CONTACT-02
- cập nhật contact info
- Kỳ vọng: request thành công, dữ liệu được cập nhật

## 5.7 Auto renew / Email forwarding

### TC-AR-01
- lấy trạng thái auto renew
- Kỳ vọng: nhận bool/status đúng

### TC-AR-02
- cập nhật auto renew
- Kỳ vọng: update thành công

### TC-EMAIL-01
- lấy email forwarding
- Kỳ vọng: có response hợp lệ

### TC-EMAIL-02
- cập nhật email forwarding
- Kỳ vọng: update thành công

## 5.8 DNS

### TC-DNS-01
- lấy danh sách DNS records
- Kỳ vọng: trả về list records

### TC-DNS-02
- lấy danh sách record types
- Kỳ vọng: có mảng `types`

### TC-DNS-03
- tạo record A
- Kỳ vọng: record được tạo

### TC-DNS-04
- update record MX
- Kỳ vọng: record được cập nhật

### TC-DNS-05
- xóa record
- Kỳ vọng: record bị xóa

## 5.9 DNSSEC

### TC-DNSSEC-01
- lấy danh sách DNSSEC keys
- Kỳ vọng: response hợp lệ

### TC-DNSSEC-02
- lấy danh sách flags
- Kỳ vọng: response hợp lệ

### TC-DNSSEC-03
- thêm DNSSEC key
- Kỳ vọng: request thành công

### TC-DNSSEC-04
- xóa DNSSEC key
- Kỳ vọng: request thành công

## 5.10 Domain listing

### TC-LIST-01
- lấy danh sách domains
- Kỳ vọng: response hợp lệ

## 6. Tiêu chí pass/fail

### Pass
- các flow chính chạy đúng ở staging
- không có lỗi auth/cURL nghiêm trọng
- dữ liệu HostBill và API không lệch bất thường

### Fail
- auth không ổn định
- domain lifecycle không thực hiện đúng
- DNS/DNSSEC hỏng hoặc map sai nghiêm trọng
- order flow không phù hợp với provisioning thực tế

## 7. Báo cáo sau test

Nên ghi nhận:
- test case nào pass/fail
- response thực tế khác gì so với giả định
- endpoint nào cần sửa mapping
- có cần thêm API docs từ HiTechCloud hay không
