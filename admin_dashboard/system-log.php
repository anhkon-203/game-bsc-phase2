<?php
if (!defined('ABSPATH')) {
    exit;
}

$timezone = TIMEZONE;
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');

$page_slug = sanitize_text_field((string) ($_GET['page'] ?? 'dashboard-layout'));
$sub_slug = sanitize_text_field((string) ($_GET['sub'] ?? 'system-log'));

$log_tab = sanitize_key((string) ($_GET['log_tab'] ?? 'admin'));
if (!in_array($log_tab, ['admin', 'voucher-excel', 'gotit-webhook'], true)) {
    $log_tab = 'admin';
}

$base_params = [
    'page' => $page_slug,
    'sub' => $sub_slug,
];

$search_query = isset($_GET['search']) ? sanitize_text_field((string) $_GET['search']) : '';
$admin_date_from = isset($_GET['admin_date_from']) ? sanitize_text_field((string) $_GET['admin_date_from']) : '';
$admin_date_to = isset($_GET['admin_date_to']) ? sanitize_text_field((string) $_GET['admin_date_to']) : '';
$paged = max(1, (int) ($_GET['paged'] ?? 1));
$per_page = 10;
$logs_data = game_bsc_get_dashboard_logs_with_search($paged, $per_page, $search_query, $admin_date_from, $admin_date_to);

$voucher_search_query = isset($_GET['voucher_search']) ? sanitize_text_field((string) $_GET['voucher_search']) : '';
$voucher_date_from = isset($_GET['voucher_date_from']) ? sanitize_text_field((string) $_GET['voucher_date_from']) : '';
$voucher_date_to = isset($_GET['voucher_date_to']) ? sanitize_text_field((string) $_GET['voucher_date_to']) : '';
$voucher_mode = sanitize_key((string) ($_GET['voucher_mode'] ?? 'all'));
if (!in_array($voucher_mode, ['all', 'dry-run', 'apply'], true)) {
    $voucher_mode = 'all';
}

$voucher_history_paged = max(1, (int) ($_GET['vh_paged'] ?? 1));
$voucher_related_paged = max(1, (int) ($_GET['vl_paged'] ?? 1));
$voucher_per_page = 10;

$voucher_history = game_bsc_get_voucher_excel_history_data(
    $voucher_history_paged,
    $voucher_per_page,
    $voucher_search_query,
    $voucher_mode,
    $voucher_date_from,
    $voucher_date_to
);
$voucher_related_logs = game_bsc_get_voucher_excel_related_logs(
    $voucher_related_paged,
    $voucher_per_page,
    $voucher_search_query,
    $voucher_date_from,
    $voucher_date_to
);

$webhook_search = '';
$webhook_status = 'all';
$webhook_date_from = '';
$webhook_date_to = '';
$webhook_logs = [];
$webhook_paged = 1;
$webhook_total_items = 0;
$webhook_total_pages = 0;

if ($log_tab === 'gotit-webhook') {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'game_gotit_webhook_logs';

    $webhook_search = isset($_GET['webhook_search']) ? sanitize_text_field((string)$_GET['webhook_search']) : '';
    $webhook_status = isset($_GET['webhook_status']) ? sanitize_key((string)$_GET['webhook_status']) : 'all';
    $webhook_date_from = isset($_GET['webhook_date_from']) ? sanitize_text_field((string)$_GET['webhook_date_from']) : '';
    $webhook_date_to = isset($_GET['webhook_date_to']) ? sanitize_text_field((string)$_GET['webhook_date_to']) : '';
    
    $webhook_paged = max(1, (int)($_GET['wh_paged'] ?? 1));
    $webhook_per_page = 10;
    $offset = ($webhook_paged - 1) * $webhook_per_page;

    $where = ["1=1"];
    $params = [];

    if (!empty($webhook_search)) {
        $where[] = "(request_body LIKE %s OR error_detail LIKE %s OR ip_address LIKE %s)";
        $like_search = '%' . $wpdb->esc_like($webhook_search) . '%';
        $params[] = $like_search;
        $params[] = $like_search;
        $params[] = $like_search;
    }

    if (!empty($webhook_status) && $webhook_status !== 'all') {
        $where[] = "status = %s";
        $params[] = $webhook_status;
    }

    if (!empty($webhook_date_from)) {
        $where[] = "created_at >= %s";
        $params[] = $webhook_date_from;
    }

    if (!empty($webhook_date_to)) {
        $where[] = "created_at <= %s";
        $params[] = $webhook_date_to;
    }

    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*) FROM {$table_logs} WHERE {$where_sql}";
    if (!empty($params)) {
        $webhook_total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
    } else {
        $webhook_total_items = (int) $wpdb->get_var($count_sql);
    }

    $webhook_total_pages = ceil($webhook_total_items / $webhook_per_page);

    $query_sql = "SELECT * FROM {$table_logs} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
    $query_params = array_merge($params, [$webhook_per_page, $offset]);
    $webhook_logs = $wpdb->get_results($wpdb->prepare($query_sql, $query_params), ARRAY_A);
}
?>
<script src="<?php echo esc_url(GAME_BSC_PLUGIN_URL . 'admin_dashboard/assets/js/tailwind.config.js'); ?>"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>

