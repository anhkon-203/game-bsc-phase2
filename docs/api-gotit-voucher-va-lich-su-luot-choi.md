# API Voucher Got It và API Lịch sử lượt chơi (dữ liệu thực tế)

- Ngày cập nhật: 2026-04-07
- Môi trường lấy mẫu: local `http://bsc-game.test`
- Base URL: `http://bsc-game.test/wp-json`
- Namespace đang chạy thực tế: `game-bsc`

## 0) Cấu trúc response chung
Tất cả API trong tài liệu này dùng envelope chuẩn:

```json
{
  "resCode": 200,
  "data": {},
  "message": "..."
}
```

Giải thích:
- `resCode`: mã kết quả theo nghiệp vụ (200 thành công, 4xx/5xx là lỗi).
- `data`: dữ liệu chính của API.
- `message`: thông điệp trả về.

---

## 1) Nhóm API liên quan đến Voucher Got It

## 1.1 Lấy danh mục voucher
### Mô tả API
Lấy danh sách danh mục voucher (taxonomy `game_voucher_category`) kèm logo danh mục.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/voucher-categories`

### Input
Không có query param bắt buộc.

### Response thực tế (rút gọn)
```json
{
  "resCode": 200,
  "data": [
    {
      "id": 114,
      "name": "[autotest_2023]_active_103",
      "logo": ""
    },
    {
      "id": 113,
      "name": "[autotest_2023]_active_98",
      "logo": "https://img-stg.gotit.vn/category/1696407393_kZFTU.png"
    },
    {
      "id": 96,
      "name": "Nhà Hàng",
      "logo": "https://img-stg.gotit.vn/category_version/image/bbb0dd71fcfa17e6e6e353b70fc164c3.png"
    }
  ],
  "message": "Lấy danh sách danh mục voucher thành công."
}
```

### Giải thích từng trường response
- `data[].id`: ID term danh mục trong WordPress.
- `data[].name`: tên danh mục.
- `data[].logo`: URL logo danh mục (có thể rỗng nếu chưa có).

---

## 1.2 Lấy danh sách voucher Got It
### Mô tả API
Lấy danh sách voucher loại `THIRD_PARTY` (Got It), có hỗ trợ phân trang và lọc theo danh mục.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/gotit-vouchers`

### Input
- Query `page` (không bắt buộc, mặc định `1`).
- Query `per_page` (không bắt buộc, mặc định `20`, tối đa `100`).
- Query `category_id` (không bắt buộc, lọc theo term ID của `game_voucher_category`).

### Response thực tế
```json
{
  "resCode": 200,
  "data": {
    "vouchers": [
      {
        "id": 1837,
        "title": "AutoTest_Shopee_Convert Code - 100k",
        "code": "GOTIT-13648-34073",
        "type": "THIRD_PARTY",
        "voucher_display_name": "AutoTest_Shopee_Convert Code",
        "voucher_image_url": "https://img-stg.gotit.vn/compress/580x580/2023/06/1688099375_RNVa0.png",
        "voucher_selected_value": "100000",
        "points_cost": 100000
      },
      {
        "id": 1838,
        "title": "AutoTest_Shopee_Convert Code - 1",
        "code": "GOTIT-13648-43157",
        "type": "THIRD_PARTY",
        "voucher_display_name": "AutoTest_Shopee_Convert Code",
        "voucher_image_url": "https://img-stg.gotit.vn/compress/580x580/2023/06/1688099375_RNVa0.png",
        "voucher_selected_value": "150000",
        "points_cost": 150000
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 2,
      "total_items": 302,
      "total_pages": 151,
      "has_next": true,
      "has_prev": false
    }
  },
  "message": "Lấy danh sách voucher Got It thành công."
}
```

### Giải thích từng trường response
- `data.vouchers[].id`: post ID voucher trong WordPress.
- `data.vouchers[].title`: tiêu đề voucher.
- `data.vouchers[].code`: mã nội bộ voucher Got It.
- `data.vouchers[].type`: loại voucher (`THIRD_PARTY`).
- `data.vouchers[].voucher_display_name`: tên hiển thị cho frontend.
- `data.vouchers[].voucher_image_url`: ảnh voucher.
- `data.vouchers[].voucher_selected_value`: mệnh giá được map.
- `data.vouchers[].points_cost`: số điểm cần đổi.
- `data.pagination`: thông tin phân trang.

