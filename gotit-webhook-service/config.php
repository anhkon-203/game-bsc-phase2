<?php
/**
 * Configuration – GOTIT Standalone Webhook Service
 *
 * Tự động đọc cấu hình từ:
 *   - wp-config.php  → DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, $table_prefix
 *   - wp_options     → game_bsc_gotit_webhook_secret, game_bsc_gotit_public_key
 *
 * KHÔNG cần sửa file này khi deploy – mọi giá trị lấy thẳng từ WordPress.
 */

// =============================================================================
// 1. TỰ ĐỘNG NẠP DB CONFIG TỪ wp-config.php (không load WordPress)
// =============================================================================
(static function (): void {
    // Tìm wp-config.php từ thư mục hiện tại đi lên tối đa 8 cấp
    $dir = __DIR__;
    $wp_config_path = null;

    for ($i = 0; $i < 8; $i++) {
        $candidate = $dir . '/wp-config.php';
        if (file_exists($candidate)) {
            $wp_config_path = $candidate;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    if (!$wp_config_path) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error: wp-config.php not found.']);
        exit;
    }

    // Đọc nội dung wp-config.php nhưng BỎ QUA dòng require wp-settings.php
    // → Chỉ nạp các hằng số DB, không bootstrap toàn bộ WordPress
    // Regex bắt cả hai dạng:
    //   require_once( ABSPATH . 'wp-settings.php' );
    //   require_once '/path/to/wp-settings.php';
    $content = file_get_contents($wp_config_path);
    $content = preg_replace(
        '/^\s*(?:require|include)(?:_once)?\s*[^;]*wp-settings\.php[^;]*;/m',
        '// [wp-settings.php skipped by gotit-webhook-service]',
        $content
    );

    // phpcs:ignore Squiz.PHP.Eval.Discouraged
    eval('?>' . $content);
})();

// Sau khi eval: DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $table_prefix đã được định nghĩa

// =============================================================================
// 2. DATABASE – lấy từ wp-config.php (tự động, không cần sửa)
// =============================================================================
define('WEBHOOK_DB_HOST',     defined('DB_HOST')     ? DB_HOST     : 'localhost');
define('WEBHOOK_DB_NAME',     defined('DB_NAME')     ? DB_NAME     : '');
define('WEBHOOK_DB_USER',     defined('DB_USER')     ? DB_USER     : '');
define('WEBHOOK_DB_PASSWORD', defined('DB_PASSWORD') ? DB_PASSWORD : '');
define('WEBHOOK_DB_CHARSET',  'utf8mb4');

// Table prefix từ WordPress ($table_prefix được định nghĩa trong wp-config.php)
global $table_prefix;
define('WEBHOOK_TABLE_PREFIX', !empty($table_prefix) ? $table_prefix : 'wp_');

// =============================================================================
// 3. GOT IT SECRETS – đọc từ wp_options (giống get_option() của WordPress)
// =============================================================================
(static function (): void {
    /**
     * Đọc một option từ wp_options bằng PDO trực tiếp
     * (không cần load WordPress)
     */
    $read_wp_option = static function (string $option_name) use (&$read_wp_option): string {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                WEBHOOK_DB_HOST,
                WEBHOOK_DB_NAME
            );
            $pdo = new PDO($dsn, WEBHOOK_DB_USER, WEBHOOK_DB_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $table = WEBHOOK_TABLE_PREFIX . 'options';
            $stmt  = $pdo->prepare(
                "SELECT option_value FROM `{$table}` WHERE option_name = ? LIMIT 1"
            );
            $stmt->execute([$option_name]);
            $row = $stmt->fetch();

            return $row ? (string) $row['option_value'] : '';
        } catch (Throwable $e) {
            // Không log ra đây để tránh lộ thông tin nhạy cảm
            return '';
        }
    };

    // Đọc Webhook Secret từ wp_options (lưu bởi plugin game-bsc)
    // Fallback → biến môi trường → chuỗi rỗng
    $secret = $read_wp_option('game_bsc_gotit_webhook_secret')
        ?: (getenv('GOTIT_WEBHOOK_SECRET') ?: '');

    // Đọc Public Key RSA từ wp_options (nếu dùng phương thức RSA)
    $public_key = $read_wp_option('game_bsc_gotit_public_key')
        ?: (getenv('GOTIT_PUBLIC_KEY') ?: '');

    define('GOTIT_WEBHOOK_SECRET', $secret);
    define('GOTIT_PUBLIC_KEY',     $public_key);
})();

// =============================================================================
// 4. TIMEZONE
// =============================================================================
define('APP_TIMEZONE', 'Asia/Ho_Chi_Minh');



// =============================================================================
// 6. REQUEST LIMITS
// =============================================================================
define('MAX_BODY_SIZE',            1 * 1024 * 1024); // 1 MB
define('MAX_VOUCHERS_PER_REQUEST', 500);

// =============================================================================
// 7. LOGGING
// Tắt DEBUG_MODE trên production để không ghi log thừa
// =============================================================================
define('DEBUG_MODE', false);
define('LOG_FILE',    __DIR__ . '/logs/webhook_debug.log');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