<link rel="stylesheet" href="<?php echo esc_url(GAME_BSC_PLUGIN_URL . 'admin_dashboard/assets/style.css'); ?>">

<main class="flex flex-col gap-8 py-8">
    <div class="card-top">
        <div class="breadcrumb flex flex-col gap-3">
            <nav class="flex gap-1">
                <a href="#" class="text-sm font-regular text-[#6A7A95]">Game BSC</a>
                <span class="text-sm font-regular text-[#6A7A95]">/</span>
                <span class="text-sm font-regular text-[#6A7A95]">Nhật ký hệ thống</span>
            </nav>
            <h2 class="text-lg font-medium text-[#31333F]">Nhật ký hệ thống</h2>
        </div>
        <div class="desc text-sm font-regular text-[#6A7A95] mt-2">
            Cập nhật lần cuối: <?php echo esc_html($current_date); ?> - <?php echo esc_html($current_time); ?>
        </div>
    </div>

    <div class="container mt-6">
        <div class="flex gap-2">
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'dashboard'])); ?>" class="tab-item-nav">
                Dashboard
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management'])); ?>" class="tab-item-nav">
                Quản lý user
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-history'])); ?>" class="tab-item-nav">
                Lịch sử chơi
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-credit-history'])); ?>" class="tab-item-nav">
                Biến động lịch sử lượt chơi
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'system-log'])); ?>" class="tab-item-nav active">
                Nhật ký hệ thống
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'voucher-list'])); ?>" class="tab-item-nav">
                Danh sách quà đã đổi
            </a>
        </div>
    </div>

    <style>
        .list-user .wrapper-table {
            border-radius: 8px;
            border: 1px solid var(--Gray-200, #EAECF0);
            background: var(--Base-White, #FFF);
            box-shadow: 0 1px 3px 0 rgba(16, 24, 40, 0.10), 0 1px 2px 0 rgba(16, 24, 40, 0.06);
        }
        .badge-action {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
            color: white;
        }
        .log-tab {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            color: #344054;
            background: #fff;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }
        .log-tab.active {
            border-color: #2d68ff;
            background: #eef4ff;
            color: #1d4ed8;
        }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Custom filter style to ensure perfectly uniform heights and premium aesthetic */
        .log-filter-form {
            display: flex !important;
            gap: 12px !important;
            align-items: flex-end !important;
            flex-wrap: wrap !important;
            margin-bottom: 4px !important;
        }
        .log-filter-group {
            display: flex !important;
            flex-direction: column !important;
        }
        .log-filter-label {
            display: block !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            color: #667085 !important;
            margin-bottom: 6px !important;
        }
        .log-filter-input {
            display: inline-block !important;
            height: 38px !important;
            line-height: 38px !important;
            padding: 0 12px !important;
            border: 1px solid #D0D5DD !important;
            border-radius: 8px !important;
            background-color: #FFF !important;
            color: #344054 !important;
            font-size: 14px !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            outline: none !important;
            box-shadow: 0 1px 2px 0 rgba(16, 24, 40, 0.05) !important;
            transition: all 0.2s ease !important;
        }
        .log-filter-input:focus {
            border-color: #2D68FF !important;
            box-shadow: 0 0 0 3px rgba(45, 104, 255, 0.1) !important;
        }
        select.log-filter-input {
            height: 38px !important;
            padding: 0 32px 0 12px !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%23667085' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E") !important;
            background-position: right 10px center !important;
            background-repeat: no-repeat !important;
            background-size: 16px !important;
        }
        .log-filter-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 38px !important;
            padding: 0 20px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            border-radius: 8px !important;
            box-sizing: border-box !important;
            line-height: 1 !important;
            margin: 0 !important;
            transition: all 0.2s ease !important;
        }
    </style>

    <section class="list-user">
        <div class="container">
            <div class="wrapper p-6 bg-white rounded-xl flex flex-col gap-6">
                <h2 class="text-2xl font-medium text-[#31333F]">Log trong Admin</h2>

                <div class="flex gap-2">
                    <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab' => 'admin', 'paged' => 1]))); ?>" class="log-tab <?php echo $log_tab === 'admin' ? 'active' : ''; ?>">
                        Log hệ thống chung
                    </a>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab' => 'voucher-excel', 'vh_paged' => 1, 'vl_paged' => 1]))); ?>" class="log-tab <?php echo $log_tab === 'voucher-excel' ? 'active' : ''; ?>">
                        Lịch sử Excel Voucher Gotit
                    </a>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab' => 'gotit-webhook', 'wh_paged' => 1]))); ?>" class="log-tab <?php echo $log_tab === 'gotit-webhook' ? 'active' : ''; ?>">
                        Lịch sử Got It Webhook
                    </a>
                </div>

                <?php if ($log_tab === 'admin'): ?>
                    <div class="flex flex-col wrapper-table">
                        <div class="flex justify-between items-center bg-white pl-4">
                            <p class="text-[#4D7CFF] text-sm font-medium cus-bg">
                                <?php echo sprintf(__('Tổng %d log', 'wg-game-bsc'), (int) $logs_data['total']); ?>
                            </p>
                            <div class="list-filter-in-table py-3 px-4 flex gap-4 items-center">
                                <form method="get" action="" class="log-filter-form">
                                    <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
                                    <input type="hidden" name="sub" value="<?php echo esc_attr($sub_slug); ?>">
                                    <input type="hidden" name="log_tab" value="admin">

                                    <div class="log-filter-group">
                                        <label class="log-filter-label">Từ ngày</label>
                                        <input type="datetime-local" name="admin_date_from" class="log-filter-input" value="<?php echo esc_attr($admin_date_from); ?>">
                                    </div>

                                    <div class="log-filter-group">
                                        <label class="log-filter-label">Đến ngày</label>
                                        <input type="datetime-local" name="admin_date_to" class="log-filter-input" value="<?php echo esc_attr($admin_date_to); ?>">
                                    </div>

                                    <div class="log-filter-group">
                                        <label class="log-filter-label">Từ khóa</label>
                                        <input type="text" name="search" class="log-filter-input min-w-[300px]"
                                               placeholder="Tìm kiếm theo tên hoặc email"
                                               value="<?php echo esc_attr($search_query); ?>">
                                    </div>

                                    <button type="submit" class="button button-primary log-filter-btn">
                                        Tìm kiếm
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="overflow-x-auto table-general">
                            <table class="min-w-full border-collapse divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left">ID</th>
                                    <th class="px-6 py-3 text-left">Người thực hiện</th>
                                    <th class="px-6 py-3 text-left">Hành động</th>
                                    <th class="px-6 py-3 text-left">Chi tiết</th>
                                    <th class="px-6 py-3 text-left">Thời gian</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($logs_data['logs'])): ?>
                                    <?php foreach ($logs_data['logs'] as $log): ?>
                                        <tr class="hover:bg-gray-50 transition td-content">
                                            <td class="px-6 py-3"><?php echo esc_html(str_pad((string) $log['id'], 6, '0', STR_PAD_LEFT)); ?></td>
                                            <td class="px-6 py-3">
                                                <div><?php echo esc_html($log['user_name']); ?></div>
                                                <small style="color: #999;"><?php echo esc_html($log['user_email']); ?></small>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div>
                                                    <span class="badge-action" style="<?php echo esc_attr(game_bsc_get_action_badge_style($log['action'])); ?>">
                                                        <?php echo esc_html($log['action_label']); ?>
                                                    </span>
                                                    <br>
                                                    <small style="color: #666; margin-top: 3px; display: inline-block;">
                                                        <?php echo esc_html($log['setting_label']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3 max-w-[500px]">
                                                <details style="cursor: pointer;">
                                                    <summary style="padding: 8px; background: #f5f5f5; border-radius: 3px; user-select: none;">📋 Xem chi tiết</summary>
                                                    <div style="margin-top: 10px;">
                                                        <?php echo game_bsc_render_log_change_details($log['changed_fields']); ?>
                                                    </div>
                                                </details>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div><?php echo esc_html($log['created_at_formatted']); ?></div>
                                                <small style="color: #999;"><?php echo esc_html($log['ip_address']); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 30px;">
                                            <em>Không có dữ liệu log</em>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                                <tfoot>
                                <tr>
                                    <td colspan="5" class="px-6 py-4">
                                        <div class="flex justify-between items-center">
                                            <p class="text-sm font-regular text-[#6A7A95]">
                                                Trang <span class="text-[#344054] font-medium"><?php echo (int) $logs_data['paged']; ?></span>
                                                trên <span class="text-[#344054] font-medium"><?php echo (int) $logs_data['total_pages']; ?></span>
                                            </p>
                                            <div class="right flex gap-4">
                                                <?php if ((int) $logs_data['paged'] > 1): ?>
                                                    <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab' => 'admin', 'search' => $search_query, 'admin_date_from' => $admin_date_from, 'admin_date_to' => $admin_date_to, 'paged' => (int) $logs_data['paged'] - 1]))); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">Trang trước</a>
                                                <?php else: ?>
                                                    <button disabled class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] opacity-50">Trang trước</button>
                                                <?php endif; ?>

                                                <?php if ((int) $logs_data['paged'] < (int) $logs_data['total_pages']): ?>
                                                    <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab' => 'admin', 'search' => $search_query, 'admin_date_from' => $admin_date_from, 'admin_date_to' => $admin_date_to, 'paged' => (int) $logs_data['paged'] + 1]))); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">Trang sau</a>
                                                <?php else: ?>
                                                    <button disabled class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] opacity-50">Trang sau</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php elseif ($log_tab === 'voucher-excel'): ?>
                    <div class="flex flex-col gap-4">
                        <form method="get" action="" id="voucher-log-form" class="log-filter-form">
                            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
                            <input type="hidden" name="sub" value="<?php echo esc_attr($sub_slug); ?>">
                            <input type="hidden" name="log_tab" value="voucher-excel">

                            <div class="log-filter-group">
                                <label class="log-filter-label">Từ ngày</label>
                                <input type="datetime-local" id="v_from" name="voucher_date_from" class="log-filter-input" value="<?php echo esc_attr($voucher_date_from); ?>">
                            </div>

                            <div class="log-filter-group">
                                <label class="log-filter-label">Đến ngày</label>
                                <input type="datetime-local" id="v_to" name="voucher_date_to" class="log-filter-input" value="<?php echo esc_attr($voucher_date_to); ?>">
                            </div>

                            <div class="log-filter-group">
                                <label class="log-filter-label">Từ khóa</label>
                                <input type="text" name="voucher_search" class="log-filter-input min-w-[300px]" placeholder="Tên file, tên hoặc email người thao tác" value="<?php echo esc_attr($voucher_search_query); ?>">
                            </div>

                            <div class="log-filter-group">
                                <label class="log-filter-label">Chế độ import</label>
                                <select name="voucher_mode" class="log-filter-input">
                                    <option value="all" <?php selected($voucher_mode, 'all'); ?>>Tất cả</option>
                                    <option value="dry-run" <?php selected($voucher_mode, 'dry-run'); ?>>Chạy thử</option>
                                    <option value="apply" <?php selected($voucher_mode, 'apply'); ?>>Áp dụng</option>
                                </select>
                            </div>

                            <button type="submit" class="button button-primary log-filter-btn">Lọc dữ liệu</button>
                        </form>

                        <!-- Lịch sử Upload/Import -->
                        <div id="panel-vtab-upload" class="flex flex-col wrapper-table">
                            <div class="overflow-x-auto table-general">
                                <table class="min-w-full border-collapse divide-y divide-gray-200">
                                    <thead class="bg-gray-50"><tr>
                                        <th class="px-6 py-3 text-left">ID</th>
                                        <th class="px-6 py-3 text-left">File</th>
                                        <th class="px-6 py-3 text-left">Người upload</th>
                                        <th class="px-6 py-3 text-left">Kết quả</th>
                                        <th class="px-6 py-3 text-left">Thời gian</th>
                                    </tr></thead>
                                    <tbody class="divide-y divide-gray-100">
                                    <?php if (!empty($voucher_history['rows'])): ?>
                                        <?php foreach ($voucher_history['rows'] as $item): ?>
                                            <tr class="hover:bg-gray-50 transition td-content">
                                                <td class="px-6 py-3"><?php echo (int) $item['id']; ?></td>
                                                <td class="px-6 py-3">
                                                    <div class="font-medium"><?php echo esc_html($item['file_name']); ?></div>
                                                    <?php if (!empty($item['file_url'])): ?>
                                                        <a href="<?php echo esc_url($item['file_url']); ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline text-xs">Mở file upload</a>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-3">
                                                    <div><?php echo esc_html($item['user_name']); ?></div>
                                                    <small style="color:#999;"><?php echo esc_html($item['user_email']); ?></small>
                                                </td>
                                                <td class="px-6 py-3">
                                                    <span class="badge-action" style="background:<?php echo $item['mode'] === 'apply' ? '#2271b1' : '#6c757d'; ?>;color:#fff;"><?php echo esc_html($item['mode_label']); ?></span>
                                                    <small class="block mt-2">Tổng: <?php echo (int)$item['total_rows']; ?>, Cập nhật: <?php echo (int)$item['updated_rows']; ?>, Bỏ qua: <?php echo (int)$item['skipped_rows']; ?>, Conflict: <?php echo (int)$item['conflict_rows']; ?>, Lỗi: <?php echo (int)$item['error_rows']; ?></small>
                                                </td>
                                                <td class="px-6 py-3"><?php echo esc_html($item['uploaded_at_formatted']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500"><em>Không có dữ liệu lịch sử upload/import.</em></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center">
                                <p class="text-sm text-[#6A7A95]">Trang <span class="font-medium text-[#344054]"><?php echo (int)$voucher_history['paged']; ?></span> trên <span class="font-medium text-[#344054]"><?php echo (int)$voucher_history['total_pages']; ?></span></p>
                                <div class="flex gap-4">
                                    <?php if ((int)$voucher_history['paged'] > 1): ?>
                                        <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab'=>'voucher-excel','voucher_search'=>$voucher_search_query,'voucher_mode'=>$voucher_mode,'voucher_date_from'=>$voucher_date_from,'voucher_date_to'=>$voucher_date_to,'vh_paged'=>(int)$voucher_history['paged']-1,'vl_paged'=>(int)$voucher_related_logs['paged']]))); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] hover:bg-gray-50">Trang trước</a>
                                    <?php else: ?><button disabled class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] opacity-50">Trang trước</button><?php endif; ?>
                                    <?php if ((int)$voucher_history['paged'] < (int)$voucher_history['total_pages']): ?>
                                        <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab'=>'voucher-excel','voucher_search'=>$voucher_search_query,'voucher_mode'=>$voucher_mode,'voucher_date_from'=>$voucher_date_from,'voucher_date_to'=>$voucher_date_to,'vh_paged'=>(int)$voucher_history['paged']+1,'vl_paged'=>(int)$voucher_related_logs['paged']]))); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] hover:bg-gray-50">Trang sau</a>
                                    <?php else: ?><button disabled class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] opacity-50">Trang sau</button><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($log_tab === 'gotit-webhook'): ?>
                    <div class="flex flex-col gap-4">
                        <form method="get" action="" id="webhook-log-form" class="log-filter-form">
                            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
                            <input type="hidden" name="sub" value="<?php echo esc_attr($sub_slug); ?>">
                            <input type="hidden" name="log_tab" value="gotit-webhook">

                            <div class="log-filter-group">
                                <label class="log-filter-label">Từ ngày</label>
                                <input type="datetime-local" id="wh_from" name="webhook_date_from" class="log-filter-input" value="<?php echo esc_attr($webhook_date_from); ?>">
                            </div>

                            <div class="log-filter-group">
                                <label class="log-filter-label">Đến ngày</label>
                                <input type="datetime-local" id="wh_to" name="webhook_date_to" class="log-filter-input" value="<?php echo esc_attr($webhook_date_to); ?>">
                            </div>

                            <div class="log-filter-group">
                                <label class="log-filter-label">Từ khóa</label>
                                <input type="text" name="webhook_search" class="log-filter-input min-w-[300px]" placeholder="Body, IP hoặc nội dung lỗi" value="<?php echo esc_attr($webhook_search); ?>">
                            </div>

                            <div class="log-filter-group">
                                <label class="log-filter-label">Trạng thái</label>
                                <select name="webhook_status" class="log-filter-input">
                                    <option value="all" <?php selected($webhook_status, 'all'); ?>>Tất cả</option>
                                    <option value="success" <?php selected($webhook_status, 'success'); ?>>Thành công</option>
                                    <option value="partial" <?php selected($webhook_status, 'partial'); ?>>Thành công 1 phần</option>
                                    <option value="auth_failed" <?php selected($webhook_status, 'auth_failed'); ?>>Sai chữ ký (401)</option>
                                    <option value="invalid_body" <?php selected($webhook_status, 'invalid_body'); ?>>Sai body (400)</option>
                                    <option value="failed" <?php selected($webhook_status, 'failed'); ?>>Thất bại</option>
                                </select>
                            </div>

                            <button type="submit" class="button button-primary log-filter-btn">Lọc dữ liệu</button>
                        </form>

                        <div class="flex flex-col wrapper-table">
                            <div class="flex justify-between items-center bg-white pl-4 py-3">
                                <p class="text-[#4D7CFF] text-sm font-medium cus-bg">
                                    Tổng số: <?php echo (int) $webhook_total_items; ?> log webhook
                                </p>
                            </div>

                            <div class="overflow-x-auto table-general">
                                <table class="min-w-full border-collapse divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left">ID</th>
                                        <th class="px-6 py-3 text-left">Trạng thái</th>
                                        <th class="px-6 py-3 text-left">Số lượng</th>
                                        <th class="px-6 py-3 text-left">Chi tiết lỗi / Cảnh báo</th>
                                        <th class="px-6 py-3 text-left">Payload Request</th>
                                        <th class="px-6 py-3 text-left">IP / Thời gian</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                    <?php if (!empty($webhook_logs)): ?>
                                        <?php 
                                        if (!function_exists('game_bsc_get_webhook_status_badge_style')) {
                                            function game_bsc_get_webhook_status_badge_style($status) {
                                                switch ($status) {
                                                    case 'success':
                                                        return 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;';
                                                    case 'partial':
                                                        return 'background: #fff3cd; color: #856404; border: 1px solid #ffeeba;';
                                                    case 'auth_failed':
                                                    case 'invalid_body':
                                                    case 'failed':
                                                        return 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                                                    default:
                                                        return 'background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db;';
                                                }
                                            }
                                        }
                                        if (!function_exists('game_bsc_get_webhook_status_label')) {
                                            function game_bsc_get_webhook_status_label($status) {
                                                switch ($status) {
                                                    case 'success':
                                                        return 'Thành công';
                                                    case 'partial':
                                                        return 'Một phần';
                                                    case 'auth_failed':
                                                        return 'Sai chữ ký (401)';
                                                    case 'invalid_body':
                                                        return 'Sai body (400)';
                                                    case 'failed':
                                                        return 'Thất bại';
                                                    default:
                                                        return $status;
                                                }
                                            }
                                        }
                                        ?>
                                        <?php foreach ($webhook_logs as $log): ?>
                                            <tr class="hover:bg-gray-50 transition td-content">
                                                <td class="px-6 py-3">WH-<?php echo esc_html(str_pad((string) $log['id'], 6, '0', STR_PAD_LEFT)); ?></td>
                                                <td class="px-6 py-3">
                                                    <span class="badge-action" style="<?php echo esc_attr(game_bsc_get_webhook_status_badge_style($log['status'])); ?>">
                                                        <?php echo esc_html(game_bsc_get_webhook_status_label($log['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-3">
                                                    <div class="text-sm">Tổng: <span class="font-bold"><?php echo (int) $log['total_vouchers']; ?></span></div>
                                                    <small class="text-gray-500">Khớp: <span class="font-bold"><?php echo (int) $log['processed_count']; ?></span></small>
                                                </td>
                                                <td class="px-6 py-3 max-w-[300px]">
                                                    <?php if (!empty($log['error_detail'])): ?>
                                                        <div class="text-xs text-red-600 font-medium whitespace-normal break-words"><?php echo esc_html($log['error_detail']); ?></div>
                                                    <?php else: ?>
                                                        <span class="text-xs text-gray-400">---</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-3 max-w-[400px]">
                                                    <details style="cursor: pointer;">
                                                        <summary class="text-xs text-blue-600 hover:underline select-none">📋 Xem JSON Payload</summary>
                                                        <div class="mt-2 bg-gray-50 p-3 rounded border overflow-x-auto">
                                                            <pre class="mono"><?php 
                                                                $decoded = json_decode($log['request_body'], true);
                                                                if ($decoded) {
                                                                    echo esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                                                } else {
                                                                    echo esc_html($log['request_body']);
                                                                }
                                                            ?></pre>
                                                        </div>
                                                    </details>
                                                </td>
                                                <td class="px-6 py-3">
                                                    <div class="text-sm"><?php echo esc_html($log['created_at']); ?></div>
                                                    <small class="text-gray-500"><?php echo esc_html($log['ip_address'] ?: 'CLI / Môi trường nội bộ'); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                                <em>Không có dữ liệu log webhook.</em>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center">
                                <p class="text-sm text-[#6A7A95]">
                                    Trang <span class="font-medium text-[#344054]"><?php echo (int) $webhook_paged; ?></span> 
                                    trên <span class="font-medium text-[#344054]"><?php echo (int) $webhook_total_pages; ?></span>
                                </p>
                                <div class="flex gap-4">
                                    <?php if ($webhook_paged > 1): ?>
                                        <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab'=>'gotit-webhook','webhook_search'=>$webhook_search,'webhook_status'=>$webhook_status,'webhook_date_from'=>$webhook_date_from,'webhook_date_to'=>$webhook_date_to,'wh_paged'=>$webhook_paged - 1]))); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] hover:bg-gray-50">Trang trước</a>
                                    <?php else: ?>
                                        <button disabled class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] opacity-50">Trang trước</button>
                                    <?php endif; ?>

                                    <?php if ($webhook_paged < $webhook_total_pages): ?>
                                        <a href="<?php echo esc_url(add_query_arg(array_merge($base_params, ['log_tab'=>'gotit-webhook','webhook_search'=>$webhook_search,'webhook_status'=>$webhook_status,'webhook_date_from'=>$webhook_date_from,'webhook_date_to'=>$webhook_date_to,'wh_paged'=>$webhook_paged + 1]))); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] hover:bg-gray-50">Trang sau</a>
                                    <?php else: ?>
                                        <button disabled class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-[#344054] opacity-50">Trang sau</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.tailwindcss.com"></script>

<script>
/* ── Date range validation: Từ ngày phải <= Đến ngày ── */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var pairs = [
                ['admin_date_from',   'admin_date_to'],
                ['voucher_date_from', 'voucher_date_to'],
                ['webhook_date_from', 'webhook_date_to'],
                ['date_from',         'date_to'],
            ];
            for (var i = 0; i < pairs.length; i++) {
                var fromEl = form.querySelector('[name="' + pairs[i][0] + '"]');
                var toEl   = form.querySelector('[name="' + pairs[i][1] + '"]');
                if (fromEl && toEl && fromEl.value && toEl.value && fromEl.value > toEl.value) {
                    alert('"Từ ngày" phải nhỏ hơn hoặc bằng "Đến ngày".');
                    toEl.focus();
                    e.preventDefault();
                    return;
                }
            }
        });
    });
});
</script>
