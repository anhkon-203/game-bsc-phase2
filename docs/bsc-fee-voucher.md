# BSC Fee Voucher - Voucher hoàn phí giao dịch BSC

> **Ngày tạo:** 2026-03-20
> **Trạng thái:** Đang phát triển
> **Plugin:** game-bsc

---

## 1. Tổng quan

Cho phép khách hàng dùng **điểm tích lũy** trong Game đổi lấy **Voucher hoàn phí giao dịch BSC**. Voucher này hoạt động như một ví giảm phí: mỗi lần khách hàng giao dịch chứng khoán trên nền tảng BSC, phí giao dịch sẽ được hoàn lại từ balance của voucher cho đến khi hết giá trị hoặc hết hạn.

### Điểm khác biệt với voucher hiện tại

| | Voucher thường / Got It | Voucher hoàn phí BSC |
|---|---|---|
| Cách dùng | Single-use, nhận code | Balance giảm dần theo giao dịch |
| Giá trị | Cố định, dùng 1 lần | Mệnh giá gốc, trừ dần |
| Hết hạn | Theo voucher post | Tính từ ngày đổi + N ngày |
| Tích hợp | Got It API (bên thứ 3) | BSC Trading System (nội bộ) |
| Hiển thị | Số lượng đã đổi | Số dư khả dụng còn lại |

---

## 2. Yêu cầu nghiệp vụ

### 2.1 Trang "Đổi quà"

Hiển thị các voucher hoàn phí giao dịch BSC với mệnh giá:

| Mệnh giá | Điểm cần đổi (admin cấu hình) |
|-----------|-------------------------------|
| 20,000 VND | Do admin thiết lập |
| 50,000 VND | Do admin thiết lập |
| 100,000 VND | Do admin thiết lập |
| 200,000 VND | Do admin thiết lập |
| 500,000 VND | Do admin thiết lập |

- Khách hàng đủ điểm → nhấn **"Đổi quà"** → trừ điểm, tạo voucher vào **Kho quà tặng**
- Tỷ lệ hoàn phí: **cố định 100%** (hoàn toàn bộ phí giao dịch đến khi hết balance)

### 2.2 Trang "Kho quà tặng"

**Thông tin hiển thị cho mỗi voucher:**

| Trường | Mô tả | Ví dụ |
|--------|-------|-------|
| Tên voucher | Tên hiển thị | "Voucher hoàn phí giao dịch 50,000đ" |
| Ngày bắt đầu | Ngày đổi voucher | 20/03/2026 |
| Ngày kết thúc | Ngày hết hạn | 19/04/2026 |
| Tỷ lệ hoàn phí | Phần trăm hoàn phí | 100% |
| Số dư khả dụng còn lại | Balance hiện tại | 35,000 VND |

**Quy tắc hiển thị:**

| # | Trường hợp | Hành vi |
|---|-----------|---------|
| 1 | Đã sử dụng hết giá trị (remaining_balance = 0) | **Xóa** khỏi Kho quà tặng |
| 2 | Đã sử dụng một phần (remaining_balance > 0) | **Hiển thị** với số dư còn lại |
| 3 | Chưa sử dụng (remaining_balance = denomination) | **Hiển thị** trong Kho quà tặng |
| 4 | Chưa sử dụng nhưng hết hạn (valid_to < NOW) | **Xóa** khỏi Kho quà tặng |

> **Ghi chú:** Khi đổi cùng 1 loại voucher nhiều lần, mỗi voucher hiển thị **riêng biệt** (không gộp).

---

## 3. Database Schema

### 3.1 Bảng `wp_game_bsc_fee_vouchers`

Lưu từng voucher instance đã được khách hàng đổi.

