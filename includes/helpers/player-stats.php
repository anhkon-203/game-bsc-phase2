<?php
if (!defined('ABSPATH')) exit;

/**
 * Award Status Dashboard Statistics
 * Provider Constants
 */
if (!defined('MTRADER_APP')) define('MTRADER_APP', 'mtrader_app');
if (!defined('BSC_SMART_INVEST')) define('BSC_SMART_INVEST', 'bsc_smart_invest');
if (!defined('WEBTRADING')) define('WEBTRADING', 'webtrading');
if (!defined('BSC_WEB')) define('BSC_WEB', 'bsc_web');

/**
 * Award Status Dashboard Statistics
 * - Lượt truy cập (wp_game_user_login_logs - số lần đăng nhập)
 * - Lượt tham gia (wp_game_users_play_sessions - số session chơi)
 * - Lượt hoàn thành (wp_game_users_play_sessions where questions_count = correct_count)
 */

// ===== REGISTER AJAX HANDLERS =====
add_action('wp_ajax_game_bsc_dashboard_stats', 'game_bsc_ajax_dashboard_stats');
add_action('wp_ajax_nopriv_game_bsc_dashboard_stats', 'game_bsc_ajax_dashboard_stats');

function game_bsc_parse_dashboard_filter_date($raw_date, DateTimeZone $tz)
{
	$raw_date = trim((string)$raw_date);
	if ($raw_date === '') {
		return null;
	}

	$formats = ['d/m/Y', 'Y-m-d'];
	foreach ($formats as $format) {
		$date_obj = DateTimeImmutable::createFromFormat('!' . $format, $raw_date, $tz);
		$errors = DateTimeImmutable::getLastErrors();
		$warning_count = is_array($errors) ? (int)($errors['warning_count'] ?? 0) : 0;
		$error_count = is_array($errors) ? (int)($errors['error_count'] ?? 0) : 0;
		if ($date_obj && $warning_count === 0 && $error_count === 0) {
			return $date_obj;
		}
	}

	return null;
}

/**
 * AJAX Callback: Lấy thống kê dashboard
 * Parameters: filter (day|week|month)
 */
function game_bsc_ajax_dashboard_stats() {
	check_ajax_referer('game_bsc_nonce', '_nonce');
	
	$filter = sanitize_text_field($_POST['filter'] ?? 'day');
	$from_date = sanitize_text_field($_POST['from_date'] ?? '');
	$to_date = sanitize_text_field($_POST['to_date'] ?? '');
	
	// Validate filter
	if (!in_array($filter, ['day', 'week', 'month', 'custom', 'last_12_months'], true)) {
		$filter = 'day';
	}
	
	try {
		$stats = game_bsc_get_dashboard_stats($filter, $from_date, $to_date);
		wp_send_json_success($stats);
	} catch (Exception $e) {
		wp_send_json_error(['message' => $e->getMessage()]);
	}
}

/**
 * Hàm chính: Lấy dữ liệu thống kê dashboard
 *
 * @param string $filter 'day' | 'week' | 'month'
 * @return array {
 *     'quick_stats': {
 *         'visits': { 'total': int, 'mtrader_app': int, 'bsc_smart_invest': int, 'webtrading': int, 'bsc_web': int, 'breakdown': array },
 *         'participation': { 'total': int, 'mtrader_app': int, 'bsc_smart_invest': int, 'webtrading': int, 'bsc_web': int, 'percentage': float, 'breakdown': array },
 *         'completed': { 'total': int, 'mtrader_app': int, 'bsc_smart_invest': int, 'webtrading': int, 'bsc_web': int, 'percentage': float, 'breakdown': array }
 *     },
 *     'period': {
 *         'start_date': string,
 *         'end_date': string,
 *         'filter': string,
 *         'label': string
 *     }
 * }
 */
function game_bsc_get_dashboard_stats($filter = 'week', $from_date = '', $to_date = '') {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// 1) Tính toán khoảng thời gian
	$tz = TIMEZONE;
	$period = game_bsc_calculate_period($filter, $tz, $from_date, $to_date);
	
	$start_date = $period['start_date'];
	$end_date = $period['end_date'];
	
	// 2) ✅ Lấy dữ liệu visits từ login_logs (số lần đăng nhập theo provider)
	$visits_data = game_bsc_get_visits_stats($start_date, $end_date, $prefix);
	
	// 3) Lấy dữ liệu participation (từ wp_game_users_play_sessions - số session)
	$participation_data = game_bsc_get_participation_stats($start_date, $end_date, $prefix);
	
	// 4) Lấy dữ liệu completed (questions_count = correct_count)
	$completed_data = game_bsc_get_completed_stats($start_date, $end_date, $prefix);
	
	// 5) Chuẩn bị response
	return [
		'quick_stats' => [
			'visits' => $visits_data['summary'],
			'participation' => $participation_data['summary'],
			'completed' => $completed_data['summary'],
		],
		'period' => [
			'start_date' => $start_date->format('Y-m-d'),
			'end_date' => $end_date->format('Y-m-d'),
			'filter' => $filter,
			'label' => $period['label'],
		]
	];
}

