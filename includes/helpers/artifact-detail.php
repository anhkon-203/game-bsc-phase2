<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_game_bsc_artifact_piece_detail', 'game_bsc_artifact_piece_detail_handler');

function game_bsc_artifact_piece_detail_handler() {
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        wp_send_json_error('Forbidden', 403);
    }

    global $wpdb;

    $artifact_id = isset($_POST['artifact_id']) ? (int) $_POST['artifact_id'] : 0;
    $piece_count = isset($_POST['piece_count']) ? (int) $_POST['piece_count'] : 0;

    if ($artifact_id <= 0 || $piece_count < 1 || $piece_count > 4) {
        wp_send_json_error('Invalid parameters');
    }

    $users_table      = $wpdb->prefix . 'game_users';
    $user_pieces_table = $wpdb->prefix . 'game_user_pieces';
    $pieces_table      = $wpdb->prefix . 'game_pieces';
    $redemptions_table = $wpdb->prefix . 'game_user_artifact_redemptions';

    // Get users who have exactly $piece_count distinct piece_codes for this artifact
    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id
         FROM {$user_pieces_table} up
         INNER JOIN {$pieces_table} p ON up.piece_id = p.id
         WHERE up.artifact_id = %d AND up.qty > 0
         GROUP BY up.user_id
         HAVING COUNT(DISTINCT p.piece_code) = %d",
        $artifact_id,
        $piece_count
    ));

    if (empty($user_ids)) {
        wp_send_json_success([]);
    }

    $results = [];

    foreach ($user_ids as $uid) {
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT external_user_id, name FROM {$users_table} WHERE id = %d",
            $uid
        ));

        if (!$user) {
            continue;
        }

        // Get piece quantities for P1-P4
        $piece_data = $wpdb->get_results($wpdb->prepare(
            "SELECT p.piece_code, up.qty
             FROM {$user_pieces_table} up
             INNER JOIN {$pieces_table} p ON up.piece_id = p.id
             WHERE up.user_id = %d AND up.artifact_id = %d
             ORDER BY p.piece_code ASC",
            $uid,
            $artifact_id
        ));

        $p = ['P1' => 0, 'P2' => 0, 'P3' => 0, 'P4' => 0];
        foreach ($piece_data as $pd) {
            if (isset($p[$pd->piece_code])) {
                $p[$pd->piece_code] = (int) $pd->qty;
            }
        }

        // Check redemption status
        $redeemed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$redemptions_table} WHERE user_id = %d AND artifact_id = %d",
            $uid,
            $artifact_id
        ));

        $results[] = [
            'account' => $user->external_user_id,
            'name'    => $user->name,
            'p1'      => $p['P1'],
            'p2'      => $p['P2'],
            'p3'      => $p['P3'],
            'p4'      => $p['P4'],
            'status'  => ((int) $redeemed > 0) ? 'Đã đổi quà' : 'Chưa đủ điều kiện đổi quà',
        ];
    }

    wp_send_json_success($results);
}
