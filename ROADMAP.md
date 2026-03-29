# Roadmap

Lộ trình đề xuất cho module `hitechcloud_domains`.

## Mục tiêu ngắn hạn

### 1. Ổn định flow hiện tại
- Chuẩn hóa parse response cho toàn bộ endpoint đang dùng
- Bổ sung log chi tiết cho từng thao tác quan trọng
- Lưu `remote_domain_id` vào `extended` sau khi resolve thành công
- Kiểm tra lại toàn bộ flow với dữ liệu thật

### 2. Nâng chất lượng vận hành
- Thêm retry/backoff cho lỗi tạm thời
- Cải thiện thông báo lỗi rõ ràng hơn
- Bổ sung test checklist chi tiết hơn theo từng environment

## Mục tiêu trung hạn

### 3. Mở rộng chức năng domain
- Hỗ trợ glue records / child nameservers nếu có API docs
- Hỗ trợ premium domain nếu API có trả giá và trạng thái premium
- Hỗ trợ import bảng giá domain (`DomainPriceImport`)
- Mở rộng DNSSEC theo schema chính thức nếu có

### 4. Cải thiện quản lý dữ liệu
- Đồng bộ tốt hơn giữa local domain ID và remote domain ID
- Cache thêm dữ liệu thường dùng nếu cần
- Chuẩn hóa mapping contact data

## Mục tiêu dài hạn

### 5. Chuyển sang registrar provisioning thực thụ
- Thay thế order-based flow bằng registrar backend API nếu HiTechCloud cung cấp
- Tách biệt rõ register/transfer/renew provisioning với order creation
- Hỗ trợ trạng thái provisioning bất đồng bộ nếu API có queue/callback

### 6. Nâng cao chất lượng kỹ thuật
- Viết bộ test tự động nếu môi trường HostBill cho phép
- Chuẩn hóa logging/audit trail
- Bổ sung monitoring cho các lỗi auth, timeout, DNS, DNSSEC

## Ưu tiên đề xuất

Thứ tự nên làm tiếp:
1. Lưu persistent `remote_domain_id`
2. Logging chi tiết request/action
3. Chuẩn hóa response mapping
4. Test thực tế toàn bộ flow
5. Glue records
6. Price import
7. Premium domains
8. Registrar backend provisioning

## Điều kiện để mở rộng nhanh hơn

Cần có thêm:
- tài liệu API backend chính thức
- schema response đầy đủ cho từng endpoint
- thông tin rõ về provisioning thực tế sau khi tạo order
- dữ liệu test/staging thật từ HiTechCloud
