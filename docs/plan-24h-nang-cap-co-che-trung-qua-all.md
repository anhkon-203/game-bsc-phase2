# Kế hoạch 24h: Triển khai mới Backend theo tính năng (phục vụ báo OT)

Tài liệu này chia task theo từng tính năng backend theo hướng triển khai mới hoàn toàn (coi như chưa có sẵn tính năng nào), để dễ báo OT theo phần việc thực tế.

Ngày cập nhật: 2026-04-25

---

## 1) Tổng hợp OT theo tính năng (24h)

| Mã tính năng | Tính năng BE | Giờ OT |
|---|---|---|
| F1 | Quản lý thời gian hiện vật + kỳ + quota kỳ | 5h |
| F2 | Pity System (3/4 -> mảnh thiếu) | 4h |
| F3 | Giới hạn 1 hiện vật/user/đợt + Safe-piece | 5h |
| F4 | Auto-redeem + ledger + integrity dữ liệu | 4h |
| F5 | API response cho luồng trúng quà + hardening/race test | 4h |
|  | **Tổng** | **24h** |

---

## 2) Task chi tiết theo tính năng (triển khai mới)

Giả định triển khai:
- Chưa có logic nghiệp vụ trong code, cần xây dựng từ đầu.
- Được phép tạo mới helper/service/API field/migration cần thiết.
- Mục tiêu là có bản chạy được, test được, bàn giao được trong 24h.

## F1 - Quản lý thời gian hiện vật + kỳ + quota kỳ (5h)

Mục tiêu:
- Đảm bảo artifact chỉ hợp lệ trong thời gian hiệu lực.
- Đảm bảo hết quota kỳ thì không rơi mảnh/không redeem được.

Task:
- F1.1 Thiết kế schema và rule nghiệp vụ period/quota (0.75h)
  - định nghĩa `period_start`, `period_end`, `total_periods`, `max_redemptions_per_period`.
- F1.2 Cài đặt helper period/quota mới trong `includes/helpers/artifact-period.php` (1.75h)
  - triển khai `game_artifact_current_period()` và `game_artifact_period_has_quota()`.
- F1.3 Tích hợp gate period/quota vào API trả thưởng `includes/api/rest-sessions.php` (1h)
  - mọi nhánh PIECE phải đi qua gate period/quota.
- F1.4 Tích hợp gate period/quota vào API đổi quà `includes/api/rest-gift.php` (1h)
  - manual redeem phải chặn đúng khi hết kỳ/hết quota.
- F1.5 Viết test case và dữ liệu test cho F1 (0.5h)

Bằng chứng OT cần lưu:
- Diff code các file BE liên quan.
- Ảnh/chụp log test: ngoài thời gian, hết quota kỳ, sang kỳ mới.

---

## F2 - Pity System (4h)

Mục tiêu:
- Pity chỉ can thiệp loại mảnh, không can thiệp tỉ lệ point/piece.
- Chỉ kích hoạt khi user có đúng 3 mảnh khác nhau và artifact còn hợp lệ.

Task:
- F2.1 Xây dựng hàm xác định user đủ điều kiện pity trong `includes/helpers/artifact-period.php` (1h)
  - điều kiện đúng 3/4 mảnh khác nhau.
- F2.2 Cài đặt luồng áp dụng pity trong `includes/api/rest-sessions.php` (1.5h)
  - chỉ can thiệp khi kết quả random vào nhánh PIECE.
  - tự bỏ qua khi artifact không hợp lệ hoặc user không đạt điều kiện.
- F2.3 Bổ sung log kỹ thuật cho nhánh pity hit/skip (1h)
- F2.4 Viết và chạy test case pity bắt buộc (0.5h)
  - case random PIECE + đủ 3/4.
  - case random POINT.

Bằng chứng OT cần lưu:
- Log nhánh pity hit/skip.
- Kết quả test 3/4 -> nhận mảnh thiếu.

---

## F3 - Giới hạn 1 hiện vật/user/đợt + Safe-piece (5h)

Mục tiêu:
- Một user chỉ nhận tối đa 1 hiện vật trong cùng đợt game.
- Sau khi đã có 1 hiện vật, các artifact khác không thể gom đủ bộ.

