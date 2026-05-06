<?php
/**
 * REST API endpoints cho các thao tác liên quan đến người dùng
 *
 * File này cung cấp các endpoint API để:
 * - Lấy thông tin người dùng và thống kê game
 * - Quản lý huy hiệu và thành tích của người chơi
 * - Theo dõi tiến độ milestone của huy hiệu
 */

if (!defined('ABSPATH')) exit; // Bảo vệ không cho truy cập trực tiếp file

/**
 * Đăng ký endpoint: GET /wp-json/game-bsc/v1/user/{user_id}
 *
 * Trả về thông tin chi tiết của người dùng bao gồm:
 * - Thông tin cơ bản (id, tên, avatar)
 * - Số dư play credit (lượt chơi)
 * - Thông tin ngày game hiện tại
 * - Thống kê 11 ngày làm việc (5 ngày trước, hôm nay, 5 ngày sau)
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/user', array(
		'methods' => 'GET',
		'callback' => 'game_get_user_info',
		'permission_callback' => '__return_true',
	));
});

/**
 * Đăng ký endpoint: GET /wp-json/game-bsc/v1/user/{user_id}/stats
 *
 * Trả về thống kê tổng hợp của người dùng:
 * - Tổng điểm (points) đã tích lũy
 * - Tổng mảnh ghép (pieces) đã thu thập
 * - Thành tích tuần này (số câu trả lời đúng)
 * - Thành tích tháng này (số câu trả lời đúng)
 * - Danh sách huy hiệu đã đạt được và chưa đạt được
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/user/stats', array(
		'methods' => 'GET',
		'callback' => 'game_get_user_stats',
		'permission_callback' => '__return_true',
	));
});

/**
 * Đăng ký endpoint: GET /wp-json/game-bsc/v1/user/{user_id}/badges
 *
 * Trả về danh sách tất cả huy hiệu của người dùng:
 * - Huy hiệu đã đạt được (earned = true)
 * - Huy hiệu chưa đạt được (earned = false)
 * - Bao gồm tên, mô tả, icon và trạng thái của từng huy hiệu
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/user/badges', array(
		'methods' => 'GET',
		'callback' => 'game_get_user_badges',
		'permission_callback' => '__return_true',
	));
});

/**
 * Đăng ký endpoint: GET /wp-json/game-bsc/v1/user/{user_id}/badge-milestone
 *
 * Trả về thông tin milestone (mốc) huy hiệu tiếp theo:
 * - Huy hiệu sắp đạt được (theo thứ tự badge_order)
 * - Tiến độ hiện tại so với điều kiện đạt huy hiệu
 * - Lịch chơi trong tuần (5 ngày từ thứ 2 đến thứ 6)
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/user/badge-milestone', array(
		'methods' => 'GET',
		'callback' => 'game_get_badge_milestone',
		'permission_callback' => '__return_true',
	));
});

/**
 * Đăng ký endpoint: GET /wp-json/game-bsc/v1/user/unviewed-badges
 *
 * Lấy danh sách huy hiệu chưa được hiển thị cho người chơi (viewed = 0)
 * Sau khi lấy, tự động update viewed = 1 cho các huy hiệu đó
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/user/unviewed-badges', array(
		'methods' => 'GET',
		'callback' => 'game_get_unviewed_badges',
		'permission_callback' => '__return_true',
	));
});

/**
 * Đăng ký endpoint: GET /wp-json/game-bsc/v1/user/voucher-redemptions
 *
 * Lấy lịch sử đổi điểm (chỉ ref_type = 'VOUCHER') của người dùng đang đăng nhập.
 * Query params:
 * - page (int) trang, mặc định 1
 * - per_page (int) số bản ghi/trang, mặc định 20, tối đa 100
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/user/voucher-redemptions', array(
		'methods' => 'GET',
		'callback' => 'game_get_user_voucher_redemptions_history',
		'permission_callback' => '__return_true',
	));
});

/**
 * Đăng ký endpoint: GET /wp-json/game-bsc/v1/user/points-added
 *
 * Lấy lịch sử các lần được cộng điểm (ref_type = 'SESSION' or 'BADGE')
 * Nhóm theo ngày và phân trang theo số ngày
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/user/points-added', array(
		'methods' => 'GET',
		'callback' => 'game_get_user_points_added_history',
		'permission_callback' => '__return_true',
	));
});

// route logout

add_action('rest_api_init', function () {
	register_rest_route(NS, '/user/logout', array(
		'methods' => 'POST',
		'callback' => 'game_user_logout',
		'permission_callback' => '__return_true',
	));
});

/**
 * Lấy danh sách 11 ngày làm việc (chỉ thứ 2-6) xung quanh ngày hôm nay
 *
 * FIX: Nếu hôm nay là thứ 7 hoặc CN, không thêm hôm nay, chỉ lấy 5 trước + 5 sau = 10 ngày
 *
 * @param DateTimeImmutable $today Ngày tham chiếu (ngày hôm nay)
 * @return array Mảng các đối tượng DateTimeImmutable đại diện cho các ngày làm việc
 */