/**
 * Tính toán khoảng thời gian theo filter
 *
 * @param string $filter 'day' | 'week' | 'month'
 * @param DateTimeZone $tz Timezone
 * @return array { 'start_date': DateTimeImmutable, 'end_date': DateTimeImmutable, 'label': string }
 */
function game_bsc_calculate_period($filter, $tz, $from_date = '', $to_date = '') {
	$today = new DateTimeImmutable('now', $tz);

	if ($filter === 'last_12_months') {
		$start = $today->modify('-12 months')->setTime(0, 0, 0);
		$end = $today->setTime(23, 59, 59);
		return [
			'start_date' => $start,
			'end_date' => $end,
			'label' => '12 tháng gần nhất',
		];
	}

	if ($filter === 'custom') {
		$start_obj = game_bsc_parse_dashboard_filter_date($from_date, $tz);
		$end_obj = game_bsc_parse_dashboard_filter_date($to_date, $tz);
		if (!$start_obj || !$end_obj) {
			throw new InvalidArgumentException('Định dạng ngày không hợp lệ, yêu cầu dd/mm/yyyy.');
		}

		if ($start_obj > $end_obj) {
			$tmp = $start_obj;
			$start_obj = $end_obj;
			$end_obj = $tmp;
		}

		if ($end_obj > $today) {
			$end_obj = $today;
		}

		return [
			'start_date' => $start_obj->setTime(0, 0, 0),
			'end_date' => $end_obj->setTime(23, 59, 59),
			'label' => 'Tùy chọn khoảng ngày',
		];
	}
	
	switch ($filter) {
		case 'week':
			// Tuần hiện tại (Thứ 2 - Chủ nhật)
			$start = $today->modify('monday this week')->setTime(0, 0, 0);
			$end = $today->modify('sunday this week')->setTime(23, 59, 59);
			$label = 'Tuần này';
			break;
		
		case 'month':
			// Tháng hiện tại (1st - last day)
			$start = $today->setTime(0, 0, 0)->modify('first day of this month');
			$end = $today->setTime(23, 59, 59)->modify('last day of this month');
			$label = 'Tháng này';
			break;
		
		case 'day':
		default:
			// Hôm nay
			$start = $today->setTime(0, 0, 0);
			$end = $today->setTime(23, 59, 59);
			$label = 'Hôm nay';
			break;
	}
	
	return [
		'start_date' => $start,
		'end_date' => $end,
		'label' => $label,
	];
}

/**
 * Get provider display name
 *
 * @param string $provider
 * @return string
 */
function game_bsc_get_provider_name($provider) {
	$names = [
		MTRADER_APP => 'Mtrader',
		BSC_SMART_INVEST => 'BSC Smart Invest',
		WEBTRADING => 'BSC Webtrading',
		BSC_WEB => 'BSC Website'
	];
	return isset($names[$provider]) ? $names[$provider] : ucwords(str_replace('_', ' ', $provider));
}

/**
 * ✅ FIX: Lấy thống kê lượt truy cập từ login_logs
 *
 * Lượt truy cập = số lần đăng nhập thành công (result = 'OK') theo provider
 * Một user có thể đăng nhập nhiều lần => tính tổng số lần check_in thành công
 *
 * @param DateTimeImmutable $start_date
 * @param DateTimeImmutable $end_date
 * @param string $prefix
 * @return array { 'summary': array, 'chart': array }
 */
