<?php
if (!defined('ABSPATH')) exit;

/**
 * Ensure the requester can use admin test tools.
 */
function game_bsc_gotit_test_guard() {
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Permission denied.'], 403);
    }

    check_ajax_referer('game_bsc_gotit_test_nonce', 'nonce');
}

function game_bsc_gotit_to_mysql_datetime($value) {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }

    return gmdate('Y-m-d H:i:s', $ts);
}

function game_bsc_gotit_transactions_table_columns($table_name) {
    global $wpdb;

    static $cache = [];
    if (isset($cache[$table_name])) {
        return $cache[$table_name];
    }

    $columns = [];
    $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $field = sanitize_key((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
    }

    $cache[$table_name] = $columns;
    return $columns;
}

/**
 * Insert one test transaction row for audit/debug.
 */
function game_bsc_insert_gotit_test_transaction($args) {
    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';
    $table = $prefix . 'gotit_transactions';

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return ['ok' => false, 'error' => 'Table gotit_transactions does not exist.'];
    }

    $columns = game_bsc_gotit_transactions_table_columns($table);

    $data = [
        'redemption_id' => (int) ($args['redemption_id'] ?? 0),
        'user_id' => (int) ($args['user_id'] ?? 0),
        'voucher_post_id' => (int) ($args['voucher_post_id'] ?? 0),
        'transaction_ref_id' => sanitize_text_field($args['transaction_ref_id'] ?? ''),
        'gotit_order_name' => sanitize_text_field($args['gotit_order_name'] ?? ''),
        'gotit_product_id' => (int) ($args['gotit_product_id'] ?? 0),
        'gotit_product_price_id' => (int) ($args['gotit_product_price_id'] ?? 0),
        'gotit_voucher_link' => esc_url_raw($args['gotit_voucher_link'] ?? ''),
        'gotit_voucher_code' => sanitize_text_field($args['gotit_voucher_code'] ?? ''),
        'gotit_status' => (int) ($args['gotit_status'] ?? 0),
        'gotit_raw_response' => (string) ($args['gotit_raw_response'] ?? ''),
        'gotit_status_code' => (int) ($args['gotit_status_code'] ?? 0),
        'gotit_error_message' => sanitize_text_field($args['gotit_error_message'] ?? ''),
        'created_at' => game_now(),
        'updated_at' => game_now(),
    ];

    $formats = ['%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s'];

    if (!empty($columns['gotit_voucher_image'])) {
        $data['gotit_voucher_image'] = esc_url_raw($args['gotit_voucher_image'] ?? '');
        $formats[] = '%s';
    }

    if (!empty($columns['gotit_serial'])) {
        $data['gotit_serial'] = sanitize_text_field($args['gotit_serial'] ?? '');
        $formats[] = '%s';
    }

    if (!empty($columns['gotit_vendor_name'])) {
        $data['gotit_vendor_name'] = sanitize_text_field($args['gotit_vendor_name'] ?? '');
        $formats[] = '%s';
    }

    if (!empty($columns['gotit_expiry_date'])) {
        $gotit_expiry_date = game_bsc_gotit_to_mysql_datetime($args['gotit_expiry_date'] ?? '');
        if ($gotit_expiry_date !== '') {
            $data['gotit_expiry_date'] = $gotit_expiry_date;
            $formats[] = '%s';
        }
    }

    if (!empty($columns['gotit_partner_expiry_date'])) {
        $partner_expiry_date = game_bsc_gotit_to_mysql_datetime($args['gotit_partner_expiry_date'] ?? '');
        if ($partner_expiry_date !== '') {
            $data['gotit_partner_expiry_date'] = $partner_expiry_date;
            $formats[] = '%s';
        }
    }

    if (!empty($columns['gotit_is_partner_code'])) {
        $raw_partner_code = $args['gotit_is_partner_code'] ?? 0;
        $is_partner_code = in_array(strtolower((string) $raw_partner_code), ['1', 'true', 'yes'], true) ? 1 : 0;
        if ($raw_partner_code === true || $raw_partner_code === 1) {
            $is_partner_code = 1;
        }
        $data['gotit_is_partner_code'] = $is_partner_code;
        $formats[] = '%d';
    }

    $inserted = $wpdb->insert(
        $table,
        $data,
        $formats
    );

    if ($inserted === false) {
        return ['ok' => false, 'error' => $wpdb->last_error];
    }

    return ['ok' => true, 'id' => (int) $wpdb->insert_id];
}

function game_bsc_gotit_is_list_array($value) {
    return is_array($value) && array_values($value) === $value;
}

function game_bsc_gotit_extract_products_list($value) {
    if (!is_array($value)) {
        return [];
    }

    if (game_bsc_gotit_is_list_array($value)) {
        return $value;
    }

    $candidate_keys = ['products', 'items', 'list', 'rows', 'result', 'data'];
    foreach ($candidate_keys as $key) {
        if (isset($value[$key]) && is_array($value[$key])) {
            $list = game_bsc_gotit_extract_products_list($value[$key]);
            if (!empty($list)) {
                return $list;
            }
        }
    }

    return [];
}

function game_bsc_gotit_normalize_price_label($price_item) {
    if (!is_array($price_item)) {
        return '';
    }

    // ===== XỬ LÝ AMOUNT (value) TRƯỚC =====
    // Got It trả về priceInfo[].value = 50000 (số thực tế)
    // Ưu tiên: format thành "50.000đ" nếu là số hợp lệ
    $amount = 0;
    foreach (['price', 'value', 'amount', 'denomination'] as $money_key) {
        if (isset($price_item[$money_key]) && is_numeric($price_item[$money_key])) {
            $amount = (int) $price_item[$money_key];
            break;
        }
    }

    // ===== XỬ LÝ NAME =====
    // Bỏ qua name nếu là số đơn thuần ("1", "2", "50000") — không có nghĩa với người dùng
    $raw_name = trim((string) ($price_item['name'] ?? $price_item['productPriceName'] ?? ''));
    $is_meaningful_name = $raw_name !== ''
        && !ctype_digit($raw_name)                      // không phải số nguyên thuần
        && !preg_match('/^\d+$/', $raw_name);           // không phải chuỗi chỉ có chữ số

    if ($is_meaningful_name) {
        // Name đã có nghĩa (vd: "50,000đ", "100k", "Thẻ điện thoại 100.000đ") → dùng thẳng
        return $raw_name;
    }

    // Name không có nghĩa → format từ amount
    if ($amount > 0) {
        return number_format($amount, 0, ',', '.') . 'đ';
    }

    // amount = 0 nhưng có name hợp lệ → vẫn dùng name
    if ($raw_name !== '') {
        return $raw_name;
    }

    return '';
}

function game_bsc_gotit_normalize_product_prices($value) {
    $normalized = [];

    if (!is_array($value)) {
        return $normalized;
    }

    if (!game_bsc_gotit_is_list_array($value)) {
        foreach (['productPrices', 'prices', 'items', 'list', 'data'] as $nested_key) {
            if (isset($value[$nested_key]) && is_array($value[$nested_key])) {
                return game_bsc_gotit_normalize_product_prices($value[$nested_key]);
            }
        }

        if (!empty($value['productPriceId']) || !empty($value['id']) || !empty($value['priceId'])) {
            $value = [$value];
        }
    }

    foreach ($value as $price_item) {
        if (is_scalar($price_item)) {
            $price_id = (int) $price_item;
            if ($price_id > 0) {
                $normalized[] = [
                    'productPriceId' => $price_id,
                    'name' => (string) $price_id,
                    'value' => 0,
                    'label' => (string) $price_id,
                ];
            }
            continue;
        }

        if (!is_array($price_item)) {
            continue;
        }

        $price_id = 0;
        foreach (['productPriceId', 'id', 'priceId'] as $key) {
            if (!empty($price_item[$key])) {
                $price_id = (int) $price_item[$key];
                break;
            }
        }

        if ($price_id < 1) {
            continue;
        }

        $label = game_bsc_gotit_normalize_price_label($price_item);
        if ($label === '') {
            $label = (string) $price_id;
        }

        $price_name = sanitize_text_field((string) ($price_item['name'] ?? $price_item['productPriceName'] ?? ''));

        $price_value = 0;
        foreach (['value', 'price', 'amount', 'denomination'] as $value_key) {
            if (isset($price_item[$value_key]) && is_numeric($price_item[$value_key])) {
                $price_value = (int) $price_item[$value_key];
                break;
            }
        }

        $normalized[] = [
            'productPriceId' => $price_id,
            'name' => $price_name,
            'value' => $price_value,
            'label' => $label,
        ];
    }

    return $normalized;
}

function game_bsc_gotit_normalize_products($value) {
    $normalized = [];
    $products = game_bsc_gotit_extract_products_list($value);

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        $product_id = 0;
        foreach (['productId', 'id'] as $key) {
            if (!empty($product[$key])) {
                $product_id = (int) $product[$key];
                break;
            }
        }

        if ($product_id < 1) {
            continue;
        }

        $product_name = '';
        foreach (['productName', 'name', 'productTitle', 'title'] as $key) {
            if (!empty($product[$key])) {
                $product_name = sanitize_text_field((string) $product[$key]);
                break;
            }
        }

        if ($product_name === '') {
            $product_name = 'Product ' . $product_id;
        }

        $prices = [];
        foreach (['productPriceId', 'productPrices', 'prices', 'priceList', 'productPriceIds', 'priceInfo'] as $key) {
            if (!empty($product[$key]) && is_array($product[$key])) {
                $prices = game_bsc_gotit_normalize_product_prices($product[$key]);
                if (!empty($prices)) {
                    break;
                }
            }
        }

        $brand_info = [];
        if (!empty($product['brandInfo']) && is_array($product['brandInfo'])) {
            $brand_info = [
                'id' => (int) ($product['brandInfo']['id'] ?? 0),
                'name' => sanitize_text_field((string) ($product['brandInfo']['name'] ?? '')),
                'logo' => esc_url_raw((string) ($product['brandInfo']['logo'] ?? '')),
            ];
        }

        $additional_images = [];
        if (!empty($product['additionalImages']) && is_array($product['additionalImages'])) {
            foreach ($product['additionalImages'] as $img) {
                if (is_string($img) && $img !== '') {
                    $additional_images[] = esc_url_raw($img);
                }
            }
        }

        $extra_fields = [];
        if (isset($product['extraFields'])) {
            $extra_fields = is_array($product['extraFields']) ? $product['extraFields'] : [];
        }

        $normalized[] = [
            'productId'        => $product_id,
            'productName'      => $product_name,
            'prices'           => $prices,
            'image'            => esc_url_raw((string) ($product['image'] ?? '')),
            'additionalImages' => $additional_images,
            'type'             => sanitize_text_field((string) ($product['type'] ?? '')),
            'description'      => (string) ($product['description'] ?? ''),
            'shortDescription' => (string) ($product['shortDescription'] ?? ''),
            'slug'             => sanitize_text_field((string) ($product['slug'] ?? '')),
            'link'             => esc_url_raw((string) ($product['link'] ?? '')),
            'voucherType'      => sanitize_text_field((string) ($product['voucherType'] ?? '')),
            'terms'            => (string) ($product['terms'] ?? ''),
            'serviceGuide'     => (string) ($product['serviceGuide'] ?? ''),
            'brandInfo'        => $brand_info,
            'extraFields'      => $extra_fields,
            'categoryId'       => (int) ($product['categoryId'] ?? $product['category_id'] ?? 0),
            'categoryName'     => sanitize_text_field((string) ($product['categoryName'] ?? $product['category_name'] ?? '')),
            'raw'              => $product,
        ];
    }

    return $normalized;
}

