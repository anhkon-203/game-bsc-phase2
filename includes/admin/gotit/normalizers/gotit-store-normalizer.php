<?php
if (!defined('ABSPATH')) {
    exit;
}

// Thu thập tên cửa hàng từ node bất kỳ trong payload (đệ quy có giới hạn depth).
function game_bsc_gotit_store_normalizer_collect_store_names_from_node($node, &$names, $depth = 0) {
    if ($depth > 5 || !is_array($node)) {
        return;
    }

    $name_keys = ['storeName', 'storeNm', 'store_name', 'branchName', 'branchNm', 'shopName', 'outletName', 'displayName', 'name'];

    if (game_bsc_gotit_product_normalizer_is_list_array($node)) {
        foreach ($node as $child) {
            if (is_array($child)) {
                game_bsc_gotit_store_normalizer_collect_store_names_from_node($child, $names, $depth + 1);
            }
        }
        return;
    }

    foreach ($name_keys as $name_key) {
        if (!array_key_exists($name_key, $node) || !is_scalar($node[$name_key])) {
            continue;
        }

        $name = game_bsc_gotit_content_helper_clean_store_text($node[$name_key]);
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
            game_bsc_gotit_store_normalizer_collect_store_names_from_node($value, $names, $depth + 1);
        }
    }
}

// Tách tên cửa hàng từ chuỗi text (xuống dòng/dấu phẩy/chấm phẩy).
function game_bsc_gotit_store_normalizer_collect_store_names_from_text($text, &$names) {
    if (!is_scalar($text)) {
        return;
    }

    $parts = preg_split('/[\r\n,;|]+/', (string) $text);
    if (empty($parts) || !is_array($parts)) {
        return;
    }

    foreach ($parts as $part) {
        $name = game_bsc_gotit_content_helper_clean_store_text($part);
        if ($name !== '') {
            $names[$name] = true;
        }
    }
}

// Chuẩn hóa 1 store row về schema thống nhất.
function game_bsc_gotit_store_normalizer_normalize_store_row($store) {
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
            $name = game_bsc_gotit_content_helper_clean_store_text($store[$name_key]);
            if ($name !== '') {
                break;
            }
        }
    }

    $address = '';
    foreach (['address', 'storeAddress', 'storeAddr', 'branchAddress', 'fullAddress'] as $address_key) {
        if (array_key_exists($address_key, $store)) {
            $address = game_bsc_gotit_content_helper_clean_store_text($store[$address_key]);
            if ($address !== '') {
                break;
            }
        }
    }

    $email = '';
    foreach (['email', 'storeEmail', 'contactEmail'] as $email_key) {
        if (array_key_exists($email_key, $store)) {
            $email = game_bsc_gotit_content_helper_clean_store_text($store[$email_key]);
            if ($email !== '') {
                break;
            }
        }
    }

    $phone = '';
    foreach (['phone', 'phoneNo', 'phoneNumber', 'tel', 'hotline', 'contactPhone'] as $phone_key) {
        if (array_key_exists($phone_key, $store)) {
            $phone = game_bsc_gotit_content_helper_clean_store_text($store[$phone_key]);
            if ($phone !== '') {
                break;
            }
        }
    }

    $lat = '';
    foreach (['lat', 'latitude'] as $lat_key) {
        if (array_key_exists($lat_key, $store)) {
            $lat = game_bsc_gotit_content_helper_clean_store_text($store[$lat_key]);
            if ($lat !== '') {
                break;
            }
        }
    }

    $long = '';
    foreach (['long', 'lng', 'longitude'] as $long_key) {
        if (array_key_exists($long_key, $store)) {
            $long = game_bsc_gotit_content_helper_clean_store_text($store[$long_key]);
            if ($long !== '') {
                break;
            }
        }
    }

    $district_name = game_bsc_gotit_content_helper_clean_store_text($store['districtName'] ?? $store['district_name'] ?? '');
    $city_name = game_bsc_gotit_content_helper_clean_store_text($store['cityName'] ?? $store['city_name'] ?? '');

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

