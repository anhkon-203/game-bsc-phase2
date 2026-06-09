<?php
/**
 * Plugin Name: WG Game BSC
 * Plugin URI: https://wecan-group.com/
 * Description: A WordPress plugin to create game in bsc.
 * Version: 1.0
 * Author: Wecan group
 * Author URI: https://wecan-group.com/
 * Text Domain: game-bsc
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: 
 * Requires Plugins: advanced-custom-fields-pro
 *
 */
// Prevent direct access
if (!defined('ABSPATH')) exit;

// Define plugin constants
define('WG_GAME_VERSION', '1.0');
define('GAME_BSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GAME_BSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GAME_BSC_PLUGIN_FILE', __FILE__);
define('WG_GAME_PLUGIN_TEXTDOMAIN', 'wg-game-bsc');
define('WG_GAME_MAX_UPLOAD_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('WG_GAME_ITEMS_PER_PAGE', 50); // Số item trên 1 trang
define('WG_GAME_PLUGIN_DB_VERSION', '30.0');
define('WG_GAME_DEFAULT_AVATAR_URL', site_url() . '/wp-content/plugins/game-bsc/assets/images/avatar.png');
define('TIMEZONE', new DateTimeZone('Asia/Ho_Chi_Minh'));
// Cookie name for plugin auth token (stored as opaque token in DB)
define('GAME_AUTH_COOKIE', 'game_auth_token');
/**
 *  Define mission codes
 */
define('DAILY_LOGIN_CODE', 'daily_login'); // Mã định danh nhiệm vụ đăng nhập hàng ngày
define('MTRADER_LOGIN_CODE', 'mtrader_login'); // Mã định danh nhiệm vụ đăng nhập MTrader
define('EKYC_COMPLETE_CODE', 'ekyc_complete'); // Mã định danh nhiệm vụ hoàn thiện ký số
define('OPEN_BIDV_CODE', 'open_bidv'); // Mã định danh nhiệm vụ mở tài khoản BIDV
define('OPEN_NEW_ACCOUNT_CODE', 'open_new_account'); // Mã định danh nhiệm vụ mở tài khoản mới
define('FIRST_DEPOSIT_CODE', 'first_deposit'); // Mã định danh nhiệm vụ nạp tiền lần đầu
define('OPEN_BSC_DERIVATIVE_ACCOUNT_CODE', 'open_bsc_derivative_account'); // Mã định danh nhiệm vụ mở tiểu khoản phái sinh tại BSC
define('OPEN_MARGIN_ACCOUNT_CODE', 'open_margin_account'); // Mã định danh nhiệm vụ mở tài khoản Margin
define('USE_BSC_BUY_PACKAGE_CODE', 'use_bsc_buy_package'); // Mã định danh nhiệm vụ sử dụng gói BSC BUY
define('USE_MR90_PACKAGE_CODE', 'use_mr90_package'); // Mã định danh nhiệm vụ sử dụng gói MR90
define('TRADE_100M_VND_CODE', 'trade_100m_vnd'); // Mã định danh nhiệm vụ thực hiện giao dịch giá trị 100,000,000vnd
define('TRADE_100M_VND_DEFAULT_VALUE', 100000000); // Giá trị mặc định của nhiệm vụ thực hiện giao dịch 100,000,000vnd
/**
 *  Define mission url
 */
define('DAILY_LOGIN_URL', site_url());
define('MTRADER_LOGIN_URL', '/Gamification/GetTransactionMtrader');
define('EKYC_COMPLETE_URL', '/Gamification/GetInforCustomerInApiopenaccount');
define('OPEN_BIDV_URL', '/Gamification/GetLinkCorebank');
define('OPEN_NEW_ACCOUNT_URL', '/Gamification/GetOpendatebyCustodycd');
define('FIRST_DEPOSIT_URL', '/Gamification/GetDepositMoney');
define('OPEN_BSC_DERIVATIVE_ACCOUNT_URL', '/Gamification/GetPSByCustodycd');
define('OPEN_MARGIN_ACCOUNT_URL', '/Gamification/GetMarginByCustodycd');
define('USE_BSC_BUY_PACKAGE_URL', '/Gamification/GetBscBuyByCustodycd');
define('USE_MR90_PACKAGE_URL', '/Gamification/GetMarginBalance');
define('TRADE_100M_VND_URL', '/Gamification/GetTransactionValueByTxdate');
define('NS', 'game-bsc');

// utm source

define('MTRADER_APP', 'mtrader_app');
define('BSC_SMART_INVEST', 'bsc_smart_invest');
define('WEBTRADING', 'webtrading');
define('BSC_WEB', 'bsc_web');

// ==== Auto-login OTT: Xử lý ?ott= từ App (Mtrader, BSC Smart Invest, Webtrading) ====
// Spec: docs/ott-one-time-token-spec.md
add_action('init', 'game_bsc_handle_ott_auto_login', 5);

/**
 * Tự động đăng nhập khi App/Webtrading mở Webview kèm ?ott= trên URL trang game.
 *
 * Luồng OTT (One Time Token):
 *   1. BSC App/Webtrading sinh mã OTT cho user đã đăng nhập.
 *   2. Mở URL: https://domain/duong-dua-chung-si/?ott=<ma_ott>&utm_source=<app>
 *   3. Hàm này chạy sớm (init priority 5), gọi API Token Exchange đổi OTT → access_token.
 *   4. Set cookie access_token + transient.
 *   5. Redirect về URL sạch (bỏ ?ott=) để tránh lộ OTT (chỉ dùng 1 lần, hạn 30-60s).
 *   6. Sau redirect, template-home.php gọi bsc_game_handle_sso_callback() đọc cookie
 *      và tạo game_auth_token như bình thường.
 *
 * Ràng buộc OTT (theo spec BSC):
 *   - OTT chỉ dùng 1 lần, bị vô hiệu hóa sau khi đối soát.
 *   - OTT có thời hạn cực ngắn (30-60 giây).
 *   - Bắt buộc ghi log giao dịch đổi OTT (phục vụ đối soát).
 */
function game_bsc_handle_ott_auto_login() {
    // 1. Chỉ xử lý nếu có tham số 'ott' trên URL
    if ( ! isset($_GET['ott']) || empty($_GET['ott']) ) {
        return;
    }

    // 2. Nếu đã đăng nhập rồi (có cookie access_token hợp lệ) → bỏ qua
    if ( isset($_COOKIE['access_token']) && !empty($_COOKIE['access_token']) ) {
        $key = 'user_logged_in_' . md5($_COOKIE['access_token']);
        if ( get_transient($key) ) {
            return; // Đã đăng nhập, không cần xử lý
        }
    }

    // 3. Tránh xung đột: Không xử lý nếu đang ở URL callback SSO của theme
    if (function_exists('get_field')) {
        $callback_url = get_field('cdapi_ip_address_url_call_back', 'option');
        if ($callback_url) {
            $parsed = parse_url($callback_url, PHP_URL_PATH);
            if ($parsed && strpos($_SERVER['REQUEST_URI'], $parsed) !== false) {
                return; // Để theme callback xử lý
            }
        }
    }

    // 4. Đổi OTT lấy access_token (Token Exchange)
    $ott = sanitize_text_field($_GET['ott']);
    $exchange_result = game_bsc_exchange_ott_for_token($ott);

    if ($exchange_result && !empty($exchange_result['access_token'])) {
        $access_token = $exchange_result['access_token'];

        // 5. Set cookie + transient (giống theme: customizer-api.php)
        $expires_in = !empty($exchange_result['expires_in']) ? (int)$exchange_result['expires_in'] : 3600;
        $key = 'user_logged_in_' . md5($access_token);
        set_transient($key, true, $expires_in);
        setcookie('access_token', $access_token, time() + $expires_in, COOKIEPATH, COOKIE_DOMAIN);

        // Cập nhật superglobal để bsc_game_handle_sso_callback() có thể đọc ngay
        $_COOKIE['access_token'] = $access_token;

        // Log thành công (bắt buộc theo spec: ghi nhật ký giao dịch đổi OTT)
        error_log('[Game BSC OTT] Exchange thành công. OTT=' . substr($ott, 0, 8) . '... → token set, expires_in=' . $expires_in . 's');

        // 6. Redirect về URL sạch (bỏ param 'ott' để tránh lộ và reuse)
        $clean_url = remove_query_arg('ott');
        wp_redirect($clean_url);
        exit;
    }

    // Nếu thất bại → log lỗi, cho user vào trang bình thường
    // Spec: "hiển thị thông báo thân thiện" — xử lý ở FE khi API trả 401
    $error_msg = !empty($exchange_result['error']) ? $exchange_result['error'] : 'unknown';
    error_log('[Game BSC OTT] Exchange thất bại. OTT=' . substr($ott, 0, 8) . '... Error: ' . $error_msg);
}

/**
 * Đổi mã OTT lấy access_token từ BSC SSO API (Token Exchange).
 *
 * Theo spec OTT (docs/ott-one-time-token-spec.md):
 *   - Method: POST
 *   - URI: <trading_server>/sso/oauth/token
 *   - grant_type: urn:ietf:params:oauth:grant-type:token-exchange
 *   - subject_token: <mã_OTT>
 *   - subject_token_type: urn:ietf:params:oauth:token-type:access_token
 *   - client_id, client_secret: do BSC cấp
 *
 * Response format:
 *   - s: "ok" | "error"
 *   - em: error message
 *   - data: { accessToken, refreshToken, token_type, expires_in, scope }
 *
 * @param string $ott Mã OTT từ URL
 * @return array|null ['access_token' => ..., 'expires_in' => ...] hoặc ['error' => ...] hoặc null
 */
function game_bsc_exchange_ott_for_token($ott) {
    if (!function_exists('get_field')) {
        error_log('[Game BSC OTT] Exchange: ACF get_field() not available.');
        return null;
    }

    $client_id     = get_field('cdapi_ip_address_clientid', 'option');
    $client_secret = get_field('cdapi_ip_address_clientsecret', 'option');
    $api_url       = get_field('cdapi_ip_address_apiurl', 'option');

    if (empty($client_id) || empty($client_secret) || empty($api_url)) {
        error_log('[Game BSC OTT] Exchange: missing SSO config (client_id/secret/api_url).');
        return null;
    }

    $token_url = rtrim($api_url, '/') . '/sso/oauth/token';

    // Log request (bắt buộc theo spec: ghi nhật ký giao dịch)
    error_log('[Game BSC OTT] Exchange request: URL=' . $token_url . ', OTT=' . substr($ott, 0, 8) . '...');

    $response = wp_remote_post($token_url, [
        'body' => [
            'grant_type'         => 'urn:ietf:params:oauth:grant-type:token-exchange',
            'subject_token'      => $ott,
            'subject_token_type' => 'urn:ietf:params:oauth:token-type:access_token',
            'client_id'          => $client_id,
            'client_secret'      => $client_secret,
        ],
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('[Game BSC OTT] Exchange WP error: ' . $response->get_error_message());
        return ['error' => $response->get_error_message()];
    }

    $http_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body  = wp_remote_retrieve_body($response);

    if ($http_code < 200 || $http_code >= 300) {
        error_log('[Game BSC OTT] Exchange HTTP ' . $http_code . ': ' . $raw_body);
        return ['error' => 'HTTP ' . $http_code];
    }

    $data = json_decode($raw_body, true);

    // Kiểm tra response format theo spec BSC: { s, em, data: { accessToken, ... } }
    if (!is_array($data)) {
        error_log('[Game BSC OTT] Exchange: invalid JSON response. Body: ' . $raw_body);
        return ['error' => 'Invalid JSON'];
    }

    // Trường hợp lỗi từ BSC
    if (isset($data['s']) && $data['s'] === 'error') {
        $em = $data['em'] ?? 'Unknown error from BSC';
        error_log('[Game BSC OTT] Exchange BSC error: ' . $em);
        return ['error' => $em];
    }

    // Trường hợp thành công: s=ok, data.accessToken
    if (isset($data['s']) && $data['s'] === 'ok' && !empty($data['data']['accessToken'])) {
        return [
            'access_token' => $data['data']['accessToken'],
            'refresh_token' => $data['data']['refreshToken'] ?? null,
            'token_type'    => $data['data']['token_type'] ?? 'Bearer',
            'expires_in'    => $data['data']['expires_in'] ?? 3600,
            'scope'         => $data['data']['scope'] ?? null,
        ];
    }

    // Fallback: response format không đúng spec
    error_log('[Game BSC OTT] Exchange: unexpected response format. Body: ' . $raw_body);
    return ['error' => 'Unexpected response format'];
}

// ==== Khởi tạo SESSION sớm ====
add_action('init', function () {
    // Chỉ khởi tạo session cho front-end, không phải admin
    // if (session_status() === PHP_SESSION_NONE && !is_admin()) {
    //     // Cookie session an toàn
    //     $params = session_get_cookie_params();
    //     session_set_cookie_params([
    //         'lifetime' => 0,
    //         'path'     => $params['path'] ?? '/',
    //         'domain'   => $params['domain'] ?? '',
    //         'secure'   => is_ssl(),
    //         'httponly' => true,
    //         'samesite' => 'Lax',
    //     ]);
    //     @session_start();
    // }

    // Xử lý lưu utm_source từ query string vào cookie
    if ( empty( $_GET['utm_source'] ) ) {
        return;
    }

    // Lấy và sạch dữ liệu (wp_unslash nếu giá trị đến từ query string)
    $utm = sanitize_text_field( wp_unslash( $_GET['utm_source'] ) );

    // Thời gian hết hạn: 1 ngày
    $expire = time() + DAY_IN_SECONDS; // DAY_IN_SECONDS = 86400

    // Nếu PHP >= 7.3 bạn có thể dùng dạng array options (hỗ trợ SameSite)
    if ( PHP_VERSION_ID >= 70300 ) {
        setcookie( 'utm_source', $utm, [
            'expires'  => $expire,
            'path'     => defined('COOKIEPATH') ? COOKIEPATH : '/',
            'domain'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
    } else {
        // Dự phòng cho PHP cũ: không có SameSite option
        setcookie(
            'utm_source',
            $utm,
            $expire,
            ( defined('COOKIEPATH') ? COOKIEPATH : '/' ),
            ( defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '' ),
            is_ssl(),
            false
        );
    }

    // (Tùy chọn) cập nhật superglobal để có thể đọc ngay trong cùng request
    $_COOKIE['utm_source'] = $utm;
}, 0);

// Autoload dependencies using Composer
if ( file_exists( GAME_BSC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once GAME_BSC_PLUGIN_DIR . 'vendor/autoload.php';
}
// Includes
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/install-tables.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/post-type.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/acf-fields.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/templates.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/artifact-period.php';

// REST API JSON response — chỉ format response, không xử lý session.
// Việc clear session khi 401 được xử lý tập trung tại hook rest_post_dispatch.
function wg_json_response(int $resCode, $data, string $message = 'success', ?int $http_status = null) {
    $payload = [
        'resCode' => $resCode,
        'data'    => $data,
        'message' => $message,
    ];
    $resp = new WP_REST_Response($payload);
    $resp->set_status($http_status ?? $resCode);
    return $resp;
}

/**
 * Force logout: Thu hồi session token game và xóa toàn bộ cookies liên quan.
 * Được gọi tập trung từ rest_post_dispatch khi response là 401.
 */
function game_force_logout_cookies() {
    $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    $cookie_path   = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $host          = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    // Revoke game token từ DB và xóa GAME_AUTH_COOKIE khỏi trình duyệt
    if (!empty($_COOKIE[GAME_AUTH_COOKIE])) {
        game_revoke_user_token($_COOKIE[GAME_AUTH_COOKIE]);
    }

    // Xóa access_token cookie trên nhiều phạm vi domain/path để đảm bảo sạch sẽ
    foreach (['/', $cookie_path] as $path) {
        setcookie('access_token', '', time() - 3600, $path, $cookie_domain);
        if ($host) {
            setcookie('access_token', '', time() - 3600, $path, $host);
        }
    }
    unset($_COOKIE['access_token']);
}

// Lấy thời gian hiện tại theo múi giờ Asia/Ho_Chi_Minh
function game_now($type = null)
{
    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
    $nowLocal = new DateTime('now', $tz);
    if($type === 'date') {
    $started_at = $nowLocal->format('Y-m-d');
    } else {
    $started_at = $nowLocal->format('Y-m-d H:i:s');
    }
    return $started_at;
}
// 
/*
* Hàm lưu thông tin user vào database
* @param array $user_info User information (provider, external_user_id, avatar_url)
* @param string|null $access_token BSC access token để lấy tiểu khoản (optional)
*/
function save_game_user_to_db($user_info, $access_token = null) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'game_users';
  $prefix = $wpdb->prefix . 'game_';

  // Kiểm tra nếu user đã tồn tại
  $existing_user = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $table_name WHERE external_user_id = %s",
      $user_info['external_user_id']
  ));

  if (!$existing_user) {
     // Gọi api lấy thông tin user
    $api_url = get_field('cdapi_ip_address_apiurl', 'option') . 'user/info';
    $access_token = $_COOKIE['access_token'];
    $response = wp_remote_get( $api_url, array(
        'headers' => array(
          'Authorization' => 'Bearer ' . $access_token,
          'Content-Type' => 'application/x-www-form-urlencoded',
        ),
    ) );
    if ( is_wp_error( $response ) ) {
      $error_message = '[GAME] Lỗi khi kết nối đến API (user/info): ' . $response->get_error_message();
        // Ghi vào debug.log
      error_log($error_message);
      return false;
    }
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    if($data['s'] == 'ok' && isset($data['d']['userinfo']['fullname']) && !empty($data['d']['userinfo']['fullname'])) {
      $name = $data['d']['userinfo']['fullname'];
    } else {
      $name = $user_info['external_user_id'];
    }
    // Bắt đầu transaction cho user mới
    $wpdb->query('START TRANSACTION');

    // Thêm user mới
    $result_insert = $wpdb->insert(
        $table_name,
        [
          'provider'          => $user_info['provider'],
          'external_user_id'  => $user_info['external_user_id'],
          'name'              => $name,
          'avatar_url'        => $user_info['avatar_url'],
          'status'            => 1, // active
          'created_at'        => game_now(),
          'last_login_at'     => game_now(),
          'access_token_hash' => $access_token ? md5($access_token) : null,
        ]
    );

    if (!$result_insert) {
      $wpdb->query('ROLLBACK');
      return false;
    }

    $user_id = $wpdb->insert_id;

    // Insert login log
    $login_log = $wpdb->insert(
      $prefix . 'user_login_logs',
      [
        'user_id'    => $user_id,
        'provider'   => $user_info['provider'],
        'checked_at' => game_now(),
        'result'     => 'OK',
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'raw'        => null,
      ]
    );

    if (!$login_log) {
      $wpdb->query('ROLLBACK');
      return false;
    }

    $wpdb->query('COMMIT');

    // Lấy tiểu khoản thường cơ sở từ BSC Trading API nếu có access_token
    if ($access_token) {
      bsc_game_sync_afacctno($user_id, $access_token);
    }

    return $user_id;

  } else {
    $name = $existing_user->name;
    // Nếu user['external_user_id'] == user['name'] thì gọi api lấy tên đầy đủ cập nhật lại(Với tài khoản tạo trước khi update code gọi api lấy thông tin)
    if($existing_user->external_user_id == $existing_user->name) {
      $api_url = get_field('cdapi_ip_address_apiurl', 'option') . 'user/info';
      $access_token = $_COOKIE['access_token'];
      $response = wp_remote_get( $api_url, array(
          'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/x-www-form-urlencoded',
          ),
      ) );
      if ( is_wp_error( $response ) ) {
        $error_message = '[GAME] Lỗi khi kết nối đến API (user/info): ' . $response->get_error_message();
        // Ghi vào debug.log
        error_log($error_message);
      }
      $body = wp_remote_retrieve_body( $response );
      $data = json_decode( $body, true );
      if($data['s'] == 'ok' && isset($data['d']['userinfo']['fullname']) && !empty($data['d']['userinfo']['fullname'])) {
        $name = $data['d']['userinfo']['fullname'];
      }
    }
    // User đã tồn tại - cập nhật last_login_at
    $result_update = $wpdb->update(
        $table_name,
        [
          'name'              => $name,
          'last_login_at'     => game_now(),
          'access_token_hash' => $access_token ? md5($access_token) : null,
        ],
        [
          'id' => $existing_user->id,
        ]
    );

    if ($result_update === false) {
      return false;
    }

    // Insert login log (không cần transaction nếu update đã thành công)
    $login_log = $wpdb->insert(
        $prefix . 'user_login_logs',
        [
            'user_id'    => $existing_user->id,
            'provider'   => $user_info['provider'],
            'checked_at' => game_now(),
            'result'     => 'OK',
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'raw'        => null,
        ]
    );

    if (!$login_log) {
      return false;
    }

    // Lấy tiểu khoản thường cơ sở từ BSC Trading API nếu có access_token
    if ($access_token) {
      bsc_game_sync_afacctno($existing_user->id, $access_token);
    }

    return $existing_user->id;
  }
}
/* 
* Hàm xử lý token để lấy số tài khoản chứng khoản
*/
function parse_token_parts($token) {
  $parts = explode('||', (string)$token, 3);
  return [
      'part1'      => $parts[0] ?? null,
      'client_id'  => $parts[1] ?? null,
      'opaque'     => $parts[2] ?? null,
  ];
}

/**
 * Generate and persist an opaque auth token for a user.
 * Returns the plain token (to set in cookie) or false on failure.
 */
function game_generate_user_token($user_id, $ttl = 10800) {
  global $wpdb;
  $table = $wpdb->prefix . 'game_user_tokens';
  try {
    $token = bin2hex(random_bytes(32));
  } catch (Throwable $e) {
    return false;
  }
  $hash = hash('sha256', $token);
  $expires = new DateTime('now', TIMEZONE);
  $expires->modify('+' . $ttl . ' seconds');
  $expires_at = $expires->format('Y-m-d H:i:s');
  $inserted = $wpdb->insert($table, [
    'user_id' => $user_id,
    'token_hash' => $hash,
    'created_at' => game_now(),
    'expires_at' => $expires_at,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
  ]);
  if ($inserted) return $token;
  return false;
}

/**
 * Set the auth cookie (httpOnly) for the generated token.
 */
function game_set_auth_cookie($token, $ttl = 10800) {
  $expire = time() + $ttl;
  if ( PHP_VERSION_ID >= 70300 ) {
    setcookie( GAME_AUTH_COOKIE, $token, [
      'expires'  => $expire,
      'path'     => defined('COOKIEPATH') ? COOKIEPATH : '/',
      'domain'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
      'secure'   => is_ssl(),
      'httponly' => true,
      'samesite' => 'Lax',
    ] );
  } else {
    setcookie(
      GAME_AUTH_COOKIE,
      $token,
      $expire,
      ( defined('COOKIEPATH') ? COOKIEPATH : '/' ),
      ( defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '' ),
      is_ssl(),
      true
    );
  }
  // Make available in same request
  $_COOKIE[GAME_AUTH_COOKIE] = $token;
}

/**
 * Đánh dấu cookie báo đây là tài khoản không hợp lệ (nước ngoài hoặc tổ chức).
 * Cookie được PHP server đọc qua $_COOKIE để trả về error_code='game_invalid_account' trong API response.
 * httpOnly=true vì chỉ server cần đọc, JS không cần truy cập trực tiếp.
 */
function game_set_invalid_account_cookie($ttl = 10800, $value = '1') {
  $expire = time() + $ttl;
  $cookie_name = 'game_invalid_account';
  if ( PHP_VERSION_ID >= 70300 ) {
    setcookie( $cookie_name, $value, [
      'expires'  => $expire,
      'path'     => defined('COOKIEPATH') ? COOKIEPATH : '/',
      'domain'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
      'secure'   => is_ssl(),
      'httponly' => true, 
      'samesite' => 'Lax',
    ] );
  } else {
    setcookie(
      $cookie_name,
      $value,
      $expire,
      ( defined('COOKIEPATH') ? COOKIEPATH : '/' ),
      ( defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '' ),
      is_ssl(),
      false
    );
  }
  $_COOKIE[$cookie_name] = $value;
}

/**
 * Xoá cookie game_invalid_account (khi user hợp lệ đăng nhập thành công).
 */
function game_clear_invalid_account_cookie() {
  $cookie_name = 'game_invalid_account';
  if ( PHP_VERSION_ID >= 70300 ) {
    setcookie( $cookie_name, '', [
      'expires'  => time() - 3600,
      'path'     => defined('COOKIEPATH') ? COOKIEPATH : '/',
      'domain'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
      'secure'   => is_ssl(),
      'httponly' => false,
      'samesite' => 'Lax',
    ] );
  } else {
    setcookie(
      $cookie_name,
      '',
      time() - 3600,
      ( defined('COOKIEPATH') ? COOKIEPATH : '/' ),
      ( defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '' ),
      is_ssl(),
      false
    );
  }
  unset($_COOKIE[$cookie_name]);
}

/**
 * Validate an auth token from cookie. Returns user array on success or WP_Error.
 */
function game_validate_user_token($token) {
  if (empty($token)) return new WP_Error('not_logged_in', 'Plugin auth token is empty', ['status' => 401]);
  global $wpdb;
  $table = $wpdb->prefix . 'game_user_tokens';
  $user_table = $wpdb->prefix . 'game_users';
  $hash = hash('sha256', $token);
  $now = game_now();
  // Piggyback access_token_hash vào JOIN sẵn có — không tốn thêm query
  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT t.user_id, u.provider, u.name, u.external_user_id, u.avatar_url, u.access_token_hash
     FROM {$table} t JOIN {$user_table} u ON u.id = t.user_id
     WHERE t.token_hash = %s AND t.expires_at >= %s",
    $hash, $now
  ));
  if (!$row) return new WP_Error('not_logged_in', 'Plugin auth token not found or expired in DB', ['status' => 401]);
  return [
    'id'               => (int)$row->user_id,
    'provider'         => $row->provider,
    'external_user_id' => $row->external_user_id,
    'name'             => $row->name,
    'avatar_url'       => $row->avatar_url ?: WG_GAME_DEFAULT_AVATAR_URL,
    'access_token_hash' => $row->access_token_hash, // dùng để detect multi-login
  ];
}

