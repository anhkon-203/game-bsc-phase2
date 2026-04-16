<?php
if (!defined('ABSPATH')) {
    exit;
}

// Tìm scalar theo danh sách key trong payload lồng nhau.
function game_bsc_gotit_issue_parser_pick_scalar_recursive($payload, $keys) {
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
            $found = game_bsc_gotit_issue_parser_pick_scalar_recursive($value, $keys);
            if ($found !== null && $found !== '') {
                return $found;
            }
        }
    }

    return null;
}

// Gom các node có khả năng chứa dữ liệu voucher issue.
function game_bsc_gotit_issue_parser_collect_issue_candidates($payload) {
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

        if (game_bsc_gotit_product_normalizer_is_list_array($payload[$key])) {
            foreach ($payload[$key] as $row) {
                if (is_array($row)) {
                    $candidates[] = $row;
                }
            }
        } else {
            $candidates[] = $payload[$key];
        }
    }

    if (game_bsc_gotit_product_normalizer_is_list_array($payload)) {
        foreach ($payload as $row) {
            if (is_array($row)) {
                $candidates[] = $row;
            }
        }
    }

    return $candidates;
}

function game_bsc_gotit_issue_parser_pick_issue_value($candidates, $keys) {
    foreach ($candidates as $candidate) {
        $value = game_bsc_gotit_issue_parser_pick_scalar_recursive($candidate, $keys);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return null;
}

// Trích dữ liệu quan trọng sau khi issue voucher (code/link/expiry/vendor/state...).
function game_bsc_gotit_issue_parser_extract_issue_data($payload) {
    $candidates = game_bsc_gotit_issue_parser_collect_issue_candidates(is_array($payload) ? $payload : []);

    $voucher_code = sanitize_text_field((string) (game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['voucherCode', 'voucher_code', 'code']) ?? ''));
    $voucher_link = esc_url_raw((string) (game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['voucherLink', 'voucher_link', 'link', 'url']) ?? ''));
    $voucher_image = esc_url_raw((string) (game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['image', 'voucherImage', 'voucher_image', 'imageUrl', 'image_url']) ?? ''));
    $voucher_serial = sanitize_text_field((string) (game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['serial', 'serialNo', 'serial_no', 'voucherSerial', 'voucher_serial']) ?? ''));
    $expiry_date = sanitize_text_field((string) (game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['expiryDate', 'expiry_date', 'expiredDate', 'expired_date', 'validTo', 'valid_to']) ?? ''));
    $vendor_name = sanitize_text_field((string) (game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['vendorName', 'vendor_name', 'vendor', 'partnerName', 'partner_name']) ?? ''));

    $status_raw = game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['status', 'state', 'stateCode', 'state_code', 'newStateCode']);
    $status = is_numeric($status_raw) ? (int) $status_raw : 0;

    $is_partner_raw = game_bsc_gotit_issue_parser_pick_issue_value($candidates, ['isPartnerCode', 'is_partner_code', 'partnerCode', 'partner_code', 'isPartner']);
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

function game_bsc_gotit_issue_parser_extract_vouchers_from_ref_payload($payload) {
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

        if (game_bsc_gotit_product_normalizer_is_list_array($node)) {
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

function game_bsc_gotit_issue_parser_extract_ref_pagination($payload) {
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

// Tổng hợp trạng thái used/unused theo response get_vouchers_by_ref_id.
function game_bsc_gotit_issue_parser_build_ref_voucher_summary($vouchers) {
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
