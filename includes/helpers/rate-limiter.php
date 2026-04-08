<?php
if (!defined('ABSPATH')) exit;

/**
 * Kiểm tra xem người dùng có vượt quá giới hạn lượt gọi API hay không.
 * 
 * @param int $user_id ID của người dùng cần kiểm tra.
 * @return bool|WP_Error Trả về true nếu trong giới hạn, WP_Error nếu vượt quá.
 */
function game_check_rate_limit($user_id) {
    if (empty($user_id)) {
        // Sử dụng IP nếu người dùng chưa đăng nhập (mặc dù hầu hết route đều yêu cầu session)
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'anonymous';
    } else {
        $identifier = $user_id;
    }

    $limit = 60; // Giới hạn 60 yêu cầu
    $window = 60; // Trong vòng 60 giây
    $transient_key = 'game_rate_limit_' . $identifier;
    
    $current_hits = get_transient($transient_key);

    if ($current_hits === false) {
        // Lần truy cập đầu tiên: lưu số lượt là 1 và lưu thời điểm hết hạn
        set_transient($transient_key, 1, $window);
        set_transient($transient_key . '_expires', time() + $window, $window);
        return true;
    }

    if ($current_hits >= $limit) {
        return new WP_Error(
            'rest_rate_limited',
            'Quá nhiều yêu cầu. Vui lòng thử lại sau 1 phút.',
        );
    }

    // Tăng số lượt yêu cầu
    $current_hits++;
    
    // Tính toán thời gian còn lại để không làm mới (reset) khung 60 giây
    $expires_at = get_transient($transient_key . '_expires');
    $remaining_time = $expires_at ? $expires_at - time() : $window;

    // Chỉ cập nhật giá trị, giữ nguyên thời gian hết hạn còn lại
    if ($remaining_time > 0) {
        set_transient($transient_key, $current_hits, $remaining_time);
    }
    
    return true;
}
