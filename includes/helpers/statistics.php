<?php

if (!defined('ABSPATH')) exit;

function game_bsc_parse_stats_filter_date($raw_date, DateTimeZone $tz)
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

function game_bsc_resolve_statistics_ranges($range, DateTimeZone $tz, $from_date = '', $to_date = '')
{
	$today = new DateTimeImmutable('now', $tz);

	if ($range === 'last_12_months') {
		$custom_start = $today->modify('-12 months')->setTime(0, 0, 0);
		$custom_end = $today->setTime(23, 59, 59);
		$duration_days = ((int)$custom_start->diff($custom_end)->days) + 1;
		$compare_end = $custom_start->modify('-1 day')->setTime(23, 59, 59);
		$compare_start = $compare_end->modify('-' . ($duration_days - 1) . ' days')->setTime(0, 0, 0);

		return [
			'period_start' => $custom_start,
			'period_end' => $custom_end,
			'compare_start' => $compare_start,
			'compare_end' => $compare_end,
			'applied_range' => 'custom',
			'from_label' => $custom_start->format('d/m/Y'),
			'to_label' => $custom_end->format('d/m/Y'),
		];
	}

	if ($range === 'custom') {
		$start_obj = game_bsc_parse_stats_filter_date($from_date, $tz);
		$end_obj = game_bsc_parse_stats_filter_date($to_date, $tz);
		if (!$start_obj || !$end_obj) {
			return new WP_Error('invalid_date_range', 'Định dạng ngày không hợp lệ, yêu cầu dd/mm/yyyy.');
		}

		if ($start_obj > $end_obj) {
			$tmp = $start_obj;
			$start_obj = $end_obj;
			$end_obj = $tmp;
		}

		if ($end_obj > $today) {
			$end_obj = $today;
		}

		$period_start = $start_obj->setTime(0, 0, 0);
		$period_end = $end_obj->setTime(23, 59, 59);
		$duration_days = ((int)$period_start->diff($period_end)->days) + 1;
		$compare_end = $period_start->modify('-1 day')->setTime(23, 59, 59);
		$compare_start = $compare_end->modify('-' . ($duration_days - 1) . ' days')->setTime(0, 0, 0);

		return [
			'period_start' => $period_start,
			'period_end' => $period_end,
			'compare_start' => $compare_start,
			'compare_end' => $compare_end,
			'applied_range' => 'custom',
			'from_label' => $period_start->format('d/m/Y'),
			'to_label' => $period_end->format('d/m/Y'),
		];
	}

	switch ($range) {
		case 'today':
			$period_start = $today->setTime(0, 0, 0);
			$period_end = $today->setTime(23, 59, 59);
			$compare_start = $today->modify('-1 day')->setTime(0, 0, 0);
			$compare_end = $today->modify('-1 day')->setTime(23, 59, 59);
			break;
		case 'week':
			$period_start = $today->modify('monday this week')->setTime(0, 0, 0);
			$period_end = $today->modify('sunday this week')->setTime(23, 59, 59);
			$compare_start = $period_start->modify('-7 days')->setTime(0, 0, 0);
			$compare_end = $period_end->modify('-7 days')->setTime(23, 59, 59);
			break;
		case 'month':
		default:
			$period_start = $today->modify('first day of this month')->setTime(0, 0, 0);
			$period_end = $today->modify('last day of this month')->setTime(23, 59, 59);
			$compare_start = $period_start->modify('-1 month')->setTime(0, 0, 0);
			$compare_end = $period_end->modify('-1 month')->setTime(23, 59, 59);
			break;
	}

	if ($period_end > $today->setTime(23, 59, 59)) {
		$period_end = $today->setTime(23, 59, 59);
	}

	return [
		'period_start' => $period_start,
		'period_end' => $period_end,
		'compare_start' => $compare_start,
		'compare_end' => $compare_end,
		'applied_range' => $range,
		'from_label' => $period_start->format('d/m/Y'),
		'to_label' => $period_end->format('d/m/Y'),
	];
}

/**
 * Tổng kho voucher cho dashboard:
 * - Voucher THIRD_PARTY luôn tính 1 mỗi voucher
 * - Các loại khác dùng field quantity
 */