```sql
CREATE TABLE wp_game_bsc_fee_vouchers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    redemption_id   INT UNSIGNED    NOT NULL,           -- FK → user_voucher_redemptions.id
    voucher_post_id BIGINT UNSIGNED NOT NULL,           -- FK → WP posts (game_vouchers)
    denomination    INT             NOT NULL,            -- Mệnh giá gốc: 20000, 50000,...
    remaining_balance INT           NOT NULL,            -- Số dư khả dụng còn lại
    fee_refund_rate DECIMAL(5,2)    NOT NULL DEFAULT 100.00, -- Tỷ lệ hoàn phí (%)
    status          ENUM('ACTIVE','USED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
    valid_from      DATETIME        NOT NULL,
    valid_to        DATETIME        NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_user_status (user_id, status),
    KEY idx_user_valid  (user_id, valid_to),
    KEY idx_redemption  (redemption_id)
) ENGINE=InnoDB;
```

**Status lifecycle:**
```
ACTIVE → USED      (remaining_balance = 0, tự động chuyển khi trừ hết)
ACTIVE → EXPIRED   (valid_to < NOW(), WP-Cron hoặc API expire)
```

### 3.2 Bảng `wp_game_bsc_fee_voucher_usage_log`

Log chi tiết mỗi lần trừ balance voucher khi khách hàng giao dịch.

```sql
CREATE TABLE wp_game_bsc_fee_voucher_usage_log (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_instance_id  BIGINT UNSIGNED NOT NULL,      -- FK → bsc_fee_vouchers.id
    user_id              INT UNSIGNED    NOT NULL,
    trade_order_id       VARCHAR(128)    NOT NULL,       -- Mã lệnh giao dịch (idempotency key)
    trade_fee_amount     INT             NOT NULL,       -- Phí giao dịch gốc
    refund_amount        INT             NOT NULL,       -- Số tiền được hoàn
    balance_before       INT             NOT NULL,
    balance_after        INT             NOT NULL,
    created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_trade_order    (trade_order_id),
    KEY idx_voucher_instance       (voucher_instance_id),
    KEY idx_user_created           (user_id, created_at)
) ENGINE=InnoDB;
```

### 3.3 Quan hệ giữa các bảng

```
wp_posts (game_vouchers)
    └── wp_game_user_voucher_redemptions (mỗi lần đổi)
            └── wp_game_bsc_fee_vouchers (1 instance voucher)
                    └── wp_game_bsc_fee_voucher_usage_log (N lần sử dụng)
```

---

## 4. ACF Fields bổ sung

Thêm vào post type `game_vouchers`, conditional hiện khi `voucher_type = BSC`:

| Field Name | Field Key | Type | Mô tả | Default |
|------------|-----------|------|--------|---------|
| `is_fee_voucher` | `field_is_fee_voucher` | True/False | Đánh dấu là voucher hoàn phí giao dịch | `false` |
| `fee_voucher_denomination` | `field_fee_voucher_denomination` | Number | Mệnh giá voucher (VND) | — |
| `fee_voucher_validity_days` | `field_fee_voucher_validity_days` | Number | Số ngày hiệu lực kể từ ngày đổi | `30` |

---

## 5. API Endpoints

### 5.1 Internal APIs (Game Frontend → Plugin)

Auth: **SSO Session + Nonce** (`X-WP-Nonce`)

#### A. Danh sách voucher đổi quà (đã có)

```
GET /wp-json/game-bsc/v1/vouchers
```

Không cần sửa. Voucher hoàn phí BSC là các WordPress posts `game_vouchers` với:
- `voucher_type = BSC`
- `is_fee_voucher = 1`

Frontend filter hiển thị dựa trên field `is_fee_voucher`.

Response bổ sung field:
```json
{
  "is_fee_voucher": true,
  "fee_voucher_denomination": 50000,
  "fee_voucher_validity_days": 30
}
```

---

#### B. Đổi voucher (đã có - sửa logic)

```
POST /wp-json/game-bsc/v1/gifts/redeem
```

Body: `{ "type": "voucher", "id": <voucher_post_id> }`

**Logic bổ sung sau khi redeem thành công:**

```
IF is_fee_voucher = true:
    INSERT INTO bsc_fee_vouchers (
        user_id, redemption_id, voucher_post_id,
        denomination,               -- từ ACF fee_voucher_denomination
        remaining_balance,           -- = denomination
        fee_refund_rate,             -- 100.00
        status,                      -- 'ACTIVE'
        valid_from,                  -- NOW()
        valid_to                     -- NOW() + fee_voucher_validity_days ngày
    )
```

