<?php
if (!defined('ABSPATH')) exit;

function game_bsc_parse_award_filter_date($raw_date, DateTimeZone $tz)
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

function game_bsc_resolve_award_date_range($period, $date, $from_date, $to_date, DateTimeZone $tz)
{
	$today = new DateTimeImmutable('now', $tz);

	if ($period === 'last_12_months') {
		$start = $today->modify('-12 months');
		$end = $today;
		return ['period' => 'custom', 'start' => $start, 'end' => $end, 'reference' => $today];
	}

	if ($period === 'custom') {
		$start_obj = game_bsc_parse_award_filter_date($from_date, $tz);
		$end_obj = game_bsc_parse_award_filter_date($to_date, $tz);
		if (!$start_obj || !$end_obj) {
			return null;
		}
		if ($start_obj > $end_obj) {
			$tmp = $start_obj;
			$start_obj = $end_obj;
			$end_obj = $tmp;
		}
		if ($end_obj > $today) {
			$end_obj = $today;
		}
		return ['period' => 'custom', 'start' => $start_obj, 'end' => $end_obj, 'reference' => $today];
	}

	$date_obj = $date ? new DateTimeImmutable($date, $tz) : $today;
	$resolved_period = in_array($period, ['day', 'week', 'month'], true) ? $period : 'week';

	switch ($resolved_period) {
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
			$end = $date_obj->modify('friday this week');
			break;
	}

	if ($end > $today) {
		$end = $today;
	}

	return ['period' => $resolved_period, 'start' => $start, 'end' => $end, 'reference' => $date_obj];
}

/**
 * Nhóm data_points giải thưởng theo tuần (ngày cuối tuần làm mốc).
 * - awarded: tổng số giải trao trong tuần
 * - not_awarded: giá trị ngày cuối cùng trong tuần (vì là giá trị tồn kho tích lũy)
 */
function game_bsc_group_award_by_week($daily_points, DateTimeImmutable $start, DateTimeImmutable $end, DateTimeZone $tz)
{
	$weekly = [];
	foreach ($daily_points as $point) {
		$current = new DateTimeImmutable($point['date'], $tz);
		$week_end = $current->modify('sunday this week');
		if ($week_end > $end) {
			$week_end = $end;
		}
		$key = $week_end->format('Y-m-d');

		if (!isset($weekly[$key])) {
			$weekly[$key] = [
				'date' => $week_end->format('Y-m-d'),
				'awarded' => 0,
				'not_awarded' => 0,
				'_last_date' => $point['date'],
			];
		}

		$weekly[$key]['awarded'] += (int)$point['awarded'];
		// not_awarded là giá trị tồn kho → lấy ngày gần nhất (cuối tuần)
		if ($point['date'] >= $weekly[$key]['_last_date']) {
			$weekly[$key]['not_awarded'] = (int)$point['not_awarded'];
			$weekly[$key]['_last_date'] = $point['date'];
		}
	}

	foreach ($weekly as $key => &$bucket) {
		unset($bucket['_last_date']);
	}
	unset($bucket);

	ksort($weekly);
	return array_values($weekly);
}

function game_bsc_award_format_day_counts_by_object($rows, $id_key)
{
	$map = [];
	if (!is_array($rows)) {
		return $map;
	}

	foreach ($rows as $row) {
		$object_id = (int)($row[$id_key] ?? 0);
		$day = (string)($row['day'] ?? '');
		$count = (int)($row['total_count'] ?? 0);
		if ($object_id < 1 || $day === '' || $count < 1) {
			continue;
		}

		if (!isset($map[$day])) {
			$map[$day] = [];
		}
		$map[$day][$object_id] = $count;
	}

	return $map;
}

