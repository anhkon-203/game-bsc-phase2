<?php
/*
Template Name: Dau truong Tri Thuc
*/
if (!defined('ABSPATH')) exit;

$app_url = plugin_dir_url(dirname(__FILE__)) . 'app/dau-truong-tri-thuc/';

wp_enqueue_style('dau-truong-css', $app_url . 'assets/mount.css', [], null);

wp_enqueue_script('dau-truong-js', $app_url . 'assets/app.js', [], null, true);
wp_script_add_data('dau-truong-js', 'type', 'module');
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle !== 'dau-truong-js') {
        return $tag;
    }

    return '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>';
}, 10, 3);

wp_add_inline_script(
    'dau-truong-js',
    'window.NEXTJS_EMBEDDED_APP = ' . wp_json_encode([
        'assetUrl' => untrailingslashit($app_url),
    ]) . ';',
    'before'
);

get_header();
?>
<div id="nextjs-embedded-app"></div>
<?php get_footer(); ?>