---

## 1.3 Lấy chi tiết voucher Got It
### Mô tả API
Lấy chi tiết voucher theo `voucher_id`, gồm điều khoản, cửa hàng áp dụng, thương hiệu và danh sách mệnh giá.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/voucher-detail`

### Input
- Query `voucher_id` (bắt buộc, số nguyên dương).

### Response thực tế (rút gọn)
```json
{
  "resCode": 200,
  "data": {
    "gotit_product_id": 13648,
    "terms_and_conditions": {
      "terms": "<p>- Phiếu quà tặng điện tử được cung cấp bởi Got It...</p>",
      "service_guide": ""
    },
    "applicable_stores": [
      {
        "id": 3580,
        "name": "Store Shopee",
        "address": "hồ chí minh",
        "email": null,
        "phone": null,
        "lat": 0,
        "long": 0,
        "districtId": 807,
        "districtName": "Quận Dương Kinh",
        "cityId": 16,
        "cityName": "Hải Phòng",
        "extraFields": []
      }
    ],
    "brand_info": {
      "name": "Shopee",
      "url": "https://v-stg.gotit.vn/mua-sam/shopee/autotest-shopee-convert-code-13648.html",
      "logo_url": "https://img-stg.gotit.vn/compress/brand/2021/10/1634550062_cMQ1q.png"
    },
    "denomination": [
      {
        "voucher_id": 1835,
        "gotit_product_price_id": 34074,
        "label": "30000",
        "value": 30000,
        "points_cost": 30000,
        "is_current_voucher": false
      },
      {
        "voucher_id": 1837,
        "gotit_product_price_id": 34073,
        "label": "100000",
        "value": 100000,
        "points_cost": 100000,
        "is_current_voucher": true
      }
    ]
  },
  "message": "Lấy chi tiết voucher thành công."
}
```

### Giải thích từng trường response
- `data.gotit_product_id`: ID sản phẩm gốc từ Got It.
- `data.terms_and_conditions.terms`: điều khoản sử dụng (HTML).
- `data.terms_and_conditions.service_guide`: hướng dẫn sử dụng (HTML).
- `data.applicable_stores[]`: danh sách cửa hàng áp dụng.
- `data.brand_info`: thông tin thương hiệu.
- `data.denomination[]`: danh sách mệnh giá có thể đổi cho cùng sản phẩm.
- `data.denomination[].is_current_voucher`: đánh dấu mệnh giá đang ứng với `voucher_id` truyền vào.

---

## 1.4 Tra cứu voucher theo transaction_ref_id
### Mô tả API
Lấy thông tin voucher đã issue theo transaction ref, gồm cả trạng thái đã sử dụng hay chưa.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/gotit-voucher-by-transaction`

### Input
- Query `transaction_ref_id` (bắt buộc).

### Response thực tế (rút gọn)
```json
{
  "resCode": 200,
  "data": {
    "transaction_ref_id": "000578_20260407093500_1_1837_6760",
    "voucher_info": {
      "voucher_id": 1837,
      "title": "AutoTest_Shopee_Convert Code - 100k",
      "voucher_code": "SPEAUTOTESTRLRREELTRESET",
      "voucher_link": "https://v-stg.gotit.vn/1dStF4pf",
      "voucher_image": "https://img-stg.gotit.vn/compress/580x580/2023/06/1688099375_RNVa0.png",
      "serial": "V87ZAPHA2ZG",
      "brand_info": {
        "name": "Shopee",
        "url": "https://v-stg.gotit.vn/mua-sam/shopee/autotest-shopee-convert-code-13648.html",
        "logo_url": "https://img-stg.gotit.vn/compress/brand/2021/10/1634550062_cMQ1q.png"
      }
    },
    "barcode": "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciLi4u",
    "expiry_date": "2026-05-07 00:00:00",
    "terms_and_conditions": {
      "terms": "<p>- Phiếu quà tặng điện tử được cung cấp bởi Got It...</p>",
      "service_guide": ""
    },
    "is_used": true
  },
  "message": "Lấy thông tin voucher Got It thành công."
}
```