function game_bsc_get_visits_stats($start_date, $end_date, $prefix) {
	global $wpdb;
	
	$start_str = $start_date->format('Y-m-d H:i:s');
	$end_str = $end_date->format('Y-m-d H:i:s');
	
	// ✅ Lấy số lần truy cập (login) từ login_logs
	// JOIN với users để lấy provider
	$visits_by_provider = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
                u.provider,
                COUNT(ull.id) as count
             FROM {$prefix}user_login_logs ull
             INNER JOIN {$prefix}users u ON ull.user_id = u.id
             WHERE ull.checked_at BETWEEN %s AND %s
             AND ull.result = 'OK'
             GROUP BY u.provider",
			$start_str,
			$end_str
		),
		ARRAY_A
	);
	
	$mtrader_app_count = 0;
	$bsc_smart_invest_count = 0;
	$webtrading_count = 0;
	$bsc_web_count = 0;
	
	// ✅ Xử lý dữ liệu provider chính xác
	foreach ($visits_by_provider as $row) {
		$provider = strtolower(trim($row['provider']));
		$count = (int)$row['count'];
		
		// Kiểm tra provider name chính xác
		if ($provider === MTRADER_APP || $provider === 'mtrader') {
			$mtrader_app_count = $count;
		} elseif ($provider === BSC_SMART_INVEST || $provider === 'bsc_smart_invest') {
			$bsc_smart_invest_count = $count;
		} elseif ($provider === WEBTRADING || $provider === 'webtrading') {
			$webtrading_count = $count;
		} else {
			$bsc_web_count = $count; // Default to BSC_WEB
		}
	}
	
	$total = $mtrader_app_count + $bsc_smart_invest_count + $webtrading_count + $bsc_web_count;
	
	// ✅ Tính percentages dựa trên total chính xác
	$mtrader_app_percent = $total > 0 ? ($mtrader_app_count / $total) * 100 : 0;
	$bsc_smart_invest_percent = $total > 0 ? ($bsc_smart_invest_count / $total) * 100 : 0;
	$webtrading_percent = $total > 0 ? ($webtrading_count / $total) * 100 : 0;
	$bsc_web_percent = $total > 0 ? ($bsc_web_count / $total) * 100 : 0;
	
	return [
		'summary' => [
			'total' => $total,
			MTRADER_APP => $mtrader_app_count,
			BSC_SMART_INVEST => $bsc_smart_invest_count,
			WEBTRADING => $webtrading_count,
			BSC_WEB => $bsc_web_count,
			'breakdown' => [
				[
					'name' => game_bsc_get_provider_name(MTRADER_APP),
					'value' => $mtrader_app_count,
					'key' => MTRADER_APP,
					'percentage' => round($mtrader_app_percent, 1)
				],
				[
					'name' => game_bsc_get_provider_name(BSC_SMART_INVEST),
					'value' => $bsc_smart_invest_count,
					'key' => BSC_SMART_INVEST,
					'percentage' => round($bsc_smart_invest_percent, 1)
				],
				[
					'name' => game_bsc_get_provider_name(WEBTRADING),
					'value' => $webtrading_count,
					'key' => WEBTRADING,
					'percentage' => round($webtrading_percent, 1)
				],
				[
					'name' => game_bsc_get_provider_name(BSC_WEB),
					'value' => $bsc_web_count,
					'key' => BSC_WEB,
					'percentage' => round($bsc_web_percent, 1)
				],
			]
		],
		'chart' => [
			['name' => game_bsc_get_provider_name(MTRADER_APP), 'y' => round($mtrader_app_percent, 1)],
			['name' => game_bsc_get_provider_name(BSC_SMART_INVEST), 'y' => round($bsc_smart_invest_percent, 1)],
			['name' => game_bsc_get_provider_name(WEBTRADING), 'y' => round($webtrading_percent, 1)],
			['name' => game_bsc_get_provider_name(BSC_WEB), 'y' => round($bsc_web_percent, 1)],
		]
	];
}

/**
 * ✅ FIX: Lấy thống kê lượt tham gia
 *
 * Lượt tham gia = tổng số SESSION (play_sessions) được tạo
 * Một user có thể tham gia nhiều session
 * Tính theo provider từ bảng users
 *
 * @param DateTimeImmutable $start_date
 * @param DateTimeImmutable $end_date
 * @param string $prefix
 * @return array { 'summary': array, 'chart': array }
 */
