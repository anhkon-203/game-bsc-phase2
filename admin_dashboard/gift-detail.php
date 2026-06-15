<?php
if (!defined('ABSPATH')) {
	exit;
}

$timezone = TIMEZONE; // GMT+7
$now = new DateTime('now', $timezone);
$current_date = $now->format('Y-m-d');
$current_time = $now->format('H:i:s');

// Lấy tham số
$gift_type = isset($_GET['gift_type']) ? sanitize_text_field($_GET['gift_type']) : '';
$redemption_id = isset($_GET['redemption_id']) ? (int)$_GET['redemption_id'] : 0;

if (empty($gift_type) || empty($redemption_id)) {
	wp_die('Thiếu thông tin quà đã đổi.');
}

global $wpdb;
$prefix = $wpdb->prefix . 'game_';

// Lấy thông tin chi tiết
$gift_data = null;

if ($gift_type === 'voucher') {
	// Lấy thông tin voucher redemption
	$redemption = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				uvr.id,
				uvr.user_id,
				uvr.voucher_post_id,
				uvr.redeemed_at,
				uvr.start_date,
				uvr.gotit_expiry_date,
				uvr.prinpaid,
				u.name as user_name,
				u.external_user_id,
				u.avatar_url,
				u.created_at as user_created_at,
				p.post_title as voucher_name,
				p.post_content as voucher_description
			FROM {$prefix}user_voucher_redemptions uvr
			INNER JOIN {$prefix}users u ON uvr.user_id = u.id
			INNER JOIN {$wpdb->posts} p ON uvr.voucher_post_id = p.ID
			WHERE uvr.id = %d",
			$redemption_id
		),
		ARRAY_A
	);
	
	if ($redemption) {
		$voucher_id = (int)$redemption['voucher_post_id'];
		
		// Lấy ACF fields
		$voucher_code = get_field('voucher_code', $voucher_id) ?? 'N/A';
		$voucher_type = get_field('voucher_type', $voucher_id) ?? 'BSC';
		$voucher_applicable_stores = get_field('voucher_applicable_stores', $voucher_id) ?? '';
		$points_cost = get_field('points_cost', $voucher_id) ?? 0;
		$quantity = get_field('quantity', $voucher_id) ?? 0;
		$redemption_count = get_field('redemption_count', $voucher_id) ?? 0;
		$is_active = get_field('is_active', $voucher_id) ?? false;
		
		// Partner info (group field)
		$partner_data = get_field('partner', $voucher_id) ?: [];
		if (!is_array($partner_data)) {
			$partner_data = [];
		}
		$partner_name = $partner_data['name'] ?? '';
		$partner_url = $partner_data['url'] ?? '';
		$partner_logo_raw = $partner_data['logo'] ?? '';
		$partner_logo_url = '';
		if (is_numeric($partner_logo_raw) && (int) $partner_logo_raw > 0) {
			$partner_logo_url = (string) (wp_get_attachment_url((int) $partner_logo_raw) ?: '');
		} elseif (is_string($partner_logo_raw)) {
			$partner_logo_url = esc_url_raw($partner_logo_raw);
		}
		if ($partner_logo_url === '') {
			$partner_logo_url = esc_url_raw((string) (get_field('voucher_brand_logo_url', $voucher_id) ?? ''));
		}
		
		// Validity
		if ($voucher_type === 'BSC') {
			$valid_from = !empty($redemption['start_date']) ? $redemption['start_date'] : '';
			$valid_to = !empty($redemption['gotit_expiry_date']) ? $redemption['gotit_expiry_date'] : '';
		} else {
			$validity_data = get_field('validity', $voucher_id) ?: [];
			if (!is_array($validity_data)) {
				$validity_data = [];
			}
			$valid_from = $validity_data['valid_from'] ?? '';
			$valid_to = $validity_data['valid_to'] ?? '';
		}

		// Thông tin sử dụng voucher BSC (prinpaid / voucheramt / reamt)
		$prinpaid   = (float) ($redemption['prinpaid'] ?? 0);
		$voucheramt = (float) (get_post_meta($voucher_id, 'voucheramt', true) ?: 0);
		$reamt      = ($voucheramt > 0) ? max(0, $voucheramt - $prinpaid) : 0;
		
		// Banner & Thumbnail Images
		$banner_image_id = get_field('banner_image', $voucher_id);
		$banner_image_url = $banner_image_id ? wp_get_attachment_url($banner_image_id) : '';
		
		// Thumbnail = Featured Image của post (không phải ACF field)
		$thumbnail_id = get_post_thumbnail_id($voucher_id);
		$thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
		
		$redeemed_banner_image_id = get_field('redeemed_banner_image', $voucher_id);
		$redeemed_banner_image_url = $redeemed_banner_image_id ? wp_get_attachment_url($redeemed_banner_image_id) : '';
		if (!$redeemed_banner_image_url) {
			$default_banner_id = get_option('game_bsc_default_redeemed_banner');
			if ($default_banner_id) {
				$redeemed_banner_image_url = wp_get_attachment_image_url($default_banner_id, 'full') ?: '';
			}
		}
		
		$gift_data = [
			'type'                     => 'voucher',
			'redemption_id'            => $redemption['id'],
			'user_id'                  => $redemption['user_id'],
			'user_name'                => $redemption['user_name'],
			'external_user_id'         => $redemption['external_user_id'],
			'avatar_url'               => $redemption['avatar_url'],
			'user_created_at'          => $redemption['user_created_at'],
			'redeemed_at'              => $redemption['redeemed_at'],
			'voucher_id'               => $voucher_id,
			'voucher_code'             => $voucher_code,
			'voucher_name'             => $redemption['voucher_name'],
			'voucher_description'      => $redemption['voucher_description'],
			'voucher_type'             => $voucher_type,
			'voucher_applicable_stores'=> $voucher_applicable_stores,
			'voucher_type_label'       => ($voucher_type === 'BSC') ? 'Voucher tại BSC' : 'Voucher bên thứ 3',
			'points_cost'              => $points_cost,
			'quantity'                 => $quantity,
			'redemption_count'         => $redemption_count,
			'is_active'                => $is_active,
			'partner_name'             => $partner_name,
			'partner_url'              => $partner_url,
			'partner_logo_url'         => $partner_logo_url,
			'valid_from'               => $valid_from,
			'valid_to'                 => $valid_to,
			'banner_image_url'         => $banner_image_url,
			'thumbnail_url'            => $thumbnail_url,
			'redeemed_banner_image_url'=> $redeemed_banner_image_url,
			// Thông tin sử dụng voucher BSC từ BSC Trading API
			'prinpaid'                 => $prinpaid,
			'voucheramt'               => $voucheramt,
			'reamt'                    => $reamt,
			// GotIt transaction (chỉ có giá trị khi voucher_type !== 'BSC')
			'gotit_txn'                => null,
			// Categories từ taxonomy
			'gotit_categories'         => [],
		];

		// Lấy categories từ taxonomy game_voucher_category
		$cat_terms = wp_get_post_terms($voucher_id, 'game_voucher_category', ['fields' => 'names']);
		if (!is_wp_error($cat_terms) && !empty($cat_terms)) {
			$gift_data['gotit_categories'] = array_map('sanitize_text_field', $cat_terms);
		}

		// Lấy thông tin GotIt transaction nếu là voucher bên thứ 3
		if ($voucher_type !== 'BSC') {
			$gotit_txn = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						transaction_ref_id,
						gotit_order_name,
						gotit_product_id,
						gotit_product_price_id,
						gotit_voucher_link,
						gotit_voucher_code,
						gotit_voucher_image,
						gotit_serial,
						gotit_expiry_date,
						gotit_status,
						gotit_state_name,
						gotit_status_changed_at,
						created_at,
						updated_at
					FROM {$prefix}gotit_transactions
					WHERE redemption_id = %d
					ORDER BY id DESC
					LIMIT 1",
					(int) $redemption['id']
				),
				ARRAY_A
			);
			if ($gotit_txn) {
				$gift_data['gotit_txn'] = $gotit_txn;
			}
		}
	}
	
} else if ($gift_type === 'artifact') {
	// Lấy thông tin artifact redemption
	$redemption = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				uar.id,
				uar.user_id,
				uar.artifact_id,
				uar.redeemed_at,
				u.name as user_name,
				u.external_user_id,
				u.avatar_url,
				u.created_at as user_created_at,
				a.name as artifact_name,
				a.artifacts_url,
				a.max_redemptions,
				a.status
			FROM {$prefix}user_artifact_redemptions uar
			INNER JOIN {$prefix}users u ON uar.user_id = u.id
			INNER JOIN {$prefix}artifacts a ON uar.artifact_id = a.id
			WHERE uar.id = %d",
			$redemption_id
		),
		ARRAY_A
	);
	
	if ($redemption) {
		$artifact_id = (int)$redemption['artifact_id'];
		
		// Lấy danh sách pieces của artifact
		$pieces = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.id as piece_id,
					p.piece_code,
					p.baseline_weight,
					p.piece_img
				FROM {$prefix}pieces p
				WHERE p.artifact_id = %d
				ORDER BY p.piece_code",
				$artifact_id
			),
			ARRAY_A
		);
		
		// Đếm tổng số lần artifact đã được đổi
		$total_redeemed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}user_artifact_redemptions WHERE artifact_id = %d",
				$artifact_id
			)
		);
		
		$gift_data = [
			'type' => 'artifact',
			'redemption_id' => $redemption['id'],
			'user_id' => $redemption['user_id'],
			'user_name' => $redemption['user_name'],
			'external_user_id' => $redemption['external_user_id'],
			'avatar_url' => $redemption['avatar_url'],
			'user_created_at' => $redemption['user_created_at'],
			'redeemed_at' => $redemption['redeemed_at'],
			'artifact_id' => $artifact_id,
			'artifact_name' => $redemption['artifact_name'],
			'artifacts_url' => $redemption['artifacts_url'],
			'max_redemptions' => $redemption['max_redemptions'],
			'total_redeemed' => $total_redeemed,
			'status' => $redemption['status'],
			'pieces' => $pieces,
		];
	}
}

