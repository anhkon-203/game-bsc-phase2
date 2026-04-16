<?php
if (!defined('ABSPATH')) {
    exit;
}

// Chuẩn hóa text thô (loại HTML/rỗng/dư khoảng trắng) để lưu/so sánh dữ liệu cửa hàng.
function game_bsc_gotit_content_helper_clean_store_text($value) {
    if (!is_scalar($value)) {
        return '';
    }

    return trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $value)));
}

// Chuẩn bị HTML an toàn cho các field mô tả của voucher Got It.
function game_bsc_gotit_content_helper_prepare_html_content($value) {
    if (!is_scalar($value)) {
        return '';
    }

    $raw = (string) $value;
    if ($raw === '') {
        return '';
    }

    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return wp_kses_post($decoded);
}

// Lưu HTML đã sanitize xuống post meta và gán field key (ACF style) khi có.
function game_bsc_gotit_content_helper_set_voucher_html_field($post_id, $field_name, $field_key, $value) {
    $safe_value = game_bsc_gotit_content_helper_prepare_html_content($value);

    update_post_meta($post_id, $field_name, wp_slash($safe_value));
    if ($field_key !== '') {
        update_post_meta($post_id, '_' . $field_name, $field_key);
    }

    return $safe_value;
}