/**
 * Revoke a token (useful for logout) and clear cookie.
 */
function game_revoke_user_token($token) {
  if (empty($token)) return false;
  global $wpdb;
  $table = $wpdb->prefix . 'game_user_tokens';
  $hash = hash('sha256', $token);
  // Remove the token record entirely so validation only checks expiry
  $deleted = $wpdb->delete($table, ['token_hash' => $hash]);
  // Clear cookie
  if ( PHP_VERSION_ID >= 70300 ) {
    setcookie( GAME_AUTH_COOKIE, '', [
      'expires'  => time() - 3600,
      'path'     => defined('COOKIEPATH') ? COOKIEPATH : '/',
      'domain'   => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
      'secure'   => is_ssl(),
      'httponly' => true,
      'samesite' => 'Lax',
    ] );
  } else {
    setcookie( GAME_AUTH_COOKIE, '', time() - 3600, ( defined('COOKIEPATH') ? COOKIEPATH : '/' ), ( defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '' ), is_ssl(), true );
  }
  unset($_COOKIE[GAME_AUTH_COOKIE]);
  return (bool)$deleted;
}

/**
 * Clean up expired tokens for a user. If user_id is null, cleans all expired tokens.
 */
function game_cleanup_expired_tokens($user_id = null) {
  global $wpdb;
  $table = $wpdb->prefix . 'game_user_tokens';
  $now = game_now();
  
  if ($user_id) {
    // Delete expired tokens for specific user
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE user_id = %d AND expires_at < %s", $user_id, $now));
  } else {
    // Delete all expired tokens
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %s", $now));
  }
}

