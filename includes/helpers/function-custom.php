<?php
if (!defined('ABSPATH')) exit;

include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/statistics.php';
include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/user-trend.php';
include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/award-status.php';
include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/player-stats.php';
include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/user-access.php';
include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/user-detail.php';
include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/settings-logger.php';
include_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/voucher-list.php';


// security

function game_rest_perm_cb($request)
{
	// Code dev - bypass nonce check
	// return true;

	// Code production
	$nonce = $request->get_header('X-WP-Nonce');
	if (!$nonce) $nonce = $request->get_param('_wpnonce');
	if (!$nonce || !wp_verify_nonce($nonce, 'wp_game_rest')) {
		return false;
	}
	return true;
}


// danh sách lịch sử lượt chơi

/**
 * Hàm lấy dữ liệu lịch sử lượt chơi với filter
 *
 * @param int $page Trang hiện tại
 * @param int $per_page Số item/trang
 * @param string $date_from Ngày bắt đầu (Y-m-d)
 * @param string $date_to Ngày kết thúc (Y-m-d)
 * @param string $search Tìm kiếm tên hoặc ID
 * @return array Dữ liệu phân trang
 */
function game_bsc_get_play_sessions_data($page = 1, $per_page = 20, $date_from = '', $date_to = '', $search = '')
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$page = max(1, (int)$page);
	$per_page = max(1, min((int)$per_page, 100));
	$offset = ($page - 1) * $per_page;
	
	// Xây dựng WHERE clause
	$where_clauses = [];
	$params = [];
	
	// Filter theo khoảng ngày
	if (!empty($date_from)) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) return ['status' => 'error', 'message' => 'date_from không hợp lệ.'];
		$where_clauses[] = "DATE(ups.started_at) >= %s";
		$params[] = sanitize_text_field($date_from);
	}

	if (!empty($date_to)) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) return ['status' => 'error', 'message' => 'date_to không hợp lệ.'];
		$where_clauses[] = "DATE(ups.started_at) <= %s";
		$params[] = sanitize_text_field($date_to);
	}
	
	// Filter theo tìm kiếm (tên hoặc external_user_id)
	if (!empty($search)) {
		$where_clauses[] = "(u.name LIKE %s OR u.external_user_id LIKE %s)";
		$search_term = '%' . $wpdb->esc_like($search) . '%';
		$params[] = $search_term;
		$params[] = $search_term;
	}
	
	$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
	
	// ===== LẤY TỔNG SỐ BẢN GHI =====
	$total_sql = "SELECT COUNT(ups.id) FROM {$prefix}users_play_sessions ups
	              INNER JOIN {$prefix}users u ON ups.user_id = u.id
	              {$where_sql}";
	
	$total_query = !empty($params) ? $wpdb->prepare($total_sql, ...$params) : $total_sql;
	$total_items = (int)$wpdb->get_var($total_query);
	$total_pages = ceil($total_items / $per_page);
	
	// ===== LẤY DỮ LIỆU BẢN GHI =====
	$sql = "SELECT
		ups.id as session_id,
		ups.user_id,
		u.name as user_name,
		u.external_user_id,
		ups.started_at,
		ups.questions_count,
		ups.retries_used,
		ups.correct_count,
		COALESCE(SUM(CASE WHEN dl.outcome = 'POINT' THEN dl.points_awarded ELSE 0 END), 0) as total_points,
		COUNT(DISTINCT CASE WHEN dl.outcome = 'PIECE' THEN dl.id END) as total_pieces
	FROM {$prefix}users_play_sessions ups
	INNER JOIN {$prefix}users u ON ups.user_id = u.id
	LEFT JOIN {$prefix}drop_logs dl ON ups.id = dl.session_id
	LEFT JOIN {$prefix}pieces p ON dl.piece_id = p.id
	{$where_sql}
	GROUP BY ups.id
	ORDER BY ups.started_at DESC
	LIMIT {$offset}, {$per_page}";
	
	$query = !empty($params) ? $wpdb->prepare($sql, ...$params) : $sql;
	$sessions = $wpdb->get_results($query, ARRAY_A);
	
	// ===== FORMAT DỮ LIỆU =====
	$formatted_sessions = [];
	
	if (!empty($sessions)) {
		foreach ($sessions as $session) {
			$formatted_sessions[] = [
				'session_id' => (int)$session['session_id'],
				'user_id' => (int)$session['user_id'],
				'user_name' => sanitize_text_field($session['user_name']),
				'external_user_id' => sanitize_text_field($session['external_user_id']),
				'started_at' => $session['started_at'],
				'started_at_display' => date('d/m/Y H:i', strtotime($session['started_at'])),
				'questions_count' => (int)$session['questions_count'],
				'correct_count' => (int)$session['correct_count'],
				'retries_used' => (int)$session['retries_used'],
				'result' => (int)$session['correct_count'] . '/' . (int)$session['questions_count'],
				'total_points' => (int)$session['total_points'],
				'total_pieces' => (int)$session['total_pieces'],
				'pieces_display' => (int)$session['total_pieces'] > 0 ? '+' . (int)$session['total_pieces'] : '-'
			];
		}
	}
	
	return [
		'status' => 'success',
		'data' => $formatted_sessions,
		'pagination' => [
			'current_page' => $page,
			'per_page' => $per_page,
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'has_prev' => $page > 1,
			'has_next' => $page < $total_pages
		]
	];
}


