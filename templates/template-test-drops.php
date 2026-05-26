<?php
/**
 * Test page cho tính năng rơi mảnh hiện vật.
 * Truy cập: /game-bsc-test-drops
 *
 * Trang này cho phép:
 * - Xem danh sách hiện vật + thông tin kỳ hiện tại
 * - Giả lập rơi mảnh cho user tùy chọn
 * - Xem trạng thái mảnh của user
 * - Test pity system
 * - Test giới hạn 1 bộ / user
 *
 * CHỈ dùng cho development/testing. Chặn truy cập trên production.
 */
if (!defined('ABSPATH')) exit;

// Chặn nếu không phải admin hoặc không bật debug
if (!current_user_can('administrator') && !defined('WP_DEBUG')) {
    wp_die('Bạn không có quyền truy cập trang này.');
}

global $wpdb;
$prefix = $wpdb->prefix . 'game_';
$tz = new DateTimeZone('Asia/Ho_Chi_Minh');
$now = new DateTimeImmutable('now', $tz);

// ===== XỬ LÝ ACTION =====
$message = '';
$message_type = '';
// Determine active tab from query param or POST param
$active_tab = 'tab-drops';
if (isset($_GET['tab'])) {
    $active_tab = 'tab-' . sanitize_key($_GET['tab']);
} elseif (isset($_POST['tab'])) {
    $active_tab = 'tab-' . sanitize_key($_POST['tab']);
}

// Override active tab based on POST action to ensure robustness on form actions
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'bsc_external_api_call') {
        $active_tab = 'tab-api';
    } elseif ($_POST['action'] === 'test_mission_logic') {
        $active_tab = 'tab-missions';
    }
}

if (!in_array($active_tab, ['tab-drops', 'tab-missions', 'tab-api'], true)) {
    $active_tab = 'tab-drops';
}
$bsc_external_result = null;
$bsc_external_form = [
    'base' => 'apinoibo',
    'endpoint' => '/api/MarketData/GetCustomerByCustodycd',
    'method' => 'POST',
    'stk' => '',
    'stk_param' => 'custodycd',
    'stk_location' => 'body',
    'body_type' => 'form',
    'body' => '{"custodycd":"{{stk}}"}',
    'headers' => '',
    'use_access_token' => '0',
    'timeout' => '15',
];

if (!function_exists('game_bsc_test_replace_placeholders')) {
    function game_bsc_test_replace_placeholders($value, $stk) {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = game_bsc_test_replace_placeholders($item, $stk);
            }
            return $value;
        }

        if (is_string($value)) {
            return str_replace('{{stk}}', $stk, $value);
        }

        return $value;
    }
}

