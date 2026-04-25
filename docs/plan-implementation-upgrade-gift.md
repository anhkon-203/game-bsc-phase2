# Kế hoạch triển khai: Nâng cấp cơ chế trúng quà hiện vật (24h)

Tài liệu này chi tiết hóa các bước thực hiện để hoàn tất các tính năng trong `nang_cap_co_che_trung_qua.md` trong vòng 24 giờ làm việc.

## 1. Tổng quan các tính năng (Scope)

| Tính năng | Trạng thái hiện tại | Công việc cần làm |
|-----------|----------------------|-------------------|
| **1. Quản lý hiện vật** | Đã có: start/end date, periods, quota kỳ. | Tối ưu UI hiển thị quota kỳ trong Admin. |
| **2. Pity System** | Đã có: Logic 3/4 -> trao mảnh thiếu. | Đảm bảo pity chỉ chạy khi còn quota kỳ. |
| **3. Giới hạn 1 hiện vật** | Đã có: Chặn nhận bộ thứ 2 theo đợt game. | Kiểm tra lại logic Safe-piece đảm bảo không có kẽ hở. |
| **4. Auto-popup 4/4** | Backend đã có auto-redeem. | **Quan trọng:** Bổ sung flag vào API answer + Inject script hiển thị popup chúc mừng. |

---

## 2. Chi tiết kỹ thuật & Roadmap (24h)

### Giai đoạn 1: Backend Enhancement (Giờ 1-8)
- **Task 1.1:** Cập nhật `game_get_random_reward()` trong `rest-sessions.php`:
    - Bổ sung `artifact_name`, `artifacts_url` vào mảng `$reward` khi trúng mảnh.
    - Đảm bảo biến `$is_artifact_complete` được truyền ra ngoài endpoint `session/answer`.
- **Task 1.2:** Đồng bộ hóa logic Pity và Quota:
    - Đảm bảo Pity không "lãng phí" suất nếu kỳ đó đã hết quota (User sẽ nhận điểm thay vì mất Pity).
- **Task 1.3:** Tối ưu `game_user_has_completed_artifact`:
    - Đảm bảo query dùng đúng index và cache (nếu cần) để không làm chậm API `answer`.

### Giai đoạn 2: Frontend Celebration Popup (Giờ 9-16)
- **Task 2.1:** Thiết kế Popup HTML/CSS:
    - Tạo UI chúc mừng (Congratulations Modal) mang phong cách Game BSC (vàng/đỏ/pháo hoa).
    - Hiển thị hình ảnh hiện vật vừa trúng (`artifacts_url`).
- **Task 2.2:** Script Injection (Option C):
    - Inject JS vào `template-home.php` và `rest-init.php`.
    - Script này sẽ lắng nghe (intercept) response của API `/session/answer`.
    - Nếu thấy `is_artifact_complete: true`, thực hiện:
        1. Hiển thị Popup chúc mừng ngay lập tức.
        2. Kích hoạt hiệu ứng pháo hoa (canvas-confetti).
        3. Cung cấp nút "Xem quà của tôi".

### Giai đoạn 3: Testing & Edge Cases (Giờ 17-24)
- **Task 3.1:** Kiểm tra Race Condition:
    - Test khi user click trả lời liên tục (nhiều tab) xem có bị trúng 2 hiện vật không.
- **Task 3.2:** Kiểm tra Quota Kỳ:
    - Giả lập hết quota kỳ 1, sang kỳ 2 xem mảnh có rơi lại không.
- **Task 3.3:** Kiểm tra Safe-piece:
    - User đã trúng 1 quà -> chơi tiếp -> đảm bảo chỉ rơi mảnh trùng, không bao giờ đủ 4/4 quà khác.

---

## 3. Timeline chi tiết

| Thời gian | Hạng mục công việc |
|-----------|--------------------|
| **00:00 - 04:00** | Update API `session/answer`, trả về đầy đủ info hiện vật + flag complete. |
| **04:00 - 08:00** | Refine logic Pity/Quota/Safe-piece trong `artifact-period.php`. |
| **08:00 - 12:00** | Xây dựng UI Popup & Logic JS Injection (intercept API). |
| **12:00 - 16:00** | Tích hợp hiệu ứng pháo hoa + tối ưu mobile responsive cho popup. |
| **16:00 - 20:00** | Stress test & Fix bug (chủ yếu là race condition & UI). |
| **20:00 - 24:00** | Final Review, viết changelog và bàn giao. |

---

## 4. Cam kết logic (Bảo vệ bởi Defense-in-Depth)
1. **Lớp 1:** Chặn từ lúc random mảnh (Safe-piece).
2. **Lớp 2:** Chặn lúc Auto-redeem (Re-check DB).
3. **Lớp 3:** Chặn lúc Manual-redeem (API đổi quà).
=> Đảm bảo **TUYỆT ĐỐI** 1 user chỉ nhận 1 hiện vật / đợt game dù có lỗi ở bất kỳ tầng nào.

---
**Người lập kế hoạch:** Antigravity (Claude 3.5 Sonnet)
**Ngày:** 2026-04-25