/**
 * Tính số ngày đăng nhập liên tiếp (consecutive login days) của người dùng
 *
 * Đếm từ hôm nay trở về trước, chỉ tính các ngày làm việc (thứ 2-6)
 * Nếu có ngày nào không đăng nhập thì reset về 0
 * Logic mới: Tính theo user_login_logs thay vì users_play_sessions
 *
 * @param int $user_id ID của người dùng
 * @return int Số ngày đăng nhập liên tiếp
 */
function game_get_consecutive_play_days($user_id)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$tz = TIMEZONE;
	$today = new DateTimeImmutable('now', $tz);
	$today_str = $today->format('Y-m-d');
	
	// Lấy tất cả ngày đã đăng nhập của user từ login_logs (DESC = mới nhất trước)
	$login_dates = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT DATE(checked_at) as login_date
			 FROM {$prefix}user_login_logs
			 WHERE user_id = %d
			 AND result = 'OK'
			 ORDER BY DATE(checked_at) DESC",
			$user_id
		)
	);
	
	if (empty($login_dates)) {
		return 0;
	}
	
	// Chuyển mảng thành set để tra cứu nhanh
	$login_dates_set = array_flip($login_dates);
	
	$consecutive_count = 0;
	$current_check = new DateTimeImmutable($today_str, $tz);
	$has_started_counting = false; // Flag để bắt đầu đếm khi gặp login đầu tiên
	
	// Đếm ngược từ hôm nay về trước
	while (true) {
		$check_str = $current_check->format('Y-m-d');
		$check_dow = (int)$current_check->format('N'); // 1-7 (thứ 2 - chủ nhật)
		
		// Bỏ qua thứ 7 và chủ nhật (6, 7)
		if ($check_dow >= 6) {
			$current_check = $current_check->modify('-1 day');
			continue;
		}
		
		// Kiểm tra user có đăng nhập trong ngày này không
		if (isset($login_dates_set[$check_str])) {
			$consecutive_count++; // Tăng streak
			$has_started_counting = true; // Đã bắt đầu đếm
		} else {
			// Nếu đã bắt đầu đếm rồi mà gặp ngày không login → break
			// Nếu chưa bắt đầu đếm (chưa gặp login nào) → tiếp tục tìm
			if ($has_started_counting) {
				break;
			}
		}
		
		$current_check = $current_check->modify('-1 day'); // Lùi về 1 ngày
	}
	
	return $consecutive_count;
}