function game_bsc_get_participation_stats($start_date, $end_date, $prefix) {
	global $wpdb;
	
	$start_str = $start_date->format('Y-m-d H:i:s');
	$end_str = $end_date->format('Y-m-d H:i:s');
	
	// ✅ Đếm TỔNG SỐ SESSION (không phân biệt user)
	$participation_by_provider = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
                u.provider,
                COUNT(ups.id) as session_count
             FROM {$prefix}users_play_sessions ups
             INNER JOIN {$prefix}users u ON ups.user_id = u.id
             WHERE ups.started_at BETWEEN %s AND %s
             GROUP BY u.provider",
			$start_str,
			$end_str
		),
		ARRAY_A
	);
	
	$mtrader_app_sessions = 0;
	$bsc_smart_invest_sessions = 0;
	$webtrading_sessions = 0;
	$bsc_web_sessions = 0;
	
	foreach ($participation_by_provider as $row) {
		$provider = strtolower($row['provider']);
		$count = (int)$row['session_count'];
		
		if (strpos($provider, MTRADER_APP) !== false || strpos($provider, 'mtrader') !== false) {
			$mtrader_app_sessions = $count;
		} elseif (strpos($provider, BSC_SMART_INVEST) !== false || strpos($provider, 'bsc_smart') !== false) {
			$bsc_smart_invest_sessions = $count;
		} elseif (strpos($provider, WEBTRADING) !== false || strpos($provider, 'webtrading') !== false) {
			$webtrading_sessions = $count;
		} else {
			$bsc_web_sessions = $count; // Default to BSC_WEB
		}
	}
	
	// Tính tổng sessions
	$total_participation = $mtrader_app_sessions + $bsc_smart_invest_sessions + $webtrading_sessions + $bsc_web_sessions;
	
	// Lấy tổng visits để tính percentage
	$visits_data = game_bsc_get_visits_stats($start_date, $end_date, $prefix);
	$total_visits = $visits_data['summary']['total'];
	
	$participation_percent = $total_visits > 0 ? ($total_participation / $total_visits) * 100 : 0;
	
	$mtrader_app_percent = $total_participation > 0 ? ($mtrader_app_sessions / $total_participation) * 100 : 0;
	$bsc_smart_invest_percent = $total_participation > 0 ? ($bsc_smart_invest_sessions / $total_participation) * 100 : 0;
	$webtrading_percent = $total_participation > 0 ? ($webtrading_sessions / $total_participation) * 100 : 0;
	$bsc_web_percent = $total_participation > 0 ? ($bsc_web_sessions / $total_participation) * 100 : 0;
	
	return [
		'summary' => [
			'total' => $total_participation,
			MTRADER_APP => $mtrader_app_sessions,
			BSC_SMART_INVEST => $bsc_smart_invest_sessions,
			WEBTRADING => $webtrading_sessions,
			BSC_WEB => $bsc_web_sessions,
			'percentage' => round($participation_percent, 1),
			'breakdown' => [
				['name' => game_bsc_get_provider_name(MTRADER_APP), 'value' => $mtrader_app_sessions, 'percentage' => round($mtrader_app_percent, 1), 'key' => MTRADER_APP],
				['name' => game_bsc_get_provider_name(BSC_SMART_INVEST), 'value' => $bsc_smart_invest_sessions, 'percentage' => round($bsc_smart_invest_percent, 1), 'key' => BSC_SMART_INVEST],
				['name' => game_bsc_get_provider_name(WEBTRADING), 'value' => $webtrading_sessions, 'percentage' => round($webtrading_percent, 1), 'key' => WEBTRADING],
				['name' => game_bsc_get_provider_name(BSC_WEB), 'value' => $bsc_web_sessions, 'percentage' => round($bsc_web_percent, 1), 'key' => BSC_WEB],
			]
		],
		'chart' => [
			['name' => game_bsc_get_provider_name(MTRADER_APP), 'y' => round($mtrader_app_percent, 1)],
			['name' => game_bsc_get_provider_name(BSC_SMART_INVEST), 'y' => round($bsc_smart_invest_percent, 1)],
			['name' => game_bsc_get_provider_name(WEBTRADING), 'y' => round($webtrading_percent, 1)],
			['name' => game_bsc_get_provider_name(BSC_WEB), 'y' => round($bsc_web_percent, 1)],
		]
	];
}

/**
 * ✅ Lấy thống kê lượt hoàn thành
 * Hoàn thành = questions_count = correct_count (trả lời đúng TẤT CẢ câu hỏi)
 *
 * ✅ ĐẾM TỔNG SỐ PHIÊN HOÀN THÀNH (không phân biệt user)
 * Nếu user A hoàn thành 2 phiên, user B hoàn thành 1 phiên => tổng = 3 phiên
 *
 * @param DateTimeImmutable $start_date
 * @param DateTimeImmutable $end_date
 * @param string $prefix
 * @return array { 'summary': array, 'chart': array }
 */
