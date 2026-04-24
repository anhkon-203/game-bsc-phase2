<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 8/21/2025
 * Time: 3:37 PM
 */
if (!defined('ABSPATH')) {
    exit;
}

function game_bsc_register_post_type() {
    // Post type câu hỏi
    $labels = array(
        'name' => __('Câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
        'singular_name' => __('Câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
        'add_new_item' => __('Thêm câu hỏi mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'edit_item' => __('Chỉnh sửa câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
        'new_item' => __('Câu hỏi mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'view_item' => __('Xem câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
        'search_items' => __('Tìm câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
        'not_found' => __('Không tìm thấy câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
    );

    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'game-bsc-main',
        'capability_type' => 'post',
        'supports' => array('title', 'revisions'),
        'has_archive' => false,
//        'menu_position' => 25,
        'rewrite' => array('slug' => 'game-question'),
    );

    register_post_type('game_question', $args);

    // Post type voucher
    $labels = array(
        'name' => __('Voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'singular_name' => __('Voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'add_new_item' => __('Thêm voucher mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'edit_item' => __('Chỉnh sửa voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'new_item' => __('Voucher mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'view_item' => __('Xem voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'search_items' => __('Tìm voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'not_found' => __('Không tìm thấy voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'menu_name' => __('Voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
    );

    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'game-bsc-main',
	    'capability_type' => 'post',
	    'supports' => array('title', 'revisions', 'thumbnail'),
	    'taxonomies' => array('game_voucher_category'),
        'has_archive' => false,
//        'menu_position' => 25,
        'rewrite' => array('slug' => 'game-vouchers'),
    );

    register_post_type('game_vouchers', $args);

    // Taxonomy danh mục voucher
    $voucher_category_labels = array(
        'name' => __('Danh mục voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'singular_name' => __('Danh mục voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'search_items' => __('Tìm danh mục voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'all_items' => __('Tất cả danh mục voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'parent_item' => __('Danh mục cha', WG_GAME_PLUGIN_TEXTDOMAIN),
        'parent_item_colon' => __('Danh mục cha:', WG_GAME_PLUGIN_TEXTDOMAIN),
        'edit_item' => __('Chỉnh sửa danh mục voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'update_item' => __('Cập nhật danh mục voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'add_new_item' => __('Thêm danh mục voucher mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'new_item_name' => __('Tên danh mục voucher mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'menu_name' => __('Danh mục voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
    );

    register_taxonomy('game_voucher_category', array('game_vouchers'), array(
        'hierarchical' => true,
        'labels' => $voucher_category_labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_quick_edit' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'voucher-category'),
        'public' => false,
    ));

    // Post type badge
    $labels = array(
        'name' => __('Huy hiệu', WG_GAME_PLUGIN_TEXTDOMAIN),
        'singular_name' => __('Huy hiệu', WG_GAME_PLUGIN_TEXTDOMAIN),
        'add_new_item' => __('Thêm huy hiệu mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'edit_item' => __('Chỉnh sửa huy hiệu', WG_GAME_PLUGIN_TEXTDOMAIN),
        'new_item' => __('Huy hiệu mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'view_item' => __('Xem huy hiệu', WG_GAME_PLUGIN_TEXTDOMAIN),
        'search_items' => __('Tìm huy hiệu', WG_GAME_PLUGIN_TEXTDOMAIN),
        'not_found' => __('Không tìm thấy huy hiệu', WG_GAME_PLUGIN_TEXTDOMAIN),
    );

    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'game-bsc-main',
        'capability_type' => 'post',
        'supports' => array('title', 'revisions'),
        'has_archive' => false,
//        'menu_position' => 25,
        'rewrite' => array('slug' => 'game-badges'),
    );

    register_post_type('game_badges', $args);
}

add_action('init', 'game_bsc_register_post_type'); // register CPT

/**
 * Ensure a default voucher category exists.
 */
function game_bsc_ensure_default_voucher_category() {
    if (!taxonomy_exists('game_voucher_category')) {
        return;
    }

    if (!term_exists('chua-phan-loai', 'game_voucher_category')) {
        wp_insert_term(
            __('Chưa phân loại', WG_GAME_PLUGIN_TEXTDOMAIN),
            'game_voucher_category',
            array('slug' => 'chua-phan-loai')
        );
    }
}
add_action('init', 'game_bsc_ensure_default_voucher_category', 20);

/**
 * Backfill danh mục mặc định cho các voucher cũ (chạy 1 lần).
 */
function game_bsc_backfill_voucher_categories_once() {
    if (!taxonomy_exists('game_voucher_category')) {
        return;
    }

    if (get_option('game_bsc_voucher_category_backfilled_v1', '0') === '1') {
        return;
    }

    $default_term = get_term_by('slug', 'chua-phan-loai', 'game_voucher_category');
    if (!$default_term || is_wp_error($default_term)) {
        return;
    }

    $voucher_ids = get_posts(array(
        'post_type' => 'game_vouchers',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ));

    foreach ($voucher_ids as $voucher_id) {
        $term_ids = wp_get_object_terms((int) $voucher_id, 'game_voucher_category', array('fields' => 'ids'));
        if (is_wp_error($term_ids) || !empty($term_ids)) {
            continue;
        }

        wp_set_object_terms((int) $voucher_id, array((int) $default_term->term_id), 'game_voucher_category', false);
    }

    update_option('game_bsc_voucher_category_backfilled_v1', '1', false);
}
add_action('init', 'game_bsc_backfill_voucher_categories_once', 30);

/**
 * Mỗi voucher luôn có ít nhất 1 danh mục.
 */
function game_bsc_assign_default_voucher_category($post_id, $post, $update) {
    if (!is_object($post) || $post->post_type !== 'game_vouchers') {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $term_ids = wp_get_object_terms($post_id, 'game_voucher_category', array('fields' => 'ids'));
    if (is_wp_error($term_ids) || !empty($term_ids)) {
        return;
    }

    $default_term = get_term_by('slug', 'chua-phan-loai', 'game_voucher_category');
    if ($default_term && !is_wp_error($default_term)) {
        wp_set_object_terms($post_id, array((int) $default_term->term_id), 'game_voucher_category', false);
    }
}
add_action('save_post_game_vouchers', 'game_bsc_assign_default_voucher_category', 20, 3);

/**
 * Chuẩn hóa giá trị voucher_type.
 */
function game_bsc_normalize_voucher_type($value) {
    return strtoupper(str_replace('-', '_', trim((string) $value)));
}

/**
 * Bộ lọc nhanh loại voucher ở trang danh sách game_vouchers.
 */
function game_bsc_render_voucher_type_admin_filter($post_type, $which) {
    if ($post_type !== 'game_vouchers' || $which !== 'top') {
        return;
    }

    $current = sanitize_key((string) ($_GET['game_bsc_voucher_type'] ?? 'all'));
    ?>
    <select name="game_bsc_voucher_type" id="game-bsc-voucher-type-filter">
        <option value="all" <?php selected($current, 'all'); ?>>Tất cả loại voucher</option>
        <option value="bsc" <?php selected($current, 'bsc'); ?>>Voucher tại BSC</option>
        <option value="third_party" <?php selected($current, 'third_party'); ?>>Voucher bên thứ 3</option>
    </select>
    <?php
}
add_action('restrict_manage_posts', 'game_bsc_render_voucher_type_admin_filter', 10, 2);

/**
 * Áp điều kiện truy vấn theo bộ lọc loại voucher ở list page.
 */
function game_bsc_apply_voucher_type_admin_filter($query) {
    if (!is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
        return;
    }

    global $pagenow;
    if ($pagenow !== 'edit.php') {
        return;
    }

    $post_type = sanitize_key((string) ($query->get('post_type') ?: ($_GET['post_type'] ?? '')));
    if ($post_type !== 'game_vouchers') {
        return;
    }

    $filter = sanitize_key((string) ($_GET['game_bsc_voucher_type'] ?? 'all'));
    if ($filter !== 'bsc' && $filter !== 'third_party') {
        return;
    }

    $meta_query = $query->get('meta_query');
    if (!is_array($meta_query)) {
        $meta_query = [];
    }

    if ($filter === 'third_party') {
        $meta_query[] = [
            'key' => 'voucher_type',
            'value' => ['THIRD_PARTY', 'THIRD-PARTY', 'THIRT_PARTY'],
            'compare' => 'IN',
        ];
    } else {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key' => 'voucher_type',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => 'voucher_type',
                'value' => '',
                'compare' => '=',
            ],
            [
                'key' => 'voucher_type',
                'value' => 'BSC',
                'compare' => '=',
            ],
        ];
    }

    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'game_bsc_apply_voucher_type_admin_filter');

/**
 * Chỉ hiển thị metabox "Danh mục voucher" khi voucher_type là THIRD_PARTY.
 */
function game_bsc_toggle_voucher_category_metabox_on_edit_screen() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'game_vouchers') {
        return;
    }
    ?>
    <script>
        (function() {
            function normalizeVoucherType(value) {
                return String(value || '').trim().toUpperCase().replace(/-/g, '_');
            }

            function getCurrentVoucherType() {
                var checked = document.querySelector('.acf-field[data-key="field_voucher_type"] input[type="radio"]:checked');
                if (checked) {
                    return normalizeVoucherType(checked.value);
                }

                var fallback = document.querySelector('input[name*="[voucher_type]"][type="radio"]:checked');
                return fallback ? normalizeVoucherType(fallback.value) : '';
            }

            var categoryBox = document.getElementById('game_voucher_categorydiv') || document.getElementById('tagsdiv-game_voucher_category');
            if (!categoryBox) {
                return;
            }

            function updateCategoryBoxVisibility() {
                var shouldShow = getCurrentVoucherType() === 'THIRD_PARTY';
                categoryBox.style.display = shouldShow ? '' : 'none';
            }

            document.addEventListener('change', function(event) {
                var target = event.target;
                if (!target || target.type !== 'radio') {
                    return;
                }

                if (target.closest('.acf-field[data-key="field_voucher_type"]') || (target.name && target.name.indexOf('[voucher_type]') !== -1)) {
                    updateCategoryBoxVisibility();
                }
            });

            if (window.acf && typeof window.acf.addAction === 'function') {
                window.acf.addAction('ready', updateCategoryBoxVisibility);
                window.acf.addAction('append', updateCategoryBoxVisibility);
            }

            updateCategoryBoxVisibility();
        })();
    </script>
    <?php
}
add_action('admin_footer-post.php', 'game_bsc_toggle_voucher_category_metabox_on_edit_screen');
add_action('admin_footer-post-new.php', 'game_bsc_toggle_voucher_category_metabox_on_edit_screen');



// new phan quyen
add_action('admin_init', function() {
	// Chỉ hạn chế nếu có quyền admin_game nhưng KHÔNG phải administrator
	if (current_user_can('admin_game') && !current_user_can('administrator') ) {
		$post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
		$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
		
		// not allow to access page
		$list_pages = [
			'lich-thi-truong',
			'trach-nhiem-voi-cong-dong',
			'thong-tin-co-phieu',
			'danh-muc-khuyen-nghi',
			'chuong-trinh-khuyen-mai',
			'cai-dat-bieu-phi-giao-dich',
			'cai-dat-bao-cao-phan-tich',
			'cai-dat-quan-he-co-dong',
			'wpcf7',
			'cai-dat-so-tay-giao-dich',
			'cai-dat-tuyen-dung',
			'cai-dat-api',
		];
		if ($post_type == 'post' || in_array($page, $list_pages)) {
			wp_safe_remote_post(admin_url('index.php'));
			wp_die('Bạn không có quyền truy cập');
		}
	}
});



add_action('admin_menu', function() {
	// Chỉ hạn chế nếu có quyền admin_game nhưng KHÔNG phải administrator
	if (current_user_can('admin_game') && !current_user_can('administrator') ) {
		
		// Ẩn menu Bài viết mặc định
		remove_menu_page('edit.php');
		remove_menu_page('edit-comments.php');
		remove_menu_page('admin.php');
		
		// Ẩn các menu CPT
		$list_cpt_menu = [
			'edit.php?post_type=bieu-phi-giao-dich',
			'edit.php?post_type=bao-cao-phan-tich',
			'edit.php?post_type=chuyen-gia',
			'edit.php?post_type=kien-thuc-dau-tu',
			'edit.php?post_type=quan-he-co-dong',
			'edit.php?post_type=so-tay-giao-dich',
			'edit.php?post_type=tuyen-dung',
		];
		
		foreach ($list_cpt_menu as $slug) {
			remove_menu_page($slug);
		}
		
		// Ẩn Contact Form 7
		remove_menu_page('wpcf7');
	}
});




add_action( 'admin_head', function() {
	if ( current_user_can('admin_game') && !current_user_can('administrator') ) {
		echo "
        <style>
           #toplevel_page_lich-thi-truong, #toplevel_page_thong-tin-co-phieu,
           #toplevel_page_danh-muc-khuyen-nghi, #toplevel_page_chuong-trinh-khuyen-mai,
           #menu-appearance, #menu-tools, #toplevel_page_trach-nhiem-voi-cong-dong{
           display: none;
           }

        </style>
        ";
	}
	if ( !current_user_can('admin_game') ) {
		echo "
		<style>
		   #toplevel_page_game-bsc-main{
		   display: none;
		   }

		</style>
		";
	}
});