Response bổ sung:
```json
{
  "item": {
    "...existing fields...",
    "is_fee_voucher": true,
    "fee_voucher_instance": {
      "id": 1,
      "denomination": 50000,
      "remaining_balance": 50000,
      "fee_refund_rate": 100.00,
      "valid_from": "2026-03-20 10:00:00",
      "valid_to": "2026-04-19 10:00:00",
      "status": "ACTIVE"
    }
  }
}
```

---

#### C. Kho quà tặng - BSC Fee Vouchers (MỚI)

```
GET /wp-json/game-bsc/v1/bsc-fee-vouchers
```

Trả về danh sách voucher hoàn phí **đang còn hiệu lực** của user hiện tại.

**Điều kiện hiển thị (WHERE):**
```sql
WHERE user_id = ?
  AND status = 'ACTIVE'
  AND valid_to >= NOW()
  AND remaining_balance > 0
ORDER BY valid_to ASC
```

**Response:**
```json
{
  "resCode": 200,
  "data": {
    "vouchers": [
      {
        "id": 1,
        "voucher_post_id": 456,
        "voucher_name": "Voucher hoàn phí giao dịch 50,000đ",
        "thumbnail_url": "https://...",
        "denomination": 50000,
        "remaining_balance": 35000,
        "fee_refund_rate": 100.00,
        "valid_from": "2026-03-20 10:00:00",
        "valid_to": "2026-04-19 10:00:00",
        "status": "ACTIVE",
        "redeemed_at": "2026-03-20 10:00:00"
      }
    ],
    "total": 1
  },
  "message": "Lấy danh sách voucher hoàn phí thành công."
}
```

---

#### D. Kho quà tặng tổng hợp (đã có - sửa logic)

```
GET /wp-json/game-bsc/v1/my-redemptions
```

**Sửa:** Với voucher là BSC fee voucher:
- LEFT JOIN `bsc_fee_vouchers` để lấy thông tin balance
- Ẩn voucher có `status = USED` hoặc `EXPIRED` (theo 4 quy tắc hiển thị)
- Mỗi voucher instance hiển thị riêng (KHÔNG group by voucher_post_id cho fee voucher)

---

### 5.2 External APIs (BSC Trading System → Plugin)

Auth: **API Key** via header `X-BSC-Api-Key`
Base: `/wp-json/game-bsc/v1/bsc-api/`

> **⚠️ LƯU Ý QUAN TRỌNG:**
> Hiện tại BSC **chưa cung cấp** API specification cho phần check phí giao dịch từ hệ thống trading.
> Các endpoint bên dưới được thiết kế sẵn để BSC trading system gọi vào.
> Khi BSC cung cấp spec, có thể cần điều chỉnh request/response format cho phù hợp.

---

#### E. Trừ balance voucher khi giao dịch (MỚI)

```
POST /wp-json/game-bsc/v1/bsc-api/voucher/deduct
```

**Headers:**
```
X-BSC-Api-Key: <api_key>
Content-Type: application/json
```

**Request Body:**
```json
{
  "user_external_id": "123456",
  "trade_order_id": "ORD-2026032001",
  "trade_fee_amount": 15000
}
```

| Field | Type | Bắt buộc | Mô tả |
|-------|------|----------|-------|
| `user_external_id` | string | ✅ | Mã khách hàng BSC (external_user_id từ SSO) |
| `trade_order_id` | string | ✅ | Mã lệnh giao dịch, dùng làm **idempotency key** |
| `trade_fee_amount` | int | ✅ | Phí giao dịch cần hoàn (VND) |

**Logic xử lý:**

