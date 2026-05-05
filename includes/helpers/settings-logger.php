<?php
if (!defined('ABSPATH')) exit;

/**
 * Trang xem logs chỉnh sửa settings
 */
function game_bsc_settings_logs_page() {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$paged = max(1, intval($_GET['paged'] ?? 1));
	$per_page = 20;
	$start_date = sanitize_text_field($_GET['start_date'] ?? '');
	$end_date = sanitize_text_field($_GET['end_date'] ?? '');
	$filter_key = sanitize_text_field($_GET['filter_key'] ?? '');
	
	// Lấy dữ liệu logs
	$logs_data = game_bsc_get_all_settings_logs($paged, $per_page, $start_date, $end_date, $filter_key);
	$logs = $logs_data['logs'];
	$total = $logs_data['total'];
	$total_pages = $logs_data['total_pages'];
	
	?>
	<div class="wrap">
		<h1><?php _e('Lịch sử chỉnh sửa cài đặt', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h1>
		
		<!-- FILTER FORM -->
		<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
			<form method="get" action="">
				<input type="hidden" name="page" value="game-bsc-settings-logs">
				
				<div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 10px; margin-bottom: 10px; align-items: end;">
					<div>
						<label style="display: block; margin-bottom: 5px; font-weight: bold;">
							<?php _e('Từ ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
						</label>
						<input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
					</div>
					
					<div>
						<label style="display: block; margin-bottom: 5px; font-weight: bold;">
							<?php _e('Đến ngày', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
						</label>
						<input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
					</div>
					
					<div>
						<label style="display: block; margin-bottom: 5px; font-weight: bold;">
							<?php _e('Cài đặt', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
						</label>
						<select name="filter_key" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
							<option value="">-- <?php _e('Tất cả', WG_GAME_PLUGIN_TEXTDOMAIN); ?> --</option>
							<option value="game_bsc_dates" <?php selected($filter_key, 'game_bsc_dates'); ?>>
								<?php _e('Ngày bắt đầu/kết thúc', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
							</option>
							<option value="game_bsc_stages" <?php selected($filter_key, 'game_bsc_stages'); ?>>
								<?php _e('Chặng game', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
							</option>
							<option value="game_bsc_max_wrong_answers" <?php selected($filter_key, 'game_bsc_max_wrong_answers'); ?>>
								<?php _e('Số lần trả lời sai', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
							</option>
							<option value="game_bsc_tasks" <?php selected($filter_key, 'game_bsc_tasks'); ?>>
								<?php _e('Nhiệm vụ', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
							</option>
							<option value="game_bsc_rules" <?php selected($filter_key, 'game_bsc_rules'); ?>>
								<?php _e('Thể lệ', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
							</option>
							<option value="game_bsc_rewards_descriptions" <?php selected($filter_key, 'game_bsc_rewards_descriptions'); ?>>
								<?php _e('Cơ chế đổi quà', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
							</option>
							<?php
							// Lấy danh sách artifact logs
							$artifact_logs = $wpdb->get_col(
								"SELECT DISTINCT setting_key FROM {$prefix}settings_logs
                                 WHERE setting_key LIKE 'game_bsc_artifact_%'
                                 ORDER BY setting_key"
							);
							foreach ($artifact_logs as $key) {
								$artifact_id = str_replace('game_bsc_artifact_', '', $key);
								if (is_numeric($artifact_id)) {
									$artifact_name = $wpdb->get_var(
										$wpdb->prepare(
											"SELECT name FROM {$prefix}artifacts WHERE id = %d",
											$artifact_id
										)
									);
									if ($artifact_name) {
										?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($filter_key, $key); ?>>
											<?php echo sprintf(__('Hiện vật: %s', WG_GAME_PLUGIN_TEXTDOMAIN), esc_html($artifact_name)); ?>
										</option>
										<?php
									}
								}
							}
							?>
						</select>
					</div>
					
					<div></div>
					
					<div>
						<?php submit_button(__('Lọc', WG_GAME_PLUGIN_TEXTDOMAIN), 'primary', 'submit', true, ['style' => 'width: 100%;']); ?>
					</div>
				</div>
			</form>
		</div>
		
		<!-- STATS -->
		<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2271b1;">
			<strong><?php _e('Tổng số log:', WG_GAME_PLUGIN_TEXTDOMAIN); ?></strong> <?php echo $total; ?> |
			<strong><?php _e('Trang:', WG_GAME_PLUGIN_TEXTDOMAIN); ?></strong> <?php echo $paged; ?> / <?php echo $total_pages; ?>
		</div>
		
		<!-- LOGS TABLE -->
		<table class="widefat striped">
			<thead>
			<tr style="background: #f1f1f1;">
				<th style="width: 80px;"><?php _e('ID', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
				<th><?php _e('Người chỉnh sửa', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
				<th><?php _e('Cài đặt', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
				<th><?php _e('Hành động', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
				<th><?php _e('Chi tiết thay đổi', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
				<th style="width: 150px;"><?php _e('Thời gian', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
				<th style="width: 150px;"><?php _e('IP Address', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if (!empty($logs)): ?>
				<?php foreach ($logs as $log): ?>
					<tr>
						<td><?php echo (int)$log['id']; ?></td>
						<td>
							<?php
							$wp_user = get_user_by('id', $log['user_id']);
							if ($wp_user) {
								echo esc_html($wp_user->display_name);
								echo '<br><small style="color: #666;">' . esc_html($wp_user->user_email) . '</small>';
							} else {
								echo 'N/A';
							}
							?>
						</td>
						<td>
							<code style="background: #f5f5f5; padding: 5px 8px; border-radius: 3px; font-size: 12px;">
								<?php echo esc_html(game_bsc_format_setting_key($log['setting_key'])); ?>
							</code>
						</td>
						<td>
                                <span style="
	                                display: inline-block;
	                                padding: 4px 10px;
	                                border-radius: 3px;
	                                font-weight: bold;
	                                font-size: 12px;
	                                color: white;
                                <?php
                                if ($log['action'] === 'create') {
	                                echo 'background: #28a745;';
                                } elseif ($log['action'] === 'update') {
	                                echo 'background: #2271b1;';
                                } elseif ($log['action'] === 'delete') {
	                                echo 'background: #dc3545;';
                                }
                                ?>
	                                ">
                                    <?php echo strtoupper(esc_html($log['action'])); ?>
                                </span>
						</td>
						<td>
							<?php if (!empty($log['changed_fields'])): ?>
								<details style="cursor: pointer;">
									<summary style="padding: 8px; background: #f9f9f9; border-radius: 3px; user-select: none;">
										📋 <?php _e('Xem chi tiết', WG_GAME_PLUGIN_TEXTDOMAIN); ?>
									</summary>
									<div style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 3px; max-height: 300px; overflow: auto;">
										<?php game_bsc_render_log_changes($log); ?>
									</div>
								</details>
							<?php else: ?>
								<em><?php _e('Không có thay đổi', WG_GAME_PLUGIN_TEXTDOMAIN); ?></em>
							<?php endif; ?>
						</td>
						<td>
							<small><?php echo esc_html((new DateTime($log['created_at'], TIMEZONE))->format('d/m/Y H:i:s')); ?></small>
						</td>
						<td>
							<small><?php echo esc_html($log['ip_address'] ?? 'N/A'); ?></small>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else: ?>
				<tr>
					<td colspan="7" style="text-align: center; padding: 30px;">
						<em><?php _e('Không có dữ liệu log', WG_GAME_PLUGIN_TEXTDOMAIN); ?></em>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		
		<!-- PAGINATION -->
		<?php if ($total_pages > 1): ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					$page_links = paginate_links([
						'base' => add_query_arg('paged', '%#%'),
						'format' => '',
						'prev_text' => __('&laquo; Trước'),
						'next_text' => __('Sau &raquo;'),
						'total' => $total_pages,
						'current' => $paged,
						'type' => 'array'
					]);
					if ($page_links) {
						echo implode(' ', $page_links);
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	
	</div>
	
	<style>
        .wrap details {
            margin-bottom: 5px;
        }
        .wrap details summary:hover {
            background: #efefef !important;
        }
        .log-diff {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.6;
            margin: 5px 0;
        }
        .log-diff-old {
            color: #d32f2f;
            background: #ffebee;
            padding: 5px;
            border-radius: 3px;
            margin: 3px 0;
        }
        .log-diff-new {
            color: #388e3c;
            background: #e8f5e9;
            padding: 5px;
            border-radius: 3px;
            margin: 3px 0;
        }
        .log-diff-label {
            font-weight: bold;
            color: #555;
            margin-top: 8px;
        }
	</style>
	<?php
}

/**
 * Format setting key thành dạng dễ đọc
 */
function game_bsc_format_setting_key($key) {
	$mapping = [
		'game_bsc_dates' => 'Ngày bắt đầu/kết thúc',
		'game_bsc_stages' => 'Chặng game',
		'game_bsc_max_wrong_answers' => 'Số lần trả lời sai',
		'game_bsc_tasks' => 'Nhiệm vụ',
		'game_bsc_rules' => 'Thể lệ',
		'game_bsc_rewards_descriptions' => 'Cơ chế đổi quà',
		'game_bsc_post_game_badges' => 'Huy hiệu',
		'game_bsc_post_game_question' => 'Câu hỏi',
		'game_bsc_post_game_vouchers' => 'Voucher',
		'game_bsc_gotit_sync_categories' => 'Got It: Đồng bộ danh mục',
		'game_bsc_gotit_sync_vouchers' => 'Got It: Đồng bộ voucher',
		'game_bsc_voucher_excel_export' => 'Voucher: Xuất Excel/CSV',
		'game_bsc_voucher_excel_import' => 'Voucher: Nhập Excel/CSV',
	];
	
	if (isset($mapping[$key])) {
		return $mapping[$key];
	}
	
	if (strpos($key, 'game_bsc_artifact_') === 0) {
		$artifact_id = str_replace('game_bsc_artifact_', '', $key);
		global $wpdb;
		$prefix = $wpdb->prefix . 'game_';
		$artifact_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$prefix}artifacts WHERE id = %d",
				$artifact_id
			)
		);
		if ($artifact_name) {
			return 'Hiện vật: ' . $artifact_name;
		}
		return 'Hiện vật ID: ' . $artifact_id;
	}
	
	return $key;
}

/**
 * Render chi tiết thay đổi
 */
function game_bsc_render_log_changes($log) {
	$changed_fields = $log['changed_fields'] ?? [];
	
	if (empty($changed_fields)) {
		echo '<em>Không có chi tiết thay đổi</em>';
		return;
	}
	
	?>
	<div class="log-diff">
		<?php if (!empty($changed_fields['added'])): ?>
			<div class="log-diff-label">➕ Thêm mới:</div>
			<?php foreach ($changed_fields['added'] as $field => $value): ?>
				<div class="log-diff-new">
					<strong><?php echo esc_html($field); ?>:</strong>
					<?php echo game_bsc_format_value($value); ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
		
		<?php if (!empty($changed_fields['removed'])): ?>
			<div class="log-diff-label">➖ Xóa:</div>
			<?php foreach ($changed_fields['removed'] as $field => $value): ?>
				<div class="log-diff-old">
					<strong><?php echo esc_html($field); ?>:</strong>
					<?php echo game_bsc_format_value($value); ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
		
		<?php if (!empty($changed_fields['modified'])): ?>
			<div class="log-diff-label">✏️ Thay đổi:</div>
			<?php foreach ($changed_fields['modified'] as $field => $change): ?>
				<div style="margin: 8px 0; padding: 8px; background: #fff8e1; border-left: 3px solid #fbc02d; border-radius: 3px;">
					<strong><?php echo esc_html($field); ?></strong>
					<div class="log-diff-old" style="margin-top: 3px;">
						Trước: <?php echo game_bsc_format_value($change['old']); ?>
					</div>
					<div class="log-diff-new" style="margin-top: 3px;">
						Sau: <?php echo game_bsc_format_value($change['new']); ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Format giá trị để hiển thị
 */
function game_bsc_format_value($value) {
	if (is_array($value)) {
		return '<pre style="margin: 3px 0; padding: 5px; background: #f9f9f9; border-radius: 3px; overflow: auto; max-height: 150px;">'
			. esc_html(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
			. '</pre>';
	}
	
	if (is_string($value) && strlen($value) > 50) {
		return '<code title="' . esc_attr($value) . '">' . esc_html(substr($value, 0, 50)) . '...</code>';
	}
	
	if (is_bool($value)) {
		return $value ? '<strong style="color: green;">true</strong>' : '<strong style="color: red;">false</strong>';
	}
	
	if (is_null($value)) {
		return '<em style="color: #999;">null</em>';
	}
	
	if (is_numeric($value)) {
		return '<code>' . esc_html($value) . '</code>';
	}
	
	return '<code>' . esc_html($value) . '</code>';
}

/**
 * Lấy lịch sử logs của tất cả settings
 */
function game_bsc_get_all_settings_logs($page = 1, $per_page = 50, $start_date = null, $end_date = null, $filter_key = null) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$where = '1=1';
	$params = [];
	
	// Filter theo ngày nếu có
	if (!empty($start_date)) {
		$where .= ' AND DATE(created_at) >= %s';
		$params[] = $start_date;
	}
	
	if (!empty($end_date)) {
		$where .= ' AND DATE(created_at) <= %s';
		$params[] = $end_date;
	}
	
	// Filter theo setting_key nếu có
	if (!empty($filter_key)) {
		$where .= ' AND setting_key = %s';
		$params[] = $filter_key;
	}
	
	$offset = ($page - 1) * $per_page;
	
	// Đếm tổng
	if (!empty($params)) {
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}settings_logs WHERE {$where}",
				...$params
			)
		);
	} else {
		$total = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}settings_logs WHERE {$where}"
		);
	}
	
	// Lấy dữ liệu
	if (!empty($params)) {
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}settings_logs
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
				array_merge($params, [$per_page, $offset])
			),
			ARRAY_A
		);
	} else {
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}settings_logs
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}
	
	// Format dữ liệu
	if (!empty($logs)) {
		$logs = array_map(function($log) {
			$log['changed_fields'] = !empty($log['changed_fields'])
				? json_decode($log['changed_fields'], true)
				: [];
			$log['old_value'] = !empty($log['old_value'])
				? maybe_unserialize($log['old_value'])
				: null;
			$log['new_value'] = !empty($log['new_value'])
				? maybe_unserialize($log['new_value'])
				: null;
			$log['user'] = get_user_by('id', $log['user_id']);
			return $log;
		}, $logs);
	}
	
	return [
		'logs' => $logs,
		'total' => (int)$total,
		'page' => $page,
		'per_page' => $per_page,
		'total_pages' => ceil((int)$total / $per_page)
	];
}


if (!defined('ABSPATH')) exit;

/**
 * Log thay đổi settings vào bảng wp_game_settings_logs
 */
function game_bsc_log_settings_change($setting_key, $old_value, $new_value, $action = 'update')
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$user_id = get_current_user_id();
	if (!$user_id) {
		return false;
	}
	
	$ip_address = game_bsc_get_client_ip();
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
	
	// Tính toán changed_fields (so sánh old vs new)
	$changed_fields = game_bsc_compute_changed_fields($old_value, $new_value);
	
	$result = $wpdb->insert(
		$prefix . 'settings_logs',
		[
			'user_id' => $user_id,
			'setting_key' => $setting_key,
			'old_value' => maybe_serialize($old_value),
			'new_value' => maybe_serialize($new_value),
			'action' => $action,
			'changed_fields' => json_encode($changed_fields, JSON_UNESCAPED_UNICODE),
			'ip_address' => $ip_address,
			'user_agent' => $user_agent,
			'created_at' => game_now(),
		],
		['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
	);
	
	return $result;
}

/**
 * Lấy IP address của client
 */
function game_bsc_get_client_ip()
{
	if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
		return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ips = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']));
		return trim($ips[0]);
	} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
		return sanitize_text_field($_SERVER['REMOTE_ADDR']);
	}
	return '';
}

/**
 * Tính toán changed_fields (chi tiết thay đổi)
 * Trả về mảng với keys: added, removed, modified
 */
function game_bsc_compute_changed_fields($old_value, $new_value)
{
	$changed = [
		'added' => [],
		'removed' => [],
		'modified' => []
	];
	
	// Nếu cả 2 không phải array, so sánh trực tiếp
	if (!is_array($old_value) && !is_array($new_value)) {
		if ($old_value !== $new_value) {
			$changed['modified']['value'] = [
				'old' => $old_value,
				'new' => $new_value
			];
		}
		return $changed;
	}
	
	// Convert thành array nếu cần
	$old_arr = is_array($old_value) ? $old_value : [$old_value];
	$new_arr = is_array($new_value) ? $new_value : [$new_value];
	
	// Tìm added (key có trong new nhưng không có trong old)
	foreach ($new_arr as $key => $val) {
		if (!isset($old_arr[$key])) {
			$changed['added'][$key] = $val;
		}
	}
	
	// Tìm removed (key có trong old nhưng không có trong new)
	foreach ($old_arr as $key => $val) {
		if (!isset($new_arr[$key])) {
			$changed['removed'][$key] = $val;
		}
	}
	
	// Tìm modified (key có cả 2 nhưng value khác)
	foreach ($old_arr as $key => $val) {
		if (isset($new_arr[$key]) && $new_arr[$key] !== $val) {
			$changed['modified'][$key] = [
				'old' => $val,
				'new' => $new_arr[$key]
			];
		}
	}
	
	// Xóa các key rỗng
	foreach ($changed as &$arr) {
		if (empty($arr)) {
			unset($arr);
		}
	}
	
	return $changed;
}


if (!defined('ABSPATH')) exit;

/**
 * Lấy dữ liệu logs từ bảng wp_game_settings_logs
 * để hiển thị trong dashboard
 */

function game_bsc_get_dashboard_logs_data($paged = 1, $per_page = 10)
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$offset = ($paged - 1) * $per_page;
	
	// Đếm tổng logs
	$total = $wpdb->get_var(
		"SELECT COUNT(*) FROM {$prefix}settings_logs"
	);
	
	// Lấy logs
	$logs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT sl.*, u.display_name, u.user_email, u.user_login
            FROM {$prefix}settings_logs sl
            LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
            ORDER BY sl.created_at DESC
            LIMIT %d OFFSET %d",
			$per_page,
			$offset
		),
		ARRAY_A
	);
	
	// Format lại dữ liệu
	$formatted_logs = [];
	if (!empty($logs)) {
		foreach ($logs as $log) {
			$changed_fields = !empty($log['changed_fields'])
				? json_decode($log['changed_fields'], true)
				: [];
			
			$user_name = !empty($log['display_name']) ? $log['display_name'] : ($log['user_login'] ?? 'N/A');
			$user_email = $log['user_email'] ?? 'N/A';
			
			$formatted_logs[] = [
				'id' => (int)$log['id'],
				'user_id' => (int)$log['user_id'],
				'user_name' => sanitize_text_field($user_name),
				'user_email' => sanitize_email($user_email),
				'setting_key' => $log['setting_key'],
				'action' => strtoupper($log['action']),
				'action_label' => game_bsc_get_action_label($log['action']),
				'setting_label' => game_bsc_get_setting_label($log['setting_key']),
				'changed_fields' => $changed_fields,
				'ip_address' => $log['ip_address'] ?? 'N/A',
				'created_at' => $log['created_at'],
				'created_at_formatted' => (new DateTime($log['created_at'], TIMEZONE))->format('d/m/Y H:i'),
			];
		}
	}
	
	$total_pages = ceil($total / $per_page);
	
	return [
		'logs' => $formatted_logs,
		'total' => (int)$total,
		'paged' => $paged,
		'per_page' => $per_page,
		'total_pages' => $total_pages
	];
}

/**
 * Normalize datetime-local input to MySQL datetime.
 */
function game_bsc_normalize_datetime_local($value)
{
	$value = sanitize_text_field(trim((string) $value));
	if ($value === '') {
		return '';
	}

	$formats = ['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'];
	foreach ($formats as $format) {
		$date = DateTime::createFromFormat($format, $value, TIMEZONE);
		if ($date instanceof DateTime) {
			return $date->format('Y-m-d H:i:s');
		}
	}

	return $value;
}

/**
 * Lấy dữ liệu logs với filter tìm kiếm
 */
function game_bsc_get_dashboard_logs_with_search($paged = 1, $per_page = 10, $search_query = '', $date_from = '', $date_to = '')
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';
	
	$search_query = sanitize_text_field(trim($search_query));
	$date_from = game_bsc_normalize_datetime_local($date_from);
	$date_to = game_bsc_normalize_datetime_local($date_to);
	$offset = ($paged - 1) * $per_page;
	
	$where_clauses = ['1=1'];
	$params = [];
	
	// Nếu có tìm kiếm, tìm theo tên người dùng hoặc email
	if (!empty($search_query)) {
		$where_clauses[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR CAST(sl.user_id AS CHAR) = %s)";
		$search_like = '%' . $wpdb->esc_like($search_query) . '%';
		$params[] = $search_like;
		$params[] = $search_like;
		$params[] = $search_query;
	}

	if ($date_from !== '') {
		$where_clauses[] = 'sl.created_at >= %s';
		$params[] = $date_from;
	}

	if ($date_to !== '') {
		$where_clauses[] = 'sl.created_at <= %s';
		$params[] = $date_to;
	}
	
	$where = implode(' AND ', $where_clauses);
	
	// Đếm tổng
	if (empty($params)) {
		$total = $wpdb->get_var(
			"SELECT COUNT(DISTINCT sl.id)
            FROM {$prefix}settings_logs sl
            LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
            WHERE {$where}"
		);
	} else {
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT sl.id)
                FROM {$prefix}settings_logs sl
                LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
                WHERE {$where}",
				...$params
			)
		);
	}
	
	// Lấy dữ liệu
	if (empty($params)) {
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sl.*, u.display_name, u.user_email, u.user_login
                FROM {$prefix}settings_logs sl
                LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
                WHERE {$where}
                ORDER BY sl.created_at DESC
                LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);
	} else {
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sl.*, u.display_name, u.user_email, u.user_login
                FROM {$prefix}settings_logs sl
                LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
                WHERE {$where}
                ORDER BY sl.created_at DESC
                LIMIT %d OFFSET %d",
				array_merge($params, [$per_page, $offset])
			),
			ARRAY_A
		);
	}
	
	// Format lại dữ liệu
	$formatted_logs = [];
	if (!empty($logs)) {
		foreach ($logs as $log) {
			$changed_fields = !empty($log['changed_fields'])
				? json_decode($log['changed_fields'], true)
				: [];
			
			$user_name = !empty($log['display_name']) ? $log['display_name'] : ($log['user_login'] ?? 'N/A');
			$user_email = $log['user_email'] ?? 'N/A';
			
			$formatted_logs[] = [
				'id' => (int)$log['id'],
				'user_id' => (int)$log['user_id'],
				'user_name' => sanitize_text_field($user_name),
				'user_email' => sanitize_email($user_email),
				'setting_key' => $log['setting_key'],
				'action' => strtoupper($log['action']),
				'action_label' => game_bsc_get_action_label($log['action']),
				'setting_label' => game_bsc_get_setting_label($log['setting_key']),
				'changed_fields' => $changed_fields,
				'ip_address' => $log['ip_address'] ?? 'N/A',
				'created_at' => $log['created_at'],
				'created_at_formatted' => (new DateTime($log['created_at'], TIMEZONE))->format('d/m/Y H:i'),
			];
		}
	}
	
	$total_pages = ceil($total / $per_page);
	
	return [
		'logs' => $formatted_logs,
		'total' => (int)$total,
		'paged' => $paged,
		'per_page' => $per_page,
		'total_pages' => $total_pages,
		'search_query' => $search_query
	];
}

