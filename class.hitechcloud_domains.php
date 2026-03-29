<?php

class HiTechCloud_Domains extends DomainModule implements DomainLookupInterface, DomainWhoisInterface, DomainBulkLookupInterface, DomainSuggestionsInterface, DomainHideFormInterface, DomainModuleNameservers, DomainModuleAuth, DomainModuleLock, DomainModulePrivacy, DomainModuleContacts, DomainModuleRegistryAutorenew, DomainModuleForwarding, DomainModuleDNS, DomainModuleDNSSEC, DomainModuleListing
{
    protected $moduleName = 'HiTechCloud_Domains';
    protected $version = '1.2.0';
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

    public function Register()
    {
        return $this->createDomainOrder('register');
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
        $this->logAction([
            'action' => 'Renew domain via HiTechCloud',
            'result' => true,
            'change' => [],
            'error' => false,
        ]);

        return true;
    }

    public function Transfer()
    {
        return $this->createDomainOrder('transfer');
    }

    public function lookupDomain($sld, $tld, $settings = [])
    {
        $name = $this->buildDomainName($sld, $tld);
        $response = $this->request('POST', '/domain/lookup', ['name' => $name], [], false);
        if ($response === false) {
            return ['result' => false, 'available' => false, 'domain' => $name];
        }

        $available = $this->isLookupAvailable($response);

        return [
            'result' => true,
            'available' => $available,
            'domain' => $name,
            'premium' => false,
            'message' => $available ? 'ok' : 'unavailable',
            'raw' => $response,
        ];
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

    public function getRegistrarLock()
    {
        $domainId = $this->resolveRemoteDomainId();
        if (!$domainId) {
            return false;
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
            $response = $this->request('GET', '/domain/'.$domainId.'/idprotection');
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

        $response = $this->request('GET', '/domain/'.$domainId.'/contact');
        if ($response === false) {
            return false;
        }

        if (isset($response['contact_info']) && is_array($response['contact_info'])) {
            return $response['contact_info'];
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

        return isset($response['records']) ? $response['records'] : $response;
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

        return isset($response['domains']) ? $response['domains'] : $response;
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

        return isset($response['types']) ? $response['types'] : $response;
    }

    protected function createDomainOrder($action)
    {
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

        $response = $this->request('POST', '/domain/order', $query);
        if ($response === false) {
            return false;
        }

        $this->addDomain('Pending Registration');
        $this->logAction([
            'action' => ucfirst($action).' domain via HiTechCloud order API',
            'result' => true,
            'change' => [],
            'error' => false,
        ]);

        return true;
    }

    protected function request($method, $path, array $query = [], array $headers = [], $auth = true)
    {
        $baseUrl = rtrim((string) $this->config('API URL'), '/');
        if ($baseUrl === '') {
            $this->addError('API URL is not configured');
            return false;
        }

        if ($auth && !$this->ensureAuthenticated()) {
            return false;
        }

        $url = $baseUrl.'/'.ltrim($path, '/');
        if (!empty($query)) {
            $separator = false === strpos($url, '?') ? '?' : '&';
            $url .= $separator.$this->buildQuery($query);
        }

        $ch = curl_init();
        if (false === $ch) {
            $this->addError('Unable to initialize cURL');
            return false;
        }

        $defaultHeaders = ['Accept: application/json'];
        $token = $this->getAccessToken();
        if ($auth && $token && $this->toBoolValue($this->config('Use Bearer Token'))) {
            $defaultHeaders[] = 'Authorization: Bearer '.$token;
        }

        foreach ($headers as $header) {
            $defaultHeaders[] = $header;
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
            $this->addError('HTTP request failed: '.curl_error($ch));
            curl_close($ch);
            return false;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $body = substr($raw, $headerSize);
        $decoded = json_decode($body, true);
        $response = JSON_ERROR_NONE === json_last_error() ? $decoded : trim($body);

        if ($status >= 400) {
            $this->handleApiFailure($response, 'HTTP '.$status.' returned from '.$path);
            return false;
        }

        if (is_array($response) && isset($response['success']) && !$response['success']) {
            $this->handleApiFailure($response, 'API returned unsuccessful response');
            return false;
        }

        return $response;
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
            $response = $this->request('GET', '/domain/name/'.$this->encodePathSegment($this->name));
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
}
