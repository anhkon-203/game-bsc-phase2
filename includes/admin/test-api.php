<?php
if (!defined('ABSPATH')) {
    exit;
}

function game_bsc_test_api_page() {
    // Fixed trading server base URL for API testing
    $trading_server_base = 'https://tradeapi-krxtduat.bsc.com.vn';

    $action = isset($_GET['action_test']) ? $_GET['action_test'] : '';
    $result = null;
    $request_url = '';

    // Resolve token for Authorization header: ưu tiên nhập tay, fallback cookie access_token.
    $auth_token_input = isset($_REQUEST['auth_token']) ? trim(wp_unslash($_REQUEST['auth_token'])) : '';
    $raw_access_token = $auth_token_input !== ''
        ? $auth_token_input
        : (isset($_COOKIE['access_token']) ? trim((string) $_COOKIE['access_token']) : '');

    // Giữ nguyên token người dùng nhập; nếu đã có tiền tố "Bearer " thì dùng luôn.
    $authorization_header = '';
    if ($raw_access_token !== '') {
        $authorization_header = (stripos($raw_access_token, 'Bearer ') === 0)
            ? $raw_access_token
            : 'Bearer ' . $raw_access_token;
    }

    // API 1: /trade/accounts
    if ($action === 'test_accounts' && $trading_server_base) {
        $request_url = $trading_server_base . '/trade/accounts';
        $args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );
        if ($authorization_header !== '') {
            $args['headers']['Authorization'] = $authorization_header;
        } else {
            $result = 'Missing Authorization header: vui lòng nhập token hoặc đăng nhập để có cookie access_token.';
        }
        if ($result === null) {
            if (function_exists('callApiGame')) {
                $response = callApiGame(
                    $request_url,
                    false,
                    'GET',
                    array(
                        'Content-Type: application/json',
                        'Authorization: ' . $authorization_header,
                    )
                );
                $result = $response === null
                    ? 'API call failed (callApiGame trả về null).'
                    : wp_json_encode($response, JSON_UNESCAPED_UNICODE);
            } else {
                $response = wp_remote_get($request_url, $args);
                $result = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            }
        }
    }

    // API 2: /report/registeredVoucherList
    if ($action === 'test_vouchers' && $trading_server_base) {
        $request_url = $trading_server_base . '/report/registeredVoucherList';
        $args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );
        if ($authorization_header !== '') {
            $args['headers']['Authorization'] = $authorization_header;
        } else {
            $result = 'Missing Authorization header: vui lòng nhập token hoặc đăng nhập để có cookie access_token.';
        }
        if ($result === null) {
            if (function_exists('callApiGame')) {
                $response = callApiGame(
                    $request_url,
                    false,
                    'GET',
                    array(
                        'Content-Type: application/json',
                        'Authorization: ' . $authorization_header,
                    )
                );
                $result = $response === null
                    ? 'API call failed (callApiGame trả về null).'
                    : wp_json_encode($response, JSON_UNESCAPED_UNICODE);
            } else {
                $response = wp_remote_get($request_url, $args);
                $result = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            }
        }
    }

    // API 3: Logic kiểm tra prefix token
    if ($action === 'test_sso_block') {
        $token_string = isset($_POST['token_string']) ? wp_unslash($_POST['token_string']) : '';
        if (empty($token_string)) {
            $result = "Vui lòng nhập token string.";
        } else {
            // Chuỗi token có thể cách nhau bởi | hoặc || tuỳ lúc
            $separator = strpos($token_string, '||') !== false ? '||' : '|';
            $parts = array_map('trim', explode($separator, $token_string));
            $account = $parts[0] ?? '';
            
            $status = 'Hợp lệ';
            $is_blocked = false;
            
            if (substr($account, 0, 4) === '002F') {
                $is_blocked = true;
                $status = 'BỊ CHẶN (002F - Tài khoản nước ngoài)';
            } elseif (substr($account, 0, 4) === '002C') {
                $status = 'BÌNH THƯỜNG (002C - Tài khoản trong nước, cho phép truy cập)';
            } else {
                $status = 'KHÔNG XÁC ĐỊNH (Không bắt đầu bằng 002C hay 002F)';
            }

            $result = "Raw Token: " . esc_html($token_string) . "\n"
                    . "Tài khoản (Account parse được): " . esc_html($account) . "\n"
                    . "Trạng thái: " . $status;
        }
    }

    ?>
    <div class="wrap">
        <h1>Testing BSC API v2</h1>
        
        <?php if (!$trading_server_base): ?>
            <div class="notice notice-error"><p><strong>Lỗi:</strong> Không thể xác định <code>trading_server</code> từ cấu hình SSO (cdapi_ip_address_apilogin). Vui lòng cấu hình trong Admin.</p></div>
        <?php else: ?>
            <div class="notice notice-info"><p><strong>Nguồn Trading Server (SSO Base URL):</strong> <code><?php echo esc_html($trading_server_base); ?></code></p></div>
        <?php endif; ?>

        <?php if ($authorization_header === ''): ?>
            <div class="notice notice-warning"><p><strong>Authorization:</strong> Chưa có token. Hãy nhập token ở form bên dưới để gửi header <code>Authorization: Bearer ...</code>.</p></div>
        <?php else: ?>
            <div class="notice notice-success"><p><strong>Authorization:</strong> Header sẽ được gửi (Bearer token).</p></div>
        <?php endif; ?>

        <hr>

        <h2>1. API (1) - Thông tin tài khoản</h2>
        <p>Endpoint: <code>GET /trade/accounts</code></p>
        <p><em>Trả về danh sách tài khoản chứng khoán của khách hàng.</em></p>
        <form method="get">
            <input type="hidden" name="page" value="game-bsc-test-api">
            <input type="hidden" name="action_test" value="test_accounts">
            <input type="text" name="auth_token" value="<?php echo esc_attr($auth_token_input); ?>" style="width:100%; max-width:700px;" placeholder="Nhập Bearer token hoặc chuỗi token thô">
            <p class="description">Có thể nhập trực tiếp cả chuỗi bắt đầu bằng <code>Bearer </code>. Nếu để trống sẽ thử lấy từ cookie <code>access_token</code>.</p>
            <button type="submit" class="button button-primary">Gửi request (GET /trade/accounts)</button>
        </form>

        <hr>

        <h2>2. API (2) - Danh sách Voucher đã đăng ký</h2>
        <p>Endpoint: <code>GET /report/registeredVoucherList</code></p>
        <p><em>Trả về danh sách voucher ưu đãi đã được đăng ký cho khách hàng.</em></p>
        <form method="get">
            <input type="hidden" name="page" value="game-bsc-test-api">
            <input type="hidden" name="action_test" value="test_vouchers">
            <input type="text" name="auth_token" value="<?php echo esc_attr($auth_token_input); ?>" style="width:100%; max-width:700px;" placeholder="Nhập Bearer token hoặc chuỗi token thô">
            <p class="description">Có thể nhập trực tiếp cả chuỗi bắt đầu bằng <code>Bearer </code>. Nếu để trống sẽ thử lấy từ cookie <code>access_token</code>.</p>
            <button type="submit" class="button button-primary">Gửi request (GET /report/registeredVoucherList)</button>
        </form>

        <hr>

        <h2>3. API (3) - Cấu trúc token chặn đăng nhập</h2>
        <p>Kiểm tra logic tài khoản có prefix <strong>002C</strong> (Cho phép) hoặc <strong>002F</strong> (Chặn).</p>
        <form method="post" action="?page=game-bsc-test-api&action_test=test_sso_block">
            <input type="text" name="token_string" value="<?php echo isset($_POST['token_string']) ? esc_attr(wp_unslash($_POST['token_string'])) : '002F123456|khg78d234...'; ?>" style="width:100%; max-width:500px;" placeholder="002C... | token...">
            <br><br>
            <button type="submit" class="button button-primary">Kiểm tra Mẫu (Logic Cắt Token)</button>
        </form>

        <?php if ($result !== null): ?>
            <hr>
            <h2>Kết quả trả về:</h2>
            <?php if ($request_url): ?>
                <p><strong>Request URL:</strong> <code><?php echo esc_url($request_url); ?></code></p>
            <?php endif; ?>
            <pre style="background:#fff; padding:15px; border:1px solid #ccc; max-width: 800px; overflow-x: auto; white-space: pre-wrap;"><?php 
                // Thử decode JSON để hiển thị đẹp nếu response là JSON
                $decoded = json_decode($result, true);
                if ($decoded && json_last_error() === JSON_ERROR_NONE) {
                    echo esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } else {
                    echo esc_html($result);
                }
            ?></pre>
        <?php endif; ?>
    </div>
    <?php
}
