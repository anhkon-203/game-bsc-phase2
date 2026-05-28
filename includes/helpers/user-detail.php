<?php
if (!defined('ABSPATH')) exit;

/**
 * Lấy thông tin chi tiết người chơi
 * Bao gồm: điểm, mảnh ghép, chặng, thành tích tuần/tháng, trạng thái tài khoản
 */
function game_bsc_get_user_details($user_id) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		$user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, external_user_id, status, created_at, last_login_at FROM {$prefix}users WHERE id = %d",
				$user_id
			),
			ARRAY_A
		);
		
		if (!$user) {
			return new WP_Error('user_not_found', __('User not found', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		$total_points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(balance, 0) FROM {$prefix}user_points_balances WHERE user_id = %d",
				$user_id
			)
		);
		$total_points = (int)$total_points;
		
		$total_pieces = game_bsc_sum_valid_user_pieces($user_id);
		
		$game_day_info = game_bsc_compute_day_index_v2($user_id);
		$current_day_index = (int)$game_day_info['day_index'];
		
		$tz = TIMEZONE;
		$week_start = (new DateTimeImmutable('now', $tz))->modify('monday this week')->format('Y-m-d');
		$week_end = (new DateTimeImmutable('now', $tz))->modify('sunday this week')->format('Y-m-d');
		
		$week_correct_answers = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}users_session_answers usa
				 INNER JOIN {$prefix}users_play_sessions ups ON usa.session_id = ups.id
				 WHERE ups.user_id = %d AND usa.is_correct = 1
				 AND DATE(usa.answered_at) BETWEEN %s AND %s",
				$user_id,
				$week_start,
				$week_end
			)
		);
		$week_correct_answers = (int)$week_correct_answers;
		
		$month_start = (new DateTimeImmutable('now', $tz))->format('Y-m-01');
		$month_end = (new DateTimeImmutable('now', $tz))->format('Y-m-t');
		
		$month_correct_answers = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}users_session_answers usa
				 INNER JOIN {$prefix}users_play_sessions ups ON usa.session_id = ups.id
				 WHERE ups.user_id = %d AND usa.is_correct = 1
				 AND DATE(usa.answered_at) BETWEEN %s AND %s",
				$user_id,
				$month_start,
				$month_end
			)
		);
		$month_correct_answers = (int)$month_correct_answers;
		
		$first_login = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(MIN(checked_at), '%%d/%%m/%%Y %%H:%%i:%%s')
         FROM {$prefix}user_login_logs
         WHERE user_id = %d AND result = 'OK'",
				$user_id
			)
		);
		$first_login = $first_login ?: __('N/A', WG_GAME_PLUGIN_TEXTDOMAIN);
		
		$last_login = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(MAX(checked_at), '%%d/%%m/%%Y %%H:%%i:%%s')
         FROM {$prefix}user_login_logs
         WHERE user_id = %d AND result = 'OK'",
				$user_id
			)
		);
		$last_login = $last_login ?: __('N/A', WG_GAME_PLUGIN_TEXTDOMAIN);
		
		return [
			'user_id' => (int)$user['id'],
			'name' => sanitize_text_field($user['name']),
			'external_user_id' => sanitize_text_field($user['external_user_id'] ?? ''),
			'total_points' => $total_points,
			'total_pieces' => $total_pieces,
			'current_day_index' => $current_day_index,
			'game_status' => $game_day_info['status'],
			'week_achievements' => $week_correct_answers,
			'month_achievements' => $month_correct_answers,
			'first_login' => $first_login,
			'last_login' => $last_login,
			'account_status' => (int)$user['status'] === 1 ? 'active' : 'inactive',
			'created_at' => $user['created_at'],
		];
		
	} catch (Throwable $e) {
		return new WP_Error('error', $e->getMessage());
	}
}

/**
 * ✅ HOÀN CHỈNH: Lấy danh sách quà tặng với TÌM KIẾM ĐẦY ĐỦ và PHÂN TRANG
 */
