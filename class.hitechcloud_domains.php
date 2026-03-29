<?php

class HiTechCloud_Domains extends DomainModule implements DomainLookupInterface, DomainWhoisInterface, DomainBulkLookupInterface, DomainSuggestionsInterface, DomainHideFormInterface, DomainPremiumInterface, DomainModuleNameservers, DomainModuleGluerecords, DomainModuleAuth, DomainModuleLock, DomainModulePrivacy, DomainModuleContacts, DomainModuleRegistryAutorenew, DomainModuleForwarding, DomainModuleDNS, DomainModuleDNSSEC, DomainModuleListing, DomainPriceImport
{
    protected $moduleName = 'HiTechCloud_Domains';
    protected $version = '1.6.0';
    protected $description = 'HiTechCloud domain integration for HostBill based on available User API endpoints.';
    protected $configuration = [
        'API URL' => [
            'type' => self::CONFIG_FIELD_INPUT,
            'value' => '',
            'name' => 'API URL',
            'description' => 'Base URL, ví dụ: https://api.example.com'
        ],
        'Username' => [
            'type' => self::CONFIG_FIELD_INPUT,
            'value' => '',
            'name' => 'Username',
            'description' => 'API username/email'
        ],
        'Password' => [
            'type' => self::CONFIG_FIELD_PASSWORD,
            'value' => '',
            'name' => 'Password',
            'description' => 'API password'
        ],
        'Access Token' => [
            'type' => self::CONFIG_FIELD_PASSWORD,
            'value' => '',
            'name' => 'Access Token',
            'description' => 'Tùy chọn, nếu đã có access token cố định'
        ],
        'Refresh Token' => [
            'type' => self::CONFIG_FIELD_PASSWORD,
            'value' => '',
            'name' => 'Refresh Token',
            'description' => 'Tùy chọn, dùng để làm mới access token'
        ],
        'Use Bearer Token' => [
            'type' => self::CONFIG_FIELD_CHECK,
            'value' => true,
            'name' => 'Use Bearer Token',
            'description' => 'Gửi Authorization: Bearer <token> nếu có token'
        ],
        'Verify SSL' => [
            'type' => self::CONFIG_FIELD_CHECK,
            'value' => true,
            'name' => 'Verify SSL',
            'description' => 'Bật xác thực SSL'
        ],
        'Timeout' => [
            'type' => self::CONFIG_FIELD_INPUT,
            'value' => '60',
            'name' => 'Timeout',
            'description' => 'HTTP timeout (giây)'
        ],
        'Retry Count' => [
            'type' => self::CONFIG_FIELD_INPUT,
            'value' => '2',
            'name' => 'Retry Count',
            'description' => 'Số lần retry thêm cho lỗi tạm thời như timeout, 429, 502, 503, 504'
        ],
        'Retry Delay' => [
            'type' => self::CONFIG_FIELD_INPUT,
            'value' => '500',
            'name' => 'Retry Delay',
            'description' => 'Thời gian chờ giữa các lần retry (milliseconds)'
        ],
        'Default Payment Method' => [
            'type' => self::CONFIG_FIELD_INPUT,
            'value' => '',
            'name' => 'Default Payment Method',
            'description' => 'Bắt buộc nếu dùng endpoint tạo order/renew order'
        ],
        'Auto Login' => [
            'type' => self::CONFIG_FIELD_CHECK,
            'value' => true,
            'name' => 'Auto Login',
            'description' => 'Tự login nếu chưa có token'
        ]
    ];
    protected $tokenCache = [];
    protected $domainCache = [];

    public function Register()
    {
        $result = $this->createDomainOrder('register');
        if ($result) {
            $this->logModuleAction('Register domain', true, [
                ['name' => 'domain', 'from' => '', 'to' => (string) $this->name],
            ]);
        }

        return $result;
    }

    public function Renew()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $query = [
            'years' => (string) ($this->period ?: (isset($this->options['numyears']) ? $this->options['numyears'] : 1)),
        ];

        $payMethod = trim((string) $this->config('Default Payment Method'));
        if ($payMethod !== '') {
            $query['pay_method'] = $payMethod;
        }

        $response = $this->request('POST', '/domain/'.$domainId.'/renew', $query);
        if ($response === false) {
            return false;
        }

        $this->addPeriod();
        $this->logModuleAction('Renew domain via HiTechCloud', true, [
            ['name' => 'domain', 'from' => '', 'to' => (string) $this->name],
            ['name' => 'years', 'from' => '', 'to' => (string) $query['years']],
        ]);

