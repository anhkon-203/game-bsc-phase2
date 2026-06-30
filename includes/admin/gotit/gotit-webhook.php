<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Got It Biz Webhook - Nhận thông báo thay đổi trạng thái voucher tự động
 */

// Đăng ký REST API Route
if (get_option('game_bsc_gotit_enable_wp_webhook', '1') === '1') {
    add_action('rest_api_init', function () {
        register_rest_route(NS, '/gotit-webhook', [
            'methods'             => 'POST',
            'callback'            => 'game_bsc_gotit_webhook_handler',
            'permission_callback' => '__return_true', // Công khai, xác thực qua chữ ký (sign)
        ]);
    });
}

/**
 * Xử lý webhook request từ Got It
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_gotit_webhook_handler(WP_REST_Request $request) {
    $body = $request->get_json_params();

    // 1. Validate cấu trúc request body cơ bản
    if (empty($body) || !isset($body['total']) || !isset($body['sign']) || !isset($body['data']) || !is_array($body['data'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            game_bsc_gotit_log_webhook($body, 'invalid_body', 0, 'Thiếu thông tin bắt buộc trong request body (total, sign, data)');
        }
        return wg_json_response(400, [], __('Request body không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    $total = (int)$body['total'];
    $data_vouchers = $body['data'];

    // Nếu total > 0 nhưng data lại rỗng
    if ($total > 0 && empty($data_vouchers)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            game_bsc_gotit_log_webhook($body, 'invalid_body', 0, 'Total > 0 nhưng mảng data rỗng');
        }
        return wg_json_response(400, [], __('Dữ liệu voucher trống.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    // 2. Xác thực chữ ký (Secret Code / RSA)
    if (!game_bsc_gotit_verify_webhook_sign($body)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            game_bsc_gotit_log_webhook($body, 'auth_failed', 0, 'Chữ ký webhook không hợp lệ');
        }
        return wg_json_response(401, [], __('Chữ ký không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    // 3. Xử lý từng voucher trong data
    $processed_count = 0;
    $errors = [];

    foreach ($data_vouchers as $voucher_data) {
        $res = game_bsc_gotit_process_webhook_voucher($voucher_data);
        if ($res['success']) {
            $processed_count++;
        } else {
            $errors[] = $res['message'];
        }
    }

    // 4. Xác định trạng thái tổng thể
    $status = 'success';
    $error_detail = '';
    if (!empty($errors)) {
        $error_detail = implode(' | ', $errors);
        $status = ($processed_count > 0) ? 'partial' : 'failed';
    }

    // 5. Ghi log webhook
    game_bsc_gotit_log_webhook($body, $status, $processed_count, $error_detail);

    // 6. Trả về response thành công (tránh Got It gửi lại liên tục)
    return wg_json_response(200, [
        'processed_count' => $processed_count,
        'total_vouchers'  => count($data_vouchers),
        'status'          => $status
    ], __('Xử lý webhook thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

/**
 * Xác thực chữ ký webhook từ Got It
 *
 * @param array $body Request body dạng mảng
 * @return bool
 */
