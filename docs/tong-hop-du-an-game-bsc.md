# Tổng hợp dự án `game-bsc`

## 1) Mục tiêu plugin
`WG Game BSC` là plugin WordPress phục vụ game hoá cho người dùng BSC, gồm:
- Đăng nhập SSO và đồng bộ user game
- Làm nhiệm vụ nhận lượt chơi (play credit)
- Chơi theo phiên trả lời câu hỏi
- Nhận thưởng (điểm / mảnh ghép), đổi quà (voucher / hiện vật)
- Dashboard quản trị và báo cáo
- Tích hợp Got It để phát hành voucher bên thứ 3

---

## 2) Công nghệ và phụ thuộc
- Nền tảng: WordPress Plugin (PHP)
- Phụ thuộc chính: `advanced-custom-fields-pro` (plugin yêu cầu), `phpoffice/phpspreadsheet` (import Excel)
- Composer: `wp-content/plugins/game-bsc/composer.json`

---

## 3) Điểm vào chính
- File plugin chính: `wp-content/plugins/game-bsc/game-bsc.php`
- Khởi tạo constants, session/cookie `utm_source`, include toàn bộ module, khai báo REST API, rate limit, helper nghiệp vụ.

Một số khối quan trọng trong file chính:
- SSO/session và user game (`save_game_user_to_db`, `game_sso_require_session`)
- Tính ngày/chặng game (`game_bsc_compute_day_index`, `game_bsc_compute_day_index_v2`)
- Hook bảo mật và giới hạn tần suất API (`rest_authentication_errors`, `rest_pre_dispatch`)
- Nạp các REST module (`includes/api/*.php`)

---

## 4) Cấu trúc thư mục chính
- `admin_dashboard/`: giao diện quản trị (dashboard, user, lịch sử chơi, log hệ thống, gotit test...)
- `assets/`: tài nguyên frontend/images/sample
- `config/`: cấu hình nhiệm vụ mẫu
- `docs/`: tài liệu nghiệp vụ (ví dụ voucher hoàn phí, artifact)
- `includes/`: lõi plugin
  - `admin.php`: menu admin, enqueue script, import tools
  - `install-tables.php`: schema DB + migrate theo version
  - `post-type.php`: CPT + taxonomy
  - `acf-fields.php`: định nghĩa field ACF
  - `templates.php`: inject template plugin vào page
  - `api/`: toàn bộ REST/AJAX logic
  - `helpers/`: helper dùng chung (inventory, rate limit...)
- `templates/`: template trang game
- `vendor/`: thư viện Composer

---

## 5) Dữ liệu và schema DB
Tạo/cập nhật bảng qua `includes/install-tables.php`, theo version `WG_GAME_PLUGIN_DB_VERSION`.

Các nhóm bảng chính:

### Người dùng và đăng nhập
- `game_users`
- `game_user_login_logs`

### Câu hỏi / phiên chơi
- `game_users_play_sessions`
- `game_users_session_answers`

### Nhiệm vụ và lượt chơi
- `game_user_mission_logs`
- `game_play_credit_balances`
- `game_play_credit_ledger`

### Thưởng / mảnh ghép / hiện vật
- `game_artifacts`
- `game_pieces`
- `game_user_pieces`
- `game_user_pieces_ledger`
- `game_user_progress`
- `game_drop_logs`
- `game_user_artifact_redemptions`

### Điểm / huy hiệu / voucher
- `game_user_points_balances`
- `game_user_points_ledger`
- `game_user_badges`
- `game_user_voucher_redemptions`

### Got It + voucher hoàn phí BSC
- `game_gotit_transactions`
- `game_bsc_fee_vouchers`
- `game_bsc_fee_voucher_usage_log`

### Audit cấu hình
- `game_settings_logs`

---

## 6) Mô hình nội dung WordPress (CPT/Taxonomy)
Đăng ký ở `includes/post-type.php`:
- `game_question`: ngân hàng câu hỏi
- `game_vouchers`: voucher đổi thưởng
- `game_badges`: huy hiệu
- Taxonomy `game_voucher_category` cho voucher

