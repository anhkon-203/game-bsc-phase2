<?php
// Cập nhật lần cuối: 2025-11-06 08:39:50
$timezone  = TIMEZONE; // GMT+7
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Lấy dữ liệu user
$user_details = null;
$gifts_data = null;
$play_history = null;
$play_credit_history = null;
$error_message = '';

// ===== QUẢN LÝ SEARCH & PAGINATION =====
$gift_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$gift_page = isset($_GET['gift_page']) ? max(1, intval($_GET['gift_page'])) : 1;

$play_date_from = isset($_GET['play_date_from']) ? sanitize_text_field($_GET['play_date_from']) : '';
$play_date_to = isset($_GET['play_date_to']) ? sanitize_text_field($_GET['play_date_to']) : '';
$play_page = isset($_GET['play_page']) ? max(1, intval($_GET['play_page'])) : 1;
$credit_date_from = isset($_GET['credit_date_from']) ? sanitize_text_field($_GET['credit_date_from']) : '';
$credit_date_to = isset($_GET['credit_date_to']) ? sanitize_text_field($_GET['credit_date_to']) : '';
$credit_page = isset($_GET['credit_page']) ? max(1, intval($_GET['credit_page'])) : 1;
$credit_status = isset($_GET['credit_status']) ? sanitize_text_field($_GET['credit_status']) : 'all';
$gift_received_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$gift_received_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

if ($user_id > 0) {
    $user_details = game_bsc_get_user_details($user_id);

    $achievements = game_get_user_badges_data($user_id);
    //[0]=>
    //  array(5) {
    //    ["badge_id"]=>
    //    int(868)
    //    ["name"]=>
    //    string(9) "Ham học"
    //    ["description"]=>
    //    string(53) "Đăng nhập và tham gia chơi 5 ngày liên tiếp"
    //    ["icon_url"]=>
    //    string(57) "http://game-bsc.test/wp-content/uploads/2025/11/Badge.png"
    //    ["earned"]=>
    //    bool(true)
    //  }
    //  [1]=>
    //  array(5) {
    //    ["badge_id"]=>
    //    int(873)
    //    ["name"]=>
    //    string(11) "Chuyên gia"
    //    ["description"]=>
    //    string(41) "Đăng nhập và tham gia chơi 30 ngày"
    //    ["icon_url"]=>
    //    string(68) "http://game-bsc.test/wp-content/uploads/2025/11/Frame-1000010115.png"
    //    ["earned"]=>
    //    bool(false)
    //  }
    //  [2]=>
    //  array(5) {
    //    ["badge_id"]=>
    //    int(875)
    //    ["name"]=>
    //    string(12) "Bậc thầy"
    //    ["description"]=>
    //    string(41) "Đăng nhập và tham gia chơi 60 ngày"
    //    ["icon_url"]=>
    //    string(59) "http://game-bsc.test/wp-content/uploads/2025/11/Badge-1.png"
    //    ["earned"]=>
    //    bool(false)
    //  }
    //}

    if (is_wp_error($user_details)) {
        $error_message = $user_details->get_error_message();
    } else {
        // Lấy quà tặng (có search và phân trang)
        $gifts_data = game_bsc_get_user_gifts($user_id, 10, $gift_page, $gift_search, $gift_received_date_from, $gift_received_date_to);

        // Lấy lịch sử chơi (có search và phân trang)
        $play_history = game_bsc_get_user_play_history($user_id, 10, $play_page, $play_date_from, $play_date_to);

        // Lấy biến động lượt chơi (+/-) theo user
        $play_credit_history = game_bsc_get_user_play_credit_ledger($user_id, 10, $credit_page, $credit_date_from, $credit_date_to, $credit_status);
    }
}

?>

