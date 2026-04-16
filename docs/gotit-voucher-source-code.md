# GotIt Voucher Source Code Reference

Ngày cập nhật: 2026-04-13
Phạm vi: toàn bộ source code liên quan đến GotIt voucher trong plugin `game-bsc`

Tài liệu này mô tả lại kiến trúc, luồng xử lý, các file tham gia, các hook chính, dữ liệu lưu trữ và vai trò của từng module GotIt. Mục tiêu là để dev mới có thể đọc tài liệu này và hiểu được toàn bộ luồng GotIt voucher mà không cần mở từng file lớn ngay từ đầu.

---

## 1. Tổng quan kiến trúc

GotIt voucher hiện được tổ chức theo mô hình tách module, không còn gom toàn bộ vào một file lớn duy nhất.

### Entry point chính

- `game-bsc.php` là bootstrap của plugin.
- `includes/admin/gotit/gotit-init.php` là file khởi động cho toàn bộ GotIt.
- `includes/admin/gotit/gotit-client.php` chứa client gọi API Got It.
- `includes/admin/gotit/gotit-ajax.php` chứa các hàm nghiệp vụ, AJAX, cron worker và wrapper backward-compatible.
- Các module con xử lý chuẩn hóa dữ liệu nằm trong `includes/admin/gotit/helpers`, `includes/admin/gotit/normalizers`, `includes/admin/gotit/parsers`.

### Ý tưởng thiết kế

- `gotit-client.php` chịu trách nhiệm giao tiếp với Got It API.
- `gotit-ajax.php` giữ các endpoint/hàm cũ để không phá tương thích.
- Các hàm xử lý dữ liệu phức tạp được tách ra module riêng để dễ đọc, dễ test, dễ bảo trì.
- REST API phía WordPress vẫn đọc lại dữ liệu GotIt từ post meta và transaction table như trước.

---

## 2. Sơ đồ file liên quan

### Nhóm bootstrap và loader

- `game-bsc.php`
- `includes/admin/gotit/gotit-init.php`

### Nhóm API client

- `includes/admin/gotit/gotit-client.php`

### Nhóm xử lý dữ liệu

- `includes/admin/gotit/helpers/gotit-content-helper.php`
- `includes/admin/gotit/normalizers/gotit-product-normalizer.php`
- `includes/admin/gotit/normalizers/gotit-store-normalizer.php`
- `includes/admin/gotit/parsers/gotit-issue-parser.php`

### Nhóm nghiệp vụ, AJAX, cron

- `includes/admin/gotit/gotit-ajax.php`

### Nhóm đọc dữ liệu GotIt ở phía public/rest

- `includes/api/rest-gift.php`

### Nhóm admin UI còn liên quan gián tiếp

- `includes/admin.php`
- `admin_dashboard/dashboard.php`
- `admin_dashboard/setting.php`

---

## 3. Luồng load khi plugin khởi động

### 3.1 `game-bsc.php`

File bootstrap nạp các phần nền của plugin, sau đó nạp GotIt thông qua loader riêng:

```php
require_once GAME_BSC_PLUGIN_DIR . 'includes/admin/gotit/gotit-init.php';
```

Điều này có nghĩa là mọi logic GotIt đều đi qua một điểm vào duy nhất.

### 3.2 `includes/admin/gotit/gotit-init.php`

File này sắp xếp thứ tự load rất quan trọng:

1. Nạp `gotit-client.php` trước.
2. Nạp các module chuẩn hóa dữ liệu.
3. Nạp `gotit-ajax.php` cuối cùng.

Lý do:

- `gotit-ajax.php` có nhiều wrapper function gọi vào client và normalizer.
- Nếu nạp sai thứ tự, PHP có thể báo lỗi function chưa tồn tại.
- Tách loader giúp sau này bổ sung module mới mà không sửa bootstrap tổng.

### 3.3 Các file con được nạp từ loader

