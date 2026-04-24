# Giới hạn 1 hiện vật / user / đợt game + Safe-piece + Auto-redeem

> **Ngày cập nhật:** 2026-04-24
> **Phiên bản:** 1.2 (fix auto-redeem trừ mảnh + bổ sung trúng quà vào session/result)

---

## Mục lục

1. [Tổng quan yêu cầu](#1-tổng-quan-yêu-cầu)
2. [Vấn đề với phiên bản cũ](#2-vấn-đề-với-phiên-bản-cũ)
3. [Giải pháp: Scoping theo đợt game](#3-giải-pháp-scoping-theo-đợt-game)
4. [Kiến trúc 2 tầng ngày tháng](#4-kiến-trúc-2-tầng-ngày-tháng)
5. [Chi tiết hàm `game_user_has_completed_artifact()`](#5-chi-tiết-hàm-game_user_has_completed_artifact)
6. [Cơ chế Safe-piece](#6-cơ-chế-safe-piece)
7. [Auto-redeem khi đủ 4 mảnh](#7-auto-redeem-khi-đủ-4-mảnh)
8. [Manual redeem (API đổi quà)](#8-manual-redeem-api-đổi-quà)
9. [3 lớp bảo vệ Defense-in-Depth](#9-3-lớp-bảo-vệ-defense-in-depth)
10. [Các API bị ảnh hưởng](#10-các-api-bị-ảnh-hưởng)
11. [Database & Query](#11-database--query)
12. [Kịch bản minh họa](#12-kịch-bản-minh-họa)
13. [Cấu hình Admin](#13-cấu-hình-admin)
14. [Lưu ý khi vận hành](#14-lưu-ý-khi-vận-hành)

---

## 1. Tổng quan yêu cầu

Theo `nang_cap_co_che_trung_qua.md` (mục 3 & 4):

> - Suốt thời gian diễn ra Game, **1 user chỉ được nhận 1 quà tặng hiện vật**.
> - Khi đã nhận 1 hiện vật, các hiện vật khác chỉ được rơi các mảnh khác nhau, không đủ 4 mảnh ghép.
> - Khi user thu thập đủ 4 mảnh ghép: **tự động tung popup thông báo trúng quà**, không cần nhấn "Đổi quà".

**Bổ sung (v1.1):** Giới hạn "1 hiện vật" được tính **theo đợt game** (khoảng `start_date` → `end_date` trong Settings), không phải vĩnh viễn. Khi admin thay đổi ngày game sang đợt mới, user được reset và có thể nhận hiện vật mới.

---

## 2. Vấn đề với phiên bản cũ

### Code cũ (v1.0)

```php
// artifact-period.php — PHIÊN BẢN CŨ
function game_user_has_completed_artifact( int $user_id ): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'game_user_artifact_redemptions';
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
        $user_id
    ) );
    return $count > 0;
}
```

### Vấn đề

| # | Mô tả |
|---|-------|
| 1 | Query `COUNT(*) WHERE user_id = %d` **không có filter ngày** → check vĩnh viễn |
| 2 | User nhận hiện vật ở đợt game tháng 1 → sang đợt game tháng 4 vẫn bị chặn |
| 3 | Safe-piece kích hoạt vĩnh viễn → user không bao giờ có cơ hội nhận hiện vật mới |
| 4 | Pity system bị skip vĩnh viễn (vì `$user_already_completed = true` mãi) |

---

## 3. Giải pháp: Scoping theo đợt game

### Nguyên tắc

Hàm `game_user_has_completed_artifact()` chỉ đếm các redemption có `redeemed_at` nằm trong khoảng **đợt game hiện tại** (`game_bsc_start_date` → `game_bsc_end_date`).

### Hiệu ứng

| Khi | Kết quả |
|-----|---------|
| User có redemption **trong** đợt game hiện tại | `return true` → chặn nhận thêm |
| User có redemption **ngoài** đợt game (đợt cũ) | `return false` → cho phép nhận mới |
| Admin chưa cấu hình ngày game | Fallback: check toàn bộ (an toàn, tương thích ngược) |

---

## 4. Kiến trúc 2 tầng ngày tháng

Hệ thống có **2 tầng ngày tháng hoàn toàn tách biệt**:

```
┌──────────────────────────────────────────────────────────────┐
│  TẦNG 1: GAME-LEVEL (đợt game)                              │
│  Options: game_bsc_start_date / game_bsc_end_date            │
│  Set tại: ?page=game-bsc-settings (Tab "General Settings")  │
│  Dùng bởi:                                                   │
│    • game_check_play_time_allowed() — chặn vào game          │
│    • game_user_has_completed_artifact() — scope redemption   │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│  TẦNG 2: ARTIFACT-LEVEL (thời hạn hiện vật)                 │
│  Columns: period_start / period_end (bảng wp_game_artifacts) │
│  Set tại: Admin quản lý hiện vật                             │
│  Dùng bởi:                                                   │
│    • game_artifact_is_within_period() — hiện vật còn active? │
│    • game_artifact_current_period() — kỳ nào?                │
│    • game_artifact_period_has_quota() — còn quota kỳ?        │
└──────────────────────────────────────────────────────────────┘
```

### Flow kiểm tra khi user chơi game

```
User gọi API (session/answer)
      │
      ▼
┌─────────────────────────────────────┐
│ game_check_play_time_allowed()      │ ← Tầng 1: game-level dates
│ Ngoài ngày game? → CHẶN 403        │
│ Trong ngày game? → CHO QUA ↓       │
└──────────────┬──────────────────────┘
               ▼
┌─────────────────────────────────────┐
│ game_get_random_reward()            │
│                                     │
│ ├─ game_artifact_is_within_period() │ ← Tầng 2: artifact-level dates
│ ├─ game_artifact_period_has_quota() │ ← Tầng 2: quota kỳ
│ ├─ game_user_has_completed_artifact │ ← Tầng 1: scope theo đợt game
│ ├─ game_check_pity()               │
│ └─ game_pick_safe_piece()          │
└─────────────────────────────────────┘
```

---

## 5. Chi tiết hàm `game_user_has_completed_artifact()`

### File

`includes/helpers/artifact-period.php`, dòng 140–178

### Code hiện tại (v1.1)

```php
/**
 * Kiểm tra xem user đã hoàn thành (redeem) 1 bộ hiện vật trong đợt game hiện tại chưa.
 *
 * Chỉ đếm redemption nằm trong khoảng game_bsc_start_date → game_bsc_end_date.
 * Nếu redemption cũ thuộc đợt game trước (ngoài khoảng ngày hiện tại) → bỏ qua,
 * cho phép user nhận hiện vật mới trong đợt game mới.
 *
 * @param int $user_id
 * @return bool  true = đã có 1 bộ hoàn chỉnh trong đợt game hiện tại
 */
function game_user_has_completed_artifact( int $user_id ): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'game_user_artifact_redemptions';

    $start_date = get_option( 'game_bsc_start_date', '' );
    $end_date   = get_option( 'game_bsc_end_date', '' );

    // Nếu chưa cấu hình ngày game → fallback check toàn bộ (an toàn)
    if ( empty( $start_date ) || empty( $end_date ) ) {
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ) );
        return $count > 0;
    }

    // Chỉ đếm redemption trong khoảng đợt game hiện tại
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE user_id = %d
           AND redeemed_at >= %s
           AND redeemed_at < %s",
        $user_id,
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
    ) );

    return $count > 0;
}
```

### Logic chi tiết

```
1. Đọc game_bsc_start_date, game_bsc_end_date từ wp_options
2. Nếu chưa cấu hình (empty) → fallback check toàn bộ lịch sử (backward-compatible)
3. Nếu đã cấu hình → query:
   SELECT COUNT(*) FROM game_user_artifact_redemptions
   WHERE user_id = ?
     AND redeemed_at >= '{start_date} 00:00:00'
     AND redeemed_at < '{end_date} 23:59:59'
4. COUNT > 0 → true (đã nhận trong đợt này)
5. COUNT = 0 → false (chưa nhận, hoặc chỉ có redemption đợt cũ)
```

### Các nơi gọi hàm này

| File | Dòng | Ngữ cảnh | Tác động |
|------|------|----------|----------|
| `rest-sessions.php` | 497 | `$user_already_completed = game_user_has_completed_artifact($user_id)` | Quyết định safe-piece ON/OFF + Pity skip |
| `rest-sessions.php` | 663 | `if (game_user_has_completed_artifact($user_id))` | Chặn auto-redeem lần 2 (phòng race condition) |
| `rest-gift.php` | 2174 | `if (game_user_has_completed_artifact((int) $user_id))` | Chặn manual redeem |
| `template-test-drops.php` | 255 | Hiển thị trạng thái test UI | Thông tin debug |

> **Tất cả caller đều gọi cùng 1 hàm, không truyền tham số ngày** → chỉ cần sửa 1 chỗ là toàn bộ hệ thống tự động nhận logic mới.

---

## 6. Cơ chế Safe-piece

### Mục đích

Ngăn user đã nhận hiện vật trong đợt game hiện tại nhận thêm hiện vật thứ 2, bằng cách **chỉ trao mảnh "safe"** (mảnh không làm đủ 4/4 ở bất kỳ bộ nào).

### Flow trong `rest-sessions.php`

```
Dòng 497: $user_already_completed = game_user_has_completed_artifact($user_id)
                                     ↑ giờ scope theo đợt game
Dòng 545: $need_safe = $user_already_completed

Nếu $need_safe = true:
  Dòng 548: $chosen_piece = game_pick_safe_piece($user_id, $artifact_id, $pieces)
    │
    ├── Có mảnh safe → trao mảnh trùng (duplicate), user vẫn thiếu 1 mảnh
    │
    └── Không có mảnh safe → fallback thành ĐIỂM (dòng 551)
```

### Hàm liên quan

#### `game_will_complete_artifact(int $user_id, int $artifact_id, int $piece_id): bool`

**File:** `artifact-period.php`, dòng 180–221

Kiểm tra: "Nếu trao `piece_id` cho user, user có đủ tất cả mảnh distinct không?"

```
1. Đếm mảnh DISTINCT user đang có (qty >= 1) → $owned
2. Kiểm tra user đã có piece_id này chưa → $already_has
3. Đếm tổng mảnh của artifact → $total_pieces (= 4)
4. Nếu đã có mảnh này: return $owned >= $total_pieces
   Nếu chưa có:         return ($owned + 1) >= $total_pieces
```

#### `game_pick_safe_piece(int $user_id, int $artifact_id, array $pieces): ?object`

**File:** `artifact-period.php`, dòng 232–261

```
1. Với mỗi mảnh → gọi game_will_complete_artifact() kiểm tra
2. Chỉ giữ mảnh KHÔNG làm đủ 4/4 → $safe_pieces
3. $safe_pieces rỗng → return null (caller fallback sang điểm)
4. Có safe pieces → weighted random theo baseline_weight
```

### Ví dụ

```
User A đã nhận "Laptop" (4/4) trong đợt game hiện tại.
Giờ random ra mảnh ghép cho hiện vật "Điện thoại".
User A đang có mảnh: A, B, C của "Điện thoại".

game_pick_safe_piece() kiểm tra:
  Mảnh A → user đã có → thêm = vẫn 3 distinct → SAFE ✅
  Mảnh B → user đã có → thêm = vẫn 3 distinct → SAFE ✅
  Mảnh C → user đã có → thêm = vẫn 3 distinct → SAFE ✅
  Mảnh D → user chưa có → thêm = 4 distinct = HOÀN THÀNH → KHÔNG SAFE ❌

→ Kết quả: chỉ random giữa A, B, C. User không bao giờ đủ 4/4.
```

### Khi sang đợt game mới

```
Admin đổi start_date/end_date sang đợt mới.
game_user_has_completed_artifact() = false (redemption cũ ngoài khoảng)
→ $need_safe = false
→ Safe-piece TẮT → User được nhận mảnh bình thường
→ Pity system cũng HOẠT ĐỘNG trở lại
```

---

## 7. Auto-redeem khi đủ 4 mảnh

### File

`includes/api/rest-sessions.php`, dòng 657–742

### Flow

```
SAU khi trao mảnh cho user (INSERT/UPDATE user_pieces)
      │
      ▼
Dòng 658: $is_artifact_complete = game_will_complete_artifact(...)
      │
      ▼ (nếu true)
Dòng 663: game_user_has_completed_artifact($user_id)?
      │                                    ↑ scope theo đợt game
      ├── true → $is_artifact_complete = false (đã có bộ trong đợt → KHÔNG redeem)
      │
      └── false → Kiểm tra quota kỳ
                    │
                    ├── Hết quota → $is_artifact_complete = false
                    │
                    └── Còn quota → AUTO-REDEEM:
                          1. Trừ qty mỗi mảnh (UPDATE user_pieces SET qty = qty - 1)
                          2. Log ledger (ref_type = 'AUTO_REDEEM', delta = -1)
                          3. INSERT user_artifact_redemptions
                          4. Đóng artifact nếu đạt max_redemptions
```

### Code hiện tại (v1.2)

```php
if ( $can_redeem ) {
    // ===== AUTO-REDEEM: Trừ mảnh + ghi redemption =====
    $artifact_id_redeem = $reward['artifact_id'];

    // Lấy tất cả mảnh user đang có của artifact này (qty >= 1)
    $user_pieces_for_redeem = $wpdb->get_results( $wpdb->prepare(
        "SELECT up.id AS user_piece_id, up.piece_id, up.qty, p.piece_code
         FROM {$prefix}user_pieces up
         INNER JOIN {$prefix}pieces p ON up.piece_id = p.id
         WHERE up.user_id = %d AND up.artifact_id = %d AND up.qty >= 1
         ORDER BY p.piece_code ASC",
        $user_id, $artifact_id_redeem
    ) );

    // Trừ 1 qty cho mỗi mảnh + log ledger
    foreach ( $user_pieces_for_redeem as $rp ) {
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$prefix}user_pieces SET qty = qty - 1 WHERE id = %d AND qty >= 1",
            $rp->user_piece_id
        ) );

        $wpdb->insert( $prefix . 'user_pieces_ledger', [
            'user_piece_id' => $rp->user_piece_id,
            'ref_type'      => 'AUTO_REDEEM',
            'delta'         => -1,
            'created_at'    => game_now(),
        ] );
    }

    // Ghi redemption
    $wpdb->insert( $prefix . 'user_artifact_redemptions', [
        'user_id'     => $user_id,
        'artifact_id' => $artifact_id_redeem,
        'redeemed_at' => game_now(),
    ] );

    // Kiểm tra đóng artifact nếu đạt max_redemptions
    if ( $art_obj && $art_obj->max_redemptions > 0 ) {
        $total_redeemed_now = (int) $wpdb->get_var( ... );
        if ( $total_redeemed_now >= $art_obj->max_redemptions ) {
            $wpdb->update( $prefix . 'artifacts', ['closed' => 1], ['id' => $artifact_id_redeem] );
        }
    }
}
```

### Bug đã sửa (v1.0 → v1.2)

| Vấn đề v1.0 | Sửa trong v1.2 |
|---|---|
| Auto-redeem chỉ INSERT `user_artifact_redemptions`, **KHÔNG trừ qty** trong `user_pieces` | Bổ sung `UPDATE user_pieces SET qty = qty - 1` cho mỗi mảnh |
| Không log biến động mảnh khi auto-redeem | Bổ sung INSERT `user_pieces_ledger` với `ref_type = 'AUTO_REDEEM'` |
| Không đóng artifact khi đạt max_redemptions | Bổ sung check + UPDATE `artifacts SET closed = 1` |

### So sánh Auto-redeem vs Manual-redeem

| Thao tác | Auto-redeem (`rest-sessions.php`) | Manual-redeem (`rest-gift.php`) |
|----------|----------------------------------|-------------------------------|
| Trừ qty mảnh | `UPDATE SET qty = qty - 1` | `UPDATE SET qty = qty - 1` |
| Log ledger | `ref_type = 'AUTO_REDEEM'` | `ref_type = 'CHANGE'` |
| Ghi redemption | `INSERT user_artifact_redemptions` | `INSERT user_artifact_redemptions` |
| Đóng artifact | Check `max_redemptions` | Check `max_redemptions` |
| Transaction | Không dùng (trong context `game_get_random_reward`) | `START TRANSACTION` / `COMMIT` / `ROLLBACK` |

### Response trả về cho Frontend (từ session/answer)

```php
return array(
    'type'                 => $reward['outcome'],        // 'PIECE' hoặc 'POINT'
    'value'                => ...,
    'text'                 => ...,
    'artifact_id'          => $reward['artifact_id'],
    'piece_code'           => $reward['piece_code'],
    'is_artifact_complete' => $is_artifact_complete,     // ← FLAG cho FE popup
    'artifact_name'        => $artifact_name,            // ← Tên hiện vật cho popup
);
```

> **Lưu ý:** Thông tin trúng hiện vật từ `session/answer` chỉ trả per-question. FE nên dùng **API `session/result`** (mục 10.3) để lấy thông tin tổng hợp trúng quà sau khi kết thúc phiên chơi.

---

## 8. Manual redeem (API đổi quà)

### File

`includes/api/rest-gift.php`, dòng 2144–2376

### Endpoint

```
POST /wp-json/game-bsc/v1/redeem-artifact
Body: { artifact_id: int }
```

### Check giới hạn (dòng 2174)

```php
// 1 user chỉ được nhận 1 hiện vật trong đợt game hiện tại.
if (game_user_has_completed_artifact((int) $user_id)) {
    return wg_json_response(422, [],
        'Bạn đã nhận hiện vật trong chương trình, không thể nhận thêm.');
}
```

### Các bước kiểm tra đầy đủ

```
1. Artifact tồn tại? (status = 1, closed = 0)
2. game_user_has_completed_artifact() → đã nhận trong đợt? → CHẶN
3. game_artifact_is_within_period() → còn trong thời hạn artifact?
4. game_artifact_period_has_quota() → còn quota kỳ?
5. User có đủ 4 mảnh (qty >= 1 mỗi mảnh)?
6. Tổng lượt đổi chưa đạt max_redemptions?
7. Transaction: trừ mảnh, log ledger, ghi redemption
```

---

## 9. 3 lớp bảo vệ Defense-in-Depth

```
┌─────────────────────────────────────────────────────────────┐
│  LỚP 1: PHÒNG NGỪA (reward time)                           │
│  File: rest-sessions.php:497                                │
│  Hàm: game_user_has_completed_artifact() → $need_safe      │
│  Tác dụng: Kích hoạt safe-piece → chỉ rơi mảnh trùng      │
│            + Skip pity system                               │
│  Scope: Theo đợt game (start_date → end_date)              │
└─────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  LỚP 2: KIỂM TRA TRƯỚC KHI AUTO-REDEEM                    │
│  File: rest-sessions.php:663                                │
│  Hàm: game_user_has_completed_artifact()                    │
│  Tác dụng: Chặn auto-redeem nếu đã có bộ (race condition)  │
│  Scope: Theo đợt game (start_date → end_date)              │
└─────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  LỚP 3: CHẶN MANUAL REDEEM                                 │
│  File: rest-gift.php:2174                                   │
│  Hàm: game_user_has_completed_artifact()                    │
│  Tác dụng: Block API đổi quà nếu đã nhận                   │
│  Scope: Theo đợt game (start_date → end_date)              │
└─────────────────────────────────────────────────────────────┘
```

**Cả 3 lớp đều dùng cùng 1 hàm** → cùng 1 logic scope theo đợt game.

---

## 10. Các API bị ảnh hưởng

### 10.1 `POST /wp-json/game-bsc/v1/session/answer`

**File:** `rest-sessions.php` → `game_api_session_answer()`

**Thay đổi hành vi:**

| Aspect | Trước (v1.0) | Sau (v1.1) |
|--------|-------------|------------|
| Safe-piece | Kích hoạt vĩnh viễn sau khi nhận 1 hiện vật | Chỉ kích hoạt trong đợt game hiện tại |
| Pity | Skip vĩnh viễn sau khi nhận 1 hiện vật | Hoạt động lại ở đợt game mới |
| Auto-redeem | Chặn vĩnh viễn lần 2+ | Chặn trong đợt, cho phép ở đợt mới |

**Response structure (không thay đổi):**

```json
{
    "reward": {
        "type": "PIECE",
        "value": "https://example.com/piece-img.png",
        "text": "1x Mảnh ghép hiện vật ID 12",
        "artifact_id": 5,
        "piece_code": "laptop_3",
        "is_artifact_complete": true,
        "artifact_name": "Laptop Gaming"
    }
}
```

### 10.2 `POST /wp-json/game-bsc/v1/redeem-artifact`

**File:** `rest-gift.php` → `game_bsc_redeem_artifact_internal()`

**Thay đổi hành vi:**

| Aspect | Trước (v1.0) | Sau (v1.1) |
|--------|-------------|------------|
| Chặn đổi quà | Vĩnh viễn nếu đã có bất kỳ redemption nào | Chỉ chặn nếu có redemption trong đợt game hiện tại |

**Error response (không thay đổi):**

```json
{
    "status": 422,
    "message": "Bạn đã nhận hiện vật trong chương trình, không thể nhận thêm."
}
```

### 10.3 `GET /wp-json/game-bsc/v1/session/result?sessionId={id}` (API chính cho FE popup)

**File:** `rest-sessions.php` → `game_api_session_result()`

**Thay đổi (v1.2):** Bổ sung field `artifact_won` trong response.

**Đây là API duy nhất FE cần gọi** để biết user có trúng hiện vật trong phiên chơi vừa kết thúc hay không. Không cần lấy thông tin trúng quà từ API khác.

**Logic:** Tìm redemption có `redeemed_at` nằm trong khoảng `started_at → finished_at` của session.

```sql
SELECT r.artifact_id, r.redeemed_at, a.name AS artifact_name, a.artifacts_url
FROM wp_game_user_artifact_redemptions r
INNER JOIN wp_game_artifacts a ON a.id = r.artifact_id
WHERE r.user_id = ?
  AND r.redeemed_at >= session.started_at
  AND r.redeemed_at <= session.finished_at
ORDER BY r.redeemed_at DESC
LIMIT 1
```

**Response khi TRÚNG hiện vật:**

```json
{
    "status": 200,
    "data": {
        "session": {
            "id": 447,
            "started_at": "2026-04-24 09:15:00",
            "finished_at": "2026-04-24 09:18:30",
            "questions_total": 5,
            "correct_count": 4,
            "total_points": 40,
            "total_pieces": 1,
            "current_stage": 3,
            "status": 1
        },
        "artifact_won": {
            "artifact_id": 5,
            "artifact_name": "Laptop Gaming",
            "artifacts_url": "https://example.com/laptop.png",
            "redeemed_at": "2026-04-24 09:17:45"
        }
    }
}
```

**Response khi KHÔNG trúng hiện vật:**

```json
{
    "status": 200,
    "data": {
        "session": { ... },
        "artifact_won": null
    }
}
```

**Hướng dẫn FE:**

```
1. Gọi GET /session/result?sessionId=447
2. Kiểm tra response.data.artifact_won
3. Nếu artifact_won !== null → hiển thị popup "Chúc mừng trúng [artifact_name]!"
4. Nếu artifact_won === null → hiển thị kết quả phiên chơi bình thường
```

### 10.4 Các API KHÔNG bị ảnh hưởng

| API | Lý do |
|-----|-------|
| `POST /session/start` | Chỉ check `game_check_play_time_allowed()` (tầng gate-keeper) |
| `GET /my-redemptions` | Hiển thị tất cả redemption (không filter theo đợt) |
| `GET /user-pieces` | Hiển thị mảnh ghép user đang có |
| `POST /redeem-voucher` | Logic voucher độc lập, không liên quan hiện vật |

---

## 11. Database & Query

### Bảng chính: `wp_game_user_artifact_redemptions`

```sql
CREATE TABLE wp_game_user_artifact_redemptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    artifact_id INT UNSIGNED NOT NULL,
    redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_artifact_time (user_id, artifact_id, redeemed_at)
);
```

### Query cũ (v1.0) — check vĩnh viễn

```sql
SELECT COUNT(*) FROM wp_game_user_artifact_redemptions
WHERE user_id = ?
```

### Query mới (v1.1) — scope theo đợt game

```sql
SELECT COUNT(*) FROM wp_game_user_artifact_redemptions
WHERE user_id = ?
  AND redeemed_at >= '{start_date} 00:00:00'
  AND redeemed_at <  '{end_date} 23:59:59'
```

### Index đã có sẵn

Index `idx_user_artifact_time (user_id, artifact_id, redeemed_at)` hỗ trợ query mới vì:
- Filter `user_id` dùng prefix của composite index
- Filter `redeemed_at` dùng trailing column → range scan hiệu quả

**Không cần tạo thêm index.**

### Options table (wp_options)

| Option name | Ví dụ | Mô tả |
|-------------|-------|-------|
| `game_bsc_start_date` | `2026-04-01` | Ngày bắt đầu đợt game |
| `game_bsc_end_date` | `2026-04-30` | Ngày kết thúc đợt game |

---

## 12. Kịch bản minh họa

### Kịch bản 1: Chơi bình thường trong 1 đợt game

```
Settings: start_date = 2026-04-01, end_date = 2026-04-30

Ngày 15/04: User A đủ 4 mảnh "Laptop"
  → auto-redeem → INSERT (redeemed_at = '2026-04-15 10:30:00')
  → game_user_has_completed_artifact() = true
  → Từ nay safe-piece ON, pity SKIP

Ngày 20/04: User A random ra mảnh
  → $need_safe = true → chỉ rơi mảnh trùng
  → KHÔNG BAO GIỜ đủ 4/4 ở bộ khác trong đợt này
```

### Kịch bản 2: Sang đợt game mới

```
Đợt 1: start_date = 2026-01-01, end_date = 2026-01-31
  → User A nhận Laptop ngày 15/01 (redeemed_at = '2026-01-15')

Đợt 2: Admin đổi start_date = 2026-04-01, end_date = 2026-04-30
  → Redemption '2026-01-15' nằm NGOÀI khoảng 01/04 → 30/04
  → game_user_has_completed_artifact(user_A) = false ← RESET!
  → Safe-piece OFF, Pity ON
  → User A chơi bình thường, có thể nhận hiện vật mới

  → Ngày 20/04: User A đủ 4 mảnh "Điện thoại"
  → auto-redeem → INSERT (redeemed_at = '2026-04-20')
  → game_user_has_completed_artifact() = true ← chặn trong đợt 2
```

### Kịch bản 3: Admin chưa cấu hình ngày game

```
Settings: start_date = '' (empty), end_date = '' (empty)

→ Hàm fallback: SELECT COUNT(*) WHERE user_id = ? (không filter ngày)
→ Hành vi giống phiên bản cũ (v1.0) — check vĩnh viễn
→ Backward-compatible, không gây lỗi
```

### Kịch bản 4: Race condition (2 request đồng thời)

```
User A có 3/4 mảnh. 2 request đến cùng lúc:

Request 1:                          Request 2:
  Trao mảnh thứ 4                     Trao mảnh thứ 4
  will_complete = true                will_complete = true
  has_completed? → false              has_completed? → false
  auto-redeem → INSERT ✅             auto-redeem → kiểm tra lại
                                        has_completed? → true (req1 đã insert)
                                        → $is_artifact_complete = false ❌

→ Chỉ 1 redemption được ghi. (MySQL named lock bổ sung thêm an toàn)
```

---

## 13. Cấu hình Admin

### Trang Settings (`?page=game-bsc-settings`)

**Tab 1 — General Settings:**

| Field | Option name | Ví dụ | Ảnh hưởng |
|-------|-------------|-------|-----------|
| Từ ngày | `game_bsc_start_date` | `2026-04-01` | Mốc bắt đầu đợt game → scope redemption |
| Đến ngày | `game_bsc_end_date` | `2026-04-30` | Mốc kết thúc đợt game → scope redemption |
| Từ giờ | `game_bsc_daily_start_time` | `09:00` | Khung giờ chơi (gate-keeper) |
| Đến giờ | `game_bsc_daily_end_time` | `15:00` | Khung giờ chơi (gate-keeper) |

### Lưu ý khi đổi ngày game

Khi admin thay đổi `start_date` / `end_date`:
- **Có hiệu lực ngay lập tức** (đọc từ `wp_options` mỗi request)
- Không cần restart, không cần clear cache
- Tất cả user sẽ được đánh giá lại theo khoảng ngày mới

---

## 14. Lưu ý khi vận hành

### Checklist khi bắt đầu đợt game mới

- [ ] Đổi `start_date` và `end_date` trong Settings
- [ ] Kiểm tra artifact (hiện vật) mới đã được tạo và có đủ 4 mảnh
- [ ] Set `period_start` / `period_end` cho từng artifact (tầng 2)
- [ ] Set `max_redemptions` và `max_redemptions_per_period` cho artifact
- [ ] Artifact cũ nên set `status = 0` hoặc `closed = 1` để không rơi mảnh

### Dữ liệu cũ

- Bảng `user_artifact_redemptions` **giữ nguyên** dữ liệu đợt cũ (không xóa)
- Bảng `user_pieces` **giữ nguyên** — user vẫn có mảnh cũ trong inventory
- Hệ thống chỉ thay đổi **logic kiểm tra**, không xóa/reset data

### Fallback an toàn

Nếu admin **xóa trắng** `start_date` hoặc `end_date`:
- Hàm fallback về logic cũ (check toàn bộ lịch sử)
- An toàn hơn so với cho phép tự do (phòng trường hợp cấu hình sai)

### Khác biệt 2 loại ngày

| | Game-level dates | Artifact-level dates |
|---|---|---|
| **Scope** | Toàn bộ game | Từng hiện vật |
| **Dùng cho** | Cho phép vào chơi + scope "đã nhận hiện vật" | Hiện vật còn active? Kỳ nào? Quota kỳ? |
| **Set ở** | `?page=game-bsc-settings` | Admin quản lý hiện vật |
| **Options / Columns** | `game_bsc_start_date`, `game_bsc_end_date` | `period_start`, `period_end` (bảng artifacts) |

---

## Thay đổi so với yêu cầu gốc (nang_cap_co_che_trung_qua.md)

| Yêu cầu gốc | Cài đặt ban đầu (v1.0) | Bổ sung (v1.2) |
|---|---|---|
| "1 user chỉ được nhận 1 quà tặng hiện vật" | Check vĩnh viễn qua `COUNT(*)` | Scope theo `start_date` → `end_date` |
| Implicit: game có nhiều đợt | Không hỗ trợ | Hỗ trợ: reset khi sang đợt mới |
| Safe-piece | Kích hoạt vĩnh viễn | Kích hoạt trong đợt game hiện tại |
| Pity system | Skip vĩnh viễn sau khi nhận | Hoạt động lại ở đợt mới |
| Auto-redeem | Không check đã nhận trước, không trừ mảnh, không log ledger | Bổ sung check + trừ qty mảnh + log `AUTO_REDEEM` + đóng artifact |
| Popup trúng quà | Không có API tổng hợp cho FE | `GET /session/result` trả `artifact_won` |
