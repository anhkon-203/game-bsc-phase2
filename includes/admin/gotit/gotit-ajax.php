<?php
if (!defined('ABSPATH')) exit;

/**
 * Ensure the requester can use admin test tools.
 */
function game_bsc_gotit_test_guard() {
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Permission denied.'], 403);
    }

    check_ajax_referer('game_bsc_gotit_test_nonce', 'nonce');
}

function game_bsc_gotit_to_mysql_datetime($value) {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }

    return gmdate('Y-m-d H:i:s', $ts);
}

function game_bsc_gotit_transactions_table_columns($table_name) {
    global $wpdb;

    static $cache = [];
    if (isset($cache[$table_name])) {
        return $cache[$table_name];
    }

    $columns = [];
    $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $field = sanitize_key((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
    }

    $cache[$table_name] = $columns;
    return $columns;
}

/**
 * Insert one test transaction row for audit/debug.
 */
function game_bsc_insert_gotit_test_transaction($args) {
    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';
    $table = $prefix . 'gotit_transactions';

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return ['ok' => false, 'error' => 'Table gotit_transactions does not exist.'];
    }

    $columns = game_bsc_gotit_transactions_table_columns($table);

    $data = [
        'redemption_id' => (int) ($args['redemption_id'] ?? 0),
        'user_id' => (int) ($args['user_id'] ?? 0),
        'voucher_post_id' => (int) ($args['voucher_post_id'] ?? 0),
        'transaction_ref_id' => sanitize_text_field($args['transaction_ref_id'] ?? ''),
        'gotit_order_name' => sanitize_text_field($args['gotit_order_name'] ?? ''),
        'gotit_product_id' => (int) ($args['gotit_product_id'] ?? 0),
        'gotit_product_price_id' => (int) ($args['gotit_product_price_id'] ?? 0),
        'gotit_voucher_link' => esc_url_raw($args['gotit_voucher_link'] ?? ''),
        'gotit_voucher_code' => sanitize_text_field($args['gotit_voucher_code'] ?? ''),
        'gotit_status' => (int) ($args['gotit_status'] ?? 0),
        'gotit_raw_response' => (string) ($args['gotit_raw_response'] ?? ''),
        'gotit_status_code' => (int) ($args['gotit_status_code'] ?? 0),
        'gotit_error_message' => sanitize_text_field($args['gotit_error_message'] ?? ''),
        'created_at' => game_now(),
        'updated_at' => game_now(),
    ];

    $formats = ['%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s'];

    if (!empty($columns['gotit_voucher_image'])) {
        $data['gotit_voucher_image'] = esc_url_raw($args['gotit_voucher_image'] ?? '');
        $formats[] = '%s';
    }

    if (!empty($columns['gotit_serial'])) {
        $data['gotit_serial'] = sanitize_text_field($args['gotit_serial'] ?? '');
        $formats[] = '%s';
    }

    if (!empty($columns['gotit_vendor_name'])) {
        $data['gotit_vendor_name'] = sanitize_text_field($args['gotit_vendor_name'] ?? '');
        $formats[] = '%s';
    }

    if (!empty($columns['gotit_expiry_date'])) {
        $gotit_expiry_date = game_bsc_gotit_to_mysql_datetime($args['gotit_expiry_date'] ?? '');
        if ($gotit_expiry_date !== '') {
            $data['gotit_expiry_date'] = $gotit_expiry_date;
            $formats[] = '%s';
        }
    }

    if (!empty($columns['gotit_partner_expiry_date'])) {
        $partner_expiry_date = game_bsc_gotit_to_mysql_datetime($args['gotit_partner_expiry_date'] ?? '');
        if ($partner_expiry_date !== '') {
            $data['gotit_partner_expiry_date'] = $partner_expiry_date;
            $formats[] = '%s';
        }
    }

    if (!empty($columns['gotit_is_partner_code'])) {
        $raw_partner_code = $args['gotit_is_partner_code'] ?? 0;
        $is_partner_code = in_array(strtolower((string) $raw_partner_code), ['1', 'true', 'yes'], true) ? 1 : 0;
        if ($raw_partner_code === true || $raw_partner_code === 1) {
            $is_partner_code = 1;
        }
        $data['gotit_is_partner_code'] = $is_partner_code;
        $formats[] = '%d';
    }

    $inserted = $wpdb->insert(
        $table,
        $data,
        $formats
    );

    if ($inserted === false) {
        return ['ok' => false, 'error' => $wpdb->last_error];
    }

    return ['ok' => true, 'id' => (int) $wpdb->insert_id];
}

function game_bsc_gotit_is_list_array($value) {
    return game_bsc_gotit_product_normalizer_is_list_array($value);
}

function game_bsc_gotit_extract_products_list($value) {
    return game_bsc_gotit_product_normalizer_extract_products_list($value);
}

function game_bsc_gotit_normalize_price_label($price_item) {
    return game_bsc_gotit_product_normalizer_normalize_price_label($price_item);
}

function game_bsc_gotit_normalize_product_prices($value) {
    return game_bsc_gotit_product_normalizer_normalize_product_prices($value);
}

function game_bsc_gotit_normalize_products($value) {
    return game_bsc_gotit_product_normalizer_normalize_products($value);
}

function game_bsc_gotit_clean_store_text($value) {
    return game_bsc_gotit_content_helper_clean_store_text($value);
}

function game_bsc_gotit_prepare_html_content($value) {
    return game_bsc_gotit_content_helper_prepare_html_content($value);
}

function game_bsc_gotit_set_voucher_html_field($post_id, $field_name, $field_key, $value) {
    return game_bsc_gotit_content_helper_set_voucher_html_field($post_id, $field_name, $field_key, $value);
}

function game_bsc_gotit_collect_store_names_from_node($node, &$names, $depth = 0) {
    game_bsc_gotit_store_normalizer_collect_store_names_from_node($node, $names, $depth);
}

function game_bsc_gotit_collect_store_names_from_text($text, &$names) {
    game_bsc_gotit_store_normalizer_collect_store_names_from_text($text, $names);
}

function game_bsc_gotit_normalize_store_row($store) {
    return game_bsc_gotit_store_normalizer_normalize_store_row($store);
}

function game_bsc_gotit_collect_store_rows_from_node($node, &$rows, $depth = 0) {
    game_bsc_gotit_store_normalizer_collect_store_rows_from_node($node, $rows, $depth);
}

function game_bsc_gotit_build_fallback_store_rows_from_names($names) {
    return game_bsc_gotit_store_normalizer_build_fallback_store_rows_from_names($names);
}

function game_bsc_gotit_build_applicable_stores_text($stores, $fallback_names = []) {
    return game_bsc_gotit_store_normalizer_build_applicable_stores_text($stores, $fallback_names);
}

function game_bsc_gotit_get_existing_stores_payload($post_id) {
    return game_bsc_gotit_store_normalizer_get_existing_stores_payload($post_id);
}

function game_bsc_gotit_extract_applicable_stores_text($product) {
    return game_bsc_gotit_store_normalizer_extract_applicable_stores_text($product);
}

function game_bsc_gotit_extract_total_pages_from_stores_result($result) {
    return game_bsc_gotit_store_normalizer_extract_total_pages_from_stores_result($result);
}

function game_bsc_gotit_fetch_applicable_stores_from_api($client, $product_id) {
    return game_bsc_gotit_store_normalizer_fetch_applicable_stores_from_api($client, $product_id);
}

function game_bsc_gotit_pick_scalar_recursive($payload, $keys) {
    return game_bsc_gotit_issue_parser_pick_scalar_recursive($payload, $keys);
}

function game_bsc_gotit_collect_issue_candidates($payload) {
    return game_bsc_gotit_issue_parser_collect_issue_candidates($payload);
}

function game_bsc_gotit_pick_issue_value($candidates, $keys) {
    return game_bsc_gotit_issue_parser_pick_issue_value($candidates, $keys);
}

function game_bsc_gotit_extract_issue_data($payload) {
    return game_bsc_gotit_issue_parser_extract_issue_data($payload);
}

function game_bsc_gotit_extract_vouchers_from_ref_payload($payload) {
    return game_bsc_gotit_issue_parser_extract_vouchers_from_ref_payload($payload);
}

function game_bsc_gotit_extract_ref_pagination($payload) {
    return game_bsc_gotit_issue_parser_extract_ref_pagination($payload);
}

function game_bsc_gotit_build_ref_voucher_summary($vouchers) {
    return game_bsc_gotit_issue_parser_build_ref_voucher_summary($vouchers);
}

function game_bsc_gotit_set_voucher_field($post_id, $field_name, $value) {
    if (function_exists('update_field')) {
        update_field($field_name, $value, $post_id);
        return;
    }

    update_post_meta($post_id, $field_name, $value);
}

function game_bsc_gotit_extract_amount_from_text($text) {
    $raw = strtolower(trim((string) $text));
    if ($raw === '') {
        return 0;
    }

    $digits = preg_replace('/[^0-9]/', '', $raw);
    if ($digits === '') {
        return 0;
    }

    $amount = (int) $digits;
    if ($amount < 1000 && strpos($raw, 'k') !== false) {
        $amount *= 1000;
    }

    return $amount;
}

function game_bsc_gotit_get_existing_voucher_post_id($product_id, $price_id) {
    $posts = get_posts([
        'post_type' => 'game_vouchers',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => 'voucher_type',
                'value' => 'THIRD_PARTY',
                'compare' => '=',
            ],
            [
                'key' => 'gotit_product_id',
                'value' => (string) ((int) $product_id),
                'compare' => '=',
            ],
            [
                'key' => 'gotit_product_price_id',
                'value' => (string) ((int) $price_id),
                'compare' => '=',
            ],
        ],
    ]);

    return !empty($posts) ? (int) $posts[0] : 0;
}

function game_bsc_gotit_find_category_term_id_by_gotit_id($gotit_category_id, $force_refresh = false) {
    static $cache = [];

    $gotit_category_id = (int) $gotit_category_id;
    if ($gotit_category_id < 1 || !taxonomy_exists('game_voucher_category')) {
        return 0;
    }

    if (!$force_refresh && array_key_exists($gotit_category_id, $cache)) {
        return (int) $cache[$gotit_category_id];
    }

    $terms = get_terms([
        'taxonomy'   => 'game_voucher_category',
        'hide_empty' => false,
        'fields'     => 'ids',
        'number'     => 1,
        'meta_query' => [
            [
                'key'     => '_gotit_category_id',
                'value'   => (string) $gotit_category_id,
                'compare' => '=',
            ],
        ],
    ]);

    if (!is_wp_error($terms) && !empty($terms)) {
        $cache[$gotit_category_id] = (int) $terms[0];
        return (int) $cache[$gotit_category_id];
    }

    $cache[$gotit_category_id] = 0;
    return 0;
}