function game_bsc_gotit_clean_store_text($value) {
    if (!is_scalar($value)) {
        return '';
    }

    return trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $value)));
}

function game_bsc_gotit_prepare_html_content($value) {
    if (!is_scalar($value)) {
        return '';
    }

    $raw = (string) $value;
    if ($raw === '') {
        return '';
    }

    // Got It payload can contain HTML entities (&lt;p&gt;, &agrave;, ...) that should be decoded first.
    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return wp_kses_post($decoded);
}

function game_bsc_gotit_set_voucher_html_field($post_id, $field_name, $field_key, $value) {
    $safe_value = game_bsc_gotit_prepare_html_content($value);

    // Save raw sanitized HTML directly to postmeta to preserve markup structure.
    update_post_meta($post_id, $field_name, wp_slash($safe_value));
    if ($field_key !== '') {
        update_post_meta($post_id, '_' . $field_name, $field_key);
    }

    return $safe_value;
}

function game_bsc_gotit_collect_store_names_from_node($node, &$names, $depth = 0) {
    if ($depth > 5 || !is_array($node)) {
        return;
    }

    $name_keys = ['storeName', 'storeNm', 'store_name', 'branchName', 'branchNm', 'shopName', 'outletName', 'displayName', 'name'];

    if (game_bsc_gotit_is_list_array($node)) {
        foreach ($node as $child) {
            if (is_array($child)) {
                game_bsc_gotit_collect_store_names_from_node($child, $names, $depth + 1);
            }
        }
        return;
    }

    foreach ($name_keys as $name_key) {
        if (!array_key_exists($name_key, $node) || !is_scalar($node[$name_key])) {
            continue;
        }

        $name = game_bsc_gotit_clean_store_text($node[$name_key]);
        if ($name !== '') {
            $names[$name] = true;
        }
    }

    foreach ($node as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $lower_key = strtolower((string) $key);
        $is_store_key = strpos($lower_key, 'store') !== false
            || strpos($lower_key, 'branch') !== false
            || strpos($lower_key, 'shop') !== false
            || strpos($lower_key, 'outlet') !== false
            || in_array($lower_key, ['items', 'list', 'data'], true);

        if ($is_store_key) {
            game_bsc_gotit_collect_store_names_from_node($value, $names, $depth + 1);
        }
    }
}

function game_bsc_gotit_collect_store_names_from_text($text, &$names) {
    if (!is_scalar($text)) {
        return;
    }

    $parts = preg_split('/[\r\n,;|]+/', (string) $text);
    if (empty($parts) || !is_array($parts)) {
        return;
    }

    foreach ($parts as $part) {
        $name = game_bsc_gotit_clean_store_text($part);
        if ($name !== '') {
            $names[$name] = true;
        }
    }
}

function game_bsc_gotit_normalize_store_row($store) {
    if (!is_array($store)) {
        return [];
    }

    $has_store_signal = false;
    foreach (['id', 'storeId', 'name', 'storeName', 'storeNm', 'store_name', 'branchName', 'branchNm', 'shopName', 'outletName', 'address', 'cityName', 'districtName', 'lat', 'long', 'phone'] as $signal_key) {
        if (array_key_exists($signal_key, $store)) {
            $has_store_signal = true;
            break;
        }
    }

    if (!$has_store_signal) {
        return [];
    }

    $name = '';
    foreach (['name', 'storeName', 'storeNm', 'store_name', 'branchName', 'branchNm', 'shopName', 'outletName', 'displayName'] as $name_key) {
        if (array_key_exists($name_key, $store)) {
            $name = game_bsc_gotit_clean_store_text($store[$name_key]);
            if ($name !== '') {
                break;
            }
        }
    }

    $address = '';
    foreach (['address', 'storeAddress', 'storeAddr', 'branchAddress', 'fullAddress'] as $address_key) {
        if (array_key_exists($address_key, $store)) {
            $address = game_bsc_gotit_clean_store_text($store[$address_key]);
            if ($address !== '') {
                break;
            }
        }
    }

    $email = '';
    foreach (['email', 'storeEmail', 'contactEmail'] as $email_key) {
        if (array_key_exists($email_key, $store)) {
            $email = game_bsc_gotit_clean_store_text($store[$email_key]);
            if ($email !== '') {
                break;
            }
        }
    }

    $phone = '';
    foreach (['phone', 'phoneNo', 'phoneNumber', 'tel', 'hotline', 'contactPhone'] as $phone_key) {
        if (array_key_exists($phone_key, $store)) {
            $phone = game_bsc_gotit_clean_store_text($store[$phone_key]);
            if ($phone !== '') {
                break;
            }
        }
    }

    $lat = '';
    foreach (['lat', 'latitude'] as $lat_key) {
        if (array_key_exists($lat_key, $store)) {
            $lat = game_bsc_gotit_clean_store_text($store[$lat_key]);
            if ($lat !== '') {
                break;
            }
        }
    }

    $long = '';
    foreach (['long', 'lng', 'longitude'] as $long_key) {
        if (array_key_exists($long_key, $store)) {
            $long = game_bsc_gotit_clean_store_text($store[$long_key]);
            if ($long !== '') {
                break;
            }
        }
    }

    $district_name = game_bsc_gotit_clean_store_text($store['districtName'] ?? $store['district_name'] ?? '');
    $city_name = game_bsc_gotit_clean_store_text($store['cityName'] ?? $store['city_name'] ?? '');

    return [
        'id' => (int) ($store['id'] ?? $store['storeId'] ?? $store['store_id'] ?? 0),
        'name' => $name,
        'address' => $address,
        'email' => $email,
        'phone' => $phone,
        'lat' => $lat,
        'long' => $long,
        'districtId' => (int) ($store['districtId'] ?? $store['district_id'] ?? 0),
        'districtName' => $district_name,
        'cityId' => (int) ($store['cityId'] ?? $store['city_id'] ?? 0),
        'cityName' => $city_name,
        'extraFields' => isset($store['extraFields']) && is_array($store['extraFields']) ? $store['extraFields'] : [],
        'raw' => $store,
    ];
}

function game_bsc_gotit_collect_store_rows_from_node($node, &$rows, $depth = 0) {
    if ($depth > 6 || !is_array($node)) {
        return;
    }

    if (game_bsc_gotit_is_list_array($node)) {
        foreach ($node as $child) {
            if (is_array($child)) {
                game_bsc_gotit_collect_store_rows_from_node($child, $rows, $depth + 1);
            }
        }
        return;
    }

    $normalized_row = game_bsc_gotit_normalize_store_row($node);
    if (!empty($normalized_row)) {
        $row_key = implode('|', [
            (string) ($normalized_row['id'] ?? 0),
            strtolower((string) ($normalized_row['name'] ?? '')),
            strtolower((string) ($normalized_row['address'] ?? '')),
        ]);

        if (!isset($rows[$row_key])) {
            $rows[$row_key] = $normalized_row;
        }
    }

    foreach ($node as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $lower_key = strtolower((string) $key);
        $is_store_key = strpos($lower_key, 'store') !== false
            || strpos($lower_key, 'branch') !== false
            || strpos($lower_key, 'shop') !== false
            || strpos($lower_key, 'outlet') !== false
            || in_array($lower_key, ['items', 'list', 'data', 'stores'], true);

        if ($is_store_key) {
            game_bsc_gotit_collect_store_rows_from_node($value, $rows, $depth + 1);
        }
    }
}

function game_bsc_gotit_build_fallback_store_rows_from_names($names) {
    $rows = [];
    if (!is_array($names) || empty($names)) {
        return $rows;
    }

    foreach (array_keys($names) as $name) {
        $clean_name = game_bsc_gotit_clean_store_text($name);
        if ($clean_name === '') {
            continue;
        }

        $rows[] = [
            'id' => 0,
            'name' => $clean_name,
            'address' => '',
            'email' => '',
            'phone' => '',
            'lat' => '',
            'long' => '',
            'districtId' => 0,
            'districtName' => '',
            'cityId' => 0,
            'cityName' => '',
            'extraFields' => [],
            'raw' => [],
        ];
    }

    return $rows;
}

function game_bsc_gotit_build_applicable_stores_text($stores, $fallback_names = []) {
    $lines = [];

    if (is_array($stores)) {
        foreach ($stores as $store) {
            if (!is_array($store)) {
                continue;
            }

            $name = game_bsc_gotit_clean_store_text($store['name'] ?? '');
            $address = game_bsc_gotit_clean_store_text($store['address'] ?? '');
            $phone = game_bsc_gotit_clean_store_text($store['phone'] ?? '');
            $email = game_bsc_gotit_clean_store_text($store['email'] ?? '');
            $district_name = game_bsc_gotit_clean_store_text($store['districtName'] ?? '');
            $city_name = game_bsc_gotit_clean_store_text($store['cityName'] ?? '');
            $lat = game_bsc_gotit_clean_store_text($store['lat'] ?? '');
            $long = game_bsc_gotit_clean_store_text($store['long'] ?? '');
            $store_id = (int) ($store['id'] ?? 0);

            if ($name === '' && $address === '' && $phone === '' && $email === '') {
                continue;
            }

            $segments = [];
            $header = $name !== '' ? $name : ('Store #' . ((int) ($store['id'] ?? 0)));
            if (trim($header) !== 'Store #0') {
                $segments[] = $header;
            }

            if ($store_id > 0) {
                $segments[] = 'ID: ' . $store_id;
            }

            if ($address !== '') {
                $segments[] = 'Address: ' . $address;
            }

            $location_parts = [];
            if ($district_name !== '') {
                $location_parts[] = $district_name;
            }
            if ($city_name !== '') {
                $location_parts[] = $city_name;
            }
            if (!empty($location_parts)) {
                $segments[] = 'Area: ' . implode(', ', $location_parts);
            }

            if ($phone !== '') {
                $segments[] = 'Phone: ' . $phone;
            }
            if ($email !== '') {
                $segments[] = 'Email: ' . $email;
            }
            if ($lat !== '' || $long !== '') {
                $segments[] = 'GPS: ' . trim($lat . ', ' . $long, ', ');
            }

            if (!empty($segments)) {
                $lines[] = implode(' | ', $segments);
            }
        }
    }

    if (empty($lines) && is_array($fallback_names) && !empty($fallback_names)) {
        foreach (array_keys($fallback_names) as $name) {
            $clean_name = game_bsc_gotit_clean_store_text($name);
            if ($clean_name !== '') {
                $lines[] = $clean_name;
            }
        }
    }

    return implode("\n", $lines);
}

