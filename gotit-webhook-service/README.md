# GotIt Standalone Webhook Service

Service nhận thông báo thay đổi trạng thái voucher từ GotIt, chạy **độc lập với WordPress** trên port riêng để đảm bảo cô lập bảo mật.

---

## Kiến trúc

```
[GotIt Partner]
      │  POST https://your-domain.com/gotit-webhook
      ▼
[Web Server :443]  ──proxy──►  [Internal :8081]
                                      │
                          gotit-webhook-service/
                                  index.php
                                      │
                          ┌───────────┴───────────┐
                   Đọc config từ WP          Xác thực chữ ký
                   (wp-config.php +          (SHA256 / RSA)
                    wp_options)                    │
                                           Cập nhật DB (PDO)
                                           wp_game_gotit_transactions
                                           wp_game_gotit_webhook_logs
```

---

## Cấu hình – Tự động, không cần sửa tay

`config.php` tự động đọc toàn bộ thông số từ WordPress:

| Thông số | Nguồn |
|---|---|
| DB Host / Name / User / Password | `wp-config.php` |
| Table prefix | `wp-config.php` → `$table_prefix` |
| Webhook Secret (SHA256) | `wp_options` → `game_bsc_gotit_webhook_secret` |
| Public Key RSA (fallback) | `wp_options` → `game_bsc_gotit_public_key` |

> **Không cần chỉnh sửa bất kỳ file nào trước khi deploy.**  
> Mọi giá trị lấy thẳng từ cài đặt plugin đang có.

---

## File trong thư mục này

| File | Mô tả |
|---|---|
| `index.php` | Entry point – toàn bộ logic xử lý webhook |
| `config.php` | Tự động load config từ WordPress |
| `.htaccess` | Chặn truy cập HTTP vào file nhạy cảm (Apache) |
| `nginx-vhost.conf` | Config mẫu cho Nginx |
| `apache-vhost.conf` | Config mẫu cho Apache / Laragon |
| `logs/` | Thư mục chứa log debug (chỉ khi bật `DEBUG_MODE`) |

---

## Hướng dẫn Deploy

### Bước 1 – Gửi thông tin cho server team

Cung cấp cho đội quản trị server hai thông tin sau:

```
Port    : 8081
Thư mục : /đường-dẫn-web/wp-content/plugins/game-bsc/gotit-webhook-service/
```

File config mẫu sẵn có:
- **Nginx** → `nginx-vhost.conf`
- **Apache / Laragon** → `apache-vhost.conf`

Server team cần:
1. Tạo VirtualHost lắng nghe port `8081`, trỏ document root vào thư mục trên
2. *(Tùy chọn)* Thêm proxy location `/gotit-webhook` từ port `443` vào `127.0.0.1:8081`

---


---

### Bước 2 – Gửi URL mới cho GotIt

| Phương án | URL gửi cho GotIt |
|---|---|
| Proxy qua 443 *(khuyến nghị)* | `https://your-domain.com/gotit-webhook` |
| Expose port trực tiếp | `https://your-domain.com:8081/` |

---

## Kiểm tra hoạt động

```bash
# Phải trả về 405 Method Not Allowed (GET không hợp lệ)
curl -i http://localhost:8081/

# File nhạy cảm phải bị chặn (403 Forbidden)
curl -I http://localhost:8081/config.php
curl -I http://localhost:8081/logs/webhook_debug.log
```

| Request | Kết quả mong đợi |
|---|---|
| `GET /` | `405 Method Not Allowed` |
| `POST /` – payload + sign hợp lệ | `200 {"success": true, ...}` |
| `POST /` – sign sai | `401 Unauthorized` |
| `GET /config.php` | `403 Forbidden` |
| `GET /logs/...` | `403 Forbidden` |

---

## Bảo mật nâng cao (Khuyến nghị)


### Database User riêng

Tạo user MySQL chỉ có quyền tối thiểu cho service này:

```sql
CREATE USER 'gotit_webhook'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, UPDATE ON your_db.wp_game_gotit_transactions  TO 'gotit_webhook'@'localhost';
GRANT INSERT         ON your_db.wp_game_gotit_webhook_logs  TO 'gotit_webhook'@'localhost';
FLUSH PRIVILEGES;
```

Sau đó cập nhật thông tin user trong `wp-config.php` của service *(hoặc override bằng biến môi trường)*.

