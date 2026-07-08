<?php
if (!defined('ABSPATH')) exit;

/**
 * REST API để lấy Thể lệ chương trình
 * Endpoint: /wp-json/game-bsc/rules
 */

add_action('rest_api_init', function () {
	// GET: Lấy danh sách tất cả thể lệ
	register_rest_route(NS, '/rules', array(
		'methods' => 'GET',
		'callback' => 'game_bsc_get_rules',
		'permission_callback' => '__return_true',
	));
});


function game_bsc_get_rules(WP_REST_Request $request) {
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	$rules = get_option('game_bsc_rules', []);
	
	if (!is_array($rules)) {
		$rules = [];
	}
	
	$rules_with_id = array_map(function($rule, $index) {
		return array_merge(['id' => $index + 1], $rule);
	}, $rules, array_keys($rules));
	
	return wg_json_response(200, [
		'rules' => $rules_with_id,
		'total' => count($rules),
	], __('Lấy thể lệ chương trình thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

add_action('rest_api_init', function () {
	// GET: Lấy banner hiển thị
	register_rest_route(NS, '/banner', array(
		'methods' => 'GET',
		'callback' => 'game_bsc_get_banner',
		'permission_callback' => '__return_true',
	));
});

function game_bsc_get_banner(WP_REST_Request $request) {
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$banner_id = get_option('game_bsc_banner_manager', '');
	$banner_url = '';
	if ($banner_id) {
		$banner_url = wp_get_attachment_image_url($banner_id, 'full') ?: '';
	}

	$banner_mobile_id = get_option('game_bsc_banner_mobile', '');
	$banner_mobile_url = '';
	if ($banner_mobile_id) {
		$banner_mobile_url = wp_get_attachment_image_url($banner_mobile_id, 'full') ?: '';
	}

	$banner_text = get_option('game_bsc_banner_text', '');
	$banner_icon_id = get_option('game_bsc_banner_icon', '');
	$banner_icon_url = '';
	if ($banner_icon_id) {
		$banner_icon_url = wp_get_attachment_image_url($banner_icon_id, 'full') ?: '';
	}

	$banner_icon_mobile_id = get_option('game_bsc_banner_icon_mobile', '');
	$banner_icon_mobile_url = '';
	if ($banner_icon_mobile_id) {
		$banner_icon_mobile_url = wp_get_attachment_image_url($banner_icon_mobile_id, 'full') ?: '';
	}

	return wg_json_response(200, [
		'banner_url' => esc_url($banner_url),
		'banner_mobile_url' => esc_url($banner_mobile_url),
		'banner_text' => esc_html($banner_text),
		'banner_icon_url' => esc_url($banner_icon_url),
		'banner_icon_mobile_url' => esc_url($banner_icon_mobile_url),
	], __('Lấy banner game thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

add_action('rest_api_init', function () {
	// GET: Lấy nội dung điều khoản đổi voucher
	register_rest_route(NS, '/terms', array(
		'methods' => 'GET',
		'callback' => 'game_bsc_get_terms',
		'permission_callback' => '__return_true',
	));

	// GET: Kiểm tra xem user hiện tại đã đồng ý điều khoản hay chưa
	register_rest_route(NS, '/terms/check', array(
		'methods' => 'GET',
		'callback' => 'game_bsc_check_terms',
		'permission_callback' => '__return_true',
	));

	// POST: Đồng ý điều khoản đổi voucher
	register_rest_route(NS, '/terms/accept', array(
		'methods' => 'POST',
		'callback' => 'game_bsc_accept_terms',
		'permission_callback' => '__return_true',
	));
});

/**
 * GET: Lấy nội dung điều khoản đổi voucher
 */
function game_bsc_get_terms(WP_REST_Request $request) {
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	$terms = get_option('game_bsc_terms', []);
	if (!is_array($terms)) {
		$terms = [];
	}
	
	$terms_with_id = array_map(function($term, $index) {
		return array_merge(['id' => $index + 1], $term);
	}, $terms, array_keys($terms));
	
	$link = get_option('game_bsc_terms_link', '');
	
	return wg_json_response(200, [
		'terms' => $terms_with_id,
		'link'  => $link,
	], __('Lấy điều khoản đổi voucher thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

/**
 * GET: Kiểm tra xem user hiện tại đã đồng ý điều khoản hay chưa
 */
function game_bsc_check_terms(WP_REST_Request $request) {
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	global $wpdb;
	$user_id = intval($current_user['id']);
	$table_name = $wpdb->prefix . 'game_user_terms_acceptance';
	
	$accepted = $wpdb->get_row($wpdb->prepare(
		"SELECT accepted_at FROM {$table_name} WHERE user_id = %d",
		$user_id
	));
	
	return wg_json_response(200, [
		'accepted' => !empty($accepted),
		'accepted_at' => $accepted ? $accepted->accepted_at : null,
	], __('Kiểm tra trạng thái đồng ý điều khoản thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

/**
 * POST: Đồng ý điều khoản đổi voucher
 */
function game_bsc_accept_terms(WP_REST_Request $request) {
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// ===== SECURITY: Kiểm tra session SSO =====
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	global $wpdb;
	$user_id = intval($current_user['id']);
	$table_name = $wpdb->prefix . 'game_user_terms_acceptance';
	
	// Kiểm tra đã đồng ý chưa
	$existing = $wpdb->get_row($wpdb->prepare(
		"SELECT user_id FROM {$table_name} WHERE user_id = %d",
		$user_id
	));
	
	if ($existing) {
		return wg_json_response(200, [
			'accepted' => true,
		], __('Bạn đã đồng ý điều khoản trước đó.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	$inserted = $wpdb->insert(
		$table_name,
		[
			'user_id' => $user_id,
			'accepted_at' => current_time('mysql'),
		],
		['%d', '%s']
	);
	
	if ($inserted === false) {
		return wg_json_response(500, [], __('Lỗi lưu trạng thái đồng ý điều khoản.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	return wg_json_response(200, [
		'accepted' => true,
	], __('Đồng ý điều khoản thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

?>