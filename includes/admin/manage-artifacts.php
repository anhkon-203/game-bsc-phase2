<?php
$admin_url = admin_url('admin.php?page=game-bsc-manage-artifacts');

function game_bsc_manage_artifacts_page() {
    // Cấu hình kích thước
    $artifact_img_size = 120; // Ảnh gốc (px)
    $piece_mask_size = 60;   // Kích thước mask/mảnh (px)

    // Hiển thị danh sách hiện vật đã lưu
    global $wpdb, $admin_url;
    $artifacts_table = $wpdb->prefix . 'game_artifacts';
    $pieces_table = $wpdb->prefix . 'game_pieces';
    $artifact_list = $wpdb->get_results("SELECT * FROM $artifacts_table ORDER BY id DESC");

    // Nếu có edit_id, load dữ liệu hiện vật để chỉnh sửa
    $editing      = false;
    $edit_item    = null;
    $edit_pieces  = [];
    if (!empty($_GET['edit_id'])) {
        $editing = true;
        $edit_id = (int) $_GET['edit_id'];
        $edit_item = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $artifacts_table WHERE id=%d", $edit_id) );
        if ($edit_item) {
            $edit_pieces = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $pieces_table WHERE artifact_id=%d", $edit_item->id) );
        }
    }

    ?>

    <div class="wrap">
        <h1>Quản lý hiện vật</h1>
        <?php if (!empty($_GET['import_result'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html($_GET['import_result']); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($_GET['import_error'])): ?>
            <div class="notice notice-error"><p><?php echo esc_html($_GET['import_error']); ?></p></div>
        <?php endif; ?>
        <form id="artifact-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('game_bsc_save_artifact'); ?>
            <input type="hidden" name="action" value="game_bsc_save_artifact">
            <div id="artifact-items">
                <?php if ($editing && $edit_item): ?>
                    <?php game_bsc_render_artifact_fieldset_prefilled(0, $edit_item, $edit_pieces); ?>
                <?php else: ?>
                    <!-- Form nhập nhiều hiện vật -->
                    <button type="button" id="add-artifact" class="button">Thêm hiện vật</button>
                <?php endif; ?>
            </div>
            <p><input type="submit" name="save_artifact" class="button-primary" value="Lưu hiện vật"></p>
        </form>

        <h2>Danh sách hiện vật đã lưu</h2>
        <?php $user_pieces_table = $wpdb->prefix . 'game_user_pieces'; ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th rowspan="2">Tên hiện vật</th>
                    <th rowspan="2">Số lượt đổi tối đa</th>
                    <th colspan="4" style="text-align:center;">Số lượng KH sở hữu mảnh ghép</th>
                    <th rowspan="2">Trạng thái</th>
                    <th rowspan="2">Mảnh ghép</th>
                    <th rowspan="2">Hành động</th>
                </tr>
                <tr>
                    <th style="text-align:center;">1/4 mảnh</th>
                    <th style="text-align:center;">2/4 mảnh</th>
                    <th style="text-align:center;">3/4 mảnh</th>
                    <th style="text-align:center;">4/4 mảnh</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($artifact_list as $artifact):
                    $piece_counts_raw = $wpdb->get_results($wpdb->prepare(
                        "SELECT piece_count, COUNT(*) as user_count
                         FROM (
                             SELECT up.user_id, COUNT(DISTINCT p.piece_code) as piece_count
                             FROM {$user_pieces_table} up
                             INNER JOIN {$pieces_table} p ON up.piece_id = p.id
                             WHERE up.artifact_id = %d AND up.qty > 0
                             GROUP BY up.user_id
                         ) sub
                         GROUP BY piece_count",
                        $artifact->id
                    ));
                    $piece_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
                    foreach ($piece_counts_raw as $row) {
                        $pc = (int) $row->piece_count;
                        if ($pc >= 1 && $pc <= 4) {
                            $piece_distribution[$pc] = (int) $row->user_count;
                        }
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html($artifact->name); ?></td>
                        <td><?php echo esc_html($artifact->max_redemptions); ?></td>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <td style="text-align:center;">
                                <a href="#" class="artifact-piece-detail-link"
                                   data-artifact-id="<?php echo (int) $artifact->id; ?>"
                                   data-artifact-name="<?php echo esc_attr($artifact->name); ?>"
                                   data-piece-count="<?php echo $i; ?>">
                                    <?php echo $piece_distribution[$i]; ?>
                                </a>
                            </td>
                        <?php endfor; ?>
                        <td><?php echo $artifact->status == 0 ? 'Đã đóng' : 'Đang mở'; ?></td>
                        <td>
                            <?php
                            $pieces = $wpdb->get_results($wpdb->prepare("SELECT * FROM $pieces_table WHERE artifact_id=%d ORDER BY piece_code ASC", $artifact->id));
                            foreach ($pieces as $piece) {
                                echo '<div style="display:inline-block;text-align:center;margin-right:8px">';
                                echo '<img src="' . esc_url($piece->piece_img) . '" style="width:40px;height:40px;display:block;margin-bottom:2px;">';
                                echo '<span>' . esc_html($piece->piece_code) . ' (' . esc_html($piece->baseline_weight) . '%)</span>';
                                echo '</div>';
                            }
                            ?>
                        </td>
                        <td>
                            <a class="button" href="<?php echo esc_url( $admin_url . '&edit_id='.(int)$artifact->id ); ?>">
                                Sửa
                            </a>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                onsubmit="return confirm('Xóa hiện vật & toàn bộ mảnh của nó?');" style="display:inline-block;">
                                <?php wp_nonce_field('game_bsc_delete_artifact_' . $artifact->id); ?>
                                <input type="hidden" name="action" value="game_bsc_delete_artifact">
                                <input type="hidden" name="artifact_id" value="<?php echo (int)$artifact->id; ?>">
                                <button type="submit" class="button button-link-delete">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Bảng chi tiết KH sở hữu mảnh ghép -->
        <div id="artifact-detail-panel" style="display:none;margin-top:20px;border:1px solid #ccc;padding:16px;background:#f9f9f9;">
            <h3 id="artifact-detail-title"></h3>
            <p class="artifact-detail-loading" style="display:none;">Đang tải dữ liệu...</p>
            <table class="widefat" id="artifact-detail-table" style="display:none;">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Số TKCK</th>
                        <th>Họ và tên</th>
                        <th>Mảnh 1</th>
                        <th>Mảnh 2</th>
                        <th>Mảnh 3</th>
                        <th>Mảnh 4</th>
                        <th>Trạng thái đổi quà</th>
                    </tr>
                </thead>
                <tbody id="artifact-detail-tbody"></tbody>
            </table>
            <p style="margin-top:12px;"><button type="button" class="button" id="artifact-detail-close">Đóng</button></p>
        </div>

        <script>
        (function() {
            var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            var panel = document.getElementById('artifact-detail-panel');
            var titleEl = document.getElementById('artifact-detail-title');
            var loading = panel.querySelector('.artifact-detail-loading');
            var detailTable = document.getElementById('artifact-detail-table');
            var tbody = document.getElementById('artifact-detail-tbody');
            var closeBtn = document.getElementById('artifact-detail-close');

            function escapeHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            document.addEventListener('click', function(e) {
                var link = e.target.closest('.artifact-piece-detail-link');
                if (!link) return;
                e.preventDefault();

                var artifactId = link.getAttribute('data-artifact-id');
                var artifactName = link.getAttribute('data-artifact-name');
                var pieceCount = link.getAttribute('data-piece-count');

                titleEl.textContent = 'Danh sách khách hàng sở hữu ' + pieceCount + '/4 mảnh của quà ' + artifactName;
                panel.style.display = '';
                loading.style.display = '';
                detailTable.style.display = 'none';
                tbody.innerHTML = '';
                
                // Cuộn mượt xuống panel
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

                var formData = new FormData();
                formData.append('action', 'game_bsc_artifact_piece_detail');
                formData.append('artifact_id', artifactId);
                formData.append('piece_count', pieceCount);

                fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        loading.style.display = 'none';
                        if (!resp.success) {
                            tbody.innerHTML = '<tr><td colspan="8">Lỗi: ' + escapeHtml(String(resp.data || 'Unknown')) + '</td></tr>';
                            detailTable.style.display = '';
                            return;
                        }
                        var data = resp.data;
                        if (!data.length) {
                            tbody.innerHTML = '<tr><td colspan="8">Không có dữ liệu</td></tr>';
                            detailTable.style.display = '';
                            return;
                        }
                        var html = '';
                        for (var i = 0; i < data.length; i++) {
                            var row = data[i];
                            html += '<tr>';
                            html += '<td>' + (i + 1) + '</td>';
                            html += '<td>' + escapeHtml(String(row.account)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.name)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.p1)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.p2)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.p3)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.p4)) + '</td>';
                            html += '<td>' + escapeHtml(String(row.status)) + '</td>';
                            html += '</tr>';
                        }
                        tbody.innerHTML = html;
                        detailTable.style.display = '';
                    })
                    .catch(function() {
                        loading.style.display = 'none';
                        tbody.innerHTML = '<tr><td colspan="8">Lỗi kết nối</td></tr>';
                        detailTable.style.display = '';
                    });
            });

            closeBtn.addEventListener('click', function() {
                panel.style.display = 'none';
            });
        })();
        </script>
    </div>

    <canvas id="hidden-canvas" style="display:none;"></canvas>

    <script>
    const isEditing = <?php echo $editing ? 'true' : 'false'; ?>;
    const artifactImgSize = <?php echo intval($artifact_img_size); ?>;
    const pieceMaskSize = <?php echo intval($piece_mask_size); ?>;
    const pieceMasks = [
        '<?php echo GAME_BSC_PLUGIN_URL; ?>assets/images/mask1.png',
        '<?php echo GAME_BSC_PLUGIN_URL; ?>assets/images/mask2.png',
        '<?php echo GAME_BSC_PLUGIN_URL; ?>assets/images/mask3.png',
        '<?php echo GAME_BSC_PLUGIN_URL; ?>assets/images/mask4.png'
    ];

    let pieceBlobsArr = []; // Mảng lưu blob cho từng hiện vật

    function getFieldsets() {
        return Array.from(document.querySelectorAll('#artifact-items .artifact-fieldset'));
    }

    function toggleDeleteButtons() {
        const sets = getFieldsets();
        const show = sets.length > 1;
        sets.forEach(fs => {
            const btn = fs.querySelector('.link-delete-artifact');
            if (btn) btn.style.display = show ? '' : 'none';
        });
    }

    function reindexArtifacts() {
        const sets = getFieldsets();
        const newPieceBlobs = [];
        sets.forEach((fs, newIdx) => {
            const oldIdx = parseInt(fs.getAttribute('data-index'), 10);

            // Cập nhật data-index và legend
            fs.setAttribute('data-index', newIdx);
            const legend = fs.querySelector('legend');
            if (legend) legend.textContent = `Hiện vật #${newIdx + 1}`;

            // Cập nhật nút xóa
            const delBtn = fs.querySelector('.link-delete-artifact');
            if (delBtn) delBtn.setAttribute('data-index', newIdx);

            // Update mọi name="artifacts[old][...]" -> artifacts[new][...]
            fs.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/artifacts\[\d+\]/, `artifacts[${newIdx}]`);
            });

            // Update id/for liên quan
            fs.querySelectorAll('[id]').forEach(el => {
                el.id = el.id
                    .replace(/artifact-preview-\d+/, `artifact-preview-${newIdx}`)
                    .replace(/piece-img-url-\d+-([1-4])/, `piece-img-url-${newIdx}-$1`);
            });

            // Update data-index của input file
            fs.querySelectorAll('.artifact-img').forEach(inp => {
                inp.setAttribute('data-index', newIdx);
            });

            // Bản đồ lại piece blobs
            newPieceBlobs[newIdx] = pieceBlobsArr[oldIdx] || [];
        });
        pieceBlobsArr = newPieceBlobs;
        toggleDeleteButtons();
    }

    function renderArtifactForm(index) {
        return `
        <fieldset class="artifact-fieldset" data-index="${index}" style="border:1px solid #ccc;margin-bottom:16px;padding:10px;position:relative;">
            <legend>Hiện vật #${index + 1}</legend>
            <div class="artifact-actions" style="position:absolute;top:8px;right:10px;">
                <button type="button" class="button link-delete-artifact" data-index="${index}">Xóa hiện vật</button>
            </div>
            <table class="form-table">
                <!-- Hidden ID để update (rỗng khi thêm mới) -->
                <input type="hidden" name="artifacts[${index}][id]" value="">

                <tr>
                    <th>Tên hiện vật</th>
                    <td><input type="text" name="artifacts[${index}][name]" required></td>
                </tr>
                <tr>
                    <th>Số lượt đổi tối đa</th>
                    <td>
                        <input type="number" name="artifacts[${index}][max_redemptions]" min="1" value="1">
                        <p class="description">Tổng số quà hiện vật có thể trao trong toàn bộ chương trình. Số kỳ tung quà sẽ tự động bằng giá trị này.</p>
                    </td>
                </tr>
                <tr>
                    <th>Thời hạn hiện vật</th>
                    <td>
                        <label>Từ ngày: <input type="datetime-local" name="artifacts[${index}][period_start]" style="margin-right:12px;"></label>
                        <label>Đến ngày: <input type="datetime-local" name="artifacts[${index}][period_end]"></label>
                        <p class="description">Để trống nếu không giới hạn thời gian. Hết quota kỳ thì hiện vật sẽ không rơi mảnh trong kỳ đó.</p>
                    </td>
                </tr>
                <tr>
                    <th>Trạng thái</th>
                    <td>
                        <label style="margin-right:12px;">
                            <input type="radio" name="artifacts[${index}][status]" value="1" checked> Mở
                        </label>
                        <label>
                            <input type="radio" name="artifacts[${index}][status]" value="0"> Đóng
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Ảnh hiện vật</th>
                    <td>

                        <input type="file" class="artifact-img" name="artifacts[${index}][image]" data-index="${index}" accept="image/*" required>
                        <div class="artifact-preview" id="artifact-preview-${index}" style="margin-top:8px;"></div>
                    </td>
                </tr>
            </table>
            <h4>Thiết lập tỉ lệ rơi và upload mảnh</h4>
            <div class="artifact-pieces" id="artifact-pieces-${index}">
                ${[1,2,3,4].map(i => `
                    <div style="display:inline-block;margin-right:12px;">
                        <label>Mảnh P${i} - Tỉ lệ rơi (%)</label>
                        <input type="number" name="artifacts[${index}][piece_weight][${i}]" min="0" max="100" value="0" style="width:60px;" required>
                        <input type="hidden" name="artifacts[${index}][piece_img_url][${i}]" id="piece-img-url-${index}-${i}" required>
                    </div>
                `).join('')}
            </div>
        </fieldset>
        `;
    }


    function addArtifactForm() {
        const container = document.getElementById('artifact-items');
        const addBtn = document.getElementById('add-artifact');
        const newIndex = getFieldsets().length;
        // chèn form ngay TRƯỚC nút "Thêm hiện vật" để nút luôn nằm dưới cùng
        addBtn.insertAdjacentHTML('beforebegin', renderArtifactForm(newIndex));
        pieceBlobsArr[newIndex] = [];
        toggleDeleteButtons();
    }
    if(document.getElementById('add-artifact')) {
        document.getElementById('add-artifact').addEventListener('click', addArtifactForm);
    }

    
    if (!isEditing) {
        // Khởi tạo 1 hiện vật mặc định khi KHÔNG ở chế độ sửa
        addArtifactForm();
    }

    // Xóa hiện vật (event delegation)
    document.getElementById('artifact-items').addEventListener('click', function(e) {
        const btn = e.target.closest('.link-delete-artifact');
        if (!btn) return;

        const sets = getFieldsets();
        if (sets.length <= 1) return; // không cho xóa nếu chỉ còn 1

        const idx = parseInt(btn.getAttribute('data-index'), 10);
        const fs = document.querySelector(`.artifact-fieldset[data-index="${idx}"]`);
        if (fs) {
            fs.remove();
            reindexArtifacts();
        }
    });

    // Xử lý upload và preview từng hiện vật
    document.getElementById('artifact-items').addEventListener('change', function(e) {
        if (e.target.classList.contains('artifact-img')) {
            const index = parseInt(e.target.getAttribute('data-index'));
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(ev) {
                const img = new Image();
                img.onload = function() {
                    // Resize về kích thước cấu hình
                    const canvas = document.createElement('canvas');
                    canvas.width = artifactImgSize; canvas.height = artifactImgSize;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, artifactImgSize, artifactImgSize);

                    // Preview puzzle border
                    const previewDiv = document.getElementById('artifact-preview-' + index);
                    previewDiv.innerHTML = '';
                    const previewCanvas = document.createElement('canvas');
                    previewCanvas.width = artifactImgSize;
                    previewCanvas.height = artifactImgSize;
                    const pctx = previewCanvas.getContext('2d');
                    pctx.drawImage(img, 0, 0, artifactImgSize, artifactImgSize);

                    // Overlay mask puzzle đường kẻ trắng
                    const puzzleMask = new Image();
                    puzzleMask.src = '<?php echo GAME_BSC_PLUGIN_URL; ?>assets/images/puzzle-border.png';
                    puzzleMask.onload = function() {
                        pctx.drawImage(puzzleMask, 0, 0, artifactImgSize, artifactImgSize);
                    };
                    previewDiv.appendChild(previewCanvas);

                    // Cắt 4 mảnh để upload (ẩn)
                    pieceBlobsArr[index] = [];
                    const baseSize = artifactImgSize / 2;
                    const positions = [
                        [0, 0],      
                        [baseSize, 0],
                        [0, baseSize],
                        [baseSize, baseSize] 
                    ];
                    positions.forEach((pos, i) => {
                        const pieceCanvas = document.createElement('canvas');
                        const ctx2 = pieceCanvas.getContext('2d');
                        const maskImg = new Image();
                        maskImg.crossOrigin = "anonymous";
                        maskImg.src = pieceMasks[i];
                        maskImg.onload = function() {
                            pieceCanvas.width = maskImg.width;
                            pieceCanvas.height = maskImg.height;
                            let offsetX = pos[0];
                            let offsetY = pos[1];
                            
                            const widthDiff = maskImg.width - baseSize; // Chênh lệch chiều rộng
                            const heightDiff = maskImg.height - baseSize; // Chênh lệch chiều cao
                            if (i === 1 || i === 3) { // Mảnh 2 và 4: lồi sang trái (giảm offset X)
                                offsetX -= widthDiff;
                            }
                            if (i === 2 || i === 3) { // Mảnh 3 và 4: lồi lên trên (giảm offset Y)
                                offsetY -= heightDiff;
                            }
                            ctx2.drawImage(canvas, offsetX, offsetY, maskImg.width, maskImg.height, 0, 0, maskImg.width, maskImg.height);
                           
                            ctx2.globalCompositeOperation = 'destination-in';
                            ctx2.drawImage(maskImg, 0, 0, maskImg.width, maskImg.height);
                            ctx2.globalCompositeOperation = 'source-over';
                            pieceCanvas.toBlob(function(blob) {
                                pieceBlobsArr[index][i] = blob;
                            }, 'image/png');
                        };
                    });
                };
                img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Upload mảnh khi submit
    function toSafeFileName(str) {
        return (str || 'artifact')
        .normalize('NFD')                    // tách dấu
        .replace(/[\u0300-\u036f]/g, '')    // remove dấu
        .replace(/[^a-zA-Z0-9]+/g, '-')     // ký tự lạ -> -
        .replace(/^-+|-+$/g, '')            // trim -
        .toLowerCase();
    }

    document.getElementById('artifact-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const sets = getFieldsets();
    const uploadPromises = [];

    for (let idx = 0; idx < sets.length; idx++) {
        if (!pieceBlobsArr[idx] || pieceBlobsArr[idx].length < 4) continue;

        // Lấy "Tên hiện vật" của section idx
        const nameInput = document.querySelector(
        `.artifact-fieldset[data-index="${idx}"] input[name^="artifacts[${idx}][name]"]`
        );
        const artifactNameSafe = toSafeFileName(nameInput ? nameInput.value.trim() : 'artifact');

        for (let i = 0; i < 4; i++) {
        const blob = pieceBlobsArr[idx][i];
        if (!blob) continue;

        const input = document.getElementById(`piece-img-url-${idx}-${i + 1}`);
        const formData = new FormData();
        formData.append('action', 'game_bsc_upload_piece');
        formData.append('piece_code', `P${i + 1}`);

        const filename = `${artifactNameSafe}-P${i + 1}.png`;
        formData.append('piece', blob, filename);

        uploadPromises.push(
            fetch(ajaxurl, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.data || 'Upload thất bại');
                if (input) input.value = data.data.url;
            })
        );
        }
    }

    try {
        await Promise.all(uploadPromises);
        form.submit();
    } catch (err) {
        alert('Có lỗi khi upload mảnh: ' + (err && err.message ? err.message : err));
    }
    });


    // Ẩn nút xóa khi chỉ có 1 hiện vật
    toggleDeleteButtons();

    </script>
