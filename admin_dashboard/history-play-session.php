

<?php
$timezone  = TIMEZONE; // GMT+7
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');

?>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<!-- Highcharts -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>

<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">


<body>

    <main class="flex flex-col gap-8 pb-8">
        <!-- card top -->
        <div class="card-top">
            <div class="breadcrumb flex flex-col gap-3">
                <nav class="flex gap-1">
                    <a href="#" class="text-sm font-regular text-[#6A7A95]">Game BSC</a>
                    <span class="text-sm font-regular text-[#6A7A95]">/</span>
                    <span class="text-sm font-regular text-[#6A7A95]">Lịch sử chơi</span>
                </nav>
                <h2 class="text-lg font-medium text-[#31333F]">Lịch sử chơi</h2>
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
                   class="tab-item-nav active">
                    Lịch sử chơi
                </a>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-credit-history'])); ?>"
                       class="tab-item-nav">
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

                /* Shadow/sm */
                box-shadow: 0 1px 3px 0 rgba(16, 24, 40, 0.10), 0 1px 2px 0 rgba(16, 24, 40, 0.06);
            }
        </style>
        <?php
        // Lấy tham số filter
        $paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        // Gọi hàm lấy dữ liệu
        $result = game_bsc_get_play_sessions_data($paged, 20, $date_from, $date_to, $search);

        $sessions = [];
        $total_items = 0;
        $total_pages = 0;

        if ($result['status'] === 'success') {
            $sessions = $result['data'];
            $total_items = $result['pagination']['total_items'];
            $total_pages = $result['pagination']['total_pages'];
            $current_page = $result['pagination']['current_page'];
        }
        ?>

        <section class="list-user ">
            <div class="container">
                <div class="wrapper p-6 bg-white rounded-xl flex flex-col gap-6">
                    <h2 class="text-2xl font-medium text-[#31333F]">Danh sách lịch sử lượt chơi</h2>
                    <div class="flex flex-col wrapper-table ">
                        <div class="flex justify-between items-center bg-white pl-4">
                            <!-- Hiển thị tổng số phiên chơi -->
                            <p class="text-[#4D7CFF] text-sm font-medium cus-bg"><?php echo number_format($total_items); ?> phiên đã chơi</p>

                            <!-- Filter Form -->
                            <div class="list-filter-in-table py-3 px-4 flex gap-4 items-center">
                                <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                                    <!-- Hidden input để giữ các tham số khác -->
                                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'dashboard'); ?>">
                                    <input type="hidden" name="sub" value="<?php echo esc_attr($_GET['sub'] ?? 'play-sessions'); ?>">
                                    <input type="hidden" name="paged" value="1">

                                    <!-- Filter Date -->
                                    <div class="flex items-center gap-[15px] py-[8px] px-3 rounded-lg border border-solid border-[#C9CCD2]">
                                        <div class="flex gap-4 items-center ">
                                            <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_from">Từ ngày</label>
                                            <input type="date" name="date_from" id="date_from" class="!border-none rounded-md p-2" value="<?php echo esc_attr($date_from); ?>">
                                        </div>
                                        <span>-</span>
                                        <div class="flex gap-4 items-center ">
                                            <label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_to">Đến ngày</label>
                                            <input type="date" name="date_to" id="date_to" class="!border-none rounded-md p-2" value="<?php echo esc_attr($date_to); ?>">
                                        </div>
                                    </div>

                                    <!-- Search Input -->
                                    <input type="text" name="search" class="!py-[11px] !px-3 min-w-[372px]" placeholder="Tìm kiếm người chơi" value="<?php echo esc_attr($search); ?>">

                                    <!-- Submit Button -->
                                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md text-sm font-medium hover:bg-blue-600">Tìm kiếm</button>
                                </form>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto table-general">
                            <table class="min-w-full border-collapse divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left">STK</th>
                                    <th class="px-6 py-3 text-left">ID Lượt</th>
                                    <th class="px-6 py-3 text-left">Họ và tên</th>
                                    <th class="px-6 py-3 text-left">Ngày chơi</th>
                                    <th class="px-6 py-3 text-left">Kết quả</th>
                                    <th class="px-6 py-3 text-left">Số lượt đã thử lại</th>
                                    <th class="px-6 py-3 text-left">Điểm</th>
                                    <th class="px-6 py-3 text-left">Mảnh ghép</th>
                                    <th class="px-6 py-3 text-left"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($sessions)): ?>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr class="hover:bg-gray-50 transition td-content">
                                            <!-- STK -->
                                            <td class="px-6 py-3"><?php echo esc_html(!empty($session['external_user_id']) ? $session['external_user_id'] : '-'); ?></td>

                                            <!-- ID Lượt -->
                                            <td class="px-6 py-3">#<?php echo esc_html($session['session_id']); ?></td>

                                            <!-- Họ và tên -->
                                            <td class="px-6 py-3">
                                                <a class="w-5 h-5" href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => $session['user_id']])); ?>">
                                                    <?php echo esc_html(!empty($session['user_name']) ? $session['user_name'] : '-'); ?>
                                                </a>
                                            </td>

                                            <!-- Ngày chơi -->
                                            <td class="px-6 py-3"><?php echo esc_html($session['started_at_display']); ?></td>

                                            <!-- Kết quả (correct_count/questions_count) -->
                                            <td class="px-6 py-3">
                                            <span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo ($session['correct_count'] == $session['questions_count']) ? '#d4edda' : '#f8d7da'; ?>;">
                                                <?php echo esc_html($session['result']); ?>
                                            </span>
                                            </td>

                                            <!-- Số lượt đã thử lại -->
                                            <td class="px-6 py-3"><?php echo esc_html($session['retries_used']); ?></td>

                                            <!-- Điểm -->
                                            <td class="px-6 py-3">
                                                <?php if ($session['total_points'] > 0): ?>
                                                    <span style="color: #4D7CFF; font-weight: bold;">+<?php echo esc_html($session['total_points']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #999;">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Mảnh ghép -->
                                            <td class="px-6 py-3">
                                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <span style="font-weight: bold;">
                                                    <?php echo esc_html($session['pieces_display']); ?>
                                                </span>
                                                </div>
                                            </td>

                                            <!-- Action Button -->
                                            <td class="px-6 py-3">
                                                <a class="w-5 h-5" href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => $session['user_id']])); ?>#play-history-table" title="Xem chi tiết người chơi">
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
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                            Không tìm thấy dữ liệu
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>

                                <!-- Pagination Footer -->
                                <tfoot>
                                <tr>
                                    <td colspan="9" class="px-6 py-4">
                                        <div class="flex justify-between items-center">
                                            <div class="left">
                                                <p class="text-sm font-regular text-[#6A7A95]">
                                                    Trang <span class="text-[#344054] font-medium"><?php echo $paged; ?></span>
                                                    trên <span class="text-[#344054] font-medium"><?php echo max(1, $total_pages); ?></span>
                                                </p>
                                            </div>
                                            <div class="right flex gap-4">
                                                <!-- Nút Trang trước -->
                                                <?php if ($paged > 1): ?>
                                                    <a href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1])); ?>"
                                                       class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                        Trang trước
                                                    </a>
                                                <?php else: ?>
                                                    <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50" disabled>
                                                        Trang trước
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Nút Trang sau -->
                                                <?php if ($paged < $total_pages): ?>
                                                    <a href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1])); ?>"
                                                       class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50">
                                                        Trang sau
                                                    </a>
                                                <?php else: ?>
                                                    <button class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-[#344054] hover:bg-gray-50" disabled>
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



</body>

</html>