/**
 *  Hàm trả về url sso
 */
function bsc_game_url_sso()
{
  $redirect_uri = get_field('cdapi_ip_address_url_call_back', 'option');
  $current_url = site_url('/duong-dua-chung-si');
  $client_id = get_field('cdapi_ip_address_clientid', 'option');
  if(function_exists('pll_current_language')) {
    $current_lang = pll_current_language();
  } else {
    $current_lang = 'vi';
  }
  $url = get_field('cdapi_ip_address_apilogin', 'option') . "sso/oauth/authorize?client_id=" . $client_id . "&response_type=code&redirect_uri=" . $redirect_uri . "&scope=general&ui_locales=" . $current_lang . "&state=" . $current_url . "";
  return $url;
}

/**
 *  Hàm trả về url logout sso
 */
function bsc_game_url_sso_logout()
{
  $url = get_field('cdapi_ip_address_apilogin', 'option') . "sso/oauth/logout";
  return $url;
}
/**
 *  Hàm handle sso callback
 */
function bsc_game_handle_sso_callback()
{
  if (!isset($_COOKIE['access_token'])) {
    return;
  }

  // ===== Kiểm tra token plugin còn hạn không =====
  if (isset($_COOKIE[GAME_AUTH_COOKIE])) {
    $existing = game_validate_user_token($_COOKIE[GAME_AUTH_COOKIE]);
    if (!is_wp_error($existing)) {
      return; // Token còn hợp lệ, không cần tạo mới
    }
  }

  $access_token = $_COOKIE['access_token'];
  $token_parts  = parse_token_parts($access_token);
  $custodycd    = $token_parts['part1'] ?? '';

  // ===== RULE: Chỉ cho phép tài khoản nội địa (prefix 002C) =====
  if (empty($custodycd) || substr($custodycd, 0, 4) !== '002C' || !preg_match('/^002C[a-zA-Z0-9]+$/', $custodycd)) {
    // Tài khoản nước ngoài hoặc format không đúng
    if (!empty($custodycd) && (substr($custodycd, 0, 4) === '002F' || preg_match('/^002F[a-zA-Z0-9]+$/', $custodycd))) {
      // Tài khoản nước ngoài
      game_set_invalid_account_cookie(10800, 'foreign');
    } else {
      game_set_invalid_account_cookie(10800, '1');
    }
    return;
  }

  // ===== RULE: Kiểm tra loại tài khoản (Cá nhân hay Tổ chức) =====
  $server_api_noi_bo = get_option('game_bsc_api_base_url');
  if (!empty($server_api_noi_bo)) {
      $server_api_noi_bo = rtrim($server_api_noi_bo, '/');
      $api_url_market_data = (strpos($server_api_noi_bo, '/api') !== false)
          ? $server_api_noi_bo . '/MarketData/GetCustomerByCustodycd'
          : $server_api_noi_bo . '/api/MarketData/GetCustomerByCustodycd';
      
      $response = wp_remote_post($api_url_market_data, [
        'body'    => ['custodycd' => $custodycd],
        'timeout' => 10,
      ]);
      
      if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['s']) && $body['s'] === 'ok' && !empty($body['d'][0]['custtype'])) {
            if ($body['d'][0]['custtype'] === 'B') {
                // Là tài khoản tổ chức -> Chặn
                game_set_invalid_account_cookie(10800);
                return;
            }
        }
      }
  }

  // Tài khoản hợp lệ (Cá nhân) → xoá cờ chặn nếu còn tồn tại
  game_clear_invalid_account_cookie();

  // ===== Xác định provider từ utm_source cookie =====
  $utm_source_cookie = $_COOKIE['utm_source'] ?? '';
  if (!empty($utm_source_cookie)) {
    switch ($utm_source_cookie) {
      case MTRADER_APP:     $provider = MTRADER_APP;     break;
      case BSC_SMART_INVEST: $provider = BSC_SMART_INVEST; break;
      case WEBTRADING:      $provider = WEBTRADING;      break;
      default:              $provider = BSC_WEB;         break;
    }
  } else {
    $provider = BSC_WEB;
  }

  // ===== Lưu user vào DB (insert hoặc update last_login) =====
  $user_id = save_game_user_to_db([
    'provider'         => $provider,
    'external_user_id' => $custodycd,
    'avatar_url'       => WG_GAME_DEFAULT_AVATAR_URL,
  ], $access_token);

  if (!$user_id) {
    return;
  }




  // ===== Tạo plugin auth token (opaque, 3h) =====
  $token = game_generate_user_token($user_id, 10800);
  if ($token) {
    game_set_auth_cookie($token, 10800);
    game_cleanup_expired_tokens($user_id);
  }
}