- `helpers/gotit-content-helper.php`
- `normalizers/gotit-product-normalizer.php`
- `normalizers/gotit-store-normalizer.php`
- `parsers/gotit-issue-parser.php`

---

## 4. Vai trò từng file

## 4.1 `gotit-client.php`

Đây là lớp client gọi lên Got It API. Nó chứa toàn bộ logic kết nối, xác thực, retry, normalize query và xử lý response thô.

### Nhiệm vụ chính

- Đọc cấu hình GotIt từ option WordPress.
- Tạo `Game_BSC_GotIt_Client`.
- Gọi các endpoint như:
  - categories
  - brands
  - products
  - product detail
  - product stores
  - issue voucher
  - check voucher status
  - get voucher by transaction ref
- Ký request nếu API cần signature.
- Thử fallback giữa prefix `api` và `biz` khi endpoint khác nhau theo môi trường.

### Các hàm/cụm quan trọng

- `game_bsc_gotit_source_config()`
- `game_bsc_gotit_source_value()`
- `Game_BSC_GotIt_Client::__construct()`
- `request_with_retry()`
- `request_with_prefix_fallback()`
- `get_products()`
- `get_product_detail()`
- `get_product_stores()`
- `issue_voucher()`
- `check_voucher_status()`
- `get_vouchers_by_ref_id()`
- `get_categories_map()`
- `generate_transaction_ref_id()`

### Điểm đáng chú ý

- Client có retry backoff nhẹ cho lỗi mạng/timeout/5xx.
- `get_products()` có logic fallback khi Got It chặn `page/pageSize` ở một số môi trường.
- `issue_voucher()` có retry theo `authenticationMethod` nếu API trả lỗi xác thực theo ngữ cảnh.
- `get_categories_map()` chuẩn hóa response categories về mảng `id => ['name', 'slug', 'image']`.

### Dữ liệu cấu hình

Các option/config nổi bật:

- `game_bsc_gotit_api_key`
- `game_bsc_gotit_environment`
- `game_bsc_gotit_endpoint_prefix`
- `game_bsc_gotit_source_config()`

---

## 4.2 `gotit-content-helper.php`

Module này xử lý nội dung text/HTML an toàn.

### Nhiệm vụ chính

- Làm sạch text cửa hàng.
- Decode HTML entity trước khi sanitize HTML.
- Lưu HTML đã sanitize vào post meta.

### Hàm chính

- `game_bsc_gotit_content_helper_clean_store_text()`
- `game_bsc_gotit_content_helper_prepare_html_content()`
- `game_bsc_gotit_content_helper_set_voucher_html_field()`

### Vai trò thực tế

- Dùng khi chuẩn hóa tên cửa hàng, địa chỉ, email, phone.
- Dùng khi lưu `terms`, `service_guide`, `description`, `shortDescription`.
- Giúp tránh lưu HTML bẩn hoặc text nhiều khoảng trắng.

---

## 4.3 `gotit-product-normalizer.php`

Module này chuẩn hóa response sản phẩm Got It về schema thống nhất để các luồng sync và UI có thể đọc chung.

### Nhiệm vụ chính

- Tìm list product trong nhiều cấu trúc payload khác nhau.
- Chuẩn hóa label mệnh giá.
- Chuẩn hóa danh sách price.
- Chuẩn hóa toàn bộ product record.

### Hàm chính

- `game_bsc_gotit_product_normalizer_is_list_array()`
- `game_bsc_gotit_product_normalizer_extract_products_list()`
- `game_bsc_gotit_product_normalizer_normalize_price_label()`
- `game_bsc_gotit_product_normalizer_normalize_product_prices()`
- `game_bsc_gotit_product_normalizer_normalize_products()`

### Schema đầu ra của 1 product

Mỗi product sau normalize thường có:

- `productId`
- `productName`
- `prices`
- `image`
- `additionalImages`
- `type`
- `description`
- `shortDescription`
- `slug`
- `link`
- `voucherType`
- `terms`
- `serviceGuide`
- `brandInfo`
- `extraFields`
- `categoryId`
- `categoryName`
- `raw`

### Vai trò trong hệ thống

- Sync voucher dựa vào normalized product.
- Frontend/admin UI dùng để hiện dropdown sản phẩm/mệnh giá.
- REST endpoint có thể dùng lại dữ liệu normalize thay vì parse ad hoc.

---

## 4.4 `gotit-store-normalizer.php`

Module này tập trung vào phần cửa hàng áp dụng cho voucher.

### Nhiệm vụ chính

- Đọc tên cửa hàng từ nhiều node trong payload.
- Chuẩn hóa một store row.
- Gom store rows từ response lồng nhau.
- Build text mô tả cửa hàng áp dụng.
- Lấy payload cửa hàng đã lưu sẵn trong post meta.
- Fetch store list từ API theo product.

### Hàm chính

- `game_bsc_gotit_store_normalizer_collect_store_names_from_node()`
- `game_bsc_gotit_store_normalizer_collect_store_names_from_text()`
- `game_bsc_gotit_store_normalizer_normalize_store_row()`
- `game_bsc_gotit_store_normalizer_collect_store_rows_from_node()`
- `game_bsc_gotit_store_normalizer_build_fallback_store_rows_from_names()`
- `game_bsc_gotit_store_normalizer_build_applicable_stores_text()`
- `game_bsc_gotit_store_normalizer_get_existing_stores_payload()`
- `game_bsc_gotit_store_normalizer_extract_applicable_stores_text()`
- `game_bsc_gotit_store_normalizer_extract_total_pages_from_stores_result()`
- `game_bsc_gotit_store_normalizer_fetch_applicable_stores_from_api()`

### Dữ liệu store chuẩn hóa

Một store row thường có:

- `id`
- `name`
- `address`
- `email`
- `phone`
- `lat`
- `long`
- `districtId`
- `districtName`
- `cityId`
- `cityName`
- `extraFields`
- `raw`

### Vai trò trong sync

- Nếu API stores trả đủ dữ liệu, hệ thống lưu cả text và structured rows.
- Nếu API chỉ trả text/names, module tạo fallback rows để không mất thông tin cơ bản.
- Đây là module chính để hiển thị “Cửa hàng áp dụng” trong voucher detail.

---

## 4.5 `gotit-issue-parser.php`

Module này chuyên parse response liên quan đến issue voucher và trạng thái voucher.

### Nhiệm vụ chính

- Trích voucher code/link/image/serial/expiry/vendor/status từ payload issue.
- Dedupe danh sách voucher trong response ref.
- Trích pagination của response ref.
- Tính summary used/unused/state.

### Hàm chính

- `game_bsc_gotit_issue_parser_pick_scalar_recursive()`
- `game_bsc_gotit_issue_parser_collect_issue_candidates()`
- `game_bsc_gotit_issue_parser_pick_issue_value()`
- `game_bsc_gotit_issue_parser_extract_issue_data()`
- `game_bsc_gotit_issue_parser_extract_vouchers_from_ref_payload()`
- `game_bsc_gotit_issue_parser_extract_ref_pagination()`
- `game_bsc_gotit_issue_parser_build_ref_voucher_summary()`

### Kết quả parse issue

Hàm `extract_issue_data()` thường trả về:

- `voucher_code`
- `voucher_link`
- `voucher_image`
- `voucher_serial`
- `expiry_date`
- `vendor_name`
- `status`
- `is_partner_code`

### Kết quả summary ref vouchers

Hàm `build_ref_voucher_summary()` tạo summary như:

- `total`
- `used`
- `unused`
- `states`
- `first_used_info`

### Vai trò thực tế

- Dùng trong test issue.
- Dùng trong status check.
- Dùng trong REST history để diễn giải voucher đã issue và voucher đã dùng.

---