function game_bsc_gotit_store_normalizer_collect_store_rows_from_node($node, &$rows, $depth = 0) {
    if ($depth > 6 || !is_array($node)) {
        return;
    }

    if (game_bsc_gotit_product_normalizer_is_list_array($node)) {
        foreach ($node as $child) {
            if (is_array($child)) {
                game_bsc_gotit_store_normalizer_collect_store_rows_from_node($child, $rows, $depth + 1);
            }
        }
        return;
    }

    $normalized_row = game_bsc_gotit_store_normalizer_normalize_store_row($node);
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
            game_bsc_gotit_store_normalizer_collect_store_rows_from_node($value, $rows, $depth + 1);
        }
    }
}

function game_bsc_gotit_store_normalizer_build_fallback_store_rows_from_names($names) {
    $rows = [];
    if (!is_array($names) || empty($names)) {
        return $rows;
    }

    foreach (array_keys($names) as $name) {
        $clean_name = game_bsc_gotit_content_helper_clean_store_text($name);
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

function game_bsc_gotit_store_normalizer_build_applicable_stores_text($stores, $fallback_names = []) {
    $lines = [];

    if (is_array($stores)) {
        foreach ($stores as $store) {
            if (!is_array($store)) {
                continue;
            }

            $name = game_bsc_gotit_content_helper_clean_store_text($store['name'] ?? '');
            $address = game_bsc_gotit_content_helper_clean_store_text($store['address'] ?? '');
            $phone = game_bsc_gotit_content_helper_clean_store_text($store['phone'] ?? '');
            $email = game_bsc_gotit_content_helper_clean_store_text($store['email'] ?? '');
            $district_name = game_bsc_gotit_content_helper_clean_store_text($store['districtName'] ?? '');
            $city_name = game_bsc_gotit_content_helper_clean_store_text($store['cityName'] ?? '');
            $lat = game_bsc_gotit_content_helper_clean_store_text($store['lat'] ?? '');
            $long = game_bsc_gotit_content_helper_clean_store_text($store['long'] ?? '');
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
            $clean_name = game_bsc_gotit_content_helper_clean_store_text($name);
            if ($clean_name !== '') {
                $lines[] = $clean_name;
            }
        }
    }

    return implode("\n", $lines);
}

function game_bsc_gotit_store_normalizer_get_existing_stores_payload($post_id) {
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
        $text = game_bsc_gotit_store_normalizer_build_applicable_stores_text($stores);
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

function game_bsc_gotit_store_normalizer_extract_applicable_stores_text($product) {
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
        game_bsc_gotit_store_normalizer_collect_store_names_from_node($node, $names, 0);
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

            game_bsc_gotit_store_normalizer_collect_store_names_from_node($field, $names, 0);
            foreach (['value', 'values', 'content', 'label'] as $value_key) {
                if (array_key_exists($value_key, $field)) {
                    game_bsc_gotit_store_normalizer_collect_store_names_from_text($field[$value_key], $names);
                }
            }
        }
    }

    if (empty($names)) {
        return '';
    }

    return implode("\n", array_keys($names));
}

function game_bsc_gotit_store_normalizer_extract_total_pages_from_stores_result($result) {
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

function game_bsc_gotit_store_normalizer_fetch_applicable_stores_from_api($client, $product_id) {
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
            game_bsc_gotit_store_normalizer_collect_store_names_from_node($result['data'], $names, 0);
            game_bsc_gotit_store_normalizer_collect_store_rows_from_node($result['data'], $rows, 0);
        }

        $total_pages = game_bsc_gotit_store_normalizer_extract_total_pages_from_stores_result($result);
        $page++;
    } while ($page <= $total_pages && $page <= $max_pages);

    $stores = array_values($rows);
    if (empty($stores) && !empty($names)) {
        $stores = game_bsc_gotit_store_normalizer_build_fallback_store_rows_from_names($names);
    }

    return [
        'text' => game_bsc_gotit_store_normalizer_build_applicable_stores_text($stores, $names),
        'stores' => $stores,
        'source' => 'stores_api',
        'error' => $last_error,
        'http_code' => $last_http_code,
    ];
}