/**
 * Gọi BSC Trading API /trade/accounts và lưu AFACCTNO vào cột afacctno của game_users.
 *
 * Điều kiện tìm tiểu khoản thường cơ sở Active:
 *   - TH1: accounttype = 'SEC' và mrtype = 'N'
 *   - TH2: typename hoặc entypename = 'Thường' (nếu không đạt TH1)
 *
 * Hàm được gọi bất đồng bộ trong callback SSO nên KHÔNG block request;
 * lỗi chỉ ghi vào error_log, không ném exception.
 *
 * @param int    $user_id      ID trong bảng game_users
 * @param string $access_token BSC access_token nguyên bản (từ cookie)
 * @return void
 */
function bsc_game_sync_afacctno(int $user_id, string $access_token): void
{
  global $wpdb;

  // ----- Xác định Trading Server URL -----
  $trading_server = (string)(get_option('game_bsc_trading_server') ?: '');

  if (empty($trading_server)) {
    error_log('[BSC SSO] bsc_game_sync_afacctno: missing trading server URL');
    return;
  }
  $trading_server = rtrim($trading_server, '/');

  // ----- Gọi GET /trade/accounts -----
  $response = wp_remote_get($trading_server . '/trade/accounts', [
    'headers' => [
      'Authorization' => 'Bearer ' . $access_token,
      'Content-Type'  => 'application/json',
      'Accept'        => 'application/json',
    ],
    'timeout' => 10,
  ]);

  if (is_wp_error($response)) {
    error_log('[BSC SSO] bsc_game_sync_afacctno wp_error: ' . $response->get_error_message());
    return;
  }

  $http_code = (int) wp_remote_retrieve_response_code($response);
  if ($http_code < 200 || $http_code >= 300) {
    error_log('[BSC SSO] bsc_game_sync_afacctno HTTP ' . $http_code);
    return;
  }

  $body = json_decode(wp_remote_retrieve_body($response), true);

  if (
    !is_array($body) ||
    ($body['s'] ?? '') !== 'ok' ||
    !isset($body['d']) ||
    !is_array($body['d'])
  ) {
    error_log('[BSC SSO] bsc_game_sync_afacctno invalid body: ' . wp_json_encode($body));
    return;
  }

  // ----- Tìm tiểu khoản thường cơ sở -----
  $afacctno = null;
  foreach ($body['d'] as $account) {
    if (
      (strtoupper($account['accounttype']   ?? '') === 'SEC' && strtoupper($account['mrtype'] ?? '') === 'N') ||
      ($account['typename']                 ?? '') === 'Thường' ||
      ($account['entypename']               ?? '') === 'Thường'
    ) {
      $afacctno = (string)($account['acctno'] ?? '');
      break;
    }
  }

  if (empty($afacctno)) {
    error_log('[BSC SSO] bsc_game_sync_afacctno: không tìm thấy tiểu khoản thường cơ sở cho user_id=' . $user_id);
    return;
  }

  // ----- Lưu vào cột afacctno trong game_users -----
  $table  = $wpdb->prefix . 'game_users';
  $result = $wpdb->update(
    $table,
    ['afacctno' => sanitize_text_field($afacctno)],
    ['id'       => $user_id],
    ['%s'],
    ['%d']
  );

  if ($result === false) {
    error_log('[BSC SSO] bsc_game_sync_afacctno DB update error: ' . $wpdb->last_error);
  }
}
/**
 * HÀM CHÍNH: Kiểm tra token xác thực từ cookie
 *
 * @return array|WP_Error Thông tin user (array) nếu ok; WP_Error nếu chưa đăng nhập/không hợp lệ.
 */