```
1. Validate API Key
2. Lookup user bằng external_user_id → user_id
3. Check idempotency:
   - Nếu trade_order_id đã tồn tại → trả kết quả cũ (KHÔNG trừ thêm)
4. Tìm voucher ACTIVE, còn balance, chưa hết hạn
   → ORDER BY valid_to ASC (dùng voucher sắp hết hạn trước)
5. SELECT ... FOR UPDATE (lock row tránh race condition)
6. Tính refund:
   refund = MIN(trade_fee_amount × fee_refund_rate/100, remaining_balance)
7. Trừ balance:
   remaining_balance -= refund
8. Nếu remaining_balance = 0 → status = 'USED'
9. Ghi log vào bsc_fee_voucher_usage_log
10. Nếu 1 voucher không đủ → cascade sang voucher tiếp theo
11. COMMIT transaction
```

**Response thành công (200):**
```json
{
  "resCode": 200,
  "data": {
    "trade_order_id": "ORD-2026032001",
    "trade_fee_amount": 15000,
    "total_refunded": 15000,
    "deductions": [
      {
        "voucher_instance_id": 1,
        "refund_amount": 15000,
        "balance_before": 35000,
        "balance_after": 20000
      }
    ]
  },
  "message": "Hoàn phí giao dịch thành công."
}
```

**Response không đủ balance (200, partial refund):**
```json
{
  "resCode": 200,
  "data": {
    "trade_order_id": "ORD-2026032002",
    "trade_fee_amount": 80000,
    "total_refunded": 50000,
    "deductions": [
      {
        "voucher_instance_id": 1,
        "refund_amount": 20000,
        "balance_before": 20000,
        "balance_after": 0
      },
      {
        "voucher_instance_id": 2,
        "refund_amount": 30000,
        "balance_before": 50000,
        "balance_after": 20000
      }
    ]
  },
  "message": "Hoàn phí giao dịch thành công (một phần)."
}
```

**Response không có voucher nào:**
```json
{
  "resCode": 404,
  "data": {
    "trade_order_id": "ORD-2026032003",
    "trade_fee_amount": 15000,
    "total_refunded": 0,
    "deductions": []
  },
  "message": "Khách hàng không có voucher hoàn phí khả dụng."
}
```

**Error Responses:**

| HTTP | resCode | Trường hợp |
|------|---------|-----------|
| 401 | 401 | API Key không hợp lệ hoặc thiếu |
| 400 | 400 | Thiếu param bắt buộc |
| 404 | 404 | Không tìm thấy user |
| 409 | 409 | trade_order_id đã xử lý (trả kết quả cũ) |

---

#### F. Query số dư voucher (MỚI)

```
GET /wp-json/game-bsc/v1/bsc-api/voucher/balance?user_external_id=123456
```

**Headers:**
```
X-BSC-Api-Key: <api_key>
```

**Response:**
```json
{
  "resCode": 200,
  "data": {
    "user_external_id": "123456",
    "total_available_balance": 85000,
    "active_vouchers_count": 2,
    "vouchers": [
      {
        "id": 1,
        "denomination": 50000,
        "remaining_balance": 35000,
        "fee_refund_rate": 100.00,
        "valid_from": "2026-03-20 10:00:00",
        "valid_to": "2026-04-19 10:00:00"
      },
      {
        "id": 2,
        "denomination": 50000,
        "remaining_balance": 50000,
        "fee_refund_rate": 100.00,
        "valid_from": "2026-03-21 14:00:00",
        "valid_to": "2026-04-20 14:00:00"
      }
    ]
  },
  "message": "Lấy số dư voucher thành công."
}
```

---

#### G. Expire voucher hết hạn (Batch/Cron)

```
POST /wp-json/game-bsc/v1/bsc-api/voucher/expire-check
```

Hoặc tự động qua **WP-Cron** (chạy 1 lần/ngày).

**Logic:**
```sql
UPDATE bsc_fee_vouchers
SET status = 'EXPIRED', updated_at = NOW()
WHERE valid_to < NOW()
  AND status = 'ACTIVE'
```

**Response:**
```json
{
  "resCode": 200,
  "data": {
    "expired_count": 3
  },
  "message": "Đã xử lý 3 voucher hết hạn."
}
```

---

## 6. Luồng xử lý tổng quan

### 6.1 Luồng đổi voucher (User → Game)