/**
 * Tính tổng số ngày đã đăng nhập (không cần liên tiếp)
 * Logic mới: Tính theo user_login_logs thay vì users_play_sessions
 *
 * @param int $user_id ID của người dùng
 * @return int Tổng số ngày đã đăng nhập
 */
function game_get_total_play_days($user_id) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy thông tin chặng hiện tại
	$game_info = game_bsc_compute_day_index_v2($user_id);
	
	// Nếu game chưa bắt đầu hoặc đã kết thúc, trả về 0
	if ($game_info['status'] !== 'ongoing') {
		return 0;
	}
	
	$stage_start = $game_info['start_date'];
	$stage_end = $game_info['end_date'];
	
	// Đếm các ngày unique T2-T6 (loại T7/CN) mà user đã đăng nhập trong kỳ game
	$total_days = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT DATE(checked_at))
			 FROM {$prefix}user_login_logs
			 WHERE user_id = %d
			 AND result = 'OK'
			 AND DATE(checked_at) BETWEEN %s AND %s
			 AND WEEKDAY(checked_at) BETWEEN 0 AND 4",
			$user_id,
			$stage_start,
			$stage_end
		)
	);
	
	return (int)$total_days;
}

/**
 * Kiểm tra xem thời điểm hiện tại có nằm trong khung giờ và ngày chơi game không
 * Kiểm tra: khoảng ngày, khung giờ trong ngày, và không cho phép chơi thứ 7 & chủ nhật
 *
 * @return array ['allowed' => bool, 'message' => string]
 */
function game_check_play_time_allowed() {
	$start_date = get_option('game_bsc_start_date', date('Y-m-d'));
	$end_date = get_option('game_bsc_end_date', date('Y-m-d'));
	$start_time = get_option('game_bsc_daily_start_time', '09:00');
	$end_time = get_option('game_bsc_daily_end_time', '15:00');
	$day_allowed_to_play_game_after_period_ends = (int)get_option('game_bsc_day_allowed_to_play_game_after_period_ends', 0);
	
	// Lấy thời gian hiện tại theo timezone
	$tz = TIMEZONE;
	$now = new DateTimeImmutable('now', $tz);
	$current_date = $now->format('Y-m-d');
	$current_time = $now->format('H:i');
	$current_dow = (int)$now->format('N'); // 1=Monday, 7=Sunday
	
	$message = '';
	$is_allowed = true;
	
	// Kiểm tra thứ 7 và chủ nhật (6, 7) - không được chơi
	if ($current_dow >= 6) {
		$is_allowed = false;
		$message = sprintf(
			__('Hãy quay lại chơi trong khung giờ từ %s đến %s, từ thứ 2 - thứ 6 hàng tuần để thu thập quà tặng từ BSC!', WG_GAME_PLUGIN_TEXTDOMAIN),
			$start_time,
			$end_time
		);
	}
	// Kiểm tra ngày: phải nằm trong khoảng start_date đến end_date
	else if ($current_date < $start_date || $current_date > $end_date) {
		// Nếu vượt quá end_date nhưng không quá 2 ngày, cho phép chơi
		if ($current_date > $end_date) {
			$end_date_obj = new DateTimeImmutable($end_date, $tz);
			$days_after_end = $end_date_obj->diff($now)->days;
			
			if ($days_after_end <= $day_allowed_to_play_game_after_period_ends) {
				if ($current_time < $start_time || $current_time > $end_time) {
					$is_allowed = false;
					$message = sprintf(
						__('Hãy quay lại chơi trong khung giờ từ %s đến %s, từ thứ 2 - thứ 6 hàng tuần để thu thập quà tặng từ BSC!', WG_GAME_PLUGIN_TEXTDOMAIN),
						$start_time,
						$end_time
					);
				} else {
					$is_allowed = true;
				}
			} else {
				$is_allowed = false;
				$message = sprintf(
					__('Chương trình đã kết thúc. Bạn hãy theo dõi BSC để cập nhập thông tin các chương trình mới nhất nhé!', WG_GAME_PLUGIN_TEXTDOMAIN),
					$start_time,
					$end_time
				);
			}
		} else {
			$is_allowed = false;
			$message = sprintf(
				__('Chương trình đã kết thúc. Bạn hãy theo dõi BSC để cập nhập thông tin các chương trình mới nhất nhé!', WG_GAME_PLUGIN_TEXTDOMAIN),
				$start_time,
				$end_time
			);
		}
	}
	// Kiểm tra giờ: phải nằm trong khoảng start_time đến end_time
	else if ($current_time < $start_time || $current_time > $end_time) {
		$is_allowed = false;
		$message = sprintf(
			__('Hãy quay lại chơi trong khung giờ từ %s đến %s, từ thứ 2 - thứ 6 hàng tuần để thu thập quà tặng từ BSC!', WG_GAME_PLUGIN_TEXTDOMAIN),
			$start_time,
			$end_time
		);
	}
	
	 return [
	 	'allowed' => $is_allowed,
	 	'message' => $message,
	 ];
	// dev

}



