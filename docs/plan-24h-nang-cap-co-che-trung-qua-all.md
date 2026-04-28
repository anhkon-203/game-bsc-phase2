# Kế hoạch 24h: Làm mới chức năng theo từng phần (phục vụ báo OT)

Tài liệu này chia việc theo từng phần chức năng, theo hướng làm mới hoàn toàn (coi như chưa có sẵn), để dễ báo OT theo phần việc thực tế.

Ngày cập nhật: 2026-04-25

---

## 1) Tổng hợp OT theo tính năng (24h)

| Mã tính năng | Phần chức năng | Giờ OT |
|---|---|---|
| F1 | Cài thời gian hiện vật + chia đợt + số lượng mỗi đợt | 5h |
| F2 | Pity System (3/4 -> mảnh thiếu) | 4h |
| F3 | Giới hạn 1 hiện vật/người/đợt + chặn đủ bộ lần 2 | 5h |
| F4 | Tự đổi quà + lưu lịch sử thay đổi dữ liệu | 4h |
| F5 | Trả kết quả trúng quà ổn định + test thao tác đồng thời | 4h |
|  | **Tổng** | **24h** |

---

## 2) Task chi tiết theo từng phần chức năng (làm mới)

Giả định triển khai:
- Chưa có chức năng sẵn trong code, cần làm từ đầu.
- Được phép tạo thêm phần hỗ trợ cần thiết.
- Mục tiêu: có bản chạy được, kiểm tra được, bàn giao được trong 24h.

## F1 - Cài thời gian hiện vật + chia đợt + số lượng mỗi đợt (5h)

Mục tiêu:
- Hiện vật chỉ hoạt động trong thời gian đã đặt.
- Khi hết số lượng của đợt thì không tiếp tục rơi mảnh/không đổi được quà.

Task:
- F1.1 Chốt dữ liệu cần dùng cho thời gian và số lượng theo đợt (0.75h)
  - gồm ngày bắt đầu, ngày kết thúc, số đợt, số quà tối đa mỗi đợt.
- F1.2 Làm hàm tính đợt hiện tại và kiểm tra còn quà không (1.75h)
- F1.3 Gắn chặn theo đợt vào chỗ trả thưởng (1h)
  - nếu hết đợt thì không cho rơi mảnh.
- F1.4 Gắn chặn theo đợt vào chỗ đổi quà thủ công (1h)
- F1.5 Viết kịch bản kiểm tra cho F1 (0.5h)

Bằng chứng OT cần lưu:
- Ảnh/chụp màn hình kết quả test: ngoài thời gian, hết số lượng đợt, sang đợt mới.
- Danh sách file đã chỉnh.

---

## F2 - Cơ chế ưu tiên khi còn thiếu 1 mảnh (4h)

Mục tiêu:
- Cơ chế ưu tiên chỉ quyết định mảnh nhận được, không đổi tỉ lệ ra điểm hay mảnh.
- Chỉ bật khi người chơi có đúng 3 mảnh khác nhau và quà còn hợp lệ.

Task:
- F2.1 Làm hàm kiểm tra người chơi có đủ điều kiện ưu tiên hay chưa (1h)
- F2.2 Làm luồng nhận mảnh ưu tiên khi phù hợp (1.5h)
  - chỉ áp dụng khi lượt đó ra mảnh.
  - tự bỏ qua khi không đủ điều kiện.
- F2.3 Bổ sung ghi nhận kết quả chạy để dễ đối chiếu (1h)
- F2.4 Viết và chạy kịch bản kiểm tra F2 (0.5h)
  - trường hợp đủ 3/4.
  - trường hợp lượt đó ra điểm.

Bằng chứng OT cần lưu:
- Kết quả test trường hợp 3/4 -> nhận mảnh thiếu.
- Bản ghi cho trường hợp có/không áp dụng ưu tiên.

---

## F3 - Giới hạn 1 hiện vật/người/đợt + chặn đủ bộ lần 2 (5h)

Mục tiêu:
- Mỗi người chỉ nhận tối đa 1 quà hiện vật trong cùng một đợt.
- Sau khi đã nhận 1 quà, không thể ghép đủ bộ quà khác trong cùng đợt.