function game_sso_require_session() {

    // Code dev
    $user = [
        'id'       => 1,
        'provider'    => 'bsc',
        'external_user_id'    => '123456',
        'name'    => 'Triệu Ngọc Tài',
        'avatar_url'    => WG_GAME_DEFAULT_AVATAR_URL,
    ];
    $_SESSION['game_user']        = $user;
    $_SESSION['game_logged_in_at'] = time();
      if (session_status() === PHP_SESSION_ACTIVE) {
          session_write_close(); // nhả khóa ngay
      }
    return $user;

  // 1. Kiểm tra token từ cookie game
  if (empty($_COOKIE[GAME_AUTH_COOKIE])) {
    return new WP_Error('not_logged_in', 'Plugin auth token cookie (game_auth_token) is missing', ['status' => 401]);
  }

  $user = game_validate_user_token($_COOKIE[GAME_AUTH_COOKIE]);
  if (is_wp_error($user)) {
    return $user;
  }

  // 2. Kiểm tra token SSO (access_token)
  if (empty($_COOKIE['access_token'])) {
    // Không có access_token, xem như chưa đăng nhập sso
    game_revoke_user_token($_COOKIE[GAME_AUTH_COOKIE]);
    return new WP_Error('not_logged_in', 'SSO access token cookie (access_token) is missing', ['status' => 401]);
  }

  $access_token = $_COOKIE['access_token'];

  // 3. [MULTI-LOGIN DETECTION] So sánh hash token cookie vs hash lưu trong DB.
  // Dữ liệu đã được piggyback sẵn từ JOIN trong game_validate_user_token — không tốn thêm query.
  // Nếu user B đăng nhập cùng account, DB sẽ có hash mới của token B.
  // User A gọi API tiếp: md5(tokenA) ≠ hash trong DB → kick ra ngay lập tức.
  $stored_hash = $user['access_token_hash'] ?? null;
  if ($stored_hash !== null && md5($access_token) !== $stored_hash) {
    error_log('[SSO check] game_sso_require_session: access_token superseded by new login for user_id=' . $user['id']);
    game_force_logout_cookies();
    return new WP_Error('not_logged_in', 'Session superseded by new login (access token hash mismatch)', ['status' => 401]);
  }

  // 4. Kiểm tra SSO token có còn hợp lệ với server không (chống token hết hạn tự nhiên)
  // Dùng transient 60s để tránh gọi HTTP liên tục
  $transient_key = 'sso_token_checked_' . md5($access_token);

  // 3. Kiểm tra cache transient trước để tránh gọi API liên tục
  if (!get_transient($transient_key)) {
    // Gọi API /trade/user/info để kiểm tra token SSO
    $trading_server = (string)(get_option('game_bsc_trading_server') ?: '');
    if (empty($trading_server)) {
      error_log('[SSO check] game_sso_require_session: missing trading server URL');
      return $user;
    }

    $trading_server = rtrim($trading_server, '/');
    $api_url = $trading_server . '/trade/user/info';

    $response = wp_remote_get($api_url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
      ],
      'timeout' => 5,
    ]);

    if (is_wp_error($response)) {
      error_log('[SSO check] game_sso_require_session error: ' . $response->get_error_message());
      // Lỗi kết nối mạng, tạm thời cho qua để tránh gián đoạn trải nghiệm người dùng
      return $user;
    }

    $http_code = (int) wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
      error_log('[SSO check] game_sso_require_session: SSO token invalid, HTTP ' . $http_code);

      // Token SSO không hợp lệ / hết hạn → force logout toàn bộ session
      game_force_logout_cookies();

      return new WP_Error('not_logged_in', 'SSO session expired (trading server status: ' . $http_code . ')', ['status' => 401]);
    }

    // Token SSO hợp lệ -> Lưu cache transient trong 1 phút (60 giây) để phát hiện hết hạn nhanh
    set_transient($transient_key, true, 60);
  }

  return $user;
}

