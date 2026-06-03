<?php
$timezone  = TIMEZONE; // GMT+7
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');




?>
<script src="https://cdn.tailwindcss.com"></script>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<!-- Highcharts -->
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/no-data-to-display.js"></script>

<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">


<main class="flex flex-col gap-8 py-8 ">
	<!-- card top -->
	<div class="card-top">
		<div class="breadcrumb flex flex-col gap-3">
			<nav class="flex gap-1">
				<a href="#" class="text-sm font-regular text-[#6A7A95]">Game BSC</a>
				<span class="text-sm font-regular text-[#6A7A95]">/</span>
				<span class="text-sm font-regular text-[#6A7A95]">Danh sách quà đã đổi</span>
			</nav>
			<h2 class="text-lg font-medium text-[#31333F]">Danh sách quà đã đổi</h2>
		</div>
		<div class="desc text-sm font-regular text-[#6A7A95] mt-2">
			Cập nhật lần cuối: <?php echo esc_html($current_date); ?> - <?php echo esc_html($current_time); ?>
		</div>
	</div>
	
	
	<div class="container mt-6">
		<div class="flex gap-2">
			<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'dashboard'])); ?>"
			   class="tab-item-nav ">
				Dashboard
			</a>
			<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-management'])); ?>"
			   class="tab-item-nav">
				Quản lý user
			</a>
			<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-history'])); ?>"
			   class="tab-item-nav">
				Lịch sử chơi
			</a>
			<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'play-credit-history'])); ?>"
			   class="tab-item-nav">
				Biến động lịch sử lượt chơi
			</a>
			<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'system-log'])); ?>"
			   class="tab-item-nav">
				Nhật ký hệ thống
			</a>
			<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'voucher-list'])); ?>"
			   class="tab-item-nav active">
				Danh sách quà đã đổi
			</a>
		</div>
	</div>
	
	
	<style>
		.list-user .wrapper-table {
			border-radius: 8px;
			border: 1px solid var(--Gray-200, #EAECF0);
			background: var(--Base-White, #FFF);
			
			/* Shadow/sm */
			box-shadow: 0 1px 3px 0 rgba(16, 24, 40, 0.10), 0 1px 2px 0 rgba(16, 24, 40, 0.06);
		}
		.table-general th, .table-general td {
			white-space: nowrap;
		}
	</style>
	
	<?php
	// Lấy tham số filter
	$paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
	$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
	$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
	$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
	$gift_type = isset($_GET['gift_type']) ? sanitize_text_field($_GET['gift_type']) : 'all';
	$voucher_type = isset($_GET['voucher_type']) ? sanitize_text_field($_GET['voucher_type']) : 'all';

	// Gọi hàm lấy dữ liệu
	$result = game_bsc_get_voucher_redemptions_data($paged, 20, $date_from, $date_to, $search, $gift_type, $voucher_type);

	$redemptions = [];
	$total_items = 0;
	$total_pages = 0;
	
	if ($result['status'] === 'success') {
		$redemptions = $result['data'];
		$total_items = $result['pagination']['total_items'];
		$total_pages = $result['pagination']['total_pages'];
		$current_page = $result['pagination']['current_page'];
	}

	$export_redemptions = [];
	$export_result = game_bsc_get_voucher_redemptions_data(1, -1, $date_from, $date_to, $search, $gift_type, $voucher_type);
	if ($export_result['status'] === 'success') {
		$export_redemptions = $export_result['data'];
	}
	?>
	
	<section class="list-user ">
		<div class="container">
			<div class="wrapper p-6 bg-white rounded-xl flex flex-col gap-6">
				<h2 class="text-2xl font-medium text-[#31333F]">Danh sách quà đã đổi</h2>
				<div class="flex flex-col wrapper-table ">
					<div class="flex justify-between items-center bg-white pl-4">
						<!-- Hiển thị tổng số quà đã đổi -->
						<p class="text-[#4D7CFF] text-sm font-medium cus-bg"><?php echo number_format($total_items); ?> quà đã đổi</p>
						
						<!-- Filter Form -->
						<div class="list-filter-in-table py-3 px-4 flex gap-4 items-center">
							<form method="GET" id="voucherFilterForm" style="display: flex; gap: 15px; align-items: center;">
								<!-- Hidden input để giữ các tham số khác -->
								<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'dashboard-layout'); ?>">
								<input type="hidden" name="sub" value="<?php echo esc_attr($_GET['sub'] ?? 'voucher-list'); ?>">
								<input type="hidden" name="paged" value="1">
								
								<!-- Filter Loại quà -->
								<select name="gift_type" class="!py-[11px] !px-3 min-w-[148px] rounded-md border border-[#C9CCD2]" onchange="document.getElementById('voucherFilterForm').submit()">
									<option value="all" <?php selected($gift_type, 'all'); ?>>Tất cả loại quà</option>
									<option value="voucher" <?php selected($gift_type, 'voucher'); ?>>Voucher</option>
									<option value="artifact" <?php selected($gift_type, 'artifact'); ?>>Hiện vật</option>
								</select>

								<!-- Filter Loại voucher -->
								<select name="voucher_type" class="!py-[11px] !px-3 min-w-[152px] rounded-md border border-[#C9CCD2]" onchange="document.getElementById('voucherFilterForm').submit()">
									<option value="all" <?php selected($voucher_type, 'all'); ?>>Tất cả voucher</option>
									<option value="BSC" <?php selected($voucher_type, 'BSC'); ?>>Voucher tại BSC</option>
									<option value="third_party" <?php selected($voucher_type, 'third_party'); ?>>Voucher bên thứ 3</option>
								</select>

								<!-- Filter Date -->
								<div class="flex items-center gap-[15px] py-[8px] px-3 rounded-lg border border-solid border-[#C9CCD2]">
									<div class="flex gap-4 items-center ">
										<label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_from">Từ ngày</label>
										<input type="date" name="date_from" id="date_from" class="!border-none rounded-md p-2" value="<?php echo esc_attr($date_from); ?>">
									</div>
									<span>-</span>
									<div class="flex gap-4 items-center ">
										<label class="text-sm font-regular text-[rgba(29,29,29,0.50)]" for="date_to">Đến ngày</label>
										<input type="date" name="date_to" id="date_to" class="!border-none rounded-md p-2" value="<?php echo esc_attr($date_to); ?>">
									</div>
								</div>
								
								<!-- Search Input -->
								<input type="text" name="search" class="!py-[11px] !px-3 min-w-[180px]" placeholder="Tìm kiếm người dùng" value="<?php echo esc_attr($search); ?>">
								
								<!-- Submit Button -->
								<button type="submit" title="Tìm kiếm" class="w-[38px] h-[38px] bg-blue-500 text-white rounded-md hover:bg-blue-600 flex items-center justify-center flex-shrink-0">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<circle cx="11" cy="11" r="8"></circle>
										<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
									</svg>
								</button>
                                
                                <!-- Export CSV Button (inside form để cùng flex container) -->
                                <button type="button" onclick="exportVoucherListToCSV()" title="Xuất Excel" class="w-[38px] h-[38px] bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                </button>
							</form>
						</div>
					</div>
					
					<!-- Table -->
					<div class="overflow-x-auto overflow-y-auto max-h-[70vh] table-general">
						<table class="min-w-full border-collapse divide-y divide-gray-200">
							<thead class="bg-gray-50">
							<tr>
								<th class="px-6 py-3 text-left">STT</th>
								<th class="px-6 py-3 text-left">CUSTODYCD</th>
								<th class="px-6 py-3 text-left">AFACCTNO</th>
								<th class="px-6 py-3 text-left">Mã voucher / Tên hiện vật</th>
								<th class="px-6 py-3 text-left">Thời gian đổi</th>
								<th class="px-6 py-3 text-left">Giá trị voucher</th>
								<th class="px-6 py-3 text-left">Số điểm cần đổi voucher</th>
								<th class="px-6 py-3 text-left">DESCRIPTION</th>
<!--								<th class="px-6 py-3 text-left">Thông tin user</th>-->
								<th class="px-6 py-3 text-left">Loại voucher</th>
								<th class="px-6 py-3 text-left">Loại quà</th>
								<th class="px-6 py-3 text-left"></th>
							</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
							<?php if (!empty($redemptions)): ?>
								<?php foreach ($redemptions as $redemption): ?>
									<tr class="hover:bg-gray-50 transition td-content">
										<!-- STT -->
										<td class="px-6 py-3"><?php echo esc_html($redemption['stt']); ?></td>
										<td class="px-6 py-3">
                                            <div class="text-sm text-gray-500"> <?php echo esc_html($redemption['external_user_id']); ?></div>
                                        </td>
										<td class="px-6 py-3">
											<div class="text-sm text-gray-500"><?php echo !empty($redemption['afacctno']) ? esc_html($redemption['afacctno']) : 'N/A'; ?></div>
										</td>
										<!-- Mã voucher / Tên hiện vật -->
										<td class="px-6 py-3">
											<?php if ($redemption['gift_type'] === 'voucher'): ?>
												<div>
													<div class="font-medium"><?php echo esc_html($redemption['voucher_code']); ?></div>
												</div>
											<?php else: ?>
												<div>
													<div class="font-medium text-green-700"><?php echo esc_html($redemption['artifact_name']); ?></div>
													<div class="text-sm text-gray-500">Hiện vật</div>
												</div>
											<?php endif; ?>
										</td>
										
										<!-- Thời gian đổi -->
										<td class="px-6 py-3"><?php echo esc_html($redemption['redeemed_at_display']); ?></td>
										<td class="px-6 py-3">
                                            <?php if ($redemption['gift_type'] === 'voucher'): ?>
											<?php echo esc_html(number_format((float) ($redemption['voucher_value'] ?? 0), 0, ',', '.')); ?>
                                            <?php else: ?>
											N/A
											<?php endif; ?>
										</td>
										<td class="px-6 py-3">
											<?php if ($redemption['gift_type'] === 'voucher'): ?>
											<?php echo esc_html(number_format((int) ($redemption['points_cost'] ?? 0))); ?>
											<?php else: ?>
											N/A
                                            <?php endif; ?>
                                        </td>
				                                        <td class="text-sm text-gray-500 px-6 py-3">
                                            <?php if ($redemption['gift_type'] === 'voucher'): ?>
                                            <?php echo esc_html($redemption['voucher_name']); ?>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Thông tin user -->
<!--										<td class="px-6 py-3">-->
<!--											<div>-->
<!--												<a class="text-blue-600 hover:underline font-medium" href="--><?php //echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => $redemption['user_id']])); ?><!--">-->
<!--													--><?php //echo esc_html($redemption['user_name']); ?>
<!--												</a>-->
<!---->
<!--											</div>-->
<!--										</td>-->
										
										<!-- Loại voucher -->
										<td class="px-6 py-3">
											<?php if ($redemption['gift_type'] === 'voucher'): ?>
												<span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo ($redemption['voucher_type'] === 'BSC') ? '#e3f2fd' : '#fff3e0'; ?>; color: <?php echo ($redemption['voucher_type'] === 'BSC') ? '#1976d2' : '#f57c00'; ?>;">
													<?php echo esc_html($redemption['voucher_type_label']); ?>
												</span>
											<?php else: ?>
												<span class="text-gray-400">N/A</span>
											<?php endif; ?>
										</td>
										
										<!-- Thời gian có hiệu lực -->
<!--										<td class="px-6 py-3">-->
<!--											--><?php //if ($redemption['gift_type'] === 'voucher'): ?>
<!--												--><?php //echo esc_html($redemption['validity_display']); ?>
<!--											--><?php //else: ?>
<!--												<span class="text-gray-400">N/A</span>-->
<!--											--><?php //endif; ?>
<!--										</td>-->
										
										<!-- Loại quà -->
										<td class="px-6 py-3">
											<span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo ($redemption['gift_type'] === 'voucher') ? '#f3e8ff' : '#e0f2f1'; ?>; color: <?php echo ($redemption['gift_type'] === 'voucher') ? '#7c3aed' : '#00897b'; ?>;">
												<?php echo esc_html($redemption['gift_type_label']); ?>
											</span>
										</td>
										
										<!-- Action Button -->
										<td class="px-6 py-3">
											<a class="w-5 h-5" href="<?php echo esc_url(add_query_arg([
												'page' => 'dashboard-layout',
												'sub' => 'gift-detail',
												'gift_type' => $redemption['gift_type'],
												'redemption_id' => $redemption['redemption_id']
											])); ?>" title="Xem chi tiết quà đã đổi">
												<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
													<path d="M0.833252 10C0.833252 10 4.16658 3.33334 9.99992 3.33334C15.8333 3.33334 19.1666 10 19.1666 10C19.1666 10 15.8333 16.6667 9.99992 16.6667C4.16658 16.6667 0.833252 10 0.833252 10Z" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
													<path d="M9.99992 12.5C11.3806 12.5 12.4999 11.3807 12.4999 10C12.4999 8.6193 11.3806 7.50001 9.99992 7.50001C8.61921 7.50001 7.49992 8.6193 7.49992 10C7.49992 11.3807 8.61921 12.5 9.99992 12.5Z" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
												</svg>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr>
									<td colspan="12" class="px-6 py-8 text-center text-gray-500">
										Không có dữ liệu để hiển thị
									</td>
								</tr>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
					
					<!-- Pagination -->
					<?php if ($total_pages > 1): ?>
						<div class="flex justify-between items-center p-4 border-t">
							<div class="text-sm text-gray-600">
								Trang <?php echo $current_page; ?> / <?php echo $total_pages; ?>
							</div>
							<div class="flex gap-2">
								<?php if ($current_page > 1): ?>
									<a href="<?php echo esc_url(add_query_arg(['paged' => $current_page - 1])); ?>"
									   class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">
										« Trước
									</a>
								<?php endif; ?>
								
								<?php
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $current_page + 2);
								
								for ($i = $start_page; $i <= $end_page; $i++):
									?>
									<a href="<?php echo esc_url(add_query_arg(['paged' => $i])); ?>"
									   class="px-3 py-1 border <?php echo ($i === $current_page) ? 'bg-blue-500 text-white border-blue-500' : 'border-gray-300 hover:bg-gray-50'; ?> rounded-md">
										<?php echo $i; ?>
									</a>
								<?php endfor; ?>
								
								<?php if ($current_page < $total_pages): ?>
									<a href="<?php echo esc_url(add_query_arg(['paged' => $current_page + 1])); ?>"
									   class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">
										Sau »
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
	