        return true;
    }

    public function Transfer()
    {
        $result = $this->createDomainOrder('transfer');
        if ($result) {
            $this->logModuleAction('Transfer domain', true, [
                ['name' => 'domain', 'from' => '', 'to' => (string) $this->name],
            ]);
        }

        return $result;
    }

    public function lookupDomain($sld, $tld, $settings = [])
    {
        $name = $this->buildDomainName($sld, $tld);
        $response = $this->request('POST', '/domain/lookup', ['name' => $name], [], false);
        if ($response === false) {
            return ['result' => false, 'available' => false, 'domain' => $name];
        }

        $available = $this->isLookupAvailable($response);
        $premiumData = $this->extractPremiumData($response);

        $result = [
            'result' => true,
            'available' => $available,
            'domain' => $name,
            'premium' => $premiumData['is_premium'],
            'message' => $available ? 'ok' : 'unavailable',
            'raw' => $response,
        ];

        if (null !== $premiumData['price']) {
            $result['premium_price'] = $premiumData['price'];
        }

        if (null !== $premiumData['currency']) {
            $result['currency'] = $premiumData['currency'];
        }

        if ($premiumData['is_premium'] && !self::arePremiumDomainsAllowed()) {
            $result['premium_disabled'] = true;
            $result['message'] = self::ERR_PREMIUM_DOMAINS_DISABLED;
        }

        return $result;
    }

    public function lookupBulkDomains($sld, $tld)
    {
        $results = [];
        $tlds = is_array($tld) ? $tld : preg_split('/[\s,;]+/', (string) $tld, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tlds as $oneTld) {
            $results[] = $this->lookupDomain($sld, $oneTld);
        }

        return $results;
    }

    public function suggestDomains($sld, $tld, $settings = [])
    {
        $suggestions = [];
        $prefix = preg_replace('/[^a-z0-9\-]/i', '', (string) $sld);
        $baseTld = ltrim((string) $tld, '.');
        foreach (['com', 'net', 'org', 'vn', $baseTld] as $candidateTld) {
            if (!$candidateTld) {
                continue;
            }
            $suggestions[] = [
                'domain' => $this->buildDomainName($prefix, $candidateTld),
                'status' => 'unknown'
            ];
        }

        return array_values(array_unique($suggestions, SORT_REGULAR));
    }

    public function whoisDomain($sld, $tld, $settings = [])
    {
        $name = $this->buildDomainName($sld, $tld);
        $response = $this->request('GET', '/whoislookup/'.$this->encodePathSegment($name), [], [], false);
        if ($response === false) {
            $response = $this->request('GET', '/whois/'.$this->encodePathSegment($name), [], [], false);
        }

        return $response ?: [];
    }

    public function getNameServers()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $cached = $this->getDomainDetailValue($domainId, 'nameservers');
        if (is_array($cached) && isset($cached['nameservers']) && is_array($cached['nameservers'])) {
            return $cached['nameservers'];
        }

        $response = $this->request('GET', '/domain/'.$domainId.'/ns');
        if ($response === false) {
            return false;
        }

        return $this->extractNameservers($response);
    }

    public function updateNameServers()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $nameservers = $this->collectNameServers();
        $response = $this->request('PUT', '/domain/'.$domainId.'/ns', [
            'nameservers' => implode(',', $nameservers)
        ]);

        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Update nameservers', true, [
                ['name' => 'nameservers', 'from' => '', 'to' => implode(', ', $nameservers)],
            ]);
        }

        return false !== $response;
    }

    public function getEppCode()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $response = $this->request('GET', '/domain/'.$domainId.'/epp');
        if ($response === false) {
            return false;
        }

        if (is_array($response)) {
            foreach (['epp', 'epp_code', 'code', 'authcode', 'auth_code'] as $key) {
                if (isset($response[$key]) && $response[$key] !== '') {
                    return $response[$key];
                }
            }
        }

        return is_string($response) ? trim($response) : $response;
    }

    public function getRegisterNameServers()
    {
        return $this->unsupportedGlueRecordsAction('Get registered nameservers');
    }

    public function deleteNameServer()
    {
        return $this->unsupportedGlueRecordsAction('Delete child nameserver');
    }

    public function modifyNameServer()
    {
        return $this->unsupportedGlueRecordsAction('Modify child nameserver');
    }

    public function registerNameServer()
    {
        return $this->unsupportedGlueRecordsAction('Register child nameserver');
    }

    public function getRegistrarLock()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $cached = $this->getDomainDetailValue($domainId, 'reglock');
        if (is_array($cached) && array_key_exists('reglock', $cached)) {
            return $this->toBoolValue($cached['reglock']);
        }

        $cached = $this->getDomainDetailValue($domainId, 'locked');
        if (is_array($cached) && array_key_exists('locked', $cached)) {
            return $this->toBoolValue($cached['locked']);
        }

        $response = $this->request('GET', '/domain/'.$domainId.'/reglock');
        if ($response === false) {
            return false;
        }

        return $this->toBoolValue($this->extractFirstValue($response, ['reglock', 'lock', 'locked', 'status']));
    }

    public function updateRegistrarLock()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $switch = $this->toBoolValue($this->optionsValue('lock', true));
        $response = $this->request('PUT', '/domain/'.$domainId.'/reglock', [
            'switch' => $switch ? 'true' : 'false'
        ]);

        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Update registrar lock', true, [
                ['name' => 'lock', 'from' => '', 'to' => $switch ? 'true' : 'false'],
            ]);
        }

        return false !== $response;
    }

    public function getIDProtection()
    {
        $extended = isset($this->details['extended']) && is_array($this->details['extended']) ? $this->details['extended'] : [];
        if (isset($extended['idprotection'])) {
            return $this->toBoolValue($extended['idprotection']);
        }

        $domainId = $this->resolveRemoteDomainId();
        if ($domainId) {
            $response = $this->getDomainDetailValue($domainId, 'idprotection');
            if ($response === null) {
                $response = $this->getDomainDetailValue($domainId, 'privacy');
            }
            if ($response === null) {
                $response = $this->request('GET', '/domain/'.$domainId.'/idprotection');
            }
            if ($response !== false) {
                return $this->toBoolValue($this->extractFirstValue($response, ['idprotection', 'privacy', 'status', 'enabled']));
            }
        }

        return isset($this->details['idprotection']) ? $this->toBoolValue($this->details['idprotection']) : null;
    }

    public function updateIDProtection()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $switch = $this->toBoolValue($this->optionsValue('idprotection', true));
        $response = $this->request('PUT', '/domain/'.$domainId.'/idprotection', [
            'switch' => $switch ? 'true' : 'false'
        ]);

        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Update ID protection', true, [
                ['name' => 'idprotection', 'from' => '', 'to' => $switch ? 'true' : 'false'],
            ]);
        }

        return false !== $response;
    }

    public function getContactInfo()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $cached = $this->getDomainDetailValue($domainId, 'contact_info');
        if (is_array($cached) && isset($cached['contact_info']) && is_array($cached['contact_info'])) {
            return $cached['contact_info'];
        }

        $response = $this->request('GET', '/domain/'.$domainId.'/contact');
        if ($response === false) {
            return false;
        }

        if (isset($response['contact_info']) && is_array($response['contact_info'])) {
            return $response['contact_info'];
        }

        foreach (['contacts', 'contact', 'details'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return is_array($response) ? $response : false;
    }

    public function updateContactInfo()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $payload = [
            'contact_info' => json_encode($this->buildContactPayload())
        ];
        $response = $this->request('PUT', '/domain/'.$domainId.'/contact', $payload);

        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Update contact info', true);
        }

        return false !== $response;
    }

    public function getRegistryAutorenew()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $cached = $this->getDomainDetailValue($domainId, 'autorenew');
        if (is_array($cached) && array_key_exists('autorenew', $cached)) {
            return $this->toBoolValue($cached['autorenew']);
        }

        $response = $this->request('GET', '/domain/'.$domainId.'/autorenew');
        if ($response === false) {
            return false;
        }

        return $this->toBoolValue($this->extractFirstValue($response, ['autorenew', 'auto_renew', 'renew']));
    }

    public function updateRegistryAutorenew()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $switch = $this->toBoolValue($this->optionsValue('autorenew', true));
        $response = $this->request('PUT', '/domain/'.$domainId.'/autorenew', [
            'autorenew' => $switch ? 'true' : 'false'
        ]);

        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Update registry autorenew', true, [
                ['name' => 'autorenew', 'from' => '', 'to' => $switch ? 'true' : 'false'],
            ]);
        }

        return false !== $response;
    }

    public function getEmailForwarding()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        return $this->request('GET', '/domain/'.$domainId.'/emforwarding');
    }

    public function updateEmailForwarding()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $payload = [];
        foreach (['from', 'to'] as $field) {
            $value = $this->optionsValue($field, '');
            if ($value !== '') {
                $payload[$field] = $value;
            }
        }

        $response = $this->request('PUT', '/domain/'.$domainId.'/emforwarding', $payload);

        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Update email forwarding', true);
        }

        return false !== $response;
    }

    public function getDNSmanagement()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $response = $this->request('GET', '/domain/'.$domainId.'/dns');
        if ($response === false) {
            return false;
        }

        foreach (['records', 'dns', 'items', 'data'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return $response;
    }

    public function updateDNSManagement()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $record = $this->optionsValue('dns_record', []);
        if (!is_array($record)) {
            $record = [];
        }

        $index = isset($record['index']) ? $record['index'] : '';
        $recordId = isset($record['record_id']) ? $record['record_id'] : (isset($record['id']) ? $record['id'] : '');
        $method = 'POST';
        $path = '/domain/'.$domainId.'/dns';
        $query = [
            'name' => isset($record['name']) ? $record['name'] : '',
            'type' => isset($record['type']) ? $record['type'] : '',
            'priority' => isset($record['priority']) ? $record['priority'] : '',
            'content' => isset($record['content']) ? $record['content'] : '',
        ];

        if (!empty($record['delete'])) {
            $method = 'DELETE';
            $path = '/domain/'.$domainId.'/dns/'.$this->encodePathSegment($index !== '' ? $index : $recordId);
            $query = ['record_id' => $recordId];
        } elseif ($index !== '' || $recordId !== '') {
            $method = 'PUT';
            $path = '/domain/'.$domainId.'/dns/'.$this->encodePathSegment($index !== '' ? $index : $recordId);
            $query['record_id'] = $recordId;
        }

        $response = $this->request($method, $path, $query);

        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Update DNS management', true, [
                ['name' => 'method', 'from' => '', 'to' => $method],
                ['name' => 'path', 'from' => '', 'to' => $path],
            ]);
        }

        return false !== $response;
    }

    public function ListDomains()
    {
        $response = $this->request('GET', '/domain');
        if ($response === false) {
            return false;
        }

        foreach (['domains', 'items', 'data', 'details'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return $response;
    }

    public function getDomainPrices()
    {
        $response = $this->request('GET', '/domain/order');
        if ($response === false) {
            return false;
        }

        return $this->normalizeDomainPrices($response);
    }

    public function testConnection()
    {
        $response = $this->request('GET', '/domain', [], [], true);

        return false !== $response;
    }

    public function hideContacts()
    {
        return false;
    }

    public function hideNameServers()
    {
        return false;
    }

    public function widget_dnssec_form()
    {
        $data = $this->widget_dnssec_get();

        return [
            'fields' => [
                'action' => [
                    'type' => 'select',
                    'label' => 'Action',
                    'options' => [
                        'add' => 'Add key',
                        'delete' => 'Delete key',
                    ],
                    'value' => 'add',
                ],
                'key' => ['type' => 'text', 'label' => 'Key Tag / Key', 'value' => ''],
                'flags' => ['type' => 'text', 'label' => 'Flags', 'value' => ''],
                'alg' => ['type' => 'text', 'label' => 'Algorithm', 'value' => ''],
                'digest_type' => ['type' => 'text', 'label' => 'Digest Type', 'value' => ''],
                'digest' => ['type' => 'text', 'label' => 'Digest', 'value' => ''],
                'pubkey' => ['type' => 'textarea', 'label' => 'Public Key', 'value' => ''],
                'protocol' => ['type' => 'text', 'label' => 'Protocol', 'value' => '3'],
            ],
            'available_flags' => isset($data['available_flags']) ? $data['available_flags'] : [],
        ];
    }

    public function widget_dnssec_get()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return [];
        }

        $keysResponse = $this->request('GET', '/domain/'.$domainId.'/dnssec');
        $flagsResponse = $this->request('GET', '/domain/'.$domainId.'/dnssec/flags');

        return [
            'keys' => $this->normalizeDnssecList($keysResponse),
            'available_flags' => $this->normalizeDnssecFlags($flagsResponse),
        ];
    }

    public function widget_dnssec_set($data)
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        if (!is_array($data)) {
            $this->addError('Invalid DNSSEC payload');
            return false;
        }

        $action = isset($data['action']) ? strtolower(trim((string) $data['action'])) : 'add';
        if ('delete' === $action) {
            $key = '';
            foreach (['key', 'key_tag', 'keytag', 'id'] as $candidate) {
                if (!empty($data[$candidate])) {
                    $key = $data[$candidate];
                    break;
                }
            }

            if ($key === '') {
                $this->addError('DNSSEC key is required for delete action');
                return false;
            }

            $response = $this->request('DELETE', '/domain/'.$domainId.'/dnssec/'.$this->encodePathSegment($key));
            if (false !== $response) {
                $this->forgetDomainCache($domainId, $this->name);
                $this->logModuleAction('Delete DNSSEC key', true, [
                    ['name' => 'key', 'from' => '', 'to' => (string) $key],
                ]);
            }

            return false !== $response;
        }

        $payload = $this->normalizeDnssecPayload($data);
        if (empty($payload)) {
            $this->addError('No DNSSEC data to submit');
            return false;
        }

        $response = $this->request('PUT', '/domain/'.$domainId.'/dnssec', $payload);
        if (false !== $response) {
            $this->forgetDomainCache($domainId, $this->name);
            $this->logModuleAction('Add DNSSEC key', true);
        }

        return false !== $response;
    }

    public function getDNSRecordTypes()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
        }

        $response = $this->request('GET', '/domain/'.$domainId.'/dns/types');
        if ($response === false) {
            return false;
        }

        foreach (['types', 'records', 'items', 'data'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return $response;
    }

    protected function createDomainOrder($action)
    {
        $premiumOrder = $this->getPremiumOrderData();
        if ($premiumOrder['premium'] && !self::arePremiumDomainsAllowed()) {
            $this->addError(self::ERR_PREMIUM_DOMAINS_DISABLED);
            return false;
        }

        $payMethod = trim((string) $this->config('Default Payment Method'));
        if ($payMethod === '') {
            $this->addError('Default Payment Method is required for '.$action.' order');
            return false;
        }

        $domainName = $this->name ?: $this->buildDomainName(
            isset($this->options['sld']) ? $this->options['sld'] : '',
            isset($this->options['tld']) ? $this->options['tld'] : ''
        );

        $query = [
            'name' => $domainName,
            'years' => (string) ($this->period ?: (isset($this->options['numyears']) ? $this->options['numyears'] : 1)),
            'action' => $action,
            'tld_id' => $this->detectTldId(),
            'pay_method' => $payMethod,
        ];

        if ('transfer' === $action) {
            $epp = isset($this->details['epp_code']) ? $this->details['epp_code'] : $this->optionsValue('epp_code', '');
            if ($epp !== '') {
                $query['epp'] = $epp;
            }
        }

        $nameservers = $this->collectNameServers();
        if (!empty($nameservers)) {
            $query['nameservers'] = implode(',', $nameservers);
        }

        $contacts = $this->buildContactPayload();
        foreach (['registrant', 'admin', 'tech', 'billing'] as $type) {
            if (!empty($contacts[$type]['id'])) {
                $query[$type] = $contacts[$type]['id'];
            }
        }

        if (!empty($this->options['ext']) && is_array($this->options['ext'])) {
            $query['data'] = json_encode($this->options['ext']);
        }

        if ($premiumOrder['premium']) {
            $query['premium'] = 'true';
            if (null !== $premiumOrder['price']) {
                $query['premium_price'] = $premiumOrder['price'];
            }
            if (null !== $premiumOrder['currency']) {
                $query['currency'] = $premiumOrder['currency'];
            }
        }

        $response = $this->request('POST', '/domain/order', $query);
        if ($response === false) {
            return false;
        }

        $this->addDomain('Pending Registration');
        $this->logModuleAction(ucfirst($action).' domain via HiTechCloud order API', true, [
            ['name' => 'domain', 'from' => '', 'to' => (string) $domainName],
            ['name' => 'years', 'from' => '', 'to' => (string) $query['years']],
            ['name' => 'tld_id', 'from' => '', 'to' => (string) $query['tld_id']],
        ]);

        return true;
    }

    protected function request($method, $path, array $query = [], array $headers = [], $auth = true)
    {
        $context = [
            'method' => strtoupper($method),
            'path' => $path,
        ];

        $baseUrl = rtrim((string) $this->config('API URL'), '/');
        if ($baseUrl === '') {
            $this->addError('API URL is not configured');
            $this->logModuleAction('API request failed', false, [
                ['name' => 'reason', 'from' => '', 'to' => 'API URL is not configured'],
                ['name' => 'path', 'from' => '', 'to' => $path],
            ], true);
            return false;
        }

        if ($auth && !$this->ensureAuthenticated()) {
            $this->logModuleAction('API authentication failed', false, [
                ['name' => 'path', 'from' => '', 'to' => $path],
            ], true);
            return false;
        }

        $url = $baseUrl.'/'.ltrim($path, '/');
        if (!empty($query)) {
            $separator = false === strpos($url, '?') ? '?' : '&';
            $url .= $separator.$this->buildQuery($query);
        }

        $defaultHeaders = ['Accept: application/json'];
        $token = $this->getAccessToken();
        if ($auth && $token && $this->toBoolValue($this->config('Use Bearer Token'))) {
            $defaultHeaders[] = 'Authorization: Bearer '.$token;
        }

        foreach ($headers as $header) {
            $defaultHeaders[] = $header;
        }

        $attempt = 0;
        $maxRetries = max(0, (int) $this->config('Retry Count'));
        $maxAttempts = $maxRetries + 1;

        while ($attempt < $maxAttempts) {
            ++$attempt;

            $ch = curl_init();
            if (false === $ch) {
                $this->addError('Unable to initialize cURL');
                $this->logModuleFailure('API request failed', $context, 'Unable to initialize cURL');
                return false;
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $defaultHeaders,
                CURLOPT_TIMEOUT => max(5, (int) $this->config('Timeout')),
                CURLOPT_SSL_VERIFYPEER => $this->toBoolValue($this->config('Verify SSL')),
                CURLOPT_SSL_VERIFYHOST => $this->toBoolValue($this->config('Verify SSL')) ? 2 : 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => true,
            ]);

            $raw = curl_exec($ch);
            if (false === $raw) {
                $curlErrorNo = (int) curl_errno($ch);
                $errorMessage = 'HTTP request failed: '.curl_error($ch);
                curl_close($ch);

                if ($attempt < $maxAttempts && $this->isRetryableCurlError($curlErrorNo)) {
                    $this->logRetryAttempt($context, $attempt, $maxAttempts, $errorMessage);
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }

                $this->addError($errorMessage);
                $this->logModuleFailure('API request failed', $context, $errorMessage);
                return false;
            }

            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $headerText = substr($raw, 0, $headerSize);
            $body = substr($raw, $headerSize);
            $decoded = json_decode($body, true);
            $response = JSON_ERROR_NONE === json_last_error() ? $decoded : trim($body);

            if ($status >= 400) {
                if ($attempt < $maxAttempts && $this->isRetryableHttpStatus($status)) {
                    $retryAfterMs = $this->parseRetryAfterMs($headerText);
                    $this->logRetryAttempt($context, $attempt, $maxAttempts, 'HTTP '.$status, $retryAfterMs);
                    $this->sleepBeforeRetry($attempt, $retryAfterMs);
                    continue;
                }

                $this->handleApiFailure($response, 'HTTP '.$status.' returned from '.$path);
                $this->logModuleFailure('API request failed', $context, 'HTTP '.$status, $response);
                return false;
            }

            if (is_array($response) && isset($response['success']) && !$response['success']) {
                $this->handleApiFailure($response, 'API returned unsuccessful response');
                $this->logModuleFailure('API request failed', $context, 'API returned unsuccessful response', $response);
                return false;
            }

            return $response;
        }

        $this->addError('API request failed after retries');
        $this->logModuleFailure('API request failed', $context, 'API request failed after retries');
        return false;
    }

    protected function ensureAuthenticated()
    {
        if ($this->getAccessToken()) {
            return true;
        }

        if (!$this->toBoolValue($this->config('Auto Login'))) {
            return true;
        }

        $refreshToken = trim((string) $this->config('Refresh Token'));
        if ($refreshToken !== '') {
            $refreshed = $this->request('POST', '/token', ['refresh_token' => $refreshToken], [], false);
            if (is_array($refreshed)) {
                $token = $this->extractFirstValue($refreshed, ['access_token', 'token', 'jwt']);
                if ($token) {
                    $this->tokenCache['access_token'] = $token;
                    if (!empty($refreshed['refresh_token'])) {
                        $this->tokenCache['refresh_token'] = $refreshed['refresh_token'];
                    }
                    return true;
                }
            }
        }

        $username = trim((string) $this->config('Username'));
        $password = (string) $this->config('Password');
        if ($username === '' || $password === '') {
            return true;
        }

        $login = $this->request('POST', '/login', [
            'username' => $username,
            'password' => $password,
        ], [], false);

        if (is_array($login)) {
            $token = $this->extractFirstValue($login, ['access_token', 'token', 'jwt']);
            if ($token) {
                $this->tokenCache['access_token'] = $token;
            }
            $refresh = $this->extractFirstValue($login, ['refresh_token']);
            if ($refresh) {
                $this->tokenCache['refresh_token'] = $refresh;
            }
        }

        return true;
    }

    protected function getAccessToken()
    {
        if (!empty($this->tokenCache['access_token'])) {
            return $this->tokenCache['access_token'];
        }

        $configured = trim((string) $this->config('Access Token'));
        if ($configured !== '') {
            return $configured;
        }

        return null;
    }

    protected function config($key)
    {
        return isset($this->configuration[$key]['value']) ? $this->configuration[$key]['value'] : '';
    }

    protected function buildDomainName($sld, $tld)
    {
        return trim((string) $sld).'.'.ltrim(trim((string) $tld), '.');
    }

    protected function buildQuery(array $query)
    {
        $normalized = [];
        foreach ($query as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = json_encode($value);
            }
            $normalized[$key] = $value;
        }

        return http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
    }

    protected function collectNameServers()
    {
        $nameservers = [];
        foreach (['ns1', 'ns2', 'ns3', 'ns4', 'ns5'] as $key) {
            $value = $this->optionsValue($key, isset($this->details[$key]) ? $this->details[$key] : '');
            if ($value !== '') {
                $nameservers[] = $value;
            }
        }

        return array_values(array_unique(array_filter($nameservers)));
    }

    protected function buildContactPayload()
    {
        $contacts = !empty($this->domain_contacts) && is_array($this->domain_contacts) ? $this->domain_contacts : [];
        if (empty($contacts)) {
            $contacts = [
                'registrant' => $this->client_data,
                'admin' => $this->client_data,
                'tech' => $this->client_data,
                'billing' => $this->client_data,
            ];
        }

        return $contacts;
    }

    protected function detectTldId()
    {
        if (!empty($this->tld_id)) {
            return $this->tld_id;
        }
        if (!empty($this->options['tld_id'])) {
            return $this->options['tld_id'];
        }

        return '';
    }

    protected function optionsValue($key, $default = null)
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }

    protected function resolveRemoteDomainId()
    {
        if (!empty($this->options['remote_domain_id'])) {
            return $this->options['remote_domain_id'];
        }

        if (!empty($this->details['extended']['remote_domain_id'])) {
            return $this->details['extended']['remote_domain_id'];
        }

        if (!empty($this->domain_id)) {
            return $this->domain_id;
        }

        if (!empty($this->name)) {
            $response = $this->getDomainDetailsByName($this->name);
            if (is_array($response)) {
                $details = [];
                if (isset($response['details']) && is_array($response['details'])) {
                    $details = $response['details'];
                } else {
                    $details = $response;
                }

                if (isset($details['id']) && !empty($details['id'])) {
                    return $this->rememberRemoteDomainId($details['id'], 'domain_name_lookup');
                }

                if (isset($details[0]['id']) && !empty($details[0]['id'])) {
                    return $this->rememberRemoteDomainId($details[0]['id'], 'domain_name_lookup_list');
                }
            }
        }

        $domains = $this->ListDomains();
        if (is_array($domains) && !empty($this->name)) {
            foreach ($domains as $domain) {
                if (isset($domain['name']) && 0 === strcasecmp($domain['name'], $this->name) && !empty($domain['id'])) {
                    return $this->rememberRemoteDomainId($domain['id'], 'domain_list');
                }
            }
        }

        $this->addError('Unable to resolve remote domain id');
        return false;
    }

    protected function isLookupAvailable($response)
    {
        if (is_string($response)) {
            return strtolower(trim($response)) === 'ok' || stripos($response, 'available') !== false;
        }

        if (is_array($response)) {
            foreach (['status', 'result', 'available', 'success'] as $key) {
                if (!isset($response[$key])) {
                    continue;
                }
                $value = $response[$key];
                if (is_string($value) && in_array(strtolower($value), ['ok', 'available', 'true', '1'], true)) {
                    return true;
                }
                if (is_bool($value) || is_numeric($value)) {
                    return (bool) $value;
                }
            }
        }

        return false;
    }

    protected function extractNameservers($response)
    {
        if (isset($response['nameservers']) && is_array($response['nameservers'])) {
            return $response['nameservers'];
        }
        if (isset($response['nameServer']) && is_array($response['nameServer'])) {
            return $response['nameServer'];
        }
        if (isset($response['ns']) && is_array($response['ns'])) {
            return $response['ns'];
        }

        return is_array($response) ? $response : [];
    }

    protected function extractFirstValue($response, array $keys)
    {
        if (!is_array($response)) {
            return null;
        }
        foreach ($keys as $key) {
            if (isset($response[$key])) {
                return $response[$key];
            }
        }

        return null;
    }

    protected function toBoolValue($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool) $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'enabled', 'locked', 'active', 'ok'], true);
    }

    protected function handleApiFailure($response, $fallback)
    {
        if (is_array($response)) {
            foreach (['error', 'message', 'msg', 'detail', 'description'] as $key) {
                if (!empty($response[$key])) {
                    $this->addError(is_array($response[$key]) ? implode('; ', $response[$key]) : $response[$key]);
                    return;
                }
            }
        }

        if (is_string($response) && trim($response) !== '') {
            $this->addError($response);
            return;
        }

        $this->addError($fallback);
    }

    protected function encodePathSegment($value)
    {
        return str_replace('%2F', '/', rawurlencode((string) $value));
    }

    protected function normalizeDnssecPayload(array $data)
    {
        $map = [
            'key' => ['key', 'key_tag', 'keytag', 'id'],
            'flags' => ['flags', 'flag'],
            'alg' => ['alg', 'algorithm'],
            'digest_type' => ['digest_type', 'digesttype', 'd_type'],
            'digest' => ['digest'],
            'pubkey' => ['pubkey', 'public_key', 'publickey'],
            'protocol' => ['protocol'],
        ];

        $payload = [];
        foreach ($map as $target => $sources) {
            foreach ($sources as $source) {
                if (isset($data[$source]) && $data[$source] !== '') {
                    $payload[$target] = $data[$source];
                    break;
                }
            }
        }

        return $payload;
    }

    protected function normalizeDnssecList($response)
    {
        if (!is_array($response)) {
            return [];
        }

        foreach (['keys', 'dnssec', 'records', 'items'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }

        return isset($response[0]) ? array_values($response) : [$response];
    }

    protected function normalizeDnssecFlags($response)
    {
        if (!is_array($response)) {
            return [];
        }

        foreach (['flags', 'data', 'items'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }

        return isset($response[0]) ? array_values($response) : [$response];
    }

    protected function normalizeDomainPrices($response)
    {
        if (!is_array($response)) {
            return [];
        }

        $tlds = [];
        foreach (['tlds', 'items', 'data', 'details'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                $tlds = $response[$key];
                break;
            }
        }

        if (empty($tlds) && isset($response[0]) && is_array($response[0])) {
            $tlds = $response;
        }

        $result = [];
        foreach ($tlds as $tldData) {
            if (!is_array($tldData) || empty($tldData['tld'])) {
                continue;
            }

            $tld = ltrim((string) $tldData['tld'], '.');
            $periods = [];
            if (isset($tldData['periods']) && is_array($tldData['periods'])) {
                foreach ($tldData['periods'] as $periodData) {
                    if (!is_array($periodData)) {
                        continue;
                    }

                    $period = isset($periodData['period']) ? (string) $periodData['period'] : '';
                    if ($period === '') {
                        continue;
                    }

                    $periods[$period] = [
                        'register' => $this->normalizePriceValue($this->extractFirstValue($periodData, ['register', 'registration', 'register_price'])),
                        'transfer' => $this->normalizePriceValue($this->extractFirstValue($periodData, ['transfer', 'transfer_price'])),
                        'renew' => $this->normalizePriceValue($this->extractFirstValue($periodData, ['renew', 'renewal', 'renew_price'])),
                        'currency' => $this->extractFirstValue($periodData, ['currency', 'currency_code']),
                    ];
                }
            }

            $result[$tld] = [
                'id' => isset($tldData['id']) ? (string) $tldData['id'] : '',
                'tld' => '.'.$tld,
                'periods' => $periods,
                'currency' => $this->extractFirstValue($tldData, ['currency', 'currency_code']),
                'raw' => $tldData,
            ];
        }

        return $result;
    }

    protected function normalizePriceValue($value)
    {
        if (null === $value || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return trim((string) $value);
    }

    protected function unsupportedGlueRecordsAction($action)
    {
        $message = 'Glue records are not supported because no matching HiTechCloud API endpoint is documented';
        $this->addError($message);
        $this->logModuleAction($action, false, [
            ['name' => 'reason', 'from' => '', 'to' => $message],
        ], true);

        return false;
    }

    protected function isRetryableCurlError($errno)
    {
        return in_array((int) $errno, [
            CURLE_OPERATION_TIMEDOUT,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_SEND_ERROR,
            CURLE_RECV_ERROR,
        ], true);
    }

    protected function isRetryableHttpStatus($status)
    {
        return in_array((int) $status, [408, 429, 500, 502, 503, 504], true);
    }

    protected function sleepBeforeRetry($attempt, $overrideDelayMs = null)
    {
        if (null !== $overrideDelayMs) {
            $delayMs = max(0, (int) $overrideDelayMs);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            return;
        }

        $baseDelayMs = max(0, (int) $this->config('Retry Delay'));
        if ($baseDelayMs <= 0) {
            return;
        }

        $delayMs = $baseDelayMs * max(1, (int) $attempt);
        usleep($delayMs * 1000);
    }

    protected function logRetryAttempt(array $context, $attempt, $maxAttempts, $reason, $retryAfterMs = null)
    {
        $change = [
            ['name' => 'method', 'from' => '', 'to' => isset($context['method']) ? $context['method'] : ''],
            ['name' => 'path', 'from' => '', 'to' => isset($context['path']) ? $context['path'] : ''],
            ['name' => 'attempt', 'from' => '', 'to' => (string) $attempt],
            ['name' => 'max_attempts', 'from' => '', 'to' => (string) $maxAttempts],
            ['name' => 'reason', 'from' => '', 'to' => (string) $reason],
        ];

        if (null !== $retryAfterMs) {
            $change[] = ['name' => 'retry_after_ms', 'from' => '', 'to' => (string) $retryAfterMs];
        }

        $this->logModuleAction('API retry attempt', false, $change, false);
    }

    protected function parseRetryAfterMs($headerText)
    {
        if (!is_string($headerText) || trim($headerText) === '') {
            return null;
        }

        if (!preg_match('/^Retry-After\s*:\s*(.+)$/im', $headerText, $matches)) {
            return null;
        }

        $value = trim($matches[1]);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value * 1000;
        }

        $timestamp = strtotime($value);
        if (false === $timestamp) {
            return null;
        }

        $delay = ($timestamp - time()) * 1000;

        return $delay > 0 ? $delay : null;
    }

    protected function extractPremiumData($response)
    {
        $result = [
            'is_premium' => false,
            'price' => null,
            'currency' => null,
        ];

        if (!is_array($response)) {
            return $result;
        }

        $premiumValue = $this->extractNestedFirstValue($response, [
            'premium',
            'is_premium',
            'premium_domain',
            'premiumName',
        ]);
        if (null !== $premiumValue) {
            $result['is_premium'] = $this->toBoolValue($premiumValue);
        }

        $price = $this->extractNestedFirstValue($response, [
            'premium_price',
            'premiumPrice',
            'price',
            'registration_price',
        ]);
        if (null !== $price && '' !== $price) {
            $result['price'] = $price;
            $result['is_premium'] = true;
        }

        $currency = $this->extractNestedFirstValue($response, [
            'currency',
            'currency_code',
            'currencyCode',
        ]);
        if (null !== $currency && '' !== $currency) {
            $result['currency'] = $currency;
        }

        return $result;
    }

    protected function getPremiumOrderData()
    {
        $premium = $this->toBoolValue($this->optionsValue('premium', isset($this->details['premium']) ? $this->details['premium'] : false));
        $price = $this->optionsValue('premium_price', null);
        $currency = $this->optionsValue('currency', null);

        if (isset($this->details['extended']) && is_array($this->details['extended'])) {
            if (!$premium && isset($this->details['extended']['premium'])) {
                $premium = $this->toBoolValue($this->details['extended']['premium']);
            }
            if (null === $price && isset($this->details['extended']['premium_price'])) {
                $price = $this->details['extended']['premium_price'];
            }
            if (null === $currency && isset($this->details['extended']['currency'])) {
                $currency = $this->details['extended']['currency'];
            }
        }

        return [
            'premium' => $premium,
            'price' => $price,
            'currency' => $currency,
        ];
    }

    protected function extractNestedFirstValue($response, array $keys)
    {
        if (!is_array($response)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $response)) {
                return $response[$key];
            }
        }

        foreach ($response as $value) {
            if (is_array($value)) {
                $found = $this->extractNestedFirstValue($value, $keys);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function getDomainDetailsByName($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return false;
        }

        if (isset($this->domainCache['by_name'][$name])) {
            return $this->domainCache['by_name'][$name];
        }

        $response = $this->request('GET', '/domain/name/'.$this->encodePathSegment($name));
        if ($response !== false) {
            $this->domainCache['by_name'][$name] = $response;
            $this->cacheDomainDetailsFromResponse($response);
        }

        return $response;
    }

    protected function getDomainDetailsById($domainId)
    {
        $domainId = trim((string) $domainId);
        if ($domainId === '') {
            return false;
        }

        if (isset($this->domainCache['by_id'][$domainId])) {
            return $this->domainCache['by_id'][$domainId];
        }

        $response = $this->request('GET', '/domain/'.$domainId);
        if ($response !== false) {
            $this->domainCache['by_id'][$domainId] = $response;
            $this->cacheDomainDetailsFromResponse($response);
        }

        return $response;
    }

    protected function getDomainDetailValue($domainId, $field)
    {
        $details = $this->getDomainDetailsById($domainId);
        if (!is_array($details)) {
            return null;
        }

        $candidate = $details;
        if (isset($details['details']) && is_array($details['details'])) {
            $candidate = $details['details'];
        }

        if (isset($candidate[$field])) {
            return [$field => $candidate[$field]];
        }

        return null;
    }

    protected function cacheDomainDetailsFromResponse($response)
    {
        if (!is_array($response)) {
            return;
        }

        $entries = [];
        if (isset($response['details']) && is_array($response['details'])) {
            if (isset($response['details'][0]) && is_array($response['details'][0])) {
                $entries = $response['details'];
            } else {
                $entries = [$response['details']];
            }
        } elseif (isset($response[0]) && is_array($response[0])) {
            $entries = $response;
        } elseif (isset($response['id']) || isset($response['name'])) {
            $entries = [$response];
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (!empty($entry['id'])) {
                $this->domainCache['by_id'][(string) $entry['id']] = $entry;
            }

            if (!empty($entry['name'])) {
                $this->domainCache['by_name'][(string) $entry['name']] = $entry;
            }
        }
    }

    protected function forgetDomainCache($domainId = null, $name = null)
    {
        if (null !== $domainId && $domainId !== '') {
            unset($this->domainCache['by_id'][(string) $domainId]);
        }

        if (null !== $name && trim((string) $name) !== '') {
            unset($this->domainCache['by_name'][(string) trim((string) $name)]);
        }
    }

    protected function rememberRemoteDomainId($remoteDomainId, $source = '')
    {
        $remoteDomainId = trim((string) $remoteDomainId);
        if ($remoteDomainId === '') {
            return false;
        }

        $this->options['remote_domain_id'] = $remoteDomainId;
        if (!isset($this->details['extended']) || !is_array($this->details['extended'])) {
            $this->details['extended'] = [];
        }

        $current = isset($this->details['extended']['remote_domain_id']) ? (string) $this->details['extended']['remote_domain_id'] : '';
        $this->details['extended']['remote_domain_id'] = $remoteDomainId;
        if ($source !== '') {
            $this->details['extended']['remote_domain_id_source'] = $source;
        }

        if ($this->domain_id && $current !== $remoteDomainId) {
            $this->updateExtended($this->details['extended'], 'Persist remote domain id');
        }

        return $remoteDomainId;
    }

    protected function logModuleAction($action, $result, array $change = [], $error = false)
    {
        $this->logAction([
            'action' => $action,
            'result' => (bool) $result,
            'change' => $change,
            'error' => $error,
        ]);
    }

    protected function logModuleFailure($action, array $context, $message, $response = null)
    {
        $change = [
            ['name' => 'method', 'from' => '', 'to' => isset($context['method']) ? $context['method'] : ''],
            ['name' => 'path', 'from' => '', 'to' => isset($context['path']) ? $context['path'] : ''],
            ['name' => 'message', 'from' => '', 'to' => (string) $message],
        ];

        if (null !== $response) {
            $change[] = [
                'name' => 'response',
                'from' => '',
                'to' => is_array($response) ? json_encode($response) : (string) $response,
            ];
        }

        $this->logModuleAction($action, false, $change, true);
    }
}