function game_bsc_gotit_get_existing_stores_payload($post_id) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return ['text' => '', 'stores' => [], 'source' => 'none'];
    }

    $json = (string) get_post_meta($post_id, '_game_bsc_gotit_applicable_stores_json', true);
    $stores = json_decode($json, true);
    if (!is_array($stores)) {
        $stores = [];
    }

    $text = (string) get_post_meta($post_id, 'voucher_applicable_stores', true);
    if ($text === '' && !empty($stores)) {
        $text = game_bsc_gotit_build_applicable_stores_text($stores);
    }

    $source = sanitize_text_field((string) get_post_meta($post_id, '_game_bsc_gotit_applicable_stores_source', true));
    if ($source === '') {
        $source = 'existing_meta';
    }

    return [
        'text' => $text,
        'stores' => $stores,
        'source' => $source,
    ];
}

function game_bsc_gotit_extract_applicable_stores_text($product) {
    if (!is_array($product)) {
        return '';
    }

    $candidate_nodes = [];
    foreach ($product as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $lower_key = strtolower((string) $key);
        if (strpos($lower_key, 'store') !== false
            || strpos($lower_key, 'branch') !== false
            || strpos($lower_key, 'shop') !== false
            || strpos($lower_key, 'outlet') !== false) {
            $candidate_nodes[] = $value;
        }
    }

    if (!empty($product['data']) && is_array($product['data'])) {
        foreach ($product['data'] as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $lower_key = strtolower((string) $key);
            if (strpos($lower_key, 'store') !== false
                || strpos($lower_key, 'branch') !== false
                || strpos($lower_key, 'shop') !== false
                || strpos($lower_key, 'outlet') !== false) {
                $candidate_nodes[] = $value;
            }
        }
    }

    $names = [];
    foreach ($candidate_nodes as $node) {
        game_bsc_gotit_collect_store_names_from_node($node, $names, 0);
    }

    if (!empty($product['extraFields']) && is_array($product['extraFields'])) {
        foreach ($product['extraFields'] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_key = strtolower((string) ($field['key'] ?? $field['name'] ?? ''));
            if ($field_key === ''
                || (strpos($field_key, 'store') === false
                    && strpos($field_key, 'branch') === false
                    && strpos($field_key, 'shop') === false
                    && strpos($field_key, 'outlet') === false)) {
                continue;
            }

            game_bsc_gotit_collect_store_names_from_node($field, $names, 0);
            foreach (['value', 'values', 'content', 'label'] as $value_key) {
                if (array_key_exists($value_key, $field)) {
                    game_bsc_gotit_collect_store_names_from_text($field[$value_key], $names);
                }
            }
        }
    }

    if (empty($names)) {
        return '';
    }

    return implode("\n", array_keys($names));
}

function game_bsc_gotit_extract_total_pages_from_stores_result($result) {
    $raw = (string) ($result['raw'] ?? '');
    if ($raw === '') {
        return 1;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return 1;
    }

    $candidates = [
        (int) ($decoded['pagination']['totalPage'] ?? 0),
        (int) ($decoded['pagination']['lastPage'] ?? 0),
        (int) ($decoded['data']['pagination']['totalPage'] ?? 0),
        (int) ($decoded['data']['pagination']['lastPage'] ?? 0),
        (int) ($decoded['data'][0]['storePagination']['totalPage'] ?? 0),
        (int) ($decoded['data'][0]['pagination']['totalPage'] ?? 0),
        (int) ($decoded['data'][0]['pagination']['lastPage'] ?? 0),
    ];

    return max(1, ...$candidates);
}

function game_bsc_gotit_fetch_applicable_stores_from_api($client, $product_id) {
    $product_id = (int) $product_id;
    if (!is_object($client) || $product_id < 1) {
        return [
            'text' => '',
            'stores' => [],
            'source' => 'stores_api',
            'error' => 'invalid_client_or_product',
            'http_code' => 0,
        ];
    }

    $names = [];
    $rows = [];
    $last_error = '';
    $last_http_code = 0;
    $page = 1;
    $page_size = 100;
    $max_pages = 20;
    $total_pages = 1;

    do {
        $result = $client->get_product_stores($product_id, $page, $page_size);
        if (empty($result['success'])) {
            $last_error = sanitize_text_field((string) ($result['error'] ?? 'cannot_fetch_product_stores'));
            $last_http_code = (int) ($result['http_code'] ?? 0);
            break;
        }

        if (is_array($result['data'] ?? null)) {
            game_bsc_gotit_collect_store_names_from_node($result['data'], $names, 0);
            game_bsc_gotit_collect_store_rows_from_node($result['data'], $rows, 0);
        }

        $total_pages = game_bsc_gotit_extract_total_pages_from_stores_result($result);
        $page++;
    } while ($page <= $total_pages && $page <= $max_pages);

    $stores = array_values($rows);
    if (empty($stores) && !empty($names)) {
        $stores = game_bsc_gotit_build_fallback_store_rows_from_names($names);
    }

    return [
        'text' => game_bsc_gotit_build_applicable_stores_text($stores, $names),
        'stores' => $stores,
        'source' => 'stores_api',
        'error' => $last_error,
        'http_code' => $last_http_code,
    ];
}

function game_bsc_gotit_pick_scalar_recursive($payload, $keys) {
    if (!is_array($payload)) {
        return null;
    }

    foreach ($keys as $key) {
        if (array_key_exists($key, $payload) && is_scalar($payload[$key])) {
            return $payload[$key];
        }
    }

    foreach ($payload as $value) {
        if (is_array($value)) {
            $found = game_bsc_gotit_pick_scalar_recursive($value, $keys);
            if ($found !== null && $found !== '') {
                return $found;
            }
        }
    }

    return null;
}

function game_bsc_gotit_collect_issue_candidates($payload) {
    $candidates = [];
    if (!is_array($payload)) {
        return $candidates;
    }

    $candidates[] = $payload;

    foreach (['voucher', 'item', 'data'] as $key) {
        if (isset($payload[$key]) && is_array($payload[$key])) {
            $candidates[] = $payload[$key];
        }
    }

    foreach (['vouchers', 'items', 'list'] as $key) {
        if (!isset($payload[$key]) || !is_array($payload[$key])) {
            continue;
        }

        if (game_bsc_gotit_is_list_array($payload[$key])) {
            foreach ($payload[$key] as $row) {
                if (is_array($row)) {
                    $candidates[] = $row;
                }
            }
        } else {
            $candidates[] = $payload[$key];
        }
    }

    if (game_bsc_gotit_is_list_array($payload)) {
        foreach ($payload as $row) {
            if (is_array($row)) {
                $candidates[] = $row;
            }
        }
    }

    return $candidates;
}

