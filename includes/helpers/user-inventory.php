<?php
if (!defined('ABSPATH')) exit;

/**
 * Helper lấy điểm và mảnh của user hiện tại (qua SSO)
 *
 * @return array {
 *     'user_id'    => int,
 *     'points'     => int,
 *     'total_pieces' => int,
 *     'pieces_detail' => array [
 *         ['piece_id' => int, 'artifact_id' => int, 'artifact_name' => string, 'piece_code' => string, 'qty' => int],
 *         ...
 *     ]
 * }
 */
function game_bsc_get_user_inventory($user_id = null) {
	global $wpdb;
	
	// Lấy user từ SSO session nếu không chỉ định
	if ($user_id === null) {
		$session_user = game_sso_require_session();
		if (is_wp_error($session_user) || empty($session_user['id'])) {
			return [
				'user_id' => null,
				'points' => 0,
				'total_pieces' => 0,
				'pieces_detail' => []
			];
		}
		$user_id = (int) $session_user['id'];
	} else {
		$user_id = (int) $user_id;
	}
	
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		// Lấy số điểm hiện có
		$points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance FROM {$prefix}user_points_balances WHERE user_id = %d",
				$user_id
			)
		);
		$points = $points !== null ? (int) $points : 0;
		
		// Lấy tổng số mảnh
		$total_pieces = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(qty), 0) FROM {$prefix}user_pieces WHERE user_id = %d",
				$user_id
			)
		);
		$total_pieces = (int) $total_pieces;
		
		// Lấy chi tiết từng mảnh (kèm thông tin hiện vật)
		$pieces_detail = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    up.piece_id,
                    up.artifact_id,
                    up.qty,
                    a.name as artifact_name,
                    p.piece_code
                FROM {$prefix}user_pieces up
                INNER JOIN {$prefix}artifacts a ON up.artifact_id = a.id
                INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
                WHERE up.user_id = %d
                ORDER BY a.name, p.piece_code",
				$user_id
			),
			ARRAY_A
		);
		
		// Format lại dữ liệu
		if (!empty($pieces_detail)) {
			$pieces_detail = array_map(function($piece) {
				return [
					'piece_id' => (int) $piece['piece_id'],
					'artifact_id' => (int) $piece['artifact_id'],
					'artifact_name' => sanitize_text_field($piece['artifact_name']),
					'piece_code' => sanitize_text_field($piece['piece_code']),
					'qty' => (int) $piece['qty']
				];
			}, $pieces_detail);
		}
		
		return [
			'user_id' => $user_id,
			'points' => $points,
			'total_pieces' => $total_pieces,
			'pieces_detail' => $pieces_detail ?: [],
		];
		
	} catch (Throwable $e) {
		return [
			'user_id' => $user_id,
			'points' => 0,
			'total_pieces' => 0,
			'pieces_detail' => []
		];
	}
}

/**
 * Helper lấy điểm của user
 *
 * @param int $user_id (Optional) ID của user. Nếu null, lấy từ SSO session
 * @return int Số điểm hiện có
 */
function game_bsc_get_user_points($user_id = null) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy user_id từ session nếu không chỉ định
	if ($user_id === null) {
		$session_user = game_sso_require_session();
		if (is_wp_error($session_user) || empty($session_user['id'])) {
			return 0;
		}
		$user_id = (int) $session_user['id'];
	} else {
		$user_id = (int) $user_id;
	}
	
	$points = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT balance FROM {$prefix}user_points_balances WHERE user_id = %d",
			$user_id
		)
	);
	
	return $points !== null ? (int) $points : 0;
}

/**
 * Helper lấy tổng số mảnh của user
 *
 * @param int $user_id (Optional) ID của user. Nếu null, lấy từ SSO session
 * @return int Tổng số mảnh
 */
function game_bsc_get_user_total_pieces($user_id = null) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy user_id từ session nếu không chỉ định
	if ($user_id === null) {
		$session_user = game_sso_require_session();
		if (is_wp_error($session_user) || empty($session_user['id'])) {
			return 0;
		}
		$user_id = (int) $session_user['id'];
	} else {
		$user_id = (int) $user_id;
	}
	
	$total = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COALESCE(SUM(qty), 0) FROM {$prefix}user_pieces WHERE user_id = %d",
			$user_id
		)
	);
	
	return (int) $total;
}

/**
 * Helper lấy chi tiết mảnh của user
 *
 * @param int $user_id (Optional) ID của user. Nếu null, lấy từ SSO session
 * @return array Danh sách chi tiết mảnh
 */
