<?php
/**
 * Artifact Period Helper Functions
 *
 * Business logic cho thời hạn hiện vật, kỳ tung quà, pity system,
 * và giới hạn 1 hiện vật / user / game.
 *
 * @since 1.0
 */
if (!defined('ABSPATH')) {
    exit;
}

// =====================================================================
//  PERIOD CALCULATION (chia kỳ theo giây)
// =====================================================================

/**
 * Xác định kỳ hiện tại của 1 hiện vật (0-indexed).
 *
 * @param object $artifact  Row từ wp_game_artifacts (cần period_start, period_end, total_periods)
 * @return int|false  Chỉ số kỳ (0, 1, 2...) hoặc false nếu ngoài thời hạn hoặc không có khai báo
 */
function game_artifact_current_period( object $artifact ) {
    if ( empty( $artifact->period_start ) || empty( $artifact->period_end ) ) {
        return false;
    }

    $tz    = new DateTimeZone( 'Asia/Ho_Chi_Minh' );
    $now   = new DateTimeImmutable( 'now', $tz );
    $start = new DateTimeImmutable( $artifact->period_start, $tz );
    $end   = new DateTimeImmutable( $artifact->period_end, $tz );

    // Ngoài thời hạn
    if ( $now < $start || $now > $end ) {
        return false;
    }

    $total_periods = max( 1, (int) $artifact->total_periods );
    $total_seconds = $end->getTimestamp() - $start->getTimestamp();

    if ( $total_seconds <= 0 ) {
        return 0;
    }

    $period_length = (int) floor( $total_seconds / $total_periods );
    if ( $period_length <= 0 ) {
        return 0;
    }

    $elapsed      = $now->getTimestamp() - $start->getTimestamp();
    $period_index = min( (int) floor( $elapsed / $period_length ), $total_periods - 1 );

    return $period_index;
}

/**
 * Trả về ngày bắt đầu/kết thúc của 1 kỳ cụ thể.
 *
 * @param object $artifact     Row từ wp_game_artifacts
 * @param int    $period_index Chỉ số kỳ (0-indexed)
 * @return array ['start' => DateTimeImmutable, 'end' => DateTimeImmutable]|false
 */
function game_artifact_period_dates( object $artifact, int $period_index ) {
    if ( empty( $artifact->period_start ) || empty( $artifact->period_end ) ) {
        return false;
    }

    $tz    = new DateTimeZone( 'Asia/Ho_Chi_Minh' );
    $start = new DateTimeImmutable( $artifact->period_start, $tz );
    $end   = new DateTimeImmutable( $artifact->period_end, $tz );

    $total_periods = max( 1, (int) $artifact->total_periods );
    $total_seconds = $end->getTimestamp() - $start->getTimestamp();

    if ( $total_seconds <= 0 ) {
        return false;
    }

    $period_length  = (int) floor( $total_seconds / $total_periods );
    $period_start   = $start->modify( '+' . ( $period_index * $period_length ) . ' seconds' );

    // Kỳ cuối cùng: kết thúc đúng period_end
    if ( $period_index >= $total_periods - 1 ) {
        $period_end = $end;
    } else {
        $period_end = $start->modify( '+' . ( ( $period_index + 1 ) * $period_length ) . ' seconds' );
    }

    return [
        'start' => $period_start,
        'end'   => $period_end,
    ];
}

/**
 * Kiểm tra kỳ hiện tại còn quota đổi hiện vật không.
 * Quota = số bộ hoàn chỉnh (redemptions) trong kỳ.
 *
 * @param object $artifact     Row từ wp_game_artifacts
 * @param int    $period_index Chỉ số kỳ hiện tại
 * @return bool  true = còn chỗ, false = đã đầy
 */
