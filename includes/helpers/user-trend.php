<?php
if (!defined('ABSPATH')) exit;

/**
 * Parse ngay theo 2 format ho tro: dd/mm/yyyy va Y-m-d.
 */
function game_bsc_parse_trend_date($raw_date, DateTimeZone $tz)
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
 * Xac dinh khoang thoi gian theo period hoac theo from/to.
 */
function game_bsc_resolve_trend_range($period, $date, $from_date, $to_date, DateTimeZone $tz)
{
	$today = new DateTimeImmutable('now', $tz);
	$period = in_array($period, ['day', 'week', 'month', 'custom'], true) ? $period : 'week';

	$from_obj = game_bsc_parse_trend_date($from_date, $tz);
	$to_obj = game_bsc_parse_trend_date($to_date, $tz);

	if ($period === 'custom' || $from_obj || $to_obj) {
		if (!$from_obj && $to_obj) {
			$from_obj = $to_obj;
		}
		if (!$to_obj && $from_obj) {
			$to_obj = $from_obj;
		}
		if (!$from_obj && !$to_obj) {
			$to_obj = $today;
			$from_obj = $today->modify('-12 months');
		}

		if ($from_obj > $to_obj) {
			$temp = $from_obj;
			$from_obj = $to_obj;
			$to_obj = $temp;
		}

		if ($to_obj > $today) {
			$to_obj = $today;
		}

		return [
			'start' => $from_obj,
			'end' => $to_obj,
			'period' => 'custom',
			'reference_date' => $today,
		];
	}

	$date_obj = game_bsc_parse_trend_date($date, $tz);
	if (!$date_obj) {
		$date_obj = $today;
	}

	switch ($period) {
		case 'day':
			$start = $date_obj;
			$end = $date_obj;
			break;
		case 'month':
			$start = $date_obj->modify('first day of this month');
			$end = $date_obj->modify('last day of this month');
			break;
		case 'week':
		default:
			$start = $date_obj->modify('monday this week');
			$end = $date_obj->modify('sunday this week');
			break;
	}

	if ($end > $today) {
		$end = $today;
	}

	return [
		'start' => $start,
		'end' => $end,
		'period' => $period,
		'reference_date' => $date_obj,
	];
}

/**
 * Quy tac hien thi bieu do theo khoang ngay.
 * - <31 ngay: theo ngay
 * - >1 thang den <12 thang: theo tuan (ngay cuoi tuan)
 * - Con lai: theo thang de tranh qua nhieu cot
 */
function game_bsc_resolve_trend_point_mode(DateTimeImmutable $start, DateTimeImmutable $end)
{
	$total_days = ((int)$start->diff($end)->days) + 1;
	if ($total_days <= 31) {
		return 'day';
	}
	if ($total_days < 365) {
		return 'week';
	}
	return 'month';
}

/**
 * Gom du lieu theo tuan voi moc hien thi la ngay cuoi tuan.
 * Gia tri tra ve la trung binh/ngay trong tuan do.
 */
function game_bsc_group_trends_by_week($daily_points, DateTimeImmutable $start, DateTimeImmutable $end, DateTimeZone $tz)
{
	$weekly = [];
	foreach ($daily_points as $point) {
		$current = new DateTimeImmutable($point['date_iso'], $tz);
		$week_end = $current->modify('sunday this week');
		if ($week_end > $end) {
			$week_end = $end;
		}
		$key = $week_end->format('Y-m-d');

		if (!isset($weekly[$key])) {
			$week_start = $week_end->modify('-6 days');
			$bucket_start = $week_start < $start ? $start : $week_start;
			$bucket_end = $week_end > $end ? $end : $week_end;
			$day_count = (int)$bucket_start->diff($bucket_end)->format('%a') + 1;

			$weekly[$key] = [
				'date' => $week_end->format('d/m/Y'),
				'date_iso' => $key,
				'visits' => 0,
				'sessions' => 0,
				'participants' => 0,
				'day_count' => max(1, $day_count),
			];
		}

		$weekly[$key]['visits'] += (int)$point['visits'];
		$weekly[$key]['sessions'] += (int)$point['sessions'];
		$weekly[$key]['participants'] += (int)$point['participants'];
	}

	foreach ($weekly as $key => $bucket) {
		$days = max(1, (int)$bucket['day_count']);
		$weekly[$key]['visits'] = (int)round($bucket['visits'] / $days);
		$weekly[$key]['sessions'] = (int)round($bucket['sessions'] / $days);
		$weekly[$key]['participants'] = (int)round($bucket['participants'] / $days);
		unset($weekly[$key]['day_count']);
	}

	ksort($weekly);
	return array_values($weekly);
}

/**
 * Gom du lieu theo thang (fallback cho khoang >= 12 thang).
 */
function game_bsc_group_trends_by_month($daily_points)
{
	$monthly = [];
	foreach ($daily_points as $point) {
		$current = new DateTimeImmutable($point['date_iso']);
		$key = $current->format('Y-m');
		if (!isset($monthly[$key])) {
			$monthly[$key] = [
				'date' => $current->format('m/Y'),
				'date_iso' => $key . '-01',
				'visits' => 0,
				'sessions' => 0,
				'participants' => 0,
			];
		}

		$monthly[$key]['visits'] += (int)$point['visits'];
		$monthly[$key]['sessions'] += (int)$point['sessions'];
		$monthly[$key]['participants'] += (int)$point['participants'];
	}

	ksort($monthly);
	return array_values($monthly);
}