/**
 * Lấy lịch sử upload/import điểm voucher từ bảng wp_game_voucher_points_import_history.
 */
function game_bsc_get_voucher_excel_history_data($paged = 1, $per_page = 10, $search_query = '', $mode = 'all', $date_from = '', $date_to = '')
{
	global $wpdb;

	$history_table = $wpdb->prefix . 'game_voucher_points_import_history';
	$search_query = sanitize_text_field(trim((string) $search_query));
	$mode = sanitize_key((string) $mode);
	$date_from = game_bsc_normalize_datetime_local($date_from);
	$date_to = game_bsc_normalize_datetime_local($date_to);
	$paged = max(1, (int) $paged);
	$per_page = max(1, (int) $per_page);
	$offset = ($paged - 1) * $per_page;

	$where_clauses = ['1=1'];
	$params = [];

	if ($mode === 'apply' || $mode === 'dry-run') {
		$where_clauses[] = 'ih.mode = %s';
		$params[] = $mode;
	}

	if ($search_query !== '') {
		$search_like = '%' . $wpdb->esc_like($search_query) . '%';
		$where_clauses[] = '(ih.file_name LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s OR CAST(ih.file_author AS CHAR) = %s)';
		$params[] = $search_like;
		$params[] = $search_like;
		$params[] = $search_like;
		$params[] = $search_query;
	}

	if ($date_from !== '') {
		$where_clauses[] = 'ih.uploaded_at >= %s';
		$params[] = $date_from;
	}

	if ($date_to !== '') {
		$where_clauses[] = 'ih.uploaded_at <= %s';
		$params[] = $date_to;
	}

	$where_sql = implode(' AND ', $where_clauses);

	if (empty($params)) {
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$history_table} ih
			 LEFT JOIN {$wpdb->users} u ON ih.file_author = u.ID
			 WHERE {$where_sql}"
		);
	} else {
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$history_table} ih
				 LEFT JOIN {$wpdb->users} u ON ih.file_author = u.ID
				 WHERE {$where_sql}",
				...$params
			)
		);
	}

	$query_params = array_merge($params, [$per_page, $offset]);
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ih.*, u.display_name, u.user_email, u.user_login
			 FROM {$history_table} ih
			 LEFT JOIN {$wpdb->users} u ON ih.file_author = u.ID
			 WHERE {$where_sql}
			 ORDER BY ih.uploaded_at DESC, ih.id DESC
			 LIMIT %d OFFSET %d",
			...$query_params
		),
		ARRAY_A
	);

	$formatted_rows = [];
	foreach ((array) $rows as $row) {
		$summary_data = [];
		if (!empty($row['summary_json'])) {
			$decoded = json_decode((string) $row['summary_json'], true);
			if (is_array($decoded)) {
				$summary_data = $decoded;
			}
		}

		$user_name = !empty($row['display_name']) ? (string) $row['display_name'] : ((string) ($row['user_login'] ?? 'N/A'));
		$user_email = (string) ($row['user_email'] ?? 'N/A');
		$uploaded_at = (string) ($row['uploaded_at'] ?? '');

		$formatted_rows[] = [
			'id' => (int) ($row['id'] ?? 0),
			'file_name' => (string) ($row['file_name'] ?? ''),
			'file_url' => (string) ($row['file_url'] ?? ''),
			'file_author' => (int) ($row['file_author'] ?? 0),
			'user_name' => sanitize_text_field($user_name),
			'user_email' => sanitize_email($user_email),
			'mode' => (string) ($row['mode'] ?? 'dry-run'),
			'mode_label' => ((string) ($row['mode'] ?? '') === 'apply') ? 'Áp dụng' : 'Chạy thử',
			'total_rows' => (int) ($row['total_rows'] ?? 0),
			'updated_rows' => (int) ($row['updated_rows'] ?? 0),
			'skipped_rows' => (int) ($row['skipped_rows'] ?? 0),
			'conflict_rows' => (int) ($row['conflict_rows'] ?? 0),
			'error_rows' => (int) ($row['error_rows'] ?? 0),
			'summary_data' => $summary_data,
			'uploaded_at' => $uploaded_at,
			'uploaded_at_formatted' => $uploaded_at !== '' ? (new DateTime($uploaded_at, TIMEZONE))->format('d/m/Y H:i') : 'N/A',
		];
	}

	$total_pages = max(1, (int) ceil($total / $per_page));

	return [
		'rows' => $formatted_rows,
		'total' => $total,
		'paged' => $paged,
		'per_page' => $per_page,
		'total_pages' => $total_pages,
		'search_query' => $search_query,
		'mode' => $mode,
	];
}