```
User (Game Frontend)
  │
  ├─ 1. GET /vouchers          → Xem danh sách voucher (lọc is_fee_voucher=1)
  │
  ├─ 2. POST /gifts/redeem     → Nhấn "Đổi quà"
  │      ├─ Trừ điểm user
  │      ├─ Ghi user_voucher_redemptions
  │      ├─ Ghi user_points_ledger (ref_type=VOUCHER)
  │      └─ ★ Tạo bsc_fee_vouchers (ACTIVE, balance=denomination)
  │
  └─ 3. GET /bsc-fee-vouchers  → Xem Kho quà tặng
         └─ Hiển thị voucher ACTIVE, chưa hết hạn, còn balance
```

### 6.2 Luồng sử dụng voucher (BSC Trading → Game)

```
BSC Trading System
  │
  ├─ 1. GET /bsc-api/voucher/balance    → Kiểm tra user có voucher không
  │
  ├─ 2. POST /bsc-api/voucher/deduct    → Trừ balance khi user giao dịch
  │      ├─ Lock voucher row (SELECT FOR UPDATE)
  │      ├─ Trừ remaining_balance
  │      ├─ Cascade nếu 1 voucher không đủ
  │      ├─ Set USED nếu balance = 0
  │      └─ Ghi bsc_fee_voucher_usage_log
  │
  └─ 3. WP-Cron (daily)                 → Auto-expire voucher hết hạn
```

### 6.3 Sequence Diagram

```
┌──────┐     ┌───────────┐     ┌────────────┐     ┌──────────────┐
│ User │     │ Game FE   │     │ Game API   │     │ BSC Trading  │
└──┬───┘     └─────┬─────┘     └──────┬─────┘     └──────┬───────┘
   │               │                  │                   │
   │  Xem voucher  │                  │                   │
   │──────────────>│  GET /vouchers   │                   │
   │               │─────────────────>│                   │
   │               │<─────────────────│                   │
   │               │                  │                   │
   │  Đổi quà      │                  │                   │
   │──────────────>│ POST /redeem     │                   │
   │               │─────────────────>│                   │
   │               │                  │──┐ Trừ điểm       │
   │               │                  │  │ Tạo instance   │
   │               │                  │<─┘                │
   │               │<─────────────────│                   │
   │               │                  │                   │
   │  Xem kho quà  │                  │                   │
   │──────────────>│ GET /bsc-fee-    │                   │
   │               │  vouchers        │                   │
   │               │─────────────────>│                   │
   │               │<─────────────────│                   │
   │               │                  │                   │
   │               │                  │   User giao dịch  │
   │               │                  │<──────────────────│
   │               │                  │  POST /deduct     │
   │               │                  │──┐ Lock + trừ     │
   │               │                  │  │ balance        │
   │               │                  │<─┘                │
   │               │                  │──────────────────>│
   │               │                  │  Response         │
   │               │                  │                   │
```

---

## 7. Bảo mật

### 7.1 Internal APIs (Frontend)
- **SSO Session:** Kiểm tra `game_sso_require_session()`
- **Nonce:** Header `X-WP-Nonce` validate qua `game_rest_perm_cb()`
- **User status:** Kiểm tra user tồn tại và không bị khóa

### 7.2 External APIs (BSC Trading)
- **API Key:** Header `X-BSC-Api-Key`, lưu trong WP options `game_bsc_external_api_key`
- **IP Whitelist (optional):** WP option `game_bsc_external_api_ip_whitelist`
- **Idempotency:** `trade_order_id` UNIQUE constraint, tránh trừ trùng
- **Row locking:** `SELECT ... FOR UPDATE` tránh race condition
- **Rate limiting:** Có thể bổ sung sau nếu cần

### 7.3 Admin Settings

| Option Key | Mô tả |
|------------|-------|
| `game_bsc_external_api_key` | API Key cho BSC Trading gọi vào |
| `game_bsc_external_api_ip_whitelist` | Danh sách IP được phép (comma-separated, optional) |

---

## 8. File cần sửa / tạo mới