// nhiem vu

function getEndpointFromMissionCode($missionCode)
{
	$missionMap = [
		MTRADER_LOGIN_CODE => MTRADER_LOGIN_URL,
		EKYC_COMPLETE_CODE => EKYC_COMPLETE_URL,
		OPEN_BIDV_CODE => OPEN_BIDV_URL,
		OPEN_NEW_ACCOUNT_CODE => OPEN_NEW_ACCOUNT_URL,
		FIRST_DEPOSIT_CODE => FIRST_DEPOSIT_URL,
		OPEN_BSC_DERIVATIVE_ACCOUNT_CODE => OPEN_BSC_DERIVATIVE_ACCOUNT_URL,
		OPEN_MARGIN_ACCOUNT_CODE => OPEN_MARGIN_ACCOUNT_URL,
		USE_BSC_BUY_PACKAGE_CODE => USE_BSC_BUY_PACKAGE_URL,
		USE_MR90_PACKAGE_CODE => USE_MR90_PACKAGE_URL,
		TRADE_100M_VND_CODE => TRADE_100M_VND_URL,
	];
	
	
	
	$missions = get_option('game_bsc_tasks');
	$base_url = get_option('game_bsc_api_base_url');
	if (is_array($missions) && isset($base_url) && trim($base_url) !== '') {
		$reward_spins = intval($missions[$missionCode]['reward_spins'] ?? 0);
		$end_point = $missionMap[$missionCode];
		
		// push to array 2 option
		$array_urls = [
			'base_url' => $base_url,
			'end_point' => $end_point,
			'reward_spins' => $reward_spins,
		];
		if($missionCode === TRADE_100M_VND_CODE) {
			if(isset($missions[$missionCode]['amount_required']) && $missions[$missionCode]['amount_required'] > 0) {
				$array_urls['amount_required'] = $missions[$missionCode]['amount_required'];
			} else {
				$array_urls['amount_required'] = TRADE_100M_VND_DEFAULT_VALUE;
			}
		} else if($missionCode === FIRST_DEPOSIT_CODE) {
			$array_urls['open_new_account_base_url'] = trim($missions[OPEN_NEW_ACCOUNT_CODE]['api_url'] ?? '');
			$array_urls['open_new_account_end_point'] = $missionMap[OPEN_NEW_ACCOUNT_CODE];
		}
		return $array_urls;
	}
	
	return null;
}

