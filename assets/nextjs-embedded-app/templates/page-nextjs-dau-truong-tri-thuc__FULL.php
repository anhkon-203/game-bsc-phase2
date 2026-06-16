<?php
/*
Template Name:  Dau truong Tri Thuc FULL
*/
if (!defined('ABSPATH')) exit;

$plugin_dir = plugin_dir_path(dirname(__FILE__));
$plugin_url = plugin_dir_url(dirname(__FILE__));
$app_path = $plugin_dir . 'app/dau-truong-tri-thuc__FULL/';
$app_url = $plugin_url . 'app/dau-truong-tri-thuc__FULL/';
$old_app_url = '/wp-content/plugins/nextjs-embedded-app/app/dau-truong-tri-thuc__FULL/';

$page_slug = trim(parse_url(get_permalink(), PHP_URL_PATH), '/');
$request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$should_render_app = $page_slug && strpos($request_path, $page_slug) === 0;

$output = '';
$inline_scripts = '';

$normalize_asset_url = function ($url) use ($app_url, $old_app_url) {
    if (!$url) {
        return $url;
    }

    if (strpos($url, $old_app_url) === 0) {
        return $app_url . substr($url, strlen($old_app_url));
    }

    if (strpos($url, '/_next/') === 0 || strpos($url, '/assets/') === 0) {
        return $app_url . ltrim($url, '/');
    }

    if (preg_match('#/(_next/.*|assets/.*)$#', $url, $match)) {
        return $app_url . ltrim($match[1], '/');
    }

    return $url;
};

if ($should_render_app && file_exists($app_path . 'index.html')) {
    $html = file_get_contents($app_path . 'index.html');
    $html = str_replace(
        array($old_app_url, '="/_next', "='/_next", '="/assets', "='/assets", '="images/', "='images/"),
        array($app_url, '="' . $app_url . '_next', "='" . $app_url . '_next', '="' . $app_url . 'assets', "='" . $app_url . 'assets', '="' . $app_url . 'images/', "='" . $app_url . 'images/'),
        $html
    );

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $links = $xpath->query("//link[@rel='stylesheet']");
    $first_style_handle = '';

    foreach ($links as $link) {
        $href = $normalize_asset_url($link->getAttribute('href'));
        $handle = 'nextapp-css-' . md5($href);
        wp_enqueue_style($handle, $href, [], null);

        if (!$first_style_handle) {
            $first_style_handle = $handle;
        }
    }

    if ($first_style_handle) {
        wp_add_inline_style(
            $first_style_handle,
            'html,body{height:auto;overflow:visible;}body{background:inherit;}#nextjs-embedded-app{height:100vh;min-height:640px;overflow:hidden;}#nextjs-embedded-app .cstdio-main-layout.cstdio-page{height:100%;}'
        );
    }

    $root_divs = $xpath->query(
        "//div[contains(concat(' ', normalize-space(@class), ' '), ' cstdio-page ')] | //div[@id='__next'] | //div[@id='root']"
    );

    if ($root_divs->length > 0) {
        $output = $dom->saveHTML($root_divs->item(0));
    } else {
        $body = $dom->getElementsByTagName('body')->item(0);
        $output = $body ? $dom->saveHTML($body) : '<p>App root not found</p>';
    }

    $scripts = $xpath->query("//script[@src]");
    foreach ($scripts as $script) {
        $src = $normalize_asset_url($script->getAttribute('src'));
        $handle = 'nextapp-js-' . md5($src);

        wp_register_script($handle, $src, [], null, true);

        if ($script->hasAttribute('nomodule')) {
            wp_script_add_data($handle, 'nomodule', true);
        }

        wp_enqueue_script($handle);
    }

    $inline_script_nodes = $xpath->query("//script[not(@src)]");
    foreach ($inline_script_nodes as $script) {
        $inline_scripts .= $dom->saveHTML($script);
    }
}

get_header(); ?>
<div id="nextjs-embedded-app">
    <?php echo $output; ?>
</div>
<?php echo $inline_scripts; ?>
<?php get_footer();