// Hàm tính thời gian diễn ra game
function game_bsc_compute_day_index() {
	$tz = TIMEZONE;
	
	$start_raw = get_option('game_bsc_start_date');
	$end_raw   = get_option('game_bsc_end_date');
	
	try {
		if (empty($start_raw) || empty($end_raw)) {
			throw new Exception('Missing options');
		}
		
		// Chuẩn hoá về DateTimeImmutable theo TZ WP
		$start = new DateTimeImmutable($start_raw, $tz);
		$end   = new DateTimeImmutable($end_raw,   $tz);
		
		// Nếu option chỉ là 'Y-m-d' (không có giờ), gắn mốc ngày
		$start = $start->setTime(0, 0, 0);
		$end   = $end->setTime(23, 59, 59);
		
		if ($end < $start) {
			throw new Exception('end before start');
		}
		
		$today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
		
		// ✅ FIX: Tính tổng số ngày làm việc (T2-T6) - CHỈ ĐẾM N=1-5
		$total_days = 0;
		$current = $start;
		while ($current <= $end->setTime(0, 0, 0)) {
			$dow = (int)$current->format('N'); // 1=Mon, 7=Sun
			if ($dow >= 1 && $dow <= 5) { // Chỉ T2-T6
				$total_days++;
			}
			$current = $current->modify('+1 day');
		}
		
		if ($today < $start) {
			return [
				'day_index'      => 0,
				'status'         => 'not_started',
				'start_date'     => $start->format('Y-m-d'),
				'end_date'       => $end->format('Y-m-d'),
				'today'          => $today->format('Y-m-d'),
				'total_days'     => $total_days,
			];
		}
		
		if ($today > $end->setTime(0,0,0)) {
			return [
				'day_index'      => $total_days,
				'status'         => 'ended',
				'start_date'     => $start->format('Y-m-d'),
				'end_date'       => $end->format('Y-m-d'),
				'today'          => $today->format('Y-m-d'),
				'total_days'     => $total_days,
			];
		}
		
		// ✅ FIX: Đang trong thời gian game - tính day_index chỉ cho ngày làm việc (T2-T6)
		$day_index = 0;
		$current = $start;
		while ($current <= $today) {
			$dow = (int)$current->format('N'); // 1=Mon, 7=Sun
			if ($dow >= 1 && $dow <= 5) { // Chỉ T2-T6
				$day_index++;
			}
			$current = $current->modify('+1 day');
		}
		
		$days_passed = $day_index;
		$days_remaining = max(0, $total_days - $days_passed);
		
		return [
			'day_index'      => $day_index,
			'status'         => 'ongoing',
			'start_date'     => $start->format('Y-m-d'),
			'end_date'       => $end->format('Y-m-d'),
			'today'          => $today->format('Y-m-d'),
			'total_days'     => $total_days,
		];
	} catch (Throwable $e) {
		return [
			'day_index'      => 0,
			'status'         => 'invalid',
			'start_date'     => $start_raw ?: null,
			'end_date'       => $end_raw   ?: null,
			'today'          => (new DateTimeImmutable('now', $tz))->format('Y-m-d'),
			'total_days'     => null,
		];
	}
}

// Phiên bản 2 của hàm tính ngày
//    // Bảng phiên chơi của user
//    $tables[$prefix . 'users_play_sessions'] = "CREATE TABLE {$prefix}users_play_sessions (
//        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
//        user_id INT UNSIGNED NOT NULL,
//        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian bắt đầu lượt chơi
//        finished_at DATETIME NULL, -- Thời gian kết thúc lượt chơi
//        questions_count TINYINT UNSIGNED NOT NULL, -- Số câu hỏi cho lượt chơi
//        allowed_retries TINYINT UNSIGNED NOT NULL, -- Tổng số lần được phép trả lời lại cho lượt chơi
//        retries_used TINYINT UNSIGNED NOT NULL DEFAULT 0, -- Đã dùng bao nhiêu lượt retry
//        correct_count TINYINT UNSIGNED NOT NULL DEFAULT 0, -- Tổng số câu trả lời đúng trong lượt này
//        credit_delta INT NOT NULL DEFAULT -1, -- Ghi nhận trừ 1 lượt khi mở phiên - để đối soát
//        ip VARCHAR(45) NULL,
//        user_agent TEXT NULL,
//        current_stage INT UNSIGNED NULL,
//        PRIMARY KEY (id),
//        KEY idx_user_started (user_id, started_at)
//    ) ENGINE=InnoDB $charset_collate;";
// tính chặng hiện tại của user