function game_bsc_gotit_upsert_category_term($category_name, $gotit_category_id = 0, $category_slug = '', $category_image = null) {
    if (!taxonomy_exists('game_voucher_category')) {
        return ['term_id' => 0, 'created' => false];
    }

    $gotit_category_id = (int) $gotit_category_id;
    $category_name = trim((string) $category_name);
    $category_slug = sanitize_title((string) $category_slug);
    $should_sync_image = $category_image !== null;
    if ($should_sync_image) {
        $category_image = esc_url_raw((string) $category_image);
    }

    if ($category_name === '') {
        $category_name = __('Chưa phân loại', WG_GAME_PLUGIN_TEXTDOMAIN);
    }

    $term_id = 0;

    if ($gotit_category_id > 0) {
        $term_id = game_bsc_gotit_find_category_term_id_by_gotit_id($gotit_category_id);
    }

    if ($term_id < 1) {
        $term = term_exists($category_name, 'game_voucher_category');
        if ($term) {
            $term_id = (int) (is_array($term) ? ($term['term_id'] ?? 0) : $term);
        }
    }

    $created = false;
    if ($term_id < 1) {
        $insert_args = [];
        if ($category_slug !== '') {
            $insert_args['slug'] = $category_slug;
        }

        $created_term = wp_insert_term($category_name, 'game_voucher_category', $insert_args);
        if (is_wp_error($created_term) || empty($created_term['term_id'])) {
            return ['term_id' => 0, 'created' => false];
        }

        $term_id = (int) $created_term['term_id'];
        $created = true;
    }

    $current_term = get_term($term_id, 'game_voucher_category');
    if ($current_term && !is_wp_error($current_term)) {
        $update_args = [];
        if ($category_name !== '' && $current_term->name !== $category_name) {
            $update_args['name'] = $category_name;
        }
        if ($category_slug !== '' && $current_term->slug !== $category_slug) {
            $update_args['slug'] = $category_slug;
        }
        if (!empty($update_args)) {
            wp_update_term($term_id, 'game_voucher_category', $update_args);
        }
    }

    if ($gotit_category_id > 0) {
        update_term_meta($term_id, '_gotit_category_id', $gotit_category_id);
    }

    if ($should_sync_image) {
        if ($category_image !== '') {
            update_term_meta($term_id, '_gotit_category_image', $category_image);
        } else {
            delete_term_meta($term_id, '_gotit_category_image');
        }
    }

    if ($gotit_category_id > 0) {
        game_bsc_gotit_find_category_term_id_by_gotit_id($gotit_category_id, true);
    }

    return ['term_id' => $term_id, 'created' => $created];
}

function game_bsc_gotit_assign_voucher_category($post_id, $category_name, $append = false, $gotit_category_id = 0, $category_image = '') {
    if (!taxonomy_exists('game_voucher_category')) {
        return;
    }

    $category_name = trim((string) $category_name);
    if ($category_name === '') {
        $category_name = __('Chưa phân loại', WG_GAME_PLUGIN_TEXTDOMAIN);
    }

    $upsert_image = $category_image !== '' ? $category_image : null;
    $upsert = game_bsc_gotit_upsert_category_term($category_name, (int) $gotit_category_id, '', $upsert_image);
    $term_id = (int) ($upsert['term_id'] ?? 0);

    if ($term_id > 0) {
        // $append=false (lần đầu): xóa category cũ/stale, set category mới
        // $append=true  (lần 2+) : product ở nhiều category → giữ cũ, thêm mới
        wp_set_object_terms($post_id, [$term_id], 'game_voucher_category', $append);
    }
}

function game_bsc_gotit_async_sync_default_state() {
    return [
        'job_id' => '',
        'source' => '',
        'cancel_requested' => 0,
        'status' => 'idle',
        'message' => '',
        'queued_at' => '',
        'started_at' => '',
        'finished_at' => '',
        'requested_by' => 0,
        'gotit_category_id' => 0,
        'filter_category' => '',
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'products_count' => 0,
        'detail_calls' => 0,
        'errors_count' => 0,
        'last_error' => '',
        'current_page' => 0,
        'total_pages' => 0,
        'pages_processed' => 0,
        'category_queue' => [],
        'category_index' => 0,
    ];
}

function game_bsc_gotit_normalize_category_queue($value) {
    if (!is_array($value)) {
        return [];
    }

    $queue = [];
    foreach ($value as $raw_id) {
        $category_id = absint($raw_id);
        if ($category_id > 0) {
            $queue[$category_id] = $category_id;
        }
    }

    return array_values($queue);
}

function game_bsc_gotit_collect_category_ids_for_sync($client = null) {
    if (!$client) {
        $client = game_bsc_gotit_client();
    }

    if (!$client || !$client->is_configured()) {
        return [];
    }

    $categories_map = $client->get_categories_map();
    if (!is_array($categories_map) || empty($categories_map)) {
        return [];
    }

    $ids = [];
    foreach ($categories_map as $category_id => $cat_data) {
        $normalized_id = absint($category_id);
        if ($normalized_id > 0) {
            $ids[$normalized_id] = $normalized_id;
        }
    }

    $ids = array_values($ids);
    sort($ids, SORT_NUMERIC);

    return $ids;
}

function game_bsc_gotit_async_sync_get_state() {
    $stored = get_option('game_bsc_gotit_async_sync_state', []);
    if (!is_array($stored)) {
        $stored = [];
    }

    return array_merge(game_bsc_gotit_async_sync_default_state(), $stored);
}

function game_bsc_gotit_async_sync_update_state($patch) {
    $current = game_bsc_gotit_async_sync_get_state();
    $next = array_merge($current, is_array($patch) ? $patch : []);
    update_option('game_bsc_gotit_async_sync_state', $next, false);
    return $next;
}

function game_bsc_gotit_sync_runtime_config() {
    $pages_per_run = (int) apply_filters('game_bsc_gotit_sync_pages_per_run', 1);
    $page_size = (int) apply_filters('game_bsc_gotit_sync_page_size', 30);
    $worker_delay_seconds = (int) apply_filters('game_bsc_gotit_sync_worker_delay_seconds', 8);

    return [
        'pages_per_run' => max(1, min(5, $pages_per_run)),
        'page_size' => max(20, min(100, $page_size)),
        'worker_delay_seconds' => max(3, min(60, $worker_delay_seconds)),
    ];
}

function game_bsc_schedule_gotit_async_worker($job_id, $gotit_category_id, $requested_by, $page = 1, $delay_seconds = 1, $trigger_spawn = false) {
    $delay_seconds = max(1, (int) $delay_seconds);
    $page = max(1, (int) $page);

    wp_schedule_single_event(
        time() + $delay_seconds,
        'game_bsc_gotit_async_sync_event',
        [(string) $job_id, (int) $gotit_category_id, (int) $requested_by, $page]
    );

    if (
        $trigger_spawn
        && function_exists('spawn_cron')
        && !wp_doing_cron()
        && !is_admin()
        && !get_transient('game_bsc_spawn_cron_throttle')
    ) {
        set_transient('game_bsc_spawn_cron_throttle', 1, 30);
        spawn_cron(time());
    }
}

function game_bsc_clear_gotit_async_worker_queue() {
    wp_clear_scheduled_hook('game_bsc_gotit_async_sync_event');
}

function game_bsc_gotit_async_sync_worker_health() {
    $next_run_ts = wp_next_scheduled('game_bsc_gotit_async_sync_event');

    return [
        'has_lock' => (bool) get_transient('game_bsc_gotit_async_sync_lock'),
        'next_run_ts' => $next_run_ts ? (int) $next_run_ts : 0,
    ];
}

function game_bsc_gotit_async_sync_reconcile_state($state = null) {
    if (!is_array($state)) {
        $state = game_bsc_gotit_async_sync_get_state();
    }

    $health = game_bsc_gotit_async_sync_worker_health();
    $status = (string) ($state['status'] ?? 'idle');

    if ($status === 'stopping' && empty($health['has_lock']) && empty($health['next_run_ts'])) {
        $state = game_bsc_gotit_async_sync_update_state([
            'status' => 'stopped',
            'message' => 'Đã dừng đồng bộ theo yêu cầu.',
            'finished_at' => current_time('mysql'),
            'cancel_requested' => 0,
        ]);
    }

    return [
        'state' => $state,
        'next_run_ts' => (int) ($health['next_run_ts'] ?? 0),
    ];
}

function game_bsc_start_gotit_sync_job($args = []) {
    $gotit_category_id = isset($args['gotit_category_id']) ? absint($args['gotit_category_id']) : 0;
    $requested_by = isset($args['requested_by']) ? absint($args['requested_by']) : get_current_user_id();
    $source = sanitize_text_field((string) ($args['source'] ?? 'manual-admin-async'));
    $category_queue = game_bsc_gotit_normalize_category_queue($args['category_queue'] ?? []);
    $category_index = isset($args['category_index']) ? max(0, (int) $args['category_index']) : 0;

    if (!empty($category_queue)) {
        if ($category_index >= count($category_queue)) {
            $category_index = 0;
        }
        if ($gotit_category_id < 1) {
            $gotit_category_id = (int) $category_queue[$category_index];
        }
    }

    $state = game_bsc_gotit_async_sync_get_state();
    if (in_array((string) ($state['status'] ?? ''), ['queued', 'running', 'stopping'], true)) {
        return [
            'success' => true,
            'already_running' => true,
            'status' => $state,
        ];
    }

    $job_id = sanitize_text_field((string) ($args['job_id'] ?? ''));
    if ($job_id === '') {
        $job_id = uniqid('gotit_sync_', true);
    }

    game_bsc_clear_gotit_async_worker_queue();

    $queued_at = current_time('mysql');
    $next_state = game_bsc_gotit_async_sync_update_state([
        'job_id' => $job_id,
        'source' => $source,
        'cancel_requested' => 0,
        'status' => 'queued',
        'message' => 'Đã đưa sync vào hàng đợi.',
        'queued_at' => $queued_at,
        'started_at' => '',
        'finished_at' => '',
        'requested_by' => $requested_by,
        'gotit_category_id' => $gotit_category_id,
        'filter_category' => '',
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'products_count' => 0,
        'detail_calls' => 0,
        'errors_count' => 0,
        'last_error' => '',
        'current_page' => 1,
        'total_pages' => 0,
        'pages_processed' => 0,
        'category_queue' => $category_queue,
        'category_index' => $category_index,
    ]);

    game_bsc_schedule_gotit_async_worker($job_id, $gotit_category_id, $requested_by, 1, 1, true);

    return [
        'success' => true,
        'already_running' => false,
        'status' => $next_state,
    ];
}