function game_bsc_gotit_verify_webhook_sign($body) {
    if (empty($body['sign']) || !isset($body['data']) || !is_array($body['data'])) {
        return false;
    }

    // 1. Tạo chuỗi voucher_string theo tài liệu:
    // Sắp xếp mã voucher code tăng dần theo thứ tự a-z0-9 và nối lại bằng dấu chấm '.'
    $codes = [];
    foreach ($body['data'] as $voucher) {
        if (!empty($voucher['voucherCode'])) {
            $codes[] = trim((string)$voucher['voucherCode']);
        }
    }

    // Nếu không có voucher code nào
    if (empty($codes)) {
        return false;
    }

    sort($codes, SORT_STRING);
    $voucher_string = implode('.', $codes);
    $received_sign = trim((string)$body['sign']);

    // 2. Phương thức 1: Xác thực bằng Secret Code (AES/SHA256) - Được khuyến nghị
    // Lấy secret key từ option cài đặt hoặc config mặc định
    $secret = get_option('game_bsc_gotit_webhook_secret', '');
    if (empty($secret)) {
        $secret = (string)game_bsc_gotit_source_value('webhook_secret', '');
    }

    if (!empty($secret)) {
        $dataHash   = md5($voucher_string);
        $secretHash = md5($secret);
        $expected_sign = hash('sha256', $secretHash . $dataHash . $secretHash);

        if (hash_equals($expected_sign, $received_sign)) {
            return true;
        }
    }

    // 3. Phương thức 2: RSA Fallback (Dự phòng bằng Public Key)
    $public_key_pem = (string)game_bsc_gotit_source_value('public_key', '');
    if (!empty($public_key_pem)) {
        $public_key = openssl_pkey_get_public($public_key_pem);
        if ($public_key) {
            $signature = base64_decode($received_sign, true);
            if ($signature !== false) {
                $ok = openssl_verify($voucher_string, $signature, $public_key, OPENSSL_ALGO_SHA256) === 1;
                if ($ok) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Xử lý cập nhật trạng thái cho từng voucher
 *
 * @param array $voucher_data
 * @return array Mảng [success => bool, message => string]
 */
function game_bsc_gotit_process_webhook_voucher($voucher_data) {
    global $wpdb;

    $voucher_code = isset($voucher_data['voucherCode']) ? trim((string)$voucher_data['voucherCode']) : '';
    $serial = isset($voucher_data['voucherSerial']) ? trim((string)$voucher_data['voucherSerial']) : '';
    $new_state_code = isset($voucher_data['newStateCode']) ? (int)$voucher_data['newStateCode'] : 0;
    $new_state_name = isset($voucher_data['newStateName']) ? trim((string)$voucher_data['newStateName']) : '';
    $expired_date = isset($voucher_data['expiredDate']) ? trim((string)$voucher_data['expiredDate']) : '';
    $status_changed_at = isset($voucher_data['statusChangedAt']) ? trim((string)$voucher_data['statusChangedAt']) : '';

    if (empty($voucher_code) && empty($serial)) {
        return [
            'success' => false,
            'message' => 'Voucher không có voucherCode và voucherSerial'
        ];
    }

    // Tìm giao dịch voucher tương ứng trong DB
    $table_transactions = $wpdb->prefix . 'game_gotit_transactions';
    
    // Xây dựng điều kiện tìm kiếm: ưu tiên voucher code, dự phòng serial
    if (!empty($voucher_code) && !empty($serial)) {
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT id, gotit_status, gotit_voucher_code, gotit_serial FROM {$table_transactions} WHERE gotit_voucher_code = %s OR gotit_serial = %s LIMIT 1",
            $voucher_code,
            $serial
        ));
    } elseif (!empty($voucher_code)) {
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT id, gotit_status, gotit_voucher_code, gotit_serial FROM {$table_transactions} WHERE gotit_voucher_code = %s LIMIT 1",
            $voucher_code
        ));
    } else {
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT id, gotit_status, gotit_voucher_code, gotit_serial FROM {$table_transactions} WHERE gotit_serial = %s LIMIT 1",
            $serial
        ));
    }

    if (!$transaction) {
        return [
            'success' => false,
            'message' => sprintf('Không tìm thấy voucherCode: %s, Serial: %s trong hệ thống', $voucher_code, $serial)
        ];
    }

    // Chuẩn bị mảng dữ liệu cập nhật
    $update_data = [
        'gotit_status'            => $new_state_code,
        'gotit_state_name'        => sanitize_text_field($new_state_name),
        'updated_at'              => game_now(),
    ];

    if (!empty($status_changed_at)) {
        $update_data['gotit_status_changed_at'] = sanitize_text_field($status_changed_at);
    } else {
        $update_data['gotit_status_changed_at'] = game_now();
    }

    // Cập nhật ngày hết hạn nếu có thay đổi từ Got It
    if (!empty($expired_date)) {
        $update_data['gotit_expiry_date'] = sanitize_text_field($expired_date) . ' 23:59:59';
    }

    // Cập nhật cả voucher code hoặc serial nếu trong DB đang thiếu nhưng webhook trả về có
    if (empty($transaction->gotit_voucher_code) && !empty($voucher_code)) {
        $update_data['gotit_voucher_code'] = sanitize_text_field($voucher_code);
    }
    if (empty($transaction->gotit_serial) && !empty($serial)) {
        $update_data['gotit_serial'] = sanitize_text_field($serial);
    }

    // Thực hiện cập nhật vào DB
    $updated = $wpdb->update(
        $table_transactions,
        $update_data,
        ['id' => $transaction->id]
    );

    if ($updated === false) {
        return [
            'success' => false,
            'message' => sprintf('Lỗi DB khi cập nhật voucherCode: %s', $voucher_code)
        ];
    }

    return [
        'success' => true,
        'message' => sprintf('Cập nhật thành công voucherCode: %s sang trạng thái %s (%d)', $voucher_code, $new_state_name, $new_state_code)
    ];
}

/**
 * Ghi log nhận webhook vào CSDL để audit
 *
 * @param array  $request_body
 * @param string $status
 * @param int    $processed_count
 * @param string $error_detail
 * @return void
 */
function game_bsc_gotit_log_webhook($request_body, $status, $processed_count, $error_detail = '') {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'game_gotit_webhook_logs';

    $total = isset($request_body['total']) ? (int)$request_body['total'] : 0;
    
    $wpdb->insert($table_logs, [
        'request_body'    => wp_json_encode($request_body),
        'total_vouchers'  => $total,
        'processed_count' => (int)$processed_count,
        'status'          => sanitize_text_field($status),
        'error_detail'    => sanitize_textarea_field($error_detail),
        'ip_address'      => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        'created_at'      => game_now(),
    ]);
}