/**
 * Trích xuất payload gọn từ log import/export voucher excel.
 */
function game_bsc_format_voucher_excel_log_payload($new_value)
{
	$payload = maybe_unserialize($new_value);
	if (!is_array($payload)) {
		return is_scalar($payload) ? (string) $payload : '';
	}

	$keys = [
		'mode',
		'file_ext',
		'total_rows',
		'updated_rows',
		'error_rows',
		'status',
		'xlsx_supported',
		'triggered_at',
	];

	$compact = [];
	foreach ($keys as $key) {
		if (array_key_exists($key, $payload)) {
			$compact[$key] = $payload[$key];
		}
	}

	if (empty($compact)) {
		$compact = $payload;
	}

	return wp_json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Lấy log liên quan đến import/export voucher excel trong settings_logs.
 */
function game_bsc_get_voucher_excel_related_logs($paged = 1, $per_page = 10, $search_query = '', $date_from = '', $date_to = '')
{
	global $wpdb;
	$prefix = $wpdb->prefix . 'game_';

	$search_query = sanitize_text_field(trim((string) $search_query));
	$date_from = game_bsc_normalize_datetime_local($date_from);
	$date_to = game_bsc_normalize_datetime_local($date_to);
	$paged = max(1, (int) $paged);
	$per_page = max(1, (int) $per_page);
	$offset = ($paged - 1) * $per_page;

	$where_clauses = [
		"sl.setting_key IN ('game_bsc_voucher_excel_import', 'game_bsc_voucher_excel_export')",
	];
	$params = [];

	if ($search_query !== '') {
		$search_like = '%' . $wpdb->esc_like($search_query) . '%';
		$where_clauses[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR CAST(sl.user_id AS CHAR) = %s)';
		$params[] = $search_like;
		$params[] = $search_like;
		$params[] = $search_query;
	}

	if ($date_from !== '') {
		$where_clauses[] = 'sl.created_at >= %s';
		$params[] = $date_from;
	}

	if ($date_to !== '') {
		$where_clauses[] = 'sl.created_at <= %s';
		$params[] = $date_to;
	}

	$where_sql = implode(' AND ', $where_clauses);

	if (empty($params)) {
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$prefix}settings_logs sl
			 LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
			 WHERE {$where_sql}"
		);
	} else {
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$prefix}settings_logs sl
				 LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
				 WHERE {$where_sql}",
				...$params
			)
		);
	}

	$query_params = array_merge($params, [$per_page, $offset]);
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT sl.*, u.display_name, u.user_email, u.user_login
			 FROM {$prefix}settings_logs sl
			 LEFT JOIN {$wpdb->users} u ON sl.user_id = u.ID
			 WHERE {$where_sql}
			 ORDER BY sl.created_at DESC, sl.id DESC
			 LIMIT %d OFFSET %d",
			...$query_params
		),
		ARRAY_A
	);

	$formatted_rows = [];
	foreach ((array) $rows as $row) {
		$user_name = !empty($row['display_name']) ? (string) $row['display_name'] : ((string) ($row['user_login'] ?? 'N/A'));
		$user_email = (string) ($row['user_email'] ?? 'N/A');
		$created_at = (string) ($row['created_at'] ?? '');

		$formatted_rows[] = [
			'id' => (int) ($row['id'] ?? 0),
			'user_id' => (int) ($row['user_id'] ?? 0),
			'user_name' => sanitize_text_field($user_name),
			'user_email' => sanitize_email($user_email),
			'setting_key' => (string) ($row['setting_key'] ?? ''),
			'setting_label' => game_bsc_get_setting_label((string) ($row['setting_key'] ?? '')),
			'action' => strtoupper((string) ($row['action'] ?? 'update')),
			'action_label' => game_bsc_get_action_label((string) ($row['action'] ?? 'update')),
			'payload' => game_bsc_format_voucher_excel_log_payload($row['new_value'] ?? ''),
			'ip_address' => (string) ($row['ip_address'] ?? ''),
			'created_at' => $created_at,
			'created_at_formatted' => $created_at !== '' ? (new DateTime($created_at, TIMEZONE))->format('d/m/Y H:i') : 'N/A',
		];
	}

	$total_pages = max(1, (int) ceil($total / $per_page));

	return [
		'rows' => $formatted_rows,
		'total' => $total,
		'paged' => $paged,
		'per_page' => $per_page,
		'total_pages' => $total_pages,
		'search_query' => $search_query,
	];
}

