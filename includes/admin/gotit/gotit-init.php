<?php
if (!defined('ABSPATH')) {
    exit;
}

// Điểm vào Got It: giữ thứ tự load ổn định để tránh lỗi function/class chưa khai báo.
// 1) Client trước để các luồng sync/redeem có thể gọi ngay.
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/gotit-client.php';
// 2) Normalizer tách riêng để giảm độ dài gotit-ajax.
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/normalizers/gotit-product-normalizer.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/helpers/gotit-content-helper.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/normalizers/gotit-store-normalizer.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/parsers/gotit-issue-parser.php';
// 3) AJAX/sync layer dùng lại client + normalizer đã nạp phía trên.
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/gotit-ajax.php';

// 4) Webhook receiver – nhận thông báo trạng thái voucher từ Got It.
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/gotit-webhook.php';