function getRequiredParamsForMission($missionCode)
{
	$paramsMap = [
		DAILY_LOGIN_CODE => [],
		MTRADER_LOGIN_CODE => ['clientID', 'custodycd', 'loginTime'],
		EKYC_COMPLETE_CODE => ['custodycd'],
		OPEN_BIDV_CODE => ['custodycd'],
		OPEN_NEW_ACCOUNT_CODE => ['custodycd'],
		FIRST_DEPOSIT_CODE => ['custodycd'],
		OPEN_BSC_DERIVATIVE_ACCOUNT_CODE => ['custodycd', 'dStart', 'dEnd'],
		OPEN_MARGIN_ACCOUNT_CODE => ['custodycd', 'dStart', 'dEnd'],
		USE_BSC_BUY_PACKAGE_CODE => ['custodycd', 'dStart', 'dEnd'],
		USE_MR90_PACKAGE_CODE => ['custodycd', 'dStart', 'dEnd'],
		TRADE_100M_VND_CODE => ['custodycd', 'txdate'],
	];
	
	return isset($paramsMap[$missionCode]) ? $paramsMap[$missionCode] : [];
}

/**
 * Kiểm tra tham số có đầy đủ không
 */
function validateParams($missionCode, $params)
{
	$requiredParams = getRequiredParamsForMission($missionCode);
	
	if (empty($requiredParams)) {
		return true;
	}
	
	foreach ($requiredParams as $param) {
		if (!isset($params[$param]) || $params[$param] === '') {
			return false;
		}
	}
	
	return true;
}


function executeMissionApi($missionCode, $params = [])
{

	$url = getEndpointFromMissionCode($missionCode);
	if (!$url) {
		return null;
	}
	$apiBaseUrl = $url['base_url'];
	$endpoint = $url['end_point'];
	
	// Kiểm tra tham số
	if (!validateParams($missionCode, $params)) {
		return null;
	}
	
	// Lấy danh sách tham số bắt buộc
	$requiredParams = getRequiredParamsForMission($missionCode);
	
	// Chỉ lấy tham số được phép
	$filteredParams = [];
	foreach ($requiredParams as $param) {
		if (isset($params[$param])) {
			$filteredParams[$param] = $params[$param];
		}
	}
	
	// Xây dựng query string
	$queryString = '';
	if (!empty($filteredParams)) {
		$queryString = '?' . http_build_query($filteredParams);
	}
	
	// Xây dựng full URL
	$url = $apiBaseUrl . $endpoint . $queryString;
	
	// Gọi API
	$response = callApiGame($url, false, 'POST');
	
	return $response;
}
function callApiGame($url, $data = false, $method = "GET", $headers = array())
{
	if (!is_array($headers)) {
		$headers = array();
	}

	$has_content_type_header = false;
	foreach ($headers as $header) {
		if (stripos((string) $header, 'Content-Type:') === 0) {
			$has_content_type_header = true;
			break;
		}
	}

	if (!$has_content_type_header) {
		$headers[] = 'Content-Type: application/x-www-form-urlencoded';
	}

	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => $method,
		CURLOPT_POSTFIELDS => $data,
		CURLOPT_HTTPHEADER => $headers,
	));

	$response = curl_exec($curl);
	$error = curl_error($curl); // Lấy lỗi nếu có
	$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Mã HTTP trả về
	curl_close($curl);

	if ($error) {
		return null;
	}

	// Nếu mã HTTP không phải 2xx, ghi log lỗi
	if ($http_code < 200 || $http_code >= 300) {
		return null;
	}

	return json_decode($response);
}
// Hàm kiểm tra user đã thực hiện nhiệm vụ hiện tại chưa
function user_complete_mission($user_id, $misson_code) {
	global $wpdb;
	$table_mission = $wpdb->prefix .'game_user_mission_logs';
	
	$daily_task_codes = [
		DAILY_LOGIN_CODE,
		MTRADER_LOGIN_CODE,
		TRADE_100M_VND_CODE
	];
	
	$is_daily_task = in_array($misson_code, $daily_task_codes);
	
	if ($is_daily_task) {
		$today = game_now('date');
		$mission_log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table_mission}
                 WHERE user_id = %d
                 AND mission_code = %s
                 AND DATE(completed_at) = %s
                 LIMIT 1",
				$user_id,
				$misson_code,
				$today
			)
		);
	} else {
		$mission_log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table_mission}
                 WHERE user_id = %d
                 AND mission_code = %s
                 LIMIT 1",
				$user_id,
				$misson_code
			)
		);
	}
	
	return $mission_log ? true : false;
}

