<?php
/**
 * Got It Standalone Webhook Service
 * Handles voucher status changes notifications from Got It.
 * 
 * Works independently of WordPress core for security and high performance.
 */

declare(strict_types=1);



// -----------------------------------------------------------------------------
// 1. BOOTSTRAP & CONFIGURATION
// -----------------------------------------------------------------------------
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error: Configuration missing.']);
    exit;
}
require_once $config_file;

date_default_timezone_set(APP_TIMEZONE);

// -----------------------------------------------------------------------------
// 2. CORE UTILITIES
// -----------------------------------------------------------------------------

/**
 * Ghi log an toàn ra file (tự động xoay file nếu quá lớn)
 * Production mode (DEBUG_MODE=false): chỉ ghi ERROR và CRITICAL
 */
function webhook_log(string $level, string $message, array $context = []): void {
    // Khi tắt debug, bỏ qua INFO / DEBUG / WARNING để giảm I/O disk trên production
    $production_levels = ['ERROR', 'CRITICAL'];
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        if (!in_array(strtoupper($level), $production_levels, true)) {
            return;
        }
    }

    $log_file = LOG_FILE;
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        if (!mkdir($log_dir, 0755, true) && !is_dir($log_dir)) {
            return; // Cannot create directory
        }
    }

    // Rotate log file if it exceeds size
    if (file_exists($log_file) && filesize($log_file) > LOG_MAX_SIZE) {
        rename($log_file, $log_file . '.' . date('YmdHis') . '.bak');
    }

    $timestamp = date('Y-m-d H:i:s');
    $context_str = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    $log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}" . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Làm sạch chuỗi text tương đương sanitize_text_field() của WordPress
 * (standalone service không load WP nên phải tự cài đặt)
 * - Bỏ thẻ HTML
 * - Loại bỏ ký tự xuống dòng, tab
 * - Gộp nhiều khoảng trắng thành một
 * - Trim hai đầu
 */
function sanitize_text(string $value): string {
    // Bỏ thẻ HTML/PHP
    $value = strip_tags($value);
    // Chuyển xuống dòng/tab thành khoảng trắng, sau đó gộp whitespace liên tiếp
    $value = preg_replace('/[\r\n\t ]+/', ' ', $value) ?? '';
    return trim($value);
}

/**
 * Trả về HTTP JSON Response và kết thúc script
 */
function send_json_response(int $status_code, bool $success, string $message, array $data = []): never {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message,
    ];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    webhook_log($status_code >= 400 ? 'ERROR' : 'INFO', "Response (HTTP {$status_code})", $response);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lấy IP thực của Client qua các Proxy thông dụng
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return $ip;
}

// -----------------------------------------------------------------------------
// 3. SECURITY & VALIDATION MIDDLEWARES
// -----------------------------------------------------------------------------



/**
 * Kiểm tra method & content-type
 */
function check_request_headers(): void {
    if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
        send_json_response(405, false, 'Method Not Allowed. Expected POST.');
    }

    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'application/json') === false) {
        // Chỉ warn, có thể Got It không gửi đúng header, nhưng chuẩn REST nên có
        webhook_log('WARNING', "Content-Type is not application/json", ['content_type' => $content_type]);
    }
}

/**
 * Đọc và validate JSON payload
 */
function parse_and_validate_payload(): array {
    $raw_body = file_get_contents('php://input');

    if (empty($raw_body)) {
        send_json_response(400, false, 'Empty request body.');
    }

    if (strlen($raw_body) > MAX_BODY_SIZE) {
        webhook_log('ERROR', 'Payload too large', ['size' => strlen($raw_body)]);
        send_json_response(413, false, 'Payload Too Large.');
    }

    webhook_log('DEBUG', 'Received payload', ['raw' => $raw_body]);

    $body = json_decode($raw_body, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
        send_json_response(400, false, 'Invalid JSON format.');
    }

    if (!isset($body['total'], $body['sign'], $body['data']) || !is_array($body['data'])) {
        send_json_response(400, false, 'Missing required payload fields (total, sign, data).');
    }

    if (count($body['data']) > MAX_VOUCHERS_PER_REQUEST) {
        send_json_response(413, false, 'Too many vouchers in request.');
    }

    return [$raw_body, $body];
}

/**
 * Xác thực chữ ký từ Got It
 */