/**
 * Get label cho action
 */
function game_bsc_get_action_label($action)
{
	$labels = [
		'create' => 'Thêm mới',
		'update' => 'Chỉnh sửa',
		'delete' => 'Xóa',
	];
	return $labels[strtolower($action)] ?? $action;
}

/**
 * Get label cho setting key
 */
function game_bsc_get_setting_label($setting_key)
{
	$labels = [
		'game_bsc_dates' => 'Ngày bắt đầu/kết thúc',
		'game_bsc_stages' => 'Chặng game',
		'game_bsc_max_wrong_answers' => 'Số lần trả lời sai',
		'game_bsc_tasks' => 'Nhiệm vụ',
		'game_bsc_rules' => 'Thể lệ',
		'game_bsc_rewards_descriptions' => 'Cơ chế đổi quà',
		'game_bsc_post_game_badges' => 'Huy hiệu',
		'game_bsc_post_game_question' => 'Câu hỏi',
		'game_bsc_post_game_vouchers' => 'Voucher',
		'game_bsc_gotit_sync_categories' => 'Got It: Đồng bộ danh mục',
		'game_bsc_gotit_sync_vouchers' => 'Got It: Đồng bộ voucher',
		'game_bsc_voucher_excel_export' => 'Voucher: Xuất Excel/CSV',
		'game_bsc_voucher_excel_import' => 'Voucher: Nhập Excel/CSV',
	];
	
	if (isset($labels[$setting_key])) {
		return $labels[$setting_key];
	}
	
	// Kiểm tra xem có phải là artifact không
	if (strpos($setting_key, 'game_bsc_artifact_') === 0) {
		$artifact_id = str_replace('game_bsc_artifact_', '', $setting_key);
		if (is_numeric($artifact_id)) {
			global $wpdb;
			$prefix = $wpdb->prefix . 'game_';
			$artifact_name = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT name FROM {$prefix}artifacts WHERE id = %d",
					$artifact_id
				)
			);
			if ($artifact_name) {
				return 'Hiện vật: ' . $artifact_name;
			}
		}
	}
	
	return $setting_key;
}

