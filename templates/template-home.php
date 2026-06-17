<?php
    // Handle SSO callback nếu có
    bsc_game_handle_sso_callback();
    save_user_daily_login_mission();
    save_user_badges();

$templates_dir = GAME_BSC_PLUGIN_DIR . 'nextjs-embedded-app/templates/';

if ( wp_is_mobile() ) {
    $template_file = $templates_dir . 'page-nextjs-duong-dua-chung-si__FULL.php';
} else {
    $template_file = $templates_dir . 'page-nextjs-duong-dua-chung-si.php';
}

if ( file_exists( $template_file ) ) {
    include $template_file;
} else {
    echo '<p style="color:white;text-align:center;">Không tìm thấy app.</p>';
}

die();
