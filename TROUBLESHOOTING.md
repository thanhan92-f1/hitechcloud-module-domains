# Troubleshooting

Tài liệu xử lý sự cố thường gặp cho module `hitechcloud_domains`.

## 1. Lỗi `API URL is not configured`

### Nguyên nhân
- Chưa cấu hình `API URL`
- Cấu hình chưa được lưu đúng trong HostBill

### Cách xử lý
- Kiểm tra lại cấu hình module
- Đảm bảo `API URL` có giá trị hợp lệ
- Ví dụ: `https://api.example.com`

## 2. Lỗi xác thực / không lấy được token

### Dấu hiệu
- Request bị từ chối
- API trả 401/403
- Module không thao tác được dù endpoint tồn tại

### Nguyên nhân có thể
- Sai `Username`
- Sai `Password`
- `Access Token` không hợp lệ
- `Refresh Token` hết hạn
- `Use Bearer Token` bật/tắt không đúng với cơ chế API

### Cách xử lý
- Test lại auth bằng Postman
- Nếu dùng token cố định, kiểm tra token còn hiệu lực
- Nếu dùng refresh token, kiểm tra endpoint `/token`
- Nếu dùng login, xác nhận `POST /login` hoạt động
- Kiểm tra SSL, proxy, firewall nếu có

## 3. Lỗi timeout hoặc cURL error

### Dấu hiệu
- Request treo lâu
- Lỗi cURL
- Không lấy được response

### Nguyên nhân có thể
- API chậm
- Timeout quá thấp
- SSL lỗi
- DNS hoặc firewall nội bộ chặn kết nối

### Cách xử lý
- Tăng `Timeout`
- Tăng `Retry Count` nếu API backend thỉnh thoảng trả lỗi tạm thời
- Tăng `Retry Delay` nếu cần giãn thời gian giữa các lần gọi lại
- Nếu đang test nội bộ, tạm kiểm tra với `Verify SSL` tắt
- Kiểm tra server có outbound access tới API không
- Test endpoint trực tiếp ngoài HostBill

### Các lỗi hiện có retry tự động
- cURL timeout / connect / resolve / send / receive error
- HTTP `408`
- HTTP `429`
- HTTP `500`
- HTTP `502`
- HTTP `503`
- HTTP `504`

## 4. Không resolve được remote domain ID

### Dấu hiệu
- Lỗi: `Unable to resolve remote domain id`

### Nguyên nhân có thể
- Domain chưa tồn tại ở phía API
- Tên domain trong HostBill khác với phía API
- `remote_domain_id` chưa được truyền vào `options`
- API `/domain/name/:name` trả khác format dự kiến

### Cách xử lý
- Kiểm tra lại tên domain trong HostBill
- Gọi thử `GET /domain/name/:name`
- Gọi thử `GET /domain`
- Nếu biết chắc remote ID, truyền `remote_domain_id`
- Có thể mở rộng module để lưu persistent remote ID sau lần resolve đầu tiên

## 5. Register hoặc Transfer không chạy đúng

### Dấu hiệu
- Module không tạo được domain như mong muốn
- Có order nhưng domain không được provisioning thực tế

### Nguyên nhân có thể
- Thiếu `Default Payment Method`
- `tld_id` không đúng
- API hiện tại chỉ tạo order, không provisioning registrar thực sự
- Contact/nameserver truyền sang không đúng format

### Cách xử lý
- Kiểm tra `Default Payment Method`
- Kiểm tra `tld_id`
- Kiểm tra order có được tạo thành công phía API không
- Xác nhận với HiTechCloud backend xem order đó có trigger provisioning thật không

## 6. Renew không thành công

### Dấu hiệu
- Gọi renew lỗi
- Domain không được cộng thêm thời gian

### Nguyên nhân có thể
- Resolve sai domain ID
- API renew yêu cầu dữ liệu khác
- Domain ở trạng thái không cho renew

### Cách xử lý
- Kiểm tra lại `GET /domain/:id`
- Gọi test `POST /domain/:id/renew`
- Xác nhận trạng thái domain trên hệ thống API

## 7. Nameserver không update được

### Nguyên nhân có thể
- API yêu cầu format nameserver khác
- Domain ID sai
- Domain đang bị lock hoặc trạng thái đặc biệt

### Cách xử lý
- Kiểm tra dữ liệu gửi sang `nameservers`
- Test trực tiếp `PUT /domain/:id/ns`
- Xác nhận domain cho phép đổi nameserver

## 8. Không lấy được EPP code

### Nguyên nhân có thể
- API không trả về key đúng format
- Domain không đủ điều kiện lấy EPP
- Endpoint `/domain/:id/epp` không bật cho tài khoản hiện tại

### Cách xử lý
- Test response thật bằng Postman
- Kiểm tra key trả về có nằm trong các key module đang đọc không:
  - `epp`
  - `epp_code`
  - `code`
  - `authcode`
  - `auth_code`
- Nếu API dùng key khác, mở rộng module để map thêm

## 9. Privacy / ID protection không đúng trạng thái

### Nguyên nhân có thể
- API trả trạng thái dưới key khác
- Dữ liệu local `details` không đồng bộ với remote

### Cách xử lý
- Test trực tiếp `GET /domain/:id/idprotection`
- Kiểm tra response thực tế
- Đồng bộ lại logic mapping nếu API trả schema khác

## 10. Contact update lỗi

### Nguyên nhân có thể
- JSON `contact_info` không đúng format API mong đợi
- Thiếu field bắt buộc
- Contact IDs hoặc data không hợp lệ

### Cách xử lý
- So sánh payload với API docs/Postman
- Ghi log payload thực tế
- Kiểm tra dữ liệu contact từ HostBill trước khi gửi

## 11. DNS update lỗi

### Dấu hiệu
- Tạo/update/delete record không thành công

### Nguyên nhân có thể
- Thiếu `index` hoặc `record_id` khi update/delete
- `type` không thuộc danh sách API hỗ trợ
- `content` không hợp lệ

### Cách xử lý
- Gọi `GET /domain/:id/dns/types`
- Kiểm tra `dns_record` có đủ field chưa
- Test create/update/delete trực tiếp bằng Postman

## 12. DNSSEC add/delete lỗi

### Nguyên nhân có thể
- Payload thiếu trường bắt buộc
- `key` không đúng khi delete
- API yêu cầu schema khác với giả định hiện tại

### Cách xử lý
- Kiểm tra `GET /domain/:id/dnssec`
- Kiểm tra `GET /domain/:id/dnssec/flags`
- Xác nhận field thực tế cần gửi với backend API
- Nếu cần, mở rộng `normalizeDnssecPayload()` theo schema thật

## 13. SSL lỗi trong môi trường test

### Cách xử lý tạm thời
- Tắt `Verify SSL` để xác minh nhanh
- Sau khi test xong, bật lại trong production

> Không khuyến nghị tắt SSL verification trên production.

## 14. Khi nào cần sửa code module

Nên sửa code nếu:
- API response dùng schema khác thực tế
- API auth flow khác Postman hiện có
- Domain lifecycle thực chất cần backend registrar API riêng
- Cần thêm glue records, premium domain, pricing import

## 15. Checklist debug nhanh

1. Kiểm tra `API URL`
2. Kiểm tra auth
3. Kiểm tra resolve domain ID
4. Test endpoint tương ứng bằng Postman
5. So sánh response thật với logic parse hiện tại
6. Kiểm tra payment method / tld_id / options
7. Kiểm tra production flow có thực sự provisioning hay không
