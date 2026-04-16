<?php
if (!defined('ABSPATH')) {
    exit;
}
global $admin_url;
/**
 * Đăng ký menu admin cho Game BSC
 */
function game_bsc_admin_menu() {
    add_menu_page(
        __('Game BSC', WG_GAME_PLUGIN_TEXTDOMAIN),
        __('Game BSC', WG_GAME_PLUGIN_TEXTDOMAIN),
        'admin_game',
        'game-bsc-main',
        'game_bsc_dashboard_page',
        'dashicons-chart-line',
        25
    );

    add_submenu_page(
        'game-bsc-main',// parent slug
        __('Import Câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
        __('Import Câu hỏi', WG_GAME_PLUGIN_TEXTDOMAIN),
        'admin_game',
        'game-bsc-import-questions',
        'game_bsc_render_import_page'
    );

    add_submenu_page(
        'game-bsc-main',
        __('Cài đặt', WG_GAME_PLUGIN_TEXTDOMAIN),
        __('Cài đặt', WG_GAME_PLUGIN_TEXTDOMAIN),
        'admin_game',
        'game-bsc-settings',
        'game_bsc_settings_page'
    );

    add_submenu_page(
        'game-bsc-main',
        __('Quản lý hiện vật', WG_GAME_PLUGIN_TEXTDOMAIN),
        __('Quản lý hiện vật', WG_GAME_PLUGIN_TEXTDOMAIN),
        'admin_game',
        'game-bsc-manage-artifacts',
        'game_bsc_manage_artifacts_page'
    );
	
	
	add_submenu_page(
		'game-bsc-main',
		__('Dashboard', WG_GAME_PLUGIN_TEXTDOMAIN),
		__('Dashboard', WG_GAME_PLUGIN_TEXTDOMAIN),
		'admin_game',
		'dashboard-layout',
		'dashboard_config'
	);
	
	add_submenu_page(
		'game-bsc-main',
		__('Test API v2', WG_GAME_PLUGIN_TEXTDOMAIN),
		__('Test API v2', WG_GAME_PLUGIN_TEXTDOMAIN),
		'admin_game',
		'game-bsc-test-api',
		'game_bsc_test_api_page'
	);
	
}


function dashboard_config(){
	include_once GAME_BSC_PLUGIN_DIR . 'admin_dashboard/setting.php';
}
add_action('admin_menu', 'game_bsc_admin_menu'); // admin pages

/**
 * Enqueue WordPress Media Library scripts for settings page
 */
function game_bsc_enqueue_admin_media_scripts($hook) {
    if ($hook === 'game-bsc_page_game-bsc-settings') {
        wp_enqueue_media();
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'game_vouchers' || !in_array($screen->base, ['post', 'post-new'], true)) {
        return;
    }

    // Disable legacy test/manual picker flow on voucher editor UI.
    return;
}
add_action('admin_enqueue_scripts', 'game_bsc_enqueue_admin_media_scripts');

// Phân trang trong admin
function paginate_admin($totalposts, $p, $lpm1, $prev, $next) {
    $adjacents = 2;
    $pagination = '';

    if ($totalposts > 1) {
        $pagination = '<div class="tablenav" id="pageNumbers">';
        $pagination .= '<div class="tablenav-pages">';

        if ($p > 1) {
            $pagination .= '<button id="prevPage" class="button" data-pg="' . $prev . '">Trước</button>';
        } else {
            $pagination .= '<button id="prevPage" class="button" disabled>Trước</button>';
        }

        if ($totalposts < 7 + ($adjacents * 2)) {
            for ($counter = 1; $counter <= $totalposts; $counter++) {
                if ($counter == $p) {
                    $pagination .= '<button class="button disabled">' . $counter . '</button>';
                } else {
                    $pagination .= '<button class="button" data-pg="' . $counter . '">' . $counter . '</button>';
                }
            }
        } else {
            if ($p < 1 + ($adjacents * 2)) {
                for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++) {
                    if ($counter == $p) {
                        $pagination .= '<button class="button disabled">' . $counter . '</button>';
                    } else {
                        $pagination .= '<button class="button" data-pg="' . $counter . '">' . $counter . '</button>';
                    }
                }
                $pagination .= '<span class="button disabled">...</span>';
                $pagination .= '<button class="button" data-pg="' . $totalposts . '">' . $totalposts . '</button>';
            } elseif ($p > ($totalposts - ($adjacents * 2))) {
                $pagination .= '<button class="button" data-pg="1">1</button>';
                $pagination .= '<span class="button disabled">...</span>';

                for ($counter = $totalposts - (3 + ($adjacents * 2)); $counter <= $totalposts; $counter++) {
                    if ($counter == $p) {
                        $pagination .= '<button class="button disabled">' . $counter . '</button>';
                    } else {
                        $pagination .= '<button class="button" data-pg="' . $counter . '">' . $counter . '</button>';
                    }
                }
            } else {
                $pagination .= '<button class="button" data-pg="1">1</button>';
                $pagination .= '<span class="button disabled">...</span>';

                for ($counter = $p - $adjacents; $counter <= $p + $adjacents; $counter++) {
                    if ($counter == $p) {
                        $pagination .= '<button class="button disabled">' . $counter . '</button>';
                    } else {
                        $pagination .= '<button class="button" data-pg="' . $counter . '">' . $counter . '</button>';
                    }
                }

                $pagination .= '<span class="button disabled">...</span>';
                $pagination .= '<button class="button" data-pg="' . $totalposts . '">' . $totalposts . '</button>';
            }
        }

        if ($p < $totalposts) {
            $pagination .= '<button id="nextPage" class="button" data-pg="' . $next . '">Sau</button>';
        } else {
            $pagination .= '<button id="nextPage" class="button" disabled>Sau</button>';
        }

        $pagination .= '</div></div>';
    }
    return $pagination;
}


function game_bsc_redirect_result($msg, $admin_url) {
    wp_safe_redirect(add_query_arg(['import_result' => rawurlencode($msg)], $admin_url));
    exit;
}
function game_bsc_redirect_error($msg, $admin_url) {
    wp_safe_redirect(add_query_arg(['import_error' => rawurlencode($msg)], $admin_url));
    exit;
}

// Hàm xử lý import câu hỏi từ file Excel
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/manage-question-excel.php';
// Hàm xử lý export/import points_cost voucher từ file Excel
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/manage-voucher-excel.php';
// Cài đặt chung
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/settings.php';
// Quản lý hiện vật
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/manage-artifacts.php';
require_once GAME_BSC_PLUGIN_DIR . 'includes/helpers/artifact-detail.php';

// Test API Page
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/test-api.php';
