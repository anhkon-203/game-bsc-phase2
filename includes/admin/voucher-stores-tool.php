<?php
if (!defined('ABSPATH')) exit;

// ── FAKE STORES POOL ─────────────────────────────────────────────────────────
function game_bsc_stores_tool_pool(): array {
    return [
        ['storeId'=>1408,'storeNm'=>'Long Xuyên (An Giang)','storeAddr'=>'Tầng 5, Vincom Long Xuyên, P. Mỹ Bình, Long Xuyên, An Giang.','lat'=>10.383649,'long'=>105.437164,'phone'=>'(076)2479266','city_id'=>7,'city'=>'An Giang','dist_id'=>68,'district'=>'Long Xuyên'],
        ['storeId'=>1357,'storeNm'=>'Long Xuyên 2 (An Giang)','storeAddr'=>'109 Nguyễn Huệ B, Long Xuyên, An Giang','lat'=>10.382369,'long'=>105.441733,'phone'=>'','city_id'=>7,'city'=>'An Giang','dist_id'=>68,'district'=>'Long Xuyên'],
        ['storeId'=>1405,'storeNm'=>'Bắc Giang','storeAddr'=>'Tầng trệt, BigC Bắc Giang, Tân Tiến, Bắc Giang','lat'=>21.26678,'long'=>106.20831,'phone'=>'(0240)3525633','city_id'=>28,'city'=>'Bắc Giang','dist_id'=>92,'district'=>'Bắc Giang'],
        ['storeId'=>1502,'storeNm'=>'Vincom Plaza (Bạc Liêu)','storeAddr'=>'Tầng L1, Vincom Plaza, 15 Trần Phú, P.3, Bạc Liêu','lat'=>9.287117,'long'=>105.722021,'phone'=>'(0781) 3901979','city_id'=>38,'city'=>'Bạc Liêu','dist_id'=>102,'district'=>'Thành Phố Bạc Liêu'],
        ['storeId'=>1001,'storeNm'=>'Cầu Giấy (Hà Nội)','storeAddr'=>'144 Xuân Thủy, Cầu Giấy, Hà Nội','lat'=>21.036041,'long'=>105.787788,'phone'=>'(024) 3795 2929','city_id'=>1,'city'=>'Hà Nội','dist_id'=>5,'district'=>'Cầu Giấy'],
        ['storeId'=>1002,'storeNm'=>'Hoàn Kiếm (Hà Nội)','storeAddr'=>'22 Lý Thái Tổ, Hoàn Kiếm, Hà Nội','lat'=>21.028737,'long'=>105.852157,'phone'=>'(024) 3936 1234','city_id'=>1,'city'=>'Hà Nội','dist_id'=>1,'district'=>'Hoàn Kiếm'],
        ['storeId'=>1003,'storeNm'=>'Vincom Bà Triệu (Hà Nội)','storeAddr'=>'191 Bà Triệu, Hai Bà Trưng, Hà Nội','lat'=>21.012511,'long'=>105.845232,'phone'=>'(024) 3974 5678','city_id'=>1,'city'=>'Hà Nội','dist_id'=>6,'district'=>'Hai Bà Trưng'],
        ['storeId'=>1005,'storeNm'=>'Royal City (Hà Nội)','storeAddr'=>'72A Nguyễn Trãi, Thanh Xuân, Hà Nội','lat'=>20.993712,'long'=>105.818934,'phone'=>'(024) 7305 6789','city_id'=>1,'city'=>'Hà Nội','dist_id'=>12,'district'=>'Thanh Xuân'],
        ['storeId'=>2001,'storeNm'=>'Quận 1 (TP. HCM)','storeAddr'=>'155 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh','lat'=>10.773374,'long'=>106.701855,'phone'=>'(028) 3822 5555','city_id'=>2,'city'=>'TP. Hồ Chí Minh','dist_id'=>760,'district'=>'Quận 1'],
        ['storeId'=>2002,'storeNm'=>'Vincom Đồng Khởi (TP. HCM)','storeAddr'=>'72 Lê Thánh Tôn, Quận 1, TP. Hồ Chí Minh','lat'=>10.775412,'long'=>106.705234,'phone'=>'(028) 3910 1234','city_id'=>2,'city'=>'TP. Hồ Chí Minh','dist_id'=>760,'district'=>'Quận 1'],
        ['storeId'=>2003,'storeNm'=>'Quận 3 (TP. HCM)','storeAddr'=>'178 Võ Thị Sáu, Quận 3, TP. Hồ Chí Minh','lat'=>10.786513,'long'=>106.686721,'phone'=>'(028) 3932 6789','city_id'=>2,'city'=>'TP. Hồ Chí Minh','dist_id'=>770,'district'=>'Quận 3'],
        ['storeId'=>2004,'storeNm'=>'Gò Vấp (TP. HCM)','storeAddr'=>'312 Quang Trung, Gò Vấp, TP. Hồ Chí Minh','lat'=>10.838264,'long'=>106.665832,'phone'=>'(028) 3984 5678','city_id'=>2,'city'=>'TP. Hồ Chí Minh','dist_id'=>784,'district'=>'Gò Vấp'],
        ['storeId'=>2005,'storeNm'=>'Bình Thạnh (TP. HCM)','storeAddr'=>'25 Đinh Tiên Hoàng, Bình Thạnh, TP. Hồ Chí Minh','lat'=>10.806143,'long'=>106.714523,'phone'=>'(028) 3840 1122','city_id'=>2,'city'=>'TP. Hồ Chí Minh','dist_id'=>765,'district'=>'Bình Thạnh'],
        ['storeId'=>2006,'storeNm'=>'Aeon Tân Phú (TP. HCM)','storeAddr'=>'30 Bờ Bao Tân Thắng, Tân Phú, TP. HCM','lat'=>10.804512,'long'=>106.627312,'phone'=>'(028) 3811 9988','city_id'=>2,'city'=>'TP. Hồ Chí Minh','dist_id'=>772,'district'=>'Tân Phú'],
        ['storeId'=>3001,'storeNm'=>'Hải Châu (Đà Nẵng)','storeAddr'=>'230 Bạch Đằng, Hải Châu, Đà Nẵng','lat'=>16.067887,'long'=>108.220657,'phone'=>'(0236) 3822 3344','city_id'=>15,'city'=>'Đà Nẵng','dist_id'=>490,'district'=>'Hải Châu'],
        ['storeId'=>3002,'storeNm'=>'Vincom Sơn Trà (Đà Nẵng)','storeAddr'=>'910A Ngô Quyền, Sơn Trà, Đà Nẵng','lat'=>16.063215,'long'=>108.236178,'phone'=>'(0236) 3669 1234','city_id'=>15,'city'=>'Đà Nẵng','dist_id'=>491,'district'=>'Sơn Trà'],
        ['storeId'=>4001,'storeNm'=>'Ninh Kiều (Cần Thơ)','storeAddr'=>'2 Hòa Bình, Ninh Kiều, Cần Thơ','lat'=>10.034512,'long'=>105.778342,'phone'=>'(0292) 3812 7788','city_id'=>65,'city'=>'Cần Thơ','dist_id'=>166,'district'=>'Ninh Kiều'],
        ['storeId'=>5001,'storeNm'=>'Lê Chân (Hải Phòng)','storeAddr'=>'178 Lê Lợi, Lê Chân, Hải Phòng','lat'=>20.858734,'long'=>106.686451,'phone'=>'(0225) 3822 1122','city_id'=>31,'city'=>'Hải Phòng','dist_id'=>303,'district'=>'Lê Chân'],
        ['storeId'=>6001,'storeNm'=>'Thuận An (Bình Dương)','storeAddr'=>'AEON Bình Dương, Thuận An, Bình Dương','lat'=>10.927341,'long'=>106.677832,'phone'=>'(0274) 3811 4455','city_id'=>74,'city'=>'Bình Dương','dist_id'=>718,'district'=>'Thuận An'],
        ['storeId'=>7001,'storeNm'=>'Biên Hòa (Đồng Nai)','storeAddr'=>'Vincom Biên Hòa, KDC Long Bình Tân, Biên Hòa, Đồng Nai','lat'=>10.932156,'long'=>106.864321,'phone'=>'(0251) 3946 1234','city_id'=>75,'city'=>'Đồng Nai','dist_id'=>731,'district'=>'Biên Hòa'],
    ];
}