if (!$gift_data) {
	wp_die('Không tìm thấy thông tin quà đã đổi.');
}

?>
<script src="https://cdn.tailwindcss.com"></script>
<script src="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/js/tailwind.config.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= GAME_BSC_PLUGIN_URL ?>admin_dashboard/assets/style.css">

<style>
	.info-card {
		background: white;
		border-radius: 8px;
		border: 1px solid #EAECF0;
		padding: 24px;
		box-shadow: 0 1px 3px 0 rgba(16, 24, 40, 0.10), 0 1px 2px 0 rgba(16, 24, 40, 0.06);
	}
	.info-row {
		display: flex;
		padding: 12px 0;
		border-bottom: 1px solid #F2F4F7;
	}
	.info-row:last-child {
		border-bottom: none;
	}
	.info-label {
		font-weight: 500;
		color: #344054;
		min-width: 200px;
	}
	.info-value {
		color: #667085;
		flex: 1;
	}
	.piece-card {
		border: 1px solid #E5E7EB;
		border-radius: 8px;
		padding: 16px;
		text-align: center;
		background: white;
	}
	.piece-card img {
		width: 80px;
		height: 80px;
		object-fit: cover;
		margin: 0 auto 12px;
		border-radius: 4px;
	}
</style>

<main class="flex flex-col gap-8 py-8">
	<!-- Breadcrumb -->
	<div class="card-top">
		<div class="breadcrumb flex flex-col gap-3">
			<nav class="flex gap-1">
				<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'voucher-list'])); ?>" class="text-sm font-regular text-[#6A7A95]">Danh sách quà đã đổi</a>
				<span class="text-sm font-regular text-[#6A7A95]">/</span>
				<span class="text-sm font-regular text-[#6A7A95]">Chi tiết</span>
			</nav>
			<h2 class="text-lg font-medium text-[#31333F]">Chi tiết quà đã đổi</h2>
		</div>
		<div class="desc text-sm font-regular text-[#6A7A95] mt-2">
			Cập nhật lần cuối: <?php echo esc_html($current_date); ?> - <?php echo esc_html($current_time); ?>
		</div>
	</div>

	<div class="container">
		<div class="flex gap-6">
			<!-- Left Column: Gift Info -->
			<div class="flex-1 flex flex-col gap-6">
				<!-- Gift Header -->
				<div class="info-card">
					<div class="flex items-start gap-4 mb-4">
						<?php // Chỉ hiển thị ảnh cho hiện vật, voucher không cần ảnh ở header ?>
						<?php if ($gift_data['type'] === 'artifact' && !empty($gift_data['artifacts_url'])): ?>
							<img src="<?php echo esc_url($gift_data['artifacts_url']); ?>" alt="Artifact" style="width: 120px; height: 80px; object-fit: cover; border-radius: 8px;">
						<?php endif; ?>
						
						<div class="flex-1">
							<div class="flex items-center gap-3 mb-2">
								<h3 class="text-xl font-semibold text-[#31333F]">
									<?php echo $gift_data['type'] === 'voucher' ? esc_html($gift_data['voucher_name']) : esc_html($gift_data['artifact_name']); ?>
								</h3>
								<span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background-color: <?php echo ($gift_data['type'] === 'voucher') ? '#f3e8ff' : '#e0f2f1'; ?>; color: <?php echo ($gift_data['type'] === 'voucher') ? '#7c3aed' : '#00897b'; ?>;">
									<?php echo $gift_data['type'] === 'voucher' ? 'Voucher' : 'Hiện vật'; ?>
								</span>
								<?php if ($gift_data['type'] === 'voucher'): ?>
									<span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background-color: <?php echo $gift_data['is_active'] ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $gift_data['is_active'] ? '#155724' : '#721c24'; ?>;">
										<?php echo $gift_data['is_active'] ? '✓ Đang hoạt động' : '✗ Ngừng hoạt động'; ?>
									</span>
								<?php endif; ?>
							</div>
							<?php if ($gift_data['type'] === 'voucher'): ?>
								<div class="text-sm text-gray-500">Mã: <span class="font-medium text-gray-700"><?php echo esc_html($gift_data['voucher_code']); ?></span></div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Gift Details -->
				<div class="info-card">
					<h4 class="text-lg font-semibold text-[#31333F] mb-4">Thông tin quà</h4>
					
					<?php if ($gift_data['type'] === 'voucher'): ?>
						<div class="info-row">
							<div class="info-label">ID Voucher (Post ID):</div>
							<div class="info-value font-semibold">#<?php echo esc_html($gift_data['voucher_id']); ?></div>
						</div>
						
						<div class="info-row">
							<div class="info-label">Loại voucher:</div>
							<div class="info-value">
								<span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo ($gift_data['voucher_type'] === 'BSC') ? '#e3f2fd' : '#fff3e0'; ?>; color: <?php echo ($gift_data['voucher_type'] === 'BSC') ? '#1976d2' : '#f57c00'; ?>;">
									<?php echo esc_html($gift_data['voucher_type_label']); ?>
								</span>
							</div>
						</div>
						
						<div class="info-row">
							<div class="info-label">Trạng thái:</div>
							<div class="info-value">
								<span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo $gift_data['is_active'] ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $gift_data['is_active'] ? '#155724' : '#721c24'; ?>;">
									<?php echo $gift_data['is_active'] ? '✓ Đang hoạt động' : '✗ Ngừng hoạt động'; ?>
								</span>
							</div>
						</div>
						
						<?php if (!empty($gift_data['partner_name'])): ?>
							<div class="info-row">
								<div class="info-label">Đối tác:</div>
								<div class="info-value">
									<div class="flex items-center gap-3">
										<?php if (!empty($gift_data['partner_logo_url'])): ?>
											<img src="<?php echo esc_url($gift_data['partner_logo_url']); ?>" alt="Partner Logo" style="width: 40px; height: 40px; object-fit: contain; border-radius: 4px; border: 1px solid #e5e7eb;">
										<?php endif; ?>
										<div>
											<div class="font-medium"><?php echo esc_html($gift_data['partner_name']); ?></div>
											<?php if (!empty($gift_data['partner_url'])): ?>
												<a href="<?php echo esc_url($gift_data['partner_url']); ?>" target="_blank" class="text-sm text-blue-600 hover:underline">
													<?php echo esc_html($gift_data['partner_url']); ?>
												</a>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<?php if (!empty($gift_data['voucher_applicable_stores']) && $gift_data['voucher_type'] !== 'BSC'): ?>
							<div class="info-row">
								<div class="info-label">Cửa hàng áp dụng:</div>
								<div class="info-value" style="white-space: pre-wrap;"><?php echo esc_html($gift_data['voucher_applicable_stores']); ?></div>
							</div>
						<?php endif; ?>
						
						<div class="info-row">
							<div class="info-label">Điểm đổi:</div>
							<div class="info-value"><span class="font-semibold text-blue-600"><?php echo number_format($gift_data['points_cost']); ?></span> điểm</div>
						</div>
						
						<div class="info-row">
							<div class="info-label">Thời gian có hiệu lực:</div>
							<div class="info-value">
								<?php if (!empty($gift_data['valid_from']) && !empty($gift_data['valid_to'])): ?>
									<?php echo date('d/m/Y H:i', strtotime($gift_data['valid_from'])); ?> - <?php echo date('d/m/Y H:i', strtotime($gift_data['valid_to'])); ?>
								<?php elseif (!empty($gift_data['valid_to'])): ?>
									Đến <?php echo date('d/m/Y H:i', strtotime($gift_data['valid_to'])); ?>
								<?php elseif (!empty($gift_data['valid_from'])): ?>
									Từ <?php echo date('d/m/Y H:i', strtotime($gift_data['valid_from'])); ?>
								<?php else: ?>
									<span class="text-green-600 font-medium">Không giới hạn</span>
								<?php endif; ?>
							</div>
						</div>
						
						<div class="info-row">
							<div class="info-label">Số lượng:</div>
							<div class="info-value">
								<div>
									<span class="font-semibold">Tổng:</span> <?php echo number_format($gift_data['quantity']); ?> voucher
								</div>
								<div class="mt-1">
									<span class="font-semibold">Đã đổi:</span> <span class="text-orange-600"><?php echo number_format($gift_data['redemption_count']); ?></span> voucher
								</div>
								<div class="mt-1">
									<span class="font-semibold">Còn lại:</span> <span class="text-green-600 font-bold"><?php echo number_format($gift_data['quantity'] - $gift_data['redemption_count']); ?></span> voucher
								</div>
							</div>
						</div>
						
						<?php if (!empty($gift_data['voucher_description'])): ?>
							<div class="info-row">
								<div class="info-label">Mô tả:</div>
								<div class="info-value"><?php echo wp_kses_post($gift_data['voucher_description']); ?></div>
							</div>
						<?php endif; ?>

						<?php if ($gift_data['voucher_type'] === 'BSC' && $gift_data['voucheramt'] > 0): ?>
							<?php
							$used_pct   = min(100, ($gift_data['voucheramt'] > 0) ? ($gift_data['prinpaid'] / $gift_data['voucheramt'] * 100) : 0);
							$is_fully_used = ($gift_data['prinpaid'] >= $gift_data['voucheramt']);
							?>
							<div class="mt-6 pt-6 border-t border-gray-200">
								<h5 class="font-semibold text-[#31333F] mb-4">Thông tin sử dụng (BSC Trading):</h5>

								<div class="info-row">
									<div class="info-label">Mệnh giá voucher:</div>
									<div class="info-value font-semibold"><?php echo number_format($gift_data['voucheramt'], 0, ',', '.'); ?> ₫</div>
								</div>

								<div class="info-row">
									<div class="info-label">Đã sử dụng (prinpaid):</div>
									<div class="info-value">
										<span class="font-semibold <?php echo $is_fully_used ? 'text-red-600' : 'text-orange-600'; ?>">
											<?php echo number_format($gift_data['prinpaid'], 0, ',', '.'); ?> ₫
										</span>
										<?php if ($is_fully_used): ?>
											<span style="margin-left:8px; padding: 2px 8px; border-radius: 4px; font-size: 11px; background:#fee2e2; color:#dc2626;">Đã dùng hết</span>
										<?php endif; ?>
									</div>
								</div>

								<div class="info-row">
									<div class="info-label">Còn lại (reamt):</div>
									<div class="info-value">
										<span class="font-semibold <?php echo $gift_data['reamt'] > 0 ? 'text-green-600' : 'text-gray-400'; ?>">
											<?php echo number_format($gift_data['reamt'], 0, ',', '.'); ?> ₫
										</span>
									</div>
								</div>

								<div class="mt-3">
									<div class="flex justify-between text-xs text-gray-500 mb-1">
										<span>Đã dùng: <?php echo number_format($used_pct, 1); ?>%</span>
										<span>Còn: <?php echo number_format(100 - $used_pct, 1); ?>%</span>
									</div>
									<div class="w-full bg-gray-200 rounded-full" style="height:10px;">
										<div class="rounded-full" style="height:10px; width:<?php echo $used_pct; ?>%; background: <?php echo $is_fully_used ? '#dc2626' : '#f97316'; ?>;transition:width .4s;"></div>
									</div>
								</div>
							</div>
						<?php elseif ($gift_data['voucher_type'] === 'BSC'): ?>
							<div class="mt-6 pt-6 border-t border-gray-200">
								<h5 class="font-semibold text-[#31333F] mb-3">Thông tin sử dụng (BSC Trading):</h5>
								<div class="text-sm text-gray-400 italic">Chưa có dữ liệu từ BSC Trading API (voucheramt chưa được cấu hình)</div>
							</div>
						<?php endif; ?>

						<?php if ($gift_data['voucher_type'] !== 'BSC'): ?>
							<!-- Section GotIt Transaction -->
							<div class="mt-6 pt-6 border-t border-gray-200">
								<h5 class="font-semibold text-[#31333F] mb-4">Thông tin GotIt</h5>

								<?php if (!empty($gift_data['gotit_categories'])): ?>
									<div class="info-row">
										<div class="info-label">Danh mục:</div>
										<div class="info-value flex flex-wrap gap-1">
											<?php foreach ($gift_data['gotit_categories'] as $cat): ?>
												<span style="padding: 2px 10px; border-radius: 12px; font-size: 12px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
													<?php echo esc_html($cat); ?>
												</span>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>

								<?php if (!empty($gift_data['gotit_txn'])): ?>
									<?php $txn = $gift_data['gotit_txn']; ?>
									<?php
									$state_upper = strtoupper(trim((string) ($txn['gotit_state_name'] ?? '')));
									$state_colors = [
										'USED'      => ['bg' => '#fef3c7', 'color' => '#92400e'],
										'EXPIRED'   => ['bg' => '#fee2e2', 'color' => '#991b1b'],
										'PURCHASED' => ['bg' => '#d1fae5', 'color' => '#065f46'],
										'ISSUED'    => ['bg' => '#d1fae5', 'color' => '#065f46'],
									];
									$sc = $state_colors[$state_upper] ?? ['bg' => '#f3f4f6', 'color' => '#374151'];
									$state_label_map = [
										'USED'      => 'Đã sử dụng',
										'EXPIRED'   => 'Hết hạn',
										'PURCHASED' => 'Chưa sử dụng',
										'ISSUED'    => 'Chưa sử dụng',
									];
									$state_label = $state_label_map[$state_upper] ?? esc_html($txn['gotit_state_name']);
									?>

									<div class="info-row">
										<div class="info-label">Trạng thái:</div>
										<div class="info-value">
											<span style="padding: 4px 10px; border-radius: 4px; background-color: <?php echo esc_attr($sc['bg']); ?>; color: <?php echo esc_attr($sc['color']); ?>;">
												<?php echo esc_html($state_label); ?>
											</span>
										</div>
									</div>

									<?php if ($state_upper === 'USED' && !empty($txn['gotit_status_changed_at'])): ?>
										<div class="info-row">
											<div class="info-label">Ngày sử dụng:</div>
											<div class="info-value font-medium text-amber-700">
												<?php echo esc_html(date('d/m/Y H:i:s', strtotime($txn['gotit_status_changed_at']))); ?>
											</div>
										</div>
									<?php endif; ?>

									<div class="info-row">
										<div class="info-label">Mã voucher GotIt:</div>
										<div class="info-value font-medium"><?php echo esc_html($txn['gotit_voucher_code'] ?? 'N/A'); ?></div>
									</div>

									<?php if (!empty($txn['gotit_serial'])): ?>
										<div class="info-row">
											<div class="info-label">Serial:</div>
											<div class="info-value"><?php echo esc_html($txn['gotit_serial']); ?></div>
										</div>
									<?php endif; ?>

									<?php if (!empty($txn['gotit_voucher_link'])): ?>
										<div class="info-row">
											<div class="info-label">Link voucher:</div>
											<div class="info-value">
												<a href="<?php echo esc_url($txn['gotit_voucher_link']); ?>" target="_blank" class="text-blue-600 hover:underline break-all">
													<?php echo esc_html($txn['gotit_voucher_link']); ?>
												</a>
											</div>
										</div>
									<?php endif; ?>

									<?php if (!empty($txn['gotit_expiry_date'])): ?>
										<div class="info-row">
											<div class="info-label">Ngày hết hạn voucher:</div>
											<div class="info-value"><?php echo esc_html(date('d/m/Y', strtotime($txn['gotit_expiry_date']))); ?></div>
										</div>
									<?php endif; ?>

									<div class="info-row">
										<div class="info-label">Transaction Ref ID:</div>
										<div class="info-value text-xs text-gray-500 break-all"><?php echo esc_html($txn['transaction_ref_id'] ?? ''); ?></div>
									</div>

									<div class="info-row">
										<div class="info-label">Product ID / Price ID:</div>
										<div class="info-value"><?php echo esc_html($txn['gotit_product_id']); ?> / <?php echo esc_html($txn['gotit_product_price_id']); ?></div>
									</div>

									<div class="info-row">
										<div class="info-label">Phát hành lúc:</div>
										<div class="info-value"><?php echo esc_html(date('d/m/Y H:i:s', strtotime($txn['created_at']))); ?></div>
									</div>

									<?php if (!empty($txn['gotit_voucher_image'])): ?>
										<div class="info-row">
											<div class="info-label">Hình ảnh voucher:</div>
											<div class="info-value">
												<img src="<?php echo esc_url($txn['gotit_voucher_image']); ?>" alt="Voucher Image" style="max-width: 160px; border-radius: 6px; border: 1px solid #e5e7eb;">
											</div>
										</div>
									<?php endif; ?>

								<?php else: ?>
									<div class="text-sm text-gray-400 italic">Chưa có dữ liệu giao dịch GotIt cho voucher này.</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						
						<!-- All Images Section -->
						<?php if (!empty($gift_data['banner_image_url']) || !empty($gift_data['thumbnail_url']) || !empty($gift_data['redeemed_banner_image_url'])): ?>
							<div class="mt-6 pt-6 border-t border-gray-200">
								<h5 class="font-semibold text-[#31333F] mb-3">Hình ảnh voucher:</h5>
								<div class="grid grid-cols-3 gap-4">
									<?php if (!empty($gift_data['banner_image_url'])): ?>
										<div>
											<div class="text-xs text-gray-500 mb-2">Banner (ACF)</div>
											<img src="<?php echo esc_url($gift_data['banner_image_url']); ?>" alt="Banner" class="w-full h-32 object-cover rounded-lg border border-gray-200">
										</div>
									<?php endif; ?>
									
									<?php if (!empty($gift_data['thumbnail_url'])): ?>
										<div>
											<div class="text-xs text-gray-500 mb-2">Featured Image (Ảnh đại diện)</div>
											<img src="<?php echo esc_url($gift_data['thumbnail_url']); ?>" alt="Featured Image" class="w-full h-32 object-cover rounded-lg border border-gray-200">
										</div>
									<?php endif; ?>
									
									<?php if (!empty($gift_data['redeemed_banner_image_url'])): ?>
										<div>
											<div class="text-xs text-gray-500 mb-2">Banner đã đổi (ACF)</div>
											<img src="<?php echo esc_url($gift_data['redeemed_banner_image_url']); ?>" alt="Redeemed Banner" class="w-full h-32 object-cover rounded-lg border border-gray-200">
										</div>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
						
					<?php else: ?>
						<!-- Artifact Info -->
						<div class="info-row">
							<div class="info-label">ID Hiện vật:</div>
							<div class="info-value font-semibold">#<?php echo esc_html($gift_data['artifact_id']); ?></div>
						</div>
						
						<div class="info-row">
							<div class="info-label">Trạng thái:</div>
							<div class="info-value">
								<span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo ($gift_data['status'] == 1) ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo ($gift_data['status'] == 1) ? '#155724' : '#721c24'; ?>;">
									<?php echo ($gift_data['status'] == 1) ? '✓ Đang mở' : '✗ Đã đóng'; ?>
								</span>
							</div>
						</div>
						
						<div class="info-row">
							<div class="info-label">Số lần đổi:</div>
							<div class="info-value">
								<div>
									<span class="font-semibold">Tối đa:</span> <?php echo number_format($gift_data['max_redemptions']); ?> lần
								</div>
								<div class="mt-1">
									<span class="font-semibold">Đã đổi:</span> <span class="text-orange-600"><?php echo number_format($gift_data['total_redeemed']); ?></span> lần
								</div>
								<div class="mt-1">
									<span class="font-semibold">Còn lại:</span> <span class="text-green-600 font-bold"><?php echo number_format($gift_data['max_redemptions'] - $gift_data['total_redeemed']); ?></span> lần
								</div>
								<?php
								$percentage = ($gift_data['max_redemptions'] > 0) ? ($gift_data['total_redeemed'] / $gift_data['max_redemptions']) * 100 : 0;
								?>
								<div class="mt-2">
									<div class="w-full bg-gray-200 rounded-full h-2.5">
										<div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo min(100, $percentage); ?>%"></div>
									</div>
									<div class="text-xs text-gray-500 mt-1">Đã đổi <?php echo number_format($percentage, 1); ?>%</div>
								</div>
							</div>
						</div>
						
						<?php if (!empty($gift_data['artifacts_url'])): ?>
							<div class="info-row">
								<div class="info-label">Hình ảnh hiện vật:</div>
								<div class="info-value">
									<img src="<?php echo esc_url($gift_data['artifacts_url']); ?>" alt="<?php echo esc_attr($gift_data['artifact_name']); ?>" class="w-48 h-48 object-cover rounded-lg border border-gray-200">
								</div>
							</div>
						<?php endif; ?>
						
						<!-- Pieces -->
						<?php if (!empty($gift_data['pieces'])): ?>
							<div class="mt-6">
								<h5 class="font-semibold text-[#31333F] mb-3">Mảnh ghép (<?php echo count($gift_data['pieces']); ?> mảnh):</h5>
								<div class="grid grid-cols-4 gap-4">
									<?php foreach ($gift_data['pieces'] as $piece): ?>
										<div class="piece-card">
											<?php if (!empty($piece['piece_img'])): ?>
												<img src="<?php echo esc_url($piece['piece_img']); ?>" alt="<?php echo esc_attr($piece['piece_code']); ?>">
											<?php else: ?>
												<div style="width: 80px; height: 80px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; border-radius: 4px;">
													<span class="text-gray-400">No image</span>
												</div>
											<?php endif; ?>
											<div class="text-sm font-medium text-gray-700"><?php echo esc_html($piece['piece_code']); ?></div>
											<div class="text-xs text-gray-500">Tỉ lệ: <?php echo esc_html($piece['baseline_weight']); ?></div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Right Column: User & Redemption Info -->
			<div class="w-96 flex flex-col gap-6">
				<!-- Redemption Info -->
				<div class="info-card">
					<h4 class="text-lg font-semibold text-[#31333F] mb-4">Thông tin đổi quà</h4>
					
					<div class="info-row">
						<div class="info-label">Mã đổi quà:</div>
						<div class="info-value font-semibold">#<?php echo esc_html($gift_data['redemption_id']); ?></div>
					</div>
					
					<div class="info-row">
						<div class="info-label">Thời gian đổi:</div>
						<div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($gift_data['redeemed_at'])); ?></div>
					</div>
				</div>

				<!-- User Info -->
				<div class="info-card">
					<h4 class="text-lg font-semibold text-[#31333F] mb-4">Thông tin người đổi</h4>
					
					<div class="flex items-center gap-3 mb-4">
						<?php if (!empty($gift_data['avatar_url'])): ?>
							<img src="<?php echo esc_url($gift_data['avatar_url']); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
						<?php else: ?>
							<div style="width: 60px; height: 60px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center;">
								<svg width="30" height="30" fill="#9ca3af" viewBox="0 0 20 20">
									<path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
								</svg>
							</div>
						<?php endif; ?>
						<div>
							<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => $gift_data['user_id']])); ?>" class="font-semibold text-blue-600 hover:underline">
								<?php echo esc_html($gift_data['user_name']); ?>
							</a>
							<div class="text-sm text-gray-500">ID: <?php echo esc_html($gift_data['external_user_id']); ?></div>
						</div>
					</div>
					
					<div class="info-row">
						<div class="info-label">User ID (hệ thống):</div>
						<div class="info-value">#<?php echo esc_html($gift_data['user_id']); ?></div>
					</div>
					
					<div class="info-row">
						<div class="info-label">Ngày tạo tài khoản:</div>
						<div class="info-value"><?php echo date('d/m/Y H:i', strtotime($gift_data['user_created_at'])); ?></div>
					</div>
					
					<div class="mt-4">
						<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'user-detail', 'user_id' => $gift_data['user_id']])); ?>" class="inline-block px-4 py-2 bg-blue-500 text-white rounded-md text-sm font-medium hover:bg-blue-600">
							Xem chi tiết user
						</a>
					</div>
				</div>

				<!-- Back Button -->
				<div>
					<a href="<?php echo esc_url(add_query_arg(['page' => 'dashboard-layout', 'sub' => 'voucher-list'])); ?>" class="inline-block px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-300">
						← Quay lại danh sách
					</a>
				</div>
			</div>
		</div>
	</div>
</main>

