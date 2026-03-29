# Examples

Tài liệu này cung cấp các ví dụ input/output và cách map dữ liệu cho module `hitechcloud_domains`.

> Lưu ý: Các ví dụ dưới đây mang tính minh họa theo implementation hiện tại của module và Postman collection đã cung cấp.

## 1. Lookup domain

### Input logic
- `sld`: `example`
- `tld`: `com`

### Method
- `lookupDomain('example', 'com')`

### API mapping
- `POST /domain/lookup?name=example.com`

### Expected result dạng module
```php
[
    'result' => true,
    'available' => true,
    'domain' => 'example.com',
    'premium' => false,
    'message' => 'ok',
    'raw' => [/* API response */],
]
```

## 2. Bulk lookup

### Method
- `lookupBulkDomains('example', ['com', 'net', 'org'])`

### Result
Module sẽ gọi lần lượt từng TLD và trả mảng kết quả.

## 3. WHOIS lookup

### Method
- `whoisDomain('example', 'com')`

### API mapping
- `GET /whoislookup/example.com`
- fallback: `GET /whois/example.com`

### Expected normalized result example
```php
[
    'domain' => 'example.com',
    'registrar' => 'Example Registrar, Inc.',
    'created_at' => '2024-01-10T10:20:30Z',
    'updated_at' => '2025-01-11T11:21:31Z',
    'expires_at' => '2026-01-10T10:20:30Z',
    'status' => 'clientTransferProhibited',
    'statuses' => [
        'clientTransferProhibited',
        'clientUpdateProhibited',
    ],
    'nameservers' => [
        'ns1.example.com',
        'ns2.example.com',
    ],
    'raw' => [/* API response */],
]
```

### Raw text fallback behavior
Nếu API chỉ trả WHOIS text, module vẫn cố parse các dòng phổ biến như:
- `Domain Name:`
- `Registrar:`
- `Creation Date:`
- `Updated Date:`
- `Registry Expiry Date:`
- `Domain Status:`
- `Name Server:`

## 4. Register domain

### Input dữ liệu thường dùng
- `name`: `example.com`
- `period`: `1`
- `tld_id`: ID phần mở rộng trong HostBill
- `pay_method`: phương thức thanh toán mặc định
- nameservers nếu có
- contact IDs nếu có

### API mapping
- `POST /domain/order`

### Dữ liệu gửi best-effort
```php
[
    'name' => 'example.com',
    'years' => '1',
    'action' => 'register',
    'tld_id' => '123',
    'pay_method' => 'banktransfer',
    'nameservers' => 'ns1.example.com,ns2.example.com',
]
```

## 5. Transfer domain

### Method
- `Transfer()`

### Extra field
- `epp` hoặc `epp_code`

### API mapping
- `POST /domain/order`

### Example payload
```php
[
    'name' => 'example.com',
    'years' => '1',
    'action' => 'transfer',
    'tld_id' => '123',
    'pay_method' => 'banktransfer',
    'epp' => 'AUTH-CODE-123',
]
```

## 6. Renew domain

### Method
- `Renew()`

### API mapping
- `POST /domain/:id/renew?years=1`

## 7. Get nameservers

### Method
- `getNameServers()`

### API mapping
- `GET /domain/:id/ns`

### Example response
```php
[
    'ns1.example.com',
    'ns2.example.com',
]
```

### Also accepted response shapes
```php
['ns1' => 'ns1.example.com', 'ns2' => 'ns2.example.com']
['data' => ['nameservers' => ['ns1.example.com', 'ns2.example.com']]]
"ns1.example.com,ns2.example.com"
```

## 8. Update nameservers

### Method
- `updateNameServers()`

### API mapping
- `PUT /domain/:id/ns?nameservers=ns1.example.com,ns2.example.com`

## 8.1 Boolean management lookups

Các method sau giờ đọc tốt hơn response dạng nested:
- `getRegistrarLock()`
- `getIDProtection()`
- `getRegistryAutorenew()`

### Example accepted response shapes
```php
['locked' => true]
['data' => ['lock' => 'enabled']]
['details' => ['auto_renew' => 1]]
```

## 9. Get EPP code

### Method
- `getEppCode()`

### API mapping
- `GET /domain/:id/epp`

### Module lookup keys
Module sẽ thử đọc một trong các key sau từ response:
- `epp`
- `epp_code`
- `code`
- `authcode`
- `auth_code`

## 10. Registrar lock

### Get
- `GET /domain/:id/reglock`

### Update
- `PUT /domain/:id/reglock?switch=true`

## 11. ID Protection