## 4.6 `gotit-ajax.php`

Đây là file nghiệp vụ lớn nhất của GotIt. Nó vẫn là nơi giữ các hàm cũ để các luồng gọi hiện tại không bị gãy, nhưng phần xử lý dữ liệu đã chuyển qua module riêng.

### 4.6.1 Vai trò tổng quát

File này làm 5 việc chính:

1. Giữ các wrapper function tương thích ngược.
2. Xử lý sync voucher từ Got It sang post type `game_vouchers`.
3. Xử lý AJAX admin cho sync, status, stop, categories, debug.
4. Chạy cron/async worker để sync nền.
5. Cung cấp các helper nghiệp vụ cho hệ thống GotIt.

### 4.6.2 Wrapper tương thích ngược

Các hàm như sau hiện chỉ gọi sang module mới:

- `game_bsc_gotit_clean_store_text()`
- `game_bsc_gotit_prepare_html_content()`
- `game_bsc_gotit_set_voucher_html_field()`
- `game_bsc_gotit_collect_store_names_from_node()`
- `game_bsc_gotit_collect_store_names_from_text()`
- `game_bsc_gotit_normalize_store_row()`
- `game_bsc_gotit_collect_store_rows_from_node()`
- `game_bsc_gotit_build_fallback_store_rows_from_names()`
- `game_bsc_gotit_build_applicable_stores_text()`
- `game_bsc_gotit_get_existing_stores_payload()`
- `game_bsc_gotit_extract_applicable_stores_text()`
- `game_bsc_gotit_extract_total_pages_from_stores_result()`
- `game_bsc_gotit_fetch_applicable_stores_from_api()`
- `game_bsc_gotit_pick_scalar_recursive()`
- `game_bsc_gotit_collect_issue_candidates()`
- `game_bsc_gotit_pick_issue_value()`
- `game_bsc_gotit_extract_issue_data()`
- `game_bsc_gotit_extract_vouchers_from_ref_payload()`
- `game_bsc_gotit_extract_ref_pagination()`
- `game_bsc_gotit_build_ref_voucher_summary()`

Ý nghĩa:

- Code cũ bên dưới vẫn gọi tên cũ nên không cần sửa từng nơi ngay lập tức.
- Khi cần dọn tiếp, có thể xóa wrapper dần sau khi kiểm tra toàn bộ callsite.

### 4.6.3 Sync flow chính

Hàm trung tâm là `game_bsc_sync_gotit_products_to_vouchers($args = [])`.

#### Input chính

- `source`
- `gotit_category_id`
- `start_page`
- `pages_per_run`
- `page_size`
- `lightweight_mode`
- `max_pages`

#### Luồng xử lý

1. Lấy client GotIt.
2. Kiểm tra API key.
3. Tạm tắt logging WSAL khi sync.
4. Lấy categories map và cache bằng transient.
5. Lặp qua products theo page.
6. Normalize product list.
7. Lấy category cho product.
8. Lấy stores payload.
9. Với từng price trong product:
   - tạo/đọc voucher post tương ứng
   - cập nhật title, meta, terms, image, stores, brand, price, points
   - gán taxonomy `game_voucher_category`
10. Tính thống kê created/updated/skipped/errors.
11. Khôi phục logging WSAL.
12. Trả kết quả tổng hợp.

#### Điểm đáng chú ý

- Sync không chỉ tạo voucher mới mà còn update voucher cũ nếu đã tồn tại `productId + productPriceId`.
- Hệ thống giữ mapping 1 sản phẩm nhiều mệnh giá = nhiều voucher.
- `lightweight_mode` cho phép giảm số call stores trong async worker.

### 4.6.4 Transaction/reference management

Các hàm hỗ trợ:

- `game_bsc_gotit_get_existing_voucher_post_id()`
- `game_bsc_gotit_find_category_term_id_by_gotit_id()`
- `game_bsc_gotit_upsert_category_term()`
- `game_bsc_gotit_assign_voucher_category()`

