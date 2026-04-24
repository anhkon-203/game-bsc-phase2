<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('game_bsc_can_manage_voucher_excel')) {
    function game_bsc_can_manage_voucher_excel() {
        return current_user_can('admin_game') || current_user_can('administrator');
    }
}

if (!function_exists('game_bsc_is_voucher_list_screen')) {
    function game_bsc_is_voucher_list_screen() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen && $screen->base === 'edit' && $screen->post_type === 'game_vouchers';
    }
}

if (!function_exists('game_bsc_voucher_excel_safe_text')) {
    function game_bsc_voucher_excel_safe_text($value) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $first = substr($value, 0, 1);
        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }
}

if (!function_exists('game_bsc_store_voucher_excel_report')) {
    function game_bsc_store_voucher_excel_report(array $report) {
        $key = wp_generate_uuid4();
        set_transient('game_bsc_voucher_excel_report_' . $key, $report, 30 * MINUTE_IN_SECONDS);
        return $key;
    }
}

if (!function_exists('game_bsc_redirect_voucher_excel_report')) {
    function game_bsc_redirect_voucher_excel_report(array $report, $status = 'success') {
        $key = game_bsc_store_voucher_excel_report($report);
        $url = add_query_arg([
            'post_type' => 'game_vouchers',
            'game_bsc_voucher_excel_status' => sanitize_key((string) $status),
            'game_bsc_voucher_excel_report' => $key,
        ], admin_url('edit.php'));
        wp_safe_redirect($url);
        exit;
    }
}

if (!function_exists('game_bsc_read_sheet_cell')) {
    function game_bsc_read_sheet_cell($sheet, $column, $row) {
        $cell_ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . (int) $row;
        $value = $sheet->getCell($cell_ref)->getValue();

        if (is_object($value) || is_array($value)) {
            return '';
        }

        return trim((string) $value);
    }
}

if (!function_exists('game_bsc_voucher_excel_required_headers')) {
    function game_bsc_voucher_excel_required_headers() {
        return [
            'voucher_id',
            'voucher_code',
            'voucher_title',
            'voucher_type',
            'gotit_product_id',
            'gotit_product_price_id',
            'points_cost',
            'snapshot_post_modified_gmt',
        ];
    }
}

if (!function_exists('game_bsc_voucher_excel_supports_xlsx')) {
    function game_bsc_voucher_excel_supports_xlsx() {
        return class_exists('ZipArchive');
    }
}

if (!function_exists('game_bsc_voucher_excel_normalize_header')) {
    function game_bsc_voucher_excel_normalize_header($header) {
        $header = (string) $header;
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower(trim($header));
        $header = str_replace(['-', ' '], '_', $header);
        $header = preg_replace('/[^a-z0-9_]/', '', $header);
        $header = preg_replace('/_+/', '_', $header);
        return trim((string) $header, '_');
    }
}

if (!function_exists('game_bsc_voucher_excel_resolve_header_alias')) {
    function game_bsc_voucher_excel_resolve_header_alias($header) {
        $header = game_bsc_voucher_excel_normalize_header($header);
        if ($header === '') {
            return '';
        }

        $alias_map = [
            'id' => 'voucher_id',
            'voucherid' => 'voucher_id',
            'postid' => 'voucher_id',
            'post_id' => 'voucher_id',
            'voucher_id' => 'voucher_id',

            'vouchercode' => 'voucher_code',
            'code' => 'voucher_code',
            'voucher_code' => 'voucher_code',

            'vouchertitle' => 'voucher_title',
            'title' => 'voucher_title',
            'posttitle' => 'voucher_title',
            'post_title' => 'voucher_title',
            'voucher_title' => 'voucher_title',

            'vouchertype' => 'voucher_type',
            'type' => 'voucher_type',
            'voucher_type' => 'voucher_type',

            'gotitproductid' => 'gotit_product_id',
            'productid' => 'gotit_product_id',
            'product_id' => 'gotit_product_id',
            'gotit_product_id' => 'gotit_product_id',

            'gotitproductpriceid' => 'gotit_product_price_id',
            'productpriceid' => 'gotit_product_price_id',
            'product_price_id' => 'gotit_product_price_id',
            'gotit_product_price_id' => 'gotit_product_price_id',

            'pointscost' => 'points_cost',
            'pointcost' => 'points_cost',
            'points' => 'points_cost',
            'points_cost' => 'points_cost',

            'snapshotpostmodifiedgmt' => 'snapshot_post_modified_gmt',
            'postmodifiedgmt' => 'snapshot_post_modified_gmt',
            'post_modified_gmt' => 'snapshot_post_modified_gmt',
            'snapshot' => 'snapshot_post_modified_gmt',
            'snapshot_post_modified_gmt' => 'snapshot_post_modified_gmt',
        ];

        if (isset($alias_map[$header])) {
            return $alias_map[$header];
        }

        $compact_header = str_replace('_', '', $header);
        return isset($alias_map[$compact_header]) ? $alias_map[$compact_header] : $header;
    }
}

if (!function_exists('game_bsc_voucher_excel_build_header_map')) {
    function game_bsc_voucher_excel_build_header_map(array $header_values, $index_offset = 0) {
        $required_lookup = array_flip(game_bsc_voucher_excel_required_headers());
        $header_map = [];

        foreach ($header_values as $index => $header_value) {
            $canonical_header = game_bsc_voucher_excel_resolve_header_alias($header_value);
            if ($canonical_header === '' || !isset($required_lookup[$canonical_header])) {
                continue;
            }

            if (!isset($header_map[$canonical_header])) {
                $header_map[$canonical_header] = (int) $index + (int) $index_offset;
            }
        }

        return $header_map;
    }
}

if (!function_exists('game_bsc_voucher_excel_find_missing_headers')) {
    function game_bsc_voucher_excel_find_missing_headers(array $header_map) {
        $missing_headers = [];
        foreach (game_bsc_voucher_excel_required_headers() as $required_header) {
            if (!isset($header_map[$required_header])) {
                $missing_headers[] = $required_header;
            }
        }
        return $missing_headers;
    }
}

if (!function_exists('game_bsc_voucher_excel_is_third_party_type')) {
    function game_bsc_voucher_excel_is_third_party_type($voucher_type) {
        $normalized = strtoupper(str_replace('-', '_', trim((string) $voucher_type)));
        return in_array($normalized, ['THIRD_PARTY', 'THIRT_PARTY'], true);
    }
}