// curren_stage = chang hiện tại của user
// nếu ko có bản ghi thì chặng = 0
//                     <label>Từ chặng <input type="number" style="width: 5%" name="stages[${index}][from_stage]"></label>
//                        <label>Đến chặng <input type="number" style="width: 5%" name="stages[${index}][to_stage]"></label>

function game_bsc_compute_day_index_v2($user_id) {
	$tz = new DateTimeZone('Asia/Ho_Chi_Minh');
	$start_raw = get_option('game_bsc_start_date');
	$end_raw   = get_option('game_bsc_end_date');
	$today = (new DateTimeImmutable('now', $tz))->setTime(0,0,0);
	
	try {
		if (empty($start_raw) || empty($end_raw)) {
			throw new Exception('Missing options');
		}
		
		$start = (new DateTimeImmutable($start_raw, $tz))->setTime(0,0,0);
		$end   = (new DateTimeImmutable($end_raw, $tz))->setTime(23,59,59);
		
		// Tính tổng số ngày làm việc T2-T6
		$total_days = 0;
		$current = $start;
		while ($current <= $end->setTime(0,0,0)) {
			$dow = (int)$current->format('N'); // 1=Mon, 7=Sun
			if ($dow >= 1 && $dow <= 5) {
				$total_days++;
			}
			$current = $current->modify('+1 day');
		}
		
		global $wpdb;
		$table = $wpdb->prefix . 'game_users_play_sessions';
		
		// Lấy current_stage mới nhất của user trong thời gian game
		$current_stage = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT current_stage, current_stage_status
            FROM {$table}
            WHERE user_id = %d
              AND DATE(started_at) >= %s
              AND DATE(started_at) <= %s
            ORDER BY started_at DESC
            LIMIT 1",
            $user_id,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ),
        ARRAY_A
    );
		
		if (!$current_stage) {
      $current_stage = array(
        'current_stage' => 0,
        'current_stage_status' => 0,
      );
		}
		
		// Xác định trạng thái game
		if ($today < $start) {
			$status = 'not_started';
		} elseif ($today > $end->setTime(0,0,0)) {
			$status = 'ended';
		} else {
			$status = 'ongoing';
		}
		
		// Tính toán chặng hiện tại thực tế (nếu đã xong chặng trước thì lên chặng mới)
		$stage_num = intval($current_stage['current_stage']);
		$stage_status = intval($current_stage['current_stage_status']);
		
		if ($stage_num === 0) {
			$day_index = 1; // User mới
		} elseif ($stage_status === 1) {
			$day_index = $stage_num + 1; // Đã qua chặng trước -> Lên chặng tiếp theo
		} else {
			$day_index = $stage_num; // Đang chơi dở -> Giữ nguyên chặng
		}
		
		return [
			'day_index' => $day_index,
			'status' => $status,
			'total_days' => $total_days,
			'start_date' => $start->format('Y-m-d'),
			'end_date' => $end->format('Y-m-d'),
			'today' => $today->format('Y-m-d'),
			'current_stage_status' => $stage_status,
		];
		
	} catch (Throwable $e) {
		return [
			'day_index' => 0,
			'status' => 'invalid',
			'total_days' => null,
			'start_date' => $start_raw ?: null,
			'end_date' => $end_raw ?: null,
			'today' => (new DateTimeImmutable('now', $tz))->format('Y-m-d'),
      'current_stage_status' => 0
		];
	}
}

// HELPER functions
require_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/user-inventory.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/function-custom.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/rate-limiter.php';

// REST API
require_once GAME_BSC_PLUGIN_DIR . 'includes/api/rest-init.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/api/rest-missions.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/api/rest-users.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/api/rest-sessions.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/api/rest-play-sessions.php';
require_once(GAME_BSC_PLUGIN_DIR . 'includes/api/rest-rules.php');
require_once(GAME_BSC_PLUGIN_DIR . 'includes/api/rest-gift.php');
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/gotit-init.php';

// Cho phép (bỏ qua auth) riêng các route /wp-json/game-bsc/*
add_filter('rest_authentication_errors', function ($result) {
    if (true === $result || is_wp_error($result)) {
        return $result;
    }

    // Lấy URI hiện tại
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $rest_route = $_GET['rest_route'] ?? '';

    $is_game_bsc =
        (is_string($uri) && strpos($uri, '/wp-json/game-bsc/') !== false) ||
        (is_string($rest_route) && strpos($rest_route, '/game-bsc/') === 0);

    if ($is_game_bsc) {
        return true;
    }

    return $result;
}, 1);


/**
 * Áp dụng rate limit cho các API của game-bsc
 */
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
	// Chỉ áp dụng cho các route của game-bsc
	if (strpos($request->get_route(), '/game-bsc/') === false) {
		return $result;
	}
	
	// Lấy user_id nếu đã đăng nhập
	$user_id = null;
	$user = game_sso_require_session();
	if (!is_wp_error($user)) {
		$user_id = $user['id'];
	}
	
	// Kiểm tra rate limit
	$check = game_check_rate_limit($user_id);
	if (is_wp_error($check)) {
		return $check;
	}
	
	return $result;
}, 10, 3);


