<?php
if (!defined('ABSPATH')) exit;

/**
 * REST API để lấy Cơ chế đổi quà kèm số điểm và mảnh hiện tại của user
 */

add_action('rest_api_init', function () {
	
	// GET /wp-json/game-bsc/v1/gifts
	register_rest_route(NS, '/mechanism', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_gifts_mechanism',
		'permission_callback' => 'game_rest_perm_cb',
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/v1/voucher-categories
	register_rest_route(NS, '/voucher-categories', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_voucher_categories',
		'permission_callback' => 'game_rest_perm_cb',
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/v1/vouchers
	register_rest_route(NS, '/vouchers', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_vouchers_list',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'page' => [
				'required'          => false,
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Trang hiện tại (mặc định 1).',
			],
			'per_page' => [
				'required'          => false,
				'validate_callback' => function($param) {
					$per_page = (int)$param;
					return $per_page > 0 && $per_page <= 100;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Số lượng voucher trên mỗi trang (1-100, mặc định 20).',
			],
			'category_id' => [
				'required'          => false,
				'validate_callback' => function($param) {
					if ($param === null || $param === '') return true;
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Lọc voucher theo ID danh mục (game_voucher_category term_id)',
			],
		],
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/v1/vouchers/registered
	register_rest_route(NS, '/vouchers/registered', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_registered_vouchers_and_sync_fields',
		'permission_callback' => 'game_rest_perm_cb',
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/v1/gotit-vouchers
	register_rest_route(NS, '/gotit-vouchers', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_gotit_vouchers_list',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'page' => [
				'required'          => false,
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Trang hiện tại (mặc định 1).',
			],
			'per_page' => [
				'required'          => false,
				'validate_callback' => function($param) {
					$per_page = (int)$param;
					return $per_page > 0 && $per_page <= 100;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Số lượng voucher trên mỗi trang (1-100, mặc định 20).',
			],
			'category_id' => [
				'required'          => false,
				'validate_callback' => function($param) {
					if ($param === null || $param === '') return true;
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Lọc voucher Got It theo ID danh mục (game_voucher_category term_id)',
			],
		],
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/gotit-voucher-redemptions
	register_rest_route(NS, '/gotit-voucher-redemptions', [
		'methods'             => 'GET',
		'callback'            => 'game_get_user_gotit_voucher_redemptions_history',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'page' => [
				'required'          => false,
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Trang hiện tại (mặc định 1).',
			],
			'per_page' => [
				'required'          => false,
				'validate_callback' => function($param) {
					$per_page = (int)$param;
					return $per_page > 0 && $per_page <= 100;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Số lượng bản ghi trên mỗi trang (1-100, mặc định 20).',
			],
			'category_id' => [
				'required'          => false,
				'validate_callback' => function($param) {
					if ($param === null || $param === '') return true;
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'Lọc theo term_id của game_voucher_category.',
			],
		],
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/voucher-detail?voucher_id={id}
	register_rest_route(NS, '/voucher-detail', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_voucher_detail',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'voucher_id' => [
				'required'          => true,
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'ID voucher cần lấy chi tiết',
			],
		],
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/gotit-voucher-by-transaction?transaction_ref_id={ref}
	register_rest_route(NS, '/gotit-voucher-by-transaction', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_gotit_voucher_by_transaction_ref',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'transaction_ref_id' => [
				'required'          => true,
				'validate_callback' => function($param) {
					return is_string($param) && trim((string) $param) !== '';
				},
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => 'Transaction Ref ID của voucher Got It',
			],
		],
	]);
});

add_action('rest_api_init', function () {

	// POST /wp-json/game-bsc/v1/vouchers/issue
	register_rest_route(NS, '/vouchers/issue', [
		'methods'             => 'POST',
		'callback'            => 'game_bsc_issue_voucher',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'voucher_id' => [
				'required'          => true,
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint',
				'description'       => 'ID voucher cần phát hành',
			],
		],
	]);
});


add_action('rest_api_init', function () {
	register_rest_route(NS, '/gifts/redeem', [
		'methods'             => 'POST',
		'callback'            => 'game_bsc_redeem_item',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'type' => [
					'required' => true,
			'validate_callback' => function($param) {
					return in_array($param, ['voucher', 'artifact'], true);
				},
				'sanitize_callback' => 'sanitize_text_field'
			],
			'id' => [
				'required' => true,
				'validate_callback' => function($param) {
					return is_numeric($param) && (int)$param > 0;
				}
			],
		],
	]);
});


add_action('rest_api_init', function () {
	register_rest_route(NS, '/user-pieces', [
		'methods'             => 'GET',
		'callback'            => 'game_get_user_pieces',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => [
			'artifact_id' => [
				'required' => false,
				'validate_callback' => function ($param) {
					if ($param === null || $param === '') return true;
					return is_numeric($param) && (int)$param > 0;
				},
				'sanitize_callback' => 'absint'
			],
		],
	]);
});

function game_bsc_resolve_partner_logo_url($logo_value) {
	if (is_numeric($logo_value) && (int) $logo_value > 0) {
		return (string) (wp_get_attachment_image_url((int) $logo_value, 'full') ?: '');
	}

	if (is_string($logo_value)) {
		return esc_url_raw($logo_value);
	}

	return '';
}

/**
 * Lấy logo brand mặc định từ settings.
 * Nếu không có trong settings, trả về chuỗi rỗng.
 *
 * @return string URL của logo brand mặc định
 */
function game_bsc_get_default_brand_logo_url() {
	$default_logo_id = get_option('game_bsc_default_brand_logo', 0);
	if ($default_logo_id > 0) {
		return (string) (wp_get_attachment_image_url($default_logo_id, 'full') ?: '');
	}
	return '';
}

/**
 * Lấy danh sách voucher đã đăng ký từ BSC Trading API và sync vào field voucher nội bộ.
 * Map: voucherid (API) <=> voucher_code (admin game BSC).
	 * Chỉ update field khi dữ liệu thay đổi: prinpaid.
	 * voucheramt và reamt không còn được đồng bộ từ BSC API.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_registered_vouchers_and_sync_fields(WP_REST_Request $request) {
	global $wpdb;

	// Chuẩn hoá số tiền trả về từ API:
	// - Nếu không phải số => 0
	// - Nếu là số nguyên dạng float (vd: 1000.0) => ép về int để tránh chênh lệch so sánh
	// - Nếu là số thập phân thực sự => giữ nguyên float
	$normalize_registered_voucher_amount = static function ($value) {
		if (!is_numeric($value)) {
			return 0;
		}

		$number = (float) $value;
		$rounded = round($number);

		if (abs($number - $rounded) < 0.00001) {
			return (int) $rounded;
		}

		return $number;
	};

	// Helper to parse date from DD/MM/YYYY to Y-m-d H:i:s
	$parse_api_date = static function ($date_raw, $time_suffix = '00:00:00') {
		$date_raw = trim((string)$date_raw);
		if ($date_raw === '') {
			return null;
		}
		$date_parts = explode('/', $date_raw);
		if (count($date_parts) === 3) {
			return sprintf('%04d-%02d-%02d %s', $date_parts[2], $date_parts[1], $date_parts[0], $time_suffix);
		}
		return null;
	};



	// B1: Kiểm tra nonce — nếu không hợp lệ thì bỏ qua sync, vẫn trả true để tránh trigger login popup trên mobile.
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(200, true);
	}

	// B2: Kiểm tra session SSO — không trả 401 để tránh popup login trên mobile.
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(200, true);
	}

	$user_id = absint($current_user['id']);
	$prefix = $wpdb->prefix . 'game_';

	// B3: Xác thực user trong DB nội bộ game và trạng thái tài khoản.
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, status, external_user_id FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);

	if (!$user) {
		return wg_json_response(200, true);
	}

	if ((int) $user['status'] === 0) {
		return wg_json_response(200, true);
	}

	// B4: Lấy access token để gọi API Trading.
	$header = trim((string) $request->get_header('authorization'));
	if ($header === '' && isset($_COOKIE['access_token'])) {
		$header = trim((string) $_COOKIE['access_token']);
	}

	if ($header === '') {
		return wg_json_response(200, true);
	}

	$authorization_header = (stripos($header, 'Bearer ') === 0) ? $header : 'Bearer ' . $header;

	// B5: Resolve base URL Trading server - Lấy từ option game_bsc_trading_server
	$trading_server = (string) (get_option('game_bsc_trading_server') ?: '');
	if ($trading_server === '') {
		return wg_json_response(500, [], __('Cấu hình game_bsc_trading_server URL trống.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
	$trading_server = rtrim($trading_server, '/');

	// Capture trước khi đưa vào closure
	$external_user_id = sanitize_text_field((string) ($user['external_user_id'] ?? ''));

	// Trả success ngay, toàn bộ sync chạy sau khi response đã được gửi đi.
	add_action('shutdown', function () use (
		$wpdb,
		$user_id,
		$prefix,
		$external_user_id,
		$authorization_header,
		$trading_server,
		$normalize_registered_voucher_amount,
		$parse_api_date
	) {
		// B6: Gọi endpoint registeredVoucherList từ Trading API.
		$response = wp_remote_get($trading_server . '/report/registeredVoucherList', [
			'headers' => [
				'Authorization' => $authorization_header,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
			'timeout' => 20,
		]);

		if (is_wp_error($response)) {
			error_log('game_bsc_get_registered_vouchers_and_sync_fields wp_error: ' . $response->get_error_message());
			return;
		}

		$http_code = (int) wp_remote_retrieve_response_code($response);
		$body_raw = (string) wp_remote_retrieve_body($response);
		$body = json_decode($body_raw, true);

		if ($http_code < 200 || $http_code >= 300 || !is_array($body)) {
			error_log('game_bsc_get_registered_vouchers_and_sync_fields invalid_response: HTTP ' . $http_code . ' body=' . $body_raw);
			return;
		}

		if (($body['s'] ?? '') !== 'ok') {
			error_log('game_bsc_get_registered_vouchers_and_sync_fields api_error: s=' . ($body['s'] ?? '') . ' em=' . ($body['em'] ?? ''));
			return;
		}

		// Danh sách item từ API gốc.
		$registered_items = isset($body['d']) && is_array($body['d']) ? $body['d'] : [];

		// Lấy danh sách voucher_code của tất cả game_vouchers đang kích hoạt (publish) trong hệ thống game để làm tập lọc đối chiếu
		$active_voucher_codes = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE p.post_type = 'game_vouchers'
			   AND p.post_status = 'publish'
			   AND pm.meta_key = 'voucher_code'
			   AND pm.meta_value IS NOT NULL
			   AND pm.meta_value != ''"
		);
		$active_normalized_codes = array_map(function ($code) {
			return strtoupper(trim((string)$code));
		}, $active_voucher_codes ?: []);

		// B7: Lấy danh sách voucher BSC đã đổi trong DB của user này để khớp dữ liệu (chỉ lấy các voucher đang kích hoạt - publish)
		$user_redemptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT uvr.id, uvr.voucher_post_id, uvr.redeemed_at, uvr.prinpaid, uvr.gotit_expiry_date, uvr.start_date,
				        pm_code.meta_value as voucher_code
				 FROM {$prefix}user_voucher_redemptions uvr
				 INNER JOIN {$wpdb->posts} p ON uvr.voucher_post_id = p.ID
				 LEFT JOIN {$wpdb->postmeta} pm_type ON uvr.voucher_post_id = pm_type.post_id AND pm_type.meta_key = 'voucher_type'
				 LEFT JOIN {$wpdb->postmeta} pm_code ON uvr.voucher_post_id = pm_code.post_id AND pm_code.meta_key = 'voucher_code'
				 WHERE uvr.user_id = %d
				   AND p.post_type = 'game_vouchers'
				   AND p.post_status = 'publish'
				   AND (UPPER(TRIM(pm_type.meta_value)) NOT IN ('THIRD_PARTY', 'THIRD-PARTY') OR pm_type.meta_value IS NULL)
				 ORDER BY uvr.redeemed_at ASC, uvr.id ASC",
				$user_id
			),
			ARRAY_A
		);

		// Lọc danh sách voucher từ API thuộc về user này
		$api_vouchers = [];
		foreach ($registered_items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$item_custodycd = sanitize_text_field((string) ($item['custodycd'] ?? ''));
			if ($external_user_id !== '' && $item_custodycd !== '' && strcasecmp($item_custodycd, $external_user_id) !== 0) {
				continue;
			}

			$valdate_parsed = $parse_api_date($item['valdate'] ?? '', '00:00:00');
			$expdate_parsed = $parse_api_date($item['expdate'] ?? '', '23:59:59');
			$prinpaid_val = $normalize_registered_voucher_amount($item['prinpaid'] ?? 0);
			$voucher_code = strtoupper(trim((string)($item['voucherid'] ?? '')));

			if ($valdate_parsed === null || $voucher_code === '') {
				continue;
			}

			// NẾU ko tồn tại ở voucher code bên gami thì ko map thôi
			if (!in_array($voucher_code, $active_normalized_codes, true)) {
				continue;
			}

			$api_vouchers[] = [
				'valdate'             => $valdate_parsed,
				'valdate_ymd'         => date('Y-m-d', strtotime($valdate_parsed)),
				'expdate'             => $expdate_parsed,
				'prinpaid'            => $prinpaid_val,
				'voucher_code'        => $voucher_code,
				'original_voucherid'  => $item['voucherid'] ?? '',
				'original_valdate'    => $item['valdate'] ?? '',
			];
		}

		// BƯỚC 1: Ưu tiên khớp chính xác 100% cả Ngày đổi (DATE(redeemed_at) === valdate_ymd) và Mã voucher chuẩn hóa.
		$matched_db_ids     = [];
		$matched_api_indices = [];

		foreach ($api_vouchers as $index => $api_v) {
			$target_date = $api_v['valdate_ymd'];
			$target_code = $api_v['voucher_code'];

			foreach ($user_redemptions as $db_r) {
				$db_r_id = (int)$db_r['id'];
				if (in_array($db_r_id, $matched_db_ids, true)) {
					continue;
				}

				$db_date = date('Y-m-d', strtotime($db_r['redeemed_at']));
				$db_code = strtoupper(trim((string)($db_r['voucher_code'] ?? '')));

				if ($db_date === $target_date && $db_code === $target_code) {
					$matched_db_ids[]      = $db_r_id;
					$matched_api_indices[] = $index;

					$need_update = (
						$db_r['start_date']        !== $api_v['valdate'] ||
						$db_r['gotit_expiry_date']  !== $api_v['expdate'] ||
						(int)$db_r['prinpaid']      !== (int)$api_v['prinpaid']
					);

					if ($need_update) {
						$wpdb->update(
							"{$prefix}user_voucher_redemptions",
							[
								'start_date'        => $api_v['valdate'],
								'gotit_expiry_date' => $api_v['expdate'],
								'prinpaid'          => $api_v['prinpaid'],
							],
							['id' => $db_r_id]
						);
					}
					break;
				}
			}
		}

		// BƯỚC 2: Khớp fallback cho các bản ghi còn dư chỉ dựa trên Mã voucher chuẩn hóa theo thứ tự thời gian đổi (cho phép lệch ngày do BSC gom batch job phát hành).
		foreach ($api_vouchers as $index => $api_v) {
			if (in_array($index, $matched_api_indices, true)) {
				continue; // Đã được khớp ở Bước 1
			}

			$target_code = $api_v['voucher_code'];

			foreach ($user_redemptions as $db_r) {
				$db_r_id = (int)$db_r['id'];
				if (in_array($db_r_id, $matched_db_ids, true)) {
					continue; // Đã được khớp trước đó
				}

				$db_code = strtoupper(trim((string)($db_r['voucher_code'] ?? '')));

				if ($db_code === $target_code) {
					$matched_db_ids[]      = $db_r_id;
					$matched_api_indices[] = $index;

					$need_update = (
						$db_r['start_date']        !== $api_v['valdate'] ||
						$db_r['gotit_expiry_date']  !== $api_v['expdate'] ||
						(int)$db_r['prinpaid']      !== (int)$api_v['prinpaid']
					);

					if ($need_update) {
						$wpdb->update(
							"{$prefix}user_voucher_redemptions",
							[
								'start_date'        => $api_v['valdate'],
								'gotit_expiry_date' => $api_v['expdate'],
								'prinpaid'          => $api_v['prinpaid'],
							],
							['id' => $db_r_id]
						);
					}
					break;
				}
			}
		}

		// BƯỚC 3: Reset các bản ghi DB còn dư không được khớp (xử lý voucher bị hủy hoặc delay phía BSC)
		foreach ($user_redemptions as $db_r) {
			$db_r_id = (int)$db_r['id'];
			if (in_array($db_r_id, $matched_db_ids, true)) {
				continue;
			}

			if ($db_r['start_date'] === null) {
				// A. Voucher chưa từng được đồng bộ (đang đợi BSC tạo) nhưng nay ko có trong API -> reset về NULL/0
				$has_data = (
					$db_r['gotit_expiry_date'] !== null ||
					(int)$db_r['prinpaid'] !== 0
				);

				if ($has_data) {
					$wpdb->update(
						"{$prefix}user_voucher_redemptions",
						[
							'start_date'        => null,
							'gotit_expiry_date' => null,
							'prinpaid'          => 0,
						],
						['id' => $db_r_id]
					);
				}
			} else {
				// B. Voucher ĐÃ từng được đồng bộ trước đó nhưng nay biến mất khỏi API của BSC.
				// Nghĩa là BSC đã xoá voucher này khỏi API do đã tiêu hết hoặc hết hạn.
				// Cập nhật prinpaid = voucheramt để ẩn voucher khỏi UI và giữ nguyên start_date/gotit_expiry_date để làm lịch sử.
				$voucher_post_id = (int)$db_r['voucher_post_id'];
				$voucheramt = (float) (get_post_meta($voucher_post_id, 'voucheramt', true) ?: 0);

				if ((int)$db_r['prinpaid'] !== (int)$voucheramt) {
					$wpdb->update(
						"{$prefix}user_voucher_redemptions",
						['prinpaid' => $voucheramt],
						['id' => $db_r_id]
					);
				}
			}
		}
	}); // end shutdown closure

	return wg_json_response(200, true);
}

/**
 * Callback: Lấy danh mục voucher từ taxonomy WordPress.
 * Response item: id, name, logo
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_voucher_categories(WP_REST_Request $request) {
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	try {
		$terms = get_terms([
			'taxonomy'   => 'game_voucher_category',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]);

		if (is_wp_error($terms)) {
			error_log('game_bsc_get_voucher_categories error: ' . $terms->get_error_message());
			return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
		}

		$categories = [];
		foreach ($terms as $term) {
			if (!($term instanceof WP_Term)) {
				continue;
			}

			$normalized_name = sanitize_title((string) $term->name);
			$normalized_slug = sanitize_title((string) $term->slug);
			if (in_array($normalized_name, ['uncategorized', 'chua-phan-loai'], true) || in_array($normalized_slug, ['uncategorized', 'chua-phan-loai'], true)) {
				continue;
			}

			$logo_raw = get_term_meta((int) $term->term_id, '_gotit_category_image', true);
			if ($logo_raw === '' || $logo_raw === null) {
				$logo_raw = get_term_meta((int) $term->term_id, 'image', true);
			}

			$logo_url = '';
			if (is_numeric($logo_raw) && (int) $logo_raw > 0) {
				$logo_url = (string) (wp_get_attachment_image_url((int) $logo_raw, 'full') ?: '');
			} elseif (is_string($logo_raw)) {
				$logo_url = esc_url_raw($logo_raw);
			}

			$categories[] = [
				'id'   => (int) $term->term_id,
				'name' => sanitize_text_field((string) $term->name),
				'logo' => esc_url($logo_url),
			];
		}

		return wg_json_response(200, $categories, __('Lấy danh sách danh mục voucher thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
	} catch (Throwable $e) {
		error_log('game_bsc_get_voucher_categories exception: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * Callback: Lấy cơ chế đổi quà + số điểm + số mảnh của user hiện tại
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_gifts_mechanism(WP_REST_Request $request) {
	try {
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
		
		// ===== 3. LẤY CƠ CHẾ ĐỔI QUÀ (REWARD DESCRIPTIONS) =====
		$rewards_descriptions = get_option('game_bsc_rewards_descriptions', []);
		if (!is_array($rewards_descriptions)) {
			$rewards_descriptions = [];
		}
		
		// Chuẩn hoá dữ liệu descriptions
		$descriptions = [
			'points' => isset($rewards_descriptions['points'])
				? wp_kses_post($rewards_descriptions['points'])
				: '',
			'pieces' => isset($rewards_descriptions['pieces'])
				? wp_kses_post($rewards_descriptions['pieces'])
				: '',
		];
		
		// ===== 4. LẤY SỐ ĐIỂM HIỆN CÓ =====
		$points_balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance FROM {$prefix}user_points_balances WHERE user_id = %d",
				$user_id
			)
		);
		$points_balance = $points_balance !== null ? (int)$points_balance : 0;
		
		// ===== 5. LẤY SỐ MẢNH HIỆN CÓ (TỔNG QTY) =====
		$total_pieces = game_bsc_sum_valid_user_pieces($user_id);
		
		// ===== 6. CHUẨN BỊ RESPONSE =====
		$response_data = [
			'reward_descriptions' => $descriptions,
			'balance' => [
				'points' => $points_balance,
				'pieces' => $total_pieces,
			],
		];
		
		return wg_json_response(200, $response_data, __('Lấy thông tin cơ chế đổi quà thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Throwable $e) {
		// Log lỗi nếu cần
		error_log('game_bsc_get_gifts_mechanism error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * Callback: Lấy danh sách voucher Got It
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_gotit_vouchers_list(WP_REST_Request $request) {
	$request->set_param('only_gotit', '1');
	return game_bsc_get_vouchers_list($request);
}

/**
 * Callback: Lấy danh sách tất cả voucher
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_vouchers_list(WP_REST_Request $request) {
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	try {
		global $wpdb;

		$normalize_bsc_amount = static function ($value) {
			if (!is_numeric($value)) {
				return 0;
			}

			$number = (float) $value;
			$rounded = round($number);

			if (abs($number - $rounded) < 0.00001) {
				return (int) $rounded;
			}

			return $number;
		};

		// ===== 1. QUERY VOUCHERS =====
		$category_id = absint($request->get_param('category_id') ?? 0);
		$only_gotit  = filter_var($request->get_param('only_gotit'), FILTER_VALIDATE_BOOLEAN);
		$page        = max(1, absint($request->get_param('page') ?? 1));
		$per_page    = min(100, max(1, absint($request->get_param('per_page') ?? 20)));
		$selected_term = null;
		$selected_gotit_category_id = 0;
		$query_total_items = 0;
		$query_total_pages = 0;

		$args = [
			'post_type'      => 'game_vouchers',
			'post_status'    => 'publish',
			'orderby'        => [
				'post_date' => 'DESC',
				'ID'        => 'DESC',
			],
			'meta_query'     => [
				[
					'key'     => 'is_active',
					'value'   => '1',
					'compare' => '=',
					'type'    => 'NUMERIC'
				]
			]
		];

		if (!$only_gotit) {
			$args['meta_query'][] = [
				'relation' => 'OR',
				[
					'key'     => 'voucher_type',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'voucher_type',
					'value'   => ['THIRD_PARTY', 'THIRD-PARTY', 'third_party', 'third-party'],
					'compare' => 'NOT IN',
				],
			];
		}

		if ($only_gotit) {
			$args['meta_query'][] = [
				'key'     => 'gotit_product_id',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC'
			];
		}

		// ===== LỌC THEO DANH MỤC (nếu có truyền category_id) =====
		if ($category_id > 0) {
			$term = get_term($category_id, 'game_voucher_category');
			if (!$term || is_wp_error($term)) {
				return wg_json_response(404, [], __('Không tìm thấy danh mục voucher.'));
			}
			$selected_term = $term;
			$selected_gotit_category_id = absint(get_term_meta($category_id, '_gotit_category_id', true));

			$args['tax_query'] = [
				[
					'taxonomy' => 'game_voucher_category',
					'field'    => 'term_id',
					'terms'    => $category_id,
				]
			];
		}

		if ($only_gotit) {
			$args['posts_per_page'] = $per_page;
			$args['paged']          = $page;
			$args['no_found_rows']  = false;
		} else {
			// Query toàn bộ rồi filter hạn dùng ở PHP để pagination khớp với dữ liệu hiển thị.
			$args['posts_per_page'] = -1;
			$args['no_found_rows']  = true;
		}

		$query_result = new WP_Query($args);
		$all_vouchers = $query_result->posts;
		$query_total_items = $only_gotit ? (int) $query_result->found_posts : (int) $query_result->post_count;
		$query_total_pages = $only_gotit ? (int) $query_result->max_num_pages : ($query_total_items > 0 ? 1 : 0);

		// Fallback: nếu taxonomy relation bị thiếu nhưng voucher vẫn có meta Got It category id,
		// lọc theo `_game_bsc_gotit_category_id` để tránh mất dữ liệu khi truy vấn theo danh mục.
		if (
			empty($all_vouchers)
			&& $only_gotit
			&& $category_id > 0
			&& $selected_gotit_category_id > 0
		) {
			$fallback_args = $args;
			unset($fallback_args['tax_query']);
			$fallback_args['meta_query'][] = [
				'key'     => '_game_bsc_gotit_category_id',
				'value'   => (string) $selected_gotit_category_id,
				'compare' => '=',
			];

			$fallback_query = new WP_Query($fallback_args);
			$all_vouchers = $fallback_query->posts;
			$query_total_items = (int) $fallback_query->found_posts;
			$query_total_pages = (int) $fallback_query->max_num_pages;
		}

		if (empty($all_vouchers)) {
			$empty_data = [
				'vouchers' => [],
				'pagination' => [
					'current_page' => $page,
					'per_page' => $per_page,
					'total_items' => 0,
					'total_pages' => 0,
					'has_next' => false,
					'has_prev' => false,
				],
			];

			if ($only_gotit && $category_id > 0) {
				$category_name = $selected_term instanceof WP_Term
					? sanitize_text_field((string) $selected_term->name)
					: ('ID ' . $category_id);

				return wg_json_response(
					200,
					$empty_data,
					sprintf(
						__('Danh mục "%s" hiện chưa có voucher Got It.', WG_GAME_PLUGIN_TEXTDOMAIN),
						$category_name
					)
				);
			}

			return wg_json_response(
				200,
				$empty_data,
				$only_gotit
					? __('Không tìm thấy voucher Got It nào.', WG_GAME_PLUGIN_TEXTDOMAIN)
					: __('Không tìm thấy voucher nào.', WG_GAME_PLUGIN_TEXTDOMAIN)
			);
		}

		// ===== 2. FORMAT VOUCHER DATA =====
		$formatted_vouchers = [];

		foreach ($all_vouchers as $post) {
			$post_id = (int)$post->ID;

			// Lấy tất cả ACF fields
			$voucher_code = get_field('voucher_code', $post_id);
			$voucher_type = get_field('voucher_type', $post_id);
			$points_cost = get_field('points_cost', $post_id);

			if ($only_gotit) {
				$voucher_display_name = sanitize_text_field((string) (get_field('voucher_display_name', $post_id) ?: $post->post_title));
				$voucher_selected_value_raw = get_field('voucher_selected_value', $post_id);
				$voucher_selected_value = is_scalar($voucher_selected_value_raw)
					? sanitize_text_field((string) $voucher_selected_value_raw)
					: '';

				$voucher_image_url = esc_url_raw((string) (get_field('voucher_image_url', $post_id) ?: ''));
				if ($voucher_image_url === '') {
					$thumbnail_id = get_post_thumbnail_id($post_id);
					if ($thumbnail_id) {
						$voucher_image_url = (string) (wp_get_attachment_image_url($thumbnail_id, 'full') ?: '');
					}
				}

				$formatted_vouchers[] = [
					'id' => $post_id,
					'title' => sanitize_text_field($post->post_title),
					'code' => sanitize_text_field($voucher_code ?? ''),
					'type' => sanitize_text_field($voucher_type ?? 'THIRD_PARTY'),
					'voucher_display_name' => $voucher_display_name,
					'voucher_image_url' => esc_url($voucher_image_url),
					'voucher_selected_value' => $voucher_selected_value,
					'points_cost' => (int) $points_cost,
				];

				continue;
			}

			$voucher_type_raw = strtoupper(trim((string) $voucher_type));
			$is_third_party_voucher = in_array($voucher_type_raw, ['THIRD_PARTY', 'THIRD-PARTY'], true);
			$is_bsc_voucher = !$is_third_party_voucher;
			$partner = get_field('partner', $post_id);
			if (!is_array($partner)) {
				$partner = [];
			}
			$quantity = get_field('quantity', $post_id);
			$is_active = get_field('is_active', $post_id);
			$validity = get_field('validity', $post_id);
			$redemption_count = get_field('redemption_count', $post_id);
			$thumbnail_id = get_post_thumbnail_id($post_id);

			// ===== LẤY DANH MỤC CỦA VOUCHER =====
			$raw_terms = wp_get_object_terms($post_id, 'game_voucher_category', ['fields' => 'all']);
			$categories = [];
			if (!is_wp_error($raw_terms)) {
				foreach ($raw_terms as $term) {
					$category_image = esc_url_raw((string) get_term_meta((int) $term->term_id, '_gotit_category_image', true));
					$categories[] = [
						'id'   => (int)$term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
						'image' => $category_image,
					];
				}
			}

			// Kiểm tra thời gian hiệu lực
			$now = game_now();
			$valid_from = $validity['valid_from'] ?? '';
			$valid_to = $validity['valid_to'] ?? '';
			$is_valid_time = true;

			if ($is_third_party_voucher) {
				$valid_from = '';
				$valid_to = '';
			}

			if (!empty($valid_from) && $now < $valid_from) {
				$is_valid_time = false; // Chưa bắt đầu
			}
			if (!empty($valid_to) && $now > $valid_to) {
				$is_valid_time = false; // Đã hết hạn
			}

			// Kiểm tra còn số lượng không
			if (!$is_valid_time) {
				continue;
			}

			$is_available = (int)$quantity > (int)$redemption_count;

			// 1. Voucher BSC đã hết số lượng: ẩn, không hiển thị
			if (!$is_available) {
				continue;
			}

			// Format thumbnail URL
			$thumbnail_url = '';
			if ($thumbnail_id) {
				$thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full') ?: '';
			}

			// Ưu tiên lấy logo từ settings trước
			$partner_logo_url = game_bsc_get_default_brand_logo_url();

			// Nếu settings không có, lấy từ voucher cụ thể
			if ($partner_logo_url === '') {
				$partner_logo_url = game_bsc_resolve_partner_logo_url($partner['logo'] ?? '');
				if ($partner_logo_url === '') {
					$partner_logo_url = esc_url_raw((string) get_field('voucher_brand_logo_url', $post_id));
				}
			}

			$voucher_item = [
				'id' => $post_id,
				'title' => sanitize_text_field($post->post_title),
				'code' => sanitize_text_field($voucher_code ?? ''),
				'type' => sanitize_text_field($voucher_type ?? 'BSC'),
				'categories' => $categories,
				'partner' => [
					'name' => sanitize_text_field($partner['name'] ?? ''),
					'url' => esc_url($partner['url'] ?? ''),
					'logo_url' => esc_url($partner_logo_url),
				],
				'points_cost' => (int)$points_cost,
				'quantity' => (int)$quantity,
				'quantity_remaining' => max(0, (int)$quantity - (int)$redemption_count),
				'is_active' => (bool)$is_active,
				// 2. Chưa đổi thì chưa có hạn sử dụng
				'validity' => [
					'valid_from' => null,
					'valid_to'   => null,
				],
				'is_valid_time' => $is_valid_time,
				'is_available' => $is_available,
				'redemption_count' => 0,
				'thumbnail_url' => $thumbnail_url,
			];

			if ($is_bsc_voucher) {
				// Điều kiện và điều khoản (wysiwyg field riêng cho BSC)
				$bsc_terms_raw = get_field('bsc_voucher_terms', $post_id);
				$voucher_item['terms'] = wp_kses_post((string) ($bsc_terms_raw ?: ''));
			}

			$formatted_vouchers[] = $voucher_item;
		}

		// ===== 3. RETURN RESPONSE =====
		$success_message = $only_gotit
			? __('Lấy danh sách voucher Got It thành công.', WG_GAME_PLUGIN_TEXTDOMAIN)
			: __('Lấy danh sách voucher thành công.', WG_GAME_PLUGIN_TEXTDOMAIN);

		if ($only_gotit) {
			$total_items = $query_total_items;
			$total_pages = $query_total_pages;
		} else {
			$total_items = count($formatted_vouchers);
			$total_pages = $total_items > 0 ? (int) ceil($total_items / $per_page) : 0;
		}

		if ($total_pages > 0 && $page > $total_pages) {
			return wg_json_response(400, [], __('Số trang vượt quá tổng số trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		if ($only_gotit) {
			$paged_vouchers = $formatted_vouchers;
		} else {
			$offset = ($page - 1) * $per_page;
			$paged_vouchers = array_slice(array_values($formatted_vouchers), $offset, $per_page);
		}

		return wg_json_response(
			200,
			[
				'vouchers' => $paged_vouchers,
				'pagination' => [
					'current_page' => $page,
					'per_page' => $per_page,
					'total_items' => $total_items,
					'total_pages' => $total_pages,
					'has_next' => $page < $total_pages,
					'has_prev' => $page > 1,
				],
			],
			$success_message
		);

	} catch (Throwable $e) {
		error_log('game_bsc_get_vouchers_list error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.'), 500);
	}
}

/**
 * Callback: Lấy lịch sử voucher Got It mà user đã đổi.
 *
 * Response bao gồm:
 * - vouchers: Danh sách voucher đã đổi
 * - pagination: phân trang chuẩn API
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_get_user_gotit_voucher_redemptions_history(WP_REST_Request $request)
{
	global $wpdb;

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
	$prefix = $wpdb->prefix . 'game_';

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

	$page = max(1, (int)($request->get_param('page') ?? 1));
	$per_page = min(max((int)($request->get_param('per_page') ?? 20), 1), 100);
	$offset = ($page - 1) * $per_page;

	$category_id = absint($request->get_param('category_id') ?? 0);
	if ($category_id > 0) {
		$term = get_term($category_id, 'game_voucher_category');
		if (!$term || is_wp_error($term)) {
			return wg_json_response(404, [], __('Không tìm thấy danh mục voucher.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
	}

	$gotit_transaction_join_sql = "
		LEFT JOIN (
			SELECT g1.redemption_id, g1.transaction_ref_id, g1.gotit_expiry_date, g1.gotit_state_name, g1.gotit_status
			FROM {$prefix}gotit_transactions g1
			INNER JOIN (
				SELECT redemption_id, MAX(id) AS max_id
				FROM {$prefix}gotit_transactions
				GROUP BY redemption_id
			) g2 ON g2.max_id = g1.id
		) gtxn ON gtxn.redemption_id = uvr.id
	";

	$where_sql = "uvr.user_id = %d
		AND EXISTS (
			SELECT 1
			FROM {$wpdb->postmeta} pm
			WHERE pm.post_id = uvr.voucher_post_id
			  AND pm.meta_key = 'voucher_type'
			  AND UPPER(TRIM(pm.meta_value)) IN ('THIRD_PARTY', 'THIRD-PARTY')
		)
		AND COALESCE(NULLIF(uvr.transaction_ref_id, ''), NULLIF(gtxn.transaction_ref_id, ''), '') <> ''";
	$where_args = [$user_id];

	if ($category_id > 0) {
		$where_sql .= "
			AND EXISTS (
				SELECT 1
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tr.object_id = uvr.voucher_post_id
				  AND tt.taxonomy = 'game_voucher_category'
				  AND tt.term_id = %d
			)";
		$where_args[] = $category_id;
	}

	$total_sql = "SELECT COUNT(*) FROM {$prefix}user_voucher_redemptions uvr {$gotit_transaction_join_sql} WHERE {$where_sql}";
	$total_vouchers = (int) $wpdb->get_var($wpdb->prepare($total_sql, $where_args));
	$total_pages = $total_vouchers > 0 ? (int)ceil($total_vouchers / $per_page) : 0;

	if ($total_pages > 0 && $page > $total_pages) {
		return wg_json_response(400, [], __('Số trang vượt quá tổng số trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$rows_sql = "
		SELECT
			uvr.id AS redemption_id,
			uvr.voucher_post_id,
			uvr.redeemed_at,
			COALESCE(NULLIF(uvr.transaction_ref_id, ''), gtxn.transaction_ref_id, '') AS transaction_ref_id,
			gtxn.gotit_expiry_date AS expiry_date,
			gtxn.gotit_state_name,
			gtxn.gotit_status
		FROM {$prefix}user_voucher_redemptions uvr
		{$gotit_transaction_join_sql}
		WHERE {$where_sql}
		ORDER BY (CASE WHEN gtxn.gotit_state_name = 'USED' THEN 1 ELSE 0 END) ASC, uvr.redeemed_at DESC, uvr.id DESC
		LIMIT %d OFFSET %d
	";

	$rows_args = array_merge($where_args, [$per_page, $offset]);
	$rows = $wpdb->get_results($wpdb->prepare($rows_sql, $rows_args), ARRAY_A);

	$vouchers = [];
	foreach ((array) $rows as $row) {
		$voucher_post_id = absint($row['voucher_post_id'] ?? 0);
		$voucher_post = $voucher_post_id > 0 ? get_post($voucher_post_id) : null;

		$voucher_title = $voucher_post instanceof WP_Post
			? sanitize_text_field((string) $voucher_post->post_title)
			: '';

		$voucher_code = sanitize_text_field((string) (get_field('voucher_code', $voucher_post_id) ?? ''));
		$voucher_type = sanitize_text_field((string) (get_field('voucher_type', $voucher_post_id) ?? 'THIRD_PARTY'));
		$points_cost = (int) (get_field('points_cost', $voucher_post_id) ?: 0);
		$voucher_selected_value_raw = get_field('voucher_selected_value', $voucher_post_id);
		$voucher_selected_value = is_scalar($voucher_selected_value_raw)
			? sanitize_text_field((string) $voucher_selected_value_raw)
			: '';

		$thumbnail_url = esc_url_raw((string) (get_field('voucher_image_url', $voucher_post_id) ?? ''));
		if ($thumbnail_url === '' && $voucher_post_id > 0) {
			$thumbnail_id = get_post_thumbnail_id($voucher_post_id);
			if ($thumbnail_id) {
				$thumbnail_url = (string) (wp_get_attachment_image_url($thumbnail_id, 'full') ?: '');
			}
		}

		$transaction_ref_id = sanitize_text_field((string) ($row['transaction_ref_id'] ?? ''));
		$expiry_date = sanitize_text_field((string) ($row['expiry_date'] ?? ''));

		// Kiểm tra trạng thái đã sử dụng của voucher Got It
		$gotit_state_name = strtoupper(trim((string) ($row['gotit_state_name'] ?? '')));
		$is_used = ($gotit_state_name === 'USED');

		$vouchers[] = [
			'voucher_redemption_id' => (int) ($row['redemption_id'] ?? 0),
			'transaction_ref_id' => $transaction_ref_id,
			'redeemed_at' => sanitize_text_field((string) ($row['redeemed_at'] ?? '')),
			'is_used' => $is_used,
			'voucher' => [
				'id' => $voucher_post_id,
				'title' => $voucher_title,
				'code' => $voucher_code,
				'type' => $voucher_type,
				'voucher_value' => $voucher_selected_value,
				'points_cost' => $points_cost,
				'expiry_date' => $expiry_date,
				'thumbnail_url' => esc_url($thumbnail_url),
			],
		];
	}

	$response_data = [
		'vouchers' => $vouchers,
		'pagination' => [
			'current_page' => $page,
			'per_page' => $per_page,
			'total_items' => $total_vouchers,
			'total_pages' => $total_pages,
			'has_next' => $total_pages > 0 && $page < $total_pages,
			'has_prev' => $page > 1,
		],
	];

	return wg_json_response(
		200,
		$response_data,
		__('Lấy lịch sử voucher Got It đã đổi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN)
	);
}

/**
 * Callback: Lấy chi tiết voucher (ưu tiên use-case voucher Got It)
 *
 * Response bao gồm:
 * - terms_and_conditions: Điều kiện và điều khoản
 * - applicable_stores: Cửa hàng áp dụng
 * - applicable_unit: Đơn vị áp dụng
 * - denomination: Danh sách mệnh giá theo gotit_product_id
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_voucher_detail(WP_REST_Request $request) {
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	try {
		$voucher_id = absint($request->get_param('voucher_id'));
		if ($voucher_id < 1) {
			return wg_json_response(400, [], __('voucher_id không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN), 400);
		}

		$voucher_post = get_post($voucher_id);
		if (!$voucher_post || $voucher_post->post_type !== 'game_vouchers' || $voucher_post->post_status !== 'publish') {
			return wg_json_response(404, [], __('Không tìm thấy voucher.', WG_GAME_PLUGIN_TEXTDOMAIN), 404);
		}

		$voucher_type = sanitize_text_field((string) (get_field('voucher_type', $voucher_id) ?? ''));
		$gotit_product_id = (int) (get_field('gotit_product_id', $voucher_id) ?? 0);

		$voucher_terms = (string) (get_field('voucher_terms', $voucher_id) ?? '');
		$voucher_service_guide = (string) (get_field('voucher_service_guide', $voucher_id) ?? '');

		$stores_json = (string) get_post_meta($voucher_id, '_game_bsc_gotit_applicable_stores_json', true);
		$stores_list = json_decode($stores_json, true);
		if (!is_array($stores_list)) {
			$stores_list = [];
		}

		$applicable_stores = [];
		foreach ($stores_list as $store_item) {
			if (is_array($store_item) && isset($store_item['raw']) && is_array($store_item['raw'])) {
				$applicable_stores[] = $store_item['raw'];
			}
		}

		$partner_data = get_field('partner', $voucher_id);
		if (!is_array($partner_data)) {
			$partner_data = [];
		}

		$unit_name = sanitize_text_field((string) ($partner_data['name'] ?? get_field('voucher_brand_name', $voucher_id) ?? ''));
		$unit_url = esc_url_raw((string) ($partner_data['url'] ?? get_field('voucher_link_url', $voucher_id) ?? ''));

		// Ưu tiên lấy logo từ settings trước
		$unit_logo_url = game_bsc_get_default_brand_logo_url();

		// Nếu settings không có, lấy từ voucher cụ thể
		if ($unit_logo_url === '') {
			$unit_logo_url = game_bsc_resolve_partner_logo_url($partner_data['logo'] ?? '');
			if ($unit_logo_url === '') {
				$unit_logo_url = esc_url_raw((string) (get_field('voucher_brand_logo_url', $voucher_id) ?? ''));
			}
		}

		$denominations = [];
		if ($gotit_product_id > 0) {
			$product_vouchers = get_posts([
				'post_type'      => 'game_vouchers',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'all',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => [
					[
						'key'     => 'gotit_product_id',
						'value'   => (string) $gotit_product_id,
						'compare' => '=',
					],
					[
						'key'     => 'voucher_type',
						'value'   => ['THIRD_PARTY', 'THIRD-PARTY'],
						'compare' => 'IN',
					],
					[
						'key'     => 'is_active',
						'value'   => '1',
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]);

			$seen_price_ids = [];

			foreach ($product_vouchers as $product_voucher_post) {
				$product_voucher_id = (int) $product_voucher_post->ID;
				$product_price_id = (int) (get_field('gotit_product_price_id', $product_voucher_id) ?? 0);
				if ($product_price_id < 1) {
					continue;
				}
				if (isset($seen_price_ids[$product_price_id])) {
					continue;
				}
				$seen_price_ids[$product_price_id] = true;

				$selected_value_raw = get_field('voucher_selected_value', $product_voucher_id);
				$selected_value = is_scalar($selected_value_raw)
					? sanitize_text_field((string) $selected_value_raw)
					: '';
				$points_cost = (int) (get_field('points_cost', $product_voucher_id) ?? 0);

				$numeric_value = 0;
				if ($selected_value !== '' && function_exists('game_bsc_gotit_extract_amount_from_text')) {
					$numeric_value = (int) game_bsc_gotit_extract_amount_from_text($selected_value);
				}
				if ($numeric_value < 1 && is_numeric($selected_value)) {
					$numeric_value = (int) $selected_value;
				}
				if ($numeric_value < 1 && $points_cost > 0) {
					$numeric_value = $points_cost;
				}

				$denominations[] = [
					'voucher_id' => $product_voucher_id,
					'gotit_product_price_id' => $product_price_id,
					'label' => $selected_value !== '' ? $selected_value : ($numeric_value > 0 ? (string) $numeric_value : ''),
					'value' => $numeric_value,
					'points_cost' => $points_cost,
					'is_current_voucher' => ($product_voucher_id === $voucher_id),
				];
			}

			usort($denominations, static function($a, $b) {
				$a_value = (int) ($a['value'] ?? 0);
				$b_value = (int) ($b['value'] ?? 0);

				if ($a_value === $b_value) {
					return (int) ($a['gotit_product_price_id'] ?? 0) <=> (int) ($b['gotit_product_price_id'] ?? 0);
				}

				if ($a_value === 0) return 1;
				if ($b_value === 0) return -1;

				return $a_value <=> $b_value;
			});
		}

		return wg_json_response(
			200,
			[
				'gotit_product_id' => $gotit_product_id,
				'terms_and_conditions' => [
					'terms' => wp_kses_post($voucher_terms),
					'service_guide' => wp_kses_post($voucher_service_guide),
				],
				'applicable_stores' => array_values($applicable_stores),
				'brand_info' => [
					'name' => $unit_name,
					'url' => esc_url($unit_url),
					'logo_url' => esc_url($unit_logo_url),
				],
				'denomination' => array_values($denominations),
			],
			__('Lấy chi tiết voucher thành công.', WG_GAME_PLUGIN_TEXTDOMAIN)
		);
	} catch (Throwable $e) {
		error_log('game_bsc_get_voucher_detail error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * Kiểm tra payload có trạng thái đã sử dụng (state code = 4) hay không.
 * Fallback parser khi dữ liệu từ Got It trả về không đúng cấu trúc kỳ vọng.
 *
 * @param mixed $payload
 * @return bool
 */
function game_bsc_gotit_payload_has_used_state($payload) {
	if (!is_array($payload)) {
		return false;
	}

	$stack = [$payload];
	while (!empty($stack)) {
		$node = array_pop($stack);
		if (!is_array($node)) {
			continue;
		}

		foreach (['newStateCode', 'stateCode', 'state_code', 'state', 'status', 'code'] as $state_key) {
			if (isset($node[$state_key]) && is_numeric($node[$state_key]) && (int) $node[$state_key] === 4) {
				return true;
			}
		}

		foreach (['usedInfo', 'used_info'] as $used_info_key) {
			if (!isset($node[$used_info_key]) || !is_array($node[$used_info_key])) {
				continue;
			}

			foreach ($node[$used_info_key] as $value) {
				if ($value !== null && $value !== '') {
					return true;
				}
			}
		}

		foreach ($node as $child) {
			if (is_array($child)) {
				$stack[] = $child;
			}
		}
	}

	return false;
}

/**
 * Lấy danh sách cửa hàng áp dụng của voucher từ post meta Got It.
 *
 * @param int $voucher_id
 * @return array
 */
function game_bsc_get_voucher_applicable_stores($voucher_id) {
	$stores_json = (string) get_post_meta($voucher_id, '_game_bsc_gotit_applicable_stores_json', true);
	$stores_list = json_decode($stores_json, true);
	if (!is_array($stores_list)) {
		$stores_list = [];
	}

	$applicable_stores = [];
	foreach ($stores_list as $store_item) {
		if (is_array($store_item) && isset($store_item['raw']) && is_array($store_item['raw'])) {
			$applicable_stores[] = $store_item['raw'];
		}
	}

	return array_values($applicable_stores);
}

/**
 * Trả về bảng pattern Code 128 chuẩn dùng để dựng barcode SVG.
 *
 * @return array<int, string>
 */
function game_bsc_get_code128_patterns() {
	static $patterns = null;

	if ($patterns !== null) {
		return $patterns;
	}

	$patterns = [
		0 => '212222',
		1 => '222122',
		2 => '222221',
		3 => '121223',
		4 => '121322',
		5 => '131222',
		6 => '122213',
		7 => '122312',
		8 => '132212',
		9 => '221213',
		10 => '221312',
		11 => '231212',
		12 => '112232',
		13 => '122132',
		14 => '122231',
		15 => '113222',
		16 => '123122',
		17 => '123221',
		18 => '223211',
		19 => '221132',
		20 => '221231',
		21 => '213212',
		22 => '223112',
		23 => '312131',
		24 => '311222',
		25 => '321122',
		26 => '321221',
		27 => '312212',
		28 => '322112',
		29 => '322211',
		30 => '212123',
		31 => '212321',
		32 => '232121',
		33 => '111323',
		34 => '131123',
		35 => '131321',
		36 => '112313',
		37 => '132113',
		38 => '132311',
		39 => '211313',
		40 => '231113',
		41 => '231311',
		42 => '112133',
		43 => '112331',
		44 => '132131',
		45 => '113123',
		46 => '113321',
		47 => '133121',
		48 => '313121',
		49 => '211331',
		50 => '231131',
		51 => '213113',
		52 => '213311',
		53 => '213131',
		54 => '311123',
		55 => '311321',
		56 => '331121',
		57 => '312113',
		58 => '312311',
		59 => '332111',
		60 => '314111',
		61 => '221411',
		62 => '431111',
		63 => '111224',
		64 => '111422',
		65 => '121124',
		66 => '121421',
		67 => '141122',
		68 => '141221',
		69 => '112214',
		70 => '112412',
		71 => '122114',
		72 => '122411',
		73 => '142112',
		74 => '142211',
		75 => '241211',
		76 => '221114',
		77 => '413111',
		78 => '241112',
		79 => '134111',
		80 => '111242',
		81 => '121142',
		82 => '121241',
		83 => '114212',
		84 => '124112',
		85 => '124211',
		86 => '411212',
		87 => '421112',
		88 => '421211',
		89 => '212141',
		90 => '214121',
		91 => '412121',
		92 => '111143',
		93 => '111341',
		94 => '131141',
		95 => '114113',
		96 => '114311',
		97 => '411113',
		98 => '411311',
		99 => '113141',
		100 => '114131',
		101 => '311141',
		102 => '411131',
		103 => '211412',
		104 => '211214',
		105 => '211232',
		106 => '2331112',
	];

	return $patterns;
}

/**
 * Tạo barcode Code 128 dưới dạng data URI SVG và cache theo mã voucher.
 *
 * @param string $voucher_code
 * @return string
 */
function game_bsc_generate_code128_barcode_data_uri($voucher_code) {
	$voucher_code = trim(sanitize_text_field((string) $voucher_code));
	if ($voucher_code === '') {
		return '';
	}

	if (!preg_match('/^[\x20-\x7E]+$/', $voucher_code)) {
		$voucher_code = preg_replace('/[^\x20-\x7E]/', '', $voucher_code);
	}

	if ($voucher_code === '') {
		return '';
	}

	static $runtime_cache = [];
	$cache_key = md5($voucher_code);
	if (isset($runtime_cache[$cache_key])) {
		return $runtime_cache[$cache_key];
	}

	$object_cache_key = 'code128_' . $cache_key;
	$cached_value = wp_cache_get($object_cache_key, 'game_bsc_barcode');
	if (is_string($cached_value) && $cached_value !== '') {
		$runtime_cache[$cache_key] = $cached_value;
		return $cached_value;
	}

	$patterns = game_bsc_get_code128_patterns();
	$data_codes = [];
	$characters = str_split($voucher_code);
	foreach ($characters as $character) {
		$data_codes[] = ord($character) - 32;
	}

	$checksum = 104;
	foreach ($data_codes as $index => $code) {
		$checksum += ($code * ($index + 1));
	}
	$checksum %= 103;

	$encoded_codes = array_merge([104], $data_codes, [$checksum, 106]);

	$module_width = 2;
	$bar_height = 72;
	$quiet_zone = 4;
	$x = $quiet_zone * $module_width;
	$rects = [];

	foreach ($encoded_codes as $code) {
		$pattern = $patterns[$code] ?? '';
		if ($pattern === '') {
			continue;
		}

		$is_bar = true;
		$pattern_lengths = str_split($pattern);
		foreach ($pattern_lengths as $length) {
			$width = ((int) $length) * $module_width;
			if ($is_bar) {
				$rects[] = sprintf('<rect x="%d" y="0" width="%d" height="%d" fill="#111" />', $x, $width, $bar_height);
			}

			$x += $width;
			$is_bar = !$is_bar;
		}
	}

	$total_width = $x + ($quiet_zone * $module_width);
	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges" role="img" aria-label="Code 128 barcode"><rect width="100%%" height="100%%" fill="#fff" />%s</svg>',
		$total_width,
		$bar_height,
		$total_width,
		$bar_height,
		implode('', $rects)
	);

	$data_uri = 'data:image/svg+xml;base64,' . base64_encode($svg);
	$runtime_cache[$cache_key] = $data_uri;
	wp_cache_set($object_cache_key, $data_uri, 'game_bsc_barcode', HOUR_IN_SECONDS);

	return $data_uri;
}

/**
 * Callback: Lấy thông tin voucher Got It theo transaction_ref_id.
 * Response gồm:
 * - thông tin voucher
 * - thời hạn sử dụng
 * - điều kiện và điều khoản
 * - trạng thái đã sử dụng (true/false)
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_gotit_voucher_by_transaction_ref(WP_REST_Request $request) {
	global $wpdb;

	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	try {
		$current_user = game_sso_require_session();
		if (is_wp_error($current_user) || empty($current_user['id'])) {
			return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		$user_id = absint($current_user['id']);
		$transaction_ref_id = sanitize_text_field((string) ($request->get_param('transaction_ref_id') ?? ''));
		if ($transaction_ref_id === '') {
			return wg_json_response(400, [], __('transaction_ref_id không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN), 400);
		}

		$prefix = $wpdb->prefix . 'game_';
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}gotit_transactions WHERE transaction_ref_id = %s ORDER BY id DESC LIMIT 1",
				$transaction_ref_id
			),
			ARRAY_A
		);

		if (empty($transaction) || !is_array($transaction)) {
			return wg_json_response(404, [], __('Không tìm thấy voucher Got It theo transaction_ref_id.', WG_GAME_PLUGIN_TEXTDOMAIN), 404);
		}

		$transaction_user_id = (int) ($transaction['user_id'] ?? 0);
		if ($transaction_user_id !== $user_id) {
			return wg_json_response(403, [], __('Bạn không có quyền truy cập voucher này.', WG_GAME_PLUGIN_TEXTDOMAIN), 403);
		}

		$voucher_post_id = (int) ($transaction['voucher_post_id'] ?? 0);
		$voucher_post = $voucher_post_id > 0 ? get_post($voucher_post_id) : null;

		$voucher_terms = '';
		$voucher_service_guide = '';
		$voucher_brand_name = '';
		$voucher_brand_url = '';
		$voucher_brand_logo_url = '';
		$voucher_image_url = '';
		$applicable_stores = [];

		if ($voucher_post && $voucher_post->post_type === 'game_vouchers') {
			// Tối ưu: Lấy tất cả post meta trong 1 query thay vì nhiều get_field()
			$post_meta = get_post_custom($voucher_post_id);
			$get_meta_value = function($key) use ($post_meta) {
				return isset($post_meta[$key][0]) ? (string) $post_meta[$key][0] : '';
			};

			$voucher_terms = $get_meta_value('voucher_terms');
			$voucher_service_guide = $get_meta_value('voucher_service_guide');
			$voucher_brand_name = $get_meta_value('voucher_brand_name');
			$voucher_brand_url = $get_meta_value('voucher_link_url');
			$voucher_brand_logo_url_field = $get_meta_value('voucher_brand_logo_url');
			$voucher_image_url = $get_meta_value('voucher_image_url');

			// Lấy partner data từ cached meta
			$partner_json = $get_meta_value('partner');
			$partner_data = [];
			if (!empty($partner_json)) {
				$decoded_partner = json_decode($partner_json, true);
				if (is_array($decoded_partner)) {
					$partner_data = $decoded_partner;
				}
			}

			// Lấy applicable_stores từ cached meta
			$stores_json = $get_meta_value('_game_bsc_gotit_applicable_stores_json');
			if (!empty($stores_json)) {
				$stores_list = json_decode($stores_json, true);
				if (is_array($stores_list)) {
					foreach ($stores_list as $store_item) {
						if (is_array($store_item) && isset($store_item['raw']) && is_array($store_item['raw'])) {
							$applicable_stores[] = $store_item['raw'];
						}
					}
				}
			}

			// Brand info - ưu tiên settings trước
			$voucher_brand_logo_url = game_bsc_get_default_brand_logo_url();

			if ($voucher_brand_logo_url === '') {
				$partner_logo = $partner_data['logo'] ?? '';
				$voucher_brand_logo_url = game_bsc_resolve_partner_logo_url($partner_logo);
				if ($voucher_brand_logo_url === '') {
					$voucher_brand_logo_url = $voucher_brand_logo_url_field;
				}
			}

			$voucher_brand_name = $partner_data['name'] ?: $voucher_brand_name;
			$voucher_brand_url = $partner_data['url'] ?: $voucher_brand_url;
		}

		if ($voucher_image_url === '') {
			$voucher_image_url = esc_url_raw((string) ($transaction['gotit_voucher_image'] ?? ''));
		}

		// expiry_date - lấy từ DB làm fallback, sẽ bị override bởi Got It API bên dưới
		$expiry_date = sanitize_text_field((string) ($transaction['gotit_expiry_date'] ?? ''));

		$is_used = ((int) ($transaction['gotit_status'] ?? 0) === 4);
		$state_code = (int) ($transaction['gotit_status'] ?? 0);

		// Gọi Got It API với cache 5 phút
		$client = game_bsc_gotit_client();
		if ($client && $client->is_configured()) {
			$gotit_cache_key = 'gotit_ref_' . md5($transaction_ref_id);
			$cached_gotit = wp_cache_get($gotit_cache_key, 'game_bsc_gotit');

			if ($cached_gotit !== false) {
				$ref_result = $cached_gotit;
			} else {
				$ref_result = $client->get_vouchers_by_ref_id($transaction_ref_id, 1, 100, ['productInfo', 'usedInfo', 'groupInfo', 'stateInfo']);
				if (is_array($ref_result)) {
					wp_cache_set($gotit_cache_key, $ref_result, 'game_bsc_gotit', 5 * MINUTE_IN_SECONDS);
				}
			}

			if (!empty($ref_result['success']) && is_array($ref_result['data'] ?? null)) {
				$ref_payload = $ref_result['data'];

				if (function_exists('game_bsc_gotit_extract_vouchers_from_ref_payload')) {
					$ref_vouchers = game_bsc_gotit_extract_vouchers_from_ref_payload($ref_payload);

					if (function_exists('game_bsc_gotit_build_ref_voucher_summary')) {
						$ref_summary = game_bsc_gotit_build_ref_voucher_summary($ref_vouchers);
						if ((int) ($ref_summary['used'] ?? 0) > 0) {
							$is_used = true;
						}
					}

					if (!empty($ref_vouchers) && is_array($ref_vouchers[0])) {
						$first_ref_voucher = $ref_vouchers[0];
						// Luôn lấy expiry_date từ Got It API — đây là nguồn chính xác nhất
						$api_expiry = sanitize_text_field((string) ($first_ref_voucher['expiryDate'] ?? $first_ref_voucher['expiry_date'] ?? ''));
						if ($api_expiry !== '') {
							$expiry_date = $api_expiry;
						}

						if ($state_code === 0) {
							if (isset($first_ref_voucher['stateInfo']) && is_array($first_ref_voucher['stateInfo']) && is_numeric($first_ref_voucher['stateInfo']['code'] ?? null)) {
								$state_code = (int) $first_ref_voucher['stateInfo']['code'];
							} elseif (is_numeric($first_ref_voucher['stateCode'] ?? null)) {
								$state_code = (int) $first_ref_voucher['stateCode'];
							}
						}
					}
				} elseif (game_bsc_gotit_payload_has_used_state($ref_payload)) {
					$is_used = true;
				}
			}
		}

		if ($state_code === 4) {
			$is_used = true;
		}

		// Lấy title từ transaction nếu không có post
		$voucher_title = '';
		if ($voucher_post && isset($voucher_post->post_title)) {
			$voucher_title = sanitize_text_field($voucher_post->post_title);
		} else {
			$voucher_title = sanitize_text_field((string) ($transaction['gotit_order_name'] ?? ''));
		}

		$response = [
			'transaction_ref_id' => $transaction_ref_id,
			'voucher_info' => [
				'voucher_id' => $voucher_post_id,
				'title' => $voucher_title,
				'voucher_code' => sanitize_text_field((string) ($transaction['gotit_voucher_code'] ?? '')),
				'voucher_link' => esc_url((string) ($transaction['gotit_voucher_link'] ?? '')),
				'voucher_image' => esc_url($voucher_image_url),
				'serial' => sanitize_text_field((string) ($transaction['gotit_serial'] ?? '')),
				'barcode' => game_bsc_generate_code128_barcode_data_uri((string) ($transaction['gotit_voucher_code'] ?? '')),
				'brand_info' => [
					'name' => sanitize_text_field($voucher_brand_name),
					'url' => esc_url($voucher_brand_url),
					'logo_url' => esc_url($voucher_brand_logo_url),
				],
			],
			'redeemed_at' => sanitize_text_field((string) substr((string) ($transaction['created_at'] ?? ''), 0, 10)),
			'expiry_date' => $expiry_date,
			'terms_and_conditions' => [
				'terms' => wp_kses_post($voucher_terms),
				'service_guide' => wp_kses_post($voucher_service_guide),
			],
			'applicable_stores' => $applicable_stores,
			'is_used' => (bool) $is_used,
		];

		return wg_json_response(200, $response, __('Lấy thông tin voucher Got It thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
	} catch (Throwable $e) {
		error_log('game_bsc_get_gotit_voucher_by_transaction_ref error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * REST API: Phát hành voucher (luồng chuyên biệt cho voucher).
 * Endpoint: POST /wp-json/game-bsc/v1/vouchers/issue
 * Body params:
 * - voucher_id: ID voucher cần phát hành
 */
function game_bsc_issue_voucher(WP_REST_Request $request) {
	global $wpdb;

	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	try {
		$current_user = game_sso_require_session();
		if (is_wp_error($current_user) || empty($current_user['id'])) {
			return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		$user_id = absint($current_user['id']);
		$prefix = $wpdb->prefix . 'game_';

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

		if ((int) ($user['status'] ?? 0) === 0) {
			return wg_json_response(404, [], __('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		$voucher_id = absint($request->get_param('voucher_id'));
		if ($voucher_id < 1) {
			return wg_json_response(400, [], __('voucher_id không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN), 400);
		}

		return game_bsc_redeem_voucher_internal($user_id, $voucher_id);
	} catch (Throwable $e) {
		error_log('game_bsc_issue_voucher error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

function game_bsc_gotit_issue_public_message_from_result($issue_result) {
	$status_code = (int) ($issue_result['status_code'] ?? 0);
	$http_code = (int) ($issue_result['http_code'] ?? 0);
	$error_text = trim((string) ($issue_result['error'] ?? ''));

	$messages = [
		4001 => __('Hệ thống phát hành voucher chưa được cấu hình đúng. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4002 => __('Hệ thống phát hành voucher chưa được xác thực đúng. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4022 => __('Ngày hết hạn voucher không hợp lệ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4023 => __('Thiếu thông tin phát hành voucher. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4024 => __('Mệnh giá voucher không khớp với sản phẩm đã chọn. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4026 => __('Thiếu tên chương trình. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4028 => __('Đơn hàng không hợp lệ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4029 => __('Sản phẩm không hợp lệ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4030 => __('Danh mục không hợp lệ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4033 => __('Giao dịch này không hợp lệ hoặc đã hết hiệu lực. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4034 => __('Giao dịch này đã được xử lý trước đó. Vui lòng tải lại trang và kiểm tra lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4035 => __('Voucher link không hợp lệ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4039 => __('Sản phẩm này hiện không được phép phát hành. Vui lòng chọn voucher khác.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4041 => __('Tài khoản Got It đã hết hạn. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4042 => __('Tài khoản Got It đang bị giới hạn phát hành voucher. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4043 => __('Hệ thống xác thực phát hành voucher chưa hợp lệ. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4044 => __('Giới hạn đơn hàng đã bị vượt quá. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4057 => __('Số lượng voucher không hợp lệ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4058 => __('Mệnh giá voucher không hợp lệ. Vui lòng chọn lại voucher.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4060 => __('Thiếu mã giao dịch. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4062 => __('Mã giao dịch quá dài. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4063 => __('Định dạng mã giao dịch không hợp lệ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN),
		4064 => __('Chữ ký phát hành voucher không hợp lệ. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN),
		5000 => __('Got It đang gặp lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN),
	];

	if (isset($messages[$status_code])) {
		return $messages[$status_code];
	}

	if ($status_code >= 4000 && $status_code < 5000) {
		return __('Dữ liệu phát hành voucher chưa hợp lệ. Vui lòng kiểm tra lại và thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN);
	}

	if ($status_code >= 5000 || $http_code >= 500) {
		return __('Got It đang bận hoặc gặp lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN);
	}

	if ($http_code === 403 || stripos($error_text, 'forbidden') !== false) {
		return __('Không thể phát hành voucher ở thời điểm này. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN);
	}

	return __('Không thể phát hành voucher lúc này. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN);
}




/**
 * REST API: Đổi voucher HOẶC hiện vật
 * Endpoint: POST /wp-json/game-bsc/v1/gifts/redeem
 *
 * Body params:
 * - type: 'voucher' hoặc 'artifact'
 * - id: voucher_id hoặc artifact_id
 */

/**
 * Callback: Xử lý đổi voucher HOẶC hiện vật
 */
function game_bsc_redeem_item(WP_REST_Request $request) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
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
		$type = sanitize_text_field($request->get_param('type'));
		$id = (int)$request->get_param('id');
		
		// ===== 2. PHÂN LUỒNG THEO TYPE =====
		if ($type === 'voucher') {
			return game_bsc_redeem_voucher_internal($user_id, $id);
		} elseif ($type === 'artifact') {
			return game_bsc_redeem_artifact_internal($user_id, $id);
		}
		
		return wg_json_response(400, [], __('Loại quà không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Throwable $e) {
		error_log('game_bsc_redeem_item error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * HÀM NỘI BỘ: Đổi voucher (GIỮ NGUYÊN LOGIC CŨ)
 */
function game_bsc_redeem_voucher_internal($user_id, $voucher_post_id) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		// ===== 1. KIỂM TRA VOUCHER TỒN TẠI =====
		$voucher_post = get_post($voucher_post_id);
		if (!$voucher_post || $voucher_post->post_type !== 'game_vouchers' || $voucher_post->post_status !== 'publish') {
			return wg_json_response(404, [], __('Không tìm thấy voucher.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// ===== 2. LẤY DỮ LIỆU VOUCHER =====
		$voucher_code = get_field('voucher_code', $voucher_post_id);
		$voucher_type = get_field('voucher_type', $voucher_post_id);
		$is_active = get_field('is_active', $voucher_post_id);
		$points_cost = (int)get_field('points_cost', $voucher_post_id);
		$quantity = (int)get_field('quantity', $voucher_post_id);
		$redemption_count = (int)get_field('redemption_count', $voucher_post_id);
		$validity = get_field('validity', $voucher_post_id);
		$redeemed_banner_id = (get_field('redeemed_banner_image', $voucher_post_id) ?? '');
		$redeemed_banner_image_url = '';
		if ($redeemed_banner_id){
			$redeemed_banner_image_url = wp_get_attachment_image_url($redeemed_banner_id, 'full') ?: '';
		}
		if (!$redeemed_banner_image_url) {
			$default_banner_id = get_option('game_bsc_default_redeemed_banner');
			if ($default_banner_id) {
				$redeemed_banner_image_url = wp_get_attachment_image_url($default_banner_id, 'full') ?: '';
			}
		}

		$voucher_type_raw = strtoupper(trim((string) $voucher_type));
		$is_third_party_voucher = in_array($voucher_type_raw, ['THIRD_PARTY', 'THIRD-PARTY'], true);
		
		$partner_data = get_field('partner', $voucher_post_id) ?: [];
		if (!is_array($partner_data)) {
			$partner_data = [];
		}
		
		$partner_name = sanitize_text_field($partner_data['name'] ?? '');
		$partner_url = esc_url($partner_data['url'] ?? '');
		$partner_logo_url = game_bsc_resolve_partner_logo_url($partner_data['logo'] ?? '');
		if ($partner_logo_url === '') {
			$partner_logo_url = esc_url_raw((string) get_field('voucher_brand_logo_url', $voucher_post_id));
		}

		$gotit_client = null;
		$gotit_product_id = 0;
		$gotit_product_price_id = 0;
		$gotit_order_name = '';
		$gotit_expiry_date = '';
		$gotit_transaction_ref_id = '';
		$issued_voucher_link = '';
		$issued_voucher_serial = '';
		$issued_voucher_expiry = '';
		$issued_vendor_name = '';
		$issued_is_partner_code = 0;

		if ($is_third_party_voucher) {
			// ===== KIỂM TRA ĐỒNG Ý ĐIỀU KHOẢN =====
			$table_terms = $wpdb->prefix . 'game_user_terms_acceptance';
			$terms_accepted = $wpdb->get_var($wpdb->prepare(
				"SELECT user_id FROM {$table_terms} WHERE user_id = %d",
				$user_id
			));
			
			if (!$terms_accepted) {
				return wg_json_response(403, [
					'code' => 'terms_not_accepted'
				], __('Bạn cần đồng ý điều khoản đổi voucher trước khi tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
			}

			$gotit_client = game_bsc_gotit_client();
			if (!$gotit_client || !$gotit_client->is_configured()) {
				return wg_json_response(422, [], __('Hệ thống voucher Got It chưa được cấu hình.', WG_GAME_PLUGIN_TEXTDOMAIN));
			}

			$gotit_product_id = (int) get_field('gotit_product_id', $voucher_post_id);
			$gotit_product_price_id = (int) get_field('gotit_product_price_id', $voucher_post_id);
			if ($gotit_product_id < 1 || $gotit_product_price_id < 1) {
				return wg_json_response(422, [], __('Voucher Got It chưa đủ cấu hình productId hoặc productPriceId.', WG_GAME_PLUGIN_TEXTDOMAIN));
			}

			$gotit_expiry_days = absint(game_bsc_gotit_source_value('default_expiry_days', 30));
			if ($gotit_expiry_days < 1) {
				$gotit_expiry_days = 30;
			}

			$gotit_order_name = sanitize_text_field((string) game_bsc_gotit_source_value('default_order_name', 'BSC Game Voucher'));
			if ($gotit_order_name === '') {
				$gotit_order_name = sanitize_text_field((string) $voucher_post->post_title);
			}

			$gotit_expiry_date = gmdate('Y-m-d', strtotime('+' . $gotit_expiry_days . ' days'));
			$gotit_transaction_ref_id = $gotit_client->generate_transaction_ref_id($user_id, $voucher_post_id);
		}
		// ===== 3. VALIDATE VOUCHER =====
		if (!$is_active) {
			return wg_json_response(422, [], __('Voucher này hiện không khả dụng.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// Kiểm tra thời gian
		$now = game_now();
		$valid_from = $validity['valid_from'] ?? '';
		$valid_to = $validity['valid_to'] ?? '';

		if ($is_third_party_voucher) {
			$valid_from = '';
			$valid_to = '';
		}
		
		if (!empty($valid_to) && $now > $valid_to) {
			return wg_json_response(422, [], __('Rất tiếc, quà tặng hiện đã hết hạn sử dụng. Vui lòng chọn phần quà khác.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// Kiểm tra số lượng
		if ($redemption_count >= $quantity) {
			return wg_json_response(422, [], __('Rất tiếc, quà tặng hiện đã hết. Vui lòng chọn phần quà khác.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// ===== 4. KIỂM TRA ĐIỂM CỦA USER =====
		$user_points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance FROM {$prefix}user_points_balances WHERE user_id = %d",
				$user_id
			)
		);
		$user_points = $user_points !== null ? (int)$user_points : 0;
		
		if ($user_points < $points_cost) {
			return wg_json_response(403, [], sprintf(
				__('Cần %s điểm để đổi quà nhưng bạn đang không đủ điểm. Hãy tiếp tục chơi để có thêm điểm thưởng.', WG_GAME_PLUGIN_TEXTDOMAIN),
				number_format($points_cost, 0, ',', '.')
			));
		}
		
		// ===== 5. TRANSACTION =====
		$wpdb->query('START TRANSACTION');
		
		try {
			$redemptions_table = $prefix . 'user_voucher_redemptions';
			$redemption_table_columns = $wpdb->get_col("SHOW COLUMNS FROM {$redemptions_table}", 0);
			if (!is_array($redemption_table_columns)) {
				$redemption_table_columns = [];
			}

			// Trừ điểm
			$points_update = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$prefix}user_points_balances
					 SET balance = balance - %d
					 WHERE user_id = %d",
					$points_cost,
					$user_id
				)
			);
			
			if (!$points_update) {
				throw new Exception('Failed to update user points balance: ' . $wpdb->last_error);
			}
			
			// Lưu log redemption
			$redemption_insert_data = [
				'user_id' => $user_id,
				'voucher_post_id' => $voucher_post_id,
				'redeemed_at' => $now,
			];
			$redemption_insert_format = ['%d', '%d', '%s'];

			if (
				$is_third_party_voucher
				&& in_array('transaction_ref_id', $redemption_table_columns, true)
				&& $gotit_transaction_ref_id !== ''
			) {
				$redemption_insert_data['transaction_ref_id'] = sanitize_text_field($gotit_transaction_ref_id);
				$redemption_insert_format[] = '%s';
			}

			if ($is_third_party_voucher && in_array('gotit_expiry_date', $redemption_table_columns, true)) {
				$default_gotit_expiry_date = '';
				if ($gotit_expiry_date !== '') {
					if (function_exists('game_bsc_gotit_to_mysql_datetime')) {
						$default_gotit_expiry_date = game_bsc_gotit_to_mysql_datetime($gotit_expiry_date);
					}

					if ($default_gotit_expiry_date === '') {
						$default_gotit_expiry_date = sanitize_text_field($gotit_expiry_date);
					}
				}

				if ($default_gotit_expiry_date !== '') {
					$redemption_insert_data['gotit_expiry_date'] = $default_gotit_expiry_date;
					$redemption_insert_format[] = '%s';
				}
			}

			$redemption_result = $wpdb->insert(
				$redemptions_table,
				$redemption_insert_data,
				$redemption_insert_format
			);
			
			if (!$redemption_result) {
				throw new Exception('Failed to save redemption log: ' . $wpdb->last_error);
			}
			
			$redemption_id = (int)$wpdb->insert_id;

			if ($is_third_party_voucher) {
				$issue_result = $gotit_client->issue_voucher([
					'productId' => $gotit_product_id,
					'productPriceId' => $gotit_product_price_id,
					'quantity' => 1,
					'orderName' => $gotit_order_name,
					'expiryDate' => $gotit_expiry_date,
					'transactionRefId' => $gotit_transaction_ref_id,
				]);

				if (empty($issue_result['success'])) {
					$gotit_error = trim((string) ($issue_result['error'] ?? ''));
					$gotit_status_code = (int) ($issue_result['status_code'] ?? 0);
					$gotit_http_code = (int) ($issue_result['http_code'] ?? 0);
					$public_message = function_exists('game_bsc_gotit_issue_public_message_from_result')
						? game_bsc_gotit_issue_public_message_from_result($issue_result)
						: __('Không thể phát hành voucher lúc này. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN);

					error_log(sprintf(
						'Redeem voucher Got It issue failed: status_code=%d http_code=%d error=%s raw=%s',
						$gotit_status_code,
						$gotit_http_code,
						$gotit_error,
						isset($issue_result['raw']) ? (string) $issue_result['raw'] : ''
					));

					throw new Exception('USER_SAFE:' . $public_message);
				}

				$issue_payload = is_array($issue_result['data'] ?? null) ? $issue_result['data'] : [];
				$issue_data = function_exists('game_bsc_gotit_extract_issue_data')
					? game_bsc_gotit_extract_issue_data($issue_payload)
					: [];

				$issued_code = sanitize_text_field((string) ($issue_data['voucher_code'] ?? ''));
				$issued_voucher_link = esc_url((string) ($issue_data['voucher_link'] ?? ''));
				$issued_voucher_image = esc_url_raw((string) ($issue_data['voucher_image'] ?? ''));
				$issued_voucher_serial = sanitize_text_field((string) ($issue_data['voucher_serial'] ?? ''));
				$issued_voucher_expiry = sanitize_text_field((string) ($issue_data['expiry_date'] ?? ''));
				$issued_vendor_name = sanitize_text_field((string) ($issue_data['vendor_name'] ?? ''));
				$issued_status = (int) ($issue_data['status'] ?? 0);
				$issued_is_partner_code = (int) ($issue_data['is_partner_code'] ?? 0);

				$redemption_update_data = [];
				$redemption_update_format = [];

				if (in_array('transaction_ref_id', $redemption_table_columns, true) && $gotit_transaction_ref_id !== '') {
					$redemption_update_data['transaction_ref_id'] = sanitize_text_field($gotit_transaction_ref_id);
					$redemption_update_format[] = '%s';
				}

				if (in_array('gotit_expiry_date', $redemption_table_columns, true)) {
					$gotit_expiry_to_save = '';

					if ($issued_voucher_expiry !== '') {
						if (function_exists('game_bsc_gotit_to_mysql_datetime')) {
							$gotit_expiry_to_save = game_bsc_gotit_to_mysql_datetime($issued_voucher_expiry);
						}

						if ($gotit_expiry_to_save === '') {
							$gotit_expiry_to_save = sanitize_text_field($issued_voucher_expiry);
						}
					} elseif ($gotit_expiry_date !== '') {
						if (function_exists('game_bsc_gotit_to_mysql_datetime')) {
							$gotit_expiry_to_save = game_bsc_gotit_to_mysql_datetime($gotit_expiry_date);
						}

						if ($gotit_expiry_to_save === '') {
							$gotit_expiry_to_save = sanitize_text_field($gotit_expiry_date);
						}
					}

					if ($gotit_expiry_to_save !== '') {
						$redemption_update_data['gotit_expiry_date'] = $gotit_expiry_to_save;
						$redemption_update_format[] = '%s';
					}
				}

				if (!empty($redemption_update_data)) {
					$redemption_update_result = $wpdb->update(
						$redemptions_table,
						$redemption_update_data,
						['id' => $redemption_id],
						$redemption_update_format,
						['%d']
					);

					if ($redemption_update_result === false) {
						throw new Exception('Failed to update redemption metadata: ' . $wpdb->last_error);
					}
				}

				if ($issued_code === '' && $issued_voucher_link === '') {
					throw new Exception('Got It issue succeeded but voucher payload is empty.');
				}

				$partner_expiry_date = '';
				if ($issued_is_partner_code === 1 && $issued_voucher_expiry !== '') {
					$partner_expiry_date = $issued_voucher_expiry;
				}

				if (!function_exists('game_bsc_insert_gotit_test_transaction')) {
					throw new Exception('Got It transaction helper is not available.');
				}

				$txn_saved = game_bsc_insert_gotit_test_transaction([
					'redemption_id' => $redemption_id,
					'user_id' => $user_id,
					'voucher_post_id' => $voucher_post_id,
					'transaction_ref_id' => $gotit_transaction_ref_id,
					'gotit_order_name' => $gotit_order_name,
					'gotit_product_id' => $gotit_product_id,
					'gotit_product_price_id' => $gotit_product_price_id,
					'gotit_voucher_link' => $issued_voucher_link,
					'gotit_voucher_code' => $issued_code,
					'gotit_voucher_image' => $issued_voucher_image,
					'gotit_serial' => $issued_voucher_serial,
					'gotit_expiry_date' => $issued_voucher_expiry,
                    
					'gotit_status' => $issued_status,
                    
				]);

				if (empty($txn_saved['ok'])) {
					throw new Exception('Cannot save Got It transaction: ' . (string) ($txn_saved['error'] ?? 'unknown error'));
				}

				if ($issued_code !== '') {
					$voucher_code = $issued_code;
				}
				if ($issued_voucher_image !== '') {
					$redeemed_banner_image_url = $issued_voucher_image;
				}
				if ($issued_vendor_name !== '') {
					$partner_name = $issued_vendor_name;
				}
			}
			
			// Cập nhật redemption_count
			$new_redemption_count = $redemption_count + 1;
			$redemption_update = update_field('redemption_count', $new_redemption_count, $voucher_post_id);
			
			if ($redemption_update === false) {
				throw new Exception('Failed to update redemption count');
			}
			
			// Lưu log ledger
			$ledger_result = $wpdb->insert(
				$prefix . 'user_points_ledger',
				[
					'user_id' => $user_id,
					'delta' => -$points_cost,
					'ref_type' => 'VOUCHER',
					'ref_id' => $redemption_id,
					'created_at' => $now,
				],
				['%d', '%d', '%s', '%d', '%s']
			);
			
			if (!$ledger_result) {
				throw new Exception('Failed to save points ledger: ' . $wpdb->last_error);
			}

			$wpdb->query('COMMIT');
			
			// Response
			$remaining_points = $user_points - $points_cost;
			
			$response_data = [
				'type' => 'voucher',
				'user_id' => $user_id,
				'item' => [
					'id' => $voucher_post_id,
					'title' => sanitize_text_field($voucher_post->post_title),
					'code' => sanitize_text_field($voucher_code ?? ''),
					'type' => sanitize_text_field($voucher_type ?? ''),
					'thumbnail_url' => $redeemed_banner_image_url,
					'link' => esc_url($issued_voucher_link),
					'serial' => sanitize_text_field($issued_voucher_serial),
					'expiry_date' => $issued_voucher_expiry !== '' ? sanitize_text_field($issued_voucher_expiry) : null,
					'vendor_name' => sanitize_text_field($issued_vendor_name),
					'transaction_ref_id' => sanitize_text_field($gotit_transaction_ref_id),
					'is_partner_code' => (bool) $issued_is_partner_code,
					'partner' => [
						'name' => sanitize_text_field($partner_name),
						'url' => esc_url($partner_url),
						'logo_url' => esc_url($partner_logo_url),
					],
					'points_cost' => $points_cost,
				],
				'transaction' => [
					'points_deducted' => $points_cost,
					'points_remaining' => $remaining_points,
					'redeemed_at' => $now,
					'redemption_id' => $redemption_id,
				],
			];

			return wg_json_response(200, $response_data, __('Đổi voucher thành công!', WG_GAME_PLUGIN_TEXTDOMAIN));
			
		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			$error_message = (string) $e->getMessage();
			$public_message = __('Không thể phát hành voucher lúc này. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN);
			if (strpos($error_message, 'USER_SAFE:') === 0) {
				$public_message = substr($error_message, strlen('USER_SAFE:'));
			}
			error_log('Redeem voucher error: ' . $error_message);
			return wg_json_response(500, [], $public_message, 500);
		}
		
	} catch (Throwable $e) {
		error_log('game_bsc_redeem_voucher_internal error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * HÀM NỘI BỘ MỚI: Đổi hiện vật
 * ✅ SỬ DỤNG BẢNG user_pieces_ledger ĐỂ TRACKING BIẾN ĐỘNG MẢNH
 */
function game_bsc_redeem_artifact_internal($user_id, $artifact_id) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	try {
		// ===== 1. KIỂM TRA ARTIFACT TỒN TẠI =====
		$artifact = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, max_redemptions, status, artifacts_url, closed, period_start, period_end, total_periods, max_redemptions_per_period
				 FROM {$prefix}artifacts
				 WHERE id = %d",
				$artifact_id
			),
			ARRAY_A
		);
		
		if (!$artifact) {
			return wg_json_response(404, [], __('Không tìm thấy hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// Kiểm tra artifact có đang mở không
		if ((int)$artifact['status'] !== 1) {
			return wg_json_response(422, [], __('Hiện vật này hiện không khả dụng để đổi.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		if ((int)$artifact['closed'] === 1) {
			return wg_json_response(422, [], __('Hiện vật này đã hết suất đổi quà.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		$artifact_obj = (object) $artifact;
		if (!game_artifact_is_within_period($artifact_obj)) {
			return wg_json_response(422, [], __('Hiện vật này đang ngoài thời gian diễn ra.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		// $current_period = game_artifact_current_period($artifact_obj);
		// if ($current_period !== false && !game_artifact_period_has_quota($artifact_obj, $current_period)) {
		// 	return wg_json_response(422, [], __('Hiện vật này hiện không khả dụng để đổi.', WG_GAME_PLUGIN_TEXTDOMAIN));
		// }
		
		// ===== 2. LẤY DANH SÁCH MẢNH CỦA ARTIFACT =====
		$pieces = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, piece_code FROM {$prefix}pieces WHERE artifact_id = %d ORDER BY piece_code ASC",
				$artifact_id
			),
			ARRAY_A
		);
		
		if (count($pieces) < 4) {
			return wg_json_response(422, [], __('Hiện vật này chưa được cấu hình đủ 4 mảnh.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// ===== 3. KIỂM TRA USER CÓ ĐỦ 4 MẢNH KHÔNG =====
		$user_pieces = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT up.id as user_piece_id, up.piece_id, up.qty, p.piece_code
				 FROM {$prefix}user_pieces up
				 INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
				 WHERE up.user_id = %d AND up.artifact_id = %d AND up.qty >= 1
				 ORDER BY p.piece_code ASC",
				$user_id,
				$artifact_id
			),
			ARRAY_A
		);
		
		if (count($user_pieces) < 4) {
			return wg_json_response(422, [], __('Hiện tại bạn chưa đủ 4 mảnh ghép để đổi quà hiện vật này.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// ===== 4. KIỂM TRA SỐ LẦN ĐÃ ĐỔI =====
		$times_redeemed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$prefix}user_artifact_redemptions
				 WHERE artifact_id = %d",
				$artifact_id
			)
		);
		$times_redeemed = (int)$times_redeemed;
		$max_redemptions = (int)$artifact['max_redemptions'];
		
		if ($times_redeemed >= $max_redemptions) {
			return wg_json_response(422, [], sprintf(
				__('Bạn đã đổi hiện vật này %d lần (tối đa: %d lần).', WG_GAME_PLUGIN_TEXTDOMAIN),
				$times_redeemed,
				$max_redemptions
			));
		}
		
		// ===== 5. TRANSACTION =====
		$wpdb->query('START TRANSACTION');
		
		try {
			// ✅ Trừ 1 qty cho mỗi mảnh và log vào user_pieces_ledger
			foreach ($user_pieces as $piece) {
				$user_piece_id = (int)$piece['user_piece_id'];
				$piece_id = (int)$piece['piece_id'];
				
				// Trừ qty
				$piece_update = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$prefix}user_pieces
						 SET qty = qty - 1
						 WHERE id = %d AND qty >= 1",
						$user_piece_id
					)
				);
				
				if ($piece_update === false || $piece_update === 0) {
					throw new Exception("Failed to deduct piece {$piece['piece_code']}");
				}
				
				// ✅ LƯU LOG VÀO user_pieces_ledger
				$ledger_insert = $wpdb->insert(
					$prefix . 'user_pieces_ledger',
					[
						'user_piece_id' => $user_piece_id,
						'ref_type' => 'CHANGE',
						'delta' => -1,
						'created_at' => game_now()
					],
					['%d', '%s', '%d', '%s']
				);
				
				if ($ledger_insert === false) {
					throw new Exception("Failed to log piece deduction for piece {$piece['piece_code']}: " . $wpdb->last_error);
				}
			}
			
			// Lưu log redemption
			$redemption_result = $wpdb->insert(
				$prefix . 'user_artifact_redemptions',
				[
					'user_id' => $user_id,
					'artifact_id' => $artifact_id,
					'redeemed_at' => game_now()
				],
				['%d', '%d', '%s']
			);
			
			if ($redemption_result === false) {
				throw new Exception('Failed to save artifact redemption log: ' . $wpdb->last_error);
			}
			
			
			if (($times_redeemed + 1) >= $max_redemptions) {
				$artifact_close = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$prefix}artifacts
						 SET closed = 1
						 WHERE id = %d",
						$artifact_id
					)
				);

				if ($artifact_close === false) {
					throw new Exception('Failed to close artifact: ' . $wpdb->last_error);
				}
			}

			
			$redemption_id = (int)$wpdb->insert_id;
			
			$wpdb->query('COMMIT');
			
			// ===== 6. LẤY MẢNH CÒN LẠI =====
			$remaining_pieces = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.piece_code, up.qty
					 FROM {$prefix}user_pieces up
					 INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
					 WHERE up.user_id = %d AND up.artifact_id = %d
					 ORDER BY p.piece_code ASC",
					$user_id,
					$artifact_id
				),
				ARRAY_A
			);
			
			$pieces_formatted = [];
			foreach ($remaining_pieces as $piece) {
				$pieces_formatted[] = [
					'piece_code' => sanitize_text_field($piece['piece_code']),
					'qty' => (int)$piece['qty']
				];
			}
			
			// Response
			$response_data = [
				'type' => 'artifact',
				'user_id' => $user_id,
				'item' => [
					'id' => (int)$artifact['id'],
					'name' => sanitize_text_field($artifact['name']),
					'artifacts_url' => esc_url($artifact['artifacts_url']),
				],
				'transaction' => [
					'pieces_remaining' => $pieces_formatted,
					'times_redeemed' => $times_redeemed + 1,
					'max_redemptions' => $max_redemptions,
					'redeemed_at' => game_now(),
					'redemption_id' => $redemption_id,
				],
			];
			
			return wg_json_response(200, $response_data, __('Đổi hiện vật thành công!', WG_GAME_PLUGIN_TEXTDOMAIN));
			
		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			error_log('Redeem artifact error: ' . $e->getMessage());
			return wg_json_response(500, [], __('Giao dịch thất bại. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
		}
		
	} catch (Throwable $e) {
		error_log('game_bsc_redeem_artifact_internal error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}


/**
 * REST API để lấy danh sách vouchers mà user đã đổi
 * Endpoint: /wp-json/game-bsc/v1/my-redemptions
 * User được xác thực qua SSO session
 */

add_action('rest_api_init', function () {
	register_rest_route(NS, '/my-redemptions', array(
		'methods' => 'GET',
		'callback' => 'game_get_my_redemptions',
		'permission_callback' => 'game_rest_perm_cb',
		'args' => array(
			'page' => array(
				'validate_callback' => function ($param) {
					return is_numeric($param) && (int)$param > 0;
				},
				'default' => 1
			),
			'per_page' => array(
				'validate_callback' => function ($param) {
					$per_page = (int)$param;
					return $per_page > 0 && $per_page <= 100;
				},
				'default' => 20
			),
		),
	));
});


/**
 * Callback function: Lấy danh sách vouchers và artifacts đã đổi của user hiện tại
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_get_my_redemptions(WP_REST_Request $request)
{
	global $wpdb;
	
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	$page = max(1, absint($request->get_param('page') ?? 1));
	$per_page = min(100, absint($request->get_param('per_page') ?? 20));
	
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
			"SELECT id, name, avatar_url FROM {$prefix}users WHERE id = %d AND status = 1",
			$user_id
		)
	);
	
	if (!$user) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	try {
		// ===== LẤY DANH SÁCH VOUCHERS ĐÃ ĐỔI (không bao gồm hiện vật) =====
		$vouchers = game_get_user_voucher_redemptions($user_id);

		$all_items = [];
		foreach ($vouchers['items'] as $voucher) {
			$voucher['type'] = 'voucher';
			$all_items[] = $voucher;
		}

		// Sort theo redeemed_at giảm dần (mới nhất trước)
		usort($all_items, function ($a, $b) {
			return strtotime($b['redeemed_at']) <=> strtotime($a['redeemed_at']);
		});

		$total_items = count($all_items);
		$total_pages = ceil($total_items / $per_page);

		if ($page > $total_pages && $total_pages > 0) {
			return wg_json_response(400, [], __('Số trang vượt quá tổng số trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		$offset = ($page - 1) * $per_page;
		$paginated_items = array_slice($all_items, $offset, $per_page);

		$response = array(
			'user' => array(
				'id' => (int)$user->id,
				'name' => sanitize_text_field($user->name),
				'avatar_url' => esc_url($user->avatar_url ?? '')
			),
			'redemptions' => $paginated_items,
			'pagination' => array(
				'current_page' => $page,
				'per_page' => $per_page,
				'total_items' => $total_items,
				'total_pages' => $total_pages,
				'has_next' => $page < $total_pages,
				'has_prev' => $page > 1
			)
		);

		return wg_json_response(200, $response, __('Lấy danh sách voucher đã đổi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));

	} catch (Throwable $e) {
		error_log('game rest error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * Hàm lấy danh sách vouchers đã đổi của user
 * ✅ THÊM SỐ LƯỢNG VOUCHER CÙNG LOẠI
 * ✅ SỬ DỤNG get_field() ĐỂ LẤY ACF FIELDS
 *
 * @param int  $user_id
 * @return array
 */
function game_get_user_voucher_redemptions($user_id)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy danh sách redemptions riêng biệt từng dòng để có prinpaid và dates độc lập cho từng user
	$redemptions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				uvr.id as redemption_id,
				uvr.voucher_post_id as voucher_id,
				uvr.redeemed_at as redeemed_at,
				1 as quantity,
				p.post_title as voucher_name,
				uvr.prinpaid,
				uvr.start_date,
				uvr.gotit_expiry_date
			FROM {$prefix}user_voucher_redemptions uvr
			INNER JOIN {$wpdb->posts} p ON uvr.voucher_post_id = p.ID
			WHERE uvr.user_id = %d 
			  AND p.post_status = 'publish'
			  AND EXISTS (
				  SELECT 1
				  FROM {$wpdb->postmeta} pm
				  WHERE pm.post_id = uvr.voucher_post_id
				    AND pm.meta_key = 'voucher_type'
				    AND UPPER(TRIM(pm.meta_value)) NOT IN ('THIRD_PARTY', 'THIRD-PARTY')
			  )
			ORDER BY redeemed_at DESC",
			$user_id
		),
		ARRAY_A
	);
	
	if (empty($redemptions)) {
		return [
			'items' => [],
			'total' => 0
		];
	}
	
	// Format response
	$formatted = [];
	foreach ($redemptions as $redemption) {
		$voucher_id = (int)$redemption['voucher_id'];

		// Kiểm tra trạng thái hoạt động (active)
		$is_active = get_field('is_active', $voucher_id);
		if ($is_active !== null && !$is_active) {
			continue;
		}

		// SỬ DỤNG get_field() ĐỂ LẤY ACF FIELDS
		$voucher_code = sanitize_text_field(get_field('voucher_code', $voucher_id) ?? 'N/A');
		$voucher_type = sanitize_text_field(get_field('voucher_type', $voucher_id) ?? 'BSC');

		// Lấy thông tin hạn sử dụng
		$valid_from = '';
		$valid_to = '';
		$is_valid = true;

		if (strtoupper($voucher_type) === 'BSC') {
			// Đối với voucher BSC: sử dụng start_date và gotit_expiry_date đã đồng bộ từ DB
			$db_start = !empty($redemption['start_date']) ? $redemption['start_date'] : '';
			$db_expiry = !empty($redemption['gotit_expiry_date']) ? $redemption['gotit_expiry_date'] : '';

			if ($db_start !== '0000-00-00 00:00:00' && $db_start !== '') {
				$valid_from = date('Y-m-d H:i:s', strtotime($db_start));
			}
			if ($db_expiry !== '0000-00-00 00:00:00' && $db_expiry !== '') {
				$valid_to = date('Y-m-d H:i:s', strtotime($db_expiry));
				// Kiểm tra hạn sử dụng
				$today = game_now();
				$is_valid = strtotime($today) <= strtotime($valid_to);
			}
			// Nếu dates bị trống/NULL (do API delay chưa đồng bộ kịp), ta trả về chuỗi rỗng và vẫn hiển thị cho user (is_valid = true)
		} else {
			// Đối với các voucher khác (Got It): dùng ACF fields
			$validity_data = get_field('validity', $voucher_id) ?: [];
			if (is_array($validity_data)) {
				$valid_from = $validity_data['valid_from'] ?? '';
				$valid_to = $validity_data['valid_to'] ?? '';
			}
			if (!empty($valid_to)) {
				$today = game_now();
				$is_valid = strtotime($today) <= strtotime($valid_to);
			}
		}

		if (!$is_valid) {
			continue;
		}
		
		$points_cost = (int)(get_field('points_cost', $voucher_id) ?? 0);
		$voucheramt = (float) (get_post_meta($voucher_id, 'voucheramt', true) ?: 0);
		$prinpaid = isset($redemption['prinpaid']) ? (float)$redemption['prinpaid'] : 0.0;

		// Ẩn voucher BSC đã tiêu hết giá trị (prinpaid >= voucheramt)
		// VD: voucheramt=100,000 | prinpaid=100,000 | reamt=0 → không còn số dư → ẩn
		if ($voucheramt > 0 && $prinpaid >= $voucheramt) {
			continue;
		}

		// Lấy ảnh banner đổi quà (ưu tiên featured image/ảnh nổi bật trước)
		$redeemed_banner_image_url = '';
		$redeemed_banner_thumb_id = get_post_thumbnail_id($voucher_id);
		if ($redeemed_banner_thumb_id) {
			$redeemed_banner_image_url = wp_get_attachment_image_url($redeemed_banner_thumb_id, 'full') ?: '';
		}
		if (!$redeemed_banner_image_url) {
			$redeemed_banner_id = get_field('redeemed_banner_image', $voucher_id);
			if ($redeemed_banner_id) {
				$redeemed_banner_image_url = wp_get_attachment_image_url($redeemed_banner_id, 'full') ?: '';
			}
		}
		if (!$redeemed_banner_image_url) {
			$default_banner_id = get_option('game_bsc_default_redeemed_banner');
			if ($default_banner_id) {
				$redeemed_banner_image_url = wp_get_attachment_image_url($default_banner_id, 'full') ?: '';
			}
		}
		// Lấy partner (ACF group field)
		$partner_data = get_field('partner', $voucher_id) ?: [];
		if (!is_array($partner_data)) {
			$partner_data = [];
		}
		
		$partner_name = sanitize_text_field($partner_data['name'] ?? '');
		$partner_url = esc_url($partner_data['url'] ?? '');
		$partner_logo_url = game_bsc_resolve_partner_logo_url($partner_data['logo'] ?? '');
		if ($partner_logo_url === '') {
			$partner_logo_url = esc_url_raw((string) get_field('voucher_brand_logo_url', $voucher_id));
		}
		
		$formatted[] = array(
//			'redemption_id' => 'voucher_' . (int)$redemption['redemption_id'],
			'voucher_id' => $voucher_id,
			'voucher_code' => $voucher_code,
			'voucher_name' => sanitize_text_field($redemption['voucher_name']),
			'redeemed_banner_image_url' => esc_url($redeemed_banner_image_url),
			'voucher_type' => $voucher_type,
			'voucheramt' => $voucheramt,
			'prinpaid' => $prinpaid,
			'partner_name' => $partner_name,
			'partner_url' => $partner_url,
			'partner_logo_url' => esc_url($partner_logo_url),
			'valid_from' => $valid_from,
			'valid_to' => $valid_to,
			'is_valid' => $is_valid,
			'quantity' => (int)$redemption['quantity'],
			'redeemed_at' => $redemption['redeemed_at']
		);
	}
	
	return [
		'items' => $formatted,
		'total' => count($formatted)
	];
}

/**
 * Hàm lấy danh sách artifacts đã đổi của user
 * ✅ THÊM SỐ LƯỢNG ARTIFACT CÙNG LOẠI
 *
 * @param int $user_id
 * @return array
 */
function game_get_user_artifact_redemptions($user_id)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy danh sách redemptions - GROUP BY artifact_id để đếm số lượng
	$redemptions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				MIN(uar.id) as redemption_id,
				uar.artifact_id,
				MAX(uar.redeemed_at) as redeemed_at,
				COUNT(*) as quantity,
				a.name as artifact_name,
				a.artifacts_url
			FROM {$prefix}user_artifact_redemptions uar
			INNER JOIN {$prefix}artifacts a ON uar.artifact_id = a.id
			WHERE uar.user_id = %d
			GROUP BY uar.artifact_id
			ORDER BY redeemed_at DESC",
			$user_id
		),
		ARRAY_A
	);
	
	if (empty($redemptions)) {
		return [
			'items' => [],
			'total' => 0
		];
	}
	
	// Format response
	$formatted = [];
	foreach ($redemptions as $redemption) {
		$formatted[] = array(
//			'redemption_id' => 'artifact_' . (int)$redemption['redemption_id'],
			'artifact_id' => (int)$redemption['artifact_id'],
			'artifact_name' => sanitize_text_field($redemption['artifact_name']),
			'artifacts_url' => esc_url($redemption['artifacts_url'] ?? ''),
			'quantity' => (int)$redemption['quantity'],
			'redeemed_at' => $redemption['redeemed_at']
		);
	}
	
	return [
		'items' => $formatted,
		'total' => count($formatted)
	];
}

/**
 * REST API: Màn "Đổi quà" - Lấy danh sách tất cả artifacts với thông tin user
 * Endpoint: /wp-json/game-bsc/v1/artifacts-exchange
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/artifacts-exchange', array(
		'methods' => 'GET',
		'callback' => 'game_get_artifacts_exchange',
		'permission_callback' => 'game_rest_perm_cb',
	));
});

/**
 * Callback: Màn "Đổi quà"
 * Trả về:
 * - total_pieces: tổng mảnh ghép user đang có
 * - artifacts: hiện vật đang mở và còn trong thời hạn (period); hết đợt thì không trả về
 */
function game_get_artifacts_exchange(WP_REST_Request $request)
{
	global $wpdb;

	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$user_id = absint($current_user['id']);
	$prefix  = $wpdb->prefix . 'game_';

	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);

	if (!$user || (int)$user['status'] === 0) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng hoặc tài khoản bị khóa.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	try {
		// Tổng mảnh ghép user đang có
		$total_pieces = game_bsc_get_user_total_pieces($user_id);

		// Hiện vật đang mở; lọc thêm theo period (hết đợt → không trả về)
		$all_artifacts = $wpdb->get_results(
			"SELECT id, name, artifacts_url, max_redemptions, period_start, period_end
			 FROM {$prefix}artifacts
			 WHERE status = 1
			 ORDER BY id ASC",
			ARRAY_A
		);

		// Lấy tất cả mảnh ghép user đang sở hữu (qty > 0)
		$user_pieces_all = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					up.piece_id,
					up.artifact_id,
					up.qty,
					p.piece_code,
					p.piece_img
				FROM {$prefix}user_pieces up
				INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
				WHERE up.user_id = %d AND up.qty > 0
				ORDER BY up.artifact_id ASC, p.piece_code ASC",
				$user_id
			),
			ARRAY_A
		);

		// Map user pieces theo artifact_id
		$user_pieces_map = [];
		foreach ($user_pieces_all as $up) {
			$aid = (int)$up['artifact_id'];
			if (!isset($user_pieces_map[$aid])) {
				$user_pieces_map[$aid] = [];
			}
			$user_pieces_map[$aid][(int)$up['piece_id']] = $up;
		}

		$artifacts_list = [];

		foreach ($all_artifacts as $artifact) {
			$artifact_obj = (object) $artifact;
			if (!game_artifact_is_within_period($artifact_obj)) {
				continue;
			}

			$artifact_id = (int)$artifact['id'];

			// Số lượng còn lại (global)
			$times_redeemed = (int)$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$prefix}user_artifact_redemptions WHERE artifact_id = %d",
					$artifact_id
				)
			);
			$remaining_qty = max(0, (int)$artifact['max_redemptions'] - $times_redeemed);

			// Lấy tất cả pieces của artifact này
			$all_pieces_of_artifact = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						id as piece_id,
						piece_code,
						piece_img
					FROM {$prefix}pieces
					WHERE artifact_id = %d
					ORDER BY piece_code ASC",
					$artifact_id
				),
				ARRAY_A
			);

			$pieces_formatted = [];
			$user_pieces_count = 0;

			foreach ($all_pieces_of_artifact as $piece) {
				$piece_id = (int)$piece['piece_id'];
				$qty = 0;

				// Kiểm tra user có sở hữu mảnh này không
				if (isset($user_pieces_map[$artifact_id][$piece_id])) {
					$qty = (int)$user_pieces_map[$artifact_id][$piece_id]['qty'];
					$user_pieces_count += $qty;
				}

				$pieces_formatted[] = [
					'piece_id'   => $piece_id,
					'piece_code' => sanitize_text_field($piece['piece_code']),
					'piece_img'  => esc_url($piece['piece_img'] ?? ''),
					'qty'        => $qty,
				];
			}

			$artifacts_list[] = [
				'artifact_id'       => $artifact_id,
				'artifact_name'     => sanitize_text_field($artifact['name']),
				'artifacts_url'     => esc_url($artifact['artifacts_url'] ?? ''),
				'remaining_qty'     => $remaining_qty,
				'user_pieces_count' => $user_pieces_count,
				'pieces'            => $pieces_formatted,
			];
		}

		$response = [
			'total_pieces' => (int)$total_pieces,
			'artifacts'    => $artifacts_list,
		];

		return wg_json_response(200, $response, __('Lấy danh sách đổi quà thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));

	} catch (Throwable $e) {
		error_log('game rest error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * REST API: Màn "Kho quà tặng" - Lấy danh sách artifacts user đã đổi
 * Endpoint: /wp-json/game-bsc/v1/artifacts-inventory
 */
add_action('rest_api_init', function () {
	register_rest_route(NS, '/artifacts-inventory', array(
		'methods' => 'GET',
		'callback' => 'game_get_artifacts_inventory',
		'permission_callback' => 'game_rest_perm_cb',
	));
});

/**
 * Callback: Màn "Kho quà tặng"
 * Trả về:
 * - total_artifacts_owned: tổng số hiện vật user đã đổi
 * - total_pieces: tổng mảnh ghép user đang có
 * - artifacts: đã đổi (kể cả hiện vật hết thời hạn) + hiện vật chỉ có mảnh khi còn trong thời hạn period
 */
function game_get_artifacts_inventory(WP_REST_Request $request)
{
	global $wpdb;

	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce) {
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$user_id = absint($current_user['id']);
	$prefix  = $wpdb->prefix . 'game_';

	$user = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, status FROM {$prefix}users WHERE id = %d",
			$user_id
		),
		ARRAY_A
	);

	if (!$user || (int)$user['status'] === 0) {
		return wg_json_response(404, [], __('Không tìm thấy người dùng hoặc tài khoản bị khóa.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	try {
		// Tổng mảnh ghép user đang có
		$total_pieces = game_bsc_get_user_total_pieces($user_id);

		// Mảnh user đang sở hữu (qty > 0), map theo artifact_id + piece_id
		$user_pieces_all = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					up.piece_id,
					up.artifact_id,
					up.qty,
					p.piece_code,
					p.piece_img
				FROM {$prefix}user_pieces up
				INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
				WHERE up.user_id = %d AND up.qty > 0
				ORDER BY up.artifact_id ASC, p.piece_code ASC",
				$user_id
			),
			ARRAY_A
		);

		$user_pieces_map = [];
		foreach ($user_pieces_all as $up) {
			$aid = (int) $up['artifact_id'];
			if (!isset($user_pieces_map[$aid])) {
				$user_pieces_map[$aid] = [];
			}
			$user_pieces_map[$aid][(int) $up['piece_id']] = $up;
		}

		$format_artifact_pieces = function ($artifact_id) use ($wpdb, $prefix, $user_pieces_map) {
			$all_pieces_of_artifact = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						id as piece_id,
						piece_code,
						piece_img
					FROM {$prefix}pieces
					WHERE artifact_id = %d
					ORDER BY piece_code ASC",
					$artifact_id
				),
				ARRAY_A
			);

			$pieces_formatted   = [];
			$user_pieces_count = 0;

			foreach ($all_pieces_of_artifact as $piece) {
				$piece_id = (int) $piece['piece_id'];
				$qty      = 0;

				if (isset($user_pieces_map[ $artifact_id ][ $piece_id ])) {
					$qty = (int) $user_pieces_map[ $artifact_id ][ $piece_id ]['qty'];
					$user_pieces_count += $qty;
				}

				$pieces_formatted[] = [
					'piece_id'   => $piece_id,
					'piece_code' => sanitize_text_field($piece['piece_code']),
					'piece_img'  => esc_url($piece['piece_img'] ?? ''),
					'qty'        => $qty,
				];
			}

			return [ $pieces_formatted, $user_pieces_count ];
		};

		// Lấy danh sách artifacts user đã đổi (GROUP BY artifact_id)
		$user_artifacts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					uar.artifact_id,
					COUNT(*) as user_owned_qty,
					a.name as artifact_name,
					a.artifacts_url,
					a.max_redemptions
				 FROM {$prefix}user_artifact_redemptions uar
				 INNER JOIN {$prefix}artifacts a ON uar.artifact_id = a.id
				 WHERE uar.user_id = %d
				 GROUP BY uar.artifact_id, a.name, a.artifacts_url, a.max_redemptions
				 ORDER BY MAX(uar.redeemed_at) DESC",
				$user_id
			),
			ARRAY_A
		);

		$total_artifacts_owned = 0;
		$artifacts_list        = [];
		

		foreach ($user_artifacts as $row) {
			$artifact_id = (int) $row['artifact_id'];
			$user_owned_qty = (int) $row['user_owned_qty'];
			$total_artifacts_owned += $user_owned_qty;
			

			// Số lượng còn lại (global)
			$times_redeemed = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$prefix}user_artifact_redemptions WHERE artifact_id = %d",
					$artifact_id
				)
			);
			$remaining_qty = max(0, (int) $row['max_redemptions'] - $times_redeemed);

			$artifacts_list[] = [
				'artifact_id'         => $artifact_id,
				'artifact_name'       => sanitize_text_field($row['artifact_name']),
				'artifacts_url'       => esc_url($row['artifacts_url'] ?? ''),
				'remaining_qty'       => $remaining_qty,
				'user_owned_qty'      => $user_owned_qty,
			];
		}

		// Business rule: "Kho qua tang" chi hien thi hien vat user da doi thanh cong.

		$response = [
			'total_artifacts_owned' => $total_artifacts_owned,
			'total_pieces'          => (int) $total_pieces,
			'artifacts'             => $artifacts_list,
		];

		return wg_json_response(200, $response, __('Lấy kho quà tặng thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));

	} catch (Throwable $e) {
		error_log('game rest error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}


/**
 * API: Lấy danh sách mảnh ghép user sở hữu
 * Endpoint: /wp-json/game-bsc/v1/user-pieces
 *
 */
function game_get_user_pieces(WP_REST_Request $request) {
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
		
		// Optional filter: artifact_id
		$artifact_id = $request->get_param('artifact_id');
		$artifact_id = ($artifact_id === null || $artifact_id === '') ? 0 : absint($artifact_id);
		
		if ($artifact_id > 0) {
			$prefix = $wpdb->prefix . 'game_';
			$artifact = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id as artifact_id, id, name as artifact_name, artifacts_url, max_redemptions, status, period_start, period_end, total_periods, max_redemptions_per_period FROM {$prefix}artifacts WHERE id = %d",
					$artifact_id
				),
				ARRAY_A
			);
			if (!$artifact) {
				return wg_json_response(404, [], __('Không tìm thấy hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN));
			}

			// Kiểm tra game + period
			$game_period_check = game_bsc_compute_day_index();
			$game_active_check = ($game_period_check['status'] ?? '') !== 'ended'
				&& ($game_period_check['status'] ?? '') !== 'not_started';
			if (!$game_active_check || !game_artifact_is_within_period((object)$artifact)) {
				return wg_json_response(403, [], __('Hiện vật đã hết hạn hoặc game đã kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN));
			}

			// user pieces for this artifact
			$user_pieces = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						up.piece_id,
						up.artifact_id,
						up.qty,
						p.piece_code,
							p.baseline_weight,
						p.piece_img
					FROM {$prefix}user_pieces up
					INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
					WHERE up.user_id = %d AND up.artifact_id = %d AND up.qty > 0
					ORDER BY p.piece_code ASC",
					$user_id,
					$artifact_id
				),
				ARRAY_A
			);
			
			$user_pieces_map = [];
			$total_pieces_owned = 0;
			foreach ($user_pieces as $up) {
				$user_pieces_map[(int)$up['piece_id']] = $up;
				$total_pieces_owned += (int)$up['qty'];
			}
			
			$all_pieces_of_artifact = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						id as piece_id,
						piece_code,
						baseline_weight,
						piece_img
					FROM {$prefix}pieces
					WHERE artifact_id = %d
					ORDER BY piece_code ASC",
					$artifact_id
				),
				ARRAY_A
			);
			
			$pieces_formatted = [];
			foreach ($all_pieces_of_artifact as $piece) {
				$pid = (int)$piece['piece_id'];
				$qty = isset($user_pieces_map[$pid]) ? (int)$user_pieces_map[$pid]['qty'] : 0;
				$pieces_formatted[] = [
					'piece_id' => $pid,
					'piece_code' => sanitize_text_field($piece['piece_code']),
					'piece_img' => esc_url($piece['piece_img']),
					'qty' => $qty
				];
			}
			
			$times_redeemed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$prefix}user_artifact_redemptions
					 WHERE artifact_id = %d",
					$artifact_id
				)
			);
			$times_redeemed = (int)$times_redeemed;
			$max_redemptions = (int)$artifact['max_redemptions'];
			$remaining_qty = max(0, $max_redemptions - $times_redeemed);
			$artifact_data = [
				'artifact_id' => (int)$artifact['artifact_id'],
				'artifact_name' => sanitize_text_field($artifact['artifact_name']),
				'artifacts_url' => esc_url($artifact['artifacts_url']),
				'pieces' => $pieces_formatted,
				'remaining_qty' => $remaining_qty,
			];
			$response = [ 'total_pieces_owned' => $total_pieces_owned, 'artifacts' => [$artifact_data] ];
			return wg_json_response(200, $response, __('Lấy danh sách mảnh ghép thành công.'));
		}
		
		// ===== 2b. KIỂM TRA GAME CÒN ĐANG DIỄN RA =====
		$game_period = game_bsc_compute_day_index();
		$game_active = in_array($game_period['status'] ?? '', ['ongoing', 'active', 'started'], true)
			|| (isset($game_period['status']) && $game_period['status'] !== 'ended' && $game_period['status'] !== 'not_started');

		// ===== 3. LẤY TẤT CẢ HIỆN VẬT TRONG HỆ THỐNG =====
		$all_artifacts = $wpdb->get_results(
			"SELECT
				a.id as artifact_id,
				a.name as artifact_name,
				a.artifacts_url,
				a.max_redemptions,
				a.status,
				a.period_start,
				a.period_end,
				a.total_periods,
				a.max_redemptions_per_period
			FROM {$prefix}artifacts a
			ORDER BY a.id ASC",
			ARRAY_A
		);
		
		if (empty($all_artifacts)) {
			return wg_json_response(200, [
				'total_pieces_owned' => 0,
				'artifacts' => []
			], __('Lấy danh sách mảnh ghép thành công.'));
		}
		
		// ===== 4. LẤY CÁC MẢNH USER ĐANG SỞ HỮU (qty > 0) =====
		$user_pieces = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					up.piece_id,
					up.artifact_id,
					up.qty,
					p.piece_code,
					p.baseline_weight,
					p.piece_img
				FROM {$prefix}user_pieces up
				INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
				WHERE up.user_id = %d AND up.qty > 0
				ORDER BY up.artifact_id ASC, p.piece_code ASC",
				$user_id
			),
			ARRAY_A
		);
		
		// Tạo map để tra cứu nhanh
		$user_pieces_map = [];
		$total_pieces_owned = 0;
		
		foreach ($user_pieces as $up) {
			$artifact_id = (int)$up['artifact_id'];
			if (!isset($user_pieces_map[$artifact_id])) {
				$user_pieces_map[$artifact_id] = [];
			}
			$user_pieces_map[$artifact_id][] = $up;
			$total_pieces_owned += (int)$up['qty'];
		}
		
		// ===== 5. NHÓM DỮ LIỆU THEO HIỆN VẬT =====
		$artifacts_list = [];

		foreach ($all_artifacts as $artifact) {
			$artifact_id = (int)$artifact['artifact_id'];

			// Chuyển array → object để dùng helper
			$artifact_obj = (object)$artifact;
			$artifact_obj->id = $artifact_id;

			// Bỏ qua nếu: game không còn diễn ra HOẶC hiện vật ngoài thời hạn
			if (!$game_active || !game_artifact_is_within_period($artifact_obj)) {
				continue;
			}
			
			$artifact_data = [
				'artifact_id' => $artifact_id,
				'artifact_name' => sanitize_text_field($artifact['artifact_name']),
				'artifacts_url' => esc_url($artifact['artifacts_url']),
				'pieces' => []
			];
			
			$all_pieces_of_artifact = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						id as piece_id,
						piece_code,
						baseline_weight,
						piece_img
					FROM {$prefix}pieces
					WHERE artifact_id = %d
					ORDER BY piece_code ASC",
					$artifact_id
				),
				ARRAY_A
			);
			
			foreach ($all_pieces_of_artifact as $piece) {
				$piece_id = (int)$piece['piece_id'];
				
				// Kiểm tra user có sở hữu mảnh này không
				$qty = 0;
				if (isset($user_pieces_map[$artifact_id])) {
					foreach ($user_pieces_map[$artifact_id] as $owned_piece) {
						if ((int)$owned_piece['piece_id'] === $piece_id) {
							$qty = (int)$owned_piece['qty'];
							break;
						}
					}
				}
				
				$artifact_data['pieces'][] = [
					'piece_id' => $piece_id,
					'piece_code' => sanitize_text_field($piece['piece_code']),
					'piece_img' => esc_url($piece['piece_img']),
					'qty' => $qty
				];
			}
			
			$times_redeemed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$prefix}user_artifact_redemptions
					 WHERE artifact_id = %d",
					$artifact_id
				)
			);
			$times_redeemed = (int)$times_redeemed;
			$max_redemptions = (int)$artifact['max_redemptions'];
			$remaining_qty = max(0, $max_redemptions - $times_redeemed);
			
			$artifact_data['remaining_qty'] = $remaining_qty;
			
			$artifacts_list[] = $artifact_data;
		}
		
		// ===== 6. TRẢ VỀ RESPONSE =====
		$response = [
			'total_pieces_owned' => $total_pieces_owned,
			'artifacts' => $artifacts_list
		];
		
		return wg_json_response(200, $response, __('Lấy danh sách mảnh ghép thành công.'));
		
	} catch (Throwable $e) {
		error_log('Error in game_get_user_pieces: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
}
?>