function game_bsc_request_stop_gotit_sync_job($requested_by = 0) {
    $state = game_bsc_gotit_async_sync_get_state();
    $status = (string) ($state['status'] ?? 'idle');

    if (!in_array($status, ['queued', 'running', 'stopping'], true)) {
        return [
            'success' => false,
            'message' => 'Không có phiên sync đang chạy để dừng.',
            'status' => $state,
        ];
    }

    game_bsc_clear_gotit_async_worker_queue();
    $health = game_bsc_gotit_async_sync_worker_health();

    $next_state_patch = [
        'cancel_requested' => 1,
        'status' => 'stopping',
        'message' => 'Đã nhận yêu cầu dừng. Worker sẽ dừng sau batch hiện tại.',
        'requested_by' => absint($requested_by),
    ];

    if (
        $status === 'queued'
        || ($status === 'running' && empty($health['has_lock']) && empty($health['next_run_ts']))
    ) {
        $next_state_patch['status'] = 'stopped';
        $next_state_patch['message'] = 'Đã dừng đồng bộ theo yêu cầu.';
        $next_state_patch['finished_at'] = current_time('mysql');
        $next_state_patch['cancel_requested'] = 0;
    }

    $next_state = game_bsc_gotit_async_sync_update_state($next_state_patch);

    return [
        'success' => true,
        'message' => 'Đã gửi yêu cầu dừng đồng bộ.',
        'status' => $next_state,
    ];
}

/**
 * WSAL: Bỏ qua log event khi đang sync voucher Got It (tránh ghi vào wp_wsal_occurrences).
 */
function game_bsc_wsal_suppress_event_during_sync($event_id, $event_data) {
    return null; // Trả về null để WSAL bỏ qua, không ghi DB
}

/**
 * WSAL: Bỏ qua log post meta create/update/delete khi post type là game_vouchers và đang sync.
 */
function game_bsc_wsal_suppress_meta_event_during_sync($log_event, $meta_key, $meta_value, $post) {
    if ($post && isset($post->post_type) && $post->post_type === 'game_vouchers') {
        return false;
    }
    return $log_event;
}