| File | Hành động | Nội dung |
|------|-----------|----------|
| `game-bsc.php` | **Sửa** | Tăng `WG_GAME_PLUGIN_DB_VERSION` → `24.0`, require file mới, đăng ký WP-Cron |
| `includes/install-tables.php` | **Sửa** | Thêm 2 bảng mới |
| `includes/acf-fields.php` | **Sửa** | Thêm 3 ACF fields |
| `includes/api/rest-gift.php` | **Sửa** | Sửa redeem + my-redemptions, thêm endpoint `/bsc-fee-vouchers` |
| `includes/api/rest-bsc-external.php` | **TẠO MỚI** | External API: deduct, balance, expire-check |
| `includes/admin/settings.php` | **Sửa** | Thêm settings API key |
| `includes/helpers/voucher-list.php` | **Sửa** | Thêm cột BSC fee voucher vào admin list |

---

## 9. Trạng thái tích hợp BSC Trading

### ⚠️ Pending: API Check phí giao dịch từ BSC

Hiện tại BSC **chưa cung cấp** specification cho API check phí giao dịch từ hệ thống trading. Các endpoint external API (Section 5.2) được thiết kế sẵn dựa trên yêu cầu nghiệp vụ.

**Khi BSC cung cấp API spec, cần review lại:**

| Hạng mục | Cần xác nhận |
|----------|-------------|
| Auth method | API Key header hay phương thức khác? |
| User identifier | `external_user_id` (custodycd) hay trường khác? |
| Trade order ID format | Format chuỗi mã lệnh giao dịch |
| Fee amount | Đơn vị VND hay đơn vị khác? Có bao gồm VAT? |
| Callback vs Poll | BSC gọi sang game (push) hay game query BSC (pull)? |
| Partial refund | BSC có cần biết kết quả trước khi hoàn phí hay tự động? |
| Error handling | Retry policy khi API lỗi |
| IP Whitelist | Danh sách IP server BSC Trading |

**Mô hình hiện tại (thiết kế sẵn):**
- **Push model:** BSC Trading gọi `POST /bsc-api/voucher/deduct` sau mỗi giao dịch
- Có thể chuyển sang **Pull model** nếu BSC yêu cầu (game query BSC API để lấy lịch sử giao dịch)

---

## 10. Test Plan

### 10.1 Unit Tests

| # | Test case | Expected |
|---|-----------|----------|
| 1 | Tạo voucher BSC fee trên admin | ACF fields hiển thị đúng conditional logic |
| 2 | Đổi voucher BSC fee (đủ điểm) | Trừ điểm, tạo record bsc_fee_vouchers, status=ACTIVE |
| 3 | Đổi voucher BSC fee (thiếu điểm) | Trả 403, không tạo record |
| 4 | GET /bsc-fee-vouchers | Trả voucher ACTIVE, chưa hết hạn, còn balance |
| 5 | GET /bsc-fee-vouchers (voucher hết hạn) | Không trả voucher hết hạn |
| 6 | GET /bsc-fee-vouchers (voucher đã dùng hết) | Không trả voucher balance=0 |

### 10.2 Integration Tests (Khi có BSC API)

| # | Test case | Expected |
|---|-----------|----------|
| 7 | Deduct - đủ balance 1 voucher | Trừ đúng, balance giảm |
| 8 | Deduct - cascade 2 vouchers | Trừ hết voucher 1, tiếp voucher 2 |
| 9 | Deduct - idempotency (cùng trade_order_id) | Trả kết quả cũ, KHÔNG trừ thêm |
| 10 | Deduct - không có voucher | Trả 404, total_refunded=0 |
| 11 | Deduct - sai API key | Trả 401 |
| 12 | Balance query | Trả tổng balance + chi tiết |
| 13 | WP-Cron expire | Voucher hết hạn → status=EXPIRED |

---

## 11. Changelog

| Ngày | Nội dung | Người thực hiện |
|------|----------|----------------|
| 2026-03-20 | Khởi tạo tài liệu | Claude Code |
| | BSC cung cấp API spec | _Pending_ |
| | Hoàn thành implementation | _Pending_ |
| | UAT testing | _Pending_ |
| | Go-live | _Pending_ |