function game_artifact_period_has_quota( object $artifact, int $period_index ): bool {
    $max_per_period = (int) ( $artifact->max_redemptions_per_period ?? 0 );

    if ( $max_per_period <= 0 ) {
        return true; // Không giới hạn
    }

    // Lấy ngày bắt đầu/kết thúc của kỳ
    $dates = game_artifact_period_dates( $artifact, $period_index );
    if ( ! $dates ) {
        return true;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'game_user_artifact_redemptions';

    $period_start_str = $dates['start']->format( 'Y-m-d H:i:s' );
    $period_end_str   = $dates['end']->format( 'Y-m-d H:i:s' );

    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE artifact_id = %d
           AND redeemed_at >= %s
           AND redeemed_at <= %s",
        $artifact->id,
        $period_start_str,
        $period_end_str
    ) );

    return $count < $max_per_period;
}

// =====================================================================
//  USER COMPLETION & SAFE-PIECE
// =====================================================================

/**
 * Kiểm tra xem user đã hoàn thành (redeem) 1 bộ hiện vật trong đợt game hiện tại chưa.
 *
 * Chỉ đếm redemption nằm trong khoảng game_bsc_start_date → game_bsc_end_date.
 * Nếu redemption cũ thuộc đợt game trước (ngoài khoảng ngày hiện tại) → bỏ qua,
 * cho phép user nhận hiện vật mới trong đợt game mới.
 *
 * @param int $user_id
 * @return bool  true = đã có 1 bộ hoàn chỉnh trong đợt game hiện tại
 */
function game_user_has_completed_artifact( int $user_id ): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'game_user_artifact_redemptions';

    $start_date = get_option( 'game_bsc_start_date', '' );
    $end_date   = get_option( 'game_bsc_end_date', '' );

    // Nếu chưa cấu hình ngày game → fallback check toàn bộ (an toàn)
    if ( empty( $start_date ) || empty( $end_date ) ) {
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ) );
        return $count > 0;
    }

    // Chỉ đếm redemption trong khoảng đợt game hiện tại
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE user_id = %d
           AND redeemed_at >= %s
           AND redeemed_at < %s",
        $user_id,
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
    ) );

    return $count > 0;
}

/**
 * Kiểm tra nếu trao piece_id cho user thì user có đủ 4/4 mảnh của artifact đó không.
 *
 * @param int $user_id
 * @param int $artifact_id
 * @param int $piece_id     Mảnh sắp gán
 * @return bool  true = sẽ đủ 4 mảnh (hoàn chỉnh)
 */
function game_will_complete_artifact( int $user_id, int $artifact_id, int $piece_id ): bool {
    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';

    // Đếm số mảnh khác nhau user đang có (qty >= 1)
    $owned = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT up.piece_id)
         FROM {$prefix}user_pieces up
         INNER JOIN {$prefix}pieces p ON p.id = up.piece_id
         WHERE up.user_id = %d AND p.artifact_id = %d AND up.qty >= 1",
        $user_id, $artifact_id
    ) );

    // Kiểm tra user đã có mảnh này chưa
    $already_has = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}user_pieces
         WHERE user_id = %d AND piece_id = %d AND qty >= 1",
        $user_id, $piece_id
    ) );

    // Tổng số mảnh của hiện vật
    $total_pieces = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}pieces WHERE artifact_id = %d",
        $artifact_id
    ) );

    // Nếu user đã có mảnh này rồi → thêm 1 sẽ không tăng số distinct
    if ( $already_has > 0 ) {
        return $owned >= $total_pieces;
    }

    // User chưa có mảnh này → +1 distinct
    return ( $owned + 1 ) >= $total_pieces;
}

/**
 * Chọn mảnh "safe" cho user — mảnh không làm đủ 4/4 ở bộ khác.
 * Ưu tiên chọn mảnh user đã có (duplicate) để không block game flow.
 *
 * @param int   $user_id
 * @param int   $artifact_id
 * @param array $pieces       Danh sách mảnh (objects với id, piece_code, baseline_weight)
 * @return object|null  Mảnh được chọn hoặc null nếu không có mảnh safe
 */
