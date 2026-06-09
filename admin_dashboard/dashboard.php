<?php
$timezone  = TIMEZONE; // GMT+7
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');

$dashboard_filter_default_to_obj = new DateTimeImmutable('now', TIMEZONE);
$dashboard_filter_default_from_obj = $dashboard_filter_default_to_obj->modify('-12 months');
$dashboard_filter_default_from_iso = $dashboard_filter_default_from_obj->format('Y-m-d');
$dashboard_filter_default_to_iso = $dashboard_filter_default_to_obj->format('Y-m-d');




?>
<script src="https://cdn.tailwindcss.com"></script>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<!-- Highcharts -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/no-data-to-display.js"></script>

<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">


<main class="flex flex-col gap-8 py-8 ">
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


    <div class="container mt-6">
       <div class="flex gap-2">
           <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'dashboard'])); ?>"
              class="tab-item-nav active">
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

    <!-- chart tong quan -->
    <style>
        .list-card .card-item {
            display: flex;
            padding: 24px;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            flex: 1 0 0;
            border-radius: 12px;
            background: rgba(181, 228, 202, 0.25);
        }

        .list-card .card-item:nth-child(2) {
            background: rgba(177, 229, 252, 0.25);
        }

        .list-card .card-item:nth-child(3) {
            background: rgba(202, 189, 255, 0.25);
        }

        .list-card .card-item:nth-child(4) {
            background: rgba(255, 228, 189, 0.25);
        }
    </style>
    <div class="container">
        <div class="card-tong-quan p-6 rounded-[12px] bg-white ">
            <div class="flex flex-col gap-4">
                <div class="dashboard-filter-row">
                    <div class="flex flex-col gap-1">
                        <h2 class="dashboard-section-title">Tổng quan tình hình và hoạt động của người chơi</h2>
                    </div>
                    <div class="dashboard-filter-controls">
                        <div class="dashboard-filter-select">
                            <select class="select-chart" name="chart-filter-tong-quan" id="chart-filter-tong-quan">
                                <option value="last_12_months" selected>12 tháng gần nhất</option>
                                <option value="today">Hôm nay</option>
                                <option value="week">Tuần này</option>
                                <option value="month">Tháng này</option>
                                <option value="custom">Tùy chọn khoảng ngày</option>
                            </select>
                        </div>
                        <div class="dashboard-date-range">
                            <div class="dashboard-date-field is-start">
                                <span class="dashboard-date-label">Từ ngày</span>
                                <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                <input type="date" id="overview-from-date" class="dashboard-date-input" value="<?php echo esc_attr($dashboard_filter_default_from_iso); ?>">
                            </div>
                            <div class="dashboard-date-separator">-</div>
                            <div class="dashboard-date-field is-end">
                                <span class="dashboard-date-label">Đến ngày</span>
                                <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                <input type="date" id="overview-to-date" class="dashboard-date-input" value="<?php echo esc_attr($dashboard_filter_default_to_iso); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spinner loading -->
            <div id="stats-loading" style="display:none; text-align:center; padding:20px;">
                <p>Đang tải dữ liệu...</p>
            </div>

            <div class="flex gap-6 mt-10 list-card" id="stats-container">
                <!-- Card 1: Tổng số người chơi truy cập -->
                <div class="card-item ">
                    <div class="top flex gap-1 w-full items-center">
                        <h3 class="text-lg font-medium text-[#31333F]">Tổng số người chơi truy cập</h3>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management'])); ?>">
                            <img class="w-6 h-6"
                                 src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up-right.svg"
                                 alt="user-icon">
                        </a>
                    </div>
                    <span class="text-[40px] font-medium text-[#31333F]" id="total_user">0</span>
                    <div class="flex gap-1 items-center mt-1">
                            <img class="w-6 h-6" id="icon_players"
                                 src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up.svg"
                                 alt="">

                        <p class="text-[12px] font-medium text-[#6F767E]" id="total_user_change">
                            <span class="text-[#83BF6E]" id="change_players">0%</span>
                            trong kỳ này
                        </p>
                    </div>
                </div>

                <!-- Card 2: Tổng số lượt tham gia chơi -->
                <div class="card-item ">
                    <div class="top flex gap-1 w-full items-center">
                        <h3 class="text-lg font-medium text-[#31333F]">Tổng số lượt tham gia chơi</h3>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-history'])); ?>">
                            <img class="w-6 h-6"
                                 src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up-right.svg"
                                 alt="user-icon">
                        </a>
                    </div>
                    <span class="text-[40px] font-medium text-[#31333F]" id="total_sessions">0</span>
                    <div class="flex gap-1 items-center mt-1">
                        <img class="w-6 h-6" id="icon_sessions"
                             src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up.svg"
                             alt="">
                        <p class="text-[12px] font-medium text-[#6F767E]">
                            <span class="text-[#83BF6E]" id="change_sessions">0%</span>
                            trong kỳ này
                        </p>
                    </div>
                </div>

                <!-- Card 3: Tỷ lệ hoàn thành -->
                <div class="card-item ">
                    <div class="top flex gap-1 w-full items-center">
                        <h3 class="text-lg font-medium text-[#31333F]">Tỷ lệ hoàn thành</h3>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-history'])); ?>">
                            <img class="w-6 h-6"
                                 src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up-right.svg"
                                 alt="user-icon">
                        </a>
                    </div>
                    <span class="text-[40px] font-medium text-[#31333F]" id="total_completion_rate">0%</span>
                    <div class="flex gap-1 items-center mt-1">
                        <img class="w-6 h-6" id="icon_sessions"
                             src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up.svg"
                             alt="">
                        <p class="text-[12px] font-medium text-[#6F767E]">
                            <span class="text-[#83BF6E]" id="change_completion">0%</span>
                            trong kỳ này
                        </p>
                    </div>
                </div>

                <!-- Card 4: Giải thưởng đã trao -->
                <div class="card-item ">
                    <div class="top flex gap-1 w-full items-center">
                        <h3 class="text-lg font-medium text-[#31333F]">Giải thưởng đã trao</h3>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-history'])); ?>">
                            <img class="w-6 h-6"
                                 src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up-right.svg"
                                 alt="user-icon">
                        </a>
                    </div>
                    <span class="text-[40px] font-medium text-[#31333F]" id="total_rewards">0/0</span>
                    <div class="flex gap-1 items-center mt-1">
                        <img class="w-6 h-6" id="icon_rewards"
                             src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-down.svg"
                             alt="">
                        <p class="text-[12px] font-medium text-[#6F767E]">
                            <span class="text-[#FF0017]" id="change_rewards">0%</span>
                            trong kỳ này
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.dashboard-date-input').forEach(function(input) {
                input.addEventListener('click', function(e) {
                    if (typeof this.showPicker === 'function') {
                        try {
                            this.showPicker();
                        } catch (err) {}
                    }
                });
            });
        });

        function gameBscFormatDateLabel(value) {
            const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) {
                return '';
            }

            return match[3] + '/' + match[2] + '/' + match[1];
        }

        function gameBscRefreshDateLabel(inputId) {
            const input = document.getElementById(inputId);
            if (!input) {
                return;
            }

            const field = input.closest('.dashboard-date-field');
            if (!field) {
                return;
            }

            const label = field.querySelector('.dashboard-date-label');
            if (!label) {
                return;
            }

            if (!label.dataset.placeholder) {
                label.dataset.placeholder = label.textContent.trim();
            }

            const formattedDate = gameBscFormatDateLabel(input.value);
            label.textContent = formattedDate || label.dataset.placeholder;
        }

        function gameBscRefreshDateRangeLabels(inputIds) {
            if (!Array.isArray(inputIds)) {
                return;
            }

            inputIds.forEach(function (inputId) {
                gameBscRefreshDateLabel(inputId);
            });
        }
    </script>

    <script>
        jQuery(document).ready(function ($) {
            function isValidDatePickerValue(value) {
                const trimmed = (value || '').trim();
                const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!match) {
                    return false;
                }

                const year = parseInt(match[1], 10);
                const month = parseInt(match[2], 10);
                const day = parseInt(match[3], 10);
                const date = new Date(year, month - 1, day);

                return date.getFullYear() === year && date.getMonth() === (month - 1) && date.getDate() === day;
            }

            function updateStatistics(payload) {
                $('#stats-loading').show();
                $('#stats-container').css('opacity', '0.5');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: Object.assign({
                        action: 'game_bsc_get_statistics',
                        range: 'today'
                    }, payload),
                    success: function (response) {
                        if (response.success) {
                            const stats = response.data.today;
                            const changes = response.data.changes;

                            // Update giá trị
                            $('#total_user').text(stats.players);
                            $('#total_sessions').text(stats.sessions);
                            $('#total_completion_rate').text(stats.completion_rate + '%');
                            $('#total_rewards').text(stats.rewards_distributed);

                            // Update phần trăm thay đổi
                            updateChangeValue('#change_players', '#icon_players', changes.players);
                            updateChangeValue('#change_sessions', '#icon_sessions', changes.sessions);
                            updateChangeValue('#change_completion', '#icon_completion', changes.completion_rate);
                            updateChangeValue('#change_rewards', '#icon_rewards', changes.rewards_distributed);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX error:', error);
                        alert('Lỗi khi tải dữ liệu thống kê');
                    },
                    complete: function () {
                        $('#stats-loading').hide();
                        $('#stats-container').css('opacity', '1');
                    }
                });
            }

            function updateChangeValue(textSelector, iconSelector, percentValue) {
                const $text = $(textSelector);
                const $icon = $(iconSelector);

                // Update text color và giá trị
                if (percentValue >= 0) {
                    $text.css('color', '#83BF6E').text(percentValue + '%');
                    $icon.attr('src', '<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-up.svg');
                } else {
                    $text.css('color', '#FF0017').text(percentValue + '%');
                    $icon.attr('src', '<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/icon/arrow-down.svg');
                }
            }

            $('#chart-filter-tong-quan').on('change', function () {
                const range = $(this).val();
                const $dateRange = $(this).closest('.dashboard-filter-controls').find('.dashboard-date-range');

                if (range === 'last_12_months') {
                    $dateRange.removeClass('is-visible');
                    $('#overview-from-date').val('');
                    $('#overview-to-date').val('');
                    gameBscRefreshDateRangeLabels(['overview-from-date', 'overview-to-date']);
                    updateStatistics({
                        range: 'last_12_months',
                        from_date: '<?php echo esc_js($dashboard_filter_default_from_iso); ?>',
                        to_date: '<?php echo esc_js($dashboard_filter_default_to_iso); ?>'
                    });
                    return;
                }

                if (range === 'custom') {
                    $dateRange.addClass('is-visible');
                    $('#overview-from-date').val('');
                    $('#overview-to-date').val('');
                    gameBscRefreshDateRangeLabels(['overview-from-date', 'overview-to-date']);
                    return; // Wait for user to pick dates
                }

                $dateRange.removeClass('is-visible');
                updateStatistics({
                    range: range,
                    from_date: '',
                    to_date: ''
                });
            });

            $('#overview-from-date, #overview-to-date').on('change', function () {
                const fromDate = ($('#overview-from-date').val() || '').trim();
                const toDate = ($('#overview-to-date').val() || '').trim();
                gameBscRefreshDateRangeLabels(['overview-from-date', 'overview-to-date']);

                if (!isValidDatePickerValue(fromDate) || !isValidDatePickerValue(toDate)) {
                    return;
                }

                $('#chart-filter-tong-quan').val('custom');
                updateStatistics({
                    range: 'custom',
                    from_date: fromDate,
                    to_date: toDate
                });
            });

            updateStatistics({
                range: 'last_12_months',
                from_date: '<?php echo esc_js($dashboard_filter_default_from_iso); ?>',
                to_date: '<?php echo esc_js($dashboard_filter_default_to_iso); ?>'
            });

            gameBscRefreshDateRangeLabels(['overview-from-date', 'overview-to-date']);


        });
    </script>

    <div class="container ">
        <div class="list-chart flex justify-between">
            <?php
            $default_trends = game_bsc_get_player_trends('custom', null, $dashboard_filter_default_from_iso, $dashboard_filter_default_to_iso);
            ?>

            <div class="chart-wrapper">
                <div class="chart-top px-6 flex flex-col gap-4">
                    <div class="dashboard-filter-row">
                        <div>
                            <h3 id="chart-trend-title" class="dashboard-section-title">Xu hướng người chơi</h3>
                            <p id="chart-trend-range" class="dashboard-section-subtitle mt-1"></p>
                        </div>
                        <div class="dashboard-filter-controls">
                            <div class="dashboard-filter-select">
                                <select class="select-chart" id="chart-period-filter" name="chart-period">
                                    <option value="last_12_months" selected>12 tháng gần nhất</option>
                                    <option value="day">Hôm nay</option>
                                    <option value="week">Tuần này</option>
                                    <option value="month">Tháng này</option>
                                    <option value="custom">Tùy chọn khoảng ngày</option>
                                </select>
                            </div>
                            <div class="dashboard-date-range">
                                <div class="dashboard-date-field is-start">
                                    <span class="dashboard-date-label">Từ ngày</span>
                                    <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                    <input
                                        type="date"
                                        id="trend-from-date"
                                        class="dashboard-date-input"
                                        value="<?php echo esc_attr($dashboard_filter_default_from_iso); ?>"
                                    >
                                </div>
                                <div class="dashboard-date-separator">-</div>
                                <div class="dashboard-date-field is-end">
                                    <span class="dashboard-date-label">Đến ngày</span>
                                    <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                    <input
                                        type="date"
                                        id="trend-to-date"
                                        class="dashboard-date-input"
                                        value="<?php echo esc_attr($dashboard_filter_default_to_iso); ?>"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="chart-xu-huong-container" class="chart-body mt-[69px]"></div>
            </div>

            <script>
                jQuery(function ($) {
                    // ===== DỮ LIỆU MẶC ĐỊNH (từ PHP) =====
                    let currentTrends = <?php echo json_encode($default_trends); ?>;
                    let ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

                    function isValidDatePickerValue(value) {
                        const trimmed = (value || '').trim();
                        const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (!match) {
                            return false;
                        }

                        const year = parseInt(match[1], 10);
                        const month = parseInt(match[2], 10);
                        const day = parseInt(match[3], 10);
                        const date = new Date(year, month - 1, day);

                        return date.getFullYear() === year && date.getMonth() === (month - 1) && date.getDate() === day;
                    }

                    function updateTrendHeader(trends) {
                        let title = 'Xu hướng người chơi';
                        if (trends.point_mode === 'day') {
                            title = 'Xu hướng người chơi theo ngày';
                        } else if (trends.point_mode === 'week') {
                            title = 'Xu hướng người chơi theo tuần';
                        } else if (trends.point_mode === 'month') {
                            title = 'Xu hướng người chơi theo tháng';
                        }

                        $('#chart-trend-title').text(title);

                        const start = trends?.date_range?.start_label || trends?.applied_filter?.from_date || '';
                        const end = trends?.date_range?.end_label || trends?.applied_filter?.to_date || '';
                        if (start && end) {
                            $('#chart-trend-range').text('Từ ' + start + ' đến ' + end);
                        } else {
                            $('#chart-trend-range').text('');
                        }
                    }

                    function fetchTrends(payload) {
                        $.ajax({
                            type: 'POST',
                            url: ajaxUrl,
                            data: Object.assign({
                                action: 'game_bsc_get_player_trends',
                                date: ''
                            }, payload),
                            dataType: 'json',
                            beforeSend: function () {
                                $('#chart-xu-huong-container').html('<p style="text-align:center; padding:20px;">Đang tải dữ liệu...</p>');
                            },
                            success: function (response) {
                                if (response.success) {
                                    currentTrends = response.data;
                                    renderChart(currentTrends);
                                } else {
                                    console.error('AJAX Error:', response.data);
                                    alert('Lỗi: ' + (response.data.error || 'Không thể tải dữ liệu'));
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('AJAX Request Error:', status, error);
                                alert('Lỗi kết nối: ' + error);
                            }
                        });
                    }

                    /**
                     * Render biểu đồ Highcharts
                     */
                    function renderChart(trends) {
                        updateTrendHeader(trends);

                        const categories = [];
                        const visitsData = [];      // ✅ Đổi từ playersData
                        const sessionsData = [];    // ✅ Đổi từ participantsData

                        // Nếu có data_points (week/month), lấy từ đó
                        if (trends.data_points && trends.data_points.length > 0) {
                            trends.data_points.forEach(point => {
                                categories.push(point.label || point.date);
                                visitsData.push(point.visits);
                                sessionsData.push(point.sessions);
                            });
                        } else {
                            // Nếu là day, chỉ có summary
                            categories.push(trends?.date_range?.start_label || trends.reference_date || trends.date_range.start);
                            visitsData.push(trends.summary.visits);
                            sessionsData.push(trends.summary.sessions);
                        }

                        // Check if there's any data
                        const hasData = visitsData.length > 0 && (visitsData.some(v => v > 0) || sessionsData.some(v => v > 0));

                        Highcharts.chart('chart-xu-huong-container', {
                            chart: {
                                type: 'column'
                            },
                            title: {
                                text: ''
                            },
                            lang: {
                                noData: 'Chưa có dữ liệu'
                            },
                            noData: {
                                style: {
                                    fontWeight: 'normal',
                                    fontSize: '14px',
                                    color: '#999'
                                }
                            },
                            xAxis: {
                                categories: categories,
                                crosshair: true,
                            },
                            yAxis: {
                                min: 0,
                                title: {
                                    text: 'Số lượng'
                                },
                                tickInterval: 1,
                                allowDecimals: false,
                                type: 'linear'
                            },
                            plotOptions: {
                                column: {
                                    pointPadding: 0.2,
                                    borderWidth: 0
                                }
                            },
                            series: hasData ? [
                                {
                                    name: 'Lượt truy cập',
                                    data: visitsData
                                },
                                {
                                    name: 'Lượt tham gia',
                                    data: sessionsData
                                }
                            ] : []
                        });
                    }

                    // ===== RENDER NGAY KHI LOAD PAGE =====
                    renderChart(currentTrends);

                    // ===== JQUERY AJAX FILTER =====
                    $('#chart-period-filter').on('change', function () {
                        const period = $(this).val();
                        const $dateRange = $(this).closest('.dashboard-filter-controls').find('.dashboard-date-range');

                        if (period === 'last_12_months') {
                            $dateRange.removeClass('is-visible');
                            $('#trend-from-date').val('');
                            $('#trend-to-date').val('');
                            gameBscRefreshDateRangeLabels(['trend-from-date', 'trend-to-date']);
                            fetchTrends({
                                period: 'custom',
                                from_date: '<?php echo esc_js($dashboard_filter_default_from_iso); ?>',
                                to_date: '<?php echo esc_js($dashboard_filter_default_to_iso); ?>'
                            });
                            return;
                        }

                        if (period === 'custom') {
                            $dateRange.addClass('is-visible');
                            $('#trend-from-date').val('');
                            $('#trend-to-date').val('');
                            gameBscRefreshDateRangeLabels(['trend-from-date', 'trend-to-date']);
                            return; // Wait for user to pick dates
                        }

                        $dateRange.removeClass('is-visible');
                        fetchTrends({
                            period: period,
                            from_date: '',
                            to_date: ''
                        });
                    });

                    $('#trend-from-date, #trend-to-date').on('change', function () {
                        const fromDate = ($('#trend-from-date').val() || '').trim();
                        const toDate = ($('#trend-to-date').val() || '').trim();
                        gameBscRefreshDateRangeLabels(['trend-from-date', 'trend-to-date']);

                        if (!isValidDatePickerValue(fromDate) || !isValidDatePickerValue(toDate)) {
                            return;
                        }

                        $('#chart-period-filter').val('custom');
                        fetchTrends({
                            period: 'custom',
                            from_date: fromDate,
                            to_date: toDate
                        });
                    });

                    gameBscRefreshDateRangeLabels(['trend-from-date', 'trend-to-date']);
                });
            </script>
            <?php
            // Load dữ liệu mặc định (PHP - lần đầu load)
            $default_award_status = game_bsc_get_award_status('custom', null, $dashboard_filter_default_from_iso, $dashboard_filter_default_to_iso);
            ?>

            <div class="chart-wrapper">
                <div class="chart-top px-6 flex flex-col gap-4">
                    <div class="dashboard-filter-row">
                        <h3 class="dashboard-section-title">Tình trạng trao/nhận giải thưởng</h3>
                        <div class="dashboard-filter-controls">
                            <div class="dashboard-filter-select">
                                <select class="select-chart" id="chart-award-filter" name="chart-filter-tinh-trang">
                                    <option value="last_12_months" selected>12 tháng gần nhất</option>
                                    <option value="day">Hôm nay</option>
                                    <option value="week">Tuần này</option>
                                    <option value="month">Tháng này</option>
                                    <option value="custom">Tùy chọn khoảng ngày</option>
                                </select>
                            </div>
                            <div class="dashboard-date-range">
                                <div class="dashboard-date-field is-start">
                                    <span class="dashboard-date-label">Từ ngày</span>
                                    <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                    <input type="date" id="award-from-date" class="dashboard-date-input" value="<?php echo esc_attr($dashboard_filter_default_from_iso); ?>">
                                </div>
                                <div class="dashboard-date-separator">-</div>
                                <div class="dashboard-date-field is-end">
                                    <span class="dashboard-date-label">Đến ngày</span>
                                    <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                    <input type="date" id="award-to-date" class="dashboard-date-input" value="<?php echo esc_attr($dashboard_filter_default_to_iso); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB SELECTION -->
                <div class="list-tab tinh-trang flex items-end gap-2 px-6 mt-4">
                    <input type="radio" id="ti-le" name="award-chart-type" value="ti-le" checked>
                    <label for="ti-le" class="tab-item active" data-tab="ti-le">Tỷ lệ</label>
                    <input class="!ml-4" type="radio" id="pham-loai" name="award-chart-type" value="pham-loai">
                    <label for="pham-loai" class="tab-item" data-tab="pham-loai">Phân loại</label>
                </div>

                <!-- Spinner loading -->
                <div id="award-loading" style="display:none; text-align:center; padding:20px;">
                    <p>Đang tải dữ liệu...</p>
                </div>

                <!-- CHARTS -->
                <div id="chart-ti-le-container" class="chart-body mt-6"></div>
                <div id="chart-pham-loai-container" class="chart-body mt-6 hidden"></div>
            </div>

            <script>
                jQuery(function ($) {
                    // ===== DỮ LIỆU MẶC ĐỊNH (từ PHP) =====
                    let currentAwardStatus = <?php echo json_encode($default_award_status); ?>;
                    let ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

                    function isValidDatePickerValue(value) {
                        const trimmed = (value || '').trim();
                        const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        if (!match) {
                            return false;
                        }

                        const year = parseInt(match[1], 10);
                        const month = parseInt(match[2], 10);
                        const day = parseInt(match[3], 10);
                        const date = new Date(year, month - 1, day);

                        return date.getFullYear() === year && date.getMonth() === (month - 1) && date.getDate() === day;
                    }

                    function fetchAwardStatus(payload) {
                        $.ajax({
                            type: 'POST',
                            url: ajaxUrl,
                            data: Object.assign({
                                action: 'game_bsc_get_award_status',
                                period: 'week',
                                date: ''
                            }, payload),
                            dataType: 'json',
                            beforeSend: function () {
                                console.log('Loading award status filter...');
                                $('#award-loading').show();
                                $('#chart-ti-le-container, #chart-pham-loai-container').css('opacity', '0.5');
                            },
                            success: function (response) {
                                if (response.success) {
                                    currentAwardStatus = response.data;

                                    const activeTab = $('input[name="award-chart-type"]:checked').val();
                                    if (activeTab === 'ti-le') {
                                        renderRatioChart(currentAwardStatus);
                                    } else {
                                        renderCategoryChart(currentAwardStatus);
                                    }
                                } else {
                                    console.error('AJAX Error:', response.data);
                                    alert('Lỗi: ' + (response.data.error || 'Không thể tải dữ liệu'));
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('AJAX Request Error:', status, error);
                                alert('Lỗi kết nối: ' + error);
                            },
                            complete: function () {
                                $('#award-loading').hide();
                                $('#chart-ti-le-container, #chart-pham-loai-container').css('opacity', '1');
                            }
                        });
                    }

                    /**
                     * Render biểu đồ Tỷ lệ (Column - Stacked Percent)
                     */
                    function renderRatioChart(data) {
                        if (!data.data_points || data.data_points.length === 0) {
                            // Nếu là day, chỉ có summary
                            data.data_points = [{
                                date: data.reference_date || data.date_range.start,
                                awarded: data.summary.awarded,
                                not_awarded: data.summary.not_awarded
                            }];
                        }


                        const categories = [];
                        const awardedData = [];
                        const notAwardedData = [];

                        data.data_points.forEach(point => {
                            categories.push(point.date);
                            awardedData.push(point.awarded);
                            notAwardedData.push(point.not_awarded);
                        });

                        // Check if there's any data
                        const hasData = awardedData.some(v => v > 0) || notAwardedData.some(v => v > 0);

                        console.log(data);
                        Highcharts.chart('chart-ti-le-container', {
                            chart: {
                                type: 'column'
                            },
                            title: {
                                text: ''
                            },
                            lang: {
                                noData: 'Chưa có dữ liệu'
                            },
                            noData: {
                                style: {
                                    fontWeight: 'normal',
                                    fontSize: '14px',
                                    color: '#999'
                                }
                            },
                            xAxis: {
                                categories: categories,
                                crosshair: true,
                            },
                            yAxis: {
                                min: 0,
                                max: 100,
                                title: {
                                    text: 'Tỷ lệ (%)'
                                },
                                tickInterval: 10,
                                allowDecimals: false,
                            },
                            legend: {
                                align: 'center',
                                verticalAlign: 'bottom'
                            },
                            plotOptions: {
                                column: {
                                    stacking: 'percent',
                                    dataLabels: {
                                        enabled: true,
                                        formatter: function () {
                                            return this.percentage.toFixed(0) + '%';
                                        },
                                        style: {
                                            fontWeight: 'bold'
                                        }
                                    }
                                }
                            },
                            series: hasData ? [
                                {
                                    name: 'Số giải đã trao',
                                    color: '#F4A300',
                                    data: awardedData
                                },
                                {
                                    name: 'Số giải chưa nhận',
                                    color: '#9CF5C2',
                                    data: notAwardedData
                                }
                            ] : []
                        });
                    }

                    /**
                     * Render biểu đồ Phân loại (Bar - Stacked)
                     */
                    function renderCategoryChart(data) {
                        const categories = [];
                        const awardedData = [];
                        const notAwardedData = [];

                        if (data.by_category && Array.isArray(data.by_category)) {
                            data.by_category.forEach(cat => {
                                categories.push(cat.name);
                                awardedData.push(cat.awarded);
                                notAwardedData.push(cat.not_awarded);
                            });
                        }

                        // Check if there's any data
                        const hasData = awardedData.some(v => v > 0) || notAwardedData.some(v => v > 0);

                        Highcharts.chart('chart-pham-loai-container', {
                            chart: {
                                type: 'bar'
                            },
                            title: {
                                text: ''
                            },
                            lang: {
                                noData: 'Chưa có dữ liệu'
                            },
                            noData: {
                                style: {
                                    fontWeight: 'normal',
                                    fontSize: '14px',
                                    color: '#999'
                                }
                            },
                            xAxis: {
                                categories: categories
                            },
                            yAxis: {
                                min: 0,
                                title: {
                                    text: 'Số lượng (lượt đổi/giải)'
                                }
                            },
                            legend: {
                                reversed: false
                            },
                            plotOptions: {
                                series: {
                                    stacking: 'normal',
                                    dataLabels: {
                                        enabled: true,
                                        style: {fontWeight: 'bold'}
                                    }
                                }
                            },
                            series: hasData ? [
                                {
                                    name: 'Chưa nhận',
                                    color: '#F4A300',
                                    data: notAwardedData
                                },
                                {
                                    name: 'Đã trao',
                                    color: '#2DB44C',
                                    data: awardedData
                                }
                            ] : []
                        });
                    }

                    // ===== RENDER NGAY KHI LOAD PAGE =====
                    renderRatioChart(currentAwardStatus);

                    // ===== JQUERY AJAX FILTER =====
                    $('#chart-award-filter').on('change', function () {
                        const period = $(this).val();
                        const $dateRange = $(this).closest('.dashboard-filter-controls').find('.dashboard-date-range');

                        if (period === 'last_12_months') {
                            $dateRange.removeClass('is-visible');
                            $('#award-from-date').val('');
                            $('#award-to-date').val('');
                            gameBscRefreshDateRangeLabels(['award-from-date', 'award-to-date']);
                            fetchAwardStatus({
                                period: 'last_12_months',
                                from_date: '<?php echo esc_js($dashboard_filter_default_from_iso); ?>',
                                to_date: '<?php echo esc_js($dashboard_filter_default_to_iso); ?>'
                            });
                            return;
                        }

                        if (period === 'custom') {
                            $dateRange.addClass('is-visible');
                            $('#award-from-date').val('');
                            $('#award-to-date').val('');
                            gameBscRefreshDateRangeLabels(['award-from-date', 'award-to-date']);
                            return; // Wait for user to pick dates
                        }

                        $dateRange.removeClass('is-visible');
                        fetchAwardStatus({
                            period: period,
                            from_date: '',
                            to_date: ''
                        });
                    });

                    $('#award-from-date, #award-to-date').on('change', function () {
                        const fromDate = ($('#award-from-date').val() || '').trim();
                        const toDate = ($('#award-to-date').val() || '').trim();
                        gameBscRefreshDateRangeLabels(['award-from-date', 'award-to-date']);

                        if (!isValidDatePickerValue(fromDate) || !isValidDatePickerValue(toDate)) {
                            return;
                        }

                        $('#chart-award-filter').val('custom');
                        fetchAwardStatus({
                            period: 'custom',
                            from_date: fromDate,
                            to_date: toDate
                        });
                    });

                    gameBscRefreshDateRangeLabels(['award-from-date', 'award-to-date']);

                    // ===== XỬ LÝ TAB SWITCH =====
                    const tabInputs = document.querySelectorAll('input[name="award-chart-type"]');
                    const tabLabels = document.querySelectorAll('.list-tab.tinh-trang .tab-item');
                    const chartTiLe = document.getElementById('chart-ti-le-container');
                    const chartPhamLoai = document.getElementById('chart-pham-loai-container');

                    tabInputs.forEach(input => {
                        input.addEventListener('change', function () {

                            // Xóa active tất cả label
                            tabLabels.forEach(label => label.classList.remove('active'));

                            // Thêm active cho label tương ứng
                            const currentLabel = document.querySelector(`label[for="${this.id}"]`);
                            currentLabel.classList.add('active');

                            // Xử lý hiển thị
                            if (this.value === 'ti-le') {
                                chartTiLe.classList.remove('hidden');
                                chartPhamLoai.classList.add('hidden');
                                renderRatioChart(currentAwardStatus);
                            } else {
                                chartTiLe.classList.add('hidden');
                                chartPhamLoai.classList.remove('hidden');
                                renderCategoryChart(currentAwardStatus);
                            }
                        });
                    });

                });
            </script>
        </div>
    </div>

    <div class="container">
        <style>
            .list-tab.hieu-suat .tab-item {
                color: #4A5568;
                font-size: 14px;
                font-style: normal;
                font-weight: 500;
                line-height: 22px;
                cursor: pointer;
            }

            .list-tab.hieu-suat .tab-item.active {
                color: #4D7CFF;
                border-bottom: 1px solid #4D7CFF;
                padding-bottom: 12px;
            }
        </style>

        <?php
        // Define provider constants
        if (!defined('MTRADER_APP')) define('MTRADER_APP', 'mtrader_app');
        if (!defined('BSC_SMART_INVEST')) define('BSC_SMART_INVEST', 'bsc_smart_invest');
        if (!defined('WEBTRADING')) define('WEBTRADING', 'webtrading');
        if (!defined('BSC_WEB')) define('BSC_WEB', 'bsc_web');

        $player_stats = game_bsc_get_dashboard_stats('custom', $dashboard_filter_default_from_iso, $dashboard_filter_default_to_iso);

        // Provider display names mapping
        $provider_names = [
            MTRADER_APP => 'Mtrader',
            BSC_SMART_INVEST => 'BSC Smart Invest',
            WEBTRADING => 'BSC Webtrading',
            BSC_WEB => 'BSC Website'
        ];
        ?>

        <!-- HTML Dashboard -->
        <div class="card-wrapper-cus flex flex-col gap-6">
            <div class="flex flex-col gap-4">
                    <div class="dashboard-filter-row">
                    <div>
                            <h3 class="dashboard-section-title">Hiệu suất người chơi từ các nền tảng</h3>
                            <p class="dashboard-section-subtitle mt-2">
                            <span id="periodLabel"><?php echo esc_html($dashboard_filter_default_from_iso); ?> đến <?php echo esc_html($dashboard_filter_default_to_iso); ?></span>
                        </p>
                    </div>
                        <div class="dashboard-filter-controls">
                            <div class="dashboard-filter-select">
                                <select class="select-chart" id="filterSelect">
                                    <option value="last_12_months" selected>12 tháng gần nhất</option>
                                    <option value="day">Hôm nay</option>
                                    <option value="week">Tuần này</option>
                                    <option value="month">Tháng này</option>
                                    <option value="custom">Tùy chọn khoảng ngày</option>
                                </select>
                            </div>
                            <div class="dashboard-date-range">
                                <div class="dashboard-date-field is-start">
                                    <span class="dashboard-date-label">Từ ngày</span>
                                    <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                    <input type="date" id="performance-from-date" class="dashboard-date-input" value="<?php echo esc_attr($dashboard_filter_default_from_iso); ?>">
                                </div>
                                <div class="dashboard-date-separator">-</div>
                                <div class="dashboard-date-field is-end">
                                    <span class="dashboard-date-label">Đến ngày</span>
                                    <span class="dashboard-calendar-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                                    <input type="date" id="performance-to-date" class="dashboard-date-input" value="<?php echo esc_attr($dashboard_filter_default_to_iso); ?>">
                                </div>
                            </div>
                        </div>
                </div>
            </div>

            <div class="list-tab hieu-suat flex gap-6 mt-4">
                <span class="tab-item active" data-tab="thong-ke-nhanh">Thống kê nhanh</span>
                <span class="tab-item" data-tab="bieu-do">Biểu đồ</span>
            </div>

            <div class="list-content hieu-suat">
                <!-- Spinner loading -->
                <div id="performance-loading" style="display:none; text-align:center; padding:20px;">
                    <p>Đang tải dữ liệu...</p>
                </div>

                <!-- TAB: Thống kê nhanh -->
                <div id="thong-ke-nhanh" class="content-item grid grid-cols-3 gap-6">
                    <!-- Card 1: Lượt truy cập -->
                    <div class="card-thong-ke-nhanh flex flex-col gap-10 p-6">
                        <h4 class="text-[18px] text-[#31333F] font-medium">Lượt truy cập</h4>
                        <div class="flex flex-col gap-4">
                        <span class="text-[40px] text-[#31333F] font-medium"
                              id="visits-total"><?php echo $player_stats['quick_stats']['visits']['total']; ?></span>
                        </div>
                        <div class="w-full flex gap-2" id="visits-bars">
                            <?php foreach ($player_stats['quick_stats']['visits']['breakdown'] as $item): ?>
                                <?php if ($item['percentage'] > 0): ?>
                                    <div class="flex flex-col w-full">
                                        <div class="h-2.5 rounded-full bg-[#E5E7EB] w-full relative overflow-hidden">
                                            <div class="h-full rounded-full"
                                                 style="background-color: <?php echo get_provider_color($item['key']); ?>;">
                                            </div>
                                        </div>
                                        <span class="text-[12px] text-[#6B7280] mt-1">
                                        <?php echo $item['percentage']; ?>%
                                    </span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex flex-col gap-4" id="visits-breakdown">
                            <?php foreach ($player_stats['quick_stats']['visits']['breakdown'] as $item): ?>
                                <div class="flex justify-between items-center">
                                <span class="text-[16px] font-medium"
                                      style="color: <?php echo get_provider_color($item['key']); ?>"><?php echo $item['name']; ?></span>
                                    <p class="flex gap-2 items-center">
                                        <span class="text-[20px] text-[#31333F] font-medium"><?php echo $item['percentage']; ?>%</span>
                                        <a href="<?php echo add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management', 'provider' => $item['key'], 'status_play' => 'truy_cap']); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path d="M7 17L17 7M17 7H7M17 7V17" stroke="#4D7CFF" stroke-width="2"
                                                      stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </a>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Card 2: Lượt tham gia -->
                    <div class="card-thong-ke-nhanh flex flex-col gap-10 p-6">
                        <h4 class="text-[18px] text-[#31333F] font-medium">Lượt tham gia</h4>
                        <div class="flex flex-col gap-4">
                        <span class="text-[40px] text-[#31333F] font-medium"
                              id="participation-total"><?php echo $player_stats['quick_stats']['participation']['total']; ?>/<?php echo $player_stats['quick_stats']['visits']['total']; ?></span>
                        </div>
                        <div class="w-full flex gap-2" id="participation-bars">
                            <?php foreach ($player_stats['quick_stats']['participation']['breakdown'] as $item): ?>
                                <?php if ($item['percentage'] > 0): ?>
                                    <div class="flex flex-col w-full">
                                        <div class="h-2.5 rounded-full bg-[#E5E7EB] w-full relative overflow-hidden">
                                            <div class="h-full rounded-full"
                                                 style="background-color: <?php echo get_provider_color($item['key']); ?>;">
                                            </div>
                                        </div>
                                        <span class="text-[12px] text-[#6B7280] mt-1">
                                        <?php echo $item['percentage']; ?>%
                                    </span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex flex-col gap-4" id="participation-breakdown">
                            <?php foreach ($player_stats['quick_stats']['participation']['breakdown'] as $item): ?>
                                <div class="flex justify-between items-center">
                                <span class="text-[16px] font-medium"
                                      style="color: <?php echo get_provider_color($item['key']); ?>"><?php echo $item['name']; ?></span>
                                    <p class="flex gap-2 items-center">
                                        <span class="text-[20px] text-[#31333F] font-medium"><?php echo $item['percentage']; ?>%</span>
                                        <a href="<?php echo add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management', 'provider' => $item['key'], 'status_play' => 'tham-gia']); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path d="M7 17L17 7M17 7H7M17 7V17" stroke="#4D7CFF" stroke-width="2"
                                                      stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </a>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Card 3: Lượt hoàn thành -->
                    <div class="card-thong-ke-nhanh flex flex-col gap-10 p-6">
                        <h4 class="text-[18px] text-[#31333F] font-medium">Lượt hoàn thành</h4>
                        <div class="flex flex-col gap-4">
                        <span class="text-[40px] text-[#31333F] font-medium"
                              id="completed-total"><?php echo $player_stats['quick_stats']['completed']['total']; ?>/<?php echo $player_stats['quick_stats']['participation']['total']; ?></span>
                        </div>
                        <div class="w-full flex gap-2" id="completed-bars">
                            <?php foreach ($player_stats['quick_stats']['completed']['breakdown'] as $item): ?>
                                <?php if ($item['percentage'] > 0): ?>
                                    <div class="flex flex-col w-full">
                                        <div class="h-2.5 rounded-full bg-[#E5E7EB] w-full relative overflow-hidden">
                                            <div class="h-full rounded-full"
                                                 style="background-color: <?php echo get_provider_color($item['key']); ?>;">
                                            </div>
                                        </div>
                                        <span class="text-[12px] text-[#6B7280] mt-1">
                                        <?php echo $item['percentage']; ?>%
                                    </span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex flex-col gap-4" id="completed-breakdown">
                            <?php foreach ($player_stats['quick_stats']['completed']['breakdown'] as $item): ?>
                                <div class="flex justify-between items-center">
                                <span class="text-[16px] font-medium"
                                      style="color: <?php echo get_provider_color($item['key']); ?>"><?php echo $item['name']; ?></span>
                                    <p class="flex gap-2 items-center">
                                        <span class="text-[20px] text-[#31333F] font-medium"><?php echo $item['percentage']; ?>%</span>
                                        <a href="<?php echo add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management', 'provider' => $item['key'], 'status_play' => 'hoan-thanh']); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                 viewBox="0 0 24 24" fill="none">
                                                <path d="M7 17L17 7M17 7H7M17 7V17" stroke="#4D7CFF" stroke-width="2"
                                                      stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </a>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB: Biểu đồ -->
                <div id="bieu-do" class="content-item dashboard-grid-three hidden">
                    <div class="card-thong-ke-nhanh flex flex-col gap-10 p-6">
                        <h4 class="text-[18px] text-[#31333F] text-center font-medium">Lượt truy cập</h4>
                        <div class="line w-full h-[1px] bg-[#C9CCD2]"></div>
                        <div id="chart-visits" class="chart-body"></div>
                    </div>
                    <div class="card-thong-ke-nhanh flex flex-col gap-10 p-6">
                        <h4 class="text-[18px] text-[#31333F] text-center font-medium">Lượt tham gia</h4>
                        <div class="line w-full h-[1px] bg-[#C9CCD2]"></div>
                        <div id="chart-participation" class="chart-body"></div>
                    </div>
                    <div class="card-thong-ke-nhanh flex flex-col gap-10 p-6">
                        <h4 class="text-[18px] text-[#31333F] text-center font-medium">Lượt hoàn thành</h4>
                        <div class="line w-full h-[1px] bg-[#C9CCD2]"></div>
                        <div id="chart-completed" class="chart-body"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Provider constants
                const MTRADER_APP = '<?php echo MTRADER_APP; ?>';
                const BSC_SMART_INVEST = '<?php echo BSC_SMART_INVEST; ?>';
                const WEBTRADING = '<?php echo WEBTRADING; ?>';
                const BSC_WEB = '<?php echo BSC_WEB; ?>';

                const providerColors = {
                    [MTRADER_APP]: '#00D35E',
                    [BSC_SMART_INVEST]: '#5CCBFE',
                    [WEBTRADING]: '#F16457',
                    [BSC_WEB]: '#FF6B35'
                };

                const providerNames = {
                    [MTRADER_APP]: 'Mtrader',
                    [BSC_SMART_INVEST]: 'BSC Smart Invest',
                    [WEBTRADING]: 'BSC Webtrading',
                    [BSC_WEB]: 'BSC Website'
                };

                let currentCharts = {};
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('game_bsc_nonce'); ?>';

                function isValidDatePickerValue(value) {
                    const trimmed = (value || '').trim();
                    const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                    if (!match) {
                        return false;
                    }

                    const year = parseInt(match[1], 10);
                    const month = parseInt(match[2], 10);
                    const day = parseInt(match[3], 10);
                    const date = new Date(year, month - 1, day);

                    return date.getFullYear() === year && date.getMonth() === (month - 1) && date.getDate() === day;
                }

                // Initial charts load
                const initialData = <?php echo json_encode($player_stats['quick_stats']); ?>;
                createAllCharts(initialData);

                function createAllCharts(stats) {
                    createPieChart('chart-visits', stats.visits.breakdown);
                    createPieChart('chart-participation', stats.participation.breakdown);
                    createPieChart('chart-completed', stats.completed.breakdown);
                }

                function createPieChart(containerId, breakdown) {
                    if (currentCharts[containerId]) {
                        currentCharts[containerId].destroy();
                    }

                    // Handle empty or undefined breakdown
                    if (!breakdown || !Array.isArray(breakdown) || breakdown.length === 0) {
                        breakdown = [];
                    }

                    const total = breakdown.reduce((sum, item) => sum + item.value, 0);
                    const hasData = total > 0;

                    currentCharts[containerId] = Highcharts.chart(containerId, {
                        chart: {
                            type: 'pie',
                            custom: {},
                            events: {
                                render() {
                                    const chart = this;
                                    const series = chart.series[0];
                                    let customLabel = chart.options.chart.custom.label;

                                    if (!customLabel) {
                                        customLabel = chart.options.chart.custom.label = chart.renderer.label(
                                            hasData ? `Total<br/><strong>${total}</strong>` : 'Chưa có dữ liệu'
                                        ).css({
                                            color: hasData ? '#000' : '#999',
                                            textAnchor: 'middle'
                                        }).add();
                                    } else {
                                        customLabel.attr({
                                            text: hasData ? `Total<br/><strong>${total}</strong>` : 'Chưa có dữ liệu'
                                        }).css({
                                            color: hasData ? '#000' : '#999'
                                        });
                                    }

                                    const x = series.center[0] + chart.plotLeft;
                                    const y = series.center[1] + chart.plotTop - (customLabel.attr('height') / 2);

                                    customLabel.attr({x, y});
                                    customLabel.css({fontSize: `${series.center[2] / 12}px`});
                                }
                            }
                        },
                        tooltip: {
                            enabled: hasData,
                            pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
                        },
                        title: {text: null},
                        legend: {enabled: hasData},
                        plotOptions: {
                            series: {
                                allowPointSelect: hasData,
                                cursor: hasData ? 'pointer' : 'default',
                                borderRadius: 8,
                                dataLabels: [
                                    {enabled: hasData, distance: 20, format: '{point.name}'},
                                    {
                                        enabled: hasData,
                                        distance: -15,
                                        format: '{point.percentage:.1f}%',
                                        style: {fontSize: '0.9em'}
                                    }
                                ],
                                showInLegend: hasData
                            }
                        },
                        series: [{
                            name: 'Count',
                            colorByPoint: true,
                            innerSize: '75%',
                            data: hasData
                                ? breakdown.map(item => ({
                                    name: item.name,
                                    y: item.percentage,
                                    color: getColor(item.key)
                                }))
                                : [{
                                    name: 'No Data',
                                    y: 1,
                                    color: '#E0E0E0'
                                }]
                        }]
                    });
                }

                function fillStatsCard(type, data, quickStats) {
                    const total = document.getElementById(`${type}-total`);
                    const breakdownContainer = document.getElementById(`${type}-breakdown`);
                    const barsContainer = document.getElementById(`${type}-bars`);

                    if (type === 'participation') {
                        total.textContent = `${data.total}/${quickStats.visits.total}`;
                    } else if (type === 'completed') {
                        total.textContent = `${data.total}/${quickStats.participation.total}`;
                    } else {
                        total.textContent = data.total;
                    }

                    barsContainer.innerHTML = data.breakdown
                        .filter(item => item.percentage > 0)
                        .map(item => `
                    <div class="flex flex-col w-full">
                        <div class="h-2.5 rounded-full bg-[#E5E7EB] w-full relative overflow-hidden">
                            <div class="h-full rounded-full"
                                 style="background:${getColor(item.key)};"></div>
                        </div>
                        <span class="text-[12px] text-[#6B7280] mt-1">${item.percentage}%</span>
                    </div>
                `).join('');

                    breakdownContainer.innerHTML = data.breakdown.length > 0
                        ? data.breakdown.map(item => `
                    <div class="flex justify-between items-center">
                        <span class="text-[16px] font-medium" style="color:${getColor(item.key)}">${item.name}</span>
                        <p class="flex gap-2 items-center">
                            <span class="text-[20px] text-[#31333F] font-medium">${item.percentage}%</span>
                            <a href="<?php echo add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management']); ?>&provider=${item.key}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                     viewBox="0 0 24 24" fill="none">
                                    <path d="M7 17L17 7M17 7H7M17 7V17" stroke="#4D7CFF" stroke-width="2"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </p>
                    </div>
                `).join('')
                        : `<p class="text-center text-[#6B7280]">Chưa có dữ liệu</p>`;
                }

                async function fetchDashboardStats(filter, fromDate = '', toDate = '') {
                    const loading = document.getElementById('performance-loading');
                    const content = document.querySelector('.list-content.hieu-suat');
                    if (loading) loading.style.display = 'block';
                    if (content) content.style.opacity = '0.5';

                    try {
                        const formData = new FormData();
                        formData.append('action', 'game_bsc_dashboard_stats');
                        formData.append('filter', filter);
                        formData.append('from_date', fromDate);
                        formData.append('to_date', toDate);
                        formData.append('_nonce', nonce);

                        const response = await fetch(ajaxUrl, {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            const data = result.data;

                            // Update period label
                            document.getElementById('periodLabel').textContent =
                                `${data.period.start_date} đến ${data.period.end_date}`;

                            // Update stats cards
                            fillStatsCard('visits', data.quick_stats.visits, data.quick_stats);
                            fillStatsCard('participation', data.quick_stats.participation, data.quick_stats);
                            fillStatsCard('completed', data.quick_stats.completed, data.quick_stats);

                            // Recreate charts
                            setTimeout(() => {
                                createAllCharts(data.quick_stats);
                            }, 100);
                        } else {
                            console.error('AJAX Error:', result.data?.message);
                        }
                    } catch (error) {
                        console.error('Fetch Error:', error);
                    } finally {
                        if (loading) loading.style.display = 'none';
                        if (content) content.style.opacity = '1';
                    }
                }

                function getColor(providerKey) {
                    return providerColors[providerKey] ?? '#999';
                }

                // Tab switching
                const tabItems = document.querySelectorAll('.list-tab.hieu-suat .tab-item');
                const contentItems = document.querySelectorAll('.list-content.hieu-suat .content-item');

                tabItems.forEach(item => {
                    item.addEventListener('click', function () {
                        tabItems.forEach(tab => tab.classList.remove('active'));
                        contentItems.forEach(content => content.classList.add('hidden'));
                        this.classList.add('active');
                        const tabData = this.getAttribute('data-tab');
                        document.getElementById(tabData).classList.remove('hidden');
                    });
                });

                // Filter change - AJAX call
                document.getElementById('filterSelect').addEventListener('change', function (e) {
                    const filter = e.target.value;
                    const dateRange = this.closest('.dashboard-filter-controls').querySelector('.dashboard-date-range');

                    if (filter === 'last_12_months') {
                        dateRange.classList.remove('is-visible');
                        document.getElementById('performance-from-date').value = '';
                        document.getElementById('performance-to-date').value = '';
                        gameBscRefreshDateRangeLabels(['performance-from-date', 'performance-to-date']);
                        fetchDashboardStats('last_12_months', '<?php echo esc_js($dashboard_filter_default_from_iso); ?>', '<?php echo esc_js($dashboard_filter_default_to_iso); ?>');
                        return;
                    }

                    if (filter === 'custom') {
                        dateRange.classList.add('is-visible');
                        document.getElementById('performance-from-date').value = '';
                        document.getElementById('performance-to-date').value = '';
                        gameBscRefreshDateRangeLabels(['performance-from-date', 'performance-to-date']);
                        return; // Wait for user to pick dates
                    }

                    dateRange.classList.remove('is-visible');
                    fetchDashboardStats(filter, '', '');
                });

                document.getElementById('performance-from-date').addEventListener('change', function () {
                    const fromDate = (document.getElementById('performance-from-date').value || '').trim();
                    const toDate = (document.getElementById('performance-to-date').value || '').trim();
                    gameBscRefreshDateRangeLabels(['performance-from-date', 'performance-to-date']);

                    if (!isValidDatePickerValue(fromDate) || !isValidDatePickerValue(toDate)) {
                        return;
                    }

                    document.getElementById('filterSelect').value = 'custom';
                    fetchDashboardStats('custom', fromDate, toDate);
                });

                document.getElementById('performance-to-date').addEventListener('change', function () {
                    const fromDate = (document.getElementById('performance-from-date').value || '').trim();
                    const toDate = (document.getElementById('performance-to-date').value || '').trim();
                    gameBscRefreshDateRangeLabels(['performance-from-date', 'performance-to-date']);

                    if (!isValidDatePickerValue(fromDate) || !isValidDatePickerValue(toDate)) {
                        return;
                    }

                    document.getElementById('filterSelect').value = 'custom';
                    fetchDashboardStats('custom', fromDate, toDate);
                });

                gameBscRefreshDateRangeLabels(['performance-from-date', 'performance-to-date']);
            });
        </script>
    </div>
</main>


<!-- Highcharts Script -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/no-data-to-display.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>