function game_bsc_get_completed_stats($start_date, $end_date, $prefix) {
	global $wpdb;
	
	$start_str = $start_date->format('Y-m-d H:i:s');
	$end_str = $end_date->format('Y-m-d H:i:s');
	
	// ✅ QUERY: Đếm TỔNG SỐ PHIÊN hoàn thành (questions_count = correct_count) theo provider
	$completed_by_provider = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
                u.provider,
                COUNT(ups.id) as completed_sessions
             FROM {$prefix}users_play_sessions ups
             INNER JOIN {$prefix}users u ON ups.user_id = u.id
             WHERE ups.started_at BETWEEN %s AND %s
             AND ups.questions_count = ups.correct_count
             AND ups.questions_count > 0
             GROUP BY u.provider",
			$start_str,
			$end_str
		),
		ARRAY_A
	);
	
	$mtrader_app_completed = 0;
	$bsc_smart_invest_completed = 0;
	$webtrading_completed = 0;
	$bsc_web_completed = 0;
	
	foreach ($completed_by_provider as $row) {
		$provider = strtolower($row['provider']);
		$count = (int)$row['completed_sessions'];
		
		if (strpos($provider, MTRADER_APP) !== false || strpos($provider, 'mtrader') !== false) {
			$mtrader_app_completed = $count;
		} elseif (strpos($provider, BSC_SMART_INVEST) !== false || strpos($provider, 'bsc_smart') !== false) {
			$bsc_smart_invest_completed = $count;
		} elseif (strpos($provider, WEBTRADING) !== false || strpos($provider, 'webtrading') !== false) {
			$webtrading_completed = $count;
		} else {
			$bsc_web_completed = $count; // Default to BSC_WEB
		}
	}
	
	// ✅ Tổng số PHIÊN hoàn thành (không phân biệt user)
	$total_completed = $mtrader_app_completed + $bsc_smart_invest_completed + $webtrading_completed + $bsc_web_completed;
	
	// Lấy tổng participation (tổng số PHIÊN được chơi)
	$total_sessions = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(id)
             FROM {$prefix}users_play_sessions
             WHERE started_at BETWEEN %s AND %s",
			$start_str,
			$end_str
		)
	);
	$total_sessions = (int)$total_sessions;
	
	$completed_percent = $total_sessions > 0 ? ($total_completed / $total_sessions) * 100 : 0;
	
	$mtrader_app_percent = $total_completed > 0 ? ($mtrader_app_completed / $total_completed) * 100 : 0;
	$bsc_smart_invest_percent = $total_completed > 0 ? ($bsc_smart_invest_completed / $total_completed) * 100 : 0;
	$webtrading_percent = $total_completed > 0 ? ($webtrading_completed / $total_completed) * 100 : 0;
	$bsc_web_percent = $total_completed > 0 ? ($bsc_web_completed / $total_completed) * 100 : 0;
	
	return [
		'summary' => [
			'total' => $total_completed,
			MTRADER_APP => $mtrader_app_completed,
			BSC_SMART_INVEST => $bsc_smart_invest_completed,
			WEBTRADING => $webtrading_completed,
			BSC_WEB => $bsc_web_completed,
			'percentage' => round($completed_percent, 1),
			'breakdown' => [
				['name' => game_bsc_get_provider_name(MTRADER_APP), 'value' => $mtrader_app_completed, 'percentage' => round($mtrader_app_percent, 1), 'key' => MTRADER_APP],
				['name' => game_bsc_get_provider_name(BSC_SMART_INVEST), 'value' => $bsc_smart_invest_completed, 'percentage' => round($bsc_smart_invest_percent, 1), 'key' => BSC_SMART_INVEST],
				['name' => game_bsc_get_provider_name(WEBTRADING), 'value' => $webtrading_completed, 'percentage' => round($webtrading_percent, 1), 'key' => WEBTRADING],
				['name' => game_bsc_get_provider_name(BSC_WEB), 'value' => $bsc_web_completed, 'percentage' => round($bsc_web_percent, 1), 'key' => BSC_WEB],
			]
		],
		'chart' => [
			['name' => game_bsc_get_provider_name(MTRADER_APP), 'y' => round($mtrader_app_percent, 1)],
			['name' => game_bsc_get_provider_name(BSC_SMART_INVEST), 'y' => round($bsc_smart_invest_percent, 1)],
			['name' => game_bsc_get_provider_name(WEBTRADING), 'y' => round($webtrading_percent, 1)],
			['name' => game_bsc_get_provider_name(BSC_WEB), 'y' => round($bsc_web_percent, 1)],
		]
	];
}

/**
 * Get provider color
 *
 * @param string $provider_key
 * @return string Hex color code
 */
function get_provider_color($provider_key) {
	$colors = [
		MTRADER_APP => '#00D35E',
		BSC_SMART_INVEST => '#5CCBFE',
		WEBTRADING => '#F16457',
		BSC_WEB => '#FF6B35'
	];
	return isset($colors[$provider_key]) ? $colors[$provider_key] : '#000';
}

?>