function game_bsc_sync_gotit_products_to_vouchers($args = []) {
    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        return ['success' => false, 'message' => 'Got It API key is not configured.'];
    }

    // ===== TẮT WSAL LOGGING TRONG KHI SYNC VOUCHER =====
    // Ngăn plugin WP Security Audit Log ghi vào wp_wsal_occurrences và wp_wsal_metadata
    add_filter('wsal_event_id_before_log',               'game_bsc_wsal_suppress_event_during_sync',      PHP_INT_MAX, 2);
    add_filter('wsal_before_post_meta_create_event',     'game_bsc_wsal_suppress_meta_event_during_sync', PHP_INT_MAX, 4);
    add_filter('wsal_before_post_meta_update_event',     'game_bsc_wsal_suppress_meta_event_during_sync', PHP_INT_MAX, 4);
    add_filter('wsal_before_post_meta_delete_event',     'game_bsc_wsal_suppress_meta_event_during_sync', PHP_INT_MAX, 4);

    $max_pages     = isset($args['max_pages']) ? max(1, (int) $args['max_pages']) : 30;
    $source        = sanitize_text_field((string) ($args['source'] ?? 'manual'));
    $filter_cat_id = isset($args['gotit_category_id']) ? (int) $args['gotit_category_id'] : 0;
    $start_page    = isset($args['start_page']) ? max(1, (int) $args['start_page']) : 1;
    $pages_per_run = isset($args['pages_per_run']) ? max(1, min(5, (int) $args['pages_per_run'])) : 1;
    $page_size     = isset($args['page_size']) ? max(20, min(100, (int) $args['page_size'])) : 50;
    $lightweight_mode = array_key_exists('lightweight_mode', $args)
        ? (bool) $args['lightweight_mode']
        : (bool) apply_filters('game_bsc_gotit_sync_lightweight_mode', true, $source);

    // ===== FILTERS =====
    $excluded_raw  = (string) game_bsc_gotit_source_value('excluded_product_ids', '');
    $excluded_ids  = array_flip(array_filter(array_map('intval', explode(',', $excluded_raw))));
    $min_price_val = max(0, (int) game_bsc_gotit_source_value('min_price_value', 1000));

    // ===== STEP 1: LẤY CATEGORIES MAP =====
    $categories_cache_key = 'game_bsc_gotit_categories_map';
    $categories_map = get_transient($categories_cache_key);
    if (!is_array($categories_map) || empty($categories_map)) {
        $categories_map = $client->get_categories_map();
        if (is_array($categories_map) && !empty($categories_map)) {
            set_transient($categories_cache_key, $categories_map, 6 * HOUR_IN_SECONDS);
        }
    }

    // ===== COUNTERS =====
    $created      = 0;
    $updated      = 0;
    $skipped      = 0;
    $errors       = [];
    $detail_api_calls = 0;
    $written_keys = []; // pid:price_id → post_id

    // ===== CACHE CATEGORY CHO TỪNG PRODUCT =====
    // Got It /products list KHÔNG trả categoryId
    // → Phải gọi /products/:productId (detail) để lấy categoryId
    // Cache kết quả theo productId để tránh call trùng
    $product_category_cache = []; // productId → ['cat_id' => int, 'cat_name' => string]

    // ===== STEP 2: FETCH ALL PRODUCTS (1 LẦN, KHÔNG LOOP CATEGORY) =====
    $extra_filters = [];
    if ($filter_cat_id > 0) {
        $extra_filters['categoryId'] = $filter_cat_id;
    }

    $page        = $start_page;
    $total_pages = max(1, $start_page);
    $pages_processed = 0;
    $last_processed_page = $start_page - 1;
    $fetch_failed = false;
    $product_stores_cache = []; // productId -> ['text' => string, 'stores' => array, 'source' => string]

    do {
        $result = $client->get_products($page, $page_size, $extra_filters);

        if (empty($result['success'])) {
            $errors[] = [
                'message'   => (string) ($result['error'] ?? 'Cannot fetch products.'),
                'http_code' => (int) ($result['http_code'] ?? 0),
            ];
            $fetch_failed = true;
            break;
        }

        foreach (game_bsc_gotit_normalize_products($result['data'] ?? null) as $product) {
            $pid    = (int) ($product['productId'] ?? 0);
            $prices = is_array($product['prices'] ?? null) ? $product['prices'] : [];

            if ($pid < 1 || empty($prices)) { $skipped++; continue; }
            if (isset($excluded_ids[$pid]))  { $skipped++; continue; }

            // ===== STEP 3: LẤY CATEGORY TỪ /products/:productId (detail) =====
            if (!isset($product_category_cache[$pid])) {
                $detail_cat_id   = 0;
                $detail_cat_name = '';

                if ($filter_cat_id > 0) {
                    // Sync theo 1 category → không cần gọi detail, dùng category đã chọn
                    $detail_cat_id   = $filter_cat_id;
                    $detail_cat_name = $categories_map[$filter_cat_id]['name'] ?? ('Category ' . $filter_cat_id);
                } else {
                    // Ưu tiên lấy category đã có trong list products để giảm số lần gọi detail API.
                    $detail_cat_id = (int) ($product['categoryId'] ?? 0);
                    $detail_cat_name = sanitize_text_field((string) ($product['categoryName'] ?? ''));

                    if ($detail_cat_id > 0 && $detail_cat_name === '') {
                        $detail_cat_name = $categories_map[$detail_cat_id]['name'] ?? '';
                    }

                    if ($detail_cat_id < 1) {
                        $detail_api_calls++;
                        $detail = $client->get_product_detail($pid);
                        if (!empty($detail['success']) && is_array($detail['data'])) {
                            $d = $detail['data'];
                            // Got It trả detail dạng array [0 => {product}] → unwrap
                            if (isset($d[0]) && is_array($d[0]) && !isset($d['categoryId'])) {
                                $d = $d[0];
                            }
                            // Tìm categoryId trong response detail
                            foreach (['categoryId', 'category_id'] as $ck) {
                                if (!empty($d[$ck])) {
                                    $detail_cat_id = (int) $d[$ck];
                                    break;
                                }
                            }
                            // Tìm categoryName
                            foreach (['categoryName', 'category_name'] as $ck) {
                                if (!empty($d[$ck])) {
                                    $detail_cat_name = sanitize_text_field((string) $d[$ck]);
                                    break;
                                }
                            }
                            // Fallback: nested category object
                            if ($detail_cat_id < 1 && !empty($d['category']) && is_array($d['category'])) {
                                $detail_cat_id   = (int) ($d['category']['id'] ?? 0);
                                $detail_cat_name = sanitize_text_field((string) ($d['category']['name'] ?? ''));
                            }
                            // Fallback: categories array
                            if ($detail_cat_id < 1 && !empty($d['categories']) && is_array($d['categories'])) {
                                $first_cat = reset($d['categories']);
                                if (is_array($first_cat)) {
                                    $detail_cat_id   = (int) ($first_cat['id'] ?? 0);
                                    $detail_cat_name = sanitize_text_field((string) ($first_cat['name'] ?? ''));
                                }
                            }
                            // Tra tên từ map nếu có id nhưng không có name
                            if ($detail_cat_id > 0 && $detail_cat_name === '') {
                                $detail_cat_name = $categories_map[$detail_cat_id]['name'] ?? '';
                            }
                        }
                    }
                }

                $product_category_cache[$pid] = [
                    'cat_id'   => $detail_cat_id,
                    'cat_name' => $detail_cat_name,
                ];
            }

            $prod_cat = $product_category_cache[$pid];

            if (!isset($product_stores_cache[$pid])) {
                if ($lightweight_mode) {
                    $stores_payload = [
                        'text' => '',
                        'stores' => [],
                        'source' => 'lightweight_mode',
                    ];
                } else {
                    $stores_payload = game_bsc_gotit_fetch_applicable_stores_from_api($client, $pid);
                }
                if (!is_array($stores_payload)) {
                    $stores_payload = [
                        'text' => '',
                        'stores' => [],
                        'source' => 'stores_api',
                    ];
                }

                $stores_text = (string) ($stores_payload['text'] ?? '');
                if ($stores_text === '') {
                    $stores_text = game_bsc_gotit_extract_applicable_stores_text($product);
                    if ($stores_text !== '') {
                        $stores_payload['text'] = $stores_text;
                        $stores_payload['source'] = 'product_payload';

                        if (empty($stores_payload['stores']) || !is_array($stores_payload['stores'])) {
                            $fallback_names = [];
                            game_bsc_gotit_collect_store_names_from_text($stores_text, $fallback_names);
                            $stores_payload['stores'] = game_bsc_gotit_build_fallback_store_rows_from_names($fallback_names);
                        }
                    }
                }

                if (!isset($stores_payload['source']) || !is_scalar($stores_payload['source'])) {
                    $stores_payload['source'] = 'stores_api';
                }

                if (empty($stores_payload['stores']) || !is_array($stores_payload['stores'])) {
                    $stores_payload['stores'] = [];
                }

                $product_stores_cache[$pid] = $stores_payload;
            }

            $applicable_stores_data = is_array($product_stores_cache[$pid] ?? null) ? $product_stores_cache[$pid] : [
                'text' => '',
                'stores' => [],
                'source' => 'stores_api',
            ];

            $product_name      = sanitize_text_field((string) ($product['productName'] ?? ('Product ' . $pid)));
            $brand_name        = sanitize_text_field((string) (($product['brandInfo']['name'] ?? '') ?: $product_name));
            $brand_logo_url    = esc_url_raw((string) ($product['brandInfo']['logo'] ?? ''));
            $product_link      = esc_url_raw((string) ($product['link'] ?? ''));
            $product_image     = esc_url_raw((string) ($product['image'] ?? ''));
            $short_description = game_bsc_gotit_prepare_html_content((string) ($product['shortDescription'] ?? ''));
            $description       = game_bsc_gotit_prepare_html_content((string) ($product['description'] ?? ''));
            $service_guide     = game_bsc_gotit_prepare_html_content((string) ($product['serviceGuide'] ?? ''));
            $terms             = game_bsc_gotit_prepare_html_content((string) ($product['terms'] ?? ''));
            $applicable_stores = (string) ($applicable_stores_data['text'] ?? '');
            $applicable_stores_structured = is_array($applicable_stores_data['stores'] ?? null)
                ? array_values($applicable_stores_data['stores'])
                : [];

            foreach ($prices as $price) {
                $price_id = (int) ($price['productPriceId'] ?? 0);
                if ($price_id < 1) { $skipped++; continue; }

                // Bỏ qua mệnh giá test (quá nhỏ)
                $raw_price_val = 0;
                foreach (['value', 'price', 'amount', 'denomination'] as $vk) {
                    if (isset($price[$vk]) && is_numeric($price[$vk])) {
                        $raw_price_val = (int) $price[$vk];
                        break;
                    }
                }
                if ($min_price_val > 0 && $raw_price_val > 0 && $raw_price_val < $min_price_val) {
                    $skipped++; continue;
                }

                $sync_key = $pid . ':' . $price_id;
                $post_id  = game_bsc_gotit_get_existing_voucher_post_id($pid, $price_id);
                $is_new   = $post_id < 1;

                if (!isset($written_keys[$sync_key])) {
                    $written_keys[$sync_key] = true;

                    $price_label   = sanitize_text_field((string) ($price['label'] ?? (string) $price_id));
                    $price_name    = sanitize_text_field((string) ($price['name'] ?? ''));
                    $price_value   = isset($price['value']) && is_numeric($price['value']) ? (int) $price['value'] : 0;
                    $title_suffix  = $price_name !== '' ? $price_name : $price_label;
                    $voucher_title = trim($product_name . ' - ' . $title_suffix);
                    if ($voucher_title === '-') {
                        $voucher_title = 'Voucher Got It ' . $pid . '-' . $price_id;
                    }

                    if ($is_new) {
                        $post_id = wp_insert_post([
                            'post_type'    => 'game_vouchers',
                            'post_status'  => 'publish',
                            'post_title'   => $voucher_title,
                            'post_content' => ($description !== '' ? $description : $short_description),
                        ], true);

                        if (is_wp_error($post_id) || !$post_id) {
                            $errors[] = [
                                'product_id' => $pid,
                                'price_id'   => $price_id,
                                'message'    => is_wp_error($post_id) ? $post_id->get_error_message() : 'Cannot create post.',
                            ];
                            continue;
                        }
                        $created++;
                    } else {
                        wp_update_post([
                            'ID'           => $post_id,
                            'post_title'   => $voucher_title,
                            'post_content' => ($description !== '' ? $description : $short_description),
                        ]);
                        $updated++;
                    }

                    $points_locked = (int) get_post_meta($post_id, '_game_bsc_points_cost_locked', true) === 1;
                    $current_points = (int) (get_field('points_cost', $post_id) ?? 0);
                    $selected_value = $price_value > 0 ? (string) $price_value : $price_label;
                    $points_cost    = $current_points > 0 ? $current_points
                                      : ($price_value > 0 ? $price_value : game_bsc_gotit_extract_amount_from_text($price_label));

                    // If stores API did not return structured rows, preserve existing detailed payload if any.
                    if (empty($applicable_stores_structured)) {
                        $existing_stores_payload = game_bsc_gotit_get_existing_stores_payload($post_id);
                        if (!empty($existing_stores_payload['stores'])) {
                            $applicable_stores_structured = array_values($existing_stores_payload['stores']);
                            if ($applicable_stores === '') {
                                $applicable_stores = (string) ($existing_stores_payload['text'] ?? '');
                            }
                            $applicable_stores_data['source'] = 'existing_meta_cache';
                        }
                    }

                    if ($applicable_stores === '' && !empty($applicable_stores_structured)) {
                        $applicable_stores = game_bsc_gotit_build_applicable_stores_text($applicable_stores_structured);
                    }

                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_type',             'THIRD_PARTY');
                    game_bsc_gotit_set_voucher_field($post_id, 'gotit_product_id',          $pid);
                    game_bsc_gotit_set_voucher_field($post_id, 'gotit_product_price_id',    $price_id);

                    if ((string) get_field('voucher_code', $post_id) === '') {
                        game_bsc_gotit_set_voucher_field($post_id, 'voucher_code', 'GOTIT-' . $pid . '-' . $price_id);
                    }

                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_display_name',      $product_name);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_brand_name',         $brand_name);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_brand_logo_url',     $brand_logo_url);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_selected_value',     $selected_value);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_link_url',           $product_link);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_image_url',          $product_image);

                    // Keep HTML structure in DB for description fields.
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_short_description', 'field_voucher_short_description', $short_description);
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_long_description', 'field_voucher_long_description', $description);
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_service_guide', 'field_voucher_service_guide', $service_guide);
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_terms', 'field_voucher_terms', $terms);

                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_applicable_stores',  $applicable_stores);

                    $stores_json = wp_json_encode($applicable_stores_structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($stores_json === false) {
                        $stores_json = '[]';
                    }
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_json', $stores_json);
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_count', count($applicable_stores_structured));
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_source', sanitize_text_field((string) ($applicable_stores_data['source'] ?? 'stores_api')));
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_last_error', sanitize_text_field((string) ($applicable_stores_data['error'] ?? '')));

                    // THIRD_PARTY/Got It vouchers do not use validity date range.
                    game_bsc_gotit_set_voucher_field($post_id, 'validity', [
                        'valid_from' => '',
                        'valid_to' => '',
                    ]);
                    game_bsc_gotit_set_voucher_field($post_id, 'partner', [
                        'name' => $brand_name,
                        'url'  => $product_link,
                        'logo' => $brand_logo_url,
                    ]);
                    game_bsc_gotit_set_voucher_field($post_id, 'is_active',   1);
                    if (!$points_locked) {
                        game_bsc_gotit_set_voucher_field($post_id, 'points_cost', max(0, (int) $points_cost));
                    }

                    if ((int) (get_field('quantity', $post_id) ?? 0) < 1) {
                        game_bsc_gotit_set_voucher_field($post_id, 'quantity', 999999);
                    }

                    update_post_meta($post_id, '_game_bsc_gotit_sync_key',    $sync_key);
                    update_post_meta($post_id, '_game_bsc_gotit_synced_at',   current_time('mysql'));
                    update_post_meta($post_id, '_game_bsc_gotit_sync_source', $source);

                    $written_keys[$sync_key] = $post_id;

                } else {
                    $skipped++;
                }

                // ===== GÁN DANH MỤC =====
                // Category lấy từ /products/:productId (detail API)
                if ($post_id > 0) {
                    update_post_meta($post_id, '_game_bsc_gotit_category_id', $prod_cat['cat_id']);

                    $cat_image = '';
                    if ((int) ($prod_cat['cat_id'] ?? 0) > 0 && !empty($categories_map[$prod_cat['cat_id']]['image'])) {
                        $cat_image = esc_url_raw((string) $categories_map[$prod_cat['cat_id']]['image']);
                    }

                    game_bsc_gotit_assign_voucher_category(
                        $post_id,
                        $prod_cat['cat_name'],
                        false,
                        (int) ($prod_cat['cat_id'] ?? 0),
                        $cat_image
                    );
                }
            }
        }

        $decoded     = json_decode((string) ($result['raw'] ?? ''), true);
        $total_pages = max(
            1,
            (int) ($decoded['totalPage'] ?? 0),
            (int) ($decoded['data']['totalPage'] ?? 0),
            (int) ($decoded['paging']['totalPage'] ?? 0)
        );
        $last_processed_page = $page;
        $pages_processed++;
        $page++;

    } while (
        $page <= $total_pages
        && $page <= $max_pages
        && $pages_processed < $pages_per_run
    );

    $filter_label = $filter_cat_id > 0
        ? ($categories_map[$filter_cat_id]['name'] ?? 'ID ' . $filter_cat_id)
        : 'Tất cả danh mục';

    // ===== KHÔI PHỤC WSAL LOGGING SAU KHI SYNC XONG =====
    remove_filter('wsal_event_id_before_log',               'game_bsc_wsal_suppress_event_during_sync',      PHP_INT_MAX);
    remove_filter('wsal_before_post_meta_create_event',     'game_bsc_wsal_suppress_meta_event_during_sync', PHP_INT_MAX);
    remove_filter('wsal_before_post_meta_update_event',     'game_bsc_wsal_suppress_meta_event_during_sync', PHP_INT_MAX);
    remove_filter('wsal_before_post_meta_delete_event',     'game_bsc_wsal_suppress_meta_event_during_sync', PHP_INT_MAX);

    $is_complete = $fetch_failed || $page > $total_pages || $page > $max_pages;
    $next_page = $is_complete ? 0 : $page;
    $sync_success = !($fetch_failed && $pages_processed === 0);

    return [
        'success'         => $sync_success,
        'message'         => $sync_success ? 'Sync completed.' : 'Cannot fetch products from Got It API.',
        'created'         => $created,
        'updated'         => $updated,
        'skipped'         => $skipped,
        'errors'          => $errors,
        'products_count'  => count($written_keys),
        'detail_calls'    => $detail_api_calls,
        'filter_category' => $filter_label,
        'is_complete'     => $is_complete,
        'next_page'       => $next_page,
        'current_page'    => max(1, $last_processed_page),
        'total_pages'     => (int) $total_pages,
        'pages_processed' => (int) $pages_processed,
        'fetch_failed'    => $fetch_failed,
    ];
}