/**
 * Format giá trị để hiển thị
 */
function game_bsc_format_log_value_display($value)
{
	if (is_array($value)) {
		return json_encode($value, JSON_UNESCAPED_UNICODE);
	}
	if (is_bool($value)) {
		return $value ? 'true' : 'false';
	}
	if (is_null($value)) {
		return 'null';
	}
	return (string)$value;
}

/**
 * Render chi tiết thay đổi
 */
function game_bsc_render_log_change_details($changed_fields)
{
	if (empty($changed_fields)) {
		return '<em>Không có chi tiết thay đổi</em>';
	}
	
	$html = '';
	
	if (!empty($changed_fields['added'])) {
		$html .= '<div style="margin-bottom: 10px;">';
		$html .= '<strong style="color: green;">➕ Thêm mới:</strong><br>';
		foreach ($changed_fields['added'] as $field => $value) {
			$html .= '<div style="color: green; margin: 3px 0;">';
			$html .= '<strong>' . esc_html($field) . ':</strong> ' . esc_html(game_bsc_format_log_value_display($value));
			$html .= '</div>';
		}
		$html .= '</div>';
	}
	
	if (!empty($changed_fields['removed'])) {
		$html .= '<div style="margin-bottom: 10px;">';
		$html .= '<strong style="color: red;">➖ Xóa:</strong><br>';
		foreach ($changed_fields['removed'] as $field => $value) {
			$html .= '<div style="color: red; margin: 3px 0;">';
			$html .= '<strong>' . esc_html($field) . ':</strong> ' . esc_html(game_bsc_format_log_value_display($value));
			$html .= '</div>';
		}
		$html .= '</div>';
	}
	
	if (!empty($changed_fields['modified'])) {
		$html .= '<div style="margin-bottom: 10px;">';
		$html .= '<strong style="color: #ff9800;">✏️ Thay đổi:</strong><br>';
		foreach ($changed_fields['modified'] as $field => $change) {
			$html .= '<div style="margin: 5px 0; padding: 5px; background: #fff8e1; border-left: 3px solid #fbc02d;">';
			$html .= '<strong>' . esc_html($field) . '</strong><br>';
			$html .= '<span style="color: red;">Trước: ' . esc_html(game_bsc_format_log_value_display($change['old'])) . '</span><br>';
			$html .= '<span style="color: green;">Sau: ' . esc_html(game_bsc_format_log_value_display($change['new'])) . '</span>';
			$html .= '</div>';
		}
		$html .= '</div>';
	}
	
	return $html;
}

