<?php
/*
Template Name: Dau truong Tri Thuc
*/
if (!defined('ABSPATH'))
    exit;

// Bắt buffer để rewrite path cũ → mới trong toàn bộ output
ob_start();

$app_url = plugin_dir_url(dirname(__FILE__)) . 'app/duong-dua-chung-si/';
// Path plugin cũ (trước khi gộp vào game-bsc) → path mới
$old_plugin_url = '/wp-content/plugins/nextjs-embedded-app/';
$new_plugin_url = '/wp-content/plugins/game-bsc/nextjs-embedded-app/';

// Version busting — tránh cache file cũ sau mỗi lần build
$ver = filemtime(dirname(__FILE__) . '/../app/duong-dua-chung-si/assets/app.js');

// CSS
// wp_enqueue_style('dau-truong-css', $app_url . 'assets/mount.css', [], $ver);
wp_enqueue_style('dau-truong-css', $app_url . 'assets/mount.css', ['bsc-style'], $ver);
wp_add_inline_style(
    'dau-truong-css',
    'html,body{height:auto;overflow:visible;font-family:Lexend,Inter,Roboto,system-ui,-apple-system,Segoe UI,Noto Sans,sans-serif}body{background:inherit;}#nextjs-embedded-app{height:100vh;min-height:640px;overflow:hidden;font-family:Lexend,Inter,Roboto,system-ui,-apple-system,Segoe UI,Noto Sans,sans-serif}#nextjs-embedded-app .cstdio-main-layout.cstdio-page{height:100%;} a:where(:not(.wp-element-button)) { text-decoration: none!important; }'
);


// JS entry
wp_enqueue_script('dau-truong-js', $app_url . 'assets/app.js', [], null, true);
wp_script_add_data('dau-truong-js', 'type', 'module');
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if ($handle !== 'dau-truong-js') {
        return $tag;
    }
    return '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>';
}, 10, 3);

get_header();
?>
<script>
    window.NEXTJS_EMBEDDED_APP = <?php echo wp_json_encode([
        'assetUrl' => untrailingslashit($app_url),
    ]); ?>;

    // // Desktop postMessage handler — bắt SSO login + logout từ app
    // window.addEventListener('message', function (event) {
    //     var d = event.data;
    //     if (!d) return;
    //     // SSO login
    //     if (d.cstd_callapi_authen) {
    //         window.location.href = d.cstd_callapi_authen;
    //     }
    //     // SSO logout
    //     if (d.postBackLogout) {
    //         const win = window.open(d.postBackLogout, "sso", "width=450,height=600");
    //         var logoutHandled = false;

    //         function handleAfterLogout() {
    //             if (logoutHandled) return;
    //             logoutHandled = true;
    //             clearInterval(timer);
    //             clearTimeout(closeTimeout);

    //             window.postMessage(
    //                 { type: "logout_done" },
    //                 "<?php // echo (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>"
    //             );
    //         }

    //         // Nếu popup bị chặn
    //         if (!win) {
    //             console.warn("Popup blocked → assume logout done");
    //             handleAfterLogout();
    //         } else {
    //             var timer = setInterval(function () {
    //                 if (win.closed) {
    //                     handleAfterLogout();
    //                 }
    //             }, 500);

    //             var closeTimeout = setTimeout(function () {
    //                 try {
    //                     if (!win.closed) {
    //                         win.close();
    //                     }
    //                 } catch (e) {
    //                     console.warn("Cannot close popup:", e);
    //                 }
    //                 handleAfterLogout();
    //             }, 3000);
    //         }
    //     }
    // });
</script>

<div id="nextjs-embedded-app"></div>
<script>
(function () {
    var _originalOpen = window.open;
    var _logoutPopup = null;

    // Intercept window.open(_blank) — chỉ logout dùng pattern này.
    // Mở thành popup nhỏ cố định bên phải thay vì tab mới.
    window.open = function (url, target, features) {
        if (target === '_blank' && !features) {
            var w = 420;
            var h = window.screen.availHeight || window.screen.height;
            var left = (window.screen.availWidth || window.screen.width) - w;
            var top = 0;
            _logoutPopup = _originalOpen.call(
                window, url, 'game_logout_popup',
                'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
                ',toolbar=no,menubar=no,location=no,scrollbars=yes,resizable=no,status=no'
            );
            window.addEventListener('beforeunload', function () {
                if (_logoutPopup && !_logoutPopup.closed) {
                    _logoutPopup.close();
                }
            }, { once: true });
            return _logoutPopup;
        }
        return _originalOpen.apply(window, arguments);
    };
})();
</script>
<?php get_footer();

// Rewrite toàn bộ path plugin cũ → mới
$output = ob_get_clean();
$output = str_replace($old_plugin_url, $new_plugin_url, $output);
echo $output;