</main>

<script src="https://cdn.tailwindcss.com"></script>

<script>
function exportVoucherListToCSV() {
	// Get all data (unpaginated)
	const data = <?php echo json_encode($export_redemptions); ?>;

	if (!data || data.length === 0) {
		alert('Không có dữ liệu để xuất!');
		return;
	}

	// CSV Header
	const headers = [
		'CUSTODYCD',
		'AFACCTNO',
		'VOUCHERID',
		'REGDATE',
		'VOUCHERAMT',
		'POINTS_REQUIRED',
		'DESCR',
		'VOUCHER_TYPE',
	];

	// Build CSV content
	let csvContent = '\uFEFF'; // UTF-8 BOM for Excel compatibility

	// Add headers
	csvContent += headers.map(h => `"${h}"`).join(',') + '\n';

	// Add data rows
	data.forEach(row => {
		const voucherCode = row.gift_type === 'voucher' ? row.voucher_code : row.artifact_name;
		const voucherName = row.gift_type === 'voucher' ? row.voucher_name : 'Hiện vật';
		const voucherType = row.gift_type === 'voucher' ? row.voucher_type_label : 'N/A';
		const voucherValue = row.gift_type === 'voucher' ? row.voucher_value : '';
		const pointsCost = row.gift_type === 'voucher' ? row.points_cost : '';

		// Format AFACCTNO & VOUCHERID to preserve leading zeros in Excel (use ="value" format)
		const formattedAfacctno = row.afacctno ? `="${row.afacctno}"` : '';
		const formattedVoucherCode = voucherCode ? `="${voucherCode}"` : '';

		const rowData = [
			// row.stt,                    // STT
			row.external_user_id,       // CUSTODYCD
			formattedAfacctno,          // AFACCTNO (preserve leading zeros)
			formattedVoucherCode,       // VOUCHERID (preserve leading zeros)
			row.redeemed_at_date,       // REGDATE (dd/mm/yyyy format)
			voucherValue,               // VOUCHERAMT
			pointsCost,                 // POINTS_REQUIRED
			voucherName,                // DESCR
			voucherType,                // VOUCHER_TYPE
			// row.gift_type_label         // Loại quà
		];

		// Escape quotes and wrap in quotes
		csvContent += rowData.map(cell => {
			const cellStr = String(cell || '');
			return `"${cellStr.replace(/"/g, '""')}"`;
		}).join(',') + '\n';
	});

	// Create blob and download
	const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
	const link = document.createElement('a');

	// Generate filename with current date
	const now = new Date();
	const dateStr = now.getFullYear() +
		String(now.getMonth() + 1).padStart(2, '0') +
		String(now.getDate()).padStart(2, '0') + '_' +
		String(now.getHours()).padStart(2, '0') +
		String(now.getMinutes()).padStart(2, '0');

	const filename = `danh_sach_qua_da_doi_${dateStr}.csv`;

	if (navigator.msSaveBlob) { // IE 10+
		navigator.msSaveBlob(blob, filename);
	} else {
		link.href = URL.createObjectURL(blob);
		link.download = filename;
		link.style.display = 'none';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

	// Show success message
	alert('Đã xuất file CSV thành công!');
}
</script>

<script>
/* ── Date range validation: Từ ngày phải <= Đến ngày ── */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var fromEl = form.querySelector('[name="date_from"]');
            var toEl   = form.querySelector('[name="date_to"]');
            if (fromEl && toEl && fromEl.value && toEl.value && fromEl.value > toEl.value) {
                alert('"Từ ngày" phải nhỏ hơn hoặc bằng "Đến ngày".');
                toEl.focus();
                e.preventDefault();
            }
        });
    });
});
</script>