<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
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
                <span class="text-sm font-regular text-[#6A7A95]">Dashboard</span>
            </nav>
            <h2 class="text-lg font-medium text-[#31333F]">Dashboard</h2>
        </div>
        <div class="desc text-sm font-regular text-[#6A7A95] mt-2">
            Cập nhật lần cuối: <?php echo esc_html($current_date); ?> - <?php echo esc_html($current_time); ?>
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

        .ledger-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 24px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            line-height: 18px;
        }

        .ledger-status-badge.plus {
            color: #30D158;
            background: rgba(48, 209, 88, 0.12);
        }

        .ledger-status-badge.minus {
            color: #FF0017;
            background: rgba(255, 0, 23, 0.12);
        }

        .ledger-delta-plus {
            color: #30D158;
            font-weight: 600;
        }

        .ledger-delta-minus {
            color: #FF0017;
            font-weight: 600;
        }

        @media (max-width: 1366px) {
            .ledger-date-range {
                min-width: 360px;
            }
        }

        @media (max-width: 1024px) {
            .ledger-filters-bar {
                align-items: flex-start;
            }

            .ledger-filter-actions,
            .ledger-filter-form,
            .ledger-date-range {
                width: 100%;
            }

            .ledger-date-range {
                min-width: 0;
                justify-content: space-between;
            }
        }
    </style>

    <section class="list-user">
        <div class="container">
            <div class="wrapper p-6 bg-white rounded-xl flex flex-col gap-6">
                <div class="flex items-center gap-2">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dashboard-layout&sub=user-management')); ?>" class="cursor-pointer hover:opacity-70">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M15 18L9 12L15 6" stroke="#101828" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <h2 class="text-2xl font-medium text-[#31333F]">Chi tiết người chơi</h2>
                </div>

                <div class="line w-full h-[1px] bg-[#EAECF0]"></div>

                <?php if (!empty($error_message)): ?>
                    <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
                <?php elseif ($user_details && !is_wp_error($user_details)): ?>
                    <div class="flex justify-between">
                        <div class="flex flex-col gap-8">
                            <!-- PHẦN THÔNG TIN CÁ NHÂN -->
                            <div class="flex flex-col gap-8 min-w-[406px]">
                                <div class="flex flex-col gap-6">
                                    <h3 class="text-lg font-medium text-[#31333F]">Thông tin cá nhân</h3>
                                </div>
                                <div class="box-info border border-solid border-[#EAECF0] rounded-xl p-6 flex flex-col gap-3">
                                    <!-- Họ và tên -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-fit]">Họ và tên:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo esc_html($user_details['name']); ?>
                                    </span>
                                    </div>

                                    <!-- STK -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-fit]">STK:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo esc_html($user_details['external_user_id'] ?? '-'); ?>
                                    </span>
                                    </div>

                                    <!-- Tổng điểm -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-fit]">Tổng điểm:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo number_format($user_details['total_points']); ?>
                                    </span>
                                    </div>

                                    <!-- Số mảnh ghép -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-fit]">Số mảnh ghép:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo (int)$user_details['total_pieces']; ?>
                                    </span>
                                    </div>

                                    <!-- Chặng -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-fit]">Chặng:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo (int)$user_details['current_day_index']; ?>
                                    </span>
                                    </div>

                                    <!-- Thành tích tuần -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-[120px] w-fit">Thành tích tuần:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo (int)$user_details['week_achievements']; ?> câu
                                    </span>
                                    </div>

                                    <!-- Thành tích tháng -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-[120px] w-fit">Thành tích tháng:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo (int)$user_details['month_achievements']; ?> câu
                                    </span>
                                    </div>

                                    <!-- Truy cập lần đầu -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-[120px] w-fit">Truy cập lần đầu:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo esc_html($user_details['first_login']); ?>
                                    </span>
                                    </div>

                                    <!-- Truy cập lần cuối -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-[120px] w-fit">Truy cập lần cuối:</span>
                                        <span class="text-sm font-regular text-[#344054]">
                                        <?php echo esc_html($user_details['last_login']); ?>
                                    </span>
                                    </div>

                                    <!-- Trạng thái tài khoản -->
                                    <div class="info-item flex gap-4">
                                        <span class="text-sm font-medium text-[#6A7A95] w-[120px] w-fit">Trạng thái tài khoản:</span>
                                        <label class="switch status-toggle" data-user-id="<?php echo (int)$user_id; ?>">
                                            <input type="checkbox" class="user-status-checkbox"
                                            <?php echo ($user_details['account_status'] === 'active') ? 'checked="checked"' : ''; ?>"
                                            data-user-id="<?php echo (int)$user_id; ?>">
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                                <!--          thành tựu                  -->
                            <div class="flex flex-col gap-6 w-full max-w-[406px]">
                                <h4 class="text-[#31333F] font-medium text-[18px]">Thành tựu</h4>
                                <div class="box-achievement border border-solid border-[#EAECF0] rounded-xl p-6 flex item-center flex-wrap gap-3">
                                    <?php foreach ($achievements as $achievement): ?>
                                        <div class="flex flex-col items-center gap-2">
                                            <img class="w-12 h-12" src="<?php echo esc_url($achievement['icon_url']); ?>" alt="" title="<?php echo esc_attr($achievement['description'] ?? ''); ?>"
                                                 style="<?php echo empty($achievement['earned']) ? 'filter: grayscale(100%); opacity: 0.4;' : ''; ?>">
                                            <span class="text-[#4A5568] text-[14px] font-medium"><?php echo esc_html($achievement['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                            </div>
                        </div>

                        <!-- PHẦN BẢNG DỮ LIỆU -->
                        <div class="flex flex-col flex-1 gap-8 max-w-[58vw]">
                            <!-- ===== BẢNG QUÀ TẶNG ===== -->
                            <div class="flex flex-col w-full gap-9">
                                <h4 class="text-[18px] font-medium text-[#31333F]">Quà tặng</h4>
                                <div class="flex flex-col wrapper-table">
                                    <!-- Header với Search -->
                                    <div class="flex justify-between items-center bg-white pl-4">
                                        <p class="text-[#4D7CFF] text-sm font-medium cus-bg">
                                            <?php echo isset($gifts_data['pagination']['total_items']) ? (int)$gifts_data['pagination']['total_items'] : '0'; ?> quà tặng
                                        </p>
                                        <div class="list-filter-in-table py-3 px-4 flex gap-4 items-center">
                                            <form method="get" class="flex gap-4 items-center">
                                                <input type="hidden" name="page" value="dashboard-layout">
                                                <input type="hidden" name="sub" value="user-detail">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">
                                                <input type="hidden" name="gift_page" value="1">

                                                <div class="flex gap-4 items-center ">
                                                    <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_from">Từ ngày</label>
                                                    <input type="date" name="date_from" id="date_from" class="!border-none rounded-md p-2" value="<?php echo esc_attr($gift_received_date_from); ?>">
                                                </div>
                                                <span>-</span>
                                                <div class="flex gap-4 items-center ">
                                                    <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_to">Đến ngày</label>
                                                    <input type="date" name="date_to" id="date_to" class="!border-none rounded-md p-2" value="<?php echo esc_attr($gift_received_date_to); ?>">
                                                </div>
                                                <input type="text" name="search"
                                                       value="<?php echo esc_attr($gift_search); ?>"
                                                       class="!py-[11px] !px-3 max-w-[200px]"
                                                       placeholder="Tìm kiếm theo tên quà">

                                                <button type="submit" class="px-4 py-[11px] bg-[#4D7CFF] text-white rounded">
                                                    Tìm kiếm
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Bảng dữ liệu -->
                                    <div class="overflow-x-auto table-general">
                                        <table class="min-w-full border-collapse divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left">ID</th>
                                                <th class="px-6 py-3 text-left">Tên quà tặng</th>
                                                <th class="px-6 py-3 text-left">Ngày nhận</th>
                                                <th class="px-6 py-3 text-left">Loại</th>
                                            </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                            <?php if (!empty($gifts_data['items'])): ?>
                                                <?php foreach ($gifts_data['items'] as $gift): ?>
                                                    <tr class="hover:bg-gray-50 transition td-content">
                                                        <td class="px-6 py-3"><?php echo (int)$gift['id']; ?></td>
                                                        <td class="px-6 py-3"><?php echo esc_html($gift['name']); ?></td>
                                                        <td class="px-6 py-3"><?php echo esc_html($gift['received_date']); ?></td>
                                                        <td class="px-6 py-3">
                                                            <?php
                                                            $type_label = ($gift['type'] === 'voucher') ? 'Voucher' : 'Hiện vật';
                                                            echo esc_html($type_label);
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="px-6 py-3 text-center">Không có quà tặng nào</td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>

                                            <!-- Phân trang -->
                                            <tfoot>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4">
                                                    <div class="flex justify-between items-center">
                                                        <div class="left">
                                                            <p class="text-sm font-regular text-[#6A7A95]">
                                                                Trang <span class="text-[#344054] font-medium">
                                                                <?php echo isset($gifts_data['pagination']['current_page']) ? (int)$gifts_data['pagination']['current_page'] : '1'; ?>
                                                            </span> trên <span class="text-[#344054] font-medium">
                                                                <?php echo isset($gifts_data['pagination']['total_pages']) ? (int)$gifts_data['pagination']['total_pages'] : '1'; ?>
                                                            </span>
                                                            </p>
                                                        </div>
                                                        <div class="right flex gap-4">
                                                            <?php if (!empty($gifts_data['pagination']['has_prev'])): ?>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                        'user_id' => $user_id,
                                                                        'gift_page' => (int)$gifts_data['pagination']['current_page'] - 1,
                                                                        'date_from' => $gift_received_date_from,
                                                                        'date_to' => $gift_received_date_to,
                                                                        'search' => $gift_search
                                                                ])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                                    Trang trước
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] disabled" disabled>
                                                                    Trang trước
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if (!empty($gifts_data['pagination']['has_next'])): ?>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                        'user_id' => $user_id,
                                                                        'gift_page' => (int)$gifts_data['pagination']['current_page'] + 1,
                                                                        'date_from' => $gift_received_date_from,
                                                                        'date_to' => $gift_received_date_to,
                                                                        'search' => $gift_search
                                                                ])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                                    Trang sau
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] disabled" disabled>
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

                            <!-- ===== BẢNG LỊCH SỬ CHƠI GAME ===== -->
                            <div class="flex flex-col w-full gap-9" id="play-history-table">
                                <h4 class="text-[18px] font-medium text-[#31333F]">Lịch sử chơi game</h4>
                                <div class="flex flex-col wrapper-table">
                                    <!-- Header với Search -->
                                    <div class="flex justify-between items-center bg-white pl-4">
                                        <p class="text-[#4D7CFF] text-sm font-medium cus-bg">
                                            <?php echo isset($play_history['pagination']['total_items']) ? (int)$play_history['pagination']['total_items'] : '0'; ?> lượt đã chơi
                                        </p>
                                        <div class="list-filter-in-table py-3 px-4 flex gap-4 items-center">
                                            <form method="get" class="flex gap-4 items-center">
                                                <input type="hidden" name="page" value="dashboard-layout">
                                                <input type="hidden" name="sub" value="user-detail">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">
                                                <input type="hidden" name="play_page" value="1">

                                                <div class="flex gap-4 items-center ">
                                                    <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="play_date_from">Từ ngày</label>
                                                    <input type="date" name="play_date_from" id="play_date_from" class="!border-none rounded-md p-2" value="<?php echo esc_attr($play_date_from); ?>">
                                                </div>
                                                <span>-</span>
                                                <div class="flex gap-4 items-center ">
                                                    <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="play_date_to">Đến ngày</label>
                                                    <input type="date" name="play_date_to" id="play_date_to" class="!border-none rounded-md p-2" value="<?php echo esc_attr($play_date_to); ?>">
                                                </div>

                                                <button type="submit" class="px-4 py-[11px] bg-[#4D7CFF] text-white rounded">
                                                    Tìm kiếm
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Bảng dữ liệu -->
                                    <div class="overflow-x-auto table-general">
                                        <table class="min-w-full border-collapse divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left">ID lượt chơi</th>
                                                <th class="px-6 py-3 text-left">Họ và tên</th>
                                                <th class="px-6 py-3 text-left">Ngày chơi</th>
                                                <th class="px-6 py-3 text-left">Kết quả</th>
                                                <th class="px-6 py-3 text-left">Số lượt đã thử lại</th>
                                                <th class="px-6 py-3 text-left">Điểm</th>
                                                <th class="px-6 py-3 text-left">Mảnh ghép</th>
                                            </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                            <?php if (!empty($play_history['items'])): ?>
                                                <?php foreach ($play_history['items'] as $session):
                                                    ?>
                                                    <tr class="hover:bg-gray-50 transition td-content">
                                                        <td class="px-6 py-3">PL-<?php echo str_pad((int)$session['session_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                                        <td class="px-6 py-3"><?php echo esc_html($user_details['name']); ?></td>
                                                        <td class="px-6 py-3"><?php echo esc_html($session['play_date']); ?></td>
                                                        <td class="px-6 py-3">
                                                         <span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo ($session['correct_count'] == $session['questions_count']) ? '#d4edda' : '#f8d7da'; ?>;">
                                                                <?php echo esc_html($session['result']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-3"><?php echo esc_html($session['retries_used']); ?></td>
                                                        <td class="px-6 py-3">
                                                            <?php echo !empty($session['points']) ? esc_html($session['points']) : '-'; ?>
                                                        </td>
                                                        <td class="px-6 py-3">
<!--                                                            --><?php //echo !empty($session['pieces']) ? esc_html($session['pieces']) : '-'; ?>
                                                            <?php echo !empty($session['pieces']) ? format_pieces_collection($session['pieces']) : '-'; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="px-6 py-3 text-center">Không có lịch sử chơi nào</td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>

                                            <!-- Phân trang -->
                                            <tfoot>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4">
                                                    <div class="flex justify-between items-center">
                                                        <div class="left">
                                                            <p class="text-sm font-regular text-[#6A7A95]">
                                                                Trang <span class="text-[#344054] font-medium">
                                                                <?php echo isset($play_history['pagination']['current_page']) ? (int)$play_history['pagination']['current_page'] : '1'; ?>
                                                            </span> trên <span class="text-[#344054] font-medium">
                                                                <?php echo isset($play_history['pagination']['total_pages']) ? (int)$play_history['pagination']['total_pages'] : '1'; ?>
                                                            </span>
                                                            </p>
                                                        </div>
                                                        <div class="right flex gap-4">
                                                            <?php if (!empty($play_history['pagination']['has_prev'])): ?>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                        'user_id' => $user_id,
                                                                        'play_page' => (int)$play_history['pagination']['current_page'] - 1,
                                                                        'play_date_from' => $play_date_from,
                                                                        'play_date_to' => $play_date_to
                                                                ])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                                    Trang trước
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] disabled" disabled>
                                                                    Trang trước
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if (!empty($play_history['pagination']['has_next'])): ?>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                        'user_id' => $user_id,
                                                                        'play_page' => (int)$play_history['pagination']['current_page'] + 1,
                                                                        'play_date_from' => $play_date_from,
                                                                        'play_date_to' => $play_date_to
                                                                ])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                                    Trang sau
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] disabled" disabled>
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

                            <!-- ===== BẢNG BIẾN ĐỘNG LỊCH SỬ LƯỢT CHƠI ===== -->
                            <div class="flex flex-col w-full gap-9" id="play-credit-ledger-table">
                                <h4 class="text-[18px] font-medium text-[#31333F]">Danh sách lịch sử lượt chơi</h4>
                                <div class="flex flex-col wrapper-table">
                                    <div class="ledger-filters-bar">
                                        <div class="ledger-badges">
                                            <span class="ledger-badge">Tổng số lượt đã chơi: <?php echo number_format((int)($play_credit_history['summary']['total_played_turns'] ?? 0)); ?> lượt</span>
                                            <span class="ledger-badge">Số lượt còn lại: <?php echo number_format((int)($play_credit_history['summary']['total_remaining_turns'] ?? 0)); ?> lượt</span>
                                        </div>

                                        <div class="ledger-filter-actions">
                                            <form method="get" class="ledger-filter-form" id="user-ledger-filter-form">
                                                <input type="hidden" name="page" value="dashboard-layout">
                                                <input type="hidden" name="sub" value="user-detail">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">
                                                <input type="hidden" name="credit_page" value="1">

                                                <div class="ledger-status-wrap">
                                                    <label for="credit_status">Trạng thái</label>
                                                    <select id="credit_status" name="credit_status">
                                                        <option value="all" <?php selected($credit_status, 'all'); ?>>Tất cả</option>
                                                        <option value="plus" <?php selected($credit_status, 'plus'); ?>>Cộng lượt</option>
                                                        <option value="minus" <?php selected($credit_status, 'minus'); ?>>Trừ lượt</option>
                                                    </select>
                                                </div>

                                                <div class="ledger-date-range">
                                                    <label for="credit_date_from">Từ ngày</label>
                                                    <input type="date" name="credit_date_from" id="credit_date_from" value="<?php echo esc_attr($credit_date_from); ?>">
                                                    <span>-</span>
                                                    <label for="credit_date_to">Đến ngày</label>
                                                    <input type="date" name="credit_date_to" id="credit_date_to" value="<?php echo esc_attr($credit_date_to); ?>">
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto table-general">
                                        <table class="min-w-full border-collapse divide-y divide-gray-200 ledger-table">
                                            <thead class="bg-gray-50">
                                            <tr>
                                                <th class="text-left">Thời gian</th>
                                                <th class="text-left">Trạng thái (+/-)</th>
                                                <th class="text-left">Lý do</th>
                                                <th class="text-left">Số lượt</th>
                                            </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                            <?php if (!empty($play_credit_history['items'])): ?>
                                                <?php foreach ($play_credit_history['items'] as $ledger_item): ?>
                                                    <tr class="hover:bg-gray-50 transition td-content">
                                                        <td class="px-6 py-3"><?php echo esc_html($ledger_item['created_at_display']); ?></td>
                                                        <td class="px-6 py-3">
                                                            <span class="ledger-status-badge <?php echo ((int)$ledger_item['delta'] >= 0) ? 'plus' : 'minus'; ?>">
                                                                <?php echo ((int)$ledger_item['delta'] >= 0) ? '+' : '-'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-3">
                                                            <span class="ledger-reason"><?php echo esc_html($ledger_item['detail']); ?></span>
                                                        </td>
                                                        <td class="px-6 py-3 <?php echo ((int)$ledger_item['delta'] >= 0) ? 'ledger-delta-plus' : 'ledger-delta-minus'; ?>">
                                                            <?php echo esc_html((string)($ledger_item['delta_abs'] ?? 0)); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="px-6 py-3 text-center">Không có dữ liệu biến động</td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>

                                            <tfoot>
                                            <tr>
                                                <td colspan="4" class="px-6 py-4">
                                                    <div class="flex justify-between items-center">
                                                        <div class="left">
                                                            <p class="text-sm font-regular text-[#6A7A95]">
                                                                Trang <span class="text-[#344054] font-medium">
                                                                <?php echo isset($play_credit_history['pagination']['current_page']) ? (int)$play_credit_history['pagination']['current_page'] : 1; ?>
                                                            </span> trên <span class="text-[#344054] font-medium">
                                                                <?php echo isset($play_credit_history['pagination']['total_pages']) ? (int)$play_credit_history['pagination']['total_pages'] : 1; ?>
                                                            </span>
                                                            </p>
                                                        </div>
                                                        <div class="right flex gap-4">
                                                            <?php if (!empty($play_credit_history['pagination']['has_prev'])): ?>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                        'user_id' => $user_id,
                                                                        'credit_page' => (int)$play_credit_history['pagination']['current_page'] - 1,
                                                                        'credit_date_from' => $credit_date_from,
                                                                        'credit_date_to' => $credit_date_to,
                                                                        'credit_status' => $credit_status,
                                                                        'play_page' => $play_page,
                                                                        'play_date_from' => $play_date_from,
                                                                        'play_date_to' => $play_date_to,
                                                                        'gift_page' => $gift_page,
                                                                        'search' => $gift_search,
                                                                        'date_from' => $gift_received_date_from,
                                                                        'date_to' => $gift_received_date_to,
                                                                ])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                                    Trang trước
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] disabled" disabled>
                                                                    Trang trước
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if (!empty($play_credit_history['pagination']['has_next'])): ?>
                                                                <a href="<?php echo esc_url(add_query_arg([
                                                                        'user_id' => $user_id,
                                                                        'credit_page' => (int)$play_credit_history['pagination']['current_page'] + 1,
                                                                        'credit_date_from' => $credit_date_from,
                                                                        'credit_date_to' => $credit_date_to,
                                                                        'credit_status' => $credit_status,
                                                                        'play_page' => $play_page,
                                                                        'play_date_from' => $play_date_from,
                                                                        'play_date_to' => $play_date_to,
                                                                        'gift_page' => $gift_page,
                                                                        'search' => $gift_search,
                                                                        'date_from' => $gift_received_date_from,
                                                                        'date_to' => $gift_received_date_to,
                                                                ])); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                                    Trang sau
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] disabled" disabled>
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
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning"><p>Vui lòng chọn người chơi để xem thông tin chi tiết.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.tailwindcss.com"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ledgerForm = document.getElementById('user-ledger-filter-form');
        if (ledgerForm) {
            ['credit_status', 'credit_date_from', 'credit_date_to'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', function () {
                        ledgerForm.submit();
                    });
                }
            });
        }

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
                            label.classList.remove('loading');
                            label.classList.add('success');
                            alert('Cập nhật trạng thái tài khoản thành công!');
                        } else {
                            label.classList.remove('loading');
                            label.classList.add('error');
                            alert('Lỗi: ' + data.data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        label.classList.remove('loading');
                        label.classList.add('error');
                    });
            });
        });
    });
</script>