if (!function_exists('game_bsc_test_parse_headers')) {
    function game_bsc_test_parse_headers($raw_headers) {
        $headers = [];
        $raw_headers = trim((string)$raw_headers);

        if ($raw_headers === '') {
            return $headers;
        }

        $json_headers = json_decode($raw_headers, true);
        if (is_array($json_headers)) {
            foreach ($json_headers as $key => $value) {
                $key = trim((string)$key);
                if ($key !== '') {
                    $headers[$key] = (string)$value;
                }
            }
            return $headers;
        }

        foreach (preg_split('/\r\n|\r|\n/', $raw_headers) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            if ($key !== '') {
                $headers[$key] = trim($value);
            }
        }

        return $headers;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'game_test_drops')) {
        wp_die('Nonce không hợp lệ.');
    }

    $action = sanitize_text_field($_POST['action']);

    if ($active_tab === 'tab-drops' && $action === 'simulate_drop') {
        $test_user_id = (int)($_POST['user_id'] ?? 0);
        if ($test_user_id > 0) {
            // session_id INT UNSIGNED max ~4.2B → dùng time() (~1.7B) + random order
            $fake_session_id = time();
            $fake_order = rand(10000, 2147483647);
            $result = game_get_random_reward($test_user_id, $fake_session_id, $fake_order);
            $message = '<strong>Kết quả rơi mảnh cho User #' . $test_user_id . ':</strong><br>';
            $message .= '<pre>' . print_r($result, true) . '</pre>';
            $message_type = ($result['type'] === 'PIECE') ? 'success' : 'info';
        } else {
            $message = 'Vui lòng chọn user.';
            $message_type = 'error';
        }
    }

    if ($active_tab === 'tab-drops' && $action === 'grant_pieces') {
        $test_user_id = (int)($_POST['user_id'] ?? 0);
        $artifact_id  = (int)($_POST['artifact_id'] ?? 0);
        $pieces_to_grant = isset($_POST['pieces']) ? array_map('intval', $_POST['pieces']) : [];

        if ($test_user_id > 0 && $artifact_id > 0 && !empty($pieces_to_grant)) {
            foreach ($pieces_to_grant as $piece_id) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, qty FROM {$prefix}user_pieces WHERE user_id = %d AND piece_id = %d",
                    $test_user_id, $piece_id
                ));

                if ($existing) {
                    $wpdb->update($prefix . 'user_pieces', ['qty' => $existing->qty + 1], ['id' => $existing->id]);
                } else {
                    $wpdb->insert($prefix . 'user_pieces', [
                        'user_id' => $test_user_id,
                        'artifact_id' => $artifact_id,
                        'piece_id' => $piece_id,
                        'qty' => 1
                    ]);
                }
            }
            $message = 'Đã gán ' . count($pieces_to_grant) . ' mảnh cho User #' . $test_user_id;
            $message_type = 'success';
        }
    }

    if ($active_tab === 'tab-drops' && $action === 'reset_user_pieces') {
        $test_user_id = (int)($_POST['user_id'] ?? 0);
        if ($test_user_id > 0) {
            $wpdb->delete($prefix . 'user_pieces', ['user_id' => $test_user_id]);
            $wpdb->delete($prefix . 'user_artifact_redemptions', ['user_id' => $test_user_id]);
            $message = 'Đã xoá toàn bộ mảnh + redemption của User #' . $test_user_id;
            $message_type = 'success';
        }
    }

    if ($active_tab === 'tab-drops' && $action === 'reset_all_data') {
        $wpdb->query("TRUNCATE TABLE {$prefix}user_pieces");
        $wpdb->query("TRUNCATE TABLE {$prefix}user_pieces_ledger");
        $wpdb->query("TRUNCATE TABLE {$prefix}user_artifact_redemptions");
        $wpdb->query("TRUNCATE TABLE {$prefix}drop_logs");
        $message = '🗑️ Đã reset toàn bộ: user_pieces, user_pieces_ledger, user_artifact_redemptions, drop_logs';
        $message_type = 'success';
    }

    if ($active_tab === 'tab-drops' && $action === 'create_test_users') {
        $count = (int)($_POST['count'] ?? 10);
        $count = min(max(1, $count), 50);
        $created = 0;
        $test_names = ['Nguyễn Văn', 'Trần Thị', 'Lê Hoàng', 'Phạm Minh', 'Hoàng Thanh', 'Vũ Đức', 'Đặng Thùy', 'Bùi Quang', 'Đỗ Hải', 'Ngô Bảo', 'Dương Kim', 'Lý Thị', 'Hồ Anh', 'Phan Văn', 'Tô Minh'];

        for ($i = 0; $i < $count; $i++) {
            $name_prefix = $test_names[array_rand($test_names)];
            $suffix = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
            $name = $name_prefix . ' Test_' . $suffix;
            $external_id = 'TEST_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

            $ok = $wpdb->insert($prefix . 'users', [
                'provider'         => 'test',
                'external_user_id' => $external_id,
                'name'             => $name,
                'status'           => 1,
            ]);
            if ($ok) $created++;
        }
        $message = "Đã tạo {$created}/{$count} user test.";
        $message_type = 'success';
    }

    if ($active_tab === 'tab-api' && $action === 'bsc_external_api_call') {
        $active_tab = 'tab-api';
        $bsc_external_form = [
            'base' => sanitize_key($_POST['bsc_base'] ?? 'apinoibo'),
            'endpoint' => trim((string)wp_unslash($_POST['bsc_endpoint'] ?? '')),
            'method' => strtoupper(sanitize_text_field($_POST['bsc_method'] ?? 'GET')),
            'stk' => sanitize_text_field(wp_unslash($_POST['bsc_stk'] ?? '')),
            'stk_param' => sanitize_key($_POST['bsc_stk_param'] ?? 'custodycd'),
            'stk_location' => sanitize_key($_POST['bsc_stk_location'] ?? 'body'),
            'body_type' => sanitize_key($_POST['bsc_body_type'] ?? 'form'),
            'body' => (string)wp_unslash($_POST['bsc_body'] ?? ''),
            'headers' => (string)wp_unslash($_POST['bsc_headers'] ?? ''),
            'use_access_token' => !empty($_POST['bsc_use_access_token']) ? '1' : '0',
            'timeout' => (string)min(max((int)($_POST['bsc_timeout'] ?? 15), 1), 60),
        ];

        $base_urls = [
            'apiurl' => (string)get_field('cdapi_ip_address_apiurl', 'option'),
            'apinoibo' => (string)get_option('game_bsc_api_base_url'),
            'tradingserver' => (string)get_option('game_bsc_trading_server'),
            'full' => '',
        ];

        $method = in_array($bsc_external_form['method'], ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)
            ? $bsc_external_form['method']
            : 'GET';
        $endpoint = $bsc_external_form['endpoint'];
        $base_url = $base_urls[$bsc_external_form['base']] ?? '';

        if ($endpoint === '') {
            $bsc_external_result = [
                'ok' => false,
                'error' => 'Endpoint is required.',
            ];
        } else {
            if (preg_match('#^https?://#i', $endpoint)) {
                $url = $endpoint;
            } else {
                $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');
            }

            $headers = game_bsc_test_parse_headers($bsc_external_form['headers']);
            $headers['Accept'] = $headers['Accept'] ?? 'application/json';

            if ($bsc_external_form['use_access_token'] === '1' && !empty($_COOKIE['access_token'])) {
                $headers['Authorization'] = 'Bearer ' . sanitize_text_field(wp_unslash($_COOKIE['access_token']));
            }

            $stk = $bsc_external_form['stk'];
            $stk_param = $bsc_external_form['stk_param'] ?: 'custodycd';
            $stk_location = $bsc_external_form['stk_location'];
            $body_type = in_array($bsc_external_form['body_type'], ['json', 'form'], true)
                ? $bsc_external_form['body_type']
                : 'form';

            if ($stk !== '' && in_array($stk_location, ['query', 'both'], true)) {
                $url = add_query_arg($stk_param, $stk, $url);
            }

            $args = [
                'method' => $method,
                'timeout' => (int)$bsc_external_form['timeout'],
                'headers' => $headers,
            ];

            if (!in_array($method, ['GET', 'HEAD'], true)) {
                $raw_body = trim($bsc_external_form['body']);
                $body_data = [];

                if ($raw_body !== '') {
                    $decoded_body = json_decode($raw_body, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $bsc_external_result = [
                            'ok' => false,
                            'error' => 'Body must be valid JSON: ' . json_last_error_msg(),
                        ];
                    } elseif (is_array($decoded_body)) {
                        $body_data = game_bsc_test_replace_placeholders($decoded_body, $stk);
                    }
                }

                if ($bsc_external_result === null && $stk !== '' && in_array($stk_location, ['body', 'both'], true)) {
                    $body_data[$stk_param] = $stk;
                }

                if ($bsc_external_result === null) {
                    if ($body_type === 'json') {
                        $args['headers']['Content-Type'] = $args['headers']['Content-Type'] ?? 'application/json';
                        $args['body'] = wp_json_encode($body_data);
                    } else {
                        $args['body'] = $body_data;
                    }
                }
            }

            if ($bsc_external_result === null) {
                $started_at = microtime(true);
                $response = wp_remote_request($url, $args);
                $elapsed_ms = (int)round((microtime(true) - $started_at) * 1000);

                if (is_wp_error($response)) {
                    $bsc_external_result = [
                        'ok' => false,
                        'url' => $url,
                        'method' => $method,
                        'elapsed_ms' => $elapsed_ms,
                        'error' => $response->get_error_message(),
                    ];
                } else {
                    $response_body = wp_remote_retrieve_body($response);
                    $decoded_response = json_decode($response_body, true);
                    $response_headers = wp_remote_retrieve_headers($response);
                    $response_headers = is_object($response_headers) && method_exists($response_headers, 'getAll')
                        ? $response_headers->getAll()
                        : (array)$response_headers;
                    $bsc_external_result = [
                        'ok' => true,
                        'url' => $url,
                        'method' => $method,
                        'status' => wp_remote_retrieve_response_code($response),
                        'message' => wp_remote_retrieve_response_message($response),
                        'elapsed_ms' => $elapsed_ms,
                        'headers' => $response_headers,
                        'body' => json_last_error() === JSON_ERROR_NONE ? $decoded_response : $response_body,
                    ];
                }
            }
        }
    }

    if ($active_tab === 'tab-missions' && $action === 'test_mission_logic') {
        $active_tab = 'tab-missions';
        $mission_code = sanitize_text_field($_POST['mission_code'] ?? '');
        $stk = sanitize_text_field(wp_unslash($_POST['test_stk'] ?? ''));
        $txdate = sanitize_text_field($_POST['txdate'] ?? '');
        
        $mission_test_result = [];
        
        if (empty($mission_code) || empty($stk)) {
            $mission_test_result = ['ok' => false, 'error' => 'Vui lòng nhập Custodycd và chọn Nhiệm vụ.'];
        } else {
            // Load API config
            $api_base = getEndpointFromMissionCode($mission_code);
            if (empty($api_base['base_url']) || empty($api_base['end_point'])) {
                $mission_test_result = ['ok' => false, 'error' => 'Lỗi cấu hình nhiệm vụ, không tìm thấy URL.'];
            } else {
                $apiBaseUrl = $api_base['base_url'];
                $endpoint = $api_base['end_point'];
                $url = $apiBaseUrl . $endpoint;
                
                // Build data based on mission code
                $data = ['custodycd' => $stk];
                $dStart = get_option('game_bsc_start_date');
                $dEnd = get_option('game_bsc_end_date');
                
                if ($mission_code === MTRADER_LOGIN_CODE) {
                    $data['txdate'] = $txdate ?: game_now('date');
                } else if ($mission_code === FIRST_DEPOSIT_CODE) {
                    $data['dstart'] = $dStart;
                    $data['dend'] = $dEnd;
                    $data['transactionvalue'] = 10000;
                } else if (in_array($mission_code, [OPEN_BSC_DERIVATIVE_ACCOUNT_CODE, EKYC_COMPLETE_CODE, OPEN_BIDV_CODE, OPEN_NEW_ACCOUNT_CODE, OPEN_MARGIN_ACCOUNT_CODE, USE_BSC_BUY_PACKAGE_CODE, USE_MR90_PACKAGE_CODE])) {
                    $data['dstart'] = $dStart;
                    $data['dend'] = $dEnd;
                } else if ($mission_code === TRADE_100M_VND_CODE) {
                    $amount_required = isset($api_base['amount_required']) ? $api_base['amount_required'] : 100000000;
                    if ($amount_required >= 1000000) {
                        $amount_required = (int)($amount_required / 1000);
                    }
                    $data['txdate'] = $txdate ?: game_now('date');
                    $data['transactionvalue'] = $amount_required;
                }
                
                $started_at = microtime(true);
                $response = callApiGame($url, http_build_query($data, '', '&', PHP_QUERY_RFC3986), 'POST');
                $elapsed_ms = (int)round((microtime(true) - $started_at) * 1000);
                
                if (!$response) {
                    $mission_test_result = [
                        'ok' => false, 
                        'error' => 'API Error or Timeout',
                        'url' => $url,
                        'payload' => $data,
                        'elapsed_ms' => $elapsed_ms
                    ];
                } else {
                    $mission_test_result = [
                        'ok' => true,
                        'url' => $url,
                        'payload' => $data,
                        'elapsed_ms' => $elapsed_ms,
                        'response' => $response
                    ];
                }
            }
        }
    }
}

