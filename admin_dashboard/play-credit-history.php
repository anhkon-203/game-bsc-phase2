<?php
$timezone  = TIMEZONE; // GMT+7
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');

$paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

$result = game_bsc_get_play_credit_ledger_data($paged, 20, $date_from, $date_to, $search, $status);

$ledger_items = [];
$total_items = 0;
$total_pages = 1;
$total_played_turns = 0;
$total_remaining_turns = 0;

if (($result['status'] ?? '') === 'success') {
    $ledger_items = $result['data'] ?? [];
    $total_items = (int)($result['pagination']['total_items'] ?? 0);
    $total_pages = max(1, (int)($result['pagination']['total_pages'] ?? 1));
    $total_played_turns = (int)($result['summary']['total_played_turns'] ?? 0);
    $total_remaining_turns = (int)($result['summary']['total_remaining_turns'] ?? 0);
}
?>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>

<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">

<main class="flex flex-col gap-8 pb-8">
    <div class="card-top">
        <div class="breadcrumb flex flex-col gap-3">
            <nav class="flex gap-1">
                <a href="#" class="text-sm font-regular text-[#6A7A95]">Game BSC</a>
                <span class="text-sm font-regular text-[#6A7A95]">/</span>
                <span class="text-sm font-regular text-[#6A7A95]">Biến động lịch sử lượt chơi</span>
            </nav>
            <h2 class="text-lg font-medium text-[#31333F]">Biến động lịch sử lượt chơi</h2>
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
               class="tab-item-nav">
                Lịch sử chơi
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-credit-history'])); ?>"
               class="tab-item-nav active">
                Biến động lịch sử lượt chơi
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'system-log'])); ?>"
               class="tab-item-nav">
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

        .ledger-filters-bar {
            min-height: 68px;
            padding-left: 16px;
            border-bottom: 1px solid #EAECF0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ledger-badges {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ledger-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 16px;
            background: linear-gradient(270deg, #EBF4FA 0%, #F3FBFE 100%);
            color: #4D7CFF;
            font-size: 14px;
            font-weight: 500;
            line-height: 22px;
            white-space: nowrap;
        }

        .ledger-filter-actions {
            padding: 12px 16px;
        }

        .ledger-filter-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            row-gap: 8px;
        }

        .ledger-status-wrap {
            min-width: 170px;
            height: 42px;
            border: 1px solid #D0D5DD;
            border-radius: 8px;
            padding: 10px 16px;
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-sizing: border-box;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            background: #FFFFFF;
        }

        .ledger-status-wrap label {
            color: #6A7A95;
            font-size: 14px;
            font-weight: 400;
            line-height: 22px;
            white-space: nowrap;
        }

        .ledger-status-wrap select {
            border: none !important;
            outline: none;
            box-shadow: none !important;
            padding: 0;
            min-height: auto;
            background-position: right center;
            color: #344054;
            font-size: 14px;
            width: 88px;
            min-width: 88px;
            text-align: right;
        }

        .ledger-date-range {
            min-width: 420px;
            height: 44px;
            border: 1px solid #D0D5DD;
            border-radius: 8px;
            display: flex;
            align-items: center;
            padding: 0 12px;
            box-sizing: border-box;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            background: #FFFFFF;
            gap: 8px;
        }

        .ledger-date-range label {
            color: #1D1D1D;
            opacity: 0.5;
            font-size: 14px;
            font-weight: 400;
            line-height: 22px;
        }

        .ledger-date-range input {
            border: none !important;
            outline: none;
            box-shadow: none !important;
            font-size: 14px;
            padding: 0;
            min-height: auto;
            width: 122px;
            min-width: 122px;
            background: transparent;
        }

        .ledger-search-input {
            width: 320px;
            max-width: 100%;
            height: 44px;
            border: 1px solid #D0D5DD;
            border-radius: 8px;
            padding: 10px 16px;
            box-sizing: border-box;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            color: #667085;
            font-size: 14px;
            line-height: 22px;
        }

        @media (max-width: 1366px) {
            .ledger-date-range {
                min-width: 360px;
            }

            .ledger-search-input {
                width: 280px;
            }
        }

        @media (max-width: 1024px) {
            .ledger-filters-bar {
                align-items: flex-start;
            }

            .ledger-filter-actions,
            .ledger-filter-form,
            .ledger-date-range,
            .ledger-search-input {
                width: 100%;
            }

            .ledger-date-range {
                min-width: 0;
                justify-content: space-between;
            }
        }

        .ledger-table thead th {
            background: #F9FAFB;
            color: #667085;
            font-size: 12px;
            font-weight: 500;
            line-height: 18px;
            padding: 12px 24px;
        }

        .ledger-table tbody td {
            padding: 16px 24px;
        }

        .ledger-reason {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 2px 8px;
            border-radius: 16px;
            background: #F4F5F6;
            color: #31333F;
            font-size: 12px;
            line-height: 18px;
        }

        .ledger-delta-plus {
            color: #30D158;
            font-weight: 600;
        }

        .ledger-delta-minus {
            color: #FF0017;
            font-weight: 600;
        }
    </style>

    <section class="list-user">
        <div class="container">
            <div class="wrapper p-6 bg-white rounded-xl flex flex-col gap-6">
                <h2 class="text-2xl font-medium text-[#31333F]">Biến động lịch sử lượt chơi</h2>

                <div class="flex flex-col wrapper-table">
                    <div class="ledger-filters-bar">
                        <div class="ledger-badges">
                            <span class="ledger-badge">Tổng số lượt đã chơi: <?php echo number_format($total_played_turns); ?> lượt</span>
                            <span class="ledger-badge">Số lượt còn lại: <?php echo number_format($total_remaining_turns); ?> lượt</span>
                        </div>

                        <div class="ledger-filter-actions">
                            <form method="GET" class="ledger-filter-form" id="ledger-filter-form">
                                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'dashboard-layout'); ?>">
                                <input type="hidden" name="sub" value="<?php echo esc_attr($_GET['sub'] ?? 'play-credit-history'); ?>">
                                <input type="hidden" name="paged" value="1">

                                <div class="ledger-status-wrap">
                                    <label for="status">Trạng thái</label>
                                    <select id="status" name="status">
                                        <option value="all" <?php selected($status, 'all'); ?>>Tất cả</option>
                                        <option value="plus" <?php selected($status, 'plus'); ?>>Cộng lượt</option>
                                        <option value="minus" <?php selected($status, 'minus'); ?>>Trừ lượt</option>
                                    </select>
                                </div>

                                <div class="ledger-date-range">
                                    <label for="date_from">Từ ngày</label>
                                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                                    <span>-</span>
                                    <label for="date_to">Đến ngày</label>
                                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                                </div>

                                <input type="text" id="search" name="search" class="ledger-search-input" placeholder="Tìm kiếm theo STK" value="<?php echo esc_attr($search); ?>">
                            </form>
                        </div>
                    </div>

                    <div class="overflow-x-auto table-general">
                        <table class="min-w-full border-collapse divide-y divide-gray-200 ledger-table">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left">STK</th>
                                <th class="text-left">Họ và tên</th>
                                <th class="text-left">Thời gian</th>
                                <th class="text-left">Lý do</th>
                                <th class="text-left">Số lượt</th>
                                <th class="text-left"></th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            <?php if (!empty($ledger_items)): ?>
                                <?php foreach ($ledger_items as $item): ?>
                                    <tr class="hover:bg-gray-50 transition td-content">
                                        <td class="px-6 py-3"><?php echo esc_html($item['external_user_id'] ?: '-'); ?></td>
                                        <td class="px-6 py-3"><?php echo esc_html($item['user_name'] ?: '-'); ?></td>
                                        <td class="px-6 py-3"><?php echo esc_html($item['created_at_display']); ?></td>
                                        <td class="px-6 py-3">
                                            <span class="ledger-reason"><?php echo esc_html($item['detail']); ?></span>
                                        </td>
                                        <td class="px-6 py-3 <?php echo ((int)$item['delta'] >= 0) ? 'ledger-delta-plus' : 'ledger-delta-minus'; ?>">
                                            <?php echo esc_html($item['delta_display']); ?>
                                        </td>
                                        <td class="px-6 py-3">
                                            <a class="w-5 h-5 inline-flex items-center justify-center" href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => (int)$item['user_id']])); ?>" title="Xem chi tiết người chơi">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                    <path d="M0.833252 10C0.833252 10 4.16658 3.33334 9.99992 3.33334C15.8333 3.33334 19.1666 10 19.1666 10C19.1666 10 15.8333 16.6667 9.99992 16.6667C4.16658 16.6667 0.833252 10 0.833252 10Z" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="M9.99992 12.5C11.3806 12.5 12.4999 11.3807 12.4999 10C12.4999 8.6193 11.3806 7.50001 9.99992 7.50001C8.61921 7.50001 7.49992 8.6193 7.49992 10C7.49992 11.3807 8.61921 12.5 9.99992 12.5Z" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">Không có dữ liệu biến động</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>

                            <tfoot>
                            <tr>
                                <td colspan="6" class="px-6 py-4">
                                    <div class="flex justify-between items-center">
                                        <div class="left">
                                            <p class="text-sm font-regular text-[#6A7A95]">
                                                Trang <span class="text-[#344054] font-medium"><?php echo (int)$paged; ?></span> trên <span class="text-[#344054] font-medium"><?php echo (int)$total_pages; ?></span>
                                            </p>
                                        </div>
                                        <div class="right flex gap-4">
                                            <?php if ($paged > 1): ?>
                                                <a href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1, 'date_from' => $date_from, 'date_to' => $date_to, 'search' => $search, 'status' => $status])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">Trang trước</a>
                                            <?php else: ?>
                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054]" disabled>Trang trước</button>
                                            <?php endif; ?>

                                            <?php if ($paged < $total_pages): ?>
                                                <a href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1, 'date_from' => $date_from, 'date_to' => $date_to, 'search' => $search, 'status' => $status])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">Trang sau</a>
                                            <?php else: ?>
                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054]" disabled>Trang sau</button>
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('ledger-filter-form');
        if (!form) return;

        const controls = ['status', 'date_from', 'date_to'];
        controls.forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function () {
                    form.submit();
                });
            }
        });
    });
</script>
