<?php
if (!defined('ABSPATH')) exit;

/**
 * =========================================
 * CONFIG & COMMON HELPERS (question-only)
 * =========================================
 */
if (!defined('GAME_QUESTION_POST_TYPE')) define('GAME_QUESTION_POST_TYPE', 'game_question');

function game_db()
{
    global $wpdb;
    return $wpdb;
}
function game_tbl($s)
{
    global $wpdb;
    return $wpdb->prefix . 'game_' . $s;
}
function game_now_ts()
{
    return current_time('timestamp');
}
/** Lấy ngày hôm nay (Y-m-d) từ game_now() */
function game_bsc_today_date(): string {
    return substr(game_now(), 0, 10);
}
// Tính TTL đến nửa đêm hôm nay
function game_cache_ttl_to_midnight(): int {
    $now = game_now(); // "2025-10-31 23:50:25"
    $midnight = substr($now, 0, 10) . ' 23:59:59'; // "2025-10-31 23:59:59"
    return strtotime($midnight) - strtotime($now); // giây còn lại đến nửa đêm
}
// Lấy thông tin stage hiện tại (theo ngày hôm nay)
function game_bsc_get_current_stage(): ?array {
//    $today = game_now('date'); // "2025-10-31"
//    $cache_key = 'game_bsc_stage_' . $today;
//
//    $cached = get_transient($cache_key);
//    if ($cached != false) {
//        return $cached;
//    }

    // Tính TTL đến 00:00 ngày mai
//    $ttl = game_cache_ttl_to_midnight(); // ví dụ: 10 phút nếu là 23:50

    $stages = get_option('game_bsc_stages');
    if (!is_array($stages) || empty($stages)) {
//        set_transient($cache_key, null, $ttl);
        return null;
    }

    $current_stage = null;
    $current_user = game_sso_require_session();
    if (is_wp_error($current_user)) {
        $data_day = game_bsc_compute_day_index();
    } else {
        $user_id = absint($current_user['id']);
        $data_day = game_bsc_compute_day_index_v2($user_id);
    }
    $day_index = $data_day['day_index'] ?? 0;
    foreach ($stages as $stg) {
        $from = $stg['from_stage'] ?? null;
        $to   = $stg['to_stage']   ?? null;
        if (!$from || !$to) continue;

        if ($day_index >= (int)$from && $day_index <= (int)$to) {
            $current_stage = [
                'from'              => $from,
                'to'                => $to,
                'duration'          => max(1, (int)($stg['duration'] ?? 0)),
                'score'             => (int)($stg['score'] ?? 0),
                'questions_per_day' => max(1, (int)($stg['questions_per_day'] ?? 0)),
            ];
            break;
        }
    }

//    set_transient($cache_key, $current_stage, $ttl);

    return $current_stage;
}

/**
 * Thời gian trả lời mỗi câu (giây)
 */
function game_secs_per_q(): int {
	
    $stage = game_bsc_get_current_stage();
    if ($stage && !empty($stage['duration'])) {
        return max(1, (int)$stage['duration']);
    }
    return 30;
}

/**
 * Số câu trong một phiên
 */
function game_q_per_session(): int {
    $stage = game_bsc_get_current_stage();
    if ($stage && !empty($stage['questions_per_day'])) {
        return max(1, (int)$stage['questions_per_day']);
    }
    return 3;
}

/**
 * Điểm tặng mỗi câu đúng
 */
function game_point_per_answer(): int {
    $stage = game_bsc_get_current_stage();
    if ($stage && !empty($stage['score'])) {
        return max(1, (int)$stage['score']);
    }
    return 500;
}
/**
 *  Đếm số lần rôi mảnh trong ngày hôm nay
 */
function count_piece_drops_today( $user_id = null ) {
    global $wpdb;

    $table = $wpdb->prefix . 'game_drop_logs';

    // Lấy ngày hôm nay theo timezone của WordPress, dạng YYYY-MM-DD
    $today = game_now('date');

    $start = $today . ' 00:00:00';
    $end   = $today . ' 23:59:59';

    if ( $user_id === null ) {
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE outcome = %s AND created_at BETWEEN %s AND %s",
            'PIECE',
            $start,
            $end
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE outcome = %s AND user_id = %d AND created_at BETWEEN %s AND %s",
            'PIECE',
            (int) $user_id,
            $start,
            $end
        );
    }

    return (int) $wpdb->get_var( $sql );
}
/**
 * ========== Named Locks (MySQL) ==========
 * Dùng để tuần tự hoá thao tác theo user/session, tránh race condition.
 */
function game_acquire_lock($name, $timeout = 5)
{
    $db = game_db();
    $lock = $db->get_var($db->prepare("SELECT GET_LOCK(%s, %d)", $name, $timeout));
    return ((int)$lock === 1);
}
function game_release_lock($name)
{
    $db = game_db();
    $db->get_var($db->prepare("SELECT RELEASE_LOCK(%s)", $name));
}

/** Lấy phiên mở (KHÔNG auto close) */
function game_get_open_session_raw($user_id)
{
    $db = game_db();
    $t = game_tbl('users_play_sessions');

    return $db->get_row($db->prepare(
        "SELECT * FROM $t 
         WHERE user_id = %d 
         AND finished_at IS NULL 
         AND DATE(started_at) = CURDATE()
         ORDER BY id DESC LIMIT 1",
        $user_id
    ));
}

/** Đếm câu đã trả lời (user_answer != NULL/'' ) */
function game_count_answered($session_id)
{
    $db = game_db();
    $t = game_tbl('users_session_answers');
    return (int)$db->get_var($db->prepare(
        "SELECT COUNT(*) FROM $t WHERE session_id=%d AND user_answer IS NOT NULL AND user_answer<>''",
        $session_id
    ));
}

/** Đếm tổng bản ghi (answered + unanswered) */
function game_count_attempts($session_id)
{
    $db = game_db();
    $t = game_tbl('users_session_answers');
    return (int)$db->get_var($db->prepare(
        "SELECT COUNT(*) FROM $t WHERE session_id=%d",
        $session_id
    ));
}

/** Đếm unanswered */
function game_count_unanswered($session_id)
{
    $db = game_db();
    $t = game_tbl('users_session_answers');
    return (int)$db->get_var($db->prepare(
        "SELECT COUNT(*) FROM $t WHERE session_id=%d AND (user_answer IS NULL OR user_answer='')",
        $session_id
    ));
}

/** Lấy unanswered gần nhất (resume) */
function game_get_last_unanswered($session_id)
{
    $db = game_db();
    $t = game_tbl('users_session_answers');
    $row = $db->get_row($db->prepare(
        "SELECT id, question_post_id, order_index
         FROM $t
         WHERE session_id=%d AND (user_answer IS NULL OR user_answer='')
         ORDER BY id DESC LIMIT 1",
        $session_id
    ), ARRAY_A);
    return $row ?: null;
}

/** Câu đã dùng trong phiên (tránh lặp) */
function game_used_question_ids($sid)
{
    $db = game_db();
    $t = game_tbl('users_session_answers');
    return array_map('intval', $db->get_col($db->prepare(
        "SELECT DISTINCT question_post_id FROM $t WHERE session_id=%d AND question_post_id IS NOT NULL",
        $sid
    )));
}

/** Bốc 1 câu ngẫu nhiên */
function game_pick_question($exclude = [])
{
    $args = [
        'post_type' => GAME_QUESTION_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1, 
        'orderby' => 'rand', 
        'fields' => 'ids'
    ];
    if ($exclude) $args['post__not_in'] = $exclude;
    $q = new WP_Query($args);
    return $q->posts ? (int)$q->posts[0] : 0;
}

/** Token 60s/câu */
function game_q_token_make($sid, $ord, $qid)
{
    // Code cũ dùng nonce của wordpress
    // $key = "gq_{$sid}_{$ord}";
    // set_transient($key, ['q' => $qid, 't' => game_now_ts()], DAY_IN_SECONDS);
    // return wp_create_nonce($key);

    // Code mới tạo token thủ công
    $key = "gq_{$sid}_{$ord}";
    $token = wp_generate_password(32, false, false);
    $payload = [
        'q'     => (int)$qid,
        't'     => time(),
        'token' => $token,
    ];
    
    $ttl = DAY_IN_SECONDS;
    set_transient($key, $payload, $ttl);
    return $token;
}