Task:
- F3.1 Làm hàm kiểm tra người chơi đã nhận quà trong đợt chưa (1h)
- F3.2 Làm cơ chế chỉ rơi mảnh an toàn sau khi đã trúng 1 quà (1.5h)
  - không cho đủ bộ lần 2.
- F3.3 Gắn chặn ở bước đổi quà thủ công (1h)
- F3.4 Thêm bước kiểm tra lại trước khi lưu dữ liệu cuối cùng (1h)
- F3.5 Viết và chạy kịch bản kiểm tra F3 (0.5h)

Bằng chứng OT cần lưu:
- Kết quả test người chơi đã nhận 1 quà và chơi tiếp.
- Bằng chứng không phát sinh nhận quà lần 2 trong cùng đợt.

---

## F4 - Tự đổi quà + lưu lịch sử thay đổi dữ liệu (4h)

Mục tiêu:
- Khi đủ bộ hợp lệ, hệ thống tự đổi quà đúng.
- Dữ liệu mảnh và lịch sử thay đổi được lưu đủ, không sai lệch.

Task:
- F4.1 Làm quy trình tự đổi quà khi đủ bộ (1.5h)
  - trừ đúng số mảnh, lưu lịch sử, lưu kết quả đổi quà.
- F4.2 Làm quy trình đổi quà thủ công an toàn khi có lỗi (1h)
- F4.3 Thêm bước kiểm tra dữ liệu sau khi đổi quà (1h)
- F4.4 Viết kịch bản đối chiếu dữ liệu trước/sau đổi quà (0.5h)

Bằng chứng OT cần lưu:
- Bảng so sánh dữ liệu trước và sau đổi quà.

---

## F5 - Trả kết quả trúng quà ổn định + test thao tác đồng thời (4h)

Mục tiêu:
- Kết quả trả về cho giao diện luôn đầy đủ khi người chơi vừa trúng quà.
- Không bị đổi quà trùng khi người dùng thao tác nhanh hoặc mở nhiều tab.

Task:
- F5.1 Làm phần trả kết quả sau khi trả lời câu hỏi (1h)
  - có cờ trúng quà, tên quà, ảnh quà.
- F5.2 Xử lý các trường hợp thiếu dữ liệu trả về (0.5h)
- F5.3 Kiểm tra tình huống thao tác đồng thời (1.5h)
  - mở nhiều tab, bấm liên tục, đổi quà gần cùng lúc.
- F5.4 Sửa lỗi phát sinh và ghi chú bàn giao (1h)

Bằng chứng OT cần lưu:
- Kết quả test khi thao tác đồng thời.
- Ví dụ kết quả trả về khi thành công/thất bại.

---

## 3) Mẫu ghi OT theo task (để copy báo cáo)

| Task ID | Nội dung | Giờ OT | Kết quả/đầu ra | File chính |
|---|---|---:|---|---|
| F1.2 | Làm hàm tính đợt và kiểm tra còn quà | 1.75h | Chặn đúng khi hết quà trong đợt | `includes/helpers/artifact-period.php` |
| F2.2 | Làm cơ chế ưu tiên mảnh thiếu | 1.5h | Người chơi đủ 3/4 sẽ nhận mảnh còn thiếu | `includes/api/rest-sessions.php` |
| F3.4 | Thêm kiểm tra lại trước khi lưu cuối | 1h | Tránh lỗi nhận quà trùng khi thao tác nhanh | `includes/api/rest-sessions.php`, `includes/api/rest-gift.php` |
| F4.1 | Làm quy trình tự đổi quà | 1.5h | Trừ mảnh và lưu lịch sử đúng | `includes/api/rest-sessions.php` |
| F5.3 | Test thao tác đồng thời | 1.5h | Không phát sinh đổi quà trùng | Các đầu mối xử lý chính |

---

## 4) Điều kiện chốt 24h

- [ ] Hoàn thành đủ 24h theo 5 tính năng F1-F5.
- [ ] Mỗi task có bằng chứng OT (ảnh test/kết quả chạy/danh sách file chỉnh).
- [ ] Hoàn thành đầy đủ 5 phần chức năng đã nêu.
- [ ] Không còn lỗi đổi quà trùng khi thao tác đồng thời.
- [ ] Kết quả trả về đủ dữ liệu để giao diện hiển thị đúng.