### Giải thích từng trường response
- `data.transaction_ref_id`: mã giao dịch issue voucher.
- `data.voucher_info`: thông tin voucher đã phát hành.
- `data.barcode`: barcode chuẩn Code 128, sinh từ `voucher_code` để hiển thị/scan.
- `data.expiry_date`: hạn sử dụng voucher.
- `data.terms_and_conditions`: điều khoản và hướng dẫn sử dụng.
- `data.is_used`: trạng thái đã sử dụng hay chưa.

---

## 1.5 Lịch sử đổi voucher của user
### Mô tả API
Lấy lịch sử đổi voucher của user hiện tại (gồm cả voucher Got It và voucher khác), dữ liệu được nhóm theo ngày.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/user/voucher-redemptions`

### Input
- Query `page` (không bắt buộc, mặc định `1`).
- Query `per_page` (không bắt buộc, mặc định `5`, tối đa `30`, đơn vị là số ngày).

### Response thực tế (rút gọn)
```json
{
  "resCode": 200,
  "data": {
    "page": 1,
    "per_page": 2,
    "total_days": 21,
    "groups": [
      {
        "date": "2026-04-07",
        "total_points_used": 180000,
        "count": 3,
        "entries": [
          {
            "id": 195,
            "delta": -100000,
            "points_used": 100000,
            "voucher_redemption_id": 71,
            "voucher_post_id": 1837,
            "voucher_name": "AutoTest_Shopee_Convert Code - 100k",
            "redeemed_at": "2026-04-07 16:35:00",
            "created_at": "2026-04-07 16:35:00",
            "qty": 1
          }
        ]
      }
    ]
  },
  "message": "Lấy lịch sử đổi voucher thành công."
}
```

### Giải thích từng trường response
- `data.total_days`: tổng số ngày có phát sinh đổi voucher.
- `data.groups[]`: danh sách nhóm dữ liệu theo ngày.
- `data.groups[].total_points_used`: tổng điểm đã dùng trong ngày.
- `data.groups[].entries[]`: chi tiết từng giao dịch đổi voucher.

---

## 1.6 API issue voucher (kiểm tra runtime)
### Mô tả API
Theo mã nguồn, endpoint issue voucher đang được đăng ký là:
- `POST /wp-json/game-bsc/vouchers/issue`

### Kết quả gọi thực tế ngày 2026-04-07
- Gọi `POST /wp-json/game-bsc/vouchers/issue` với body `{"voucher_id":999999}`.
- Kết quả HTTP: `404`.
- Body response: rỗng.

### Ghi chú
Endpoint này hiện chưa trả dữ liệu trên runtime local đang test. Nếu cần dùng ở môi trường khác, nên kiểm tra lại route registration thực tế của môi trường deploy.

---

## 2) Nhóm API Lịch sử lượt chơi

## 2.1 Lịch sử phiên chơi
### Mô tả API
Lấy danh sách phiên chơi của user hiện tại, có phân trang.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/play-session-history`

### Input
- Query `page` (không bắt buộc, mặc định `1`).
- Query `per_page` (không bắt buộc, mặc định `5`, tối đa `100`).

### Response thực tế
```json
{
  "resCode": 200,
  "data": {
    "user": {
      "id": 1,
      "name": "Trieu Ngoc Tai",
      "avatar_url": "https://secure.gravatar.com/avatar/..."
    },
    "sessions": [
      {
        "session_id": 440,
        "started_at": "2026-02-04 16:19:45",
        "finished_at": "2026-02-04 16:19:53",
        "correct": 1,
        "total": 2,
        "score": "1/2",
        "points": 0,
        "pieces": [
          {
            "artifact_id": 1,
            "artifact_name": "Iphone 17 Pro 256GB",
            "piece_id": 2,
            "piece_code": "P2",
            "qty": 1
          }
        ]
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 2,
      "total_items": "336",
      "total_pages": 168,
      "has_next": true,
      "has_prev": false
    }
  },
  "message": "Lấy lịch sử chơi game thành công."
}
```

### Giải thích từng trường response
- `data.user`: thông tin user đang xem lịch sử.
- `data.sessions[]`: danh sách phiên chơi.
- `data.sessions[].score`: chuỗi tổng hợp `correct/total`.
- `data.sessions[].pieces[]`: danh sách mảnh ghép nhận được trong phiên.
- `data.pagination`: thông tin phân trang.

