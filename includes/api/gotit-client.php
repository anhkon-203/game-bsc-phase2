<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('game_bsc_gotit_source_config')) {
    function game_bsc_gotit_source_config() {
        $config = [
            'prefix' => '000578',
            'authentication_method' => '',
            'default_order_name' => 'BSC Game Voucher',
            'default_order_name_test' => 'BSC Game Voucher Test',
            'default_expiry_days' => 30,
            'private_key' => '',
            'public_key' => '',
            'excluded_product_ids' => '',
            'min_price_value' => 1000,
        ];

        return apply_filters('game_bsc_gotit_source_config', $config);
    }
}

if (!function_exists('game_bsc_gotit_source_value')) {
    function game_bsc_gotit_source_value($key, $default = null) {
        $config = game_bsc_gotit_source_config();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }
}

/**
 * Got It API client (server-side only).
 */
class Game_BSC_GotIt_Client {
    private $api_key;
    private $base_url;
    private $prefix;
    private $endpoint_prefix;
    private $authentication_method;

    private function normalize_api_key($value) {
        $raw = is_string($value) ? $value : '';
        // Remove invisible/spacing chars that often appear when copy/paste from docs.
        return preg_replace('/\s+/u', '', trim($raw));
    }

    public function __construct() {
        $this->api_key = $this->normalize_api_key(get_option('game_bsc_gotit_api_key', ''));
        $environment = get_option('game_bsc_gotit_environment', 'staging');
        $this->base_url = ($environment === 'production')
            ? 'https://openapi.gotit.vn/'
            : 'https://openapi-stg.gotit.vn/';
        $this->prefix = sanitize_text_field((string) game_bsc_gotit_source_value('prefix', '000578'));
        $this->authentication_method = $this->normalize_authentication_method((string) game_bsc_gotit_source_value('authentication_method', ''));

        $saved_endpoint_prefix = sanitize_text_field((string) get_option('game_bsc_gotit_endpoint_prefix', 'api'));
        $this->endpoint_prefix = in_array($saved_endpoint_prefix, ['api', 'biz'], true) ? $saved_endpoint_prefix : 'api';
    }

    private function normalize_authentication_method($value) {
        $raw = is_string($value) ? $value : '';
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $normalized = strtolower((string) preg_replace('/[\s_\-]+/', '', $raw));

        // Canonical values from Got It docs: none, otpsms, otpemail, coverlink, password.
        switch ($normalized) {
            case 'none':
            case 'noauth':
            case 'apikey':
            case 'signature':
            case 'rsa':
            case 'rsasignature':
            case 'token':
                return 'none';
            case 'otpsms':
            case 'sms':
            case 'otp':
                return 'otpsms';
            case 'otpemail':
            case 'email':
                return 'otpemail';
            case 'coverlink':
                return 'coverlink';
            case 'password':
            case 'pwd':
                return 'password';
            default:
                return '';
        }
    }

    private function sanitize_query_param_key($key) {
        $raw_key = trim((string) $key);
        if ($raw_key === '') {
            return '';
        }

        // Preserve Got It camelCase params (e.g. categoryId, pageSize).
        return (string) preg_replace('/[^A-Za-z0-9_]/', '', $raw_key);
    }

    private function normalize_fields_query_value($fields) {
        if (is_array($fields)) {
            $normalized_fields = [];
            foreach ($fields as $field) {
                $field_name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $field);
                if ($field_name !== '') {
                    $normalized_fields[] = $field_name;
                }
            }

            $normalized_fields = array_values(array_unique($normalized_fields));
            return !empty($normalized_fields) ? implode(',', $normalized_fields) : '';
        }

        if (is_string($fields)) {
            return (string) preg_replace('/[^a-zA-Z0-9_,]/', '', $fields);
        }