/** Verify token 60s (1 lần) */
function game_q_token_verify($sid, $ord, $token, &$out_qid)
{
    // Code cũ dùng nonce của wordpress
//     $key = "gq_{$sid}_{$ord}";
//     if (!wp_verify_nonce($token, $key)) return false;
//     $p = get_transient($key);
//     if (!$p) return false;
// //    if (game_now_ts() - (int)$p['t'] > game_secs_per_q()) return false;
//     $out_qid = (int)$p['q'];
//     delete_transient($key);
//     return true;

    // Code mới tạo token thủ công
    $key = "gq_{$sid}_{$ord}";
    if (!is_string($token) || $token === '') return false;

    $p = get_transient($key);
    if (!$p || !is_array($p)) return false;

    if (!isset($p['token'])) return false;

    // So sánh an toàn
    if (!hash_equals((string)$p['token'], (string)$token)) {
        return false;
    }

    // hợp lệ -> trả qid và xóa transient (single-use)
    $out_qid = (int)$p['q'];
    delete_transient($key);
    return true;
}

/** Lấy id câu hỏi */
function game_q_token_get_qid(int $sid, int $ord)
{
    // Tạo key transient
    $key = "gq_{$sid}_{$ord}";

    // Lấy dữ liệu từ transient
    $data = get_transient($key);
    if (!$data || !is_array($data)) {
        return 0;
    }
    // Trả về qid (không xóa transient ở đây)
    return (int)$data['q'];
}

/** Build payload câu hỏi */
function game_build_question_payload($qid, $ord, $sid)
{
    $content = get_the_title($qid);
    // Đáp án
    $meta = get_post_meta($qid);

    $choices = [];
    $listAnswer = [
        'A' => $meta['answer_a'][0] ?? '',
        'B' => $meta['answer_b'][0] ?? '',
        'C' => $meta['answer_c'][0] ?? '',
        'D' => $meta['answer_d'][0] ?? '',
    ];

    $value = 1;
    foreach ($listAnswer as $code => $answer) {
        $answer = trim($answer);
        if ($answer !== '') {
            $choices[] = [
                'value'      => $value,
                'valueCode'  => $code,
                'content'    => $answer,
            ];
            $value++;
        }
    }

    // Loại bỏ đáp án rỗng (nếu có)
    // $choices = array_filter($choices, 'strlen');
    return [
        'question_id' => $qid,
        'order_index' => $ord,
        'question_content' => wp_strip_all_tags($content),
        'list_answer' => $choices,
//        'time_left' => game_secs_per_q(), // fix code
        'question_token' => game_q_token_make($sid, $ord, $qid),
    ];
}

/** Chấm đáp án */
function game_check_answer($qid, $ans)
{
    $correct = strtoupper((string)get_post_meta($qid, 'correct_answer', true));
    return ['is_correct' => $correct === strtoupper($ans), 'correct_key' => $correct];
}

/**
 * Trừ 1 lượt ATOMIC:
 *  - Không đọc rồi ghi nữa (tránh TOCTOU).
 *  - UPDATE ... SET balance=balance-1 WHERE user_id=? AND balance>=1;
 *  - Kiểm tra rows affected.
 */
function game_credit_consume_one($uid, $sid)
{
    $db = game_db();
    $bal = game_tbl('play_credit_balances');
    $led = game_tbl('play_credit_ledger');

    $db->query('START TRANSACTION');
    $affected = $db->query($db->prepare(
        "UPDATE $bal SET balance=balance-1, updated_at=%s WHERE user_id=%d AND balance>=1",
        game_now(),
        $uid
    ));
    if ($affected !== 1) {
        $db->query('ROLLBACK');
        return [false, 'not enough credit'];
    }
    $db->insert($led, ['user_id' => $uid, 'delta' => -1, 'ref_type' => 'SESSION', 'ref_id' => $sid, 'created_at' => game_now()]);
    $db->query('COMMIT');
    return [true, null];
}
/**
 * Helper functions để quản lý used question IDs trong cache
 */
function game_get_used_questions_cache_key($sid): string {
    return "game_used_questions_{$sid}";
}
/**
 * Helper functions thêm question ID đã dùng vào cache
 */
function game_add_used_question($sid, $qid): void {
    $key = game_get_used_questions_cache_key($sid);
    $used = get_transient($key);
    
    // Nếu chưa có cache thì khởi tạo array mới
    if ($used === false) {
        $used = [];
    }
    
    // Thêm question ID mới nếu chưa có
    if (!in_array($qid, $used)) {
        $used[] = $qid;
        // Cache sẽ hết hạn sau 24h
        set_transient($key, $used, DAY_IN_SECONDS);
    }
}
/**
 * Helper functions lấy danh sách question ID đã dùng từ cache
 */
function game_get_used_questions($sid): array {
    $key = game_get_used_questions_cache_key($sid);
    $used = get_transient($key);
    return ($used === false) ? [] : $used;
}
/**
 * Helper functions xóa danh sách question ID đã dùng trong cache
 */
function game_clear_used_questions($sid): void {
    delete_transient(game_get_used_questions_cache_key($sid));
}
/** 
 * Helper function để random phần thưởng khi trả lời đúng
 * @return array ['type' => 'points|image', 'value' => int|string]
 */