/**
 * Chuẩn hóa nhãn nhiệm vụ theo mission_code.
 *
 * @param string $mission_code
 * @return string
 */
function game_bsc_get_mission_label($mission_code = '') {
	$tasks = get_option('game_bsc_tasks', []);
	if (is_array($tasks) && isset($tasks[$mission_code]['title']) && trim($tasks[$mission_code]['title']) !== '') {
		return trim($tasks[$mission_code]['title']);
	}

	$labels = [
		DAILY_LOGIN_CODE => 'Đăng nhập hàng ngày',
		MTRADER_LOGIN_CODE => 'Đăng nhập MTrader',
		EKYC_COMPLETE_CODE => 'Hoàn thành eKYC',
		OPEN_BIDV_CODE => 'Mở tài khoản BIDV',
		OPEN_NEW_ACCOUNT_CODE => 'Mở tài khoản mới',
		FIRST_DEPOSIT_CODE => 'Nạp tiền lần đầu',
		OPEN_BSC_DERIVATIVE_ACCOUNT_CODE => 'Mở tài khoản phái sinh',
		OPEN_MARGIN_ACCOUNT_CODE => 'Mở tài khoản Margin',
		USE_BSC_BUY_PACKAGE_CODE => 'Sử dụng gói BSC BUY',
		USE_MR90_PACKAGE_CODE => 'Sử dụng gói MR90',
		TRADE_100M_VND_CODE => 'Giao dịch từ 100M',
	];

	return $labels[$mission_code] ?? ($mission_code ?: '-');
}

/**
 * Lấy dữ liệu biến động lịch sử lượt chơi từ play_credit_ledger.
 *
 * @param int $page
 * @param int $per_page
 * @param string $date_from
 * @param string $date_to
 * @param string $search
 * @return array
 */