function game_bsc_ajax_gotit_sync_vouchers() {
    game_bsc_gotit_test_guard();

    $gotit_category_id = absint($_POST['gotit_category_id'] ?? 0);

    $result = game_bsc_start_gotit_sync_job([
        'source' => 'manual-admin',
        'gotit_category_id' => $gotit_category_id,
        'requested_by' => get_current_user_id(),
    ]);

    if (!empty($result['success'])) {
        $message = !empty($result['already_running'])
            ? 'Đã có phiên sync đang chạy.'
            : 'Đã đưa sync voucher vào hàng đợi nền.';
        wp_send_json_success([
            'message' => $message,
            'status' => $result['status'] ?? game_bsc_gotit_async_sync_get_state(),
        ]);
    }

    wp_send_json_error(['message' => 'Không thể bắt đầu sync voucher.'], 400);
}
add_action('wp_ajax_game_bsc_gotit_sync_vouchers', 'game_bsc_ajax_gotit_sync_vouchers');

function game_bsc_ajax_gotit_sync_vouchers_async_start() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $gotit_category_id = absint($_POST['gotit_category_id'] ?? 0);
    $current_state = game_bsc_gotit_async_sync_get_state();

    if (in_array((string) ($current_state['status'] ?? ''), ['queued', 'running'], true)) {
        wp_send_json_success([
            'message' => 'Đã có phiên sync đang chạy.',
            'status' => $current_state,
        ]);
    }

    $started = game_bsc_start_gotit_sync_job([
        'source' => 'manual-admin-async',
        'gotit_category_id' => $gotit_category_id,
        'requested_by' => get_current_user_id(),
    ]);

    wp_send_json_success([
        'message' => !empty($started['already_running'])
            ? 'Đã có phiên sync đang chạy.'
            : 'Đã bắt đầu sync bất đồng bộ.',
        'status' => $started['status'] ?? game_bsc_gotit_async_sync_get_state(),
    ]);
}
add_action('wp_ajax_game_bsc_gotit_sync_vouchers_async_start', 'game_bsc_ajax_gotit_sync_vouchers_async_start');

function game_bsc_ajax_gotit_sync_vouchers_async_status() {
    game_bsc_gotit_test_guard();

    $status_cache_key = 'game_bsc_gotit_sync_status_cache_' . absint(get_current_user_id());
    $cached = get_transient($status_cache_key);
    if (is_array($cached)) {
        wp_send_json_success($cached);
    }

    $reconciled = game_bsc_gotit_async_sync_reconcile_state();
    $state = is_array($reconciled['state'] ?? null)
        ? $reconciled['state']
        : game_bsc_gotit_async_sync_get_state();
    $next_run_ts = (int) ($reconciled['next_run_ts'] ?? 0);

    $payload = [
        'status' => $state,
        'next_run_ts' => $next_run_ts,
    ];

    set_transient($status_cache_key, $payload, 2);

    wp_send_json_success($payload);
}
add_action('wp_ajax_game_bsc_gotit_sync_vouchers_async_status', 'game_bsc_ajax_gotit_sync_vouchers_async_status');

function game_bsc_ajax_gotit_sync_vouchers_async_stop() {
    game_bsc_gotit_test_guard();

    $result = game_bsc_request_stop_gotit_sync_job(get_current_user_id());
    if (!empty($result['success'])) {
        wp_send_json_success($result);
    }

    wp_send_json_error($result, 409);
}
add_action('wp_ajax_game_bsc_gotit_sync_vouchers_async_stop', 'game_bsc_ajax_gotit_sync_vouchers_async_stop');

function game_bsc_run_gotit_async_sync_event($job_id = '', $gotit_category_id = 0, $requested_by = 0, $start_page = 1) {
    $lock_key = 'game_bsc_gotit_async_sync_lock';
    if (get_transient($lock_key)) {
        return;
    }

    set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

    try {
        $state = game_bsc_gotit_async_sync_get_state();
        if (!empty($job_id) && !empty($state['job_id']) && (string) $state['job_id'] !== (string) $job_id) {
            return;
        }

        if (in_array((string) ($state['status'] ?? ''), ['done', 'error'], true)) {
            game_bsc_clear_gotit_async_worker_queue();
            return;
        }

        if (!empty($state['cancel_requested'])) {
            game_bsc_clear_gotit_async_worker_queue();
            game_bsc_gotit_async_sync_update_state([
                'status' => 'stopped',
                'message' => 'Đã dừng đồng bộ theo yêu cầu.',
                'finished_at' => current_time('mysql'),
                'cancel_requested' => 0,
            ]);
            return;
        }

        $runtime = game_bsc_gotit_sync_runtime_config();
        $sync_source = sanitize_text_field((string) ($state['source'] ?? 'manual-admin-async'));
        $category_queue = game_bsc_gotit_normalize_category_queue($state['category_queue'] ?? []);
        $category_index = max(0, (int) ($state['category_index'] ?? 0));

        if (!empty($category_queue)) {
            if ($category_index >= count($category_queue)) {
                $category_index = 0;
            }
            $gotit_category_id = (int) $category_queue[$category_index];
        }

        $start_page = max(1, (int) $start_page);
        $state_current_page = max(1, (int) ($state['current_page'] ?? 1));
        if ((string) ($state['status'] ?? '') === 'running' && $start_page < $state_current_page) {
            return;
        }

        game_bsc_gotit_async_sync_update_state([
            'job_id' => (string) $job_id,
            'status' => 'running',
            'message' => sprintf('Đang đồng bộ dữ liệu voucher từ Got It (trang %d)...', $start_page),
            'started_at' => !empty($state['started_at']) ? (string) $state['started_at'] : current_time('mysql'),
            'finished_at' => '',
            'gotit_category_id' => (int) $gotit_category_id,
            'requested_by' => (int) $requested_by,
            'current_page' => $start_page,
            'category_queue' => $category_queue,
            'category_index' => $category_index,
        ]);

        wp_suspend_cache_invalidation(true);
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);

        $result = game_bsc_sync_gotit_products_to_vouchers([
            'source' => $sync_source,
            'gotit_category_id' => (int) $gotit_category_id,
            'start_page' => $start_page,
            'pages_per_run' => (int) $runtime['pages_per_run'],
            'page_size' => (int) $runtime['page_size'],
            'lightweight_mode' => true,
        ]);

        wp_defer_comment_counting(false);
        wp_defer_term_counting(false);
        wp_suspend_cache_invalidation(false);

        if (!empty($result['success'])) {
            $created_total = (int) ($state['created'] ?? 0) + (int) ($result['created'] ?? 0);
            $updated_total = (int) ($state['updated'] ?? 0) + (int) ($result['updated'] ?? 0);
            $skipped_total = (int) ($state['skipped'] ?? 0) + (int) ($result['skipped'] ?? 0);
            $products_total = (int) ($state['products_count'] ?? 0) + (int) ($result['products_count'] ?? 0);
            $detail_total = (int) ($state['detail_calls'] ?? 0) + (int) ($result['detail_calls'] ?? 0);
            $errors_total = (int) ($state['errors_count'] ?? 0) + (is_array($result['errors'] ?? null) ? count($result['errors']) : 0);
            $pages_total = max((int) ($state['total_pages'] ?? 0), (int) ($result['total_pages'] ?? 0));
            $pages_processed_total = (int) ($state['pages_processed'] ?? 0) + (int) ($result['pages_processed'] ?? 0);

            $next_payload = [
                'filter_category' => (string) ($result['filter_category'] ?? ''),
                'created' => $created_total,
                'updated' => $updated_total,
                'skipped' => $skipped_total,
                'products_count' => $products_total,
                'detail_calls' => $detail_total,
                'errors_count' => $errors_total,
                'pages_processed' => $pages_processed_total,
                'current_page' => (int) ($result['current_page'] ?? $start_page),
                'total_pages' => $pages_total,
                'last_error' => '',
            ];

            if (!empty($result['is_complete'])) {
                $next_category_index = $category_index + 1;
                $has_next_category = !empty($category_queue) && $next_category_index < count($category_queue);

                if ($has_next_category) {
                    $next_category_id = (int) $category_queue[$next_category_index];

                    game_bsc_gotit_async_sync_update_state(array_merge($next_payload, [
                        'status' => 'running',
                        'message' => sprintf(
                            'Hoàn tất danh mục hiện tại, chuyển sang danh mục %d/%d...',
                            $next_category_index + 1,
                            count($category_queue)
                        ),
                        'gotit_category_id' => $next_category_id,
                        'category_index' => $next_category_index,
                        'current_page' => 1,
                        'total_pages' => 0,
                        'finished_at' => '',
                    ]));

                    game_bsc_schedule_gotit_async_worker((string) $job_id, $next_category_id, (int) $requested_by, 1, (int) $runtime['worker_delay_seconds'], false);
                } else {
                    game_bsc_clear_gotit_async_worker_queue();
                    game_bsc_gotit_async_sync_update_state(array_merge($next_payload, [
                        'status' => 'done',
                        'message' => 'Sync hoàn tất.',
                        'finished_at' => current_time('mysql'),
                        'cancel_requested' => 0,
                        'category_queue' => [],
                        'category_index' => 0,
                    ]));
                }
            } else {
                $next_page = max(1, (int) ($result['next_page'] ?? ($start_page + 1)));
                $latest_state = game_bsc_gotit_async_sync_get_state();
                if (!empty($latest_state['cancel_requested'])) {
                    game_bsc_clear_gotit_async_worker_queue();
                    game_bsc_gotit_async_sync_update_state(array_merge($next_payload, [
                        'status' => 'stopped',
                        'message' => 'Đã dừng đồng bộ theo yêu cầu.',
                        'finished_at' => current_time('mysql'),
                        'cancel_requested' => 0,
                        'category_queue' => [],
                        'category_index' => 0,
                    ]));
                } else {
                    game_bsc_gotit_async_sync_update_state(array_merge($next_payload, [
                        'status' => 'running',
                        'message' => sprintf(
                            'Đang đồng bộ dữ liệu voucher từ Got It (trang %d/%d)...',
                            $next_page,
                            max(1, $pages_total)
                        ),
                        'current_page' => $next_page,
                        'finished_at' => '',
                        'category_queue' => $category_queue,
                        'category_index' => $category_index,
                    ]));
                    game_bsc_schedule_gotit_async_worker((string) $job_id, (int) $gotit_category_id, (int) $requested_by, $next_page, (int) $runtime['worker_delay_seconds'], false);
                }
            }
        } else {
            game_bsc_clear_gotit_async_worker_queue();
            game_bsc_gotit_async_sync_update_state([
                'status' => 'error',
                'message' => (string) ($result['message'] ?? 'Sync thất bại.'),
                'finished_at' => current_time('mysql'),
                'last_error' => (string) ($result['message'] ?? 'Sync failed'),
                'cancel_requested' => 0,
                'category_queue' => [],
                'category_index' => 0,
            ]);
        }
    } catch (Throwable $e) {
        game_bsc_clear_gotit_async_worker_queue();
        game_bsc_gotit_async_sync_update_state([
            'status' => 'error',
            'message' => 'Sync lỗi: ' . $e->getMessage(),
            'finished_at' => current_time('mysql'),
            'last_error' => (string) $e->getMessage(),
            'cancel_requested' => 0,
            'category_queue' => [],
            'category_index' => 0,
        ]);
        error_log('[GotIt Async Sync] ' . $e->getMessage());
    } finally {
        delete_transient($lock_key);
        wp_defer_comment_counting(false);
        wp_defer_term_counting(false);
        wp_suspend_cache_invalidation(false);
    }
}
add_action('game_bsc_gotit_async_sync_event', 'game_bsc_run_gotit_async_sync_event', 10, 4);