/**
 * Lấy badge màu cho action
 */
function game_bsc_get_action_badge_style($action)
{
	$action = strtolower($action);
	if ($action === 'create') {
		return 'background: #28a745; color: white;';
	} elseif ($action === 'update') {
		return 'background: #2271b1; color: white;';
	} elseif ($action === 'delete') {
		return 'background: #dc3545; color: white;';
	}
	return 'background: #6c757d; color: white;';
}

/**
 * Danh sách post type cần ghi nhật ký chỉnh sửa.
 */
function game_bsc_get_trackable_post_types() {
	return [
		'game_badges',
		'game_question',
		'game_vouchers',
	];
}

/**
 * Kiểm tra voucher có phải THIRD_PARTY hay không.
 */
function game_bsc_is_third_party_voucher($post_id) {
	$voucher_type = (string) get_post_meta((int) $post_id, 'voucher_type', true);
	if ($voucher_type === '' && function_exists('get_field')) {
		$voucher_type = (string) get_field('voucher_type', (int) $post_id);
	}

	$normalized = strtoupper(trim($voucher_type));
	return $normalized === 'THIRD_PARTY';
}

/**
 * Quy đổi post type sang setting_key để tái sử dụng dashboard log hiện tại.
 */
function game_bsc_get_post_log_setting_key($post_type) {
	$mapping = [
		'game_badges' => 'game_bsc_post_game_badges',
		'game_question' => 'game_bsc_post_game_question',
		'game_vouchers' => 'game_bsc_post_game_vouchers',
	];

	return $mapping[$post_type] ?? '';
}

