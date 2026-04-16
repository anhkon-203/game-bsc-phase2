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
		'permission_callback' => '__return_true',
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/v1/voucher-categories
	register_rest_route(NS, '/voucher-categories', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_voucher_categories',
		'permission_callback' => '__return_true',
	]);
});

add_action('rest_api_init', function () {

	// GET /wp-json/game-bsc/v1/vouchers
	register_rest_route(NS, '/vouchers', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_vouchers_list',
		'permission_callback' => '__return_true',
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

	// GET /wp-json/game-bsc/v1/gotit-vouchers
	register_rest_route(NS, '/gotit-vouchers', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_gotit_vouchers_list',
		'permission_callback' => '__return_true',
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
		'permission_callback' => '__return_true',
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
		'permission_callback' => '__return_true',
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
		'permission_callback' => '__return_true',
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
		'permission_callback' => '__return_true',
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
		'permission_callback' => '__return_true',
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
		'permission_callback' => '__return_true',
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
 * Callback: Lấy danh mục voucher từ taxonomy WordPress.
 * Response item: id, name, logo
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function game_bsc_get_voucher_categories(WP_REST_Request $request) {
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
		$total_pieces = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(qty), 0) FROM {$prefix}user_pieces WHERE user_id = %d",
				$user_id
			)
		);
		$total_pieces = (int)$total_pieces;
		
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
	try {
		global $wpdb;

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
			'orderby'        => 'post_date',
			'order'          => 'DESC',
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

		$args['posts_per_page'] = $per_page;
		$args['paged'] = $page;
		$args['no_found_rows'] = false;

		$query_result = new WP_Query($args);
		$all_vouchers = $query_result->posts;
		$query_total_items = (int) $query_result->found_posts;
		$query_total_pages = (int) $query_result->max_num_pages;

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
			$is_available = (int)$quantity > (int)$redemption_count;

			// Format thumbnail URL
			$thumbnail_url = '';
			if ($thumbnail_id) {
				$thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full') ?: '';
			}

			$partner_logo_url = game_bsc_resolve_partner_logo_url($partner['logo'] ?? '');
			if ($partner_logo_url === '') {
				$partner_logo_url = esc_url_raw((string) get_field('voucher_brand_logo_url', $post_id));
			}

			$formatted_vouchers[] = [
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
				'validity' => [
					'valid_from' => $valid_from ?: null,
					'valid_to' => $valid_to ?: null,
				],
				'is_valid_time' => $is_valid_time,
				'is_available' => $is_available,
				'redemption_count' => 0,
				'thumbnail_url' => $thumbnail_url,
				'is_bsc_fee_voucher' => (bool) get_field('is_bsc_fee_voucher', $post_id),
				'fee_refund_rate'    => (float) (get_field('fee_refund_rate', $post_id) ?: 0),
				'bsc_fee_denominations' => get_field('bsc_fee_denominations', $post_id) ?: '',
			];
		}

		// ===== 3. RETURN RESPONSE =====
		$success_message = $only_gotit
			? __('Lấy danh sách voucher Got It thành công.', WG_GAME_PLUGIN_TEXTDOMAIN)
			: __('Lấy danh sách voucher thành công.', WG_GAME_PLUGIN_TEXTDOMAIN);

		$total_items = max(0, (int) $query_total_items);
		$total_pages = max(0, (int) $query_total_pages);

		if ($total_pages > 0 && $page > $total_pages) {
			return wg_json_response(400, [], __('Số trang vượt quá tổng số trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}

		return wg_json_response(
			200,
			[
				'vouchers' => array_values($formatted_vouchers),
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
			SELECT g1.redemption_id, g1.transaction_ref_id, g1.gotit_expiry_date
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
			COALESCE(uvr.gotit_expiry_date, gtxn.gotit_expiry_date) AS expiry_date
		FROM {$prefix}user_voucher_redemptions uvr
		{$gotit_transaction_join_sql}
		WHERE {$where_sql}
		ORDER BY uvr.redeemed_at DESC, uvr.id DESC
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

		$vouchers[] = [
			'voucher_redemption_id' => (int) ($row['redemption_id'] ?? 0),
			'transaction_ref_id' => $transaction_ref_id,
			'redeemed_at' => sanitize_text_field((string) ($row['redeemed_at'] ?? '')),
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
		$unit_logo_url = game_bsc_resolve_partner_logo_url($partner_data['logo'] ?? '');
		if ($unit_logo_url === '') {
			$unit_logo_url = esc_url_raw((string) (get_field('voucher_brand_logo_url', $voucher_id) ?? ''));
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
		if ($transaction_user_id > 0 && $transaction_user_id !== $user_id) {
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

		if ($voucher_post && $voucher_post->post_type === 'game_vouchers') {
			$voucher_terms = (string) (get_field('voucher_terms', $voucher_post_id) ?? '');
			$voucher_service_guide = (string) (get_field('voucher_service_guide', $voucher_post_id) ?? '');
			$partner_data = get_field('partner', $voucher_post_id);
			if (!is_array($partner_data)) {
				$partner_data = [];
			}

			$voucher_brand_name = sanitize_text_field((string) ($partner_data['name'] ?? get_field('voucher_brand_name', $voucher_post_id) ?? ''));
			$voucher_brand_url = esc_url_raw((string) ($partner_data['url'] ?? get_field('voucher_link_url', $voucher_post_id) ?? ''));
			$voucher_brand_logo_url = game_bsc_resolve_partner_logo_url($partner_data['logo'] ?? '');
			if ($voucher_brand_logo_url === '') {
				$voucher_brand_logo_url = esc_url_raw((string) (get_field('voucher_brand_logo_url', $voucher_post_id) ?? ''));
			}

			$voucher_image_url = esc_url_raw((string) (get_field('voucher_image_url', $voucher_post_id) ?? ''));
		}

		if ($voucher_image_url === '') {
			$voucher_image_url = esc_url_raw((string) ($transaction['gotit_voucher_image'] ?? ''));
		}

		$expiry_date = sanitize_text_field((string) ($transaction['gotit_expiry_date'] ?? ''));
		$redemption_id = (int) ($transaction['redemption_id'] ?? 0);
		if ($expiry_date === '' && $redemption_id > 0) {
			$redemption_expiry = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT gotit_expiry_date FROM {$prefix}user_voucher_redemptions WHERE id = %d",
					$redemption_id
				)
			);

			if (is_string($redemption_expiry) && $redemption_expiry !== '') {
				$expiry_date = sanitize_text_field($redemption_expiry);
			}
		}

		$is_used = ((int) ($transaction['gotit_status'] ?? 0) === 4);
		$state_code = (int) ($transaction['gotit_status'] ?? 0);

		$client = game_bsc_gotit_client();
		if ($client && $client->is_configured()) {
			$ref_result = $client->get_vouchers_by_ref_id($transaction_ref_id, 1, 100, ['productInfo', 'usedInfo', 'groupInfo', 'stateInfo']);

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
						if ($expiry_date === '') {
							$expiry_date = sanitize_text_field((string) ($first_ref_voucher['expiryDate'] ?? $first_ref_voucher['expiry_date'] ?? ''));
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

		$response = [
			'transaction_ref_id' => $transaction_ref_id,
			'voucher_info' => [
				'voucher_id' => $voucher_post_id,
				'title' => sanitize_text_field((string) (($voucher_post && isset($voucher_post->post_title)) ? $voucher_post->post_title : ($transaction['gotit_order_name'] ?? ''))),
				'voucher_code' => sanitize_text_field((string) ($transaction['gotit_voucher_code'] ?? '')),
				'voucher_link' => esc_url((string) ($transaction['gotit_voucher_link'] ?? '')),
				'voucher_image' => esc_url($voucher_image_url),
				'serial' => sanitize_text_field((string) ($transaction['gotit_serial'] ?? '')),
				'brand_info' => [
					'name' => $voucher_brand_name,
					'url' => esc_url($voucher_brand_url),
					'logo_url' => esc_url($voucher_brand_logo_url),
				],
			],
			'expiry_date' => $expiry_date,
			'terms_and_conditions' => [
				'terms' => wp_kses_post($voucher_terms),
				'service_guide' => wp_kses_post($voucher_service_guide),
			],
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
				__('Bạn không đủ điểm. Cần %s điểm nhưng bạn chỉ có %s điểm.', WG_GAME_PLUGIN_TEXTDOMAIN),
				number_format($points_cost, 0, ',', '.'),
				number_format($user_points, 0, ',', '.')
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
					$gotit_error = trim((string) ($issue_result['error'] ?? 'Got It issue failed.'));
					throw new Exception('Got It issue failed: ' . $gotit_error);
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
					'gotit_partner_expiry_date' => $partner_expiry_date,
					'gotit_vendor_name' => $issued_vendor_name,
					'gotit_is_partner_code' => $issued_is_partner_code,
					'gotit_status' => $issued_status,
					'gotit_raw_response' => (string) ($issue_result['raw'] ?? ''),
					'gotit_status_code' => (int) ($issue_result['status_code'] ?? 0),
					'gotit_error_message' => (string) ($issue_result['error'] ?? ''),
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

			// ===== BSC FEE VOUCHER: Tạo instance nếu là voucher hoàn phí giao dịch =====
			$is_fee_voucher = (bool) get_field('is_fee_voucher', $voucher_post_id);
			$fee_voucher_instance_id = null;
			$fee_voucher_data = null;

			if ($is_fee_voucher && !$is_third_party_voucher) {
				$denomination = (int) get_field('fee_voucher_denomination', $voucher_post_id);
				$validity_days = (int) get_field('fee_voucher_validity_days', $voucher_post_id);

				if ($denomination < 1) {
					throw new Exception('Fee voucher denomination is not configured.');
				}
				if ($validity_days < 1) {
					$validity_days = 30; // Mặc định 30 ngày
				}

				$fee_valid_from = $now;
				$fee_valid_to = date('Y-m-d H:i:s', strtotime($now . ' + ' . $validity_days . ' days'));

				$fee_voucher_insert = $wpdb->insert(
					$prefix . 'bsc_fee_vouchers',
					[
						'user_id'           => $user_id,
						'redemption_id'     => $redemption_id,
						'voucher_post_id'   => $voucher_post_id,
						'denomination'      => $denomination,
						'remaining_balance' => $denomination,
						'fee_refund_rate'   => 100.00,
						'status'            => 'ACTIVE',
						'valid_from'        => $fee_valid_from,
						'valid_to'          => $fee_valid_to,
					],
					['%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s']
				);

				if (!$fee_voucher_insert) {
					throw new Exception('Failed to create BSC fee voucher instance: ' . $wpdb->last_error);
				}

				$fee_voucher_instance_id = (int) $wpdb->insert_id;
				$fee_voucher_data = [
					'fee_voucher_id'    => $fee_voucher_instance_id,
					'denomination'      => $denomination,
					'remaining_balance' => $denomination,
					'fee_refund_rate'   => 100.00,
					'valid_from'        => $fee_valid_from,
					'valid_to'          => $fee_valid_to,
					'status'            => 'ACTIVE',
				];
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

			// Nếu là voucher hoàn phí → thêm thông tin vào response
			if ($fee_voucher_data !== null) {
				$response_data['item']['fee_voucher'] = $fee_voucher_data;
			}
			
			return wg_json_response(200, $response_data, __('Đổi voucher thành công!', WG_GAME_PLUGIN_TEXTDOMAIN));
			
		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			error_log('Redeem voucher error: ' . $e->getMessage());
			return wg_json_response(500, [], __('Giao dịch thất bại: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN), 500);
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
				"SELECT id, name, max_redemptions, status, artifacts_url
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
				 WHERE up.user_id = %d AND up.artifact_id = %d
				 ORDER BY p.piece_code ASC",
				$user_id,
				$artifact_id
			),
			ARRAY_A
		);
		
		if (count($user_pieces) < 4) {
			return wg_json_response(422, [], __('Bạn chưa có đủ 4 mảnh của hiện vật này.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// Kiểm tra mỗi mảnh phải có qty >= 1
		foreach ($user_pieces as $piece) {
			if ((int)$piece['qty'] < 1) {
				return wg_json_response(422, [], sprintf(
					__('Bạn không có đủ mảnh %s.', WG_GAME_PLUGIN_TEXTDOMAIN),
					$piece['piece_code']
				));
			}
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
			
			
			if (($times_redeemed + 1) === $max_redemptions) {
				$artifact_close = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$prefix}artifacts
						 SET closed = 1
						 WHERE id = %d",
						$artifact_id
					)
				);
			}
			
			if($artifact_close == false){
				throw new Exception('Failed to close artifact: ' . $wpdb->last_error);
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
			return wg_json_response(500, [], __('Giao dịch thất bại: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN), 500);
		}
		
	} catch (Throwable $e) {
		error_log('game_bsc_redeem_artifact_internal error: ' . $e->getMessage());
		return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại sau.', WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}


/**
 * REST API để lấy danh sách vouchers và artifacts mà user đã đổi
 * Endpoint: /wp-json/game-bsc/v1/my-redemptions
 * User được xác thực qua SSO session
 */

add_action('rest_api_init', function () {
	register_rest_route(NS, '/my-redemptions', array(
		'methods' => 'GET',
		'callback' => 'game_get_my_redemptions',
		'permission_callback' => '__return_true',
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
		// ===== LẤY DANH SÁCH VOUCHERS ĐÃ ĐỔI =====
		$vouchers = game_get_user_voucher_redemptions($user_id);
		
		// ===== LẤY DANH SÁCH ARTIFACTS ĐÃ ĐỔI =====
		$artifacts = game_get_user_artifact_redemptions($user_id);
		
		// ===== GỘP CẢ 2 DANH SÁCH VÀ SORT THEO THỜI GIAN =====
		$all_items = [];
		
		foreach ($vouchers['items'] as $voucher) {
			$voucher['type'] = 'voucher';
			$all_items[] = $voucher;
		}
		
		foreach ($artifacts['items'] as $artifact) {
			$artifact['type'] = 'artifact';
			$all_items[] = $artifact;
		}
		
		// Sort theo redeemed_at giảm dần (mới nhất trước)
		usort($all_items, function ($a, $b) {
			$time_a = strtotime($a['redeemed_at']);
			$time_b = strtotime($b['redeemed_at']);
			return $time_b <=> $time_a;
		});
		
		$total_items = count($all_items);
		$total_pages = ceil($total_items / $per_page);
		
		if ($page > $total_pages && $total_pages > 0) {
			return wg_json_response(400, [], __('Số trang vượt quá tổng số trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		
		// Phân trang
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
		
		return wg_json_response(200, $response, __('Lấy danh sách quà đã đổi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Throwable $e) {
		return wg_json_response(500, [], __('Lỗi: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
}

/**
 * Hàm lấy danh sách vouchers đã đổi của user
 * ✅ THÊM SỐ LƯỢNG VOUCHER CÙNG LOẠI
 * ✅ SỬ DỤNG get_field() ĐỂ LẤY ACF FIELDS
 *
 * @param int $user_id
 * @return array
 */
function game_get_user_voucher_redemptions($user_id)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy danh sách redemptions - GROUP BY voucher_post_id để đếm số lượng
	$redemptions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT
				MIN(uvr.id) as redemption_id,
				uvr.voucher_post_id as voucher_id,
				MAX(uvr.redeemed_at) as redeemed_at,
				COUNT(*) as quantity,
				p.post_title as voucher_name
			FROM {$prefix}user_voucher_redemptions uvr
			INNER JOIN {$wpdb->posts} p ON uvr.voucher_post_id = p.ID
			WHERE uvr.user_id = %d
			GROUP BY uvr.voucher_post_id
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
		
		// ✅ SỬ DỤNG get_field() ĐỂ LẤY ACF FIELDS
		$voucher_code = sanitize_text_field(get_field('voucher_code', $voucher_id) ?? 'N/A');
		$voucher_type = sanitize_text_field(get_field('voucher_type', $voucher_id) ?? 'BSC');
		$points_cost = (int)(get_field('points_cost', $voucher_id) ?? 0);
		$redeemed_banner_id = (get_field('redeemed_banner_image', $voucher_id) ?? '');
		$redeemed_banner_image_url = '';
		if ($redeemed_banner_id){
			$redeemed_banner_image_url = wp_get_attachment_image_url($redeemed_banner_id, 'full') ?: '';
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
		
		// ✅ Lấy thông tin hạng sử dụng từ ACF field 'validity'
		$validity_data = get_field('validity', $voucher_id) ?: [];
		if (!is_array($validity_data)) {
			$validity_data = [];
		}

		$voucher_type_raw = strtoupper(trim((string) $voucher_type));
		$is_third_party_voucher = in_array($voucher_type_raw, ['THIRD_PARTY', 'THIRD-PARTY'], true);
		
		$valid_from = $validity_data['valid_from'] ?? '';
		$valid_to = $validity_data['valid_to'] ?? '';
		$is_valid = true;

		if ($is_third_party_voucher) {
			$valid_from = '';
			$valid_to = '';
		}
		
		if (!empty($valid_to)) {
			$today = game_now();
			$is_valid = strtotime($today) <= strtotime($valid_to);
		}
		
		$formatted[] = array(
//			'redemption_id' => 'voucher_' . (int)$redemption['redemption_id'],
			'voucher_id' => $voucher_id,
			'voucher_code' => $voucher_code,
			'voucher_name' => sanitize_text_field($redemption['voucher_name']),
			'redeemed_banner_image_url' => esc_url($redeemed_banner_image_url),
			'voucher_type' => $voucher_type,
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
					"SELECT id as artifact_id, name as artifact_name, artifacts_url, max_redemptions, status FROM {$prefix}artifacts WHERE id = %d",
					$artifact_id
				),
				ARRAY_A
			);
			if (!$artifact) {
				return wg_json_response(404, [], __('Không tìm thấy hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN));
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
					 WHERE user_id = %d AND artifact_id = %d",
					$user_id,
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
		
		// ===== 3. LẤY TẤT CẢ HIỆN VẬT TRONG HỆ THỐNG =====
		$all_artifacts = $wpdb->get_results(
			"SELECT
				a.id as artifact_id,
				a.name as artifact_name,
				a.artifacts_url,
				a.max_redemptions,
				a.status
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
					 WHERE user_id = %d AND artifact_id = %d",
					$user_id,
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
		return wg_json_response(500, [], __('Lỗi hệ thống: ' . $e->getMessage()));
	}
}
?>