function game_pick_safe_piece( int $user_id, int $artifact_id, array $pieces ): ?object {
    // Lọc ra những mảnh "safe" (không làm đủ 4/4)
    $safe_pieces = [];
    foreach ( $pieces as $piece ) {
        if ( ! game_will_complete_artifact( $user_id, $artifact_id, $piece->id ) ) {
            $safe_pieces[] = $piece;
        }
    }

    if ( empty( $safe_pieces ) ) {
        return null; // Không còn mảnh safe → caller sẽ fallback điểm
    }

    // Weighted random trong safe pieces
    $weight_sum = array_sum( array_column( $safe_pieces, 'baseline_weight' ) );
    if ( $weight_sum <= 0 ) {
        return $safe_pieces[ array_rand( $safe_pieces ) ];
    }

    $rand           = rand( 1, $weight_sum );
    $current_weight = 0;
    foreach ( $safe_pieces as $piece ) {
        $current_weight += (int) $piece->baseline_weight;
        if ( $rand <= $current_weight ) {
            return $piece;
        }
    }

    return $safe_pieces[0]; // Fallback
}

// =====================================================================
//  PITY SYSTEM
// =====================================================================

/**
 * Kiểm tra Pity: user có đúng 3/4 mảnh khác nhau của artifact.
 * Nếu có → trả mảnh còn thiếu.
 *
 * @param int    $user_id
 * @param object $artifact  Row từ wp_game_artifacts
 * @return object|null  Mảnh còn thiếu (piece object) hoặc null nếu không đủ điều kiện pity
 */
function game_check_pity( int $user_id, object $artifact ): ?object {
    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';

    // Lấy tất cả mảnh của hiện vật
    $all_pieces = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, piece_code FROM {$prefix}pieces WHERE artifact_id = %d ORDER BY id",
        $artifact->id
    ) );

    if ( count( $all_pieces ) < 4 ) {
        return null; // Hiện vật chưa đủ 4 mảnh
    }

    // Lấy mảnh user đang có (qty >= 1, distinct piece_id)
    $owned_piece_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT up.piece_id
         FROM {$prefix}user_pieces up
         WHERE up.user_id = %d AND up.artifact_id = %d AND up.qty >= 1",
        $user_id, $artifact->id
    ) );

    $owned_piece_ids = array_map( 'intval', $owned_piece_ids );

    // Phải có đúng 3 mảnh khác nhau
    if ( count( $owned_piece_ids ) !== 3 ) {
        return null;
    }

    // Xác định mảnh còn thiếu
    $missing_piece = null;
    foreach ( $all_pieces as $piece ) {
        if ( ! in_array( (int) $piece->id, $owned_piece_ids, true ) ) {
            $missing_piece = $piece;
            break;
        }
    }

    if ( ! $missing_piece ) {
        return null;
    }

    // Lấy full piece object (cần baseline_weight, piece_img)
    $full_piece = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}pieces WHERE id = %d",
        $missing_piece->id
    ) );

    return $full_piece;
}

// =====================================================================
//  TIME CHECK
// =====================================================================

/**
 * Kiểm tra hiện vật có đang trong thời hạn hoạt động không.
 * Nếu hiện vật không có khai báo period_start/period_end thì coi như luôn hoạt động.
 *
 * @param object $artifact  Row từ wp_game_artifacts
 * @return bool  true = đang trong thời hạn hoặc không có thời hạn
 */
function game_artifact_is_within_period( object $artifact ): bool {
    if ( empty( $artifact->period_start ) || empty( $artifact->period_end ) ) {
        return true; // Artifact cũ không có thời hạn → luôn active
    }

    $tz  = new DateTimeZone( 'Asia/Ho_Chi_Minh' );
    $now = new DateTimeImmutable( 'now', $tz );
    $start = new DateTimeImmutable( $artifact->period_start, $tz );
    $end   = new DateTimeImmutable( $artifact->period_end, $tz );

    return ( $now >= $start && $now <= $end );
}
