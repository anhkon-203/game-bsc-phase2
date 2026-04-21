<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('admin_game') && !current_user_can('administrator')) {
    wp_die(esc_html__('You do not have permission to access this page.', WG_GAME_PLUGIN_TEXTDOMAIN));
}

global $wpdb;
$prefix = $wpdb->prefix . 'game_';
$nonce = wp_create_nonce('game_bsc_gotit_test_nonce');
$ajax_url = admin_url('admin-ajax.php');

$failed_only = isset($_GET['failed_only']) ? absint($_GET['failed_only']) : 0;
$where_clause = $failed_only ? 'WHERE (gotit_voucher_link IS NULL OR gotit_voucher_link = "")' : '';
$gotit_table = $prefix . 'gotit_transactions';
$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $gotit_table)) === $gotit_table;
$transactions = [];
$gotit_table_columns = [];
$plan_required_columns = [
    'gotit_voucher_image',
    'gotit_serial',
    'gotit_expiry_date',
    'gotit_partner_expiry_date',
    'gotit_vendor_name',
    'gotit_is_partner_code',
];
$plan_required_columns_ready = 0;

if ($table_exists) {
    $gotit_table_columns = $wpdb->get_col("SHOW COLUMNS FROM {$gotit_table}", 0);
    if (!is_array($gotit_table_columns)) {
        $gotit_table_columns = [];
    }

    foreach ($plan_required_columns as $column_name) {
        if (in_array($column_name, $gotit_table_columns, true)) {
            $plan_required_columns_ready++;
        }
    }

    $transactions = $wpdb->get_results("SELECT * FROM {$gotit_table} {$where_clause} ORDER BY id DESC LIMIT 10", ARRAY_A);
}

$plan_fields_status = 'missing';
if ($plan_required_columns_ready === count($plan_required_columns) && $plan_required_columns_ready > 0) {
    $plan_fields_status = 'done';
} elseif ($plan_required_columns_ready > 0) {
    $plan_fields_status = 'partial';
}

$third_party_vouchers = get_posts([
    'post_type' => 'game_vouchers',
    'post_status' => 'publish',
    'posts_per_page' => 50,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => [
        [
            'key' => 'voucher_type',
            'value' => 'THIRD_PARTY',
            'compare' => '=',
        ],
    ],
]);

$webhook_url = rest_url(NS . '/webhook/gotit');
$issue_voucher_rest_url = rest_url(NS . '/vouchers/issue');
$rest_nonce = wp_create_nonce('wp_game_rest');
$gotit_api_key = get_option('game_bsc_gotit_api_key', '');
$settings_url = admin_url('admin.php?page=game-bsc-settings#tab-api-url');

$progress_items = [
    ['name' => 'Server-side API call only', 'status' => 'done'],
    ['name' => 'Admin test page with detailed actions', 'status' => 'done'],
    ['name' => 'Webhook signature mode confirmed (RSA/AES)', 'status' => 'partial'],
    ['name' => 'Master data cron sync', 'status' => 'done'],
    ['name' => 'Plan-required extra fields (image/serial/expiry/vendor)', 'status' => $plan_fields_status],
];

