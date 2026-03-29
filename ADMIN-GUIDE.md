# Hướng dẫn cấu hình admin cho module `hitechcloud_domains`

Tài liệu này mô tả nhanh cách cấu hình module trong HostBill và ý nghĩa từng field.

## 1. Kích hoạt module

Sau khi chép module vào đúng thư mục module domain của HostBill:
- kiểm tra file chính: `hitechcloud_domains/class.hitechcloud_domains.php`
- vào trang quản trị HostBill
- bật module `HiTechCloud_Domains`

## 2. Cấu hình từng field

### `API URL`
- Đây là URL gốc của API HiTechCloud
- Ví dụ: `https://api.example.com`
- Không nên thêm dấu `/` ở cuối, dù module có xử lý trim

### `Username`
- Tài khoản đăng nhập API
- Thường là email hoặc username theo hệ thống HiTechCloud
- Dùng khi module cần tự login

### `Password`
- Mật khẩu API tương ứng với `Username`
- Dùng cho flow `POST /login`

### `Access Token`
- Nếu bạn đã có token cố định, có thể nhập trực tiếp vào đây
- Khi field này có giá trị, module sẽ ưu tiên dùng token này trước

### `Refresh Token`
- Dùng để lấy access token mới qua `POST /token`
- Hữu ích khi hệ thống cấp refresh token riêng

### `Use Bearer Token`
- Nếu bật, module sẽ gửi header:
  - `Authorization: Bearer <token>`
- Nên bật nếu API của bạn dùng Bearer auth

### `Verify SSL`
- Bật trong môi trường production
- Chỉ nên tắt khi test môi trường nội bộ có SSL chưa chuẩn

### `Timeout`
- Timeout request HTTP, đơn vị là giây
- Khuyến nghị:
  - test/dev: `30`
  - production: `60`

### `Default Payment Method`
- Bắt buộc với flow tạo order như:
  - register
  - transfer
  - renew order-like flow
- Giá trị phải khớp payment method mà hệ thống API chấp nhận
- Nếu để trống, `Register()` hoặc `Transfer()` có thể fail

### `Auto Login`
- Nếu bật, module sẽ thử xác thực tự động khi chưa có token
- Thứ tự thử:
  1. Access Token
  2. Refresh Token
  3. Username + Password

## 3. Thứ tự xác thực khuyến nghị

Khuyến nghị cấu hình theo 1 trong 3 cách sau:

### Cách 1: Dùng Access Token cố định
- nhập `API URL`
- nhập `Access Token`
- bật `Use Bearer Token`
- có thể bỏ trống `Username`, `Password`, `Refresh Token`

### Cách 2: Dùng Refresh Token
- nhập `API URL`
- nhập `Refresh Token`
- bật `Auto Login`
- bật `Use Bearer Token`

### Cách 3: Dùng Username + Password
- nhập `API URL`
- nhập `Username`
- nhập `Password`
- bật `Auto Login`
- bật `Use Bearer Token`

## 4. Cấu hình tối thiểu để chạy

Tối thiểu nên có:
- `API URL`
- và một trong các cách xác thực:
  - `Access Token`
  - hoặc `Refresh Token`
  - hoặc `Username` + `Password`

Nếu muốn dùng register/transfer qua order flow, cần thêm:
- `Default Payment Method`

## 5. Test sau khi cấu hình

Nên test theo thứ tự:

1. `testConnection()`
2. lookup domain
3. get nameservers
4. get EPP code
5. lock / unlock
6. privacy on / off
7. contact get / update
8. DNS create / update / delete
9. DNSSEC list / add / delete
10. renew domain test

## 6. Một số lỗi cấu hình thường gặp

### Lỗi: `API URL is not configured`
- Chưa nhập `API URL`
- Hoặc nhập sai ở cấu hình module

### Lỗi đăng nhập / token
- Sai `Username` hoặc `Password`
- `Refresh Token` hết hạn
- API không dùng Bearer nhưng `Use Bearer Token` đang bật sai cách

### Lỗi register / transfer
- Chưa nhập `Default Payment Method`
- `tld_id` không đúng
- API hiện tại không phải registrar provisioning API thực sự

### Lỗi không resolve được domain ID
- Domain chưa tồn tại phía remote
- Tên domain trong HostBill và phía API không khớp
- API trả dữ liệu khác format dự kiến

## 7. Ghi chú vận hành

- Nên dùng môi trường test trước khi chạy production
- Nên bật `Verify SSL` trong production
- Nên log request/response nếu tiếp tục mở rộng module
- Với các flow quan trọng như register/transfer, nên kiểm tra lại order có thực sự provisioning domain hay chỉ tạo đơn hàng

## 8. Giới hạn hiện tại

- Chưa có glue records
- Chưa có premium domains
- Chưa có import giá domain
- DNSSEC đang ở mức best-effort theo schema Postman hiện có
- Register/Transfer/Renew vẫn phụ thuộc user/order API

## 9. Tài liệu liên quan

- `README.md`: tổng quan tiếng Việt
- `README.en.md`: tổng quan tiếng Anh
- `DEPLOYMENT-CHECKLIST.md`: checklist triển khai
- `EXAMPLES.md`: ví dụ payload và flow xử lý
- `ARCHITECTURE.md`: mô tả kiến trúc hiện tại
- `TROUBLESHOOTING.md`: hướng dẫn debug lỗi thường gặp
- `ROADMAP.md`: định hướng mở rộng tiếp theo
- `TEST-PLAN.md`: kế hoạch kiểm thử
- `HANDOVER.md`: tài liệu bàn giao nội bộ
- `CHANGELOG.md`: lịch sử thay đổi
- `LICENSE`: license nội bộ của công ty