// Hàm lưu hoàn thành nhiệm vụ đăng nhập hàng ngày
function save_user_daily_login_mission() {
  global $wpdb;
  $user = game_sso_require_session();
  if (is_wp_error($user) || empty($user['id'])) {
    return false;
  }
  
  $user_id = absint($user['id']);
  $table_missions = $wpdb->prefix . 'game_user_mission_logs';
  $today = game_now('date');

  // Check nếu đã hết thời gian diễn ra game thì return false
  $game_period = game_bsc_compute_day_index();
  if (($game_period['status'] ?? '') === 'ended') {
    return false;
  }

  
  // Kiểm tra user đã hoàn thành nhiệm vụ daily_login hôm nay chưa
  $existing = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM $table_missions WHERE user_id = %d AND mission_code = %s AND mission_date = %s",
    $user_id,
    DAILY_LOGIN_CODE,
    $today
  ));
  
  // Nếu đã hoàn thành rồi thì không cần lưu lại
  if ($existing) {
    return true;
  }
  
  // Lấy thông tin nhiệm vụ từ ACF để lấy reward value
  $saved_tasks = get_option('game_bsc_tasks', []);
  if (!is_array($saved_tasks)) {
    return false;
  }
  
  $mission_reward = 0;
  
  // Tìm kiếm nhiệm vụ daily_login trong danh sách đã lưu để lấy reward value
  foreach ($saved_tasks as $code => $task) {
    if (!empty($task) && $code === DAILY_LOGIN_CODE) {
      $mission_reward = !empty($task['reward_spins']) ? (int)$task['reward_spins'] : 0;
      break;
    }
  }
  if($mission_reward <= 0) {
    return false;
  }
  // echo $mission_reward;die;
  // Băt đầu lưu nhiệm vụ
  $wpdb->query('START TRANSACTION');

  // Lưu bản ghi hoàn thành nhiệm vụ (luôn là PLAY_CREDIT)
  $result = $wpdb->insert(
    $table_missions,
    [
      'user_id'      => $user_id,
      'mission_code' => DAILY_LOGIN_CODE,
      'mission_date' => $today,
      'reward_type'  => 'PLAY_CREDIT',
      'reward_value' => $mission_reward,
      'status'       => 'VERIFIED',
      'verified_at'  => game_now(),
    ],
    [
      '%d',
      '%s',
      '%s',
      '%s',
      '%d',
      '%s',
      '%s',
    ]
  );
  
  if (!$result) {
    $wpdb->query('ROLLBACK');
    return false;
  }
  $mission_id = $wpdb->insert_id;
  
  // Cộng PLAY_CREDIT vào balance của user
  $table_balance = $wpdb->prefix . 'game_play_credit_balances';
  
  // Kiểm tra user đã có record trong play_credit_balances chưa
  $existing_balance = $wpdb->get_row(
    $wpdb->prepare("SELECT user_id FROM $table_balance WHERE user_id = %d", $user_id)
  );
  
  if ($existing_balance) {
    // Update balance
    $result_balance = $wpdb->query($wpdb->prepare(
      "UPDATE $table_balance SET balance = balance + %d WHERE user_id = %d",
      $mission_reward,
      $user_id
    ));
    
    if (!$result_balance) {
      $wpdb->query('ROLLBACK');
      return false;
    }

  } else {
    // Insert mới
    $result_balance = $wpdb->insert(
      $table_balance,
      [
        'user_id' => $user_id,
        'balance' => $mission_reward,
      ],
      ['%d', '%d']
    );

    if (!$result_balance) {
      $wpdb->query('ROLLBACK');
      return false;
    }
  }
  
  // Log biến động play credit
  $ledger_table = $wpdb->prefix . 'game_play_credit_ledger';
  $result_ledger = $wpdb->insert(
    $ledger_table,
    [
      'user_id'    => $user_id,
      'delta'      => $mission_reward,
      'ref_type'   => 'MISSION',
      'ref_id'     => $mission_id,
      'created_at' => game_now(),
    ],
    ['%d', '%d', '%s', '%d', '%s']
  );
  if (!$result_ledger) {
    $wpdb->query('ROLLBACK');
    return false;
  }
  $wpdb->query('COMMIT');
  return true;
}


/**
 * Function help upload SVG
 */
/**
 * Allow SVG uploads for administrator users.
 *
 * @param array $upload_mimes Allowed mime types.
 *
 * @return mixed
 */
add_filter(
	'upload_mimes',
	function ($upload_mimes) {
		// By default, only administrator users are allowed to add SVGs.
		// To enable more user types edit or comment the lines below but beware of
		// the security risks if you allow any user to upload SVG files.
		if ( ! current_user_can( 'admin_game' ) ) {
			return $upload_mimes;
		}
		
		$upload_mimes['svg'] = 'image/svg+xml';
		$upload_mimes['svgz'] = 'image/svg+xml';
		
		return $upload_mimes;
	}
);

/**
 *  helper dùng để lấy huy hiệu của user khi đăng nhập, lưu vào bảng wp_game_user_badges
 *
 */

function save_user_badges() {
	global $wpdb;
	$user = game_sso_require_session();
	if (is_wp_error($user) || empty($user['id'])) {
		return false;
	}

	$user_id = absint($user['id']);
	$prefix  = $wpdb->prefix . 'game_';

	try {
		// Bắt đầu transaction (bao toàn bộ vòng lặp)
		$wpdb->query('START TRANSACTION');

		// Tính trước 1 lần, dùng cho tất cả các vòng
		$consecutive_days = game_get_consecutive_play_days($user_id);
		$total_days       = game_get_total_play_days($user_id);
		$badges_awarded   = 0;
		$max_iterations   = 20; // Giới hạn an toàn tránh vòng lặp vô tận

		// Vòng lặp: cấp hết tất cả badge mà user đủ điều kiện trong 1 lần login
		while ($max_iterations-- > 0) {

			// Đếm lại số huy hiệu đang có (sau mỗi lần insert)
			$total_record = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}user_badges WHERE user_id = %d",
				$user_id
			));

			// Tính badge_order tiếp theo cần cấp
			$badge_order = ($total_record === 0) ? 1 : ($total_record + 1);

			$query = new WP_Query([
				'post_type'      => 'game_badges',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => 'badge_order',
				'meta_query'     => [
					[
						'key'     => 'badge_order',
						'value'   => $badge_order,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]);

			// Không còn badge tiếp theo → dừng vòng lặp
			if (!$query->have_posts()) {
				break;
			}

			$badge_post_id  = $query->posts[0]->ID;
			$condition_type = get_field('condition_type', $badge_post_id) ?: '';
			$is_get_badge   = false;

			if ($condition_type === 'consecutive_days') {
				$is_get_badge = $consecutive_days >= (int) get_field('consecutive_days', $badge_post_id);
			} elseif ($condition_type === 'total_days') {
				$is_get_badge = $total_days >= (int) get_field('total_days', $badge_post_id);
			}

			// Điều kiện không thỏa → không còn badge nào cấp được → dừng
			if (!$is_get_badge) {
				break;
			}

			// Insert huy hiệu
			if (!$wpdb->insert(
				"{$prefix}user_badges",
				[
					'user_id'       => $user_id,
					'badge_post_id' => $badge_post_id,
					'awarded_at'    => game_now(),
				],
				['%d', '%d', '%s']
			)) {
				$wpdb->query('ROLLBACK');
				return false;
			}

			$user_badge_id = $wpdb->insert_id;
			$points_reward = (int) get_field('points_reward', $badge_post_id);

			if ($points_reward > 0) {
				// Cộng điểm vào số dư
				if (!$wpdb->query($wpdb->prepare(
					"INSERT INTO {$prefix}user_points_balances (user_id, balance, updated_at)
					 VALUES (%d, %d, %s)
					 ON DUPLICATE KEY UPDATE
					    balance    = balance + VALUES(balance),
					    updated_at = VALUES(updated_at)",
					$user_id,
					$points_reward,
					game_now()
				))) {
					$wpdb->query('ROLLBACK');
					return false;
				}

				// Ghi log điểm
				if (!$wpdb->insert(
					"{$prefix}user_points_ledger",
					[
						'user_id'    => $user_id,
						'delta'      => $points_reward,
						'ref_type'   => 'BADGE',
						'ref_id'     => $user_badge_id,
						'created_at' => game_now(),
					],
					['%d', '%d', '%s', '%d', '%s']
				)) {
					$wpdb->query('ROLLBACK');
					return false;
				}
			}

			$badges_awarded++;
			// Tiếp tục vòng lặp để check badge kế tiếp
		}

		// Commit toàn bộ
		$wpdb->query('COMMIT');
		return $badges_awarded > 0;

	} catch (Exception $e) {
		$wpdb->query('ROLLBACK');
		return false;
	}
}

/**
 * Filter: Cho phép người dùng có quyền 'administrator' cũng được coi là có quyền 'admin_game'
 * Điều này giúp Administrator có thể quản lý game mà không cần gán riêng quyền admin_game
 */
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
	// Kiểm tra nếu đang yêu cầu quyền admin_game
	if (isset($args[0]) && $args[0] === 'admin_game') {
		// Nếu user có quyền administrator (Administrator), tự động cấp quyền admin_game
		if (!empty($allcaps['administrator'])) {
			$allcaps['admin_game'] = true;
		}
	}
	return $allcaps;
}, 10, 4);
