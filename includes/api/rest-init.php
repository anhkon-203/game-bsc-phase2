<?php
if (!defined('ABSPATH')) exit;

/**
 * REST API khởi tạo in ra html của game
 * Endpoint: /wp-json/game-bsc/init
 */

add_action('rest_api_init', function () {
	// GET: Lấy danh sách tất cả thể lệ
	register_rest_route(NS, '/init', array(
		'methods' => 'GET',
		'callback' => 'game_bsc_init',
		'permission_callback' => '__return_true',
	));
});


function game_bsc_init(WP_REST_Request $request) {
    $html_path = GAME_BSC_PLUGIN_DIR . 'assets/front-end/index.html';

    if ( ! file_exists( $html_path ) ) {
        return new WP_Error( 'not_found', 'Index file not found', array( 'status' => 404 ) );
    }

    // Đọc nội dung file
    $index = file_get_contents( $html_path );

    // Tạo nonce
    $nonce = wp_create_nonce( 'wp_game_rest' );
    $nonce_script = "<script>window.B5X7zJe2wSqY = " . wp_json_encode( array( 'nonce' => $nonce ) ) . ";</script>";

    // Chèn nonce trước </head>, nếu không tồn tại thì trước </body>, nếu vẫn không thì ở đầu file
    if ( false !== stripos( $index, '</head>' ) ) {
        $index = str_ireplace( '</head>', $nonce_script . '</head>', $index );
    } elseif ( false !== stripos( $index, '</body>' ) ) {
        $index = str_ireplace( '</body>', $nonce_script . '</body>', $index );
    } else {
        $index = $nonce_script . $index;
    }
    
    header( 'Content-Type: text/html; charset=utf-8' );
    echo $index;
}


?>