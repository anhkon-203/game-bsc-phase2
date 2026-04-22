<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tạo các bảng DB khi active plugin
 */
function game_bsc_install_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $prefix = $wpdb->prefix . 'game_';

    $tables = [];

    /* =========================
        USERS & LOGIN LOG
    ========================== */

    // Bảng users
    $tables[$prefix . 'users'] = "CREATE TABLE {$prefix}users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        provider VARCHAR(32) NOT NULL,                  -- nhà cung cấp SSO
        external_user_id VARCHAR(128) NOT NULL,         -- id user bên SSO
        name VARCHAR(255) NOT NULL,
        avatar_url VARCHAR(255) DEFAULT NULL,
        afacctno VARCHAR(32) DEFAULT NULL,              -- số tiểu khoản thường cơ sở (BSC /trade/accounts)
        status TINYINT(1) NOT NULL DEFAULT 1,           -- 1: active, 0: blocked
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_external_user_id (external_user_id)
    ) ENGINE=InnoDB $charset_collate;"; 

    // user_login_logs (log kiểm tra/đăng nhập SSO)
    $tables[$prefix . 'user_login_logs'] = "CREATE TABLE {$prefix}user_login_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        provider VARCHAR(32) NOT NULL, -- Bên cung cấp SSO
        checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian đăng nhập
        result ENUM('OK','FAIL') NOT NULL, -- Kết quả
        ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        raw TEXT NULL, -- JSON kết quả từ API
        PRIMARY KEY (id),
        KEY idx_user_checked (user_id, checked_at)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng lưu tokens xác thực của plugin (opaque tokens, hashed)
    $tables[$prefix . 'user_tokens'] = "CREATE TABLE {$prefix}user_tokens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        PRIMARY KEY (id),
        KEY idx_user_id (user_id),
        KEY idx_token_hash (token_hash)
    ) ENGINE=InnoDB $charset_collate;";
    /* =========================
       QUESTION IMPORT
    ========================== */

    // Bảng lưu lịch sử tải lên câu hỏi bằng excel
    $tables[$prefix . 'question_upload_history'] = "CREATE TABLE {$prefix}question_upload_history (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, -- id của bảng
        file_name VARCHAR(255) NOT NULL, -- Tên file đã upload
        file_url VARCHAR(255) NOT NULL, -- Link file đã upload
        file_author INT UNSIGNED NOT NULL, -- ID người upload
        upload_message TEXT, -- Thông điệp sau khi upload
        uploaded_at DATETIME NOT NULL, -- Thời gian upload
        PRIMARY KEY (id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng lưu lịch sử import points_cost voucher
    $tables[$prefix . 'voucher_points_import_history'] = "CREATE TABLE {$prefix}voucher_points_import_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        file_name VARCHAR(255) NOT NULL,
        file_url VARCHAR(255) NULL,
        file_author INT UNSIGNED NOT NULL,
        mode VARCHAR(20) NOT NULL DEFAULT 'dry-run',
        total_rows INT UNSIGNED NOT NULL DEFAULT 0,
        updated_rows INT UNSIGNED NOT NULL DEFAULT 0,
        skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
        conflict_rows INT UNSIGNED NOT NULL DEFAULT 0,
        error_rows INT UNSIGNED NOT NULL DEFAULT 0,
        summary_json LONGTEXT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_file_author (file_author),
        KEY idx_uploaded_at (uploaded_at)
    ) ENGINE=InnoDB $charset_collate;";

    /* =========================
       ARTIFACTS & PIECES & USER_PIECES
    ========================== */

    //Bảng hiện vật
    $tables[$prefix . 'artifacts'] = "CREATE TABLE {$prefix}artifacts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        artifacts_url VARCHAR(255) NOT NULL,
        max_redemptions INT UNSIGNED NOT NULL DEFAULT 0,
        period_start DATETIME NULL COMMENT 'Ngày bắt đầu hiện vật',
        period_end DATETIME NULL COMMENT 'Ngày kết thúc hiện vật',
        total_periods INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số kỳ tung quà',
        max_redemptions_per_period INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số bộ tối đa mỗi kỳ (0=vô hạn)',
        status TINYINT(1) NOT NULL DEFAULT 0, -- 1=Mở (hiển thị), 0=Đóng
        closed TINYINT(1) NOT NULL DEFAULT 0,     -- 1 = đã hết suất (không rơi mảnh), 0 = còn suất
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng mảnh ghép hiện vật
    $tables[$prefix . 'pieces'] = "CREATE TABLE {$prefix}pieces (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        artifact_id INT UNSIGNED NOT NULL,
        piece_code VARCHAR(2) NOT NULL, -- P1..P4
        baseline_weight TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0..100
        piece_img VARCHAR(255) NOT NULL, -- url ảnh của mảnh
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_artifact_piece (artifact_id, piece_code),
        KEY idx_artifact (artifact_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng kho mảnh của user
    $tables[$prefix . 'user_pieces'] = "CREATE TABLE {$prefix}user_pieces (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        artifact_id INT UNSIGNED NOT NULL,
        piece_id INT UNSIGNED NOT NULL,
        qty INT NOT NULL DEFAULT 1, -- Số lượng mảnh hiện có
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_piece_uk (user_id, piece_id)
    ) ENGINE=InnoDB $charset_collate;";


    // Bảng log biến động mảnh của user
    $tables[$prefix . 'user_pieces_ledger'] = "CREATE TABLE {$prefix}user_pieces_ledger (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_piece_id INT UNSIGNED NOT NULL,
        ref_type ENUM('REWARD', 'CHANGE') NOT NULL, -- Loại biến động: REWARD là được thưởng mảnh từ trả lời câu hỏi, CHANGE là đổi mảnh lấy hiện vật
        delta INT NOT NULL, -- Số mảnh cộng/trừ
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_piece_created (user_piece_id, created_at)
    ) ENGINE=InnoDB $charset_collate;";

    // Đếm mảnh/ngày mỗi user
    // $tables[$prefix . 'user_daily_counters'] = "CREATE TABLE {$prefix}user_daily_counters (
    //     id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    //     user_id INT UNSIGNED NOT NULL,
    //     counter_date DATE NOT NULL,
    //     pieces_awarded INT NOT NULL DEFAULT 0,
    //     PRIMARY KEY (id),
    //     UNIQUE KEY user_date_uk (user_id, counter_date),
    //     KEY date_idx (counter_date)
    // ) ENGINE=InnoDB $charset_collate;";

    // Bảng Pity/Smart-drop theo user-artifact
    $tables[$prefix . 'user_progress'] = "CREATE TABLE {$prefix}user_progress (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        artifact_id INT UNSIGNED NOT NULL,
        attempts INT NOT NULL DEFAULT 0, -- Số lần trả lời đúng (dùng để tính pity)
        last_piece_at DATETIME NULL, -- Thời gian nhận mảnh gần nhất
        PRIMARY KEY (id),
        UNIQUE KEY user_artifact_uk (user_id, artifact_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Log đổi hiện vật
    $tables[$prefix . 'user_artifact_redemptions'] = "CREATE TABLE {$prefix}user_artifact_redemptions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        artifact_id INT UNSIGNED NOT NULL, 
        redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_artifact_time (user_id, artifact_id, redeemed_at)
    ) ENGINE=InnoDB $charset_collate;";

    /* =========================
               MISSIONS 
    ========================== */
    // Logs nhiệm vụ của user
    $tables[$prefix . 'user_mission_logs'] = "CREATE TABLE {$prefix}user_mission_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        mission_code VARCHAR(32) NOT NULL,
        mission_date DATE NOT NULL,
        reward_type ENUM('PLAY_CREDIT','POINTS') NOT NULL DEFAULT 'PLAY_CREDIT',
        reward_value INT NOT NULL DEFAULT 0,
        status ENUM('VERIFIED','FAILED') NOT NULL,
        verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        api_status INT NULL,
        api_payload MEDIUMTEXT NULL,
        viewed TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_user_mission_day (user_id, mission_code, mission_date)
    ) ENGINE=InnoDB  $charset_collate;";

    /* =========================
             PLAY CREDITS
    ========================== */

    // Bảng lưu số lượt chơi còn lại của user
    $tables[$prefix . 'play_credit_balances'] = "CREATE TABLE {$prefix}play_credit_balances (
        user_id INT UNSIGNED NOT NULL,
        balance INT NOT NULL DEFAULT 0, -- số lượt còn lại
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Log biến động lượt chơi của user
    $tables[$prefix . 'play_credit_ledger'] = "CREATE TABLE {$prefix}play_credit_ledger (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        delta INT NOT NULL, -- Số lượt chơi cộng/trừ, VD: +5, -1
        ref_type ENUM('MISSION','SESSION') NOT NULL, -- Lý do biến động: Nhiệm vụ/chơi game
        ref_id INT UNSIGNED NULL, -- id bản ghi tham chiếu (vd. user_mission_logs.id hoặc  play_sessions.id)
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB $charset_collate;";

    /* =========================
       PLAY SESSIONS + ANSWERS
    ========================== */

    // Bảng phiên chơi của user 
    $tables[$prefix . 'users_play_sessions'] = "CREATE TABLE {$prefix}users_play_sessions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian bắt đầu lượt chơi
        finished_at DATETIME NULL, -- Thời gian kết thúc lượt chơi
        questions_count TINYINT UNSIGNED NOT NULL, -- Số câu hỏi cho lượt chơi
        allowed_retries TINYINT UNSIGNED NOT NULL, -- Tổng số lần được phép trả lời lại cho lượt chơi
        retries_used TINYINT UNSIGNED NOT NULL DEFAULT 0, -- Số lần trả lời sai trong lượt chơi
        correct_count TINYINT UNSIGNED NOT NULL DEFAULT 0, -- Tổng số câu trả lời đúng trong lượt này
        credit_delta INT NOT NULL DEFAULT -1, -- Ghi nhận trừ 1 lượt khi mở phiên - để đối soát
        ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        current_stage INT UNSIGNED NULL,
        current_stage_status TINYINT(1) NOT NULL DEFAULT 0, -- Trạng thái hoàn thành stage hiện tại: 1=hoàn thành, 0=chưa
        PRIMARY KEY (id),
        KEY idx_user_started (user_id, started_at)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng lưu chi tiết câu trả lời của user trong phiên chơi
    $tables[$prefix . 'users_session_answers'] = "CREATE TABLE {$prefix}users_session_answers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id INT UNSIGNED NOT NULL, -- ID phiên trả lời câu hỏi
        question_post_id INT UNSIGNED NOT NULL, -- ID câu hỏi
        order_index TINYINT UNSIGNED NOT NULL, -- Số thứ tự câu trong phiên: 1/2/3
        attempt_no TINYINT UNSIGNED NOT NULL, -- Số lần thử để trả lời câu hỏi 1 hoặc 2
        is_correct TINYINT(1) NOT NULL, -- Kết quả - 1: đúng/0: sai
        user_answer char(1) NULL, -- Đáp án của người chơi
        answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời điểm trả lời
        PRIMARY KEY (id),
        UNIQUE KEY uniq_attempt (session_id, order_index)
    ) ENGINE=InnoDB $charset_collate;";

    /* =========================
       REWARD LOG PER QUESTION (mảnh/điểm)
    ========================== */

    // Bảng log rơi mảnh/điểm khi trả lời câu hỏi
    $tables[$prefix . 'drop_logs'] = "CREATE TABLE {$prefix}drop_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        session_id INT UNSIGNED NOT NULL, -- id của phiên trả lời câu hỏi
        order_index TINYINT UNSIGNED NOT NULL, -- Thứ tự của câu hỏi trong phiên 1/2/3
        artifact_id INT UNSIGNED NULL, -- id hiện vật - Nếu rơi mảnh
        piece_id INT UNSIGNED NULL, -- id mảnh - Nếu rơi mảnh
        outcome ENUM('PIECE','POINT') NOT NULL, -- Loại thưởng: mảnh/điểm
        points_awarded INT NOT NULL DEFAULT 0, -- số điểm tặng - Nếu rơi điểm
        weight_sum INT NOT NULL DEFAULT 0, -- Tổng trọng số tại thời điểm rơi audit
        chosen_weight INT NOT NULL DEFAULT 0, -- Trọng số của mảnh trúng audit
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_session_question (session_id, order_index),
        KEY idx_outcome_created (outcome, created_at),
        KEY idx_user_outcome_created (user_id, outcome, created_at)
    ) ENGINE=InnoDB $charset_collate;";

    /* =========================
       POINTS (balances + ledger)
    ========================== */

    // Bảng lưu số điểm hiện có của user
    $tables[$prefix . 'user_points_balances'] = "CREATE TABLE {$prefix}user_points_balances (
        user_id INT UNSIGNED NOT NULL,
        balance INT NOT NULL DEFAULT 0, -- số điểm hiện còn
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng log biến động điểm của user
    $tables[$prefix . 'user_points_ledger'] = "CREATE TABLE {$prefix}user_points_ledger (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        delta INT NOT NULL, -- Số điểm cộng/trừ, VD: +500, -1000
        ref_type ENUM('SESSION','BADGE','VOUCHER') NOT NULL,
        ref_id INT UNSIGNED NULL, -- id tham chiếu, VD: session_id, user_voucher_redemptions.id,...
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB $charset_collate;";

    /* =========================
       BADGES & VOUCHER REDEMPTIONS
    ========================== */

    // Bảng huy hiệu đã nhận của user
    $tables[$prefix . 'user_badges'] = "CREATE TABLE {$prefix}user_badges (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        badge_post_id BIGINT UNSIGNED NOT NULL, -- WP post id của badge
    	viewed TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = đã xem, 0 = chưa xem
        awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_user_badge (user_id, badge_post_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng voucher đã đổi của user
    $tables[$prefix . 'user_voucher_redemptions'] = "CREATE TABLE {$prefix}user_voucher_redemptions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        voucher_post_id BIGINT UNSIGNED NOT NULL, -- WP post id của voucher
        transaction_ref_id VARCHAR(191) NULL,
        gotit_expiry_date DATETIME NULL,
        redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_transaction_ref_id (transaction_ref_id),
        KEY idx_user_voucher_time (user_id, voucher_post_id, redeemed_at)
    ) ENGINE=InnoDB $charset_collate;";
	
	// log các hoạt động trong settings
	$tables[$prefix . 'settings_logs'] = "CREATE TABLE {$prefix}settings_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        setting_key VARCHAR(255) NOT NULL, -- Tên cài đặt được chỉnh sửa (vd: game_bsc_stages, game_bsc_artifact_1)
        old_value LONGTEXT NULL, -- Giá trị cũ (JSON hoặc serialized)
        new_value LONGTEXT NULL, -- Giá trị mới (JSON hoặc serialized)
        action VARCHAR(50) NOT NULL, -- 'update', 'create', 'delete'
        changed_fields JSON NULL, -- Chi tiết các trường thay đổi
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_created (user_id, created_at),
        KEY idx_setting_key (setting_key),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB $charset_collate;";

    // Got It transaction records for third-party voucher issuance
    $tables[$prefix . 'gotit_transactions'] = "CREATE TABLE {$prefix}gotit_transactions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        redemption_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        voucher_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        transaction_ref_id VARCHAR(191) NOT NULL,
        gotit_order_name VARCHAR(255) NOT NULL DEFAULT '',
        gotit_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        gotit_product_price_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        gotit_voucher_link TEXT NULL,
        gotit_voucher_code VARCHAR(255) NULL,
        gotit_voucher_image TEXT NULL,
        gotit_serial VARCHAR(255) NULL,
        gotit_expiry_date DATETIME NULL,
        gotit_partner_expiry_date DATETIME NULL,
        gotit_vendor_name VARCHAR(255) NULL,
        gotit_is_partner_code TINYINT NOT NULL DEFAULT 0,
        gotit_status TINYINT NOT NULL DEFAULT 0,
        gotit_raw_response LONGTEXT NULL,
        gotit_status_code INT NOT NULL DEFAULT 0,
        gotit_error_message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_transaction_ref_id (transaction_ref_id),
        KEY idx_redemption_id (redemption_id),
        KEY idx_user_id (user_id),
        KEY idx_voucher_post_id (voucher_post_id),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB $charset_collate;";

    /* =========================
       BSC FEE VOUCHERS (Voucher hoàn phí giao dịch)
    ========================== */

    // Bảng lưu mỗi voucher hoàn phí instance đã đổi
    $tables[$prefix . 'bsc_fee_vouchers'] = "CREATE TABLE {$prefix}bsc_fee_vouchers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        redemption_id INT UNSIGNED NOT NULL,            -- FK → user_voucher_redemptions.id
        voucher_post_id BIGINT UNSIGNED NOT NULL,       -- FK → WP post
        denomination INT NOT NULL,                       -- Mệnh giá gốc (20000, 50000, 100000, 200000, 500000)
        remaining_balance INT NOT NULL,                  -- Số dư khả dụng còn lại
        fee_refund_rate DECIMAL(5,2) NOT NULL DEFAULT 100.00, -- Tỷ lệ hoàn phí (%)
        status ENUM('ACTIVE','USED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
        valid_from DATETIME NOT NULL,
        valid_to DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_status (user_id, status),
        KEY idx_user_valid (user_id, valid_to),
        KEY idx_redemption (redemption_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng log sử dụng voucher hoàn phí (khi BSC trading gọi trừ balance)
    $tables[$prefix . 'bsc_fee_voucher_usage_log'] = "CREATE TABLE {$prefix}bsc_fee_voucher_usage_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        voucher_instance_id BIGINT UNSIGNED NOT NULL,   -- FK → bsc_fee_vouchers.id
        user_id INT UNSIGNED NOT NULL,
        trade_order_id VARCHAR(128) NOT NULL,            -- Mã lệnh giao dịch (idempotency key)
        trade_fee_amount INT NOT NULL,                   -- Phí giao dịch gốc
        refund_amount INT NOT NULL,                      -- Số tiền hoàn (capped by balance)
        balance_before INT NOT NULL,
        balance_after INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_trade_order (trade_order_id),
        KEY idx_voucher_instance (voucher_instance_id),
        KEY idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    foreach ($tables as $name => $sql) {
        // if ($wpdb->get_var("SHOW TABLES LIKE '{$name}'") != $name) {
            dbDelta($sql);
        // }
    }
    // Lưu phiên bản schema hiện tại để lần sau còn so sánh
    update_option('wg_game_db_version', WG_GAME_PLUGIN_DB_VERSION, false);
}
register_activation_hook(GAME_BSC_PLUGIN_FILE, 'game_bsc_install_tables');
// game_bsc_install_tables();

/**
 * Ensure legacy installs have afacctno column in game_users.
 */
function game_bsc_ensure_users_afacctno_column() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'game_users';

    // If users table does not exist yet, let install routine handle it.
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
        return;
    }

    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'afacctno'");
    if ($column_exists) {
        return;
    }

    $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `afacctno` VARCHAR(32) DEFAULT NULL AFTER `avatar_url`");
}

// Phát hành bản mới
function game_bsc_update_db_check() {
    if (get_option('wg_game_db_version') != WG_GAME_PLUGIN_DB_VERSION) {
        game_bsc_install_tables();
    }

    game_bsc_ensure_users_afacctno_column();
}
add_action('admin_init', 'game_bsc_update_db_check');
// Uninstall hook
function game_bsc_uninstall() {
    // global $wpdb;
    // $prefix = $wpdb->prefix . 'game_';
    // $tables = [
    //     $prefix . 'question_upload_history',
    // ];
    // foreach ($tables as $table) {
    //     $wpdb->query("DROP TABLE IF EXISTS {$table}");
    // }
    // Xóa option lưu phiên bản DB
    delete_option('wg_game_db_version');
}
register_uninstall_hook(GAME_BSC_PLUGIN_FILE, 'game_bsc_uninstall');