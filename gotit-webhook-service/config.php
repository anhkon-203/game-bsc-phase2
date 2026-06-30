<?php
/**
 * Configuration file for GOTIT Standalone Webhook Service
 *
 * SECURITY NOTE:
 *   - Trên môi trường production, đặt file này BÊN NGOÀI thư mục web root
 *     hoặc chặn truy cập HTTP bằng Nginx/Apache.
 *   - KHÔNG commit giá trị thật (secret, password) lên Git.
 *     Sử dụng biến môi trường hoặc .env nếu có thể.
 */

/* ================================================================
   1. DATABASE
   ================================================================ */
define('WEBHOOK_DB_HOST',     getenv('WEBHOOK_DB_HOST')     ?: 'localhost');
define('WEBHOOK_DB_NAME',     getenv('WEBHOOK_DB_NAME')     ?: 'bsc');
define('WEBHOOK_DB_USER',     getenv('WEBHOOK_DB_USER')     ?: 'root');
define('WEBHOOK_DB_PASSWORD', getenv('WEBHOOK_DB_PASSWORD') ?: '');
define('WEBHOOK_DB_CHARSET',  'utf8mb4');
define('WEBHOOK_TABLE_PREFIX', getenv('WEBHOOK_TABLE_PREFIX') ?: 'wp_');

/* ================================================================
   2. GOT IT SECRETS
   ================================================================ */
// Secret Key (SHA256) - Phương thức xác thực chính
define('GOTIT_WEBHOOK_SECRET', getenv('GOTIT_WEBHOOK_SECRET') ?: '');

// Public Key PEM (RSA) - Phương thức xác thực dự phòng
define('GOTIT_PUBLIC_KEY', getenv('GOTIT_PUBLIC_KEY') ?: '');

/* ================================================================
   3. TIMEZONE
   ================================================================ */
define('APP_TIMEZONE', 'Asia/Ho_Chi_Minh');

/* ================================================================
   4. IP WHITELISTING
   ================================================================ */
define('ENABLE_IP_WHITELIST', false);

// Danh sách IP được phép gọi webhook (cung cấp bởi Got It).
define('ALLOWED_IPS', [
    // '127.0.0.1',
]);

/* ================================================================
   5. REQUEST BODY LIMITS
   ================================================================ */
// Kích thước tối đa body request (bytes). Chặn payload quá lớn.
define('MAX_BODY_SIZE', 1 * 1024 * 1024); // 1 MB

// Số voucher tối đa trong một lần gọi webhook. Chặn batch quá lớn.
define('MAX_VOUCHERS_PER_REQUEST', 500);

/* ================================================================
   6. LOGGING
   ================================================================ */
// Bật/tắt ghi log debug ra file. Tắt trên production nếu không cần.
define('DEBUG_MODE', false);
define('LOG_FILE', __DIR__ . '/logs/webhook_debug.log');

// Kích thước tối đa file log (bytes). Tự xoay (rotate) khi vượt.
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