function game_bsc_get_user_gifts($user_id, $per_page = 10, $page = 1, $search = '',$gift_received_date_from = '',$gift_received_date_to = '') {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		$page = max(1, (int)$page);
		$per_page = max(1, (int)$per_page);
		$offset = ($page - 1) * $per_page;
		
	$search_keyword = sanitize_text_field(trim($search));
	$search_like = !empty($search_keyword) ? '%' . $wpdb->esc_like($search_keyword) . '%' : '%';
	
	$gift_received_date_from = sanitize_text_field(trim($gift_received_date_from));
	$gift_received_date_from_sql = !empty($gift_received_date_from) ? date('Y-m-d', strtotime($gift_received_date_from)) : '';
	
	$gift_received_date_to = sanitize_text_field(trim($gift_received_date_to));
	$gift_received_date_to_sql = !empty($gift_received_date_to) ? date('Y-m-d', strtotime($gift_received_date_to)) : '';
	
	// ===== VOUCHERS =====
		$vouchers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
            uvr.id,
            'voucher' AS type,
            p.post_title AS name,
            uvr.redeemed_at AS received_date,
            uvr.start_date,
            uvr.gotit_expiry_date,
            pm_type.meta_value AS voucher_type,
            pm_from.meta_value AS acf_valid_from,
            pm_to.meta_value AS acf_valid_to,
            'Đã sử dụng' AS status
        FROM {$prefix}user_voucher_redemptions uvr
        INNER JOIN {$wpdb->posts} p ON uvr.voucher_post_id = p.ID
        LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'voucher_type'
        LEFT JOIN {$wpdb->postmeta} pm_from ON p.ID = pm_from.post_id AND pm_from.meta_key = 'validity_valid_from'
        LEFT JOIN {$wpdb->postmeta} pm_to ON p.ID = pm_to.post_id AND pm_to.meta_key = 'validity_valid_to'
        WHERE uvr.user_id = %d
        AND p.post_title LIKE %s
        " . ( $gift_received_date_from_sql ? " AND DATE(uvr.redeemed_at) >= %s" : "" ) . "
        " . ( $gift_received_date_to_sql ? " AND DATE(uvr.redeemed_at) <= %s" : "" ) . "
        ORDER BY uvr.redeemed_at DESC",
			array_merge(
				[$user_id, $search_like],
				$gift_received_date_from_sql ? [$gift_received_date_from_sql] : [],
				$gift_received_date_to_sql ? [$gift_received_date_to_sql] : []
			)
			),
			ARRAY_A
		);
		
		
		// ===== ARTIFACTS =====
		$artifacts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
            uar.id,
            'artifact' AS type,
            a.name,
            uar.redeemed_at AS received_date,
            NULL AS valid_from,
            NULL AS valid_to,
            'Đã nhận' AS status
        FROM {$prefix}user_artifact_redemptions uar
        INNER JOIN {$prefix}artifacts a ON uar.artifact_id = a.id
        WHERE uar.user_id = %d
        AND a.name LIKE %s
        " . ( $gift_received_date_from_sql ? " AND DATE(uar.redeemed_at) >= %s" : "" ) . "
        " . ( $gift_received_date_to_sql ? " AND DATE(uar.redeemed_at) <= %s" : "" ) . "
        ORDER BY uar.redeemed_at DESC",
			array_merge(
				[$user_id, $search_like],
				$gift_received_date_from_sql ? [$gift_received_date_from_sql] : [],
				$gift_received_date_to_sql ? [$gift_received_date_to_sql] : []
			)
			),
			ARRAY_A
		);
		
		
		// ===== GỘP VÀ SẮP XẾP =====
		$all = array_merge($vouchers, $artifacts);
		
		usort($all, function($a, $b) {
			return strtotime($b['received_date']) - strtotime($a['received_date']);
		});
		
		// ===== PHÂN TRANG =====
		$total = count($all);
		$total_pages = ceil($total / $per_page);
		
		if ($page > $total_pages && $total_pages > 0) {
			$page = $total_pages;
		}
		
		$offset = ($page - 1) * $per_page;
		$list = array_slice($all, $offset, $per_page);
		
		// ===== FORMAT DỮ LIỆU =====
		$items = array_map(function($i) {
			$valid_from = '-';
			$valid_to = '-';
			if ($i['type'] === 'voucher') {
				$v_type = strtoupper(trim((string)($i['voucher_type'] ?? 'BSC')));
				if ($v_type === 'BSC') {
					$db_start = !empty($i['start_date']) ? $i['start_date'] : '';
					$db_expiry = !empty($i['gotit_expiry_date']) ? $i['gotit_expiry_date'] : '';
					if ($db_start !== '0000-00-00 00:00:00' && $db_start !== '') {
						$valid_from = date('d/m/Y H:i:s', strtotime($db_start));
					}
					if ($db_expiry !== '0000-00-00 00:00:00' && $db_expiry !== '') {
						$valid_to = date('d/m/Y H:i:s', strtotime($db_expiry));
					}
				} else {
					if (!empty($i['acf_valid_from'])) {
						$valid_from = date('d/m/Y H:i:s', strtotime($i['acf_valid_from']));
					}
					if (!empty($i['acf_valid_to'])) {
						$valid_to = date('d/m/Y H:i:s', strtotime($i['acf_valid_to']));
					}
				}
			}
			return [
				'id'            => (int)$i['id'],
				'type'          => $i['type'],
				'name'          => sanitize_text_field($i['name']),
				'received_date' => date('d/m/Y H:i:s', strtotime($i['received_date'])), // ✅ Thêm giờ-phút-giây
				'valid_from'    => $valid_from, // ✅
				'valid_to'      => $valid_to,   // ✅
				'used_date'     => '-',
				'status'        => $i['status'],
			];
		}, $list);
		
		return [
			'items' => $items,
			'pagination' => [
				'current_page' => $page,
				'per_page' => $per_page,
				'total_items' => $total,
				'total_pages' => $total_pages,
				'has_next' => $page < $total_pages,
				'has_prev' => $page > 1,
			]
		];
		
	} catch (Throwable $e) {
		return ['items' => [], 'pagination' => [], 'error' => $e->getMessage()];
	}
}

