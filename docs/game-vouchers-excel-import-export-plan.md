# Plan: Export/Import Excel cho game_vouchers

## 1) Muc tieu

Tren trang `wp-admin/edit.php?post_type=game_vouchers`, bo sung chuc nang:

- Export Excel danh sach voucher.
- Import Excel de cap nhat hang loat `Diem can de doi voucher` (field ACF: `points_cost`).

Muc tieu chinh: admin co the sua nhanh `points_cost` tren file Excel va import lai an toan, co bao cao loi ro rang, khong gay regression voi luong dong bo Got It.

## 2) Hien trang lien quan trong codebase

- CPT voucher: `game_vouchers` trong `includes/post-type.php`.
- Field diem doi voucher: ACF `points_cost` (`field_points_cost`) trong `includes/acf-fields.php`.
- Plugin da co pattern import qua `admin_post + nonce` trong `includes/admin/manage-question-excel.php`.
- Plugin da co `PhpSpreadsheet` (qua `vendor/autoload.php` trong `game-bsc.php`).
- Trang list voucher da co UI JS inject o `admin_footer-edit.php` (nut Sync Got It) trong `includes/api/gotit-ajax.php`.
- Luong sync Got It dang set `points_cost`, nhung uu tien gia tri hien tai neu > 0.

## 3) Pham vi va nguyen tac

### 3.1 Pham vi

- Chi ap dung cho post type `game_vouchers`.
- Import chi cap nhat `points_cost` (khong cap nhat title, quantity, voucher_type, ...).
- Khong thay doi API frontend/redeem flow hien tai.

### 3.2 Nguyen tac van hanh

- Co 2 che do import:
  - `Dry-run`: parse + validate + thong ke loi, KHONG ghi DB.
  - `Apply`: ghi DB theo batch sau khi file hop le.
- Moi thao tac deu co capability check + nonce + audit log.
- Mac dinh giu `points_cost >= 1` de tranh truong hop bi sync Got It gan lai khi gia tri = 0.

## 4) Thiet ke ky thuat toi uu

## 4.1 Hook de gan UI tren edit.php?post_type=game_vouchers

De toi uu theo hien trang plugin (dang dung JS inject tren list page), de xuat:

- `admin_footer-edit.php`
  - Inject them 2 nut: `Export Excel`, `Import Excel` gan canh nut `Sync voucher` hien tai.
  - Reuse cach xac dinh screen: `post_type=game_vouchers` + `base=edit`.
- `admin_enqueue_scripts`
  - Chi enqueue JS/CSS import-export tai dung screen.

Hoac (du phong) co the render server-side bang `manage_posts_extra_tablenav` neu muon han che JS inject.

## 4.2 Endpoint backend

- `admin_post_game_bsc_export_vouchers_excel`
  - Stream file `.xlsx` cho admin tai xuong.
- `admin_post_game_bsc_import_vouchers_excel`
  - Nhan file + mode (`dry-run`/`apply`) + validate + xu ly.

## 4.3 Cau truc file Excel

Sheet: `voucher_points`

Cot de xuat:

1. `voucher_id` (bat buoc, readonly)
2. `voucher_code` (readonly)
3. `voucher_title` (readonly)
4. `voucher_type` (readonly)
5. `gotit_product_id` (readonly)
6. `gotit_product_price_id` (readonly)
7. `points_cost` (bat buoc, cho phep sua)
8. `snapshot_post_modified_gmt` (readonly, phuc vu conflict check)

Chi cho phep nguoi dung sua cot `points_cost`.

## 4.4 Rule validate import

Moi dong du lieu:

- `voucher_id`: so nguyen duong, ton tai, `post_type=game_vouchers`.
- `points_cost`: numeric integer, `>= 1`.
- Khong cho trung `voucher_id` trong cung file.
- Neu `snapshot_post_modified_gmt` lech DB hien tai:
  - Danh dau `conflict`.
  - Mac dinh skip khi `apply` (co thong ke).
- Neu `points_cost` khong doi so voi DB: `skip_unchanged`.

Ket qua tra ve:

- `valid_rows`, `updated_rows`, `skipped_rows`, `conflict_rows`, `error_rows`.
- Danh sach loi theo dong (line number + message).

## 4.5 Bao mat

- Capability: chi `admin_game` hoac `administrator`.
- Nonce rieng cho export/import.
- Validate file upload:
  - Extension `.xlsx` (co the mo rong `.csv` ve sau neu can).
  - MIME check + parse that bai thi reject.
  - Gioi han dung luong theo config plugin.
- Sanitize input:
  - `voucher_id` -> `absint`.
  - `points_cost` -> ep int, validate range.
  - Text -> `sanitize_text_field`.
- Audit log day du thao tac import.

## 4.6 Hieu nang va do on dinh