Ý nghĩa:

- Tìm voucher đã đồng bộ từ product/price ID.
- Map GotIt category sang taxonomy term WordPress.
- Lưu `_gotit_category_id` và `_gotit_category_image` vào term meta.

### 4.6.5 Async sync state machine

Các hàm điều phối state:

- `game_bsc_gotit_async_sync_default_state()`
- `game_bsc_gotit_normalize_category_queue()`
- `game_bsc_gotit_collect_category_ids_for_sync()`
- `game_bsc_gotit_async_sync_get_state()`
- `game_bsc_gotit_async_sync_update_state()`
- `game_bsc_gotit_sync_runtime_config()`
- `game_bsc_schedule_gotit_async_worker()`
- `game_bsc_clear_gotit_async_worker_queue()`
- `game_bsc_gotit_async_sync_worker_health()`
- `game_bsc_gotit_async_sync_reconcile_state()`
- `game_bsc_start_gotit_sync_job()`
- `game_bsc_request_stop_gotit_sync_job()`

#### State fields quan trọng

- `job_id`
- `status`
- `message`
- `queued_at`
- `started_at`
- `finished_at`
- `requested_by`
- `gotit_category_id`
- `created`
- `updated`
- `skipped`
- `products_count`
- `detail_calls`
- `errors_count`
- `current_page`
- `total_pages`
- `pages_processed`
- `category_queue`
- `category_index`

#### Trạng thái thường gặp

- `idle`
- `queued`
- `running`
- `stopping`
- `stopped`
- `done`
- `error`

### 4.6.6 WSAL suppression

Có 2 hàm để chặn log tạm thời trong lúc sync:

- `game_bsc_wsal_suppress_event_during_sync()`
- `game_bsc_wsal_suppress_meta_event_during_sync()`

Mục đích:

- Tránh WP Security Audit Log ghi quá nhiều record trong lúc batch sync voucher.
- Sau khi sync xong, filter được remove lại.

### 4.6.7 AJAX endpoints

Các action còn tồn tại trong file này:

- `wp_ajax_game_bsc_gotit_sync_vouchers`
- `wp_ajax_game_bsc_gotit_sync_vouchers_async_start`
- `wp_ajax_game_bsc_gotit_sync_vouchers_async_status`
- `wp_ajax_game_bsc_gotit_sync_vouchers_async_stop`
- `wp_ajax_game_bsc_gotit_sync_categories`
- `wp_ajax_game_bsc_gotit_get_products`
- `wp_ajax_game_bsc_gotit_ping`
- `wp_ajax_game_bsc_gotit_test_issue`
- `wp_ajax_game_bsc_gotit_test_status`
- `wp_ajax_game_bsc_gotit_retry_txn`

#### Ý nghĩa từng endpoint

- `sync_vouchers`: queue sync voucher theo category.
- `sync_vouchers_async_start`: bắt đầu job async.
- `sync_vouchers_async_status`: đọc trạng thái async worker.
- `sync_vouchers_async_stop`: yêu cầu dừng worker.
- `sync_categories`: đồng bộ taxonomy category từ GotIt.
- `get_products`: debug/đọc danh sách sản phẩm.
- `ping`: test kết nối API.
- `test_issue`: issue voucher thử nghiệm.
- `test_status`: kiểm tra trạng thái voucher theo `transaction_ref_id`.
- `retry_txn`: retry transaction test.

### 4.6.8 Admin UI sync button ở trang list voucher

Hàm:

- `game_bsc_gotit_voucher_list_sync_button()`

Chức năng:

- Thêm nút sync voucher vào màn hình list `game_vouchers` trong admin.
- Thêm dropdown category GotIt.
- Thêm nút dừng sync.
- Poll status async worker bằng AJAX.

### 4.6.9 Cron GotIt định kỳ

Hàm:

