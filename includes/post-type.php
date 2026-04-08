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
        'name' => __('Vouchers', WG_GAME_PLUGIN_TEXTDOMAIN),
        'singular_name' => __('Vouchers', WG_GAME_PLUGIN_TEXTDOMAIN),
        'add_new_item' => __('Thêm voucher mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'edit_item' => __('Chỉnh sửa voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'new_item' => __('Voucher mới', WG_GAME_PLUGIN_TEXTDOMAIN),
        'view_item' => __('Xem voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'search_items' => __('Tìm voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
        'not_found' => __('Không tìm thấy voucher', WG_GAME_PLUGIN_TEXTDOMAIN),
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