// ===== LẤY DỮ LIỆU =====
$users = [];
$artifacts = [];
$selected_user_id = 0;
$user_pieces = [];
$user_redemptions = [];
$api_routes = [];
$customer_user_info_api = '';
$apinoibo_url = '';
$customer_internal_api = '';

if ($active_tab === 'tab-drops') {

// Danh sách users (top 50)
$users = $wpdb->get_results("SELECT id, name, external_user_id FROM {$prefix}users ORDER BY id LIMIT 50");

// Danh sách artifacts
$artifacts = $wpdb->get_results("SELECT * FROM {$prefix}artifacts ORDER BY id");

// User đang chọn
$selected_user_id = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
if ($selected_user_id <= 0 && !empty($users)) {
    $selected_user_id = $users[0]->id;
}

// Mảnh của user đang chọn
$user_pieces = [];
if ($selected_user_id > 0) {
    $user_pieces = $wpdb->get_results($wpdb->prepare(
        "SELECT up.*, p.piece_code, p.artifact_id, a.name as artifact_name
         FROM {$prefix}user_pieces up
         JOIN {$prefix}pieces p ON p.id = up.piece_id
         JOIN {$prefix}artifacts a ON a.id = p.artifact_id
         WHERE up.user_id = %d AND up.qty >= 1
         ORDER BY p.artifact_id, p.piece_code",
        $selected_user_id
    ));
}

// Check user đã hoàn thành bộ nào chưa
// Redemptions already initialized
if ($selected_user_id > 0) {
    $user_redemptions = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, a.name as artifact_name
         FROM {$prefix}user_artifact_redemptions r
         JOIN {$prefix}artifacts a ON a.id = r.artifact_id
         WHERE r.user_id = %d",
        $selected_user_id
    ));
}

} elseif ($active_tab === 'tab-api') {
$api_routes = [];
if (defined('NS') && function_exists('rest_get_server')) {
    $rest_server = rest_get_server();
    $registered_routes = $rest_server->get_routes();
    $namespace_prefix = '/' . NS . '/';

    foreach ($registered_routes as $route_path => $route_handlers) {
        if (strpos($route_path, $namespace_prefix) !== 0) {
            continue;
        }

        $methods = [];
        foreach ((array)$route_handlers as $handler) {
            if (empty($handler['methods']) || !is_array($handler['methods'])) {
                continue;
            }

            foreach ($handler['methods'] as $method => $enabled) {
                $method_name = is_string($method) ? $method : $enabled;
                $method_name = strtoupper((string)$method_name);
                if ($method_name && !in_array($method_name, $methods, true)) {
                    $methods[] = $method_name;
                }
            }
        }

        sort($methods);
        $api_routes[] = [
            'route' => $route_path,
            'methods' => $methods,
            'has_path_params' => strpos($route_path, '(?P<') !== false,
        ];
    }

    usort($api_routes, function ($a, $b) {
        return strcmp($a['route'], $b['route']);
    });
}

$customer_user_info_api = rtrim((string)get_field('cdapi_ip_address_apiurl', 'option'), '/') . '/user/info';
$apinoibo_url = rtrim((string)get_option('game_bsc_api_base_url'), '/');
$customer_internal_api = (strpos($apinoibo_url, '/api') !== false)
    ? $apinoibo_url . '/MarketData/GetCustomerByCustodycd'
    : $apinoibo_url . '/api/MarketData/GetCustomerByCustodycd';
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Rơi Mảnh - Game BSC</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
        h1 { color: #38bdf8; margin-bottom: 20px; font-size: 24px; }
        h2 { color: #7dd3fc; margin: 20px 0 12px; font-size: 18px; border-bottom: 1px solid #334155; padding-bottom: 8px; }
        h3 { color: #93c5fd; margin: 12px 0 8px; font-size: 15px; }

        .container { max-width: 1200px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 16px; margin-bottom: 16px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #0f172a; color: #94a3b8; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        tr:hover { background: #1e3a5f; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #064e3b; color: #6ee7b7; }
        .badge-red { background: #7f1d1d; color: #fca5a5; }
        .badge-yellow { background: #78350f; color: #fcd34d; }
        .badge-blue { background: #1e3a5f; color: #93c5fd; }
        .badge-purple { background: #4c1d95; color: #c4b5fd; }

        select, input[type="number"] { background: #0f172a; color: #e2e8f0; border: 1px solid #475569; padding: 6px 10px; border-radius: 6px; }
        select { min-width: 200px; }

        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        .msg { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
        .msg-success { background: #064e3b; border: 1px solid #059669; color: #6ee7b7; }
        .msg-error { background: #7f1d1d; border: 1px solid #dc2626; color: #fca5a5; }
        .msg-info { background: #1e3a5f; border: 1px solid #2563eb; color: #93c5fd; }

        .piece-grid { display: flex; gap: 8px; flex-wrap: wrap; }
        .piece-item { background: #0f172a; border: 1px solid #475569; padding: 6px 10px; border-radius: 6px; font-size: 12px; }
        .piece-item.owned { border-color: #059669; background: #064e3b; }

        pre { background: #0f172a; border: 1px solid #334155; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; color: #a5f3fc; }
        .mono { font-family: 'Fira Code', 'Consolas', monospace; font-size: 12px; }
        form { display: inline; }
        label { font-size: 13px; color: #94a3b8; }
        .inline-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .checkbox-group { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0; }
        .checkbox-group label { display: flex; align-items: center; gap: 4px; background: #0f172a; padding: 4px 10px; border-radius: 6px; border: 1px solid #475569; cursor: pointer; }
        .checkbox-group label:hover { border-color: #60a5fa; }
        a { color: #60a5fa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        textarea, input[type="text"] { background: #0f172a; color: #e2e8f0; border: 1px solid #475569; padding: 8px 10px; border-radius: 6px; width: 100%; }
        textarea { min-height: 140px; resize: vertical; font-family: 'Fira Code', 'Consolas', monospace; font-size: 12px; line-height: 1.45; }
        .tabs { display: flex; gap: 8px; margin: 16px 0; border-bottom: 1px solid #334155; }
        .tab-btn { background: transparent; color: #94a3b8; border: 1px solid transparent; border-bottom: none; padding: 10px 14px; border-radius: 8px 8px 0 0; cursor: pointer; font-weight: 700; }
        .tab-btn.active { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .api-layout { display: grid; grid-template-columns: 360px 1fr; gap: 16px; align-items: start; }
        .api-list { max-height: 620px; overflow: auto; }
        .api-route-btn { width: 100%; text-align: left; background: #0f172a; color: #e2e8f0; border: 1px solid #334155; border-radius: 6px; padding: 8px 10px; margin-bottom: 6px; cursor: pointer; }
        .api-route-btn.active, .api-route-btn:hover { border-color: #60a5fa; background: #1e3a5f; }
        .api-route-btn small { display: block; color: #94a3b8; margin-top: 4px; }
        .api-tester form { display: block; }
        .api-field { margin-bottom: 12px; }
        .api-field label { display: block; margin-bottom: 6px; }
        .api-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .api-status { color: #94a3b8; font-size: 12px; }
        .api-result-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        @media (max-width: 900px) {
            .grid, .api-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🧪 Test Rơi Mảnh Hiện Vật</h1>
    <p style="color:#64748b;margin-bottom:16px;">Thời gian hiện tại: <span class="mono"><?php echo $now->format('Y-m-d H:i:s'); ?></span> (Asia/Ho_Chi_Minh)</p>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="tabs" role="tablist">
        <button type="button" class="tab-btn <?php echo $active_tab === 'tab-drops' ? 'active' : ''; ?>" data-tab-target="tab-drops">Test drop</button>
        <button type="button" class="tab-btn <?php echo $active_tab === 'tab-missions' ? 'active' : ''; ?>" data-tab-target="tab-missions">Test Nhiệm Vụ</button>
        <button type="button" class="tab-btn <?php echo $active_tab === 'tab-api' ? 'active' : ''; ?>" data-tab-target="tab-api">Test API</button>
    </div>

    <?php if ($active_tab === 'tab-drops'): ?>
    <div id="tab-drops" class="tab-panel active">

    <!-- ===== CHỌN USER ===== -->
    <div class="card">
        <h2>👤 Chọn User để test</h2>
        <form method="get" action="" class="inline-form">
            <input type="hidden" name="tab" value="drops">
            <select name="user_id" onchange="this.form.submit()">
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u->id; ?>" <?php selected($selected_user_id, $u->id); ?>>
                        #<?php echo $u->id; ?> - <?php echo esc_html($u->name); ?> (<?php echo esc_html($u->external_user_id); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selected_user_id > 0): ?>
            <span style="margin-left:12px;">
                Đã hoàn thành bộ:
                <?php if (game_user_has_completed_artifact($selected_user_id)): ?>
                    <span class="badge badge-green">CÓ (<?php echo count($user_redemptions); ?> bộ)</span>
                <?php else: ?>
                    <span class="badge badge-yellow">CHƯA</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>

        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #334155;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <form method="post" class="inline-form">
                <?php wp_nonce_field('game_test_drops'); ?>
                <input type="hidden" name="action" value="create_test_users">
                <label>Số user: <input type="number" name="count" value="10" min="1" max="50" style="width:60px;"></label>
                <button type="submit" class="btn btn-success btn-sm">➕ Tạo user test</button>
                <span style="color:#64748b;font-size:12px;margin-left:8px;">Hiện có <?php echo count($users); ?> user</span>
            </form>

            <form method="post" class="inline-form" onsubmit="return confirm('⚠️ XÓA TOÀN BỘ dữ liệu mảnh, redemptions, drop_logs của TẤT CẢ user. Bạn chắc chắn?');">
                <?php wp_nonce_field('game_test_drops'); ?>
                <input type="hidden" name="action" value="reset_all_data">
                <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;">🗑️ Reset toàn bộ data mảnh</button>
            </form>
        </div>
    </div>

    <div class="grid">
        <!-- ===== CỘT TRÁI: HIỆN VẬT + KỲ ===== -->
        <div>
            <h2>📦 Danh sách hiện vật & Thông tin kỳ</h2>

            <?php foreach ($artifacts as $art): ?>
                <?php
                $current_period = game_artifact_current_period($art);
                $is_within = game_artifact_is_within_period($art);
                $has_quota = ($current_period !== false) ? game_artifact_period_has_quota($art, $current_period) : true;

                // Lấy mảnh
                $pieces = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$prefix}pieces WHERE artifact_id = %d ORDER BY piece_code",
                    $art->id
                ));

                // Check pity cho user hiện tại
                $pity_piece = null;
                if ($selected_user_id > 0) {
                    $pity_piece = game_check_pity($selected_user_id, $art);
                }
                ?>
                <div class="card">
                    <h3>
                        <?php echo esc_html($art->name); ?>
                        <span class="badge <?php echo ($art->status == 1) ? 'badge-green' : 'badge-red'; ?>">
                            <?php echo ($art->status == 1) ? 'Mở' : 'Đóng'; ?>
                        </span>
                        <?php if (!$is_within): ?>
                            <span class="badge badge-red">Ngoài thời hạn</span>
                        <?php endif; ?>
                        <?php if (!$has_quota): ?>
                            <span class="badge badge-yellow">Hết quota kỳ</span>
                        <?php endif; ?>
                        <?php if ($pity_piece): ?>
                            <span class="badge badge-purple">PITY → <?php echo $pity_piece->piece_code; ?></span>
                        <?php endif; ?>
                    </h3>

                    <table>
                        <tr><td>ID</td><td class="mono"><?php echo $art->id; ?></td></tr>
                        <tr><td>Max redemptions</td><td><?php echo $art->max_redemptions; ?></td></tr>
                        <tr>
                            <td>Thời hạn</td>
                            <td>
                                <?php if ($art->period_start && $art->period_end): ?>
                                    <span class="mono"><?php echo $art->period_start; ?></span> →
                                    <span class="mono"><?php echo $art->period_end; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-yellow">Không giới hạn</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Kỳ hiện tại</td>
                            <td>
                                <?php if ($current_period !== false): ?>
                                    <span class="badge badge-blue">Kỳ <?php echo $current_period + 1; ?> / <?php echo $art->total_periods; ?></span>
                                    <?php
                                    $dates = game_artifact_period_dates($art, $current_period);
                                    if ($dates):
                                    ?>
                                        <br><small class="mono" style="color:#64748b;">
                                            <?php echo $dates['start']->format('d/m H:i'); ?> →
                                            <?php echo $dates['end']->format('d/m H:i'); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php
                                    // Đếm redemptions trong kỳ
                                    if ($dates):
                                        $period_redemptions = (int)$wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$prefix}user_artifact_redemptions
                                             WHERE artifact_id = %d AND redeemed_at >= %s AND redeemed_at <= %s",
                                            $art->id, $dates['start']->format('Y-m-d H:i:s'), $dates['end']->format('Y-m-d H:i:s')
                                        ));
                                    ?>
                                        <br><small>Redemptions kỳ này: <strong><?php echo $period_redemptions; ?></strong> / <?php echo $art->max_redemptions_per_period; ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-red">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <!-- Danh sách tất cả kỳ -->
                    <?php if ($art->period_start && $art->period_end && $art->total_periods > 1): ?>
                        <h3 style="margin-top:12px;">Tất cả kỳ</h3>
                        <table>
                            <tr><th>#</th><th>Bắt đầu</th><th>Kết thúc</th><th>Trạng thái</th></tr>
                            <?php for ($pi = 0; $pi < $art->total_periods; $pi++):
                                $pd = game_artifact_period_dates($art, $pi);
                                if (!$pd) continue;
                                $is_current = ($pi === $current_period);
                            ?>
                                <tr style="<?php echo $is_current ? 'background:#1e3a5f;' : ''; ?>">
                                    <td><?php echo $pi + 1; ?></td>
                                    <td class="mono"><?php echo $pd['start']->format('d/m/Y H:i'); ?></td>
                                    <td class="mono"><?php echo $pd['end']->format('d/m/Y H:i'); ?></td>
                                    <td>
                                        <?php if ($is_current): ?>
                                            <span class="badge badge-green">Đang diễn ra</span>
                                        <?php elseif ($now < $pd['start']): ?>
                                            <span class="badge badge-blue">Sắp tới</span>
                                        <?php else: ?>
                                            <span class="badge badge-yellow">Đã qua</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </table>
                    <?php endif; ?>

                    <!-- Grant pieces -->
                    <?php if ($selected_user_id > 0 && !empty($pieces)): ?>
                        <h3 style="margin-top:12px;">Gán mảnh cho User #<?php echo $selected_user_id; ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('game_test_drops'); ?>
                            <input type="hidden" name="action" value="grant_pieces">
                            <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                            <input type="hidden" name="artifact_id" value="<?php echo $art->id; ?>">
                            <div class="checkbox-group">
                                <?php foreach ($pieces as $p):
                                    $owned = $wpdb->get_var($wpdb->prepare(
                                        "SELECT qty FROM {$prefix}user_pieces WHERE user_id = %d AND piece_id = %d",
                                        $selected_user_id, $p->id
                                    ));
                                    $owned = (int)$owned;
                                ?>
                                    <label class="<?php echo $owned > 0 ? 'owned' : ''; ?>" style="<?php echo $owned > 0 ? 'border-color:#059669;background:#064e3b;' : ''; ?>">
                                        <input type="checkbox" name="pieces[]" value="<?php echo $p->id; ?>">
                                        <?php echo $p->piece_code; ?> (w:<?php echo $p->baseline_weight; ?>)
                                        <?php if ($owned > 0): ?>
                                            <span class="badge badge-green">×<?php echo $owned; ?></span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm">Gán mảnh đã chọn</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== CỘT PHẢI: THAO TÁC TEST ===== -->
        <div>
            <h2>🎯 Giả lập rơi mảnh</h2>
            <div class="card">
                <form method="post" class="inline-form">
                    <?php wp_nonce_field('game_test_drops'); ?>
                    <input type="hidden" name="action" value="simulate_drop">
                    <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                    <label>User #<?php echo $selected_user_id; ?></label>
                    <button type="submit" class="btn btn-primary">Rơi mảnh 1 lần</button>
                </form>
                <p style="margin-top:8px;color:#64748b;font-size:12px;">
                    Gọi <code>game_get_random_reward()</code> cho user đã chọn. Kết quả thật (ghi log + cập nhật DB).
                </p>
            </div>

            <h2>🗑️ Reset dữ liệu user</h2>
            <div class="card">
                <form method="post" class="inline-form">
                    <?php wp_nonce_field('game_test_drops'); ?>
                    <input type="hidden" name="action" value="reset_user_pieces">
                    <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Xoá toàn bộ mảnh của user #<?php echo $selected_user_id; ?>?');">
                        Xoá toàn bộ mảnh User #<?php echo $selected_user_id; ?>
                    </button>
                </form>
            </div>

            <h2>🧩 Mảnh đang có (User #<?php echo $selected_user_id; ?>)</h2>
            <div class="card">
                <?php if (empty($user_pieces)): ?>
                    <p style="color:#64748b;">Chưa có mảnh nào.</p>
                <?php else: ?>
                    <?php
                    $grouped = [];
                    foreach ($user_pieces as $up) {
                        $grouped[$up->artifact_name][] = $up;
                    }
                    foreach ($grouped as $art_name => $pieces_list):
                    ?>
                        <h3><?php echo esc_html($art_name); ?></h3>
                        <div class="piece-grid">
                            <?php foreach ($pieces_list as $up): ?>
                                <div class="piece-item owned">
                                    <?php echo $up->piece_code; ?> × <?php echo $up->qty; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2>🏆 Bộ đã hoàn thành (User #<?php echo $selected_user_id; ?>)</h2>
            <div class="card">
                <?php if (empty($user_redemptions)): ?>
                    <p style="color:#64748b;">Chưa hoàn thành bộ nào.</p>
                <?php else: ?>
                    <table>
                        <tr><th>Hiện vật</th><th>Thời gian</th></tr>
                        <?php foreach ($user_redemptions as $r): ?>
                            <tr>
                                <td><?php echo esc_html($r->artifact_name); ?></td>
                                <td class="mono"><?php echo $r->redeemed_at; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <h2>📊 Debug Info</h2>
            <div class="card">
                <?php
                $system_drops_today = count_piece_drops_today();
                $user_drops_today = ($selected_user_id > 0) ? count_piece_drops_today($selected_user_id) : 0;
                $max_system = (int)get_option('game_bsc_max_drop_pieces_per_day', 0);
                $max_user = (int)get_option('game_bsc_max_user_drop_pieces_per_day', 3);
                $drop_rate = (int)get_option('game_bsc_piece_drop_rate', 30);
                ?>
                <table>
                    <tr><td>Mảnh rơi hệ thống hôm nay</td><td class="mono"><?php echo $system_drops_today; ?> / <?php echo $max_system ?: '∞'; ?></td></tr>
                    <tr><td>Mảnh rơi user hôm nay</td><td class="mono"><?php echo $user_drops_today; ?> / <?php echo $max_user; ?></td></tr>
                    <tr><td>Xác suất rơi mảnh</td><td class="mono"><?php echo $drop_rate; ?>%</td></tr>
                    <tr><td>Điểm / câu đúng</td><td class="mono"><?php echo game_point_per_answer(); ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 'tab-missions'): ?>
    <div id="tab-missions" class="tab-panel active">
        <div class="card">
            <h2>Kiểm tra Logic Nhiệm Vụ</h2>
            <p style="margin-bottom:16px;color:#64748b;font-size:12px;">
                Chức năng này dùng để giả lập việc gọi API kiểm tra nhiệm vụ đến BSC cho một User cụ thể.
                <br><strong>Lưu ý:</strong> Chức năng chỉ hiển thị kết quả trả về từ BSC, KHÔNG lưu lịch sử hoàn thành hay cộng điểm cho người dùng trên hệ thống.
            </p>
            <form method="post" class="api-tester">
                <?php wp_nonce_field('game_test_drops'); ?>
                <input type="hidden" name="tab" value="missions">
                <input type="hidden" name="action" value="test_mission_logic">
                
                <div class="grid">
                    <div class="api-field">
                        <label for="test_stk">Custodycd / STK (Bắt buộc)</label>
                        <input type="text" id="test_stk" name="test_stk" value="<?php echo esc_attr($_POST['test_stk'] ?? ''); ?>" placeholder="Ví dụ: 026C..." required>
                    </div>
                    
                    <div class="api-field">
                        <label for="mission_code">Chọn Nhiệm vụ (Bắt buộc)</label>
                        <select id="mission_code" name="mission_code" required style="width:100%;">
                            <option value="">-- Chọn nhiệm vụ --</option>
                            <?php 
                            $mission_list = include GAME_BSC_PLUGIN_DIR . 'config/missions.php';
                            if (is_array($mission_list)) {
                                foreach ($mission_list as $m) {
                                    $selected = (($_POST['mission_code'] ?? '') === $m['code']) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($m['code']) . '" ' . $selected . '>' . esc_html($m['title'] . ' (' . $m['code'] . ')') . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="api-field">
                    <label for="txdate">Ngày giao dịch (txdate)</label>
                    <input type="date" id="txdate" name="txdate" value="<?php echo esc_attr($_POST['txdate'] ?? game_now('date')); ?>" style="padding:8px 10px; border-radius:6px; background:#0f172a; border:1px solid #475569; color:#e2e8f0; width:100%; max-width:200px;">
                    <p style="margin-top:6px;color:#64748b;font-size:12px;">Chỉ bắt buộc với các nhiệm vụ cần kiểm tra theo ngày (như giao dịch 100M, đăng nhập MTrader).</p>
                </div>

                <div class="api-actions" style="margin-top: 16px;">
                    <button type="submit" class="btn btn-success">Test Mission API</button>
                </div>
            </form>

            <?php if (isset($mission_test_result)): ?>
                <h2 style="margin-top: 24px;">Kết quả trả về</h2>
                <div class="api-result-meta">
                    <?php if (empty($mission_test_result['ok'])): ?>
                        <span class="badge badge-red">Lỗi Request</span>
                    <?php else: ?>
                        <span class="badge badge-green">Thành công</span>
                    <?php endif; ?>
                    
                    <?php if (isset($mission_test_result['elapsed_ms'])): ?>
                        <span class="badge badge-blue"><?php echo esc_html($mission_test_result['elapsed_ms']); ?>ms</span>
                    <?php endif; ?>
                    
                    <?php if (!empty($mission_test_result['response']) && isset($mission_test_result['response']->s)): ?>
                        <span class="badge <?php echo ($mission_test_result['response']->s === 'ok') ? 'badge-green' : 'badge-red'; ?>">
                            Status: <?php echo esc_html($mission_test_result['response']->s); ?>
                        </span>
                        <?php if (isset($mission_test_result['response']->d)): ?>
                            <span class="badge <?php echo (!empty($mission_test_result['response']->d)) ? 'badge-green' : 'badge-yellow'; ?>">
                                Data (Lượt chơi): <?php echo esc_html(is_bool($mission_test_result['response']->d) ? ($mission_test_result['response']->d ? 'true' : 'false') : (is_scalar($mission_test_result['response']->d) ? $mission_test_result['response']->d : wp_json_encode($mission_test_result['response']->d))); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($mission_test_result['url'])): ?>
                    <p style="margin-bottom:8px;font-size:13px;"><strong>URL:</strong> <span class="mono" style="color:#94a3b8;"><?php echo esc_html($mission_test_result['url']); ?></span></p>
                <?php endif; ?>
                
                <?php if (!empty($mission_test_result['payload'])): ?>
                    <p style="margin-bottom:8px;font-size:13px;"><strong>Payload đã gửi:</strong></p>
                    <pre style="margin-bottom:16px;"><?php echo esc_html(print_r($mission_test_result['payload'], true)); ?></pre>
                <?php endif; ?>

                <p style="margin-bottom:8px;font-size:13px;"><strong>Raw Response:</strong></p>
                <pre><?php echo esc_html(wp_json_encode($mission_test_result['response'] ?? $mission_test_result['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 'tab-api'): ?>
    <div id="tab-api" class="tab-panel active">
        <div class="card">
            <h2>API lay thong tin khach hang</h2>
            <table>
                <tr>
                    <td>REST trong game</td>
                    <td class="mono">GET <?php echo esc_html(rest_url(NS . '/user')); ?></td>
                </tr>
                <tr>
                    <td>Field ten khach hang</td>
                    <td class="mono">data.user.name</td>
                </tr>
                <tr>
                    <td>BSC user info</td>
                    <td class="mono">GET <?php echo esc_html($customer_user_info_api); ?></td>
                </tr>
                <tr>
                    <td>Field ten tu BSC</td>
                    <td class="mono">d.userinfo.fullname</td>
                </tr>
                <tr>
                    <td>Check custodycd noi bo</td>
                    <td class="mono">POST <?php echo esc_html($customer_internal_api); ?></td>
                </tr>
            </table>
            <p style="margin-top:8px;color:#64748b;font-size:12px;">
                Code dang lay ten trong <code>save_game_user_to_db()</code> bang API <code>user/info</code>, sau do REST <code>/user</code> tra ve <code>user.name</code>.
            </p>
        </div>

        <div class="card">
            <h2>Test BSC external API</h2>
            <form method="post" class="api-tester">
                <?php wp_nonce_field('game_test_drops'); ?>
                <input type="hidden" name="tab" value="api">
                <input type="hidden" name="action" value="bsc_external_api_call">

                <div class="grid">
                    <div class="api-field">
                        <label for="bsc_base">Base URL</label>
                        <select id="bsc_base" name="bsc_base" style="width:100%;">
                            <option value="apinoibo" <?php selected($bsc_external_form['base'], 'apinoibo'); ?>>cdapi_ip_address_apinoibo</option>
                            <option value="apiurl" <?php selected($bsc_external_form['base'], 'apiurl'); ?>>cdapi_ip_address_apiurl</option>
                            <option value="tradingserver" <?php selected($bsc_external_form['base'], 'tradingserver'); ?>>game_bsc_trading_server</option>
                            <option value="full" <?php selected($bsc_external_form['base'], 'full'); ?>>Full URL trong endpoint</option>
                        </select>
                    </div>

                    <div class="api-field">
                        <label for="bsc_method">Method</label>
                        <select id="bsc_method" name="bsc_method" style="width:100%;">
                            <?php foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method_option): ?>
                                <option value="<?php echo esc_attr($method_option); ?>" <?php selected($bsc_external_form['method'], $method_option); ?>><?php echo esc_html($method_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="api-field">
                    <label for="bsc_endpoint">Endpoint</label>
                    <input type="text" id="bsc_endpoint" name="bsc_endpoint" value="<?php echo esc_attr($bsc_external_form['endpoint']); ?>" list="bsc_endpoint_presets" placeholder="/api/MarketData/GetCustomerByCustodycd hoac https://...">
                    <datalist id="bsc_endpoint_presets">
                        <option value="/api/MarketData/GetCustomerByCustodycd">Internal: GetCustomerByCustodycd</option>
                        <option value="/user/info">SSO API: user/info</option>
                        <option value="/trade/accounts">Trading API: trade/accounts</option>
                        <option value="/report/registeredVoucherList">Trading API: registeredVoucherList</option>
                    </datalist>
                </div>

                <div class="grid">
                    <div class="api-field">
                        <label for="bsc_stk">STK / custodycd / afacctno</label>
                        <input type="text" id="bsc_stk" name="bsc_stk" value="<?php echo esc_attr($bsc_external_form['stk']); ?>" placeholder="002C...">
                    </div>

                    <div class="api-field">
                        <label for="bsc_stk_param">Ten param STK</label>
                        <input type="text" id="bsc_stk_param" name="bsc_stk_param" value="<?php echo esc_attr($bsc_external_form['stk_param']); ?>" placeholder="custodycd">
                    </div>
                </div>

                <div class="grid">
                    <div class="api-field">
                        <label for="bsc_stk_location">Chen STK vao</label>
                        <select id="bsc_stk_location" name="bsc_stk_location" style="width:100%;">
                            <option value="body" <?php selected($bsc_external_form['stk_location'], 'body'); ?>>Body</option>
                            <option value="query" <?php selected($bsc_external_form['stk_location'], 'query'); ?>>Query string</option>
                            <option value="both" <?php selected($bsc_external_form['stk_location'], 'both'); ?>>Body + query</option>
                            <option value="none" <?php selected($bsc_external_form['stk_location'], 'none'); ?>>Khong chen tu dong</option>
                        </select>
                    </div>

                    <div class="api-field">
                        <label for="bsc_body_type">Body type</label>
                        <select id="bsc_body_type" name="bsc_body_type" style="width:100%;">
                            <option value="form" <?php selected($bsc_external_form['body_type'], 'form'); ?>>Form body</option>
                            <option value="json" <?php selected($bsc_external_form['body_type'], 'json'); ?>>JSON body</option>
                        </select>
                    </div>
                </div>

                <div class="api-field">
                    <label for="bsc_body">Body JSON, co the dung placeholder <code>{{stk}}</code></label>
                    <textarea id="bsc_body" name="bsc_body" spellcheck="false"><?php echo esc_textarea($bsc_external_form['body']); ?></textarea>
                </div>

                <div class="api-field">
                    <label for="bsc_headers">Headers tuy chon, nhap JSON hoac moi dong <code>Key: Value</code></label>
                    <textarea id="bsc_headers" name="bsc_headers" spellcheck="false" placeholder="X-Custom: value"><?php echo esc_textarea($bsc_external_form['headers']); ?></textarea>
                </div>

                <div class="api-actions">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="bsc_use_access_token" value="1" <?php checked($bsc_external_form['use_access_token'], '1'); ?>>
                        Gan Authorization Bearer tu cookie access_token
                    </label>
                    <label>Timeout
                        <input type="number" name="bsc_timeout" value="<?php echo esc_attr($bsc_external_form['timeout']); ?>" min="1" max="60" style="width:72px;">
                    </label>
                    <button type="submit" class="btn btn-success">Call BSC API</button>
                </div>
            </form>

            <?php if ($bsc_external_result !== null): ?>
                <h2>BSC response</h2>
                <div class="api-result-meta">
                    <?php if (!empty($bsc_external_result['status'])): ?>
                        <span class="badge <?php echo ((int)$bsc_external_result['status'] >= 200 && (int)$bsc_external_result['status'] < 300) ? 'badge-green' : 'badge-red'; ?>">
                            <?php echo esc_html($bsc_external_result['status'] . ' ' . ($bsc_external_result['message'] ?? '')); ?>
                        </span>
                    <?php elseif (empty($bsc_external_result['ok'])): ?>
                        <span class="badge badge-red">ERROR</span>
                    <?php endif; ?>
                    <?php if (isset($bsc_external_result['elapsed_ms'])): ?>
                        <span class="badge badge-blue"><?php echo esc_html($bsc_external_result['elapsed_ms']); ?>ms</span>
                    <?php endif; ?>
                    <?php if (!empty($bsc_external_result['method'])): ?>
                        <span class="badge badge-purple"><?php echo esc_html($bsc_external_result['method']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($bsc_external_result['url'])): ?>
                    <p class="mono" style="margin-bottom:8px;color:#94a3b8;word-break:break-all;"><?php echo esc_html($bsc_external_result['url']); ?></p>
                <?php endif; ?>
                <pre><?php echo esc_html(wp_json_encode($bsc_external_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
            <?php endif; ?>
        </div>

        <div class="api-layout api-tester">
            <div class="card">
                <h2>Danh sach API game-bsc</h2>
                <div class="api-actions" style="margin-bottom:12px;">
                    <button type="button" class="btn btn-primary btn-sm" id="api-run-all-get">Run all GET</button>
                    <span class="api-status"><?php echo count($api_routes); ?> routes</span>
                </div>
                <p style="margin-bottom:12px;color:#64748b;font-size:12px;">
                    Batch chi chay GET. Mot so GET co the cap nhat trang thai test, vi du <code>/user/unviewed-badges</code>.
                </p>
                <div class="api-list" id="api-route-list">
                    <?php foreach ($api_routes as $index => $route): ?>
                        <button type="button" class="api-route-btn<?php echo $index === 0 ? ' active' : ''; ?>" data-api-index="<?php echo (int)$index; ?>">
                            <span class="mono"><?php echo esc_html($route['route']); ?></span>
                            <small><?php echo esc_html(implode(', ', $route['methods'])); ?><?php echo $route['has_path_params'] ? ' | path params' : ''; ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h2>Request</h2>
                <form id="api-test-form">
                    <div class="api-field">
                        <label for="api-method">Method</label>
                        <select id="api-method"></select>
                    </div>
                    <div class="api-field">
                        <label for="api-path">Path</label>
                        <input type="text" id="api-path" value="">
                    </div>
                    <div class="api-field">
                        <label for="api-query">Query string</label>
                        <input type="text" id="api-query" placeholder="page=1&per_page=5">
                    </div>
                    <div class="api-field">
                        <label for="api-body">JSON body</label>
                        <textarea id="api-body" spellcheck="false"></textarea>
                    </div>
                    <div class="api-actions">
                        <button type="submit" class="btn btn-success">Send request</button>
                        <button type="button" class="btn btn-primary" id="api-fill-example">Fill example</button>
                        <span class="api-status" id="api-status">Ready</span>
                    </div>
                </form>

                <h2>Response</h2>
                <div class="api-result-meta" id="api-result-meta"></div>
                <pre id="api-result">{}</pre>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php if ($active_tab === 'tab-api'): ?>
<script>
(function () {
    const routes = <?php echo wp_json_encode($api_routes); ?>;
    const restRoot = <?php echo wp_json_encode(untrailingslashit(rest_url())); ?>;
    const wpNonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
    const gameNonce = <?php echo wp_json_encode(wp_create_nonce('wp_game_rest')); ?>;
    const routeButtons = Array.from(document.querySelectorAll('.api-route-btn'));
    const methodSelect = document.getElementById('api-method');
    const pathInput = document.getElementById('api-path');
    const queryInput = document.getElementById('api-query');
    const bodyInput = document.getElementById('api-body');
    const resultPre = document.getElementById('api-result');
    const resultMeta = document.getElementById('api-result-meta');
    const statusEl = document.getElementById('api-status');

    const examples = {
        'POST /game-bsc/session/start': { play_credit: 1 },
        'POST /game-bsc/session/answer': { session_id: 1, question_id: 1, answer: 'A' },
        'POST /game-bsc/session/next': { session_id: 1 },
        'POST /game-bsc/missions/check': { mission_code: 'daily_login' },
        'POST /game-bsc/missions/check-all': {},
        'POST /game-bsc/vouchers/issue': { voucher_post_id: 1 },
        'POST /game-bsc/gifts/redeem': { artifact_id: 1 }
    };

    function normalizePath(route) {
        return route.replace(/\(\?P<([^>]+)>[^)]+\)/g, '1');
    }

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text;
    }

    function setActiveTab(targetId) {
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.tabTarget === targetId);
        });
        document.querySelectorAll('.tab-panel').forEach(function (panel) {
            panel.classList.toggle('active', panel.id === targetId);
        });
    }

    function renderRoute(index) {
        const route = routes[index];
        if (!route) return;

        routeButtons.forEach(function (btn) {
            btn.classList.toggle('active', Number(btn.dataset.apiIndex) === index);
        });

        methodSelect.innerHTML = '';
        (route.methods || ['GET']).forEach(function (method) {
            const option = document.createElement('option');
            option.value = method;
            option.textContent = method;
            methodSelect.appendChild(option);
        });

        pathInput.value = normalizePath(route.route);
        queryInput.value = '';
        fillExample();
        resultMeta.innerHTML = '';
        resultPre.textContent = '{}';
        setStatus('Ready');
    }

    function currentExampleKey() {
        return methodSelect.value + ' ' + pathInput.value.replace(/\/+$/, '');
    }

    function fillExample() {
        const method = methodSelect.value;
        const key = currentExampleKey();
        const example = examples[key];
        bodyInput.value = (method === 'GET' || method === 'HEAD')
            ? ''
            : JSON.stringify(example || {}, null, 2);
    }

    function buildUrl(path, query) {
        const cleanPath = path.replace(/^\/+/, '');
        let url = restRoot + '/' + cleanPath;
        if (query.trim()) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + query.replace(/^\?+/, '');
        }
        return url;
    }

    async function sendRequest(method, path, query, rawBody) {
        const startedAt = performance.now();
        const headers = {
            'Accept': 'application/json',
            'X-WP-Nonce': wpNonce,
            'X-Game-Nonce': gameNonce
        };
        const options = {
            method: method,
            headers: headers,
            credentials: 'same-origin'
        };

        if (method !== 'GET' && method !== 'HEAD' && rawBody.trim()) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(JSON.parse(rawBody));
        }

        const response = await fetch(buildUrl(path, query), options);
        const responseText = await response.text();
        const elapsed = Math.round(performance.now() - startedAt);
        let payload = responseText;

        try {
            payload = JSON.stringify(JSON.parse(responseText), null, 2);
        } catch (error) {
            payload = responseText || '(empty response)';
        }

        return {
            status: response.status,
            ok: response.ok,
            statusText: response.statusText,
            elapsed: elapsed,
            body: payload
        };
    }

    async function runCurrentRequest(event) {
        if (event) event.preventDefault();
        try {
            setStatus('Sending...');
            const result = await sendRequest(methodSelect.value, pathInput.value, queryInput.value, bodyInput.value);
            resultMeta.innerHTML =
                '<span class="badge ' + (result.ok ? 'badge-green' : 'badge-red') + '">' + result.status + ' ' + result.statusText + '</span>' +
                '<span class="badge badge-blue">' + result.elapsed + 'ms</span>';
            resultPre.textContent = result.body;
            setStatus('Done');
        } catch (error) {
            resultMeta.innerHTML = '<span class="badge badge-red">Request error</span>';
            resultPre.textContent = error && error.message ? error.message : String(error);
            setStatus('Error');
        }
    }

    async function runAllGet() {
        if (!window.confirm('Run all GET routes? Mot so GET co the cap nhat trang thai test.')) {
            return;
        }

        const getRoutes = routes.filter(function (route) {
            return (route.methods || []).indexOf('GET') !== -1;
        });
        const rows = [];
        setStatus('Running GET routes...');

        for (const route of getRoutes) {
            const path = normalizePath(route.route);
            try {
                const result = await sendRequest('GET', path, '', '');
                rows.push({
                    method: 'GET',
                    path: path,
                    status: result.status,
                    ok: result.ok,
                    elapsed: result.elapsed
                });
            } catch (error) {
                rows.push({
                    method: 'GET',
                    path: path,
                    status: 'ERROR',
                    ok: false,
                    elapsed: null,
                    error: error && error.message ? error.message : String(error)
                });
            }
        }

        resultMeta.innerHTML = '<span class="badge badge-blue">' + rows.length + ' GET routes</span>';
        resultPre.textContent = JSON.stringify(rows, null, 2);
        setStatus('Done');
    }

    // Client-side dynamic switching removed to reload page on tab change

    routeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            renderRoute(Number(btn.dataset.apiIndex));
        });
    });

    methodSelect.addEventListener('change', fillExample);
    document.getElementById('api-fill-example').addEventListener('click', fillExample);
    document.getElementById('api-test-form').addEventListener('submit', runCurrentRequest);
    document.getElementById('api-run-all-get').addEventListener('click', runAllGet);

    renderRoute(0);
})();
</script>
<?php endif; ?>

<script>
document.querySelectorAll('.tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const tab = btn.dataset.tabTarget.replace('tab-', '');
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.location.href = url.toString();
    });
});
</script>
</body>
</html>
