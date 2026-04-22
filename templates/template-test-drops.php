<?php
/**
 * Test page cho tính năng rơi mảnh hiện vật.
 * Truy cập: /game-bsc-test-drops
 *
 * Trang này cho phép:
 * - Xem danh sách hiện vật + thông tin kỳ hiện tại
 * - Giả lập rơi mảnh cho user tùy chọn
 * - Xem trạng thái mảnh của user
 * - Test pity system
 * - Test giới hạn 1 bộ / user
 *
 * CHỈ dùng cho development/testing. Chặn truy cập trên production.
 */
if (!defined('ABSPATH')) exit;

// Chặn nếu không phải admin hoặc không bật debug
if (!current_user_can('administrator') && !defined('WP_DEBUG')) {
    wp_die('Bạn không có quyền truy cập trang này.');
}

global $wpdb;
$prefix = $wpdb->prefix . 'game_';
$tz = new DateTimeZone('Asia/Ho_Chi_Minh');
$now = new DateTimeImmutable('now', $tz);

// ===== XỬ LÝ ACTION =====
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'game_test_drops')) {
        wp_die('Nonce không hợp lệ.');
    }

    $action = sanitize_text_field($_POST['action']);

    if ($action === 'simulate_drop') {
        $test_user_id = (int)($_POST['user_id'] ?? 0);
        if ($test_user_id > 0) {
            // session_id INT UNSIGNED max ~4.2B → dùng time() (~1.7B) + random order
            $fake_session_id = time();
            $fake_order = rand(10000, 2147483647);
            $result = game_get_random_reward($test_user_id, $fake_session_id, $fake_order);
            $message = '<strong>Kết quả rơi mảnh cho User #' . $test_user_id . ':</strong><br>';
            $message .= '<pre>' . print_r($result, true) . '</pre>';
            $message_type = ($result['type'] === 'PIECE') ? 'success' : 'info';
        } else {
            $message = 'Vui lòng chọn user.';
            $message_type = 'error';
        }
    }

    if ($action === 'grant_pieces') {
        $test_user_id = (int)($_POST['user_id'] ?? 0);
        $artifact_id  = (int)($_POST['artifact_id'] ?? 0);
        $pieces_to_grant = isset($_POST['pieces']) ? array_map('intval', $_POST['pieces']) : [];

        if ($test_user_id > 0 && $artifact_id > 0 && !empty($pieces_to_grant)) {
            foreach ($pieces_to_grant as $piece_id) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, qty FROM {$prefix}user_pieces WHERE user_id = %d AND piece_id = %d",
                    $test_user_id, $piece_id
                ));

                if ($existing) {
                    $wpdb->update($prefix . 'user_pieces', ['qty' => $existing->qty + 1], ['id' => $existing->id]);
                } else {
                    $wpdb->insert($prefix . 'user_pieces', [
                        'user_id' => $test_user_id,
                        'artifact_id' => $artifact_id,
                        'piece_id' => $piece_id,
                        'qty' => 1
                    ]);
                }
            }
            $message = 'Đã gán ' . count($pieces_to_grant) . ' mảnh cho User #' . $test_user_id;
            $message_type = 'success';
        }
    }

    if ($action === 'reset_user_pieces') {
        $test_user_id = (int)($_POST['user_id'] ?? 0);
        if ($test_user_id > 0) {
            $wpdb->delete($prefix . 'user_pieces', ['user_id' => $test_user_id]);
            $wpdb->delete($prefix . 'user_artifact_redemptions', ['user_id' => $test_user_id]);
            $message = 'Đã xoá toàn bộ mảnh + redemption của User #' . $test_user_id;
            $message_type = 'success';
        }
    }

    if ($action === 'reset_all_data') {
        $wpdb->query("TRUNCATE TABLE {$prefix}user_pieces");
        $wpdb->query("TRUNCATE TABLE {$prefix}user_pieces_ledger");
        $wpdb->query("TRUNCATE TABLE {$prefix}user_artifact_redemptions");
        $wpdb->query("TRUNCATE TABLE {$prefix}drop_logs");
        $message = '🗑️ Đã reset toàn bộ: user_pieces, user_pieces_ledger, user_artifact_redemptions, drop_logs';
        $message_type = 'success';
    }

    if ($action === 'create_test_users') {
        $count = (int)($_POST['count'] ?? 10);
        $count = min(max(1, $count), 50);
        $created = 0;
        $test_names = ['Nguyễn Văn', 'Trần Thị', 'Lê Hoàng', 'Phạm Minh', 'Hoàng Thanh', 'Vũ Đức', 'Đặng Thùy', 'Bùi Quang', 'Đỗ Hải', 'Ngô Bảo', 'Dương Kim', 'Lý Thị', 'Hồ Anh', 'Phan Văn', 'Tô Minh'];

        for ($i = 0; $i < $count; $i++) {
            $name_prefix = $test_names[array_rand($test_names)];
            $suffix = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
            $name = $name_prefix . ' Test_' . $suffix;
            $external_id = 'TEST_' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

            $ok = $wpdb->insert($prefix . 'users', [
                'provider'         => 'test',
                'external_user_id' => $external_id,
                'name'             => $name,
                'status'           => 1,
            ]);
            if ($ok) $created++;
        }
        $message = "Đã tạo {$created}/{$count} user test.";
        $message_type = 'success';
    }
}

