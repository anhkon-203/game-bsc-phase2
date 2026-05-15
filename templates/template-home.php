<?php
    // Handle SSO callback nếu có
    bsc_game_handle_sso_callback();
    save_user_daily_login_mission();
    save_user_badges();
if(!wp_is_mobile()){

get_header();
// $header_output = ob_get_clean();
$iframe_src = rest_url('game-bsc/init');
?>
<iframe id="next_iframe" src="<?php echo $iframe_src; ?>" style="width: 100%; height: 100vh; border: none; overflow:hidden" > </iframe>

<script>
    window.addEventListener("message", function (event) {
        const iframe_next = document.getElementById("next_iframe");
        if (event.data && event.data.iframeHeight) {
            iframe_next.style.height = event.data.iframeHeight + "px";
        }
        if(event.data && event.data.cstd_callapi_authen) {
            window.location.href = event.data.cstd_callapi_authen;
        }
        if(event.data && event.data.scrollToElement) {
            iframe_next.scrollIntoView({ behavior: "smooth", block: "end", inline: "nearest" });
        }
        if(event.data && event.data.postBackLogout) {
            const win = window.open(event.data.postBackLogout, "sso", "width=450,height=600");

                // Nếu popup bị chặn
                if (!win) {
                    console.warn("Popup blocked → assume logout done");
                    handleAfterLogout();
                } else {
                    setTimeout(() => {
                        try {
                        // Đóng popup sau 2 giây
                        if (!win.closed) {
                            win.close();
                        }
                        } catch (e) {
                        console.warn("Cannot close popup:", e);
                        }

                        console.log("Popup auto-closed after 2s → assume logout done");
                        handleAfterLogout();
                    }, 2000);
                }

                // Hàm xử lý tiếp sau logout
                function handleAfterLogout() {
                    if (iframe_next && iframe_next.contentWindow) {
                        iframe_next.contentWindow.postMessage(
                            { type: "logout_done" },
                            "<?php echo (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>"
                        );
                    }
                }
        }
    });
</script>
<?php
get_footer();

} else {
    $html_path = GAME_BSC_PLUGIN_DIR . 'assets/front-end/index.html';

    if ( ! file_exists( $html_path ) ) {
        echo '<p style="color:white;text-align:center;">Không tìm thấy app.</p>';
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
    echo $index;
}





    die();
/**
 * Lấy innerHTML <body> (giữ như bạn có)
 */
function get_next_body_html(string $file_path, bool $strip_bom = true): string {
    if (!file_exists($file_path)) return '';
    $content = file_get_contents($file_path);
    if ($content === false) return '';
    if ($strip_bom) $content = preg_replace('/^\x{FEFF}/u', '', $content);

    if (preg_match('#<body\b[^>]*>(.*?)</body>#isu', $content, $m)) {
        return $m[1];
    }
    if (preg_match('#<html\b[^>]*>(.*?)</html>#isu', $content, $m2)) {
        return $m2[1];
    }
    return $content;
}

/**
 * Xóa mọi <script>...</script> khỏi html (đã có)
 */
function strip_script_tags(string $html): string {
    $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $clean = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', '', $clean);
    return $clean;
}

/**
 * Build public URL cho asset dựa trên path trong tag (vd: /_next/... hoặc /wp-content/plugins/...)
 */
function nextapp_build_public_url(string $path) : string {
    // base uri và base path plugin
    $plugin_base_uri  = untrailingslashit( GAME_BSC_PLUGIN_URL . 'assets/front-end' ); // e.g. https://site/wp-content/plugins/game-bsc/assets/front-end
    // Normalize
    $p = str_replace('\\', '/', $path);

    // Already absolute URL?
    if (preg_match('#^https?://#i', $p)) {
        return $p;
    }

    // If starts with /wp-content/plugins/game-bsc/assets/front-end
    $prefix = '/wp-content/plugins/game-bsc/assets/front-end';
    if (strpos($p, $prefix) === 0) {
        $rel = substr($p, strlen($prefix));
        return $plugin_base_uri . $rel;
    }

    // If starts with /_next
    if (strpos($p, '/_next') === 0) {
        $rel = substr($p, strlen('/_next'));
        return $plugin_base_uri . '/_next' . $rel;
    }

    // If starts with _next or ./_next (relative)
    $p_trim = ltrim($p, './');
    if (strpos($p_trim, '_next') === 0) {
        return $plugin_base_uri . '/' . $p_trim;
    }

    // If path starts with '/' but not match above, return as-is (site-root relative)
    if (substr($p, 0, 1) === '/') {
        return $p;
    }

    // fallback: return as-is
    return $p;
}

/**
 * Extract link tags (rel=stylesheet) from head HTML
 * Returns array of processed tag strings (href mapped)
 */
function extract_link_tags_from_head(string $head_html): array {
    $out = [];
    if (preg_match_all('/<link\b[^>]*rel\s*=\s*([\'"])stylesheet\1[^>]*>/is', $head_html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $link_tag = $m[0];
            // find href
            if (preg_match('/href\s*=\s*([\'"])(.*?)\1/i', $link_tag, $hm)) {
                $href = $hm[2];
                $new_href = nextapp_build_public_url($href);
                // replace href in tag with mapped URL (escape)
                $safe = str_replace($hm[0], 'href="' . esc_attr($new_href) . '"', $link_tag);
                $out[] = $safe;
            } else {
                // no href? keep as-is
                $out[] = $link_tag;
            }
        }
    }
    return $out;
}

/**
 * Extract script tags from given HTML (head or body)
 * Returns array of tags where external src are mapped to public URL and inline scripts kept.
 */
function extract_script_tags(string $html): array {
    $out = [];
    if (preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $attr_str = $m[1];
            $inner    = $m[2];
            // check src attr
            if (preg_match('/\bsrc\s*=\s*([\'"])(.*?)\1/i', $attr_str, $sm)) {
                $src = $sm[2];
                $new_src = nextapp_build_public_url($src);
                // rebuild opening tag preserving other attrs except old src
                // remove old src attribute from attr_str
                $attr_str_clean = preg_replace('/\bsrc\s*=\s*([\'"])(.*?)\1/i', '', $attr_str);
                $attr_str_clean = trim($attr_str_clean);
                $tag = '<script' . ($attr_str_clean ? ' ' . $attr_str_clean : '') . ' src="' . esc_attr($new_src) . '">';
                // if there is any inline content (rare when src present), append it
                $tag .= $inner . '</script>';
                $out[] = $tag;
            } else {
                // inline script - preserve attributes (if any) and content
                $attr_str = trim($attr_str);
                $tag = '<script' . ($attr_str ? ' ' . $attr_str : '') . '>' . $inner . '</script>';
                $out[] = $tag;
            }
        }
    }
    return $out;
}