function game_bsc_gotit_pick_issue_value($candidates, $keys) {
    foreach ($candidates as $candidate) {
        $value = game_bsc_gotit_pick_scalar_recursive($candidate, $keys);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return null;
}

function game_bsc_gotit_extract_issue_data($payload) {
    $candidates = game_bsc_gotit_collect_issue_candidates(is_array($payload) ? $payload : []);

    $voucher_code = sanitize_text_field((string) (game_bsc_gotit_pick_issue_value($candidates, ['voucherCode', 'voucher_code', 'code']) ?? ''));
    $voucher_link = esc_url_raw((string) (game_bsc_gotit_pick_issue_value($candidates, ['voucherLink', 'voucher_link', 'link', 'url']) ?? ''));
    $voucher_image = esc_url_raw((string) (game_bsc_gotit_pick_issue_value($candidates, ['image', 'voucherImage', 'voucher_image', 'imageUrl', 'image_url']) ?? ''));
    $voucher_serial = sanitize_text_field((string) (game_bsc_gotit_pick_issue_value($candidates, ['serial', 'serialNo', 'serial_no', 'voucherSerial', 'voucher_serial']) ?? ''));
    $expiry_date = sanitize_text_field((string) (game_bsc_gotit_pick_issue_value($candidates, ['expiryDate', 'expiry_date', 'expiredDate', 'expired_date', 'validTo', 'valid_to']) ?? ''));
    $vendor_name = sanitize_text_field((string) (game_bsc_gotit_pick_issue_value($candidates, ['vendorName', 'vendor_name', 'vendor', 'partnerName', 'partner_name']) ?? ''));

    $status_raw = game_bsc_gotit_pick_issue_value($candidates, ['status', 'state', 'stateCode', 'state_code', 'newStateCode']);
    $status = is_numeric($status_raw) ? (int) $status_raw : 0;

    $is_partner_raw = game_bsc_gotit_pick_issue_value($candidates, ['isPartnerCode', 'is_partner_code', 'partnerCode', 'partner_code', 'isPartner']);
    $is_partner_code = 0;
    if ($is_partner_raw !== null) {
        $is_partner_code = in_array(strtolower((string) $is_partner_raw), ['1', 'true', 'yes'], true) ? 1 : 0;
        if ($is_partner_raw === true || $is_partner_raw === 1) {
            $is_partner_code = 1;
        }
    }

    return [
        'voucher_code' => $voucher_code,
        'voucher_link' => $voucher_link,
        'voucher_image' => $voucher_image,
        'voucher_serial' => $voucher_serial,
        'expiry_date' => $expiry_date,
        'vendor_name' => $vendor_name,
        'status' => $status,
        'is_partner_code' => $is_partner_code,
    ];
}

function game_bsc_gotit_extract_vouchers_from_ref_payload($payload) {
    $rows = [];
    if (!is_array($payload)) {
        return $rows;
    }

    $stack = [$payload];
    while (!empty($stack)) {
        $node = array_pop($stack);
        if (!is_array($node)) {
            continue;
        }

        if (isset($node['vouchers']) && is_array($node['vouchers'])) {
            foreach ($node['vouchers'] as $voucher) {
                if (is_array($voucher)) {
                    $rows[] = $voucher;
                }
            }
        }

        if (isset($node['data']) && is_array($node['data'])) {
            $stack[] = $node['data'];
        }

        if (game_bsc_gotit_is_list_array($node)) {
            foreach ($node as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }
    }

    if (empty($rows)) {
        return $rows;
    }

    $unique = [];
    $deduped = [];
    foreach ($rows as $idx => $voucher) {
        $key = implode('|', [
            sanitize_text_field((string) ($voucher['serial'] ?? '')),
            sanitize_text_field((string) ($voucher['code'] ?? '')),
            esc_url_raw((string) ($voucher['link'] ?? '')),
            (string) $idx,
        ]);

        if (isset($unique[$key])) {
            continue;
        }

        $unique[$key] = true;
        $deduped[] = $voucher;
    }

    return $deduped;
}

function game_bsc_gotit_extract_ref_pagination($payload) {
    if (!is_array($payload)) {
        return [];
    }

    $sources = [$payload];
    if (isset($payload['data']) && is_array($payload['data'])) {
        $sources[] = $payload['data'];
    }

    foreach ($sources as $source) {
        if (empty($source['pagination']) || !is_array($source['pagination'])) {
            continue;
        }

        return [
            'page' => (int) ($source['pagination']['page'] ?? 0),
            'pageSize' => (int) ($source['pagination']['pageSize'] ?? 0),
            'totalPage' => (int) ($source['pagination']['totalPage'] ?? 0),
        ];
    }

    return [];
}

function game_bsc_gotit_build_ref_voucher_summary($vouchers) {
    $summary = [
        'total' => 0,
        'used' => 0,
        'unused' => 0,
        'states' => [],
        'first_used_info' => null,
    ];

    if (!is_array($vouchers) || empty($vouchers)) {
        return $summary;
    }

    $summary['total'] = count($vouchers);
    $states = [];

    foreach ($vouchers as $voucher) {
        if (!is_array($voucher)) {
            continue;
        }

        $state_info = [];
        if (isset($voucher['stateInfo']) && is_array($voucher['stateInfo'])) {
            $state_info = $voucher['stateInfo'];
        } elseif (isset($voucher['state_info']) && is_array($voucher['state_info'])) {
            $state_info = $voucher['state_info'];
        }

        $state_code = 0;
        foreach (['code', 'stateCode', 'state_code', 'state', 'status'] as $state_key) {
            if (isset($state_info[$state_key]) && is_numeric($state_info[$state_key])) {
                $state_code = (int) $state_info[$state_key];
                break;
            }

            if (isset($voucher[$state_key]) && is_numeric($voucher[$state_key])) {
                $state_code = (int) $voucher[$state_key];
                break;
            }
        }

        $state_text = sanitize_text_field((string) ($state_info['status'] ?? $state_info['name'] ?? ''));
        if ($state_code > 0) {
            if (!isset($states[$state_code])) {
                $states[$state_code] = [
                    'code' => $state_code,
                    'status' => $state_text,
                    'count' => 0,
                ];
            }

            $states[$state_code]['count']++;
            if ($states[$state_code]['status'] === '' && $state_text !== '') {
                $states[$state_code]['status'] = $state_text;
            }
        }

        $used_info = null;
        if (isset($voucher['usedInfo']) && is_array($voucher['usedInfo'])) {
            $used_info = $voucher['usedInfo'];
        } elseif (isset($voucher['used_info']) && is_array($voucher['used_info'])) {
            $used_info = $voucher['used_info'];
        }

        $has_used_data = false;
        if (is_array($used_info)) {
            foreach ($used_info as $value) {
                if ($value !== null && $value !== '') {
                    $has_used_data = true;
                    break;
                }
            }
        }

        if ($state_code === 4 || $has_used_data) {
            $summary['used']++;

            if ($summary['first_used_info'] === null && is_array($used_info)) {
                $summary['first_used_info'] = [
                    'store' => sanitize_text_field((string) ($used_info['store'] ?? $used_info['storeName'] ?? '')),
                    'time' => sanitize_text_field((string) ($used_info['time'] ?? '')),
                    'brand_name' => sanitize_text_field((string) ($used_info['brandName'] ?? $used_info['brand_name'] ?? '')),
                    'method' => sanitize_text_field((string) ($used_info['method'] ?? '')),
                ];
            }
        }
    }

    ksort($states);
    $summary['states'] = array_values($states);
    $summary['unused'] = max(0, (int) $summary['total'] - (int) $summary['used']);

    return $summary;
}

function game_bsc_gotit_set_voucher_field($post_id, $field_name, $value) {
    if (function_exists('update_field')) {
        update_field($field_name, $value, $post_id);
        return;
    }

    update_post_meta($post_id, $field_name, $value);
}

function game_bsc_gotit_extract_amount_from_text($text) {
    $raw = strtolower(trim((string) $text));
    if ($raw === '') {
        return 0;
    }

    $digits = preg_replace('/[^0-9]/', '', $raw);
    if ($digits === '') {
        return 0;
    }

    $amount = (int) $digits;
    if ($amount < 1000 && strpos($raw, 'k') !== false) {
        $amount *= 1000;
    }

    return $amount;
}

function game_bsc_gotit_get_existing_voucher_post_id($product_id, $price_id) {
    $posts = get_posts([
        'post_type' => 'game_vouchers',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => 'voucher_type',
                'value' => 'THIRD_PARTY',
                'compare' => '=',
            ],
            [
                'key' => 'gotit_product_id',
                'value' => (string) ((int) $product_id),
                'compare' => '=',
            ],
            [
                'key' => 'gotit_product_price_id',
                'value' => (string) ((int) $price_id),
                'compare' => '=',
            ],
        ],
    ]);

    return !empty($posts) ? (int) $posts[0] : 0;
}

function game_bsc_gotit_find_category_term_id_by_gotit_id($gotit_category_id, $force_refresh = false) {
    static $cache = [];

    $gotit_category_id = (int) $gotit_category_id;
    if ($gotit_category_id < 1 || !taxonomy_exists('game_voucher_category')) {
        return 0;
    }

    if (!$force_refresh && array_key_exists($gotit_category_id, $cache)) {
        return (int) $cache[$gotit_category_id];
    }

    $terms = get_terms([
        'taxonomy'   => 'game_voucher_category',
        'hide_empty' => false,
        'fields'     => 'ids',
        'number'     => 1,
        'meta_query' => [
            [
                'key'     => '_gotit_category_id',
                'value'   => (string) $gotit_category_id,
                'compare' => '=',
            ],
        ],
    ]);

    if (!is_wp_error($terms) && !empty($terms)) {
        $cache[$gotit_category_id] = (int) $terms[0];
        return (int) $cache[$gotit_category_id];
    }

    $cache[$gotit_category_id] = 0;
    return 0;
}

function game_bsc_gotit_upsert_category_term($category_name, $gotit_category_id = 0, $category_slug = '', $category_image = null) {
    if (!taxonomy_exists('game_voucher_category')) {
        return ['term_id' => 0, 'created' => false];
    }

    $gotit_category_id = (int) $gotit_category_id;
    $category_name = trim((string) $category_name);
    $category_slug = sanitize_title((string) $category_slug);
    $should_sync_image = $category_image !== null;
    if ($should_sync_image) {
        $category_image = esc_url_raw((string) $category_image);
    }

    if ($category_name === '') {
        $category_name = __('Chưa phân loại', WG_GAME_PLUGIN_TEXTDOMAIN);
    }

    $term_id = 0;

    if ($gotit_category_id > 0) {
        $term_id = game_bsc_gotit_find_category_term_id_by_gotit_id($gotit_category_id);
    }

    if ($term_id < 1) {
        $term = term_exists($category_name, 'game_voucher_category');
        if ($term) {
            $term_id = (int) (is_array($term) ? ($term['term_id'] ?? 0) : $term);
        }
    }

    $created = false;
    if ($term_id < 1) {
        $insert_args = [];
        if ($category_slug !== '') {
            $insert_args['slug'] = $category_slug;
        }

        $created_term = wp_insert_term($category_name, 'game_voucher_category', $insert_args);
        if (is_wp_error($created_term) || empty($created_term['term_id'])) {
            return ['term_id' => 0, 'created' => false];
        }

        $term_id = (int) $created_term['term_id'];
        $created = true;
    }

    $current_term = get_term($term_id, 'game_voucher_category');
    if ($current_term && !is_wp_error($current_term)) {
        $update_args = [];
        if ($category_name !== '' && $current_term->name !== $category_name) {
            $update_args['name'] = $category_name;
        }
        if ($category_slug !== '' && $current_term->slug !== $category_slug) {
            $update_args['slug'] = $category_slug;
        }
        if (!empty($update_args)) {
            wp_update_term($term_id, 'game_voucher_category', $update_args);
        }
    }

    if ($gotit_category_id > 0) {
        update_term_meta($term_id, '_gotit_category_id', $gotit_category_id);
    }

    if ($should_sync_image) {
        if ($category_image !== '') {
            update_term_meta($term_id, '_gotit_category_image', $category_image);
        } else {
            delete_term_meta($term_id, '_gotit_category_image');
        }
    }

    if ($gotit_category_id > 0) {
        game_bsc_gotit_find_category_term_id_by_gotit_id($gotit_category_id, true);
    }

    return ['term_id' => $term_id, 'created' => $created];
}

function game_bsc_gotit_assign_voucher_category($post_id, $category_name, $append = false, $gotit_category_id = 0, $category_image = '') {
    if (!taxonomy_exists('game_voucher_category')) {
        return;
    }

    $category_name = trim((string) $category_name);
    if ($category_name === '') {
        $category_name = __('Chưa phân loại', WG_GAME_PLUGIN_TEXTDOMAIN);
    }

    $upsert_image = $category_image !== '' ? $category_image : null;
    $upsert = game_bsc_gotit_upsert_category_term($category_name, (int) $gotit_category_id, '', $upsert_image);
    $term_id = (int) ($upsert['term_id'] ?? 0);

    if ($term_id > 0) {
        // $append=false (lần đầu): xóa category cũ/stale, set category mới
        // $append=true  (lần 2+) : product ở nhiều category → giữ cũ, thêm mới
        wp_set_object_terms($post_id, [$term_id], 'game_voucher_category', $append);
    }
}

function game_bsc_gotit_async_sync_default_state() {
    return [
        'job_id' => '',
        'status' => 'idle',
        'message' => '',
        'queued_at' => '',
        'started_at' => '',
        'finished_at' => '',
        'requested_by' => 0,
        'gotit_category_id' => 0,
        'filter_category' => '',
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'products_count' => 0,
        'detail_calls' => 0,
        'errors_count' => 0,
        'last_error' => '',
    ];
}

function game_bsc_gotit_async_sync_get_state() {
    $stored = get_option('game_bsc_gotit_async_sync_state', []);
    if (!is_array($stored)) {
        $stored = [];
    }

    return array_merge(game_bsc_gotit_async_sync_default_state(), $stored);
}

function game_bsc_gotit_async_sync_update_state($patch) {
    $current = game_bsc_gotit_async_sync_get_state();
    $next = array_merge($current, is_array($patch) ? $patch : []);
    update_option('game_bsc_gotit_async_sync_state', $next, false);
    return $next;
}