function game_bsc_get_user_pieces_detail($user_id = null) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy user_id từ session nếu không chỉ định
	if ($user_id === null) {
		$session_user = game_sso_require_session();
		if (is_wp_error($session_user) || empty($session_user['id'])) {
			return [];
		}
		$user_id = (int) $session_user['id'];
	} else {
		$user_id = (int) $user_id;
	}
	
	$pieces = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
                up.piece_id,
                up.artifact_id,
                up.qty,
                a.name as artifact_name,
                a.status,
                p.piece_code,
                p.baseline_weight,
                p.piece_img
            FROM {$prefix}user_pieces up
            INNER JOIN {$prefix}artifacts a ON up.artifact_id = a.id
            INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
            WHERE up.user_id = %d
            ORDER BY a.name, p.piece_code",
			$user_id
		),
		ARRAY_A
	);
	
	if (empty($pieces)) {
		return [];
	}
	
	return array_map(function($piece) {
		return [
			'piece_id' => (int) $piece['piece_id'],
			'artifact_id' => (int) $piece['artifact_id'],
			'artifact_name' => sanitize_text_field($piece['artifact_name']),
			'artifact_status' => (int) $piece['status'],
			'piece_code' => sanitize_text_field($piece['piece_code']),
			'baseline_weight' => (int) $piece['baseline_weight'],
			'piece_img' => esc_url($piece['piece_img']),
			'qty' => (int) $piece['qty']
		];
	}, $pieces);
}

/**
 * Helper lấy mảnh của một hiện vật cụ thể
 *
 * @param int $artifact_id ID của hiện vật
 * @param int $user_id (Optional) ID của user. Nếu null, lấy từ SSO session
 * @return array Danh sách mảnh của hiện vật
 */
function game_bsc_get_user_pieces_by_artifact($artifact_id, $user_id = null) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	$artifact_id = (int) $artifact_id;
	
	// Lấy user_id từ session nếu không chỉ định
	if ($user_id === null) {
		$session_user = game_sso_require_session();
		if (is_wp_error($session_user) || empty($session_user['id'])) {
			return [];
		}
		$user_id = (int) $session_user['id'];
	} else {
		$user_id = (int) $user_id;
	}
	
	$pieces = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
                up.piece_id,
                up.qty,
                p.piece_code,
                p.piece_img,
                p.baseline_weight
            FROM {$prefix}user_pieces up
            INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
            WHERE up.user_id = %d AND up.artifact_id = %d
            ORDER BY p.piece_code",
			$user_id,
			$artifact_id
		),
		ARRAY_A
	);
	
	if (empty($pieces)) {
		return [];
	}
	
	return array_map(function($piece) {
		return [
			'piece_id' => (int) $piece['piece_id'],
			'piece_code' => sanitize_text_field($piece['piece_code']),
			'piece_img' => esc_url($piece['piece_img']),
			'baseline_weight' => (int) $piece['baseline_weight'],
			'qty' => (int) $piece['qty']
		];
	}, $pieces);
}



function game_get_user_badges_data($user_id) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		// Lấy tất cả huy hiệu từ post type 'game_badges'
		$all_badges = $wpdb->get_results(
			"SELECT
				p.ID as badge_post_id,
				p.post_title as badge_name
			FROM {$wpdb->posts} p
			WHERE p.post_type = 'game_badges'
			AND p.post_status = 'publish'
			ORDER BY p.post_date ASC",
			ARRAY_A
		);
		
		// Lấy danh sách ID huy hiệu mà user đã đạt được
		$user_badge_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT badge_post_id FROM {$prefix}user_badges WHERE user_id = %d",
				$user_id
			)
		);
		
		$badges_formatted = array();
		
		if (!empty($all_badges)) {
			// Duyệt qua từng huy hiệu và format dữ liệu
			foreach ($all_badges as $badge) {
				$badge_post_id = (int)$badge['badge_post_id'];
				$is_earned = in_array($badge_post_id, $user_badge_ids); // Kiểm tra user đã đạt được chưa
				
				// Lấy hình ảnh huy hiệu từ ACF field
				$badge_image_id = get_field('badge_image', $badge_post_id);
				$badge_icon_url = '';
				if ($badge_image_id) {
					$badge_icon_url = wp_get_attachment_image_url($badge_image_id, 'full') ?: '';
				}
				
				// Lấy mô tả nhiệm vụ của huy hiệu
				$badge_description = get_field('badge_task_content', $badge_post_id);
				
				// Thêm huy hiệu vào mảng kết quả
				$badges_formatted[] = array(
					'badge_id' => $badge_post_id,
					'name' => $badge['badge_name'],
					'description' => $badge_description ?: '',
					'icon_url' => $badge_icon_url,
					'earned' => $is_earned, // true nếu đã đạt được, false nếu chưa
				);
			}
		}
		
		return $badges_formatted;
	} catch (Throwable $e) {
		return []; // Trả về mảng rỗng nếu có lỗi
	}
}