        return '';
    }

    private function build_master_data_query($page = 1, $page_size = 100, $fields = []) {
        $query = [
            'page' => max(1, (int) $page),
            'pageSize' => min(100, max(1, (int) $page_size)),
        ];

        $fields_value = $this->normalize_fields_query_value($fields);
        if ($fields_value !== '') {
            $query['fields'] = $fields_value;
        }

        return $query;
    }

    private function request_client_group_endpoint($resource, $query_params = []) {
        $resource = trim((string) $resource, '/');
        if ($resource === '') {
            return ['success' => false, 'error' => 'Invalid client resource.', 'http_code' => 0];
        }

        $client_result = $this->request_with_prefix_fallback('GET', 'v5.0/client/' . $resource, null, $query_params);
        if (!empty($client_result['success']) || !$this->is_forbidden_or_not_found($client_result)) {
            return $client_result;
        }

        return $this->request_with_prefix_fallback('GET', 'v5.0/' . $resource, null, $query_params);
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    public function get_base_url() {
        return $this->base_url;
    }

    public function get_prefix() {
        return $this->prefix;
    }

    public function get_auth_diagnostics() {
        $len = strlen((string) $this->api_key);
        return [
            'base_url' => $this->base_url,
            'endpoint_prefix' => $this->endpoint_prefix,
            'authentication_method' => $this->authentication_method,
            'api_key_len' => $len,
            'api_key_fingerprint' => $len > 0 ? substr(hash('sha256', (string) $this->api_key), 0, 12) : '',
        ];
    }

    public function generate_transaction_ref_id($user_id = 0, $voucher_post_id = 0) {
        $timestamp = gmdate('YmdHis');
        $random = wp_rand(1000, 9999);
        return sprintf('%s_%s_%d_%d_%d', $this->prefix, $timestamp, (int) $user_id, (int) $voucher_post_id, $random);
    }

    public function get_categories($page = 1, $page_size = 100, $fields = []) {
        $query = $this->build_master_data_query($page, $page_size, $fields);
        return $this->request_client_group_endpoint('categories', $query);
    }

    /**
     * Lấy chi tiết 1 product theo productId.
     * Endpoint: GET /api/v5.0/products/:productId
     * Response bao gồm categoryId mà /products list KHÔNG trả.
     */
    public function get_product_detail($product_id) {
        $product_id = (int) $product_id;
        if ($product_id < 1) {
            return ['success' => false, 'error' => 'Invalid product ID.', 'http_code' => 0];
        }
        return $this->request_with_prefix_fallback('GET', 'v5.0/products/' . $product_id);
    }

    public function get_brands($page = 1, $page_size = 100, $fields = []) {
        $query = $this->build_master_data_query($page, $page_size, $fields);
        return $this->request_client_group_endpoint('brands', $query);
    }

    public function get_products($page = 1, $page_size = 100, $extra_filters = []) {
        $query = [];

        if (is_array($extra_filters)) {
            foreach ($extra_filters as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $query_key = $this->sanitize_query_param_key($key);
                if ($query_key === '') {
                    continue;
                }

                $query[$query_key] = is_scalar($value) ? (string) $value : $value;
            }
        }

        // Keep request aligned with Got It /biz endpoint behavior.
        // If querying by ids, avoid sending page/pageSize unless caller explicitly provided.
        if (!isset($query['ids'])) {
            $query['page'] = max(1, (int) $page);
            $query['pageSize'] = min(100, max(1, (int) $page_size));
        }

        $result = $this->request_with_prefix_fallback('GET', 'v5.0/products', null, $query);

        // Some Got It environments reject page/pageSize for /biz/v5.0/products.
        if (empty($result['success']) && (int) ($result['http_code'] ?? 0) === 400) {
            $query_without_paging = $query;
            unset($query_without_paging['page'], $query_without_paging['pageSize']);
            $retry = $this->request_with_prefix_fallback('GET', 'v5.0/products', null, $query_without_paging);
            if (!empty($retry['success'])) {
                return $retry;
            }
        }

        return $result;
    }

    /**
     * Lấy danh sách cửa hàng áp dụng theo product.
     * Endpoint: GET /api/v5.0/products/:productId/stores
     */
    public function get_product_stores($product_id, $page = 1, $page_size = 100, $extra_filters = []) {
        $product_id = (int) $product_id;
        if ($product_id < 1) {
            return ['success' => false, 'error' => 'Invalid product ID.', 'http_code' => 0];
        }

        $query = [
            'page' => max(1, (int) $page),
            'pageSize' => min(100, max(1, (int) $page_size)),
        ];

        if (is_array($extra_filters)) {
            foreach ($extra_filters as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $query_key = $this->sanitize_query_param_key($key);
                if ($query_key === '') {
                    continue;
                }

                $query[$query_key] = is_scalar($value) ? (string) $value : $value;
            }
        }

        return $this->request_with_prefix_fallback('GET', 'v5.0/products/' . $product_id . '/stores', null, $query);
    }

    /**
     * Lấy danh sách categories từ Got It và trả về map:
     * id => ['name'=>..., 'slug'=>..., 'image'=>...]
     * Got It API trả về: { "categories": [{ "id": 1, "name": "Nhà Hàng", "image": "...", "slug": "restaurant-new", ... }] }
     *
     * @return array  [id => ['name' => string, 'slug' => string, 'image' => string], ...]
     */
    public function get_categories_map() {
        $result = $this->get_categories();
        if (empty($result['success']) || !is_array($result['data'])) {
            return [];
        }

        $list = $result['data'];

        // Got It trả về: { "categories": [...], "pagination": {...} }
        if (isset($list['categories']) && is_array($list['categories'])) {
            $list = $list['categories'];
        } elseif (isset($list['data']) && is_array($list['data'])) {
            $list = $list['data'];
        }

        $map = [];
        foreach ($list as $cat) {
            if (!is_array($cat)) continue;
            // Got It dùng "id" và "name" (không phải categoryId/categoryName)
            $id   = (int) ($cat['id'] ?? 0);
            $name = sanitize_text_field((string) ($cat['name'] ?? ''));
            $slug = sanitize_text_field((string) ($cat['slug'] ?? ''));
            $image = esc_url_raw((string) ($cat['image'] ?? ''));
            if ($id > 0 && $name !== '') {
                $map[$id] = ['name' => $name, 'slug' => $slug, 'image' => $image];
            }
        }

        return $map;
    }

    public function check_voucher_status($transaction_ref_id) {
        $transaction_ref_id = sanitize_text_field((string) $transaction_ref_id);
        if ($transaction_ref_id === '') {
            return ['success' => false, 'error' => 'Transaction ref ID is required.', 'http_code' => 0];
        }

        $endpoint = 'v5.0/vouchers/multiple/status/' . rawurlencode($transaction_ref_id);
        $query = [];

        // Some Got It accounts enforce RSA signature for ref-id checks.
        $signature = $this->create_ref_signature($transaction_ref_id);
        if (!empty($signature)) {
            $query['signature'] = $signature;
        }

        return $this->request_with_prefix_fallback('GET', $endpoint, null, $query);
    }

    /**
     * Get voucher details by transactionRefId.
     * Endpoint: GET /api|biz/v5.0/vouchers?transactionRefId=...
     */
    public function get_vouchers_by_ref_id($transaction_ref_id, $page = 1, $page_size = 20, $fields = ['productInfo', 'usedInfo']) {
        $transaction_ref_id = sanitize_text_field((string) $transaction_ref_id);
        if ($transaction_ref_id === '') {
            return ['success' => false, 'error' => 'Transaction ref ID is required.', 'http_code' => 0];
        }

        $query = [
            'transactionRefId' => $transaction_ref_id,
            'page' => max(1, (int) $page),
            'pageSize' => min(100, max(1, (int) $page_size)),
        ];

        if (is_array($fields)) {
            $normalized_fields = [];
            foreach ($fields as $field) {
                $field_name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $field);
                if ($field_name !== '') {
                    $normalized_fields[] = $field_name;
                }
            }
            $normalized_fields = array_values(array_unique($normalized_fields));
            if (!empty($normalized_fields)) {
                $query['fields'] = implode(',', $normalized_fields);
            }
        } elseif (is_string($fields)) {
            $fields_value = preg_replace('/[^a-zA-Z0-9_,]/', '', $fields);
            if ($fields_value !== '') {
                $query['fields'] = $fields_value;
            }
        }

        $signature = $this->create_ref_signature($transaction_ref_id);
        if (!empty($signature)) {
            $query['signature'] = $signature;
        }

        return $this->request_with_prefix_fallback('GET', 'v5.0/vouchers', null, $query);
    }

    public function issue_voucher($params) {
        $base_body = [
            'productId' => (int) ($params['productId'] ?? 0),
            'productPriceId' => (int) ($params['productPriceId'] ?? 0),
            'quantity' => (int) ($params['quantity'] ?? 1),
            'orderName' => sanitize_text_field($params['orderName'] ?? ''),
            'expiryDate' => sanitize_text_field($params['expiryDate'] ?? ''),
            'transactionRefId' => sanitize_text_field($params['transactionRefId'] ?? ''),
        ];

        $receiver_name = sanitize_text_field($params['receiverName'] ?? '');
        if ($receiver_name !== '') {
            $base_body['receiverName'] = $receiver_name;
        }

        $phone = preg_replace('/\D+/', '', (string) ($params['phone'] ?? ''));
        if ($phone !== '') {
            $base_body['phone'] = $phone;
        }

        $email = sanitize_email((string) ($params['email'] ?? ''));
        if ($email !== '') {
            $base_body['email'] = $email;
        }

        $password = preg_replace('/\D+/', '', (string) ($params['password'] ?? ''));
        if (preg_match('/^\d{6}$/', (string) $password) === 1) {
            $base_body['password'] = $password;
        }

        $signature = $this->create_signature($base_body);
        $body = $base_body;
        if (!empty($signature)) {
            $body['signature'] = $signature;
        }

        $explicit_auth_method = $this->normalize_authentication_method($params['authenticationMethod'] ?? '');
        $auth_method = $explicit_auth_method !== ''
            ? $explicit_auth_method
            : $this->resolve_issue_authentication_method(!empty($signature), $base_body);
        if ($auth_method !== '') {
            $body['authenticationMethod'] = $auth_method;
        }

        $auth_debug = [
            'configured_method' => $this->authentication_method,
            'initial_key' => $auth_method !== '' ? 'authenticationMethod' : '',
            'initial_method' => $auth_method,
            'has_signature' => !empty($signature),
            'attempts' => [],
        ];

        $result = $this->request_with_prefix_fallback('POST', 'v5.0/vouchers/v', $body);
        $auth_debug['attempts'][] = [
            'key' => $auth_method !== '' ? 'authenticationMethod' : '',
            'value' => $auth_method,
            'status_code' => (int) ($result['status_code'] ?? 0),
            'http_code' => (int) ($result['http_code'] ?? 0),
        ];

        if (!empty($result['success']) || !$this->is_auth_method_retryable($result)) {
            $result['auth_debug'] = $auth_debug;
            return $result;
        }

        $retry_payloads = $this->build_issue_auth_retry_payloads($body, $auth_method);
        $attempt_count = 0;
        foreach ($retry_payloads as $retry_payload) {
            if ($attempt_count >= 12) {
                break;
            }

            $attempt_count++;
            $retry_result = $this->request_with_prefix_fallback('POST', 'v5.0/vouchers/v', $retry_payload['body']);
            $auth_debug['attempts'][] = [
                'key' => (string) $retry_payload['key'],
                'value' => (string) $retry_payload['value'],
                'status_code' => (int) ($retry_result['status_code'] ?? 0),
                'http_code' => (int) ($retry_result['http_code'] ?? 0),
            ];

            if (!empty($retry_result['success']) || !$this->is_auth_method_retryable($retry_result)) {
                $retry_result['auth_debug'] = $auth_debug;
                return $retry_result;
            }

            $result = $retry_result;
        }

        $result['auth_debug'] = $auth_debug;
        return $result;
    }

    private function resolve_issue_authentication_method($has_signature, $body = []) {
        if ($this->authentication_method !== '') {
            return $this->authentication_method;
        }

        // Prefer context-derived method when caller provides otp/password hints.
        if (!empty($body['password']) && preg_match('/^\d{6}$/', (string) $body['password']) === 1) {
            return 'password';
        }
        if (!empty($body['phone'])) {
            return 'otpsms';
        }
        if (!empty($body['email'])) {
            return 'otpemail';
        }

        return 'none';
    }

    private function is_auth_method_retryable($result) {
        $status_code = (int) ($result['status_code'] ?? 0);
        if (in_array($status_code, [4083, 4084], true)) {
            return true;
        }

        $error_text = strtolower((string) ($result['error'] ?? ''));
        if ($error_text !== '' && strpos($error_text, 'authentication method') !== false) {
            return true;
        }

        return false;
    }

    private function build_issue_auth_retry_payloads($base_body, $current_method) {
        $normalized_current = $this->normalize_authentication_method($current_method);

        $method_candidates = ['none', 'coverlink'];
        if (!empty($base_body['phone'])) {
            $method_candidates[] = 'otpsms';
        }
        if (!empty($base_body['email'])) {
            $method_candidates[] = 'otpemail';
        }
        if (!empty($base_body['password']) && preg_match('/^\d{6}$/', (string) $base_body['password']) === 1) {
            $method_candidates[] = 'password';
        }

        $method_candidates = array_values(array_unique(array_filter(array_map([$this, 'normalize_authentication_method'], $method_candidates))));

        $variants = [];
        foreach ($method_candidates as $candidate_value) {
            if ($candidate_value === $normalized_current) {
                continue;
            }

            $payload = $base_body;
            $payload['authenticationMethod'] = $candidate_value;
            $variants[] = [
                'key' => 'authenticationMethod',
                'value' => $candidate_value,
                'body' => $payload,
            ];
        }

        return $variants;
    }

    private function is_forbidden_or_not_found($result) {
        $http_code = (int) ($result['http_code'] ?? 0);
        $error_text = strtolower((string) ($result['error'] ?? ''));

        if (in_array($http_code, [401, 403, 404], true)) {
            return true;
        }

        // Some gateways can return 400 with message "Forbidden".
        if ($http_code === 400 && strpos($error_text, 'forbidden') !== false) {
            return true;
        }

        return false;
    }

    private function request_with_prefix_fallback($method, $endpoint_without_prefix, $body = null, $query_params = []) {
        $primary = $this->endpoint_prefix;
        $secondary = $primary === 'api' ? 'biz' : 'api';

        $prefixes = [$primary, $secondary];
        $last_result = null;
        $tried = [];

        foreach ($prefixes as $prefix) {
            $endpoint = trim($prefix, '/') . '/' . ltrim((string) $endpoint_without_prefix, '/');
            $result = $this->request($method, $endpoint, $body, $query_params);
            $tried[] = $result['url'] ?? '';

            if (!empty($result['success'])) {
                $result['tried_urls'] = $tried;
                return $result;
            }

            $last_result = $result;
            if (!$this->is_forbidden_or_not_found($result)) {
                break;
            }
        }

        if (is_array($last_result)) {
            $last_result['tried_urls'] = $tried;
        }
        return $last_result;
    }

    public function verify_response_signature($response_body, $signature_header) {
        $public_key_pem = (string) game_bsc_gotit_source_value('public_key', '');
        if (empty($public_key_pem) || empty($signature_header)) {
            return false;
        }

        $public_key = openssl_pkey_get_public($public_key_pem);
        if (!$public_key) {
            error_log('[GotIt] Cannot load public key for signature verify');
            return false;
        }

        $signature = base64_decode((string) $signature_header, true);
        if ($signature === false) {
            return false;
        }

        return openssl_verify((string) $response_body, $signature, $public_key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function create_signature($payload) {
        $private_key_pem = (string) game_bsc_gotit_source_value('private_key', '');
        if (empty($private_key_pem)) {
            return null;
        }

        $private_key = openssl_pkey_get_private($private_key_pem);
        if (!$private_key) {
            error_log('[GotIt] Cannot load private key for signing');
            return null;
        }

        // Current signed string follows Got It vouchers/v style from docs.
        $access_code = implode('|', [
            $this->api_key,
            (string) ($payload['orderName'] ?? ''),
            (string) ($payload['expiryDate'] ?? ''),
            (string) ($payload['transactionRefId'] ?? ''),
        ]);

        $signature = '';
        $ok = openssl_sign($access_code, $signature, $private_key, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            error_log('[GotIt] Failed to sign request payload');
            return null;
        }

        return base64_encode($signature);
    }

    private function create_ref_signature($transaction_ref_id) {
        $transaction_ref_id = trim((string) $transaction_ref_id);
        if ($transaction_ref_id === '') {
            return null;
        }

        $private_key_pem = (string) game_bsc_gotit_source_value('private_key', '');
        if (empty($private_key_pem)) {
            return null;
        }

        $private_key = openssl_pkey_get_private($private_key_pem);
        if (!$private_key) {
            error_log('[GotIt] Cannot load private key for ref signature');
            return null;
        }

        $access_code = $this->api_key . '|' . $transaction_ref_id;

        $signature = '';
        $ok = openssl_sign($access_code, $signature, $private_key, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            error_log('[GotIt] Failed to sign ref payload');
            return null;
        }

        return base64_encode($signature);
    }

    private function request($method, $endpoint, $body = null, $query_params = []) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'data' => null,
                'status_code' => 0,
                'error' => 'Got It API key is not configured.',
                'raw' => '',
                'http_code' => 0,
            ];
        }

        $url = rtrim($this->base_url, '/') . '/' . ltrim((string) $endpoint, '/');
        if (!empty($query_params)) {
            $url = add_query_arg($query_params, $url);
        }

        $normalized_method = strtoupper((string) $method);
        $headers = [
            'Accept' => 'application/json',
            'Accept-Language' => 'vi',
            'X-GI-Authorization' => $this->api_key,
        ];

        $args = [
            'method' => $normalized_method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'BSC-Game-GotIt/1.0',
        ];

        if (!empty($body) && $normalized_method === 'POST') {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $started_at = microtime(true);
        $response = wp_remote_request($url, $args);
        $elapsed_ms = (int) round((microtime(true) - $started_at) * 1000);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'data' => null,
                'status_code' => 0,
                'error' => $response->get_error_message(),
                'raw' => '',
                'http_code' => 0,
                'elapsed_ms' => $elapsed_ms,
            ];
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw_body, true);
        $status_code = (is_array($decoded) && isset($decoded['statusCode'])) ? (int) $decoded['statusCode'] : 0;

        $error_message = null;
        if (is_array($decoded)) {
            foreach (['statusMessage', 'message', 'error', 'errorMessage', 'msg'] as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    $error_message = trim($decoded[$key]);
                    break;
                }
            }
        }

        if ($error_message === null && !($http_code >= 200 && $http_code < 300)) {
            if ($http_code === 403) {
                $error_message = 'Forbidden';
            } else {
                $error_message = 'Unknown API error';
            }
        }

        $success = false;
        if (in_array($status_code, [2000, 2004], true)) {
            $success = true;
        } elseif ($status_code === 0 && $http_code >= 200 && $http_code < 300) {
            // Fallback for endpoints that do not include statusCode in JSON body.
            $success = true;
        }

        return [
            'success' => $success,
            'data' => (is_array($decoded) ? ($decoded['data'] ?? $decoded) : null),
            'status_code' => $status_code,
            'error' => $error_message,
            'raw' => $raw_body,
            'http_code' => $http_code,
            'elapsed_ms' => $elapsed_ms,
            'url' => $url,
        ];
    }
}

function game_bsc_gotit_client() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Game_BSC_GotIt_Client();
    }
    return $instance;
}
