<?php
if (!defined('ABSPATH')) exit;

require_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/function-custom.php';
var_dump(callApiGame('http://vietinfoapi.wecan-group.info/api/event/list?start_time=2026-01-01&type%5B%5D=&type%5B%5D=1&type%5B%5D=2&type%5B%5D=3&type%5B%5D=4&state=GA&city=&limit=7', false, 'GET'));

$timezone  = TIMEZONE; // GMT+7
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');

// Lấy dữ liệu logs
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$paged = max(1, intval($_GET['paged'] ?? 1));
$per_page = 10;
$logs_data = game_bsc_get_dashboard_logs_with_search($paged, $per_page, $search_query);

?>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<!-- Highcharts -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>

<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">


<main class="flex flex-col gap-8 py-8">
    <!-- card top -->
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
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'dashboard'])); ?>"
               class="tab-item-nav ">
                Dashboard
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management'])); ?>"
               class="tab-item-nav">
                Quản lý user
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-history'])); ?>"
               class="tab-item-nav ">
                Lịch sử chơi
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-credit-history'])); ?>"
               class="tab-item-nav ">
                Biến động lịch sử lượt chơi
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'system-log'])); ?>"
               class="tab-item-nav active">
                Nhật ký hệ thống
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'voucher-list'])); ?>"
               class="tab-item-nav ">
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
    </style>

    <section class="list-user">
        <div class="container">
            <div class="wrapper p-6 bg-white rounded-xl flex flex-col gap-6">
                <h2 class="text-2xl font-medium text-[#31333F]">Log trong Admin</h2>

                <div class="flex flex-col wrapper-table">
                    <div class="flex justify-between items-center bg-white pl-4">
                        <p class="text-[#4D7CFF] text-sm font-medium cus-bg">
                            <?php echo sprintf(__('Tổng %d log', 'wg-game-bsc'), $logs_data['total']); ?>
                        </p>
                        <div class="list-filter-in-table py-3 px-4 flex gap-4 items-center">
                            <form method="get" action="" style="display: flex; gap: 10px; align-items: flex-end;">
                                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'game-bsc-dashboard'); ?>">
                                <?php if (!empty($_GET['sub'])): ?>
                                    <input type="hidden" name="sub" value="<?php echo esc_attr($_GET['sub']); ?>">
                                <?php endif; ?>

                                <div class="flex items-center gap-[15px] py-[8px] px-3 rounded-lg border border-solid border-[#C9CCD2]">
                                    <div class="flex gap-4 items-center">
                                        <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]">Từ ngày</label>
                                        <input type="date" name="start_date" class="!border-none rounded-md p-2">
                                    </div>
                                    <span>-</span>
                                    <div class="flex gap-4 items-center">
                                        <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]">Đến ngày</label>
                                        <input type="date" name="end_date" class="!border-none rounded-md p-2">
                                    </div>
                                </div>

                                <input type="text" name="search" class="!py-[11px] !px-3 min-w-[300px]"
                                       placeholder="Tìm kiếm theo tên hoặc email"
                                       value="<?php echo esc_attr($search_query); ?>">

                                <button type="submit" class="button button-primary" style="height: 38px;">
                                    Tìm kiếm
                                </button>

<!--                                --><?php //if (!empty($search_query)): ?>
<!--                                    <a href="--><?php //echo esc_url(admin_url('admin.php?page=' . ($_GET['page'] ?? 'game-bsc-dashboard') . (isset($_GET['sub']) ? '&sub=' . $_GET['sub'] : ''))); ?><!--" class="button" style="height: 38px;">-->
<!--                                        Xóa tìm kiếm-->
<!--                                    </a>-->
<!--                                --><?php //endif; ?>
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
                                        <td class="px-6 py-3">
                                            <?php echo esc_html(str_pad($log['id'], 6, '0', STR_PAD_LEFT)); ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div><?php echo esc_html($log['user_name']); ?></div>
                                            <small style="color: #999;"><?php echo esc_html($log['user_email']); ?></small>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div>
                                                    <span class="badge-action" style="<?php echo game_bsc_get_action_badge_style($log['action']); ?>">
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
                                                <summary style="padding: 8px; background: #f5f5f5; border-radius: 3px; user-select: none;">
                                                    📋 Xem chi tiết
                                                </summary>
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
                                        <em>Không có dữ liệu log
                                            <?php if (!empty($search_query)): ?>
                                                cho tìm kiếm "<strong><?php echo esc_html($search_query); ?></strong>"
                                            <?php endif; ?>
                                        </em>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="5" class="px-6 py-4">
                                    <div class="flex justify-between items-center">
                                        <div class="left">
                                            <p class="text-sm font-regular text-[#6A7A95]">
                                                Trang <span class="text-[#344054] font-medium"><?php echo $logs_data['paged']; ?></span>
                                                trên <span class="text-[#344054] font-medium"><?php echo $logs_data['total_pages']; ?></span>
                                            </p>
                                        </div>
                                        <div class="right flex gap-4">
                                            <?php if ($logs_data['paged'] > 1): ?>
                                                <a href="<?php echo esc_url(add_query_arg(['paged' => $logs_data['paged'] - 1, 'search' => $search_query])); ?>"
                                                   class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                    Trang trước
                                                </a>
                                            <?php else: ?>
                                                <button disabled
                                                        class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] opacity-50">
                                                    Trang trước
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($logs_data['paged'] < $logs_data['total_pages']): ?>
                                                <a href="<?php echo esc_url(add_query_arg(['paged' => $logs_data['paged'] + 1, 'search' => $search_query])); ?>"
                                                   class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                    Trang sau
                                                </a>
                                            <?php else: ?>
                                                <button disabled
                                                        class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] opacity-50">
                                                    Trang sau
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.tailwindcss.com"></script>