function game_get_random_reward($user_id, $session_id, $order_index) {
    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';
    $pity_lock_name = null;

    // ===== Bước 0: Các limit hệ thống (giữ nguyên logic cũ) =====
    $system_piece_today = (int)get_option('game_bsc_max_drop_pieces_per_day', 0);
    $system_piece_rewarded_today = count_piece_drops_today();
    $user_piece_today = (int)get_option('game_bsc_max_user_drop_pieces_per_day', 3);
    $user_piece_rewarded_today = count_piece_drops_today( $user_id );
    $piece_drop_rate = (int)get_option('game_bsc_piece_drop_rate', 30);
    if(empty($piece_drop_rate)) {
        $piece_drop_rate = 30;
    }
    $is_piece = (rand(1, 100) <= $piece_drop_rate);
    
    // Nếu hệ thống hoặc user đã hết quota ngày hoặc random ra điểm → thưởng điểm
    if (($system_piece_today > 0 && $system_piece_rewarded_today >= $system_piece_today) || ($user_piece_rewarded_today >= $user_piece_today) || !$is_piece) {
        $reward = game_build_point_reward();
    } else {
        // ===== Bước 1: Lấy danh sách hiện vật, filter theo thời hạn + tổng lượt đổi =====
        $artifacts = $wpdb->get_results(
            "SELECT * FROM {$prefix}artifacts WHERE status = 1 AND closed = 0"
        );

        // Lọc theo thời hạn + tổng lượt đổi.
        // Không loại artifact khi kỳ đã hết quota, vì vẫn cần rơi mảnh safe (không complete).
        $eligible_artifacts = [];
        foreach ($artifacts as $art) {
            // Kiểm tra thời hạn
            if ( ! game_artifact_is_within_period( $art ) ) {
                continue;
            }

            // Kiểm tra hiện vật đã hết tổng lượt đổi chưa
            if ( ! empty( $art->max_redemptions ) && $art->max_redemptions > 0 ) {
                $total_redeemed = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$prefix}user_artifact_redemptions WHERE artifact_id = %d",
                    $art->id
                ) );
                if ( $total_redeemed >= (int) $art->max_redemptions ) {
                    continue; // Hiện vật đã hết lượt đổi hoàn toàn → không rơi mảnh nữa
                }
            }

            $eligible_artifacts[] = $art;
        }

        if (empty($eligible_artifacts)) {
            // Không có hiện vật nào eligible → fallback điểm
            $reward = game_build_point_reward();
        } else {
            // ===== Bước 2: Xác định chế độ safe-piece =====
            // Safe-piece kích hoạt khi user đã hoàn thành 1 bộ bất kỳ → chặn bộ thứ 2.
            $user_already_completed = game_user_has_completed_artifact( $user_id );

            // ===== Bước 3: Check Pity trước khi random =====
            // Sắp xếp theo drop_weight giảm dần: hiện vật ưu tiên cao → pity check trước
            usort($eligible_artifacts, function($a, $b) {
                return ((int)($b->drop_weight ?? 1)) - ((int)($a->drop_weight ?? 1));
            });
            $pity_piece = null;
            $pity_artifact = null;
            foreach ($eligible_artifacts as $art) {
                // Skip pity nếu user đã có bộ
                if ( $user_already_completed ) {
                    continue;
                }

                $current_period = game_artifact_current_period( $art );
                $period_has_quota = ( $current_period === false ) ? true : game_artifact_period_has_quota( $art, $current_period );
                if ( ! $period_has_quota ) {
                    continue;
                }

                $pity_result = game_check_pity( $user_id, $art );
                if ( $pity_result ) {
                    if ( $current_period !== false ) {
                        $lock_name = sprintf( 'game_pity_art_%d_period_%d', (int) $art->id, (int) $current_period );

                        // Cạnh tranh thời điểm: request nào vào vùng lock trước sẽ có quyền pity.
                        if ( ! game_acquire_lock( $lock_name, 1 ) ) {
                            continue;
                        }

                        // Re-check sau lock để tránh race condition vượt quota kỳ.
                        if ( ! game_artifact_period_has_quota( $art, $current_period ) ) {
                            game_release_lock( $lock_name );
                            continue;
                        }

                        $pity_lock_name = $lock_name;
                    }

                    $pity_piece = $pity_result;
                    $pity_artifact = $art;
                    break;
                }
            }

            if ( $pity_piece && $pity_artifact ) {
                // ===== PITY: Gán mảnh còn thiếu =====
                $pity_period_index = game_artifact_current_period( $pity_artifact );
                $reward = [
                    'outcome' => 'PIECE',
                    'points_awarded' => 0,
                    'artifact_id' => $pity_artifact->id,
                    'piece_id' => $pity_piece->id,
                    'piece_code' => $pity_piece->piece_code,
                    'piece_url' => $pity_piece->piece_img,
                    'weight_sum' => 0,
                    'chosen_weight' => 0,
                    'is_pity' => true,
                    'period_index' => $pity_period_index
                ];
            } else {
                // ===== Bước 4: Random theo trọng số drop_weight =====
                $art_weight_sum = 0;
                foreach ($eligible_artifacts as $ea) {
                    $art_weight_sum += max(1, (int)($ea->drop_weight ?? 1));
                }
                $art_rand = rand(1, $art_weight_sum);
                $art_current = 0;
                $artifact = $eligible_artifacts[0]; // fallback
                foreach ($eligible_artifacts as $ea) {
                    $art_current += max(1, (int)($ea->drop_weight ?? 1));
                    if ($art_rand <= $art_current) {
                        $artifact = $ea;
                        break;
                    }
                }
                $artifact_period_index = game_artifact_current_period( $artifact );
                $artifact_period_has_quota = ( $artifact_period_index === false )
                    ? true
                    : game_artifact_period_has_quota( $artifact, $artifact_period_index );

                // Lấy các mảnh của hiện vật
                $pieces = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, piece_code, baseline_weight, piece_img
                    FROM {$prefix}pieces 
                    WHERE artifact_id = %d",
                    $artifact->id
                ));

                if (empty($pieces)) {
                    $reward = game_build_point_reward();
                } else {
                    // Cần safe-piece trong 3 trường hợp:
                    // 1) user đã có bộ ở đợt game hiện tại,
                    // 2) kỳ của artifact đã hết quota,
                    // 3) user có 3 mảnh nhưng thua cạnh tranh lock pity ở cùng thời điểm.
                    $need_safe = $user_already_completed || ! $artifact_period_has_quota;

                    if ( ! $need_safe && ! $user_already_completed ) {
                        $pity_candidate = game_check_pity( $user_id, $artifact );
                        if ( $pity_candidate && $artifact_period_index !== false ) {
                            $lock_name = sprintf( 'game_pity_art_%d_period_%d', (int) $artifact->id, (int) $artifact_period_index );
                            if ( ! game_acquire_lock( $lock_name, 1 ) ) {
                                $need_safe = true;
                            } else {
                                if ( ! game_artifact_period_has_quota( $artifact, $artifact_period_index ) ) {
                                    game_release_lock( $lock_name );
                                    $need_safe = true;
                                } else {
                                    $pity_lock_name = $lock_name;
                                }
                            }
                        }
                    }

                    if ( $need_safe ) {
                        $chosen_piece = game_pick_safe_piece( $user_id, $artifact->id, $pieces );
                        if ( ! $chosen_piece ) {
                            // Không còn mảnh safe → fallback điểm
                            $reward = game_build_point_reward();
                        } else {
                            $weight_sum = array_sum(array_column($pieces, 'baseline_weight'));
                            $reward = [
                                'outcome' => 'PIECE',
                                'points_awarded' => 0,
                                'artifact_id' => $artifact->id,
                                'piece_id' => $chosen_piece->id,
                                'piece_code' => $chosen_piece->piece_code,
                                'piece_url' => $chosen_piece->piece_img,
                                'weight_sum' => $weight_sum,
                                'chosen_weight' => $chosen_piece->baseline_weight,
                                'period_index' => $artifact_period_index
                            ];
                        }
                    } else {
                        // User chưa có bộ nào + kỳ còn quota → random theo trọng số bình thường
                        $weight_sum = array_sum(array_column($pieces, 'baseline_weight'));
                        $rand = rand(1, $weight_sum);
                        $current_weight = 0;
                        $chosen_piece = null;
                        $chosen_weight = 0;

                        foreach ($pieces as $piece) {
                            $current_weight += $piece->baseline_weight;
                            if ($rand <= $current_weight) {
                                $chosen_piece = $piece;
                                $chosen_weight = $piece->baseline_weight;
                                break;
                            }
                        }

                        $reward = [
                            'outcome' => 'PIECE',
                            'points_awarded' => 0,
                            'artifact_id' => $artifact->id,
                            'piece_id' => $chosen_piece->id,
                            'piece_code' => $chosen_piece->piece_code,
                            'piece_url' => $chosen_piece->piece_img,
                            'weight_sum' => $weight_sum,
                            'chosen_weight' => $chosen_weight,
                            'period_index' => $artifact_period_index
                        ];
                    }
                }
            }
        }
    }

    // ===== Lưu vào drop_logs =====
    $wpdb->insert(
        $prefix . 'drop_logs',
        [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'order_index' => $order_index,
            'artifact_id' => $reward['artifact_id'] ?? null,
            'piece_id' => $reward['piece_id'] ?? null,
            'outcome' => $reward['outcome'],
            'points_awarded' => $reward['points_awarded'] ?? 0,
            'weight_sum' => $reward['weight_sum'] ?? 0,
            'chosen_weight' => $reward['chosen_weight'] ?? 0,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );

    // ===== Xử lý mảnh =====
    $is_artifact_complete = false;

    if ($reward['outcome'] === 'PIECE') {
        // Cập nhật user_pieces
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, qty FROM {$prefix}user_pieces 
            WHERE user_id = %d AND piece_id = %d",
            $user_id, $reward['piece_id']
        ));

        if ($existing) {
            $wpdb->update(
                $prefix . 'user_pieces',
                ['qty' => $existing->qty + 1],
                ['id' => $existing->id]
            );
            $user_piece_id = $existing->id;
        } else {
            $wpdb->insert(
                $prefix . 'user_pieces',
                [
                    'user_id' => $user_id,
                    'artifact_id' => $reward['artifact_id'],
                    'piece_id' => $reward['piece_id'],
                    'qty' => 1
                ]
            );
            $user_piece_id = $wpdb->insert_id;
        }

        // Ghi log biến động mảnh
        $wpdb->insert(
            $prefix . 'user_pieces_ledger',
            [
                'user_piece_id' => $user_piece_id,
                'ref_type' => !empty($reward['is_pity']) ? 'PITY' : 'REWARD',
                'delta' => 1
            ]
        );

        // ===== Check hoàn thành bộ → Auto Redeem =====
        $is_artifact_complete = game_will_complete_artifact( $user_id, $reward['artifact_id'], $reward['piece_id'] );

        if ( $is_artifact_complete ) {
            // Kiểm tra user đã nhận hiện vật trong đợt game hiện tại chưa
            // (phòng race condition hoặc edge case)
            if ( game_user_has_completed_artifact( $user_id ) ) {
                $is_artifact_complete = false;
            } else {
                // Kiểm tra quota kỳ trước khi auto-redeem
                $can_redeem = true;
                $art_obj = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$prefix}artifacts WHERE id = %d",
                    $reward['artifact_id']
                ) );
                if ( $art_obj ) {
                    $current_period = game_artifact_current_period( $art_obj );
                    if ( $current_period !== false ) {
                        if ( ! game_artifact_period_has_quota( $art_obj, $current_period ) ) {
                            $can_redeem = false; // Kỳ này hết quota → chờ kỳ sau
                            $is_artifact_complete = false;
                        }
                    }
                }

                if ( $can_redeem ) {
                    // ===== AUTO-REDEEM: Trừ mảnh + ghi redemption =====
                    $artifact_id_redeem = $reward['artifact_id'];
                    $redeem_period_index = isset( $reward['period_index'] ) ? $reward['period_index'] : game_artifact_current_period( $art_obj );

                    // Lấy tất cả mảnh user đang có của artifact này (qty >= 1)
                    $user_pieces_for_redeem = $wpdb->get_results( $wpdb->prepare(
                        "SELECT up.id AS user_piece_id, up.piece_id, up.qty, p.piece_code
                         FROM {$prefix}user_pieces up
                         INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
                         WHERE up.user_id = %d AND up.artifact_id = %d AND up.qty >= 1
                         ORDER BY p.piece_code ASC",
                        $user_id, $artifact_id_redeem
                    ) );

                    $redeem_ok = true;

                    // Trừ 1 qty cho mỗi mảnh + log ledger
                    foreach ( $user_pieces_for_redeem as $rp ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE {$prefix}user_pieces SET qty = qty - 1 WHERE id = %d AND qty >= 1",
                            $rp->user_piece_id
                        ) );

                        $wpdb->insert(
                            $prefix . 'user_pieces_ledger',
                            [
                                'user_piece_id' => $rp->user_piece_id,
                                'ref_type'      => 'AUTO_REDEEM',
                                'delta'         => -1,
                                'created_at'    => game_now(),
                            ]
                        );
                    }

                    // Ghi redemption
                    $wpdb->insert(
                        $prefix . 'user_artifact_redemptions',
                        [
                            'user_id'     => $user_id,
                            'artifact_id' => $artifact_id_redeem,
                            'redeemed_at' => game_now(),
                        ]
                    );

                    // Kiểm tra đóng artifact nếu đạt max_redemptions
                    if ( $art_obj && ! empty( $art_obj->max_redemptions ) && $art_obj->max_redemptions > 0 ) {
                        $total_redeemed_now = (int) $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$prefix}user_artifact_redemptions WHERE artifact_id = %d",
                            $artifact_id_redeem
                        ) );
                        if ( $total_redeemed_now >= (int) $art_obj->max_redemptions ) {
                            $wpdb->update(
                                $prefix . 'artifacts',
                                [ 'closed' => 1 ],
                                [ 'id' => $artifact_id_redeem ]
                            );
                        }
                    }
                }
            }
        }

    } else if ($reward['outcome'] === 'POINT' && ($reward['points_awarded'] ?? 0) > 0) {
		// Cập nhật số dư điểm người chơi
		$wpdb->query($wpdb->prepare(
			"INSERT INTO {$prefix}user_points_balances (user_id, balance, updated_at)
			 VALUES (%d, %d, %s)
			 ON DUPLICATE KEY UPDATE
				balance = balance + VALUES(balance),
				updated_at = VALUES(updated_at)",
			$user_id,
			$reward['points_awarded'],
			game_now()
		));

		// Ghi log biến động điểm
		$wpdb->insert(
			$prefix . 'user_points_ledger',
			[
				'user_id' => $user_id,
				'delta' => $reward['points_awarded'],
				'ref_type' => 'SESSION',
				'ref_id' => $session_id,
				'created_at' => game_now()
			]
		);
	}

    $response_data = array(
        'type' => $reward['outcome'],
        'value' => ($reward['outcome'] === 'POINT') ? ($reward['points_awarded'] ?? 0) : ($reward['piece_url'] ?? ''),
        'text' => ($reward['outcome'] === 'POINT') ? "{$reward['points_awarded']} điểm" : "1x Mảnh ghép hiện vật ID {$reward['piece_id']}",
        'artifact_id' => ($reward['outcome'] === 'PIECE') ? $reward['artifact_id'] : 0,
        'piece_code' => ($reward['outcome'] === 'PIECE') ? $reward['piece_code'] : '',
        'is_artifact_complete' => $is_artifact_complete,
    );

    if ($is_artifact_complete && isset($art_obj)) {
        $response_data['artifact_name'] = $art_obj->name;
        $response_data['artifacts_url'] = $art_obj->artifacts_url;
    }

    if ( ! empty( $pity_lock_name ) ) {
        game_release_lock( $pity_lock_name );
    }

    return $response_data;
}