//function game_get_weekday_range(DateTimeImmutable $today, DateTimeImmutable $game_start, DateTimeImmutable $game_end)
//{
//	$weekday_dates = [];
//
//	// ===== TRƯỚC HÔM NAY (tối đa 5 ngày làm việc) =====
//	$current = $today->modify('-1 day');
//	$picked  = 0;
//	while ($picked < 5 && $current >= $game_start) {
//		$dow = (int) $current->format('N'); // 1..7
//		if ($dow >= 1 && $dow <= 5) {
//			array_unshift($weekday_dates, $current);
//			$picked++;
//		}
//		$current = $current->modify('-1 day');
//	}
//
//	// ===== HÔM NAY =====
//	$today_dow = (int) $today->format('N');
//	if ($today_dow >= 1 && $today_dow <= 5 && $today >= $game_start && $today <= $game_end) {
//		$weekday_dates[] = $today;
//	}
//
//	// ===== SAU HÔM NAY (tối đa 5 ngày làm việc) =====
//	$current = $today->modify('+1 day');
//	$picked  = 0;
//	while ($picked < 5 && $current <= $game_end) {
//		$dow = (int) $current->format('N');
//		if ($dow >= 1 && $dow <= 5) {
//			$weekday_dates[] = $current;
//			$picked++;
//		}
//		$current = $current->modify('+1 day');
//	}
//
//	return $weekday_dates;
//}