function game_bsc_get_total_vouchers_stock_for_dashboard()
{
	global $wpdb;

	$total = (int)$wpdb->get_var(
		"
		SELECT SUM(
			CASE
				WHEN UPPER(COALESCE(vt.meta_value, '')) = 'THIRD_PARTY' THEN 1
				ELSE CAST(COALESCE(NULLIF(q.meta_value, ''), '0') AS UNSIGNED)
			END
		)
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} vt
			ON vt.post_id = p.ID
			AND vt.meta_key = 'voucher_type'
		LEFT JOIN {$wpdb->postmeta} q
			ON q.post_id = p.ID
			AND q.meta_key = 'quantity'
		WHERE p.post_type = 'game_vouchers'
			AND p.post_status = 'publish'
		"
	);

	return max(0, $total);
}

function game_bsc_get_daily_statistics($date = null)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	$tz = TIMEZONE;
	
	try {
		// Xác định ngày cần thống kê
		if (empty($date)) {
			$current_date = new DateTimeImmutable('now', $tz);
		} else {
			$current_date = new DateTimeImmutable($date, $tz);
		}
		
		// Ngày hôm qua
		$previous_date = $current_date->modify('-1 day');
		
		$current_date_str = $current_date->format('Y-m-d');
		$previous_date_str = $previous_date->format('Y-m-d');
		
		// Lấy thống kê 2 ngày
		$today_stats = game_bsc_get_daily_stats_internal($current_date_str);
		$yesterday_stats = game_bsc_get_daily_stats_internal($previous_date_str);
		
		$yesterday_rewards_count = (int)explode('/', $yesterday_stats['rewards_distributed'])[0];
		$today_rewards_count = (int)explode('/', $today_stats['rewards_distributed'])[0];
		
		return [
			'date' => $current_date_str,
			'previous_date' => $previous_date_str,
			'today' => $today_stats,
			'changes' => [
				'players' => game_bsc_calc_percent_change($yesterday_stats['players'], $today_stats['players']),
				'sessions' => game_bsc_calc_percent_change($yesterday_stats['sessions'], $today_stats['sessions']),
				'completion_rate' => game_bsc_calc_percent_change($yesterday_stats['completion_rate'], $today_stats['completion_rate']),
				'rewards_distributed' => game_bsc_calc_percent_change($yesterday_rewards_count, $today_rewards_count),
			]
		];
		
	} catch (Throwable $e) {
		error_log('game_bsc_get_daily_statistics error: ' . $e->getMessage());
		return [
			'error' => $e->getMessage(),
			'date' => $date,
			'today' => game_bsc_empty_stats(),
			'changes' => [
				'players' => 0,
				'sessions' => 0,
				'completion_rate' => 0,
				'rewards_distributed' => 0,
			]
		];
	}
}

/**
 * Lấy thống kê cho 1 ngày cụ thể
 * ✅ FIX: Tính tỷ lệ hoàn thành = Số phiên hoàn thành / Tổng số phiên × 100
 * (Không tính theo user, mà tính theo phiên chơi)
 *
 * @param string $date Y-m-d
 * @return array
 */