if (!function_exists('game_bsc_voucher_excel_normalize_snapshot')) {
    function game_bsc_voucher_excel_normalize_snapshot($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = trim((string) $value, " \t\n\r\0\x0B'\"");

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?$/', $value)) {
            $value = str_replace('T', ' ', $value);
            $value = preg_replace('/(?:\.\d+)?Z$/', '', $value);
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            $excel_serial = (float) $value;
            if ($excel_serial > 0) {
                $unix_time = (int) round(($excel_serial - 25569) * DAY_IN_SECONDS);
                if ($unix_time > 0) {
                    return gmdate('Y-m-d H:i:s', $unix_time);
                }
            }
        }

        return $value;
    }
}

if (!function_exists('game_bsc_voucher_excel_snapshots_conflict')) {
    function game_bsc_voucher_excel_snapshots_conflict($raw_snapshot, $db_snapshot) {
        $original_raw_snapshot = trim((string) $raw_snapshot);
        $raw_snapshot = game_bsc_voucher_excel_normalize_snapshot($raw_snapshot);
        $db_snapshot = game_bsc_voucher_excel_normalize_snapshot($db_snapshot);

        if ($raw_snapshot === '' || $db_snapshot === '') {
            return false;
        }

        if ($raw_snapshot === $db_snapshot) {
            return false;
        }

        $raw_time = strtotime($raw_snapshot);
        $db_time = strtotime($db_snapshot);
        if ($raw_time !== false && $db_time !== false && $raw_time === $db_time) {
            return false;
        }

        $raw_has_seconds = preg_match('/\d{2}:\d{2}:\d{2}$/', $original_raw_snapshot) === 1
            || preg_match('/T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?$/', $original_raw_snapshot) === 1;

        if (!$raw_has_seconds && $raw_time !== false && $db_time !== false) {
            if (gmdate('Y-m-d H:i', $raw_time) === gmdate('Y-m-d H:i', $db_time)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('game_bsc_voucher_excel_read_csv_rows')) {
    function game_bsc_voucher_excel_read_csv_rows($file_path, &$delimiter = ',', &$sep_line_number = null) {
        if (!is_readable($file_path)) {
            return [];
        }

        $raw_content = @file_get_contents($file_path);
        if ($raw_content === false || $raw_content === '') {
            return [];
        }

        $encoding = null;
        if (substr($raw_content, 0, 3) === "\xEF\xBB\xBF") {
            $encoding = 'UTF-8';
            $raw_content = substr($raw_content, 3);
        } elseif (substr($raw_content, 0, 2) === "\xFF\xFE") {
            $encoding = 'UTF-16LE';
            $raw_content = substr($raw_content, 2);
        } elseif (substr($raw_content, 0, 2) === "\xFE\xFF") {
            $encoding = 'UTF-16BE';
            $raw_content = substr($raw_content, 2);
        }

        if ($encoding === null) {
            $sample = substr($raw_content, 0, 65536);
            if (function_exists('mb_detect_encoding')) {
                $encoding = mb_detect_encoding(
                    $sample,
                    ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'Windows-1252', 'ISO-8859-1', 'ASCII'],
                    true
                );
            }
            if ($encoding === false || $encoding === null) {
                $encoding = 'Windows-1252';
            }
        }

        if (strtoupper((string) $encoding) !== 'UTF-8') {
            $converted = @iconv((string) $encoding, 'UTF-8//IGNORE', $raw_content);
            if (($converted === false || $converted === '') && function_exists('iconv')) {
                foreach (['Windows-1258', 'CP1258', 'Windows-1252', 'ISO-8859-1'] as $fallback_encoding) {
                    $converted = @iconv($fallback_encoding, 'UTF-8//IGNORE', $raw_content);
                    if (is_string($converted) && $converted !== '') {
                        break;
                    }
                }
            }
            if ($converted === false && function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($raw_content, 'UTF-8', (string) $encoding);
            }
            if (is_string($converted) && $converted !== '') {
                $raw_content = $converted;
            }
        }

        $raw_content = str_replace(["\r\n", "\r"], "\n", $raw_content);
        $lines = explode("\n", $raw_content);
        if (empty($lines)) {
            return [];
        }

        $delimiter = ',';
        $sep_line_index = null;
        for ($i = 0; $i < min(count($lines), 5); $i++) {
            $trimmed_line = trim((string) $lines[$i]);
            if ($i === 0) {
                $trimmed_line = preg_replace('/^\xEF\xBB\xBF/u', '', $trimmed_line);
            }
            if (stripos($trimmed_line, 'sep=') === 0) {
                $declared_delimiter = substr($trimmed_line, 4, 1);
                if ($declared_delimiter !== '') {
                    $delimiter = $declared_delimiter;
                    $sep_line_index = $i;
                    $sep_line_number = $i + 1;
                }
                break;
            }
        }

        if ($sep_line_index === null) {
            $candidates = [',', ';', "\t"];
            $best_delimiter = ',';
            $best_score = -1;

            foreach ($candidates as $candidate) {
                $score = 0;
                $samples = 0;
                for ($i = 0; $i < min(count($lines), 20); $i++) {
                    $sample_line = trim((string) $lines[$i]);
                    if ($sample_line === '') {
                        continue;
                    }
                    $fields = str_getcsv($sample_line, $candidate, '"');
                    $score += is_array($fields) ? count($fields) : 0;
                    $samples++;
                }

                if ($samples > 0 && $score > $best_score) {
                    $best_score = $score;
                    $best_delimiter = $candidate;
                }
            }

            $delimiter = $best_delimiter;
        }

        $rows = [];
        foreach ($lines as $line_index => $line) {
            if ($sep_line_index !== null && $line_index === $sep_line_index) {
                continue;
            }

            if ($line === '' || ctype_space($line)) {
                continue;
            }

            $cells = str_getcsv((string) $line, $delimiter, '"');
            if (!is_array($cells)) {
                continue;
            }

            if (isset($cells[0])) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string) $cells[0]);
            }

            foreach ($cells as &$cell) {
                if (is_string($cell)) {
                    $cell = trim($cell);
                }
            }
            unset($cell);

            if (count(array_filter($cells, static function ($value) {
                return $value !== '' && $value !== null;
            })) === 0) {
                continue;
            }

            $rows[] = [
                'line_number' => $line_index + 1,
                'cells' => $cells,
            ];
        }

        return $rows;
    }
}