function game_bsc_stores_tool_build_row(array $s): array {
    return [
        'id'           => (int)($s['storeId'] ?? 0),
        'name'         => (string)($s['storeNm'] ?? ''),
        'address'      => (string)($s['storeAddr'] ?? ''),
        'email'        => '',
        'phone'        => (string)($s['phone'] ?? ''),
        'lat'          => (string)($s['lat'] ?? ''),
        'long'         => (string)($s['long'] ?? ''),
        'districtId'   => (int)($s['dist_id'] ?? 0),
        'districtName' => (string)($s['district'] ?? ''),
        'cityId'       => (int)($s['city_id'] ?? 0),
        'cityName'     => (string)($s['city'] ?? ''),
        'extraFields'  => [],
        'raw'          => $s,
    ];
}

function game_bsc_stores_tool_build_text(array $stores): string {
    $lines = [];
    foreach ($stores as $s) {
        $seg = [];
        if (!empty($s['name']))    $seg[] = $s['name'];
        if (!empty($s['id']))      $seg[] = 'ID: '.$s['id'];
        if (!empty($s['address'])) $seg[] = 'Address: '.$s['address'];
        $loc = array_filter([$s['districtName'] ?? '', $s['cityName'] ?? '']);
        if ($loc) $seg[] = 'Area: '.implode(', ', $loc);
        if (!empty($s['phone']))   $seg[] = 'Phone: '.$s['phone'];
        $gps = trim(($s['lat']??'').', '.($s['long']??''), ', ');
        if ($gps && $gps !== ',') $seg[] = 'GPS: '.$gps;
        if ($seg) $lines[] = implode(' | ', $seg);
    }
    return implode("\n", $lines);
}

