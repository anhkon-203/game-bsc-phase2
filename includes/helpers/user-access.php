<?php

/**
 * Hàm lấy dữ liệu truy cập của người dùng
 *
 * Trạng thái tham gia:
 * - Truy cập: Có bản ghi trong bảng users nhưng chưa chơi (0 phiên)
 * - Tham gia: Đã vào chơi (>= 1 phiên) nhưng chưa hoàn thành bất kỳ phiên nào
 * - Hoàn thành: Đã hoàn thành ít nhất 1 phiên (questions_count = correct_count)
 */
function game_bsc_get_users_access_data($date_from = null, $date_to = null, $provider = null, $status_play = null, $status_active = null, $search = null, $date_access = null) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		// ===== 1. KIỂM TRA BỘ LỌC NGÀY - CẦN ĐỦ 2/3 TRƯỜNG =====
		$date_filters_count = 0;
		if (!empty($date_from) && $date_from !== '') $date_filters_count++;
		if (!empty($date_to) && $date_to !== '') $date_filters_count++;
		if (!empty($date_access) && $date_access !== '') $date_filters_count++;
		
		// Nếu có ít hơn 2 trường, bỏ qua tất cả các bộ lọc ngày
		if ($date_filters_count < 2) {
			$date_from = null;
			$date_to = null;
			$date_access = null;
		}
		
		// ===== 2. KHỞI TẠO QUERY CƠ BẢN =====
		$query = "SELECT
			u.id,
			u.provider,
			u.name,
			u.external_user_id,
			u.avatar_url,
			u.status as status_active,
			COALESCE(first_login.checked_at, u.created_at) as first_access_date,
			COALESCE(last_login.checked_at, NULL) as last_login_at,
			COUNT(DISTINCT ps.id) as total_sessions,
			COUNT(DISTINCT CASE
				WHEN ps.id IS NOT NULL
				AND ps.questions_count = ps.correct_count
				AND ps.questions_count > 0
				THEN ps.id
			END) as completed_sessions
		FROM {$prefix}users u
		LEFT JOIN {$prefix}user_login_logs first_login ON u.id = first_login.user_id
			AND first_login.result = 'OK'
			AND first_login.checked_at = (
				SELECT MIN(checked_at) FROM {$prefix}user_login_logs
				WHERE user_id = u.id AND result = 'OK'
			)
		LEFT JOIN {$prefix}user_login_logs last_login ON u.id = last_login.user_id
			AND last_login.result = 'OK'
			AND last_login.checked_at = (
				SELECT MAX(checked_at) FROM {$prefix}user_login_logs
				WHERE user_id = u.id AND result = 'OK'
			)
		LEFT JOIN {$prefix}users_play_sessions ps ON u.id = ps.user_id
		WHERE 1=1";
		
		$params = [];
		
		// ===== 3. FILTER THEO NGÀY - PHẢI TRỊ VỀ THỰC TẾ CHỈ TRONG VÒNG [date_from, date_to] =====
		// FIX: Sử dụng first_access_date hoặc last_login_at thay vì u.created_at
		if ($date_filters_count >= 2 && !empty($date_from)) {
			// Lọc dựa trên date_access type
			if (!empty($date_access) && $date_access === 'first_login') {
				$query .= " AND DATE(COALESCE(first_login.checked_at, u.created_at)) >= %s";
			} elseif (!empty($date_access) && $date_access === 'last_login') {
				$query .= " AND DATE(COALESCE(last_login.checked_at, u.created_at)) >= %s";
			} else {
				// Default: lọc theo created_at
				$query .= " AND DATE(u.created_at) >= %s";
			}
			$params[] = $date_from;
		}
		
		if ($date_filters_count >= 2 && !empty($date_to)) {
			if (!empty($date_access) && $date_access === 'first_login') {
				$query .= " AND DATE(COALESCE(first_login.checked_at, u.created_at)) <= %s";
			} elseif (!empty($date_access) && $date_access === 'last_login') {
				$query .= " AND DATE(COALESCE(last_login.checked_at, u.created_at)) <= %s";
			} else {
				// Default: lọc theo created_at
				$query .= " AND DATE(u.created_at) <= %s";
			}
			$params[] = $date_to;
		}
		
		// ===== 4. FILTER THEO NỀN TẢNG =====
		if (!empty($provider)) {
			$provider = sanitize_text_field($provider);

			if (in_array($provider, [MTRADER_APP, BSC_SMART_INVEST, WEBTRADING, BSC_WEB], true)) {
				$query .= " AND u.provider = %s";
				$params[] = $provider;
			}
		}
		
		// ===== 5. FILTER THEO TRẠNG THÁI HOẠT ĐỘNG =====
		if ($status_active !== null && $status_active !== '') {
			$status_active_val = (int)$status_active;
			if (in_array($status_active_val, [0, 1], true)) {
				$query .= " AND u.status = %d";
				$params[] = $status_active_val;
			}
		}
		
		// ===== 6. FILTER THEO TÌM KIẾM =====
		if (!empty($search)) {
			$search_term = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
			$query .= " AND (u.name LIKE %s OR u.external_user_id LIKE %s OR CAST(u.id AS CHAR) LIKE %s)";
			$params[] = $search_term;
			$params[] = $search_term;
			$params[] = $search_term;
		}
		
		// ===== 7. GROUP BY USER =====
		$query .= " GROUP BY u.id";
		
		// ===== 8. EXECUTE QUERY =====
		if (!empty($params)) {
			$results = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
		} else {
			$results = $wpdb->get_results($query, ARRAY_A);
		}
		
		if (is_null($results)) {
			return [
				'status' => 'error',
				'message' => 'Query error: ' . $wpdb->last_error,
				'data' => []
			];
		}
		
		// ===== 9. POST-PROCESS KẾT QUẢ - TÍNH TRẠNG THÁI THAM GIA =====
		$formatted_results = [];
		
		foreach ($results as $row) {
			$user_id = (int)$row['id'];
			$total_sessions = (int)$row['total_sessions'];
			$completed_sessions = (int)$row['completed_sessions'];
			
			// Xác định trạng thái tham gia
			if ($total_sessions == 0) {
				$play_status = 'truy-cap';
			} elseif ($completed_sessions > 0) {
				$play_status = 'hoan-thanh';
			} else {
				$play_status = 'tham-gia';
			}
			
			// ===== 10. FILTER THEO STATUS_PLAY =====
			if (!empty($status_play)) {
				if ($play_status !== $status_play) {
					continue;
				}
			}
			
			// Format lại dữ liệu
			$formatted_results[] = [
				'id' => $user_id,
				'name' => sanitize_text_field($row['name']),
				'external_user_id' => sanitize_text_field($row['external_user_id'] ?? ''),
				'avatar_url' => esc_url($row['avatar_url'] ?? ''),
				'provider' => sanitize_text_field($row['provider']),
				'first_access_date' => $row['first_access_date'],
				'last_login_at' => $row['last_login_at'] ?? null,
				'play_status' => $play_status,
				'status_active' => (int)$row['status_active'],
				'total_sessions' => $total_sessions,
				'completed_sessions' => $completed_sessions,
			];
		}
		
		// ===== 11. SORT VÀ RETURN =====
		usort($formatted_results, function ($a, $b) {
			$time_a = strtotime($a['last_login_at'] ?? '1970-01-01');
			$time_b = strtotime($b['last_login_at'] ?? '1970-01-01');
			return $time_b <=> $time_a;
		});
		
		return [
			'status' => 'success',
			'total_count' => count($formatted_results),
			'data' => $formatted_results
		];
		
	} catch (Throwable $e) {
		return [
			'status' => 'error',
			'message' => 'Exception: ' . $e->getMessage(),
			'data' => []
		];
	}
}