- `game_bsc_add_gotit_voucher_sync_cron_schedules()`
- `game_bsc_get_next_gotit_voucher_sync_timestamp()`
- `game_bsc_schedule_gotit_voucher_sync_event()`
- `game_bsc_maybe_schedule_gotit_voucher_sync_event()`
- `game_bsc_clear_gotit_voucher_sync_event()`
- `game_bsc_run_gotit_daily_sync_event()`

Cron hiện dùng recurrence riêng `game_bsc_every_14_days` và hook:

- `game_bsc_gotit_daily_sync_event`

Ý tưởng:

- Định kỳ queue job sync GotIt.
- Nếu có category queue thì sync lần lượt từng category.
- Không chạy thẳng full sync trong cron, mà queue worker theo state machine.

---

## 5. Tích hợp với REST API của plugin

## 5.1 `includes/api/rest-gift.php`

File này không gọi trực tiếp Got It API client nhiều như `gotit-ajax.php`, nhưng nó đọc dữ liệu GotIt đã lưu để phục vụ API public.

### Vai trò liên quan GotIt

- Đọc transaction/issuance history.
- Gộp `gotit_expiry_date` từ transaction table.
- Trả `voucher_value`, `expiry_date`, `thumbnail_url` trong response lịch sử đổi quà.
- Đây là nơi consumer frontend thấy được dữ liệu GotIt đã issue.

### Ý nghĩa thực tế

- Khi voucher đã đổi xong, REST history cần hiển thị đúng thông tin voucher.
- `rest-gift.php` là lớp đọc, không phải lớp đồng bộ.

---

## 6. Tích hợp với admin UI

## 6.1 `includes/admin.php`

File này từng enqueue script picker/test cho GotIt voucher, nhưng luồng test UI đã bị vô hiệu hóa.

### Vai trò hiện tại

- Không còn bật manual picker/test flow cho GotIt voucher.
- Tránh admin UI test chồng lên luồng sync tự động.

## 6.2 `admin_dashboard/dashboard.php` và `admin_dashboard/setting.php`

Các file dashboard từng có trang test riêng cho GotIt, nhưng luồng test đã bị gỡ khỏi dashboard.

### Ý nghĩa

- GotIt hiện tập trung vào flow production.
- Không khuyến khích thao tác test thủ công từ dashboard nữa.

---

## 7. Dữ liệu lưu trong WordPress

### 7.1 Post type voucher

`game_vouchers` là nơi lưu voucher GotIt sau sync.

### 7.2 Post meta quan trọng

Một số meta key thường gặp:

- `voucher_type` = `THIRD_PARTY`
- `gotit_product_id`
- `gotit_product_price_id`
- `gotit_voucher_link`
- `gotit_voucher_code`
- `gotit_voucher_image`
- `gotit_serial`
- `gotit_expiry_date`
- `gotit_partner_expiry_date`
- `gotit_vendor_name`
- `gotit_is_partner_code`
- `voucher_display_name`
- `voucher_brand_name`
- `voucher_link_url`
- `voucher_image_url`
- `voucher_short_description`
- `voucher_long_description`
- `voucher_service_guide`
- `voucher_terms`
- `voucher_applicable_stores`
- `_game_bsc_gotit_applicable_stores_json`
- `_game_bsc_gotit_applicable_stores_source`

### 7.3 Term meta danh mục

- `_gotit_category_id`
- `_gotit_category_image`

### 7.4 Transaction table

Trong sync/issue test, data được ghi vào bảng `gotit_transactions` của prefix `game_`.

Các cột hay dùng:

- `redemption_id`
- `user_id`
- `voucher_post_id`
- `transaction_ref_id`
- `gotit_order_name`
- `gotit_product_id`
- `gotit_product_price_id`
- `gotit_voucher_link`
- `gotit_voucher_code`
- `gotit_voucher_image`
- `gotit_serial`
- `gotit_expiry_date`
- `gotit_partner_expiry_date`
- `gotit_vendor_name`
- `gotit_is_partner_code`
- `gotit_status`
- `gotit_raw_response`
- `gotit_status_code`
- `gotit_error_message`
- `created_at`
- `updated_at`