/**
 * Helper: Tạo reward mặc định là điểm.
 */
function game_build_point_reward(): array {
    $point_reward = game_point_per_answer();
    return [
        'outcome' => 'POINT',
        'points_awarded' => $point_reward,
        'artifact_id' => null,
        'piece_id' => null
    ];
}
/**
 *  Helpers lấy phần thưởng của một phiên chơi
 */

function get_session_rewards($session_id) {
    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';
    
    $query = $wpdb->prepare(
        "SELECT 
            COUNT(CASE WHEN outcome = 'PIECE' THEN 1 END) as pieces_count,
            SUM(CASE WHEN outcome = 'POINT' THEN points_awarded ELSE 0 END) as total_points
        FROM {$prefix}drop_logs 
        WHERE session_id = %d",
        $session_id
    );
    
    return $wpdb->get_row($query, ARRAY_A);
}

/**
 *  Helpers lấy phiên chơi có retries_used lớn nhất trong ngày hiện tại của 1 user
 *  Dùng để giới hạn số lần chơi lại trong ngày
 */
function get_max_retries_session_today(int $user_id) {
    global $wpdb;
    $t_session = $wpdb->prefix . 'game_users_play_sessions';

    // Tính khoảng [t0, t1) theo timezone local (vd: Asia/Ho_Chi_Minh), rồi đổi sang UTC nếu DB lưu UTC
    $tzLocal = new DateTimeZone('Asia/Ho_Chi_Minh');
    $start_local  = (new DateTimeImmutable('now', $tzLocal))->setTime(0,0,0);
    $end_local    = $start_local->modify('+1 day');
    $start_utc = $start_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $end_utc = $end_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    // Lấy lượt chơi lại lớn nhất trong ngày của 1 user
    $maxRetries = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(s2.retries_used)
        FROM $t_session s2
        WHERE s2.user_id = %d
        AND s2.started_at >= %s
        AND s2.started_at <  %s",
        $user_id, $start_utc, $end_utc
    ));

    // Ví dụ: ép về số nguyên, dùng 0 nếu NULL
    $maxRetries = is_null($maxRetries) ? 0 : (int)$maxRetries;
    return $maxRetries;
}

/**
 *  Helpers kiểm tra xem người chơi có thể chơi game ngày hiện tại hay không (đã trả lời sai hết số lượt cho phép chưa)
 */
