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
	
	$rules = get_option('game_bsc_rules', []);
	
	if (!is_array($rules)) {
		$rules = [];
	}
	
	$rules_with_id = array_map(function($rule, $index) {
		return array_merge($rule, ['id' => $index + 1]);
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
	
	$banner_id = get_option('game_bsc_banner_manager', '');
	$banner_url = '';
	if ($banner_id) {
		$banner_url = wp_get_attachment_image_url($banner_id, 'full') ?: '';
	}
	
	return wg_json_response(200, [
		'banner_id' => $banner_id ? intval($banner_id) : null,
		'banner_url' => esc_url($banner_url),
	], __('Lấy banner game thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

?>