/**
 * ✅ HOÀN CHỈNH: Lấy lịch sử chơi game với LỌC THEO KHOẢNG THỜI GIAN và PHÂN TRANG
 *
 * Hỗ trợ lọc:
 * - Theo khoảng thời gian (từ ngày - đến ngày)
 */
function game_bsc_get_user_play_history($user_id, $per_page = 10, $page = 1, $play_date_from = '', $play_date_to = '') {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		$page = max(1, (int)$page);
		$per_page = max(1, (int)$per_page);
		
		$play_date_from = sanitize_text_field(trim($play_date_from));
		$play_date_from_sql = !empty($play_date_from) ? date('Y-m-d', strtotime($play_date_from)) : '';
		
		$play_date_to = sanitize_text_field(trim($play_date_to));
		$play_date_to_sql = !empty($play_date_to) ? date('Y-m-d', strtotime($play_date_to)) : '';
		
		$where_extra = "";
		
		if ($play_date_from_sql) {
			$where_extra .= $wpdb->prepare(" AND DATE(ups.started_at) >= %s ", $play_date_from_sql);
		}
		
		if ($play_date_to_sql) {
			$where_extra .= $wpdb->prepare(" AND DATE(ups.started_at) <= %s ", $play_date_to_sql);
		}
		
		
		// ===== XÂY DỰNG QUERY CƠ BẢN =====
				$base_query = "
		    SELECT
		        ups.id as session_id,
		        ups.started_at,
		        ups.finished_at,
		        ups.correct_count,
		        ups.retries_used,
		        ups.questions_count,
		        COALESCE(SUM(dl.points_awarded), 0) as total_points,
		        GROUP_CONCAT(
		            CONCAT_WS('|',
		                COALESCE(p.piece_code, ''),
		                COALESCE(a.name, '')
		            )
		            SEPARATOR ';;'
		        ) as pieces_collected
		    FROM {$prefix}users_play_sessions ups
		    LEFT JOIN {$prefix}drop_logs dl ON ups.id = dl.session_id AND dl.outcome = 'POINT'
		    LEFT JOIN {$prefix}drop_logs dl2 ON ups.id = dl2.session_id AND dl2.outcome = 'PIECE'
		    LEFT JOIN {$prefix}pieces p ON dl2.piece_id = p.id
		    LEFT JOIN {$prefix}artifacts a ON dl2.artifact_id = a.id
		    WHERE ups.user_id = %d
		    $where_extra
		";
		
		
		$base_query .= " GROUP BY ups.id ORDER BY ups.started_at DESC";
		
		// ===== ĐẾM TỔNG SỐ PHIÊN CHƠI =====
		$count_query = "SELECT COUNT(DISTINCT ups.id) FROM {$prefix}users_play_sessions ups
			LEFT JOIN {$prefix}drop_logs dl ON ups.id = dl.session_id AND dl.outcome = 'POINT'
			LEFT JOIN {$prefix}drop_logs dl2 ON ups.id = dl2.session_id AND dl2.outcome = 'PIECE'
			LEFT JOIN {$prefix}pieces p ON dl2.piece_id = p.id
			LEFT JOIN {$prefix}artifacts a ON dl2.artifact_id = a.id
			WHERE ups.user_id = %d" . $where_extra;
		
		$total_sessions = (int)$wpdb->get_var($wpdb->prepare($count_query, $user_id));
		$total_pages = ceil($total_sessions / $per_page);
		
		if ($page > $total_pages && $total_pages > 0) {
			$page = $total_pages;
		}
		
		$offset = ($page - 1) * $per_page;
		
		// ===== LẤY DANH SÁCH PHIÊN CHƠI VỚI OFFSET =====
		$sessions = $wpdb->get_results(
			$wpdb->prepare($base_query . " LIMIT %d OFFSET %d", $user_id, $per_page, $offset),
			ARRAY_A
		);
		
		// ===== FORMAT DỮ LIỆU =====
		$formatted_sessions = array_map(function($session) {
			$pieces = [];
			if (!empty($session['pieces_collected'])) {
				$pieces_data = explode(';;', $session['pieces_collected']);
				$pieces_unique = []; // ✅ Thêm array để tracking
				
				foreach ($pieces_data as $piece_str) {
					if (!empty($piece_str)) {
						$parts = explode('|', $piece_str);
						if (count($parts) === 2 && !empty($parts[0])) {
							$piece_key = $parts[0] . '/' . $parts[1];
							// ✅ Chỉ thêm nếu chưa có
							if (!isset($pieces_unique[$piece_key])) {
								$pieces_unique[$piece_key] = true;
								$pieces[] = sanitize_text_field($piece_key);
							}
						}
					}
				}
			}
			
			return [
				'session_id' => (int)$session['session_id'],
				'result' => sprintf('%d/%d', $session['correct_count'], $session['questions_count']),
				'correct_count' => $session['correct_count'],
				'retries_used' => $session['retries_used'],
				'questions_count' => $session['questions_count'],
				'play_date' => date('d/m/Y H:i:s', strtotime($session['started_at'])), // ✅ Thêm giờ-phút-giây
				'points' => (int)$session['total_points'],
				'pieces' => implode(', ', $pieces) ?: 'Không có mảnh',
			];
		}, $sessions);
		
		return [
			'items' => $formatted_sessions,
			'pagination' => [
				'current_page' => $page,
				'per_page' => $per_page,
				'total_items' => $total_sessions,
				'total_pages' => $total_pages,
				'has_next' => $page < $total_pages,
				'has_prev' => $page > 1,
			]
		];
		
	} catch (Throwable $e) {
		return [
			'items' => [],
			'pagination' => [
				'current_page' => 1,
				'per_page' => $per_page,
				'total_items' => 0,
				'total_pages' => 0,
				'has_next' => false,
				'has_prev' => false,
			],
			'error' => $e->getMessage()
		];
	}
}