function game_bsc_sync_gotit_products_to_vouchers($args = []) {
    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        return ['success' => false, 'message' => 'Got It API key is not configured.'];
    }

    $max_pages     = isset($args['max_pages']) ? max(1, (int) $args['max_pages']) : 30;
    $source        = sanitize_text_field((string) ($args['source'] ?? 'manual'));
    $filter_cat_id = isset($args['gotit_category_id']) ? (int) $args['gotit_category_id'] : 0;

    // ===== FILTERS =====
    $excluded_raw  = (string) game_bsc_gotit_source_value('excluded_product_ids', '');
    $excluded_ids  = array_flip(array_filter(array_map('intval', explode(',', $excluded_raw))));
    $min_price_val = max(0, (int) game_bsc_gotit_source_value('min_price_value', 1000));

    // ===== STEP 1: LẤY CATEGORIES MAP =====
    $categories_map = $client->get_categories_map();

    // ===== COUNTERS =====
    $created      = 0;
    $updated      = 0;
    $skipped      = 0;
    $errors       = [];
    $detail_api_calls = 0;
    $written_keys = []; // pid:price_id → post_id

    // ===== CACHE CATEGORY CHO TỪNG PRODUCT =====
    // Got It /products list KHÔNG trả categoryId
    // → Phải gọi /products/:productId (detail) để lấy categoryId
    // Cache kết quả theo productId để tránh call trùng
    $product_category_cache = []; // productId → ['cat_id' => int, 'cat_name' => string]

    // ===== STEP 2: FETCH ALL PRODUCTS (1 LẦN, KHÔNG LOOP CATEGORY) =====
    $extra_filters = [];
    if ($filter_cat_id > 0) {
        $extra_filters['categoryId'] = $filter_cat_id;
    }

    $page        = 1;
    $total_pages = 1;
    $product_stores_cache = []; // productId -> ['text' => string, 'stores' => array, 'source' => string]

    do {
        $result = $client->get_products($page, 100, $extra_filters);

        if (empty($result['success'])) {
            $errors[] = [
                'message'   => (string) ($result['error'] ?? 'Cannot fetch products.'),
                'http_code' => (int) ($result['http_code'] ?? 0),
            ];
            break;
        }

        foreach (game_bsc_gotit_normalize_products($result['data'] ?? null) as $product) {
            $pid    = (int) ($product['productId'] ?? 0);
            $prices = is_array($product['prices'] ?? null) ? $product['prices'] : [];

            if ($pid < 1 || empty($prices)) { $skipped++; continue; }
            if (isset($excluded_ids[$pid]))  { $skipped++; continue; }

            // ===== STEP 3: LẤY CATEGORY TỪ /products/:productId (detail) =====
            if (!isset($product_category_cache[$pid])) {
                $detail_cat_id   = 0;
                $detail_cat_name = '';

                if ($filter_cat_id > 0) {
                    // Sync theo 1 category → không cần gọi detail, dùng category đã chọn
                    $detail_cat_id   = $filter_cat_id;
                    $detail_cat_name = $categories_map[$filter_cat_id]['name'] ?? ('Category ' . $filter_cat_id);
                } else {
                    // Ưu tiên lấy category đã có trong list products để giảm số lần gọi detail API.
                    $detail_cat_id = (int) ($product['categoryId'] ?? 0);
                    $detail_cat_name = sanitize_text_field((string) ($product['categoryName'] ?? ''));

                    if ($detail_cat_id > 0 && $detail_cat_name === '') {
                        $detail_cat_name = $categories_map[$detail_cat_id]['name'] ?? '';
                    }

                    if ($detail_cat_id < 1) {
                        $detail_api_calls++;
                        $detail = $client->get_product_detail($pid);
                        if (!empty($detail['success']) && is_array($detail['data'])) {
                            $d = $detail['data'];
                            // Got It trả detail dạng array [0 => {product}] → unwrap
                            if (isset($d[0]) && is_array($d[0]) && !isset($d['categoryId'])) {
                                $d = $d[0];
                            }
                            // Tìm categoryId trong response detail
                            foreach (['categoryId', 'category_id'] as $ck) {
                                if (!empty($d[$ck])) {
                                    $detail_cat_id = (int) $d[$ck];
                                    break;
                                }
                            }
                            // Tìm categoryName
                            foreach (['categoryName', 'category_name'] as $ck) {
                                if (!empty($d[$ck])) {
                                    $detail_cat_name = sanitize_text_field((string) $d[$ck]);
                                    break;
                                }
                            }
                            // Fallback: nested category object
                            if ($detail_cat_id < 1 && !empty($d['category']) && is_array($d['category'])) {
                                $detail_cat_id   = (int) ($d['category']['id'] ?? 0);
                                $detail_cat_name = sanitize_text_field((string) ($d['category']['name'] ?? ''));
                            }
                            // Fallback: categories array
                            if ($detail_cat_id < 1 && !empty($d['categories']) && is_array($d['categories'])) {
                                $first_cat = reset($d['categories']);
                                if (is_array($first_cat)) {
                                    $detail_cat_id   = (int) ($first_cat['id'] ?? 0);
                                    $detail_cat_name = sanitize_text_field((string) ($first_cat['name'] ?? ''));
                                }
                            }
                            // Tra tên từ map nếu có id nhưng không có name
                            if ($detail_cat_id > 0 && $detail_cat_name === '') {
                                $detail_cat_name = $categories_map[$detail_cat_id]['name'] ?? '';
                            }
                        }
                    }
                }

                $product_category_cache[$pid] = [
                    'cat_id'   => $detail_cat_id,
                    'cat_name' => $detail_cat_name,
                ];
            }

            $prod_cat = $product_category_cache[$pid];

            if (!isset($product_stores_cache[$pid])) {
                $stores_payload = game_bsc_gotit_fetch_applicable_stores_from_api($client, $pid);
                if (!is_array($stores_payload)) {
                    $stores_payload = [
                        'text' => '',
                        'stores' => [],
                        'source' => 'stores_api',
                    ];
                }

                $stores_text = (string) ($stores_payload['text'] ?? '');
                if ($stores_text === '') {
                    $stores_text = game_bsc_gotit_extract_applicable_stores_text($product);
                    if ($stores_text !== '') {
                        $stores_payload['text'] = $stores_text;
                        $stores_payload['source'] = 'product_payload';

                        if (empty($stores_payload['stores']) || !is_array($stores_payload['stores'])) {
                            $fallback_names = [];
                            game_bsc_gotit_collect_store_names_from_text($stores_text, $fallback_names);
                            $stores_payload['stores'] = game_bsc_gotit_build_fallback_store_rows_from_names($fallback_names);
                        }
                    }
                }

                if (!isset($stores_payload['source']) || !is_scalar($stores_payload['source'])) {
                    $stores_payload['source'] = 'stores_api';
                }

                if (empty($stores_payload['stores']) || !is_array($stores_payload['stores'])) {
                    $stores_payload['stores'] = [];
                }

                $product_stores_cache[$pid] = $stores_payload;
            }

            $applicable_stores_data = is_array($product_stores_cache[$pid] ?? null) ? $product_stores_cache[$pid] : [
                'text' => '',
                'stores' => [],
                'source' => 'stores_api',
            ];

            $product_name      = sanitize_text_field((string) ($product['productName'] ?? ('Product ' . $pid)));
            $brand_name        = sanitize_text_field((string) (($product['brandInfo']['name'] ?? '') ?: $product_name));
            $brand_logo_url    = esc_url_raw((string) ($product['brandInfo']['logo'] ?? ''));
            $product_link      = esc_url_raw((string) ($product['link'] ?? ''));
            $product_image     = esc_url_raw((string) ($product['image'] ?? ''));
            $short_description = game_bsc_gotit_prepare_html_content((string) ($product['shortDescription'] ?? ''));
            $description       = game_bsc_gotit_prepare_html_content((string) ($product['description'] ?? ''));
            $service_guide     = game_bsc_gotit_prepare_html_content((string) ($product['serviceGuide'] ?? ''));
            $terms             = game_bsc_gotit_prepare_html_content((string) ($product['terms'] ?? ''));
            $applicable_stores = (string) ($applicable_stores_data['text'] ?? '');
            $applicable_stores_structured = is_array($applicable_stores_data['stores'] ?? null)
                ? array_values($applicable_stores_data['stores'])
                : [];

            foreach ($prices as $price) {
                $price_id = (int) ($price['productPriceId'] ?? 0);
                if ($price_id < 1) { $skipped++; continue; }

                // Bỏ qua mệnh giá test (quá nhỏ)
                $raw_price_val = 0;
                foreach (['value', 'price', 'amount', 'denomination'] as $vk) {
                    if (isset($price[$vk]) && is_numeric($price[$vk])) {
                        $raw_price_val = (int) $price[$vk];
                        break;
                    }
                }
                if ($min_price_val > 0 && $raw_price_val > 0 && $raw_price_val < $min_price_val) {
                    $skipped++; continue;
                }

                $sync_key = $pid . ':' . $price_id;
                $post_id  = game_bsc_gotit_get_existing_voucher_post_id($pid, $price_id);
                $is_new   = $post_id < 1;

                if (!isset($written_keys[$sync_key])) {
                    $written_keys[$sync_key] = true;

                    $price_label   = sanitize_text_field((string) ($price['label'] ?? (string) $price_id));
                    $price_name    = sanitize_text_field((string) ($price['name'] ?? ''));
                    $price_value   = isset($price['value']) && is_numeric($price['value']) ? (int) $price['value'] : 0;
                    $title_suffix  = $price_name !== '' ? $price_name : $price_label;
                    $voucher_title = trim($product_name . ' - ' . $title_suffix);
                    if ($voucher_title === '-') {
                        $voucher_title = 'Voucher Got It ' . $pid . '-' . $price_id;
                    }

                    if ($is_new) {
                        $post_id = wp_insert_post([
                            'post_type'    => 'game_vouchers',
                            'post_status'  => 'publish',
                            'post_title'   => $voucher_title,
                            'post_content' => ($description !== '' ? $description : $short_description),
                        ], true);

                        if (is_wp_error($post_id) || !$post_id) {
                            $errors[] = [
                                'product_id' => $pid,
                                'price_id'   => $price_id,
                                'message'    => is_wp_error($post_id) ? $post_id->get_error_message() : 'Cannot create post.',
                            ];
                            continue;
                        }
                        $created++;
                    } else {
                        wp_update_post([
                            'ID'           => $post_id,
                            'post_title'   => $voucher_title,
                            'post_content' => ($description !== '' ? $description : $short_description),
                        ]);
                        $updated++;
                    }

                    $points_locked = (int) get_post_meta($post_id, '_game_bsc_points_cost_locked', true) === 1;
                    $current_points = (int) (get_field('points_cost', $post_id) ?? 0);
                    $selected_value = $price_value > 0 ? (string) $price_value : $price_label;
                    $points_cost    = $current_points > 0 ? $current_points
                                      : ($price_value > 0 ? $price_value : game_bsc_gotit_extract_amount_from_text($price_label));

                    // If stores API did not return structured rows, preserve existing detailed payload if any.
                    if (empty($applicable_stores_structured)) {
                        $existing_stores_payload = game_bsc_gotit_get_existing_stores_payload($post_id);
                        if (!empty($existing_stores_payload['stores'])) {
                            $applicable_stores_structured = array_values($existing_stores_payload['stores']);
                            if ($applicable_stores === '') {
                                $applicable_stores = (string) ($existing_stores_payload['text'] ?? '');
                            }
                            $applicable_stores_data['source'] = 'existing_meta_cache';
                        }
                    }

                    if ($applicable_stores === '' && !empty($applicable_stores_structured)) {
                        $applicable_stores = game_bsc_gotit_build_applicable_stores_text($applicable_stores_structured);
                    }

                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_type',             'THIRD_PARTY');
                    game_bsc_gotit_set_voucher_field($post_id, 'gotit_product_id',          $pid);
                    game_bsc_gotit_set_voucher_field($post_id, 'gotit_product_price_id',    $price_id);

                    if ((string) get_field('voucher_code', $post_id) === '') {
                        game_bsc_gotit_set_voucher_field($post_id, 'voucher_code', 'GOTIT-' . $pid . '-' . $price_id);
                    }

                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_display_name',      $product_name);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_brand_name',         $brand_name);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_brand_logo_url',     $brand_logo_url);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_selected_value',     $selected_value);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_link_url',           $product_link);
                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_image_url',          $product_image);

                    // Keep HTML structure in DB for description fields.
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_short_description', 'field_voucher_short_description', $short_description);
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_long_description', 'field_voucher_long_description', $description);
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_service_guide', 'field_voucher_service_guide', $service_guide);
                    game_bsc_gotit_set_voucher_html_field($post_id, 'voucher_terms', 'field_voucher_terms', $terms);

                    game_bsc_gotit_set_voucher_field($post_id, 'voucher_applicable_stores',  $applicable_stores);

                    $stores_json = wp_json_encode($applicable_stores_structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($stores_json === false) {
                        $stores_json = '[]';
                    }
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_json', $stores_json);
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_count', count($applicable_stores_structured));
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_source', sanitize_text_field((string) ($applicable_stores_data['source'] ?? 'stores_api')));
                    update_post_meta($post_id, '_game_bsc_gotit_applicable_stores_last_error', sanitize_text_field((string) ($applicable_stores_data['error'] ?? '')));

                    // THIRD_PARTY/Got It vouchers do not use validity date range.
                    game_bsc_gotit_set_voucher_field($post_id, 'validity', [
                        'valid_from' => '',
                        'valid_to' => '',
                    ]);
                    game_bsc_gotit_set_voucher_field($post_id, 'partner', [
                        'name' => $brand_name,
                        'url'  => $product_link,
                        'logo' => $brand_logo_url,
                    ]);
                    game_bsc_gotit_set_voucher_field($post_id, 'is_active',   1);
                    if (!$points_locked) {
                        game_bsc_gotit_set_voucher_field($post_id, 'points_cost', max(0, (int) $points_cost));
                    }

                    if ((int) (get_field('quantity', $post_id) ?? 0) < 1) {
                        game_bsc_gotit_set_voucher_field($post_id, 'quantity', 999999);
                    }

                    update_post_meta($post_id, '_game_bsc_gotit_sync_key',    $sync_key);
                    update_post_meta($post_id, '_game_bsc_gotit_synced_at',   current_time('mysql'));
                    update_post_meta($post_id, '_game_bsc_gotit_sync_source', $source);

                    $written_keys[$sync_key] = $post_id;

                } else {
                    $skipped++;
                }

                // ===== GÁN DANH MỤC =====
                // Category lấy từ /products/:productId (detail API)
                if ($post_id > 0) {
                    update_post_meta($post_id, '_game_bsc_gotit_category_id', $prod_cat['cat_id']);

                    $cat_image = '';
                    if ((int) ($prod_cat['cat_id'] ?? 0) > 0 && !empty($categories_map[$prod_cat['cat_id']]['image'])) {
                        $cat_image = esc_url_raw((string) $categories_map[$prod_cat['cat_id']]['image']);
                    }

                    game_bsc_gotit_assign_voucher_category(
                        $post_id,
                        $prod_cat['cat_name'],
                        false,
                        (int) ($prod_cat['cat_id'] ?? 0),
                        $cat_image
                    );
                }
            }
        }

        $decoded     = json_decode((string) ($result['raw'] ?? ''), true);
        $total_pages = max(
            1,
            (int) ($decoded['totalPage'] ?? 0),
            (int) ($decoded['data']['totalPage'] ?? 0),
            (int) ($decoded['paging']['totalPage'] ?? 0)
        );
        $page++;

    } while ($page <= $total_pages && $page <= $max_pages);

    $filter_label = $filter_cat_id > 0
        ? ($categories_map[$filter_cat_id]['name'] ?? 'ID ' . $filter_cat_id)
        : 'Tất cả danh mục';

    return [
        'success'         => true,
        'message'         => 'Sync completed.',
        'created'         => $created,
        'updated'         => $updated,
        'skipped'         => $skipped,
        'errors'          => $errors,
        'products_count'  => count($written_keys),
        'detail_calls'    => $detail_api_calls,
        'filter_category' => $filter_label,
    ];
}

