

<?php
$timezone  = TIMEZONE;
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');





?>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>

<!-- Highcharts -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>

<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">



<?php

$page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : null;
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : null;
$provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : null;
$status_play = isset($_GET['status_play']) ? sanitize_text_field($_GET['status_play']) : null;
$status_active = isset($_GET['status_active']) ? sanitize_text_field($_GET['status_active']) : null;
$date_access = isset($_GET['date_access']) ? sanitize_text_field($_GET['date_access']) : null;
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : null;
// Gọi hàm lấy dữ liệu
$result = game_bsc_get_users_access_data_paginated($page, 20, [
        'date_from' => $date_from,
        'date_to' => $date_to,
        'provider' => $provider,
        'status_play' => $status_play,
        'status_active' => $status_active,
        'date_access' => $date_access,
        'search' => $search,
]);

// Xử lý dữ liệu
$users = [];
$total_count = 0;
$total_pages = 0;


if ($result['status'] === 'success') {
    $users = $result['data'];
    $total_count = $result['total_count'];
    $total_pages = $result['total_pages'];
}

// Hàm format trạng thái tham gia
function get_status_play_text($status) {
    $statuses = [
            'truy-cap' => 'Truy cập',
            'tham-gia' => 'Tham gia',
            'hoan-thanh' => 'Hoàn thành'
    ];
    return $statuses[$status] ?? $status;
}

// Hàm format class cho trạng thái tham gia

function get_status_play_class($status) {
    $classes = [
            'truy-cap' => 'truycap-text',
            'tham-gia' => 'thamgia-text',
            'hoan-thanh' => 'hoanthanh-text'
    ];
    return $classes[$status] ?? '';
}

// Hàm format provider
//// Lấy tham số từ GET
////define('MTRADER_APP', 'mtrader_app');
////define('BSC_SMART_INVEST', 'bsc_smart_invest');
////define('WEBTRADING', 'webtrading');
////define('BSC_WEB', 'bsc_web');
function get_provider_text($provider) {
    $providers = [
            MTRADER_APP => 'Mtrader',
            BSC_SMART_INVEST => 'BSC Smart Invest',
            WEBTRADING => 'BSC Webtrading',
            BSC_WEB => 'BSC Website'
    ];
    return $providers[$provider] ?? $provider;
}

// Hàm format provider class
function get_provider_class($provider) {
    $classes = [
            MTRADER_APP => 'mtrader-text',
            WEBTRADING => 'website-text',
            BSC_SMART_INVEST => 'mobile-text',
            BSC_WEB => 'website-text'
    ];
    return $classes[$provider] ?? '';
}

// Hàm format ngày
function format_date($date_str) {
    if (empty($date_str)) return '-';
    return date('d/m/Y', strtotime($date_str));
}

// Hàm format ngày giờ
function format_datetime($datetime_str) {
    if (empty($datetime_str)) return '-';
    return date('d/m/Y H:i:s', strtotime($datetime_str));
}
?>