/**
 * Lấy biến động lượt chơi (play_credit_ledger) theo user.
 *
 * @param int $user_id
 * @param int $per_page
 * @param int $page
 * @param string $date_from
 * @param string $date_to
 * @return array
 */
function game_bsc_get_user_play_credit_ledger($user_id, $per_page = 10, $page = 1, $date_from = '', $date_to = '', $status = 'all') {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';

	try {
		$page = max(1, (int)$page);
		$per_page = max(1, (int)$per_page);

		$date_from = sanitize_text_field(trim($date_from));
		$date_from_sql = !empty($date_from) ? date('Y-m-d', strtotime($date_from)) : '';

		$date_to = sanitize_text_field(trim($date_to));
		$date_to_sql = !empty($date_to) ? date('Y-m-d', strtotime($date_to)) : '';

		$status = sanitize_text_field($status);

		$where_extra = " AND NOT (l.ref_type = 'MISSION' AND l.delta = 0) ";
		if ($date_from_sql) {
			$where_extra .= $wpdb->prepare(' AND DATE(l.created_at) >= %s ', $date_from_sql);
		}
		if ($date_to_sql) {
			$where_extra .= $wpdb->prepare(' AND DATE(l.created_at) <= %s ', $date_to_sql);
		}
		if ($status === 'plus') {
			$where_extra .= ' AND l.delta > 0 ';
		} elseif ($status === 'minus') {
			$where_extra .= ' AND l.delta < 0 ';
		}

		$count_query = "SELECT COUNT(*)
			FROM {$prefix}play_credit_ledger l
			WHERE l.user_id = %d {$where_extra}";

		$total_items = (int)$wpdb->get_var($wpdb->prepare($count_query, $user_id));
		$total_pages = (int)ceil($total_items / $per_page);
		if ($total_pages < 1) {
			$total_pages = 1;
		}

		if ($page > $total_pages) {
			$page = $total_pages;
		}

		$offset = ($page - 1) * $per_page;

		$data_query = "SELECT
			l.id AS ledger_id,
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
		WHERE l.user_id = %d {$where_extra}
		ORDER BY l.created_at DESC, l.id DESC
		LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results(
			$wpdb->prepare($data_query, $user_id, $per_page, $offset),
			ARRAY_A
		);

		$items = array_map(function ($row) {
			$delta = (int)$row['delta'];

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
				'delta' => $delta,
				'delta_display' => $delta > 0 ? ('+' . $delta) : (string)$delta,
				'delta_abs' => abs($delta),
				'delta_status' => $delta >= 0 ? '+' : '-',
				'created_at_display' => !empty($row['created_at']) ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '-',
				'ref_id' => isset($row['ref_id']) ? (int)$row['ref_id'] : 0,
				'detail' => $detail,
			];
		}, $rows ?: []);

		$summary_query = "SELECT
			COALESCE(ABS(SUM(CASE WHEN l.delta < 0 THEN l.delta ELSE 0 END)), 0) AS total_played_turns,
			COALESCE((SELECT b.balance FROM {$prefix}play_credit_balances b WHERE b.user_id = %d LIMIT 1), 0) AS total_remaining_turns
		FROM {$prefix}play_credit_ledger l
		WHERE l.user_id = %d {$where_extra}";

		$summary = $wpdb->get_row(
			$wpdb->prepare($summary_query, $user_id, $user_id),
			ARRAY_A
		);

		return [
			'items' => $items,
			'summary' => [
				'total_played_turns' => isset($summary['total_played_turns']) ? (int)$summary['total_played_turns'] : 0,
				'total_remaining_turns' => isset($summary['total_remaining_turns']) ? (int)$summary['total_remaining_turns'] : 0,
			],
			'pagination' => [
				'current_page' => $page,
				'per_page' => $per_page,
				'total_items' => $total_items,
				'total_pages' => $total_pages,
				'has_next' => $page < $total_pages,
				'has_prev' => $page > 1,
			],
		];
	} catch (Throwable $e) {
		return [
			'items' => [],
			'pagination' => [
				'current_page' => 1,
				'per_page' => $per_page,
				'total_items' => 0,
				'total_pages' => 1,
				'has_next' => false,
				'has_prev' => false,
			],
			'error' => $e->getMessage(),
		];
	}
}

