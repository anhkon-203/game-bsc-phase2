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

	$wpdb->query("DROP TABLE IF EXISTS {$prefix}bsc_fee_voucher_usage_log, {$prefix}bsc_fee_vouchers");

    $tables = [];

    /* =========================
        USERS & LOGIN LOG
    ========================== */

    // Bảng users
    $tables[$prefix . 'users'] = "CREATE TABLE {$prefix}users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        provider VARCHAR(32) NOT NULL,
        external_user_id VARCHAR(128) NOT NULL,
        name VARCHAR(255) NOT NULL,
        avatar_url VARCHAR(255) DEFAULT NULL,
        afacctno VARCHAR(32) DEFAULT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_external_user_id (external_user_id)
    ) ENGINE=InnoDB $charset_collate;"; 

    // user_login_logs (log kiểm tra/đăng nhập SSO)
    $tables[$prefix . 'user_login_logs'] = "CREATE TABLE {$prefix}user_login_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        provider VARCHAR(32) NOT NULL,
        checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        result ENUM('OK','FAIL') NOT NULL,
        ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        raw TEXT NULL,
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
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        file_name VARCHAR(255) NOT NULL,
        file_url VARCHAR(255) NOT NULL,
        file_author INT UNSIGNED NOT NULL,
        upload_message TEXT,
        uploaded_at DATETIME NOT NULL,
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
        drop_weight SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Trọng số random hiện vật (cao hơn = rơi nhiều hơn)',
        status TINYINT(1) NOT NULL DEFAULT 0,
        closed TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng mảnh ghép hiện vật
    $tables[$prefix . 'pieces'] = "CREATE TABLE {$prefix}pieces (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        artifact_id INT UNSIGNED NOT NULL,
        piece_code VARCHAR(2) NOT NULL,
        baseline_weight TINYINT UNSIGNED NOT NULL DEFAULT 0,
        piece_img VARCHAR(255) NOT NULL,
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
        qty INT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_piece_uk (user_id, piece_id)
    ) ENGINE=InnoDB $charset_collate;";


    // Bảng log biến động mảnh của user
    $tables[$prefix . 'user_pieces_ledger'] = "CREATE TABLE {$prefix}user_pieces_ledger (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_piece_id INT UNSIGNED NOT NULL,
        ref_type ENUM('REWARD', 'CHANGE') NOT NULL,
        delta INT NOT NULL,
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
        balance INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Log biến động lượt chơi của user
    $tables[$prefix . 'play_credit_ledger'] = "CREATE TABLE {$prefix}play_credit_ledger (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        delta INT NOT NULL,
        ref_type ENUM('MISSION','SESSION') NOT NULL,
        ref_id INT UNSIGNED NULL,
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
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        finished_at DATETIME NULL,
        questions_count TINYINT UNSIGNED NOT NULL,
        allowed_retries TINYINT UNSIGNED NOT NULL,
        retries_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
        correct_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        credit_delta INT NOT NULL DEFAULT -1,
        ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        current_stage INT UNSIGNED NULL,
        current_stage_status TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_user_started (user_id, started_at)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng lưu chi tiết câu trả lời của user trong phiên chơi
    $tables[$prefix . 'users_session_answers'] = "CREATE TABLE {$prefix}users_session_answers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id INT UNSIGNED NOT NULL,
        question_post_id INT UNSIGNED NOT NULL,
        order_index TINYINT UNSIGNED NOT NULL,
        attempt_no TINYINT UNSIGNED NOT NULL,
        is_correct TINYINT(1) NOT NULL,
        user_answer char(1) NULL,
        answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
        session_id INT UNSIGNED NOT NULL,
        order_index TINYINT UNSIGNED NOT NULL,
        artifact_id INT UNSIGNED NULL,
        piece_id INT UNSIGNED NULL,
        outcome ENUM('PIECE','POINT') NOT NULL,
        points_awarded INT NOT NULL DEFAULT 0,
        weight_sum INT NOT NULL DEFAULT 0,
        chosen_weight INT NOT NULL DEFAULT 0,
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
        balance INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng log biến động điểm của user
    $tables[$prefix . 'user_points_ledger'] = "CREATE TABLE {$prefix}user_points_ledger (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        delta INT NOT NULL,
        ref_type ENUM('SESSION','BADGE','VOUCHER') NOT NULL,
        ref_id INT UNSIGNED NULL,
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
        badge_post_id BIGINT UNSIGNED NOT NULL,
    	viewed TINYINT(1) NOT NULL DEFAULT 0,
        awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_user_badge (user_id, badge_post_id)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng voucher đã đổi của user
    $tables[$prefix . 'user_voucher_redemptions'] = "CREATE TABLE {$prefix}user_voucher_redemptions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        voucher_post_id BIGINT UNSIGNED NOT NULL,
        transaction_ref_id VARCHAR(191) NULL,
        gotit_expiry_date DATETIME NULL,
        prinpaid INT NOT NULL DEFAULT 0,
        redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_transaction_ref_id (transaction_ref_id),
        KEY idx_user_voucher_time (user_id, voucher_post_id, redeemed_at)
    ) ENGINE=InnoDB $charset_collate;";
	
	// log các hoạt động trong settings
	$tables[$prefix . 'settings_logs'] = "CREATE TABLE {$prefix}settings_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        setting_key VARCHAR(255) NOT NULL,
        old_value LONGTEXT NULL,
        new_value LONGTEXT NULL,
        action VARCHAR(50) NOT NULL,
        changed_fields JSON NULL,
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
        gotit_status TINYINT NOT NULL DEFAULT 0,
        gotit_state_name VARCHAR(50) NULL,
        gotit_status_changed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_transaction_ref_id (transaction_ref_id),
        KEY idx_redemption_id (redemption_id),
        KEY idx_user_id (user_id),
        KEY idx_voucher_post_id (voucher_post_id),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB $charset_collate;";

    // Bảng log webhook Got It
    $tables[$prefix . 'gotit_webhook_logs'] = "CREATE TABLE {$prefix}gotit_webhook_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        request_body LONGTEXT NULL,
        total_vouchers INT UNSIGNED NOT NULL DEFAULT 0,
        processed_count INT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'success',
        error_detail TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_status (status),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    foreach ($tables as $name => $sql) {
        // if ($wpdb->get_var("SHOW TABLES LIKE '{$name}'") != $name) {
            dbDelta($sql);
        // }
    }
    // For existing installs, remove legacy/debug-only gotit columns we no longer keep.
    $gotit_table = $wpdb->prefix . 'game_gotit_transactions';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gotit_table)) === $gotit_table) {
        $legacy_columns = [
            'gotit_partner_expiry_date',
            'gotit_vendor_name',
            'gotit_is_partner_code',
            'gotit_raw_response',
            'gotit_status_code',
            'gotit_error_message',
        ];

        foreach ($legacy_columns as $col) {
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$gotit_table} LIKE '{$col}'");
            if ($exists === $col) {
                $wpdb->query("ALTER TABLE {$gotit_table} DROP COLUMN {$col}");
            }
        }

        // Ensure new webhook tracking columns exist in game_gotit_transactions
        $new_columns = [
            'gotit_state_name' => "VARCHAR(50) NULL AFTER gotit_status",
            'gotit_status_changed_at' => "DATETIME NULL AFTER gotit_state_name",
        ];

        foreach ($new_columns as $col => $definition) {
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$gotit_table} LIKE '{$col}'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$gotit_table} ADD COLUMN {$col} {$definition}");
            }
        }
    }
    
    // Drop unused game_user_progress table if it exists (never populated in codebase).
    $unused_user_progress_table = $wpdb->prefix . 'game_user_progress';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $unused_user_progress_table)) === $unused_user_progress_table) {
        $wpdb->query("DROP TABLE {$unused_user_progress_table}");
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

/**
 * Ensure legacy installs have prinpaid column in game_user_voucher_redemptions.
 */
function game_bsc_ensure_voucher_redemptions_prinpaid_column() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'game_user_voucher_redemptions';

    // If table does not exist yet, let install routine handle it.
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
        return;
    }

    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'prinpaid'");
    if ($column_exists) {
        return;
    }

    $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `prinpaid` INT NOT NULL DEFAULT 0 AFTER `gotit_expiry_date`");
}

// Phát hành bản mới
function game_bsc_update_db_check() {
    if (get_option('wg_game_db_version') != WG_GAME_PLUGIN_DB_VERSION) {
        game_bsc_install_tables();
    }

    game_bsc_ensure_users_afacctno_column();
    game_bsc_ensure_voucher_redemptions_prinpaid_column();
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