function game_bsc_ajax_gotit_sync_vouchers() {
    game_bsc_gotit_test_guard();

    $gotit_category_id = absint($_POST['gotit_category_id'] ?? 0);

    $result = game_bsc_sync_gotit_products_to_vouchers([
        'source'           => 'manual-admin',
        'gotit_category_id' => $gotit_category_id,
    ]);

    if (!empty($result['success'])) {
        wp_send_json_success($result);
    }

    wp_send_json_error($result, 400);
}
add_action('wp_ajax_game_bsc_gotit_sync_vouchers', 'game_bsc_ajax_gotit_sync_vouchers');

function game_bsc_ajax_gotit_sync_vouchers_async_start() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $gotit_category_id = absint($_POST['gotit_category_id'] ?? 0);
    $current_state = game_bsc_gotit_async_sync_get_state();

    if (in_array((string) ($current_state['status'] ?? ''), ['queued', 'running'], true)) {
        wp_send_json_success([
            'message' => 'Đã có phiên sync đang chạy.',
            'status' => $current_state,
        ]);
    }

    $job_id = uniqid('gotit_sync_', true);
    $requested_by = get_current_user_id();
    $queued_at = current_time('mysql');

    $state = game_bsc_gotit_async_sync_update_state([
        'job_id' => $job_id,
        'status' => 'queued',
        'message' => 'Đã đưa sync vào hàng đợi.',
        'queued_at' => $queued_at,
        'started_at' => '',
        'finished_at' => '',
        'requested_by' => $requested_by,
        'gotit_category_id' => $gotit_category_id,
        'filter_category' => '',
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'products_count' => 0,
        'detail_calls' => 0,
        'errors_count' => 0,
        'last_error' => '',
    ]);

    wp_schedule_single_event(time() + 1, 'game_bsc_gotit_async_sync_event', [$job_id, $gotit_category_id, $requested_by]);
    if (function_exists('spawn_cron')) {
        spawn_cron(time());
    }

    wp_send_json_success([
        'message' => 'Đã bắt đầu sync bất đồng bộ.',
        'status' => $state,
    ]);
}
add_action('wp_ajax_game_bsc_gotit_sync_vouchers_async_start', 'game_bsc_ajax_gotit_sync_vouchers_async_start');

function game_bsc_ajax_gotit_sync_vouchers_async_status() {
    game_bsc_gotit_test_guard();

    $state = game_bsc_gotit_async_sync_get_state();
    $next_run_ts = wp_next_scheduled('game_bsc_gotit_async_sync_event');

    wp_send_json_success([
        'status' => $state,
        'next_run_ts' => $next_run_ts ? (int) $next_run_ts : 0,
    ]);
}
add_action('wp_ajax_game_bsc_gotit_sync_vouchers_async_status', 'game_bsc_ajax_gotit_sync_vouchers_async_status');

function game_bsc_run_gotit_async_sync_event($job_id = '', $gotit_category_id = 0, $requested_by = 0) {
    $lock_key = 'game_bsc_gotit_async_sync_lock';
    if (get_transient($lock_key)) {
        return;
    }

    set_transient($lock_key, 1, 45 * MINUTE_IN_SECONDS);

    try {
        $state = game_bsc_gotit_async_sync_get_state();
        if (!empty($job_id) && !empty($state['job_id']) && (string) $state['job_id'] !== (string) $job_id) {
            return;
        }

        game_bsc_gotit_async_sync_update_state([
            'job_id' => (string) $job_id,
            'status' => 'running',
            'message' => 'Đang đồng bộ dữ liệu voucher từ Got It...',
            'started_at' => current_time('mysql'),
            'finished_at' => '',
            'gotit_category_id' => (int) $gotit_category_id,
            'requested_by' => (int) $requested_by,
        ]);

        $result = game_bsc_sync_gotit_products_to_vouchers([
            'source' => 'manual-admin-async',
            'gotit_category_id' => (int) $gotit_category_id,
        ]);

        if (!empty($result['success'])) {
            game_bsc_gotit_async_sync_update_state([
                'status' => 'done',
                'message' => 'Sync hoàn tất.',
                'finished_at' => current_time('mysql'),
                'filter_category' => (string) ($result['filter_category'] ?? ''),
                'created' => (int) ($result['created'] ?? 0),
                'updated' => (int) ($result['updated'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'products_count' => (int) ($result['products_count'] ?? 0),
                'detail_calls' => (int) ($result['detail_calls'] ?? 0),
                'errors_count' => is_array($result['errors'] ?? null) ? count($result['errors']) : 0,
                'last_error' => '',
            ]);
        } else {
            game_bsc_gotit_async_sync_update_state([
                'status' => 'error',
                'message' => (string) ($result['message'] ?? 'Sync thất bại.'),
                'finished_at' => current_time('mysql'),
                'last_error' => (string) ($result['message'] ?? 'Sync failed'),
            ]);
        }
    } catch (Throwable $e) {
        game_bsc_gotit_async_sync_update_state([
            'status' => 'error',
            'message' => 'Sync lỗi: ' . $e->getMessage(),
            'finished_at' => current_time('mysql'),
            'last_error' => (string) $e->getMessage(),
        ]);
        error_log('[GotIt Async Sync] ' . $e->getMessage());
    } finally {
        delete_transient($lock_key);
    }
}
add_action('game_bsc_gotit_async_sync_event', 'game_bsc_run_gotit_async_sync_event', 10, 3);

// ===== SYNC DANH MỤC TỪ GOT IT =====

function game_bsc_ajax_gotit_sync_categories() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $map = $client->get_categories_map();

    if (empty($map)) {
        wp_send_json_error(['message' => 'Không lấy được danh mục từ Got It API. Kiểm tra API key và kết nối.'], 400);
    }

    $created  = 0;
    $existing = 0;

    foreach ($map as $cat_id => $cat_data) {
        // get_categories_map() trả về [id => ['name'=>..., 'slug'=>..., 'image'=>...]]
        $cat_name = trim((string) ($cat_data['name'] ?? $cat_data));
        $cat_slug = (string) ($cat_data['slug'] ?? '');
        $cat_image = esc_url_raw((string) ($cat_data['image'] ?? ''));
        if ($cat_name === '') continue;

        $upsert = game_bsc_gotit_upsert_category_term($cat_name, (int) $cat_id, $cat_slug, $cat_image);
        $term_id = (int) ($upsert['term_id'] ?? 0);
        if ($term_id < 1) {
            continue;
        }

        if (!empty($upsert['created'])) {
            $created++;
        } else {
            $existing++;
        }
    }

    wp_send_json_success([
        'message'    => 'Sync danh mục hoàn tất.',
        'created'    => $created,
        'existing'   => $existing,
        'total'      => count($map),
        'categories' => array_values(array_map(function($v) {
            return is_array($v) ? ($v['name'] ?? '') : (string) $v;
        }, $map)),
    ]);
}
add_action('wp_ajax_game_bsc_gotit_sync_categories', 'game_bsc_ajax_gotit_sync_categories');

// ===== DEBUG RAW API RESPONSE =====
function game_bsc_ajax_gotit_debug_raw() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.']);
    }

    // Lấy 1 product đầu tiên để xem raw fields
    $products_result = $client->get_products(1, 1, [
        'storeListPage'          => 1,
        'storeListPageSize'      => 5,
        'isExcludeStoreListInfo' => 'false',
    ]);

    // Lấy categories
    $categories_result = $client->get_categories();

    $first_product_raw = null;
    $first_product_id  = 0;
    if (!empty($products_result['data'])) {
        $data = $products_result['data'];
        $list = $data['products'] ?? $data['data'] ?? $data['items'] ?? (isset($data[0]) ? $data : []);
        if (!empty($list) && is_array($list)) {
            $first_product_raw = reset($list);
            $first_product_id  = (int) ($first_product_raw['id'] ?? $first_product_raw['productId'] ?? 0);
        }
    }

    // ===== GỌI /products/:productId (detail) để xem có categoryId không =====
    $detail_raw    = null;
    $detail_keys   = [];
    $detail_cat    = null;
    if ($first_product_id > 0) {
        $detail_result = $client->get_product_detail($first_product_id);
        if (!empty($detail_result['success']) && is_array($detail_result['data'])) {
            $detail_raw = $detail_result['data'];
            // Got It trả detail dạng array [0 => {product}] → unwrap
            if (isset($detail_raw[0]) && is_array($detail_raw[0]) && !isset($detail_raw['categoryId'])) {
                $detail_raw = $detail_raw[0];
            }
            $detail_keys = array_keys($detail_raw);
            $detail_cat  = [
                'categoryId'   => $detail_raw['categoryId']   ?? 'MISSING',
                'category_id'  => $detail_raw['category_id']  ?? 'MISSING',
                'categoryName' => $detail_raw['categoryName']  ?? 'MISSING',
                'category'     => $detail_raw['category']      ?? 'MISSING',
                'categories'   => $detail_raw['categories']    ?? 'MISSING',
            ];
        }
    }

    wp_send_json_success([
        'categories_raw'    => $categories_result['data'] ?? null,
        'categories_status' => $categories_result['http_code'] ?? null,
        'product_list_raw'  => $first_product_raw,
        'product_list_keys' => $first_product_raw ? array_keys($first_product_raw) : [],
        'product_list_category_fields' => $first_product_raw ? [
            'categoryId'   => $first_product_raw['categoryId']   ?? 'MISSING',
            'category_id'  => $first_product_raw['category_id']  ?? 'MISSING',
            'categoryName' => $first_product_raw['categoryName']  ?? 'MISSING',
            'category'     => $first_product_raw['category']      ?? 'MISSING',
        ] : null,
        'product_detail_raw'  => $detail_raw,
        'product_detail_keys' => $detail_keys,
        'product_detail_category_fields' => $detail_cat,
        'hint' => 'So sánh product_list_keys vs product_detail_keys để thấy detail có thêm field gì (categoryId, etc)',
    ]);
}
add_action('wp_ajax_game_bsc_gotit_debug_raw', 'game_bsc_ajax_gotit_debug_raw');