/**
 * Lấy trạng thái tài khoản của user
 */
function game_bsc_get_user_account_status($user_id) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);
	
	if (!$user) {
		return [
			'status' => 'not_found',
			'label' => __('Người dùng không tồn tại', WG_GAME_PLUGIN_TEXTDOMAIN)
		];
	}
	
	return [
		'user_id' => (int)$user['id'],
		'status' => (int)$user['status'] === 1 ? 'active' : 'inactive',
		'label' => (int)$user['status'] === 1 ? __('Hoạt động', WG_GAME_PLUGIN_TEXTDOMAIN) : __('Bị khóa', WG_GAME_PLUGIN_TEXTDOMAIN),
		'is_active' => (int)$user['status'] === 1,
	];
}

/**
 * Cập nhật trạng thái tài khoản của user
 */
function game_bsc_update_user_account_status($user_id, $status) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$status = in_array((int)$status, [0, 1]) ? (int)$status : 1;
	
	$result = $wpdb->update(
		$prefix . 'users',
		['status' => $status],
		['id' => $user_id],
		['%d'],
		['%d']
	);
	
	if ($result === false) {
		return new WP_Error('db_error', $wpdb->last_error);
	}
	
	return true;
}

function format_pieces_collection($pieces_string) {
	if (empty($pieces_string)) {
		return 'Không có mảnh';
	}
	
	// Tách các mảnh từ chuỗi
	$pieces_array = array_filter(array_map('trim', explode(',', $pieces_string)));
	
	if (empty($pieces_array)) {
		return 'Không có mảnh';
	}
	
	$formatted_pieces = [];
	$piece_count = [];
	
	foreach ($pieces_array as $piece) {
		// Parse định dạng: P3/Iphone 17 Pro 256GB
		if (preg_match('/^P(\d)\/(.+)$/i', $piece, $matches)) {
			$piece_num = (int)$matches[1];  // 3
			$product_name = trim($matches[2]); // Iphone 17 Pro 256GB
			
			// Rút gọn tên sản phẩm (bỏ đi "Pro", "Max", "Plus", "GB", "256GB", etc.)
			$short_name = preg_replace('/\s+(Pro|Max|Plus|Standard|Base|GB|TB|MP|Hz|Inch|\")\b/i', '', $product_name);
			$short_name = trim($short_name);
			
			// Tạo key để group các mảnh cùng sản phẩm
			$key = $piece_num . '|' . $short_name;
			
			// Đếm số lượng mảnh
			if (!isset($piece_count[$key])) {
				$piece_count[$key] = 0;
			}
			$piece_count[$key]++;
		}
	}
	
	// Format output
	foreach ($piece_count as $key => $count) {
		list($piece_num, $short_name) = explode('|', $key);
		$formatted_pieces[] = sprintf(
			'+ %d mảnh ghép %d/4 %s',
			$count,
			$piece_num,
			strtolower($short_name)
		);
	}
	
	return !empty($formatted_pieces) ? implode(', ', $formatted_pieces) : 'Không có mảnh';
}