---

## 8. Luồng nghiệp vụ end-to-end

## 8.1 Sync voucher từ GotIt

1. Admin bấm sync ở list voucher hoặc cron queue job.
2. `gotit-ajax.php` tạo hoặc cập nhật async state.
3. Worker gọi `game_bsc_sync_gotit_products_to_vouchers()`.
4. Client lấy danh sách products.
5. Product được normalize.
6. Mỗi product/price tạo hoặc cập nhật `game_vouchers`.
7. Stores và terms được lưu vào meta.
8. Category GotIt được map sang taxonomy WordPress.
9. State cập nhật để UI poll tiến độ.

## 8.2 Issue voucher test

1. Admin gọi AJAX test issue.
2. Client tạo `transaction_ref_id`.
3. Client issue voucher qua Got It.
4. Response được parse qua `gotit-issue-parser.php`.
5. Transaction test được ghi vào `gotit_transactions`.
6. Kết quả trả về cho UI debug.

## 8.3 Check status / ref vouchers

1. UI hoặc REST call truyền `transaction_ref_id`.
2. Client gọi API status/ref.
3. Response được parse thành danh sách voucher và summary.
4. Hệ thống lưu hoặc hiển thị thông tin used/unused.

## 8.4 Read data cho public REST

1. Frontend gọi REST của plugin.
2. `rest-gift.php` đọc voucher post meta + transaction table.
3. Response public trả về dữ liệu chuẩn hóa cho UI.

---

## 9. Bảo mật và chuẩn hóa

### 9.1 Escape và sanitize

Code GotIt dùng nhiều lớp sanitize/escape:

- `sanitize_text_field()` cho text.
- `sanitize_email()` cho email.
- `esc_url_raw()` cho URL lưu trữ.
- `wp_kses_post()` cho HTML mô tả.
- `absint()` cho ID số nguyên.

### 9.2 Nonce và capability

AJAX test tools có guard riêng:

- Chỉ user có `admin_game` hoặc `administrator` mới đi tiếp.
- Nonce `game_bsc_gotit_test_nonce` được kiểm tra.

### 9.3 Retry và timeout

- Client có retry logic để giảm lỗi ngắt quãng.
- Timeout request được giới hạn để tránh treo worker.

---

## 10. Điểm cần nhớ khi sửa tiếp

- Không sửa trực tiếp vào `gotit-client.php` nếu chỉ là parse dữ liệu. Nên sửa ở normalizer/parser trước.
- Nếu thêm module mới, nạp trong `gotit-init.php` trước khi `gotit-ajax.php`.
- Nếu đổi tên hàm wrapper, phải rà toàn bộ callsite vì `rest-gift.php` và admin UI vẫn có thể đang dùng.
- Nếu định xóa wrapper cũ, chỉ làm sau khi xác nhận không còn callsite nào.
- Sync async đang dùng state machine, nên đừng biến lại thành sync một phát để tránh mất tiến độ và lock logic.

---

## 11. Tóm tắt ngắn

Nếu cần nhớ nhanh, chỉ cần 4 lớp này:

- `gotit-client.php`: gọi Got It API.
- `gotit-*.php` trong `helpers/normalizers/parsers`: chuẩn hóa dữ liệu.
- `gotit-ajax.php`: điều phối nghiệp vụ, AJAX, cron, wrapper.
- `rest-gift.php`: đọc dữ liệu GotIt đã lưu để trả cho REST public.

---

## 12. Ghi chú thực tế

- Phần GotIt test UI trên dashboard đã bị gỡ khỏi luồng chính.
- Các module GotIt hiện đã được chuyển sang `includes/admin/gotit`.
- `includes/api` chỉ còn các REST file không liên quan đến GotIt core.

