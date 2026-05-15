<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Đăng ký ACF Field Group local (nếu ACF được cài)
 * Gồm: answer_a, answer_b, answer_c, answer_d (text) và correct_answer (radio A/B/C/D)
 */
function game_bsc_register_acf_fields() {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	// Câu hỏi
	acf_add_local_field_group(array(
		'key' => 'group_game_bsc_question',
		'title' => 'Câu hỏi',
		'fields' => array(
			array(
				'key' => 'field_answer_a',
				'label' => 'Đáp án A <span style="color:red">(bắt buộc)</span>',
				'name' => 'answer_a',
				'type' => 'text',
			),
			array(
				'key' => 'field_answer_b',
				'label' => 'Đáp án B <span style="color:red">(bắt buộc)</span>',
				'name' => 'answer_b',
				'type' => 'text',
			),
			array(
				'key' => 'field_answer_c',
				'label' => 'Đáp án C',
				'name' => 'answer_c',
				'type' => 'text',
			),
			array(
				'key' => 'field_answer_d',
				'label' => 'Đáp án D',
				'name' => 'answer_d',
				'type' => 'text',
			),
			array(
				'key' => 'field_correct_answer',
				'label' => 'Đáp án đúng <span style="color:red">(vui lòng chọn đáp án đã nhập dữ liệu)</span>',
				'name' => 'correct_answer',
				'type' => 'radio',
				'choices' => array(
					'A' => 'A',
					'B' => 'B',
					'C' => 'C',
					'D' => 'D',
				),
				'layout' => 'vertical'
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'game_question',
				),
			),
		),
	));
	
	
	add_filter('acf/validate_value/key=field_answer_a', function ($valid, $value) {
		if ($valid !== true) return $valid;
		if (empty(trim($value))) return 'Vui lòng nhập đáp án A.';
		return $valid;
	}, 10, 2);
	
	add_filter('acf/validate_value/key=field_answer_b', function ($valid, $value) {
		if ($valid !== true) return $valid;
		if (empty(trim($value))) return 'Vui lòng nhập đáp án B.';
		return $valid;
	}, 10, 2);
	
	add_filter('acf/validate_value/key=field_correct_answer', function ($valid, $value) {
		
		if ($valid !== true) return $valid;
		
		$ans = [
			'A' => trim($_POST['acf']['field_answer_a'] ?? ''),
			'B' => trim($_POST['acf']['field_answer_b'] ?? ''),
			'C' => trim($_POST['acf']['field_answer_c'] ?? ''),
			'D' => trim($_POST['acf']['field_answer_d'] ?? ''),
		];
		
		if (empty($value)) return 'Vui lòng chọn đáp án đúng.';
		
		if (empty($ans[$value])) {
			return 'Đáp án đúng bạn chọn chưa có dữ liệu.';
		}
		
		return $valid;
	}, 10, 3);

	add_action('edit_form_after_title', function () {
		global $post;
		if ($post->post_type === 'game_question') {
			echo '<p style="color:red; font-weight:bold; margin-top:10px;">(Vui lòng nhập câu hỏi và ít nhất 2 đáp án A và B)</p>';
		}
	});
	add_filter('wp_insert_post_data', function ($data, $postarr) {
		if ($data['post_type'] === 'game_question') {
			
			// Validate title (Câu hỏi)
			if (empty(trim($data['post_title']))) {
				wp_die(
					'<h2>Lỗi nhập liệu</h2><p>Bạn phải nhập Câu hỏi (tiêu đề bài viết).</p>
                <p><a href="javascript:history.back()">Quay lại</a></p>'
				);
			}
		}
		return $data;
	}, 10, 2);
	
	
	// Voucher
	acf_add_local_field_group(array(
		'key' => 'group_game_bsc_voucher',
		'title' => 'Voucher',
		'fields' => array(
			
			array(
				'key'   => 'field_voucher_code',
				'label' => 'Mã voucher',
				'name'  => 'voucher_code',
				'type'  => 'text',
				'required' => 1,
				'wrapper' => array('width' => 50),
			),
			
			array(
				'key'   => 'field_voucher_type',
				'label' => 'Loại voucher',
				'name'  => 'voucher_type',
				'type'  => 'radio',
				'choices' => array(
					'BSC'          => 'Voucher tại BSC',
					'THIRD_PARTY'  => 'Voucher bên thứ 3',
				),
				'default_value' => 'BSC',
				'layout' => 'horizontal',
				'return_format' => 'value',
				'wrapper' => array('width' => 50),
			),
			array(
				'key'   => 'field_voucheramt',
				'label' => 'Giá trị voucher',
				'name'  => 'voucheramt',
				'type'  => 'number',
				'wrapper' => array('width' => 33),
				'step' => '0.01',
				'default_value' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'BSC',
						),
					),
				),
			),
			array(
				'key'   => 'field_prinpaid',
				'label' => 'Số tiền đã sử dụng',
				'name'  => 'prinpaid',
				'type'  => 'number',
				'wrapper' => array('width' => 33),
				'step' => '0.01',
				'default_value' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'BSC',
						),
					),
				),
			),
			array(
				'key'   => 'field_gotit_product_id',
				'label' => 'Got It Product ID',
				'name'  => 'gotit_product_id',
				'type'  => 'number',
				'min'   => 1,
				'step'  => 1,
				'instructions' => 'Required for THIRD_PARTY vouchers when issuing from Got It.',
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_gotit_product_price_id',
				'label' => 'Got It Product Price ID',
				'name'  => 'gotit_product_price_id',
				'type'  => 'number',
				'min'   => 1,
				'step'  => 1,
				'instructions' => 'Required for THIRD_PARTY vouchers when issuing from Got It.',
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_display_name',
				'label' => 'Tên voucher hiển thị',
				'name'  => 'voucher_display_name',
				'type'  => 'text',
				'instructions' => 'Tự động điền khi chọn voucher Got It, bạn có thể sửa lại nếu cần.',
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_brand_name',
				'label' => 'Thương hiệu',
				'name'  => 'voucher_brand_name',
				'type'  => 'text',
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_brand_logo_url',
				'label' => 'Logo thương hiệu (URL)',
				'name'  => 'voucher_brand_logo_url',
				'type'  => 'url',
				'instructions' => 'URL logo lấy từ Got It API. Hiển thị trong giao diện đổi quà.',
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_selected_value',
				'label' => 'Mệnh giá đã chọn',
				'name'  => 'voucher_selected_value',
				'type'  => 'text',
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_link_url',
				'label' => 'Đường dẫn voucher',
				'name'  => 'voucher_link_url',
				'type'  => 'url',
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_image_url',
				'label' => 'Ảnh voucher (URL)',
				'name'  => 'voucher_image_url',
				'type'  => 'url',
				'wrapper' => array('width' => 100),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_short_description',
				'label' => 'Mô tả ngắn',
				'name'  => 'voucher_short_description',
				'type'  => 'textarea',
				'rows'  => 3,
				'wrapper' => array('width' => 100),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_long_description',
				'label' => 'Mô tả chi tiết',
				'name'  => 'voucher_long_description',
				'type'  => 'textarea',
				'rows'  => 5,
				'wrapper' => array('width' => 100),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_service_guide',
				'label' => 'Hướng dẫn sử dụng',
				'name'  => 'voucher_service_guide',
				'type'  => 'textarea',
				'rows'  => 4,
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_terms',
				'label' => 'Điều kiện sử dụng',
				'name'  => 'voucher_terms',
				'type'  => 'textarea',
				'rows'  => 4,
				'wrapper' => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			// Điều kiện và điều khoản - chỉ hiển thị cho Voucher BSC
			array(
				'key'          => 'field_bsc_voucher_terms',
				'label'        => 'Điều kiện và điều khoản',
				'name'         => 'bsc_voucher_terms',
				'type'         => 'wysiwyg',
				'tabs'         => 'all',
				'toolbar'      => 'full',
				'media_upload' => 0,
				'delay'        => 0,
				'wrapper'      => array('width' => 100),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'BSC',
						),
					),
				),
			),
			array(
				'key'   => 'field_voucher_applicable_stores',
				'label' => 'Cửa hàng áp dụng',
				'name'  => 'voucher_applicable_stores',
				'type'  => 'textarea',
				'rows'  => 4,
				'instructions' => 'Tự động điền từ Got It API, bạn có thể chỉnh sửa lại nếu cần.',
				'wrapper' => array('width' => 100),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_voucher_type',
							'operator' => '==',
							'value'    => 'THIRD_PARTY',
						),
					),
				),
			),
			// Đơn vị áp dụng (Group)
			array(
				'key'   => 'field_partner_group',
				'label' => 'Đơn vị áp dụng',
				'name'  => 'partner',
				'type'  => 'group',
				'layout'=> 'row',
				'sub_fields' => array(
					array(
						'key' => 'field_partner_name',
						'label' => 'Tên đơn vị',
						'name' => 'name',
						'type' => 'text',
						'wrapper' => array('width' => 30),
					),
					array(
						'key' => 'field_partner_url',
						'label' => 'URL đơn vị áp dụng',
						'name' => 'url',
						'type' => 'url',
						'wrapper' => array('width' => 30),
					),
					array(
						'key' => 'field_partner_logo',
						'label' => 'Logo đơn vị áp dụng',
						'name' => 'logo',
						'type' => 'text',
						'instructions' => 'Lưu URL logo đơn vị áp dụng (ví dụ lấy từ brandInfo.logo của Got It).',
						'wrapper' => array('width' => 40),
					),
				),
			),
			
			array(
				'key'   => 'field_points_cost',
				'label' => 'Điểm cần để đổi voucher',
				'name'  => 'points_cost',
				'type'  => 'number',
				'min'   => 0,
				'step'  => 1,
				'required' => 1,
				'wrapper' => array('width' => 33),
			),
			
			array(
				'key'   => 'field_quantity',
				'label' => 'Số lượng',
				'name'  => 'quantity',
				'type'  => 'number',
				'min'   => 0,
				'step'  => 1,
				'required' => 1,
				'wrapper' => array('width' => 33),
			),
			
			array(
				'key'   => 'field_is_active',
				'label' => 'Bật voucher',
				'name'  => 'is_active',
				'type'  => 'true_false',
				'ui'    => 1,
				'default_value' => 1,
				'wrapper' => array('width' => 33),
			),
			
			// Thời gian hiệu lực (Group)
			array(
				'key'   => 'field_validity_group',
				'label' => 'Thời gian có hiệu lực',
				'name'  => 'validity',
				'type'  => 'group',
				'layout'=> 'row',
				'sub_fields' => array(
					array(
						'key'   => 'field_valid_from',
						'label' => 'Ngày bắt đầu',
						'name'  => 'valid_from',
						'type'  => 'date_time_picker', // nếu bản ACF không có, đổi sang 'date_picker'
						'display_format' => 'Y-m-d H:i:s',
						'return_format'  => 'Y-m-d H:i:s',
						'wrapper' => array('width' => 50),
					),
					array(
						'key'   => 'field_valid_to',
						'label' => 'Ngày kết thúc',
						'name'  => 'valid_to',
						'type'  => 'date_time_picker', // nếu bản ACF không có, đổi sang 'date_picker'
						'display_format' => 'Y-m-d H:i:s',
						'return_format'  => 'Y-m-d H:i:s',
						'wrapper' => array('width' => 50),
					),
				),
			),
			// Số lượt đổi
			array(
				'key'           => 'field_redemption_count',
				'label'         => 'Số voucher đã được đổi',
				'name'          => 'redemption_count',
				'type'          => 'number',
				'default_value' => 0,
				'min'           => 0,
				'step'          => 1,
				'wrapper'       => array('width' => 33),
			),
			
			// ảnh banner đã đổi
			array(
				'key'           => 'field_redeemed_banner_image',
				'label'         => 'Ảnh banner khi đã đổi voucher',
				'name'          => 'redeemed_banner_image',
				'type'          => 'image',
				'instructions'  => 'Ảnh hiển thị khi người chơi đã đổi voucher thành công.',
				'return_format' => 'id',
				'preview_size'  => 'medium',
				'wrapper'       => array('width' => 100),
			),
		),
		
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'game_vouchers',
				),
			),
		)
	));
	
	add_filter('acf/validate_value/key=field_voucher_code', function ($valid, $value, $field, $input) {
		
		if (!$valid) {
			return $valid; // giữ lỗi mặc định nếu có
		}
		
		if (!$value) {
			return $valid;
		}
		
		// ID đang edit (nếu có)
		$current_post_id = isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0;
		
		// Query tìm mã trùng
		$duplicate = get_posts([
			'post_type'      => 'game_vouchers',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => 'voucher_code',
					'value' => $value,
				]
			]
		]);
		
		if (!empty($duplicate)) {
			if (intval($duplicate[0]) !== $current_post_id) {
				return '⚠️ Mã voucher "' . $value . '" đã tồn tại!';
			}
		}
		
		return $valid;
	}, 10, 4);
	
	// Disable field 'redemption_count' in admin (read-only)
	add_filter('acf/prepare_field/name=redemption_count', function ($field) {
		if (is_admin()) {
			$field['disabled'] = 1;
		}
		return $field;
	});

	add_filter('acf/prepare_field/name=voucheramt', function ($field) {
		if (is_admin()) {
			unset($field['readonly']);
			$field['instructions'] = '';
		}
		return $field;
	});

	add_filter('acf/prepare_field/name=prinpaid', function ($field) {
		if (is_admin()) {
			$field['readonly'] = 1;
			$field['instructions'] = '';
		}
		return $field;
	});

	// Got It IDs are synced automatically from API, admin does not need to select manually.
	add_filter('acf/prepare_field/name=gotit_product_id', function ($field) {
		if (is_admin()) {
			$field['readonly'] = 1;
			$field['instructions'] = 'Duoc dong bo tu dong tu Got It.';
		}
		return $field;
	});

	add_filter('acf/prepare_field/name=gotit_product_price_id', function ($field) {
		if (is_admin()) {
			$field['readonly'] = 1;
			$field['instructions'] = 'Duoc dong bo tu dong tu Got It.';
		}
		return $field;
	});
	
	// Huy hiệu
	acf_add_local_field_group(array(
		'key'   => 'group_game_bsc_badge',
		'title' => 'Thiết lập Huy hiệu',
		'fields' => array(
			// thêm 1 field "THứ tự"
			array(
				'key'           => 'field_badge_order',
				'label'         => 'Thứ tự hiển thị',
				'name'          => 'badge_order',
				'type'          => 'number',
				'required'      => 1,
				'instructions'  => 'Số thứ tự để sắp xếp huy hiệu khi hiển thị (số nhỏ hơn sẽ hiển thị trước).',
				'min'           => 1,
				'step'          => 1,
				'wrapper'       => array('width' => 100),
			),
			
			array(
				'key'           => 'field_badge_image',
				'label'         => 'Hình ảnh huy hiệu',
				'name'          => 'badge_image',
				'type'          => 'image',
				'required'      => 1,
				'instructions'  => 'Ảnh biểu tượng của huy hiệu.',
				'return_format' => 'id',
				'preview_size'  => 'thumbnail',
				'wrapper'       => array('width' => 50),
			),
			array(
				'key'           => 'field_badge_effect_color',
				'label'         => 'Màu hiệu ứng',
				'name'          => 'badge_effect_color',
				'type'          => 'color_picker',
				'required'      => 0,
				'instructions'  => 'Chọn mã màu cho hiệu ứng của huy hiệu.',
				'default_value' => '#F45332',
				'return_format' => 'string',
				'wrapper'       => array('width' => 50),
			),
			array(
				'key'           => 'field_badge_task_content',
				'label'         => 'Nội dung nhiệm vụ của huy hiệu',
				'name'          => 'badge_task_content',
				'type'          => 'textarea',
				'required'      => 0,
				'instructions'  => 'Mô tả chi tiết nội dung nhiệm vụ để người chơi có thể hiểu rõ.',
				'rows'          => 4,
				'wrapper'       => array('width' => 50),
			),
			array(
				'key'           => 'field_condition_type',
				'label'         => 'Loại điều kiện nhận huy hiệu',
				'name'          => 'condition_type',
				'type'          => 'select',
				'required'      => 1,
				'choices'       => array(
					'consecutive_days' => 'Số ngày liên tiếp để nhận huy hiệu',
					'total_days'       => 'Tổng số ngày để nhận huy hiệu',
				),
				'default_value' => 'consecutive_days',
				'return_format' => 'value',
				'wrapper'       => array('width' => 100),
			),
			array(
				'key'           => 'field_consecutive_days',
				'label'         => 'Số ngày liên tiếp',
				'name'          => 'consecutive_days',
				'type'          => 'number',
				'required'      => 1,
				'instructions'  => 'Nhập số ngày người chơi đăng nhập và tham gia chơi liên tiếp để đạt huy hiệu.',
				'min'           => 1,
				'step'          => 1,
				'wrapper'       => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_condition_type',
							'operator' => '==',
							'value'    => 'consecutive_days',
						),
					),
				),
			),
			array(
				'key'           => 'field_total_days',
				'label'         => 'Tổng số ngày',
				'name'          => 'total_days',
				'type'          => 'number',
				'required'      => 1,
				'instructions'  => 'Nhập tổng số ngày (không cần liên tiếp) để đạt huy hiệu.',
				'min'           => 1,
				'step'          => 1,
				'wrapper'       => array('width' => 50),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_condition_type',
							'operator' => '==',
							'value'    => 'total_days',
						),
					),
				),
			),
			array(
				'key'           => 'field_points_reward',
				'label'         => 'Số điểm được tặng',
				'name'          => 'points_reward',
				'type'          => 'number',
				'required'      => 1,
				'instructions'  => 'Số điểm thưởng khi người chơi đạt huy hiệu.',
				'min'           => 0,
				'step'          => 1,
				'wrapper'       => array('width' => 100),
			),
		),
		'location' => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'game_badges',
				),
			),
		),
	));
	
	add_filter('acf/validate_value/key=field_badge_order', function ($valid, $value, $field, $input) {
		
		if (!$valid) {
			return $valid; // giữ lỗi mặc định nếu có
		}
		
		if (!$value) {
			return $valid;
		}
		
		// ID đang edit (nếu có)
		$current_post_id = isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0;
		
		// Query tìm mã trùng
		$duplicate = get_posts([
			'post_type'      => 'game_badges',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => 'badge_order',
					'value' => $value,
				]
			]
		]);
		
		if (!empty($duplicate)) {
			if (intval($duplicate[0]) !== $current_post_id) {
				return '⚠️ Mã ID "' . $value . '" đã tồn tại!';
			}
		}
		
		return $valid;
	}, 10, 4);


}
add_action('acf/init', 'game_bsc_register_acf_fields'); // register ACF fields (if ACF exists)