if (!function_exists('game_bsc_voucher_excel_fetch_export_rows')) {
    function game_bsc_voucher_excel_fetch_export_rows($limit, $offset) {
        global $wpdb;

        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $meta_keys = [
            'voucher_code',
            'voucher_type',
            'gotit_product_id',
            'gotit_product_price_id',
            'points_cost',
        ];
        $post_statuses = ['publish', 'draft', 'pending', 'future', 'private'];

        $meta_key_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));

        $sql = "
            SELECT
                p.ID AS voucher_id,
                p.post_title AS voucher_title,
                p.post_modified_gmt AS snapshot_post_modified_gmt,
                MAX(CASE WHEN pm.meta_key = 'voucher_code' THEN pm.meta_value END) AS voucher_code,
                MAX(CASE WHEN pm.meta_key = 'voucher_type' THEN pm.meta_value END) AS voucher_type,
                MAX(CASE WHEN pm.meta_key = 'gotit_product_id' THEN pm.meta_value END) AS gotit_product_id,
                MAX(CASE WHEN pm.meta_key = 'gotit_product_price_id' THEN pm.meta_value END) AS gotit_product_price_id,
                MAX(CASE WHEN pm.meta_key = 'points_cost' THEN pm.meta_value END) AS points_cost
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} vt
                ON vt.post_id = p.ID
                AND vt.meta_key = 'voucher_type'
            LEFT JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID
                AND pm.meta_key IN ($meta_key_placeholders)
            WHERE p.post_type = %s
              AND p.post_status IN ($status_placeholders)
              AND UPPER(REPLACE(COALESCE(vt.meta_value, ''), '-', '_')) IN ('THIRD_PARTY', 'THIRT_PARTY')
            GROUP BY p.ID, p.post_title, p.post_modified_gmt
            ORDER BY p.ID ASC
            LIMIT %d OFFSET %d
        ";

        $args = array_merge($meta_keys, ['game_vouchers'], $post_statuses, [$limit, $offset]);
        $prepared_sql = $wpdb->prepare($sql, ...$args);
        if (!is_string($prepared_sql) || $prepared_sql === '') {
            return [];
        }

        $rows = $wpdb->get_results($prepared_sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('game_bsc_voucher_excel_extract_header_map')) {
    function game_bsc_voucher_excel_extract_header_map($sheet, &$detected_header_row = 1) {
        $required_count = count(game_bsc_voucher_excel_required_headers());
        $highest_data_row = (int) $sheet->getHighestDataRow();
        $max_scan_row = min(max($highest_data_row, 1), 30);

        $best_map = [];
        $best_row = 1;

        for ($row = 1; $row <= $max_scan_row; $row++) {
            $highest_col = $sheet->getHighestDataColumn($row);
            $highest_col_index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highest_col);
            if ($highest_col_index < 1) {
                continue;
            }

            $header_values = [];
            for ($col = 1; $col <= $highest_col_index; $col++) {
                $header_values[] = game_bsc_read_sheet_cell($sheet, $col, $row);
            }

            $candidate_map = game_bsc_voucher_excel_build_header_map($header_values, 1);
            if (count($candidate_map) > count($best_map)) {
                $best_map = $candidate_map;
                $best_row = $row;
            }

            if (count($candidate_map) === $required_count) {
                $detected_header_row = $row;
                return $candidate_map;
            }
        }

        $detected_header_row = $best_row;
        return $best_map;
    }
}