- Export:
  - Query chi lay field can thiet.
  - Khong load object du thua.
- Import:
  - Dung `PhpSpreadsheet` voi read mode tiet kiem bo nho (`setReadDataOnly(true)`).
  - Xu ly theo batch (de xuat 200 dong/batch).
  - Voi file lon (>1500 dong): dua vao async queue (co lock transient de tranh chay trung).
  - Co progress state de admin theo doi.

## 4.7 Chien luoc tranh xung dot voi sync Got It

Rui ro:

- Got It sync co the cap nhat `points_cost` trong mot so tinh huong.

Giai phap de xuat:

1. Rule import bat buoc `points_cost >= 1`.
2. Khi apply import thanh cong, set meta lock:
   - `_game_bsc_points_cost_locked = 1`
   - `_game_bsc_points_cost_locked_by`
   - `_game_bsc_points_cost_locked_at`
3. Trong luong sync Got It (`includes/api/gotit-ajax.php`):
   - Neu co lock -> khong ghi de `points_cost`.
4. Co co che mo lock thu cong (neu can) trong settings/maintenance.

## 5) Ke hoach trien khai theo phase

### Phase A - Discovery (0.5 ngay cong)

- Chot contract file Excel.
- Chot validation + conflict policy.
- Chot UX (button placement, thong bao ket qua).

### Phase B - Design (0.5 ngay cong)

- Thiet ke hook, endpoint, flow dry-run/apply.
- Thiet ke schema audit log.
- Thiet ke lock policy voi Got It sync.

### Phase C - Build (2.0 ngay cong)

- Tao module import/export voucher.
- Them UI tren list page.
- Tao endpoint export/import.
- Them audit log + migration.
- Them lock va update logic sync Got It.

### Phase D - Test (1.0 ngay cong)

- Unit/smoke test importer.
- UAT tren staging voi file nho/vua/lon.
- Regression test redeem flow va sync Got It.

### Phase E - Rollout (0.5 ngay cong)

- Backup DB.
- Deploy staging -> production.
- Theo doi log 3-5 ngay.
- Ban giao tai lieu su dung cho admin.

Tong estimate: **4.5 ngay cong**.

## 6) Danh sach file du kien tao/sua

### Tao moi

- `wp-content/plugins/game-bsc/includes/admin/manage-voucher-excel.php`
- `wp-content/plugins/game-bsc/assets/js/voucher-excel-admin.js`
- `wp-content/plugins/game-bsc/assets/samples/sample-voucher-points.xlsx`

### Sua

- `wp-content/plugins/game-bsc/includes/admin.php`
  - require module moi.
  - hook enqueue script/import endpoint helper.
- `wp-content/plugins/game-bsc/includes/install-tables.php`
  - tao bang log import (neu chua co).
- `wp-content/plugins/game-bsc/game-bsc.php`
  - bump `WG_GAME_PLUGIN_DB_VERSION`.
- `wp-content/plugins/game-bsc/includes/api/gotit-ajax.php`
  - ton trong `_game_bsc_points_cost_locked` khi sync.

## 7) De xuat bang log audit import

Ten bang: `wp_game_voucher_points_import_history`

Cot de xuat:

- `id` (PK)
- `file_name`
- `file_url`
- `file_author`
- `mode` (`dry-run`/`apply`)
- `total_rows`
- `updated_rows`
- `skipped_rows`
- `conflict_rows`
- `error_rows`
- `summary_json`
- `uploaded_at`

## 8) Checklist test case

1. User khong du quyen khong the export/import.
2. Nonce sai bi chan.
3. File sai dinh dang bi reject.
4. Dry-run file hop le: khong doi DB, co thong ke dung.
5. Apply file hop le: cap nhat dung `points_cost`.
6. Co dong `voucher_id` khong ton tai: bao loi dung dong.
7. Co dong `points_cost` am/chu: bao loi dung dong.
8. Co duplicate `voucher_id`: bao loi.
9. Conflict snapshot: skip va thong bao.
10. File lon: khong timeout, khong vo memory.
11. Sau import, chay sync Got It khong de `points_cost` da lock.
12. File export mo duoc tren Excel, khong vo ky tu tieng Viet.

## 9) Tieu chi nghiem thu

- Co nut Export/Import tren dung trang list `game_vouchers`.
- Import dry-run va apply hoat dong dung theo design.
- Bao mat dat yeu cau capability + nonce + file validation.
- Co audit log truy vet.
- Khong anh huong flow redeem voucher va sync Got It.

## 10) Luong thao tac de xuat cho admin

1. Vao `wp-admin/edit.php?post_type=game_vouchers`.
2. Bam `Export Excel`.
3. Sua cot `points_cost`.
4. Upload file va chay `Dry-run`.
5. Neu khong con loi, chay `Apply`.
6. Xem thong bao ket qua + lich su import.