### Get
- ưu tiên đọc từ dữ liệu local/extended
- sau đó thử gọi `GET /domain/:id/idprotection`

### Update
- `PUT /domain/:id/idprotection?switch=true`

## 12. Contact info

### Get
- `GET /domain/:id/contact`

### Example normalized result
```php
[
    'registrant' => [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@example.com',
        'companyname' => 'Example Ltd',
        'phone' => '+84900000000',
        'address1' => '123 Street',
        'city' => 'HCMC',
        'state' => 'HCM',
        'postcode' => '700000',
        'country' => 'VN',
    ],
]
```

### Update
- `PUT /domain/:id/contact`

### Example payload
```php
[
    'contact_info' => json_encode([
        'registrant' => [/* ... */],
        'admin' => [/* ... */],
        'tech' => [/* ... */],
        'billing' => [/* ... */],
    ]),
]
```

## 13. Registry auto-renew

### Get
- `GET /domain/:id/autorenew`

### Update
- `PUT /domain/:id/autorenew?autorenew=true`

## 14. Email forwarding

### Get
- `GET /domain/:id/emforwarding`

### Example normalized result
```php
[
    'from' => 'info',
    'to' => 'admin@example.com',
    'forwardings' => [
        [
            'from' => 'sales',
            'to' => 'sales@example.com',
        ],
    ],
]
```

### Update
- `PUT /domain/:id/emforwarding`

### Example payload
```php
[
    'from' => 'info',
    'to' => 'admin@example.com',
]
```

## 15. DNS records

### List records
- `GET /domain/:id/dns`

### Example normalized result
```php
[
    [
        'id' => '15',
        'name' => 'www',
        'type' => 'A',
        'content' => '192.168.1.10',
        'priority' => '',
        'ttl' => '3600',
    ],
]
```

### Supported types lookup
- `GET /domain/:id/dns/types`

### Example normalized types
```php
['A', 'AAAA', 'CNAME', 'MX', 'TXT']
```

### Create record
- `POST /domain/:id/dns`

Example:
```php
[
    'name' => 'www',
    'type' => 'A',
    'priority' => '',
    'content' => '192.168.1.10',
]
```

### Update record
- `PUT /domain/:id/dns/:index`

Example:
```php
[
    'record_id' => '15',
    'name' => 'mail',
    'type' => 'MX',
    'priority' => '10',
    'content' => 'mail.example.com',
]
```

Example with alternate input keys accepted by the module:
```php
[
    'id' => '15',
    'host' => 'mail',
    'record_type' => 'MX',
    'target' => 'mail.example.com',
    'priority' => '10',
    'ttl' => '3600',
]
```

### Delete record
- `DELETE /domain/:id/dns/:index?record_id=15`

## 16. DNSSEC

### List keys
- `GET /domain/:id/dnssec`

### List flags
- `GET /domain/:id/dnssec/flags`

### Example normalized get result
```php
[
    'keys' => [
        [
            'key' => '12345',
            'flags' => '257',
            'alg' => '13',
            'digest_type' => '2',
            'digest' => 'ABCDEF1234567890',
            'pubkey' => 'BASE64PUBLICKEY',
            'protocol' => '3',
        ],
    ],
    'available_flags' => [
        ['value' => '256', 'label' => 'ZSK'],
        ['value' => '257', 'label' => 'KSK'],
    ],
    'key_count' => 1,
]
```

### Add key
- `PUT /domain/:id/dnssec`

Example best-effort payload:
```php
[
    'key' => '12345',
    'flags' => '257',
    'alg' => '13',
    'digest_type' => '2',
    'digest' => 'ABCDEF1234567890',
    'pubkey' => 'BASE64PUBLICKEY',
    'protocol' => '3',
]
```

### Delete key
- `DELETE /domain/:id/dnssec/:key`

## 17. Resolve remote domain ID

Module hiện resolve `remote_domain_id` theo thứ tự:
1. `options['remote_domain_id']`
2. `details['extended']['remote_domain_id']`
3. `domain_id` local
4. `GET /domain/name/:name`
5. `GET /domain` và dò theo `name`

## 18. Test connection

### Method
- `testConnection()`

### API mapping
- `GET /domain`

### Success condition
- request không trả lỗi

## 19. Lưu ý khi tích hợp thực tế

- `Register()`, `Transfer()`, `Renew()` hiện là best-effort
- Cần xác minh bên API có provisioning thực sự hay chỉ tạo order
- Một số response schema trong Postman chưa đầy đủ nên module đang normalize theo kiểu linh hoạt
