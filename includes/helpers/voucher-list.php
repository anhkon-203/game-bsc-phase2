<?php
/**
 * Lấy danh sách voucher và hiện vật mà các user đã đổi
 *
 * @param int $page Trang hiện tại
 * @param int $per_page Số bản ghi trên mỗi trang
 * @param string $date_from Lọc từ ngày (Y-m-d)
 * @param string $date_to Lọc đến ngày (Y-m-d)
 * @param string $search Tìm kiếm theo tên user hoặc mã voucher
 * @param string $gift_type Lọc theo loại quà: 'all', 'voucher', 'artifact'
 * @param string $voucher_type Lọc theo loại voucher: 'all', 'BSC', 'third_party'
 * @return array Mảng chứa danh sách và phân trang
 */
function game_bsc_get_voucher_redemptions_data($page = 1, $per_page = 20, $date_from = '', $date_to = '', $search = '', $gift_type = 'all', $voucher_type = 'all')
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$page = max(1, (int)$page);
	$per_page = max(1, min((int)$per_page, 100));
	$offset = ($page - 1) * $per_page;
	
	$all_redemptions = [];
	
	// ===== LẤY DỮ LIỆU VOUCHER REDEMPTIONS =====
	if ($gift_type === 'all' || $gift_type === 'voucher') {
		$voucher_where_clauses = [];
		$voucher_params = [];
		
		// Filter theo khoảng ngày
		if (!empty($date_from)) {
			$voucher_where_clauses[] = "DATE(uvr.redeemed_at) >= %s";
			$voucher_params[] = sanitize_text_field($date_from);
		}
		
		if (!empty($date_to)) {
			$voucher_where_clauses[] = "DATE(uvr.redeemed_at) <= %s";
			$voucher_params[] = sanitize_text_field($date_to);
		}
		
		// Filter theo tìm kiếm (tên user hoặc mã voucher)
		if (!empty($search)) {
			$voucher_where_clauses[] = "(u.name LIKE %s OR u.external_user_id LIKE %s)";
			$search_term = '%' . $wpdb->esc_like($search) . '%';
			$voucher_params[] = $search_term;
			$voucher_params[] = $search_term;
		}
		
		$voucher_where_sql = !empty($voucher_where_clauses) ? 'WHERE ' . implode(' AND ', $voucher_where_clauses) : '';
		
		$voucher_sql = "SELECT
			uvr.id as redemption_id,
			uvr.user_id,
			u.name as user_name,
			u.external_user_id,
			u.afacctno,
			uvr.voucher_post_id,
			uvr.redeemed_at,
			p.post_title as voucher_name,
			'voucher' as gift_type
		FROM {$prefix}user_voucher_redemptions uvr
		INNER JOIN {$prefix}users u ON uvr.user_id = u.id
		INNER JOIN {$wpdb->posts} p ON uvr.voucher_post_id = p.ID
		{$voucher_where_sql}
		ORDER BY uvr.redeemed_at DESC";
		
		$voucher_query = !empty($voucher_params) ? $wpdb->prepare($voucher_sql, ...$voucher_params) : $voucher_sql;
		$vouchers = $wpdb->get_results($voucher_query, ARRAY_A);
		
		if (!empty($vouchers)) {
			$all_redemptions = array_merge($all_redemptions, $vouchers);
		}
	}
	
	// ===== LẤY DỮ LIỆU ARTIFACT REDEMPTIONS =====
	if ($gift_type === 'all' || $gift_type === 'artifact') {
		$artifact_where_clauses = [];
		$artifact_params = [];
		
		// Filter theo khoảng ngày
		if (!empty($date_from)) {
			$artifact_where_clauses[] = "DATE(uar.redeemed_at) >= %s";
			$artifact_params[] = sanitize_text_field($date_from);
		}
		
		if (!empty($date_to)) {
			$artifact_where_clauses[] = "DATE(uar.redeemed_at) <= %s";
			$artifact_params[] = sanitize_text_field($date_to);
		}
		
		// Filter theo tìm kiếm (tên user)
		if (!empty($search)) {
			$artifact_where_clauses[] = "(u.name LIKE %s OR u.external_user_id LIKE %s)";
			$search_term = '%' . $wpdb->esc_like($search) . '%';
			$artifact_params[] = $search_term;
			$artifact_params[] = $search_term;
		}
		
		$artifact_where_sql = !empty($artifact_where_clauses) ? 'WHERE ' . implode(' AND ', $artifact_where_clauses) : '';
		
		$artifact_sql = "SELECT
			uar.id as redemption_id,
			uar.user_id,
			u.name as user_name,
			u.external_user_id,
			u.afacctno,
			uar.artifact_id,
			uar.redeemed_at,
			a.name as artifact_name,
			'artifact' as gift_type
		FROM {$prefix}user_artifact_redemptions uar
		INNER JOIN {$prefix}users u ON uar.user_id = u.id
		INNER JOIN {$prefix}artifacts a ON uar.artifact_id = a.id
		{$artifact_where_sql}
		ORDER BY uar.redeemed_at DESC";
		
		$artifact_query = !empty($artifact_params) ? $wpdb->prepare($artifact_sql, ...$artifact_params) : $artifact_sql;
		$artifacts = $wpdb->get_results($artifact_query, ARRAY_A);
		
		if (!empty($artifacts)) {
			$all_redemptions = array_merge($all_redemptions, $artifacts);
		}
	}
	
	// ===== SẮP XẾP THEO THỜI GIAN ĐỔI =====
	usort($all_redemptions, function($a, $b) {
		return strtotime($b['redeemed_at']) - strtotime($a['redeemed_at']);
	});
	
	// ===== LỌC THEO LOẠI VOUCHER (BSC hoặc Third Party) =====
	if ($voucher_type !== 'all') {
		$filtered_redemptions = [];
		foreach ($all_redemptions as $redemption) {
			// Chỉ lọc nếu là voucher
			if ($redemption['gift_type'] === 'voucher') {
				$voucher_id = (int)$redemption['voucher_post_id'];
				$current_voucher_type = get_field('voucher_type', $voucher_id) ?? 'BSC';
				
				// So sánh với filter
				if ($voucher_type === 'BSC' && $current_voucher_type === 'BSC') {
					$filtered_redemptions[] = $redemption;
				} elseif ($voucher_type === 'third_party' && $current_voucher_type !== 'BSC') {
					$filtered_redemptions[] = $redemption;
				}
			}
			// Bỏ qua artifact khi filter theo voucher_type
		}
		$all_redemptions = $filtered_redemptions;
	}
	
	// ===== TÍNH TỔNG SỐ BẢN GHI VÀ PHÂN TRANG =====
	$total_items = count($all_redemptions);
	$total_pages = ceil($total_items / $per_page);
	
	// ===== LẤY DỮ LIỆU THEO TRANG =====
	$paginated_redemptions = array_slice($all_redemptions, $offset, $per_page);
	
	// ===== FORMAT DỮ LIỆU =====
	$formatted_redemptions = [];
	$row_number = $offset + 1; // STT bắt đầu từ 1
	
	foreach ($paginated_redemptions as $redemption) {
		$is_voucher = ($redemption['gift_type'] === 'voucher');
		
		$formatted = [
			'stt' => $row_number++,
			'redemption_id' => (int)$redemption['redemption_id'],
			'user_id' => (int)$redemption['user_id'],
			'user_name' => sanitize_text_field($redemption['user_name']),
			'external_user_id' => sanitize_text_field($redemption['external_user_id']),
			'afacctno' => sanitize_text_field($redemption['afacctno'] ?? ''),
			'redeemed_at' => $redemption['redeemed_at'],
			'redeemed_at_display' => date('d/m/Y H:i', strtotime($redemption['redeemed_at'])),
			'gift_type' => $redemption['gift_type'],
			'gift_type_label' => $is_voucher ? 'Voucher' : 'Hiện vật',
		];
		
		if ($is_voucher) {
			$voucher_id = (int)$redemption['voucher_post_id'];
			$voucher_code = get_field('voucher_code', $voucher_id) ?? 'N/A';
			$voucher_type = get_field('voucher_type', $voucher_id) ?? 'BSC';
			$is_bsc_voucher = strtoupper(trim((string) $voucher_type)) === 'BSC';
			
			// Lấy thông tin validity
			$validity_data = get_field('validity', $voucher_id) ?: [];
			if (!is_array($validity_data)) {
				$validity_data = [];
			}
			$valid_from = $validity_data['valid_from'] ?? '';
			$valid_to = $validity_data['valid_to'] ?? '';
			$is_third_party_voucher = strtoupper(trim((string) $voucher_type)) === 'THIRD_PARTY';
			if ($is_third_party_voucher) {
				$valid_from = '';
				$valid_to = '';
			}
			
			// Format validity display
			$validity_display = '';
			if (!empty($valid_from) && !empty($valid_to)) {
				$validity_display = date('d/m/Y', strtotime($valid_from)) . ' - ' . date('d/m/Y', strtotime($valid_to));
			} elseif (!empty($valid_to)) {
				$validity_display = 'Đến ' . date('d/m/Y', strtotime($valid_to));
			} elseif (!empty($valid_from)) {
				$validity_display = 'Từ ' . date('d/m/Y', strtotime($valid_from));
			} else {
				$validity_display = 'Không giới hạn';
			}
			
			$formatted['voucher_id'] = $voucher_id;
			$formatted['voucher_code'] = sanitize_text_field($voucher_code);
			$formatted['voucher_name'] = sanitize_text_field($redemption['voucher_name']);
			$formatted['voucher_type'] = $voucher_type;
			$formatted['voucher_type_label'] = ($voucher_type === 'BSC') ? 'Voucher tại BSC' : 'Voucher bên thứ 3';
			if ($is_bsc_voucher) {
				$formatted['voucher_value'] = (float) (get_field('voucheramt', $voucher_id) ?? 0);
			} else {
				$formatted['voucher_value'] = (float) (get_field('voucher_selected_value', $voucher_id) ?? 0);
			}
			$formatted['points_cost'] = (int)(get_field('points_cost', $voucher_id) ?? 0);
			$formatted['validity_display'] = $validity_display;
			$formatted['valid_from'] = $valid_from;
			$formatted['valid_to'] = $valid_to;
		} else {
			// Artifact
			$artifact_id = (int)$redemption['artifact_id'];
			$formatted['artifact_id'] = $artifact_id;
			$formatted['artifact_name'] = sanitize_text_field($redemption['artifact_name']);
			$formatted['voucher_code'] = 'N/A'; // Hiện vật không có mã
			$formatted['voucher_type_label'] = 'Hiện vật';
			$formatted['validity_display'] = 'N/A'; // Hiện vật không có thời gian hiệu lực
		}
		
		$formatted_redemptions[] = $formatted;
	}
	
	return [
		'status' => 'success',
		'data' => $formatted_redemptions,
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