if (!function_exists('game_bsc_voucher_excel_render_tools')) {
    function game_bsc_voucher_excel_render_tools() {
        if (!game_bsc_is_voucher_list_screen() || !game_bsc_can_manage_voucher_excel()) {
            return;
        }

        $xlsx_supported = false;
        $report_key = sanitize_text_field((string) ($_GET['game_bsc_voucher_excel_report'] ?? ''));
        $report_status = sanitize_key((string) ($_GET['game_bsc_voucher_excel_status'] ?? 'success'));
        $report_summary = '';
        $report_errors = [];

        if ($report_key !== '') {
            $report = get_transient('game_bsc_voucher_excel_report_' . $report_key);
            if (is_array($report)) {
                delete_transient('game_bsc_voucher_excel_report_' . $report_key);
                $report_summary = isset($report['summary']) ? (string) $report['summary'] : '';
                $report_errors = isset($report['errors']) && is_array($report['errors']) ? $report['errors'] : [];
            }
        }
        $export_button_text = $xlsx_supported
            ? 'Xuất tệp THIRD_PARTY (.XLSX)'
            : 'Xuất tệp THIRD_PARTY (.CSV dự phòng)';
        $import_accept = '.csv';
        $export_button_text = 'Xuat tep THIRD_PARTY (.CSV)';
        $import_button_text = 'Nhap tep CSV';
        $import_button_text = $xlsx_supported ? 'Nhập tệp XLSX/CSV' : 'Nhập tệp CSV';

        $import_button_text = 'Nhap tep CSV';
        $export_nonce = wp_create_nonce('game_bsc_export_vouchers_excel');
        $import_nonce = wp_create_nonce('game_bsc_import_vouchers_excel');
        ?>
        <div id="game-bsc-voucher-excel-tools" class="game-bsc-voucher-excel-tools" style="display:none;">
            <div class="game-bsc-voucher-excel-tools__header">
                <div>
                    <p class="game-bsc-voucher-excel-tools__eyebrow">THIRD_PARTY CSV TOOLS</p>
                    <h2 class="game-bsc-voucher-excel-tools__title">Quản lý file voucher quà tặng</h2>
                </div>
                <span class="game-bsc-voucher-excel-tools__scope">Chỉ áp dụng cho voucher quà tặng (THIRD_PARTY)</span>
            </div>

            <div class="game-bsc-voucher-excel-tools__row">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="game-bsc-voucher-excel-tools__form game-bsc-voucher-excel-tools__form--export">
                    <input type="hidden" name="action" value="game_bsc_export_vouchers_excel" />
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($export_nonce); ?>" />
                    <button type="submit" class="button button-secondary game-bsc-voucher-excel-tools__btn-secondary"><?php echo esc_html($export_button_text); ?></button>
                </form>

                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="game-bsc-voucher-excel-tools__form game-bsc-voucher-excel-tools__form--import">
                    <input type="hidden" name="action" value="game_bsc_import_vouchers_excel" />
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($import_nonce); ?>" />
                    <input class="game-bsc-voucher-excel-tools__file" type="file" name="voucher_points_file" accept="<?php echo esc_attr($import_accept); ?>" required />
                    <select class="game-bsc-voucher-excel-tools__select" name="import_mode">
                        <option value="dry-run">Chạy thử (không cập nhật CSDL)</option>
                        <option value="apply">Áp dụng (cập nhật CSDL)</option>
                    </select>
                    <button type="submit" class="button button-primary game-bsc-voucher-excel-tools__btn-primary"><?php echo esc_html($import_button_text); ?></button>
                </form>
            </div>
            <p class="game-bsc-voucher-excel-tools__note">
                Chỉ chỉnh cột <strong>points_cost</strong>. Khuyến nghị chạy thử trước khi áp dụng.
            </p>
            <p class="game-bsc-voucher-excel-tools__note game-bsc-voucher-excel-tools__note--secondary">
                Lưu ý: không đổi tên cột header, không xóa dòng header.
            </p>
            <?php if (false): ?>
                <p style="margin:4px 0 0; color:#b32d2e;">
                    Máy chủ đang thiếu extension ZIP (ZipArchive), tạm thời chỉ dùng CSV cho nhập/xuất.
                </p>
            <?php endif; ?>
            <?php if ($report_summary !== '' || !empty($report_errors)): ?>
                <div class="game-bsc-voucher-excel-result game-bsc-voucher-excel-result--<?php echo esc_attr($report_status === 'error' ? 'error' : 'success'); ?>">
                    <p class="game-bsc-voucher-excel-result__title">
                        <strong><?php echo esc_html($report_status === 'error' ? 'Ket qua import Excel: that bai' : 'Ket qua import Excel: thanh cong'); ?></strong>
                    </p>
                    <?php if ($report_summary !== ''): ?>
                        <p class="game-bsc-voucher-excel-result__summary"><?php echo esc_html($report_summary); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($report_errors)): ?>
                        <div class="game-bsc-voucher-excel-result__errors">
                            <p><strong>Danh sach loi (toi da 20 dong dau):</strong></p>
                            <ul>
                                <?php foreach (array_slice($report_errors, 0, 20) as $item): ?>
                                    <li><?php echo esc_html((string) $item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .game-bsc-voucher-excel-tools {
                margin: 12px 0 0;
                padding: 14px;
                border: 1px solid #dcdcde;
                border-radius: 10px;
                background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
                box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
            }
            .game-bsc-voucher-excel-tools__header {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 12px;
            }
            .game-bsc-voucher-excel-tools__eyebrow {
                margin: 0 0 4px;
                color: #3858a6;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.08em;
            }
            .game-bsc-voucher-excel-tools__title {
                margin: 0;
                font-size: 18px;
                line-height: 1.3;
            }
            .game-bsc-voucher-excel-tools__scope {
                display: inline-flex;
                align-items: center;
                min-height: 30px;
                padding: 0 12px;
                border-radius: 999px;
                border: 1px solid #ffd8a8;
                background: #fff7ed;
                color: #9a3412;
                font-size: 12px;
                font-weight: 600;
            }
            .game-bsc-voucher-excel-tools__row {
                display: flex;
                flex-wrap: wrap;
                gap: 10px 12px;
                align-items: center;
            }
            .game-bsc-voucher-excel-tools__form {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
                margin: 0;
                padding: 8px;
                border: 1px solid #d0d7e2;
                border-radius: 8px;
                background: #ffffff;
            }
            .game-bsc-voucher-excel-tools__file {
                max-width: 260px;
            }
            .game-bsc-voucher-excel-tools__select {
                min-width: 210px;
            }
            .game-bsc-voucher-excel-tools__btn-secondary,
            .game-bsc-voucher-excel-tools__btn-primary {
                min-height: 32px;
            }
            .game-bsc-voucher-excel-tools__note {
                margin: 8px 0 0;
                color: #334155;
            }
            .game-bsc-voucher-excel-tools__note--secondary {
                margin-top: 4px;
                color: #50575e;
            }
            .game-bsc-voucher-excel-result {
                margin-top: 12px;
                padding: 12px;
                border: 1px solid #dcdcde;
                border-left-width: 4px;
                border-radius: 6px;
                background: #f6f7f7;
            }
            .game-bsc-voucher-excel-result--success {
                border-left-color: #00a32a;
            }
            .game-bsc-voucher-excel-result--error {
                border-left-color: #d63638;
                background: #fcf0f1;
            }
            .game-bsc-voucher-excel-result__title,
            .game-bsc-voucher-excel-result__summary,
            .game-bsc-voucher-excel-result__errors p {
                margin: 0 0 8px;
            }
            .game-bsc-voucher-excel-result__errors ul {
                margin: 0 0 0 18px;
                list-style: disc;
            }
            @media (max-width: 900px) {
                .game-bsc-voucher-excel-tools__form {
                    width: 100%;
                }
                .game-bsc-voucher-excel-tools__file,
                .game-bsc-voucher-excel-tools__select {
                    width: 100%;
                    max-width: none;
                }
            }
        </style>
        <script>
            (function() {
                var box = document.getElementById('game-bsc-voucher-excel-tools');
                if (!box) {
                    return;
                }

                var heading = document.querySelector('.wrap h1.wp-heading-inline');
                if (heading && heading.parentNode) {
                    heading.insertAdjacentElement('afterend', box);
                } else {
                    var wrap = document.querySelector('.wrap');
                    if (wrap) {
                        wrap.insertAdjacentElement('afterbegin', box);
                    }
                }
                box.style.display = 'block';
            })();
        </script>
        <?php
    }
}
add_action('admin_footer-edit.php', 'game_bsc_voucher_excel_render_tools', 30);