function check_max_retries_session_today(int $user_id) {
    global $wpdb;
    $t_session = $wpdb->prefix . 'game_users_play_sessions';

    // Lấy khoảng [t0, t1) theo timezone local (vd: Asia/Ho_Chi_Minh), rồi đổi sang UTC nếu DB lưu UTC
    $tzLocal = new DateTimeZone('Asia/Ho_Chi_Minh');
    $start_local  = (new DateTimeImmutable('now', $tzLocal))->setTime(0,0,0);
    $end_local    = $start_local->modify('+1 day');
    $start_utc = $start_local->setTimezone($tzLocal)->format('Y-m-d H:i:s');
    $end_utc = $end_local->setTimezone($tzLocal)->format('Y-m-d H:i:s');

    // Lấy tất cả sessions trong ngày, theo thứ tự thời gian (cũ -> mới)
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT retries_used FROM $t_session WHERE user_id = %d AND started_at >= %s AND started_at < %s ORDER BY started_at ASC",
        $user_id, $start_utc, $end_utc
    ));

    // Quy ước mới:
    // - retries_used == 0 : không tính
    // - retries_used == 1 : "light fail"
    // - retries_used >= 2  : "heavy fail"
    // Luật chặn tiếp tục trong ngày:
    // - Nếu có 2 "light fail" (không nhất thiết phải liên tiếp) => chặn ngày
    // - Nếu đã gặp 1 "heavy fail" và sau đó gặp bất kỳ fail nào (light/heavy) => chặn ngày

    $light_fails = 0;
    $heavy_seen = false;
    $total_retries = 0;
    $max_retries_seen = 0;

    foreach ($rows as $r) {
        $ru = (int)($r->retries_used ?? 0);
        // Nếu trả lời sai >=3 lần trong 1 phiên thì chặn luôn
        // if ($ru >= 3) {
        //     return new WP_Error('forbidden', __('You have used all your retries for today. Please try again tomorrow.'), ['status' => 403]);
        // }
        $total_retries += $ru;
        $max_retries_seen = max($max_retries_seen, $ru);
        if ($ru <= 0) continue;

        if ($ru >= 2) {
            // heavy fail
            $heavy_seen = true;
            // heavy alone does NOT immediately block (per requirement)
            continue;
        }

        if ($ru === 1) {
            $light_fails++;
            if ($light_fails >= 2) {
                return new WP_Error('forbidden', __('You have used all your retries for today. Please try again tomorrow.'), ['status' => 403]);
            }
            if ($heavy_seen) {
                // heavy then any subsequent fail => block
                return new WP_Error('forbidden', __('You have used all your retries for today. Please try again tomorrow.'), ['status' => 403]);
            }
        }
    }

    // Không bị chặn -> trả về thông tin tóm tắt để caller có thể dùng
    return array(
        'total_retries_today' => $total_retries,
        'max_retries_today' => $max_retries_seen,
        'light_fails_today' => $light_fails,
        'heavy_fail_seen' => $heavy_seen,
        'max_allowed_retries' => (int)get_option('game_bsc_max_wrong_answers', 0)
    );
}
/**
 *  Helpers kiểm tra xem ngày truyền vào có phải quá khứ hay không
 */
function is_date_before_today(string $str, string $tz = 'Asia/Ho_Chi_Minh'): bool {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $str, new DateTimeZone($tz));
    if (! $dt) return false;

    $today = new DateTime('today', new DateTimeZone($tz)); // 00:00:00 hôm nay
    return $dt < $today; // true nếu ngày của $dt nhỏ hơn ngày hôm nay
}

/**
 * =========================================
 * NONCE & AUTH
 * =========================================
 */
function game_verify_rest_nonce(WP_REST_Request $request)
{
    return true;
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce) $nonce = $request->get_param('_wpnonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_game_rest')) {
        return new WP_Error('forbidden', __('Invalid or missing REST nonce.'), ['status' => 403]);
    }
    return true;
}

function game_auth_current_user_or_401()
{
    $u = game_sso_require_session();
    if (is_wp_error($u) || empty($u['id'])) return new WP_Error('unauthorized', __('Unauthorized access.'), ['status' => 401]);
    return (int)$u['id'];
}

/**
 * Kiểm tra trạng thái user trong database
 * @param int $user_id ID của user
 * @return bool|WP_Error true nếu user active (status=1), WP_Error nếu blocked
 */
function game_check_user_status($user_id)
{
    global $wpdb;
    $t_users = game_tbl('users');
    
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT status FROM $t_users WHERE id = %d",
        $user_id
    ));
    
    if (!$user) {
        return new WP_Error('user_not_found', __('User not found.'), ['status' => 404]);
    }
    
    if ((int)$user->status !== 1) {
        return new WP_Error('user_blocked', __('Your account has been blocked. Please contact administrator.'), ['status' => 403]);
    }
    
    return true;
}

/**
 * =========================================
 * ROUTES
 * =========================================
 */
add_action('rest_api_init', function () {
    register_rest_route(NS, '/session/start',  ['methods' => 'POST', 'callback' => 'game_api_session_start', 'permission_callback' => '__return_true']);
    register_rest_route(NS, '/session/answer', ['methods' => 'POST', 'callback' => 'game_api_session_answer', 'permission_callback' => '__return_true']);
    register_rest_route(NS, '/session/next',   ['methods' => 'POST', 'callback' => 'game_api_session_next', 'permission_callback' => '__return_true']);
    register_rest_route(NS, '/session/result',   ['methods' => 'GET', 'callback' => 'game_api_session_result', 'permission_callback' => '__return_true']);
});

/**
 * =========================================
 * HANDLERS (with concurrency safety)
 * =========================================
 */

