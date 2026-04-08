# Yêu cầu 8.1.a — Bổ sung thời hạn hiện vật & Cơ chế Pity

> **Ngày tạo:** 2026-03-31
> **Trạng thái:** Phân tích kỹ thuật — Chờ phát triển
> **Plugin:** game-bsc
> **Tài liệu gốc:** Gami-doc.md — Mục 8. Nâng cấp tính năng và cơ chế trúng quà hiện vật (8.1.a)

---

## 1. Tổng quan

Yêu cầu 8.1.a mở rộng cơ chế rơi mảnh ghép hiện tại theo **3 hướng chính**:

| # | Tính năng | Mô tả ngắn |
|---|-----------|-----------|
| 1 | **Thời hạn & Kỳ tung quà** | Hiện vật có ngày bắt đầu/kết thúc; chia thành N kỳ, mỗi kỳ có quota mảnh riêng |
| 2 | **Pity System** | Ưu tiên trao mảnh còn thiếu cho user đang có 3/4 mảnh từ đầu kỳ mới |
| 3 | **Giới hạn 1 hiện vật / user / toàn chương trình** | Sau khi hoàn thành 1 bộ, user không thể gom đủ 4 mảnh ở bộ khác |

Ngoài ra có **Auto-popup**: khi user thu thập đủ 4 mảnh → hệ thống tự bắn popup thông báo trúng quà (không cần nhấn "Đổi quà").

---

## 2. Yêu cầu nghiệp vụ chi tiết

### 2.1 Thời hạn hiện vật & Kỳ tung quà

**Khai báo admin:**
- Thêm trường **Ngày bắt đầu** (`period_start`) và **Ngày kết thúc** (`period_end`) cho mỗi hiện vật.
- Thêm trường **Số kỳ tung quà** (`total_periods`) — ví dụ: 30 ngày, 4 kỳ → mỗi kỳ ~7 ngày.
- Thêm trường **Số quà mỗi kỳ** (`max_redemptions_per_period`) — số lượt đổi/mảnh tối đa mỗi kỳ.

**Logic hệ thống:**
- Ngoài thời hạn → không rơi mảnh của hiện vật đó (fallback sang điểm).
- Mỗi kỳ reset quota; hết quota trong kỳ → không rơi mảnh cho đến kỳ tiếp theo.
- Kỳ được xác định tự động: `kỳ hiện tại = floor((ngày_hôm_nay - period_start) / độ_dài_kỳ)`.

**Ví dụ minh họa:**

```
Hiện vật A: 01/04/2026 → 30/04/2026 | 4 kỳ | 50 quà/kỳ
├── Kỳ 1: 01/04 → 07/04  (50 suất)
├── Kỳ 2: 08/04 → 14/04  (50 suất)
├── Kỳ 3: 15/04 → 21/04  (50 suất)
└── Kỳ 4: 22/04 → 30/04  (50 suất)
```

### 2.2 Pity System (Hệ thống ưu tiên mảnh còn thiếu)

**Định nghĩa:** Tại **thời điểm bắt đầu mỗi kỳ mới**, user đang sở hữu **đúng 3/4 mảnh** của 1 hiện vật sẽ được **ưu tiên nhận mảnh còn thiếu** trong lần rơi tiếp theo của kỳ đó.

**Logic:**
1. Khi kỳ mới bắt đầu → hệ thống scan user có `3/4 mảnh` của hiện vật.
2. Trong `game_get_random_reward()`: nếu user đang có pity → bỏ qua random, gán thẳng mảnh còn thiếu.
3. Sau khi trao mảnh pity → xóa cờ pity của user cho kỳ đó.
4. Pity chỉ kích hoạt **một lần mỗi kỳ** cho mỗi user.

**Ví dụ:**
```
User có: P1 ✓ P2 ✓ P3 ✓ P4 ✗ → Kỳ 2 bắt đầu → Lần rơi tiếp theo bắt buộc cho P4
```

### 2.3 Giới hạn 1 hiện vật / user / toàn chương trình

