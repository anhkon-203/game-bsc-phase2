<?php
if (!defined('ABSPATH')) {
    exit;
}

function game_bsc_gotit_product_normalizer_is_list_array($value) {
    // Kiểm tra mảng dạng list tuần tự (0..n) thay vì object map.
    return is_array($value) && array_values($value) === $value;
}

function game_bsc_gotit_product_normalizer_extract_products_list($value) {
    // Tìm danh sách products trong nhiều cấu trúc payload khác nhau.
    if (!is_array($value)) {
        return [];
    }

    if (game_bsc_gotit_product_normalizer_is_list_array($value)) {
        return $value;
    }

    $candidate_keys = ['products', 'items', 'list', 'rows', 'result', 'data'];
    foreach ($candidate_keys as $key) {
        if (isset($value[$key]) && is_array($value[$key])) {
            $list = game_bsc_gotit_product_normalizer_extract_products_list($value[$key]);
            if (!empty($list)) {
                return $list;
            }
        }
    }

    return [];
}

function game_bsc_gotit_product_normalizer_normalize_price_label($price_item) {
    // Chuẩn hóa label hiển thị mệnh giá cho UI/admin.
    if (!is_array($price_item)) {
        return '';
    }

    $amount = 0;
    foreach (['price', 'value', 'amount', 'denomination'] as $money_key) {
        if (isset($price_item[$money_key]) && is_numeric($price_item[$money_key])) {
            $amount = (int) $price_item[$money_key];
            break;
        }
    }

    $raw_name = trim((string) ($price_item['name'] ?? $price_item['productPriceName'] ?? ''));
    $is_meaningful_name = $raw_name !== ''
        && !ctype_digit($raw_name)
        && !preg_match('/^\d+$/', $raw_name);

    if ($is_meaningful_name) {
        return $raw_name;
    }

    if ($amount > 0) {
        return number_format($amount, 0, ',', '.') . 'đ';
    }

    if ($raw_name !== '') {
        return $raw_name;
    }

    return '';
}

function game_bsc_gotit_product_normalizer_normalize_product_prices($value) {
    // Đưa dữ liệu mệnh giá về một schema thống nhất.
    $normalized = [];

    if (!is_array($value)) {
        return $normalized;
    }

    if (!game_bsc_gotit_product_normalizer_is_list_array($value)) {
        foreach (['productPrices', 'prices', 'items', 'list', 'data'] as $nested_key) {
            if (isset($value[$nested_key]) && is_array($value[$nested_key])) {
                return game_bsc_gotit_product_normalizer_normalize_product_prices($value[$nested_key]);
            }
        }

        if (!empty($value['productPriceId']) || !empty($value['id']) || !empty($value['priceId'])) {
            $value = [$value];
        }
    }

    foreach ($value as $price_item) {
        // Hỗ trợ payload chỉ chứa scalar productPriceId.
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

        $label = game_bsc_gotit_product_normalizer_normalize_price_label($price_item);
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

function game_bsc_gotit_product_normalizer_normalize_products($value) {
    // Chuẩn hóa toàn bộ product payload để các luồng sync dùng chung.
    $normalized = [];
    $products = game_bsc_gotit_product_normalizer_extract_products_list($value);

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
        // Ưu tiên các key phổ biến chứa danh sách mệnh giá.
        foreach (['productPriceId', 'productPrices', 'prices', 'priceList', 'productPriceIds', 'priceInfo'] as $key) {
            if (!empty($product[$key]) && is_array($product[$key])) {
                $prices = game_bsc_gotit_product_normalizer_normalize_product_prices($product[$key]);
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
            // Giữ lại raw để debug khi API thay đổi schema.
            'productId' => $product_id,
            'productName' => $product_name,
            'prices' => $prices,
            'image' => esc_url_raw((string) ($product['image'] ?? '')),
            'additionalImages' => $additional_images,
            'type' => sanitize_text_field((string) ($product['type'] ?? '')),
            'description' => (string) ($product['description'] ?? ''),
            'shortDescription' => (string) ($product['shortDescription'] ?? ''),
            'slug' => sanitize_text_field((string) ($product['slug'] ?? '')),
            'link' => esc_url_raw((string) ($product['link'] ?? '')),
            'voucherType' => sanitize_text_field((string) ($product['voucherType'] ?? '')),
            'terms' => (string) ($product['terms'] ?? ''),
            'serviceGuide' => (string) ($product['serviceGuide'] ?? ''),
            'brandInfo' => $brand_info,
            'extraFields' => $extra_fields,
            'categoryId' => (int) ($product['categoryId'] ?? $product['category_id'] ?? 0),
            'categoryName' => sanitize_text_field((string) ($product['categoryName'] ?? $product['category_name'] ?? '')),
            'raw' => $product,
        ];
    }

    return $normalized;
}