// ===== NÚT SYNC THỦ CÔNG TRÊN TRANG DANH SÁCH VOUCHER =====

/**
 * Thêm nút "Sync từ Got It" vào phía trên bảng danh sách post type game_vouchers
 */
function game_bsc_gotit_voucher_list_sync_button() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'game_vouchers' || $screen->base !== 'edit') {
        return;
    }
    if (!current_user_can('admin_game') && !current_user_can('administrator')) {
        return;
    }

    $nonce = wp_create_nonce('game_bsc_gotit_test_nonce');

    // Lấy danh sách taxonomy terms để build dropdown
    $terms = get_terms([
        'taxonomy'   => 'game_voucher_category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    $select_html = '<select id="game-bsc-gotit-cat-select" style="margin-left:8px;vertical-align:middle;">'
        . '<option value="0">— Tất cả danh mục —</option>';

    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $gotit_cat_id = (int) get_term_meta($term->term_id, '_gotit_category_id', true);
            if ($gotit_cat_id < 1) continue; // bỏ qua term không phải từ Got It
            $select_html .= sprintf(
                '<option value="%d">%s</option>',
                $gotit_cat_id,
                esc_html($term->name)
            );
        }
    }
    $select_html .= '</select>';
    ?>
    <script>
    (function($) {
        $(document).ready(function() {
            var nonce = '<?php echo esc_js($nonce); ?>';

            var $select   = $(<?php echo wp_json_encode($select_html); ?>);
            var $btnVoucher = $(
                '<button type="button" id="game-bsc-gotit-sync-btn" class="button button-primary" style="margin-left:6px;">' +
                '<span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:4px;"></span>' +
                'Sync voucher' +
                '</button>'
            );
            var $btnCat = $(
                '<button type="button" id="game-bsc-gotit-sync-cat-btn" class="button" style="margin-left:6px;">' +
                '<span class="dashicons dashicons-category" style="vertical-align:middle;margin-right:4px;"></span>' +
                'Sync danh mục' +
                '</button>'
            );
            var $status = $('<span id="game-bsc-gotit-sync-status" style="margin-left:10px;font-style:italic;"></span>');

            $('.wrap .page-title-action').first()
                .after($status)
                .after($btnCat)
                .after($btnVoucher)
                .after($select);

            function setLoading(loading) {
                [$btnVoucher, $btnCat, $select].forEach(function($el) {
                    $el.prop('disabled', loading);
                });
                loading
                    ? $btnVoucher.find('.dashicons').addClass('spin')
                    : $btnVoucher.find('.dashicons').removeClass('spin');
                loading
                    ? $btnCat.find('.dashicons').addClass('spin')
                    : $btnCat.find('.dashicons').removeClass('spin');
            }

            // === Sync voucher (có thể lọc theo danh mục) ===
            $btnVoucher.on('click', function() {
                if ($btnVoucher.prop('disabled')) return;
                var catId   = parseInt($select.val()) || 0;
                var catText = catId > 0
                    ? ($select.find('option:selected').text())
                    : 'tất cả danh mục';

                setLoading(true);
                $status.css('color', '').text('Đang xếp hàng sync voucher (' + catText + ')...');

                var syncPollTimer = null;
                function stopSyncPoll() {
                    if (syncPollTimer) {
                        clearInterval(syncPollTimer);
                        syncPollTimer = null;
                    }
                }

                function pollSyncStatus() {
                    $.post(ajaxurl, {
                        action: 'game_bsc_gotit_sync_vouchers_async_status',
                        nonce: nonce,
                    }, function(statusRes) {
                        if (!statusRes.success || !statusRes.data || !statusRes.data.status) {
                            return;
                        }

                        var st = statusRes.data.status;
                        if (st.status === 'queued') {
                            $status.css('color', '').text('⏳ Đang chờ worker chạy sync...');
                            return;
                        }

                        if (st.status === 'running') {
                            $status.css('color', '').text('🔄 Đang đồng bộ dữ liệu từ Got It...');
                            return;
                        }

                        if (st.status === 'done') {
                            stopSyncPoll();
                            setLoading(false);
                            $status.css('color', 'green').text(
                                '✅ Sync xong [' + (st.filter_category || catText) + ']' +
                                ' — Tạo mới: ' + (st.created || 0) +
                                ', Cập nhật: ' + (st.updated || 0) +
                                ', Bỏ qua: ' + (st.skipped || 0) +
                                ' (tổng ' + (st.products_count || 0) + ' sản phẩm)'
                            );
                            setTimeout(function() { location.reload(); }, 1800);
                            return;
                        }

                        if (st.status === 'error') {
                            stopSyncPoll();
                            setLoading(false);
                            $status.css('color', 'red').text('❌ ' + (st.last_error || st.message || 'Sync thất bại.'));
                        }
                    });
                }

                $.post(ajaxurl, {
                    action: 'game_bsc_gotit_sync_vouchers_async_start',
                    nonce: nonce,
                    gotit_category_id: catId,
                }, function(res) {
                    if (!res.success) {
                        var msg = (res.data && res.data.message) ? res.data.message : 'Không thể bắt đầu sync.';
                        $status.css('color', 'red').text('❌ ' + msg);
                        setLoading(false);
                        return;
                    }

                    $status.css('color', '').text('⏳ Sync đã được đưa vào hàng đợi. Đang theo dõi...');
                    pollSyncStatus();
                    stopSyncPoll();
                    syncPollTimer = setInterval(pollSyncStatus, 3000);
                }).fail(function() {
                    $status.css('color', 'red').text('❌ Lỗi kết nối khi bắt đầu sync voucher.');
                    setLoading(false);
                });
            });

            // === Sync danh mục ===
            $btnCat.on('click', function() {
                if ($btnCat.prop('disabled')) return;
                setLoading(true);
                $status.css('color', '').text('Đang sync danh mục từ Got It...');

                $.post(ajaxurl, { action: 'game_bsc_gotit_sync_categories', nonce: nonce }, function(res) {
                    if (res.success) {
                        var d = res.data;
                        $status.css('color', 'green').text(
                            '✅ Danh mục xong! Tạo mới: ' + d.created +
                            ', Đã có: ' + d.existing +
                            ' (tổng ' + d.total + ' danh mục: ' + d.categories.join(', ') + ')'
                        );
                        // Reload để dropdown cập nhật danh mục mới
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        var msg = (res.data && res.data.message) ? res.data.message : 'Sync danh mục thất bại.';
                        $status.css('color', 'red').text('❌ ' + msg);
                    }
                }).fail(function() {
                    $status.css('color', 'red').text('❌ Lỗi kết nối khi sync danh mục.');
                }).always(function() { setLoading(false); });
            });
        });
    })(jQuery);
    </script>
    <style>
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .dashicons.spin { display:inline-block; animation: spin 1s linear infinite; }
    </style>
    <?php
}
add_action('admin_footer-edit.php', 'game_bsc_gotit_voucher_list_sync_button');

function game_bsc_schedule_gotit_voucher_sync_event() {
    if (wp_next_scheduled('game_bsc_gotit_daily_sync_event')) {
        return;
    }

    $next = strtotime('tomorrow 02:00:00');
    if ($next <= time()) {
        $next = time() + HOUR_IN_SECONDS;
    }

    wp_schedule_event($next, 'daily', 'game_bsc_gotit_daily_sync_event');
}
add_action('init', 'game_bsc_schedule_gotit_voucher_sync_event', 30);

function game_bsc_run_gotit_daily_sync_event() {
    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        return;
    }

    $result = game_bsc_sync_gotit_products_to_vouchers([
        'source' => 'daily-cron',
    ]);

    if (empty($result['success'])) {
        error_log('[GotIt Sync] Daily sync failed: ' . wp_json_encode($result));
    }
}
add_action('game_bsc_gotit_daily_sync_event', 'game_bsc_run_gotit_daily_sync_event');

