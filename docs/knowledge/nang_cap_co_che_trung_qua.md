# Yêu cầu tính năng: Game Mảnh Ghép Hiện Vật

---

## 1. Admin – Quản lý hiện vật

Bổ sung khai báo:
- **Thời gian:** từ ngày... đến ngày...
- **Số kỳ tung quà:** ví dụ chạy 30 ngày, 4 kỳ → mỗi ~7 ngày tung 1 quà

> Hết số quà trong kỳ vẫn có thể rơi mảnh ghép, nhưng chỉ rơi mảnh an toàn để không ai đủ 4 mảnh trong kỳ đó.

---

## 2. Pity System – Cơ chế ưu tiên mảnh ghép

Ưu tiên user có 3 mảnh ghép nhận được mảnh còn lại theo cơ chế tranh chấp tại thời điểm rơi mảnh: cùng thời điểm chỉ 1 request thắng quyền pity.

### 2.1 Cách hoạt động

Trong mỗi lượt nhận thưởng, người chơi có thể nhận điểm hoặc mảnh ghép. Pity System không thay đổi xác suất ra điểm hay mảnh — random vẫn quyết định như bình thường. Pity **chỉ can thiệp vào loại mảnh được trao**, trong trường hợp lượt đó đã ra mảnh ghép.

Mỗi lượt thưởng diễn ra theo thứ tự:

1. Hệ thống quay thưởng như bình thường.
2. Nếu kết quả là **điểm** → người chơi nhận điểm, Pity không tham gia.
3. Nếu kết quả là **mảnh ghép** → hệ thống kiểm tra người chơi có đang giữ đúng 3 mảnh khác nhau của cùng một quà hiện vật không:
   - **Có đủ 3 mảnh và thắng tranh chấp pity tại thời điểm đó:** hệ thống bỏ qua random mảnh, trao thẳng mảnh còn thiếu để hoàn tất bộ 4.
   - **Có đủ 3 mảnh nhưng thua tranh chấp pity:** hệ thống chỉ rơi mảnh an toàn (duplicate hoặc mảnh không làm đủ 4).
   - **Chưa đủ 3 mảnh:** hệ thống rơi mảnh theo logic thông thường.

### 2.2 Điều kiện để Pity được áp dụng

Pity chỉ kích hoạt khi **đồng thời** thỏa mãn tất cả các điều kiện sau:

- Lượt thưởng hiện tại ra mảnh ghép (không phải điểm).
- Người chơi đang giữ đúng 3 mảnh khác nhau của cùng một quà hiện vật.
- Request của người chơi thắng cơ chế lock pity tại thời điểm tranh chấp (cùng hiện vật, cùng kỳ).
- Quà hiện vật đó còn hợp lệ: đang trong thời gian diễn ra, còn suất trong kỳ, và người chơi chưa vượt giới hạn nhận hiện vật của chương trình.

### 2.3 Khi nào Pity không áp dụng

Pity bị bỏ qua trong các trường hợp:

- Lượt thưởng ra điểm.
- Người chơi chưa có đủ 3 mảnh khác nhau của cùng một quà.
- Quà đã hết suất trong kỳ hoặc hết tổng suất toàn chương trình.
- Quà nằm ngoài thời gian hiệu lực.
- Người chơi đã đạt giới hạn nhận hiện vật theo chính sách chương trình.

### 2.4 Hành vi sau khi đã có người trúng trong kỳ

- Khi kỳ đã hết quota đổi quà, hệ thống **vẫn có thể rơi mảnh ghép**.
- Tuy nhiên tất cả mảnh rơi ở trạng thái này phải là **safe-piece** để không user nào đủ 4 mảnh trong cùng kỳ đó.
- Sang kỳ mới (còn quota), cơ chế pity ưu tiên hoạt động lại bình thường.

---

## 3. Giới hạn nhận quà tặng hiện vật

Suốt thời gian diễn ra Game, **1 user chỉ được nhận 1 quà tặng hiện vật**. Khi đã nhận 1 hiện vật, các hiện vật khác chỉ được rơi các mảnh khác nhau, không đủ 4 mảnh ghép.

---

## 4. Tự động popup khi đủ 4 mảnh ghép

Khi user thu thập đủ 4 mảnh ghép của 1 quà hiện vật: **tự động tung popup thông báo trúng quà**, không cần nhấn "Đổi quà".