**Quy tắc:**
- Mỗi user chỉ được **nhận 1 hiện vật hoàn chỉnh** trong suốt thời gian diễn ra Game.
- Sau khi đã hoàn thành 1 bộ 4 mảnh:
  - Ở **các hiện vật còn lại**: user vẫn có thể nhận mảnh, nhưng hệ thống đảm bảo **không bao giờ đủ 4/4**.
  - Cụ thể: với mỗi hiện vật chưa hoàn thành, hệ thống chỉ cho phép user tích lũy tối đa **3/4 mảnh**.

**Kiểm tra tại thời điểm rơi mảnh:**
```
Trước khi gán mảnh → kiểm tra:
  IF (user đã có bộ hoàn chỉnh) AND (mảnh sắp gán sẽ làm đủ 4/4 ở hiện vật khác)
  → KHÔNG gán mảnh đó → fallback sang điểm
```

### 2.4 Auto-popup khi đủ 4 mảnh

- API `game_get_random_reward()` trả về field `is_artifact_complete: true` khi user vừa nhận đủ 4 mảnh.
- Frontend nhận flag → **tự động hiển thị popup** chúc mừng trúng quà.
- Popup không yêu cầu user nhấn "Đổi quà".

---

## 3. Phân tích kỹ thuật

### 3.1 Thay đổi Schema Database

#### 3.1.a Bảng `wp_game_artifacts` — Thêm cột thời hạn và kỳ

```sql
ALTER TABLE wp_game_artifacts
  ADD COLUMN period_start        DATETIME     NULL          COMMENT 'Ngày bắt đầu hiện vật',
  ADD COLUMN period_end          DATETIME     NULL          COMMENT 'Ngày kết thúc hiện vật',
  ADD COLUMN total_periods       INT UNSIGNED DEFAULT 1     COMMENT 'Tổng số kỳ tung quà',
  ADD COLUMN max_redemptions_per_period INT UNSIGNED DEFAULT 0 COMMENT '0 = không giới hạn theo kỳ';
```

> **Ghi chú:** Cột `max_redemptions` hiện có giữ nguyên (tổng suất toàn chương trình). `max_redemptions_per_period` là giới hạn mỗi kỳ.

#### 3.1.b Bảng mới `wp_game_artifact_period_drops` — Đếm drop theo kỳ

