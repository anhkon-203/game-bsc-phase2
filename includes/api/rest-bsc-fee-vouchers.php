<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
	register_rest_route(NS, '/bsc-fee-vouchers', [
		'methods'             => 'GET',
		'callback'            => 'game_bsc_get_user_fee_vouchers',
		'permission_callback' => '__return_true',
	]);
});

function game_bsc_get_user_fee_vouchers(WP_REST_Request $request) {
	global $wpdb;
	try {
		$check_nonce = game_rest_perm_cb($request);
		if (!$check_nonce) {
			return wg_json_response(403, [], 'Yeu cau khong hop le.');
		}
		$current_user = game_sso_require_session();
		if (is_wp_error($current_user) || empty($current_user['id'])) {
			return wg_json_response(401, ['login_url' => bsc_game_url_sso()], 'Ban chua dang nhap.');
		}
		$user_id = absint($current_user['id']);
		$prefix  = $wpdb->prefix . 'game_';
		$now     = game_now();

		$wpdb->query($wpdb->prepare(
			"UPDATE {$prefix}bsc_fee_vouchers SET status = 'EXPIRED' WHERE user_id = %d AND status = 'ACTIVE' AND valid_to < %s",
			$user_id, $now
		));

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT fv.id AS fee_voucher_id, fv.voucher_post_id, fv.denomination, fv.remaining_balance, fv.fee_refund_rate, fv.status, fv.valid_from, fv.valid_to, fv.created_at, p.post_title AS voucher_name FROM {$prefix}bsc_fee_vouchers fv INNER JOIN {$wpdb->posts} p ON fv.voucher_post_id = p.ID WHERE fv.user_id = %d AND fv.status = 'ACTIVE' AND fv.remaining_balance > 0 ORDER BY fv.valid_to ASC",
			$user_id
		), ARRAY_A);

		$vouchers = [];
		foreach ($rows as $row) {
			$thumbnail_id  = get_post_thumbnail_id((int) $row['voucher_post_id']);
			$thumbnail_url = $thumbnail_id ? (wp_get_attachment_image_url($thumbnail_id, 'full') ?: '') : '';
			$vouchers[] = [
				'fee_voucher_id'    => (int) $row['fee_voucher_id'],
				'voucher_post_id'   => (int) $row['voucher_post_id'],
				'voucher_name'      => sanitize_text_field($row['voucher_name']),
				'thumbnail_url'     => $thumbnail_url,
				'denomination'      => (int) $row['denomination'],
				'remaining_balance' => (int) $row['remaining_balance'],
				'fee_refund_rate'   => (float) $row['fee_refund_rate'],
				'valid_from'        => $row['valid_from'],
				'valid_to'          => $row['valid_to'],
				'valid_from_display' => date('d/m/Y', strtotime($row['valid_from'])),
				'valid_to_display'   => date('d/m/Y', strtotime($row['valid_to'])),
				'created_at'        => $row['created_at'],
				'status'            => $row['status'],
			];
		}
		return wg_json_response(200, ['total' => count($vouchers), 'vouchers' => $vouchers], 'Lay kho qua tang thanh cong.');
	} catch (Throwable $e) {
		error_log('game_bsc_get_user_fee_vouchers error: ' . $e->getMessage());
		return wg_json_response(500, [], 'Loi he thong.', 500);
	}
}
