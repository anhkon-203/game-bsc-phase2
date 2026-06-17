<?php
/*
Plugin Name: Nextjs embedded Multi App
Description: Cho phép nhúng nhiều ứng dụng Next.js khác nhau làm template page trong WordPress.
Version: 1.0.0
Author: Vu ba Cong
License: GPL2
*/

if (!defined('ABSPATH'))
    exit;

// Quét tất cả file PHP trong thư mục templates/
add_filter('theme_page_templates', function ($templates) {
    $files = glob(plugin_dir_path(__FILE__) . 'templates/*.php');
    foreach ($files as $file) {
        $basename = basename($file);
        if (wp_is_mobile()) {
            $basename = str_replace('.php', '__FULL.php', $basename);
        } 
        // Đọc tên template trong comment header
        $contents = file_get_contents($file);
        if (preg_match('/Template Name:\s*(.+)/i', $contents, $match)) {
            $templates[$basename] = trim($match[1]);
        }
    }
    return $templates;
});

// Hook để include template tương ứng
add_filter('template_include', function ($template) {
    if (is_page()) {
        $slug = get_page_template_slug();
         if (wp_is_mobile()) {
            $slug = str_replace('.php', '__FULL.php', $slug);
        } 
        if ($slug) {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/' . $slug;
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
    }
    return $template;
});
