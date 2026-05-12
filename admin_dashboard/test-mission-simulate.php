<?php
/**
 * TEST PAGE: Giả lập hoàn thành nhiệm vụ cho user_id = 1
 * Truy cập: WP Admin > Game BSC > Settings > ?page=dashboard-layout&sub=test-mission
 *
 * Mục đích: kiểm tra mission_date lưu đúng GMT+7 (Y-m-d) sau bug fix
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('Không có quyền truy cập.');

global $wpdb;
$prefix = $wpdb->prefix . 'game_';
$tz     = TIMEZONE; // Asia/Ho_Chi_Minh

$test_user_id = 1;
$now_dt       = new DateTimeImmutable('now', $tz);
$today        = $now_dt->format('Y-m-d');
$now_full     = $now_dt->format('Y-m-d H:i:s');

// --- Danh sách mission code ---
$all_codes = [
    DAILY_LOGIN_CODE,
    MTRADER_LOGIN_CODE,
    TRADE_100M_VND_CODE,
    EKYC_COMPLETE_CODE,
    OPEN_BIDV_CODE,
    OPEN_NEW_ACCOUNT_CODE,
    FIRST_DEPOSIT_CODE,
    OPEN_BSC_DERIVATIVE_ACCOUNT_CODE,
    OPEN_MARGIN_ACCOUNT_CODE,
    USE_BSC_BUY_PACKAGE_CODE,
    USE_MR90_PACKAGE_CODE,
];

$message = '';
$action  = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

// ====== XỬ LÝ POST ======
if ($action === 'simulate' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'test_mission_nonce')) {
    $selected_code  = sanitize_text_field($_POST['mission_code'] ?? '');
    $reward_spins   = absint($_POST['reward_spins'] ?? 5);

    if (!in_array($selected_code, $all_codes)) {
        $message = '<div class="notice notice-error"><p>❌ Mission code không hợp lệ.</p></div>';
    } else {
        // Kiểm tra user tồn tại trong game_users
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$prefix}users WHERE id = %d", $test_user_id
        ), ARRAY_A);

        if (!$user) {
            $message = '<div class="notice notice-error"><p>❌ User ID=1 chưa tồn tại trong game_users. Hãy đăng nhập game trước.</p></div>';
        } else {
            $wpdb->query('START TRANSACTION');
            try {
                // Insert mission log — dùng game_now('date') cho mission_date (đây là điểm bug fix)
                $insert = $wpdb->insert(
                    "{$prefix}user_mission_logs",
                    [
                        'user_id'      => $test_user_id,
                        'mission_code' => $selected_code,
                        'mission_date' => game_now('date'),    // ← PHải là Y-m-d (GMT+7)
                        'reward_type'  => 'PLAY_CREDIT',
                        'reward_value' => $reward_spins,
                        'status'       => 'VERIFIED',
                        'verified_at'  => game_now(),          // ← Y-m-d H:i:s (GMT+7)
                        'api_status'   => 1,
                        'api_payload'  => wp_json_encode(['simulated' => true, 'by' => 'test-page']),
                        'viewed'       => 0,
                    ],
                    ['%d','%s','%s','%s','%d','%s','%s','%d','%s','%d']
                );

                if ($insert === false) throw new Exception('Insert mission log failed: ' . $wpdb->last_error);

                $log_id = $wpdb->insert_id;

                // Update balance
                $bal = $wpdb->query($wpdb->prepare(
                    "UPDATE {$prefix}play_credit_balances SET balance = balance + %d WHERE user_id = %d",
                    $reward_spins, $test_user_id
                ));
                if ($bal === false) throw new Exception('Update balance failed: ' . $wpdb->last_error);

                // Insert ledger
                $led = $wpdb->insert(
                    $wpdb->prefix . 'game_play_credit_ledger',
                    [
                        'user_id'    => $test_user_id,
                        'delta'      => $reward_spins,
                        'ref_type'   => 'MISSION',
                        'ref_id'     => $log_id,
                        'created_at' => game_now(),
                    ],
                    ['%d','%d','%s','%d','%s']
                );
                if ($led === false) throw new Exception('Insert ledger failed: ' . $wpdb->last_error);

                $wpdb->query('COMMIT');

                $message = sprintf(
                    '<div class="notice notice-success"><p>✅ <strong>Thành công!</strong> Đã giả lập nhiệm vụ <code>%s</code> cho user ID=%d</p>
                    <ul>
                        <li>📅 <strong>mission_date</strong> lưu: <code>%s</code> (phải là Y-m-d, GMT+7)</li>
                        <li>🕐 <strong>verified_at</strong> lưu: <code>%s</code> (GMT+7)</li>
                        <li>🎯 <strong>reward_value</strong>: %d lượt chơi</li>
                        <li>🔑 <strong>mission_log id</strong>: %d</li>
                    </ul></p></div>',
                    esc_html($selected_code), $test_user_id,
                    game_now('date'), game_now(),
                    $reward_spins, $log_id
                );

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                $message = '<div class="notice notice-error"><p>❌ Lỗi: ' . esc_html($e->getMessage()) . '</p></div>';
            }
        }
    }
}

if ($action === 'delete_today' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'test_mission_nonce')) {
    // Xóa logs test hôm nay của user 1 (để test lại)
    $del_code = sanitize_text_field($_POST['mission_code'] ?? '');
    if (!empty($del_code) && in_array($del_code, $all_codes)) {
        $wpdb->delete("{$prefix}user_mission_logs", [
            'user_id'      => $test_user_id,
            'mission_code' => $del_code,
            'mission_date' => $today,
        ], ['%d','%s','%s']);
        $message = '<div class="notice notice-warning"><p>🗑️ Đã xóa log nhiệm vụ <code>' . esc_html($del_code) . '</code> ngày <code>' . esc_html($today) . '</code> của user ID=1</p></div>';
    }
}

// === Lấy logs hiện tại của user 1 (7 ngày gần nhất) ===
$logs = $wpdb->get_results($wpdb->prepare(
    "SELECT id, mission_code, mission_date, reward_value, status, verified_at, api_status
     FROM {$prefix}user_mission_logs
     WHERE user_id = %d
     ORDER BY id DESC
     LIMIT 30",
    $test_user_id
), ARRAY_A);

$balance = $wpdb->get_var($wpdb->prepare(
    "SELECT balance FROM {$prefix}play_credit_balances WHERE user_id = %d",
    $test_user_id
));
?>
<div class="wrap">
    <h1>🧪 Test: Giả lập nhiệm vụ — User ID = 1</h1>

    <?php echo $message; ?>

    <div style="background:#fff3cd;border:1px solid #ffc107;padding:12px 16px;border-radius:6px;margin-bottom:20px;">
        <strong>⏰ Thời gian server hiện tại (GMT+7):</strong>
        <code><?php echo esc_html($now_full); ?></code>
        &nbsp;|&nbsp;
        <strong>today (Y-m-d):</strong>
        <code><?php echo esc_html($today); ?></code>
        &nbsp;|&nbsp;
        <strong>game_now('date'):</strong>
        <code><?php echo esc_html(game_now('date')); ?></code>
        &nbsp;|&nbsp;
        <strong>game_now():</strong>
        <code><?php echo esc_html(game_now()); ?></code>
    </div>

    <div style="background:#d4edda;border:1px solid #28a745;padding:12px 16px;border-radius:6px;margin-bottom:20px;">
        <strong>✅ Bug fix đã áp dụng:</strong> <code>check-all</code> bulk insert → <code>mission_date</code> dùng <code>game_now('date')</code> (Y-m-d, GMT+7) thay vì <code>game_now()</code> (Y-m-d H:i:s).
    </div>

    <div style="display:flex;gap:20px;align-items:flex-start;">
        <!-- FORM SIMULATE -->
        <div style="flex:1;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:6px;">
            <h2>Giả lập hoàn thành nhiệm vụ</h2>
            <form method="POST">
                <?php wp_nonce_field('test_mission_nonce'); ?>
                <input type="hidden" name="action_type" value="simulate">
                <table class="form-table">
                    <tr>
                        <th>Mission Code</th>
                        <td>
                            <select name="mission_code" style="min-width:280px;">
                                <?php foreach ($all_codes as $c): ?>
                                    <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Reward Spins</th>
                        <td><input type="number" name="reward_spins" value="5" min="1" max="100" style="width:80px;"></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">▶ Giả lập nhiệm vụ cho User ID=1</button></p>
            </form>
        </div>

        <!-- FORM DELETE (để test lại) -->
        <div style="flex:1;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:6px;">
            <h2>Xóa log nhiệm vụ hôm nay (để test lại)</h2>
            <form method="POST">
                <?php wp_nonce_field('test_mission_nonce'); ?>
                <input type="hidden" name="action_type" value="delete_today">
                <table class="form-table">
                    <tr>
                        <th>Mission Code</th>
                        <td>
                            <select name="mission_code" style="min-width:280px;">
                                <?php foreach ($all_codes as $c): ?>
                                    <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-secondary" onclick="return confirm('Xóa log hôm nay?')">🗑️ Xóa log ngày <?php echo esc_html($today); ?></button></p>
            </form>
        </div>

        <!-- INFO USER -->
        <div style="flex:0 0 220px;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:6px;">
            <h2>User ID = 1</h2>
            <?php
            $u = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, status FROM {$prefix}users WHERE id = %d", $test_user_id
            ), ARRAY_A);
            if ($u): ?>
                <p>Tên: <strong><?php echo esc_html($u['name']); ?></strong></p>
                <p>Status: <code><?php echo $u['status'] ? 'active' : 'locked'; ?></code></p>
            <?php else: ?>
                <p style="color:red;">⚠️ User chưa tồn tại trong game_users</p>
            <?php endif; ?>
            <p>Balance: <strong><?php echo intval($balance); ?> lượt</strong></p>
        </div>
    </div>

    <!-- LOGS TABLE -->
    <h2 style="margin-top:30px;">📋 Mission Logs gần nhất của User ID=1 (30 bản ghi)</h2>
    <p>
        <strong>Kiểm tra:</strong> Cột <code>mission_date</code> phải là <code>Y-m-d</code> (VD: <code><?php echo esc_html($today); ?></code>),
        <strong>KHÔNG</strong> phải <code>Y-m-d H:i:s</code>.
    </p>

    <?php if (empty($logs)): ?>
        <p><em>Chưa có log nào.</em></p>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
        <thead>
            <tr>
                <th style="width:60px">ID</th>
                <th>Mission Code</th>
                <th style="width:140px">mission_date <small>(phải Y-m-d)</small></th>
                <th style="width:160px">verified_at <small>(Y-m-d H:i:s)</small></th>
                <th style="width:80px">Spins</th>
                <th style="width:80px">Status</th>
                <th style="width:80px">OK?</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log):
                $date_val = $log['mission_date'];
                // Kiểm tra: đúng format Y-m-d (10 ký tự, không có giờ)
                $is_correct = (strlen($date_val) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_val));
                $row_style  = $is_correct ? '' : 'background:#ffe0e0;';
            ?>
            <tr style="<?php echo $row_style; ?>">
                <td><?php echo intval($log['id']); ?></td>
                <td><code><?php echo esc_html($log['mission_code']); ?></code></td>
                <td>
                    <code style="<?php echo $is_correct ? 'color:green;' : 'color:red;font-weight:bold;'; ?>">
                        <?php echo esc_html($date_val); ?>
                    </code>
                </td>
                <td><code><?php echo esc_html($log['verified_at']); ?></code></td>
                <td><?php echo intval($log['reward_value']); ?></td>
                <td><?php echo esc_html($log['status']); ?></td>
                <td><?php echo $is_correct ? '✅' : '❌ SAI FORMAT'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><small>🔴 Highlight đỏ = mission_date bị sai format (còn giờ phút giây) — là data cũ trước bug fix.</small></p>
    <?php endif; ?>

    <!-- QUICK API TEST INFO -->
    <h2 style="margin-top:30px;">🔗 Kiểm tra nhanh qua API</h2>
    <div style="background:#f0f4ff;border:1px solid #7b9cfa;padding:16px;border-radius:6px;">
        <p>Sau khi giả lập, dùng trình duyệt/Postman để kiểm tra:</p>
        <ul>
            <li>
                <strong>Play Credit History:</strong>
                <a href="<?php echo esc_url(rest_url('game-bsc/play-credit-history?page=1&per_page=10')); ?>" target="_blank">
                    <code><?php echo esc_html(rest_url('game-bsc/play-credit-history?page=1&per_page=10')); ?></code>
                </a>
            </li>
            <li>
                <strong>Missions List:</strong>
                <a href="<?php echo esc_url(rest_url('game-bsc/v1/missions')); ?>" target="_blank">
                    <code><?php echo esc_html(rest_url('game-bsc/v1/missions')); ?></code>
                </a>
            </li>
        </ul>
        <p><small>Lưu ý: API yêu cầu session SSO — đăng nhập game trước khi test.</small></p>
    </div>
</div>
