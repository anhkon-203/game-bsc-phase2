<?php


// Trang import (form upload)
function game_bsc_render_import_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'game_question_upload_history';
    $paged = max(1, intval($_GET['paged'] ?? 1));
    $per_page = WG_GAME_ITEMS_PER_PAGE;
    $offset = ($paged - 1) * $per_page;

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $history = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}");
    ?>
    <div class="wrap">
        <h1><?php _e('Import Câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h1>
        <?php if (!empty($_GET['import_result'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html($_GET['import_result']); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($_GET['import_error'])): ?>
            <div class="notice notice-error"><p><?php echo esc_html($_GET['import_error']); ?></p></div>
        <?php endif; ?>


        <p><?php _e('Tải lên file CSV (.csv) theo mẫu sau:', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
        <p><a href="<?php echo esc_url(GAME_BSC_PLUGIN_URL . 'assets/samples/sample-questions.csv'); ?>" class="button" download><?php _e('Tải mẫu CSV', WG_GAME_PLUGIN_TEXTDOMAIN); ?></a></p>
        <p><?php _e('Chú ý: File CSV phải có định dạng đúng theo mẫu, bao gồm các cột: STT, Câu hỏi, Đáp án A, Đáp án B, Đáp án C, Đáp án D, Đáp án đúng.', WG_GAME_PLUGIN_TEXTDOMAIN); ?></p>
        <h3><?php _e('Tải lên file CSV', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h3>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('game_bsc_import_questions'); ?>
            <input type="hidden" name="action" value="game_bsc_import_questions">
            <input type="file" name="questions_file" accept=".csv" required>
            <?php submit_button(__('Import', WG_GAME_PLUGIN_TEXTDOMAIN)); ?>
        </form>

        <h3><?php _e('Lịch sử upload file', WG_GAME_PLUGIN_TEXTDOMAIN); ?></h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Thời gian', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
                    <th><?php _e('Tên file', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
                    <th><?php _e('Người upload', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
                    <th><?php _e('Kết quả', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
                    <th><?php _e('Link file', WG_GAME_PLUGIN_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($history): foreach ($history as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->uploaded_at); ?></td>
                        <td><?php echo esc_html($row->file_name); ?></td>
                        <td><?php echo esc_html(get_userdata($row->file_author)->display_name ?? $row->file_author); ?></td>
                        <td><?php echo esc_html($row->upload_message); ?></td>
                        <td><a href="<?php echo esc_url($row->file_url); ?>" target="_blank"><?php _e('Tải về', WG_GAME_PLUGIN_TEXTDOMAIN); ?></a></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5"><?php _e('Chưa có lịch sử upload.', WG_GAME_PLUGIN_TEXTDOMAIN); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
            $total_pages = ceil($total / $per_page);
            echo paginate_admin($total_pages, $paged, $total_pages - 1, $paged - 1, $paged + 1);
        ?>
    </div>
    <script>
        let currentUrl = new URL(window.location.href);
        jQuery('#pageNumbers button, #prevPage, #nextPage').on('click', function (e) {
            e.preventDefault();
            if (jQuery(this).prop('disabled')) return;
            let page = jQuery(this).data('pg');
            if (page) {
                currentUrl.searchParams.set('paged', page);
                window.location.href = currentUrl.toString();
            }
        });
    </script>
    <?php
}
// Handle import form submission
add_action('admin_post_game_bsc_import_questions', 'game_bsc_handle_import_questions');

function game_bsc_handle_import_questions() {
    $admin_url = admin_url('admin.php?page=game-bsc-import-questions');
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        wp_die(__('Bạn không có quyền thực hiện hành động này.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    check_admin_referer('game_bsc_import_questions');

    if (empty($_FILES['questions_file']) || $_FILES['questions_file']['error'] !== UPLOAD_ERR_OK) {
        return game_bsc_redirect_error(__('Lỗi khi tải lên file.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
    // Giới hạn dung lượng upload file
    if($_FILES['questions_file']['size'] > WG_GAME_MAX_UPLOAD_FILE_SIZE) {
        return game_bsc_redirect_error(sprintf(__('File quá lớn. Vui lòng tải lên file nhỏ hơn %d MB.', WG_GAME_PLUGIN_TEXTDOMAIN), WG_GAME_MAX_UPLOAD_FILE_SIZE / (1024 * 1024)), $admin_url);
    }

    // Upload file tạm
    $overrides = ['test_form' => false, 'mimes' => [
        'csv' => 'text/csv',
    ]];
    $uploaded = wp_handle_upload($_FILES['questions_file'], $overrides);

    if (isset($uploaded['error'])) {
        return game_bsc_redirect_error(sprintf(__('Lỗi upload: %s', WG_GAME_PLUGIN_TEXTDOMAIN), $uploaded['error']), $admin_url);
    }

    $file_path = $uploaded['file'];
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $file_name = basename($file_path);
    $file_url = $uploaded['url'];
    $file_author = get_current_user_id();
    try {
        if($ext === 'csv') {
            $rows = game_bsc_read_csv($file_path);
            if (empty($rows)) {
                return game_bsc_redirect_error(__('File trống hoặc đọc không được dữ liệu.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
            }
            // Kiểm tra cấu trúc file đã đúng hay chưa
            $header = array_map('trim', $rows[0]);
            $expected_header = ['STT', 'Câu hỏi', 'Đáp án A', 'Đáp án B', 'Đáp án C', 'Đáp án D', 'Đáp án đúng'];
            $header_prefix = array_slice($header, 0, count($expected_header));
            if ($header_prefix !== $expected_header) {
                return game_bsc_redirect_error(__('Cấu trúc file không đúng. Vui lòng sử dụng mẫu csv.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
            }
            // Biến đếm kết quả
            $created = 0; $skipped = 0; $errors = 0;
            // Tạo mảng lưu lỗi chi tiết (nếu cần)
            $error_msgs = [];
            // Duyệt mảng ghi dữ liệu
            foreach ($rows as $index => $row) {
                if ($index === 0) continue; // bỏ qua header
                // Bỏ qua dòng trống (nếu có)
                if (count(array_filter($row)) === 0) continue;

                // Lấy dữ liệu từng cột
                $question_text = trim($row[1] ?? '');
                $answer_a = trim($row[2] ?? '');
                $answer_b = trim($row[3] ?? '');
                $answer_c = trim($row[4] ?? '');
                $answer_d = trim($row[5] ?? '');
                $correct_answer = strtoupper(trim($row[6] ?? ''));

                // Kiểm tra dữ liệu bắt buộc
                if (empty($question_text) || empty($answer_a) || empty($answer_b) || !in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
                    $errors++;
                    $error_msgs[] = sprintf(__('Dòng %d: Thiếu dữ liệu hoặc đáp án đúng không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN), $index + 1);
                    continue;
                }
                // Kiểm tra trùng lặp (theo nội dung câu hỏi)
                $existing = get_posts([
                    'post_type' => 'game_question',
                    'title' => $question_text,
                    'post_status' => 'any',
                    'numberposts' => 1,
                ]);
                if (!empty($existing)) {
                    $skipped++;
                    continue;
                }
                
                // Tạo post mới cho câu hỏi
                $post_id = wp_insert_post([
                    'post_type' => 'game_question',
                    'post_title' => wp_strip_all_tags($question_text),
                    'post_status' => 'publish',
                ]);

                if (is_wp_error($post_id)) {
                    $errors++;
                    $error_msgs[] = "Dòng ".($index + 1).": ".$post_id->get_error_message();
                    continue;
                }

                // Lưu metadata cho đáp án
                if (function_exists('update_field')) {
                    update_field('answer_a', $answer_a, $post_id);
                    update_field('answer_b', $answer_b, $post_id);
                    update_field('answer_c', $answer_c, $post_id);
                    update_field('answer_d', $answer_d, $post_id);
                    update_field('correct_answer', $correct_answer, $post_id);
                } else {
                    // fallback khi ACF chưa sẵn: lưu plain meta
                    update_post_meta($post_id, 'answer_a', $answer_a);
                    update_post_meta($post_id, 'answer_b', $answer_b);
                    update_post_meta($post_id, 'answer_c', $answer_c);
                    update_post_meta($post_id, 'answer_d', $answer_d);
                    update_post_meta($post_id, 'correct_answer', $correct_answer);
                }
                $created++;
            }

            $summary = sprintf(
                __('Import xong: %d tạo mới, %d bỏ qua, %d lỗi.', WG_GAME_PLUGIN_TEXTDOMAIN),
                $created, $skipped, $errors
            );
            if ($errors && !empty($error_msgs)) {
                $summary .= ' ' . __('Chi tiết lỗi:', WG_GAME_PLUGIN_TEXTDOMAIN) . ' ' . implode(' | ', $error_msgs);
            }
            // Lưu lịch sử upload
            $upload_history = game_bsc_upload_history($file_name, $file_url, $file_path, $file_author, $summary);
            return game_bsc_redirect_result($summary, $admin_url);
        } else {
            return game_bsc_redirect_error(__('Định dạng file không được hỗ trợ. Vui lòng tải lên file .csv', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
        }
    } catch (Exception $e) {
        return game_bsc_redirect_error(__('Lỗi khi đọc file CSV: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
}

// Đọc file CSV và trả về mảng các dòng
/**
 * Đọc CSV bất kể encoding (CP1258/ANSI, UTF-8, UTF-16LE/BE...) và trả về mảng UTF-8 chuẩn.
 */
function game_bsc_read_csv($path, $delimiter = ',', $enclosure = '"') {
    if ( !is_readable($path) ) return [];

    $bin = file_get_contents($path);
    if ($bin === false) return [];

    // 1) Phát hiện BOM nhanh
    $enc = null;
    if (substr($bin, 0, 3) === "\xEF\xBB\xBF") {
        $enc = 'UTF-8';
        $bin = substr($bin, 3); // bỏ BOM
    } elseif (substr($bin, 0, 2) === "\xFF\xFE") {
        $enc = 'UTF-16LE';
        $bin = substr($bin, 2);
    } elseif (substr($bin, 0, 2) === "\xFE\xFF") {
        $enc = 'UTF-16BE';
        $bin = substr($bin, 2);
    }

    // 2) Nếu chưa biết encoding, thử đoán
    if ($enc === null) {
        // Lấy mẫu ~64KB để detect
        $sample = substr($bin, 0, 65536);
        $enc = mb_detect_encoding(
            $sample,
            // Thứ tự ưu tiên phổ biến cho file CSV từ Windows VN
            ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'CP1258', 'Windows-1252', 'ISO-8859-1', 'SJIS-win', 'ASCII'],
            true
        );
        if ($enc === false) {
            // Nếu vẫn không đoán được, giả định CP1258 (thường gặp ở VN)
            $enc = 'CP1258';
        }
    }

    // 3) Chuyển toàn bộ nội dung sang UTF-8 (bỏ ký tự lỗi)
    if (strtoupper($enc) !== 'UTF-8') {
        // iconv hoặc mb_convert_encoding đều được; dùng iconv cho tốc độ
        $text = @iconv($enc, 'UTF-8//IGNORE', $bin);
        if ($text === false) {
            $text = mb_convert_encoding($bin, 'UTF-8', $enc);
        }
    } else {
        $text = $bin;
    }

    // 4) Chuẩn hoá xuống dòng và parse từng dòng bằng str_getcsv
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);

    $rows = [];
    foreach (explode("\n", $text) as $line) {
        if ($line === '' || ctype_space($line)) continue;
        $row = str_getcsv($line, $delimiter, $enclosure);

        // Loại bỏ BOM còn sót ở ô đầu nếu có (trường hợp file UTF-8 BOM không bị cắt ở bước 1)
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/u', '', $row[0]);
        }

        // Trim khoảng trắng không cần thiết
        foreach ($row as &$cell) {
            if (is_string($cell)) {
                $cell = trim($cell);
            }
        }
        unset($cell);

        // Bỏ qua dòng header rỗng thực sự
        if (count(array_filter($row, fn($v) => $v !== '' && $v !== null)) === 0) {
            continue;
        }

        $rows[] = $row;
    }
    
    return $rows;
}


// Lưu lịch sử upload file
function game_bsc_upload_history($file_name, $file_url, $file_path, $file_author, $message) {
    global $wpdb, $admin_url;
    $table = $wpdb->prefix . 'game_question_upload_history';
    // Validate required fields
    if (empty($file_name) || empty($file_url) || empty($file_author)) {
        if (file_exists($file_path)) {
            if ( function_exists( 'wp_delete_file' ) ) {
                wp_delete_file( $file_path );
            } else {
                @unlink( $file_path );
            }
        }
        return game_bsc_redirect_error(__('Đã có lỗi xảy ra khi lưu file. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
    $result = $wpdb->insert($table, [
        'file_name' => $file_name,
        'file_url' => $file_url,
        'file_author' => $file_author,
        'upload_message' => $message,
        'uploaded_at' => current_time('mysql'),
    ]);
    if ($result === false) {
        if (file_exists($file_path)) {
            if ( function_exists( 'wp_delete_file' ) ) {
                wp_delete_file( $file_path );
            } else {
                @unlink( $file_path );
            }
        }
        return game_bsc_redirect_error(__('Đã có lỗi xảy ra khi lưu file. Vui lòng thử lại.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
    return true;
}