function game_bsc_get_play_credit_ledger_data($page = 1, $per_page = 20, $date_from = '', $date_to = '', $search = '', $status = 'all') {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';

	$page = max(1, (int)$page);
	$per_page = max(1, min((int)$per_page, 100));
	$offset = ($page - 1) * $per_page;

	$where_clauses = [
		"NOT (l.ref_type = 'MISSION' AND l.delta = 0)"
	];
	$params = [];

	if (!empty($date_from)) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) return ['status' => 'error', 'message' => 'date_from không hợp lệ.'];
		$where_clauses[] = 'DATE(l.created_at) >= %s';
		$params[] = sanitize_text_field($date_from);
	}

	if (!empty($date_to)) {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) return ['status' => 'error', 'message' => 'date_to không hợp lệ.'];
		$where_clauses[] = 'DATE(l.created_at) <= %s';
		$params[] = sanitize_text_field($date_to);
	}

	if (!empty($search)) {
		$where_clauses[] = '(u.name LIKE %s OR u.external_user_id LIKE %s)';
		$search_term = '%' . $wpdb->esc_like($search) . '%';
		$params[] = $search_term;
		$params[] = $search_term;
	}

	$status = sanitize_text_field($status);
	if ($status === 'plus') {
		$where_clauses[] = 'l.delta > 0';
	} elseif ($status === 'minus') {
		$where_clauses[] = 'l.delta < 0';
	}

	$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

	$total_sql = "SELECT COUNT(l.id)
		FROM {$prefix}play_credit_ledger l
		INNER JOIN {$prefix}users u ON l.user_id = u.id
		{$where_sql}";
	$total_query = !empty($params) ? $wpdb->prepare($total_sql, ...$params) : $total_sql;
	$total_items = (int)$wpdb->get_var($total_query);
	$total_pages = max(1, (int)ceil($total_items / $per_page));

	$data_sql = "SELECT
		l.id AS ledger_id,
		l.user_id,
		u.name AS user_name,
		u.external_user_id,
		l.delta,
		l.ref_type,
		l.ref_id,
		l.created_at,
		ml.mission_code,
		s.correct_count,
		s.questions_count
	FROM {$prefix}play_credit_ledger l
	INNER JOIN {$prefix}users u ON l.user_id = u.id
	LEFT JOIN {$prefix}user_mission_logs ml ON (l.ref_type = 'MISSION' AND l.ref_id = ml.id)
	LEFT JOIN {$prefix}users_play_sessions s ON (l.ref_type = 'SESSION' AND l.ref_id = s.id)
	{$where_sql}
	ORDER BY l.created_at DESC, l.id DESC
	LIMIT {$offset}, {$per_page}";

	$data_query = !empty($params) ? $wpdb->prepare($data_sql, ...$params) : $data_sql;
	$rows = $wpdb->get_results($data_query, ARRAY_A);

	$search_only_params = [];
	$search_user_sql = '';
	if (!empty($search)) {
		$search_user_sql = 'WHERE (u.name LIKE %s OR u.external_user_id LIKE %s)';
		$search_user_term = '%' . $wpdb->esc_like($search) . '%';
		$search_only_params[] = $search_user_term;
		$search_only_params[] = $search_user_term;
	}

	$played_sql = "SELECT COALESCE(ABS(SUM(CASE WHEN l.delta < 0 THEN l.delta ELSE 0 END)), 0)
		FROM {$prefix}play_credit_ledger l
		INNER JOIN {$prefix}users u ON l.user_id = u.id
		{$where_sql}";
	$played_query = !empty($params) ? $wpdb->prepare($played_sql, ...$params) : $played_sql;
	$total_played_turns = (int)$wpdb->get_var($played_query);

	$remaining_sql = "SELECT COALESCE(SUM(b.balance), 0)
		FROM {$prefix}play_credit_balances b
		INNER JOIN {$prefix}users u ON b.user_id = u.id
		{$search_user_sql}";
	$remaining_query = !empty($search_only_params) ? $wpdb->prepare($remaining_sql, ...$search_only_params) : $remaining_sql;
	$total_remaining_turns = (int)$wpdb->get_var($remaining_query);

	$items = array_map(function ($row) {
		$delta = (int)$row['delta'];
		$source_label = $row['ref_type'] === 'MISSION' ? 'Nhiệm vụ' : 'Phiên chơi';

		if ($row['ref_type'] === 'MISSION') {
			$detail = game_bsc_get_mission_label((string)$row['mission_code']);
		} elseif ($row['ref_type'] === 'SESSION') {
			$correct = isset($row['correct_count']) ? (int)$row['correct_count'] : 0;
			$total = isset($row['questions_count']) ? (int)$row['questions_count'] : 0;
			$detail = 'KQ: ' . $correct . '/' . $total;
		} else {
			$detail = '-';
		}

		return [
			'ledger_id' => (int)$row['ledger_id'],
			'user_id' => (int)$row['user_id'],
			'user_name' => sanitize_text_field($row['user_name'] ?? ''),
			'external_user_id' => sanitize_text_field($row['external_user_id'] ?? ''),
			'delta' => $delta,
			'delta_display' => $delta > 0 ? ('+' . $delta) : (string)$delta,
			'created_at' => (string)$row['created_at'],
			'created_at_display' => !empty($row['created_at']) ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '-',
			'ref_type' => (string)$row['ref_type'],
			'ref_id' => isset($row['ref_id']) ? (int)$row['ref_id'] : 0,
			'source_label' => $source_label,
			'detail' => $detail,
		];
	}, $rows ?: []);

	return [
		'status' => 'success',
		'data' => $items,
		'summary' => [
			'total_played_turns' => $total_played_turns,
			'total_remaining_turns' => $total_remaining_turns,
		],
		'pagination' => [
			'current_page' => $page,
			'per_page' => $per_page,
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'has_prev' => $page > 1,
			'has_next' => $page < $total_pages,
		],
	];
}