// ===== SYNC DANH MỤC TỪ GOT IT =====

function game_bsc_ajax_gotit_sync_categories() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $map = $client->get_categories_map();

    if (empty($map)) {
        wp_send_json_error(['message' => 'Không lấy được danh mục từ Got It API. Kiểm tra API key và kết nối.'], 400);
    }

    $created  = 0;
    $existing = 0;

    foreach ($map as $cat_id => $cat_data) {
        // get_categories_map() trả về [id => ['name'=>..., 'slug'=>..., 'image'=>...]]
        $cat_name = trim((string) ($cat_data['name'] ?? $cat_data));
        $cat_slug = (string) ($cat_data['slug'] ?? '');
        $cat_image = esc_url_raw((string) ($cat_data['image'] ?? ''));
        if ($cat_name === '') continue;

        $upsert = game_bsc_gotit_upsert_category_term($cat_name, (int) $cat_id, $cat_slug, $cat_image);
        $term_id = (int) ($upsert['term_id'] ?? 0);
        if ($term_id < 1) {
            continue;
        }

        if (!empty($upsert['created'])) {
            $created++;
        } else {
            $existing++;
        }
    }

    wp_send_json_success([
        'message'    => 'Sync danh mục hoàn tất.',
        'created'    => $created,
        'existing'   => $existing,
        'total'      => count($map),
        'categories' => array_values(array_map(function($v) {
            return is_array($v) ? ($v['name'] ?? '') : (string) $v;
        }, $map)),
    ]);
}
add_action('wp_ajax_game_bsc_gotit_sync_categories', 'game_bsc_ajax_gotit_sync_categories');

// ===== DEBUG RAW API RESPONSE =====
function game_bsc_ajax_gotit_debug_raw() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.']);
    }

    // Lấy 1 product đầu tiên để xem raw fields
    $products_result = $client->get_products(1, 1, [
        'storeListPage'          => 1,
        'storeListPageSize'      => 5,
        'isExcludeStoreListInfo' => 'false',
    ]);

    // Lấy categories
    $categories_result = $client->get_categories();

    $first_product_raw = null;
    $first_product_id  = 0;
    if (!empty($products_result['data'])) {
        $data = $products_result['data'];
        $list = $data['products'] ?? $data['data'] ?? $data['items'] ?? (isset($data[0]) ? $data : []);
        if (!empty($list) && is_array($list)) {
            $first_product_raw = reset($list);
            $first_product_id  = (int) ($first_product_raw['id'] ?? $first_product_raw['productId'] ?? 0);
        }
    }

    // ===== GỌI /products/:productId (detail) để xem có categoryId không =====
    $detail_raw    = null;
    $detail_keys   = [];
    $detail_cat    = null;
    if ($first_product_id > 0) {
        $detail_result = $client->get_product_detail($first_product_id);
        if (!empty($detail_result['success']) && is_array($detail_result['data'])) {
            $detail_raw = $detail_result['data'];
            // Got It trả detail dạng array [0 => {product}] → unwrap
            if (isset($detail_raw[0]) && is_array($detail_raw[0]) && !isset($detail_raw['categoryId'])) {
                $detail_raw = $detail_raw[0];
            }
            $detail_keys = array_keys($detail_raw);
            $detail_cat  = [
                'categoryId'   => $detail_raw['categoryId']   ?? 'MISSING',
                'category_id'  => $detail_raw['category_id']  ?? 'MISSING',
                'categoryName' => $detail_raw['categoryName']  ?? 'MISSING',
                'category'     => $detail_raw['category']      ?? 'MISSING',
                'categories'   => $detail_raw['categories']    ?? 'MISSING',
            ];
        }
    }

    wp_send_json_success([
        'categories_raw'    => $categories_result['data'] ?? null,
        'categories_status' => $categories_result['http_code'] ?? null,
        'product_list_raw'  => $first_product_raw,
        'product_list_keys' => $first_product_raw ? array_keys($first_product_raw) : [],
        'product_list_category_fields' => $first_product_raw ? [
            'categoryId'   => $first_product_raw['categoryId']   ?? 'MISSING',
            'category_id'  => $first_product_raw['category_id']  ?? 'MISSING',
            'categoryName' => $first_product_raw['categoryName']  ?? 'MISSING',
            'category'     => $first_product_raw['category']      ?? 'MISSING',
        ] : null,
        'product_detail_raw'  => $detail_raw,
        'product_detail_keys' => $detail_keys,
        'product_detail_category_fields' => $detail_cat,
        'hint' => 'So sánh product_list_keys vs product_detail_keys để thấy detail có thêm field gì (categoryId, etc)',
    ]);
}

// ===== NÚT SYNC THỦ CÔNG TRÊN TRANG DANH SÁCH VOUCHER =====

/**
 * Thêm nút "Sync từ Got It" vào phía trên bảng danh sách post type game_vouchers
 */