<main class="flex flex-col gap-8 py-8">
    <!-- card top -->
    <div class="card-top">
        <div class="breadcrumb flex flex-col gap-3">
            <nav class="flex gap-1">
                <a href="#" class="text-sm font-regular text-[#6A7A95]">Game BSC</a>
                <span class="text-sm font-regular text-[#6A7A95]">/</span>
                <span class="text-sm font-regular text-[#6A7A95]">Quản lý user</span>
            </nav>
            <h2 class="text-lg font-medium text-[#31333F]">Quản lý user</h2>
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
               class="tab-item-nav active">
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
               class="tab-item-nav ">
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

        .truycap-text { color: #FF9500; }
        .thamgia-text { color: #4CAF50; }
        .hoanthanh-text { color: #2196F3; }
        .mtrader-text { color: #FF6B6B; }
        .website-text { color: #4ECDC4; }
        .mobile-text { color: #95E1D3; }

        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            background-color: currentColor;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .status-toggle {
            cursor: pointer;
            opacity: 1;
            transition: opacity 0.3s;
        }

        .status-toggle.loading {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-message {
            display: none;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
        }

        .status-message.show {
            display: block;
        }

        .status-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>

    <section class="list-user ">
        <div class="container">
            <div class="wrapper p-6 bg-white rounded-xl flex flex-col gap-6">
                <!-- Status Message -->
                <div id="statusMessage" class="status-message"></div>

                <div class="flex justify-between items-center ">
                    <h2 class="text-2xl font-medium text-[#31333F]">Danh sách người chơi truy cập</h2>
                    <form method="GET" class="flex items-center gap-[15px] py-[6px] px-3 rounded-lg border border-solid border-[#C9CCD2]">
                        <input type="hidden" name="page" value="dashboard-layout">
                        <input type="hidden" name="sub" value="user-management">
                        <input type="hidden" name="search" value="<?php echo esc_attr($search); ?>">

                        <select name="date_access" class="cus-ic"  >
                            <option value="">Ngày truy cập</option>
                            <option value="first_login" <?php selected($date_access, 'first_login'); ?>>Ngày truy cập lần đầu</option>
                            <option value="last_login" <?php selected($date_access, 'last_login'); ?>>Lượt truy cập cuối</option>
                        </select>

                        <div class="flex gap-4 items-center ">
                            <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_from">Từ ngày</label>
                            <input type="date" name="date_from" id="date_from" class="!border-none rounded-md p-2" value="<?php echo esc_attr($date_from); ?>">
                        </div>
                        <span>-</span>
                        <div class="flex gap-4 items-center ">
                            <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_to">Đến ngày</label>
                            <input type="date" name="date_to" id="date_to" class="!border-none rounded-md p-2" value="<?php echo esc_attr($date_to); ?>">
                        </div>
                        <button type="submit" class="px-3 py-2 bg-blue-500 text-white rounded-md text-sm font-medium hover:bg-blue-600">Lọc</button>
                    </form>
                </div>

                <div class="flex flex-col wrapper-table ">
                    <div class="flex justify-between items-center bg-white pl-4">
                        <p class="text-[#4D7CFF] text-sm font-medium cus-bg"><?php echo number_format($total_count); ?> người đã truy cập</p>
                        <div class="list-filter-in-table py-3 px-4 flex gap-4 items-center">
                            <!-- Lọc theo nền tảng -->
                            <form method="GET" style="display: flex; gap: 10px;">
                                <input type="hidden" name="page" value="dashboard-layout">
                                <input type="hidden" name="sub" value="user-management">
                                <input type="hidden" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                                <input type="hidden" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                                <input type="hidden" name="search" value="<?php echo esc_attr($search); ?>">
                                <input type="hidden" name="paged" value="1">

                                <select name="provider" onchange="this.form.submit()">
                                    <option value="">Nền tảng</option>
                                    <option value="<?= MTRADER_APP ?>" <?php selected($provider, MTRADER_APP); ?>>Mtrader</option>
                                    <option value="<?= WEBTRADING ?>" <?php selected($provider, WEBTRADING); ?>>BSC Webtrading</option>
                                    <option value="<?= BSC_SMART_INVEST ?>" <?php selected($provider, BSC_SMART_INVEST); ?>>BSC Smart Invest</option>
                                    <option value="<?= BSC_WEB ?>" <?php selected($provider, BSC_WEB); ?>>BSC Website</option>
                                </select>

                                <!-- Lọc theo trạng thái tham gia -->
                                <select name="status_play" onchange="this.form.submit()">
                                    <option value="">Trạng thái tham gia</option>
                                    <option value="truy-cap" <?php selected($status_play, 'truy-cap'); ?>>Truy cập</option>
                                    <option value="tham-gia" <?php selected($status_play, 'tham-gia'); ?>>Tham gia</option>
                                    <option value="hoan-thanh" <?php selected($status_play, 'hoan-thanh'); ?>>Hoàn thành</option>
                                </select>

                                <!-- Lọc theo trạng thái hoạt động -->
                                <select name="status_active" onchange="this.form.submit()">
                                    <option value="">Trạng thái hoạt động</option>
                                    <option value="1" <?php selected($status_active, '1'); ?>>Active</option>
                                    <option value="0" <?php selected($status_active, '0'); ?>>Inactive</option>
                                </select>

                                <input type="text" name="search" placeholder="Tìm kiếm" value="<?php echo esc_attr($search); ?>">
                                <button type="submit" class="px-3 py-2 bg-blue-500 text-white rounded-md">Tìm</button>
                            </form>
                        </div>
                    </div>

                    <div class="overflow-x-auto table-general">
                        <table class="min-w-full border-collapse divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">STK</th>
                                <th class="px-6 py-3 text-left">ID</th>
                                <th class="px-6 py-3 text-left">Họ và tên</th>
                                <th class="px-6 py-3 text-left">Nền tảng</th>
                                <th class="px-6 py-3 text-left">Ngày truy cập lần đầu</th>
                                <th class="px-6 py-3 text-left">Lượt truy cập cuối</th>
                                <th class="px-6 py-3 text-left">Trạng thái tham gia</th>
                                <th class="px-6 py-3 text-left">Trạng thái hoạt động</th>
                                <th class="px-6 py-3 text-left"></th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50 transition td-content">
                                        <td class="px-6 py-3"><?php echo esc_html(!empty($user['external_user_id']) ? $user['external_user_id'] : '-'); ?></td>
                                        <td class="px-6 py-3"><?php echo esc_html($user['id']); ?></td>
                                        <td class="px-6 py-3">
                                            <a class="w-5 h-5" href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => $user['id']])); ?>">
                                                <?php echo esc_html(!empty($user['name']) ? $user['name'] : '-'); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-3 <?php echo esc_attr(get_provider_class($user['provider'])); ?>">
                                            <?php echo esc_html(get_provider_text($user['provider'])); ?>
                                        </td>
                                        <td class="px-6 py-3"><?php echo format_datetime($user['first_access_date']); ?></td>
                                        <td class="px-6 py-3"><?php echo format_datetime($user['last_login_at']); ?></td>
                                        <td class="px-6 py-3 flex items-center w-full">
                                            <p class="<?php echo esc_attr(get_status_play_class($user['play_status'])); ?>">
                                                <span class="dot"></span>
                                                <?php echo esc_html(get_status_play_text($user['play_status'])); ?>
                                            </p>
                                        </td>
                                        <td class="px-6 py-3">
                                            <label class="switch status-toggle" data-user-id="<?php echo esc_attr($user['id']); ?>">
                                                <input
                                                        type="checkbox"
                                                        class="user-status-checkbox"
                                                        <?php checked($user['status_active'], 1); ?>
                                                        data-user-id="<?php echo esc_attr($user['id']); ?>">
                                                <span class="slider"></span>
                                            </label>
                                        </td>
                                        <td class="px-6 py-3">
                                            <a class="w-5 h-5" href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => $user['id']])); ?>"
                                               title="Xem chi tiết">
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
                                    <td colspan="9" class="px-6 py-4 text-center text-gray-500">Không có dữ liệu</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="9" class="px-6 py-4">
                                    <div class="flex justify-between items-center">
                                        <div class="left">
                                            <p class="text-sm font-regular text-[#6A7A95]">
                                                Trang <span class="text-[#344054] font-medium"><?php echo $page; ?></span> trên <span class="text-[#344054] font-medium"><?php echo max(1, $total_pages); ?></span>
                                            </p>
                                        </div>
                                        <div class="right flex gap-4">
                                            <?php if ($page > 1): ?>
                                                <a href="<?php echo esc_url(add_query_arg(['paged' => $page - 1])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">Trang trước</a>
                                            <?php else: ?>
                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50" disabled>Trang trước</button>
                                            <?php endif; ?>

                                            <?php if ($page < $total_pages): ?>
                                                <a href="<?php echo esc_url(add_query_arg(['paged' => $page + 1])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">Trang sau</a>
                                            <?php else: ?>
                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50" disabled>Trang sau</button>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.user-status-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const userId = parseInt(this.getAttribute('data-user-id'));
                const newStatus = this.checked ? 1 : 0;
                const label = this.closest('.switch');

                label.classList.add('loading');

                const formData = new FormData();
                formData.append('action', 'game_bsc_toggle_user_status');
                formData.append('user_id', userId);
                formData.append('status', newStatus);
                formData.append('nonce', '<?php echo wp_create_nonce('game_bsc_toggle_user_status'); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showStatusMessage('Cập nhật trạng thái thành công!', 'success');
                        } else {
                            // Revert checkbox
                            checkbox.checked = !checkbox.checked;
                            showStatusMessage('Lỗi: ' + (data.data || 'Không thể cập nhật trạng thái'), 'error');
                        }
                        label.classList.remove('loading');
                    })
                    .catch(error => {
                        // Revert checkbox
                        checkbox.checked = !checkbox.checked;
                        showStatusMessage('Lỗi: ' + error.message, 'error');
                        label.classList.remove('loading');
                    });
            });
        });

        function showStatusMessage(message, type) {
            const messageEl = document.getElementById('statusMessage');
            messageEl.textContent = message;
            messageEl.className = 'status-message show ' + type;

            // Auto hide after 5 seconds
            setTimeout(function() {
                messageEl.classList.remove('show');
            }, 5000);
        }
    });
</script>


    <script src="https://cdn.tailwindcss.com"></script>