function game_bsc_award_format_total_counts_by_object($rows, $id_key)
{
	$map = [];
	if (!is_array($rows)) {
		return $map;
	}

	foreach ($rows as $row) {
		$object_id = (int)($row[$id_key] ?? 0);
		if ($object_id < 1) {
			continue;
		}
		$map[$object_id] = (int)($row['total_count'] ?? 0);
	}

	return $map;
}

function game_bsc_award_sum_day_counts($rows)
{
	$map = [];
	if (!is_array($rows)) {
		return $map;
	}

	foreach ($rows as $row) {
		$day = (string)($row['day'] ?? '');
		if ($day === '') {
			continue;
		}
		if (!isset($map[$day])) {
			$map[$day] = 0;
		}
		$map[$day] += (int)($row['total_count'] ?? 0);
	}

	return $map;
}

/**
 * ✅ FIX:
 * 1. Loại trừ thứ 7 và chủ nhật
 * 2. Tính "Giải chưa nhận" = Tổng voucher/artifact vào ngày đó - Tổng đã trao cho đến hết ngày đó
 */
function game_bsc_get_award_status($period = 'week', $date = null, $from_date = '', $to_date = '') {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		$tz = TIMEZONE;
		$resolved = game_bsc_resolve_award_date_range($period, $date, $from_date, $to_date, $tz);
		if (!$resolved) {
			return [
				'error' => 'Định dạng ngày không hợp lệ, yêu cầu dd/mm/yyyy.',
				'period' => 'custom',
				'summary' => ['awarded' => 0, 'not_awarded' => 0, 'total' => 0],
				'by_category' => [],
				'data_points' => [],
			];
		}

		$period = $resolved['period'];
		$ref_date = $resolved['reference']->format('Y-m-d');
		$start_date = $resolved['start']->format('Y-m-d');
		$end_date = $resolved['end']->format('Y-m-d');

		// ===== 1) INVENTORY VOUCHER + ARTIFACT =====
		$voucher_rows = $wpdb->get_results(
			"
			SELECT
				p.ID AS voucher_id,
				UPPER(COALESCE(vt.meta_value, '')) AS voucher_type,
				CASE
					WHEN UPPER(COALESCE(vt.meta_value, '')) = 'THIRD_PARTY' THEN 1
					ELSE CAST(COALESCE(NULLIF(q.meta_value, ''), '0') AS UNSIGNED)
				END AS capacity
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} vt
				ON vt.post_id = p.ID AND vt.meta_key = 'voucher_type'
			LEFT JOIN {$wpdb->postmeta} q
				ON q.post_id = p.ID AND q.meta_key = 'quantity'
			WHERE p.post_type = 'game_vouchers'
				AND p.post_status = 'publish'
			",
			ARRAY_A
		);

		$artifact_rows = $wpdb->get_results(
			"SELECT a.id AS artifact_id, a.max_redemptions AS capacity
			 FROM {$prefix}artifacts a
			 WHERE a.status = 1",
			ARRAY_A
		);

		$voucher_inventory = [];
		$total_voucher_capacity = 0;
		foreach ($voucher_rows as $row) {
			$voucher_id = (int)($row['voucher_id'] ?? 0);
			if ($voucher_id < 1) {
				continue;
			}

			$voucher_type = (string)($row['voucher_type'] ?? '');
			if ($voucher_type !== 'BSC' && $voucher_type !== 'THIRD_PARTY') {
				$voucher_type = 'BSC';
			}

			$capacity = max(0, (int)($row['capacity'] ?? 0));
			$voucher_inventory[$voucher_id] = [
				'type' => $voucher_type,
				'capacity' => $capacity,
			];
			$total_voucher_capacity += $capacity;
		}

		$artifact_inventory = [];
		$total_artifact_capacity = 0;
		foreach ($artifact_rows as $row) {
			$artifact_id = (int)($row['artifact_id'] ?? 0);
			if ($artifact_id < 1) {
				continue;
			}
			$capacity = max(0, (int)($row['capacity'] ?? 0));
			$artifact_inventory[$artifact_id] = $capacity;
			$total_artifact_capacity += $capacity;
		}

		// ===== 2) REDEMPTION MAPS (gom theo lô) =====
		$voucher_period_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT voucher_post_id, COUNT(id) AS total_count
				 FROM {$prefix}user_voucher_redemptions
				 WHERE DATE(redeemed_at) BETWEEN %s AND %s
				 GROUP BY voucher_post_id",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$voucher_end_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT voucher_post_id, COUNT(id) AS total_count
				 FROM {$prefix}user_voucher_redemptions
				 WHERE DATE(redeemed_at) <= %s
				 GROUP BY voucher_post_id",
				$end_date
			),
			ARRAY_A
		);

		$voucher_before_start_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT voucher_post_id, COUNT(id) AS total_count
				 FROM {$prefix}user_voucher_redemptions
				 WHERE DATE(redeemed_at) < %s
				 GROUP BY voucher_post_id",
				$start_date
			),
			ARRAY_A
		);

		$voucher_day_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(redeemed_at) AS day, voucher_post_id, COUNT(id) AS total_count
				 FROM {$prefix}user_voucher_redemptions
				 WHERE DATE(redeemed_at) BETWEEN %s AND %s
				 GROUP BY DATE(redeemed_at), voucher_post_id",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$voucher_period_map = game_bsc_award_format_total_counts_by_object($voucher_period_rows, 'voucher_post_id');
		$voucher_end_map = game_bsc_award_format_total_counts_by_object($voucher_end_rows, 'voucher_post_id');
		$voucher_before_start_map = game_bsc_award_format_total_counts_by_object($voucher_before_start_rows, 'voucher_post_id');
		$voucher_day_map = game_bsc_award_format_day_counts_by_object($voucher_day_rows, 'voucher_post_id');
		$voucher_awarded_day_total = game_bsc_award_sum_day_counts($voucher_day_rows);

		$artifact_period_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT artifact_id, COUNT(id) AS total_count
				 FROM {$prefix}user_artifact_redemptions
				 WHERE DATE(redeemed_at) BETWEEN %s AND %s
				 GROUP BY artifact_id",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$artifact_end_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT artifact_id, COUNT(id) AS total_count
				 FROM {$prefix}user_artifact_redemptions
				 WHERE DATE(redeemed_at) <= %s
				 GROUP BY artifact_id",
				$end_date
			),
			ARRAY_A
		);

		$artifact_before_start_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT artifact_id, COUNT(id) AS total_count
				 FROM {$prefix}user_artifact_redemptions
				 WHERE DATE(redeemed_at) < %s
				 GROUP BY artifact_id",
				$start_date
			),
			ARRAY_A
		);

		$artifact_day_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(redeemed_at) AS day, artifact_id, COUNT(id) AS total_count
				 FROM {$prefix}user_artifact_redemptions
				 WHERE DATE(redeemed_at) BETWEEN %s AND %s
				 GROUP BY DATE(redeemed_at), artifact_id",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$artifact_period_map = game_bsc_award_format_total_counts_by_object($artifact_period_rows, 'artifact_id');
		$artifact_end_map = game_bsc_award_format_total_counts_by_object($artifact_end_rows, 'artifact_id');
		$artifact_before_start_map = game_bsc_award_format_total_counts_by_object($artifact_before_start_rows, 'artifact_id');
		$artifact_day_map = game_bsc_award_format_day_counts_by_object($artifact_day_rows, 'artifact_id');
		$artifact_awarded_day_total = game_bsc_award_sum_day_counts($artifact_day_rows);

		// ===== 3) SUMMARY =====
		$awarded_vouchers = array_sum($voucher_period_map);
		$awarded_artifacts = array_sum($artifact_period_map);
		$total_awarded = $awarded_vouchers + $awarded_artifacts;

		$not_awarded_vouchers = 0;
		foreach ($voucher_inventory as $voucher_id => $item) {
			$capacity = (int)$item['capacity'];
			$redeemed_end = (int)($voucher_end_map[$voucher_id] ?? 0);
			$not_awarded_vouchers += max(0, $capacity - $redeemed_end);
		}

		$not_awarded_artifacts = 0;
		foreach ($artifact_inventory as $artifact_id => $capacity) {
			$redeemed_end = (int)($artifact_end_map[$artifact_id] ?? 0);
			$not_awarded_artifacts += max(0, (int)$capacity - $redeemed_end);
		}

		$total_not_awarded = $not_awarded_vouchers + $not_awarded_artifacts;
		$total = $total_awarded + $total_not_awarded;

		// ===== 4) PHÂN LOẠI =====
		$bsc_awarded = 0;
		$bsc_not_awarded = 0;
		$third_party_awarded = 0;
		$third_party_not_awarded = 0;

		foreach ($voucher_inventory as $voucher_id => $item) {
			$voucher_type = $item['type'];
			$capacity = (int)$item['capacity'];
			$period_count = (int)($voucher_period_map[$voucher_id] ?? 0);
			$end_count = (int)($voucher_end_map[$voucher_id] ?? 0);
			$remaining = max(0, $capacity - $end_count);

			if ($voucher_type === 'THIRD_PARTY') {
				$third_party_awarded += $period_count;
				$third_party_not_awarded += $remaining;
			} else {
				$bsc_awarded += $period_count;
				$bsc_not_awarded += $remaining;
			}
		}

		$artifact_awarded_in_period = array_sum($artifact_period_map);
		$artifact_not_awarded_total = 0;
		foreach ($artifact_inventory as $artifact_id => $capacity) {
			$end_count = (int)($artifact_end_map[$artifact_id] ?? 0);
			$artifact_not_awarded_total += max(0, (int)$capacity - $end_count);
		}

		$by_category = [
			[
				'name' => 'Voucher phí giao dịch BSC',
				'awarded' => $bsc_awarded,
				'not_awarded' => $bsc_not_awarded,
			],
			[
				'name' => 'Voucher bên thứ 3',
				'awarded' => $third_party_awarded,
				'not_awarded' => $third_party_not_awarded,
			],
			[
				'name' => 'Quà hiện vật',
				'awarded' => $artifact_awarded_in_period,
				'not_awarded' => $artifact_not_awarded_total,
			],
		];

		// ===== 5) DATA POINTS (all days) =====
		$daily_points = [];
		if ($period !== 'day') {
			$current = new DateTimeImmutable($start_date, $tz);
			$end = new DateTimeImmutable($end_date, $tz);
			$today = new DateTimeImmutable('now', $tz);

			if ($end > $today) {
				$end = $today;
			}

			$current_voucher_redeemed = [];
			$total_voucher_remaining_running = 0;
			foreach ($voucher_inventory as $voucher_id => $item) {
				$before_count = (int)($voucher_before_start_map[$voucher_id] ?? 0);
				$current_voucher_redeemed[$voucher_id] = $before_count;
				$total_voucher_remaining_running += max(0, (int)$item['capacity'] - $before_count);
			}

			$current_artifact_redeemed = [];
			$total_artifact_remaining_running = 0;
			foreach ($artifact_inventory as $artifact_id => $capacity) {
				$before_count = (int)($artifact_before_start_map[$artifact_id] ?? 0);
				$current_artifact_redeemed[$artifact_id] = $before_count;
				$total_artifact_remaining_running += max(0, (int)$capacity - $before_count);
			}

			while ($current <= $end) {
				$check_date = $current->format('Y-m-d');

				if (!empty($voucher_day_map[$check_date])) {
					foreach ($voucher_day_map[$check_date] as $voucher_id => $inc) {
						$capacity = (int)($voucher_inventory[$voucher_id]['capacity'] ?? 0);
						$old_count = (int)($current_voucher_redeemed[$voucher_id] ?? 0);
						$old_remaining = max(0, $capacity - $old_count);
						$new_count = $old_count + (int)$inc;
						$new_remaining = max(0, $capacity - $new_count);

						$current_voucher_redeemed[$voucher_id] = $new_count;
						$total_voucher_remaining_running += ($new_remaining - $old_remaining);
					}
				}

				if (!empty($artifact_day_map[$check_date])) {
					foreach ($artifact_day_map[$check_date] as $artifact_id => $inc) {
						$capacity = (int)($artifact_inventory[$artifact_id] ?? 0);
						$old_count = (int)($current_artifact_redeemed[$artifact_id] ?? 0);
						$old_remaining = max(0, $capacity - $old_count);
						$new_count = $old_count + (int)$inc;
						$new_remaining = max(0, $capacity - $new_count);

						$current_artifact_redeemed[$artifact_id] = $new_count;
						$total_artifact_remaining_running += ($new_remaining - $old_remaining);
					}
				}

				$day_awarded = (int)($voucher_awarded_day_total[$check_date] ?? 0) + (int)($artifact_awarded_day_total[$check_date] ?? 0);
				$day_not_awarded = max(0, $total_voucher_remaining_running + $total_artifact_remaining_running);

				$daily_points[] = [
					'date' => $check_date,
					'awarded' => $day_awarded,
					'not_awarded' => $day_not_awarded,
				];

				$current = $current->modify('+1 day');
			}
		}

		// ---- Nhóm data_points theo quy tắc ----
		$award_start = new DateTimeImmutable($start_date, $tz);
		$award_end_dt = new DateTimeImmutable($end_date, $tz);
		$total_days = ((int)$award_start->diff($award_end_dt)->days) + 1;

		if ($total_days > 32) {
			$data_points = game_bsc_group_award_by_week($daily_points, $award_start, $award_end_dt, $tz);
		} else {
			$data_points = $daily_points;
		}

		// ===== 7. RESPONSE =====
		$response = [
			'period' => $period,
			'reference_date' => $ref_date,
			'date_range' => [
				'start' => $start_date,
				'end' => $end_date,
				'start_label' => $resolved['start']->format('d/m/Y'),
				'end_label' => $resolved['end']->format('d/m/Y'),
			],
			'applied_filter' => [
				'from_date' => $resolved['start']->format('d/m/Y'),
				'to_date' => $resolved['end']->format('d/m/Y'),
			],
			'summary' => [
				'awarded' => $total_awarded,
				'not_awarded' => $total_not_awarded,
				'total' => $total,
			],
			'by_category' => $by_category,
			'data_points' => $data_points,
			'generated_at' => current_time('Y-m-d H:i:s'),
		];
		
		return $response;
		
	} catch (Throwable $e) {
		return [
			'error' => $e->getMessage(),
			'period' => $period,
			'summary' => [
				'awarded' => 0,
				'not_awarded' => 0,
				'total' => 0,
			],
			'by_category' => [],
			'data_points' => [],
		];
	}
}

/**
 * wp_ajax handler: Lấy dữ liệu tình trạng trao/nhận giải thưởng
 */
add_action('wp_ajax_game_bsc_get_award_status', function() {
	$period = sanitize_text_field($_POST['period'] ?? 'week');
	$date = sanitize_text_field($_POST['date'] ?? '');
	$from_date = sanitize_text_field($_POST['from_date'] ?? '');
	$to_date = sanitize_text_field($_POST['to_date'] ?? '');
	
	$data = game_bsc_get_award_status($period, $date ?: null, $from_date, $to_date);
	
	if (isset($data['error'])) {
		wp_send_json_error($data);
	} else {
		wp_send_json_success($data);
	}
});

add_action('wp_ajax_nopriv_game_bsc_get_award_status', function() {
	do_action('wp_ajax_game_bsc_get_award_status');
});

?>