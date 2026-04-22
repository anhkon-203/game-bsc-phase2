# Logic rơi mảnh & quản lý hiện vật (v4 — 22/04/2026)

> Tài liệu kỹ thuật chi tiết cơ chế rơi mảnh ghép hiện vật trong game-bsc.
> Mô tả đầy đủ code logic, file, line number để dễ trace.

---

## Files liên quan

| File | Vai trò |
|------|---------|
| `includes/api/rest-sessions.php` | Hàm chính `game_get_random_reward()` (L438-744) |
| `includes/helpers/artifact-period.php` | Tất cả helper: period, pity, safe-piece, completion |
| `templates/template-test-drops.php` | Trang test admin `/game-bsc-test-drops` |
| `includes/install-tables.php` | Schema bảng DB |

---

## 1. Cấu hình hiện vật (Admin)

Bảng `wp_game_artifacts` — 4 cột quan trọng:

| Cột | Kiểu | Ý nghĩa |
|-----|------|---------|
| `period_start` | DATETIME NULL | Ngày bắt đầu rơi mảnh |
| `period_end` | DATETIME NULL | Ngày kết thúc rơi mảnh |
| `total_periods` | INT | Số kỳ (= max_redemptions) |
| `max_redemptions_per_period` | INT | Luôn = 1 (mỗi kỳ 1 bộ) |

Nếu `period_start` = NULL → hiện vật luôn active, không chia kỳ.

---

## 2. Chia kỳ (theo giây)

**File:** `artifact-period.php` → `game_artifact_current_period()` (L26-57)

```php
$total_seconds = $end->getTimestamp() - $start->getTimestamp();
$period_length = floor($total_seconds / $total_periods);
$elapsed       = $now->getTimestamp() - $start->getTimestamp();
$period_index  = min(floor($elapsed / $period_length), $total_periods - 1);
```

Kỳ cuối luôn kéo dài đến `period_end`.

### Ví dụ: 30 ngày / 4 kỳ

| Kỳ | Bắt đầu | Kết thúc | Độ dài |
|----|---------|----------|--------|
| 0 | 01/04 00:00 | 08/04 12:00 | 7.5 ngày |
| 1 | 08/04 12:00 | 16/04 00:00 | 7.5 ngày |
| 2 | 16/04 00:00 | 23/04 12:00 | 7.5 ngày |
| 3 | 23/04 12:00 | 01/05 00:00 | 7.5 ngày |

---

## 3. Luồng rơi mảnh chi tiết

**File:** `rest-sessions.php` → `game_get_random_reward()` (L438-744)

### Bước 0: Limit hệ thống (L443-456)

```php
$system_piece_today        // Option: game_bsc_max_drop_pieces_per_day (default 0 = unlimited)
$user_piece_today           // Option: game_bsc_max_user_drop_pieces_per_day (default 3)
$piece_drop_rate            // Option: game_bsc_piece_drop_rate (default 30%)
$is_piece = rand(1,100) <= $piece_drop_rate
```

Nếu hết quota ngày hoặc random ra điểm → `game_build_point_reward()` → return.

### Bước 1: Lọc eligible artifacts (L458-483)

```php
SELECT * FROM wp_game_artifacts WHERE status = 1 AND closed = 0
```

Lọc thêm:
- ① `game_artifact_is_within_period($art)` → trong thời hạn? (L467)
- ② Tổng redemptions < max_redemptions? (L471-479)
  - Query: `SELECT COUNT(*) FROM user_artifact_redemptions WHERE artifact_id = ?`

**QUAN TRỌNG:** Quota kỳ KHÔNG lọc ở bước này. Mảnh vẫn rơi dù kỳ hết quota.

### Bước 2: Xác định safe-piece mode (L489-502)

```php
$user_already_completed = game_user_has_completed_artifact($user_id);
// → Check bảng user_artifact_redemptions WHERE user_id = ?

$quota_blocked_ids = [];
foreach ($eligible_artifacts as $art) {
    $cp = game_artifact_current_period($art);
    if ($cp !== false && !game_artifact_period_has_quota($art, $cp)) {
        $quota_blocked_ids[] = $art->id;  // Kỳ hết quota → block
    }
}
```

**Safe-piece kích hoạt khi (OR):**
- `$user_already_completed = true` → user đã có 1 bộ hoàn chỉnh
- `$art->id ∈ $quota_blocked_ids` → kỳ hiện tại đã có 1 redemption

### Bước 3: Check Pity (L504-523)