/**
 * Bỏ qua log cho autosave/revision hoặc khi voucher đang được sync từ Got It.
 */
function game_bsc_should_skip_post_log($post_id, $post_type) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return true;
	}

	if (wp_is_post_revision($post_id)) {
		return true;
	}

	if ($post_type === 'game_vouchers') {
		// Voucher THIRD_PARTY không log chỉnh sửa thủ công, chỉ log theo action GotIt/Excel.
		if (game_bsc_is_third_party_voucher($post_id)) {
			return true;
		}

		$state = get_option('game_bsc_gotit_async_sync_state', []);
		$status = is_array($state) ? ($state['status'] ?? '') : '';
		if (in_array($status, ['queued', 'running', 'stopping'], true)) {
			return true;
		}

		$ajax_action = isset($_REQUEST['action']) ? sanitize_text_field((string) $_REQUEST['action']) : '';
		if (strpos($ajax_action, 'game_bsc_gotit_sync_') === 0) {
			return true;
		}
	}

	return false;
}

/**
 * Ghi log khi chỉnh sửa thông tin core của post (tiêu đề/trạng thái).
 */
function game_bsc_log_trackable_post_update($post_id, $post_after, $post_before) {
	if (!is_object($post_after) || !is_object($post_before)) {
		return;
	}

	$post_type = $post_after->post_type ?? '';
	if (!in_array($post_type, game_bsc_get_trackable_post_types(), true)) {
		return;
	}

	if (game_bsc_should_skip_post_log((int) $post_id, $post_type)) {
		return;
	}

	$old_value = [
		'post_title' => (string) ($post_before->post_title ?? ''),
		'post_status' => (string) ($post_before->post_status ?? ''),
	];
	$new_value = [
		'post_title' => (string) ($post_after->post_title ?? ''),
		'post_status' => (string) ($post_after->post_status ?? ''),
	];

	if ($old_value === $new_value) {
		return;
	}

	$setting_key = game_bsc_get_post_log_setting_key($post_type);
	if ($setting_key === '') {
		return;
	}

	game_bsc_log_settings_change($setting_key, $old_value, $new_value, 'update');
}
add_action('post_updated', 'game_bsc_log_trackable_post_update', 20, 3);

