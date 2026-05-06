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
define('WG_GAME_PLUGIN_DB_VERSION', '27.0');
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

// REST API JSON response 
function wg_json_response(int $resCode, $data, string $message = 'success', ?int $http_status = null) {
    $payload = [
        'resCode' => $resCode,
        'data'    => $data,
        'message' => $message,
    ];
    $resp = new WP_REST_Response($payload);
    // Nếu không set http_status riêng, dùng cùng mã với resCode (mặc định 200)
    $resp->set_status($http_status ?? $resCode);
    return $resp;
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
          'provider'         => $user_info['provider'],
          'external_user_id' => $user_info['external_user_id'],
          'name'             => $name,
          'avatar_url'       => $user_info['avatar_url'],
          'status'           => 1, // active
          'created_at'       => game_now(),
          'last_login_at'    => game_now(),
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
          'name' => $name,
          'last_login_at' => game_now(),
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
 * Đánh dấu cookie báo đây là tài khoản nước ngoài (không được phép chơi Gamification).
 * Cookie được PHP server đọc qua $_COOKIE để trả về error_code='foreign_account' trong API response.
 * httpOnly=true vì chỉ server cần đọc, JS không cần truy cập trực tiếp.
 */
function game_set_foreign_account_cookie($ttl = 10800) {
  $expire = time() + $ttl;
  $cookie_name = 'game_foreign_account';
  if ( PHP_VERSION_ID >= 70300 ) {
    setcookie( $cookie_name, '1', [
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
      '1',
      $expire,
      ( defined('COOKIEPATH') ? COOKIEPATH : '/' ),
      ( defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '' ),
      is_ssl(),
      false
    );
  }
  $_COOKIE[$cookie_name] = '1';
}

/**
 * Xoá cookie game_foreign_account (khi user hợp lệ đăng nhập thành công).
 */
function game_clear_foreign_account_cookie() {
  $cookie_name = 'game_foreign_account';
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
  if (empty($token)) return new WP_Error('not_logged_in', 'User not logged in', ['status' => 401]);
  global $wpdb;
  $table = $wpdb->prefix . 'game_user_tokens';
  $user_table = $wpdb->prefix . 'game_users';
  $hash = hash('sha256', $token);
  $now = game_now();
  $row = $wpdb->get_row($wpdb->prepare("SELECT t.user_id, u.provider, u.name, u.external_user_id, u.avatar_url FROM {$table} t JOIN {$user_table} u ON u.id = t.user_id WHERE t.token_hash = %s AND t.expires_at >= %s", $hash, $now));
  if (!$row) return new WP_Error('not_logged_in', 'User not logged in', ['status' => 401]);
  return [
    'id' => (int)$row->user_id,
    'provider' => $row->provider,
    'external_user_id' => $row->external_user_id,
    'name' => $row->name,
    'avatar_url' => $row->avatar_url ?: WG_GAME_DEFAULT_AVATAR_URL,
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
  if (!isset($_COOKIE['access_token']) || isset($_COOKIE[GAME_AUTH_COOKIE])) {
    return;
  }

  $access_token = $_COOKIE['access_token'];
  $token_parts  = parse_token_parts($access_token);
  $custodycd    = $token_parts['part1'] ?? '';

  // ===== RULE: Chỉ cho phép tài khoản nội địa (prefix 002C) =====
  // SECURITY FIX: Cần validate cả format  
  if (empty($custodycd) || substr($custodycd, 0, 4) !== '002C' || !preg_match('/^002C[a-zA-Z0-9]+$/', $custodycd)) {
    // Đánh dấu cookie để FE biết đây là tài khoản nước ngoài hoặc invalid
    game_set_foreign_account_cookie(10800);
    return;
  }

  // Tài khoản hợp lệ → xoá cờ nước ngoài nếu còn tồn tại
  game_clear_foreign_account_cookie();

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


  // ===== Kiểm tra token plugin còn hạn không =====
  if (!empty($_COOKIE[GAME_AUTH_COOKIE])) {
    $existing = game_validate_user_token($_COOKIE[GAME_AUTH_COOKIE]);
    if (!is_wp_error($existing)) {
      return; // Token còn hợp lệ, không cần tạo mới
    }
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
 *   - accounttype   = 'SEC'  (chứng khoán)
 *   - mrtype        = 'N'    (thường – không ký quỹ)
 *   - alternateacct = 'Y'    (tiểu khoản cơ sở)
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
  $trading_server = '';
  // Ưu tiên: lấy từ option settings
  $trading_server = (string)(get_option('game_bsc_trading_server') ?? '');
  // Fallback: lấy từ ACF field (cũ)
  if (empty($trading_server) && function_exists('get_field')) {
    $trading_server = (string)(get_field('cdapi_ip_address_tradingserver', 'option') ?? '');
  }
  // Fallback cuối: dùng URL mặc định
  if (empty($trading_server)) {
    $trading_server = 'https://tradeapi-krxtduat.bsc.com.vn';
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
      strtoupper($account['accounttype']   ?? '') === 'SEC' &&
      strtoupper($account['mrtype']        ?? '') === 'N'   &&
      strtoupper($account['alternateacct'] ?? '') === 'Y'
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
    // 5) Lưu SESSION và trả về
    $_SESSION['game_user']        = $user;
    $_SESSION['game_logged_in_at'] = time();
      if (session_status() === PHP_SESSION_ACTIVE) {
          session_write_close(); // nhả khóa ngay
      }
    return $user;

  // Kiểm tra token từ cookie
  // if (!empty($_COOKIE[GAME_AUTH_COOKIE])) {
  //   $user = game_validate_user_token($_COOKIE[GAME_AUTH_COOKIE]);
  //   if (!is_wp_error($user)) {
  //     return $user;
  //   }
  // }

  return new WP_Error('not_logged_in', 'User not logged in', ['status' => 401]);
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
		
		return [
			'day_index' => intval($current_stage['current_stage']),
			'status' => $status,
			'total_days' => $total_days,
			'start_date' => $start->format('Y-m-d'),
			'end_date' => $end->format('Y-m-d'),
			'today' => $today->format('Y-m-d'),
      'current_stage_status' => intval($current_stage['current_stage_status']),
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
  $tz = TIMEZONE;
  $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

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
      'user_id'  => $user_id,
      'delta'    => $mission_reward,
      'ref_type' => 'MISSION',
      'ref_id'   => $mission_id,
    ],
    ['%d', '%d', '%s', '%d']
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
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		// Bắt đầu transaction
		$wpdb->query('START TRANSACTION');
		
		// Đếm số huy hiệu user đang có
		$total_record = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$prefix}user_badges WHERE user_id = %d",
			$user_id
		));
		
		// Lấy huy hiệu tiếp theo theo thứ tự (hoặc cái đầu tiên)
		$badge_order = ($total_record == 0) ? 1 : ($total_record + 1);
		
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
					'type'    => 'NUMERIC'
				]
			]
		]);
		
		if (!$query->have_posts()) {
			$wpdb->query('ROLLBACK');
			return false; // Không còn huy hiệu tiếp theo
		}
		
		$badge_post_id = $query->posts[0]->ID;
		
		// Điều kiện nhận huy hiệu
		$consecutive_days = game_get_consecutive_play_days($user_id);
		$total_days       = game_get_total_play_days($user_id);
		$condition_type   = get_field('condition_type', $badge_post_id) ?: '';
		$is_get_badge     = false;
		
		if ($condition_type === 'consecutive_days') {
			$is_get_badge = $consecutive_days >= (int)get_field('consecutive_days', $badge_post_id);
		} elseif ($condition_type === 'total_days') {
			$is_get_badge = $total_days >= (int)get_field('total_days', $badge_post_id);
		}
		
		if (!$is_get_badge) {
			$wpdb->query('ROLLBACK');
			return false;
		}
		
		// Insert huy hiệu
		if (!$wpdb->insert(
			"{$prefix}user_badges",
			[
				'user_id'       => $user_id,
				'badge_post_id' => $badge_post_id,
				'awarded_at'    => game_now(),
			],
			['%d','%d','%s']
		)) {
			$wpdb->query('ROLLBACK');
			return false;
		}
		
		$user_badge_id = $wpdb->insert_id;
		$points_reward = (int)get_field('points_reward', $badge_post_id);
		
		if ($points_reward > 0) {
			// Insert / update số dư
			if (!$wpdb->query($wpdb->prepare(
				"INSERT INTO {$prefix}user_points_balances (user_id, balance, updated_at)
                 VALUES (%d, %d, %s)
                 ON DUPLICATE KEY UPDATE
                    balance = balance + VALUES(balance),
                    updated_at = VALUES(updated_at)",
				$user_id,
				$points_reward,
				game_now()
			))) {
				$wpdb->query('ROLLBACK');
				return false;
			}
			
			// Ghi log
			if (!$wpdb->insert(
				"{$prefix}user_points_ledger",
				[
					'user_id'    => $user_id,
					'delta'      => $points_reward,
					'ref_type'   => 'BADGE',
					'ref_id'     => $user_badge_id,
					'created_at' => game_now()
				],
				['%d','%d','%s','%d','%s']
			)) {
				$wpdb->query('ROLLBACK');
				return false;
			}
		}
		
		// Commit transaction
		$wpdb->query('COMMIT');
		return true;
		
	} catch (Exception $e) {
		// Rollback nếu có lỗi
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