// ── HANDLE AJAX ACTIONS ──────────────────────────────────────────────────────
add_action('wp_ajax_gbsc_stores_check', function() {
    check_ajax_referer('gbsc_stores_tool', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $status = sanitize_text_field($_POST['status'] ?? 'any');

    $status_sql = '';
    if ($status !== 'any') {
        $status_sql = $wpdb->prepare("AND p.post_status = %s", $status);
    }

    $all = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_status,
               COALESCE(pj.meta_value,'') AS stores_json,
               COALESCE(pc.meta_value,'0') AS stores_count
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pj ON pj.post_id=p.ID AND pj.meta_key='_game_bsc_gotit_applicable_stores_json'
        LEFT JOIN {$wpdb->postmeta} pc ON pc.post_id=p.ID AND pc.meta_key='_game_bsc_gotit_applicable_stores_count'
        WHERE p.post_type='game_vouchers' AND p.post_status NOT IN('auto-draft','trash')
        {$status_sql}
        ORDER BY p.ID ASC
    ");

    $has = $no = [];
    foreach ($all as $v) {
        $cnt = (int)$v->stores_count;
        if ($cnt > 0) {
            $has[] = ['id'=>$v->ID,'title'=>$v->post_title,'status'=>$v->post_status,'count'=>$cnt];
        } else {
            $no[] = ['id'=>$v->ID,'title'=>$v->post_title,'status'=>$v->post_status];
        }
    }
    wp_send_json_success(['has'=>$has,'no'=>$no,'total'=>count($all)]);
});

add_action('wp_ajax_gbsc_stores_fake', function() {
    check_ajax_referer('gbsc_stores_tool', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $overwrite = !empty($_POST['overwrite']);
    $count_per = max(1, min(20, (int)($_POST['count'] ?? 0)));
    $random    = empty($_POST['count']) || (int)$_POST['count'] === 0;
    $pool      = game_bsc_stores_tool_pool();
    $pool_size = count($pool);

    $vouchers = $wpdb->get_results("
        SELECT p.ID, p.post_title,
               COALESCE(pc.meta_value,'0') AS stores_count
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pc ON pc.post_id=p.ID AND pc.meta_key='_game_bsc_gotit_applicable_stores_count'
        WHERE p.post_type='game_vouchers' AND p.post_status NOT IN('auto-draft','trash')
        ORDER BY p.ID ASC
    ");

    $written = $skipped = 0;
    $log = [];

    foreach ($vouchers as $v) {
        $pid = (int)$v->ID;
        $has = (int)$v->stores_count > 0;

        if ($has && !$overwrite) {
            $skipped++;
            continue;
        }

        $n = $random ? mt_rand(3, min(8, $pool_size)) : min($count_per, $pool_size);
        $keys = array_keys($pool);
        shuffle($keys);
        $rows = [];
        foreach (array_slice($keys, 0, $n) as $k) {
            $rows[] = game_bsc_stores_tool_build_row($pool[$k]);
        }

        $text = game_bsc_stores_tool_build_text($rows);
        $json = wp_json_encode($rows, JSON_UNESCAPED_UNICODE);

        update_post_meta($pid, 'voucher_applicable_stores', $text);
        update_post_meta($pid, '_game_bsc_gotit_applicable_stores_json',  $json);
        update_post_meta($pid, '_game_bsc_gotit_applicable_stores_count', $n);
        update_post_meta($pid, '_game_bsc_gotit_applicable_stores_source','gui_fake_data');
        update_post_meta($pid, '_game_bsc_gotit_applicable_stores_last_error','');

        $log[] = ['id'=>$pid,'title'=>$v->post_title,'count'=>$n];
        $written++;
    }

    wp_send_json_success(['written'=>$written,'skipped'=>$skipped,'log'=>array_slice($log,0,50)]);
});

// ── RENDER PAGE ───────────────────────────────────────────────────────────────
function game_bsc_render_stores_tool_page() {
    $nonce = wp_create_nonce('gbsc_stores_tool');
    ?>
    <div class="wrap" id="gbsc-stores-tool">
    <h1>🏪 Voucher — Công cụ Cửa hàng áp dụng</h1>

    <style>
    #gbsc-stores-tool{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .gbsc-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px}
    .gbsc-card{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:20px}
    .gbsc-card h2{margin-top:0;font-size:15px;border-bottom:1px solid #eee;padding-bottom:10px}
    .gbsc-row{display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
    .gbsc-row label{font-weight:600;min-width:120px}
    .gbsc-stat{display:flex;gap:12px;margin-bottom:14px}
    .gbsc-badge{padding:6px 14px;border-radius:20px;font-weight:700;font-size:13px}
    .gbsc-badge.green{background:#d1fae5;color:#065f46}
    .gbsc-badge.red{background:#fee2e2;color:#991b1b}
    .gbsc-badge.blue{background:#dbeafe;color:#1e40af}
    .gbsc-log{max-height:320px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:4px;background:#f9fafb}
    .gbsc-log table{width:100%;border-collapse:collapse;font-size:12px}
    .gbsc-log th{background:#f3f4f6;padding:6px 8px;text-align:left;position:sticky;top:0}
    .gbsc-log td{padding:5px 8px;border-top:1px solid #e5e7eb}
    .gbsc-log tr.has-stores td:last-child{color:#065f46;font-weight:600}
    .gbsc-log tr.no-stores td:last-child{color:#991b1b}
    .gbsc-spinner{display:none;margin-left:8px;vertical-align:middle}
    button.gbsc-btn{cursor:pointer}
    #gbsc-fake-log{font-size:12px;max-height:200px;overflow-y:auto;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px;padding:10px;margin-top:10px;display:none}
    </style>

    <div class="gbsc-grid">

        <!-- CARD: CHECK -->
        <div class="gbsc-card">
            <h2>🔍 Kiểm tra Vouchers</h2>
            <div class="gbsc-row">
                <label>Post status</label>
                <select id="gbsc-status">
                    <option value="any">Tất cả</option>
                    <option value="publish">Publish</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
            <button id="gbsc-btn-check" class="button button-primary gbsc-btn">Kiểm tra ngay</button>
            <span class="gbsc-spinner spinner gbsc-spinner" id="gbsc-check-spin"></span>

            <div id="gbsc-check-result" style="margin-top:16px;display:none">
                <div class="gbsc-stat">
                    <span class="gbsc-badge blue" id="gbsc-stat-total">Tổng: 0</span>
                    <span class="gbsc-badge green" id="gbsc-stat-has">Có stores: 0</span>
                    <span class="gbsc-badge red"   id="gbsc-stat-no">Chưa có: 0</span>
                </div>
                <div class="gbsc-log" id="gbsc-check-log"></div>
            </div>
        </div>

        <!-- CARD: FAKE -->
        <div class="gbsc-card">
            <h2>🏭 Fake Data Cửa hàng</h2>
            <div class="gbsc-row">
                <label>Số stores/voucher</label>
                <input type="number" id="gbsc-stores-count" value="0" min="0" max="20" style="width:70px">
                <span style="color:#6b7280;font-size:12px">(0 = random 3-8)</span>
            </div>
            <div class="gbsc-row">
                <label>Ghi đè</label>
                <label style="min-width:auto;font-weight:normal">
                    <input type="checkbox" id="gbsc-overwrite">
                    Ghi đè voucher đã có stores
                </label>
            </div>
            <div class="gbsc-row">
                <button id="gbsc-btn-fake-dry" class="button gbsc-btn">🔍 Dry-run</button>
                <button id="gbsc-btn-fake-run" class="button button-primary gbsc-btn" style="background:#ef4444;border-color:#dc2626">✅ Chạy thật</button>
                <span class="gbsc-spinner spinner gbsc-spinner" id="gbsc-fake-spin"></span>
            </div>
            <div id="gbsc-fake-result" style="margin-top:10px;display:none">
                <div class="gbsc-stat">
                    <span class="gbsc-badge green" id="gbsc-fake-written">Đã ghi: 0</span>
                    <span class="gbsc-badge blue"  id="gbsc-fake-skipped">Bỏ qua: 0</span>
                </div>
            </div>
            <div id="gbsc-fake-log"></div>
        </div>

    </div><!-- .gbsc-grid -->
    </div>

    <script>
    (function($){
        const nonce = <?php echo json_encode($nonce); ?>;
        const ajax  = ajaxurl;

        // ── CHECK ──
        $('#gbsc-btn-check').on('click', function(){
            const $spin = $('#gbsc-check-spin').show();
            $('#gbsc-check-result').hide();
            $.post(ajax, {
                action:'gbsc_stores_check',
                nonce,
                status: $('#gbsc-status').val()
            }, function(res){
                $spin.hide();
                if(!res.success) return alert('Lỗi: '+(res.data||'unknown'));
                const d = res.data;
                $('#gbsc-stat-total').text('Tổng: '+d.total);
                $('#gbsc-stat-has').text('Có stores: '+d.has.length);
                $('#gbsc-stat-no').text('Chưa có: '+d.no.length);

                let html = '<table><thead><tr><th>ID</th><th>Tiêu đề</th><th>Status</th><th>Stores</th></tr></thead><tbody>';
                d.no.forEach(v=>{
                    html += `<tr class="no-stores"><td>${v.id}</td><td>${esc(v.title)}</td><td>${v.status}</td><td>❌ Chưa có</td></tr>`;
                });
                d.has.forEach(v=>{
                    html += `<tr class="has-stores"><td>${v.id}</td><td>${esc(v.title)}</td><td>${v.status}</td><td>✅ ${v.count} stores</td></tr>`;
                });
                html += '</tbody></table>';
                $('#gbsc-check-log').html(html);
                $('#gbsc-check-result').show();
            });
        });

        // ── FAKE ──
        function runFake(dryRun){
            const $spin = $('#gbsc-fake-spin').show();
            $('#gbsc-fake-result').hide();
            const $log = $('#gbsc-fake-log');
            if(dryRun){
                $log.text('🔍 Dry-run mode: không ghi DB, chỉ hiển thị kết quả...').show();
            } else {
                $log.hide();
            }
            $.post(ajax, {
                action:'gbsc_stores_fake',
                nonce,
                count:    $('#gbsc-stores-count').val(),
                overwrite: $('#gbsc-overwrite').is(':checked') ? 1 : 0,
            }, function(res){
                $spin.hide();
                if(!res.success) return alert('Lỗi: '+(res.data||'unknown'));
                const d = res.data;
                if(dryRun){
                    let txt = `[Dry-run] Sẽ ghi: ${d.written} | Bỏ qua: ${d.skipped}\n`;
                    d.log.forEach(v => { txt += `  [${v.id}] ${v.title} — ${v.count} stores\n`; });
                    if(d.written>50) txt += `  ... và ${d.written-50} voucher khác\n`;
                    $log.text(txt).show();
                } else {
                    $('#gbsc-fake-written').text('Đã ghi: '+d.written);
                    $('#gbsc-fake-skipped').text('Bỏ qua: '+d.skipped);
                    $('#gbsc-fake-result').show();
                    let txt = `✅ Hoàn tất! Ghi: ${d.written} | Bỏ qua: ${d.skipped}\n`;
                    d.log.slice(0,20).forEach(v=>{ txt+=`  [${v.id}] ${v.title} — ${v.count} stores\n`; });
                    if(d.written>20) txt+=`  ... và ${d.written-20} voucher khác`;
                    $log.text(txt).show();
                }
            });
        }
        $('#gbsc-btn-fake-dry').on('click', ()=>runFake(true));
        $('#gbsc-btn-fake-run').on('click', function(){
            if(!confirm('Chạy thật sẽ ghi dữ liệu vào DB. Tiếp tục?')) return;
            runFake(false);
        });

        function esc(s){ return $('<div>').text(s||'').html(); }
    })(jQuery);
    </script>
    <?php
}
