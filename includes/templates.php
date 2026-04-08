<?php
if (!defined('ABSPATH')) exit;
// Đăng ký template
add_filter('theme_page_templates', function($page_templates) {
    $page_templates['template-home.php'] = 'Game - Homepage';
    $page_templates['template-test.php'] = 'Game - test';
    return $page_templates;
});

// Load template file
add_filter('template_include', function($template) {
    if (is_page()) {
        $slug = get_page_template_slug(get_queried_object_id());
        if ($slug === 'template-home.php') {
            $plugin_tpl = GAME_BSC_PLUGIN_DIR . 'templates/template-home.php';
            if (file_exists($plugin_tpl)) {
                return $plugin_tpl;
            }
        }
        if ($slug === 'template-test.php') {
            $plugin_tpl = GAME_BSC_PLUGIN_DIR . 'templates/template-test.php';
            if (file_exists($plugin_tpl)) {
                return $plugin_tpl;
            }
        }
    }
    return $template;
});