---

## 2.2 Chi tiết câu hỏi trong một phiên
### Mô tả API
Lấy toàn bộ lịch sử câu hỏi của một phiên chơi, gồm lựa chọn đáp án, đáp án đúng và đáp án user đã chọn.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/play-session/{session_id}/questions`

### Input
- Path `session_id` (bắt buộc, số nguyên dương).

### Response thực tế
```json
{
  "resCode": 200,
  "data": {
    "session": {
      "session_id": 440,
      "user": {
        "id": 1
      }
    },
    "questions_history": [
      {
        "order": 1,
        "question": "Mở tài khoản chứng khoán để làm gì?",
        "options": [
          {"value": 1, "valueCode": "A", "content": "Gửi tiết kiệm"},
          {"value": 2, "valueCode": "B", "content": "Giao dịch chứng khoán"}
        ],
        "correct_answer": "B",
        "user_answer": "A",
        "is_correct": false
      }
    ]
  },
  "message": "Lấy chi tiết câu hỏi thành công."
}
```

### Giải thích từng trường response
- `data.session.session_id`: ID phiên chơi.
- `data.questions_history[].order`: thứ tự câu hỏi trong phiên.
- `data.questions_history[].options[]`: danh sách đáp án để chọn.
- `data.questions_history[].correct_answer`: đáp án đúng.
- `data.questions_history[].user_answer`: đáp án user đã chọn.
- `data.questions_history[].is_correct`: kết quả đúng/sai.

---

## 2.3 Lịch sử biến động lượt chơi
### Mô tả API
Lấy lịch sử cộng/trừ lượt chơi của user, bao gồm tổng kết và chi tiết từng biến động.

### Method
`GET`

### API URL + Endpoint
`/wp-json/game-bsc/play-credit-history`

### Input
- Query `page` (không bắt buộc, mặc định `1`).
- Query `per_page` (không bắt buộc, mặc định `20`, tối đa `100`).

### Response thực tế
```json
{
  "resCode": 200,
  "data": {
    "user": {
      "id": 1,
      "name": "Trieu Ngoc Tai",
      "avatar_url": "https://secure.gravatar.com/avatar/..."
    },
    "summary": {
      "total_received": 200,
      "total_played": 422,
      "total_remaining": 100
    },
    "history": [
      {
        "id": 535,
        "type": "credit",
        "delta": 2,
        "delta_display": "+2",
        "ref_type": "MISSION",
        "ref_id": 103,
        "reason": "Đăng nhập hàng ngày",
        "created_at": "2026-03-09 11:02:53",
        "created_at_display": "09/03/2026 11:02:53"
      },
      {
        "id": 534,
        "type": "credit",
        "delta": 2,
        "delta_display": "+2",
        "ref_type": "MISSION",
        "ref_id": 102,
        "reason": "Đăng nhập hàng ngày",
        "created_at": "2026-03-08 00:42:01",
        "created_at_display": "08/03/2026 00:42:01"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 2,
      "total_items": 523,
      "total_pages": 262,
      "has_next": true,
      "has_prev": false
    }
  },
  "message": "Lấy lịch sử biến động lượt chơi thành công."
}
```

### Giải thích từng trường response
- `data.summary.total_received`: tổng lượt được cộng.
- `data.summary.total_played`: tổng lượt đã dùng.
- `data.summary.total_remaining`: lượt còn lại hiện tại.
- `data.history[].type`: `credit` (cộng) hoặc `debit` (trừ).
- `data.history[].delta`: giá trị biến động thực.
- `data.history[].ref_type`: nguồn phát sinh (`MISSION`, `SESSION`, ...).
- `data.history[].reason`: mô tả lý do hiển thị cho user.
- `data.pagination`: thông tin phân trang.

---

## 3) Ghi chú kiểm thử dữ liệu thực tế
- Toàn bộ mẫu JSON trong tài liệu này được lấy trực tiếp từ API local ngày 2026-04-07.
- Dữ liệu có tính thời điểm, có thể thay đổi theo dữ liệu DB và tài khoản đăng nhập.
- Các trường text quá dài (ví dụ `terms` HTML) đã được rút gọn trong tài liệu để dễ đọc nhưng vẫn giữ đúng cấu trúc field.