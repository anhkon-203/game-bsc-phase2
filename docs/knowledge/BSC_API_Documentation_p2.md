# TÀI LIỆU API

**BSC Trade API — Phiên bản tích hợp đối tác**

---

## MỤC LỤC

- [API 1 — Thông tin tài khoản](#api-1--thông-tin-tài-khoản)
- [API 2 — Danh sách Voucher đã đăng ký](#api-2--danh-sách-voucher-đã-đăng-ký)
- [API 3 — SSO (Cách chặn đăng nhập tài khoản nước ngoài)](#api-3--cách-chặn-đăng-nhập-tài-khoản-nước-ngoài)

---

## API 1 — Thông tin tài khoản

**`GET`** `https://<trading_server>/trade/accounts`

Trả về danh sách tài khoản chứng khoán của khách hàng.

### Cấu trúc Response

| **Trường** | **Kiểu dữ liệu** | **Mô tả** |
|------------|------------------|-----------|
| s          | string           | Trạng thái phản hồi (`"ok"` nếu thành công) |
| ec         | string           | Mã lỗi (`"0"` nếu không có lỗi) |
| em         | string \| null   | Thông điệp lỗi (null nếu thành công) |
| d          | array            | Mảng danh sách tài khoản |

### Chi tiết các trường (`d[]`)

| **Trường**       | **Kiểu dữ liệu** | **Mô tả** |
|------------------|------------------|-----------|
| cftype           | string           | Loại hình tài khoản của tiểu khoản |
| custodycd        | string           | Số tài khoản |
| id               | string           | Id của tiểu khoản, dùng để truyền vào biến `accountId` khi gọi OpenApi |
| name             | string           | Họ và tên khách hàng |
| typename         | string           | Tên loại tài khoản (tiếng Việt) |
| entypename       | string           | Tên loại tài khoản (tiếng Anh) |
| accounttype      | string           | Loại tài khoản (`"SEC"` — chứng khoán, `"FDS"` — Phái sinh) |
| acctno           | string           | Số tiểu khoản |
| producttype      | string \| null   | Loại sản phẩm |
| producttypename  | string \| null   | Tên loại sản phẩm |
| afacctnoext      | string \| null   | Số tài khoản mở rộng |
| mrtype           | string           | Loại ký quỹ (`"N"`: thường) |

### Ví dụ Response

```json
{
  "s": "ok",
  "ec": "0",
  "em": null,
  "d": [
    {
      "cftype": "1",
      "custodycd": "002C036985",
      "id": "0101067396",
      "name": "Nguyễn Thanh Binh",
      "corebank": "N",
      "typename": "Thường",
      "entypename": "Thường",
      "alternateacct": "N",
      "accounttype": "SEC",
      "acctno": "0101067396",
      "producttype": null,
      "producttypename": null,
      "afacctnoext": null,
      "mrtype": "N"
    }
  ]
}
```

---

## API 2 — Danh sách Voucher đã đăng ký

**`GET`** `https://<trading_server>/report/registeredVoucherList`

Trả về danh sách voucher ưu đãi đã được đăng ký cho khách hàng.

### Cấu trúc Response

| **Trường** | **Kiểu dữ liệu** | **Mô tả** |
|------------|------------------|-----------|
| s          | string           | Trạng thái phản hồi (`"ok"` nếu thành công) |
| ec         | number           | Mã lỗi (0 nếu không có lỗi) |
| em         | string           | Thông điệp lỗi |
| d          | array            | Mảng danh sách voucher |

### Chi tiết đối tượng voucher (`d[]`)

| **Trường**    | **Kiểu dữ liệu** | **Mô tả** |
|---------------|------------------|-----------|
| custodycd     | string           | Số tài khoản |
| fullname      | string           | Họ và tên khách hàng |
| afacctno      | string           | Số tiểu khoản khách hàng đang được gán voucher |
| voucherid     | string           | Id của Voucher |
| vouchername   | string           | Tên voucher |
| voucheramt    | number           | Tổng giá trị ưu đãi của Voucher (VND) |
| prinpaid      | number           | Số tiền đã sử dụng (mới tạo là 0) |
| reamt         | number           | Giá trị còn lại của voucher |
| valdate       | string           | Ngày bắt đầu hiệu lực (dd/MM/yyyy) |
| expdate       | string           | Ngày hết hạn (dd/MM/yyyy) |
| refullname    | string           | Họ tên nhân viên tham chiếu |
| regroupname   | string           | Tên nhóm/phòng ban quản lý |
| makeuser      | string           | Người tạo voucher |
| checkuser     | string           | Người duyệt voucher |
| status        | string           | Trạng thái (vd: `"Hoat dong"`) |
| status_en     | string           | Trạng thái tiếng Anh (vd: `"Active"`) |
| ciamt         | number           | Giá trị tiền mặc định (thường là 0) |
| vcrate        | number           | Tỉ lệ hưởng ưu đãi (thường là 100 = 100%) |

### Mapping cột Database ↔ Trường API

| **Cột Database** | **Trường API** | **Mô tả** |
|------------------|----------------|-----------|
| TXDATE           | —              | Ngày giao dịch |
| CUSTODYCD        | custodycd      | Số lưu ký khách hàng |
| CUSTID           | —              | Mã khách hàng |
| AFACCTNO         | afacctno       | Số tiểu khoản khách hàng |
| VOUCHERID        | voucherid      | Mã loại Voucher (Template) |
| CIAMT            | ciamt          | Giá trị tiền (mặc định 0) |
| VOUCHERAMT       | voucheramt     | Tổng giá trị ưu đãi |
| PRINPAID         | prinpaid       | Số tiền đã sử dụng |
| OPENDATE         | —              | Ngày mở voucher |
| VALDATE          | valdate        | Ngày bắt đầu hiệu lực |
| EXPDATE          | expdate        | Ngày hết hạn |
| VALDAY           | —              | Số ngày hiệu lực |
| ISBUYVC          | —              | Voucher cho lệnh mua (1: Có, 0: Không) |
| VCRATE           | vcrate         | Tỉ lệ hưởng (%) |
| STATUS           | status         | Trạng thái (A: Hoat dong) |
| TLID             | makeuser       | ID nhân viên tạo |
| OFFID            | checkuser      | ID nhân viên duyệt |

### Ví dụ Response

```json
{
  "s": "ok",
  "ec": 0,
  "em": "",
  "d": [
    {
      "custodycd": "002C036985",
      "fullname": "Nguyen Thanh Binh",
      "afacctno": "0101067396",
      "voucherid": "0008",
      "vouchername": "Voucher Qua tang sinh nhat",
      "voucheramt": 200000,
      "prinpaid": 0,
      "reamt": 200000,
      "valdate": "20/10/2025",
      "expdate": "03/08/2026",
      "refullname": "Tran Dai Thanh",
      "regroupname": "GiaLai - DHT - HCM",
      "makeuser": "RootUser",
      "checkuser": "RootUser",
      "status": "Hoat dong",
      "status_en": "Active",
      "ciamt": 0,
      "vcrate": 100
    }
  ]
}
```

---

## API 3 — Cách chặn đăng nhập tài khoản nước ngoài

Khi thực hiện gọi API lấy `access_token`, hệ thống trả về một chuỗi có định dạng:

```
tài_khoản | chuỗi_token
```

Tại bước xử lý này, cần thực hiện tách và kiểm tra thông tin **tài khoản** trong chuỗi:

- Nếu tài khoản có prefix **`002C`** → cho phép tiếp tục truy cập.
- Nếu tài khoản có prefix **`002F`** → thực hiện chặn truy cập.

### Lưu ý

- Phạm vi chặn chỉ áp dụng đối với chức năng **Gamification**.
- Không ảnh hưởng đến các chức năng đăng nhập và sử dụng khác trên website.
