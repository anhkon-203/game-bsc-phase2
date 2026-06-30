# Got It Standalone Webhook Service

Service này được thiết kế độc lập hoàn toàn với lõi WordPress (không nạp WordPress) để tăng tính bảo mật, giảm độ trễ (latency) và đảm bảo tính liên tục của hệ thống đổi voucher.

---

## Hướng dẫn cài đặt và cấu hình

### 1. Cấu hình kết nối
Mở tệp `config.php` trong thư mục này và cập nhật các thông số sau:
- **Database:** Thông số kết nối CSDL MySQL (Host, DB Name, User, Password, Table Prefix).
- **Got It Secrets:** Điền `GOTIT_WEBHOOK_SECRET` và `GOTIT_PUBLIC_KEY` (nếu dùng RSA).
- **IP Whitelist (Khuyến nghị):**
  - Đổi `ENABLE_IP_WHITELIST` thành `true`.
  - Thêm các địa chỉ IP của máy chủ Got It cung cấp vào mảng `ALLOWED_IPS`.

---

### 2. Cấu hình Nginx (Khuyến nghị)
Để service lắng nghe ở một cổng riêng (ví dụ: `8081`) và ẩn khỏi internet trực tiếp, cấu hình block Nginx Virtual Host sau trên máy chủ của bạn:

```nginx
# 1. Cấu hình Service nội bộ ở cổng 8081
server {
    listen 127.0.0.1:8081; # Chỉ lắng nghe nội bộ từ localhost
    server_name localhost;
    root /var/www/bsc-game/wp-content/plugins/game-bsc/gotit-webhook-service;
    index index.php;

    access_log off;
    error_log /var/log/nginx/gotit_webhook_error.log warn;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Thay đổi theo phiên bản PHP FPM của bạn
    }

    # Không cho phép truy cập trực tiếp file log hoặc config qua HTTP
    location ~* \.(log|ini|conf|php)$ {
        if ($uri ~* "index.php") {
            break;
        }
        deny all;
    }
}

# 2. Proxy request HTTPS bên ngoài vào service nội bộ
# Thêm cấu hình này vào block server HTTPS chính (cổng 443) của website của bạn
server {
    listen 443 ssl http2;
    server_name bsc-game.vn; # Tên miền website của bạn

    # ... các cấu hình SSL và website khác ...

    # Hướng route /gotit-webhook-service/ về port 8081
    location /gotit-webhook-service/ {
        # Chỉ cho phép IP của Got It truy cập ở tầng Nginx (tùy chọn)
        # allow 118.69.x.x; # IP Got It
        # deny all;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        proxy_pass http://127.0.0.1:8081/;
    }
}
```

---

### 3. Cấu hình Apache
Nếu hệ thống của bạn sử dụng Apache thay vì Nginx, bạn cũng có thể mở một port riêng bằng cách thêm cấu hình sau vào tệp cấu hình Apache (ví dụ `httpd.conf` hoặc `apache2.conf`):

```apache
# Mở port 8081
Listen 8081

<VirtualHost *:8081>
    ServerName localhost
    DocumentRoot /var/www/bsc-game/wp-content/plugins/game-bsc/gotit-webhook-service

    <Directory /var/www/bsc-game/wp-content/plugins/game-bsc/gotit-webhook-service>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Đồng thời, bạn cũng nên tạo tệp `.htaccess` trong thư mục `gotit-webhook-service` để chặn truy cập trực tiếp vào các tệp nhạy cảm (nếu chưa cấu hình ở cấp máy chủ):

```apache
<FilesMatch "\.(config|log|ini)$">
    Require all denied
</FilesMatch>
```

---

## Bảo mật bổ sung
- **Database User giới hạn:** Khuyến nghị tạo một tài khoản MySQL riêng chỉ có quyền `SELECT` và `UPDATE` trên bảng `wp_game_gotit_transactions` và `INSERT` trên bảng `wp_game_gotit_webhook_logs`.
- **Logs:** Tệp log debug sẽ được lưu tại `gotit-webhook-service/webhook_debug.log`. Hãy đảm bảo phân quyền file để bên ngoài không thể đọc trực tiếp (Nginx config phía trên đã chặn).