if (!function_exists('game_bsc_render_voucher_excel_notice')) {
    function game_bsc_render_voucher_excel_notice() {
        if (!game_bsc_is_voucher_list_screen() || !game_bsc_can_manage_voucher_excel()) {
            return;
        }

        $report_key = sanitize_text_field((string) ($_GET['game_bsc_voucher_excel_report'] ?? ''));
        if ($report_key === '') {
            return;
        }

        $status = sanitize_key((string) ($_GET['game_bsc_voucher_excel_status'] ?? 'success'));
        $report = get_transient('game_bsc_voucher_excel_report_' . $report_key);
        if (!is_array($report)) {
            return;
        }

        delete_transient('game_bsc_voucher_excel_report_' . $report_key);

        $class = ($status === 'error') ? 'notice notice-error' : 'notice notice-success';
        $title = ($status === 'error') ? 'Xử lý Excel thất bại.' : 'Xử lý Excel thành công.';
        $summary = isset($report['summary']) ? (string) $report['summary'] : '';
        $errors = isset($report['errors']) && is_array($report['errors']) ? $report['errors'] : [];
        ?>
        <div class="<?php echo esc_attr($class); ?> is-dismissible game-bsc-voucher-excel-notice">
            <p><strong><?php echo esc_html($title); ?></strong></p>
            <?php if ($summary !== ''): ?>
                <p><?php echo esc_html($summary); ?></p>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <p><strong>Danh sách lỗi (tối đa 20 dòng đầu):</strong></p>
                <ul style="margin-left:20px; list-style:disc;">
                    <?php foreach (array_slice($errors, 0, 20) as $item): ?>
                        <li><?php echo esc_html((string) $item); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}
// Render import/export result inside the voucher Excel tools section instead of admin notices.

if (!function_exists('game_bsc_render_voucher_excel_notice_fallback')) {
    function game_bsc_render_voucher_excel_notice_fallback() {
        return;
        ?>
        <script>
            (function() {
                function insertFallbackNotice() {
                    var staleNotices = document.querySelectorAll('.game-bsc-voucher-excel-notice');
                    if (staleNotices && staleNotices.length) {
                        staleNotices.forEach(function(node) {
                            if (node && node.parentNode) {
                                node.parentNode.removeChild(node);
                            }
                        });
                    }

                    var wrap = document.querySelector('.wrap');
                    if (!wrap) {
                        return;
                    }

                    var notice = document.createElement('div');
                    notice.className = 'notice is-dismissible game-bsc-voucher-excel-notice <?php echo esc_js($status === 'error' ? 'notice-error' : 'notice-success'); ?>';
                    notice.style.marginTop = '12px';
                    notice.style.display = 'block';
                    notice.style.visibility = 'visible';
                    notice.style.clear = 'both';

                    var title = document.createElement('p');
                    title.innerHTML = '<strong>' + <?php echo wp_json_encode($status === 'error' ? 'Xử lý Excel thất bại.' : 'Xử lý Excel thành công.'); ?> + '</strong>';

                    var body = document.createElement('p');
                    body.textContent = <?php echo wp_json_encode($message); ?>;

                    notice.appendChild(title);
                    notice.appendChild(body);

                    var heading = wrap.querySelector('h1');
                    if (heading && heading.parentNode) {
                        heading.insertAdjacentElement('afterend', notice);
                    } else {
                        wrap.insertAdjacentElement('afterbegin', notice);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', insertFallbackNotice);
                } else {
                    insertFallbackNotice();
                }
            })();
        </script>
        <?php
    }
}
add_action('admin_footer-edit.php', 'game_bsc_render_voucher_excel_notice_fallback', 40);

if (!function_exists('game_bsc_voucher_excel_update_points')) {
    function game_bsc_voucher_excel_update_points($post_id, $points_cost, $user_id) {
        $post_id = (int) $post_id;
        $points_cost = max(1, (int) $points_cost);
        $user_id = (int) $user_id;

        if (function_exists('update_field')) {
            update_field('points_cost', $points_cost, $post_id);
        } else {
            update_post_meta($post_id, 'points_cost', $points_cost);
        }

        update_post_meta($post_id, '_game_bsc_points_cost_locked', 1);
        update_post_meta($post_id, '_game_bsc_points_cost_locked_by', $user_id);
        update_post_meta($post_id, '_game_bsc_points_cost_locked_at', current_time('mysql'));
    }
}

if (!function_exists('game_bsc_voucher_excel_log_history')) {
    function game_bsc_voucher_excel_log_history($file_name, $file_url, $file_author, array $report) {
        global $wpdb;

        $table = $wpdb->prefix . 'game_voucher_points_import_history';
        $summary_json = wp_json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($summary_json === false) {
            $summary_json = '{}';
        }

        $wpdb->insert($table, [
            'file_name' => sanitize_text_field((string) $file_name),
            'file_url' => esc_url_raw((string) $file_url),
            'file_author' => (int) $file_author,
            'mode' => sanitize_text_field((string) ($report['mode'] ?? 'dry-run')),
            'total_rows' => (int) ($report['total_rows'] ?? 0),
            'updated_rows' => (int) ($report['updated_rows'] ?? 0),
            'skipped_rows' => (int) ($report['skipped_rows'] ?? 0),
            'conflict_rows' => (int) ($report['conflict_rows'] ?? 0),
            'error_rows' => (int) ($report['error_rows'] ?? 0),
            'summary_json' => $summary_json,
            'uploaded_at' => current_time('mysql'),
        ]);
    }
}

if (!function_exists('game_bsc_handle_export_vouchers_excel')) {
    function game_bsc_handle_export_vouchers_excel() {
        if (!game_bsc_can_manage_voucher_excel()) {
            wp_die('Ban khong co quyen thuc hien hanh dong nay.');
        }

        check_admin_referer('game_bsc_export_vouchers_excel');

        if (function_exists('game_bsc_log_settings_change')) {
            game_bsc_log_settings_change(
                'game_bsc_voucher_excel_export',
                [],
                [
                    'requested_by' => (int) get_current_user_id(),
                    'xlsx_supported' => 0,
                    'triggered_at' => game_now(),
                ],
                'update'
            );
        }

        @set_time_limit(0);
        @ini_set('display_errors', '0');
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        if (true) {
            $headers = game_bsc_voucher_excel_required_headers();
            $filename = 'game-vouchers-third-party-points-' . gmdate('Ymd-His') . '.csv';
            $temp_file = wp_tempnam($filename);

            if (!is_string($temp_file) || $temp_file === '') {
                game_bsc_redirect_voucher_excel_report([
                    'summary' => 'Khong tao duoc file tam de export CSV.',
                    'errors' => ['He thong khong tao duoc temp file.'],
                ], 'error');
            }

            $temp_handle = fopen($temp_file, 'w');
            if ($temp_handle === false) {
                @unlink($temp_file);
                game_bsc_redirect_voucher_excel_report([
                    'summary' => 'Khong mo duoc file tam de export CSV.',
                    'errors' => ['He thong khong mo duoc temp file.'],
                ], 'error');
            }

            try {
                fwrite($temp_handle, "\xEF\xBB\xBF");
                fputcsv($temp_handle, $headers);

                $page = 0;
                $per_page = 500;

                do {
                    $rows = game_bsc_voucher_excel_fetch_export_rows($per_page, $page * $per_page);
                    if (empty($rows)) {
                        break;
                    }

                    foreach ($rows as $row) {
                        fputcsv($temp_handle, [
                            (int) ($row['voucher_id'] ?? 0),
                            game_bsc_voucher_excel_safe_text((string) ($row['voucher_code'] ?? '')),
                            game_bsc_voucher_excel_safe_text((string) ($row['voucher_title'] ?? '')),
                            game_bsc_voucher_excel_safe_text((string) ($row['voucher_type'] ?? '')),
                            game_bsc_voucher_excel_safe_text((string) ($row['gotit_product_id'] ?? '')),
                            game_bsc_voucher_excel_safe_text((string) ($row['gotit_product_price_id'] ?? '')),
                            (int) ($row['points_cost'] ?? 0),
                            (string) ($row['snapshot_post_modified_gmt'] ?? ''),
                        ]);
                    }

                    $page++;
                    fflush($temp_handle);
                } while (count($rows) === $per_page);

                fclose($temp_handle);

                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                nocache_headers();
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment;filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                header('Pragma: public');

                $streamed = readfile($temp_file);
                @unlink($temp_file);

                if ($streamed === false) {
                    wp_die('Khong the xuat file CSV.');
                }
                exit;
            } catch (Throwable $e) {
                fclose($temp_handle);
                @unlink($temp_file);
                game_bsc_redirect_voucher_excel_report([
                    'summary' => 'Export CSV that bai.',
                    'errors' => ['Exception: ' . $e->getMessage()],
                ], 'error');
            }
        }

        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || !class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'Khong tim thay PhpSpreadsheet trong he thong.',
                'errors' => ['PhpSpreadsheet chua duoc nap.'],
            ], 'error');
        }

        $headers = game_bsc_voucher_excel_required_headers();
        $filename = 'game-vouchers-third-party-points-' . gmdate('Ymd-His') . '.xlsx';
        $temp_file = wp_tempnam($filename);

        if (!is_string($temp_file) || $temp_file === '') {
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'Khong tao duoc file tam de export.',
                'errors' => ['He thong khong tao duoc temp file.'],
            ], 'error');
        }

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('voucher_points');

            foreach ($headers as $index => $header) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit(
                    $column . '1',
                    (string) $header,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }

            $page = 0;
            $per_page = 500;
            $row_number = 2;

            do {
                $rows = game_bsc_voucher_excel_fetch_export_rows($per_page, $page * $per_page);
                if (empty($rows)) {
                    break;
                }

                foreach ($rows as $row) {
                    $sheet->setCellValueExplicit('A' . $row_number, (string) ((int) ($row['voucher_id'] ?? 0)), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('B' . $row_number, game_bsc_voucher_excel_safe_text((string) ($row['voucher_code'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('C' . $row_number, game_bsc_voucher_excel_safe_text((string) ($row['voucher_title'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('D' . $row_number, game_bsc_voucher_excel_safe_text((string) ($row['voucher_type'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('E' . $row_number, game_bsc_voucher_excel_safe_text((string) ($row['gotit_product_id'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('F' . $row_number, game_bsc_voucher_excel_safe_text((string) ($row['gotit_product_price_id'] ?? '')), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('G' . $row_number, (string) ((int) ($row['points_cost'] ?? 0)), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('H' . $row_number, (string) ($row['snapshot_post_modified_gmt'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $row_number++;
                }

                $page++;

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            } while (count($rows) === $per_page);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            if (method_exists($writer, 'setPreCalculateFormulas')) {
                $writer->setPreCalculateFormulas(false);
            }
            $writer->save($temp_file);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            unset($writer);

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            nocache_headers();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');

            $streamed = readfile($temp_file);
            @unlink($temp_file);

            if ($streamed === false) {
                wp_die('Khong the xuat file XLSX.');
            }
            exit;
        } catch (Throwable $e) {
            if (isset($spreadsheet) && $spreadsheet instanceof \PhpOffice\PhpSpreadsheet\Spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }
            @unlink($temp_file);
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'Export that bai.',
                'errors' => ['Exception: ' . $e->getMessage()],
            ], 'error');
        }
    }
}
add_action('admin_post_game_bsc_export_vouchers_excel', 'game_bsc_handle_export_vouchers_excel');

if (!function_exists('game_bsc_process_voucher_rows')) {
    function game_bsc_process_voucher_rows($rows, $mode = 'dry-run') {
        $mode = ($mode === 'apply') ? 'apply' : 'dry-run';

        $report = [
            'mode' => $mode,
            'total_rows' => 0,
            'valid_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'conflict_rows' => 0,
            'error_rows' => 0,
            'errors' => [],
            'summary' => '',
        ];

        $seen_voucher_ids = [];
        $rows_to_apply = [];
        $operator_id = get_current_user_id();
        $max_error_messages = 200;
        $hidden_error_count = 0;

        $append_error = static function (array &$report, $message) use ($max_error_messages, &$hidden_error_count) {
            if (count($report['errors']) < $max_error_messages) {
                $report['errors'][] = (string) $message;
                return;
            }
            $hidden_error_count++;
        };

        $flush_apply_rows = static function (array &$rows_to_apply, array &$report, $operator_id) {
            if (empty($rows_to_apply)) {
                return;
            }

            foreach ($rows_to_apply as $item) {
                game_bsc_voucher_excel_update_points(
                    (int) $item['voucher_id'],
                    (int) $item['points_cost'],
                    (int) $operator_id
                );
                $report['updated_rows']++;
            }

            $rows_to_apply = [];
        };

        foreach ($rows as $row_data) {
            $row = (int) ($row_data['row_number'] ?? 0);
            $voucher_id_raw = trim((string) ($row_data['voucher_id'] ?? ''));
            $points_cost_raw = trim((string) ($row_data['points_cost'] ?? ''));
            $snapshot_raw = trim((string) ($row_data['snapshot_post_modified_gmt'] ?? ''));

            if ($voucher_id_raw === '' && $points_cost_raw === '' && $snapshot_raw === '') {
                continue;
            }

            $report['total_rows']++;

            if (!preg_match('/^\d+$/', $voucher_id_raw) || (int) $voucher_id_raw < 1) {
                $report['error_rows']++;
                $append_error($report, 'Dong ' . $row . ': voucher_id khong hop le.');
                continue;
            }

            $voucher_id = (int) $voucher_id_raw;
            if (isset($seen_voucher_ids[$voucher_id])) {
                $report['error_rows']++;
                $append_error($report, 'Dong ' . $row . ': voucher_id bi trung trong file (' . $voucher_id . ').');
                continue;
            }
            $seen_voucher_ids[$voucher_id] = true;

            $post_type = (string) get_post_field('post_type', $voucher_id);
            if ($post_type !== 'game_vouchers') {
                $report['error_rows']++;
                $append_error($report, 'Dong ' . $row . ': voucher_id khong ton tai hoac khong phai game_vouchers.');
                continue;
            }

            $voucher_type = (string) get_post_meta($voucher_id, 'voucher_type', true);
            if (!game_bsc_voucher_excel_is_third_party_type($voucher_type)) {
                $report['error_rows']++;
                $append_error($report, 'Dong ' . $row . ': chi cho phep import voucher THIRD_PARTY.');
                continue;
            }

            if (!preg_match('/^-?\d+$/', $points_cost_raw)) {
                $report['error_rows']++;
                $append_error($report, 'Dong ' . $row . ': points_cost phai la so nguyen.');
                continue;
            }

            $new_points_cost = (int) $points_cost_raw;
            if ($new_points_cost < 1) {
                $report['error_rows']++;
                $append_error($report, 'Dong ' . $row . ': points_cost phai >= 1.');
                continue;
            }

            $db_snapshot = (string) get_post_field('post_modified_gmt', $voucher_id);
            if (game_bsc_voucher_excel_snapshots_conflict($snapshot_raw, $db_snapshot)) {
                $report['conflict_rows']++;
                $append_error($report, 'Dong ' . $row . ': du lieu da thay doi tren he thong (snapshot conflict), bo qua dong nay.');
                continue;
            }

            $current_points = (int) get_post_meta($voucher_id, 'points_cost', true);
            if ($current_points === $new_points_cost) {
                $report['skipped_rows']++;
                continue;
            }

            $report['valid_rows']++;

            if ($mode === 'apply') {
                $rows_to_apply[] = [
                    'voucher_id' => $voucher_id,
                    'points_cost' => $new_points_cost,
                ];

                // Flush in small batches to keep memory usage stable.
                if (count($rows_to_apply) >= 200) {
                    $flush_apply_rows($rows_to_apply, $report, $operator_id);
                }
            }

            if (($report['total_rows'] % 500) === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        if ($mode === 'apply' && !empty($rows_to_apply)) {
            $flush_apply_rows($rows_to_apply, $report, $operator_id);
        }

        if ($hidden_error_count > 0) {
            $report['errors'][] = 'Con ' . (int) $hidden_error_count . ' loi khac khong hien thi de tranh tran bo nho.';
        }

        if ($mode === 'dry-run') {
            $report['summary'] = sprintf(
                'Dry-run xong: %d dong hop le, %d dong conflict, %d dong skip, %d dong loi.',
                (int) $report['valid_rows'],
                (int) $report['conflict_rows'],
                (int) $report['skipped_rows'],
                (int) $report['error_rows']
            );
        } else {
            $report['summary'] = sprintf(
                'Apply xong: %d dong cap nhat, %d dong conflict, %d dong skip, %d dong loi.',
                (int) $report['updated_rows'],
                (int) $report['conflict_rows'],
                (int) $report['skipped_rows'],
                (int) $report['error_rows']
            );
        }

        return $report;
    }
}

if (!function_exists('game_bsc_process_voucher_csv_file')) {
    function game_bsc_process_voucher_csv_file($file_path, $mode = 'dry-run') {
        $mode = ($mode === 'apply') ? 'apply' : 'dry-run';

        $error_report = [
            'mode' => $mode,
            'total_rows' => 0,
            'valid_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'conflict_rows' => 0,
            'error_rows' => 1,
            'errors' => [],
            'summary' => '',
        ];

        $delimiter = ',';
        $sep_line_number = null;
        $csv_rows = game_bsc_voucher_excel_read_csv_rows($file_path, $delimiter, $sep_line_number);
        if (empty($csv_rows)) {
            $error_report['errors'][] = 'Khong mo duoc file CSV hoac file rong.';
            $error_report['summary'] = 'File CSV khong hop le.';
            return $error_report;
        }

        $required_count = count(game_bsc_voucher_excel_required_headers());
        $best_header_row_index = null;
        $best_header_line_number = null;
        $best_header_map = [];

        for ($i = 0; $i < min(count($csv_rows), 120); $i++) {
            $cells = isset($csv_rows[$i]['cells']) && is_array($csv_rows[$i]['cells']) ? $csv_rows[$i]['cells'] : [];
            $candidate_map = game_bsc_voucher_excel_build_header_map($cells, 0);
            if (count($candidate_map) > count($best_header_map)) {
                $best_header_map = $candidate_map;
                $best_header_row_index = $i;
                $best_header_line_number = (int) ($csv_rows[$i]['line_number'] ?? ($i + 1));
            }

            if (count($candidate_map) === $required_count) {
                $best_header_map = $candidate_map;
                $best_header_row_index = $i;
                $best_header_line_number = (int) ($csv_rows[$i]['line_number'] ?? ($i + 1));
                break;
            }
        }

        $missing_headers = game_bsc_voucher_excel_find_missing_headers($best_header_map);
        if ($best_header_row_index === null || !empty($missing_headers)) {
            $error_report['errors'][] = 'Thieu cot bat buoc: ' . implode(', ', $missing_headers);
            if ($best_header_line_number !== null) {
                $error_report['errors'][] = 'Khong nhan dien du header day du (dong nghi ngo header: ' . (int) $best_header_line_number . ').';
            } else {
                $error_report['errors'][] = 'Khong tim thay dong header hop le trong 30 dong dau file.';
            }
            $error_report['summary'] = 'File khong hop le: thieu cot bat buoc.';
            return $error_report;
        }

        $row_generator = (function () use ($csv_rows, $best_header_row_index, $best_header_map) {
            for ($i = $best_header_row_index + 1; $i < count($csv_rows); $i++) {
                $csv_row = isset($csv_rows[$i]['cells']) && is_array($csv_rows[$i]['cells']) ? $csv_rows[$i]['cells'] : [];
                yield [
                    'row_number' => (int) ($csv_rows[$i]['line_number'] ?? ($i + 1)),
                    'voucher_id' => isset($csv_row[$best_header_map['voucher_id']]) ? $csv_row[$best_header_map['voucher_id']] : '',
                    'points_cost' => isset($csv_row[$best_header_map['points_cost']]) ? $csv_row[$best_header_map['points_cost']] : '',
                    'snapshot_post_modified_gmt' => isset($csv_row[$best_header_map['snapshot_post_modified_gmt']]) ? $csv_row[$best_header_map['snapshot_post_modified_gmt']] : '',
                ];
            }
        })();

        return game_bsc_process_voucher_rows($row_generator, $mode);
    }
}

if (!function_exists('game_bsc_process_voucher_excel_file')) {
    function game_bsc_process_voucher_excel_file($file_path, $mode = 'dry-run', $file_ext = 'csv') {
        $mode = ($mode === 'apply') ? 'apply' : 'dry-run';
        $file_ext = strtolower((string) $file_ext);

        if ($file_ext !== 'csv') {
            return [
                'mode' => $mode,
                'total_rows' => 0,
                'valid_rows' => 0,
                'updated_rows' => 0,
                'skipped_rows' => 0,
                'conflict_rows' => 0,
                'error_rows' => 1,
                'errors' => ['Chi ho tro file .csv cho chuc nang nay.'],
                'summary' => 'Dinh dang file khong hop le.',
            ];
        }

        return game_bsc_process_voucher_csv_file($file_path, $mode);
    }
}

if (!function_exists('game_bsc_handle_import_vouchers_excel')) {
    function game_bsc_handle_import_vouchers_excel() {
        if (!game_bsc_can_manage_voucher_excel()) {
            wp_die('Ban khong co quyen thuc hien hanh dong nay.');
        }

        check_admin_referer('game_bsc_import_vouchers_excel');

        @set_time_limit(0);
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $mode = sanitize_text_field((string) ($_POST['import_mode'] ?? 'dry-run'));
        $mode = ($mode === 'apply') ? 'apply' : 'dry-run';

        if (empty($_FILES['voucher_points_file']) || !isset($_FILES['voucher_points_file']['error'])) {
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'Khong tim thay file upload.',
                'errors' => ['Vui long chon file .csv de import.'],
            ], 'error');
        }

        if ((int) $_FILES['voucher_points_file']['error'] !== UPLOAD_ERR_OK) {
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'Upload file that bai.',
                'errors' => ['Ma loi upload: ' . (int) $_FILES['voucher_points_file']['error']],
            ], 'error');
        }

        if ((int) $_FILES['voucher_points_file']['size'] > WG_GAME_MAX_UPLOAD_FILE_SIZE) {
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'File vuot qua gioi han dung luong.',
                'errors' => ['Dung luong toi da: ' . (int) (WG_GAME_MAX_UPLOAD_FILE_SIZE / (1024 * 1024)) . 'MB.'],
            ], 'error');
        }

        $ext = strtolower((string) pathinfo((string) $_FILES['voucher_points_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'Dinh dang file khong hop le.',
                'errors' => ['Chi ho tro file .csv.'],
            ], 'error');
        }

        $upload = wp_handle_upload($_FILES['voucher_points_file'], [
            'test_form' => false,
            'mimes' => [
                'csv' => 'text/csv',
            ],
        ]);

        if (!empty($upload['error'])) {
            game_bsc_redirect_voucher_excel_report([
                'summary' => 'Upload file that bai.',
                'errors' => [(string) $upload['error']],
            ], 'error');
        }

        $file_path = (string) ($upload['file'] ?? '');
        $file_url = (string) ($upload['url'] ?? '');
        $file_name = (string) ($_FILES['voucher_points_file']['name'] ?? basename($file_path));

        $cache_suspend_state = null;
        if (function_exists('wp_suspend_cache_addition')) {
            $cache_suspend_state = wp_suspend_cache_addition(true);
        }

        try {
            $report = game_bsc_process_voucher_excel_file($file_path, $mode, $ext);
            game_bsc_voucher_excel_log_history($file_name, $file_url, get_current_user_id(), $report);
        } catch (Throwable $e) {
            $report = [
                'mode' => $mode,
                'total_rows' => 0,
                'valid_rows' => 0,
                'updated_rows' => 0,
                'skipped_rows' => 0,
                'conflict_rows' => 0,
                'error_rows' => 1,
                'errors' => ['Exception: ' . $e->getMessage()],
                'summary' => 'Co loi xay ra trong qua trinh xu ly file Excel.',
            ];

            game_bsc_voucher_excel_log_history($file_name, $file_url, get_current_user_id(), $report);

            game_bsc_redirect_voucher_excel_report($report, 'error');
        } finally {
            if (function_exists('wp_suspend_cache_addition') && $cache_suspend_state !== null) {
                wp_suspend_cache_addition($cache_suspend_state);
            }
        }

        // Keep uploaded file for audit/history so file_url in import history remains valid.

        if (function_exists('game_bsc_log_settings_change')) {
            game_bsc_log_settings_change(
                'game_bsc_voucher_excel_import',
                [],
                [
                    'requested_by' => (int) get_current_user_id(),
                    'mode' => $mode,
                    'file_ext' => $ext,
                    'total_rows' => (int) ($report['total_rows'] ?? 0),
                    'updated_rows' => (int) ($report['updated_rows'] ?? 0),
                    'error_rows' => (int) ($report['error_rows'] ?? 0),
                    'status' => ((int) ($report['error_rows'] ?? 0) > 0) ? 'error' : 'success',
                    'triggered_at' => game_now(),
                ],
                'update'
            );
        }

        $status = ((int) ($report['error_rows'] ?? 0) > 0) ? 'error' : 'success';
        game_bsc_redirect_voucher_excel_report($report, $status);
    }
}
add_action('admin_post_game_bsc_import_vouchers_excel', 'game_bsc_handle_import_vouchers_excel');
