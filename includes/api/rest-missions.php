<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
	
	register_rest_route(NS, '/missions', [
		'methods'             => 'GET',
		'callback'            => 'game_missions_list',
		'permission_callback' => '__return_true',
	]);

	register_rest_route(NS, '/missions/check', [
		'methods'             => 'POST',
		'callback'            => 'game_missions_check',
		'permission_callback' => '__return_true',
	]);

	register_rest_route(NS, '/missions/check-all', [
		'methods'             => 'POST',
		'callback'            => 'game_missions_check_all',
		'permission_callback' => '__return_true',
	]);

	// API mới: Lấy thông báo nhiệm vụ và tự động đánh dấu đã xem
	register_rest_route(NS, '/missions/notifications', [
		'methods'             => 'GET',
		'callback'            => 'game_missions_get_notifications_and_mark_viewed',
		'permission_callback' => '__return_true',
	]);
});

//
// ====== CALLBACKS ======
//

/** GET /missions */
function game_missions_list(WP_REST_Request $request)
{
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
		
		$tz = TIMEZONE;
		$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
		

		$saved = get_option('game_bsc_tasks', []);
		if (!is_array($saved)) {
			return wg_json_response(200, [], 'success');
		}
		
		
		//nhiệm vụ có thể làm lại mỗi ngày)
		$daily_task_codes = [
			DAILY_LOGIN_CODE,
			MTRADER_LOGIN_CODE,
			TRADE_100M_VND_CODE
		];
		
		// nhiệm vụ chỉ làm được 1 lần)
		$onetime_task_codes = [
			EKYC_COMPLETE_CODE,
			OPEN_BIDV_CODE,
			OPEN_NEW_ACCOUNT_CODE,
			FIRST_DEPOSIT_CODE,
			OPEN_BSC_DERIVATIVE_ACCOUNT_CODE,
			OPEN_MARGIN_ACCOUNT_CODE,
			USE_BSC_BUY_PACKAGE_CODE,
			USE_MR90_PACKAGE_CODE,
		];
		
		// Nhiệm vụ cần check isexist (đã tồn tại từ trước)
		$exist_check_task_codes = [
			OPEN_BIDV_CODE,
			OPEN_MARGIN_ACCOUNT_CODE,
			OPEN_BSC_DERIVATIVE_ACCOUNT_CODE,
			USE_BSC_BUY_PACKAGE_CODE,
			// EKYC_COMPLETE_CODE
		];
		
		// Check và lưu log cho các nhiệm vụ đã tồn tại (chỉ chạy 1 lần duy nhất trong thời gian game)
		game_check_existing_missions_once($user_id, $exist_check_task_codes, $saved);
		
		$out = [];
		foreach ($saved as $code => $task) {
			$titleHas   = isset($task['title']) && trim((string)$task['title']) !== '';
			$spinsHas   = isset($task['reward_spins']) && (int)$task['reward_spins'] > 0;
			
			$actionUrl = isset($task['api_url']) && trim((string)$task['api_url']) !== '' ? trim((string)$task['api_url']) : '';
			
			// lưu ý guide_note
			$guide_note = isset($task['guide_note']) && trim((string)$task['guide_note']) !== '' ? trim((string)$task['guide_note']) : '';
			// độ trễ
			$guide_delay = isset( $task['guide_delay'])&& trim((string)$task['guide_delay']) !== '' ? trim((string)$task['guide_delay']) : '';
			// steps
			//"guide_steps":[{"content":"2222"},{"content":"<p>3333333333333<\/p>"}]
			// mỗi mảng là 1 bứớc
			$guide_steps = isset($task['guide_steps']) && is_array($task['guide_steps']) ? $task['guide_steps'] : [];
			if (!$titleHas || !$spinsHas) {
				continue; // bỏ qua nhiệm vụ không đủ thông tin
			}
			
			// >>> KIỂM TRA TRẠNG THÁI NHIỆM VỤ CỦA USER
			$task_status = 1;
			$is_exist = true; // Mặc định là nhiệm vụ đã tồn tại từ trước
			
			// Nếu là nhiệm vụ hàng ngày (daily): kiểm tra bản ghi hôm nay
			if (in_array($code, $daily_task_codes)) {
				$mission_log = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, reward_value FROM {$prefix}user_mission_logs
                         WHERE user_id = %d
                         AND mission_code = %s
                         AND mission_date = %s",
						$user_id,
						$code,
						$today
					)
				);
				if ($mission_log) {
					$task_status = 1;
					// if (isset($mission_log->reward_value) && intval($mission_log->reward_value) === 0) {
					// 	$is_exist = false;
					// }
				} else {
					$task_status = 0;
				}
			}
			// Nếu là nhiệm vụ một lần (one-time): kiểm tra bất kỳ ngày nào có bản ghi
			elseif (in_array($code, $onetime_task_codes)) {
				$mission_log = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, reward_value FROM {$prefix}user_mission_logs
                         WHERE user_id = %d
                         AND mission_code = %s
                         LIMIT 1",
						$user_id,
						$code
					)
				);
				if ($mission_log) {
					$task_status = 1;
					if (isset($mission_log->reward_value) && intval($mission_log->reward_value) === 0 && $code === OPEN_NEW_ACCOUNT_CODE) {
						// $is_exist = false;
						// Nếu là nhiệm vụ mở tài khoản mới thì không tính là đã hoàn thành nếu reward_value = 0 để hiển thị button thực hiện để hiển thị thông báo đúng yêu cầu
						$task_status = 0;
					}
				} else {
					$task_status = 0;
				}
			}
			
			$easy_task_codes = [
				DAILY_LOGIN_CODE,
				MTRADER_LOGIN_CODE,
				EKYC_COMPLETE_CODE,
				OPEN_BIDV_CODE,
				OPEN_NEW_ACCOUNT_CODE
			];
			$difficult_task_codes = [
				FIRST_DEPOSIT_CODE,
				OPEN_BSC_DERIVATIVE_ACCOUNT_CODE,
				OPEN_MARGIN_ACCOUNT_CODE,
				USE_BSC_BUY_PACKAGE_CODE,
				USE_MR90_PACKAGE_CODE,
				TRADE_100M_VND_CODE
			];
			
			if(in_array($code, $easy_task_codes)) {
				// Nhiệm vụ dễ
				$out['easy'][] = [
					'code'         => sanitize_key((string)$code),
					'action_url'   => $actionUrl,
					'title'        => (string)$task['title'],
					'reward_spins' => (int)$task['reward_spins'],
//					'description'  => isset($task['description']) ? (string)$task['description'] : '',
					'guide'        => [
						'note'  => $guide_note,
						'delay' => $guide_delay,
						'steps' => $guide_steps
					],
					'status'       => $task_status,
					'is_daily'     => in_array($code, $daily_task_codes),
					'is_exist'     => $is_exist
				];
			} elseif(in_array($code, $difficult_task_codes)) {
				// Nhiệm vụ khó
				$out['difficult'][] = [
					'code'         => sanitize_key((string)$code),
					'action_url'   => $actionUrl,
					'title'        => (string)$task['title'],
					'reward_spins' => (int)$task['reward_spins'],
//					'description'  => isset($task['description']) ? (string)$task['description'] : '',
					'guide'        => [
						'note'  => $guide_note,
						'delay' => $guide_delay,
						'steps' => $guide_steps
					],
					'status'       => $task_status,
					'is_daily'     => in_array($code, $daily_task_codes),
					'is_exist'     => $is_exist
				];
			}
		}
		// Thành công
		return wg_json_response(200, $out, __('Lấy danh sách nhiệm vụ thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Throwable $e) {
		// Lỗi phía server
		return wg_json_response(500, null, __('Lỗi hệ thống: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN), 500);
	}
	
}

function game_missions_check(WP_REST_Request $request)
{

	global $wpdb;
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id']) || empty($current_user['external_user_id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	// Kiểm tra xem có đang trong thời gian diễn ra game không
	$game_info = game_bsc_compute_day_index_v2($current_user['id']);
	if($game_info['status'] === 'invalid') {
		return wg_json_response(500, [], __('Đã có lỗi xảy ra vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}else if($game_info['status'] !== 'ongoing') {
		return wg_json_response(400, [], __('Thời gian diễn ra của game đã hết không thể thực hiện nhiệm vụ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$custodycd = $current_user['external_user_id'];

	$parameters = $request->get_json_params();
	$mission_code = isset($parameters['mission_code']) ? sanitize_text_field($parameters['mission_code']) : '';
	
	if (empty($mission_code) || $mission_code === DAILY_LOGIN_CODE) {
		return wg_json_response(400, null, __('Thiếu mã nhiệm vụ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// Định nghĩa các nhiệm vụ có độ trễ
	$delayed_missions = [
		OPEN_BIDV_CODE,
		OPEN_BSC_DERIVATIVE_ACCOUNT_CODE,
		OPEN_MARGIN_ACCOUNT_CODE,
		USE_BSC_BUY_PACKAGE_CODE,
		USE_MR90_PACKAGE_CODE,
		TRADE_100M_VND_CODE
	];
	
	// Xác định xem nhiệm vụ hiện tại có độ trễ hay không
	$is_delay = in_array($mission_code, $delayed_missions);
	
	// Lấy thông tin api tương ứng với mission_code
	$api_base = getEndpointFromMissionCode($mission_code);
	if(empty($api_base)) {
		return wg_json_response(400, null, __('Mã nhiệm vụ không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	// Kiểm tra xem user đã hoàn thành nhiệm vụ hiện tại chưa
	if(user_complete_mission($current_user['id'], $mission_code)) {
		if($mission_code === OPEN_NEW_ACCOUNT_CODE) {
			return wg_json_response(200, array(
				'mission_code' => $mission_code,
				'status' => false,
				'is_delay' => $is_delay
			), __('Hãy kêu gọi bạn bè mở tài khoản chứng khoán BSC để cùng săn nhiều phần quà giá trị!', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		return wg_json_response(400, null, __('Nhiệm vụ đã được hoàn thành.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}

	$apiBaseUrl = $api_base['base_url'];
	$endpoint = $api_base['end_point'];
	$reward_spins = $api_base['reward_spins'];
	if(empty($apiBaseUrl) || empty($endpoint) || empty($reward_spins)) {
		return wg_json_response(500, null, __('Lỗi cấu hình nhiệm vụ, vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// Nếu nhiệm vụ là thực hiện giao dịch 100tr
	if($mission_code === TRADE_100M_VND_CODE) {
		$amount_required = isset($api_base['amount_required']) ? $api_base['amount_required'] : 0;
		if($amount_required <=0) {
			return wg_json_response(500, null, __('Lỗi số liệu nhiệm vụ, vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
	} else if($mission_code === FIRST_DEPOSIT_CODE) {
		$open_new_account_base_url = $api_base['open_new_account_base_url'];
		$open_new_account_end_point = $api_base['open_new_account_end_point'];

		if(empty($open_new_account_base_url) || empty($open_new_account_end_point)) {
			return wg_json_response(500, null, __('Lỗi cấu hình nhiệm vụ, vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
	}

	// Lấy param của nhiệm vụ
	$data['custodycd'] = $custodycd;
	if($mission_code === MTRADER_LOGIN_CODE) {
		// $client_id = 'MTRADER2025';
		// $data['clientid'] = $client_id;
		$data['txdate'] = game_now('date');
	} else if($mission_code === FIRST_DEPOSIT_CODE) {
		$dStart = get_option('game_bsc_start_date');
		$dEnd = get_option('game_bsc_end_date');
		if(empty($dStart) || empty($dEnd)) {
			return wg_json_response(500, null, __('Đã có lỗi xảy ra. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		$data['dstart'] = $dStart;
		$data['dend'] = $dEnd;
		$data['transactionvalue'] = 10000;
	} else if($mission_code === OPEN_BSC_DERIVATIVE_ACCOUNT_CODE || $mission_code === EKYC_COMPLETE_CODE || $mission_code === OPEN_BIDV_CODE || $mission_code === OPEN_NEW_ACCOUNT_CODE
		|| $mission_code === OPEN_MARGIN_ACCOUNT_CODE || $mission_code === USE_BSC_BUY_PACKAGE_CODE || $mission_code === USE_MR90_PACKAGE_CODE ) {
		$dStart = get_option('game_bsc_start_date');
		$dEnd = get_option('game_bsc_end_date');
		if (empty($dStart) || empty($dEnd)) {
			return wg_json_response(500, null, __('Đã có lỗi xảy ra. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		$data['dstart'] = $dStart;
		$data['dend'] = $dEnd;
	} else if($mission_code === TRADE_100M_VND_CODE) {
		$yesterday = (new DateTime('now', TIMEZONE))->modify('-1 day')->format('Y-m-d');
		$data['txdate'] = $yesterday;
		$data['transactionvalue'] = $amount_required;
	}

	// Nếu nhiệm vụ là nạp tiền lần đầu
	// if($mission_code === FIRST_DEPOSIT_CODE) {
	// 	// Gọi api mở tài khoản để kiểm tra
	// 	$response_open_new_account = callApiGame($open_new_account_base_url . $open_new_account_end_point, $data, 'POST');
	// 	// Lỗi khi gọi api
	// 	if(!$response_open_new_account) {
	// 		return wg_json_response(500, null, __('An error occurred please contact the administrator.', WG_GAME_PLUGIN_TEXTDOMAIN));
	// 	}

	// 	if(isset($response_open_new_account->s) && isset($response_open_new_account->d) && $response_open_new_account->s == 'ok') {
	// 		if($response_open_new_account->d == false) {
	// 			return wg_json_response(200, null, __('Tài khoản của bạn không đủ điều kiện để hoàn thành nhiệm vụ này.', WG_GAME_PLUGIN_TEXTDOMAIN));
	// 		}
	// 	} else {
	// 		return wg_json_response(500, null, __('An error occurred please contact the administrator.', WG_GAME_PLUGIN_TEXTDOMAIN));
	// 	}
	// }

	// Gọi api kiểm tra trạng thái nhiệm vụ
	$response = callApiGame($apiBaseUrl . $endpoint, http_build_query($data, '', '&', PHP_QUERY_RFC3986), 'POST');
	
	// Lỗi khi gọi api
	if(!$response) {
		return wg_json_response(500, null, __('Đã có lỗi xảy ra. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	if($mission_code === TRADE_100M_VND_CODE) {
		if(isset($response->s) && isset($response->d) && $response->s == 'ok') {
			$actual_reward_spins = absint($response->d);
			
			if($actual_reward_spins > 0) {
				// Bắt đầu transaction
				$wpdb->query('START TRANSACTION');
				
				try {
					$mission_response = array(
						'mission_code' => $mission_code,
						'status' => true,
						'reward' => $actual_reward_spins
					);
					
					$status_int = $response->s == 'ok' ? 1 : 0;
					$mission_log_result = $wpdb->insert(
						$wpdb->prefix . 'game_user_mission_logs',
						[
							'user_id'       => absint($current_user['id']),
							'mission_code'  => sanitize_text_field($mission_code),
							'mission_date'  => game_now(),
							'reward_type'   => 'PLAY_CREDIT',
							'reward_value'  => absint($actual_reward_spins),
							'status'        => 'VERIFIED',
							'verified_at'   => game_now(),
							'api_status'    => $status_int,
							'api_payload'   => wp_json_encode($response),
							'viewed'    	=> 1
						],
						[
							'%d',
							'%s',
							'%s',
							'%s',
							'%d',
							'%s',
							'%s',
							'%d',
							'%s',
							'%d'
						]
					);
					
					if (!$mission_log_result) {
						throw new Exception('Failed to insert mission log');
					}
					
					$id_mission_log = $wpdb->insert_id;
					
					$table_balance = $wpdb->prefix . 'game_play_credit_balances';
					$play_credit_balance_result = $wpdb->query($wpdb->prepare(
						"UPDATE $table_balance SET balance = balance + %d WHERE user_id = %d",
						$actual_reward_spins,
						$current_user['id']
					));
					
					if ($play_credit_balance_result === false) {
						throw new Exception('Failed to update play credit balance');
					}
					
					$play_credit_ledger_result = $wpdb->insert(
						$wpdb->prefix . 'game_play_credit_ledger',
						[
							'user_id'   => absint($current_user['id']),
							'delta'     => absint($actual_reward_spins),
							'ref_type'  => 'MISSION',
							'ref_id'    => absint($id_mission_log),
							'created_at'=> game_now(),
						],
						[
							'%d',
							'%d',
							'%s',
							'%d',
							'%s'
						]
					);
					
					if (!$play_credit_ledger_result) {
						throw new Exception('Failed to insert play credit ledger');
					}
					
					// Commit transaction nếu tất cả thành công
					$wpdb->query('COMMIT');
					
					return wg_json_response(200, $mission_response, __('Bạn đã nhận được ' . $actual_reward_spins . ' lượt chơi', WG_GAME_PLUGIN_TEXTDOMAIN));
					
				} catch (Exception $e) {
					// Rollback nếu có lỗi
					$wpdb->query('ROLLBACK');
					return wg_json_response(500, null, __('Đã có lỗi xảy ra khi xử lý nhiệm vụ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
				}
			}
			// Chưa hoàn thành nhiệm vụ hoặc không có lượt chơi
			$mission_response = array(
				'mission_code' => $mission_code,
				'status' => false,
				'is_delay' => $is_delay
			);
			return wg_json_response(200, $mission_response, __('<ul><li>Nếu bạn chưa thực hiện nhiệm vụ này, vui lòng thực hiện nhiệm vụ để được nhận thêm lượt chơi.</li><li>Nếu bạn đã thực hiện nhiệm vụ, vui lòng chờ đợi BSC phê duyệt trong vòng 01 ngày làm việc.</li></ul>', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
		return wg_json_response(500, null, __('Đã có lỗi xảy ra. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	} else { // Xu ly các nhiệm vụ khác
		if(isset($response->s) && isset($response->d) && $response->s == 'ok') {
			if($response->d == true) {
				// Bắt đầu transaction
				$wpdb->query('START TRANSACTION');
				
				try {
					$mission_response = array(
						'mission_code' => $mission_code,
						'status' => true,
						'reward' => $reward_spins
					);
					
					$status_int = $response->s == 'ok' ? 1 : 0;
					$mission_log_result = $wpdb->insert(
						$wpdb->prefix . 'game_user_mission_logs',
						[
							'user_id'       => absint($current_user['id']),
							'mission_code'  => sanitize_text_field($mission_code),
							'mission_date'  => game_now(),
							'reward_type'   => 'PLAY_CREDIT',
							'reward_value'  => absint($reward_spins),
							'status'        => 'VERIFIED',
							'verified_at'   => game_now(),
							'api_status'    => $status_int,
							'api_payload'   => wp_json_encode($response),
							'viewed'    	=> 1
						],
						[
							'%d',
							'%s',
							'%s',
							'%s',
							'%d',
							'%s',
							'%s',
							'%d',
							'%s',
							'%d'
						]
					);
					
					if (!$mission_log_result) {
						throw new Exception('Failed to insert mission log');
					}
					
					$id_mission_log = $wpdb->insert_id;
					
					$table_balance = $wpdb->prefix . 'game_play_credit_balances';
					$play_credit_balance_result = $wpdb->query($wpdb->prepare(
						"UPDATE $table_balance SET balance = balance + %d WHERE user_id = %d",
						$reward_spins,
						$current_user['id']
					));
					
					if ($play_credit_balance_result === false) {
						throw new Exception('Failed to update play credit balance');
					}
					
					$play_credit_ledger_result = $wpdb->insert(
						$wpdb->prefix . 'game_play_credit_ledger',
						[
							'user_id'   => absint($current_user['id']),
							'delta'     => absint($reward_spins),
							'ref_type'  => 'MISSION',
							'ref_id'    => absint($id_mission_log),
							'created_at'=> game_now(),
						],
						[
							'%d',
							'%d',
							'%s',
							'%d',
							'%s'
						]
					);
					
					if (!$play_credit_ledger_result) {
						throw new Exception('Failed to insert play credit ledger');
					}
					
					// Commit transaction nếu tất cả thành công
					$wpdb->query('COMMIT');
					
					return wg_json_response(200, $mission_response, __('Bạn đã nhận được ' . $reward_spins . ' lượt chơi', WG_GAME_PLUGIN_TEXTDOMAIN));
					
				} catch (Exception $e) {
					// Rollback nếu có lỗi
					$wpdb->query('ROLLBACK');
					return wg_json_response(500, null, __('Đã có lỗi xảy ra khi xử lý nhiệm vụ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
				}
			}
			// Chưa hoàn thành nhiệm vụ
			$mission_response = array(
				'mission_code' => $mission_code,
				'status' => false,
				'is_delay' => $is_delay
			);
			if($is_delay) {
				return wg_json_response(200, $mission_response, __('<ul><li>Nếu bạn chưa thực hiện nhiệm vụ này, vui lòng thực hiện nhiệm vụ để được nhận thêm lượt chơi.</li><li>Nếu bạn đã thực hiện nhiệm vụ, vui lòng chờ đợi BSC phê duyệt trong vòng 01 ngày làm việc.</li></ul>', WG_GAME_PLUGIN_TEXTDOMAIN));
			} else {
				return wg_json_response(200, $mission_response, __('Bạn chưa thực hiện nhiệm vụ này. Hãy thực hiện nhiệm vụ để được nhận thêm lượt chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));
			}
		}
		return wg_json_response(500, null, __('Đã có lỗi xảy ra. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
}

function game_missions_check_all(WP_REST_Request $request){
	global $wpdb;
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	// Kiểm tra session SSO
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id']) || empty($current_user['external_user_id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// Kiểm tra thời gian game
	$game_info = game_bsc_compute_day_index_v2($current_user['id']);
	if ($game_info['status'] === 'invalid') {
		return wg_json_response(500, [], __('Đã có lỗi xảy ra vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
	} else if ($game_info['status'] !== 'ongoing') {
		return wg_json_response(400, [], __('Thời gian diễn ra của game đã hết không thể thực hiện nhiệm vụ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	$custodycd = $current_user['external_user_id'];
	$user_id = absint($current_user['id']);
	$prefix = $wpdb->prefix . 'game_';
	
	// CACHE CHECK: Kiểm tra cache để tránh gọi API liên tục
	$cache_key = "missions_check_{$user_id}";
	$cached_result = wp_cache_get($cache_key);
	if ($cached_result !== false) {
		return wg_json_response(200, $cached_result['data'], $cached_result['message']);
	}
	
	// Lấy ngày game
	$dStart = get_option('game_bsc_start_date');
	$dEnd = get_option('game_bsc_end_date');
	if (empty($dStart) || empty($dEnd)) {
		return wg_json_response(500, null, __('Đã có lỗi xảy ra. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// Danh sách nhiệm vụ cần check
	$mission_codes_to_check = [
		EKYC_COMPLETE_CODE,
		OPEN_BIDV_CODE,
		OPEN_NEW_ACCOUNT_CODE,
		FIRST_DEPOSIT_CODE,
		OPEN_BSC_DERIVATIVE_ACCOUNT_CODE,
		OPEN_MARGIN_ACCOUNT_CODE,
		USE_BSC_BUY_PACKAGE_CODE,
		USE_MR90_PACKAGE_CODE
	];
	
	// Lấy một lần tất cả mission logs đã có
	$placeholders = implode(',', array_fill(0, count($mission_codes_to_check), '%s'));
	$existing_missions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT mission_code FROM {$prefix}user_mission_logs
			WHERE user_id = %d AND mission_code IN ($placeholders)",
			array_merge([$user_id], $mission_codes_to_check)
		),
		ARRAY_A
	);
	
	// Map các nhiệm vụ đã hoàn thành
	$completed_missions_map = [];
	foreach ($existing_missions as $row) {
		$completed_missions_map[$row['mission_code']] = true;
	}
	
	$results = [];
	$total_rewards = 0;
	$missions_completed = [];
	
	// Thu thập missions cần gọi API
	$missions_to_call = [];
	foreach ($mission_codes_to_check as $mission_code) {
		if (isset($completed_missions_map[$mission_code])) {
			$results[$mission_code] = [
				'status' => 0,
				'message' => 'Đã hoàn thành trước đó'
			];
			continue;
		}
		
		$api_base = getEndpointFromMissionCode($mission_code);
		if (empty($api_base) || empty($api_base['base_url']) || empty($api_base['end_point']) || empty($api_base['reward_spins'])) {
			$results[$mission_code] = [
				'status' => 0,
				'message' => 'Lỗi cấu hình nhiệm vụ'
			];
			continue;
		}
		
		$missions_to_call[$mission_code] = [
			'url' => $api_base['base_url'],
			'endpoint' => $api_base['end_point'],
			'reward_spins' => $api_base['reward_spins']
		];
	}
	
	// OPTIMIZATION: Gọi API song song với curl_multi được tối ưu
	$api_responses = [];
	if (!empty($missions_to_call)) {
		$multi_handle = curl_multi_init();
		$curl_handles = [];
		
		// Tăng số connections đồng thời
		curl_multi_setopt($multi_handle, CURLMOPT_MAX_TOTAL_CONNECTIONS, 10);
		curl_multi_setopt($multi_handle, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
		
		foreach ($missions_to_call as $mission_code => $api_info) {
			$data = [
				'custodycd' => $custodycd,
				'dstart' => $dStart,
				'dend' => $dEnd
			];
			
			if ($mission_code === FIRST_DEPOSIT_CODE) {
				$data['transactionvalue'] = 10000;
			}
			
			$url = $api_info['url'] . $api_info['endpoint'];
			$post_data = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
			
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => $url,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $post_data,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 20, // Giảm từ 30s
				CURLOPT_CONNECTTIMEOUT => 5, // Giảm từ 10s
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Dùng HTTP/2 nếu hỗ trợ
				CURLOPT_TCP_NODELAY => true, // Tắt Nagle's algorithm
				CURLOPT_ENCODING => '', // Bật compression
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/x-www-form-urlencoded',
					'Accept-Encoding: gzip, deflate',
					'Connection: keep-alive'
				]
			]);
			
			curl_multi_add_handle($multi_handle, $ch);
			$curl_handles[$mission_code] = $ch;
		}
		
		// Thực thi với timeout tối ưu
		$running = null;
		$max_wait = 0.1; // 100ms
		do {
			$status = curl_multi_exec($multi_handle, $running);
			if ($running) {
				// Chờ activity hoặc timeout
				curl_multi_select($multi_handle, $max_wait);
			}
		} while ($running > 0 && $status == CURLM_OK);
		
		// Thu thập kết quả
		foreach ($curl_handles as $mission_code => $ch) {
			$response_body = curl_multi_getcontent($ch);
			$curl_error = curl_error($ch);
			
			if (!empty($curl_error)) {
				$api_responses[$mission_code] = null;
			} else {
				$api_responses[$mission_code] = json_decode($response_body);
			}
			
			curl_multi_remove_handle($multi_handle, $ch);
			curl_close($ch);
		}
		
		curl_multi_close($multi_handle);
	}
	
	// Xử lý kết quả và chuẩn bị bulk insert
	$mission_logs_data = [];
	$ledger_data = [];
	$db_operations = [];
	$open_new_account_response = null;

	if (isset($api_responses[OPEN_NEW_ACCOUNT_CODE])) {
		$open_new_account_response = $api_responses[OPEN_NEW_ACCOUNT_CODE];
	}

	foreach ($missions_to_call as $mission_code => $api_info) {
		$response = isset($api_responses[$mission_code]) ? $api_responses[$mission_code] : null;
		
		if (!$response || !isset($response->s) || !isset($response->d)) {
			$results[$mission_code] = [
				'status' => 0,
				'message' => 'Lỗi khi gọi API kiểm tra'
			];
			continue;
		}
		
		if ($response->s === 'ok') {

			/**
			 * CASE 1: Hoàn thành bình thường (d = true)
			 */
			if ($response->d === true) {

				$reward_spins = $api_info['reward_spins'];
				$total_rewards += $reward_spins;
				$missions_completed[] = $mission_code;

				$db_operations[] = [
					'mission_code' => $mission_code,
					'reward_spins' => $reward_spins,
					'response'     => $response
				];

				$results[$mission_code] = [
					'status'  => 1,
					'reward'  => $reward_spins,
					'message' => 'Hoàn thành nhiệm vụ'
				];

			}

			/**
			 * CASE 2: OPEN_NEW_ACCOUNT_CODE + d = false
			 * => vẫn ghi nhận hoàn thành, 0 lượt chơi
			 */
			else if (
				$mission_code === OPEN_NEW_ACCOUNT_CODE &&
				$response->d === false
			) {

				$missions_completed[] = $mission_code;

				$db_operations[] = [
					'mission_code' => $mission_code,
					'reward_spins' => 0,
					'response'     => $response
				];

				$results[$mission_code] = [
					'status'  => 1,
					'reward'  => 0,
					'message' => 'Nhiệm vụ đã được ghi nhận'
				];
			}

			/**
			 * CASE 3: EKYC_COMPLETE_CODE
			 * + OPEN_NEW_ACCOUNT_CODE đã trả về d = false
			 */
			else if (
				$mission_code === EKYC_COMPLETE_CODE &&
				$open_new_account_response &&
				$open_new_account_response->s === 'ok' &&
				$open_new_account_response->d === false
			) {

				$missions_completed[] = $mission_code;

				$db_operations[] = [
					'mission_code' => $mission_code,
					'reward_spins' => 0,
					'response'     => $response
				];

				$results[$mission_code] = [
					'status'  => 1,
					'reward'  => 0,
					'message' => 'EKYC đã được ghi nhận'
				];
			}

			/**
			 * CASE 4: Chưa đạt
			 */
			else {

				$results[$mission_code] = [
					'status'  => 0,
					'message' => 'Chưa đạt điều kiện'
				];
			}

		} else {

			$results[$mission_code] = [
				'status'  => 0,
				'message' => 'Chưa đạt điều kiện'
			];
		}
	}
	
	// OPTIMIZATION: Bulk insert thay vì insert từng dòng
	if (!empty($db_operations)) {
		$wpdb->query('START TRANSACTION');
		
		try {
			$now = game_now();
			$mission_log_values = [];
			$ledger_values = [];
			
			foreach ($db_operations as $op) {
				$mission_code = $wpdb->_real_escape($op['mission_code']);
				$reward_spins = absint($op['reward_spins']);
				$api_payload = $wpdb->_real_escape(wp_json_encode($op['response']));
				
				$mission_log_values[] = "({$user_id}, '{$mission_code}', '{$now}', 'PLAY_CREDIT', {$reward_spins}, 'VERIFIED', '{$now}', 1, 0, '{$api_payload}')";
			}
			
			// Bulk insert mission logs
			if (!empty($mission_log_values)) {
				$values_str = implode(',', $mission_log_values);
				$insert_result = $wpdb->query(
					"INSERT INTO {$prefix}user_mission_logs
					(user_id, mission_code, mission_date, reward_type, reward_value, status, verified_at, api_status, viewed, api_payload)
					VALUES {$values_str}"
				);
				
				if ($insert_result === false) {
					throw new Exception('Failed to bulk insert mission logs');
				}
				
				// Lấy IDs của các records vừa insert
				$first_insert_id = $wpdb->insert_id;
				
				// Cập nhật balance một lần
				$table_balance = $wpdb->prefix . 'game_play_credit_balances';
				$balance_result = $wpdb->query($wpdb->prepare(
					"UPDATE $table_balance SET balance = balance + %d WHERE user_id = %d",
					$total_rewards,
					$user_id
				));
				
				if ($balance_result === false) {
					throw new Exception('Failed to update play credit balance');
				}
				
				// Bulk insert ledger
				for ($i = 0; $i < count($db_operations); $i++) {
					$reward_spins = absint($db_operations[$i]['reward_spins']);
					$ref_id = $first_insert_id + $i;
					$ledger_values[] = "({$user_id}, {$reward_spins}, 'MISSION', {$ref_id}, '{$now}')";
				}
				
				$ledger_values_str = implode(',', $ledger_values);
				$ledger_result = $wpdb->query(
					"INSERT INTO {$wpdb->prefix}game_play_credit_ledger
					(user_id, delta, ref_type, ref_id, created_at)
					VALUES {$ledger_values_str}"
				);
				
				if ($ledger_result === false) {
					throw new Exception('Failed to bulk insert play credit ledger');
				}
			}
			
			$wpdb->query('COMMIT');
			
		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			return wg_json_response(500, [
				'results' => $results,
				'error' => $e->getMessage()
			], __('Đã có lỗi xảy ra khi xử lý nhiệm vụ. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
		}
	}
	
	$response_data = [
		'results' => $results,
		'total_rewards' => $total_rewards,
		'completed_count' => count($missions_completed)
	];
	
	$response_message = __('Kiểm tra hoàn tất. Bạn đã nhận được tổng ' . $total_rewards . ' lượt chơi', WG_GAME_PLUGIN_TEXTDOMAIN);
	
	// Cache kết quả trong 30 giây
	wp_cache_set($cache_key, [
		'data' => $response_data,
		'message' => $response_message
	], '', 30);
	
	return wg_json_response(200, $response_data, $response_message);
}

/**
 * Kiểm tra các nhiệm vụ đã tồn tại (isexist=true) và tự động lưu mission log với 0 điểm, viewed = 1
 * Hàm này CHỈ CHẠY 1 LẦN DUY NHẤT trong thời gian game cho mỗi user => Đổi sang check mỗi lần gọi API nhiệm vụ
 *
 * @param int $user_id ID người dùng
 * @param array $exist_check_task_codes Danh sách mã nhiệm vụ cần check
 * @param array $saved Cấu hình nhiệm vụ từ options
 * @return void
 */
function game_check_existing_missions_once($user_id, $exist_check_task_codes, $saved) {
	global $wpdb;
	
	// Lấy thông tin user từ session
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['external_user_id'])) {
		return; // Không có session, bỏ qua
	}
	
	// Lấy ngày bắt đầu và kết thúc game
	$dStart = get_option('game_bsc_start_date');
	$dEnd = get_option('game_bsc_end_date');
	if (empty($dStart) || empty($dEnd)) {
		return;
	}
	
	// Kiểm tra flag đã check chưa (lưu transient có thời hạn đến hết game)
	// $check_flag_key = 'game_bsc_exist_check_u' . $user_id;
	// $cache_key = $check_flag_key . '_' . md5($dStart . $dEnd);
	
	// Kiểm tra xem đã check chưa
	// $already_checked = get_transient($cache_key);
	// if ($already_checked === 'done') {
	// 	return; // Đã check rồi, bỏ qua
	// }
	
	$custodycd = $current_user['external_user_id'];
	$prefix = $wpdb->prefix . 'game_';
	
	foreach ($exist_check_task_codes as $mission_code) {
		// Kiểm tra xem đã có mission_log chưa
		$mission_log_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}user_mission_logs
				WHERE user_id = %d AND mission_code = %s",
				$user_id,
				$mission_code
			)
		);
		
		// Nếu đã có log rồi thì bỏ qua
		if ($mission_log_exists > 0) {
			continue;
		}
		
		// Lấy thông tin API endpoint
		$api_base = getEndpointFromMissionCode($mission_code);
		if (empty($api_base)) {
			continue;
		}
		
		$apiBaseUrl = $api_base['base_url'];
		$endpoint = $api_base['end_point'];
		
		if (empty($apiBaseUrl) || empty($endpoint)) {
			continue;
		}
		
		// Chuẩn bị data cho API với isexist=true
		$data = [
			'custodycd' => $custodycd,
			'dstart' => $dStart,
			'dend' => $dEnd,
			'isexist' => 'true' // Param đặc biệt để check tồn tại
		];
		
		// Gọi API kiểm tra
		$response = callApiGame($apiBaseUrl . $endpoint, http_build_query($data, '', '&', PHP_QUERY_RFC3986), 'POST');
		
		// Nếu API không trả về hoặc lỗi, bỏ qua
		if (!$response || !isset($response->s) || !isset($response->d)) {
			continue;
		}
		
		// Nếu API trả về true (đã tồn tại), lưu mission log với 0 điểm và viewed = 1
		if ($response->s === 'ok' && $response->d === true) {
			$wpdb->insert(
				"{$prefix}user_mission_logs",
				[
					'user_id'       => absint($user_id),
					'mission_code'  => sanitize_text_field($mission_code),
					'mission_date'  => game_now(),
					'reward_type'   => 'PLAY_CREDIT',
					'reward_value'  => 0, // 0 điểm
					'status'        => 'VERIFIED',
					'verified_at'   => game_now(),
					'api_status'    => 1,
					'api_payload'   => wp_json_encode($response),
					'viewed'        => 1 // Đánh dấu đã xem
				],
				[
					'%d',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%d',
					'%s',
					'%d'
				]
			);
		}
	}
	
	// Lưu flag đã check (transient hết hạn sau khi game kết thúc)
	// Tính số giây từ bây giờ đến khi game kết thúc + thêm 1 ngày
	// try {
	// 	$now = new DateTime('now', TIMEZONE);
	// 	$end_date = new DateTime($dEnd . ' 23:59:59', TIMEZONE);
	// 	$seconds_until_end = $end_date->getTimestamp() - $now->getTimestamp();
		
	// 	// Nếu game đã kết thúc hoặc lỗi tính toán, set 7 ngày
	// 	if ($seconds_until_end <= 0) {
	// 		$seconds_until_end = 7 * DAY_IN_SECONDS;
	// 	} else {
	// 		// Thêm 1 ngày buffer
	// 		$seconds_until_end += DAY_IN_SECONDS;
	// 	}
		
	// 	set_transient($cache_key, 'done', $seconds_until_end);
	// } catch (Exception $e) {
	// 	// Fallback: lưu 30 ngày nếu có lỗi
	// 	set_transient($cache_key, 'done', 30 * DAY_IN_SECONDS);
	// }
}

/**
 * ============================================================================
 * API MỚI: Lấy thông báo nhiệm vụ đã hoàn thành và tự động đánh dấu đã xem
 * ============================================================================
 * GET /wp-json/game-bsc/v1/missions/notifications
 *
 * Khi gọi API này:
 * 1. Lấy danh sách nhiệm vụ viewed = 0 (chưa xem) và reward_value > 0
 * 2. Tự động cập nhật viewed = 1 cho tất cả các nhiệm vụ đó
 * 3. Trả về danh sách kèm tên nhiệm vụ và số lượt chơi được tặng
 *
 * @param WP_REST_Request $req
 * @return WP_REST_Response
 */
function game_missions_get_notifications_and_mark_viewed(WP_REST_Request $request){
	global $wpdb;
	$check_nonce = game_rest_perm_cb($request);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	// Kiểm tra session SSO
	$current_user = game_sso_require_session();
	if (is_wp_error($current_user) || empty($current_user['id'])) {
		return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	$user_id = absint($current_user['id']);
	$prefix = $wpdb->prefix . 'game_';
	
	// Lấy cấu hình nhiệm vụ để map tên
	$saved_tasks = get_option('game_bsc_tasks', []);
	if (!is_array($saved_tasks)) {
		$saved_tasks = [];
	}
	
	// Lấy config mặc định từ file làm fallback
	$default_missions = [];
	$missions_config_file = GAME_BSC_PLUGIN_DIR . 'config/missions.php';
	if (file_exists($missions_config_file)) {
		$missions_config = include $missions_config_file;
		if (is_array($missions_config)) {
			foreach ($missions_config as $mission) {
				if (isset($mission['code']) && isset($mission['title'])) {
					$default_missions[$mission['code']] = $mission['title'];
				}
			}
		}
	}
	
	// Bắt đầu transaction để đảm bảo tính nhất quán
	$wpdb->query('START TRANSACTION');
	
	try {
		// 1. Lấy danh sách mission logs chưa xem (viewed = 0) và có điểm thưởng > 0
		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, mission_code, reward_value, mission_date, verified_at
				FROM {$prefix}user_mission_logs
				WHERE user_id = %d
				AND viewed = 0
				AND reward_value > 0
				AND status = 'VERIFIED'
				ORDER BY verified_at DESC
				LIMIT 50",
				$user_id
			),
			ARRAY_A
		);
		
		$result = [];
		$notification_ids = [];
		
		// 2. Build response data
		foreach ($notifications as $notification) {
			$mission_code = $notification['mission_code'];
			$reward_value = absint($notification['reward_value']);
			$notification_id = absint($notification['id']);
			
			$notification_ids[] = $notification_id;
			
			// Lấy tên nhiệm vụ với 3 mức ưu tiên
			$mission_title = null;
			
			// Ưu tiên 1: "Tên hiển thị" từ admin settings (do admin nhập)
			if (isset($saved_tasks[$mission_code]['title']) && trim($saved_tasks[$mission_code]['title']) !== '') {
				$mission_title = trim($saved_tasks[$mission_code]['title']);
			}
			
			// Ưu tiên 2: "Tên nhiệm vụ" mặc định từ config/missions.php
			if (empty($mission_title) && isset($default_missions[$mission_code])) {
				$mission_title = $default_missions[$mission_code];
			}
			
			// Ưu tiên 3: Fallback cuối cùng
			if (empty($mission_title)) {
				$mission_title = 'Nhiệm vụ';
			}
			
			$result[] = [
				'id' => $notification_id,
				'mission_code' => sanitize_text_field($mission_code),
				'mission_title' => sanitize_text_field($mission_title),
				'reward_spins' => $reward_value,
			];
		}
		
		// 3. Tự động cập nhật viewed = 1 cho tất cả thông báo vừa lấy
		if (!empty($notification_ids)) {
			$placeholders = implode(',', array_fill(0, count($notification_ids), '%d'));
			$query = $wpdb->prepare(
				"UPDATE {$prefix}user_mission_logs
				SET viewed = 1
				WHERE user_id = %d
				AND id IN ($placeholders)",
				array_merge([$user_id], $notification_ids)
			);
			
			$updated = $wpdb->query($query);
			
			if ($updated === false) {
				throw new Exception('Failed to update viewed status');
			}
		}
		
		// 4. Commit transaction
		$wpdb->query('COMMIT');
		
		// fake response để test API
//
//		return wg_json_response(200, [
//			'notifications' => [
//				[
//					'id' => 1,
//					'mission_code' => 'EKYC_COMPLETE',
//					'mission_title' => 'Hoàn thành EKYC',
//					'reward_spins' => 10,
//				],
//				[
//					'id' => 2,
//					'mission_code' => 'OPEN_BIDV',
//					'mission_title' => 'Mở tài khoản BIDV',
//					'reward_spins' => 20,
//				],
//			],
//			'total' => 2,
//		], __('Lấy danh sách thông báo thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
		return wg_json_response(200, [
			'notifications' => $result,
			'total' => count($result),
		], __('Lấy danh sách thông báo thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
		
	} catch (Exception $e) {
		// Rollback nếu có lỗi
		$wpdb->query('ROLLBACK');
		return wg_json_response(500, [
			'error' => $e->getMessage()
		], __('Lỗi khi lấy thông báo.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
}

/**
 * ============================================================================
 * CRONJOB: Tự động check nhiệm vụ TRADE_100M_VND_CODE
 * ============================================================================
 * - Chạy hàng ngày, mỗi 2 tiếng check 200 users để giảm tải
 * - Lưu cache đánh dấu users đã check trong ngày
 * - Cache tự động reset vào 00:00 ngày mới
 */

/**
 * Đăng ký schedule 2 tiếng
 */
add_filter('cron_schedules', 'game_trade_mission_cron_schedules');
// Product
// function game_trade_mission_cron_schedules($schedules) {
// 	$schedules['every_2_hours'] = [
// 		'interval' => 2 * HOUR_IN_SECONDS,
// 		'display'  => __('Mỗi 2 tiếng', WG_GAME_PLUGIN_TEXTDOMAIN)
// 	];
// 	return $schedules;
// }

// Dev
function game_trade_mission_cron_schedules($schedules) {
	$schedules['every_5_minutes'] = [
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __('Mỗi 5 phút', WG_GAME_PLUGIN_TEXTDOMAIN)
	];
	return $schedules;
}

/**
 * Kích hoạt cronjob khi activate plugin
 */
// add_action('init', 'game_trade_mission_schedule_cron');
register_activation_hook(GAME_BSC_PLUGIN_FILE, 'game_trade_mission_schedule_cron');
// Product
// function game_trade_mission_schedule_cron() {
// 	if (!wp_next_scheduled('game_trade_mission_check_hook')) {
// 		wp_schedule_event(time(), 'every_2_hours', 'game_trade_mission_check_hook');
// 	}
// }
// Dev
function game_trade_mission_schedule_cron() {
	$hook = 'game_trade_mission_check_hook';

	// Xóa cron cũ nếu có
	// if ($timestamp = wp_next_scheduled($hook)) {
	// 	wp_unschedule_event($timestamp, $hook);
	// }

	// Schedule lại mỗi 5 phút
	if (!wp_next_scheduled($hook)) {
		wp_schedule_event(time(), 'every_5_minutes', $hook);
	}
}

/**
 * Hook cronjob
 */
add_action('game_trade_mission_check_hook', 'game_trade_mission_auto_check');

/**
 * Hàm xử lý cronjob - check 200 users mỗi lần chạy
 */
function game_trade_mission_auto_check() {
	global $wpdb;
	$log_prefix = '[TRADE_MISSION_CRON]';
	
	// Kiểm tra thời gian game
	$dStart = get_option('game_bsc_start_date');
	$dEnd = get_option('game_bsc_end_date');
	
	if (empty($dStart) || empty($dEnd)) {
		return;
	}
	
	$now = new DateTime('now', TIMEZONE);
	$start_date = new DateTime($dStart, TIMEZONE);
	$end_date = new DateTime($dEnd . ' 23:59:59', TIMEZONE);
	
	if ($now < $start_date || $now > $end_date) {
		return;
	}
	
	// Lấy config nhiệm vụ
	$saved_tasks = get_option('game_bsc_tasks', []);
	if (!isset($saved_tasks[TRADE_100M_VND_CODE])) {
		game_trade_cron_log('EXIT: mission config not found');
		return;
	}
	
	$mission_config = $saved_tasks[TRADE_100M_VND_CODE];
	$reward_spins = isset($mission_config['reward_spins']) ? absint($mission_config['reward_spins']) : 0;
	$amount_required = isset($mission_config['amount_required']) ? absint($mission_config['amount_required']) : TRADE_100M_VND_DEFAULT_VALUE;
	$amount_required = $amount_required / 1000;
	
	// if ($reward_spins <= 0) {
	// 	return;
	// }
	
	// Lấy API config
	$api_base = getEndpointFromMissionCode(TRADE_100M_VND_CODE);
	if (empty($api_base['base_url']) || empty($api_base['end_point'])) {
		game_trade_cron_log('EXIT: API config invalid');
		return;
	}
	
	$apiBaseUrl = $api_base['base_url'];
	$endpoint = $api_base['end_point'];
	
	// Ngày hôm qua (check giao dịch hôm qua)
	$todayObj = new DateTime('now', TIMEZONE);
	$today = $todayObj->format('Y-m-d');
	$dayOfWeek = (int) $todayObj->format('N');

	// Không chạy cron vào Thứ 7 & Chủ nhật
	if ($dayOfWeek === 6 || $dayOfWeek === 7) {
		return;
	}

	// Xác định ngày check giao dịch
	if ($dayOfWeek === 1) {
		// Thứ 2 → check giao dịch Thứ 6 tuần trước
		$check_date = (clone $todayObj)->modify('-3 days')->format('Y-m-d');
	} else {
		// Thứ 3 → Thứ 6 → check ngày hôm qua
		$check_date = (clone $todayObj)->modify('-1 day')->format('Y-m-d');
	}
	
	// Cache key cho ngày hôm nay
	$cache_key = 'game_trade_checked_' . $today;
	$checked_users = get_transient($cache_key);
	if (!is_array($checked_users)) {
		$checked_users = [];
	}
	
	// Lấy tất cả users active
	$prefix = $wpdb->prefix . 'game_';
	$all_users = $wpdb->get_results(
		"SELECT id, external_user_id
		FROM {$prefix}users
		WHERE status = 1
		ORDER BY id ASC",
		ARRAY_A
	);
	
	if (empty($all_users)) {
		return;
	}
	
	// Lọc users chưa check hôm nay
	$users_to_check = [];
	foreach ($all_users as $user) {
		$user_id = absint($user['id']);
		
		// Skip nếu đã check hôm nay
		if (in_array($user_id, $checked_users)) {
			continue;
		}
		
		// Skip nếu đã hoàn thành nhiệm vụ hôm nay
		$completed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}user_mission_logs
				WHERE user_id = %d
				AND mission_code = %s
				AND mission_date = %s
				LIMIT 1",
				$user_id,
				TRADE_100M_VND_CODE,
				$today
			)
		);
		
		if ($completed) {
			$checked_users[] = $user_id;
			continue;
		}
		
		$users_to_check[] = $user;
	}
	
	// Giới hạn 200 users mỗi lần
	$users_to_check = array_slice($users_to_check, 0, 200);
	
	$success_count = 0;
	$fail_count = 0;
	
	// Check từng user
	foreach ($users_to_check as $user) {
		$user_id = absint($user['id']);
		$custodycd = $user['external_user_id'];
		// Đánh dấu đã check
		$checked_users[] = $user_id;
		
		// Data cho API
		$data = [
			'custodycd' => $custodycd,
			'txdate' => $check_date,
			'transactionvalue' => $amount_required
		];
		
		// Gọi API
		$response = callApiGame($apiBaseUrl . $endpoint, http_build_query($data, '', '&', PHP_QUERY_RFC3986), 'POST');
		
		if (!$response || !isset($response->s) || !isset($response->d)) {
			$fail_count++;
			game_trade_cron_log("API ERROR user_id={$user_id}");
			continue;
		}
		game_trade_cron_log("API RESPONSE user_id={$user_id}: " . wp_json_encode($response));
		// Nếu hoàn thành
		if ($response->s === 'ok') {
			$actual_reward_spins = absint($response->d);
			if ($actual_reward_spins > 0) {
				$wpdb->query('START TRANSACTION');
				
				try {
					// Lưu mission log
					$wpdb->insert(
						"{$prefix}user_mission_logs",
						[
							'user_id'       => $user_id,
							'mission_code'  => TRADE_100M_VND_CODE,
							'mission_date'  => $today,
							'reward_type'   => 'PLAY_CREDIT',
							'reward_value'  => $actual_reward_spins,
							'status'        => 'VERIFIED',
							'verified_at'   => game_now(),
							'api_status'    => 1,
							'api_payload'   => wp_json_encode($response)
						],
						['%d','%s','%s','%s','%d','%s','%s','%d','%s']
					);
					
					$id_mission_log = $wpdb->insert_id;
					
					// Cập nhật balance
					$table_balance = $wpdb->prefix . 'game_play_credit_balances';
					
					// Ensure balance record exists
					$balance_exists = $wpdb->get_var($wpdb->prepare(
						"SELECT user_id FROM $table_balance WHERE user_id = %d",
						$user_id
					));
					
					if (!$balance_exists) {
						$wpdb->insert($table_balance, ['user_id' => $user_id, 'balance' => 0], ['%d','%d']);
					}
					
					$wpdb->query($wpdb->prepare(
						"UPDATE $table_balance SET balance = balance + %d WHERE user_id = %d",
						$actual_reward_spins,
						$user_id
					));
					
					// Lưu ledger
					$wpdb->insert(
						$wpdb->prefix . 'game_play_credit_ledger',
						[
							'user_id'   => $user_id,
							'delta'     => $actual_reward_spins,
							'ref_type'  => 'MISSION',
							'ref_id'    => $id_mission_log,
							'created_at'=> game_now(),
						],
						['%d','%d','%s','%d','%s']
					);
					
					$wpdb->query('COMMIT');
					$success_count++;
					
				} catch (Exception $e) {
					$wpdb->query('ROLLBACK');
					$fail_count++;
				}
			}
		}
		
		// Sleep 100ms giữa các request
		usleep(100000);
	}
	game_trade_cron_log("CRON FINISHED success={$success_count}, fail={$fail_count}");
	// Lưu cache đến hết ngày hôm nay (tự động reset 00:00)
	$now = new DateTime('now', TIMEZONE);
	$end_of_day = new DateTime($today . ' 23:59:59', TIMEZONE);
	$seconds_until_midnight = $end_of_day->getTimestamp() - $now->getTimestamp();
	
	if ($seconds_until_midnight > 0) {
		set_transient($cache_key, array_unique($checked_users), $seconds_until_midnight);
	}
}
if (!function_exists('game_trade_cron_log')) {
	function game_trade_cron_log($message) {
		$time = current_time('Y-m-d H:i:s');
		error_log("[TRADE_CRON][$time] " . $message);
	}
}