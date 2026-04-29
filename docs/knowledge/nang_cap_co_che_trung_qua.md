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

#### 2.1.1 Thắng tranh chấp pity (chi tiết)

Khi có nhiều request cùng thời điểm và cùng nhóm hiện vật, hệ thống dùng cơ chế lock pity để chỉ chọn ra 1 request thắng. Các bước xử lý:

1. Xác định khóa tranh chấp (pity key) theo: `artifact_id + period_id` (cùng hiện vật, cùng kỳ).
2. Khi request vào đến bước kiểm tra pity, hệ thống thử chiếm lock trên pity key.
3. Request nào chiếm được lock hợp lệ sẽ là request thắng tranh chấp.
4. Các request không chiếm được lock trong khoảng thời gian tranh chấp sẽ bị xem là thua pity.

Chi tiết lock:

- **Atomic**: lock phải được tạo bằng thao tác atom (ví dụ: `SETNX`/transaction) để tránh 2 request cùng thắng.
- **TTL**: lock có TTL ngắn (ví dụ 200-500ms) chỉ để bảo vệ khoảng thời gian tranh chấp tại lượt đó.
- **Idempotent**: nếu request retry trong TTL, không được đổi kết quả thắng/thua đã xác định.
- **Scope**: lock chỉ áp dụng trong 1 kỳ của 1 hiện vật; hết kỳ thì lock không còn hiệu lực.

Hệ quả khi thắng tranh chấp:

- Hệ thống bỏ qua random loại mảnh và trao thẳng mảnh còn thiếu để đủ 4.
- Ghi nhận log thắng pity (user_id, artifact_id, period_id, time).

Hệ quả khi thua tranh chấp:

- Hệ thống chỉ cho phép rơi mảnh an toàn (duplicate hoặc mảnh không làm đủ 4).
- Ghi nhận log thua pity để theo dõi tranh chấp.

#### 2.1.2 Vi tri code (pity + tranh chap)

- Lock MySQL (GET_LOCK/RELEASE_LOCK): includes/api/rest-sessions.php
- Check pity + tranh chap lock + gan manh pity: includes/api/rest-sessions.php (ham game_get_random_reward)
- Nhanh thua lock => safe-piece: includes/api/rest-sessions.php (doan need_safe)
- Xac dinh du 3 manh va tra manh con thieu: includes/helpers/artifact-period.php (ham game_check_pity)

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