function verify_signature(array $body): void {
    $codes = [];
    foreach ($body['data'] as $voucher) {
        if (!empty($voucher['voucherCode'])) {
            $codes[] = trim((string)$voucher['voucherCode']);
        }
    }

    if (empty($codes)) {
        webhook_log('ERROR', 'Signature verification failed: No voucher codes found');
        send_json_response(400, false, 'No voucherCode found to verify signature.');
    }

    sort($codes, SORT_STRING);
    $voucher_string = implode('.', $codes);
    $received_sign = trim((string)$body['sign']);
    $is_valid = false;

    // Cách 1: Xác thực bằng SHA256 (Secret Code Method)
    if (!empty(GOTIT_WEBHOOK_SECRET)) {
        $secretHash = md5(GOTIT_WEBHOOK_SECRET);
        $dataHash   = md5($voucher_string);
        $expected_sign = hash('sha256', $secretHash . $dataHash . $secretHash);
        
        if (hash_equals($expected_sign, $received_sign)) {
            $is_valid = true;
        }
    }

    // Cách 2: Xác thực bằng RSA Public Key (Fallback)
    if (!$is_valid && !empty(GOTIT_PUBLIC_KEY)) {
        $public_key = openssl_pkey_get_public(GOTIT_PUBLIC_KEY);
        if ($public_key) {
            $signature = base64_decode($received_sign, true);
            if ($signature !== false) {
                if (openssl_verify($voucher_string, $signature, $public_key, OPENSSL_ALGO_SHA256) === 1) {
                    $is_valid = true;
                }
            }
        }
    }

    if (!$is_valid) {
        webhook_log('ERROR', 'Invalid signature provided', [
            'expected_string' => $voucher_string,
            'received_sign' => $received_sign
        ]);
        send_json_response(401, false, 'Unauthorized. Invalid signature.');
    }
}

// -----------------------------------------------------------------------------
// 4. DATABASE HANDLERS
// -----------------------------------------------------------------------------

/**
 * Khởi tạo kết nối DB bằng PDO — dùng static variable để reuse trong cùng 1 request
 */
function get_db_connection(): PDO {
    // Reuse kết nối đã tạo trước đó thay vì mở socket mới mỗi lần gọi
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        WEBHOOK_DB_HOST,
        WEBHOOK_DB_NAME,
        WEBHOOK_DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, WEBHOOK_DB_USER, WEBHOOK_DB_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        webhook_log('CRITICAL', 'Database connection failed', ['error' => $e->getMessage()]);
        send_json_response(500, false, 'Internal database connection error.');
    }
}

/**
 * Xử lý cập nhật một voucher
 */
function process_single_voucher(PDO $pdo, array $voucher_data): array {
    $voucher_code = isset($voucher_data['voucherCode']) ? trim((string)$voucher_data['voucherCode']) : '';
    $serial       = isset($voucher_data['voucherSerial']) ? trim((string)$voucher_data['voucherSerial']) : '';
    $new_state_code = isset($voucher_data['newStateCode']) ? (int)$voucher_data['newStateCode'] : 0;
    $new_state_name = isset($voucher_data['newStateName']) ? trim((string)$voucher_data['newStateName']) : '';
    $expired_date   = isset($voucher_data['expiredDate']) ? trim((string)$voucher_data['expiredDate']) : '';
    $status_changed = isset($voucher_data['statusChangedAt']) ? trim((string)$voucher_data['statusChangedAt']) : date('Y-m-d H:i:s');

    if (empty($voucher_code) && empty($serial)) {
        return ['success' => false, 'message' => 'Missing voucherCode and voucherSerial'];
    }

    $table = WEBHOOK_TABLE_PREFIX . 'game_gotit_transactions';

    try {
        // Tìm transaction khớp với voucher_code HOẶC serial
        // Chuẩn bị query động dựa trên tham số có sẵn
        $where_clauses = [];
        $search_params = [];
        
        if (!empty($voucher_code)) {
            $where_clauses[] = "gotit_voucher_code = ?";
            $search_params[] = $voucher_code;
        }
        if (!empty($serial)) {
            $where_clauses[] = "gotit_serial = ?";
            $search_params[] = $serial;
        }
        
        $where_sql = implode(' OR ', $where_clauses);
        // Lấy thêm gotit_status để kiểm tra idempotency
        $stmt = $pdo->prepare("SELECT id, gotit_voucher_code, gotit_serial, gotit_status FROM `{$table}` WHERE {$where_sql} LIMIT 1");
        $stmt->execute($search_params);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            return ['success' => false, 'message' => "Not found DB (Code: {$voucher_code}, Serial: {$serial})"];
        }

        // Idempotency: bỏ qua nếu trạng thái đã giống nhau (Got It có thể retry webhook)
        if ((int)$transaction['gotit_status'] === $new_state_code) {
            webhook_log('INFO', "Idempotent skip (already state {$new_state_code})", ['code' => $voucher_code]);
            return ['success' => true, 'message' => "Already state {$new_state_code}: {$voucher_code}"];
        }

        // Tạo mảng dữ liệu update (sanitize khớp với bản gốc WordPress)
        $update_data = [
            'gotit_status'            => $new_state_code,
            'gotit_state_name'        => sanitize_text($new_state_name),
            'updated_at'              => date('Y-m-d H:i:s'),
            'gotit_status_changed_at' => sanitize_text($status_changed),
        ];

        if (!empty($expired_date)) {
            $update_data['gotit_expiry_date'] = sanitize_text($expired_date) . ' 23:59:59';
        }

        // Bổ sung code/serial nếu DB đang thiếu
        if (empty($transaction['gotit_voucher_code']) && !empty($voucher_code)) {
            $update_data['gotit_voucher_code'] = sanitize_text($voucher_code);
        }
        if (empty($transaction['gotit_serial']) && !empty($serial)) {
            $update_data['gotit_serial'] = sanitize_text($serial);
        }

        // Build câu SQL UPDATE
        $set_parts = [];
        $update_params = [];
        foreach ($update_data as $key => $val) {
            $set_parts[] = "`{$key}` = ?";
            $update_params[] = $val;
        }
        $update_params[] = $transaction['id']; // ID cho mệnh đề WHERE
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $set_parts) . " WHERE `id` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($update_params);

        return ['success' => true, 'message' => "Updated code: {$voucher_code} (State: {$new_state_code})"];

    } catch (PDOException $e) {
        webhook_log('ERROR', 'DB Error updating voucher', ['error' => $e->getMessage(), 'voucher' => $voucher_code]);
        return ['success' => false, 'message' => 'DB update exception'];
    }
}