/**
 * Lấy số lượng lượt truy cập, lượt tham gia và người tham gia theo thời gian.
 */
function game_bsc_get_player_trends($period = 'week', $date = null, $from_date = '', $to_date = '') {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		$tz = TIMEZONE;
		$range = game_bsc_resolve_trend_range($period, $date, $from_date, $to_date, $tz);
		$start = $range['start']->setTime(0, 0, 0);
		$end = $range['end']->setTime(23, 59, 59);

		$start_date = $start->format('Y-m-d');
		$end_date = $end->format('Y-m-d');
		$ref_date = $range['reference_date']->format('Y-m-d');

		$visits_by_day_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(checked_at) AS stat_date, COUNT(id) AS total
				 FROM {$prefix}user_login_logs
				 WHERE checked_at BETWEEN %s AND %s
				 AND result = 'OK'
				 GROUP BY DATE(checked_at)",
				$start->format('Y-m-d H:i:s'),
				$end->format('Y-m-d H:i:s')
			),
			ARRAY_A
		);

		$sessions_by_day_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(started_at) AS stat_date, COUNT(id) AS total
				 FROM {$prefix}users_play_sessions
				 WHERE started_at BETWEEN %s AND %s
				 GROUP BY DATE(started_at)",
				$start->format('Y-m-d H:i:s'),
				$end->format('Y-m-d H:i:s')
			),
			ARRAY_A
		);

		$participants_by_day_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(started_at) AS stat_date, COUNT(DISTINCT user_id) AS total
				 FROM {$prefix}users_play_sessions
				 WHERE started_at BETWEEN %s AND %s
				 GROUP BY DATE(started_at)",
				$start->format('Y-m-d H:i:s'),
				$end->format('Y-m-d H:i:s')
			),
			ARRAY_A
		);

		$visits_map = [];
		foreach ($visits_by_day_rows as $row) {
			$visits_map[$row['stat_date']] = (int)$row['total'];
		}

		$sessions_map = [];
		foreach ($sessions_by_day_rows as $row) {
			$sessions_map[$row['stat_date']] = (int)$row['total'];
		}

		$participants_map = [];
		foreach ($participants_by_day_rows as $row) {
			$participants_map[$row['stat_date']] = (int)$row['total'];
		}

		$daily_points = [];
		$current = $start;
		$visits_count = 0;
		$sessions_count = 0;
		$participants_count = 0;
		while ($current <= $end) {
			$date_key = $current->format('Y-m-d');
			$visits = $visits_map[$date_key] ?? 0;
			$sessions = $sessions_map[$date_key] ?? 0;
			$participants = $participants_map[$date_key] ?? 0;

			$daily_points[] = [
				'date' => $current->format('d/m/Y'),
				'date_iso' => $date_key,
				'visits' => $visits,
				'sessions' => $sessions,
				'participants' => $participants,
			];

			$visits_count += $visits;
			$sessions_count += $sessions;
			$participants_count += $participants;
			$current = $current->modify('+1 day');
		}

		$point_mode = game_bsc_resolve_trend_point_mode($start, $end);
		if ($point_mode === 'week') {
			$data_points = game_bsc_group_trends_by_week($daily_points, $start, $end, $tz);
		} elseif ($point_mode === 'month') {
			$data_points = game_bsc_group_trends_by_month($daily_points);
		} else {
			$data_points = $daily_points;
		}

		$response = [
			'period' => $range['period'],
			'point_mode' => $point_mode,
			'reference_date' => $ref_date,
			'date_range' => [
				'start' => $start_date,
				'end' => $end_date,
				'start_label' => $start->format('d/m/Y'),
				'end_label' => $end->format('d/m/Y'),
			],
			'applied_filter' => [
				'from_date' => $start->format('d/m/Y'),
				'to_date' => $end->format('d/m/Y'),
			],
			'summary' => [
				'visits' => (int)$visits_count,
				'sessions' => (int)$sessions_count,
				'participants' => (int)$participants_count,
			],
			'data_points' => $data_points,
			'generated_at' => current_time('Y-m-d H:i:s'),
		];
		
		return $response;
		
	} catch (Throwable $e) {
		return [
			'error' => $e->getMessage(),
			'period' => $period,
			'reference_date' => $ref_date ?? null,
			'summary' => [
				'visits' => 0,
				'sessions' => 0,
				'participants' => 0,
			],
			'data_points' => [],
		];
	}
}

/**
 * wp_ajax handler: Lấy dữ liệu xu hướng người chơi
 * Endpoint: admin-ajax.php?action=game_bsc_get_player_trends
 */
add_action('wp_ajax_game_bsc_get_player_trends', function() {
	$period = sanitize_text_field($_POST['period'] ?? 'week');
	$date = sanitize_text_field($_POST['date'] ?? '');
	$from_date = sanitize_text_field($_POST['from_date'] ?? '');
	$to_date = sanitize_text_field($_POST['to_date'] ?? '');
	
	$data = game_bsc_get_player_trends($period, $date ?: null, $from_date, $to_date);
	
	if (isset($data['error'])) {
		wp_send_json_error($data);
	} else {
		wp_send_json_success($data);
	}
});

// Cho phép access khi chưa đăng nhập
add_action('wp_ajax_nopriv_game_bsc_get_player_trends', function() {
	do_action('wp_ajax_game_bsc_get_player_trends');
});

?>