// ===== LẤY DỮ LIỆU =====

// Danh sách users (top 50)
$users = $wpdb->get_results("SELECT id, name, external_user_id FROM {$prefix}users ORDER BY id LIMIT 50");

// Danh sách artifacts
$artifacts = $wpdb->get_results("SELECT * FROM {$prefix}artifacts ORDER BY id");

// User đang chọn
$selected_user_id = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
if ($selected_user_id <= 0 && !empty($users)) {
    $selected_user_id = $users[0]->id;
}

// Mảnh của user đang chọn
$user_pieces = [];
if ($selected_user_id > 0) {
    $user_pieces = $wpdb->get_results($wpdb->prepare(
        "SELECT up.*, p.piece_code, p.artifact_id, a.name as artifact_name
         FROM {$prefix}user_pieces up
         JOIN {$prefix}pieces p ON p.id = up.piece_id
         JOIN {$prefix}artifacts a ON a.id = p.artifact_id
         WHERE up.user_id = %d AND up.qty >= 1
         ORDER BY p.artifact_id, p.piece_code",
        $selected_user_id
    ));
}

// Check user đã hoàn thành bộ nào chưa
$user_redemptions = [];
if ($selected_user_id > 0) {
    $user_redemptions = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, a.name as artifact_name
         FROM {$prefix}user_artifact_redemptions r
         JOIN {$prefix}artifacts a ON a.id = r.artifact_id
         WHERE r.user_id = %d",
        $selected_user_id
    ));
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Rơi Mảnh - Game BSC</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
        h1 { color: #38bdf8; margin-bottom: 20px; font-size: 24px; }
        h2 { color: #7dd3fc; margin: 20px 0 12px; font-size: 18px; border-bottom: 1px solid #334155; padding-bottom: 8px; }
        h3 { color: #93c5fd; margin: 12px 0 8px; font-size: 15px; }

        .container { max-width: 1200px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 16px; margin-bottom: 16px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #0f172a; color: #94a3b8; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        tr:hover { background: #1e3a5f; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #064e3b; color: #6ee7b7; }
        .badge-red { background: #7f1d1d; color: #fca5a5; }
        .badge-yellow { background: #78350f; color: #fcd34d; }
        .badge-blue { background: #1e3a5f; color: #93c5fd; }
        .badge-purple { background: #4c1d95; color: #c4b5fd; }

        select, input[type="number"] { background: #0f172a; color: #e2e8f0; border: 1px solid #475569; padding: 6px 10px; border-radius: 6px; }
        select { min-width: 200px; }

        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        .msg { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
        .msg-success { background: #064e3b; border: 1px solid #059669; color: #6ee7b7; }
        .msg-error { background: #7f1d1d; border: 1px solid #dc2626; color: #fca5a5; }
        .msg-info { background: #1e3a5f; border: 1px solid #2563eb; color: #93c5fd; }

        .piece-grid { display: flex; gap: 8px; flex-wrap: wrap; }
        .piece-item { background: #0f172a; border: 1px solid #475569; padding: 6px 10px; border-radius: 6px; font-size: 12px; }
        .piece-item.owned { border-color: #059669; background: #064e3b; }

        pre { background: #0f172a; border: 1px solid #334155; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; color: #a5f3fc; }
        .mono { font-family: 'Fira Code', 'Consolas', monospace; font-size: 12px; }
        form { display: inline; }
        label { font-size: 13px; color: #94a3b8; }
        .inline-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .checkbox-group { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0; }
        .checkbox-group label { display: flex; align-items: center; gap: 4px; background: #0f172a; padding: 4px 10px; border-radius: 6px; border: 1px solid #475569; cursor: pointer; }
        .checkbox-group label:hover { border-color: #60a5fa; }
        a { color: #60a5fa; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧪 Test Rơi Mảnh Hiện Vật</h1>
    <p style="color:#64748b;margin-bottom:16px;">Thời gian hiện tại: <span class="mono"><?php echo $now->format('Y-m-d H:i:s'); ?></span> (Asia/Ho_Chi_Minh)</p>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- ===== CHỌN USER ===== -->
    <div class="card">
        <h2>👤 Chọn User để test</h2>
        <form method="get" action="" class="inline-form">
            <select name="user_id" onchange="this.form.submit()">
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u->id; ?>" <?php selected($selected_user_id, $u->id); ?>>
                        #<?php echo $u->id; ?> - <?php echo esc_html($u->name); ?> (<?php echo esc_html($u->external_user_id); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selected_user_id > 0): ?>
            <span style="margin-left:12px;">
                Đã hoàn thành bộ:
                <?php if (game_user_has_completed_artifact($selected_user_id)): ?>
                    <span class="badge badge-green">CÓ (<?php echo count($user_redemptions); ?> bộ)</span>
                <?php else: ?>
                    <span class="badge badge-yellow">CHƯA</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>

        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #334155;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <form method="post" class="inline-form">
                <?php wp_nonce_field('game_test_drops'); ?>
                <input type="hidden" name="action" value="create_test_users">
                <label>Số user: <input type="number" name="count" value="10" min="1" max="50" style="width:60px;"></label>
                <button type="submit" class="btn btn-success btn-sm">➕ Tạo user test</button>
                <span style="color:#64748b;font-size:12px;margin-left:8px;">Hiện có <?php echo count($users); ?> user</span>
            </form>

            <form method="post" class="inline-form" onsubmit="return confirm('⚠️ XÓA TOÀN BỘ dữ liệu mảnh, redemptions, drop_logs của TẤT CẢ user. Bạn chắc chắn?');">
                <?php wp_nonce_field('game_test_drops'); ?>
                <input type="hidden" name="action" value="reset_all_data">
                <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;">🗑️ Reset toàn bộ data mảnh</button>
            </form>
        </div>
    </div>

    <div class="grid">
        <!-- ===== CỘT TRÁI: HIỆN VẬT + KỲ ===== -->
        <div>
            <h2>📦 Danh sách hiện vật & Thông tin kỳ</h2>

            <?php foreach ($artifacts as $art): ?>
                <?php
                $current_period = game_artifact_current_period($art);
                $is_within = game_artifact_is_within_period($art);
                $has_quota = ($current_period !== false) ? game_artifact_period_has_quota($art, $current_period) : true;

                // Lấy mảnh
                $pieces = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$prefix}pieces WHERE artifact_id = %d ORDER BY piece_code",
                    $art->id
                ));

                // Check pity cho user hiện tại
                $pity_piece = null;
                if ($selected_user_id > 0) {
                    $pity_piece = game_check_pity($selected_user_id, $art);
                }
                ?>
                <div class="card">
                    <h3>
                        <?php echo esc_html($art->name); ?>
                        <span class="badge <?php echo ($art->status == 1) ? 'badge-green' : 'badge-red'; ?>">
                            <?php echo ($art->status == 1) ? 'Mở' : 'Đóng'; ?>
                        </span>
                        <?php if (!$is_within): ?>
                            <span class="badge badge-red">Ngoài thời hạn</span>
                        <?php endif; ?>
                        <?php if (!$has_quota): ?>
                            <span class="badge badge-yellow">Hết quota kỳ</span>
                        <?php endif; ?>
                        <?php if ($pity_piece): ?>
                            <span class="badge badge-purple">PITY → <?php echo $pity_piece->piece_code; ?></span>
                        <?php endif; ?>
                    </h3>

                    <table>
                        <tr><td>ID</td><td class="mono"><?php echo $art->id; ?></td></tr>
                        <tr><td>Max redemptions</td><td><?php echo $art->max_redemptions; ?></td></tr>
                        <tr>
                            <td>Thời hạn</td>
                            <td>
                                <?php if ($art->period_start && $art->period_end): ?>
                                    <span class="mono"><?php echo $art->period_start; ?></span> →
                                    <span class="mono"><?php echo $art->period_end; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-yellow">Không giới hạn</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Kỳ hiện tại</td>
                            <td>
                                <?php if ($current_period !== false): ?>
                                    <span class="badge badge-blue">Kỳ <?php echo $current_period + 1; ?> / <?php echo $art->total_periods; ?></span>
                                    <?php
                                    $dates = game_artifact_period_dates($art, $current_period);
                                    if ($dates):
                                    ?>
                                        <br><small class="mono" style="color:#64748b;">
                                            <?php echo $dates['start']->format('d/m H:i'); ?> →
                                            <?php echo $dates['end']->format('d/m H:i'); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php
                                    // Đếm redemptions trong kỳ
                                    if ($dates):
                                        $period_redemptions = (int)$wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$prefix}user_artifact_redemptions
                                             WHERE artifact_id = %d AND redeemed_at >= %s AND redeemed_at <= %s",
                                            $art->id, $dates['start']->format('Y-m-d H:i:s'), $dates['end']->format('Y-m-d H:i:s')
                                        ));
                                    ?>
                                        <br><small>Redemptions kỳ này: <strong><?php echo $period_redemptions; ?></strong> / <?php echo $art->max_redemptions_per_period; ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-red">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <!-- Danh sách tất cả kỳ -->
                    <?php if ($art->period_start && $art->period_end && $art->total_periods > 1): ?>
                        <h3 style="margin-top:12px;">Tất cả kỳ</h3>
                        <table>
                            <tr><th>#</th><th>Bắt đầu</th><th>Kết thúc</th><th>Trạng thái</th></tr>
                            <?php for ($pi = 0; $pi < $art->total_periods; $pi++):
                                $pd = game_artifact_period_dates($art, $pi);
                                if (!$pd) continue;
                                $is_current = ($pi === $current_period);
                            ?>
                                <tr style="<?php echo $is_current ? 'background:#1e3a5f;' : ''; ?>">
                                    <td><?php echo $pi + 1; ?></td>
                                    <td class="mono"><?php echo $pd['start']->format('d/m/Y H:i'); ?></td>
                                    <td class="mono"><?php echo $pd['end']->format('d/m/Y H:i'); ?></td>
                                    <td>
                                        <?php if ($is_current): ?>
                                            <span class="badge badge-green">Đang diễn ra</span>
                                        <?php elseif ($now < $pd['start']): ?>
                                            <span class="badge badge-blue">Sắp tới</span>
                                        <?php else: ?>
                                            <span class="badge badge-yellow">Đã qua</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </table>
                    <?php endif; ?>

                    <!-- Grant pieces -->
                    <?php if ($selected_user_id > 0 && !empty($pieces)): ?>
                        <h3 style="margin-top:12px;">Gán mảnh cho User #<?php echo $selected_user_id; ?></h3>
                        <form method="post">
                            <?php wp_nonce_field('game_test_drops'); ?>
                            <input type="hidden" name="action" value="grant_pieces">
                            <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                            <input type="hidden" name="artifact_id" value="<?php echo $art->id; ?>">
                            <div class="checkbox-group">
                                <?php foreach ($pieces as $p):
                                    $owned = $wpdb->get_var($wpdb->prepare(
                                        "SELECT qty FROM {$prefix}user_pieces WHERE user_id = %d AND piece_id = %d",
                                        $selected_user_id, $p->id
                                    ));
                                    $owned = (int)$owned;
                                ?>
                                    <label class="<?php echo $owned > 0 ? 'owned' : ''; ?>" style="<?php echo $owned > 0 ? 'border-color:#059669;background:#064e3b;' : ''; ?>">
                                        <input type="checkbox" name="pieces[]" value="<?php echo $p->id; ?>">
                                        <?php echo $p->piece_code; ?> (w:<?php echo $p->baseline_weight; ?>)
                                        <?php if ($owned > 0): ?>
                                            <span class="badge badge-green">×<?php echo $owned; ?></span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm">Gán mảnh đã chọn</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== CỘT PHẢI: THAO TÁC TEST ===== -->
        <div>
            <h2>🎯 Giả lập rơi mảnh</h2>
            <div class="card">
                <form method="post" class="inline-form">
                    <?php wp_nonce_field('game_test_drops'); ?>
                    <input type="hidden" name="action" value="simulate_drop">
                    <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                    <label>User #<?php echo $selected_user_id; ?></label>
                    <button type="submit" class="btn btn-primary">Rơi mảnh 1 lần</button>
                </form>
                <p style="margin-top:8px;color:#64748b;font-size:12px;">
                    Gọi <code>game_get_random_reward()</code> cho user đã chọn. Kết quả thật (ghi log + cập nhật DB).
                </p>
            </div>

            <h2>🗑️ Reset dữ liệu user</h2>
            <div class="card">
                <form method="post" class="inline-form">
                    <?php wp_nonce_field('game_test_drops'); ?>
                    <input type="hidden" name="action" value="reset_user_pieces">
                    <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Xoá toàn bộ mảnh của user #<?php echo $selected_user_id; ?>?');">
                        Xoá toàn bộ mảnh User #<?php echo $selected_user_id; ?>
                    </button>
                </form>
            </div>

            <h2>🧩 Mảnh đang có (User #<?php echo $selected_user_id; ?>)</h2>
            <div class="card">
                <?php if (empty($user_pieces)): ?>
                    <p style="color:#64748b;">Chưa có mảnh nào.</p>
                <?php else: ?>
                    <?php
                    $grouped = [];
                    foreach ($user_pieces as $up) {
                        $grouped[$up->artifact_name][] = $up;
                    }
                    foreach ($grouped as $art_name => $pieces_list):
                    ?>
                        <h3><?php echo esc_html($art_name); ?></h3>
                        <div class="piece-grid">
                            <?php foreach ($pieces_list as $up): ?>
                                <div class="piece-item owned">
                                    <?php echo $up->piece_code; ?> × <?php echo $up->qty; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2>🏆 Bộ đã hoàn thành (User #<?php echo $selected_user_id; ?>)</h2>
            <div class="card">
                <?php if (empty($user_redemptions)): ?>
                    <p style="color:#64748b;">Chưa hoàn thành bộ nào.</p>
                <?php else: ?>
                    <table>
                        <tr><th>Hiện vật</th><th>Thời gian</th></tr>
                        <?php foreach ($user_redemptions as $r): ?>
                            <tr>
                                <td><?php echo esc_html($r->artifact_name); ?></td>
                                <td class="mono"><?php echo $r->redeemed_at; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <h2>📊 Debug Info</h2>
            <div class="card">
                <?php
                $system_drops_today = count_piece_drops_today();
                $user_drops_today = ($selected_user_id > 0) ? count_piece_drops_today($selected_user_id) : 0;
                $max_system = (int)get_option('game_bsc_max_drop_pieces_per_day', 0);
                $max_user = (int)get_option('game_bsc_max_user_drop_pieces_per_day', 3);
                $drop_rate = (int)get_option('game_bsc_piece_drop_rate', 30);
                ?>
                <table>
                    <tr><td>Mảnh rơi hệ thống hôm nay</td><td class="mono"><?php echo $system_drops_today; ?> / <?php echo $max_system ?: '∞'; ?></td></tr>
                    <tr><td>Mảnh rơi user hôm nay</td><td class="mono"><?php echo $user_drops_today; ?> / <?php echo $max_user; ?></td></tr>
                    <tr><td>Xác suất rơi mảnh</td><td class="mono"><?php echo $drop_rate; ?>%</td></tr>
                    <tr><td>Điểm / câu đúng</td><td class="mono"><?php echo game_point_per_answer(); ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