```sql
CREATE TABLE IF NOT EXISTS wp_game_artifact_period_drops (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    artifact_id   INT UNSIGNED  NOT NULL,
    period_index  TINYINT UNSIGNED NOT NULL  COMMENT 'Chỉ số kỳ: 0, 1, 2, ...',
    drop_count    INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Số mảnh đã rơi trong kỳ',
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_artifact_period (artifact_id, period_index),
    KEY idx_artifact (artifact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.c Bảng mới `wp_game_user_pity` — Theo dõi pity của user

```sql
CREATE TABLE IF NOT EXISTS wp_game_user_pity (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED  NOT NULL,
    artifact_id   INT UNSIGNED  NOT NULL,
    period_index  TINYINT UNSIGNED NOT NULL  COMMENT 'Kỳ mà pity được kích hoạt',
    missing_piece_id INT UNSIGNED NOT NULL   COMMENT 'Mảnh còn thiếu cần trao',
    is_claimed    TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '0=chưa trao, 1=đã trao',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at    DATETIME      NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_artifact_period (user_id, artifact_id, period_index),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.2 File PHP cần tạo / chỉnh sửa

| File | Loại | Mô tả thay đổi |
|------|------|----------------|
| `includes/install-tables.php` | Chỉnh sửa | Thêm `ALTER TABLE` cho `wp_game_artifacts`; tạo 2 bảng mới |
| `includes/api/rest-sessions.php` | Chỉnh sửa | Nâng cấp `game_get_random_reward()` — thêm logic kỳ, pity, giới hạn 1 hiện vật |
| `includes/admin/manage-artifacts.php` | Chỉnh sửa | Thêm form khai báo `period_start`, `period_end`, `total_periods`, `max_redemptions_per_period` |
| `includes/helpers/artifact-period.php` | **Tạo mới** | Helper functions: tính kỳ hiện tại, kiểm tra quota kỳ, scan pity users |
| `includes/helpers/artifact-detail.php` | Chỉnh sửa | Cập nhật AJAX để trả thêm thông tin kỳ, pity status |

---

### 3.3 Helper mới: `includes/helpers/artifact-period.php`

File này chứa toàn bộ business logic cho thời hạn & kỳ tung quà.

#### Hàm 1: Lấy kỳ hiện tại

```php
/**
 * Xác định kỳ hiện tại của 1 hiện vật (0-indexed).
 *
 * @param object $artifact  Row từ wp_game_artifacts (cần period_start, period_end, total_periods)
 * @return int|false  Chỉ số kỳ (0, 1, 2...) hoặc false nếu ngoài thời hạn
 */
function game_artifact_current_period( object $artifact ) {
    $now   = current_time( 'timestamp' );
    $start = strtotime( $artifact->period_start );
    $end   = strtotime( $artifact->period_end );

    // Ngoài thời hạn
    if ( $now < $start || $now > $end ) {
        return false;
    }

    $total_periods = max( 1, (int) $artifact->total_periods );
    $total_seconds = $end - $start;
    $period_length = floor( $total_seconds / $total_periods ); // giây mỗi kỳ

    $elapsed       = $now - $start;
    $period_index  = min( (int) floor( $elapsed / $period_length ), $total_periods - 1 );

    return $period_index;
}
```

#### Hàm 2: Kiểm tra quota kỳ còn chỗ

```php
/**
 * Kiểm tra kỳ hiện tại còn quota rơi mảnh không.
 *
 * @param int    $artifact_id
 * @param int    $period_index
 * @param int    $max_per_period  0 = vô hạn
 * @return bool  true = còn chỗ, false = đã đầy
 */
function game_artifact_period_has_quota( int $artifact_id, int $period_index, int $max_per_period ): bool {
    if ( $max_per_period <= 0 ) {
        return true; // Không giới hạn
    }

    global $wpdb;
    $prefix = $wpdb->prefix;

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT drop_count FROM {$prefix}game_artifact_period_drops
         WHERE artifact_id = %d AND period_index = %d",
        $artifact_id, $period_index
    ) );

    $current_count = $row ? (int) $row->drop_count : 0;

    return $current_count < $max_per_period;
}
```

#### Hàm 3: Tăng counter drop của kỳ

```php
/**
 * Tăng drop_count của kỳ hiện tại lên 1.
 *
 * @param int $artifact_id
 * @param int $period_index
 */
function game_artifact_period_increment_drop( int $artifact_id, int $period_index ): void {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$prefix}game_artifact_period_drops (artifact_id, period_index, drop_count)
         VALUES (%d, %d, 1)
         ON DUPLICATE KEY UPDATE drop_count = drop_count + 1",
        $artifact_id, $period_index
    ) );
}
```

#### Hàm 4: Kiểm tra & lấy pity của user

```php
/**
 * Lấy thông tin pity đang chờ của user trong kỳ hiện tại.
 *
 * @param int $user_id
 * @param int $artifact_id
 * @param int $period_index
 * @return object|null  Row pity hoặc null nếu không có
 */
function game_get_user_pity( int $user_id, int $artifact_id, int $period_index ): ?object {
    global $wpdb;
    $prefix = $wpdb->prefix;

    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}game_user_pity
         WHERE user_id = %d AND artifact_id = %d AND period_index = %d AND is_claimed = 0",
        $user_id, $artifact_id, $period_index
    ) );
}
```

#### Hàm 5: Đánh dấu pity đã được trao

```php
/**
 * Đánh dấu pity của user là đã trao.
 *
 * @param int $pity_id  ID của row trong wp_game_user_pity
 */
function game_mark_pity_claimed( int $pity_id ): void {
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'game_user_pity',
        [ 'is_claimed' => 1, 'claimed_at' => current_time( 'mysql' ) ],
        [ 'id' => $pity_id ],
        [ '%d', '%s' ],
        [ '%d' ]
    );
}
```

#### Hàm 6: Tạo pity cho user 3/4 mảnh khi kỳ mới bắt đầu

> Hàm này nên được gọi qua WP Cron mỗi ngày — tự detect kỳ mới và tạo bản ghi pity.

```php
/**
 * Quét tất cả user đang có 3/4 mảnh của 1 hiện vật và đang ở kỳ mới.
 * Tạo bản ghi wp_game_user_pity nếu chưa có.
 *
 * Gọi từ WP Cron: hook 'game_bsc_daily_pity_scan'
 */
function game_artifact_scan_and_create_pity(): void {
    global $wpdb;
    $prefix = $wpdb->prefix;

    // Lấy tất cả hiện vật đang hoạt động (trong thời hạn)
    $artifacts = $wpdb->get_results(
        "SELECT * FROM {$prefix}game_artifacts
         WHERE status = 1
           AND period_start IS NOT NULL
           AND period_end IS NOT NULL
           AND NOW() BETWEEN period_start AND period_end"
    );

    foreach ( $artifacts as $artifact ) {
        $period_index = game_artifact_current_period( $artifact );
        if ( $period_index === false ) continue;

        $total_pieces = 4; // Cố định 4 mảnh

        // Lấy tất cả user có đúng 3 mảnh khác nhau của hiện vật này
        $users_with_3 = $wpdb->get_results( $wpdb->prepare(
            "SELECT up.user_id,
                    GROUP_CONCAT(up.piece_id ORDER BY up.piece_id) AS owned_piece_ids
             FROM {$prefix}game_user_pieces up
             INNER JOIN {$prefix}game_pieces p ON p.id = up.piece_id
             WHERE p.artifact_id = %d AND up.qty >= 1
             GROUP BY up.user_id
             HAVING COUNT(DISTINCT up.piece_id) = 3",
            $artifact->id
        ) );

        foreach ( $users_with_3 as $row ) {
            // Xác định mảnh còn thiếu
            $all_pieces = $wpdb->get_results( $wpdb->prepare(
                "SELECT id FROM {$prefix}game_pieces WHERE artifact_id = %d ORDER BY id",
                $artifact->id
            ) );
            $all_ids     = array_column( $all_pieces, 'id' );
            $owned_ids   = array_map( 'intval', explode( ',', $row->owned_piece_ids ) );
            $missing_ids = array_diff( $all_ids, $owned_ids );
            $missing_id  = reset( $missing_ids );

            if ( ! $missing_id ) continue;

            // Tạo pity nếu chưa có cho kỳ này
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$prefix}game_user_pity
                    (user_id, artifact_id, period_index, missing_piece_id, is_claimed)
                 VALUES (%d, %d, %d, %d, 0)",
                $row->user_id, $artifact->id, $period_index, $missing_id
            ) );
        }
    }
}
add_action( 'game_bsc_daily_pity_scan', 'game_artifact_scan_and_create_pity' );
```

#### Hàm 7: Kiểm tra user đã hoàn thành 1 hiện vật chưa

```php
/**
 * Kiểm tra xem user có đã hoàn thành 1 bộ hiện vật trong chương trình không.
 *
 * @param int $user_id
 * @return bool  true = đã có 1 bộ hoàn chỉnh
 */
function game_user_has_completed_artifact( int $user_id ): bool {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}game_user_artifact_redemptions WHERE user_id = %d",
        $user_id
    ) );

    return (int) $count > 0;
}
```

#### Hàm 8: Kiểm tra mảnh sắp gán có làm user đủ 4/4 không

```php
/**
 * Kiểm tra nếu trao piece_id cho user thì user có đủ 4/4 mảnh của artifact đó không.
 *
 * @param int $user_id
 * @param int $artifact_id
 * @param int $piece_id     Mảnh sắp gán
 * @return bool  true = sẽ đủ 4 mảnh (hoàn chỉnh)
 */
function game_will_complete_artifact( int $user_id, int $artifact_id, int $piece_id ): bool {
    global $wpdb;
    $prefix = $wpdb->prefix;

    // Đếm số mảnh khác nhau user đang có (không tính piece_id sắp gán)
    $owned = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT up.piece_id)
         FROM {$prefix}game_user_pieces up
         INNER JOIN {$prefix}game_pieces p ON p.id = up.piece_id
         WHERE up.user_id = %d AND p.artifact_id = %d AND up.qty >= 1",
        $user_id, $artifact_id
    ) );

    $total_pieces = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}game_pieces WHERE artifact_id = %d",
        $artifact_id
    ) );

    // Nếu hiện có owned mảnh, +1 mảnh mới = đủ total_pieces?
    return ( (int) $owned + 1 ) >= (int) $total_pieces;
}
```

---

### 3.4 Nâng cấp `game_get_random_reward()` trong `rest-sessions.php`

Thêm các bước sau vào **đầu luồng chọn hiện vật**, sau khi xác định sẽ thưởng mảnh (không phải điểm):

```
[Hiện tại] → Chọn random hiện vật → Chọn random mảnh → Ghi log
[Nâng cấp] → Lọc hiện vật theo thời hạn & quota kỳ
            → Kiểm tra pity của user
            → Kiểm tra giới hạn 1 hiện vật / user
            → Chọn mảnh (pity hoặc weighted random)
            → Kiểm tra is_artifact_complete
            → Ghi log + tăng counter kỳ
```

**Đoạn code bổ sung (chèn vào luồng hiện có):**

```php
// ── BƯỚC 1: Lọc hiện vật còn trong thời hạn và còn quota kỳ ──────────
$active_artifacts = [];
foreach ( $raw_artifacts as $art ) {
    // Bỏ qua nếu không có thời hạn khai báo
    if ( empty( $art->period_start ) || empty( $art->period_end ) ) {
        $active_artifacts[] = $art; // Artifact cũ không có thời hạn → giữ nguyên
        continue;
    }

    $period_index = game_artifact_current_period( $art );
    if ( $period_index === false ) continue; // Ngoài thời hạn

    if ( ! game_artifact_period_has_quota( $art->id, $period_index, (int) $art->max_redemptions_per_period ) ) {
        continue; // Hết quota kỳ
    }

    $art->_current_period = $period_index; // Gắn kỳ hiện tại để dùng sau
    $active_artifacts[]   = $art;
}

if ( empty( $active_artifacts ) ) {
    // Tất cả hiện vật đều ngoài thời hạn / hết quota → fallback điểm
    return game_reward_points( $user_id );
}

// ── BƯỚC 2: Chọn hiện vật (random) ──────────────────────────────────────
$artifact       = $active_artifacts[ array_rand( $active_artifacts ) ];
$period_index   = $artifact->_current_period ?? 0;

// ── BƯỚC 3: Kiểm tra pity ────────────────────────────────────────────────
$pity = game_get_user_pity( $user_id, $artifact->id, $period_index );

if ( $pity ) {
    // Trao mảnh pity
    $chosen_piece = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}game_pieces WHERE id = %d",
        $pity->missing_piece_id
    ) );
    game_mark_pity_claimed( $pity->id );
} else {
    // ── BƯỚC 4: Kiểm tra giới hạn 1 hiện vật / user ─────────────────────
    // (Chọn mảnh theo weighted random như hiện tại)
    $chosen_piece = game_weighted_random_piece( $artifact->id );

    // Nếu user đã hoàn thành 1 bộ và mảnh này sẽ làm đủ 4 ở bộ khác → block
    if ( game_user_has_completed_artifact( $user_id )
         && game_will_complete_artifact( $user_id, $artifact->id, $chosen_piece->id )
    ) {
        // Chọn lại mảnh khác (mảnh mà user chưa có, nhưng tổng không đủ 4)
        $chosen_piece = game_pick_safe_piece( $user_id, $artifact->id );
        if ( ! $chosen_piece ) {
            // Không còn mảnh safe → fallback điểm
            return game_reward_points( $user_id );
        }
    }
}

// ── BƯỚC 5: Tăng counter kỳ ─────────────────────────────────────────────
game_artifact_period_increment_drop( $artifact->id, $period_index );

// ── BƯỚC 6: Trao mảnh & kiểm tra is_artifact_complete ────────────────────
// ... (logic trao mảnh hiện có) ...
$is_complete = game_will_complete_artifact( $user_id, $artifact->id, $chosen_piece->id );

// Gắn flag vào response
$reward['is_artifact_complete'] = $is_complete;
$reward['artifact_name']        = $artifact->name;
```

---

### 3.5 Thay đổi Admin UI (`manage-artifacts.php`)

**Form khai báo hiện vật — Thêm các trường:**

| Trường | Input type | Validation | Ghi chú |
|--------|-----------|-----------|---------|
| Ngày bắt đầu | `datetime-local` | ≤ Ngày kết thúc | Lưu `period_start` |
| Ngày kết thúc | `datetime-local` | ≥ Ngày bắt đầu | Lưu `period_end` |
| Số kỳ tung quà | `number` (min=1) | Số nguyên dương | Lưu `total_periods` |
| Số quà tối đa / kỳ | `number` (min=0) | 0 = không giới hạn | Lưu `max_redemptions_per_period` |

**Hiển thị thêm trong danh sách hiện vật:**

```
Thời hạn:    01/04/2026 → 30/04/2026
Kỳ hiện tại: Kỳ 2 / 4  (08/04 → 14/04)
Quota kỳ:    23 / 50 mảnh đã rơi
```

---

### 3.6 WP Cron — Scan pity hàng ngày

Đăng ký hook trong `game-bsc.php` (phần `register_activation_hook` hoặc `init`):

```php
// Đăng ký cron event khi plugin activate
register_activation_hook( __FILE__, function () {
    if ( ! wp_next_scheduled( 'game_bsc_daily_pity_scan' ) ) {
        wp_schedule_event( strtotime( 'today midnight' ), 'daily', 'game_bsc_daily_pity_scan' );
    }
} );

// Hủy khi deactivate
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'game_bsc_daily_pity_scan' );
} );
```

---

### 3.7 Response API — Auto-popup flag

Khi `is_artifact_complete = true`, response từ `/wp-json/game-bsc/v1/session/answer` bổ sung:

```json
{
  "outcome": "PIECE",
  "artifact_id": 3,
  "piece_id": 12,
  "piece_code": "P4",
  "piece_url": "https://example.com/p4.png",
  "is_artifact_complete": true,
  "artifact_name": "Ví tiền vàng",
  "popup": {
    "type": "artifact_complete",
    "title": "Chúc mừng! Bạn đã trúng quà!",
    "message": "Bạn đã thu thập đủ 4 mảnh ghép của \"Ví tiền vàng\".",
    "cta_label": "Xem quà của tôi",
    "cta_url": "/kho-qua-tang"
  }
}
```

Frontend check field `is_artifact_complete` sau mỗi lần trả lời → nếu `true` → tự render popup, không cần user tương tác thêm.

---

## 4. Luồng xử lý tổng hợp

```
User trả lời đúng câu hỏi
        │
        ▼
game_get_random_reward($user_id, $session_id, $order_index)
        │
        ├─ Random: ĐIỂM? → Trả về điểm (như cũ)
        │
        └─ MẢNH?
              │
              ▼
        Lọc artifacts (trong thời hạn + còn quota kỳ)
              │
              ├─ Không còn artifact hợp lệ → fallback ĐIỂM
              │
              └─ Chọn random artifact
                    │
                    ▼
              Có pity?  ────YES────► Trao mảnh pity → mark claimed
                    │
                   NO
                    │
                    ▼
              Chọn mảnh weighted random
                    │
                    ▼
              User đã có 1 bộ hoàn chỉnh?
                    │
                   YES──► Mảnh này sẽ làm đủ 4/4 ở bộ khác?
                    │              │
                   NO              YES──► Chọn mảnh safe / fallback ĐIỂM
                    │              │
                    └──────────────┘
                    │
                    ▼
              Trao mảnh + tăng counter kỳ
                    │
                    ▼
              Đủ 4/4? → is_artifact_complete = true → Frontend auto-popup
```

---

## 5. Danh sách việc cần làm (Implementation Checklist)

### Database
- [ ] Viết migration: `ALTER TABLE wp_game_artifacts ADD COLUMN ...`
- [ ] Viết `CREATE TABLE wp_game_artifact_period_drops ...`
- [ ] Viết `CREATE TABLE wp_game_user_pity ...`
- [ ] Tích hợp vào `includes/install-tables.php` (hàm `game_bsc_create_tables()`)

### Backend PHP
- [ ] Tạo file `includes/helpers/artifact-period.php` với 8 hàm như mục 3.3
- [ ] Nâng cấp `game_get_random_reward()` trong `rest-sessions.php` (mục 3.4)
- [ ] Đăng ký WP Cron `game_bsc_daily_pity_scan` trong `game-bsc.php`
- [ ] Thêm hàm `game_pick_safe_piece($user_id, $artifact_id)` — chọn mảnh không làm đủ 4 (cần viết thêm)

### Admin UI
- [ ] Cập nhật form tạo/sửa hiện vật: thêm 4 trường mới (mục 3.5)
- [ ] Cập nhật save/update logic trong `manage-artifacts.php`
- [ ] Hiển thị thông tin kỳ & quota trong danh sách hiện vật

### Frontend
- [ ] Đọc flag `is_artifact_complete` trong response API answer
- [ ] Render popup tự động khi `is_artifact_complete = true`
- [ ] Không yêu cầu nhấn "Đổi quà" để nhận thông báo

### Testing
- [ ] Unit test: `game_artifact_current_period()` với các edge case (trước/sau thời hạn, ngày đầu/cuối kỳ)
- [ ] Test pity: User 3/4 mảnh → kỳ mới → lần chơi tiếp nhận đúng mảnh 4
- [ ] Test giới hạn: Hoàn thành bộ A → chơi tiếp → không bao giờ đủ 4 ở bộ B
- [ ] Test auto-popup: API trả `is_artifact_complete: true` khi mảnh cuối được trao

---

## 6. Câu hỏi cần xác nhận với khách hàng / BSC

| # | Câu hỏi | Tác động |
|---|---------|---------|
| 1 | Pity chỉ kích hoạt đầu kỳ mới, hay kích hoạt ngay khi user đạt 3/4 mảnh bất kỳ lúc nào? | Thay đổi trigger logic scan pity |
| 2 | Nếu game có nhiều hiện vật đồng thời, pity ưu tiên hiện vật nào nếu user có 3/4 ở nhiều bộ? | Cần thêm logic chọn artifact ưu tiên |
| 3 | Sau khi game hết hạn `period_end`, user có 3 mảnh còn dở có được giữ lại cho event tiếp không? | Ảnh hưởng bảng `user_pieces` và logic reset |
| 4 | `max_redemptions` (tổng) và `max_redemptions_per_period` (mỗi kỳ): cả 2 cùng áp dụng hay chỉ dùng 1? | Schema và validation logic |
| 5 | Auto-popup hiển thị ngay trong game hay chuyển hướng sang trang "Kho quà tặng"? | Frontend implementation |

---

## 7. Phụ lục — Bảng tóm tắt thay đổi

| Loại | Chi tiết |
|------|---------|
| **Bảng DB mới** | `wp_game_artifact_period_drops`, `wp_game_user_pity` |
| **Cột DB mới** | `wp_game_artifacts`: `period_start`, `period_end`, `total_periods`, `max_redemptions_per_period` |
| **File PHP tạo mới** | `includes/helpers/artifact-period.php` |
| **File PHP chỉnh sửa** | `rest-sessions.php`, `manage-artifacts.php`, `install-tables.php`, `game-bsc.php` |
| **API thay đổi** | `/session/answer` response thêm `is_artifact_complete`, `popup` object |
| **WP Cron mới** | `game_bsc_daily_pity_scan` — chạy 00:00 mỗi ngày |
