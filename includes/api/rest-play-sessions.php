<?php
if (!defined('ABSPATH')) exit;

/**
 * REST API để lấy lịch sử chơi game của user
 * Bao gồm 3 endpoint:
 * 1. API 1: Lịch sử chơi game (tóm tắt theo ngày) - CÓ PHÂN TRANG
 * 2. API 2: Chi tiết câu hỏi trong một phiên chơi
 * 3. API 3: Lịch sử biến động lượt chơi theo user (cộng/trừ lượt, lý do, thời gian chi tiết)
 */

add_action('rest_api_init', function () {
	// API 1: Lịch sử chơi game
	register_rest_route(NS, '/play-session-history', array(
		'methods' => 'GET',
		'callback' => 'game_get_play_session_history',
		'permission_callback' => '__return_true',
		'args' => array(
			'page' => array(
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				},
				'default' => 1
			),
			'per_page' => array(
				'validate_callback' => function($param) {
					$per_page = (int)$param;
					return $per_page > 0 && $per_page <= 100;
				},
				'default' => 5
			),
		),
	));

	// API 2: Chi tiết câu hỏi trong một phiên
	register_rest_route(NS, '/play-session/(?P<session_id>\d+)/questions', array(
		'methods' => 'GET',
		'callback' => 'game_get_session_questions_detail',
		'permission_callback' => '__return_true',
		'args' => array(
			'session_id' => array(
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				}
			),
		),
	));
});

// =========================================================================
// API 3: Đăng ký endpoint lịch sử biến động lượt chơi
// =========================================================================
add_action('rest_api_init', function () {
	/**
	 * GET /wp-json/game-bsc/play-credit-history
	 *
	 * Trả về lịch sử biến động lượt chơi của user đang đăng nhập:
	 * - Tổng lượt đã nhận, đã chơi, còn lại
	 * - Danh sách chi tiết từng biến động: cộng/trừ, số lượt, lý do, thời gian (ngày giờ phút giây)
	 *
	 * @query page     int  Trang hiện tại, mặc định 1
	 * @query per_page int  Số bản ghi/trang, mặc định 20, tối đa 100
	 */
	register_rest_route(NS, '/play-credit-history', array(
		'methods'             => 'GET',
		'callback'            => 'game_get_play_credit_history',
		'permission_callback' => '__return_true',
		'args'                => array(
			'page'     => array(
				'validate_callback' => function ($p) { return is_numeric($p) && (int)$p > 0; },
				'default'           => 1,
			),
			'per_page' => array(
				'validate_callback' => function ($p) { $v = (int)$p; return $v > 0 && $v <= 100; },
				'default'           => 20,
			),
		),
	));
});