/** Tạo phiên mới + trừ 1 lượt + phát câu #1 (dùng chung) — chạy dưới LOCK user */
function game_start_new_session_and_first_question($user_id)
{
	global $wpdb;
    $t_sess = game_tbl('users_play_sessions');
    $t_bal  = game_tbl('play_credit_balances');
    $t_led  = game_tbl('play_credit_ledger');

    // Kiểm tra xem người chơi đã trả lời sai hết số lượt cho phép trong ngày chưa
   $max_retries = check_max_retries_session_today($user_id);
   if (is_wp_error($max_retries)) {
       return wg_json_response(403, ['error_code' => 'not_allowed_to_play'], __('Bạn đã sử dụng hết số lần chơi lại trong ngày. Vui lòng thử lại vào ngày mai.', WG_GAME_PLUGIN_TEXTDOMAIN));
   }
    //  $max_retries = array(
    //      'max_retries_today' => 0,
    //      'max_allowed_retries' => get_option('game_bsc_max_wrong_answers', 0)
    //  );
	// fix: check credit balance
	$affected = $wpdb->get_row($wpdb->prepare(
		"SELECT balance FROM $t_bal WHERE user_id = %d",
		$user_id
	));
	if ((int)$affected->balance < 1) {
		return wg_json_response(403, ['error_code' => 'not_enough_credit'], __('Hãy thực hiện các nhiệm vụ để có thêm lượt chơi', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
    // Bắt đầu transaction
    $wpdb->query('START TRANSACTION');


    // 2) Tạo session

    // Lấy số câu hỏi từ cấu hình stage hiện tại
    $question_count = game_q_per_session();
	
	$last_play_session = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $t_sess WHERE user_id = %d ORDER BY id DESC LIMIT 1",
		$user_id
	),
	ARRAY_A);
	$current_stage = 1;
	if ($last_play_session) {
		$tz = TIMEZONE;
		$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
		$last_session_date = (new DateTimeImmutable($last_play_session['started_at'], $tz))->format('Y-m-d');
		// if ($last_session_date < $today) {
		// 	$last_stage = $last_play_session['current_stage'];
		// 	$current_stage = ((int)$last_stage ?? 0) + 1;
		// }else{
		// 	$current_stage = $last_play_session['current_stage'];
		// }
        if($last_play_session['current_stage_status'] == 1) {
            $last_stage = (int)$last_play_session['current_stage'];
            $current_stage = ($last_stage ?? 0) + 1;
        } else {
            $current_stage = (int)$last_play_session['current_stage'];
        }
        
	}
    $wpdb->insert($t_sess, [
        'user_id'         => $user_id,
        'started_at'      => game_now(),
        'questions_count' => $question_count,
        'allowed_retries' => 2,
        'retries_used'    => 0,
        'correct_count'   => 0,
        'credit_delta'    => -1,
		'current_stage'   => $current_stage,
        'ip'              => $_SERVER['REMOTE_ADDR']     ?? null,
        'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    $sid = (int)$wpdb->insert_id;
    if (!$sid) {
        // Chỉ rollback — tự hoàn lại debit
        $wpdb->query('ROLLBACK');
        return wg_json_response(500, [], __('Không thể tạo phiên chơi. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }



    // 4) Commit — từ đây trở đi không còn hoàn tác debit bằng rollback được nữa
    $wpdb->query('COMMIT');

    // 5) Phát câu #1 (ngoài transaction để giảm thời gian giữ khoá)
    $qid = game_pick_question([]);
    if (!$qid) {
        $wpdb->query('ROLLBACK');
        // Không có câu hỏi: kết thúc phiên để tránh treo
        // $wpdb->update($t_sess, ['finished_at' => game_now()], ['id' => $sid]);
        return wg_json_response(500, [], __('Không có câu hỏi khả dụng. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }
    // Thêm question ID đầu tiên vào cache
    game_add_used_question($sid, $qid);

    return wg_json_response(200, [
        'session'  => ['id' => $sid, 'started_at' => game_now(), 'questions_count' => $question_count, 'point_reward' => 0, 'piece_reward' => 0],
        'question' => game_build_question_payload($qid, 1, $sid)
    ], __('Bắt đầu phiên chơi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}


/**
 * START: chỉ START áp dụng logic đặc biệt — bọc bởi LOCK user để ngăn start đua.
 */
function game_api_session_start(WP_REST_Request $r)
{
	$check_nonce = game_rest_perm_cb($r);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
	
	global $wpdb;
    $uid = game_auth_current_user_or_401();
    if (is_wp_error($uid)) return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));

    // Kiểm tra thời gian chơi game (ngày, giờ, và ngày trong tuần)
    $time_check = game_check_play_time_allowed();
    if (!$time_check['allowed']) {
        return wg_json_response(403, ['error_code' => 'not_in_game_time'], $time_check['message']);
    }

    // Kiểm tra trạng thái user
    $status_check = game_check_user_status($uid);
    if (is_wp_error($status_check)) {
        return wg_json_response(
            $status_check->get_error_data()['status'] ?? 403,
            [],
            $status_check->get_error_message()
        );
    }

    $lock = "game_user_{$uid}";
    if (!game_acquire_lock($lock)) {
        return wg_json_response(429, [], __('Bạn đang thực hiện quá nhiều yêu cầu. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    try {
        // 1) Không có session mở -> tạo mới + phát câu #1
        $open = game_get_open_session_raw($uid);
        if (!$open) {
            return game_start_new_session_and_first_question($uid);
        }

        $sid = (int)$open->id;

        // 2) Kiểm tra đã từng lưu câu hỏi nào cho session này chưa (kể cả unanswered)
        $attempts = game_count_attempts($sid);

        // 2a) Nếu ĐÃ có => kết thúc session cũ & mở session mới
        if ($attempts > 0 || is_date_before_today($open->started_at)) {
            $wpdb->update(game_tbl('users_play_sessions'), ['finished_at' => game_now()], ['id' => $sid]);

            // Clear cache khi session kết thúc
            game_clear_used_questions($sid);

            return game_start_new_session_and_first_question($uid);
        }

        // 2b) Nếu CHƯA có câu nào => phát câu #1 cho session hiện tại
        $qid = game_pick_question([]);
        if (!$qid) {
            return wg_json_response(500, [], __('Không có câu hỏi khả dụng. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
        }

        // Thêm question ID vào cache
        game_add_used_question($sid, $qid);
        
        return wg_json_response(200, [
            'session'  => ['id' => $sid, 'started_at' => $open->started_at, 'questions_count' => (int)$open->questions_count, 'point_reward' => 0, 'piece_reward' => 0],
            'question' => game_build_question_payload($qid, 1, $sid)
        ], __('Tiếp tục phiên chơi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));

    } finally {
        game_release_lock($lock);
    }
}

/**
 * ANSWER:
 *  - Khoá theo session để tuần tự hoá ghi câu trả lời.
 *  - Hỗ trợ timeout (answer rỗng + question_id khi token hết hạn).
 *  - Upsert idempotent: chỉ ghi khi user_answer hiện đang NULL.
 *  - Tăng correct_count đúng 1 lần (khi chuyển từ NULL->có đáp án đúng).
 */
/**
 * Xử lý câu trả lời session game (REST API).
 *
 * @param WP_REST_Request $r
 * @return WP_REST_Response
 */
function game_api_session_answer(WP_REST_Request $r) {
	$check_nonce = game_rest_perm_cb($r);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
    global $wpdb;
    $uid = game_auth_current_user_or_401();
	if (is_wp_error($uid)) return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));

    // Kiểm tra thời gian chơi game (ngày, giờ, và ngày trong tuần)
    $time_check = game_check_play_time_allowed();
    if (!$time_check['allowed']) {
        return wg_json_response(403, ['error_code' => 'not_in_game_time'], $time_check['message']);
    }

    // Kiểm tra trạng thái user
    $status_check = game_check_user_status($uid);
    if (is_wp_error($status_check)) {
        return wg_json_response(
            $status_check->get_error_data()['status'] ?? 403,
            [],
            $status_check->get_error_message()
        );
    }

    // Input
    $sid  = (int)$r->get_param('sessionId');
    $ord  = (int)$r->get_param('orderIndex');
    $tok  = (string)$r->get_param('questionToken');
    $ans_raw = $r->get_param('answer');
    $ans  = is_null($ans_raw) ? '' : strtoupper((string)sanitize_text_field($ans_raw));
    $qid_param = (int)$r->get_param('questionId');

    if ($sid <= 0 || $ord <= 0 || $tok === '') {
        return wg_json_response(400, [], __('Tham số không hợp lệ. Vui lòng tải lại trang.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    $t_sessions = game_tbl('users_play_sessions');
    $t_answers  = game_tbl('users_session_answers');
	$t_bal      = game_tbl('play_credit_balances');
	$t_legger   = game_tbl('play_credit_ledger');

    // Kiểm tra session thuộc user và chưa kết thúc
    $sess = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_sessions WHERE id=%d AND user_id=%d", $sid, $uid));
    if (!$sess) return wg_json_response(404, [], __('Không tìm thấy phiên chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));

    if ($sess->finished_at !== null) {
        return wg_json_response(403, [], __('Phiên chơi đã kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    if($ord < 1 || $ord > $sess->questions_count) {
        return wg_json_response(400, [], __('Chỉ số câu hỏi không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    $lock = "game_sess_{$sid}";
    if (!game_acquire_lock($lock)) {
        return wg_json_response(429, [], __('Bạn đang thực hiện quá nhiều yêu cầu. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    $wpdb->query('START TRANSACTION');
    
    try {
	    // trừ 1 lươt chơi
	    // check nếu phiên đó đã trừ lượt chơi thì ko trừ nữa
	    $is_deducted = $wpdb->get_var($wpdb->prepare(
		    "SELECT id FROM $t_legger WHERE ref_type = 'SESSION' AND ref_id = %d",
		    $sid
	    ));
//		print_r($is_deducted);die();
		
		if (empty($is_deducted)) {
			$affected = $wpdb->query($wpdb->prepare(
				"UPDATE $t_bal SET balance = balance - 1, updated_at = %s
         WHERE user_id = %d AND balance >= 1",
				game_now(),
				$uid
			));
			
			if ($affected !== 1) {
				$wpdb->query('ROLLBACK');
				return wg_json_response(403, [], __('Đã có lỗi xảy ra. Vui lòng thử lại sau.'));
			}
			
			// 3) Ghi ledger
			$ok_led = $wpdb->insert($t_legger, [
				'user_id'    => $uid,
				'delta'      => -1,
				'ref_type'   => 'SESSION',
				'ref_id'     => $sid,
				'created_at' => game_now(),
			]);
			if (!$ok_led) {
				$wpdb->query('ROLLBACK');
				return wg_json_response(500, [], __('Không thể ghi nhận lượt chơi. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
			}
		}
        // Re-read session with FOR UPDATE để “ghim” dòng phiên làm việc
        $sess = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $t_sessions WHERE id=%d AND user_id=%d FOR UPDATE", $sid, $uid)
        );
        if (!$sess) {
            throw new RuntimeException('Session missing at write time.');
        }
        if ($sess->finished_at !== null) {
            // Có thể vừa bị kết thúc bởi tiến trình khác
            $wpdb->query('ROLLBACK');
            return wg_json_response(403, [], __('Phiên chơi đã kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN));
        }

        $qid = 0;
        $token_ok = game_q_token_verify($sid, $ord, $tok, $qid);
        
        $is_correct = 0;
        $user_answer = NULL;
        $correct_key = null;
        $message = __('Đã ghi nhận là câu trả lời sai.', WG_GAME_PLUGIN_TEXTDOMAIN);

        if (!$token_ok) {
            $qid = game_q_token_get_qid($sid, $ord);
            // Token hết hạn xử lý như sai/timeout
            if ($qid <= 0) {
                if($qid_param > 0) {
                    // Dùng qid từ param nếu có
                    $qid = $qid_param;
                } else {
                    $wpdb->query('ROLLBACK');
                    return wg_json_response(403, [], __('Câu hỏi đã hết hạn. Không thể ghi nhận câu trả lời.', WG_GAME_PLUGIN_TEXTDOMAIN));
                }
            }
            // $message = __('Hết thời gian, đã ghi nhận là câu trả lời sai.', WG_GAME_PLUGIN_TEXTDOMAIN);
        } else {
            if($qid <= 0) {
                $wpdb->query('ROLLBACK');
                return wg_json_response(403, [], __('Mã câu hỏi không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
            }
            // Token OK
            if ($ans === '' || !in_array($ans, ['A', 'B', 'C', 'D'], true)) {
                // Ans invalid → xử lý như sai
                $message = __('Chưa có câu trả lời hợp lệ, đã ghi nhận là câu trả lời sai.', WG_GAME_PLUGIN_TEXTDOMAIN);
            } else {
                // Ans hợp lệ → chấm điểm
                $judge = game_check_answer($qid, $ans);
                $is_correct = !empty($judge['is_correct']) ? 1 : 0;
                $user_answer = $ans;
                $correct_key = $judge['correct_key'];
                $message = __('Đã ghi nhận câu trả lời.', WG_GAME_PLUGIN_TEXTDOMAIN);
            }
        }

        // Lấy answer hiện có (FOR UPDATE) để tính retry
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $t_answers WHERE session_id=%d AND order_index=%d FOR UPDATE",
                $sid, $ord
            )
        );

        $did_retry        = false;
        $increase_correct = false;

        if ($existing) {
            // Retry: Chỉ cho nếu trước đó sai
            if ((int)$existing->is_correct == 1) {
                // Không thay đổi gì, trả về kết quả hiện có
                $wpdb->query('ROLLBACK');
                return wg_json_response(200, [
                    'correct'          => true,
                    // 'correctKey'       => $existing->user_answer ? null : null, // giữ kín đáp án nếu cần
                    'session_finished'  => false,
                    'retry'            => false,
                ], __('Bạn đã trả lời đúng câu hỏi này rồi.', WG_GAME_PLUGIN_TEXTDOMAIN));
            }
            
            $did_retry = true;

            // Update
            $wpdb->query($wpdb->prepare(
                "UPDATE $t_answers SET
                    attempt_no = attempt_no + 1,
                    is_correct = %d,
                    user_answer = %s,
                    answered_at = %s
                WHERE session_id=%d AND order_index=%d",
                $is_correct,
                $user_answer,
                game_now(),
                $sid,
                $ord
            ));
            if ($wpdb->last_error) throw new RuntimeException($wpdb->last_error);

            if ($is_correct == 1) {
                $increase_correct = true;
            }
        } else {
            // Insert mới
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $t_answers (session_id, question_post_id, order_index, attempt_no, is_correct, user_answer, answered_at)
                VALUES (%d, %d, %d, 1, %d, %s, %s)",
                $sid, $qid, $ord, $is_correct, $user_answer, game_now()
            ));
            if ($wpdb->last_error) throw new RuntimeException($wpdb->last_error);

            if ($is_correct == 1) {
                $increase_correct = true;
            }
        }

        // Cập nhật counters của session (gộp trong 1 hoặc 2 câu UPDATE)
        // if ($did_retry) {
        //     $wpdb->query($wpdb->prepare(
        //         "UPDATE $t_sessions SET retries_used = retries_used + 1 WHERE id=%d",
        //         $sid
        //     ));
        //     if ($wpdb->last_error) throw new RuntimeException($wpdb->last_error);
        // }
        if ($increase_correct) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $t_sessions SET correct_count = correct_count + 1 WHERE id=%d",
                $sid
            ));
            if ($wpdb->last_error) throw new RuntimeException($wpdb->last_error);
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $t_sessions SET retries_used = retries_used + 1 WHERE id=%d",
                $sid
            ));
            if ($wpdb->last_error) throw new RuntimeException($wpdb->last_error);
        }

        // Quyết định đóng session khi hết quyền retry và lần này vẫn sai
        $sess = $wpdb->get_row(
            $wpdb->prepare("SELECT retries_used, allowed_retries FROM $t_sessions WHERE id=%d FOR UPDATE", $sid)
        );

        $session_finished = false;
        $should_retry     = false;

        if ((int)$is_correct !== 1) {
            if ((int)$sess->retries_used >= (int)$sess->allowed_retries) {
                // Tăng thêm retries_used 1 lần nữa để đánh dấu là đã dùng hết retry để dễ hơn cho việc check ngày hiện tại đã  trả lời sai chưa để không cho chơi ngày hiện tại nữa
                $wpdb->query($wpdb->prepare(
                    "UPDATE $t_sessions SET finished_at=%s WHERE id=%d AND finished_at IS NULL",
                    game_now(), $sid
                ));
                if ($wpdb->last_error) throw new RuntimeException($wpdb->last_error);
                $session_finished = true;
            } else {
                // Kiểm tra xem người chơi đã trả lời sai hết số lượt cho phép trong ngày chưa
                $max_retries = check_max_retries_session_today($uid);
                if (is_wp_error($max_retries)) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $t_sessions SET finished_at=%s WHERE id=%d AND finished_at IS NULL",
                        game_now(), $sid
                    ));
                    if ($wpdb->last_error) throw new RuntimeException($wpdb->last_error);
                    $session_finished = true;
                } else {
                    $should_retry = true;
                }
            }
        } else {
            // Nếu trả lời đúng lấy phần thưởng ngẫu nhiên
            $reward = game_get_random_reward($uid, $sid, $ord);
        }

        $wpdb->query('COMMIT');

        
        $resp = [
            'correct' => (bool)$is_correct,
            // 'correctKey' => $correct_key,
            'session_finished' => $session_finished,
            'retry' => $should_retry,
        ];

        if(isset($reward)) {
            $resp['reward'] = $reward;
        }

        if ($should_retry) {
            // Reset/create a fresh question token and include time_left
            $new_token = game_q_token_make($sid, $ord, $qid);
            $resp['question_token'] = $new_token;
//            $resp['time_left'] = game_secs_per_q();
        }

        return wg_json_response(200, $resp, $message);
    } catch (\Throwable $e) {
        $wpdb->query('ROLLBACK');
        return wg_json_response(500, [], __('Lỗi hệ thống. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
    } finally {
        game_release_lock($lock);
    }
}

/**
 * NEXT: bình thường, nhưng khoá theo session để tránh 2 NEXT đồng thời phát 2 câu khác nhau.
 */
function game_api_session_next(WP_REST_Request $r)
{
    global $wpdb;
	$check_nonce = game_rest_perm_cb($r);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
    $uid = game_auth_current_user_or_401();
	if (is_wp_error($uid)) return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));
    
    // Kiểm tra thời gian chơi game (ngày, giờ, và ngày trong tuần)
    $time_check = game_check_play_time_allowed();
    if (!$time_check['allowed']) {
        return wg_json_response(403, ['error_code' => 'not_in_game_time'], $time_check['message']);
    }
    
    // Kiểm tra trạng thái user
    $status_check = game_check_user_status($uid);
    if (is_wp_error($status_check)) {
        return wg_json_response(
            $status_check->get_error_data()['status'] ?? 403,
            [],
            $status_check->get_error_message()
        );
    }
    
    $sid = (int)$r->get_param('sessionId');
    if (!$sid) return wg_json_response(400, [], __('Thiếu mã phiên chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));

    $t = game_tbl('users_play_sessions');
    $sess = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d AND user_id=%d", $sid, $uid));
    if (!$sess) return wg_json_response(404, [], __('Không tìm thấy phiên chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));

    if ($sess->finished_at !== null) {
        return wg_json_response(403, [], __('Phiên chơi đã kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }
    
    $lock = "game_sess_{$sid}";
    if (!game_acquire_lock($lock)) {
        return wg_json_response(429, [], __('Bạn đang thực hiện quá nhiều yêu cầu. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }
    try {
        // Kiểm tra xem người chơi đã trả lời sai hết số lượt cho phép trong ngày chưa
       $max_retries = check_max_retries_session_today($uid);
       if (is_wp_error($max_retries)) {
           if(empty($sess->finished_at)) {
               $wpdb->update($t, ['finished_at' => game_now()], ['id' => $sid]);
               // Clear cache khi session kết thúc
               game_clear_used_questions($sid);

               return wg_json_response(200, [
                   'session_finished' => true,
                   'summary' => ['correct_count' => (int)$sess->correct_count, 'questions_total' => (int)$sess->questions_count]
               ], __('Phiên chơi đã kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN));
           }
           return wg_json_response(403, ['error_code' => 'not_allowed_to_play'], __('Bạn đã sử dụng hết số lần chơi lại trong ngày. Vui lòng thử lại vào ngày mai.', WG_GAME_PLUGIN_TEXTDOMAIN));
       }
        $attempts = game_count_attempts($sid);
        $total    = (int)$sess->questions_count;

        if ($attempts >= $total) {
            if (empty($sess->finished_at)) {
                $wpdb->update($t, ['finished_at' => game_now(), 'current_stage_status' => 1], ['id' => $sid]);
                // Clear cache khi session kết thúc
                game_clear_used_questions($sid);
            }

            return wg_json_response(200, [
                'session_finished' => true,
                'summary' => ['correct_count' => (int)$sess->correct_count, 'questions_total' => $total]
            ], __('Phiên chơi đã kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN));
        }

        $next_order = $attempts + 1;
        $qid = game_pick_question(game_get_used_questions($sid));
	    if (!$qid) return wg_json_response(500, [], __('Không có câu hỏi khả dụng. Vui lòng liên hệ quản trị viên.', WG_GAME_PLUGIN_TEXTDOMAIN));
        // Thêm question ID vào cache
        game_add_used_question($sid, $qid);

        // Lấy phần thưởng đã nhận của lượt chơi này
        $rewards = get_session_rewards($sid);

        return wg_json_response(200, [
            'session_finished' => false,
            'session' => ['id' => $sid, 'started_at' => $sess->started_at, 'questions_count' => $total, 'point_reward' => $rewards['total_points'], 'piece_reward' => $rewards['pieces_count']],
            'question' => game_build_question_payload($qid, $next_order, $sid)
        ], __('Lấy câu hỏi tiếp theo thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
    } finally {
        game_release_lock($lock);
    }
}

/** RESULT: chỉ đọc (không cần lock) */
function game_api_session_result(WP_REST_Request $r)
{
    global $wpdb;
	$check_nonce = game_rest_perm_cb($r);
	if (!$check_nonce){
		return wg_json_response(403, [], __('Yêu cầu không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
	}
    $uid = game_auth_current_user_or_401();
    if (is_wp_error($uid)) return wg_json_response(401, ['login_url' => bsc_game_url_sso()], __('Bạn chưa đăng nhập. Vui lòng đăng nhập để tiếp tục.', WG_GAME_PLUGIN_TEXTDOMAIN));

    $sid = (int)$r->get_param('sessionId');
    if (!$sid) return wg_json_response(400, [], __('Thiếu mã phiên chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));

    $t_sess = game_tbl('users_play_sessions');
    $t_drop = game_tbl('drop_logs');

    $sess = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_sess WHERE id=%d AND user_id=%d", $sid, $uid));
    if (!$sess) return wg_json_response(404, [], __('Không tìm thấy phiên chơi.', WG_GAME_PLUGIN_TEXTDOMAIN));

    if (empty($sess->finished_at)) {
        return wg_json_response(403, [], __('Phiên chơi chưa kết thúc.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    // Tổng điểm: tổng points_awarded trong drop_logs cho session này (outcome = 'POINT')
    $total_points = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(points_awarded),0) FROM $t_drop WHERE session_id=%d AND user_id=%d AND outcome='POINT'",
        $sid,
        $uid
    ));

    // Tổng mảnh: đếm các bản ghi outcome = 'PIECE' cho session này
    $total_pieces = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t_drop WHERE session_id=%d AND user_id=%d AND outcome='PIECE'",
        $sid,
        $uid
    ));

    // Chặng hiện tại
    $current_stage = game_bsc_compute_day_index_v2($uid);

    // ===== Kiểm tra trúng hiện vật trong phiên chơi này =====
    $t_redemptions = game_tbl('user_artifact_redemptions');
    $t_artifacts   = game_tbl('artifacts');
    $t_drop        = game_tbl('drop_logs');

    // Tìm artifact_won thông qua drop_logs:
    //   1. Lấy artifact_id có mảnh rơi trong session này
    //   2. Join với redemptions của cùng user trong khoảng thời gian session hiện tại
    // Nếu chỉ join theo user_id + artifact_id, session sau có thể báo lại redemption cũ.
    $artifact_won = $wpdb->get_row( $wpdb->prepare(
        "SELECT r.artifact_id, r.redeemed_at, a.name AS artifact_name, a.artifacts_url
         FROM $t_drop d
         INNER JOIN $t_redemptions r
            ON r.user_id = d.user_id AND r.artifact_id = d.artifact_id
         INNER JOIN $t_artifacts a ON a.id = r.artifact_id
         WHERE d.session_id = %d
           AND d.user_id = %d
           AND d.outcome = 'PIECE'
           AND r.redeemed_at >= %s
           AND r.redeemed_at <= %s
         ORDER BY r.redeemed_at DESC
         LIMIT 1",
        $sid,
        $uid,
        $sess->started_at,
        $sess->finished_at
    ) );

    $resp = [
        'session' => [
            'id' => $sid,
            'started_at' => $sess->started_at,
            'finished_at' => $sess->finished_at,
            'questions_total' => (int)$sess->questions_count,
            'correct_count' => (int)$sess->correct_count,
            'total_points' => $total_points,
            'total_pieces' => $total_pieces,
            'current_stage' => $current_stage['day_index'],
            'status' => (int)$sess->current_stage_status,
        ],
        'artifact_won' => $artifact_won ? [
            'artifact_id'   => (int) $artifact_won->artifact_id,
            'artifact_name' => $artifact_won->artifact_name,
            'artifacts_url' => $artifact_won->artifacts_url,
            'redeemed_at'   => $artifact_won->redeemed_at,
        ] : null,
    ];




    return wg_json_response(200, $resp, __('Lấy kết quả phiên chơi thành công.', WG_GAME_PLUGIN_TEXTDOMAIN));
}