/**
 * Lưu lịch sử request vào DB (Webhook Logs)
 */
function record_webhook_log(PDO $pdo, string $raw_body, int $total, int $processed_count, string $status, string $error_detail): void {
    $table = WEBHOOK_TABLE_PREFIX . 'game_gotit_webhook_logs';
    $client_ip = get_client_ip();

    try {
        $sql = "INSERT INTO `{$table}` (`request_body`, `total_vouchers`, `processed_count`, `status`, `error_detail`, `ip_address`, `created_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $raw_body,
            $total,
            $processed_count,
            $status,
            substr($error_detail, 0, 500), // Cắt ngắn để tránh quá độ dài field
            substr($client_ip, 0, 45),
            date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {
        webhook_log('ERROR', 'Failed to insert webhook DB log', ['error' => $e->getMessage()]);
    }
}

// -----------------------------------------------------------------------------
// 5. MAIN EXECUTION FLOW
// -----------------------------------------------------------------------------
function main(): void {
    webhook_log('INFO', '--- Incoming Webhook Request ---');

    check_request_headers();
    
    // Đọc và validate payload
    [$raw_body, $body] = parse_and_validate_payload();
    
    // Kiểm tra cấu trúc logic
    $total = (int)($body['total'] ?? 0);
    $data_vouchers = $body['data'];

    if ($total > 0 && empty($data_vouchers)) {
        webhook_log('ERROR', 'Total > 0 but data array is empty');
        send_json_response(400, false, 'Data array is empty despite total > 0.');
    }

    // Xác minh danh tính
    verify_signature($body);

    // Xử lý dữ liệu
    $pdo = get_db_connection();
    
    $processed_count = 0;
    $errors = [];

    foreach ($data_vouchers as $voucher_data) {
        $res = process_single_voucher($pdo, $voucher_data);
        if ($res['success']) {
            $processed_count++;
        } else {
            $errors[] = $res['message'];
        }
    }

    // Tổng kết kết quả
    $status = 'success';
    $error_detail = '';
    if (!empty($errors)) {
        $error_detail = implode(' | ', $errors);
        $status = ($processed_count > 0) ? 'partial' : 'failed';
        webhook_log('WARNING', 'Voucher processing had errors', ['errors' => $errors]);
    }

    record_webhook_log($pdo, $raw_body, $total, $processed_count, $status, $error_detail);

    // Phản hồi về Got It (theo chuẩn API Got It, HTTP 200)
    send_json_response(200, true, 'Webhook processed successfully.', [
        'processed_count' => $processed_count,
        'total_vouchers'  => count($data_vouchers),
        'status'          => $status
    ]);
}

// Run application
try {
    main();
} catch (Throwable $e) {
    webhook_log('CRITICAL', 'Unhandled Exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    send_json_response(500, false, 'Internal Server Error. Please contact admin.');
}