function game_bsc_gotit_voucher_list_sync_button() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'game_vouchers' || $screen->base !== 'edit') {
        return;
    }
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        return;
    }

    $nonce = wp_create_nonce('game_bsc_gotit_test_nonce');

    // Lấy danh sách taxonomy terms để build dropdown
    $terms = get_terms([
        'taxonomy'   => 'game_voucher_category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    $select_html = '<select id="game-bsc-gotit-cat-select" style="margin-left:8px;vertical-align:middle;">'
        . '<option value="0">— Tất cả danh mục —</option>';

    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $gotit_cat_id = (int) get_term_meta($term->term_id, '_gotit_category_id', true);
            if ($gotit_cat_id < 1) continue; // bỏ qua term không phải từ Got It
            $select_html .= sprintf(
                '<option value="%d">%s</option>',
                $gotit_cat_id,
                esc_html($term->name)
            );
        }
    }
    $select_html .= '</select>';
    ?>
    <script>
    (function($) {
        $(document).ready(function() {
            var nonce = '<?php echo esc_js($nonce); ?>';

            var $select   = $(<?php echo wp_json_encode($select_html); ?>);
            var $btnVoucher = $(
                '<button type="button" id="game-bsc-gotit-sync-btn" class="button button-primary" style="margin-left:6px;">' +
                '<span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:4px;"></span>' +
                'Sync voucher' +
                '</button>'
            );
            var $btnCat = $(
                '<button type="button" id="game-bsc-gotit-sync-cat-btn" class="button" style="margin-left:6px;">' +
                '<span class="dashicons dashicons-category" style="vertical-align:middle;margin-right:4px;"></span>' +
                'Sync danh mục' +
                '</button>'
            );
            var $btnStop = $(
                '<button type="button" id="game-bsc-gotit-stop-btn" class="button" style="margin-left:6px;" disabled>' +
                '<span class="dashicons dashicons-no-alt" style="vertical-align:middle;margin-right:4px;"></span>' +
                'Dừng sync' +
                '</button>'
            );
            var $status = $('<span id="game-bsc-gotit-sync-status" style="margin-left:10px;font-style:italic;"></span>');

            $('.wrap .page-title-action').first()
                .after($status)
                .after($btnCat)
                .after($btnStop)
                .after($btnVoucher)
                .after($select);

            function setLoading(loading) {
                [$btnVoucher, $btnCat, $select].forEach(function($el) {
                    $el.prop('disabled', loading);
                });
                if (!loading) {
                    $btnStop.prop('disabled', true);
                }
                loading
                    ? $btnVoucher.find('.dashicons').addClass('spin')
                    : $btnVoucher.find('.dashicons').removeClass('spin');
                loading
                    ? $btnCat.find('.dashicons').addClass('spin')
                    : $btnCat.find('.dashicons').removeClass('spin');
            }

            var syncPollTimer = null;
            var syncPollEnabled = false;
            var syncPollInFlight = false;
            var syncPollDelayMs = 6000;
            var currentSyncLabel = 'tất cả danh mục';

            function stopSyncPoll() {
                syncPollEnabled = false;
                syncPollInFlight = false;
                if (syncPollTimer) {
                    clearTimeout(syncPollTimer);
                    syncPollTimer = null;
                }
            }

            function queueNextSyncPoll(delayMs) {
                if (!syncPollEnabled) {
                    return;
                }
                if (syncPollTimer) {
                    clearTimeout(syncPollTimer);
                }

                var adjustedDelay = delayMs;
                if (document.hidden) {
                    adjustedDelay = Math.max(delayMs, 15000);
                }

                syncPollTimer = setTimeout(function() {
                    pollSyncStatus();
                }, adjustedDelay);
            }

            function startSyncPoll(initialDelayMs) {
                syncPollEnabled = true;
                queueNextSyncPoll(Math.max(500, initialDelayMs || 1000));
            }

            function updateStopButtonByStatus(st) {
                var status = (st && st.status) ? st.status : '';
                var canStop = (status === 'queued' || status === 'running' || status === 'stopping');
                var isStopping = (status === 'stopping');
                $btnStop.prop('disabled', !canStop || isStopping);
            }

            function pollSyncStatus() {
                if (!syncPollEnabled || syncPollInFlight) {
                    return;
                }

                syncPollInFlight = true;
                $.post(ajaxurl, {
                    action: 'game_bsc_gotit_sync_vouchers_async_status',
                    nonce: nonce,
                }, function(statusRes) {
                    if (!statusRes.success || !statusRes.data || !statusRes.data.status) {
                        syncPollDelayMs = 12000;
                        return;
                    }

                    var st = statusRes.data.status;
                    updateStopButtonByStatus(st);

                    if (st.status === 'queued') {
                        syncPollDelayMs = 5000;
                        $status.css('color', '').text('⏳ Đang chờ worker chạy sync...');
                        return;
                    }

                    if (st.status === 'running') {
                        syncPollDelayMs = 7000;
                        var pageText = '';
                        if ((parseInt(st.current_page, 10) || 0) > 0 && (parseInt(st.total_pages, 10) || 0) > 0) {
                            pageText = ' (trang ' + st.current_page + '/' + st.total_pages + ')';
                        }
                        $status.css('color', '').text('🔄 Đang đồng bộ dữ liệu từ Got It' + pageText + '...');
                        return;
                    }

                    if (st.status === 'stopping') {
                        syncPollDelayMs = 4000;
                        $status.css('color', '#b26a00').text('🛑 Đang dừng đồng bộ, vui lòng chờ batch hiện tại hoàn tất...');
                        return;
                    }

                    if (st.status === 'stopped') {
                        stopSyncPoll();
                        setLoading(false);
                        $status.css('color', '#b26a00').text('🛑 Đồng bộ đã dừng theo yêu cầu.');
                        return;
                    }

                    if (st.status === 'done') {
                        stopSyncPoll();
                        setLoading(false);
                        $status.css('color', 'green').text(
                            '✅ Sync xong [' + (st.filter_category || currentSyncLabel) + ']' +
                            ' — Tạo mới: ' + (st.created || 0) +
                            ', Cập nhật: ' + (st.updated || 0) +
                            ', Bỏ qua: ' + (st.skipped || 0) +
                            ' (tổng ' + (st.products_count || 0) + ' sản phẩm)'
                        );
                        setTimeout(function() { location.reload(); }, 1800);
                        return;
                    }

                    if (st.status === 'error') {
                        stopSyncPoll();
                        setLoading(false);
                        $status.css('color', 'red').text('❌ ' + (st.last_error || st.message || 'Sync thất bại.'));
                    }
                }).fail(function() {
                    syncPollDelayMs = 12000;
                }).always(function() {
                    syncPollInFlight = false;
                    if (syncPollEnabled) {
                        queueNextSyncPoll(syncPollDelayMs);
                    }
                });
            }

            // === Sync voucher (có thể lọc theo danh mục) ===
            $btnVoucher.on('click', function() {
                if ($btnVoucher.prop('disabled')) return;
                var catId   = parseInt($select.val()) || 0;
                var catText = catId > 0
                    ? ($select.find('option:selected').text())
                    : 'tất cả danh mục';
                currentSyncLabel = catText;

                setLoading(true);
                $status.css('color', '').text('Đang xếp hàng sync voucher (' + catText + ')...');

                $.post(ajaxurl, {
                    action: 'game_bsc_gotit_sync_vouchers_async_start',
                    nonce: nonce,
                    gotit_category_id: catId,
                }, function(res) {
                    if (!res.success) {
                        var msg = (res.data && res.data.message) ? res.data.message : 'Không thể bắt đầu sync.';
                        $status.css('color', 'red').text('❌ ' + msg);
                        setLoading(false);
                        return;
                    }

                    $status.css('color', '').text('⏳ Sync đã được đưa vào hàng đợi. Đang theo dõi...');
                    updateStopButtonByStatus({ status: 'queued' });
                    stopSyncPoll();
                    syncPollDelayMs = 2000;
                    startSyncPoll(200);
                }).fail(function() {
                    $status.css('color', 'red').text('❌ Lỗi kết nối khi bắt đầu sync voucher.');
                    setLoading(false);
                });
            });

            $btnStop.on('click', function() {
                if ($btnStop.prop('disabled')) return;

                $btnStop.prop('disabled', true);
                $status.css('color', '#b26a00').text('🛑 Đang gửi yêu cầu dừng đồng bộ...');

                $.post(ajaxurl, {
                    action: 'game_bsc_gotit_sync_vouchers_async_stop',
                    nonce: nonce,
                }, function(res) {
                    if (!res.success) {
                        var msg = (res.data && res.data.message) ? res.data.message : 'Không thể dừng sync lúc này.';
                        $status.css('color', 'red').text('❌ ' + msg);
                        return;
                    }

                    $status.css('color', '#b26a00').text('🛑 Đã gửi yêu cầu dừng. Đang chờ worker xác nhận...');
                    if (!syncPollEnabled) {
                        startSyncPoll(300);
                    } else {
                        queueNextSyncPoll(300);
                    }
                }).fail(function() {
                    $status.css('color', 'red').text('❌ Lỗi kết nối khi gửi yêu cầu dừng.');
                });
            });

            // === Sync danh mục ===
            $btnCat.on('click', function() {
                if ($btnCat.prop('disabled')) return;
                setLoading(true);
                $status.css('color', '').text('Đang sync danh mục từ Got It...');

                $.post(ajaxurl, { action: 'game_bsc_gotit_sync_categories', nonce: nonce }, function(res) {
                    if (res.success) {
                        var d = res.data;
                        $status.css('color', 'green').text(
                            '✅ Danh mục xong! Tạo mới: ' + d.created +
                            ', Đã có: ' + d.existing +
                            ' (tổng ' + d.total + ' danh mục: ' + d.categories.join(', ') + ')'
                        );
                        // Reload để dropdown cập nhật danh mục mới
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        var msg = (res.data && res.data.message) ? res.data.message : 'Sync danh mục thất bại.';
                        $status.css('color', 'red').text('❌ ' + msg);
                    }
                }).fail(function() {
                    $status.css('color', 'red').text('❌ Lỗi kết nối khi sync danh mục.');
                }).always(function() { setLoading(false); });
            });
        });
    })(jQuery);
    </script>
    <style>
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .dashicons.spin { display:inline-block; animation: spin 1s linear infinite; }
    </style>
    <?php
}
add_action('admin_footer-edit.php', 'game_bsc_gotit_voucher_list_sync_button');

function game_bsc_add_gotit_voucher_sync_cron_schedules($schedules) {
    $schedules['game_bsc_every_14_days'] = [
        'interval' => 14 * DAY_IN_SECONDS,
        'display' => __('Mỗi 14 ngày', WG_GAME_PLUGIN_TEXTDOMAIN),
    ];

    return $schedules;
}
add_filter('cron_schedules', 'game_bsc_add_gotit_voucher_sync_cron_schedules');

function game_bsc_get_next_gotit_voucher_sync_timestamp() {
    $timezone = wp_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $next = $now->modify('this saturday')->setTime(2, 0, 0);

    if ($next <= $now) {
        $next = $next->modify('+7 days');
    }

    return $next->getTimestamp();
}

function game_bsc_schedule_gotit_voucher_sync_event() {
    $hook = 'game_bsc_gotit_daily_sync_event';
    $recurrence = 'game_bsc_every_14_days';

    if (function_exists('wp_get_scheduled_event')) {
        $scheduled_event = wp_get_scheduled_event($hook);
        if (
            $scheduled_event
            && !empty($scheduled_event->schedule)
            && (string) $scheduled_event->schedule !== $recurrence
        ) {
            wp_clear_scheduled_hook($hook);
        }
    }

    if (wp_next_scheduled($hook)) {
        return;
    }

    $next = game_bsc_get_next_gotit_voucher_sync_timestamp();
    wp_schedule_event($next, $recurrence, $hook);
}

function game_bsc_maybe_schedule_gotit_voucher_sync_event() {
    if (is_admin() && !wp_doing_cron()) {
        return;
    }

    if (get_transient('game_bsc_gotit_biweekly_schedule_checked')) {
        return;
    }

    set_transient('game_bsc_gotit_biweekly_schedule_checked', 1, 12 * HOUR_IN_SECONDS);
    game_bsc_schedule_gotit_voucher_sync_event();
}

function game_bsc_clear_gotit_voucher_sync_event() {
    wp_clear_scheduled_hook('game_bsc_gotit_daily_sync_event');
}

register_activation_hook(GAME_BSC_PLUGIN_FILE, 'game_bsc_schedule_gotit_voucher_sync_event');
register_deactivation_hook(GAME_BSC_PLUGIN_FILE, 'game_bsc_clear_gotit_voucher_sync_event');

function game_bsc_run_gotit_daily_sync_event() {
    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        return;
    }

    $category_ids = game_bsc_gotit_collect_category_ids_for_sync($client);
    $start_args = [
        'source' => 'daily-cron',
        'requested_by' => 0,
    ];

    if (!empty($category_ids)) {
        $start_args['category_queue'] = $category_ids;
        $start_args['category_index'] = 0;
        $start_args['gotit_category_id'] = (int) $category_ids[0];
    }

    $started = game_bsc_start_gotit_sync_job($start_args);

    if (empty($started['success'])) {
        error_log('[GotIt Sync] Daily queue failed: cannot start async job');
    } elseif (!empty($started['already_running'])) {
        error_log('[GotIt Sync] Daily queue skipped: another sync is running');
    }
}
add_action('game_bsc_gotit_daily_sync_event', 'game_bsc_run_gotit_daily_sync_event');