/**
 * Chụp snapshot ACF trước khi save để so sánh sau save.
 */
function game_bsc_capture_acf_snapshot_before_save() {
	if (!is_admin()) {
		return;
	}

	$post_id = isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0;
	if ($post_id <= 0) {
		return;
	}

	$post_type = get_post_type($post_id);
	if (!in_array($post_type, game_bsc_get_trackable_post_types(), true)) {
		return;
	}

	if (game_bsc_should_skip_post_log($post_id, $post_type)) {
		return;
	}

	if (!function_exists('get_fields')) {
		return;
	}

	$old_fields = get_fields($post_id);
	if (!is_array($old_fields)) {
		$old_fields = [];
	}

	$GLOBALS['game_bsc_acf_log_snapshot'][$post_id] = $old_fields;
}
add_action('acf/validate_save_post', 'game_bsc_capture_acf_snapshot_before_save', 5);

/**
 * Ghi log khi dữ liệu ACF thay đổi trên Huy hiệu/Câu hỏi/Voucher.
 */
function game_bsc_log_acf_changes_after_save($post_id) {
	$post_id = absint($post_id);
	if ($post_id <= 0) {
		return;
	}

	if (!isset($GLOBALS['game_bsc_acf_log_snapshot'][$post_id])) {
		return;
	}

	$post_type = get_post_type($post_id);
	if (!in_array($post_type, game_bsc_get_trackable_post_types(), true)) {
		unset($GLOBALS['game_bsc_acf_log_snapshot'][$post_id]);
		return;
	}

	if (game_bsc_should_skip_post_log($post_id, $post_type)) {
		unset($GLOBALS['game_bsc_acf_log_snapshot'][$post_id]);
		return;
	}

	if (!function_exists('get_fields')) {
		unset($GLOBALS['game_bsc_acf_log_snapshot'][$post_id]);
		return;
	}

	$old_fields = $GLOBALS['game_bsc_acf_log_snapshot'][$post_id];
	$new_fields = get_fields($post_id);
	if (!is_array($new_fields)) {
		$new_fields = [];
	}

	unset($GLOBALS['game_bsc_acf_log_snapshot'][$post_id]);

	if ($old_fields === $new_fields) {
		return;
	}

	$setting_key = game_bsc_get_post_log_setting_key($post_type);
	if ($setting_key === '') {
		return;
	}

	game_bsc_log_settings_change($setting_key, ['acf' => $old_fields], ['acf' => $new_fields], 'update');
}
add_action('acf/save_post', 'game_bsc_log_acf_changes_after_save', 20);