```php
foreach ($eligible_artifacts as $art) {
    if ($user_already_completed) continue;         // ← Chặn bộ thứ 2
    if (in_array($art->id, $quota_blocked_ids)) continue;  // ← Kỳ hết quota

    $pity_result = game_check_pity($user_id, $art);
    // → Check user có đúng 3/4 distinct pieces (qty >= 1)
    // → Trả mảnh thiếu hoặc null
}
```

**`game_check_pity()`** (`artifact-period.php`):
- Lấy 4 mảnh của artifact
- Lấy distinct piece_ids user đang có (qty ≥ 1)
- Nếu có đúng 3 → tìm mảnh thiếu → return full piece object
- Nếu không phải 3 → return null

### Bước 4: Random mảnh (L538-604)

**Nếu pity fired (L525-537):**
```php
$reward = [ 'outcome' => 'PIECE', 'piece_id' => $pity_piece->id, 'is_pity' => true ];
```

**Nếu không pity:**
```php
$artifact = $eligible_artifacts[array_rand($eligible_artifacts)]; // Random 1 artifact
$pieces = SELECT FROM pieces WHERE artifact_id = ?

$need_safe = $user_already_completed || in_array($artifact->id, $quota_blocked_ids);

if ($need_safe) {
    $chosen_piece = game_pick_safe_piece($user_id, $artifact->id, $pieces);
    // → Lọc mảnh không làm đủ 4/4 → weighted random trong safe pieces
    // → null nếu hết mảnh safe → fallback ĐIỂM
} else {
    // Weighted random bình thường trong tất cả pieces
}
```

**`game_pick_safe_piece()`** (`artifact-period.php`):
- Với mỗi piece: gọi `game_will_complete_artifact()` → nếu true thì loại
- Random weighted trong pieces còn lại
- Return null nếu không còn safe piece

**`game_will_complete_artifact()`** (`artifact-period.php`):
- Đếm distinct pieces user có: `$owned`
- Nếu user đã có mảnh này: `$owned >= $total_pieces` (thêm không tăng distinct)
- Nếu user chưa có: `($owned + 1) >= $total_pieces`

### Bước 5: Ghi dữ liệu (L608-665)

```
① INSERT wp_game_drop_logs         — log rơi mảnh (L608-624)
② INSERT/UPDATE wp_game_user_pieces — cộng qty (L629-655)
③ INSERT wp_game_user_pieces_ledger — log biến động, ref_type = 'REWARD' hoặc 'PITY' (L658-665)
```

### Bước 6: Auto-Redeem (L667-698)

```php
$is_artifact_complete = game_will_complete_artifact($user_id, $artifact_id, $piece_id);

if ($is_artifact_complete) {
    // Check quota kỳ TRƯỚC khi redeem
    $art_obj = SELECT FROM artifacts WHERE id = ?
    $current_period = game_artifact_current_period($art_obj);

    if ($current_period !== false && !game_artifact_period_has_quota($art_obj, $current_period)) {
        $can_redeem = false;          // Kỳ hết quota → KHÔNG redeem
        $is_artifact_complete = false; // Frontend không hiện popup
    }

    if ($can_redeem) {
        INSERT INTO user_artifact_redemptions (user_id, artifact_id, redeemed_at)
        // → Frontend nhận is_artifact_complete = true → popup trúng quà
    }
}
```

### Bước 7: Response API (L735-743)

```json
{
    "type": "PIECE",
    "value": "url_ảnh_mảnh",
    "text": "1x Mảnh ghép hiện vật ID 72",
    "artifact_id": 10,
    "piece_code": "P4",
    "is_artifact_complete": true,
    "artifact_name": "Hiện vật Test"
}
```

Frontend check `is_artifact_complete === true` → hiện popup trúng quà ngay.

---

## 4. Quota kỳ chi tiết

**File:** `artifact-period.php` → `game_artifact_period_has_quota()` (L105-137)

```php
$dates = game_artifact_period_dates($artifact, $period_index);
// → Tính start/end của kỳ bằng giây

$count = SELECT COUNT(*) FROM user_artifact_redemptions
         WHERE artifact_id = ? AND redeemed_at BETWEEN ? AND ?

return $count < $max_per_period;  // $max_per_period = 1
```

### Ý nghĩa quota kỳ

**Quota kỳ = số bộ hiện vật được hoàn thành trong 1 kỳ (luôn = 1).**

Khi kỳ hết quota (đã có 1 redemption):
1. Pity **SKIP** cho artifact này
2. Random chuyển sang **safe-piece** mode
3. Auto-redeem bị **chặn** (user giữ 4 mảnh, chờ kỳ sau)