function game_get_user_info(WP_REST_Request $request)
{
	global $wpdb;
	
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// ===== KIỂM TRA TÀI KHOẢN BỊ CHẶN (NƯỚC NGOÀI HOẶC TỔ CHỨC) =====
	if (!empty($_COOKIE['game_invalid_account'])) {
		$utm_source_cookie = $_COOKIE['utm_source'] ?? '';
		$show_login_button = false;
		
		switch ($utm_source_cookie) {
			case MTRADER_APP:
			case BSC_SMART_INVEST:
			case WEBTRADING:
				$show_login_button = true;
				break;
			default:
				$show_login_button = false;
				break;
		}

		return wg_json_response(401, [
			'error_code'         => 'invalid_account',
			'show_login_button'  => $show_login_button,
		], __('Tài khoản không được phép truy cập hệ thống', WG_GAME_PLUGIN_TEXTDOMAIN), 401);
	}

	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	$user_id = absint($current_user['id']);
	
	// ===== KIỂM TRA USER TỒN TẠI =====
	$prefix = $wpdb->prefix . 'game_';
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, name, avatar_url,status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);
	
	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	if ($user['status'] == 0) {
		return wg_json_response(404, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// Lấy play credit balance
	$balance = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT balance FROM {$prefix}play_credit_balances WHERE user_id = %d",
			$user_id
		)
	);
	$balance = $balance !== null ? (int)$balance : 0;
	
	$inventory_user = game_bsc_get_user_inventory();
	$points_balance = $inventory_user['points'] ?? 0;
	$pieces_balance = $inventory_user['total_pieces'] ?? 0;
	
	// ===== TÍNH CHẶNG HIỆN TẠI =====
	$game_day = game_bsc_compute_day_index_v2($user_id);
	$current_stage = (int)$game_day['day_index']; // Chặng hiện tại
	
	// Lấy timezone
	$tz = TIMEZONE;
	$today = new DateTimeImmutable('now', $tz);
	$today_str = $today->format('Y-m-d');
	
	// Lấy stages configuration
	$stages = get_option('game_bsc_stages', []);
	if (!is_array($stages)) $stages = [];
	
	$stage_map = [];
	foreach ($stages as $stage) {
		$from_stage = (int)($stage['from_stage'] ?? 0);
		$to_stage = (int)($stage['to_stage'] ?? 0);
		
		if ($from_stage > 0 && $to_stage > 0) {
			for ($s = $from_stage; $s <= $to_stage; $s++) {
				$stage_map[$s] = $stage;
			}
		}
	}
	
	// ===== BUILD WEEKDAY_RANGE THEO CHẶNG (không theo ngày) =====
	// Lấy min và max chặng
	$all_stages = array_unique(array_keys($stage_map));
	sort($all_stages);
	
	if (empty($all_stages)) {
		// Không có chặng nào được cấu hình
		$weekday_range = [];
	} else {
		$min_stage = min($all_stages);
		$max_stage = max($all_stages);
		
		// Range: bắt đầu từ chặng nhỏ nhất (ví dụ chặng 1) tới chặng current_stage + 5,
		// nhưng vẫn giới hạn trong [min_stage, max_stage]
		// Lưu ý: không lấy 5 chặng trước chặng hiện tại nữa, thay vào đó lấy tất cả chặng bắt đầu từ $min_stage
		$start_stage = $min_stage;
		$end_stage = $current_stage + 5;
		
		$weekday_range = [];
		
		for ($stage_num = $start_stage; $stage_num <= $end_stage; $stage_num++) {
			// if (!isset($stage_map[$stage_num])) {
			// 	continue;
			// }
			
			$stage_info = $stage_map[$stage_num];
			$is_active = ($stage_num === $current_stage);
			
			$score_per_question = (int)$stage_info['score'];
			$questions_per_day = (int)$stage_info['questions_per_day'];
			
			$day_data = [
				'day_index' => $stage_num,
				'is_active' => $is_active
			];
			
			// Nếu là chặng hiện tại, thêm dữ liệu chi tiết
			if ($is_active) {
				$day_data['answered_count'] = 0;
				$day_data['total_possible'] = 0;
				$day_data['pieces_collected'] = 0;
				$day_data['max_points'] = 0;
				
				// Lấy số câu trả lời hôm nay
				$answered_count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*)
						 FROM {$prefix}users_session_answers usa
						 INNER JOIN {$prefix}users_play_sessions ups ON usa.session_id = ups.id
						 WHERE ups.user_id = %d AND DATE(usa.answered_at) = %s",
						$user_id,
						$today_str
						)
					);
				$day_data['answered_count'] = (int)$answered_count;
				$day_data['total_possible'] = ($questions_per_day * $balance) + $answered_count;
				
				// Lấy tổng điểm đã thu thập cho chặng hiện tại
				$points_sum = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COALESCE(SUM(dl.points_awarded), 0)
						 FROM {$prefix}drop_logs dl
						 INNER JOIN {$prefix}users_play_sessions ups ON dl.session_id = ups.id
						 WHERE ups.user_id = %d AND ups.current_stage = %d AND dl.outcome = 'POINT'",
						$user_id,
						$current_stage
						)
					);
				$day_data['max_points'] = (int)$points_sum;
				
				// Lấy số mảnh thu thập được cho chặng hiện tại
				$pieces_count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(dl.id) FROM {$prefix}drop_logs dl
						 INNER JOIN {$prefix}users_play_sessions ups ON dl.session_id = ups.id
						 WHERE ups.user_id = %d AND ups.current_stage = %d AND dl.outcome = 'PIECE'",
						$user_id,
						$current_stage
						)
					);
				$day_data['pieces_collected'] = (int)$pieces_count;
			}
			
			$weekday_range[] = $day_data;
		}
	}
	
	// ===== SECURITY FIX: Output Sanitization =====
	// Code cũ:
	// $response = array(
	// 	'user' => array(
	// 		'id' => (int)$current_user['id'],
	// 		'name' => $current_user['name'],
	// 		'avatar_url' => $current_user['avatar_url'] ?: '',
	// 		...
	// 	),
	// );
	
	// Code mới (thêm sanitization):
	$response = array(
		'user' => array(
			'id' => (int)$current_user['id'],
			'name' => sanitize_text_field($current_user['name']),
			'avatar_url' => esc_url($current_user['avatar_url'] ?: ''),
			'points' => (int)$points_balance,
			'pieces' => (int)$pieces_balance,
		),
		'play_credit_balance' => (int)$balance,
		'game_day' => $game_day,
		'weekday_range' => $weekday_range
	);
	return wg_json_response(200, $response, __('Lấy thông tin người dùng thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

/**
 * Lấy thống kê tổng hợp của người dùng
 *
 * Bao gồm: tổng điểm, tổng mảnh ghép, thành tích tuần/tháng, và danh sách huy hiệu
 *
 * @param WP_REST_Request $request Đối tượng request chứa user_id
 * @return WP_REST_Response Thống kê tổng hợp của người dùng
 */
function game_get_user_stats(WP_REST_Request $request)
{

	global $wpdb;
	
	
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	
	$user_id = absint($current_user['id']);
	
	
	// ===== KIỂM TRA USER TỒN TẠI =====
	$prefix = $wpdb->prefix . 'game_';
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, name, avatar_url,status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);
	
	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	if ($user['status'] == 0) {
		return wg_json_response(404, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

//	end SSO
	
	try {
		$inventory = game_bsc_get_user_inventory($user_id);
		$total_points = $inventory['points'];
		$total_pieces = $inventory['total_pieces'];
		
		// Tính thành tích tuần này (số câu trả lời đúng từ thứ 2 đến chủ nhật)
		$tz = TIMEZONE;
		$today = new DateTimeImmutable('now', $tz);
		$week_start = $today->modify('monday this week')->format('Y-m-d'); // Thứ 2 tuần này
		$week_end = $today->modify('sunday this week')->format('Y-m-d'); // Chủ nhật tuần này
		
		// Đếm số câu trả lời đúng trong tuần này
		$week_correct_answers = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
                 FROM {$prefix}users_session_answers usa
                 INNER JOIN {$prefix}users_play_sessions ups ON usa.session_id = ups.id
                 WHERE ups.user_id = %d
                 AND usa.is_correct = 1
                 AND DATE(usa.answered_at) BETWEEN %s AND %s",
				$user_id,
				$week_start,
				$week_end
			)
		);
		$week_correct_answers = (int)$week_correct_answers;
		
		// Tính thành tích tháng này (từ ngày 1 đến ngày cuối tháng)
		$month_start = $today->format('Y-m-01'); // Ngày đầu tháng
		$month_end = $today->format('Y-m-t'); // Ngày cuối tháng
		
		// Đếm số câu trả lời đúng trong tháng này
		$month_correct_answers = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
                 FROM {$prefix}users_session_answers usa
                 INNER JOIN {$prefix}users_play_sessions ups ON usa.session_id = ups.id
                 WHERE ups.user_id = %d
                 AND usa.is_correct = 1
                 AND DATE(usa.answered_at) BETWEEN %s AND %s",
				$user_id,
				$month_start,
				$month_end
			)
		);
		$month_correct_answers = (int)$month_correct_answers;
		
		// Lấy danh sách huy hiệu của người dùng
		$badges_formatted = game_get_user_badges_data($user_id);
		
		// Tạo response trả về
		$response = array(
			'user' => $current_user,
			'points' => $total_points,
			'pieces' => $total_pieces,
			'achievements' => array(
				'week' => $week_correct_answers, // Thành tích tuần
				'month' => $month_correct_answers // Thành tích tháng
			),
			'badges' => $badges_formatted
		);
		
		return wg_json_response(200, $response, __('Lấy thống kê người dùng thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Throwable $e) {
		return wg_json_response(500, [], __('Lỗi: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN));
	}
}

/**
 * Lấy danh sách huy hiệu của người dùng (callback cho endpoint)
 *
 * @param WP_REST_Request $request Đối tượng request chứa user_id
 * @return WP_REST_Response Danh sách huy hiệu đã đạt được và chưa đạt được
 */
function game_get_user_badges(WP_REST_Request $request)
{
	global $wpdb;
	
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	
	$user_id = absint($current_user['id']);
	
	
	// ===== KIỂM TRA USER TỒN TẠI =====
	$prefix = $wpdb->prefix . 'game_';
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, name, avatar_url,status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);
	
	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	if ($user['status'] == 0) {
		return wg_json_response(404, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

//	end SSO
	
	try {
		// Lấy dữ liệu huy hiệu
		$badges = game_get_user_badges_data($user_id);
		
		$response = array(
			'user_id' => $user_id,
			'badges' => $badges
		);
		
		return wg_json_response(200, $response, __('Lấy danh sách huy hiệu thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
	} catch (Throwable $e) {
		return wg_json_response(500, [], __('Lỗi: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN));
	}
}

/**
 * Lấy chi tiết các ngày đăng nhập trong tuần (thứ 2-6)
 * Logic mới: Tính theo user_login_logs thay vì users_play_sessions
 *
 * Trả về mảng 5 ngày từ thứ 2 đến thứ 6 kèm trạng thái played hay chưa
 *
 * @param int $user_id ID của người dùng
 * @return array Mảng 5 ngày với thông tin day, date, và played
 */
function game_get_week_play_days_detailed($user_id)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$tz = TIMEZONE;
	$today = new DateTimeImmutable('now', $tz);
	
	// Lấy ngày đầu tuần (thứ 2) và cuối tuần (thứ 6)
	$week_start = $today->modify('monday this week')->format('Y-m-d');
	$week_end = $today->modify('friday this week')->format('Y-m-d');
	
	// Lấy các ngày đã đăng nhập trong tuần này từ login_logs
	$week_login_dates = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT DATE(checked_at) as login_date
			 FROM {$prefix}user_login_logs
			 WHERE user_id = %d
			 AND result = 'OK'
			 AND DATE(checked_at) BETWEEN %s AND %s
			 ORDER BY DATE(checked_at) ASC",
			$user_id,
			$week_start,
			$week_end
		)
	);
	
	$week_days = [];
	$current = new DateTimeImmutable($week_start, $tz);
	$week_login_dates_set = array_flip($week_login_dates); // Chuyển thành set để tra cứu nhanh
	
	// Duyệt qua 5 ngày (thứ 2 đến thứ 6)
	for ($i = 0; $i < 5; $i++) {
		$date_str = $current->format('Y-m-d');
		$dow = (int)$current->format('N') + 1; // +1 để format giống calendar (2=thứ 2, 6=thứ 6)
		
		$week_days[] = [
			'day' => (string)$dow,
			'date' => $date_str,
			'played' => isset($week_login_dates_set[$date_str]) // true nếu đã đăng nhập ngày đó
		];
		
		$current = $current->modify('+1 day');
	}
	
	return $week_days;
}

/**
 * Format thông tin huy hiệu hiện tại (milestone sắp đạt được)
 *
 * Dựa vào điều kiện của huy hiệu (consecutive_days hoặc total_days) để tính progress
 *
 * @param int $badge_post_id ID post của huy hiệu
 * @param int $current_consecutive Số ngày chơi liên tiếp hiện tại
 * @param int $current_total Tổng số ngày đã chơi hiện tại
 * @return array Thông tin huy hiệu đã format
 */
function game_format_current_badge($badge_post_id, $current_consecutive, $current_total)
{
	// Lấy icon của huy hiệu
	$badge_image_id = get_field('badge_image', $badge_post_id);
	$badge_icon_url = '';
	if ($badge_image_id) {
		$badge_icon_url = wp_get_attachment_image_url($badge_image_id, 'full') ?: '';
	}
	
	// Lấy loại điều kiện (consecutive_days hoặc total_days)
	$condition_type = get_field('condition_type', $badge_post_id) ?: 'consecutive_days';
	
	// Tính giá trị điều kiện và progress tương ứng
	if ($condition_type === 'consecutive_days') {
		$condition_value = (int)get_field('consecutive_days', $badge_post_id);
		$progress = $current_consecutive; // Dùng số ngày liên tiếp
	} else {
		$condition_value = (int)get_field('total_days', $badge_post_id);
		$progress = $current_total; // Dùng tổng số ngày
	}
	
	// Lấy tên và mô tả huy hiệu
	$post = get_post($badge_post_id);
	$badge_name = $post->post_title ?? '';
	$badge_description = get_field('badge_task_content', $badge_post_id) ?: '';

	// Lấy mã màu hiệu ứng của huy hiệu
	$badge_effect_color = get_field('badge_effect_color', $badge_post_id) ?: '#F45332';
	
	return array(
		'name' => $badge_name,
		'icon_url' => $badge_icon_url,
		'points_reward' => (int)get_field('points_reward', $badge_post_id),
		'condition_value' => $condition_value, // Giá trị cần đạt
		'current_progress' => $progress, // Tiến độ hiện tại
		'description' => $badge_description,
		'effect_color' => $badge_effect_color,
	);
}

/**
 * Lấy thông tin huy hiệu milestone tiếp theo và lịch chơi trong tuần
 *
 * Xác định huy hiệu sắp đạt được dựa vào thứ tự badge_order và trạng thái đã đạt
 *
 * @param WP_REST_Request $request Đối tượng request chứa user_id
 * @return WP_REST_Response Thông tin milestone tiếp theo và lịch chơi tuần
 */
function game_get_badge_milestone(WP_REST_Request $request)
{
	global $wpdb;
	
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	
	$user_id = absint($current_user['id']);
	
	
	// ===== KIỂM TRA USER TỒN TẠI =====
	$prefix = $wpdb->prefix . 'game_';
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, name, avatar_url,status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);
	
	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	if ($user['status'] == 0) {
		return wg_json_response(404, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

//	end SSO
	try {
		// Lấy tất cả huy hiệu từ post type 'game_badges'
		$all_badges = $wpdb->get_results(
			"SELECT p.ID as badge_post_id, p.post_title as badge_name
			FROM {$wpdb->posts} p
			WHERE p.post_type = 'game_badges'
			AND p.post_status = 'publish'",
			ARRAY_A
		);
		
		// Nếu không có huy hiệu nào, trả về null
		if (empty($all_badges)) {
			return wg_json_response(200, array(
				'user_id' => $user_id,
				'current_milestone' => null,
				'weekdays' => []
			), __('Lấy thông tin milestone thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// Lấy danh sách ID của tất cả huy hiệu
		$badge_ids = array_map(function ($b) {
			return (int)$b['badge_post_id'];
		}, $all_badges);
		
		// Query meta fields của các huy hiệu (optional - có thể bỏ nếu không dùng)
		$badge_fields = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id IN (" . implode(',', $badge_ids) . ")
			AND meta_key IN ('_badge_order', '_condition_type', '_consecutive_days', '_total_days', '_points_reward')",
			OBJECT_K
		);
		
		// Thêm badge_order vào mỗi huy hiệu để sắp xếp
		$badges_with_order = [];
		foreach ($all_badges as $badge) {
			$badge_post_id = (int)$badge['badge_post_id'];
			$badge_order = (int)get_field('badge_order', $badge_post_id) ?: 999999;
			$badge['badge_order'] = $badge_order;
			$badges_with_order[] = $badge;
		}
		
		// Sắp xếp huy hiệu theo badge_order (thứ tự ưu tiên)
		usort($badges_with_order, function ($a, $b) {
			return $a['badge_order'] <=> $b['badge_order'];
		});
		
		$earned_badge_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT badge_post_id FROM {$prefix}user_badges WHERE user_id = %d",
				$user_id
			)
		);
		$earned_badge_ids_set = array_flip($earned_badge_ids);
		
		$consecutive_days = game_get_consecutive_play_days($user_id);
		$total_days = game_get_total_play_days($user_id);
		
		$current_milestone = null;
		
		foreach ($badges_with_order as $badge) {
			$badge_post_id = (int)$badge['badge_post_id'];
			
			if (isset($earned_badge_ids_set[$badge_post_id])) {
				continue;
			}
			
			$current_milestone = game_format_current_badge(
				$badge_post_id,
				$consecutive_days,
				$total_days
			);
			
			break;
		}
		
		$week_days = game_get_week_play_days_detailed($user_id);
		
		$response = array(
			'user_id' => $user_id,
			'current_milestone' => $current_milestone,
			'weekdays' => $week_days
		);
		
		return wg_json_response(200, $response, __('Lấy thông tin milestone thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Throwable $e) {
		return wg_json_response(500, [], __('Lỗi: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN));
	}
}

function game_user_logout(WP_REST_Request $request)
{
	global $wpdb;
	
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	
	$user_id = absint($current_user['id']);
	
	
	// ===== KIỂM TRA USER TỒN TẠI =====
	$prefix = $wpdb->prefix . 'game_';
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, name, avatar_url,status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);
	
	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	if ($user['status'] == 0) {
		return wg_json_response(404, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== REVOKE PLUGIN TOKEN & CLEANUP =====
    // Revoke plugin token and clear cookies if present
    if (isset($_COOKIE[GAME_AUTH_COOKIE])) {
        game_revoke_user_token($_COOKIE[GAME_AUTH_COOKIE]);
    }
    // Cleanup all expired tokens for this user
    game_cleanup_expired_tokens($user_id);
    if (isset($_COOKIE['access_token'])) {
        setcookie('access_token', '', time() - 3600, '/');
        unset($_COOKIE['access_token']);
    }
    if (isset($_COOKIE['utm_source'])) {
        setcookie('utm_source', '', time() - 3600, '/');
        unset($_COOKIE['utm_source']);
    }

	
	return wg_json_response(200, ['login_url' => bsc_game_url_sso(), 'logout_sso' => bsc_game_url_sso_logout()], __('Đăng xuất thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

/**
 * Lấy danh sách huy hiệu chưa được xem (viewed = 0) và đánh dấu đã xem
 *
 * Flow xử lý:
 * 1. Validate session và user
 * 2. Query huy hiệu chưa xem (viewed = 0)
 * 3. Format thông tin chi tiết của từng huy hiệu
 * 4. Update trạng thái viewed = 1 (trong transaction)
 * 5. Trả về danh sách huy hiệu đã format
 *
 * @param WP_REST_Request $request Đối tượng request
 * @return WP_REST_Response Danh sách huy hiệu chưa xem hoặc lỗi
 */
function game_get_unviewed_badges(WP_REST_Request $request)
{
	global $wpdb;
	
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	try {
		// ===== SECURITY: Kiểm tra session SSO =====
		$current_user = game_sso_require_session();
		if (is_wp_error($current_user) || empty($current_user['id'])) {
			return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		$user_id = absint($current_user['id']);
		if ($user_id <= 0) {
			return wg_json_response(400, [], __('ID người dùng không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// ===== KIỂM TRA USER TỒN TẠI =====
		$prefix = $wpdb->prefix . 'game_';
		$user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, avatar_url, status FROM {$prefix}users WHERE id = %d",
				$user_id
			),
			ARRAY_A
		);
		
		if (!$user) {
			return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		if ((int)$user['status'] === 0) {
			return wg_json_response(403, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// ===== LẤY DANH SÁCH HUY HIỆU CHƯA XEM =====
		$unviewed_badge_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT badge_post_id, awarded_at
				 FROM {$prefix}user_badges
				 WHERE user_id = %d AND viewed = 0
				 ORDER BY awarded_at DESC",
				$user_id
			),
			ARRAY_A
		);
		if (empty($unviewed_badge_rows)) {
			return wg_json_response(200, [], __('Không có huy hiệu mới chưa xem.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		$badge_post_ids = array_filter(
			array_map(function ($row) {
				$id = absint($row['badge_post_id'] ?? 0);
				return $id > 0 ? $id : null;
			}, $unviewed_badge_rows)
		);
		
		if (empty($badge_post_ids)) {
			return wg_json_response(200, [], __('Không tìm thấy ID huy hiệu hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// ===== LẤY THÔNG TIN CHI TIẾT CỦA CÁC HUY HIỆU =====
		$unviewed_badges = [];
		
		foreach ($badge_post_ids as $badge_post_id) {
			$badge_post = get_post($badge_post_id);
			if (!$badge_post || $badge_post->post_type !== 'game_badges' || $badge_post->post_status !== 'publish') {
				continue;
			}
			
			$badge_data = game_format_current_badge($badge_post_id, 0, 0);
			
			if (!empty($badge_data) && is_array($badge_data)) {
				$unviewed_badges[] = $badge_data;
			}
		}
		
		// ===== CẬP NHẬT TRẠNG THÁI VIEWED = 1 =====
		if (!empty($badge_post_ids)) {
			$placeholders = implode(',', array_fill(0, count($badge_post_ids), '%d'));
			
			$update_query = $wpdb->prepare(
				"UPDATE {$prefix}user_badges
				 SET viewed = 1
				 WHERE user_id = %d
				 AND badge_post_id IN ($placeholders)",
				array_merge([$user_id], $badge_post_ids)
			);
			// Comment để test
			 $updated_rows = $wpdb->query($update_query);
		}
		
		// ===== TRẢ VỀ KẾT QUẢ =====
		return wg_json_response(200, $unviewed_badges, __('Success', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Throwable $e) {
		return wg_json_response(500, [], __('Error: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN));
	}
}


function game_get_user_voucher_redemptions_history(WP_REST_Request $request)
{
	global $wpdb;

	/* =========================
	   SECURITY & AUTH
	========================= */
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(
			401,
			['login_url' => bsc_game_url_sso()],
			__('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN)
		);
	}

	$user_id = absint($current_user['id']);
	$prefix  = $wpdb->prefix . 'game_';

	/* =========================
	   KIỂM TRA USER
	========================= */
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);

	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	if ((int)$user['status'] === 0) {
		return wg_json_response(403, [], __('Tài khoản của bạn đã bị khóa.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	/* =========================
	   PAGINATION THEO NGÀY
	========================= */
	$page     = max(1, (int)($request->get_param('page') ?? 1));
	$per_page = min(max((int)($request->get_param('per_page') ?? 5), 1), 30); // số NGÀY / page

	/* =========================
	   LẤY TOÀN BỘ LEDGER (KHÔNG LIMIT)
	========================= */
	$items = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT 
				l.id,
				l.delta,
				l.ref_id,
				l.created_at,
				uvr.voucher_post_id,
				uvr.redeemed_at
			 FROM {$prefix}user_points_ledger l
			 LEFT JOIN {$prefix}user_voucher_redemptions uvr
			        ON l.ref_id = uvr.id
			 WHERE l.user_id = %d
			   AND l.ref_type = 'VOUCHER'
			 ORDER BY l.created_at DESC",
			$user_id
		),
		ARRAY_A
	);

	/* =========================
	   GROUP THEO NGÀY
	========================= */
	$rows_by_date = [];

	foreach ($items as $row) {
		if (empty($row['created_at'])) {
			continue;
		}

		$date_key = (new DateTime($row['created_at']))->format('Y-m-d');

		if (!isset($rows_by_date[$date_key])) {
			$rows_by_date[$date_key] = [];
		}

		$rows_by_date[$date_key][] = $row;
	}

	/* =========================
	   GOM THEO voucher_post_id (GIỮ LOGIC CŨ)
	========================= */
	$groups_map = [];

	foreach ($rows_by_date as $date_key => $rows) {
		$groups_map[$date_key] = [
			'date' => $date_key,
			'total_points_used' => 0,
			'count' => count($rows),
			'entries_map' => [],
		];

		// Đếm số lần xuất hiện của mỗi voucher_post_id trong ngày
		$voucher_counts = [];
		foreach ($rows as $r) {
			if ($r['voucher_post_id'] !== null) {
				$vp = (int)$r['voucher_post_id'];
				if (!isset($voucher_counts[$vp])) {
					$voucher_counts[$vp] = 0;
				}
				$voucher_counts[$vp]++;
			}
		}

		foreach ($rows as $row) {
			$points_used = max(0, -(int)$row['delta']);

			// Giữ nguyên logic của bạn:
			// Nếu voucher_post_id xuất hiện > 1 lần trong ngày → gom
			if (
				$row['voucher_post_id'] !== null &&
				(($voucher_counts[(int)$row['voucher_post_id']] ?? 0) > 1)
			) {
				$entry_key = 'v_' . (int)$row['voucher_post_id'];
			} else {
				$entry_key = 'l_' . (int)$row['id'];
			}

			if (!isset($groups_map[$date_key]['entries_map'][$entry_key])) {
				$groups_map[$date_key]['entries_map'][$entry_key] = [
					'id' => (int)$row['id'],
					'delta' => (int)$row['delta'],
					'points_used' => $points_used,
					'voucher_redemption_id' => $row['ref_id'] ? (int)$row['ref_id'] : null,
					'voucher_post_id' => $row['voucher_post_id'] ? (int)$row['voucher_post_id'] : null,
					'voucher_name' => $row['voucher_post_id'] ? get_the_title((int)$row['voucher_post_id']) : null,
					'redeemed_at' => $row['redeemed_at'],
					'created_at' => $row['created_at'],
					'qty' => 1,
				];
			} else {
				// Nếu đã tồn tại entry (đang bị gom)
				$existing = &$groups_map[$date_key]['entries_map'][$entry_key];
				$existing['qty']++;
				$existing['points_used'] += $points_used;

				// Lấy record mới nhất làm đại diện
				if (strtotime($row['created_at']) > strtotime($existing['created_at'])) {
					$existing['id'] = (int)$row['id'];
					$existing['created_at'] = $row['created_at'];
					$existing['redeemed_at'] = $row['redeemed_at'] ?? $existing['redeemed_at'];
				}
			}

			$groups_map[$date_key]['total_points_used'] += $points_used;
		}
	}

	/* =========================
	   entries_map → entries + SORT
	========================= */
	foreach ($groups_map as &$group) {
		$entries = array_values($group['entries_map']);
		usort($entries, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
		$group['entries'] = $entries;
		unset($group['entries_map']);
	}
	unset($group);

	/* =========================
	   SORT NGÀY DESC
	========================= */
	uksort($groups_map, fn($a, $b) => strcmp($b, $a));

	/* =========================
	   PHÂN TRANG THEO NGÀY
	========================= */
	$all_days   = array_values($groups_map);
	$total_days = count($all_days);

	$groups = array_slice(
		$all_days,
		($page - 1) * $per_page,
		$per_page
	);

	/* =========================
	   RESPONSE
	========================= */
	return wg_json_response(
		200,
		[
			'page'        => $page,
			'per_page'   => $per_page, // số ngày / page
			'total_days' => $total_days,
			'groups'     => $groups,
		],
		__('Lấy lịch sử đổi voucher thành công.', WG_GAME_PLUGIN_TEXTDOMAIN)
	);
}

function game_get_user_points_added_history(WP_REST_Request $request)
{
	global $wpdb;

	/* =========================
	   SECURITY & AUTH
	========================= */
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(
			401,
			['login_url' => bsc_game_url_sso()],
			__('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN)
		);
	}

	$user_id = absint($current_user['id']);
	$prefix  = $wpdb->prefix . 'game_';

	/* =========================
	   KIỂM TRA USER
	========================= */
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);

	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	if ((int)$user['status'] === 0) {
		return wg_json_response(403, [], __('Tài khoản của bạn đã bị khóa.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	/* =========================
	   PAGINATION THEO NGÀY
	========================= */
	$page     = max(1, (int)($request->get_param('page') ?? 1));
	$per_page = min(max((int)($request->get_param('per_page') ?? 5), 1), 30); // số NGÀY / page

	/* =========================
	   LẤY TOÀN BỘ LEDGER (KHÔNG LIMIT) - chỉ các bản ghi tăng điểm
	========================= */
	$items = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				l.id,
				l.delta,
				l.ref_type,
				l.ref_id,
				l.created_at,
				ups.current_stage as session_stage,
				ups.started_at as session_started_at,
				ub.badge_post_id,
				ups.correct_count as session_correct,
			ups.questions_count as session_questions,
			ub.awarded_at as badge_awarded_at
			 FROM {$prefix}user_points_ledger l
			 LEFT JOIN {$prefix}users_play_sessions ups
				ON (l.ref_type = 'SESSION' AND l.ref_id = ups.id)
			 LEFT JOIN {$prefix}user_badges ub
				ON (l.ref_type = 'BADGE' AND l.ref_id = ub.id)
			 WHERE l.user_id = %d
			   AND l.ref_type IN ('SESSION','BADGE')
			   AND l.delta > 0
			 ORDER BY l.created_at DESC",
			$user_id
		),
		ARRAY_A
	);

	/* =========================
	   GROUP THEO NGÀY
	========================= */
	$rows_by_date = [];
	foreach ($items as $row) {
		if (empty($row['created_at'])) continue;
		$date_key = (new DateTime($row['created_at']))->format('Y-m-d');
		$rows_by_date[$date_key][] = $row;
	}

	$groups_map = [];
	foreach ($rows_by_date as $date_key => $rows) {
		$entries = [];
		$total_points = 0;

		foreach ($rows as $row) {
			$points_added = max(0, (int)$row['delta']);

			$entry = [
				'id' => (int)$row['id'],
				'delta' => (int)$row['delta'],
				'points_added' => $points_added,
				'ref_type' => $row['ref_type'],
				'ref_id' => $row['ref_id'] ? (int)$row['ref_id'] : null,
				'created_at' => $row['created_at'],
			];

			// Thêm thông tin phụ theo loại
			if ($row['ref_type'] === 'SESSION') {
				$entry['session_stage'] = $row['session_stage'] !== null ? (int)$row['session_stage'] : null;
				$entry['session_started_at'] = $row['session_started_at'] ?? null;
				$entry['session_correct'] = isset($row['session_correct']) ? (int)$row['session_correct'] : null;
				$entry['session_questions'] = isset($row['session_questions']) ? (int)$row['session_questions'] : null;
				$entry['session_summary'] = (!is_null($entry['session_correct']) && !is_null($entry['session_questions']))
					? sprintf(__('Trả lời đúng %d/%d câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN), $entry['session_correct'], $entry['session_questions'])
					: null;
			} elseif ($row['ref_type'] === 'BADGE') {
				$entry['badge_post_id'] = $row['badge_post_id'] ? (int)$row['badge_post_id'] : null;
				$entry['badge_name'] = $row['badge_post_id'] ? get_the_title((int)$row['badge_post_id']) : null;
				$entry['badge_awarded_at'] = $row['badge_awarded_at'] ?? null;
				$entry['achievement_text'] = $entry['badge_name'] ? sprintf(__('Đạt được thành tựu "%s"', WG_GAME_PLUGIN_TEXTDOMAIN), $entry['badge_name']) : null;
			}

			$entries[] = $entry;
			$total_points += $points_added;
		}

		// Sắp xếp entries theo created_at giảm dần
		usort($entries, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

		$groups_map[$date_key] = [
			'date' => $date_key,
			'total_points_added' => $total_points,
			'count' => count($entries),
			'entries' => $entries,
		];
	}

	// Chuyển entries_map -> entries và sắp xếp (nếu có)
	foreach ($groups_map as &$group) {
		if (isset($group['entries_map']) && is_array($group['entries_map'])) {
			$entries = array_values($group['entries_map']);
			usort($entries, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
			$group['entries'] = $entries;
			unset($group['entries_map']);
		}
	}
	unset($group);

	// Sắp xếp ngày giảm dần
	uksort($groups_map, fn($a, $b) => strcmp($b, $a));

	// Phân trang theo ngày
	$all_days = array_values($groups_map);
	$total_days = count($all_days);
	$groups = array_slice($all_days, ($page - 1) * $per_page, $per_page);

	// Trả về
	return wg_json_response(
		200,
		[
			'page'        => $page,
			'per_page'    => $per_page,
			'total_days'  => $total_days,
			'groups'      => $groups,
		],
		__('Lấy lịch sử các lần được cộng điểm thành công.', WG_GAME_PLUGIN_TEXTDOMAIN)
	);
}