/* ------------------ USAGE: chèn vào template ------------------ */
$index_file = untrailingslashit( GAME_BSC_PLUGIN_DIR ) . '/assets/front-end/index.html';

// nếu không tồn tại file thì dừng
if (!file_exists($index_file)) {
    echo '<p style="color:white;text-align:center;">Không tìm thấy app.</p>';
    return;
}

// đọc file gốc
$raw = file_get_contents($index_file);
if ($raw === false) {
    echo '<p style="color:white;text-align:center;">Không đọc được file app.</p>';
    return;
}

// Tách head và body
$head_html = '';
$body_html = '';
if (preg_match('#<head\b[^>]*>(.*?)</head>#isu', $raw, $hm)) {
    $head_html = $hm[1];
}
if (preg_match('#<body\b[^>]*>(.*?)</body>#isu', $raw, $bm)) {
    $body_html = $bm[1];
} else {
    // fallback: lấy toàn bộ sau head
    $body_html = get_next_body_html($index_file);
}

// extract link tags from head
$links = extract_link_tags_from_head($head_html);

// extract scripts in head & body
$head_scripts = [];
$body_scripts = [];
if ($head_html !== '') {
    $head_scripts = extract_script_tags($head_html);
}
if ($body_html !== '') {
    $body_scripts = extract_script_tags($body_html);
}

// Tạo nonce JS (của bạn)
$nonce = wp_create_nonce('wp_game_rest');
$nonce_script = "<script>window.B5X7zJe2wSqY = " . wp_json_encode(['nonce' => $nonce]) . "</script>";

// --- In header nhưng chèn link + head scripts trước </head> ---
ob_start();
get_header();
$header_output = ob_get_clean();

if (stripos($header_output, '</head>') !== false) {
    // nhóm link then head scripts
    $insert = '';
    if (!empty($links)) {
        $insert .= implode("\n", $links) . "\n";
    }
    if (!empty($head_scripts)) {
        $insert .= implode("\n", $head_scripts) . "\n";
    }
    // chèn trước </head>
    $header_output = str_ireplace('</head>', $insert . '</head>', $header_output);
} else {
    // fallback: append at top
    $insert = '';
    if (!empty($links)) $insert .= implode("\n", $links) . "\n";
    if (!empty($head_scripts)) $insert .= implode("\n", $head_scripts) . "\n";
    $header_output = $insert . $header_output;
}
// $html = '';
echo $header_output;
echo "<style>
        body {
            overflow: hidden;
            line-height: 22px;
            margin: 0;
        }
    </style>";
// $html .= $header_output;

// --- In phần body (không có script tags) ---
$body_no_scripts = strip_script_tags($body_html);
echo $body_no_scripts;
// $html .= $body_no_scripts;

// In nonce script (nếu bạn muốn ở body)
echo $nonce_script;
// $html .= $nonce_script;

// --- In các script phần body (external + inline) ngay trước footer ---
if (!empty($body_scripts)) {
    echo implode("\n", $body_scripts);
    // $html .= implode("\n", $body_scripts);
}
// echo $html;
// gọi footer
get_footer();