<?php
}

// Xử lý upload mảnh (AJAX)
add_action('wp_ajax_game_bsc_upload_piece', function() {
    if (!current_user_can('admin_game') && !current_user_can('administrator')) wp_send_json_error('No permission');
    if (empty($_FILES['piece'])) wp_send_json_error('No file');
    $piece_code = sanitize_text_field($_POST['piece_code'] ?? '');
    $file = $_FILES['piece'];
    $upload = wp_handle_upload($file, ['test_form' => false]);
    if (isset($upload['error'])) wp_send_json_error($upload['error']);
    wp_send_json_success(['url' => $upload['url']]);
});

add_action('admin_post_game_bsc_save_artifact', 'game_bsc_handle_save_artifact');

function game_bsc_handle_save_artifact() {
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        wp_die(__('Bạn không có quyền thực hiện hành động này.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    check_admin_referer('game_bsc_save_artifact');

    global $wpdb, $admin_url;
    $artifacts_table = $wpdb->prefix . 'game_artifacts';
    $pieces_table    = $wpdb->prefix . 'game_pieces';

    $post = wp_unslash($_POST);
    $artifacts = $post['artifacts'] ?? [];

    $wpdb->query('START TRANSACTION');

    try {
        // ✅ Xử lý FILES với cấu trúc lồng nhau
        $files_by_index = [];
        if (!empty($_FILES['artifacts'])) {
            $files = $_FILES['artifacts'];

            // Chuẩn hoá structure của $_FILES thành mảng theo index
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                if (isset($files[$key])) {
                    foreach ($files[$key] as $index => $artifact_files) {
                        if (!isset($files_by_index[$index])) {
                            $files_by_index[$index] = [];
                        }
                        if (is_array($artifact_files)) {
                            // Nếu là mảng (file input), lấy phần tử 'image'
                            $files_by_index[$index][$key] = $artifact_files['image'] ?? null;
                        } else {
                            $files_by_index[$index][$key] = $artifact_files;
                        }
                    }
                }
            }
        }

        foreach ($artifacts as $artifact_index => $artifact) {
            // -------- Artifact ----------
            $id              = isset($artifact['id']) ? (int)$artifact['id'] : 0;
            $name            = sanitize_text_field($artifact['name'] ?? '');
            $max_redemptions = (int)($artifact['max_redemptions'] ?? 0);
            $status          = (int)($artifact['status'] ?? 0);
            $status          = ($status === 1) ? 1 : 0;

            // -------- Thời hạn & Kỳ ----------
            $period_start_raw = sanitize_text_field($artifact['period_start'] ?? '');
            $period_end_raw   = sanitize_text_field($artifact['period_end'] ?? '');
            // Auto-calculate total_periods = max_redemptions (each period gets 1 artifact)
            $total_periods    = max(1, $max_redemptions);
            $max_per_period   = 1; // Always 1 artifact per period

            // Chuyển datetime-local (Y-m-d\TH:i) → MySQL datetime (Y-m-d H:i:s)
            $period_start = !empty($period_start_raw) ? str_replace('T', ' ', $period_start_raw) . ':00' : null;
            $period_end   = !empty($period_end_raw) ? str_replace('T', ' ', $period_end_raw) . ':00' : null;

            // Validate thời hạn
            if ($period_start && $period_end && $period_start > $period_end) {
                throw new Exception(sprintf(
                    __('Ngày bắt đầu phải trước ngày kết thúc cho hiện vật "%s".', WG_GAME_PLUGIN_TEXTDOMAIN),
                    $name
                ));
            }

            // ✅ XỬ LÝ UPLOAD ẢNH HIỆN VẬT từ $_FILES chuẩn hoá
            $artifacts_url = '';
            if (isset($files_by_index[$artifact_index])) {
                $file_data = $files_by_index[$artifact_index];

                // Kiểm tra lỗi upload
                // Xử lý upload ảnh hiện vật (chỉ upload nếu người dùng chọn file)
                if (isset($file_data['error']) && $file_data['error'] === UPLOAD_ERR_OK) {

                    $file = [
                            'name'     => $file_data['name'] ?? '',
                            'type'     => $file_data['type'] ?? '',
                            'tmp_name' => $file_data['tmp_name'] ?? '',
                            'error'    => $file_data['error'] ?? UPLOAD_ERR_NO_FILE,
                            'size'     => $file_data['size'] ?? 0,
                    ];

                    $upload = wp_handle_upload($file, ['test_form' => false]);

                    if (isset($upload['error'])) {
                        throw new Exception(__('Lỗi upload ảnh hiện vật: ' . $upload['error'], WG_GAME_PLUGIN_TEXTDOMAIN));
                    }

                    $artifacts_url = esc_url_raw($upload['url']);

                }
// ✅ Nếu không upload ảnh mới → giữ URL cũ
                elseif ($id > 0) {
                    $old_artifact = $wpdb->get_row(
                            $wpdb->prepare("SELECT artifacts_url FROM $artifacts_table WHERE id = %d", $id)
                    );
                    if ($old_artifact) {
                        $artifacts_url = $old_artifact->artifacts_url;
                    }
                }
            }

            // Nếu là chế độ sửa và không tải ảnh mới, giữ URL cũ
            if (empty($artifacts_url) && $id > 0) {
                // Lấy URL cũ từ database
                $old_artifact = $wpdb->get_row(
                        $wpdb->prepare("SELECT artifacts_url FROM $artifacts_table WHERE id = %d", $id)
                );
                if ($old_artifact) {
                    $artifacts_url = $old_artifact->artifacts_url;
                }
            }

            if ($name === '') {
                throw new Exception(__('Tên hiện vật không được để trống.', WG_GAME_PLUGIN_TEXTDOMAIN));
            }

            if($max_redemptions <= 0) {
                throw new Exception(__('Số lượt đổi tối đa phải lớn hơn 0.', WG_GAME_PLUGIN_TEXTDOMAIN));
            }

            if($total_periods <= 0) {
                throw new Exception(__('Số kỳ tung quà phải lớn hơn 0.', WG_GAME_PLUGIN_TEXTDOMAIN));
            }

            // Validate tổng baseline_weight của 4 mảnh
            $total_weight = 0;
            for ($i = 1; $i <= 4; $i++) {
                $w = (int)($artifact['piece_weight'][$i] ?? 0);
                if ($w < 0) $w = 0;
                if ($w > 100) $w = 100;
                $total_weight += $w;
            }

            if ($total_weight !== 100) {
                throw new Exception(
                        sprintf(
                                __('Tổng tỉ lệ rơi của hiện vật "%s" phải bằng 100%% (hiện tại là %d%%).', WG_GAME_PLUGIN_TEXTDOMAIN),
                                $name,
                                $total_weight
                        )
                );
            }

            // -------- LẤY GIÁTRỊ CŨ ĐỂ LOG --------
            $old_artifact_data = null;
            $old_pieces_data = [];
            if ($id > 0) {
                $old_artifact_data = $wpdb->get_row(
                        $wpdb->prepare("SELECT name, artifacts_url, max_redemptions, status FROM $artifacts_table WHERE id = %d", $id),
                        ARRAY_A
                );
                $old_pieces_data = $wpdb->get_results(
                        $wpdb->prepare("SELECT piece_code, baseline_weight FROM $pieces_table WHERE artifact_id = %d ORDER BY piece_code", $id),
                        ARRAY_A
                );
                // Chuyển thành map
                $pieces_map = [];
                foreach ($old_pieces_data as $p) {
                    $pieces_map[$p['piece_code']] = (int)$p['baseline_weight'];
                }
                $old_pieces_data = $pieces_map;
            }

            // -------- Lưu hiện vật ----------
            if ($id > 0) {
                $ok = $wpdb->update(
                        $artifacts_table,
                        [
                                'name'                       => $name,
                                'max_redemptions'            => $max_redemptions,
                                'status'                     => $status,
                                'artifacts_url'              => $artifacts_url,
                                'period_start'               => $period_start,
                                'period_end'                 => $period_end,
                                'total_periods'              => $total_periods,
                                'max_redemptions_per_period' => $max_per_period,
                        ],
                        ['id' => $id],
                        ['%s','%d','%d','%s','%s','%s','%d','%d'],
                        ['%d']
                );
                if ($ok === false) {
                    throw new Exception($wpdb->last_error ?: __('Không thể cập nhật hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN));
                }
                $artifact_id = $id;
            } else {
                $ok = $wpdb->insert(
                        $artifacts_table,
                        [
                                'name'                       => $name,
                                'max_redemptions'            => $max_redemptions,
                                'status'                     => $status,
                                'artifacts_url'              => $artifacts_url,
                                'period_start'               => $period_start,
                                'period_end'                 => $period_end,
                                'total_periods'              => $total_periods,
                                'max_redemptions_per_period' => $max_per_period,
                        ],
                        ['%s','%d','%d','%s','%s','%s','%d','%d']
                );
                if ($ok === false) {
                    throw new Exception($wpdb->last_error ?: __('Không thể tạo hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN));
                }
                $artifact_id = (int)$wpdb->insert_id;
            }

            if (!$artifact_id) {
                throw new Exception(__('Không lấy được ID hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN));
            }

            // -------- Lưu 4 mảnh ----------
            $new_pieces_data = [];
            for ($i = 1; $i <= 4; $i++) {
                $piece_code = 'P'.$i;
                $weight     = (int)($artifact['piece_weight'][$i] ?? 0);
                if ($weight < 0)   $weight = 0;
                if ($weight > 100) $weight = 100;

                $piece_img  = esc_url_raw($artifact['piece_img_url'][$i] ?? '');
                if ($piece_img === '') {
                    throw new Exception(sprintf(__('Thiếu ảnh cho mảnh %s.', WG_GAME_PLUGIN_TEXTDOMAIN), $piece_code));
                }

                // UPSERT theo UNIQUE (artifact_id, piece_code)
                $sql = $wpdb->prepare(
                        "INSERT INTO $pieces_table (artifact_id, piece_code, baseline_weight, piece_img)
                     VALUES (%d, %s, %d, %s)
                     ON DUPLICATE KEY UPDATE baseline_weight = VALUES(baseline_weight),
                                             piece_img       = VALUES(piece_img)",
                        $artifact_id, $piece_code, $weight, $piece_img
                );
                $ok = $wpdb->query($sql);
                if ($ok === false) {
                    throw new Exception($wpdb->last_error ?: sprintf(__('Không thể lưu mảnh %s.', WG_GAME_PLUGIN_TEXTDOMAIN), $piece_code));
                }

                $new_pieces_data[$piece_code] = $weight;
            }

            // ✅ LOG THAY ĐỔI HIỆN VẬT
            if ($id > 0) {
                // Cập nhật hiện vật - so sánh với dữ liệu cũ
                $old_artifact_info = [
                        'name' => $old_artifact_data['name'],
                        'max_redemptions' => (int)$old_artifact_data['max_redemptions'],
                        'status' => (int)$old_artifact_data['status'],
                        'artifacts_url' => $old_artifact_data['artifacts_url'],
                ];
                $new_artifact_info = [
                        'name' => $name,
                        'max_redemptions' => $max_redemptions,
                        'status' => $status,
                        'artifacts_url' => $artifacts_url,
                ];

                // Log artifact change
                if ($old_artifact_info !== $new_artifact_info || $old_pieces_data !== $new_pieces_data) {
                    game_bsc_log_artifact_change(
                            $artifact_id,
                            'update',
                            [
                                    'artifact' => $old_artifact_info,
                                    'pieces' => $old_pieces_data
                            ],
                            [
                                    'artifact' => $new_artifact_info,
                                    'pieces' => $new_pieces_data
                            ]
                    );
                }
            } else {
                // Tạo hiện vật mới
                game_bsc_log_artifact_change(
                        $artifact_id,
                        'create',
                        [],
                        [
                                'artifact' => [
                                        'name' => $name,
                                        'max_redemptions' => $max_redemptions,
                                        'status' => $status,
                                        'artifacts_url' => $artifacts_url,
                                ],
                                'pieces' => $new_pieces_data
                        ]
                );
            }
        }

        $wpdb->query('COMMIT');
        return game_bsc_redirect_result(__('Thêm hiện vật thành công!', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return game_bsc_redirect_error(__('Lỗi: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
}

// ✅ Helper function để get upload error message
function get_upload_error_message($error_code) {
    $errors = [
            UPLOAD_ERR_OK           => 'OK',
            UPLOAD_ERR_INI_SIZE     => 'File vượt quá upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE    => 'File vượt quá kích thước giới hạn',
            UPLOAD_ERR_PARTIAL      => 'File chỉ upload một phần',
            UPLOAD_ERR_NO_FILE      => 'Không có file được chọn',
            UPLOAD_ERR_NO_TMP_DIR   => 'Thư mục tạm không tồn tại',
            UPLOAD_ERR_CANT_WRITE   => 'Không thể ghi file',
            UPLOAD_ERR_EXTENSION    => 'Extension PHP không cho phép',
    ];
    return $errors[$error_code] ?? 'Lỗi upload không xác định';
}

// Xử lý xóa hiện vật
add_action('admin_post_game_bsc_delete_artifact', 'game_bsc_handle_delete_artifact');

function game_bsc_handle_delete_artifact() {
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        wp_die(__('Bạn không có quyền thực hiện hành động này.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    global $wpdb, $admin_url;

    $artifact_id = isset($_POST['artifact_id']) ? (int) $_POST['artifact_id'] : 0;
    if ($artifact_id <= 0) {
        return game_bsc_redirect_error(__('Thiếu ID hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }

    // Verify nonce
    $nonce_action = 'game_bsc_delete_artifact_' . $artifact_id;
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], $nonce_action)) {
        wp_die(__('Xác thực không hợp lệ.', WG_GAME_PLUGIN_TEXTDOMAIN));
    }

    $artifacts_table = $wpdb->prefix . 'game_artifacts';
    $pieces_table    = $wpdb->prefix . 'game_pieces';

    // Bắt đầu transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Lấy dữ liệu cũ để log
        $artifact = $wpdb->get_row(
                $wpdb->prepare("SELECT name, artifacts_url, max_redemptions, status FROM $artifacts_table WHERE id = %d", $artifact_id),
                ARRAY_A
        );
        $pieces = $wpdb->get_results(
                $wpdb->prepare("SELECT piece_code, baseline_weight FROM $pieces_table WHERE artifact_id = %d", $artifact_id),
                ARRAY_A
        );

        $pieces_map = [];
        foreach ($pieces as $p) {
            $pieces_map[$p['piece_code']] = (int)$p['baseline_weight'];
        }

        // Xoá mảnh (an toàn cả khi có/không có FK CASCADE)
        $ok = $wpdb->delete($pieces_table, ['artifact_id' => $artifact_id], ['%d']);
        if ($ok === false) {
            throw new Exception($wpdb->last_error ?: __('Không thể xoá mảnh ghép.', WG_GAME_PLUGIN_TEXTDOMAIN));
        }

        // Xoá hiện vật
        $ok2 = $wpdb->delete($artifacts_table, ['id' => $artifact_id], ['%d']);
        if ($ok2 === false) {
            throw new Exception($wpdb->last_error ?: __('Không thể xoá hiện vật.', WG_GAME_PLUGIN_TEXTDOMAIN));
        }
        if ($ok2 === 0) {
            throw new Exception(__('Hiện vật không tồn tại hoặc đã bị xoá.', WG_GAME_PLUGIN_TEXTDOMAIN));
        }

        // ✅ LOG XÓA HIỆN VẬT
        game_bsc_log_artifact_change(
                $artifact_id,
                'delete',
                [
                        'artifact' => [
                                'name' => $artifact['name'],
                                'max_redemptions' => (int)$artifact['max_redemptions'],
                                'status' => (int)$artifact['status'],
                                'artifacts_url' => $artifact['artifacts_url'],
                        ],
                        'pieces' => $pieces_map
                ],
                []
        );

        $wpdb->query('COMMIT');
        return game_bsc_redirect_result(__('Đã xoá hiện vật thành công.', WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return game_bsc_redirect_error(__('Xoá thất bại: ' . $e->getMessage(), WG_GAME_PLUGIN_TEXTDOMAIN), $admin_url);
    }
}

// ✅ HÀM LOG THAY ĐỔI HIỆN VẬT
function game_bsc_log_artifact_change($artifact_id, $action, $old_value, $new_value) {
    game_bsc_log_settings_change(
            'game_bsc_artifact_' . $artifact_id,
            $old_value,
            $new_value,
            $action
    );
}

// render hiện vật cần chỉnh sửa
function game_bsc_render_artifact_fieldset_prefilled($index, $artifact, $pieces) {
    // Map pieces theo P1..P4
    $piece_map = ['P1'=>null,'P2'=>null,'P3'=>null,'P4'=>null];
    foreach ($pieces as $p) {
        $piece_map[$p->piece_code] = $p;
    }

    // Format datetime cho input datetime-local (Y-m-d\TH:i)
    $period_start_val = !empty($artifact->period_start) ? date('Y-m-d\TH:i', strtotime($artifact->period_start)) : '';
    $period_end_val   = !empty($artifact->period_end) ? date('Y-m-d\TH:i', strtotime($artifact->period_end)) : '';
    $max_per_period_val = (int)($artifact->max_redemptions_per_period ?? 0);
    ?>
    <fieldset class="artifact-fieldset" data-index="<?php echo (int)$index; ?>" style="border:1px solid #ccc;margin-bottom:16px;padding:10px;position:relative;">
        <legend>Hiện vật #<?php echo (int)$index + 1; ?></legend>
        <div class="artifact-actions" style="position:absolute;top:8px;right:10px;">
            <a class="button" href="<?php echo esc_url( admin_url('admin.php?page=game-bsc-manage-artifacts') ); ?>">Hủy sửa</a>
        </div>
        <table class="form-table">
            <input type="hidden" name="artifacts[<?php echo (int)$index; ?>][id]" value="<?php echo (int)$artifact->id; ?>">
            <tr>
                <th>Tên hiện vật</th>
                <td><input type="text" name="artifacts[<?php echo (int)$index; ?>][name]" value="<?php echo esc_attr($artifact->name); ?>" required></td>
            </tr>
            <tr>
                <th>Số lượt đổi tối đa</th>
                <td>
                    <input type="number" name="artifacts[<?php echo (int)$index; ?>][max_redemptions]" min="1" value="<?php echo (int)$artifact->max_redemptions; ?>">
                    <p class="description">Số kỳ tung quà được hệ thống tự tính theo số lượt đổi tối đa, không cần nhập tay.</p>
                </td>
            </tr>
            <tr>
                <th>Thời hạn hiện vật</th>
                <td>
                    <label>Từ ngày: <input type="datetime-local" name="artifacts[<?php echo (int)$index; ?>][period_start]" value="<?php echo esc_attr($period_start_val); ?>" style="margin-right:12px;"></label>
                    <label>Đến ngày: <input type="datetime-local" name="artifacts[<?php echo (int)$index; ?>][period_end]" value="<?php echo esc_attr($period_end_val); ?>"></label>
                    <p class="description">Để trống nếu không giới hạn thời gian. Hết quota kỳ thì hiện vật sẽ không rơi mảnh trong kỳ đó.</p>
                </td>
            </tr>
            <tr>
                <th>Trạng thái</th>
                <td>
                    <label style="margin-right:12px;">
                        <input type="radio" name="artifacts[<?php echo (int)$index; ?>][status]" value="1" <?php checked( (int)$artifact->status, 1 ); ?>> Mở
                    </label>
                    <label>
                        <input type="radio" name="artifacts[<?php echo (int)$index; ?>][status]" value="0" <?php checked( (int)$artifact->status, 0 ); ?>> Đóng
                    </label>
                </td>
            </tr>
            <tr>
                <th>Ảnh hiện vật</th>
                <td>
                    <!-- Chế độ sửa: KHÔNG required -->
                    <input type="file" class="artifact-img" name="artifacts[<?php echo (int)$index; ?>][image]" data-index="<?php echo (int)$index; ?>" accept="image/*">
                    <div class="artifact-preview" id="artifact-preview-<?php echo (int)$index; ?>" style="margin-top:8px;"></div>
                    <p class="description">Chỉ chọn ảnh nếu muốn tạo lại 4 mảnh mới. Nếu không, giữ nguyên các mảnh hiện có.</p>
                </td>
            </tr>
        </table>
        <h4>Thiết lập tỉ lệ rơi và mảnh hiện có</h4>
        <div class="artifact-pieces" id="artifact-pieces-<?php echo (int)$index; ?>">
            <?php for ($i=1;$i<=4;$i++):
                $code = 'P'.$i;
                $p = $piece_map[$code];
                $weight = $p ? (int)$p->baseline_weight : 0;
                $img = $p ? esc_url($p->piece_img) : '';
            ?>
            <div style="display:inline-block;margin-right:12px;">
                <label>Mảnh <?php echo $code; ?> - Tỉ lệ rơi (%)</label>
                <input type="number" name="artifacts[<?php echo (int)$index; ?>][piece_weight][<?php echo $i; ?>]" min="0" max="100" value="<?php echo $weight; ?>" style="width:60px;" required>
                <input type="hidden" name="artifacts[<?php echo (int)$index; ?>][piece_img_url][<?php echo $i; ?>]" id="piece-img-url-<?php echo (int)$index; ?>-<?php echo $i; ?>" value="<?php echo $img; ?>" required>
                <?php if ($img): ?>
                    <div><img src="<?php echo $img; ?>" style="width:40px;height:40px;border:1px solid #ddd;margin-top:4px;"></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </fieldset>
    <?php
}