function game_bsc_ajax_gotit_get_products() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $page = 1;
    $total_pages = 1;
    $max_pages = 20;
    $products = [];
    $seen = [];

    $ids = sanitize_text_field((string) ($_POST['ids'] ?? $_GET['ids'] ?? ''));
    $store_list_page = absint($_POST['storeListPage'] ?? $_GET['storeListPage'] ?? 1);
    if ($store_list_page < 1) {
        $store_list_page = 1;
    }
    $store_list_page_size = absint($_POST['storeListPageSize'] ?? $_GET['storeListPageSize'] ?? 10);
    if ($store_list_page_size < 1) {
        $store_list_page_size = 10;
    }
    $is_exclude_store_info_raw = strtolower((string) ($_POST['isExcludeStoreListInfo'] ?? $_GET['isExcludeStoreListInfo'] ?? 'false'));
    $is_exclude_store_info = in_array($is_exclude_store_info_raw, ['1', 'true', 'yes'], true) ? 'true' : 'false';

    $extra_filters = [
        'storeListPage' => $store_list_page,
        'storeListPageSize' => $store_list_page_size,
        'isExcludeStoreListInfo' => $is_exclude_store_info,
    ];
    if ($ids !== '') {
        $extra_filters['ids'] = $ids;
    }

    $client_diag = method_exists($client, 'get_auth_diagnostics') ? $client->get_auth_diagnostics() : [];

    do {
        $result = $client->get_products($page, 100, $extra_filters);
        if (empty($result['success'])) {
            $http_code = (int) ($result['http_code'] ?? 0);
            $message = (string) ($result['error'] ?? 'Cannot fetch Got It products.');
            $hint = '';

            if ($http_code === 403 || strtolower($message) === 'forbidden') {
                $message = 'Forbidden khi goi API products tu Got It.';
                $hint = 'Kiem tra IP whitelist, API key dung moi truong (staging/production), quyen cua API key cho endpoint /api/v5.0/products, va so sanh request headers voi Postman.';
            }

            wp_send_json_error([
                'message' => $message,
                'hint' => $hint,
                'page' => $page,
                'http_code' => $http_code,
                'status_code' => (int) ($result['status_code'] ?? 0),
                'url' => (string) ($result['url'] ?? ''),
                'tried_urls' => (array) ($result['tried_urls'] ?? []),
                'raw' => $result['raw'] ?? '',
                'diagnostics' => $client_diag,
            ], $http_code > 0 ? $http_code : 400);
        }

        foreach (game_bsc_gotit_normalize_products($result['data'] ?? null) as $product) {
            if (!isset($seen[$product['productId']])) {
                $products[] = $product;
                $seen[$product['productId']] = true;
            }
        }

        $decoded = json_decode((string) ($result['raw'] ?? ''), true);
        $total_pages = max(
            1,
            (int) ($decoded['totalPage'] ?? 0),
            (int) ($decoded['data']['totalPage'] ?? 0),
            (int) ($decoded['paging']['totalPage'] ?? 0)
        );
        $page++;
    } while ($page <= $total_pages && $page <= $max_pages);

    wp_send_json_success([
        'products' => $products,
        'count' => count($products),
        'page_count' => min($total_pages, $max_pages),
        'request_filters' => $extra_filters,
    ]);
}

function game_bsc_ajax_gotit_ping() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error([
            'message' => 'Got It API key is not configured.',
            'environment' => get_option('game_bsc_gotit_environment', 'staging'),
        ], 400);
    }

    $result = $client->get_categories();
    wp_send_json_success([
        'configured' => true,
        'environment' => get_option('game_bsc_gotit_environment', 'staging'),
        'base_url' => $client->get_base_url(),
        'http_code' => $result['http_code'] ?? 0,
        'status_code' => $result['status_code'] ?? 0,
        'elapsed_ms' => $result['elapsed_ms'] ?? null,
        'success' => (bool) ($result['success'] ?? false),
        'error' => $result['error'] ?? null,
        'sample_count' => is_array($result['data']) ? count($result['data']) : 0,
        'raw' => $result['raw'] ?? '',
    ]);
}

function game_bsc_ajax_gotit_test_issue() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $mode = sanitize_text_field($_POST['mode'] ?? 'manual');
    $order_name = sanitize_text_field($_POST['order_name'] ?? game_bsc_gotit_source_value('default_order_name_test', 'BSC Game Voucher Test'));
    $expiry_days = absint($_POST['expiry_days'] ?? game_bsc_gotit_source_value('default_expiry_days', 30));
    if ($expiry_days < 1) $expiry_days = 30;

    $user_id = absint($_POST['user_id'] ?? 0);
    $voucher_post_id = 0;
    $product_id = 0;
    $product_price_id = 0;

    if ($mode === 'voucher') {
        $voucher_post_id = absint($_POST['voucher_post_id'] ?? 0);
        if ($voucher_post_id < 1) {
            wp_send_json_error(['message' => 'voucher_post_id is required for voucher mode.'], 400);
        }

        $product_id = (int) get_field('gotit_product_id', $voucher_post_id);
        $product_price_id = (int) get_field('gotit_product_price_id', $voucher_post_id);

        if ($product_id < 1 || $product_price_id < 1) {
            wp_send_json_error(['message' => 'Selected voucher does not have gotit_product_id or gotit_product_price_id.'], 400);
        }
    } else {
        $product_id = absint($_POST['product_id'] ?? 0);
        $product_price_id = absint($_POST['product_price_id'] ?? 0);
        if ($product_id < 1 || $product_price_id < 1) {
            wp_send_json_error(['message' => 'product_id and product_price_id are required.'], 400);
        }
    }

    $transaction_ref_id = $client->generate_transaction_ref_id($user_id, $voucher_post_id);
    $expiry_date = gmdate('Y-m-d', strtotime('+' . $expiry_days . ' days'));

    $result = $client->issue_voucher([
        'productId' => $product_id,
        'productPriceId' => $product_price_id,
        'quantity' => 1,
        'orderName' => $order_name,
        'expiryDate' => $expiry_date,
        'transactionRefId' => $transaction_ref_id,
    ]);

    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
    $issue_data = game_bsc_gotit_extract_issue_data($data);

    $partner_expiry_date = '';
    if (!empty($issue_data['is_partner_code']) && !empty($issue_data['expiry_date'])) {
        $partner_expiry_date = (string) $issue_data['expiry_date'];
    }

    $txn_save = game_bsc_insert_gotit_test_transaction([
        'redemption_id' => 0,
        'user_id' => $user_id,
        'voucher_post_id' => $voucher_post_id,
        'transaction_ref_id' => $transaction_ref_id,
        'gotit_order_name' => $order_name,
        'gotit_product_id' => $product_id,
        'gotit_product_price_id' => $product_price_id,
        'gotit_voucher_link' => $issue_data['voucher_link'] ?? '',
        'gotit_voucher_code' => $issue_data['voucher_code'] ?? '',
        'gotit_voucher_image' => $issue_data['voucher_image'] ?? '',
        'gotit_serial' => $issue_data['voucher_serial'] ?? '',
        'gotit_expiry_date' => $issue_data['expiry_date'] ?? '',
        'gotit_partner_expiry_date' => $partner_expiry_date,
        'gotit_vendor_name' => $issue_data['vendor_name'] ?? '',
        'gotit_is_partner_code' => (int) ($issue_data['is_partner_code'] ?? 0),
        'gotit_status' => (int) ($issue_data['status'] ?? 0),
        'gotit_raw_response' => $result['raw'] ?? '',
        'gotit_status_code' => (int) ($result['status_code'] ?? 0),
        'gotit_error_message' => (string) ($result['error'] ?? ''),
    ]);

    wp_send_json_success([
        'mode' => $mode,
        'transaction_ref_id' => $transaction_ref_id,
        'issue_result' => $result,
        'issue_data' => $issue_data,
        'db_saved' => $txn_save,
    ]);
}

function game_bsc_ajax_gotit_test_status() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $transaction_ref_id = sanitize_text_field($_POST['transaction_ref_id'] ?? '');
    if ($transaction_ref_id === '') {
        wp_send_json_error(['message' => 'transaction_ref_id is required.'], 400);
    }

    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';
    $db_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$prefix}gotit_transactions WHERE transaction_ref_id = %s ORDER BY id DESC LIMIT 1", $transaction_ref_id),
        ARRAY_A
    );

    $status_result = $client->check_voucher_status($transaction_ref_id);
    $ref_result = $client->get_vouchers_by_ref_id($transaction_ref_id, 1, 100, ['productInfo', 'usedInfo', 'groupInfo']);

    $ref_payload = is_array($ref_result['data'] ?? null) ? $ref_result['data'] : [];
    $ref_vouchers = game_bsc_gotit_extract_vouchers_from_ref_payload($ref_payload);
    $ref_pagination = game_bsc_gotit_extract_ref_pagination($ref_payload);
    $ref_summary = game_bsc_gotit_build_ref_voucher_summary($ref_vouchers);

    wp_send_json_success([
        'transaction_ref_id' => $transaction_ref_id,
        'api_result' => $status_result,
        'ref_vouchers_result' => $ref_result,
        'ref_vouchers_pagination' => $ref_pagination,
        'ref_vouchers_count' => count($ref_vouchers),
        'ref_vouchers_summary' => $ref_summary,
        'ref_vouchers' => $ref_vouchers,
        'db_record' => $db_row,
    ]);
}

function game_bsc_ajax_gotit_retry_txn() {
    game_bsc_gotit_test_guard();

    $transaction_id = absint($_POST['transaction_id'] ?? 0);
    if ($transaction_id < 1) {
        wp_send_json_error(['message' => 'transaction_id is required.'], 400);
    }

    if (!function_exists('game_bsc_retry_gotit_voucher')) {
        wp_send_json_error(['message' => 'Retry function is not available in current build.'], 500);
    }

    $result = call_user_func('game_bsc_retry_gotit_voucher', $transaction_id);
    if (!empty($result['success'])) {
        wp_send_json_success($result);
    }

    wp_send_json_error($result, 400);
}