function game_bsc_get_daily_stats_internal($date)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	$tz = TIMEZONE;
	
	try {
		$date_obj = new DateTimeImmutable($date, $tz);
		$dow = (int)$date_obj->format('N'); // 1=Mon, 7=Sun
		
		// ✅ Bỏ qua T7 (6), CN (7)
		if ($dow >= 6) {
			return game_bsc_empty_stats();
		}
		
		$from = $date_obj->setTime(0, 0, 0)->format('Y-m-d H:i:s');
		$to = $date_obj->setTime(23, 59, 59)->format('Y-m-d H:i:s');
		
		// ===== 1. TỔNG SỐ NGƯỜI CHƠI TRUY CẬP =====
		// ✅ Lấy từ user_login_logs (mỗi user/ngày = 1)
		$players = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id)
                 FROM {$prefix}user_login_logs
                 WHERE DATE(checked_at) = %s AND result = 'OK' AND user_id IS NOT NULL",
				$date
			)
		);
		
		// ===== 2. TỔNG SỐ LƯỢT THAM GIA CHƠI =====
		$sessions = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id)
                 FROM {$prefix}users_play_sessions
                 WHERE started_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		
		// ===== 3. TỶ LỆ HOÀN THÀNH (%) =====
		// ✅ FIX: Công thức mới = Số phiên hoàn thành / Tổng số phiên × 100
		$completion_rate = 0;
		
		if ($sessions > 0) {
			// Đếm số phiên HOÀN THÀNH (questions_count = correct_count)
			$completed_sessions = (int)$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id)
                     FROM {$prefix}users_play_sessions
                     WHERE started_at BETWEEN %s AND %s
                     AND questions_count = correct_count
                     AND questions_count > 0",
					$from,
					$to
				)
			);
			
			// Tính tỷ lệ = (phiên hoàn thành / tổng phiên) × 100
			$completion_rate = (int)round(($completed_sessions / $sessions) * 100);
		}
		
		// ===== 4. GIẢI THƯỞNG ĐÃ TRAO (X/Y format) =====
		$vouchers_redeemed = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id)
                 FROM {$prefix}user_voucher_redemptions
                 WHERE redeemed_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		
		$artifacts_redeemed = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id)
                 FROM {$prefix}user_artifact_redemptions
                 WHERE redeemed_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		
		$total_rewards_distributed = $vouchers_redeemed + $artifacts_redeemed;
		
		$total_vouchers_stock = game_bsc_get_total_vouchers_stock_for_dashboard();
		
		$total_artifacts_stock = (int)$wpdb->get_var(
			"SELECT COUNT(id)
             FROM {$prefix}artifacts
             WHERE status = 1"
		);
		
		$total_possible_rewards = $total_vouchers_stock + $total_artifacts_stock;
		$rewards_distributed = $total_rewards_distributed . '/' . $total_possible_rewards;
		
		return [
			'players' => $players,
			'sessions' => $sessions,
			'completion_rate' => $completion_rate,
			'rewards_distributed' => $rewards_distributed,
		];
		
	} catch (Throwable $e) {
		error_log('game_bsc_get_daily_stats_internal error: ' . $e->getMessage());
		return game_bsc_empty_stats();
	}
}

/**
 * Áp dụng logic tương tự cho khoảng thời gian
 */
function game_bsc_get_stats_for_period($start_date, $end_date)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	$tz = TIMEZONE;
	
	try {
		$start_obj = new DateTimeImmutable($start_date, $tz);
		$end_obj = new DateTimeImmutable($end_date, $tz);
		
		$from = $start_obj->setTime(0, 0, 0)->format('Y-m-d H:i:s');
		$to = $end_obj->setTime(23, 59, 59)->format('Y-m-d H:i:s');
		
		// ===== 1. TỔNG SỐ NGƯỜI CHƠI =====
		$players = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id)
                 FROM {$prefix}user_login_logs
                 WHERE checked_at BETWEEN %s AND %s AND result = 'OK' AND user_id IS NOT NULL",
				$from,
				$to
			)
		);
		
		// ===== 2. TỔNG SỐ LƯỢT CHƠI =====
		$sessions = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id)
                 FROM {$prefix}users_play_sessions
                 WHERE started_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		
		// ===== 3. TỶ LỆ HOÀN THÀNH (%) =====
		// ✅ FIX: Công thức mới = Số phiên hoàn thành / Tổng số phiên × 100
		$completion_rate = 0;
		
		if ($sessions > 0) {
			// Đếm số phiên hoàn thành
			$completed_sessions = (int)$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id)
                     FROM {$prefix}users_play_sessions
                     WHERE started_at BETWEEN %s AND %s
                     AND questions_count = correct_count
                     AND questions_count > 0",
					$from,
					$to
				)
			);
			
			$completion_rate = (int)round(($completed_sessions / $sessions) * 100);
		}
		
		// ===== 4. GIẢI THƯỞNG ĐÃ TRAO =====
		$vouchers_redeemed = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id)
                 FROM {$prefix}user_voucher_redemptions
                 WHERE redeemed_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		
		$artifacts_redeemed = (int)$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id)
                 FROM {$prefix}user_artifact_redemptions
                 WHERE redeemed_at BETWEEN %s AND %s",
				$from,
				$to
			)
		);
		
		$total_rewards_distributed = $vouchers_redeemed + $artifacts_redeemed;
		
		$total_vouchers_stock = game_bsc_get_total_vouchers_stock_for_dashboard();
		
		$total_artifacts_stock = (int)$wpdb->get_var(
			"
    SELECT SUM(max_redemptions)
    FROM {$prefix}artifacts
    WHERE status = 1
    "
		);
		
		$total_possible_rewards = $total_vouchers_stock + $total_artifacts_stock;
		$rewards_distributed = $total_rewards_distributed . '/' . $total_possible_rewards;
		
		return [
			'players' => $players,
			'sessions' => $sessions,
			'completion_rate' => $completion_rate,
			'rewards_distributed' => $rewards_distributed,
		];
		
	} catch (Throwable $e) {
		error_log('game_bsc_get_stats_for_period error: ' . $e->getMessage());
		return [
			'players' => 0,
			'sessions' => 0,
			'completion_rate' => 0,
			'rewards_distributed' => '0/0',
		];
	}
}