/**
 * API 1: Lấy lịch sử chơi game của user (tóm tắt theo ngày) - CÓ PHÂN TRANG
 * Endpoint: /wp-json/game-bsc/play-session-history/{user_id}?page=1&per_page=5
 *
 * Tối ưu:
 * - Sử dụng JOIN thay vì lặp lấy dữ liệu
 * - Group by date để giảm loop
 * - Cache query results
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_get_play_session_history(WP_REST_Request $request) {
	global $wpdb;
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, [], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$user_id = absint($current_user['id']);

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

	if ($user['status'] == 0) {
		return wg_json_response(404, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== KIỂM TRA QUYỀN TRUY CẬP =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, [], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	if (absint($current_user['id']) !== $user_id) {
		return wg_json_response(403, [], __('Bạn chỉ có thể xem lịch sử của chính mình.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== PAGINATION =====
	$page = max(1, absint($request->get_param('page') ?? 1));
	$per_page = absint($request->get_param('per_page') ?? 10);

	// ===== CLEANUP: XÓA CÁC PHIÊN CHƠI KHÔNG CÓ CÂU TRẢ LỜI =====
	// Xóa các bản ghi users_play_sessions của người dùng nếu không có câu trả lời tương ứng trong users_session_answers
	$delete_query = $wpdb->prepare(
		"DELETE ps
		 FROM {$prefix}users_play_sessions ps
		 LEFT JOIN {$prefix}users_session_answers usa ON ps.id = usa.session_id
		 WHERE ps.user_id = %d AND usa.id IS NULL",
		$user_id
	);
	// Thực hiện xóa (nếu có)
	$wpdb->query($delete_query);

	// ===== LẤY TỔNG SỐ LƯỢT CHƠI =====
	$total_items = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$prefix}users_play_sessions WHERE user_id = %d",
			$user_id
		)
	);

	if ($total_items == 0) {
		return wg_json_response(200, array(
			'user' => array(
				'id' => (int)$user['id'],
				'name' => sanitize_text_field($user['name']),
				'avatar_url' => esc_url($user['avatar_url'] ?? '')
			),
			'sessions' => array(),
			'pagination' => array(
				'current_page' => 1,
				'per_page' => $per_page,
				'total_items' => 0,
				'total_pages' => 0,
				'has_next' => false,
				'has_prev' => false
			)
		), __('Không tìm thấy lịch sử chơi game.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$total_pages = ceil($total_items / $per_page);

	if ($page > $total_pages) {
		return wg_json_response(400, [], __('Số trang vượt quá tổng số trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$offset = ($page - 1) * $per_page;

	// ===== LẤY DỮ LIỆU CÁC LƯỢT CHƠI =====
	$sessions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				ps.id as session_id,
				ps.started_at,
				ps.finished_at,
				ps.questions_count,
				ps.correct_count,
				GROUP_CONCAT(
					CONCAT_WS('|', dl.outcome, dl.points_awarded, COALESCE(a.id, ''),
					COALESCE(a.name, ''), COALESCE(p.id, ''), COALESCE(p.piece_code, ''))
					ORDER BY dl.id
					SEPARATOR ';;'
				) as rewards_data
			FROM {$prefix}users_play_sessions ps
			LEFT JOIN {$prefix}drop_logs dl ON ps.id = dl.session_id
			LEFT JOIN {$prefix}artifacts a ON dl.artifact_id = a.id
			LEFT JOIN {$prefix}pieces p ON dl.piece_id = p.id
			WHERE ps.user_id = %d
			GROUP BY ps.id
			ORDER BY ps.started_at DESC
			LIMIT %d OFFSET %d",
			$user_id,
			$per_page,
			$offset
		)
	);

	// ===== XỬ LÝ DỮ LIỆU LƯỢT CHƠI =====
	$sessions_list = array();

	foreach ($sessions as $session) {
		$session_points = 0;
		$session_pieces = array();

		if (!empty($session->rewards_data)) {
			$rewards_list = explode(';;', $session->rewards_data);

			foreach ($rewards_list as $reward_str) {
				$reward_parts = explode('|', $reward_str);
				$outcome = $reward_parts[0] ?? '';
				$points = (int)($reward_parts[1] ?? 0);
				$artifact_id = (int)($reward_parts[2] ?? 0);
				$artifact_name = $reward_parts[3] ?? 'N/A';
				$piece_id = (int)($reward_parts[4] ?? 0);
				$piece_code = $reward_parts[5] ?? 'N/A';

				if ($outcome === 'POINT') {
					$session_points += $points;
				} elseif ($outcome === 'PIECE' && $piece_id > 0) {
					// Nếu cùng piece_id được tặng nhiều lần trong phiên, tăng qty thay vì ghi đè
					if (!isset($session_pieces[$piece_id])) {
						$session_pieces[$piece_id] = array(
							'artifact_id' => $artifact_id,
							'artifact_name' => sanitize_text_field($artifact_name),
							'piece_id' => $piece_id,
							'piece_code' => sanitize_text_field($piece_code),
							'qty' => 1
						);
					} else {
						$session_pieces[$piece_id]['qty'] += 1;
					}
				}
			}
		}

		$session_obj = array(
			'session_id' => (int)$session->session_id,
			'started_at' => $session->started_at,
			'finished_at' => $session->finished_at,
			'correct' => (int)$session->correct_count,
			'total' => (int)$session->questions_count,
			'score' => (int)$session->correct_count . '/' . (int)$session->questions_count,
			'points' => $session_points,
			'pieces' => array_values($session_pieces)
		);

		$sessions_list[] = $session_obj;
	}

	// ===== RETURN RESPONSE =====
	$response = array(
		'user' => array(
			'id' => (int)$user['id'],
			'name' => sanitize_text_field($user['name']),
			'avatar_url' => esc_url($user['avatar_url'] ?? '')
		),
		'sessions' => $sessions_list,
		'pagination' => array(
			'current_page' => $page,
			'per_page' => $per_page,
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'has_next' => $page < $total_pages,
			'has_prev' => $page > 1
		)
	);

	return wg_json_response(200, $response, __('Lấy lịch sử chơi game thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

/**
 * API 2: Lấy chi tiết câu hỏi trong một phiên chơi
 * Endpoint: /wp-json/game-bsc/play-session/{session_id}/questions

 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_get_session_questions_detail(WP_REST_Request $request) {
	global $wpdb;
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$session_id = absint($request['session_id']);

	// ===== SECURITY: Kiểm tra session =====
	$session_user = game_sso_require_session();
	if (is_wp_error($session_user) || empty($session_user['id'])) {
		return wg_json_response(401, [], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$session_user_id = absint($session_user['id']);

	// lấy thông tin phiên chơi
	$prefix = $wpdb->prefix . 'game_';
	$session = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, user_id, started_at, finished_at, questions_count, correct_count
			 FROM {$prefix}users_play_sessions
			 WHERE id = %d",
			$session_id
		),
		ARRAY_A
	);

	if (!$session) {
		return wg_json_response(404, [], __('Không tìm thấy phiên chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// check chủ phiên chơi
	if (absint($session['user_id']) !== $session_user_id) {
		return wg_json_response(403, [], __('Bạn chỉ có thể xem chi tiết phiên chơi của chính mình.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// get user
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, name, avatar_url FROM {$prefix}users WHERE id = %d AND status = 1",
			$session['user_id']
		),
		ARRAY_A
	);

	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// lấy danh sách câu hỏi
	$answers = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				question_post_id,
				order_index,
				is_correct,
				user_answer,
				MAX(answered_at) as last_answered_at
			FROM {$prefix}users_session_answers
			WHERE session_id = %d
			GROUP BY order_index, question_post_id
			ORDER BY order_index ASC",
			$session_id
		),
		ARRAY_A
	);

	if (empty($answers)) {
		return wg_json_response(200, array(
		'session' => array(
			'session_id' => (int)$session['id'],
			'user' => array('id' => (int)$user['id']),
		),
		'questions_history' => array()
	), __('Không tìm thấy câu hỏi nào.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// lọc danh sách post_id
	$post_ids = array_map(function($a) { return (int)$a['question_post_id']; }, $answers);
	$post_ids = array_unique($post_ids);

	// ===== SECURITY FIX: Sử dụng prepare() cho IN clause =====
	// Code cũ (không an toàn):
	// $posts = $wpdb->get_results(
	// 	"SELECT ID, post_title FROM {$wpdb->posts}
	// 	 WHERE ID IN (" . implode(',', $post_ids) . ")
	// 	 AND post_status = 'publish'"
	// );

	// Code mới (an toàn với SQL injection):
	// Tạo placeholders cho IN clause
	$post_ids_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
	$posts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_title FROM {$wpdb->posts}
			 WHERE ID IN ($post_ids_placeholders)
			 AND post_status = 'publish'",
			...$post_ids
		)
	);

	$posts_map = array();
	foreach ($posts as $post) {
		$posts_map[(int)$post->ID] = $post->post_title;
	}

	// ===== SECURITY FIX: Sử dụng prepare() cho IN clause =====
	// Code cũ (không an toàn):
	// $meta_values = $wpdb->get_results(
	// 	"SELECT post_id, meta_key, meta_value
	// 	 FROM {$wpdb->postmeta}
	// 	 WHERE post_id IN (" . implode(',', $post_ids) . ")
	// 	 AND meta_key IN ('answer_a', 'answer_b', 'answer_c', 'answer_d', 'correct_answer')"
	// );

	// Code mới (an toàn với SQL injection):
	$meta_values = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_key, meta_value
			 FROM {$wpdb->postmeta}
			 WHERE post_id IN ($post_ids_placeholders)
			 AND meta_key IN ('answer_a', 'answer_b', 'answer_c', 'answer_d', 'correct_answer')",
			...$post_ids
		)
	);

	$meta_map = array();
	foreach ($meta_values as $meta) {
		$post_id = (int)$meta->post_id;
		if (!isset($meta_map[$post_id])) {
			$meta_map[$post_id] = array();
		}
		$meta_map[$post_id][$meta->meta_key] = $meta->meta_value;
	}

	// lấy danh sách đáp án
	$questions_list = array();

	foreach ($answers as $answer) {
		$post_id = (int)$answer['question_post_id'];
		$order = (int)$answer['order_index'];

		$meta = $meta_map[$post_id] ?? array();

		$options = array();
		$listOptions = array(
			'A' => sanitize_text_field($meta['answer_a'] ?? ''),
			'B' => sanitize_text_field($meta['answer_b'] ?? ''),
			'C' => sanitize_text_field($meta['answer_c'] ?? ''),
			'D' => sanitize_text_field($meta['answer_d'] ?? '')
		);

		$value = 1;
		foreach ($listOptions as $code => $option) {
			$option = trim($option);
			if ($option !== '') {
				$options[] = [
					'value'      => $value,
					'valueCode'  => $code,
					'content'    => $option,
				];
				$value++;
			}
		}

		$question_detail = array(
			'order' => $order,
			'question' => isset($posts_map[$post_id]) ? sanitize_text_field($posts_map[$post_id]) : 'Câu hỏi không có tiêu đề',
			'options' => $options,
			'correct_answer' => strtoupper(sanitize_text_field($meta['correct_answer'] ?? 'A')),
			'user_answer' => strtoupper(sanitize_text_field($answer['user_answer'] ?? '')),
			'is_correct' => (int)$answer['is_correct'] === 1,
		);

		$questions_list[] = $question_detail;
	}

	$response = array(
		'session' => array(
			'session_id' => (int)$session['id'],
			'user' => array(
				'id' => (int)$user['id'],
			),
		),
		'questions_history' => $questions_list
	);

	return wg_json_response(200, $response, __('Lấy chi tiết câu hỏi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

/**
 * API 3: Lịch sử biến động lượt chơi theo user đang đăng nhập
 * Endpoint: GET /wp-json/game-bsc/play-credit-history?page=1&per_page=20
 *
 * Response trả về:
 * - summary: tổng lượt đã nhận, đã chơi, còn lại
 * - history: danh sách chi tiết biến động (cộng/trừ, số lượt, lý do, thời gian yyyy-mm-dd HH:mm:ss)
 * - pagination: phân trang chuẩn
 *
 * Bảng tham chiếu:
 * - play_credit_ledger  (delta, ref_type, ref_id, created_at)
 * - play_credit_balances (balance)
 * - user_mission_logs    (mission_code)  khi ref_type = MISSION
 * - users_play_sessions  (correct_count, questions_count)  khi ref_type = SESSION
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_get_play_credit_history(WP_REST_Request $request) {
	global $wpdb;

	// ===== SECURITY: Kiểm tra nonce =====
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, [], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$user_id = absint($current_user['id']);

	// ===== KIỂM TRA USER TỒN TẠI & ACTIVE =====
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

	if ($user['status'] == 0) {
		return wg_json_response(403, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== PAGINATION =====
	$page     = max(1, absint($request->get_param('page') ?? 1));
	$per_page = min(100, max(1, absint($request->get_param('per_page') ?? 20)));
	$offset   = ($page - 1) * $per_page;

	// ===== 1. TỔNG HỢP SUMMARY =====
	// Tổng lượt đã nhận (tổng delta dương)
	$total_received = (int)$wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(SUM(delta), 0) FROM {$prefix}play_credit_ledger WHERE user_id = %d AND delta > 0",
			$user_id
		)
	);

	// Tổng lượt đã chơi (tổng |delta âm|)
	$total_played = (int)$wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(ABS(SUM(delta)), 0) FROM {$prefix}play_credit_ledger WHERE user_id = %d AND delta < 0",
			$user_id
		)
	);

	// Số lượt còn lại (lấy từ bảng balance, chính xác hơn tự tính)
	$balance_remaining = (int)$wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(balance, 0) FROM {$prefix}play_credit_balances WHERE user_id = %d",
			$user_id
		)
	);

	// ===== 2. TỔNG SỐ BẢN GHI LEDGER =====
	$total_items = (int)$wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$prefix}play_credit_ledger WHERE user_id = %d",
			$user_id
		)
	);

	$total_pages = max(1, (int)ceil($total_items / $per_page));

	// Trường hợp không có dữ liệu
	if ($total_items === 0) {
		return wg_json_response(200, array(
			'user' => array(
				'id'         => (int)$user['id'],
				'name'       => sanitize_text_field($user['name']),
				'avatar_url' => esc_url($user['avatar_url'] ?? ''),
			),
			'summary' => array(
				'total_received'  => 0,
				'total_played'    => 0,
				'total_remaining' => 0,
			),
			'history'    => array(),
			'pagination' => array(
				'current_page' => 1,
				'per_page'     => $per_page,
				'total_items'  => 0,
				'total_pages'  => 0,
				'has_next'     => false,
				'has_prev'     => false,
			),
		), __('Chưa có lịch sử biến động lượt chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	if ($page > $total_pages) {
		return wg_json_response(400, [], __('Số trang vượt quá tổng số trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== 3. LẤY DANH SÁCH BIẾN ĐỘNG =====
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				l.id            AS ledger_id,
				l.delta,
				l.ref_type,
				l.ref_id,
				l.created_at,
				ml.mission_code,
				s.correct_count,
				s.questions_count
			FROM {$prefix}play_credit_ledger l
			LEFT JOIN {$prefix}user_mission_logs ml ON (l.ref_type = 'MISSION' AND l.ref_id = ml.id)
			LEFT JOIN {$prefix}users_play_sessions s ON (l.ref_type = 'SESSION' AND l.ref_id = s.id)
			WHERE l.user_id = %d
			ORDER BY l.created_at DESC, l.id DESC
			LIMIT %d OFFSET %d",
			$user_id,
			$per_page,
			$offset
		),
		ARRAY_A
	);

	// ===== 4. FORMAT DỮ LIỆU HISTORY =====
	$history = array();

	foreach ($rows as $row) {
		$delta = (int)$row['delta'];

		// Xác định loại biến động
		$type = $delta >= 0 ? 'credit' : 'debit'; // credit = cộng, debit = trừ

		// Xác định lý do
		if ($row['ref_type'] === 'MISSION') {
			$reason = game_bsc_get_mission_label((string)$row['mission_code']);
		} elseif ($row['ref_type'] === 'SESSION') {
			$reason  = 'Chơi game';
		} else {
			$reason = '-';
		}

		// Format thời gian: Y-m-d H:i:s
		$created_at_raw     = (string)$row['created_at'];
		$created_at_display = !empty($created_at_raw) ? date('d/m/Y H:i:s', strtotime($created_at_raw)) : '-';

		$history[] = array(
			'id'                 => (int)$row['ledger_id'],
			'type'               => $type,                            // "credit" | "debit"
			'delta'              => $delta,                            // +5 hoặc -1
			'delta_display'      => $delta > 0 ? ('+' . $delta) : (string)$delta,
			'ref_type'           => (string)$row['ref_type'],         // "MISSION" | "SESSION"
			'ref_id'             => isset($row['ref_id']) ? (int)$row['ref_id'] : 0,
			'reason'             => $reason,                           // Lý do cụ thể
			'created_at'         => $created_at_raw,                   // "2025-06-15 14:30:25"
			'created_at_display' => $created_at_display,               // "15/06/2025 14:30:25"
		);
	}

	// ===== 5. RETURN RESPONSE =====
	$response = array(
		'user' => array(
			'id'         => (int)$user['id'],
			'name'       => sanitize_text_field($user['name']),
			'avatar_url' => esc_url($user['avatar_url'] ?? ''),
		),
		'summary' => array(
			'total_received'  => $total_received,   // Tổng lượt đã nhận
			'total_played'    => $total_played,      // Tổng lượt đã chơi
			'total_remaining' => $balance_remaining,  // Số lượt còn lại
		),
		'history'    => $history,
		'pagination' => array(
			'current_page' => $page,
			'per_page'     => $per_page,
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
			'has_next'     => $page < $total_pages,
			'has_prev'     => $page > 1,
		),
	);

	return wg_json_response(200, $response, __('Lấy lịch sử biến động lượt chơi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}