Task:
- F3.1 Cài đặt hàm xác định user đã nhận hiện vật trong đợt (`game_user_has_completed_artifact`) (1h)
  - scope theo `game_bsc_start_date` -> `game_bsc_end_date`.
- F3.2 Cài đặt safe-piece logic trong `rest-sessions.php` + helper (1.5h)
  - ngăn tạo bộ thứ 2 sau khi user đã nhận 1 hiện vật.
- F3.3 Cài đặt guard giới hạn 1 hiện vật ở manual redeem (`rest-gift.php`) (1h)
- F3.4 Bổ sung re-check trước commit ở auto/manual redeem để chống đua dữ liệu (1h)
- F3.5 Viết và chạy test case giới hạn 1 hiện vật theo đợt (0.5h)

Bằng chứng OT cần lưu:
- Kết quả test user đã nhận 1 hiện vật và tiếp tục chơi.
- Log/DB snapshot chứng minh không có redemption thứ 2.

---

## F4 - Auto-redeem + ledger + integrity dữ liệu (4h)

Mục tiêu:
- Khi đủ bộ hợp lệ, auto-redeem trừ đúng mảnh, ghi ledger đầy đủ, ghi redemption đúng 1 lần.

Task:
- F4.1 Cài đặt transaction auto-redeem trong `includes/api/rest-sessions.php` (1.5h)
  - trừ qty từng mảnh, ghi ledger `AUTO_REDEEM`, insert redemption.
- F4.2 Cài đặt transaction manual redeem trong `includes/api/rest-gift.php` (1h)
  - đảm bảo rollback an toàn khi lỗi giữa chừng.
- F4.3 Cài đặt kiểm tra integrity sau giao dịch (1h)
- F4.4 Viết script/test đối soát dữ liệu sau redeem (0.5h)

Bằng chứng OT cần lưu:
- So sánh trước/sau ở `user_pieces`, `user_pieces_ledger`, `user_artifact_redemptions`.

---

## F5 - API response trúng quà + hardening race/concurrency test (4h)

Mục tiêu:
- Response BE ổn định cho FE khi user vừa hoàn tất bộ mảnh.
- Không phát sinh double redeem khi request đồng thời.

Task:
- F5.1 Thiết kế và cài đặt response của `session/answer` (1h)
  - `is_artifact_complete`, `artifact_name`, `artifacts_url`.
- F5.2 Cài đặt xử lý edge-case response null/missing data (0.5h)
- F5.3 Thiết kế và chạy test concurrency (1.5h)
  - nhiều tab, spam `session/answer`, đua với `redeem`.
- F5.4 Fix bug phát sinh + cập nhật ghi chú kỹ thuật BE (1h)

Bằng chứng OT cần lưu:
- Kết quả test concurrency.
- Ví dụ payload response thành công/thất bại.

---

## 3) Mẫu ghi OT theo task (để copy báo cáo)

| Task ID | Nội dung | Giờ OT | Kết quả/đầu ra | File chính |
|---|---|---:|---|---|
| F1.2 | Cài helper period/quota | 1.75h | Có hàm tính kỳ và check quota kỳ chạy được | `includes/helpers/artifact-period.php` |
| F2.2 | Cài luồng áp dụng pity | 1.5h | Pity hoạt động đúng rule 3/4 | `includes/api/rest-sessions.php` |
| F3.4 | Cài re-check trước commit | 1h | Chặn race ở lớp auto/manual | `includes/api/rest-sessions.php`, `includes/api/rest-gift.php` |
| F4.1 | Cài transaction auto-redeem | 1.5h | Ledger + redemption nhất quán | `includes/api/rest-sessions.php` |
| F5.3 | Test concurrency | 1.5h | Không phát sinh double redeem | BE APIs |

---

## 4) Điều kiện chốt 24h BE

- [ ] Hoàn thành đủ 24h theo 5 tính năng F1-F5.
- [ ] Mỗi task có bằng chứng OT (diff/log/test result).
- [ ] Hoàn thành đầy đủ logic period/quota/pity/1-user-1-artifact.
- [ ] Không còn lỗi double redeem trong test concurrency.
- [ ] Response API đủ dữ liệu cho luồng hiển thị trúng quà.