### Ví dụ

```
Hiện vật X — max_redemptions = 4 — 30 ngày → 4 kỳ

Kỳ 1 (7.5 ngày):
  User A: rơi mảnh → đủ 4/4 → auto-redeem → quota = 1/1 (HẾT)
  User B: rơi mảnh bình thường ✅ (artifact vẫn eligible)
          nhưng pity SKIP + safe-piece → tối đa 3/4 mảnh ✅
  User C: tương tự User B

Kỳ 2 (7.5 ngày):
  Quota reset = 0/1
  User B: pity fire (đã có 3/4) → đủ 4/4 → auto-redeem ✅
  User C: safe-piece mode (kỳ 2 hết quota)

Kỳ 3: User C có thể hoàn thành...
```

---

## 5. Giới hạn 1 bộ / user

**File:** `artifact-period.php` → `game_user_has_completed_artifact()` (L143-155)

```php
$count = SELECT COUNT(*) FROM user_artifact_redemptions WHERE user_id = ?
return $count > 0;
```

Khi user đã có 1 bộ:
- Pity SKIP cho **mọi** artifact (L509-511)
- Random luôn dùng `game_pick_safe_piece()` (L554-557)
- Hết mảnh safe → fallback ĐIỂM (L559-561)

---

## 6. Database Schema

### Bảng `wp_game_user_artifact_redemptions`

```sql
CREATE TABLE wp_game_user_artifact_redemptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    artifact_id INT UNSIGNED NOT NULL,
    redeemed_at DATETIME NOT NULL,
    INDEX (user_id),
    INDEX (artifact_id),
    INDEX (redeemed_at)
);
```

### Bảng `wp_game_drop_logs`

Ghi log mỗi lần rơi mảnh/điểm:

| Cột | Mô tả |
|-----|-------|
| user_id | User nhận |
| session_id | Phiên chơi |
| order_index | Thứ tự câu hỏi |
| artifact_id | Hiện vật (null nếu điểm) |
| piece_id | Mảnh (null nếu điểm) |
| outcome | 'PIECE' hoặc 'POINT' |
| points_awarded | Điểm (0 nếu mảnh) |

### Bảng `wp_game_user_pieces`

| Cột | Mô tả |
|-----|-------|
| user_id | User |
| artifact_id | Hiện vật |
| piece_id | Mảnh |
| qty | Số lượng (có thể > 1 nếu trùng) |

---

## 7. Tóm tắt Edge Cases

| Trường hợp | Xử lý | Code reference |
|-------------|-------|----------------|
| User trả lời đúng + random ra ĐIỂM | Không rơi mảnh | L452-456 |
| Hiện vật hết tổng lượt đổi | Loại khỏi eligible | L471-479 |
| Hiện vật ngoài thời hạn | Loại khỏi eligible | L467 |
| User đã có 1 bộ hoàn chỉnh | Pity skip + safe-piece | L509, L554 |
| Kỳ hết quota | Pity skip + safe-piece + chặn redeem | L513, L555, L680-683 |
| User có 3/4 + chưa có bộ + kỳ còn quota | Pity → gán mảnh thiếu | L517-521 |
| User đủ 4/4 + kỳ hết quota | Giữ mảnh, KHÔNG redeem | L680-683 |
| User đủ 4/4 + kỳ còn quota | Auto-redeem + popup | L687-697 |
| Hết mảnh safe | Fallback ĐIỂM | L559-561 |
| Artifact không có period (NULL) | Luôn active, không chia kỳ | L27-29 |

---

## 8. Settings (wp_options)

| Option key | Default | Mô tả |
|-----------|---------|-------|
| `game_bsc_max_drop_pieces_per_day` | 0 (unlimited) | Tổng mảnh/ngày toàn hệ thống |
| `game_bsc_max_user_drop_pieces_per_day` | 3 | Mảnh/ngày/user |
| `game_bsc_piece_drop_rate` | 30 | Tỉ lệ rơi mảnh (%) vs điểm |

---

## 9. Trang test

**File:** `templates/template-test-drops.php`
**URL:** `/game-bsc-test-drops` (chỉ admin, cần `WP_DEBUG = true`)

Chức năng:
- Chọn user + xem trạng thái mảnh từng hiện vật
- Giả lập rơi mảnh (gọi `game_get_random_reward()` thật)
- Gán mảnh thủ công (test pity 3/4)
- Reset mảnh user
- Tạo user test hàng loạt
- Xem kỳ / quota / pity status