Có logic đảm bảo voucher luôn có danh mục mặc định `chua-phan-loai`.

---

## 7) REST API chính (`/wp-json/game-bsc/...`)
Namespace dùng hằng `NS = 'game-bsc'`.

### Init
- `GET /init` → trả HTML frontend game + inject nonce

### Session chơi
- `POST /session/start`
- `POST /session/answer`
- `POST /session/next`
- `GET /session/result`

### User
- `GET /user`
- `GET /user/stats`
- `GET /user/badges`
- `GET /user/badge-milestone`
- `GET /user/unviewed-badges`
- `GET /user/voucher-redemptions`
- `GET /user/points-added`
- `POST /user/logout`

### Missions
- `GET /missions`
- `POST /missions/check`
- `POST /missions/check-all`
- `GET /missions/notifications`

### Gift/Voucher
- `GET /mechanism`
- `GET /vouchers`
- `POST /gifts/redeem`
- `GET /user-pieces`
- `GET /my-redemptions`

### Lịch sử chơi/lượt
- `GET /play-session-history`
- `GET /play-session/{session_id}/questions`
- `GET /play-credit-history`

### Voucher hoàn phí BSC
- `GET /bsc-fee-vouchers`

### Rules
- `GET /rules`

---

## 8) Tích hợp Got It
Hai file chính:
- `includes/api/gotit-client.php`: API client (master data, issue voucher, check status, lấy voucher theo ref-id, xử lý authentication method/signature)
- `includes/api/gotit-ajax.php`: AJAX test/sync danh mục sản phẩm Got It sang voucher WP, async job, retry, debug, cron sync định kỳ.

Luồng chính:
1. Đồng bộ product/price từ Got It
2. Map sang `game_vouchers` + ACF fields `gotit_product_id`, `gotit_product_price_id`
3. Khi redeem THIRD_PARTY → issue voucher Got It
4. Lưu transaction vào `game_gotit_transactions`
5. Theo dõi trạng thái / retry theo transaction ref id

---

## 9) Dashboard quản trị
Màn hình chính ở `admin_dashboard/dashboard.php` với các tab:
- Dashboard tổng quan
- Quản lý user
- Lịch sử chơi
- Biến động lượt chơi
- Nhật ký hệ thống
- Danh sách quà đã đổi
- Got It Test

`includes/admin.php` đăng ký menu/submenu và nạp script cần thiết cho trang admin.

---

## 10) Bảo mật và vận hành
- Nhiều endpoint kiểm tra nonce/session trước khi xử lý
- Có kiểm tra trạng thái user bị khóa
- Có giới hạn tần suất gọi API theo user/IP (`60 request / 60s`)
- Có lock theo user/session ở flow chơi để giảm race condition
- DB transaction dùng nhiều tại các luồng ghi quan trọng (credit, ledger, redeem...)

---

## 11) Gợi ý cải thiện (ngắn)
- Chuẩn hóa `permission_callback` cho toàn bộ REST route (không chỉ `__return_true`)
- Tách bớt hàm lớn trong `rest-sessions.php`, `rest-gift.php`, `gotit-ajax.php`
- Bổ sung test tự động cho nghiệp vụ quan trọng (redeem, ledger, retry Got It)
- Tài liệu hóa API contract (OpenAPI) để FE/BE đồng bộ nhanh hơn

---

## 12) File tham chiếu nhanh
- Plugin bootstrap: `game-bsc.php`
- DB schema: `includes/install-tables.php`
- CPT/Taxonomy: `includes/post-type.php`
- ACF fields: `includes/acf-fields.php`
- Session game core: `includes/api/rest-sessions.php`
- User API: `includes/api/rest-users.php`
- Gift/Voucher API: `includes/api/rest-gift.php`
- Got It client: `includes/api/gotit-client.php`
- Got It AJAX/sync: `includes/api/gotit-ajax.php`
- Admin menu: `includes/admin.php`
- Dashboard UI: `admin_dashboard/dashboard.php`