function game_bsc_ajax_gotit_get_products() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $page = 1;
    $total_pages = 1;
    $max_pages = 20;
    $products = [];
    $seen = [];

    $ids = sanitize_text_field((string) ($_POST['ids'] ?? $_GET['ids'] ?? ''));
    $store_list_page = absint($_POST['storeListPage'] ?? $_GET['storeListPage'] ?? 1);
    if ($store_list_page < 1) {
        $store_list_page = 1;
    }
    $store_list_page_size = absint($_POST['storeListPageSize'] ?? $_GET['storeListPageSize'] ?? 10);
    if ($store_list_page_size < 1) {
        $store_list_page_size = 10;
    }
    $is_exclude_store_info_raw = strtolower((string) ($_POST['isExcludeStoreListInfo'] ?? $_GET['isExcludeStoreListInfo'] ?? 'false'));
    $is_exclude_store_info = in_array($is_exclude_store_info_raw, ['1', 'true', 'yes'], true) ? 'true' : 'false';

    $extra_filters = [
        'storeListPage' => $store_list_page,
        'storeListPageSize' => $store_list_page_size,
        'isExcludeStoreListInfo' => $is_exclude_store_info,
    ];
    if ($ids !== '') {
        $extra_filters['ids'] = $ids;
    }

    $client_diag = method_exists($client, 'get_auth_diagnostics') ? $client->get_auth_diagnostics() : [];

    do {
        $result = $client->get_products($page, 100, $extra_filters);
        if (empty($result['success'])) {
            $http_code = (int) ($result['http_code'] ?? 0);
            $message = (string) ($result['error'] ?? 'Cannot fetch Got It products.');
            $hint = '';

            if ($http_code === 403 || strtolower($message) === 'forbidden') {
                $message = 'Forbidden khi goi API products tu Got It.';
                $hint = 'Kiem tra IP whitelist, API key dung moi truong (staging/production), quyen cua API key cho endpoint /api/v5.0/products, va so sanh request headers voi Postman.';
            }

            wp_send_json_error([
                'message' => $message,
                'hint' => $hint,
                'page' => $page,
                'http_code' => $http_code,
                'status_code' => (int) ($result['status_code'] ?? 0),
                'url' => (string) ($result['url'] ?? ''),
                'tried_urls' => (array) ($result['tried_urls'] ?? []),
                'raw' => $result['raw'] ?? '',
                'diagnostics' => $client_diag,
            ], $http_code > 0 ? $http_code : 400);
        }

        foreach (game_bsc_gotit_normalize_products($result['data'] ?? null) as $product) {
            if (!isset($seen[$product['productId']])) {
                $products[] = $product;
                $seen[$product['productId']] = true;
            }
        }

        $decoded = json_decode((string) ($result['raw'] ?? ''), true);
        $total_pages = max(
            1,
            (int) ($decoded['totalPage'] ?? 0),
            (int) ($decoded['data']['totalPage'] ?? 0),
            (int) ($decoded['paging']['totalPage'] ?? 0)
        );
        $page++;
    } while ($page <= $total_pages && $page <= $max_pages);

    wp_send_json_success([
        'products' => $products,
        'count' => count($products),
        'page_count' => min($total_pages, $max_pages),
        'request_filters' => $extra_filters,
    ]);
}
add_action('wp_ajax_game_bsc_gotit_get_products', 'game_bsc_ajax_gotit_get_products');

function game_bsc_ajax_gotit_ping() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error([
            'message' => 'Got It API key is not configured.',
            'environment' => get_option('game_bsc_gotit_environment', 'staging'),
        ], 400);
    }

    $result = $client->get_categories();
    wp_send_json_success([
        'configured' => true,
        'environment' => get_option('game_bsc_gotit_environment', 'staging'),
        'base_url' => $client->get_base_url(),
        'http_code' => $result['http_code'] ?? 0,
        'status_code' => $result['status_code'] ?? 0,
        'elapsed_ms' => $result['elapsed_ms'] ?? null,
        'success' => (bool) ($result['success'] ?? false),
        'error' => $result['error'] ?? null,
        'sample_count' => is_array($result['data']) ? count($result['data']) : 0,
        'raw' => $result['raw'] ?? '',
    ]);
}
add_action('wp_ajax_game_bsc_gotit_ping', 'game_bsc_ajax_gotit_ping');

function game_bsc_ajax_gotit_test_issue() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $mode = sanitize_text_field($_POST['mode'] ?? 'manual');
    $order_name = sanitize_text_field($_POST['order_name'] ?? game_bsc_gotit_source_value('default_order_name_test', 'BSC Game Voucher Test'));
    $expiry_days = absint($_POST['expiry_days'] ?? game_bsc_gotit_source_value('default_expiry_days', 30));
    if ($expiry_days < 1) $expiry_days = 30;

    $user_id = absint($_POST['user_id'] ?? 0);
    $voucher_post_id = 0;
    $product_id = 0;
    $product_price_id = 0;

    if ($mode === 'voucher') {
        $voucher_post_id = absint($_POST['voucher_post_id'] ?? 0);
        if ($voucher_post_id < 1) {
            wp_send_json_error(['message' => 'voucher_post_id is required for voucher mode.'], 400);
        }

        $product_id = (int) get_field('gotit_product_id', $voucher_post_id);
        $product_price_id = (int) get_field('gotit_product_price_id', $voucher_post_id);

        if ($product_id < 1 || $product_price_id < 1) {
            wp_send_json_error(['message' => 'Selected voucher does not have gotit_product_id or gotit_product_price_id.'], 400);
        }
    } else {
        $product_id = absint($_POST['product_id'] ?? 0);
        $product_price_id = absint($_POST['product_price_id'] ?? 0);
        if ($product_id < 1 || $product_price_id < 1) {
            wp_send_json_error(['message' => 'product_id and product_price_id are required.'], 400);
        }
    }

    $transaction_ref_id = $client->generate_transaction_ref_id($user_id, $voucher_post_id);
    $expiry_date = gmdate('Y-m-d', strtotime('+' . $expiry_days . ' days'));

    $result = $client->issue_voucher([
        'productId' => $product_id,
        'productPriceId' => $product_price_id,
        'quantity' => 1,
        'orderName' => $order_name,
        'expiryDate' => $expiry_date,
        'transactionRefId' => $transaction_ref_id,
    ]);

    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
    $issue_data = game_bsc_gotit_extract_issue_data($data);

    $partner_expiry_date = '';
    if (!empty($issue_data['is_partner_code']) && !empty($issue_data['expiry_date'])) {
        $partner_expiry_date = (string) $issue_data['expiry_date'];
    }

    $txn_save = game_bsc_insert_gotit_test_transaction([
        'redemption_id' => 0,
        'user_id' => $user_id,
        'voucher_post_id' => $voucher_post_id,
        'transaction_ref_id' => $transaction_ref_id,
        'gotit_order_name' => $order_name,
        'gotit_product_id' => $product_id,
        'gotit_product_price_id' => $product_price_id,
        'gotit_voucher_link' => $issue_data['voucher_link'] ?? '',
        'gotit_voucher_code' => $issue_data['voucher_code'] ?? '',
        'gotit_voucher_image' => $issue_data['voucher_image'] ?? '',
        'gotit_serial' => $issue_data['voucher_serial'] ?? '',
        'gotit_expiry_date' => $issue_data['expiry_date'] ?? '',
        'gotit_partner_expiry_date' => $partner_expiry_date,
        'gotit_vendor_name' => $issue_data['vendor_name'] ?? '',
        'gotit_is_partner_code' => (int) ($issue_data['is_partner_code'] ?? 0),
        'gotit_status' => (int) ($issue_data['status'] ?? 0),
        'gotit_raw_response' => $result['raw'] ?? '',
        'gotit_status_code' => (int) ($result['status_code'] ?? 0),
        'gotit_error_message' => (string) ($result['error'] ?? ''),
    ]);

    wp_send_json_success([
        'mode' => $mode,
        'transaction_ref_id' => $transaction_ref_id,
        'issue_result' => $result,
        'issue_data' => $issue_data,
        'db_saved' => $txn_save,
    ]);
}
add_action('wp_ajax_game_bsc_gotit_test_issue', 'game_bsc_ajax_gotit_test_issue');

function game_bsc_ajax_gotit_test_status() {
    game_bsc_gotit_test_guard();

    $client = game_bsc_gotit_client();
    if (!$client->is_configured()) {
        wp_send_json_error(['message' => 'Got It API key is not configured.'], 400);
    }

    $transaction_ref_id = sanitize_text_field($_POST['transaction_ref_id'] ?? '');
    if ($transaction_ref_id === '') {
        wp_send_json_error(['message' => 'transaction_ref_id is required.'], 400);
    }

    global $wpdb;
    $prefix = $wpdb->prefix . 'game_';
    $db_row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$prefix}gotit_transactions WHERE transaction_ref_id = %s ORDER BY id DESC LIMIT 1", $transaction_ref_id),
        ARRAY_A
    );

    $status_result = $client->check_voucher_status($transaction_ref_id);
    $ref_result = $client->get_vouchers_by_ref_id($transaction_ref_id, 1, 100, ['productInfo', 'usedInfo', 'groupInfo']);

    $ref_payload = is_array($ref_result['data'] ?? null) ? $ref_result['data'] : [];
    $ref_vouchers = game_bsc_gotit_extract_vouchers_from_ref_payload($ref_payload);
    $ref_pagination = game_bsc_gotit_extract_ref_pagination($ref_payload);
    $ref_summary = game_bsc_gotit_build_ref_voucher_summary($ref_vouchers);

    wp_send_json_success([
        'transaction_ref_id' => $transaction_ref_id,
        'api_result' => $status_result,
        'ref_vouchers_result' => $ref_result,
        'ref_vouchers_pagination' => $ref_pagination,
        'ref_vouchers_count' => count($ref_vouchers),
        'ref_vouchers_summary' => $ref_summary,
        'ref_vouchers' => $ref_vouchers,
        'db_record' => $db_row,
    ]);
}
add_action('wp_ajax_game_bsc_gotit_test_status', 'game_bsc_ajax_gotit_test_status');

function game_bsc_ajax_gotit_retry_txn() {
    game_bsc_gotit_test_guard();

    $transaction_id = absint($_POST['transaction_id'] ?? 0);
    if ($transaction_id < 1) {
        wp_send_json_error(['message' => 'transaction_id is required.'], 400);
    }

    if (!function_exists('game_bsc_retry_gotit_voucher')) {
        wp_send_json_error(['message' => 'Retry function is not available in current build.'], 500);
    }

    $result = call_user_func('game_bsc_retry_gotit_voucher', $transaction_id);
    if (!empty($result['success'])) {
        wp_send_json_success($result);
    }

    wp_send_json_error($result, 400);
}
add_action('wp_ajax_game_bsc_gotit_retry_txn', 'game_bsc_ajax_gotit_retry_txn');