/**
 * Hàm lấy dữ liệu truy cập với phân trang
 */
function game_bsc_get_users_access_data_paginated($page = 1, $per_page = 20, $filters = []) {
	$page = max(1, (int)$page);
	$per_page = max(1, min((int)$per_page, 100));
	
	// Sanitize filters
	$filters = array_map('sanitize_text_field', $filters);
	
	$all_data = game_bsc_get_users_access_data(
		$filters['date_from'] ?? null,
		$filters['date_to'] ?? null,
		$filters['provider'] ?? null,
		$filters['status_play'] ?? null,
		$filters['status_active'] ?? null,
		$filters['search'] ?? null,
		$filters['date_access'] ?? null
	);
	
	if ($all_data['status'] !== 'success') {
		return $all_data;
	}
	
	$total_items = $all_data['total_count'];
	$total_pages = ceil($total_items / $per_page);
	
	if ($page > $total_pages && $total_pages > 0) {
		return [
			'status' => 'error',
			'message' => 'Page number exceeds total pages',
			'data' => []
		];
	}
	
	$offset = ($page - 1) * $per_page;
	$paginated_data = array_slice($all_data['data'], $offset, $per_page);
	
	return [
		'status' => 'success',
		'total_count' => $total_items,
		'total_pages' => $total_pages,
		'current_page' => $page,
		'per_page' => $per_page,
		'data' => $paginated_data
	];
}

add_action('wp_ajax_game_bsc_toggle_user_status', 'game_bsc_handle_toggle_user_status');

function game_bsc_handle_toggle_user_status() {
	if (!current_user_can('admin_game') && !current_user_can('administrator')) {
		wp_send_json_error('Bạn không có quyền thực hiện hành động này.', 403);
	}
	
	if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'game_bsc_toggle_user_status')) {
		wp_send_json_error('Xác thực không hợp lệ.', 403);
	}
	
	$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
	$status = isset($_POST['status']) ? (int)$_POST['status'] : null;
	
	if ($user_id <= 0 || $status === null) {
		wp_send_json_error('Tham số không hợp lệ.', 400);
	}
	
	$status = ($status === 1) ? 1 : 0;
	
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		$user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status FROM {$prefix}users WHERE id = %d",
				$user_id
			),
			ARRAY_A
		);
		
		if (!$user) {
			wp_send_json_error('Người dùng không tồn tại.', 404);
		}
		
		$result = $wpdb->update(
			$prefix . 'users',
			['status' => $status],
			['id' => $user_id],
			['%d'],
			['%d']
		);
		
		if ($result === false) {
			throw new Exception('Lỗi database: ' . $wpdb->last_error);
		}
		
		error_log(sprintf(
			'User %d changed user #%d status to %d at %s',
			get_current_user_id(),
			$user_id,
			$status,
			current_time('mysql')
		));
		
		wp_send_json_success([
			'user_id' => $user_id,
			'status' => $status,
			'message' => $status === 1 ? 'Bật hoạt động thành công' : 'Tắt hoạt động thành công'
		]);
		
	} catch (Exception $e) {
		error_log('game_bsc_handle_toggle_user_status error: ' . $e->getMessage());
		wp_send_json_error($e->getMessage(), 500);
	}
}