function game_bsc_progress_badge($status) {
    if ($status === 'done') {
        return '<span style="background:#dcfce7;color:#166534;padding:3px 8px;border-radius:999px;font-size:12px;">DONE</span>';
    }
    if ($status === 'partial') {
        return '<span style="background:#fef3c7;color:#92400e;padding:3px 8px;border-radius:999px;font-size:12px;">PARTIAL</span>';
    }
    return '<span style="background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:999px;font-size:12px;">MISSING</span>';
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">

<main class="flex flex-col gap-6 py-8">
    <div class="card-top">
        <div class="breadcrumb flex flex-col gap-3">
            <nav class="flex gap-1">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'dashboard'])); ?>" class="text-sm font-regular text-[#6A7A95]">Dashboard</a>
                <span class="text-sm font-regular text-[#6A7A95]">/</span>
                <span class="text-sm font-regular text-[#6A7A95]">Got It Test</span>
            </nav>
            <h2 class="text-lg font-medium text-[#31333F]">Got It Progress & Detailed Test</h2>
        </div>
        <div class="desc text-sm font-regular text-[#6A7A95] mt-2">
            File control: <code>.skill/GOTIT_PROGRESS.md</code>
        </div>
    </div>

    <div class="container mt-2">
        <div class="flex gap-2">
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'dashboard'])); ?>" class="tab-item-nav">Dashboard</a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'voucher-list'])); ?>" class="tab-item-nav">Danh sach qua da doi</a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'gotit-test'])); ?>" class="tab-item-nav active">Got It Test</a>
        </div>
    </div>

    <div class="container">
        <?php if (empty($gotit_api_key)): ?>
            <div class="notice notice-warning" style="margin-bottom:12px;padding:10px 12px;">
                Got It API key is not configured. Vui long vao
                <a href="<?php echo esc_url($settings_url); ?>"><strong>Game BSC > Cai dat > tab Got It API</strong></a>
                de nhap API key.
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
            <h3 class="text-[18px] font-medium text-[#31333F] mb-4">1) Progress Tracker</h3>
            <table class="widefat striped">
                <thead><tr><th>Item</th><th style="width:160px;">Status</th></tr></thead>
                <tbody>
                <?php foreach ($progress_items as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['name']); ?></td>
                        <td><?php echo game_bsc_progress_badge($item['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <section class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
                <h3 class="text-[18px] font-medium text-[#31333F] mb-3">2) Connection Test</h3>
                <button id="btn-ping" class="button button-primary">Ping Got It API</button>
                <button id="btn-sync-vouchers" class="button button-secondary" style="margin-left:8px;">Sync THIRD_PARTY vouchers</button>
                <button id="btn-debug-raw" class="button" style="margin-left:8px;background:#f0f0f0;">🔍 Debug Raw API</button>
                <div id="sync-vouchers-status" class="mt-3 text-xs text-[#6A7A95]" style="display:none;"></div>
                <p class="mt-3 text-sm text-[#6A7A95]">Test endpoint: <code>/biz/v5.0/client/categories</code></p>
                <p class="mt-2 text-xs text-[#6A7A95]">Sau khi sync, website sẽ tự tạo/cập nhật voucher THIRD_PARTY theo Product + Mệnh giá, admin không cần chọn tay Product ID / Product Price ID.</p>
                <p class="mt-1 text-xs text-[#6A7A95]"><strong>Quy tắc:</strong> 1 sản phẩm có N mệnh giá thì hệ thống sẽ có N voucher tương ứng (mỗi mệnh giá là 1 voucher riêng).</p>
            </section>

            <section class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
                <h3 class="text-[18px] font-medium text-[#31333F] mb-3">3) Issue Voucher Test</h3>

                <div class="mb-3">
                    <label class="mr-3"><input type="radio" name="issue_mode" value="manual" checked> Manual</label>
                    <label><input type="radio" name="issue_mode" value="voucher"> Use THIRD_PARTY voucher post</label>
                </div>

                <div class="mb-3 text-xs text-[#6A7A95]">
                    <strong>Giải thích:</strong> <br>
                    <b>Product ID</b> là mã sản phẩm voucher Got It (ví dụ: thẻ điện thoại, thẻ mua sắm...). <br>
                    <b>Product Price ID</b> là mã mệnh giá của sản phẩm (ví dụ: 50k, 100k...). <br>
                    Để lấy các mã này, gọi API <code>GET /api/v5.0/products</code>.<br>
                    Khi chọn từ danh sách voucher đã cấu hình, hệ thống sẽ tự lấy các trường này từ ACF hoặc dữ liệu đã đồng bộ.
                </div>

                <div id="manual-fields" class="space-y-2 mb-3">
                    <div class="flex gap-2 mb-2">
                        <button type="button" id="btn-fetch-products" class="button button-secondary">Lấy danh sách sản phẩm</button>
                        <span id="product-fetch-status" class="text-xs text-[#6A7A95]"></span>
                    </div>
                    <select id="select_product" class="w-full mb-2" style="display:none;"></select>
                    <select id="select_price" class="w-full mb-2" style="display:none;"></select>
                    <input type="number" id="product_id" class="w-full" placeholder="Product ID">
                    <input type="number" id="product_price_id" class="w-full" placeholder="Product Price ID">
                </div>

                <div id="voucher-fields" class="space-y-2 mb-3" style="display:none;">
                    <select id="voucher_post_id" class="w-full">
                        <option value="">-- Select THIRD_PARTY voucher --</option>
                        <?php foreach ($third_party_vouchers as $voucher): ?>
                            <option value="<?php echo (int) $voucher->ID; ?>">#<?php echo (int) $voucher->ID; ?> - <?php echo esc_html($voucher->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-2 mb-3">
                    <input type="text" id="order_name" class="w-full" value="BSC Game Voucher Test" placeholder="Order Name">
                    <input type="number" id="expiry_days" class="w-full" value="30" min="1" max="365" placeholder="Expiry days">
                    <input type="number" id="user_id" class="w-full" value="0" min="0" placeholder="User ID (optional)">
                </div>

                <button id="btn-issue" class="button button-primary">Issue Test Voucher</button>

                <div id="issue-summary" class="mt-4" style="display:none;">
                    <div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#fafafa;">
                        <div style="font-weight:600;margin-bottom:8px;">Issue Summary (theo plan)</div>
                        <div id="issue-summary-content" class="text-xs text-[#6A7A95]"></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <section class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
                <h3 class="text-[18px] font-medium text-[#31333F] mb-3">4) Check Status by transactionRefId</h3>
                <input type="text" id="transaction_ref_id" class="w-full mb-3" placeholder="000578_...">
                <button id="btn-status" class="button button-primary">Check Status</button>

                <div id="status-summary" class="mt-4" style="display:none;">
                    <div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#fafafa;">
                        <div style="font-weight:600;margin-bottom:8px;">Status Summary by Ref ID</div>
                        <div id="status-summary-content" class="text-xs text-[#6A7A95]"></div>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
                <h3 class="text-[18px] font-medium text-[#31333F] mb-3">6) Webhook Info</h3>
                <p class="text-sm mb-2">Register this URL with Got It:</p>
                <code style="display:block;padding:8px;background:#f5f5f5;border-radius:6px;"><?php echo esc_html($webhook_url); ?></code>
                <p class="text-xs text-[#6A7A95] mt-2">Note: finalize signature standard with Got It (RSA/AES) before production.</p>
            </section>
        </div>
    </div>

    <div class="container">
        <section class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
            <h3 class="text-[18px] font-medium text-[#31333F] mb-3">7) Test REST API vouchers/issue</h3>

            <div class="space-y-2 mb-3">
                <select id="issue_voucher_post_id" class="w-full">
                    <option value="">-- Chọn voucher THIRD_PARTY để test --</option>
                    <?php foreach ($third_party_vouchers as $voucher): ?>
                        <option value="<?php echo (int) $voucher->ID; ?>">#<?php echo (int) $voucher->ID; ?> - <?php echo esc_html($voucher->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" id="issue_voucher_id" class="w-full" min="1" placeholder="Voucher ID (hoặc chọn ở dropdown bên trên)">
            </div>

            <button id="btn-issue-rest" class="button button-primary">Call API vouchers/issue</button>
            <p class="mt-3 text-xs text-[#6A7A95]">Endpoint: <code><?php echo esc_html($issue_voucher_rest_url); ?></code></p>
            <p class="mt-1 text-xs text-[#6A7A95]">Lưu ý: API này cần session SSO hợp lệ (không chỉ đăng nhập WP Admin).</p>

            <div id="issue-rest-summary" class="mt-4" style="display:none;">
                <div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#fafafa;">
                    <div style="font-weight:600;margin-bottom:8px;">Issue via REST Summary</div>
                    <div id="issue-rest-summary-content" class="text-xs text-[#6A7A95]"></div>
                </div>
            </div>
        </section>
    </div>

    <div class="container">
        <div class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[18px] font-medium text-[#31333F]">5) Recent gotit_transactions (latest 10)</h3>
                <div class="flex gap-2">
                    <a class="button <?php echo $failed_only ? '' : 'button-primary'; ?>" href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'gotit-test', 'failed_only' => 0])); ?>">All</a>
                    <a class="button <?php echo $failed_only ? 'button-primary' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'gotit-test', 'failed_only' => 1])); ?>">Failed only</a>
                </div>
            </div>

            <?php if (!$table_exists): ?>
                <div class="notice notice-warning" style="margin: 0 0 12px; padding: 8px 12px;">
                    Table <code><?php echo esc_html($gotit_table); ?></code> is not created yet. Open any admin page once to trigger DB update, then reload this page.
                </div>
            <?php endif; ?>

            <div style="overflow:auto;">
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ref ID</th>
                        <th>Voucher Code</th>
                        <th>User</th>
                        <th>Voucher Post</th>
                        <th>Status</th>
                        <th>Voucher Link</th>
                        <th>Image</th>
                        <th>Serial</th>
                        <th>Expiry</th>
                        <th>Vendor</th>
                        <th>Partner</th>
                        <th>Error</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="15">No records</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><?php echo (int) $txn['id']; ?></td>
                                <td><code><?php echo esc_html($txn['transaction_ref_id']); ?></code></td>
                                <td><code><?php echo esc_html((string) ($txn['gotit_voucher_code'] ?? '')); ?></code></td>
                                <td><?php echo (int) $txn['user_id']; ?></td>
                                <td><?php echo (int) $txn['voucher_post_id']; ?></td>
                                <td><?php echo (int) $txn['gotit_status']; ?></td>
                                <td>
                                    <?php if (!empty($txn['gotit_voucher_link'])): ?>
                                        <a href="<?php echo esc_url($txn['gotit_voucher_link']); ?>" target="_blank">Open</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($txn['gotit_voucher_image'])): ?>
                                        <a href="<?php echo esc_url($txn['gotit_voucher_image']); ?>" target="_blank">Open</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html((string) ($txn['gotit_serial'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($txn['gotit_expiry_date'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($txn['gotit_vendor_name'] ?? '')); ?></td>
                                <td><?php echo !empty($txn['gotit_is_partner_code']) ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html((string) $txn['gotit_error_message']); ?></td>
                                <td><?php echo esc_html((string) $txn['created_at']); ?></td>
                                <td>
                                    <?php if (empty($txn['gotit_voucher_link'])): ?>
                                        <button class="button button-small btn-retry" data-id="<?php echo (int) $txn['id']; ?>">Retry</button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="bg-white rounded-[12px] p-6 shadow-sm border border-[#EAECF0]">
            <h3 class="text-[18px] font-medium text-[#31333F] mb-3">Debug Output (raw JSON)</h3>
            <pre id="debug-output" style="background:#0b1021;color:#d8e1ff;padding:16px;border-radius:8px;white-space:pre-wrap;min-height:180px;">No action yet.</pre>
        </div>
    </div>
</main>

<script>
(function() {
    const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
    const nonce = <?php echo wp_json_encode($nonce); ?>;
    const restNonce = <?php echo wp_json_encode($rest_nonce); ?>;
    const issueRestUrl = <?php echo wp_json_encode($issue_voucher_rest_url); ?>;
    const debugOutput = document.getElementById('debug-output');
    const issueSummary = document.getElementById('issue-summary');
    const issueSummaryContent = document.getElementById('issue-summary-content');
    const issueRestSummary = document.getElementById('issue-rest-summary');
    const issueRestSummaryContent = document.getElementById('issue-rest-summary-content');
    const statusSummary = document.getElementById('status-summary');
    const statusSummaryContent = document.getElementById('status-summary-content');
    const syncVouchersStatus = document.getElementById('sync-vouchers-status');
    let syncPollTimer = null;

    // --- Product/Price select logic ---
    let productsCache = null;
    const btnFetchProducts = document.getElementById('btn-fetch-products');
    const selectProduct = document.getElementById('select_product');
    const selectPrice = document.getElementById('select_price');
    const productIdInput = document.getElementById('product_id');
    const productPriceIdInput = document.getElementById('product_price_id');
    const productFetchStatus = document.getElementById('product-fetch-status');

    btnFetchProducts.addEventListener('click', async function() {
        productFetchStatus.textContent = 'Đang tải...';
        selectProduct.style.display = 'none';
        selectPrice.style.display = 'none';
        try {
            const currentProductId = (productIdInput.value || '').trim();
            const params = new URLSearchParams();
            params.set('action', 'game_bsc_gotit_get_products');
            params.set('nonce', nonce);
            if (currentProductId !== '') {
                params.set('ids', currentProductId);
                params.set('storeListPage', '1');
                params.set('storeListPageSize', '10');
                params.set('isExcludeStoreListInfo', 'false');
            }

            const resp = await fetch(ajaxUrl + '?' + params.toString(), { method: 'POST' });
            const data = await resp.json();
            if (!data.success || !data.data || !Array.isArray(data.data.products)) {
                if (data && data.data && data.data.message) {
                    productFetchStatus.textContent = data.data.message + (data.data.hint ? (' - ' + data.data.hint) : '');
                } else {
                    productFetchStatus.textContent = 'Không lấy được danh sách sản phẩm.';
                }
                return;
            }
            productsCache = data.data.products;
            // Populate product select
            selectProduct.innerHTML = '<option value="">-- Chọn sản phẩm --</option>' + productsCache.map(p => `<option value="${p.productId}">${p.productId} - ${p.productName}</option>`).join('');
            selectProduct.style.display = '';
            selectPrice.style.display = 'none';
            productFetchStatus.textContent = 'Đã tải sản phẩm.';
        } catch (e) {
            productFetchStatus.textContent = 'Lỗi khi tải sản phẩm.';
        }
    });

    selectProduct.addEventListener('change', function() {
        const pid = this.value;
        if (!pid || !productsCache) {
            selectPrice.style.display = 'none';
            return;
        }
        const prod = productsCache.find(p => String(p.productId) === String(pid));
        if (!prod || !Array.isArray(prod.prices)) {
            selectPrice.style.display = 'none';
            return;
        }
        selectPrice.innerHTML = '<option value="">-- Chọn mệnh giá --</option>' + prod.prices.map(price => `<option value="${price.productPriceId}">${price.label}</option>`).join('');
        selectPrice.style.display = '';
        // Gán Product ID vào input
        productIdInput.value = prod.productId;
        productPriceIdInput.value = '';
    });

    selectPrice.addEventListener('change', function() {
        productPriceIdInput.value = this.value;
    });

    function writeDebug(payload) {
        debugOutput.textContent = JSON.stringify(payload, null, 2);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderIssueSummary(response) {
        if (!issueSummary || !issueSummaryContent) {
            return;
        }

        const payload = response && response.data ? response.data : null;
        const issueData = payload && payload.issue_data ? payload.issue_data : null;
        if (!response || !response.success || !issueData) {
            issueSummary.style.display = 'none';
            issueSummaryContent.innerHTML = '';
            return;
        }

        const transactionRefId = payload.transaction_ref_id || '';
        const voucherCode = issueData.voucher_code || '';
        const voucherLink = issueData.voucher_link || '';
        const voucherImage = issueData.voucher_image || '';
        const voucherSerial = issueData.voucher_serial || '';
        const expiryDate = issueData.expiry_date || '';
        const vendorName = issueData.vendor_name || '';
        const isPartnerCode = issueData.is_partner_code ? 'Yes' : 'No';

        let html = '';
        html += '<div><b>transactionRefId:</b> <code>' + escapeHtml(transactionRefId) + '</code></div>';
        html += '<div><b>voucherCode:</b> ' + escapeHtml(voucherCode || '-') + '</div>';
        html += '<div><b>voucherLink:</b> ' + (voucherLink ? '<a href="' + escapeHtml(voucherLink) + '" target="_blank" rel="noopener">' + escapeHtml(voucherLink) + '</a>' : '-') + '</div>';
        html += '<div><b>image:</b> ' + (voucherImage ? '<a href="' + escapeHtml(voucherImage) + '" target="_blank" rel="noopener">' + escapeHtml(voucherImage) + '</a>' : '-') + '</div>';
        html += '<div><b>serial:</b> ' + escapeHtml(voucherSerial || '-') + '</div>';
        html += '<div><b>expiryDate:</b> ' + escapeHtml(expiryDate || '-') + '</div>';
        html += '<div><b>vendorName:</b> ' + escapeHtml(vendorName || '-') + '</div>';
        html += '<div><b>isPartnerCode:</b> ' + escapeHtml(isPartnerCode) + '</div>';

        issueSummaryContent.innerHTML = html;
        issueSummary.style.display = '';
    }

    function renderIssueRestSummary(result) {
        if (!issueRestSummary || !issueRestSummaryContent) {
            return;
        }

        if (!result) {
            issueRestSummary.style.display = 'none';
            issueRestSummaryContent.innerHTML = '';
            return;
        }

        const payload = result.body && typeof result.body === 'object' ? result.body : null;
        const resCode = payload && typeof payload.resCode !== 'undefined' ? payload.resCode : '-';
        const message = payload && payload.message ? payload.message : 'No message';
        const data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
        const item = data.item && typeof data.item === 'object' ? data.item : {};
        const txn = data.transaction && typeof data.transaction === 'object' ? data.transaction : {};

        let html = '';
        html += '<div><b>HTTP status:</b> ' + escapeHtml(result.http_status ?? '-') + '</div>';
        html += '<div><b>resCode:</b> ' + escapeHtml(resCode) + '</div>';
        html += '<div><b>message:</b> ' + escapeHtml(message) + '</div>';

        if (Object.keys(item).length > 0) {
            html += '<div style="margin-top:8px;"><b>Item:</b> ID=' + escapeHtml(item.id ?? '-')
                + ', code=' + escapeHtml(item.code ?? '-')
                + ', link=' + (item.link ? '<a href="' + escapeHtml(item.link) + '" target="_blank" rel="noopener">Open</a>' : '-')
                + ', points=' + escapeHtml(item.points_cost ?? '-')
                + '</div>';
        }

        if (Object.keys(txn).length > 0) {
            html += '<div style="margin-top:4px;"><b>Transaction:</b> redemption_id=' + escapeHtml(txn.redemption_id ?? '-')
                + ', redeemed_at=' + escapeHtml(txn.redeemed_at ?? '-')
                + ', points_remaining=' + escapeHtml(txn.points_remaining ?? '-')
                + '</div>';
        }

        if (result.parse_error) {
            html += '<div style="margin-top:8px;color:#b42318;"><b>Parse error:</b> Response không phải JSON hợp lệ.</div>';
        }

        issueRestSummaryContent.innerHTML = html;
        issueRestSummary.style.display = '';
    }

    function renderStatusSummary(response) {
        if (!statusSummary || !statusSummaryContent) {
            return;
        }

        const payload = response && response.data ? response.data : null;
        if (!response || !response.success || !payload) {
            statusSummary.style.display = 'none';
            statusSummaryContent.innerHTML = '';
            return;
        }

        const transactionRefId = payload.transaction_ref_id || '';
        const apiResult = payload.api_result || {};
        const refResult = payload.ref_vouchers_result || {};
        const summary = payload.ref_vouchers_summary || {};
        const firstUsed = summary.first_used_info || null;
        const states = Array.isArray(summary.states) ? summary.states : [];
        const vouchers = Array.isArray(payload.ref_vouchers) ? payload.ref_vouchers : [];
        const pagination = payload.ref_vouchers_pagination || {};

        let html = '';
        html += '<div><b>transactionRefId:</b> <code>' + escapeHtml(transactionRefId) + '</code></div>';
        html += '<div><b>Check status API:</b> statusCode=' + escapeHtml(apiResult.status_code || '-') + ', http=' + escapeHtml(apiResult.http_code || '-') + '</div>';
        html += '<div><b>Ref vouchers API:</b> statusCode=' + escapeHtml(refResult.status_code || '-') + ', http=' + escapeHtml(refResult.http_code || '-') + '</div>';
        html += '<div><b>Tổng voucher:</b> ' + escapeHtml(summary.total || 0) + ' | <b>Đã dùng:</b> ' + escapeHtml(summary.used || 0) + ' | <b>Chưa dùng:</b> ' + escapeHtml(summary.unused || 0) + '</div>';

        if (pagination && pagination.totalPage) {
            html += '<div><b>Phân trang:</b> page ' + escapeHtml(pagination.page || 1) + '/' + escapeHtml(pagination.totalPage || 1) + ', pageSize=' + escapeHtml(pagination.pageSize || vouchers.length) + '</div>';
        }

        if (firstUsed) {
            html += '<div style="margin-top:8px;"><b>Used info (mẫu):</b> '
                + 'Store=' + escapeHtml(firstUsed.store || '-')
                + ' | Time=' + escapeHtml(firstUsed.time || '-')
                + ' | Brand=' + escapeHtml(firstUsed.brand_name || '-')
                + ' | Method=' + escapeHtml(firstUsed.method || '-')
                + '</div>';
        }

        if (states.length > 0) {
            html += '<div style="margin-top:8px;"><b>Trạng thái:</b> ';
            html += states.map(function(state) {
                const label = '[' + (state.code || '-') + '] ' + (state.status || '-') + ': ' + (state.count || 0);
                return '<span style="display:inline-block;margin-right:6px;padding:2px 6px;border-radius:999px;background:#eef2ff;color:#3730a3;">' + escapeHtml(label) + '</span>';
            }).join('');
            html += '</div>';
        }

        if (vouchers.length > 0) {
            const rows = vouchers.slice(0, 20).map(function(voucher, index) {
                const stateInfo = voucher && voucher.stateInfo ? voucher.stateInfo : (voucher && voucher.state_info ? voucher.state_info : {});
                const usedInfo = voucher && voucher.usedInfo ? voucher.usedInfo : (voucher && voucher.used_info ? voucher.used_info : {});
                const link = voucher && voucher.link ? String(voucher.link) : '';
                const code = voucher && voucher.code ? String(voucher.code) : '-';
                const serial = voucher && voucher.serial ? String(voucher.serial) : '-';
                const stateText = '[' + (stateInfo.code || voucher.state || '-') + '] ' + (stateInfo.status || stateInfo.name || '-');
                const usedTime = usedInfo && usedInfo.time ? String(usedInfo.time) : '-';
                const usedStore = usedInfo && (usedInfo.store || usedInfo.storeName) ? String(usedInfo.store || usedInfo.storeName) : '-';

                return '<tr>'
                    + '<td>' + escapeHtml(index + 1) + '</td>'
                    + '<td><code>' + escapeHtml(code) + '</code></td>'
                    + '<td><code>' + escapeHtml(serial) + '</code></td>'
                    + '<td>' + (link ? '<a target="_blank" rel="noopener" href="' + escapeHtml(link) + '">Open</a>' : '-') + '</td>'
                    + '<td>' + escapeHtml(stateText) + '</td>'
                    + '<td>' + escapeHtml(usedTime) + '</td>'
                    + '<td>' + escapeHtml(usedStore) + '</td>'
                    + '</tr>';
            }).join('');

            html += '<div style="margin-top:10px;overflow:auto;">'
                + '<table class="widefat striped">'
                + '<thead><tr><th>#</th><th>Code</th><th>Serial</th><th>Link</th><th>State</th><th>Used time</th><th>Store</th></tr></thead>'
                + '<tbody>' + rows + '</tbody>'
                + '</table>'
                + '</div>';

            if (vouchers.length > 20) {
                html += '<div class="mt-1">Hiển thị 20/' + escapeHtml(vouchers.length) + ' voucher đầu tiên.</div>';
            }
        }

        statusSummaryContent.innerHTML = html;
        statusSummary.style.display = '';
    }

    function renderSyncVouchersStatus(state) {
        if (!syncVouchersStatus) {
            return;
        }

        if (!state || !state.status) {
            syncVouchersStatus.style.display = 'none';
            syncVouchersStatus.innerHTML = '';
            return;
        }

        let color = '#6A7A95';
        if (state.status === 'done') {
            color = '#166534';
        } else if (state.status === 'error') {
            color = '#b42318';
        } else if (state.status === 'running') {
            color = '#1d4ed8';
        }

        let html = '';
        html += '<div><b>Sync status:</b> ' + escapeHtml(state.status) + '</div>';
        if (state.message) {
            html += '<div>' + escapeHtml(state.message) + '</div>';
        }
        if (state.status === 'done') {
            html += '<div>Tạo mới: ' + escapeHtml(state.created || 0)
                + ', Cập nhật: ' + escapeHtml(state.updated || 0)
                + ', Bỏ qua: ' + escapeHtml(state.skipped || 0)
                + ', Tổng sản phẩm: ' + escapeHtml(state.products_count || 0)
                + ', Detail API calls: ' + escapeHtml(state.detail_calls || 0)
                + '</div>';
        }
        if (state.last_error) {
            html += '<div>Lỗi: ' + escapeHtml(state.last_error) + '</div>';
        }

        syncVouchersStatus.style.display = '';
        syncVouchersStatus.style.color = color;
        syncVouchersStatus.innerHTML = html;
    }

    function stopSyncPoll() {
        if (syncPollTimer) {
            clearInterval(syncPollTimer);
            syncPollTimer = null;
        }
    }

    async function pollSyncStatus() {
        const statusRes = await callAction('game_bsc_gotit_sync_vouchers_async_status', {}, false);
        const state = statusRes && statusRes.success && statusRes.data ? statusRes.data.status : null;
        renderSyncVouchersStatus(state);

        if (state && (state.status === 'done' || state.status === 'error')) {
            stopSyncPoll();
        }
    }

    async function callAction(action, payload = {}, shouldWriteDebug = true) {
        const body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', nonce);
        Object.keys(payload).forEach((key) => body.append(key, payload[key]));

        const response = await fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        });

        const data = await response.json();
        if (shouldWriteDebug) {
            writeDebug(data);
        }
        return data;
    }

    document.querySelectorAll('input[name="issue_mode"]').forEach((node) => {
        node.addEventListener('change', function() {
            const isManual = this.value === 'manual';
            document.getElementById('manual-fields').style.display = isManual ? '' : 'none';
            document.getElementById('voucher-fields').style.display = isManual ? 'none' : '';
        });
    });

    document.getElementById('btn-ping').addEventListener('click', function() {
        callAction('game_bsc_gotit_ping');
    });

    document.getElementById('btn-sync-vouchers').addEventListener('click', async function() {
        const startRes = await callAction('game_bsc_gotit_sync_vouchers_async_start', {
            gotit_category_id: 0
        });

        const state = startRes && startRes.success && startRes.data ? startRes.data.status : null;
        renderSyncVouchersStatus(state);

        if (startRes && startRes.success) {
            stopSyncPoll();
            await pollSyncStatus();
            syncPollTimer = setInterval(function() {
                pollSyncStatus();
            }, 3000);
        }
    });

    document.getElementById('btn-debug-raw').addEventListener('click', function() {
        debugOutput.textContent = 'Đang lấy raw data từ Got It API...';
        callAction('game_bsc_gotit_debug_raw');
    });

    document.getElementById('btn-issue').addEventListener('click', async function() {
        const mode = document.querySelector('input[name="issue_mode"]:checked').value;
        const payload = {
            mode: mode,
            order_name: document.getElementById('order_name').value,
            expiry_days: document.getElementById('expiry_days').value,
            user_id: document.getElementById('user_id').value
        };

        if (mode === 'manual') {
            payload.product_id = document.getElementById('product_id').value;
            payload.product_price_id = document.getElementById('product_price_id').value;
        } else {
            payload.voucher_post_id = document.getElementById('voucher_post_id').value;
        }

        const result = await callAction('game_bsc_gotit_test_issue', payload);
        renderIssueSummary(result);
    });

    document.getElementById('btn-status').addEventListener('click', async function() {
        const result = await callAction('game_bsc_gotit_test_status', {
            transaction_ref_id: document.getElementById('transaction_ref_id').value
        });
        renderStatusSummary(result);
    });

    const issueVoucherSelect = document.getElementById('issue_voucher_post_id');
    const issueVoucherInput = document.getElementById('issue_voucher_id');
    const issueRestButton = document.getElementById('btn-issue-rest');

    if (issueVoucherSelect && issueVoucherInput) {
        issueVoucherSelect.addEventListener('change', function() {
            issueVoucherInput.value = this.value || '';
        });
    }

    if (issueRestButton) {
        issueRestButton.addEventListener('click', async function() {
            const voucherId = parseInt((issueVoucherInput && issueVoucherInput.value ? issueVoucherInput.value : ''), 10);

            if (!voucherId || voucherId < 1) {
                renderIssueRestSummary({
                    http_status: '-',
                    body: {
                        resCode: 400,
                        message: 'Voucher ID không hợp lệ.',
                        data: {}
                    }
                });
                return;
            }

            let result;
            try {
                const response = await fetch(issueRestUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce,
                    },
                    body: JSON.stringify({
                        voucher_id: voucherId,
                        _wpnonce: restNonce,
                    }),
                });

                const rawText = await response.text();
                let parsed;
                let parseError = false;

                try {
                    parsed = JSON.parse(rawText);
                } catch (e) {
                    parseError = true;
                    parsed = {
                        message: 'Response is not valid JSON.',
                        raw: rawText,
                    };
                }

                result = {
                    http_status: response.status,
                    body: parsed,
                    raw: rawText,
                    parse_error: parseError,
                };
            } catch (e) {
                result = {
                    http_status: 0,
                    body: {
                        resCode: 500,
                        message: e && e.message ? e.message : 'Network error',
                        data: {},
                    },
                };
            }

            writeDebug({
                action: 'rest_vouchers_issue',
                request: {
                    url: issueRestUrl,
                    method: 'POST',
                    payload: {
                        voucher_id: voucherId,
                    },
                },
                response: result,
            });

            renderIssueRestSummary(result);
        });
    }

    document.querySelectorAll('.btn-retry').forEach((button) => {
        button.addEventListener('click', function() {
            callAction('game_bsc_gotit_retry_txn', {
                transaction_id: this.getAttribute('data-id')
            });
        });
    });
})();
</script>