/**
 * Tính phần trăm thay đổi giữa 2 giá trị
 * ✅ Không có số thập phân, chỉ lấy số nguyên
 *
 * @param float $old_value Giá trị cũ
 * @param float $new_value Giá trị mới
 * @return int Phần trăm thay đổi (số nguyên)
 */
function game_bsc_calc_percent_change($old_value, $new_value)
{
	$old_value = (float)$old_value;
	$new_value = (float)$new_value;
	
	if ($old_value == 0) {
		return $new_value == 0 ? 0 : 100;
	}
	
	// ✅ FIX: Chuyển thành int, không có số thập phân
	return (int)round((($new_value - $old_value) / $old_value) * 100);
}

/**
 * Trả về stats rỗng
 */
function game_bsc_empty_stats()
{
	return [
		'players' => 0,
		'sessions' => 0,
		'completion_rate' => 0,
		'rewards_distributed' => '0/0',
	];
}


add_action('wp_ajax_nopriv_game_bsc_get_statistics', 'game_bsc_ajax_get_statistics');
add_action('wp_ajax_game_bsc_get_statistics', 'game_bsc_ajax_get_statistics');

function game_bsc_ajax_get_statistics()
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	$tz = TIMEZONE;
	
	try {
		$range = sanitize_text_field($_POST['range'] ?? 'today');
		$from_date = sanitize_text_field($_POST['from_date'] ?? '');
		$to_date = sanitize_text_field($_POST['to_date'] ?? '');
		
		// Validate range
		if (!in_array($range, ['today', 'week', 'month', 'custom', 'last_12_months'], true)) {
			wp_send_json_error(['message' => 'Invalid range parameter']);
		}

		$ranges = game_bsc_resolve_statistics_ranges($range, $tz, $from_date, $to_date);
		if (is_wp_error($ranges)) {
			wp_send_json_error(['message' => $ranges->get_error_message()]);
		}

		$period_start = $ranges['period_start']->format('Y-m-d');
		$period_end = $ranges['period_end']->format('Y-m-d');
		$compare_start = $ranges['compare_start']->format('Y-m-d');
		$compare_end = $ranges['compare_end']->format('Y-m-d');
		
		// ===== LẤY THỐNG KÊ =====
		$current_stats = game_bsc_get_stats_for_period($period_start, $period_end);
		$previous_stats = game_bsc_get_stats_for_period($compare_start, $compare_end);
		
		// ===== TÍNH PHẦN TRĂM THAY ĐỔI =====
		$changes = [
			'players' => game_bsc_calc_percent_change($previous_stats['players'], $current_stats['players']),
			'sessions' => game_bsc_calc_percent_change($previous_stats['sessions'], $current_stats['sessions']),
			'completion_rate' => game_bsc_calc_percent_change($previous_stats['completion_rate'], $current_stats['completion_rate']),
			'rewards_distributed' => game_bsc_calc_percent_change(
				(int)explode('/', $previous_stats['rewards_distributed'])[0],
				(int)explode('/', $current_stats['rewards_distributed'])[0]
			),
		];
		
		$response = [
			'range' => $ranges['applied_range'],
			'period' => [
				'start' => $period_start,
				'end' => $period_end,
				'start_label' => $ranges['from_label'],
				'end_label' => $ranges['to_label'],
			],
			'applied_filter' => [
				'from_date' => $ranges['from_label'],
				'to_date' => $ranges['to_label'],
			],
			'today' => $current_stats,
			'changes' => $changes,
		];
		
		wp_send_json_success($response);
		
	} catch (Throwable $e) {
		error_log('game_bsc_ajax_get_statistics error: ' . $e->getMessage());
		wp_send_json_error(['message' => 'Internal server error']);
	}
}